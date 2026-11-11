<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberBillableTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: OrganizationMember} */
    private function setupOrg(): array
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => 'Org Uji', 'created_by' => $admin->id]);
        $org->members()->create([
            'user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email,
            'role' => 'admin', 'is_billable' => false,
        ]);
        $contact = $org->members()->create(['name' => 'Budi Kontak', 'is_billable' => true]);

        return [$org, $admin, $contact];
    }

    public function test_admin_mengubah_status_ditagih_dan_tercatat_di_log(): void
    {
        [$org, $admin, $contact] = $this->setupOrg();

        $this->actingAs($admin)
            ->patch(route('members.update', [$org, $contact]), ['name' => 'Budi Kontak', 'is_billable' => 0])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $contact->fresh()->is_billable);
        $this->assertTrue(ActivityLog::where('action', 'member.billable_changed')->where('organization_id', $org->id)->exists());

        // Admin ikut ditagih lewat baris miliknya sendiri (D3)
        $mAdmin = $org->members()->where('user_id', $admin->id)->firstOrFail();
        $this->actingAs($admin)
            ->patch(route('members.update', [$org, $mAdmin]), ['name' => $mAdmin->name, 'is_billable' => 1])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $mAdmin->fresh()->is_billable);
        $this->assertSame(2, ActivityLog::where('action', 'member.billable_changed')->count());
    }

    public function test_tanpa_perubahan_status_ditagih_tidak_ada_log_billable(): void
    {
        [$org, $admin, $contact] = $this->setupOrg();

        $this->actingAs($admin)
            ->patch(route('members.update', [$org, $contact]), ['name' => 'Budi Baru', 'is_billable' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('Budi Baru', $contact->fresh()->name);
        $this->assertSame(0, ActivityLog::where('action', 'member.billable_changed')->count());
    }
}
