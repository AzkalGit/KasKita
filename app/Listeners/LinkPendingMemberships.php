<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Verified;

// Dijalankan saat pengguna selesai memverifikasi emailnya (event bawaan Laravel).
class LinkPendingMemberships
{
    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $event->user->linkPendingMemberships();
        }
    }
}
