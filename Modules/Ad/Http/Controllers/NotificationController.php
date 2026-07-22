<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ad\Services\NotificationService;
use Modules\Gp\Services\ActionLookupService;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationService $notifications, ActionLookupService $lookup)
    {
        if (! $lookup->hasTable('ad_notifications')) {
            return [];
        }

        return $notifications->list($request->user()?->id);
    }
}
