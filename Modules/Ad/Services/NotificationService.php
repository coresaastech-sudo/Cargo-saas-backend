<?php

namespace Modules\Ad\Services;

use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function list(?int $userId)
    {
        return DB::table('ad_notifications')
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }
}
