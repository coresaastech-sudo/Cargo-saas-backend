<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Re\Entities\ReInstTable;
use Modules\Re\Entities\ReInstTableField;
use Modules\Re\Entities\Views\VwReInstTables;
use Modules\Re\Http\Requests\ReInstTableRequest;

class ReInstTableController extends Controller
{
    /**
     * re010000
     * Display a listing of the Report Temp Param.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, ReInstTable::where('statusid', 1)
            ->where('instid', 1));
    }

    /**
     * re010300
     * Update Report Temp Param
     * @param ReInstTableRequest $request
     * @return Response
     */
    public function update(ReInstTableRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error('RC000011');
        }
        $user = auth()->user();
        $validated['updated_by'] = $user->id;

        $array = $validate['fields'];
        unset($validate['fields']);

        $allattribute = false;

        if (!empty($validate['allattribute'])) {
            $allattribute = $validate['allattribute'];
        }

        unset($validate['allattribute']);

        if (array_key_exists('name', $validate) && !empty($validate['name'])) {
            $validate['name'] = mb_strtoupper($validate['name']);
        }

        if (array_key_exists('name2', $validate) && !empty($validate['name2'])) {
            $validate['name2'] = mb_strtoupper($validate['name2']);
        }

        $update = ReInstTable::where('instid', 1)
            ->where("statusid", 1)->where('id', $validate['id'])->update($validate);

