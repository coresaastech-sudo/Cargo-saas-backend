<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstRolePerms;
use Modules\Gp\Entities\GpInstTxnType;
use Modules\Gp\Entities\Views\VwGpInstQual;
use Modules\Gp\Entities\Views\VwGpInstTxnPerm;
use Modules\Gp\Entities\Views\VwGpInstTxnTypeDetail;
use Modules\Gp\Entities\Views\VwGpInstTxnTypeList;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Requests\GpInstTxnTypeRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstTxnTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, VwGpInstTxnTypeList::where('statusid', 1)
            ->where('instid', auth()->user()->instid), [['field' => 'ACTION_CODE', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @AC gp015103
     * @param GpInstTxnTypeRequest $request
     * @return Response
     */
    public function store(GpInstTxnTypeRequest $request)
    {
        $validated = $request->validated();
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        if (Str::upper($validated['moduleid']) == 'TR') {
            $trLast = GpInstTxnType::where('instid', auth()->user()->instid)
                ->whereRaw("upper(moduleid) = 'TR'")
                ->orderBy('created_at', 'desc')->first();
            $seq = '000001';
            if ($trLast) {
                $seq = fillZeroString(substr($trLast->ACTION_CODE, -6) * 1 + 1, 6);
            }
            $validated['ACTION_CODE'] = ('tr' . $seq);
            $validated['qualifier'] = 0;
            $validated['txnopt'] = 4;
            $validated['txntype'] = 2;
        }
        $isdupl = GpInstTxnType::where('instid', auth()->user()->instid)->where('statusid', 1)
            ->where('ACTION_CODE', $validated['ACTION_CODE'])->first();

        if ($isdupl) {
            $this->error('RC000028');
        }

        return GpInstTxnType::create($validated);
    }

    /**
     * Show the specified resource.
     * @AC gp015004
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'ACTION_CODE' => 'required'
        ], [
            'ACTION_CODE.required' => "RC000011"
        ]);
        $GPinst = VwGpInstTxnTypeDetail::where('instid', auth()->user()->instid)
            ->where('ACTION_CODE', $validated['ACTION_CODE'])
            ->where('statusid', 1)->first();

        if ($GPinst) {
            if ($GPinst->qualifier == 1) {
                $GPinst->quals = VwGpInstQual::where('txncode', $validated['ACTION_CODE'])
                    ->where('instid',  auth()->user()->instid)->where('statusid', 1)
                    ->orderBy('prodcode', 'ASC')->orderBy('clscode', 'ASC')->get();
            }
            return $GPinst;
        } else {
            $this->error("RC000010", ['id' => $validated['ACTION_CODE']]);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpInstTxnTypeRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['ACTION_CODE'])) {
            $this->error("RC000011");
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstTxnType::where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->find($validated['ACTION_CODE']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'ACTION_CODE' => 'required'
        ], [
            'ACTION_CODE.required' => "RC000011"
        ]);
        $count = GpInstTxnType::where('instid', auth()->user()->instid)->where('statusid', '<>', 1)
            ->where('ACTION_CODE', $validate['ACTION_CODE'])->count();

        GpInstTxnType::where('instid', auth()->user()->instid)
            ->where('ACTION_CODE', $validate['ACTION_CODE'])->where('statusid', 1)->update([
                'statusid' => $count ? ($count + 1) * -1 : -1,
                'updated_by' => auth()->user()->id,
            ]);
    }

    /**
     * @AC gp015013
     */
    public function getInstPerm(Request $request)
    {
        $validate = $this->validate($request, [
            'instid' => 'nullable',
            'notroleid' => 'nullable'
        ]);
        $user = auth()->user();
        if (empty($validate['instid'])) {
            $validate['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validate['instid'] = auth()->user()->instid;
            }
        }
        $sql = VwGpInstTxnPerm::where('instid', $validate['instid']);
        if (!empty($validate['notroleid'])) {
            $sql = $sql->whereNotIn('ACTION_CODE', function ($query) use ($validate) {
                $query->select('AC')
                    ->from(with(new GpInstRolePerms())->getTable())
                    ->where('roleid', $validate['notroleid'])
                    ->where('statusid', '<>', -1);
            });
        }
        return $this->getGridData(
            $request,
            $sql,
            [
                ['field' => 'txntype', 'dir' => 'ASC'],
                ['field' => 'ACTION_CODE', 'dir' => 'ASC']
            ]
        );
    }

    public function getInstTxnSettings(Request $request)
    {
        $validate = $this->validateMe($request, [
            'ACTION_CODE' => 'required'
        ], [
            'ACTION_CODE.required' => "RC000011"
        ]);

        $GPinst = VwGpInstTxnTypeDetail::select(['name', 'rtypecode'])
            ->where('instid', auth()->user()->instid)
            ->where('ACTION_CODE', $validate['ACTION_CODE'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $detail = new VwGpInstTxnTypeDetail();
            // Монгол банк ханшийн төрөл
            $detail->rtypecode = 1;
            $detail->name = "Үндсэн ханшийн төрөл тохируулаагүй байна.";
            return  $detail;
        }
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_txn_type
        );
    }
}
