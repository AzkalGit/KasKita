<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationLeaveButtonTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrg(): array
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => 'Org Uji', 'created_by' => $admin->id]);
        $org->members()->create([
            'user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email,
            'role' => 'admin', 'is_billable' => false,
        ]);

        return [$org, $admin];
    }

    private function addAccount(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
        ]);

        return $user;
    }

    public function test_admin_sendirian_melihat_tombol_keluar_di_daftar(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)->get(route('organizations.index'))
            ->assertOk()
            ->assertSee(route('members.leave', $org))
            ->assertDontSee('Angkat Admin dulu');
    }

    public function test_admin_tunggal_dengan_anggota_lain_melihat_tautan_bukan_tombol(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->addAccount($org, 'editor');

        $this->actingAs($admin)->get(route('organizations.index'))
            ->assertOk()
            ->assertSee('Angkat Admin dulu')
            ->assertDontSee(route('members.leave', $org));
    }

    public function test_admin_dengan_admin_lain_dan_editor_melihat_tombol_keluar(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->addAccount($org, 'admin');
        $editor = $this->addAccount($org, 'editor');

        $this->actingAs($admin)->get(route('organizations.index'))
            ->assertSee(route('members.leave', $org));
        $this->actingAs($editor)->get(route('organizations.index'))
            ->assertSee(route('members.leave', $org))
            ->assertDontSee('Angkat Admin dulu');
    }
}
