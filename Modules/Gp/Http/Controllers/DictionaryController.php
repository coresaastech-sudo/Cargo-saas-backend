<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Gp\Entities\DictionaryMain;
use Modules\Gp\Entities\InstitutionConstant;
use Modules\Gp\Services\DictionaryService;

class DictionaryController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('gp_dic_mains')) {
            return $this->emptyDictionaryGrid();
        }

        $query = DictionaryMain::select('gp_dic_mains.*', 'gp_dic_mains.dic_code as id')
            ->where('statusid', '<>', -1);

        return $this->getGridData(
            $request,
            $query,
            [['field' => 'dic_code', 'dir' => 'ASC']]
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validateMe($request, [
            'dic_code' => 'nullable',
            'vw_name' => 'required',
            'description' => 'required',
        ]);

        if (empty($validated['dic_code'])) {
            $validated['dic_code'] = $this->nextDicCode();
        }

        $validated['statusid'] = 1;
        $validated['created_by'] = $request->user()?->id;
        $validated['updated_by'] = $request->user()?->id;

        return DictionaryMain::create($validated);
    }

    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ]);

        $dictionary = DictionaryMain::select('gp_dic_mains.*', 'gp_dic_mains.dic_code as id')
            ->where('dic_code', $validated['id'])
            ->where('statusid', '<>', -1)
            ->first();

        if (! $dictionary) {
            $this->error('RESOURCE_NOT_FOUND', $validated, 404);
        }

        return $dictionary;
    }

    public function update(Request $request)
    {
        $validated = $this->validateMe($request, [
            'dic_code' => 'required',
            'vw_name' => 'required',
            'description' => 'required',
        ]);

        DictionaryMain::where('dic_code', $validated['dic_code'])
            ->where('statusid', '<>', -1)
            ->update(array_merge($validated, [
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]));
    }

    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ]);

        DictionaryMain::where('dic_code', $validated['id'])
            ->where('statusid', '<>', -1)
            ->update([
                'statusid' => -1,
                'updated_by' => $request->user()?->id,
                'updated_at' => now(),
            ]);
    }

    public function options(Request $request, DictionaryService $dictionaries): array
    {
        $code = $request->input('dictionary_code') ?: $request->input('code');

        return $dictionaries->options($code, $request->user()?->organization_id);
    }

    public function getDictionary(Request $request)
    {
        $validated = $this->validateMe($request, [
            'dic_code' => 'required',
            'parentValue' => 'nullable',
            'parentDicCode' => 'nullable',
        ]);

        if (! Schema::hasTable('gp_dic_mains')) {
            return $this->options($request, app(DictionaryService::class));
        }

        $dictionary = DictionaryMain::where('dic_code', $validated['dic_code'])
            ->where('statusid', '<>', -1)
            ->first();

        if (! $dictionary) {
            $this->error('DICTIONARY_NOT_FOUND', $validated, 404);
        }

        if (! Schema::hasTable($dictionary->vw_name)) {
            return $this->dictionaryConstFallback($request, $validated['dic_code'], $validated['parentValue'] ?? null);
        }

        $columns = array_diff(Schema::getColumnListing($dictionary->vw_name), ['id', 'listorder']);
        $selects = ['id', 'listorder'];

        foreach ($columns as $column) {
            $selects[] = $column;
        }

        $query = DB::table($dictionary->vw_name)->select($selects);
        $organizationId = $request->user()?->organization_id ?: $request->input('organization_id');

        if (! empty($validated['parentValue'])) {
            $query->where('parent_code', $validated['parentValue']);
        }

        if ($organizationId && Schema::hasColumn($dictionary->vw_name, 'organization_id')) {
            $query->where(function ($scope) use ($organizationId): void {
                $scope->whereNull('organization_id')->orWhere('organization_id', $organizationId);
            });
        }

        return $query->orderBy('listorder')->get()->toArray();
    }

    public function fillDicCodeValue($dicCode): string
    {
        $dicCode = (intval($dicCode) + 1).'';

        return match (strlen($dicCode)) {
            1 => '00'.$dicCode,
            2 => '0'.$dicCode,
            default => $dicCode,
        };
    }

    private function nextDicCode(): string
    {
        $dictionary = DictionaryMain::orderBy('dic_code', 'desc')->first();

        if (! $dictionary) {
            return 'DIC_001';
        }

        return 'DIC_'.$this->fillDicCodeValue(substr($dictionary->dic_code, 4));
    }

    private function dictionaryConstFallback(Request $request, string $dicCode, ?string $parentValue): array
    {
        if (! Schema::hasTable('gp_inst_consts')) {
            return [];
        }

        $organizationId = $request->user()?->organization_id ?: $request->input('organization_id');

        return InstitutionConstant::select([
            'id',
            'listorder',
            'organization_id',
            'dic_code',
            'code',
            'name',
            'name2',
            'value',
            'parent_code',
        ])
            ->where('dic_code', $dicCode)
            ->where('statusid', '<>', -1)
            ->when($parentValue, fn ($query) => $query->where('parent_code', $parentValue))
            ->when($organizationId, fn ($query) => $query->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $organizationId)))
            ->orderBy('listorder')
            ->get()
            ->toArray();
    }

    private function emptyDictionaryGrid(): array
    {
        return [
            'data' => [],
            'current_page' => 1,
            'per_page' => 50,
            'total' => 0,
        ];
    }
}
