<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Lead;

class ActivityLogService
{
    public function log(Lead $lead, string $action, string $description, mixed $old = null, mixed $new = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => auth()->id() ?? $lead->user_id, 'lead_id' => $lead->id, 'action' => $action,
            'old_value' => is_scalar($old) ? (string) $old : json_encode($old),
            'new_value' => is_scalar($new) ? (string) $new : json_encode($new), 'description' => $description,
        ]);
    }
}
