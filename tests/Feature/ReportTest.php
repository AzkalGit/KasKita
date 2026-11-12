<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
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

    private function category(Organization $org, string $name = 'Konsumsi', bool $system = false): Category
    {
        return $org->categories()->create(['name' => $name, 'is_system' => $system]);
    }

    private function tx(Organization $org, User $by, Category $cat, array $override = []): Transaction
    {
        return Transaction::create(array_merge([
            'organization_id' => $org->id, 'category_id' => $cat->id, 'created_by' => $by->id,
            'type' => 'keluar', 'amount' => 1000, 'tanggal' => '2026-10-10', 'description' => 'Rapat',
        ], $override));
    }

    private function url(Organization $org, array $query = []): string
    {
        return route('reports.index', $org).($query ? '?'.http_build_query($query) : '');
    }

    private function csvUrl(Organization $org, array $query = []): string
    {
        return route('reports.download', $org).($query ? '?'.http_build_query($query) : '');
    }

    public function test_hanya_admin_dan_editor_melihat_laporan(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $outsider = User::factory()->create();

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))->assertOk();
        $this->actingAs($editor)->get($this->url($org, ['month' => '2026-10']))->assertOk();
        $this->actingAs($anggota)->get($this->url($org))->assertForbidden();
        $this->actingAs($outsider)->get($this->url($org))->assertForbidden();
    }

    public function test_unduh_csv_ditolak_untuk_anggota_dan_bukan_anggota(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $outsider = User::factory()->create();

        $this->actingAs($anggota)->get($this->csvUrl($org))->assertForbidden();
        $this->actingAs($outsider)->get($this->csvUrl($org))->assertForbidden();
        $this->actingAs($editor)->get($this->csvUrl($org))->assertOk();
        $this->actingAs($admin)->get($this->csvUrl($org))->assertOk();
    }

    public function test_organisasi_yang_tidak_ada_404_dan_data_organisasi_lain_tidak_tercampur(): void
    {
        [$org, $admin] = $this->makeOrg('Org A');
        [$other, $otherAdmin] = $this->makeOrg('Org B');
        $this->tx($org, $admin, $this->category($org), ['type' => 'masuk', 'amount' => 1000]);
        $this->tx($other, $otherAdmin, $this->category($other), ['type' => 'masuk', 'amount' => 999000]);

        $this->actingAs($admin)->get('/organisasi/999999/laporan')->assertNotFound();

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))
            ->assertOk()
            ->assertViewHas('masuk', 1000)
            ->assertViewHas('closing', 1000);
    }

    public function test_saldo_awal_periode_menghitung_transaksi_sebelum_periode(): void
    {
        [$org, $admin] = $this->makeOrg('Org Uji', 100000);
        $cat = $this->category($org);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 50000, 'tanggal' => '2026-09-30']);
        $this->tx($org, $admin, $cat, ['type' => 'keluar', 'amount' => 20000, 'tanggal' => '2026-09-15']);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 10000, 'tanggal' => '2026-10-05']);
        $this->tx($org, $admin, $cat, ['type' => 'keluar', 'amount' => 4000, 'tanggal' => '2026-10-20']);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 777000, 'tanggal' => '2026-11-01']); // sesudah periode

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))
            ->assertOk()
            ->assertViewHas('opening', 130000)
            ->assertViewHas('masuk', 10000)
            ->assertViewHas('keluar', 4000)
            ->assertViewHas('closing', 136000);
    }

    public function test_minggu_dipotong_di_batas_bulan(): void
    {
        // 1 Okt 2026 hari Kamis: minggu 1 = tgl 1-4, minggu 2 = 5-11, ..., minggu 5 = 26-31
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 500, 'tanggal' => '2026-09-30']);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 1000, 'tanggal' => '2026-10-04']);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 2000, 'tanggal' => '2026-10-05']);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 4000, 'tanggal' => '2026-10-31']);

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10', 'week' => 1]))
            ->assertViewHas('opening', 500)
            ->assertViewHas('masuk', 1000)
            ->assertViewHas('closing', 1500);

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10', 'week' => 2]))
            ->assertViewHas('opening', 1500)
            ->assertViewHas('masuk', 2000)
            ->assertViewHas('closing', 3500);

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10', 'week' => 5]))
            ->assertViewHas('opening', 3500)
            ->assertViewHas('masuk', 4000)
            ->assertViewHas('closing', 7500);
    }

    public function test_transaksi_soft_deleted_tidak_dihitung_dan_tidak_diekspor(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 3000, 'description' => 'Tetap ada']);
        $dihapus = $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 90000, 'description' => 'Sudah dihapus']);
        $dihapus->delete();

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))
            ->assertViewHas('masuk', 3000)
            ->assertDontSee('Sudah dihapus');

        $csv = $this->actingAs($admin)->get($this->csvUrl($org, ['month' => '2026-10']))->streamedContent();
        $this->assertStringContainsString('Tetap ada', $csv);
        $this->assertStringNotContainsString('Sudah dihapus', $csv);
    }

    public function test_rincian_per_kategori(): void
    {
        [$org, $admin] = $this->makeOrg();
        $konsumsi = $this->category($org, 'Konsumsi');
        $donasi = $this->category($org, 'Donasi');
        $this->tx($org, $admin, $konsumsi, ['type' => 'keluar', 'amount' => 1000]);
        $this->tx($org, $admin, $konsumsi, ['type' => 'keluar', 'amount' => 2000]);
        $this->tx($org, $admin, $donasi, ['type' => 'masuk', 'amount' => 5000]);

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))
            ->assertViewHas('breakdown', function ($b) {
                $this->assertSame(['Donasi', 'Konsumsi'], $b->pluck('name')->all());
                $this->assertSame(5000, $b[0]['masuk']);
                $this->assertSame(0, $b[0]['keluar']);
                $this->assertSame(0, $b[1]['masuk']);
                $this->assertSame(3000, $b[1]['keluar']);

                return true;
            });
    }

    public function test_tunggakan_hanya_untuk_admin_dan_data_tidak_dikirim_ke_editor(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');

        $belum = $org->members()->create(['name' => 'Citra Belum', 'email' => 'citra@kontak.test', 'is_billable' => true]);
        $menunggu = $org->members()->create(['name' => 'Dimas Menunggu', 'email' => 'dimas@kontak.test', 'is_billable' => true]);
        $lunas = $org->members()->create(['name' => 'Sinta Lunas', 'email' => 'sinta@kontak.test', 'is_billable' => true]);
        Invoice::create(['member_id' => $belum->id, 'period' => '2026-10', 'amount' => 25000, 'status' => 'belum']);
        Invoice::create(['member_id' => $menunggu->id, 'period' => '2026-10', 'amount' => 25000, 'status' => 'menunggu']);
        Invoice::create(['member_id' => $lunas->id, 'period' => '2026-10', 'amount' => 25000, 'status' => 'lunas']);

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))
            ->assertOk()
            ->assertSee('Tunggakan iuran')
            ->assertSee('Citra Belum')
            ->assertSee('Dimas Menunggu')
            ->assertDontSee('Sinta Lunas')
            ->assertViewHas('arrears', fn ($a) => $a->count() === 2);

        $this->actingAs($editor)->get($this->url($org, ['month' => '2026-10']))
            ->assertOk()
            ->assertDontSee('Tunggakan')
            ->assertDontSee('Citra Belum')
            ->assertDontSee('Dimas Menunggu')
            ->assertViewHas('arrears', null);
    }

    public function test_tunggakan_organisasi_lain_tidak_tampil(): void
    {
        [$org, $admin] = $this->makeOrg('Org A');
        [$other] = $this->makeOrg('Org B');
        $asing = $other->members()->create(['name' => 'Orang Asing', 'email' => 'asing@kontak.test', 'is_billable' => true]);
        Invoice::create(['member_id' => $asing->id, 'period' => '2026-10', 'amount' => 25000, 'status' => 'belum']);

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10']))
            ->assertDontSee('Orang Asing')
            ->assertViewHas('arrears', fn ($a) => $a->isEmpty());
    }

    public function test_csv_berisi_bom_header_dan_baris_yang_benar(): void
    {
        [$org, $admin] = $this->makeOrg('Org Uji');
        $cat = $this->category($org, 'Konsumsi');
        $this->tx($org, $admin, $cat, ['description' => 'Beli "spanduk", besar', 'amount' => 1000, 'tanggal' => '2026-10-10']);
        $this->tx($org, $admin, $cat, ['description' => 'Bulan lain', 'tanggal' => '2026-09-10']);

        $response = $this->actingAs($admin)->get($this->csvUrl($org, ['month' => '2026-10']));
        $response->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('laporan-org-uji-2026-10.csv', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFTanggal,Jenis,Kategori,Keterangan,Jumlah\n", $csv);
        $this->assertStringContainsString("2026-10-10,Keluar,Konsumsi,\"Beli \"\"spanduk\"\", besar\",1000\n", $csv);
        $this->assertStringNotContainsString('Bulan lain', $csv);
    }

    public function test_nama_berkas_csv_memuat_minggu_bila_dipilih(): void
    {
        [$org, $admin] = $this->makeOrg('Org Uji');

        $response = $this->actingAs($admin)->get($this->csvUrl($org, ['month' => '2026-10', 'week' => 2]));

        $this->assertStringContainsString('laporan-org-uji-2026-10-minggu-2.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_csv_menetralkan_nilai_yang_bisa_jadi_rumus(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        foreach (['=A1', '+A1', '-A1', '@A1'] as $teks) {
            $this->tx($org, $admin, $cat, ['description' => $teks]);
        }

        $csv = $this->actingAs($admin)->get($this->csvUrl($org, ['month' => '2026-10']))->streamedContent();

        foreach (['=A1', '+A1', '-A1', '@A1'] as $teks) {
            $this->assertStringContainsString(",'".$teks.',1000', $csv);
            $this->assertStringNotContainsString(','.$teks.',1000', $csv);
        }
    }

    public function test_unduh_csv_dicatat_di_log_aktivitas(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)->get($this->csvUrl($org, ['month' => '2026-10']))->streamedContent();

        $this->assertSame(1, ActivityLog::where('action', 'report.exported')
            ->where('organization_id', $org->id)
            ->where('user_id', $admin->id)
            ->count());
    }

    public function test_filter_tidak_valid_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-13']))->assertSessionHasErrors('month');
        $this->actingAs($admin)->get($this->url($org, ['month' => 'abc']))->assertSessionHasErrors('month');
        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10', 'week' => 0]))->assertSessionHasErrors('week');
        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10', 'week' => 'abc']))->assertSessionHasErrors('week');
        $this->actingAs($admin)->get($this->url($org, ['month' => '2026-10', 'week' => 6]))->assertSessionHasErrors('week');
        $this->actingAs($admin)->get($this->csvUrl($org, ['month' => '2026-13']))->assertSessionHasErrors('month');

        $this->assertSame(0, ActivityLog::where('action', 'report.exported')->count());
    }

    public function test_tanpa_filter_memakai_bulan_berjalan(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->travelTo(Carbon::parse('2026-10-15 10:00:00'));

        $this->actingAs($admin)->get($this->url($org))
            ->assertOk()
            ->assertViewHas('month', '2026-10')
            ->assertViewHas('week', null);
    }

    public function test_tautan_laporan_di_dashboard_hanya_untuk_admin_dan_editor(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $tautan = route('reports.index', $org);

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertSee($tautan);
        $this->actingAs($editor)->get(route('organizations.dashboard', $org))->assertSee($tautan);
        $this->actingAs($anggota)->get(route('organizations.dashboard', $org))->assertDontSee($tautan);
    }
}
