<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Database\Seeders\TestOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

// Akun hanya boleh ditautkan ke data anggota (kontak) setelah emailnya terbukti miliknya.
class MembershipLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $email): User
    {
        $this->post('/register', [
            'name' => 'Pendaftar',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        return User::where('email', $email)->firstOrFail();
    }

    private function verify(User $user): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url);
    }

    public function test_mendaftar_dengan_email_kontak_tidak_langsung_menjadi_anggota(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $org = Organization::firstOrFail();

        $user = $this->register('sinta@kaskita.demo');

        $this->assertNull($user->email_verified_at);
        $this->assertNull(OrganizationMember::where('email', 'sinta@kaskita.demo')->value('user_id'));
        $this->assertSame(0, $user->memberships()->count());
        $this->get(route('organizations.dashboard', $org))->assertRedirect(route('verification.notice'));
    }

    public function test_setelah_email_diverifikasi_akun_tertaut_sebagai_anggota(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $org = Organization::firstOrFail();
        $user = $this->register('sinta@kaskita.demo');

        $this->verify($user);

        $member = $user->memberships()->firstOrFail();
        $this->assertSame($org->id, $member->organization_id);
        $this->assertSame('anggota', $member->role);
        $this->get(route('organizations.dashboard', $org))->assertOk();
    }

    public function test_kontak_nonaktif_tidak_dihidupkan_lagi_saat_verifikasi(): void
    {
        $org = Organization::create(['name' => 'Uji', 'created_by' => User::factory()->create()->id]);
        $org->members()->create(['name' => 'Bekas', 'email' => 'bekas@x.id', 'is_active' => false]);

        $user = $this->register('bekas@x.id');
        $this->verify($user);

        $this->assertSame(0, $user->memberships()->count());
    }

    public function test_tidak_menautkan_ke_organisasi_yang_sudah_diikuti_akun_itu(): void
    {
        $org = Organization::create(['name' => 'Uji', 'created_by' => User::factory()->create()->id]);
        $user = User::factory()->create(['email' => 'baru@x.id']);
        // sudah anggota dengan email lama, lalu Admin mencatat kontak dengan email barunya
        $org->members()->create(['user_id' => $user->id, 'name' => 'Lama', 'email' => 'lama@x.id', 'role' => 'anggota']);
        $org->members()->create(['name' => 'Kontak baru', 'email' => 'baru@x.id']);

        $this->assertSame(0, $user->linkPendingMemberships());
        $this->assertSame(1, $user->memberships()->count());
    }

    public function test_email_kontak_disimpan_huruf_kecil_dan_kosong_menjadi_null(): void
    {
        $org = Organization::create(['name' => 'Uji', 'created_by' => User::factory()->create()->id]);

        $a = $org->members()->create(['name' => 'A', 'email' => '  Campur@Huruf.ID ']);
        $b = $org->members()->create(['name' => 'B', 'email' => '']);
        $c = $org->members()->create(['name' => 'C', 'email' => '   ']);

        $this->assertSame('campur@huruf.id', $a->fresh()->email);
        $this->assertNull($b->fresh()->email);
        $this->assertNull($c->fresh()->email); // dua kontak tanpa email tidak bentrok di indeks unik
    }

    public function test_kontak_berhuruf_besar_tertaut_setelah_verifikasi(): void
    {
        $org = Organization::create(['name' => 'Uji', 'created_by' => User::factory()->create()->id]);
        $org->members()->create(['name' => 'Campur', 'email' => 'Campur@Huruf.id']);

        $user = $this->register('campur@huruf.id');
        $this->verify($user);

        $this->assertSame(1, $user->memberships()->count());
    }

    public function test_akun_yang_dihapus_tidak_mewariskan_keanggotaan_ke_pendaftar_berikutnya(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $dimas = User::where('email', 'anggota@kaskita.demo')->firstOrFail();

        $this->actingAs($dimas)->delete('/profile', ['password' => 'KitaDemo2026!'])->assertRedirect('/');

        $baris = OrganizationMember::where('email', 'anggota@kaskita.demo')->firstOrFail();
        $this->assertNull($baris->user_id);
        $this->assertNull($baris->role);
        $this->assertFalse($baris->is_active);

        auth()->logout();
        $orangLain = $this->register('anggota@kaskita.demo');
        $this->verify($orangLain);

        $this->assertSame(0, $orangLain->memberships()->count());
    }
}
