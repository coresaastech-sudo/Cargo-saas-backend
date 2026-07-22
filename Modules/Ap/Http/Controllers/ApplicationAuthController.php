<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Gp\Http\Controllers\NavigationController;

class ApplicationAuthController extends Controller
{
    public function login(Request $request): array
    {
        $validated = $this->validateMe($request, [
            'email' => ['required_without:username', 'nullable', 'string'],
            'username' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $login = $validated['email'] ?? $validated['username'] ?? null;
        $user = $this->findUser((string) $login);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw new MeException('AUTH_FAILED', [], 401, 'Invalid credentials.');
        }

        if (($user->status ?? 'active') !== 'active') {
            throw new MeException('USER_INACTIVE', [], 403);
        }

        if (Schema::hasColumn('users', 'last_login_at')) {
            $user->forceFill(['last_login_at' => now()])->save();
        }

        return [
            'token_type' => 'Bearer',
            'token' => $user->createToken($validated['device_name'] ?? 'backoffice')->plainTextToken,
            'user' => $this->userPayload($user),
        ];
    }

    public function bootstrap(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new MeException('AUTH_REQUIRED', [], 401);
        }

        return [
            'user' => $this->userPayload($user),
            'organization' => $this->organizationPayload($user),
            'branch' => $this->branchPayload($user),
            'roles' => $this->roles($user),
            'menus' => app(NavigationController::class)->menu($request, app(\Modules\Gp\Services\NavigationService::class)),
        ];
    }

    public function logout(Request $request): array
    {
        $request->user()?->currentAccessToken()?->delete();

        return ['logged_out' => true];
    }

    private function findUser(string $login): ?User
    {
        $query = User::query()->where('email', $login);

        if (Schema::hasColumn('users', 'username')) {
            $query->orWhere('username', $login);
        }

        return $query->first();
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username ?? null,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'organization_id' => $user->organization_id ?? null,
            'branch_id' => $user->branch_id ?? null,
            'status' => $user->status ?? 'active',
        ];
    }

    private function organizationPayload(User $user): ?array
    {
        if (! $user->organization_id || ! Schema::hasTable('gp_organizations')) {
            return null;
        }

        $organization = DB::table('gp_organizations')->where('id', $user->organization_id)->first();

        return $organization ? (array) $organization : null;
    }

    private function branchPayload(User $user): ?array
    {
        if (! $user->branch_id || ! Schema::hasTable('gp_branches')) {
            return null;
        }

        $branch = DB::table('gp_branches')->where('id', $user->branch_id)->first();

        return $branch ? (array) $branch : null;
    }

    private function roles(User $user): array
    {
        if (! Schema::hasTable('gp_user_roles') || ! Schema::hasTable('gp_roles')) {
            return [];
        }

        return DB::table('gp_user_roles')
            ->join('gp_roles', 'gp_roles.id', '=', 'gp_user_roles.role_id')
            ->where('gp_user_roles.user_id', $user->id)
            ->where('gp_user_roles.status', 'active')
            ->get(['gp_roles.id', 'gp_roles.role_code', 'gp_roles.name', 'gp_roles.is_admin'])
            ->map(fn ($role): array => (array) $role)
            ->all();
    }
}
