<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Gp\Entities\GpInstRole;
use Modules\Gp\Entities\GpInstRolePerms;
use Modules\Gp\Entities\GpInstUserRole;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Entities\Views\VwGpInstPerm;
use Modules\Gp\Entities\Views\VwGpInstRole;
use Modules\Gp\Entities\Views\VwInstRolePermList;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Requests\GpInstRoleRequest;

class GpInstRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp060000
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validate($request, [
            'notuserid' => 'nullable',
            'instid' => 'nullable'
        ]);

        $user = auth()->user();
        if ($user->isadmin != 1) {
            $validate['instid'] = auth()->user()->instid;
        }

        $sql = VwGpInstRole::where('statusid', '<>', -1);
        if (isset($validate['instid'])) {
            if ($user->isadmin == 1) {
                $sql = $sql->where('instid', [$validate['instid'], '0']);
            } else $sql = $sql->where('instid', $validate['instid']);
        }
        if (!empty($validate['notuserid'])) {
            $sql = $sql->whereNotIn('id', function ($query) use ($validate) {
                $query->select('roleid')
                    ->from(with(new GpInstUserRole)->getTable())
                    ->where('userid', $validate['notuserid'])
                    ->where('statusid', '<>', -1);
            });
        }

        return $this->getGridData($request, $sql, [['field' => 'listorder', 'dir' => 'ASC'], ['field' => 'id', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstRoleRequest $request)
    {
        $validated = $request->validated();
        $validated['statusid'] = 1;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        return GpInstRole::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = VwGpInstRole::where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpInstRoleRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        GpInstRole::where('id', $validate['id'])->where('statusid', '<>', -1)->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        GpInstRole::where('id', $validate['id'])->where('statusid', '<>', -1)->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * Role дээр бүртгэлтэй ActionCode жагсаалтыг авах
     *
     * @AC gp061000
     * @param  mixed $request
     * @return Response
     */
    public function getRolePerms(Request $request)
    {
        $validate = $this->validate($request, [
            'roleid' => 'required',
            'notroleid' => 'nullable'
        ], [
            'roleid.required' => ResponseCodeEnum::required
        ]);
        $sql = VwInstRolePermList::where('roleid', $validate['roleid']);
        if (!empty($validate['notroleid'])) {
            $sql = $sql
                ->whereNotIn('ACTION_CODE', function ($query) use ($validate) {
                    $query->select('AC')
                        ->from(with(new GpInstRolePerms)->getTable())
                        ->where('roleid', $validate['notroleid'])
                        ->where('statusid', '!=', -1);
                });
        }
        return $this->getGridData($request, $sql,  [['field' => 'ACTION_CODE', 'dir' => 'ASC']]);
    }

    /**
     * storeRolePerms
     *
     * @AC gp061200
     * @param  mixed $request
     * @return void
     */
    public function storeRolePerms(Request $request)
    {
        $validate = $this->validate($request, [
            'permids' => 'required_without:AC|array',
            'roleid' => 'required',
            'instid' => 'nullable',
            'AC' => 'required_without:permids',
        ], [
            'permids.required' => ResponseCodeEnum::required,
            'roleid.required' => ResponseCodeEnum::required,
        ]);
        $user = auth()->user();
        $isadminrole = false;
        if (
            isset($validate['instid']) && $validate['instid'] == 0
            && $user->isadmin == 1
        ) {
            $isadminrole = true;
        }

        if (empty($validate['instid'])) {
            $validate['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validate['instid'] = auth()->user()->instid;
            }
        }

        if ($user->isadmin == 1) {
            $role = GpInstRole::where('id', $validate['roleid'])
                ->whereIn('instid', [$validate['instid'], '0'])
                ->where('statusid', '<>', -1)->first();
        } else {
            $role = GpInstRole::where('id', $validate['roleid'])
                ->where('instid', $validate['instid'])
                ->where('statusid', '<>', -1)->first();
        }
        if ($role) {
            if ($isadminrole) {
                $inst_perms = GpActionCode::select('ACTION_CODE')
                    ->where('statusid', 1);
            } else {
                $inst_perms = VwGpInstPerm::select('ACTION_CODE')
                    ->where('instid', $validate['instid']);
            }
            if (isset($validate['AC'])) {
                $validate['permids'] = [$validate['AC']];
            }
            $inst_perms = $inst_perms->whereIn('ACTION_CODE', $validate['permids'])
                ->whereNotIn('ACTION_CODE', function ($query) use ($validate) {
                    $query->select('AC')
                        ->from(with(new GpInstRolePerms)->getTable())
                        ->where('roleid', $validate['roleid'])
                        ->where('statusid', '!=', -1);
                })
                ->where('statusid', '<>', -1)->get();
            foreach ($inst_perms as $value) {
                GpInstRolePerms::create([
                    'roleid' => $validate['roleid'],
                    'AC' => $value->ACTION_CODE,
                    'statusid' => 1,
                    'isadmin' => $role->isadmin,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ]);
            }
        } else {
            $this->error("VC000009");
        }
        // GpInstRolePerms
    }

    /**
     * deleteRolePerms
     *
     * @param  mixed $request
     * @AC gp061400
     * @return void
     */
    public function deleteRolePerms(Request $request)
    {
        $validate = $this->validate($request, [
            'ids' => 'required|array',
            'roleid' => 'required',
            'instid' => 'nullable'
        ], [
            'permids.required' => ResponseCodeEnum::required,
            'roleid.required' => ResponseCodeEnum::required,
        ]);

        $user = auth()->user();
        if (empty($validate['instid'])) {
            $validate['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validate['instid'] = auth()->user()->instid;
            }
        }

        if ($user->isadmin == 1) {
            $role = GpInstRole::where('id', $validate['roleid'])
                ->whereIn('instid', [$validate['instid'], '0'])
                ->where('statusid', '<>', -1)->first();
        } else {
            $role = GpInstRole::where('id', $validate['roleid'])
                ->where('instid', $validate['instid'])
                ->where('statusid', '<>', -1)->first();
        }
        if ($role) {
            GpInstRolePerms::whereIn('id', $validate['ids'])
                ->where('statusid', '<>', -1)->where('roleid', $validate['roleid'])->update([
                    'statusid' => -1,
                    'updated_by' => auth()->user()->id
                ]);
        } else {
            $this->error("VC000009");
        }
    }
}
