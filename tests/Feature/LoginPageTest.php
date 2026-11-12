<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_kotak_akun_demo_tidak_tampil_di_luar_local(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke akun KasKita kamu.')
            ->assertDontSee('KitaDemo2026!')
            ->assertDontSee('owner@kaskita.demo');
    }

    public function test_kotak_akun_demo_tampil_di_lingkungan_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/login')
            ->assertOk()
            ->assertSee('Akun demo presentasi')
            ->assertSee('KitaDemo2026!');
    }
}
