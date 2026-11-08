<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public static function record(
        Organization $organization,
        ?User $user,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
    ): ActivityLog {
        return ActivityLog::create([
            'organization_id' => $organization->id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
        ]);
    }
}
