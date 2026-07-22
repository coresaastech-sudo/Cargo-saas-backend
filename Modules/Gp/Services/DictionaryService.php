<?php

namespace Modules\Gp\Services;

use Illuminate\Support\Facades\DB;

class DictionaryService
{
    public function __construct(private readonly ActionLookupService $lookup)
    {
    }

    public function options(?string $code, ?int $organizationId): array
    {
        if (! $this->lookup->hasTable('gp_dictionaries') || ! $this->lookup->hasTable('gp_dictionary_items')) {
            return $this->fallback($code);
        }

        $dictionaries = DB::table('gp_dictionaries')
            ->where('status', 'active')
            ->when($code, fn ($query) => $query->where('dictionary_code', $code))
            ->when(
                $organizationId,
                fn ($query) => $query->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $organizationId)),
                fn ($query) => $query->whereNull('organization_id')
            )
            ->get();

        return $dictionaries->mapWithKeys(function ($dictionary): array {
            $items = DB::table('gp_dictionary_items')
                ->where('dictionary_id', $dictionary->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['item_code', 'name', 'value'])
                ->map(fn ($item): array => [
                    'code' => $item->item_code,
                    'name' => $item->name,
                    'value' => $item->value ? json_decode($item->value, true) : null,
                ])
                ->all();

            return [$dictionary->dictionary_code => $items];
        })->all();
    }

    private function fallback(?string $code): array
    {
        $items = [
            'cargo_status' => [
                ['code' => 'draft', 'name' => 'Draft'],
                ['code' => 'in_transit', 'name' => 'In transit'],
                ['code' => 'delivered', 'name' => 'Delivered'],
                ['code' => 'cancelled', 'name' => 'Cancelled'],
            ],
            'payment_status' => [
                ['code' => 'unpaid', 'name' => 'Unpaid'],
                ['code' => 'paid', 'name' => 'Paid'],
            ],
        ];

        return $code ? [$code => $items[$code] ?? []] : $items;
    }
}
