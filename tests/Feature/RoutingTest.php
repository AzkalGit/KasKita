<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoutingTest extends TestCase
{
    // Laravel menyimpan rute dengan kunci "METODE + URI", jadi rute yang ditulis dua kali tidak terlihat
    // lewat Route::getRoutes(): yang belakangan diam-diam menimpa yang pertama (middleware-nya ikut hilang).
    // Karena itu pemeriksaannya dilakukan pada penulisan di berkas routes/.
    public function test_tidak_ada_rute_yang_ditulis_dua_kali_di_berkas_routes(): void
    {
        $ditulis = [];

        foreach (glob(base_path('routes/*.php')) as $berkas) {
            if (basename($berkas) === 'console.php') {
                continue;
            }
            preg_match_all("/Route::(get|post|put|patch|delete|any)\(\s*'([^']+)'/", file_get_contents($berkas), $m, PREG_SET_ORDER);
            foreach ($m as [, $metode, $uri]) {
                $ditulis[strtoupper($metode).' /'.trim($uri, '/')][] = basename($berkas);
            }
        }

        $dobel = array_filter($ditulis, fn ($berkas) => count($berkas) > 1);

        $this->assertSame([], array_keys($dobel), 'Rute ditulis lebih dari sekali: '.json_encode($dobel));
    }

    public function test_rute_organisasi_butuh_login_dan_email_terverifikasi(): void
    {
        foreach (['organizations.index', 'organizations.store', 'organizations.join', 'organizations.dashboard'] as $nama) {
            $middleware = Route::getRoutes()->getByName($nama)->gatherMiddleware();

            $this->assertContains('auth', $middleware, "$nama tanpa auth");
            $this->assertContains('verified', $middleware, "$nama tanpa verified");
        }
    }

    public function test_rute_dashboard_organisasi_juga_dibatasi_anggota(): void
    {
        $this->assertContains('org.member', Route::getRoutes()->getByName('organizations.dashboard')->gatherMiddleware());
    }
}
