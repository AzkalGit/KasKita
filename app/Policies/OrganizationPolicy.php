<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\OrganizationMember as M;
use App\Models\User;

// Matriks hak akses mengikuti CLAUDE.md bagian 6.
// Semua aturan hanya berlaku untuk keanggotaan AKTIF di organisasi tersebut.
class OrganizationPolicy
{
    private function hasRole(User $user, Organization $organization, string ...$roles): bool
    {
        $member = $user->memberships()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();

        return $member?->hasRole(...$roles) ?? false;
    }

    // D2: Anggota boleh melihat dashboard (hanya lihat)
    public function viewDashboard(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN, M::ROLE_EDITOR, M::ROLE_ANGGOTA);
    }

    public function viewTransactions(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN, M::ROLE_EDITOR, M::ROLE_ANGGOTA);
    }

    // Halaman Iuran: pengelola (Admin/Editor) melihat semua tagihan, Anggota hanya miliknya (dibatasi di controller)
    public function viewInvoices(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN, M::ROLE_EDITOR, M::ROLE_ANGGOTA);
    }

    public function viewReports(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN, M::ROLE_EDITOR);
    }

    public function viewArrears(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN);
    }

    public function manageTransactions(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN, M::ROLE_EDITOR);
    }

    public function deleteTransactions(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN);
    }

    // D1 (revisi): Admin + Editor. Aturan "bukan tagihan sendiri" dijaga di InvoiceController.
    public function manageInvoices(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN, M::ROLE_EDITOR);
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN);
    }

    public function manageInvitations(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN);
    }

    public function manageSettings(User $user, Organization $organization): bool
    {
        return $this->hasRole($user, $organization, M::ROLE_ADMIN);
    }
}
