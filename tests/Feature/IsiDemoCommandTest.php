<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsiDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_tidak_membuat_akun_tambahan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, User::count());
        $this->assertSame(4, Organization::count());
    }

    public function test_perintah_menambah_24_akun_terverifikasi(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('kaskita:isi-demo')->assertSuccessful();

        $this->assertSame(28, User::count());
        $this->assertSame(24, User::where('email', 'like', '%@demo.kaskita.id')->whereNotNull('email_verified_at')->count());
        $this->assertSame(4, Organization::count());
        $this->assertSame(44, OrganizationMember::count()); // 12 dari seeder + 32 baru
    }

    public function test_setiap_tagihan_lunas_punya_transaksi_dan_tanpa_berkas_bukti(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('kaskita:isi-demo')->assertSuccessful();

        $this->assertGreaterThan(0, Invoice::where('status', 'lunas')->count());
        $this->assertGreaterThan(0, Invoice::where('status', 'menunggu')->count());
        $this->assertGreaterThan(0, Invoice::where('status', 'belum')->count());
        $this->assertSame(
            Invoice::where('status', 'lunas')->count(),
            Transaction::whereNotNull('invoice_id')->count()
        );
        $this->assertSame(0, Invoice::whereNotNull('proof_path')->count());
    }

    public function test_perintah_menolak_dijalankan_dua_kali(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->artisan('kaskita:isi-demo')->assertSuccessful();

        $this->artisan('kaskita:isi-demo')->assertFailed();

        $this->assertSame(28, User::count());
    }

    public function test_perintah_menolak_tanpa_seeder_dan_di_luar_lokal(): void
    {
        $this->artisan('kaskita:isi-demo')->assertFailed(); // organisasi demo belum ada

        $this->seed(DatabaseSeeder::class);
        $this->app['env'] = 'production';

        $this->artisan('kaskita:isi-demo')->assertFailed();
        $this->assertSame(4, User::count());
    }
}
