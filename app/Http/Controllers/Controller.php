<?php

namespace App\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function validateMe(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            throw new MeException('VALIDATION_FAILED', ['errors' => $validator->errors()->toArray()], 422);
        }

        return $validator->validated();
    }

    public function success(mixed $data = null): JsonResponse
    {
        return response()->json(
            [
                'response_code' => 'OK',
                'response' => $data ?? 'Success',
            ],
            200,
            ['Content-Type' => 'application/json;charset=UTF-8'],
            JSON_UNESCAPED_UNICODE
        );
    }

    public function error(string $code, array $data = [], int $status = 400): never
    {
        throw new MeException($code, $data, $status);
    }

    public function errorResponse(MeException $exception): JsonResponse
    {
        $payload = [
            'response_code' => $exception->responseCode(),
            'message' => $exception->getMessage(),
        ];

        if ($exception->data() !== []) {
            $payload['data'] = $exception->data();
        }

        return response()->json(
            $payload,
            $exception->status(),
            ['Content-Type' => 'application/json;charset=UTF-8'],
            JSON_UNESCAPED_UNICODE
        );
    }

    public function storeErrorLog(Throwable $exception): void
    {
        try {
            if (! Schema::hasTable('gp_error_logs')) {
                return;
            }

            $content = request()->getContent();
            $json = json_decode($content);

            if (is_object($json) && property_exists($json, 'password')) {
                $json->password = '******';
                $content = json_encode($json, JSON_UNESCAPED_UNICODE);
            }

            DB::table('gp_error_logs')->insert([
                'module_code' => request()->header('module_code'),
                'action_code' => request()->header('posting_code') ?: request()->input('posting_code') ?: request()->input('action_code'),
                'actor_id' => request()->user()?->id,
                'event' => 'BACKOFFICE_ACTION_ERROR',
                'error_code' => $exception instanceof MeException ? $exception->responseCode() : 'SYSTEM_ERROR',
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'request_body' => $content ? json_decode($content, true) : null,
                'path' => request()->path(),
                'method' => request()->method(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable) {
            return;
        }
    }

    public function getGridData(Request $request, $query, array $defaultOrders = [], array $mandatoryFilters = [], array $mandatoryAnyFields = [])
    {
        $this->validateMe($request, [
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:80',
            'filters.*.value' => 'nullable',
            'filters.*.cond' => 'nullable|max:20',
            'orders' => 'nullable|array',
            'orders.*.field' => 'required|max:80',
            'orders.*.dir' => 'nullable|max:5',
            'perPage' => 'nullable|numeric',
            'page' => 'nullable|numeric',
            'functions' => 'nullable|array',
            'functions.*.field' => 'required|max:80',
            'functions.*.cond' => 'required|max:20|in:max,min,avg,sum',
        ]);

        $filters = (array) $request->input('filters', []);
        $orders = (array) $request->input('orders', $defaultOrders);

        foreach ($mandatoryFilters as $field) {
            $exists = collect($filters)->contains(fn (array $filter): bool => ($filter['field'] ?? null) === $field);

            if (! $exists) {
                $this->error('MANDATORY_FILTER_REQUIRED', ['field' => $field], 422);
            }
        }

        if ($mandatoryAnyFields !== []) {
            $exists = collect($filters)->contains(fn (array $filter): bool => in_array($filter['field'] ?? null, $mandatoryAnyFields, true));

            if (! $exists) {
                $this->error('MANDATORY_ANY_FILTER_REQUIRED', ['fields' => $mandatoryAnyFields], 422);
            }
        }

        foreach ($filters as $filter) {
            $fieldKey = Arr::get($filter, 'field');
            $field = is_string($fieldKey) ? $fieldKey : null;

            if (! $field) {
                continue;
            }

            $operator = strtolower((string) Arr::get($filter, 'cond', '='));
            $value = Arr::get($filter, 'value');

            match ($operator) {
                'like' => $query->whereRaw("upper({$field}) like upper(?)", [$value]),
                'in' => is_array($value) ? $query->whereIn($field, $value) : null,
                'not in' => is_array($value) ? $query->whereNotIn($field, $value) : null,
                'null' => $query->whereNull($field),
                'notnull' => $query->whereNotNull($field),
                '>', '>=', '<', '<=', '!=', '<>', '=' => $query->where($field, $operator, $value),
                default => null,
            };
        }

        foreach ($orders as $order) {
            $fieldKey = Arr::get($order, 'field');
            $field = is_string($fieldKey) ? $fieldKey : null;

            if (! $field) {
                continue;
            }

            $direction = strtolower((string) Arr::get($order, 'dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($field, $direction);
        }

        $functionData = null;
        $functions = (array) $request->input('functions', []);

        if ($functions !== []) {
            $bindings = $query->getBindings();
            $sql = $query->toSql();
            $selects = [];

            foreach ($functions as $function) {
                $cond = strtolower((string) ($function['cond'] ?? ''));
                $field = (string) ($function['field'] ?? '');

                if (! in_array($cond, ['max', 'min', 'avg', 'sum'], true) || $field === '') {
                    continue;
                }

                $selects[] = strtoupper($cond)."({$field}) AS {$cond}_{$field}";
            }

            if ($selects !== []) {
                $functionData = DB::select(
                    'SELECT '.implode(', ', $selects)." FROM ({$sql}) grid_source",
                    $bindings
                );
            }
        }

        $perPage = min(max((int) $request->input('perPage', 50), 1), 200);
        $page = max((int) $request->input('page', 1), 1);

        $data = $query->simplePaginate($perPage, ['*'], 'page', $page);

        if ($functionData) {
            return [
                'data' => $data,
                'functions' => (array) ($functionData[0] ?? []),
            ];
        }

        return $data;
    }
}
