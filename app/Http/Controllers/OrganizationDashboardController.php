<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class OrganizationDashboardController extends Controller
{
    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public function show(Organization $organization)
    {
        Gate::authorize('viewDashboard', $organization);
        // Saldo tidak disimpan: dihitung dari saldo awal + transaksi
        $masuk = $organization->transactions()->where('type', 'masuk')->sum('amount');
        $keluar = $organization->transactions()->where('type', 'keluar')->sum('amount');
        $saldo = $organization->opening_balance + $masuk - $keluar;
        $jumlah = $organization->transactions()->count();

        $terbaru = $organization->transactions()
            ->with('category')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $grafik = $this->grafik($organization);

        // Banner "bukti menunggu verifikasi": hanya untuk pengelola (Admin/Editor).
        $menunggu = Gate::allows('manageInvoices', $organization)
            ? Invoice::inOrganization($organization)
                ->where('invoices.status', Invoice::STATUS_MENUNGGU)
                ->count()
            : 0;

        return view('organizations.dashboard', compact(
            'organization', 'masuk', 'keluar', 'saldo', 'jumlah', 'terbaru', 'grafik', 'menunggu'
        ));
    }

    /**
     * Pemasukan dan pengeluaran per bulan untuk 6 bulan yang berakhir di bulan
     * transaksi terakhir (atau bulan ini bila belum ada transaksi), seperti prototipe.
     * Tinggi batang dalam persen terhadap nilai terbesar.
     */
    private function grafik(Organization $organization): array
    {
        $terakhir = $organization->transactions()->max('tanggal');
        $akhir = ($terakhir ? Carbon::parse($terakhir) : now())->startOfMonth();
        $awal = $akhir->copy()->subMonths(5);

        $bulan = [];
        for ($i = 0; $i < 6; $i++) {
            $d = $awal->copy()->addMonths($i);
            $bulan[$d->format('Y-m')] = [
                'label' => self::BULAN[$d->month - 1],
                'masuk' => 0,
                'keluar' => 0,
            ];
        }

        $baris = $organization->transactions()
            ->whereDate('tanggal', '>=', $awal->toDateString())
            ->whereDate('tanggal', '<=', $akhir->copy()->endOfMonth()->toDateString())
            ->get(['tanggal', 'type', 'amount']);

        foreach ($baris as $t) {
            $k = $t->tanggal->format('Y-m');
            if (isset($bulan[$k])) {
                $bulan[$k][$t->type] += $t->amount;
            }
        }

        $maks = 1;
        foreach ($bulan as $b) {
            $maks = max($maks, $b['masuk'], $b['keluar']);
        }

        return array_values(array_map(function ($b) use ($maks) {
            $b['tinggi_masuk'] = $b['masuk'] > 0 ? max(3, round($b['masuk'] / $maks * 100)) : 0;
            $b['tinggi_keluar'] = $b['keluar'] > 0 ? max(3, round($b['keluar'] / $maks * 100)) : 0;

            return $b;
        }, $bulan));
    }
}
