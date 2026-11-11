<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private User $editor;

    private User $member;

    private User $other;

    private OrganizationMember $adminM;

    private OrganizationMember $editorM;

    private OrganizationMember $memberM;

    private OrganizationMember $otherM;

    private Invoice $inv;      // tagihan $member

    private Invoice $otherInv; // tagihan $other

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->org = Organization::create([
            'name' => 'Org Uji', 'status' => 'aktif', 'opening_balance' => 0, 'default_dues' => 25000,
        ]);

        [$this->admin, $this->adminM] = $this->makeMember('Admin', 'admin', false);
        [$this->editor, $this->editorM] = $this->makeMember('Editor', 'editor', false);
        [$this->member, $this->memberM] = $this->makeMember('Dimas', 'anggota', true);
        [$this->other, $this->otherM] = $this->makeMember('Rina', 'anggota', true);

        $this->inv = $this->invoiceFor($this->memberM);
        $this->otherInv = $this->invoiceFor($this->otherM);
    }

    private function makeMember(string $name, string $role, bool $billable): array
    {
        $user = User::factory()->create(['name' => $name]);
        $m = $this->org->members()->create([
            'user_id' => $user->id, 'name' => $name, 'email' => $user->email,
            'role' => $role, 'is_billable' => $billable,
        ]);

        return [$user, $m];
    }

    private function invoiceFor(OrganizationMember $m): Invoice
    {
        return Invoice::create([
            'member_id' => $m->id, 'period' => '2026-10', 'amount' => 25000, 'status' => 'belum',
        ]);
    }

    private function pdf(string $name = 'bukti.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function submitAs(User $user, Invoice $inv, array $data = [])
    {
        return $this->actingAs($user)->post(
            route('invoices.submit', [$this->org, $inv->id]),
            $data + ['method' => 'transfer'],
        );
    }

    private function submitted(bool $withProof = true): Invoice
    {
        $this->submitAs($this->member, $this->inv, $withProof ? ['proof' => $this->pdf()] : []);

        return $this->inv->fresh();
    }

    // --- Pengajuan ---

    public function test_owner_can_submit_without_proof(): void
    {
        $this->submitAs($this->member, $this->inv)->assertSessionHasNoErrors();

        $inv = $this->inv->fresh();
        $this->assertSame('menunggu', $inv->status);
        $this->assertSame('transfer', $inv->method);
        $this->assertNull($inv->proof_path);
    }

    public function test_owner_can_submit_with_proof_stored_privately(): void
    {
        $inv = $this->submitted();

        $this->assertSame('menunggu', $inv->status);
        $this->assertNotNull($inv->proof_path);
        Storage::disk('local')->assertExists($inv->proof_path);
    }

    public function test_invalid_proof_type_size_and_method_are_rejected(): void
    {
        $this->submitAs($this->member, $this->inv, ['proof' => UploadedFile::fake()->create('x.txt', 10, 'text/plain')])
            ->assertSessionHasErrors('proof');
        $this->submitAs($this->member, $this->inv, ['proof' => $this->pdf('besar.pdf', 3000)])
            ->assertSessionHasErrors('proof');
        $this->submitAs($this->member, $this->inv, ['method' => 'kartu'])
            ->assertSessionHasErrors('method');

        $this->assertSame('belum', $this->inv->fresh()->status);
    }

    public function test_other_member_cannot_submit_someone_elses_invoice(): void
    {
        $this->submitAs($this->other, $this->inv)->assertForbidden();
        $this->assertSame('belum', $this->inv->fresh()->status);
    }

    public function test_invoice_of_another_organization_is_404(): void
    {
        $org2 = Organization::create(['name' => 'Org Lain', 'status' => 'aktif', 'opening_balance' => 0, 'default_dues' => 0]);
        $u2 = User::factory()->create();
        $m2 = $org2->members()->create(['user_id' => $u2->id, 'name' => 'X', 'email' => $u2->email, 'role' => 'anggota']);
        $inv2 = $this->invoiceFor($m2);

        $this->submitAs($this->member, $inv2)->assertNotFound();
    }

    public function test_cannot_submit_twice(): void
    {
        $this->submitted(false);

        $this->submitAs($this->member, $this->inv)->assertSessionHasErrors('invoice');
    }

    // --- Bukti ---

    public function test_replacing_proof_deletes_old_file(): void
    {
        $old = $this->submitted()->proof_path;

        $this->actingAs($this->member)->post(
            route('invoices.proof_store', [$this->org, $this->inv->id]),
            ['proof' => $this->pdf('baru.pdf')],
        )->assertSessionHasNoErrors();

        $new = $this->inv->fresh()->proof_path;
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_owner_can_remove_proof_while_waiting(): void
    {
        $path = $this->submitted()->proof_path;

        $this->actingAs($this->member)
            ->delete(route('invoices.proof_destroy', [$this->org, $this->inv->id]))
            ->assertSessionHasNoErrors();

        $inv = $this->inv->fresh();
        $this->assertSame('menunggu', $inv->status);
        $this->assertNull($inv->proof_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_owner_can_withdraw_and_file_is_deleted(): void
    {
        $path = $this->submitted()->proof_path;

        $this->actingAs($this->member)
            ->patch(route('invoices.withdraw', [$this->org, $this->inv->id]))
            ->assertSessionHasNoErrors();

        $inv = $this->inv->fresh();
        $this->assertSame('belum', $inv->status);
        $this->assertNull($inv->method);
        $this->assertNull($inv->proof_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_other_member_cannot_touch_proof_or_withdraw(): void
    {
        $this->submitted();

        $this->actingAs($this->other)
            ->post(route('invoices.proof_store', [$this->org, $this->inv->id]), ['proof' => $this->pdf()])
            ->assertForbidden();
        $this->actingAs($this->other)
            ->delete(route('invoices.proof_destroy', [$this->org, $this->inv->id]))
            ->assertForbidden();
        $this->actingAs($this->other)
            ->patch(route('invoices.withdraw', [$this->org, $this->inv->id]))
            ->assertForbidden();
    }

    // --- Unduh ---

    public function test_download_allowed_for_owner_and_managers_only(): void
    {
        $this->submitted();
        $url = route('invoices.proof_download', [$this->org, $this->inv->id]);

        $this->actingAs($this->member)->get($url)->assertOk();
        $this->actingAs($this->editor)->get($url)->assertOk();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $this->actingAs($this->other)->get($url)->assertForbidden();
    }

    public function test_download_of_missing_file_redirects_with_message(): void
    {
        $this->inv->update(['status' => 'menunggu', 'method' => 'transfer', 'proof_path' => 'proofs/tidak-ada.svg']);

        $this->actingAs($this->member)
            ->get(route('invoices.proof_download', [$this->org, $this->inv->id]))
            ->assertRedirect()
            ->assertSessionHasErrors('invoice');

        $this->actingAs($this->editor)
            ->get(route('invoices.index', $this->org))
            ->assertOk()
            ->assertSee('Berkas bukti tidak tersedia');
    }

    // --- Verifikasi ---

    public function test_editor_approves_and_transaction_is_created(): void
    {
        $this->submitted();

        $this->actingAs($this->editor)
            ->patch(route('invoices.approve', [$this->org, $this->inv->id]))
            ->assertSessionHasNoErrors();

        $inv = $this->inv->fresh();
        $this->assertSame('lunas', $inv->status);
        $this->assertSame('transfer', $inv->method);

        $tx = Transaction::where('invoice_id', $inv->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('masuk', $tx->type);
        $this->assertSame(25000, (int) $tx->amount);
        $this->assertSame($this->editor->id, $tx->created_by);
    }

    public function test_plain_member_cannot_approve_or_reject(): void
    {
        $this->submitted();

        $this->actingAs($this->other)
            ->patch(route('invoices.approve', [$this->org, $this->inv->id]))
            ->assertForbidden();
        $this->actingAs($this->other)
            ->patch(route('invoices.reject', [$this->org, $this->inv->id]), ['reason' => 'x'])
            ->assertForbidden();

        $this->assertSame('menunggu', $this->inv->fresh()->status);
    }

    public function test_reject_requires_reason_then_member_can_resubmit(): void
    {
        $path = $this->submitted()->proof_path;
        $url = route('invoices.reject', [$this->org, $this->inv->id]);

        $this->actingAs($this->admin)->patch($url, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame('menunggu', $this->inv->fresh()->status);

        $this->actingAs($this->admin)->patch($url, ['reason' => 'Bukti buram'])->assertSessionHasNoErrors();

        $inv = $this->inv->fresh();
        $this->assertSame('belum', $inv->status);
        $this->assertSame('Bukti buram', $inv->rejection_reason);
        $this->assertNull($inv->proof_path);
        Storage::disk('local')->assertMissing($path);

        $this->submitAs($this->member, $this->inv, ['proof' => $this->pdf()])->assertSessionHasNoErrors();

        $inv = $this->inv->fresh();
        $this->assertSame('menunggu', $inv->status);
        $this->assertNull($inv->rejection_reason);
    }

    public function test_billable_manager_cannot_approve_or_reject_own_submission_but_other_manager_can(): void
    {
        $this->editorM->update(['is_billable' => true]);
        $own = $this->invoiceFor($this->editorM);

        $this->submitAs($this->editor, $own)->assertSessionHasNoErrors();
        $this->assertSame('menunggu', $own->fresh()->status);

        $this->actingAs($this->editor)
            ->patch(route('invoices.approve', [$this->org, $own->id]))
            ->assertSessionHasErrors('invoice');
        $this->actingAs($this->editor)
            ->patch(route('invoices.reject', [$this->org, $own->id]), ['reason' => 'x'])
            ->assertSessionHasErrors('invoice');
        $this->assertSame('menunggu', $own->fresh()->status);

        $this->actingAs($this->admin)
            ->patch(route('invoices.approve', [$this->org, $own->id]))
            ->assertSessionHasNoErrors();
        $this->assertSame('lunas', $own->fresh()->status);
    }

    // --- Terkunci setelah lunas ---

    public function test_proof_is_locked_after_approval_and_cancel_clears_it(): void
    {
        $path = $this->submitted()->proof_path;

        $this->actingAs($this->editor)->patch(route('invoices.approve', [$this->org, $this->inv->id]));

        $this->actingAs($this->member)
            ->post(route('invoices.proof_store', [$this->org, $this->inv->id]), ['proof' => $this->pdf('lain.pdf')])
            ->assertSessionHasErrors('invoice');
        $this->actingAs($this->member)
            ->delete(route('invoices.proof_destroy', [$this->org, $this->inv->id]))
            ->assertSessionHasErrors('invoice');
        $this->actingAs($this->member)
            ->patch(route('invoices.withdraw', [$this->org, $this->inv->id]))
            ->assertSessionHasErrors('invoice');

        $this->assertSame($path, $this->inv->fresh()->proof_path);
        Storage::disk('local')->assertExists($path);

        // Batalkan pelunasan: bukti ikut dibuang
        $this->actingAs($this->editor)
            ->patch(route('invoices.cancel', [$this->org, $this->inv->id]))
            ->assertSessionHasNoErrors();

        $this->assertNull($this->inv->fresh()->proof_path);
        Storage::disk('local')->assertMissing($path);
    }

    // --- Tampilan ---

    public function test_views_show_submit_button_and_verifier_labels(): void
    {
        $this->actingAs($this->member)
            ->get(route('invoices.index', $this->org))
            ->assertOk()
            ->assertSee('Saya sudah bayar')
            ->assertDontSee('Untuk saat ini pelunasan dicatat');

        $this->submitAs($this->member, $this->inv); // tanpa bukti

        $this->actingAs($this->editor)
            ->get(route('invoices.index', $this->org))
            ->assertOk()
            ->assertSee('Tanpa bukti')
            ->assertSee('Setujui')
            ->assertSee('Tolak');
    }
}
