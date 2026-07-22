<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Gp\Entities\GPInstRole;
use Modules\Gp\Entities\GPInstUserRole;
use Modules\Gp\Entities\Views\VwGPInstRole;
use Modules\Gp\Http\Requests\GPInstRoleRequest;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Entities\Views\VwInstRolePermList;
use Modules\Gp\Entities\Views\VwGPInstPerm;
use Modules\Gp\Entities\GPInstPerm;

class AdInstRoleController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC ad060000
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'notuserid' => 'nullable'
        ]);

        $validated['instid'] = auth()->user()->instid;
        $sql = VwGPInstRole::where('instid', $validated['instid'])
            ->where('isadmin', 0)
            ->where('statusid', '<>', -1);
        if (!empty($validated['notuserid'])) {
            $sql = $sql->whereNotIn('id', function ($query) use ($validated) {
                $query->select('roleid')
                    ->from(with(new GPInstUserRole)->getTable())
                    ->where('userid', $validated['notuserid'])
                    ->where('statusid', '<>', -1);
            });
        }

        return $this->getGridData($request, $sql, [['field' => 'listorder', 'dir' => 'ASC'],['field' => 'id', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GPInstRoleRequest $request)
    {
        $validated = $request->validated();
        $validated['statusid'] = 1;
        $validated['isadmin'] = 0;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        return GPInstRole::create($validated);
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
        $GPinst = VwGPInstRole::where('id', $validate['id'])
            ->where('isadmin', 0)->where('statusid', '<>', -1)->first();
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
    public function update(GPInstRoleRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $validate['updated_by'] = auth()->user()->id;
        GPInstRole::where('id', $validate['id'])
            ->where('isadmin', 0)
            ->where('statusid', '<>', -1)->update($validate);
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
        GPInstRole::where('id', $validate['id'])
            ->where('isadmin', 0)->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }

    /**
     * Associate a primary rights group with an organization
     * @AC ad061200
     * @param array
     * @return Response
     */
    public function ad061200(Request $request)
    {
        $validate = $this->validate($request, [
            'instid' => 'required',
            'role_codes' => 'required|array',
        ], [
            'role_codes.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required
        ]);

        if (auth()->user()->isadmin != 1) {
            $this->error("RC000014", ['AC' => "ad061200"]);
        }

        $sql = VwInstRolePermList::select('ACTION_CODE')->whereIn('roleid', $validate['role_codes'])->distinct();
        $sql = $sql->whereNotIn('ACTION_CODE', function ($query) use ($validate) {
            $query->select('ACTION_CODE')
            ->from(with(new VwGPInstPerm)->getTable())
            ->where('instid', $validate['instid']);
        }) ;
        $ACTION_CODEs = $sql->get()->pluck('ACTION_CODE');

        foreach ($ACTION_CODEs as $key => $value) {
            GPInstPerm::create([
                'instid' => $validate['instid'],
                'statusid' => 1,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
                'AC' => $value
            ]);
        }
    }

    /**
     *Get a list of primary law groups
     * @AC ad061000
     * @param Request
     * @return Response
     */
    public function ad061000(Request $request)
    {
        $user = auth()->user();
        if ($user->isadmin != 1) {
            $this->error("RC000014", ['AC' => "ad061000"]);
        }
        $sql = VwGPInstRole::where('statusid', '<>', -1)->where('instid', 0);
        return $this->getGridData($request, $sql);
    }
}
