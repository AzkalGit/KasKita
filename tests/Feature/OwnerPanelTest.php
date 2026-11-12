<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerPanelTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->forceFill(['platform_role' => 'owner'])->save();

        return $owner;
    }

    private function organization(string $name = 'Org Uji', array $extra = []): Organization
    {
        $creator = User::factory()->create();

        return Organization::create(array_merge([
            'name' => $name,
            'status' => 'aktif',
            'created_by' => $creator->id,
        ], $extra));
    }

    /** Pengguna biasa dengan peran tertentu di organisasi. */
    private function memberOf(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        $org->members()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user;
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('owner.organizations.index'))->assertRedirect(route('login'));
    }

    public function test_non_owner_ditolak_di_semua_rute_panel(): void
    {
        $org = $this->organization();

        foreach (['admin', 'editor', 'anggota'] as $role) {
            $user = $this->memberOf($org, $role);

            $this->actingAs($user)->get(route('owner.organizations.index'))->assertForbidden();
            $this->actingAs($user)->patch(route('owner.organizations.deactivate', $org))->assertForbidden();
            $this->actingAs($user)->patch(route('owner.organizations.activate', $org))->assertForbidden();
        }

        $this->assertSame('aktif', $org->fresh()->status);
    }

    public function test_owner_melihat_daftar_dan_ringkasan(): void
    {
        $a = $this->organization('Org Alfa');
        $this->organization('Org Beta', ['status' => 'nonaktif']);
        $this->memberOf($a, 'admin');

        $response = $this->actingAs($this->owner())->get(route('owner.organizations.index'));

        $response->assertOk()->assertSee('Org Alfa')->assertSee('Org Beta');
        $response->assertViewHas('totalOrganizations', 2);
        $response->assertViewHas('activeOrganizations', 1);
    }

    public function test_panel_tidak_menampilkan_data_keuangan_atau_nama_anggota(): void
    {
        $org = $this->organization('Org Rahasia', [
            'opening_balance' => 987654321,
            'payment_info' => 'REKENING-RAHASIA-123',
        ]);
        $org->members()->create(['name' => 'Nama Anggota Rahasia', 'email' => 'rahasia@example.test', 'is_active' => true]);

        $this->actingAs($this->owner())
            ->get(route('owner.organizations.index'))
            ->assertOk()
            ->assertSee('Org Rahasia')
            ->assertDontSee('987654321')
            ->assertDontSee('987.654.321')
            ->assertDontSee('REKENING-RAHASIA-123')
            ->assertDontSee('Nama Anggota Rahasia')
            ->assertDontSee('rahasia@example.test');
    }

    public function test_penanda_tanpa_admin_hanya_untuk_organisasi_tanpa_admin_aktif(): void
    {
        $withAdmin = $this->organization('Org Beradmin');
        $this->memberOf($withAdmin, 'admin');

        $this->actingAs($this->owner())
            ->get(route('owner.organizations.index'))
            ->assertDontSee('Tanpa Admin');

        $this->organization('Org Yatim');

        $this->actingAs($this->owner())
            ->get(route('owner.organizations.index'))
            ->assertSee('Tanpa Admin');
    }

    public function test_owner_menonaktifkan_organisasi_dan_dicatat(): void
    {
        $org = $this->organization();
        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch(route('owner.organizations.deactivate', $org))
            ->assertRedirect(route('owner.organizations.index'))
            ->assertSessionHas('status');

        $this->assertSame('nonaktif', $org->fresh()->status);
        $this->assertTrue(
            ActivityLog::where('organization_id', $org->id)
                ->where('user_id', $owner->id)
                ->where('action', 'organization.deactivated')
                ->exists()
        );
    }

    public function test_anggota_organisasi_nonaktif_ditolak(): void
    {
        $org = $this->organization();
        $admin = $this->memberOf($org, 'admin');

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertOk();

        $this->actingAs($this->owner())->patch(route('owner.organizations.deactivate', $org));

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertForbidden();
    }

    public function test_owner_mengaktifkan_kembali_dan_dicatat(): void
    {
        $org = $this->organization('Org Tidur', ['status' => 'nonaktif']);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->patch(route('owner.organizations.activate', $org))
            ->assertRedirect(route('owner.organizations.index'));

        $this->assertSame('aktif', $org->fresh()->status);
        $this->assertTrue(
            ActivityLog::where('organization_id', $org->id)->where('action', 'organization.activated')->exists()
        );
    }

    public function test_status_yang_sama_ditolak_tanpa_mencatat_log(): void
    {
        $org = $this->organization();

        $this->actingAs($this->owner())
            ->patch(route('owner.organizations.activate', $org))
            ->assertSessionHasErrors('owner');

        $this->assertSame(0, ActivityLog::where('organization_id', $org->id)->count());
    }

    public function test_tautan_panel_hanya_tampil_untuk_owner(): void
    {
        $this->actingAs($this->owner())->get(route('owner.organizations.index'))->assertSee('Panel Owner');

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('organizations.index'))->assertDontSee('Panel Owner');
    }
}
