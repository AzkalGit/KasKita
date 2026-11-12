<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClosePeriodRequest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PeriodClosureController extends Controller
{
    public function store(ClosePeriodRequest $request, Organization $organization): RedirectResponse
    {
        $period = $request->validated('period');
        $label = Invoice::labelFor($period);

        // Hanya bulan yang sudah berlalu (format Y-m bisa dibandingkan sebagai teks)
        if ($period >= now()->format('Y-m')) {
            return $this->refuse($organization, 'Hanya bulan yang sudah berlalu yang bisa ditutup. Bulan berjalan belum bisa.', $period);
        }

        $closure = $organization->periodClosures()->firstOrCreate(
            ['period' => $period],
            ['closed_by' => $request->user()->id, 'closed_at' => now()],
        );

        if (! $closure->wasRecentlyCreated) {
            return $this->refuse($organization, "Buku {$label} sudah ditutup.", $period);
        }

        ActivityLogger::record($organization, $request->user(), 'period.closed', $closure, [
            'period' => $period,
        ]);

        return $this->done($organization, "Buku {$label} ditutup.", $period);
    }

    public function destroy(Request $request, Organization $organization, string $period): RedirectResponse
    {
        Gate::authorize('closePeriods', $organization);

        $label = Invoice::labelFor($period);
        $closure = $organization->periodClosures()->where('period', $period)->first();

        if ($closure === null) {
            return $this->refuse($organization, "Buku {$label} tidak sedang ditutup.", $period);
        }

        $closedAt = $closure->closed_at;
        $closure->delete();

        ActivityLogger::record($organization, $request->user(), 'period.reopened', null, [
            'period' => $period,
            'closed_at' => $closedAt?->toDateTimeString(),
        ]);

        return $this->done($organization, "Buku {$label} dibuka kembali.", $period);
    }

    private function done(Organization $organization, string $message, string $period): RedirectResponse
    {
        return redirect()->route('reports.index', ['organization' => $organization, 'month' => $period])->with('status', $message);
    }

    private function refuse(Organization $organization, string $message, string $period): RedirectResponse
    {
        return redirect()->route('reports.index', ['organization' => $organization, 'month' => $period])->withErrors(['period' => $message]);
    }
}
