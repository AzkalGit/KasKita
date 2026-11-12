<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PeriodClosure;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 10:00:00')); // bulan berjalan = 2026-11
    }

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

    private function userWithRole(Organization $org, string $role): User
    {
        $user = User::factory()->create();
        $org->members()->create([
            'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $role,
        ]);

        return $user;
    }

    private function category(Organization $org): Category
    {
        return $org->categories()->create(['name' => 'Operasional', 'is_system' => false]);
    }

    private function close(Organization $org, User $by, string $period): PeriodClosure
    {
        return PeriodClosure::create([
            'organization_id' => $org->id, 'period' => $period,
            'closed_by' => $by->id, 'closed_at' => now(),
        ]);
    }

    private function txn(Organization $org, User $by, Category $cat, string $tanggal, array $extra = []): Transaction
    {
        return Transaction::create(array_merge([
            'organization_id' => $org->id, 'category_id' => $cat->id, 'created_by' => $by->id,
            'type' => 'keluar', 'amount' => 10000, 'tanggal' => $tanggal, 'description' => 'Beli ATK',
        ], $extra));
    }

    private function payload(Category $cat, string $tanggal): array
    {
        return [
            'type' => 'keluar', 'category_id' => $cat->id, 'amount' => 5000,
            'tanggal' => $tanggal, 'description' => 'Uji',
        ];
    }

    private function contactInvoice(Organization $org, string $status = 'belum', array $extra = []): Invoice
    {
        $member = $org->members()->create(['name' => 'Sinta']);

        return Invoice::create(array_merge([
            'member_id' => $member->id, 'period' => '2026-10', 'amount' => 25000, 'status' => $status,
        ], $extra));
    }

    // ---------- Menutup dan membuka kembali ----------

    public function test_admin_can_close_a_past_month_and_it_is_logged(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)
            ->post(route('periods.close', $org), ['period' => '2026-10'])
            ->assertSessionHas('status');

        $this->assertTrue($org->isPeriodClosed('2026-10-15'));
        $this->assertDatabaseHas('period_closures', [
            'organization_id' => $org->id, 'period' => '2026-10', 'closed_by' => $admin->id,
        ]);
        $this->assertTrue(ActivityLog::where('organization_id', $org->id)->where('action', 'period.closed')->exists());
    }

    public function test_current_and_future_months_cannot_be_closed(): void
    {
        [$org, $admin] = $this->makeOrg();

        foreach (['2026-11', '2026-12'] as $period) {
            $this->actingAs($admin)
                ->post(route('periods.close', $org), ['period' => $period])
                ->assertSessionHasErrors('period');
        }

        $this->assertSame(0, PeriodClosure::count());
    }

    public function test_invalid_period_is_rejected(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)
            ->post(route('periods.close', $org), ['period' => '2026-13'])
            ->assertSessionHasErrors('period');

        $this->assertSame(0, PeriodClosure::count());
    }

    public function test_closing_twice_is_refused_without_duplicate(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->close($org, $admin, '2026-10');

        $this->actingAs($admin)
            ->post(route('periods.close', $org), ['period' => '2026-10'])
            ->assertSessionHasErrors('period');

        $this->assertSame(1, PeriodClosure::count());
    }

    public function test_admin_can_reopen_and_it_is_logged(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->close($org, $admin, '2026-10');

        $this->actingAs($admin)
            ->delete(route('periods.reopen', [$org, '2026-10']))
            ->assertSessionHas('status');

        $this->assertFalse($org->isPeriodClosed('2026-10-15'));
        $this->assertTrue(ActivityLog::where('organization_id', $org->id)->where('action', 'period.reopened')->exists());
    }

    public function test_reopening_a_month_that_is_not_closed_is_refused(): void
    {
        [$org, $admin] = $this->makeOrg();

        $this->actingAs($admin)
            ->delete(route('periods.reopen', [$org, '2026-10']))
            ->assertSessionHasErrors('period');

        $this->assertFalse(ActivityLog::where('action', 'period.reopened')->exists());
    }

    public function test_editor_and_member_cannot_close_or_reopen(): void
    {
        [$org, $admin] = $this->makeOrg();
        $this->close($org, $admin, '2026-09');

        foreach (['editor', 'anggota'] as $role) {
            $user = $this->userWithRole($org, $role);

            $this->actingAs($user)->post(route('periods.close', $org), ['period' => '2026-10'])->assertForbidden();
            $this->actingAs($user)->delete(route('periods.reopen', [$org, '2026-09']))->assertForbidden();
        }

        $this->assertSame(1, PeriodClosure::count());
        $this->assertTrue($org->isPeriodClosed('2026-09-01'));
    }

    // ---------- Isolasi antar organisasi ----------

    public function test_closure_in_one_organization_does_not_affect_another(): void
    {
        [$orgA, $adminA] = $this->makeOrg();
        [$orgB, $adminB] = $this->makeOrg();
        $this->close($orgA, $adminA, '2026-10');
        $cat = $this->category($orgB);

        $this->actingAs($adminB)
            ->post(route('transactions.store', $orgB), $this->payload($cat, '2026-10-15'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $orgB->transactions()->count());
    }

    public function test_admin_of_another_organization_cannot_close_this_one(): void
    {
        [$orgA] = $this->makeOrg();
        [, $adminB] = $this->makeOrg();

        $response = $this->actingAs($adminB)->post(route('periods.close', $orgA), ['period' => '2026-10']);

        $this->assertContains($response->status(), [403, 404]);
        $this->assertSame(0, PeriodClosure::count());
    }

    // ---------- Penjaga di transaksi ----------

    public function test_cannot_create_transaction_in_closed_month_but_other_months_work(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $this->close($org, $admin, '2026-10');

        $this->actingAs($admin)
            ->post(route('transactions.store', $org), $this->payload($cat, '2026-10-20'))
            ->assertSessionHasErrors('tanggal');
        $this->assertSame(0, $org->transactions()->count());

        $this->actingAs($admin)
            ->post(route('transactions.store', $org), $this->payload($cat, '2026-09-20'))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $org->transactions()->count());
    }

    public function test_cannot_move_transaction_into_a_closed_month(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $t = $this->txn($org, $admin, $cat, '2026-09-10');
        $this->close($org, $admin, '2026-10');

        $this->actingAs($admin)
            ->patch(route('transactions.update', [$org, $t]), $this->payload($cat, '2026-10-05'))
            ->assertSessionHasErrors('tanggal');

        $this->assertSame('2026-09-10', $t->fresh()->tanggal->toDateString());
    }

    public function test_cannot_update_or_open_edit_for_transaction_in_closed_month(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $t = $this->txn($org, $admin, $cat, '2026-10-10');
        $this->close($org, $admin, '2026-10');

        // Memindahkan ke bulan terbuka pun ditolak (tanggal lama tertutup)
        $this->actingAs($admin)
            ->patch(route('transactions.update', [$org, $t]), $this->payload($cat, '2026-09-10'))
            ->assertSessionHasErrors('transaction');
        $this->assertSame('2026-10-10', $t->fresh()->tanggal->toDateString());
        $this->assertSame('Beli ATK', $t->fresh()->description);

        $this->actingAs($admin)
            ->get(route('transactions.edit', [$org, $t]))
            ->assertSessionHasErrors('transaction');
    }

    public function test_cannot_delete_transaction_in_closed_month(): void
    {
        [$org, $admin] = $this->makeOrg();
        $cat = $this->category($org);
        $t = $this->txn($org, $admin, $cat, '2026-10-10');
        $this->close($org, $admin, '2026-10');

        $this->actingAs($admin)
            ->delete(route('transactions.destroy', [$org, $t]))
            ->assertSessionHasErrors('transaction');

        $this->assertNull($t->fresh()->deleted_at);
    }

    // ---------- Penjaga di iuran ----------

    public function test_pay_cash_is_refused_when_todays_month_is_closed(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->contactInvoice($org);
        $this->close($org, $admin, '2026-11'); // langsung lewat model: lewat UI bulan berjalan tidak bisa ditutup

        $this->actingAs($admin)
            ->patch(route('invoices.pay_cash', [$org, $invoice->id]))
            ->assertSessionHasErrors('invoice');

        $this->assertSame('belum', $invoice->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_approve_is_refused_when_todays_month_is_closed(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->contactInvoice($org, 'menunggu', ['method' => 'transfer']);
        $this->close($org, $admin, '2026-11');

        $this->actingAs($admin)
            ->patch(route('invoices.approve', [$org, $invoice->id]))
            ->assertSessionHasErrors('invoice');

        $this->assertSame('menunggu', $invoice->fresh()->status);
        $this->assertSame(0, Transaction::count());
    }

    public function test_cancel_is_refused_when_the_payment_transaction_is_in_a_closed_month(): void
    {
        [$org, $admin] = $this->makeOrg();
        $invoice = $this->contactInvoice($org, 'lunas', ['method' => 'tunai', 'paid_at' => '2026-10-15 09:00:00']);
        $cat = $org->categories()->where('is_system', true)->first();
        $this->txn($org, $admin, $cat, '2026-10-15', [
            'invoice_id' => $invoice->id, 'type' => 'masuk', 'amount' => 25000, 'description' => 'Iuran Oktober 2026',
        ]);
        $this->close($org, $admin, '2026-10');

        $this->actingAs($admin)
            ->patch(route('invoices.cancel', [$org, $invoice->id]))
            ->assertSessionHasErrors('invoice');

        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame(1, Transaction::withTrashed()->where('invoice_id', $invoice->id)->count());
    }

    public function test_member_can_still_submit_payment_when_the_invoice_month_is_closed(): void
    {
        [$org, $admin] = $this->makeOrg();
        $user = $this->userWithRole($org, 'anggota');
        $member = $org->members()->where('user_id', $user->id)->first();
        $invoice = Invoice::create([
            'member_id' => $member->id, 'period' => '2026-10', 'amount' => 25000, 'status' => 'belum',
        ]);
        $this->close($org, $admin, '2026-10');

        $this->actingAs($user)
            ->post(route('invoices.submit', [$org, $invoice->id]), ['method' => 'tunai'])
            ->assertSessionHasNoErrors();

        $this->assertSame('menunggu', $invoice->fresh()->status);
    }

    // ---------- Tampilan Laporan ----------

    public function test_report_shows_closed_status_and_reopen_button_only_to_admin(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');
        $this->close($org, $admin, '2026-10');

        $this->actingAs($editor)->get(route('reports.index', [$org, 'month' => '2026-10']))
            ->assertOk()->assertSee('Buku ditutup')->assertDontSee('Buka kembali');

        $this->actingAs($admin)->get(route('reports.index', [$org, 'month' => '2026-10']))
            ->assertOk()->assertSee('Buku ditutup')->assertSee('Buka kembali');
    }

    public function test_report_offers_close_button_only_for_past_open_months_to_admin(): void
    {
        [$org, $admin] = $this->makeOrg();
        $editor = $this->userWithRole($org, 'editor');

        $this->actingAs($admin)->get(route('reports.index', [$org, 'month' => '2026-10']))
            ->assertOk()->assertSee('Tutup buku');

        $this->actingAs($admin)->get(route('reports.index', [$org, 'month' => '2026-11']))
            ->assertOk()->assertDontSee('Tutup buku')->assertSee('Bulan berjalan belum bisa ditutup');

        $this->actingAs($editor)->get(route('reports.index', [$org, 'month' => '2026-10']))
            ->assertOk()->assertDontSee('Tutup buku');
    }
}
