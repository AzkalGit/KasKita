<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    // Token CSRF dikirim sendiri: saat lingkungan diganti dari "testing",
    // Laravel memeriksa CSRF lagi, jadi tes lingkungan lain butuh token yang cocok.
    private function daftar(string $email = 'test@example.com')
    {
        return $this->withSession(['_token' => 'token-tes'])->post('/register', [
            '_token' => 'token-tes',
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->daftar();

        $this->assertAuthenticated();
        $response->assertRedirect(route('organizations.index', absolute: false));
    }

    public function test_di_lingkungan_testing_email_baru_belum_terverifikasi(): void
    {
        $this->daftar();

        $this->assertNull(User::where('email', 'test@example.com')->first()->email_verified_at);
    }

    public function test_di_lingkungan_local_akun_baru_langsung_terverifikasi_dan_memicu_penautan(): void
    {
        $this->app['env'] = 'local';
        Event::fake([Verified::class]);

        $this->daftar('lokal@example.com');

        $user = User::where('email', 'lokal@example.com')->first();
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Verified::class, fn ($e) => $e->user->is($user));
    }

    public function test_di_lingkungan_non_local_verifikasi_tetap_wajib(): void
    {
        $this->app['env'] = 'production';
        Event::fake([Verified::class]);

        $this->daftar('prod@example.com');

        $this->assertNull(User::where('email', 'prod@example.com')->first()->email_verified_at);
        Event::assertNotDispatched(Verified::class);
    }
}
