<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// D17: Owner platform terpisah dari peran organisasi (tidak membuat atau bergabung ke organisasi)
class OwnerSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $owner->forceFill(['platform_role' => 'owner'])->save();

        return $owner;
    }

    public function test_owner_dialihkan_dari_daftar_organisasi_ke_panel(): void
    {
        $this->actingAs($this->owner())
            ->get(route('organizations.index'))
            ->assertRedirect(route('owner.organizations.index'));
    }

    public function test_owner_tidak_bisa_membuat_organisasi(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('organizations.store'), ['name' => 'Org Owner'])
            ->assertForbidden();

        $this->assertSame(0, Organization::count());
        $this->assertSame(0, $owner->memberships()->count());
    }

    public function test_owner_tidak_bisa_bergabung_dengan_kode(): void
    {
        $creator = User::factory()->create();
        $org = Organization::create(['name' => 'Org Uji', 'created_by' => $creator->id]);
        Invitation::issue($org, Invitation::DEFAULT_DAYS, $creator);
        $code = Invitation::where('organization_id', $org->id)->firstOrFail()->code;

        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('organizations.join'), ['code' => $code])
            ->assertForbidden();

        $this->assertSame(0, $owner->memberships()->count());
    }

    public function test_pengguna_biasa_tidak_terpengaruh(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('organizations.index'))->assertOk();

        $this->actingAs($user)
            ->post(route('organizations.store'), ['name' => 'Org Biasa'])
            ->assertRedirect(route('organizations.index'));

        $this->assertSame(1, $user->memberships()->count());
    }
}
