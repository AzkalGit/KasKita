<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLeaveTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: OrganizationMember} */
    private function makeOrg(string $name = 'Org Uji'): array
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => $name, 'created_by' => $admin->id]);
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

    public function test_admin_sendirian_bisa_keluar_dan_organisasi_hilang_dari_daftar(): void
    {
        [$org, $admin, $m] = $this->makeOrg('Org Ditinggalkan');

        $this->actingAs($admin)->post(route('members.leave', $org))
            ->assertRedirect(route('organizations.index'));

        $m->refresh();
        $this->assertFalse($m->is_active);
        $this->assertNull($m->role);
        $this->assertTrue(ActivityLog::where('action', 'member.left')->where('organization_id', $org->id)->exists());

        $this->actingAs($admin)->get(route('organizations.index'))
            ->assertOk()
            ->assertViewHas('memberships', fn ($memberships) => $memberships->isEmpty());
        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertForbidden();
    }

    public function test_admin_tunggal_ditolak_bila_masih_ada_editor_atau_anggota(): void
    {
        foreach (['editor', 'anggota'] as $role) {
            [$org, $admin, $adminMember] = $this->makeOrg();
            $this->addAccount($org, $role);

            $this->actingAs($admin)->post(route('members.leave', $org))->assertForbidden();

            $this->assertTrue($adminMember->fresh()->is_active);
            $this->assertSame('admin', $adminMember->fresh()->role);
        }
    }

    public function test_admin_tunggal_boleh_keluar_bila_sisanya_hanya_kontak_atau_nonaktif(): void
    {
        [$org, $admin, $adminMember] = $this->makeOrg();
        $org->members()->create(['name' => 'Budi', 'email' => 'budi@kontak.test']);
        [, $bekas] = $this->addAccount($org, 'anggota');
        $bekas->deactivate();

        $this->actingAs($admin)->post(route('members.leave', $org))
            ->assertRedirect(route('organizations.index'));

        $this->assertFalse($adminMember->fresh()->is_active);
    }

    public function test_admin_boleh_keluar_bila_ada_admin_lain_aktif(): void
    {
        [$org, $admin, $adminMember] = $this->makeOrg();
        [, $admin2] = $this->addAccount($org, 'admin');
        $this->addAccount($org, 'editor');

        $this->actingAs($admin)->post(route('members.leave', $org))
            ->assertRedirect(route('organizations.index'));

        $this->assertFalse($adminMember->fresh()->is_active);
        $this->assertTrue($admin2->fresh()->is_active);
        $this->assertSame('admin', $admin2->fresh()->role);
    }

    public function test_alur_lengkap_angkat_pengganti_lalu_keluar(): void
    {
        [$org, $admin, $adminMember] = $this->makeOrg();
        [$editor, $editorMember] = $this->addAccount($org, 'editor');

        $this->actingAs($admin)->post(route('members.leave', $org))->assertForbidden();

        $this->actingAs($admin)->patch(route('members.promote', [$org, $editorMember]))
            ->assertRedirect(route('members.index', $org));
        $this->assertSame('admin', $editorMember->fresh()->role);

        $this->actingAs($admin)->post(route('members.leave', $org))
            ->assertRedirect(route('organizations.index'));

        $this->assertFalse($adminMember->fresh()->is_active);
        $this->assertTrue($editorMember->fresh()->is_active);
        $this->actingAs($editor)->get(route('members.index', $org))->assertOk();
    }

    public function test_keluar_dari_satu_organisasi_tidak_mengubah_organisasi_lain(): void
    {
        [$org1, $admin] = $this->makeOrg('Org Satu');
        $org2 = Organization::create(['name' => 'Org Dua', 'created_by' => $admin->id]);
        $member2 = $org2->members()->create([
            'user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email,
            'role' => 'admin', 'is_billable' => false,
        ]);

        $this->actingAs($admin)->post(route('members.leave', $org1))
            ->assertRedirect(route('organizations.index'));

        $this->assertTrue($member2->fresh()->is_active);
        $this->assertSame('admin', $member2->fresh()->role);
        $this->actingAs($admin)->get(route('organizations.index'))
            ->assertViewHas('memberships', fn ($memberships) => $memberships->count() === 1);
    }

    public function test_admin_menjadikan_editor_dan_anggota_sebagai_admin(): void
    {
        [$org, $admin] = $this->makeOrg();

        foreach (['editor', 'anggota'] as $role) {
            [, $m] = $this->addAccount($org, $role);

            $this->actingAs($admin)->patch(route('members.promote', [$org, $m]))
                ->assertRedirect(route('members.index', $org));

            $this->assertSame('admin', $m->fresh()->role);
            $this->assertTrue(
                ActivityLog::where('action', 'member.role_changed')->where('subject_id', $m->id)->exists()
            );
        }
    }

    public function test_hanya_admin_organisasi_itu_boleh_mempromosikan(): void
    {
        [$org] = $this->makeOrg();
        [, $target] = $this->addAccount($org, 'anggota');
        [$otherOrg, $otherAdmin] = $this->makeOrg('Org Lain');

        foreach (['editor', 'anggota'] as $role) {
            [$user] = $this->addAccount($org, $role);
            $this->actingAs($user)->patch(route('members.promote', [$org, $target]))->assertForbidden();
        }
        $this->actingAs($otherAdmin)->patch(route('members.promote', [$org, $target]))->assertForbidden();

        $this->assertSame('anggota', $target->fresh()->role);
    }

    public function test_promosi_ditolak_untuk_kontak_nonaktif_dan_anggota_organisasi_lain(): void
    {
        [$org, $admin] = $this->makeOrg();
        $contact = $org->members()->create(['name' => 'Budi', 'email' => 'budi@kontak.test']);
        [, $nonaktif] = $this->addAccount($org, 'editor');
        $nonaktif->deactivate();
        [$org2] = $this->makeOrg('Org Lain');
        [, $foreign] = $this->addAccount($org2, 'editor');

        $this->actingAs($admin)->patch(route('members.promote', [$org, $contact]))->assertSessionHasErrors('member');
        $this->actingAs($admin)->patch(route('members.promote', [$org, $nonaktif]))->assertSessionHasErrors('member');
        $this->actingAs($admin)->patch(route('members.promote', [$org, $foreign]))->assertNotFound();

        $this->assertNull($contact->fresh()->role);
        $this->assertNull($nonaktif->fresh()->role);
        $this->assertSame('editor', $foreign->fresh()->role);
    }

    public function test_mempromosikan_yang_sudah_admin_tidak_mengubah_apa_apa(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $admin2] = $this->addAccount($org, 'admin');

        $this->actingAs($admin)->patch(route('members.promote', [$org, $admin2]))
            ->assertRedirect(route('members.index', $org))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('admin', $admin2->fresh()->role);
        $this->assertSame(0, ActivityLog::where('action', 'member.role_changed')->count());
    }

    public function test_halaman_anggota_menampilkan_tombol_promosi_dan_catatan_keluar(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $editor] = $this->addAccount($org, 'editor');

        $this->actingAs($admin)->get(route('members.index', $org))
            ->assertOk()
            ->assertSee('Jadikan Admin')
            ->assertSee('satu-satunya Admin');

        $editor->deactivate();

        $this->actingAs($admin)->get(route('members.index', $org))
            ->assertOk()
            ->assertDontSee('satu-satunya Admin')
            ->assertSee('Kamu tidak akan melihat organisasi ini lagi');
    }
}
