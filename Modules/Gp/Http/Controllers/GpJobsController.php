<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpJobsList;
use Modules\Gp\Entities\GpFailedJobsList;
use Modules\Gp\Entities\GpJobInfo;

class GpJobsController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function indexJob(Request $request)
    {
        return $this->getGridData(
            $request,
            GpJobsList::select([
                'id',
                'attempts',
                'available_at',
                'created_at',
                'queue',
                'reserved_at'
            ]),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    public function indexFailedJob(Request $request)
    {
        return $this->getGridData(
            $request,
            GpFailedJobsList::select(['id', 'connection', 'failed_at', 'queue', 'uuid']),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function showFailedJob(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpFailedJobsList::where('id', $validate['id'])->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    public function gp080011()
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        return GpJobInfo::orderBy('lastexecdate', 'DESC')->get();
    }
}
