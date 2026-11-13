<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_halaman_login_tidak_menampilkan_akun_demo_di_luar_local(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke akun KasKita kamu.')
            ->assertDontSee('Akun demo presentasi')
            ->assertDontSee('azkal123')
            ->assertDontSee('furqan@gmail.com');
    }

    public function test_halaman_login_tidak_menampilkan_akun_demo_di_lingkungan_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke akun KasKita kamu.')
            ->assertDontSee('Akun demo presentasi')
            ->assertDontSee('azkal123')
            ->assertDontSee('furqan@gmail.com');
    }
}
