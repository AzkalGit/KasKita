<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Organization, 1: User, 2: Category} */
    private function makeOrg(): array
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => 'Org Uji', 'created_by' => $admin->id]);
        $org->members()->create([
            'user_id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email,
            'role' => 'admin', 'is_billable' => false,
        ]);
        $iuran = $org->categories()->create(['name' => 'Iuran', 'is_system' => true]);

        return [$org, $admin, $iuran];
    }

    private function userWithRole(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
        ]);

        return $user;
    }

    private function transactionIn(Organization $org, User $by, Category $category): Transaction
    {
        return Transaction::create([
            'organization_id' => $org->id, 'category_id' => $category->id, 'created_by' => $by->id,
            'type' => 'keluar', 'amount' => 1000, 'tanggal' => '2026-10-10', 'description' => 'Rapat',
        ]);
    }

    public function test_hanya_admin_melihat_halaman_kategori(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $anggota = $this->userWithRole($org, 'anggota');
        $outsider = User::factory()->create();

        $this->actingAs($admin)->get(route('categories.index', $org))->assertOk()->assertSee('Iuran');
        $this->actingAs($editor)->get(route('categories.index', $org))->assertForbidden();
        $this->actingAs($anggota)->get(route('categories.index', $org))->assertForbidden();
        $this->actingAs($outsider)->get(route('categories.index', $org))->assertForbidden();
    }

    public function test_admin_menambah_kategori_dan_tercatat(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)
            ->post(route('categories.store', $org), ['name' => '  Konsumsi  '])
            ->assertRedirect(route('categories.index', $org));

        $this->assertDatabaseHas('categories', [
            'organization_id' => $org->id, 'name' => 'Konsumsi', 'is_system' => 0,
        ]);
        $this->assertTrue(ActivityLog::where('action', 'category.created')->where('organization_id', $org->id)->exists());
    }

    public function test_nama_kembar_ditolak_tanpa_peka_huruf_besar_dan_hanya_dalam_satu_organisasi(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2, $admin2] = $this->makeOrg();
        $org->categories()->create(['name' => 'Konsumsi']);

        $this->actingAs($admin)
            ->post(route('categories.store', $org), ['name' => 'KONSUMSI'])
            ->assertSessionHasErrors('name');
        $this->actingAs($admin)
            ->post(route('categories.store', $org), ['name' => 'iuran'])
            ->assertSessionHasErrors('name');
        $this->assertSame(1, $org->categories()->whereRaw('LOWER(name) = ?', ['konsumsi'])->count());

        // Organisasi lain boleh memakai nama yang sama
        $this->actingAs($admin2)
            ->post(route('categories.store', $org2), ['name' => 'Konsumsi'])
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(1, $org2->categories()->where('name', 'Konsumsi')->count());
    }

    public function test_editor_dan_anggota_tidak_bisa_menambah_mengubah_atau_menghapus(): void
    {
        [$org] = $this->makeOrg();
        $target = $org->categories()->create(['name' => 'Konsumsi']);

        foreach (['editor', 'anggota'] as $role) {
            $user = $this->userWithRole($org, $role);

            $this->actingAs($user)->post(route('categories.store', $org), ['name' => 'Penyusup'])->assertForbidden();
            $this->actingAs($user)->patch(route('categories.update', [$org, $target]), ['name' => 'Diubah'])->assertForbidden();
            $this->actingAs($user)->delete(route('categories.destroy', [$org, $target]))->assertForbidden();
        }

        $this->assertDatabaseMissing('categories', ['name' => 'Penyusup']);
        $this->assertDatabaseMissing('categories', ['name' => 'Diubah']);
        $this->assertDatabaseHas('categories', ['id' => $target->id, 'name' => 'Konsumsi']);
    }

    public function test_admin_mengubah_nama_kategori(): void
    {
        [$org, $admin] = $this->makeOrg();
        $category = $org->categories()->create(['name' => 'Konsumsi']);

        $this->actingAs($admin)
            ->patch(route('categories.update', [$org, $category]), ['name' => 'Makanan'])
            ->assertRedirect(route('categories.index', $org));

        $this->assertSame('Makanan', $category->fresh()->name);
        $this->assertTrue(
            ActivityLog::where('action', 'category.renamed')->where('subject_id', $category->id)->exists()
        );
    }

    public function test_mengganti_huruf_besar_kecil_nama_sendiri_tidak_dianggap_kembar(): void
    {
        [$org, $admin] = $this->makeOrg();
        $category = $org->categories()->create(['name' => 'Konsumsi']);

        $this->actingAs($admin)
            ->patch(route('categories.update', [$org, $category]), ['name' => 'konsumsi'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('konsumsi', $category->fresh()->name);
    }

    public function test_kategori_iuran_tidak_bisa_diubah_atau_dihapus(): void
    {
        [$org, $admin, $iuran] = $this->makeOrg();

        $this->actingAs($admin)
            ->patch(route('categories.update', [$org, $iuran]), ['name' => 'Sumbangan'])
            ->assertSessionHasErrors('category');
        $this->actingAs($admin)
            ->delete(route('categories.destroy', [$org, $iuran]))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $iuran->id, 'name' => 'Iuran']);
    }

    public function test_kategori_yang_dipakai_transaksi_tidak_bisa_dihapus(): void
    {
        [$org, $admin] = $this->makeOrg();
        $category = $org->categories()->create(['name' => 'Konsumsi']);
        $transaction = $this->transactionIn($org, $admin, $category);

        $this->actingAs($admin)
            ->delete(route('categories.destroy', [$org, $category]))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        // Transaksi yang sudah dihapus (soft delete) tetap menahan kategorinya
        $transaction->delete();
        $this->actingAs($admin)
            ->delete(route('categories.destroy', [$org, $category]))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_kategori_kosong_bisa_dihapus(): void
    {
        [$org, $admin] = $this->makeOrg();
        $category = $org->categories()->create(['name' => 'Konsumsi']);

        $this->actingAs($admin)
            ->delete(route('categories.destroy', [$org, $category]))
            ->assertRedirect(route('categories.index', $org));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertTrue(ActivityLog::where('action', 'category.deleted')->where('organization_id', $org->id)->exists());
    }

    public function test_kategori_organisasi_lain_menghasilkan_404(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2] = $this->makeOrg();
        $foreign = $org2->categories()->create(['name' => 'Milik Lain']);

        $this->actingAs($admin)->patch(route('categories.update', [$org, $foreign]), ['name' => 'X'])->assertNotFound();
        $this->actingAs($admin)->delete(route('categories.destroy', [$org, $foreign]))->assertNotFound();

        $this->assertDatabaseHas('categories', ['id' => $foreign->id, 'name' => 'Milik Lain']);
    }

    public function test_nama_wajib_diisi_dan_dibatasi_100_karakter(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)->post(route('categories.store', $org), ['name' => '   '])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('categories.store', $org), ['name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post(route('categories.store', $org), ['name' => str_repeat('a', 100)])->assertSessionDoesntHaveErrors();
    }
}
