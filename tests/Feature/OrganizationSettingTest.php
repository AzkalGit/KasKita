<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSettingTest extends TestCase
{
    use RefreshDatabase;

    private function orgWith(string $role, ?string $info = 'Info lama'): array
    {
        $org = Organization::create(['name' => 'Org Uji', 'payment_info' => $info]);
        $user = User::factory()->create();
        $org->members()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $role,
        ]);

        return [$org, $user];
    }

    public function test_admin_melihat_form_terisi(): void
    {
        [$org, $admin] = $this->orgWith('admin');

        $this->actingAs($admin)
            ->get(route('settings.edit', $org))
            ->assertOk()
            ->assertSee('Info lama');
    }

    public function test_admin_menyimpan_dan_tercatat_tanpa_isi_di_log(): void
    {
        [$org, $admin] = $this->orgWith('admin');

        $this->actingAs($admin)
            ->patch(route('settings.update', $org), ['payment_info' => 'BSI 999 a.n. Bendahara'])
            ->assertRedirect(route('settings.edit', $org));

        $this->assertSame('BSI 999 a.n. Bendahara', $org->fresh()->payment_info);

        $log = ActivityLog::where('organization_id', $org->id)
            ->where('action', 'organization.payment_info_updated')
            ->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('BSI 999', json_encode($log->metadata));
    }

    public function test_isi_kosong_disimpan_sebagai_null(): void
    {
        [$org, $admin] = $this->orgWith('admin');

        $this->actingAs($admin)
            ->patch(route('settings.update', $org), ['payment_info' => '   '])
            ->assertRedirect();

        $this->assertNull($org->fresh()->payment_info);
    }

    public function test_lebih_dari_500_karakter_ditolak(): void
    {
        [$org, $admin] = $this->orgWith('admin');

        $this->actingAs($admin)
            ->patch(route('settings.update', $org), ['payment_info' => str_repeat('a', 501)])
            ->assertSessionHasErrors('payment_info');

        $this->assertSame('Info lama', $org->fresh()->payment_info);
    }

    public function test_tanpa_perubahan_tidak_membuat_log(): void
    {
        [$org, $admin] = $this->orgWith('admin');

        $this->actingAs($admin)
            ->patch(route('settings.update', $org), ['payment_info' => 'Info lama']);

        $this->assertSame(0, ActivityLog::where('action', 'organization.payment_info_updated')->count());
    }

    public function test_editor_dan_anggota_ditolak(): void
    {
        foreach (['editor', 'anggota'] as $role) {
            [$org, $user] = $this->orgWith($role);

            $this->actingAs($user)->get(route('settings.edit', $org))->assertForbidden();
            $this->actingAs($user)
                ->patch(route('settings.update', $org), ['payment_info' => 'Diubah'])
                ->assertForbidden();

            $this->assertSame('Info lama', $org->fresh()->payment_info);
        }
    }

    public function test_admin_organisasi_lain_tidak_bisa_mengubah(): void
    {
        [$orgA] = $this->orgWith('admin');
        [, $adminB] = $this->orgWith('admin');

        $this->actingAs($adminB)
            ->patch(route('settings.update', $orgA), ['payment_info' => 'Diretas'])
            ->assertForbidden();

        $this->assertSame('Info lama', $orgA->fresh()->payment_info);
    }

    public function test_link_pengaturan_di_sidebar_hanya_untuk_admin(): void
    {
        [$org, $admin] = $this->orgWith('admin');
        $this->actingAs($admin)
            ->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertSee(route('settings.edit', $org));

        foreach (['editor', 'anggota'] as $role) {
            [$org2, $user] = $this->orgWith($role);
            $this->actingAs($user)
                ->get(route('organizations.dashboard', $org2))
                ->assertOk()
                ->assertDontSee(route('settings.edit', $org2));
        }
    }

    public function test_rekening_tampil_di_halaman_iuran_anggota_dengan_baris_baru(): void
    {
        [$org, $anggota] = $this->orgWith('anggota', "BSI 123\na.n. Bendahara");

        $this->actingAs($anggota)
            ->get(route('invoices.index', $org))
            ->assertOk()
            ->assertSee('BSI 123')
            ->assertSee('whitespace-pre-line', false);
    }
}
