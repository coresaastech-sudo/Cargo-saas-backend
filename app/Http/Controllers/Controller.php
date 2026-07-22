<?php

namespace App\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

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

    public function getGridData(Request $request, $query, array $allowedFields, array $defaultOrders = [])
    {
        $filters = (array) $request->input('filters', []);
        $orders = (array) $request->input('orders', $defaultOrders);

        foreach ($filters as $filter) {
            $fieldKey = Arr::get($filter, 'field');
            $field = $allowedFields[$fieldKey] ?? null;

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
            $field = $allowedFields[$fieldKey] ?? null;

            if (! $field) {
                continue;
            }

            $direction = strtolower((string) Arr::get($order, 'dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($field, $direction);
        }

        $perPage = min(max((int) $request->input('perPage', 50), 1), 200);
        $page = max((int) $request->input('page', 1), 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
