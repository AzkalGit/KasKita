<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class OrganizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** Buat pengguna dengan peran tertentu di sebuah organisasi (null = bukan anggota). */
    private function userWithRole(?string $role, bool $active = true): array
    {
        $creator = User::factory()->create();
        $org = Organization::create(['name' => 'Org Uji', 'created_by' => $creator->id]);
        $user = User::factory()->create();

        if ($role !== null) {
            $org->members()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'is_active' => $active,
            ]);
        }

        return [$user, $org];
    }

    public function test_matriks_hak_akses_sesuai_rancangan(): void
    {
        // kemampuan => [peran => boleh?]
        $matrix = [
            'viewDashboard' => ['admin' => true,  'editor' => true,  'anggota' => true,  'bukan' => false],
            'viewReports' => ['admin' => true,  'editor' => true,  'anggota' => false, 'bukan' => false],
            'viewArrears' => ['admin' => true,  'editor' => false, 'anggota' => false, 'bukan' => false],
            'manageTransactions' => ['admin' => true,  'editor' => true,  'anggota' => false, 'bukan' => false],
            'deleteTransactions' => ['admin' => true,  'editor' => false, 'anggota' => false, 'bukan' => false],
            'manageInvoices' => ['admin' => true,  'editor' => false, 'anggota' => false, 'bukan' => false],
            'manageMembers' => ['admin' => true,  'editor' => false, 'anggota' => false, 'bukan' => false],
            'manageInvitations' => ['admin' => true,  'editor' => false, 'anggota' => false, 'bukan' => false],
            'manageSettings' => ['admin' => true,  'editor' => false, 'anggota' => false, 'bukan' => false],
        ];

        foreach ($matrix as $ability => $expected) {
            foreach ($expected as $role => $allowed) {
                [$user, $org] = $this->userWithRole($role === 'bukan' ? null : $role);

                $this->assertSame(
                    $allowed,
                    Gate::forUser($user)->allows($ability, $org),
                    "{$ability} untuk peran {$role}"
                );
            }
        }
    }

    public function test_admin_nonaktif_tidak_punya_hak_apa_pun(): void
    {
        [$user, $org] = $this->userWithRole('admin', active: false);

        $this->assertFalse(Gate::forUser($user)->allows('viewDashboard', $org));
        $this->assertFalse(Gate::forUser($user)->allows('manageMembers', $org));
    }

    public function test_semua_peran_anggota_bisa_membuka_dashboard(): void
    {
        foreach (['admin', 'editor', 'anggota'] as $role) {
            [$user, $org] = $this->userWithRole($role);

            $this->actingAs($user)
                ->get(route('organizations.dashboard', $org))
                ->assertOk();
        }
    }

    public function test_bukan_anggota_ditolak_di_dashboard(): void
    {
        [$user, $org] = $this->userWithRole(null);

        $this->actingAs($user)
            ->get(route('organizations.dashboard', $org))
            ->assertForbidden();
    }
}
