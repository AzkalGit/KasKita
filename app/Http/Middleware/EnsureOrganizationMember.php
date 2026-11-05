<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        $member = $request->user()->memberships()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->first();

        abort_if(! $member, 403, 'Kamu bukan anggota organisasi ini.');
        abort_if(! $organization->isActive(), 403, 'Organisasi ini sedang dinonaktifkan.');

        // Dipakai controller dan view untuk mengetahui peran di organisasi ini
        $request->attributes->set('member', $member);
        view()->share('currentOrganization', $organization);
        view()->share('currentMember', $member);

        return $next($request);
    }
}