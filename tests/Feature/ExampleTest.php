<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_di_halaman_awal_dialihkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_pengguna_yang_sudah_masuk_dialihkan_ke_daftar_organisasi(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('organizations.index'));
    }
}