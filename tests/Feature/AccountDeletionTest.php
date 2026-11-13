<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\TestOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'KitaDemo2026!';

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_admin_tunggal_tidak_bisa_menghapus_akun(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $nadia = $this->user('admin@kaskita.demo');

        $this->actingAs($nadia)->from('/profile')
            ->delete('/profile', ['password' => self::PASSWORD])
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertDatabaseHas('users', ['email' => 'admin@kaskita.demo']);
        $this->assertAuthenticatedAs($nadia);
        $this->assertStringContainsString('BEM Fakultas Teknologi', session('errors')->getBag('userDeletion')->first('password'));
    }

    public function test_kata_sandi_salah_tetap_ditolak(): void
    {
        $this->seed(TestOrganizationSeeder::class);

        $this->actingAs($this->user('anggota@kaskita.demo'))->from('/profile')
            ->delete('/profile', ['password' => 'salah'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertDatabaseHas('users', ['email' => 'anggota@kaskita.demo']);
    }

    public function test_admin_boleh_menghapus_akun_bila_ada_admin_lain_dan_organisasi_tetap_ada(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $org = Organization::firstOrFail();
        $nadia = $this->user('admin@kaskita.demo');
        $org->members()->where('user_id', $this->user('editor@kaskita.demo')->id)->update(['role' => 'admin']);

        $this->actingAs($nadia)->delete('/profile', ['password' => self::PASSWORD])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['email' => 'admin@kaskita.demo']);
        $this->assertNull($org->fresh()->created_by);
        $this->assertTrue(Transaction::whereNull('created_by')->exists(), 'transaksi lama tetap ada, pencatatnya dikosongkan');
    }

    public function test_editor_yang_pernah_mencatat_transaksi_bisa_menghapus_akun(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $org = Organization::firstOrFail();
        $rafi = $this->user('editor@kaskita.demo');
        $trx = Transaction::create([
            'organization_id' => $org->id,
            'category_id' => Category::where('organization_id', $org->id)->firstOrFail()->id,
            'created_by' => $rafi->id,
            'type' => 'keluar',
            'amount' => 10000,
            'tanggal' => '2026-10-20',
            'description' => 'Dicatat Rafi',
        ]);

        $this->actingAs($rafi)->delete('/profile', ['password' => self::PASSWORD])->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['email' => 'editor@kaskita.demo']);
        $this->assertNull($trx->fresh()->created_by);
    }

    public function test_menghapus_akun_menonaktifkan_keanggotaan_dan_riwayat_tagihan_tetap(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $dimas = $this->user('anggota@kaskita.demo');
        $member = $dimas->memberships()->firstOrFail();
        $tagihan = $member->invoices()->count();

        $this->actingAs($dimas)->delete('/profile', ['password' => self::PASSWORD])->assertRedirect('/');

        $member->refresh();
        $this->assertNull($member->user_id);
        $this->assertNull($member->role);
        $this->assertFalse($member->is_active);
        $this->assertSame($tagihan, $member->invoices()->count());
    }

    public function test_organisasi_membantu_menghitung_admin_tunggal(): void
    {
        $this->seed(TestOrganizationSeeder::class);
        $org = Organization::firstOrFail();

        $this->assertSame([$org->id], $this->user('admin@kaskita.demo')->organizationsWhereSoleAdmin()->pluck('id')->all());
        $this->assertCount(0, $this->user('editor@kaskita.demo')->organizationsWhereSoleAdmin());

        $org->members()->where('user_id', $this->user('editor@kaskita.demo')->id)->update(['role' => 'admin']);
        $this->assertCount(0, $this->user('admin@kaskita.demo')->organizationsWhereSoleAdmin());
    }

    public function test_platform_role_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $this->assertNotContains('platform_role', (new User)->getFillable());

        $user = User::create(['name' => 'X', 'email' => 'x@x.id', 'password' => 'password', 'platform_role' => 'owner']);
        $this->assertNull($user->fresh()->platform_role);

        $this->post('/register', [
            'name' => 'Y', 'email' => 'y@x.id', 'password' => 'password',
            'password_confirmation' => 'password', 'platform_role' => 'owner',
        ]);
        $this->assertNull(User::where('email', 'y@x.id')->firstOrFail()->platform_role);
    }
}
