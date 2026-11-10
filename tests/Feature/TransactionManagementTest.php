<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User} */
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

    private function lockedTx(Organization $org, User $by): Transaction
    {
        $iuran = $this->category($org, 'Iuran', true);
        $member = $org->members()->create(['name' => 'Budi', 'email' => 'budi@kontak.test']);
        $invoice = Invoice::create(['member_id' => $member->id, 'period' => '2026-10', 'amount' => 25000]);

        return $this->tx($org, $by, $iuran, [
            'invoice_id' => $invoice->id, 'type' => 'masuk', 'amount' => 25000, 'description' => 'Iuran Oktober',
        ]);
    }

    private function payload(Category $cat, array $override = []): array
    {
        return array_merge([
            'type' => 'keluar', 'category_id' => $cat->id, 'amount' => 25000,
            'tanggal' => '2026-10-12', 'description' => 'Beli spanduk',
        ], $override);
    }

    public function test_semua_anggota_aktif_melihat_daftar_dan_bukan_anggota_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $outsider = User::factory()->create();
        $this->tx($org, $admin, $this->category($org), ['description' => 'Konsumsi rapat']);

        foreach ([$admin, $editor, $anggota] as $user) {
            $this->actingAs($user)->get(route('transactions.index', $org))
                ->assertOk()->assertSee('Konsumsi rapat');
        }
        $this->actingAs($outsider)->get(route('transactions.index', $org))->assertForbidden();
    }

    public function test_tombol_mengikuti_peran(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $this->tx($org, $admin, $this->category($org));

        $this->actingAs($admin)->get(route('transactions.index', $org))
            ->assertSee('Tambah transaksi')->assertSee('Ubah')->assertSee('Hapus');
        $this->actingAs($editor)->get(route('transactions.index', $org))
            ->assertSee('Tambah transaksi')->assertSee('Ubah')->assertDontSee('Hapus');
        $this->actingAs($anggota)->get(route('transactions.index', $org))
            ->assertDontSee('Tambah transaksi')->assertDontSee('Ubah')->assertDontSee('Hapus');
    }

    public function test_admin_dan_editor_mencatat_transaksi(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $cat = $this->category($org);

        foreach ([$admin, $editor] as $i => $user) {
            $this->actingAs($user)
                ->post(route('transactions.store', $org), $this->payload($cat, ['description' => "Catatan {$i}"]))
                ->assertRedirect(route('transactions.index', $org));

            $t = Transaction::where('description', "Catatan {$i}")->firstOrFail();
            $this->assertEquals($user->id, $t->created_by);
            $this->assertSame(25000, $t->amount);
            $this->assertSame('keluar', $t->type);
            $this->assertSame('2026-10-12', $t->tanggal->toDateString());
            $this->assertNull($t->invoice_id);
        }

        $this->assertSame(2, ActivityLog::where('action', 'transaction.created')->where('organization_id', $org->id)->count());
    }

    public function test_anggota_dan_bukan_anggota_tidak_bisa_mencatat(): void
    {
        [$org] = $this->makeOrg();
        $cat = $this->category($org);

        foreach ([$this->userWithRole($org, 'anggota'), User::factory()->create()] as $user) {
            $this->actingAs($user)->post(route('transactions.store', $org), $this->payload($cat))->assertForbidden();
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_isian_yang_tidak_valid_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);

        $bad = [
            ['amount' => 0],
            ['amount' => -5],
            ['amount' => '12.5'],
            ['amount' => 'abc'],
            ['type' => 'transfer'],
            ['tanggal' => '31-10-2026'],
            ['tanggal' => '2026-02-30'],
            ['description' => ''],
            ['category_id' => ''],
        ];

        foreach ($bad as $override) {
            $this->actingAs($admin)
                ->post(route('transactions.store', $org), $this->payload($cat, $override))
                ->assertSessionHasErrors(array_key_first($override));
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_kategori_harus_milik_organisasi_ini_dan_bukan_iuran(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2] = $this->makeOrg();
        $foreign = $this->category($org2, 'Milik Lain');
        $iuran = $this->category($org, 'Iuran', true);

        $this->actingAs($admin)->post(route('transactions.store', $org), $this->payload($foreign))
            ->assertSessionHasErrors('category_id');
        $this->actingAs($admin)->post(route('transactions.store', $org), $this->payload($iuran))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Transaction::count());
    }

    public function test_editor_mengubah_transaksi_manual_dan_tercatat(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $cat = $this->category($org);
        $t = $this->tx($org, $admin, $cat);

        $this->actingAs($editor)->get(route('transactions.edit', [$org, $t]))->assertOk();
        $this->actingAs($editor)
            ->patch(route('transactions.update', [$org, $t]), $this->payload($cat, [
                'amount' => 77000, 'description' => 'Sudah diubah', 'type' => 'masuk',
            ]))
            ->assertRedirect(route('transactions.index', $org));

        $t->refresh();
        $this->assertSame(77000, $t->amount);
        $this->assertSame('Sudah diubah', $t->description);
        $this->assertSame('masuk', $t->type);
        $this->assertEquals($admin->id, $t->created_by); // pencatat awal tidak berganti
        $this->assertTrue(ActivityLog::where('action', 'transaction.updated')->where('subject_id', $t->id)->exists());
    }

    public function test_anggota_tidak_bisa_mengubah_dan_transaksi_lain_organisasi_atau_terhapus_404(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2, $admin2] = $this->makeOrg();
        $cat = $this->category($org);
        $t = $this->tx($org, $admin, $cat);
        $foreign = $this->tx($org2, $admin2, $this->category($org2));
        $trashed = $this->tx($org, $admin, $cat, ['description' => 'Sudah dihapus']);
        $trashed->delete();
        $anggota = $this->userWithRole($org, 'anggota');

        $this->actingAs($anggota)->patch(route('transactions.update', [$org, $t]), $this->payload($cat))->assertForbidden();
        $this->actingAs($anggota)->get(route('transactions.edit', [$org, $t]))->assertForbidden();
        $this->actingAs($admin)->patch(route('transactions.update', [$org, $foreign]), $this->payload($cat))->assertNotFound();
        $this->actingAs($admin)->patch(route('transactions.update', [$org, $trashed]), $this->payload($cat))->assertNotFound();

        $this->assertSame('Rapat', $t->fresh()->description);
        $this->assertSame('Rapat', $foreign->fresh()->description);
    }

    public function test_transaksi_otomatis_dari_iuran_terkunci(): void
    {
        [$org, $admin] = $this->makeOrg();
        $locked = $this->lockedTx($org, $admin);
        $cat = $this->category($org);

        $this->actingAs($admin)->get(route('transactions.edit', [$org, $locked]))
            ->assertRedirect(route('transactions.index', $org));
        $this->actingAs($admin)
            ->patch(route('transactions.update', [$org, $locked]), $this->payload($cat, ['amount' => 1]))
            ->assertSessionHasErrors('transaction');
        $this->actingAs($admin)->delete(route('transactions.destroy', [$org, $locked]))
            ->assertSessionHasErrors('transaction');

        $fresh = $locked->fresh();
        $this->assertSame(25000, $fresh->amount);
        $this->assertNull($fresh->deleted_at);
    }

    public function test_admin_menghapus_soft_delete_dan_saldo_menyesuaikan(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $this->tx($org, $admin, $cat, ['type' => 'masuk', 'amount' => 120000, 'description' => 'Donasi']);
        $keluar = $this->tx($org, $admin, $cat, ['type' => 'keluar', 'amount' => 45000, 'description' => 'Belanja']);

        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertSee('Rp 75.000');

        $this->actingAs($admin)->delete(route('transactions.destroy', [$org, $keluar]))
            ->assertRedirect(route('transactions.index', $org));

        $this->assertSoftDeleted('transactions', ['id' => $keluar->id]);
        $this->actingAs($admin)->get(route('organizations.dashboard', $org))->assertDontSee('Rp 75.000');
        $this->actingAs($admin)->get(route('transactions.index', $org))->assertDontSee('Belanja');
        $this->assertTrue(ActivityLog::where('action', 'transaction.deleted')->where('subject_id', $keluar->id)->exists());
    }

    public function test_hanya_admin_yang_boleh_menghapus(): void
    {
        [$org, $admin] = $this->makeOrg();
        $t = $this->tx($org, $admin, $this->category($org));

        foreach (['editor', 'anggota'] as $role) {
            $this->actingAs($this->userWithRole($org, $role))
                ->delete(route('transactions.destroy', [$org, $t]))->assertForbidden();
        }
        $this->actingAs(User::factory()->create())
            ->delete(route('transactions.destroy', [$org, $t]))->assertForbidden();

        $this->assertNull($t->fresh()->deleted_at);
    }

    public function test_filter_bulan_jenis_dan_pencarian(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $this->tx($org, $admin, $cat, ['tanggal' => '2026-09-30', 'description' => 'Sewa aula']);
        $this->tx($org, $admin, $cat, ['tanggal' => '2026-10-01', 'description' => 'Awal Oktober']);
        $this->tx($org, $admin, $cat, ['tanggal' => '2026-10-31', 'description' => 'Akhir Oktober', 'type' => 'masuk']);
        $this->tx($org, $admin, $cat, ['tanggal' => '2026-11-01', 'description' => 'Awal November']);

        $this->actingAs($admin)->get(route('transactions.index', [$org, 'month' => '2026-10']))
            ->assertSee('Awal Oktober')->assertSee('Akhir Oktober')
            ->assertDontSee('Sewa aula')->assertDontSee('Awal November');

        $this->actingAs($admin)->get(route('transactions.index', [$org, 'month' => '2026-10', 'type' => 'masuk']))
            ->assertSee('Akhir Oktober')->assertDontSee('Awal Oktober');

        $this->actingAs($admin)->get(route('transactions.index', [$org, 'q' => 'aula']))
            ->assertSee('Sewa aula')->assertDontSee('Awal Oktober');
    }

    public function test_daftar_dibagi_per_halaman(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        for ($i = 1; $i <= 16; $i++) {
            $this->tx($org, $admin, $cat, [
                'tanggal' => sprintf('2026-10-%02d', $i),
                'description' => sprintf('Transaksi-ke-%02d', $i),
            ]);
        }

        $this->actingAs($admin)->get(route('transactions.index', $org))
            ->assertSee('Transaksi-ke-16')->assertDontSee('Transaksi-ke-01');
        $this->actingAs($admin)->get(route('transactions.index', [$org, 'page' => 2]))
            ->assertSee('Transaksi-ke-01')->assertDontSee('Transaksi-ke-16');
    }

    public function test_filter_bulan_yang_salah_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)->get(route('transactions.index', [$org, 'month' => 'bukan-bulan']))
            ->assertSessionHasErrors('month');
    }
}
