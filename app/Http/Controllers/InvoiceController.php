<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    private const OWN_INVOICE_MESSAGE = 'Kamu tidak bisa melunasi atau membatalkan pelunasan tagihanmu sendiri. Minta pengelola lain (Admin atau Editor) untuk memverifikasinya.';

    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('viewInvoices', $organization);

        $filters = $request->validate(
            ['period' => ['nullable', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/']],
            ['period.regex' => 'Pilih bulan dengan format yang benar.'],
        );

        $canManage = Gate::allows('manageInvoices', $organization);
        $base = Invoice::inOrganization($organization);
        $period = null;
        $stats = null;
        $hasOtherManager = false;

        if ($canManage) {
            // Admin/Editor melihat semua tagihan satu periode (bawaan: periode terbaru)
            $period = $filters['period'] ?? Invoice::inOrganization($organization)->max('period');
            if ($period) {
                $base->where('period', $period);
            }

            $rows = (clone $base)
                ->selectRaw('status, COUNT(*) as jumlah, SUM(amount) as nominal')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            $stats = [];
            foreach ([Invoice::STATUS_BELUM, Invoice::STATUS_MENUNGGU, Invoice::STATUS_LUNAS] as $status) {
                $stats[$status] = [
                    'jumlah' => (int) ($rows->get($status)?->jumlah ?? 0),
                    'nominal' => (int) ($rows->get($status)?->nominal ?? 0),
                ];
            }

            // Ada pengelola aktif lain yang bisa memverifikasi tagihan pengelola ini?
            $hasOtherManager = $organization->members()
                ->where('is_active', true)
                ->whereIn('role', [OrganizationMember::ROLE_ADMIN, OrganizationMember::ROLE_EDITOR])
                ->where('user_id', '!=', $request->user()->id)
                ->exists();
        } else {
            // Anggota hanya melihat tagihan miliknya sendiri
            $member = $request->user()->memberships()
                ->where('organization_id', $organization->id)
                ->where('is_active', true)
                ->firstOrFail();
            $base->where('member_id', $member->id);
        }

        $invoices = $base->with('member')
            ->orderByDesc('period')
            ->orderBy('member_id')
            ->paginate(20)
            ->withQueryString();

        return view('invoices.index', [
            'organization' => $organization,
            'invoices' => $invoices,
            'period' => $period,
            'stats' => $stats,
            'canManage' => $canManage,
            'hasOtherManager' => $hasOtherManager,
        ]);
    }

    public function store(StoreInvoiceRequest $request, Organization $organization): RedirectResponse
    {
        $period = $request->validated('period');
        $amount = (int) $request->validated('amount');
        $label = Invoice::labelFor($period);

        $created = DB::transaction(function () use ($organization, $period, $amount) {
            $count = 0;

            $members = $organization->members()
                ->where('is_billable', true)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            foreach ($members as $member) {
                // Tidak dobel: yang sudah punya tagihan periode ini dilewati (jumlahnya tidak diubah)
                $invoice = Invoice::firstOrCreate(
                    ['member_id' => $member->id, 'period' => $period],
                    ['amount' => $amount, 'status' => Invoice::STATUS_BELUM],
                );

                if ($invoice->wasRecentlyCreated) {
                    $count++;
                }
            }

            return $count;
        });

        if ($created === 0) {
            return $this->done($organization, "Semua anggota yang ditagih sudah punya tagihan {$label}.", $period);
        }

        ActivityLogger::record($organization, $request->user(), 'invoice.generated', $organization, [
            'period' => $period,
            'amount' => $amount,
            'created' => $created,
        ]);

        return $this->done($organization, "{$created} tagihan {$label} dibuat.", $period);
    }

    public function payCash(Request $request, Organization $organization, int $invoice): RedirectResponse
    {
        Gate::authorize('manageInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);

        // E2: tak seorang pun melunasi tagihannya sendiri
        if ($this->isOwn($request, $found)) {
            return $this->refuse($organization, self::OWN_INVOICE_MESSAGE, $found->period);
        }

        $paid = DB::transaction(function () use ($organization, $found, $request) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if ($locked->isPaid()) {
                return null;
            }

            $locked->update([
                'status' => Invoice::STATUS_LUNAS,
                'method' => Invoice::METHOD_TUNAI,
                'paid_at' => now(),
                'rejection_reason' => null,
            ]);

            $category = $organization->categories()->firstOrCreate(['name' => 'Iuran', 'is_system' => true]);

            // Keterangan sengaja tanpa nama anggota (D2): semua anggota bisa melihat daftar transaksi
            Transaction::create([
                'organization_id' => $organization->id,
                'category_id' => $category->id,
                'invoice_id' => $locked->id,
                'created_by' => $request->user()->id,
                'type' => 'masuk',
                'amount' => $locked->amount,
                'tanggal' => now()->toDateString(),
                'description' => 'Iuran '.Invoice::labelFor($locked->period),
            ]);

            return $locked;
        });

        if ($paid === null) {
            return $this->refuse($organization, 'Tagihan ini sudah lunas.', $found->period);
        }

        ActivityLogger::record($organization, $request->user(), 'invoice.paid_cash', $paid, [
            'period' => $paid->period,
            'amount' => $paid->amount,
            'member' => $found->member->name,
        ]);

        return $this->done($organization, "Iuran {$found->member->name} ({$paid->periodLabel()}) dicatat lunas tunai.", $paid->period);
    }

    public function cancel(Request $request, Organization $organization, int $invoice): RedirectResponse
    {
        Gate::authorize('manageInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);

        // E2: tak seorang pun membatalkan pelunasan tagihannya sendiri
        if ($this->isOwn($request, $found)) {
            return $this->refuse($organization, self::OWN_INVOICE_MESSAGE, $found->period);
        }

        $reverted = DB::transaction(function () use ($found) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if (! $locked->isPaid()) {
                return null;
            }

            // Dihapus permanen: transactions.invoice_id unik, sehingga transaksi yang hanya
            // di-soft-delete akan menghalangi pelunasan ulang. Jejaknya ada di riwayat aktivitas.
            Transaction::withTrashed()->where('invoice_id', $locked->id)->get()->each->forceDelete();

            $locked->update([
                'status' => Invoice::STATUS_BELUM,
                'method' => null,
                'paid_at' => null,
                'rejection_reason' => null,
            ]);

            return $locked;
        });

        if ($reverted === null) {
            return $this->refuse($organization, 'Hanya tagihan yang sudah lunas yang bisa dibatalkan pelunasannya.', $found->period);
        }

        ActivityLogger::record($organization, $request->user(), 'invoice.payment_cancelled', $reverted, [
            'period' => $reverted->period,
            'amount' => $reverted->amount,
            'member' => $found->member->name,
        ]);

        return $this->done($organization, "Pelunasan iuran {$found->member->name} ({$reverted->periodLabel()}) dibatalkan.", $reverted->period);
    }

    // Tagihan milik organisasi lain = 404
    private function findInvoice(Organization $organization, int $id): Invoice
    {
        return Invoice::inOrganization($organization)->with('member')->findOrFail($id);
    }

    // Kontak tanpa akun (user_id NULL) tidak pernah "milik sendiri"
    private function isOwn(Request $request, Invoice $invoice): bool
    {
        $owner = $invoice->member->user_id;

        return $owner !== null && (int) $owner === (int) $request->user()->id;
    }

    private function done(Organization $organization, string $message, ?string $period = null): RedirectResponse
    {
        return redirect()->route('invoices.index', $this->params($organization, $period))->with('status', $message);
    }

    private function refuse(Organization $organization, string $message, ?string $period = null): RedirectResponse
    {
        return redirect()->route('invoices.index', $this->params($organization, $period))->withErrors(['invoice' => $message]);
    }

    private function params(Organization $organization, ?string $period): array
    {
        $params = ['organization' => $organization];
        if ($period) {
            $params['period'] = $period;
        }

        return $params;
    }
}
