<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstGp;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Requests\GpInstGpRequest;
use Modules\Gp\Http\Services\CoreService;

class GpInstGpController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp011002
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            GpInstGp::where('instid', auth()->user()->instid)
                ->where('itemtype', '<>', 9),
            [['field' => 'id', 'dir' => 'ASC']]
        );
    }

    /**
     * Display a single row of the resource.
     * @AC gp011502
     * @return Response
     */
    public function get(Request $request)
    {

        $validate = $this->validate($request, [
            'itemname' => 'required'
        ], [
            'itemname.required' => "RC000011"
        ]);
        $instid = auth()->user()->instid;

        $gp = GpInstGp::where('instid', $instid)->where('itemname', $validate['itemname'])->first();
        if (!$gp || empty($gp->itemvalue)) {
            $this->error('RC000162', [
                'field' => $validate['itemname']
            ]);
        }

        return [
            'itemvalue' => $gp->itemvalue
        ];
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @AC gp011202
     * @return Response
     */
    public function store()
    {
        $this->createGp(auth()->user()->instid);
    }

    public function createGp($instid)
    {
        $gps = GpInstGp::where('instid', 1)
            ->whereNotIn('itemname', function ($query) use ($instid) {
                $query->select('itemname')
                    ->from(with(new GpInstGp)->getTable())
                    ->where('instid', $instid);
            })->get();
        $datas = [];
        foreach ($gps as $key => $value) {
            $datas[] = [
                'itemname' => $value['itemname'],
                'itemdesc' => $value['itemdesc'],
                'itemdesc2' => $value['itemdesc2'],
                'itemvalue' => $value['itemvalue'],
                'itemadditional' => $value['itemadditional'],
                'itemadditional2' => $value['itemadditional2'],
                'itemtype' => $value['itemtype'],
                'groupname' => $value['groupname'],
                'instid' => $instid,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
                'updated_at' => getNow(),
                'created_at' => getNow(),
            ];
        }
        GpInstGp::insert($datas);
    }

    public function update(GpInstGpRequest $request)
    {
        $validated = $request->validated();

        if (empty($validated['id'])) {
            $user = auth()->user();
            if ($user->instid == 1) {
                GpInstGp::create([
                    'itemname' => $validated['itemname'],
                    'itemdesc' => $validated['itemdesc'] ?? null,
                    'itemdesc2' => $validated['itemdesc2'] ?? null,
                    'itemvalue' => $validated['itemvalue'],
                    'itemadditional' => $validated['itemadditional'] ?? null,
                    'itemadditional2' => $validated['itemadditional2'] ?? null,
                    'itemtype' => $validated['itemtype'] ?? 0,
                    'groupname' => $validated['groupname'] ?? null,
                    'instid' => $user->instid,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ]);
            } else {
                $this->error("RC000011");
            }
        } else {
            $instid =  auth()->user()->instid;
            $gpsusp = GpInstGp::where('id', $validated['id'])
                ->where('instid', $instid)
                ->first();

            if (!$gpsusp) {
                $this->error('RC000027');
            }
            if ($instid != 1) {
                $validated['itemtype'] = $gpsusp->itemtype;
            }
            $validated['updated_by'] = auth()->user()->id;
            GpInstGp::where('id', $validated['id'])->update($validated);
        }
    }

    /**
     * Cache цэвэрлэх
     * @AC gp011902
     * @return void
     */
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst_gp
        );
    }
}
