<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePaymentInfoRequest;
use App\Models\Organization;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Gate;

class OrganizationSettingController extends Controller
{
    public function edit(Organization $organization)
    {
        Gate::authorize('manageSettings', $organization);

        return view('organizations.settings', compact('organization'));
    }

    public function update(UpdatePaymentInfoRequest $request, Organization $organization)
    {
        $info = trim((string) $request->validated('payment_info'));

        $organization->payment_info = $info === '' ? null : $info;

        if ($organization->isDirty('payment_info')) {
            $organization->save();

            // Isi rekening sengaja tidak masuk log, hanya penanda bahwa kolom ini diubah
            ActivityLogger::record(
                $organization,
                $request->user(),
                'organization.payment_info_updated',
                null,
                ['field' => 'payment_info']
            );
        }

        return $this->done($organization, 'Info pembayaran disimpan.');
    }

    private function done(Organization $organization, string $pesan)
    {
        return redirect()
            ->route('settings.edit', $organization)
            ->with('status', $pesan);
    }
}
