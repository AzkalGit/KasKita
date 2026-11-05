<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_menghapus_organisasi_beserta_tagihan_transaksi_dan_anggota(): void
    {
        $this->seed(DemoOrganizationSeeder::class);
        $demo = Organization::firstOrFail();

        // organisasi lain yang harus tidak tersentuh
        $lain = Organization::create(['name' => 'Lain', 'created_by' => User::factory()->create()->id]);
        $lain->members()->create(['name' => 'Anggota Lain', 'email' => 'lain@x.id']);

        // transaksi yang sudah di-soft-delete pun harus ikut terhapus
        Transaction::where('organization_id', $demo->id)->firstOrFail()->delete();

        $this->assertGreaterThan(0, Invoice::count());
        $this->assertGreaterThan(0, Transaction::withTrashed()->count());

        $demo->purge();

        $this->assertNull(Organization::find($demo->id));
        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Transaction::withTrashed()->count());
        $this->assertSame(1, OrganizationMember::count());
        $this->assertNotNull(Organization::find($lain->id));
    }

    public function test_purge_organisasi_kosong_juga_berhasil(): void
    {
        $org = Organization::create(['name' => 'Kosong', 'created_by' => User::factory()->create()->id]);

        $org->purge();

        $this->assertNull(Organization::find($org->id));
    }
}
