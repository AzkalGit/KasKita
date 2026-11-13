<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\TestOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): Organization
    {
        $this->seed(TestOrganizationSeeder::class);

        return Organization::firstOrFail();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    // ---------- halaman daftar organisasi ----------

    public function test_halaman_organisasi_memakai_layout_dan_punya_formulir_buat_dan_gabung(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('organizations.index'))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee(route('organizations.store'), false)
            ->assertSee(route('organizations.join'), false)
            ->assertSee('Buat organisasi baru')
            ->assertSee('Gabung dengan kode')
            ->assertSee('Kamu belum tergabung');
    }

    public function test_daftar_menampilkan_organisasi_peran_dan_tautan_buka(): void
    {
        $org = $this->demo();

        $this->actingAs($this->user('anggota@kaskita.demo'))
            ->get(route('organizations.index'))
            ->assertOk()
            ->assertSee('BEM Fakultas Teknologi')
            ->assertSee('Anggota')
            ->assertSee(route('organizations.dashboard', $org), false);
    }

    public function test_organisasi_nonaktif_tampil_tanpa_tautan_buka(): void
    {
        $org = $this->demo();
        $org->update(['status' => 'nonaktif']);

        $this->actingAs($this->user('anggota@kaskita.demo'))
            ->get(route('organizations.index'))
            ->assertOk()
            ->assertSee('Nonaktif')
            ->assertDontSee(route('organizations.dashboard', $org), false);
    }

    public function test_pesan_sukses_dan_galat_ditampilkan_di_halaman(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->followingRedirects()
            ->post(route('organizations.store'), ['name' => 'Himpunan Uji'])
            ->assertSee('Organisasi berhasil dibuat');

        $this->actingAs($user)->followingRedirects()
            ->from(route('organizations.index'))
            ->post(route('organizations.join'), ['code' => 'SALAH123'])
            ->assertSee('Kode tidak valid');
    }

    // ---------- email wajib terverifikasi ----------

    public function test_pengguna_belum_verifikasi_dialihkan_dari_semua_halaman_organisasi(): void
    {
        $org = $this->demo();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('organizations.index'))->assertRedirect(route('verification.notice'));
        $this->post(route('organizations.store'), ['name' => 'X'])->assertRedirect(route('verification.notice'));
        $this->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertRedirect(route('verification.notice'));
        $this->get(route('organizations.dashboard', $org))->assertRedirect(route('verification.notice'));
    }

    public function test_dashboard_bawaan_breeze_dialihkan_ke_daftar_organisasi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertRedirect('/organisasi');
    }

    // ---------- buat organisasi ----------

    public function test_membuat_organisasi_menjadikan_pembuat_admin_tanpa_ditagih(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('organizations.store'), ['name' => 'Himpunan Uji'])
            ->assertRedirect(route('organizations.index'));

        $org = Organization::where('name', 'Himpunan Uji')->firstOrFail();
        $member = $org->members()->firstOrFail();

        $this->assertSame($user->id, $org->created_by);
        $this->assertSame($user->id, $member->user_id);
        $this->assertSame('admin', $member->role);
        $this->assertFalse($member->is_billable);
        $this->assertTrue($org->categories()->where('name', 'Iuran')->where('is_system', true)->exists());
        $this->assertSame(1, $org->invitations()->count());
    }

    public function test_nama_organisasi_wajib_diisi(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('organizations.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    // ---------- gabung dengan kode ----------

    public function test_gabung_dengan_kode_benar_menjadi_anggota_dan_kode_tidak_peka_huruf_besar(): void
    {
        $org = $this->demo();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('organizations.join'), ['code' => '  bem26kas '])
            ->assertRedirect(route('organizations.index'));

        $member = $user->memberships()->firstOrFail();
        $this->assertSame($org->id, $member->organization_id);
        $this->assertSame('anggota', $member->role);
        $this->assertSame(1, Invitation::where('code', 'BEM26KAS')->firstOrFail()->uses()->count());
    }

    public function test_lima_kode_salah_memblokir_percobaan_berikutnya_walau_kode_benar(): void
    {
        $this->demo();
        $user = User::factory()->create();
        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('organizations.join'), ['code' => 'SALAH'.$i])->assertSessionHasErrors('code');
        }

        $this->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasErrors('code');
        $this->assertSame(0, $user->memberships()->count());
    }

    public function test_tidak_bisa_gabung_dua_kali(): void
    {
        $this->demo();
        $user = $this->user('anggota@kaskita.demo');

        $this->actingAs($user)
            ->post(route('organizations.join'), ['code' => 'BEM26KAS'])
            ->assertSessionHasErrors('code');
        $this->assertSame(1, $user->memberships()->count());
    }

    public function test_anggota_yang_dinonaktifkan_tidak_bisa_masuk_lagi_dengan_kode(): void
    {
        $org = $this->demo();
        $user = User::factory()->create();
        $org->members()->create(['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => 'anggota', 'is_active' => false]);

        $this->actingAs($user)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasErrors('code');
        $this->get(route('organizations.dashboard', $org))->assertForbidden();
    }

    public function test_kontak_nonaktif_tanpa_akun_tidak_dihidupkan_lagi_lewat_kode(): void
    {
        $org = $this->demo();
        $user = User::factory()->create(['email' => 'bekas@x.id']);
        $org->members()->create(['name' => 'Bekas', 'email' => 'bekas@x.id', 'is_active' => false]);

        $this->actingAs($user)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasErrors('code');
        $this->assertSame(0, $user->memberships()->count());
    }

    public function test_gabung_menautkan_kontak_yang_emailnya_sama_tanpa_menggandakan(): void
    {
        $org = $this->demo();
        $user = User::factory()->create(['email' => 'sinta@kaskita.demo']); // kontak dari seeder, belum punya akun

        $this->actingAs($user)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertRedirect();

        $this->assertSame(1, $org->members()->where('email', 'sinta@kaskita.demo')->count());
        $this->assertSame($user->id, $org->members()->where('email', 'sinta@kaskita.demo')->value('user_id'));
    }

    public function test_organisasi_nonaktif_menolak_kode(): void
    {
        $this->demo()->update(['status' => 'nonaktif']);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasErrors('code');
        $this->assertSame(0, $user->memberships()->count());
    }

    // ---------- dashboard organisasi ----------

    public function test_anggota_aktif_melihat_dashboard_dengan_saldo_yang_benar(): void
    {
        $org = $this->demo();

        $this->actingAs($this->user('admin@kaskita.demo'))
            ->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertSee('Rp 455.000'); // saldo awal 500.000 + masuk 250.000 - keluar 295.000
    }

    public function test_bukan_anggota_ditolak(): void
    {
        $org = $this->demo();

        $this->actingAs(User::factory()->create())
            ->get(route('organizations.dashboard', $org))
            ->assertForbidden();
    }

    public function test_organisasi_nonaktif_menutup_dashboard(): void
    {
        $org = $this->demo();
        $org->update(['status' => 'nonaktif']);

        $this->actingAs($this->user('admin@kaskita.demo'))
            ->get(route('organizations.dashboard', $org))
            ->assertForbidden();
    }
}
