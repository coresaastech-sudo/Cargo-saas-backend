<?php

namespace Modules\Gp\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Modules\Gp\Entities\GpServiceFee;
use Modules\Gp\Entities\Views\VwGpServiceFee;
use Throwable;

class ServiceFeeController extends Controller
{
    protected $model = GpServiceFee::class;

    protected $view = VwGpServiceFee::class;

    protected $allowedFields = [
        'fee_code' => 'fee_code',
        'name' => 'name',
        'fee_type' => 'fee_type',
        'status' => 'status',
    ];

    protected $fillable = [
        'fee_code',
        'name',
        'fee_type',
        'amount',
        'percent',
        'currency',
        'settings',
        'status',
    ];

    protected $organizationScoped = true;

    public function index(Request $request)
    {
        $model = $this->readModel();
        $table = $this->tableName($model);

        if (! $this->tableReady($table)) {
            return $this->emptyGrid();
        }

        $query = $model::query();
        $this->applyCargoScope($query, $request, $table);

        return $this->getGridData(
            $request,
            $query,
            [['field' => $this->defaultOrderField(), 'dir' => 'ASC']]
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $validated = $this->preparePayload($request, $validated, true);

        if (! $this->tableReady($this->writeTable())) {
            throw new MeException('RESOURCE_TABLE_NOT_READY', ['table' => $this->writeTable()], 409);
        }

        $model = $this->model;

        return $model::create($validated);
    }

    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => 'ID_REQUIRED',
        ]);

        return $this->findById($request, $validated['id']);
    }

    public function update(Request $request)
    {
        $validated = $this->validatePayload($request);
        $id = $request->input('id') ?: $request->input('payload.id');

        if (empty($id)) {
            throw new MeException('ID_REQUIRED', [], 422);
        }

        if (! $this->tableReady($this->writeTable())) {
            throw new MeException('RESOURCE_TABLE_NOT_READY', ['table' => $this->writeTable()], 409);
        }

        $query = $this->writeQuery($request)->where('id', $id);

        if (! $query->exists()) {
            throw new MeException('RESOURCE_NOT_FOUND', ['table' => $this->writeTable(), 'id' => $id], 404);
        }

        $query->update($this->preparePayload($request, $validated, false));

        return $this->findById($request, $id);
    }

    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => 'ID_REQUIRED',
        ]);

        if (! $this->tableReady($this->writeTable())) {
            throw new MeException('RESOURCE_TABLE_NOT_READY', ['table' => $this->writeTable()], 409);
        }

        $query = $this->writeQuery($request)->where('id', $validated['id']);

        if (! $query->exists()) {
            throw new MeException('RESOURCE_NOT_FOUND', ['table' => $this->writeTable(), 'id' => $validated['id']], 404);
        }

        if ($this->hasColumn($this->writeTable(), 'statusid')) {
            $query->update(['statusid' => -1, 'updated_at' => now()]);
        } elseif ($this->hasColumn($this->writeTable(), 'status')) {
            $query->update(['status' => 'deleted', 'updated_at' => now()]);
        } else {
            $query->delete();
        }
    }

    protected function validatePayload(Request $request)
    {
        $source = $request->input('payload');
        $source = is_array($source) ? $source : $request->all();

        return Arr::only($source, $this->fillable);
    }

    protected function findById(Request $request, $id)
    {
        $query = $this->readQuery($request)->where('id', $id);
        $row = $query->first();

        if (! $row) {
            throw new MeException('RESOURCE_NOT_FOUND', ['table' => $this->writeTable(), 'id' => $id], 404);
        }

        return $row;
    }

    protected function preparePayload(Request $request, array $payload, $creating)
    {
        if ($this->organizationScoped && $this->hasColumn($this->writeTable(), 'organization_id') && ! array_key_exists('organization_id', $payload)) {
            $payload['organization_id'] = $request->user()?->organization_id ?: $request->input('organization_id');
        }

        if ($this->hasColumn($this->writeTable(), 'branch_id') && ! array_key_exists('branch_id', $payload)) {
            $payload['branch_id'] = $request->user()?->branch_id ?: $request->input('branch_id');
        }

        if (array_key_exists('password', $payload) && ! empty($payload['password'])) {
            $payload['password'] = Hash::make($payload['password']);
        }

        if ($creating && $this->hasColumn($this->writeTable(), 'created_by') && ! array_key_exists('created_by', $payload)) {
            $payload['created_by'] = $request->user()?->id;
        }

        if ($this->hasColumn($this->writeTable(), 'updated_by')) {
            $payload['updated_by'] = $request->user()?->id;
        }

        if ($creating && $this->hasColumn($this->writeTable(), 'statusid') && ! array_key_exists('statusid', $payload)) {
            $payload['statusid'] = 1;
        }

        if ($creating && $this->hasColumn($this->writeTable(), 'created_at') && ! array_key_exists('created_at', $payload)) {
            $payload['created_at'] = now();
        }

        if ($this->hasColumn($this->writeTable(), 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    protected function applyCargoScope($query, Request $request, $table)
    {
        $organizationId = $request->user()?->organization_id ?: $request->input('organization_id');

        if ($this->organizationScoped && $organizationId && $this->hasColumn($table, 'organization_id')) {
            $query->where('organization_id', $organizationId);
        }

        if ($this->hasColumn($table, 'statusid')) {
            $query->where('statusid', '<>', -1);
        } elseif ($this->hasColumn($table, 'status')) {
            $query->where('status', '<>', 'deleted');
        }
    }

    protected function readModel()
    {
        if ($this->tableReady($this->tableName($this->view))) {
            return $this->view;
        }

        return $this->model;
    }

    protected function readQuery(Request $request)
    {
        $model = $this->readModel();
        $query = $model::query();
        $this->applyCargoScope($query, $request, $this->tableName($model));

        return $query;
    }

    protected function writeQuery(Request $request)
    {
        $model = $this->model;
        $query = $model::query();
        $this->applyCargoScope($query, $request, $this->writeTable());

        return $query;
    }

    protected function writeTable()
    {
        return $this->tableName($this->model);
    }

    protected function tableName($model)
    {
        return (new $model)->getTable();
    }

    protected function defaultOrderField()
    {
        $fields = array_values($this->allowedFields);

        return $fields[0] ?? 'id';
    }

    protected function tableReady($table)
    {
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    protected function hasColumn($table, $column)
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    protected function emptyGrid()
    {
        return [
            'data' => [],
            'current_page' => 1,
            'per_page' => 50,
            'total' => 0,
        ];
    }
}
