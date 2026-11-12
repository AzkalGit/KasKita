<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Organization;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('viewReports', $organization);

        $p = $this->period($request);
        $query = $this->inPeriod($organization, $p['start'], $p['end']);

        // Saldo awal periode = saldo awal organisasi + semua transaksi SEBELUM periode
        $before = fn (string $type) => (int) $organization->transactions()
            ->where('type', $type)
            ->whereDate('tanggal', '<', $p['start']->toDateString())
            ->sum('amount');

        $opening = $organization->opening_balance + $before('masuk') - $before('keluar');
        $masuk = (int) (clone $query)->where('type', 'masuk')->sum('amount');
        $keluar = (int) (clone $query)->where('type', 'keluar')->sum('amount');
        $closing = $opening + $masuk - $keluar;

        $names = $organization->categories()->pluck('name', 'id');
        $breakdown = (clone $query)
            ->selectRaw('category_id, type, SUM(amount) as total')
            ->groupBy('category_id', 'type')
            ->get()
            ->groupBy('category_id')
            ->map(fn ($rows, $id) => [
                'name' => $names[$id] ?? 'Tanpa kategori',
                'masuk' => (int) $rows->where('type', 'masuk')->sum('total'),
                'keluar' => (int) $rows->where('type', 'keluar')->sum('total'),
            ])
            ->sortBy('name')
            ->values();

        $transactions = (clone $query)->with('category')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        // Tunggakan hanya untuk Admin. Untuk yang lain datanya TIDAK diambil sama sekali.
        $arrears = null;
        if (Gate::allows('viewArrears', $organization)) {
            $arrears = Invoice::inOrganization($organization)
                ->whereIn('status', [Invoice::STATUS_BELUM, Invoice::STATUS_MENUNGGU])
                ->with('member')
                ->orderBy('period')
                ->orderBy('id')
                ->get();
        }

        // F2: status tutup buku untuk bulan yang dipilih
        $closure = $organization->periodClosures()->with('closer')->where('period', $p['month'])->first();

        return view('reports.index', [
            'organization' => $organization,
            'month' => $p['month'],
            'week' => $p['week'],
            'label' => $p['label'],
            'weeks' => $p['weekLabels'],
            'opening' => $opening,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'closing' => $closing,
            'breakdown' => $breakdown,
            'transactions' => $transactions,
            'arrears' => $arrears,
            'closure' => $closure,
            'canClose' => Gate::allows('closePeriods', $organization),
            'isPastMonth' => $p['month'] < now()->format('Y-m'),
        ]);
    }

    public function download(Request $request, Organization $organization): StreamedResponse
    {
        Gate::authorize('viewReports', $organization);

        $p = $this->period($request);

        $rows = $this->inPeriod($organization, $p['start'], $p['end'])
            ->with('category')
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        ActivityLogger::record($organization, $request->user(), 'report.exported', null, [
            'month' => $p['month'],
            'week' => $p['week'],
            'rows' => $rows->count(),
        ]);

        $filename = 'laporan-'.Str::slug($organization->name).'-'.$p['month']
            .($p['week'] ? '-minggu-'.$p['week'] : '').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 agar Excel membaca huruf dengan benar

            // Parameter ke-5 ('') wajib dikirim eksplisit di PHP 8.4
            fputcsv($out, ['Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Jumlah'], ',', '"', '');

            foreach ($rows as $t) {
                fputcsv($out, [
                    $t->tanggal->format('Y-m-d'),
                    $t->type === 'masuk' ? 'Masuk' : 'Keluar',
                    self::safe((string) $t->category?->name),
                    self::safe((string) $t->description),
                    $t->amount,
                ], ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // Teks berawalan = + - @ (atau tab/CR) diberi tanda petik agar Excel tidak menganggapnya rumus
    private static function safe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function inPeriod(Organization $organization, Carbon $start, Carbon $end)
    {
        // whereDate agar hasilnya sama di MariaDB dan SQLite; transaksi soft-deleted otomatis tidak ikut
        return $organization->transactions()
            ->whereDate('tanggal', '>=', $start->toDateString())
            ->whereDate('tanggal', '<=', $end->toDateString());
    }

    /**
     * Filter bulan (Y-m) dan minggu (nomor, mulai 1). Bulan default = bulan berjalan.
     *
     * @return array{month: string, week: ?int, start: Carbon, end: Carbon, label: string, weekLabels: array<int,string>}
     */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'week' => ['nullable', 'integer', 'min:1'],
        ]);

        $month = $data['month'] ?? now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('!Y-m-d', $month.'-01');
        $weeks = $this->weeksOf($monthStart);

        $weekLabels = [];
        foreach ($weeks as $i => [$s, $e]) {
            $weekLabels[$i + 1] = 'Minggu '.($i + 1).' (tgl '.$s->day.'–'.$e->day.')';
        }

        $week = isset($data['week']) ? (int) $data['week'] : null;

        if ($week !== null && $week > count($weeks)) {
            throw ValidationException::withMessages(['week' => 'Minggu itu tidak ada di bulan yang dipilih.']);
        }

        if ($week === null) {
            $start = $monthStart->copy();
            $end = $monthStart->copy()->endOfMonth()->startOfDay();
            $label = Invoice::labelFor($month);
        } else {
            [$start, $end] = $weeks[$week - 1];
            $label = $weekLabels[$week].' · '.Invoice::labelFor($month);
        }

        return compact('month', 'week', 'start', 'end', 'label', 'weekLabels');
    }

    /**
     * Minggu Senin–Minggu, dipotong di batas bulan.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function weeksOf(Carbon $monthStart): array
    {
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $weeks = [];
        $cursor = $monthStart->copy();

        while ($cursor->lte($monthEnd)) {
            $end = $cursor->copy()->addDays(7 - $cursor->dayOfWeekIso); // sampai hari Minggu
            if ($end->gt($monthEnd)) {
                $end = $monthEnd->copy();
            }
            $weeks[] = [$cursor->copy(), $end];
            $cursor = $end->copy()->addDay();
        }

        return $weeks;
    }
}
