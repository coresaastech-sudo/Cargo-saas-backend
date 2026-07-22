<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstRolePerms;
use Modules\Gp\Entities\GpInstUserRole;
use Modules\Gp\Entities\GpModuleList;
use Modules\Gp\Http\Requests\GpModuleRequest;
use Modules\Gp\Http\Services\CoreService;

class GpModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function getCheckModules(Request $request)
    {
        $validate = $this->validate($request, [
            'issubmenu' => 'nullable'
        ]);
        $user = auth()->user();
        $txndate = CoreService::getTxnDate($user->instid);
        $sql = GpModuleList::whereIn('AC', function ($query) use ($txndate) {
            $instUserRole = with(new GpInstUserRole)->getTable();
            $rolePerm = with(new GpInstRolePerms)->getTable();
            $query->select('AC')
                ->from($rolePerm)
                ->join($instUserRole, function ($join) use ($instUserRole, $rolePerm, $txndate) {
                    $join->on($instUserRole . '.roleid', '=', $rolePerm . '.roleid');
                    $join->where($instUserRole . '.statusid', '1')
                        ->where($instUserRole . '.userid', auth()->user()->id)
                        ->where($instUserRole . '.startdate', '<=', $txndate)
                        ->where($instUserRole . '.enddate', '>=', $txndate);
                })
                ->where($rolePerm . '.statusid', '1');
        })
            ->where('statusid', '1')->where('typeid', 1)
            ->whereIn('isadmin', [0, auth()->user()->isadmin])
            ->orderBy('listorder');
        if (@$validate['issubmenu']) {
            return $sql->whereNotNull('parentid')->get();
        }
        return $sql->whereNull('parentid')->get();
    }


    /**
     * Модулийн жагсаалт
     *
     * @param  mixed $request
     * @AC gp030000
     * @return array
     */
    public function index(Request $request)
    {
        $query = GpModuleList::select('GP_module_list.*', 'GP_module_list.moduleid as id')->where('statusid', '<>', -1)
            ->when(request('parentid', '') != '', function ($q) {
                $q->where('parentid', request('parentid'));
            })
            ->when(request('parentid', '') == '', function ($q) {
                $q->whereNull('parentid');
            })->orderBy('listorder');
        return $this->getGridData($request, $query);
    }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpModuleRequest $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validated = $request->validated();
        $validated['statusid'] = 1;
        return GpModuleList::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpModuleList::select('GP_module_list.*', 'GP_module_list.moduleid as id')->where('moduleid', $validate['id'])->where('statusid', '<>', -1)->first();
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
    public function update(GpModuleRequest $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validate = $request->validated();
        if (empty($validate['moduleid'])) {
            $this->error("RC000011");
        }
        GpModuleList::where('moduleid', $validate['moduleid'])->where('statusid', '<>', -1)->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        GpModuleList::where('moduleid', $validate['id'])->where('statusid', '<>', -1)->update([
            'statusid' => -1,
        ]);
    }
}
