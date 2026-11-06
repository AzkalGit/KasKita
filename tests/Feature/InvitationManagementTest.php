<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DemoOrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoOrganizationSeeder::class);
        $this->org = Organization::firstOrFail();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function admin(): User
    {
        return $this->user('admin@kaskita.demo');
    }

    private function demoCode(): Invitation
    {
        return Invitation::where('code', 'BEM26KAS')->firstOrFail();
    }

    // ---------- tampilan ----------

    public function test_admin_melihat_daftar_kode_beserta_statusnya(): void
    {
        Invitation::create(['organization_id' => $this->org->id, 'code' => 'TUTUP222', 'closed_at' => now()]);
        Invitation::create(['organization_id' => $this->org->id, 'code' => 'LEWAT333', 'expires_at' => now()->subDay()]);

        $this->actingAs($this->admin())
            ->get(route('invitations.index', $this->org))
            ->assertOk()
            ->assertSee('<html', false)
            ->assertSee('BEM26KAS')
            ->assertSee('TUTUP222')
            ->assertSee('LEWAT333')
            ->assertSee('Aktif')
            ->assertSee('Ditutup')
            ->assertSee('Kedaluwarsa')
            ->assertSee('Tanpa batas waktu'); // kode demo BEM26KAS memang permanen
    }

    public function test_daftar_pemakaian_menunjukkan_siapa_yang_bergabung(): void
    {
        $baru = User::factory()->create(['name' => 'Pendatang Baru']);
        $this->actingAs($baru)->post(route('organizations.join'), ['code' => 'BEM26KAS']);

        $this->actingAs($this->admin())
            ->get(route('invitations.index', $this->org))
            ->assertOk()
            ->assertSee('Pendatang Baru')
            ->assertSee($baru->email)
            ->assertSee('1 orang');
    }

    // ---------- hanya Admin ----------

    public function test_hanya_admin_boleh_mengelola_undangan(): void
    {
        $kode = $this->demoCode();
        $jumlahKode = Invitation::count();
        $jumlahLog = ActivityLog::count();

        $permintaan = [
            ['get', route('invitations.index', $this->org), []],
            ['post', route('invitations.store', $this->org), ['days' => 7]],
            ['patch', route('invitations.close', [$this->org, $kode]), []],
            ['patch', route('invitations.reopen', [$this->org, $kode]), []],
            ['post', route('invitations.regenerate', [$this->org, $kode]), ['days' => 7]],
        ];

        $pelaku = [
            'editor' => $this->user('editor@kaskita.demo'),
            'anggota' => $this->user('anggota@kaskita.demo'),
            'bukan anggota' => User::factory()->create(),
        ];

        foreach ($pelaku as $nama => $user) {
            foreach ($permintaan as [$metode, $url, $data]) {
                $this->actingAs($user)->{$metode}($url, $data)
                    ->assertForbidden("$nama harus ditolak: $metode $url");
            }
        }

        $this->assertSame($jumlahKode, Invitation::count());
        $this->assertSame($jumlahLog, ActivityLog::count());
        $this->assertNull($kode->fresh()->closed_at);
    }

    public function test_pengguna_belum_verifikasi_dialihkan(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('invitations.index', $this->org))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_organisasi_lain_tidak_bisa_menyentuh_kode_organisasi_ini(): void
    {
        $adminB = User::factory()->create();
        $this->actingAs($adminB)->post(route('organizations.store'), ['name' => 'Organisasi B']);
        $orgB = Organization::where('name', 'Organisasi B')->firstOrFail();
        $kodeA = $this->demoCode();

        // lewat URL organisasi A: ditolak karena bukan anggota A
        $this->patch(route('invitations.close', [$this->org, $kodeA]))->assertForbidden();

        // lewat URL organisasi B tetapi dengan kode milik A: scopeBindings membuatnya 404
        $this->patch(route('invitations.close', [$orgB, $kodeA]))->assertNotFound();
        $this->patch(route('invitations.reopen', [$orgB, $kodeA]))->assertNotFound();
        $this->post(route('invitations.regenerate', [$orgB, $kodeA]), ['days' => 7])->assertNotFound();

        $this->assertNull($kodeA->fresh()->closed_at);
    }

    // ---------- membuat kode ----------

    public function test_admin_membuat_kode_dengan_masa_berlaku_dan_tercatat(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('invitations.store', $this->org), ['days' => 7])
            ->assertRedirect(route('invitations.index', $this->org))
            ->assertSessionHas('status');

        $baru = Invitation::where('organization_id', $this->org->id)->latest('id')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $baru->code);
        $this->assertSame($admin->id, $baru->created_by);
        $this->assertNull($baru->closed_at);
        $this->assertEqualsWithDelta(7 * 24, now()->diffInHours($baru->expires_at), 1);

        $log = ActivityLog::where('action', 'invitation.created')->firstOrFail();
        $this->assertSame($this->org->id, $log->organization_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($baru->code, $log->metadata['code']);
    }

    public function test_masa_berlaku_hanya_1_7_atau_30_hari(): void
    {
        $sebelum = Invitation::count();

        foreach ([['days' => 5], ['days' => 0], ['days' => 365], ['days' => 'abc'], []] as $data) {
            $this->actingAs($this->admin())
                ->post(route('invitations.store', $this->org), $data)
                ->assertSessionHasErrors('days');
        }
        $this->assertSame($sebelum, Invitation::count());

        foreach ([1, 30] as $hari) {
            $this->post(route('invitations.store', $this->org), ['days' => $hari])->assertSessionHasNoErrors();
        }
        $this->assertSame($sebelum + 2, Invitation::count());
    }

    public function test_kode_awal_organisasi_baru_berlaku_7_hari_bukan_permanen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('organizations.store'), ['name' => 'Himpunan Uji']);

        $kode = Organization::where('name', 'Himpunan Uji')->firstOrFail()->invitations()->firstOrFail();
        $this->assertNotNull($kode->expires_at);
        $this->assertEqualsWithDelta(7 * 24, now()->diffInHours($kode->expires_at), 1);
        $this->assertSame($user->id, $kode->created_by);
    }

    public function test_kode_acak_memakai_alfabet_tanpa_karakter_membingungkan_dan_unik(): void
    {
        $kode = [];
        for ($i = 0; $i < 200; $i++) {
            $c = Invitation::generateCode();
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $c);
            $kode[$c] = true;
        }
        $this->assertGreaterThan(195, count($kode), 'kode acak tidak boleh sering kembar');
    }

    // ---------- tutup, buka, kedaluwarsa ----------

    public function test_kode_ditutup_tidak_bisa_dipakai_lalu_bisa_dibuka_lagi(): void
    {
        $kode = $this->demoCode();

        $this->actingAs($this->admin())
            ->patch(route('invitations.close', [$this->org, $kode]))
            ->assertRedirect(route('invitations.index', $this->org));
        $this->assertNotNull($kode->fresh()->closed_at);
        $this->assertSame('ditutup', $kode->fresh()->status());
        $this->assertTrue(ActivityLog::where('action', 'invitation.closed')->exists());

        $pendatang = User::factory()->create();
        $this->actingAs($pendatang)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasErrors('code');
        $this->assertSame(0, $pendatang->memberships()->count());

        $this->actingAs($this->admin())->patch(route('invitations.reopen', [$this->org, $kode]));
        $this->assertNull($kode->fresh()->closed_at);
        $this->assertTrue(ActivityLog::where('action', 'invitation.reopened')->exists());

        $this->actingAs($pendatang)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasNoErrors();
        $this->assertSame(1, $pendatang->memberships()->count());
    }

    public function test_kode_kedaluwarsa_tidak_bisa_dibuka_lagi_dan_tidak_perlu_ditutup(): void
    {
        $tutupLewat = Invitation::create([
            'organization_id' => $this->org->id, 'code' => 'LEWAT444',
            'expires_at' => now()->subDay(), 'closed_at' => now()->subDays(2),
        ]);
        $lewatSaja = Invitation::create([
            'organization_id' => $this->org->id, 'code' => 'LEWAT555', 'expires_at' => now()->subHour(),
        ]);

        $this->actingAs($this->admin())
            ->patch(route('invitations.reopen', [$this->org, $tutupLewat]))
            ->assertSessionHasErrors('invitation');
        $this->assertNotNull($tutupLewat->fresh()->closed_at);

        $this->patch(route('invitations.close', [$this->org, $lewatSaja]))->assertSessionHas('status');
        $this->assertNull($lewatSaja->fresh()->closed_at);
        $this->assertSame('kedaluwarsa', $lewatSaja->fresh()->status());

        $pendatang = User::factory()->create();
        $this->actingAs($pendatang)->post(route('organizations.join'), ['code' => 'LEWAT555'])->assertSessionHasErrors('code');
    }

    public function test_membuka_atau_menutup_kode_yang_sudah_begitu_tidak_mengubah_apa_apa(): void
    {
        $kode = $this->demoCode();
        $log = ActivityLog::count();

        $this->actingAs($this->admin())->patch(route('invitations.reopen', [$this->org, $kode]))->assertSessionHas('status');
        $kode->update(['closed_at' => now()]);
        $this->patch(route('invitations.close', [$this->org, $kode]))->assertSessionHas('status');

        $this->assertSame($log, ActivityLog::count());
    }

    // ---------- ganti kode ----------

    public function test_ganti_kode_menutup_yang_lama_dan_membuat_yang_baru(): void
    {
        $lama = $this->demoCode();

        $this->actingAs($this->admin())
            ->post(route('invitations.regenerate', [$this->org, $lama]), ['days' => 30])
            ->assertRedirect(route('invitations.index', $this->org));

        $this->assertNotNull($lama->fresh()->closed_at);
        $baru = Invitation::where('organization_id', $this->org->id)->where('id', '!=', $lama->id)->latest('id')->firstOrFail();
        $this->assertNotSame($lama->code, $baru->code);
        $this->assertTrue($baru->isUsable());
        $this->assertEqualsWithDelta(30 * 24, now()->diffInHours($baru->expires_at), 1);

        $log = ActivityLog::where('action', 'invitation.regenerated')->firstOrFail();
        $this->assertSame('BEM26KAS', $log->metadata['replaced_code']);

        $pendatang = User::factory()->create();
        $this->actingAs($pendatang)->post(route('organizations.join'), ['code' => 'BEM26KAS'])->assertSessionHasErrors('code');
        $this->actingAs($pendatang)->post(route('organizations.join'), ['code' => strtolower($baru->code)])->assertSessionHasNoErrors();
        $this->assertSame(1, $pendatang->memberships()->count());
    }

    public function test_ganti_kode_yang_sudah_kedaluwarsa_tetap_membuat_kode_baru(): void
    {
        $lewat = Invitation::create(['organization_id' => $this->org->id, 'code' => 'LEWAT666', 'expires_at' => now()->subDay()]);

        $this->actingAs($this->admin())->post(route('invitations.regenerate', [$this->org, $lewat]), ['days' => 7])->assertSessionHasNoErrors();

        $this->assertNull($lewat->fresh()->closed_at, 'kode yang sudah lewat tidak perlu ditutup');
        $this->assertSame(3, Invitation::where('organization_id', $this->org->id)->count());
    }

    // ---------- model ----------

    public function test_status_kode(): void
    {
        $this->assertSame('aktif', (new Invitation(['expires_at' => null]))->status());
        $this->assertSame('aktif', (new Invitation(['expires_at' => now()->addHour()]))->status());
        $this->assertSame('kedaluwarsa', (new Invitation(['expires_at' => now()->subHour()]))->status());
        $this->assertSame('ditutup', (new Invitation(['closed_at' => now()]))->status());
        $this->assertSame('ditutup', (new Invitation(['closed_at' => now(), 'expires_at' => now()->subDay()]))->status(), 'ditutup menang atas kedaluwarsa');
    }
}
