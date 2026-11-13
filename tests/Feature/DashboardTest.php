<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User} */
    private function makeOrg(string $name = 'Org Uji', int $opening = 0): array
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => $name, 'created_by' => $admin->id, 'opening_balance' => $opening]);
        $org->members()->create([
            'user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email,
            'role' => 'admin', 'is_billable' => false,
        ]);

        return [$org, $admin];
    }

    private function userWithRole(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
        ]);

        return $user;
    }

    private function tx(Organization $org, User $by, array $override = []): Transaction
    {
        $cat = $org->categories()->first() ?? $org->categories()->create(['name' => 'Konsumsi', 'is_system' => false]);

        return Transaction::create(array_merge([
            'organization_id' => $org->id, 'category_id' => $cat->id, 'created_by' => $by->id,
            'type' => 'keluar', 'amount' => 1000, 'tanggal' => '2026-10-10', 'description' => 'Rapat',
        ], $override));
    }

    /** Tagihan berstatus $status untuk satu kontak baru (tanpa akun). */
    private function invoice(Organization $org, string $status, string $nama = 'Kontak'): Invoice
    {
        $member = $org->members()->create([
            'name' => $nama, 'role' => 'anggota', 'is_billable' => true,
        ]);

        return Invoice::forceCreate([
            'member_id' => $member->id, 'period' => '2026-10', 'amount' => 25000, 'status' => $status,
        ]);
    }

    public function test_kartu_menampilkan_saldo_total_dan_jumlah_transaksi(): void
    {
        [$org, $admin] = $this->makeOrg('Org Uji', 500000);
        $this->tx($org, $admin, ['type' => 'masuk', 'amount' => 200000]);
        $this->tx($org, $admin, ['type' => 'keluar', 'amount' => 50000]);

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertSee('Rp 650.000')
            ->assertSee('termasuk saldo awal Rp 500.000')
            ->assertSee('Rp 200.000')
            ->assertSee('Rp 50.000')
            ->assertSee('Jumlah transaksi');
    }

    public function test_transaksi_dihapus_tidak_dihitung_di_kartu_dan_grafik(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->tx($org, $admin, ['type' => 'masuk', 'amount' => 90000]);
        $hapus = $this->tx($org, $admin, ['type' => 'keluar', 'amount' => 77000]);
        $hapus->delete();

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertDontSee('77.000');
    }

    public function test_grafik_menampilkan_enam_bulan_dan_nilai_per_bulan(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->tx($org, $admin, ['type' => 'masuk', 'amount' => 200000, 'tanggal' => '2026-10-05']);
        $this->tx($org, $admin, ['type' => 'keluar', 'amount' => 50000, 'tanggal' => '2026-10-20']);

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertSee('Okt: masuk Rp 200.000, keluar Rp 50.000')
            ->assertSee('Mei: masuk Rp 0, keluar Rp 0');
    }

    public function test_grafik_tanpa_transaksi_tidak_error(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertSee('Belum ada transaksi.');
    }

    public function test_banner_bukti_menunggu_hanya_untuk_admin_dan_editor(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $this->invoice($org, Invoice::STATUS_MENUNGGU, 'Budi');
        $this->invoice($org, Invoice::STATUS_MENUNGGU, 'Sinta');
        $this->invoice($org, Invoice::STATUS_BELUM, 'Citra');

        $banner = '2 bukti pembayaran iuran menunggu verifikasi';
        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertSee($banner);
        $this->actingAs($editor)->get(route('organizations.dashboard', $org))->assertSee($banner);
        $this->actingAs($anggota)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertDontSee('menunggu verifikasi');
    }

    public function test_banner_tidak_muncul_bila_tidak_ada_yang_menunggu(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->invoice($org, Invoice::STATUS_LUNAS);

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertDontSee('menunggu verifikasi')
            ->assertDontSee('data-badge-iuran', false);
    }

    public function test_tagihan_menunggu_organisasi_lain_tidak_dihitung(): void
    {
        [$org, $admin] = $this->makeOrg('Org A');
        [$lain] = $this->makeOrg('Org B');
        $this->invoice($lain, Invoice::STATUS_MENUNGGU);

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertDontSee('menunggu verifikasi')
            ->assertDontSee('data-badge-iuran', false);
    }

    public function test_badge_iuran_di_sidebar_hanya_untuk_pengelola(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $this->invoice($org, Invoice::STATUS_MENUNGGU);

        // Badge ada di sidebar semua halaman organisasi, bukan hanya dashboard.
        $this->actingAs($admin)->get(route('transactions.index', $org))->assertSee('data-badge-iuran', false);
        $this->actingAs($editor)->get(route('transactions.index', $org))->assertSee('data-badge-iuran', false);
        $this->actingAs($anggota)->get(route('transactions.index', $org))->assertDontSee('data-badge-iuran', false);
    }

    public function test_nama_anggota_tidak_tampil_di_transaksi_terakhir(): void
    {
        [$org, $admin] = $this->makeOrg();
        $member = $org->members()->create(['name' => 'Alya Nurdin', 'role' => 'anggota', 'is_billable' => true]);
        $this->tx($org, $admin, ['type' => 'masuk', 'amount' => 25000, 'description' => 'Iuran Oktober 2026']);

        $anggota = $this->userWithRole($org, 'anggota');
        $this->actingAs($anggota)->get(route('organizations.dashboard', $org))
            ->assertOk()
            ->assertSee('Iuran Oktober 2026')
            ->assertDontSee($member->name);
    }
}
