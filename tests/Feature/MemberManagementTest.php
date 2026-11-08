<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: OrganizationMember} */
    private function makeOrg(): array
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => 'Org Uji', 'created_by' => $admin->id]);
        $member = $org->members()->create([
            'user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email,
            'role' => 'admin', 'is_billable' => false,
        ]);

        return [$org, $admin, $member];
    }

    /** @return array{0: User, 1: OrganizationMember} */
    private function addAccount(Organization $org, string $role): array
    {
        $user = User::factory()->create();
        $member = $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
        ]);

        return [$user, $member];
    }

    private function addContact(Organization $org, string $name = 'Budi', string $email = 'budi@kontak.test'): OrganizationMember
    {
        return $org->members()->create(['name' => $name, 'email' => $email]);
    }

    public function test_hanya_admin_melihat_daftar_anggota(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor] = $this->addAccount($org, 'editor');
        [$anggota] = $this->addAccount($org, 'anggota');
        $this->addContact($org, 'Budi Kontak');
        $outsider = User::factory()->create();

        $this->actingAs($admin)->get(route('members.index', $org))->assertOk()->assertSee('Budi Kontak');
        $this->actingAs($editor)->get(route('members.index', $org))->assertForbidden();
        $this->actingAs($anggota)->get(route('members.index', $org))->assertForbidden();
        $this->actingAs($outsider)->get(route('members.index', $org))->assertForbidden();
    }

    public function test_admin_menambah_kontak_tanpa_akun(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)
            ->post(route('members.store', $org), [
                'name' => 'Sinta', 'email' => 'SINTA@Kontak.test', 'phone' => '0812', 'is_billable' => 1,
            ])
            ->assertRedirect(route('members.index', $org));

        $this->assertDatabaseHas('organization_members', [
            'organization_id' => $org->id, 'name' => 'Sinta', 'email' => 'sinta@kontak.test',
            'user_id' => null, 'role' => null,
        ]);
        $this->assertTrue(ActivityLog::where('action', 'member.created')->where('organization_id', $org->id)->exists());
    }

    public function test_email_kembar_ditolak_hanya_dalam_satu_organisasi(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2, $admin2] = $this->makeOrg();
        $this->addContact($org, 'Budi', 'budi@kontak.test');

        $this->actingAs($admin)
            ->post(route('members.store', $org), ['name' => 'Budi 2', 'email' => 'BUDI@kontak.test', 'is_billable' => 1])
            ->assertSessionHasErrors('email');
        $this->assertSame(1, OrganizationMember::where('email', 'budi@kontak.test')->count());

        // Organisasi lain boleh memakai email yang sama
        $this->actingAs($admin2)
            ->post(route('members.store', $org2), ['name' => 'Budi', 'email' => 'budi@kontak.test', 'is_billable' => 1])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(2, OrganizationMember::where('email', 'budi@kontak.test')->count());
    }

    public function test_editor_dan_anggota_tidak_bisa_menambah_kontak(): void
    {
        [$org] = $this->makeOrg();

        foreach (['editor', 'anggota'] as $role) {
            [$user] = $this->addAccount($org, $role);

            $this->actingAs($user)
                ->post(route('members.store', $org), ['name' => 'Penyusup', 'is_billable' => 1])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('organization_members', ['name' => 'Penyusup']);
    }

    public function test_admin_mengubah_peran_anggota_dan_editor(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $m] = $this->addAccount($org, 'anggota');

        $this->actingAs($admin)
            ->patch(route('members.role', [$org, $m]), ['role' => 'editor'])
            ->assertRedirect(route('members.index', $org));
        $this->assertSame('editor', $m->fresh()->role);

        $this->actingAs($admin)->patch(route('members.role', [$org, $m]), ['role' => 'anggota']);
        $this->assertSame('anggota', $m->fresh()->role);

        $this->assertTrue(
            ActivityLog::where('action', 'member.role_changed')->where('subject_id', $m->id)->exists()
        );
    }

    public function test_peran_admin_tidak_bisa_diubah(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $admin2Member] = $this->addAccount($org, 'admin');

        $this->actingAs($admin)
            ->patch(route('members.role', [$org, $admin2Member]), ['role' => 'anggota'])
            ->assertSessionHasErrors('member');

        $this->assertSame('admin', $admin2Member->fresh()->role);
    }

    public function test_tidak_bisa_mengubah_peran_sendiri_dan_pembuat_terlindungi(): void
    {
        [$org, $admin, $adminMember] = $this->makeOrg();

        $this->actingAs($admin)
            ->patch(route('members.role', [$org, $adminMember]), ['role' => 'anggota'])
            ->assertSessionHasErrors('member');

        $this->assertSame('admin', $adminMember->fresh()->role);
        $this->assertTrue($adminMember->isCreator());
    }

    public function test_peran_admin_tidak_bisa_diberikan_lewat_form(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $m] = $this->addAccount($org, 'anggota');

        $this->actingAs($admin)
            ->patch(route('members.role', [$org, $m]), ['role' => 'admin'])
            ->assertSessionHasErrors('role');

        $this->assertSame('anggota', $m->fresh()->role);
    }

    public function test_kontak_tanpa_akun_tidak_bisa_diberi_peran(): void
    {
        [$org, $admin] = $this->makeOrg();
        $contact = $this->addContact($org);

        $this->actingAs($admin)
            ->patch(route('members.role', [$org, $contact]), ['role' => 'editor'])
            ->assertSessionHasErrors('member');

        $this->assertNull($contact->fresh()->role);
    }

    public function test_editor_dan_anggota_tidak_bisa_mengubah_peran_atau_menonaktifkan(): void
    {
        [$org] = $this->makeOrg();
        [, $target] = $this->addAccount($org, 'anggota');

        foreach (['editor', 'anggota'] as $role) {
            [$user] = $this->addAccount($org, $role);

            $this->actingAs($user)->patch(route('members.role', [$org, $target]), ['role' => 'editor'])->assertForbidden();
            $this->actingAs($user)->patch(route('members.deactivate', [$org, $target]))->assertForbidden();
        }

        $this->assertSame('anggota', $target->fresh()->role);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_menonaktifkan_anggota_mencabut_aksesnya(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$user, $m] = $this->addAccount($org, 'anggota');

        $this->actingAs($user)->get(route('organizations.dashboard', $org))->assertOk();

        $this->actingAs($admin)->patch(route('members.deactivate', [$org, $m]))
            ->assertRedirect(route('members.index', $org));

        $m->refresh();
        $this->assertFalse($m->is_active);
        $this->assertNull($m->role);
        $this->actingAs($user)->get(route('organizations.dashboard', $org))->assertForbidden();
        $this->assertTrue(ActivityLog::where('action', 'member.deactivated')->exists());
    }

    public function test_admin_dan_pembuat_tidak_bisa_dinonaktifkan(): void
    {
        [$org, $admin, $creatorMember] = $this->makeOrg();
        [, $admin2Member] = $this->addAccount($org, 'admin');

        $this->actingAs($admin)->patch(route('members.deactivate', [$org, $admin2Member]))
            ->assertSessionHasErrors('member');
        $this->actingAs($admin)->patch(route('members.deactivate', [$org, $creatorMember]))
            ->assertSessionHasErrors('member');

        $this->assertTrue($admin2Member->fresh()->is_active);
        $this->assertTrue($creatorMember->fresh()->is_active);
    }

    public function test_kontak_dengan_tagihan_dinonaktifkan_bukan_dihapus(): void
    {
        [$org, $admin] = $this->makeOrg();
        $contact = $this->addContact($org);
        $contact->invoices()->create(['period' => '2026-10', 'amount' => 25000]);

        $this->actingAs($admin)->patch(route('members.deactivate', [$org, $contact]));

        $this->assertDatabaseHas('organization_members', ['id' => $contact->id, 'is_active' => 0]);
        $this->assertSame(1, $contact->invoices()->count());
    }

    public function test_mengaktifkan_kembali(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $account] = $this->addAccount($org, 'editor');
        $contact = $this->addContact($org);
        $account->deactivate();
        $contact->deactivate();

        $this->actingAs($admin)->patch(route('members.reactivate', [$org, $account]));
        $this->actingAs($admin)->patch(route('members.reactivate', [$org, $contact]));

        $this->assertTrue($account->fresh()->is_active);
        $this->assertSame('anggota', $account->fresh()->role);
        $this->assertTrue($contact->fresh()->is_active);
        $this->assertNull($contact->fresh()->role);
    }

    public function test_mengubah_data_kontak_dan_email_akun_tidak_berubah(): void
    {
        [$org, $admin] = $this->makeOrg();
        $contact = $this->addContact($org);
        [$user, $account] = $this->addAccount($org, 'anggota');

        $this->actingAs($admin)->patch(route('members.update', [$org, $contact]), [
            'name' => 'Budi Baru', 'email' => 'baru@kontak.test', 'phone' => '0899', 'is_billable' => 0,
        ])->assertSessionDoesntHaveErrors();

        $contact->refresh();
        $this->assertSame('Budi Baru', $contact->name);
        $this->assertSame('baru@kontak.test', $contact->email);
        $this->assertFalse($contact->is_billable);

        $this->actingAs($admin)->patch(route('members.update', [$org, $account]), [
            'name' => 'Nama Baru', 'email' => 'lain@kontak.test', 'is_billable' => 1,
        ]);

        $this->assertSame('Nama Baru', $account->fresh()->name);
        $this->assertSame($user->email, $account->fresh()->email);
    }

    public function test_anggota_organisasi_lain_menghasilkan_404(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2] = $this->makeOrg();
        $foreign = $this->addContact($org2, 'Orang Lain', 'lain@kontak.test');

        $this->actingAs($admin)->patch(route('members.deactivate', [$org, $foreign]))->assertNotFound();
        $this->actingAs($admin)->patch(route('members.update', [$org, $foreign]), ['name' => 'X', 'is_billable' => 1])->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
        $this->assertSame('Orang Lain', $foreign->fresh()->name);
    }

    public function test_anggota_bisa_keluar_sendiri_dan_aksesnya_hilang(): void
    {
        [$org] = $this->makeOrg();
        [$user, $m] = $this->addAccount($org, 'editor');

        $this->actingAs($user)->post(route('members.leave', $org))
            ->assertRedirect(route('organizations.index'));

        $this->assertFalse($m->fresh()->is_active);
        $this->actingAs($user)->get(route('organizations.dashboard', $org))->assertForbidden();
        $this->assertTrue(ActivityLog::where('action', 'member.left')->exists());
    }

    public function test_admin_tidak_bisa_keluar(): void
    {
        [$org, $admin, $adminMember] = $this->makeOrg();

        $this->actingAs($admin)->post(route('members.leave', $org))->assertForbidden();

        $this->assertTrue($adminMember->fresh()->is_active);
    }

    public function test_bergabung_lewat_kode_dicatat_di_riwayat(): void
    {
        [$org, $admin] = $this->makeOrg();
        Invitation::create(['organization_id' => $org->id, 'code' => 'ABCD2345', 'created_by' => $admin->id]);
        $newcomer = User::factory()->create();

        $this->actingAs($newcomer)->post(route('organizations.join'), ['code' => 'abcd2345']);

        $this->assertTrue(
            ActivityLog::where('action', 'member.joined')
                ->where('organization_id', $org->id)
                ->where('user_id', $newcomer->id)
                ->exists()
        );
    }
}
