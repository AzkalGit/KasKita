<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Organization $organization)
    {
        Gate::authorize('manageMembers', $organization);

        $members = $organization->members()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('members.index', compact('organization', 'members'));
    }

    public function store(StoreMemberRequest $request, Organization $organization): RedirectResponse
    {
        $member = $organization->members()->create($request->validated());

        ActivityLogger::record($organization, $request->user(), 'member.created', $member, [
            'name' => $member->name,
        ]);

        return $this->done($organization, "{$member->name} ditambahkan sebagai kontak.");
    }

    public function update(UpdateMemberRequest $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'phone']);
        $data['is_billable'] = $request->boolean('is_billable');

        // Email anggota yang punya akun mengikuti akunnya, jadi hanya kontak yang boleh diubah emailnya
        if (! $member->hasAccount() && array_key_exists('email', $request->validated())) {
            $data['email'] = $request->validated('email');
        }

        $member->fill($data);
        $billableChanged = $member->isDirty('is_billable');
        $member->save();

        ActivityLogger::record($organization, $request->user(), 'member.updated', $member, [
            'name' => $member->name,
        ]);

        // E2: perubahan "ditagih atau tidak" dicatat tersendiri
        if ($billableChanged) {
            ActivityLogger::record($organization, $request->user(), 'member.billable_changed', $member, [
                'name' => $member->name,
                'is_billable' => (bool) $member->is_billable,
            ]);
        }

        return $this->done($organization, 'Data anggota diperbarui.');
    }

    // Hanya Anggota <-> Editor. Peran Admin tidak bisa diberikan maupun dicabut di sini.
    public function updateRole(Request $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $organization);

        $data = $request->validate([
            'role' => ['required', Rule::in([OrganizationMember::ROLE_EDITOR, OrganizationMember::ROLE_ANGGOTA])],
        ]);

        if ($reason = $this->protectedReason($request, $member, 'mengubah peran')) {
            return $this->refuse($organization, $reason);
        }

        if (! $member->is_active || ! $member->hasAccount()) {
            return $this->refuse($organization, 'Hanya anggota aktif yang sudah punya akun yang bisa diberi peran.');
        }

        if ($member->role === $data['role']) {
            return $this->done($organization, 'Peran tidak berubah.');
        }

        $from = $member->role;
        $member->update(['role' => $data['role']]);

        ActivityLogger::record($organization, $request->user(), 'member.role_changed', $member, [
            'name' => $member->name,
            'from' => $from,
            'to' => $data['role'],
        ]);

        return $this->done($organization, "Peran {$member->name} diubah menjadi ".ucfirst($data['role']).'.');
    }

    public function deactivate(Request $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $organization);

        if ($reason = $this->protectedReason($request, $member, 'menonaktifkan')) {
            return $this->refuse($organization, $reason);
        }

        if (! $member->is_active) {
            return $this->done($organization, 'Anggota ini sudah nonaktif.');
        }

        $member->deactivate();

        ActivityLogger::record($organization, $request->user(), 'member.deactivated', $member, [
            'name' => $member->name,
        ]);

        return $this->done($organization, "{$member->name} dinonaktifkan. Riwayat tagihannya tetap tersimpan.");
    }

    public function reactivate(Request $request, Organization $organization, OrganizationMember $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $organization);

        if ($member->is_active) {
            return $this->done($organization, 'Anggota ini sudah aktif.');
        }

        $member->reactivate();

        ActivityLogger::record($organization, $request->user(), 'member.reactivated', $member, [
            'name' => $member->name,
        ]);

        return $this->done($organization, "{$member->name} diaktifkan kembali.");
    }

    // Keluar sendiri: boleh untuk anggota aktif mana pun, kecuali Admin (serah-terima belum ada, D5)
    public function leave(Request $request, Organization $organization): RedirectResponse
    {
        $member = $request->attributes->get('member');

        abort_if(
            $member->hasRole(OrganizationMember::ROLE_ADMIN),
            403,
            'Admin belum bisa keluar dari organisasi. Serahkan peran Admin terlebih dahulu.'
        );

        $member->deactivate();

        ActivityLogger::record($organization, $request->user(), 'member.left', $member, [
            'name' => $member->name,
        ]);

        return redirect()->route('organizations.index')
            ->with('status', "Kamu telah keluar dari {$organization->name}.");
    }

    // Aturan yang melindungi pembuat, Admin, dan diri sendiri
    private function protectedReason(Request $request, OrganizationMember $member, string $aksi): ?string
    {
        if ($member->isCreator()) {
            return 'Pembuat organisasi tidak bisa diturunkan atau dikeluarkan.';
        }

        if ($member->user_id !== null && (int) $member->user_id === (int) $request->user()->id) {
            return "Kamu tidak bisa {$aksi} dirimu sendiri.";
        }

        if ($member->hasRole(OrganizationMember::ROLE_ADMIN)) {
            return 'Peran Admin tidak bisa diubah atau dinonaktifkan. Organisasi harus selalu punya Admin.';
        }

        return null;
    }

    private function done(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('members.index', $organization)->with('status', $message);
    }

    private function refuse(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('members.index', $organization)->withErrors(['member' => $message]);
    }
}
