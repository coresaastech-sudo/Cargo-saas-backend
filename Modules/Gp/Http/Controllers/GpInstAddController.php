<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Gp\Entities\GpInstAdd;
use Modules\Gp\Entities\GpInstAddField;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Entities\Views\VwGpInstAdd;


class GpInstAddController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     * @AC - gp011003
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwGpInstAdd::where('instid', auth()->user()->instid)
                ->where('statusid', 1),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $datas = $request->all();
        $inst = GpInstList::where('id', $instid)->first();
        if ($inst) {
            foreach ($datas as $key => $value) {
                if (gettype($key) == 'integer') {
                    $dtl = GpInstAdd::where('keyfield', $key)
                        ->where('instid', $instid)
                        ->where('statusid', '<>', 0)->first();
                    if ($dtl) {
                        $dtl->update([
                            'itemvalue' => $value,
                            'updated_by' => $userid
                        ]);
                    } else {
                        $cnst = GpInstAddField::where('id', $key)
                            ->where('instid', $instid)
                            ->where('statusid', '<>', -1)->first();
                        if ($cnst) {
                            GpInstAdd::create([
                                'keyfield' => $key,
                                'itemvalue' => $value,
                                'statusid' => 1,
                                'instid' => $instid,
                                'created_by' => $userid,
                                'updated_by' => $userid,
                            ]);
                        }
                    }
                } else {
                    $dtl = VwGpInstAdd::where('code', $key)
                        ->where('instid', $instid)
                        ->where('statusid', '<>', 0)->first();
                    if ($dtl) {
                        $bs = GpInstAdd::where('id', $dtl->id)
                        ->where('instid', auth()->user()->instid)->first();
                        if ($bs) {
                            $bs->update([
                                'itemvalue' => $value,
                                'updated_by' => auth()->user()->id
                            ]);
                        }
                    } else {
                        $cnst = GpInstAddField::where('code', $key)
                            ->where('instid', $instid)
                            ->where('statusid', '<>', -1)->first();
                        if ($cnst) {
                            GpInstAdd::create([
                                'keyfield' => $cnst->id,
                                'itemvalue' => $value,
                                'statusid' => 1,
                                'instid' => $instid,
                                'created_by' => $userid,
                                'updated_by' => $userid,
                            ]);
                        }
                    }
                }
            }
        } else {
            $this->error('RC000015');
        }
    }
}
