<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstSeq;
use Modules\Gp\Http\Requests\GpInstSeqRequest;

class GpInstSeqController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, GpInstSeq::where('instid', auth()->user()->instid), [['field' => 'seqid', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validated = [];
        $validated['instid'] = auth()->user()->instid;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;

        $mainseqs = GpInstSeq::where('instid', 1)->get();
        $ownseqs = GpInstSeq::where('instid', auth()->user()->instid)->get();

        if (count($mainseqs) == 0) {
            $this->error('RC000027');
        }
        foreach ($mainseqs as $key => $mainseq) {
            if (count($ownseqs) > 0) {
                $ownseq = GpInstSeq::where('instid', auth()->user()->instid)->where('seqid', $mainseq->seqid)->first();
                if (empty($ownseq)) {
                    if ($mainseq->seqid == 'SYSDATE' || $mainseq->seqid == 'EODSYSDATE' || $mainseq->seqid == 'GLDATE') {
                        $validated['seqno'] = Carbon::now()->format('Y-m-d');
                    } else {
                        $validated['seqno'] = $mainseq->seqno;
                    }
                    $validated['seqid'] = $mainseq->seqid;
                    GpInstSeq::create($validated);
                }
            } else {
                if ($mainseq->seqid == 'SYSDATE' || $mainseq->seqid == 'EODSYSDATE' || $mainseq->seqid == 'GLDATE') {
                    $validated['seqno'] = Carbon::now()->format('Y-m-d');
                } else {
                    $validated['seqno'] = $mainseq->seqno;
                }
                $validated['seqid'] = $mainseq->seqid;
                GpInstSeq::create($validated);
            }
        }

    }
}
