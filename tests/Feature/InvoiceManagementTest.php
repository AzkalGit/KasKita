<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
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
        $org->categories()->create(['name' => 'Iuran', 'is_system' => true]);

        return [$org, $admin];
    }

    /** @return array{0: User, 1: OrganizationMember} */
    private function memberWithRole(Organization $org, string $role): array
    {
        $user = User::factory()->create();
        $member = $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
        ]);

        return [$user, $member];
    }

    // Keanggotaan milik Admin pembuat organisasi (is_billable=false dari makeOrg)
    private function adminMember(Organization $org, User $admin): OrganizationMember
    {
        return $org->members()->where('user_id', $admin->id)->firstOrFail();
    }

    // Kontak tanpa akun yang ditagih
    private function contact(Organization $org, string $name, array $override = []): OrganizationMember
    {
        return $org->members()->create(array_merge(['name' => $name], $override));
    }

    private function invoice(OrganizationMember $member, string $period = '2026-10', int $amount = 25000, string $status = 'belum'): Invoice
    {
        return Invoice::create([
            'member_id' => $member->id, 'period' => $period, 'amount' => $amount, 'status' => $status,
        ]);
    }

    public function test_semua_anggota_aktif_membuka_halaman_dan_bukan_anggota_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor] = $this->memberWithRole($org, 'editor');
        [$anggota] = $this->memberWithRole($org, 'anggota');
        $outsider = User::factory()->create();

        foreach ([$admin, $editor, $anggota] as $user) {
            $this->actingAs($user)->get(route('invoices.index', $org))->assertOk();
        }
        $this->actingAs($outsider)->get(route('invoices.index', $org))->assertForbidden();
    }

    public function test_admin_melihat_semua_tagihan_sedangkan_anggota_hanya_miliknya(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$anggota, $mAnggota] = $this->memberWithRole($org, 'anggota');
        $budi = $this->contact($org, 'Budi Kontak');
        $this->invoice($mAnggota, '2026-10', 31000);
        $this->invoice($budi, '2026-10', 47000);

        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('Budi Kontak')->assertSee('Rp 31.000')->assertSee('Rp 47.000');

        $this->actingAs($anggota)->get(route('invoices.index', $org))
            ->assertSee('Rp 31.000')
            ->assertDontSee('Budi Kontak')->assertDontSee('Rp 47.000');
    }

    public function test_editor_melihat_semua_tagihan_satu_periode(): void
    {
        [$org] = $this->makeOrg();
        [$editor] = $this->memberWithRole($org, 'editor');
        $this->invoice($this->contact($org, 'Budi Kontak'), '2026-10', 47000);
        $this->invoice($this->contact($org, 'Sinta Kontak'), '2026-10', 53000);

        $this->actingAs($editor)->get(route('invoices.index', $org))
            ->assertSee('Budi Kontak')->assertSee('Sinta Kontak')
            ->assertSee('Rp 47.000')->assertSee('Rp 53.000');
    }

    public function test_anggota_melihat_tagihannya_dari_semua_bulan(): void
    {
        [$org] = $this->makeOrg();
        [$anggota, $mAnggota] = $this->memberWithRole($org, 'anggota');
        $this->invoice($mAnggota, '2026-09');
        $this->invoice($mAnggota, '2026-10');

        $this->actingAs($anggota)->get(route('invoices.index', $org))
            ->assertSee('September 2026')->assertSee('Oktober 2026');
    }

    public function test_tombol_mengikuti_peran(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor] = $this->memberWithRole($org, 'editor');
        [$anggota, $mAnggota] = $this->memberWithRole($org, 'anggota');
        $this->invoice($mAnggota);

        foreach ([$admin, $editor] as $pengelola) {
            $this->actingAs($pengelola)->get(route('invoices.index', $org))
                ->assertSee('Buat tagihan')->assertSee('Lunas tunai');
        }

        $this->actingAs($anggota)->get(route('invoices.index', $org))
            ->assertDontSee('Buat tagihan')->assertDontSee('Lunas tunai')->assertDontSee('Batalkan pelunasan');
    }

    public function test_admin_membuat_tagihan_hanya_untuk_anggota_aktif_yang_ditagih(): void
    {
        [$org, $admin] = $this->makeOrg();
        [, $mAnggota] = $this->memberWithRole($org, 'anggota');
        $budi = $this->contact($org, 'Budi Kontak');
        $this->contact($org, 'Tidak Ditagih', ['is_billable' => false]);
        $this->contact($org, 'Sudah Nonaktif', ['is_active' => false]);

        $this->actingAs($admin)
            ->post(route('invoices.store', $org), ['period' => '2026-10', 'amount' => 25000])
            ->assertRedirect(route('invoices.index', [$org, 'period' => '2026-10']));

        // Admin (tidak ditagih), kontak tidak ditagih, dan kontak nonaktif tidak dapat tagihan
        $this->assertEqualsCanonicalizing([$mAnggota->id, $budi->id], Invoice::pluck('member_id')->all());
        $this->assertSame(2, Invoice::where('period', '2026-10')->where('status', 'belum')->where('amount', 25000)->count());
        $this->assertTrue(ActivityLog::where('action', 'invoice.generated')->where('organization_id', $org->id)->exists());
    }

    public function test_membuat_tagihan_dua_kali_tidak_menggandakan_dan_tidak_mengubah_jumlah(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->contact($org, 'Budi Kontak');

        $this->actingAs($admin)->post(route('invoices.store', $org), ['period' => '2026-10', 'amount' => 25000]);
        $this->actingAs($admin)->post(route('invoices.store', $org), ['period' => '2026-10', 'amount' => 99000]);

        $this->assertSame(1, Invoice::count());
        $this->assertSame(25000, Invoice::first()->amount);

        // Anggota baru hanya mendapat tagihan miliknya saat dibuat ulang
        $this->contact($org, 'Sinta Kontak');
        $this->actingAs($admin)->post(route('invoices.store', $org), ['period' => '2026-10', 'amount' => 25000]);

        $this->assertSame(2, Invoice::count());
    }

    public function test_isian_tagihan_yang_tidak_valid_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->contact($org, 'Budi Kontak');

        $valid = ['period' => '2026-10', 'amount' => 25000];
        $bad = [
            ['period' => ''],
            ['period' => '2026-13'],
            ['period' => '2026-10-01'],
            ['period' => 'bukan-bulan'],
            ['amount' => 0],
            ['amount' => -5],
            ['amount' => '12.5'],
            ['amount' => 'abc'],
            ['amount' => ''],
        ];

        foreach ($bad as $override) {
            $this->actingAs($admin)
                ->post(route('invoices.store', $org), array_merge($valid, $override))
                ->assertSessionHasErrors(array_key_first($override));
        }

        $this->assertSame(0, Invoice::count());
    }

    public function test_admin_dan_editor_boleh_membuat_tagihan_anggota_dan_bukan_anggota_ditolak(): void
    {
        [$org] = $this->makeOrg();
        $this->contact($org, 'Budi Kontak');
        [$editor] = $this->memberWithRole($org, 'editor');
        [$anggota] = $this->memberWithRole($org, 'anggota');
        $outsider = User::factory()->create();

        foreach ([$anggota, $outsider] as $user) {
            $this->actingAs($user)
                ->post(route('invoices.store', $org), ['period' => '2026-10', 'amount' => 25000])
                ->assertForbidden();
        }
        $this->assertSame(0, Invoice::count());

        // E2: Editor boleh (Budi dan Editor sendiri ditagih secara bawaan, Anggota juga)
        $this->actingAs($editor)
            ->post(route('invoices.store', $org), ['period' => '2026-10', 'amount' => 25000])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('invoices.index', [$org, 'period' => '2026-10']));

        $this->assertGreaterThan(0, Invoice::count());
    }

    public function test_lunas_tunai_menandai_lunas_dan_membuat_transaksi_otomatis_tanpa_nama(): void
    {
        [$org, $admin] = $this->makeOrg();
        $budi = $this->contact($org, 'Budi Kontak');
        $invoice = $this->invoice($budi, '2026-10', 25000);

        $this->actingAs($admin)
            ->patch(route('invoices.pay_cash', [$org, $invoice]))
            ->assertRedirect(route('invoices.index', [$org, 'period' => '2026-10']));

        $invoice->refresh();
        $this->assertSame('lunas', $invoice->status);
        $this->assertSame('tunai', $invoice->method);
        $this->assertNotNull($invoice->paid_at);

        $tx = Transaction::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals($org->id, $tx->organization_id);
        $this->assertEquals($admin->id, $tx->created_by);
        $this->assertSame('masuk', $tx->type);
        $this->assertSame(25000, $tx->amount);
        $this->assertSame('Iuran Oktober 2026', $tx->description); // D2: tanpa nama anggota
        $this->assertStringNotContainsString('Budi', $tx->description);
        $this->assertSame('Iuran', $tx->category->name);
        $this->assertTrue((bool) $tx->category->is_system);
        $this->assertTrue($tx->isLocked());
        $this->assertTrue(ActivityLog::where('action', 'invoice.paid_cash')->where('subject_id', $invoice->id)->exists());
    }

    public function test_lunas_tunai_dua_kali_ditolak_dan_transaksi_tidak_ganda(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->invoice($this->contact($org, 'Budi Kontak'));

        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $invoice]));
        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $invoice]))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Transaction::where('invoice_id', $invoice->id)->count());
    }

    public function test_tagihan_menunggu_verifikasi_bisa_dilunasi_tunai(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->invoice($this->contact($org, 'Budi Kontak'), '2026-10', 25000, 'menunggu');

        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $invoice]))
            ->assertSessionHasNoErrors();

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame(1, Transaction::where('invoice_id', $invoice->id)->count());
    }

    public function test_batalkan_pelunasan_menghapus_transaksi_otomatis_dan_bisa_dilunasi_lagi(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->invoice($this->contact($org, 'Budi Kontak'));

        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $invoice]));
        $this->actingAs($admin)->patch(route('invoices.cancel', [$org, $invoice]))
            ->assertRedirect(route('invoices.index', [$org, 'period' => '2026-10']));

        $invoice->refresh();
        $this->assertSame('belum', $invoice->status);
        $this->assertNull($invoice->method);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(0, Transaction::withTrashed()->where('invoice_id', $invoice->id)->count());
        $this->assertTrue(ActivityLog::where('action', 'invoice.payment_cancelled')->where('subject_id', $invoice->id)->exists());

        // invoice_id unik: pelunasan ulang harus tetap berhasil
        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $invoice]))->assertSessionHasNoErrors();
        $this->assertSame(1, Transaction::where('invoice_id', $invoice->id)->count());
    }

    public function test_batalkan_tagihan_yang_belum_lunas_ditolak(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->invoice($this->contact($org, 'Budi Kontak'));

        $this->actingAs($admin)->patch(route('invoices.cancel', [$org, $invoice]))
            ->assertSessionHasErrors('invoice');

        $this->assertSame('belum', $invoice->fresh()->status);
    }

    public function test_anggota_dan_bukan_anggota_tidak_bisa_melunasi_atau_membatalkan(): void
    {
        [$org, $admin] = $this->makeOrg();
        $pending = $this->invoice($this->contact($org, 'Budi Kontak'));
        $paid = $this->invoice($this->contact($org, 'Sinta Kontak'));
        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $paid]));

        [$anggota] = $this->memberWithRole($org, 'anggota');
        $outsider = User::factory()->create();

        foreach ([$anggota, $outsider] as $user) {
            $this->actingAs($user)->patch(route('invoices.pay_cash', [$org, $pending]))->assertForbidden();
            $this->actingAs($user)->patch(route('invoices.cancel', [$org, $paid]))->assertForbidden();
        }

        $this->assertSame('belum', $pending->fresh()->status);
        $this->assertSame('lunas', $paid->fresh()->status);
        $this->assertSame(1, Transaction::count());
    }

    public function test_editor_melunasi_dan_membatalkan_tagihan_orang_lain(): void
    {
        [$org] = $this->makeOrg();
        [$editor] = $this->memberWithRole($org, 'editor');
        $invoice = $this->invoice($this->contact($org, 'Budi Kontak'));

        $this->actingAs($editor)->patch(route('invoices.pay_cash', [$org, $invoice]))
            ->assertSessionHasNoErrors();

        $this->assertSame('lunas', $invoice->fresh()->status);
        $tx = Transaction::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals($editor->id, $tx->created_by);

        $this->actingAs($editor)->patch(route('invoices.cancel', [$org, $invoice]))
            ->assertSessionHasNoErrors();

        $this->assertSame('belum', $invoice->fresh()->status);
        $this->assertSame(0, Transaction::withTrashed()->where('invoice_id', $invoice->id)->count());
    }

    public function test_pengelola_tidak_bisa_melunasi_tagihannya_sendiri(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor, $mEditor] = $this->memberWithRole($org, 'editor');
        $mAdmin = $this->adminMember($org, $admin);
        $mAdmin->update(['is_billable' => true]); // D3: Admin yang ingin ikut membayar

        $own = [
            [$admin, $this->invoice($mAdmin)],
            [$editor, $this->invoice($mEditor)],
        ];

        foreach ($own as [$user, $invoice]) {
            $this->actingAs($user)->patch(route('invoices.pay_cash', [$org, $invoice]))
                ->assertSessionHasErrors('invoice');

            $this->assertSame('belum', $invoice->fresh()->status);
        }

        $this->assertSame(0, Transaction::count());
    }

    public function test_pengelola_tidak_bisa_membatalkan_pelunasan_tagihannya_sendiri(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor, $mEditor] = $this->memberWithRole($org, 'editor');
        $mAdmin = $this->adminMember($org, $admin);
        $mAdmin->update(['is_billable' => true]);

        $adminInvoice = $this->invoice($mAdmin);
        $editorInvoice = $this->invoice($mEditor);

        // Masing-masing dilunasi oleh pengelola lain
        $this->actingAs($editor)->patch(route('invoices.pay_cash', [$org, $adminInvoice]))->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $editorInvoice]))->assertSessionHasNoErrors();

        // ... tetapi tidak bisa dibatalkan oleh pemiliknya sendiri
        $this->actingAs($admin)->patch(route('invoices.cancel', [$org, $adminInvoice]))->assertSessionHasErrors('invoice');
        $this->actingAs($editor)->patch(route('invoices.cancel', [$org, $editorInvoice]))->assertSessionHasErrors('invoice');

        $this->assertSame('lunas', $adminInvoice->fresh()->status);
        $this->assertSame('lunas', $editorInvoice->fresh()->status);
        $this->assertSame(2, Transaction::count());
    }

    public function test_admin_dan_editor_saling_memverifikasi_tagihan_masing_masing(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor, $mEditor] = $this->memberWithRole($org, 'editor');
        $mAdmin = $this->adminMember($org, $admin);
        $mAdmin->update(['is_billable' => true]);

        $adminInvoice = $this->invoice($mAdmin);
        $editorInvoice = $this->invoice($mEditor);

        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $editorInvoice]))->assertSessionHasNoErrors();
        $this->actingAs($editor)->patch(route('invoices.pay_cash', [$org, $adminInvoice]))->assertSessionHasNoErrors();

        $this->assertSame('lunas', $adminInvoice->fresh()->status);
        $this->assertSame('lunas', $editorInvoice->fresh()->status);

        // Admin boleh membatalkan pelunasan Editor (bukan miliknya)
        $this->actingAs($admin)->patch(route('invoices.cancel', [$org, $editorInvoice]))->assertSessionHasNoErrors();
        $this->assertSame('belum', $editorInvoice->fresh()->status);
    }

    public function test_tagihan_sendiri_tanpa_tombol_dan_ada_keterangan(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$editor] = $this->memberWithRole($org, 'editor');
        $mAdmin = $this->adminMember($org, $admin);
        $mAdmin->update(['is_billable' => true]);
        $this->invoice($mAdmin, status: 'menunggu');

        // Admin melihat tagihannya sendiri: tanpa tombol, ada keterangan menunggu pengelola lain
        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('(tagihanmu)')
            ->assertSee('Menunggu diverifikasi pengelola lain')
            ->assertDontSee('Lunas tunai');

        // Editor melihat tagihan Admin: tombol tersedia
        $this->actingAs($editor)->get(route('invoices.index', $org))
            ->assertSee('Lunas tunai')
            ->assertDontSee('(tagihanmu)');
    }

    public function test_pesan_bila_tidak_ada_pengelola_lain_aktif(): void
    {
        [$org, $admin] = $this->makeOrg();
        $mAdmin = $this->adminMember($org, $admin);
        $mAdmin->update(['is_billable' => true]);
        $this->invoice($mAdmin, status: 'menunggu');

        // Admin sendirian
        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('Belum ada pengelola lain')
            ->assertDontSee('Lunas tunai');

        // Editor nonaktif tidak dihitung
        $user = User::factory()->create();
        $mEditor = $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email,
            'role' => 'editor', 'is_active' => false,
        ]);
        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('Belum ada pengelola lain');

        // Setelah aktif, pesannya berganti
        $mEditor->update(['is_active' => true]);
        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('Menunggu diverifikasi pengelola lain')
            ->assertDontSee('Belum ada pengelola lain');
    }

    public function test_tagihan_sendiri_belum_diajukan_tidak_menampilkan_pesan_menunggu(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->memberWithRole($org, 'editor');
        $mAdmin = $this->adminMember($org, $admin);
        $mAdmin->update(['is_billable' => true]);
        $this->invoice($mAdmin);

        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('(tagihanmu)')
            ->assertDontSee('Menunggu diverifikasi pengelola lain')
            ->assertDontSee('Belum ada pengelola lain')
            ->assertSee('Pengelola lain bisa melunasi');
    }

    public function test_tagihan_organisasi_lain_menghasilkan_404(): void
    {
        [$org, $admin] = $this->makeOrg();
        [$org2] = $this->makeOrg();
        $foreign = $this->invoice($this->contact($org2, 'Milik Lain'));

        $this->actingAs($admin)->patch(route('invoices.pay_cash', [$org, $foreign]))->assertNotFound();
        $this->actingAs($admin)->patch(route('invoices.cancel', [$org, $foreign]))->assertNotFound();

        $this->assertSame('belum', $foreign->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_admin_memilih_bulan_dan_bawaannya_bulan_terbaru(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->invoice($this->contact($org, 'Budi Kontak'), '2026-09', 11000);
        $this->invoice($this->contact($org, 'Sinta Kontak'), '2026-10', 22000);

        $this->actingAs($admin)->get(route('invoices.index', $org))
            ->assertSee('Sinta Kontak')->assertDontSee('Budi Kontak');

        $this->actingAs($admin)->get(route('invoices.index', [$org, 'period' => '2026-09']))
            ->assertSee('Budi Kontak')->assertDontSee('Sinta Kontak');

        $this->actingAs($admin)->get(route('invoices.index', [$org, 'period' => 'bukan-bulan']))
            ->assertSessionHasErrors('period');
    }
}
