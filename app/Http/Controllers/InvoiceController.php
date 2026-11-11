<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProofRequest;
use App\Http\Requests\RejectInvoiceRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\SubmitPaymentRequest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    private const OWN_INVOICE_MESSAGE = 'Kamu tidak bisa melunasi atau membatalkan pelunasan tagihanmu sendiri. Minta pengelola lain (Admin atau Editor) untuk memverifikasinya.';

    private const OWN_VERIFY_MESSAGE = 'Kamu tidak bisa menyetujui atau menolak pengajuan pembayaranmu sendiri. Minta pengelola lain (Admin atau Editor) untuk memeriksanya.';

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

    // ---------------------------------------------------------------
    // Pengelola (Admin/Editor): lunas tunai, batalkan, setujui, tolak
    // ---------------------------------------------------------------

    public function payCash(Request $request, Organization $organization, int $invoice): RedirectResponse
    {
        Gate::authorize('manageInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);

        // E2: tak seorang pun melunasi tagihannya sendiri
        if ($this->isOwn($request, $found)) {
            return $this->refuse($organization, self::OWN_INVOICE_MESSAGE, $found->period);
        }

        $paid = Invoice::settle(
            $found->id,
            $organization,
            $request->user(),
            Invoice::METHOD_TUNAI,
            [Invoice::STATUS_BELUM, Invoice::STATUS_MENUNGGU],
        );

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

        $result = DB::transaction(function () use ($found) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if (! $locked->isPaid()) {
                return null;
            }

            $oldProof = $locked->proof_path;

            // Dihapus permanen: transactions.invoice_id unik, sehingga transaksi yang hanya
            // di-soft-delete akan menghalangi pelunasan ulang. Jejaknya ada di riwayat aktivitas.
            Transaction::withTrashed()->where('invoice_id', $locked->id)->get()->each->forceDelete();

            $locked->update([
                'status' => Invoice::STATUS_BELUM,
                'method' => null,
                'paid_at' => null,
                'rejection_reason' => null,
                'proof_path' => null, // E3: bukti lama dibuang, anggota mengajukan ulang bila perlu
            ]);

            return [$locked, $oldProof];
        });

        if ($result === null) {
            return $this->refuse($organization, 'Hanya tagihan yang sudah lunas yang bisa dibatalkan pelunasannya.', $found->period);
        }

        [$reverted, $oldProof] = $result;
        $this->deleteProof($oldProof);

        ActivityLogger::record($organization, $request->user(), 'invoice.payment_cancelled', $reverted, [
            'period' => $reverted->period,
            'amount' => $reverted->amount,
            'member' => $found->member->name,
        ]);

        return $this->done($organization, "Pelunasan iuran {$found->member->name} ({$reverted->periodLabel()}) dibatalkan.", $reverted->period);
    }

    public function approve(Request $request, Organization $organization, int $invoice): RedirectResponse
    {
        Gate::authorize('manageInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);

        // Aturan tagihan sendiri berlaku juga untuk menyetujui
        if ($this->isOwn($request, $found)) {
            return $this->refuse($organization, self::OWN_VERIFY_MESSAGE, $found->period);
        }

        // method null = pakai cara bayar yang diajukan anggota (transfer/tunai)
        $paid = Invoice::settle($found->id, $organization, $request->user(), null, [Invoice::STATUS_MENUNGGU]);

        if ($paid === null) {
            return $this->refuse($organization, 'Pengajuan ini sudah tidak menunggu verifikasi.', $found->period);
        }

        ActivityLogger::record($organization, $request->user(), 'invoice.approved', $paid, [
            'period' => $paid->period,
            'amount' => $paid->amount,
            'method' => $paid->method,
            'member' => $found->member->name,
        ]);

        return $this->done($organization, "Pengajuan iuran {$found->member->name} ({$paid->periodLabel()}) disetujui dan dicatat lunas.", $paid->period);
    }

    public function reject(RejectInvoiceRequest $request, Organization $organization, int $invoice): RedirectResponse
    {
        $found = $this->findInvoice($organization, $invoice);

        if ($this->isOwn($request, $found)) {
            return $this->refuse($organization, self::OWN_VERIFY_MESSAGE, $found->period);
        }

        $reason = $request->validated('reason');

        $result = DB::transaction(function () use ($found, $reason) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if ($locked->status !== Invoice::STATUS_MENUNGGU) {
                return null;
            }

            $oldProof = $locked->proof_path;

            $locked->update([
                'status' => Invoice::STATUS_BELUM,
                'method' => null,
                'proof_path' => null,
                'paid_at' => null,
                'rejection_reason' => $reason,
            ]);

            return [$locked, $oldProof];
        });

        if ($result === null) {
            return $this->refuse($organization, 'Pengajuan ini sudah tidak menunggu verifikasi.', $found->period);
        }

        [$rejected, $oldProof] = $result;
        $this->deleteProof($oldProof);

        ActivityLogger::record($organization, $request->user(), 'invoice.rejected', $rejected, [
            'period' => $rejected->period,
            'amount' => $rejected->amount,
            'member' => $found->member->name,
            'reason' => $reason,
        ]);

        return $this->done($organization, "Pengajuan iuran {$found->member->name} ({$rejected->periodLabel()}) ditolak. Anggota bisa mengajukan ulang.", $rejected->period);
    }

    // ---------------------------------------------------------------
    // Pemilik tagihan: ajukan, bukti, tarik
    // ---------------------------------------------------------------

    public function submit(SubmitPaymentRequest $request, Organization $organization, int $invoice): RedirectResponse
    {
        $found = $this->findInvoice($organization, $invoice);
        $method = $request->validated('method');

        $file = $request->file('proof');
        $path = $file ? $file->store(Invoice::PROOF_DIR, Invoice::PROOF_DISK) : null;

        $ok = DB::transaction(function () use ($found, $method, $path) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if ($locked->status !== Invoice::STATUS_BELUM) {
                return false;
            }

            $locked->update([
                'status' => Invoice::STATUS_MENUNGGU,
                'method' => $method,
                'proof_path' => $path,
                'rejection_reason' => null,
            ]);

            return true;
        });

        if (! $ok) {
            $this->deleteProof($path);

            return $this->refuse($organization, 'Tagihan ini sudah diajukan atau sudah lunas, jadi tidak bisa diajukan lagi.', $found->period);
        }

        ActivityLogger::record($organization, $request->user(), 'invoice.submitted', $found, [
            'period' => $found->period,
            'amount' => $found->amount,
            'method' => $method,
            'has_proof' => $path !== null,
        ]);

        return $this->done($organization, "Pengajuan pembayaran iuran {$found->periodLabel()} terkirim. Tunggu diperiksa pengelola.", $found->period);
    }

    // Tambah atau ganti bukti (hanya selama menunggu verifikasi)
    public function storeProof(ProofRequest $request, Organization $organization, int $invoice): RedirectResponse
    {
        $found = $this->findInvoice($organization, $invoice);

        $path = $request->file('proof')->store(Invoice::PROOF_DIR, Invoice::PROOF_DISK);

        $result = DB::transaction(function () use ($found, $path) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if ($locked->status !== Invoice::STATUS_MENUNGGU) {
                return null;
            }

            $old = $locked->proof_path;
            $locked->update(['proof_path' => $path]);

            return ['old' => $old];
        });

        if ($result === null) {
            $this->deleteProof($path);

            return $this->refuse($organization, 'Bukti hanya bisa diubah selama pengajuan menunggu verifikasi.', $found->period);
        }

        $this->deleteProof($result['old']);

        ActivityLogger::record($organization, $request->user(), 'invoice.proof_updated', $found, [
            'period' => $found->period,
            'replaced' => $result['old'] !== null,
        ]);

        return $this->done($organization, 'Bukti pembayaran disimpan.', $found->period);
    }

    public function destroyProof(Request $request, Organization $organization, int $invoice): RedirectResponse
    {
        Gate::authorize('viewInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);
        abort_unless($this->isOwn($request, $found), 403);

        $result = DB::transaction(function () use ($found) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if ($locked->status !== Invoice::STATUS_MENUNGGU || $locked->proof_path === null) {
                return null;
            }

            $old = $locked->proof_path;
            $locked->update(['proof_path' => null]);

            return ['old' => $old];
        });

        if ($result === null) {
            return $this->refuse($organization, 'Tidak ada bukti yang bisa dihapus, atau pengajuan sudah tidak menunggu verifikasi.', $found->period);
        }

        $this->deleteProof($result['old']);

        ActivityLogger::record($organization, $request->user(), 'invoice.proof_removed', $found, [
            'period' => $found->period,
        ]);

        return $this->done($organization, 'Bukti pembayaran dihapus. Pengajuanmu tetap menunggu verifikasi (tanpa bukti).', $found->period);
    }

    // Tarik pengajuan: kembali ke "belum dibayar"
    public function withdraw(Request $request, Organization $organization, int $invoice): RedirectResponse
    {
        Gate::authorize('viewInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);
        abort_unless($this->isOwn($request, $found), 403);

        $result = DB::transaction(function () use ($found) {
            $locked = Invoice::whereKey($found->id)->lockForUpdate()->first();

            if ($locked->status !== Invoice::STATUS_MENUNGGU) {
                return null;
            }

            $old = $locked->proof_path;

            $locked->update([
                'status' => Invoice::STATUS_BELUM,
                'method' => null,
                'proof_path' => null,
                'rejection_reason' => null,
            ]);

            return ['old' => $old];
        });

        if ($result === null) {
            return $this->refuse($organization, 'Pengajuan ini sudah tidak menunggu verifikasi, jadi tidak bisa ditarik.', $found->period);
        }

        $this->deleteProof($result['old']);

        ActivityLogger::record($organization, $request->user(), 'invoice.withdrawn', $found, [
            'period' => $found->period,
        ]);

        return $this->done($organization, "Pengajuan iuran {$found->periodLabel()} ditarik. Kamu bisa mengajukan lagi kapan saja.", $found->period);
    }

    // Unduh bukti: hanya pemilik tagihan atau pengelola organisasi ini
    public function proof(Request $request, Organization $organization, int $invoice)
    {
        Gate::authorize('viewInvoices', $organization);

        $found = $this->findInvoice($organization, $invoice);

        abort_unless(
            $this->isOwn($request, $found) || Gate::allows('manageInvoices', $organization),
            403,
        );

        if (! $found->proofAvailable()) {
            return $this->refuse($organization, 'Berkas bukti tidak tersedia.', $found->period);
        }

        $ext = pathinfo($found->proof_path, PATHINFO_EXTENSION);

        return Storage::disk(Invoice::PROOF_DISK)
            ->download($found->proof_path, "bukti-iuran-{$found->period}-{$found->id}.{$ext}");
    }

    // ---------------------------------------------------------------

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

    private function deleteProof(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(Invoice::PROOF_DISK)->delete($path);
        }
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
