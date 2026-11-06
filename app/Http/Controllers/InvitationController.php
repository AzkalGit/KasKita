<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationRequest;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// Kelola kode undangan organisasi. Semua aksi khusus Admin (kemampuan 'manageInvitations').
// Rute ada di grup org.member dengan scopeBindings: kode dari organisasi lain otomatis 404.
class InvitationController extends Controller
{
    public function index(Organization $organization): View
    {
        Gate::authorize('manageInvitations', $organization);

        $invitations = $organization->invitations()
            ->with(['creator', 'uses.user'])
            ->withCount('uses')
            ->latest()
            ->latest('id')
            ->get();

        $joins = $invitations
            ->flatMap(fn (Invitation $i) => $i->uses->map(fn ($use) => ['use' => $use, 'code' => $i->code]))
            ->sortByDesc(fn ($row) => $row['use']->used_at)
            ->values();

        return view('invitations.index', [
            'organization' => $organization,
            'invitations' => $invitations,
            'joins' => $joins,
            'durations' => Invitation::DURATIONS,
        ]);
    }

    public function store(StoreInvitationRequest $request, Organization $organization): RedirectResponse
    {
        $days = (int) $request->validated('days');
        $invitation = Invitation::issue($organization, $days, $request->user());

        $this->log($request, $organization, 'invitation.created', $invitation, ['days' => $days]);

        return $this->back($organization)
            ->with('status', "Kode {$invitation->code} dibuat dan berlaku {$days} hari.");
    }

    public function close(Request $request, Organization $organization, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('manageInvitations', $organization);

        if ($invitation->isClosed()) {
            return $this->back($organization)->with('status', 'Kode ini sudah ditutup.');
        }
        if ($invitation->isExpired()) {
            return $this->back($organization)->with('status', 'Kode ini sudah kedaluwarsa, tidak perlu ditutup.');
        }

        $invitation->update(['closed_at' => now()]);
        $this->log($request, $organization, 'invitation.closed', $invitation);

        return $this->back($organization)
            ->with('status', "Kode {$invitation->code} ditutup. Tidak ada yang bisa memakainya lagi sampai dibuka kembali.");
    }

    public function reopen(Request $request, Organization $organization, Invitation $invitation): RedirectResponse
    {
        Gate::authorize('manageInvitations', $organization);

        if ($invitation->isExpired()) {
            return $this->back($organization)->withErrors([
                'invitation' => 'Kode ini sudah kedaluwarsa dan tidak bisa dibuka lagi. Buat kode baru atau ganti kodenya.',
            ]);
        }
        if (! $invitation->isClosed()) {
            return $this->back($organization)->with('status', 'Kode ini sudah aktif.');
        }

        $invitation->update(['closed_at' => null]);
        $this->log($request, $organization, 'invitation.reopened', $invitation);

        return $this->back($organization)->with('status', "Kode {$invitation->code} dibuka kembali.");
    }

    // Ganti kode: kode lama langsung ditutup, kode baru dibuat. Dipakai bila kode bocor atau tersebar ke orang yang salah.
    public function regenerate(StoreInvitationRequest $request, Organization $organization, Invitation $invitation): RedirectResponse
    {
        $days = (int) $request->validated('days');

        $new = DB::transaction(function () use ($invitation, $organization, $request, $days) {
            if ($invitation->isUsable()) {
                $invitation->update(['closed_at' => now()]);
            }

            return Invitation::issue($organization, $days, $request->user());
        });

        $this->log($request, $organization, 'invitation.regenerated', $new, [
            'replaced_code' => $invitation->code,
            'days' => $days,
        ]);

        return $this->back($organization)
            ->with('status', "Kode diganti. Kode baru: {$new->code} (berlaku {$days} hari). Kode lama tidak bisa dipakai lagi.");
    }

    private function back(Organization $organization): RedirectResponse
    {
        return redirect()->route('invitations.index', $organization);
    }

    private function log(Request $request, Organization $organization, string $action, Invitation $invitation, array $meta = []): void
    {
        ActivityLog::create([
            'organization_id' => $organization->id,
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => Invitation::class,
            'subject_id' => $invitation->id,
            'metadata' => ['code' => $invitation->code] + $meta,
        ]);
    }
}