        if ($allattribute) {
            ReInstTableField::where("instid", 1)->where("statusid", 1)->where("tableid", $validate['id'])->update(["statusid" => -1, "updated_by" => $user->id]);
            $query = "select * from information_schema.columns where table_name = '" . $validate['tablename'] . "' ORDER BY ordinal_position ASC";
            $result = DB::select($query);
            $result = json_decode(json_encode($result));
            $insertion = [];
            foreach ($result as $field) {
                $item = ['fieldname' => $field->{'column_name'}, 'name' => $field->{'column_name'}, 'name2' => $field->{'column_name'}];
                if (in_array($field->{'data_type'}, ['string', 'character varying', 'char', 'text', 'citext'])) {
                    // Category: String
                    $item['type'] = 1;
                } elseif (in_array($field->{'data_type'}, ['numeric', 'integer', 'int', 'bigint', 'decimal', 'real', 'double precision'])) {
                    // Category: Numeric
                    $item['type'] = 2;
                } elseif (in_array($field->{'data_type'}, ['boolean'])) {
                    // Category: Boolean
                    $item['type'] = 3;
                } elseif (in_array($field->{'data_type'}, ['date', 'time', 'timestamp', 'interval'])) {
                    // Category: Date
                    $item['type'] = 4;
                } else {
                    // Unknown category
                    $item['type'] = 1;
                }
                if (empty($item['ispkey'])) $item['ispkey'] = false;
                $item['tableid'] = $validate['id'];
                $item['statusid'] = 1;
                $item['instid'] = 1;
                $item['created_by'] = $user->id;
                $item['updated_by'] = $user->id;
                array_push($insertion, $item);
            }
            ReInstTableField::insert($insertion);
        } else if ($array) {
            ReInstTableField::where("instid", 1)->where("statusid", 1)->where("tableid", $validate['id'])->update(["statusid" => -1, "updated_by" => $user->id]);
            foreach ($array as &$item) {
                if (empty($item['ispkey'])) $item['ispkey'] = false;
                if (empty($item['id'])) {
                    unset($item['id']);
                    $item['tableid'] = $validate['id'];
                    $item['statusid'] = 1;
                    $item['instid'] = 1;
                    $item['created_by'] = $user->id;
                    $item['updated_by'] = $user->id;
                    ReInstTableField::create($item);
                } else {
                    $item["statusid"] = 1;
                    $item["updated_by"] = $user->id;
                    $id = $item['id'];
                    unset($item['id']);
                    ReInstTableField::where("instid", 1)->where("id", $id)->update($item);
                }
            }
        }
    }

    /**
     * re010200
     * Store Report Temp Param
     * @param ReInstTableRequest $request
     * @return Response
     */
    public function store(ReInstTableRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();
        $validate['statusid'] = 1;
        $validate['instid'] = 1;
        $validate['created_by'] = $user->id;
        $validate['updated_by'] = $user->id;
        $validate['name'] = mb_strtoupper($validate['name']);
        $validate['name2'] = mb_strtoupper($validate['name2']);

        $allattribute = false;

        if (!empty($validate['allattribute'])) {
            $allattribute = $validate['allattribute'];
            unset($validate['allattribute']);
        }

        $array = null;
        if (!empty($validate['fields'])) $array = $validate['fields'];

        unset($validate['fields']);
        $table = ReInstTable::create($validate);

        if (!$allattribute && $array) {
            $foundInst = false;
            foreach ($array as &$item) {
                if ($item->fieldname === 'instid' || $item->fieldname === 'institution') $foundInst;
                unset($item['id']);
                if (empty($item['ispkey'])) $item['ispkey'] = false;
                $item['tableid'] = $table->id;
                $item['statusid'] = 1;
                $item['instid'] = 1;
                $item['created_by'] = $user->id;
                ReInstTableField::create($item);
            }
            if ($foundInst === false) {
                ReInstTableField::create([
                    'fieldname' => 'instid',
                    'name' => 'Байгууллагын дугаар',
                    'name2' => 'Institution',
                    'description' => 'Байгууллагын дугаар',
                    'isprimarykey' => false,
                    'type' => 2,
                    'ispkey' => false,
                    'tableid' => $table->id,
                    'statusid' => 1,
                    'instid' => 1,
                    'created_by' => $user->id,
                ]);
            }
        } else {
            $query = "select * from information_schema.columns where table_name = '" . $validate['tablename'] . "' ORDER BY ordinal_position ASC";
            $result = DB::select($query);
            $result = json_decode(json_encode($result));
            $insertion = [];
            foreach ($result as $field) {
                $item = ['fieldname' => $field->{'column_name'}, 'name' => $field->{'column_name'}, 'name2' => $field->{'column_name'}];
                if (in_array($field->{'data_type'}, ['string', 'character varying', 'char', 'text', 'citext'])) {
                    // Category: String
                    $item['type'] = 1;
                } elseif (in_array($field->{'data_type'}, ['numeric', 'integer', 'int', 'bigint', 'decimal', 'real', 'double precision'])) {
                    // Category: Numeric
                    $item['type'] = 2;
                } elseif (in_array($field->{'data_type'}, ['boolean'])) {
                    // Category: Boolean
                    $item['type'] = 3;
                } elseif (in_array($field->{'data_type'}, ['date', 'time', 'timestamp', 'interval'])) {
                    // Category: Date
                    $item['type'] = 4;
                } else {
                    // Unknown category
                    $item['type'] = 1;
                }
                if (empty($item['ispkey'])) $item['ispkey'] = false;
                $item['tableid'] = $table->id;
                $item['statusid'] = 1;
                $item['instid'] = 1;
                $item['created_by'] = $user->id;
                $item['updated_by'] = $user->id;
                array_push($insertion, $item);
            }
            ReInstTableField::insert($insertion);
        }
        return $table;
    }

    /**
     * re010400
     * Destroy Report Temp Param
     * @param Request $request
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $table = ReInstTable::where("instid", 1)
            ->where("id", $validate['id'])->where('statusid', 1)->first();

        $table->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);

        ReInstTableField::where("instid", 1)
            ->where("tableid", $validate["id"])->where("statusid", 1)->update(["statusid" => -1, "updated_by" => auth()->user()->id]);
    }

    /**
     * re010100
     * Show Report Temp Param
     * @param Request $request
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $table = VwReInstTables::where("instid", 1)
            ->where("id", $validate["id"])->where("statusid", 1)->first();

        $tablefields = ReInstTableField::where("instid", 1)
            ->where("tableid", $validate["id"])->where("statusid", 1)->orderBy('id')->get();

        if ($table) {
            $table['fields'] = $tablefields;
            return $table;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
