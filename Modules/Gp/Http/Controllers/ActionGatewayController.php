<?php

namespace Modules\Gp\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Services\ActionLookupService;
use Throwable;

class ActionGatewayController extends Controller
{
    public function dispatch(Request $request, ActionLookupService $actions): JsonResponse
    {
        $this->attachBearerUser($request);

        try {
            $actionCode = $this->resolveActionCode($request);
            $action = $actions->find($actionCode);

            if (! $action) {
                throw new MeException('ACTION_NOT_FOUND', ['action' => $actionCode], 404);
            }

            if (! $this->isAllowed($action, $request->user(), $actions)) {
                throw new MeException('ACTION_FORBIDDEN', ['action' => $actionCode], 403);
            }

            $response = App::call($action->controller . '@' . $action->function, ['request' => $request]);

            if ($response instanceof JsonResponse) {
                return $response;
            }

            return $this->success($response);
        } catch (MeException $exception) {
            return $this->errorResponse($exception);
        } catch (Throwable $exception) {
            Log::error($exception);

            return response()->json([
                'response_code' => 'SYSTEM_ERROR',
                'message' => app()->isProduction() ? 'System error.' : $exception->getMessage(),
            ], 500, ['Content-Type' => 'application/json;charset=UTF-8'], JSON_UNESCAPED_UNICODE);
        }
    }

    private function resolveActionCode(Request $request): string
    {
        $action = $request->header('action') ?: $request->input('action') ?: $request->input('action_code');

        if (! is_string($action) || trim($action) === '') {
            throw new MeException('ACTION_REQUIRED', [], 422);
        }

        return strtolower(trim($action));
    }

    private function isAllowed(object $action, ?User $user, ActionLookupService $lookup): bool
    {
        if (! (bool) ($action->requires_auth ?? true)) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if (! (bool) ($action->requires_permission ?? true)) {
            return true;
        }

        if (! $lookup->hasTable('gp_user_roles') || ! $lookup->hasTable('gp_role_actions')) {
            return true;
        }

        $roleIds = DB::table('gp_user_roles')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return true;
        }

        return DB::table('gp_role_actions')
            ->whereIn('role_id', $roleIds)
            ->where('action_code', $action->action_code)
            ->exists();
    }

    private function attachBearerUser(Request $request): void
    {
        try {
            $user = Auth::guard('sanctum')->user();
        } catch (Throwable) {
            $user = null;
        }

        if (! $user instanceof User) {
            return;
        }

        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);
    }
}
