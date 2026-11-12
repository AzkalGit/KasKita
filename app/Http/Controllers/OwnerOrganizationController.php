<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Panel Owner/Dev: hanya metadata organisasi. Sengaja TIDAK membaca saldo, transaksi, tagihan, atau nama anggota.
class OwnerOrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()
            ->select(['id', 'name', 'status', 'created_at'])
            ->withCount([
                // anggota aktif yang punya akun (kontak tanpa akun tidak dihitung)
                'members as active_members_count' => fn ($q) => $q
                    ->where('is_active', true)
                    ->whereNotNull('user_id'),
                'members as active_admins_count' => fn ($q) => $q
                    ->where('is_active', true)
                    ->where('role', OrganizationMember::ROLE_ADMIN),
            ])
            ->orderBy('name')
            ->get();

        return view('owner.organizations.index', [
            'organizations' => $organizations,
            'totalOrganizations' => $organizations->count(),
            'activeOrganizations' => $organizations->where('status', 'aktif')->count(),
            'totalUsers' => User::whereNull('platform_role')->count(),
        ]);
    }

    public function deactivate(Request $request, Organization $organization): RedirectResponse
    {
        return $this->changeStatus($request, $organization, 'nonaktif', 'organization.deactivated', 'dinonaktifkan');
    }

    public function activate(Request $request, Organization $organization): RedirectResponse
    {
        return $this->changeStatus($request, $organization, 'aktif', 'organization.activated', 'diaktifkan kembali');
    }

    private function changeStatus(Request $request, Organization $organization, string $to, string $action, string $verb): RedirectResponse
    {
        $from = $organization->status;

        if ($from === $to) {
            return $this->refuse("Organisasi \"{$organization->name}\" sudah berstatus {$to}.");
        }

        DB::transaction(function () use ($request, $organization, $from, $to, $action) {
            $organization->update(['status' => $to]);

            ActivityLogger::record($organization, $request->user(), $action, $organization, [
                'from' => $from,
                'to' => $to,
            ]);
        });

        return $this->done("Organisasi \"{$organization->name}\" {$verb}.");
    }

    private function done(string $message): RedirectResponse
    {
        return redirect()->route('owner.organizations.index')->with('status', $message);
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('owner.organizations.index')->withErrors(['owner' => $message]);
    }
}
