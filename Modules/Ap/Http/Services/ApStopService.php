<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApInstStopService;
use Modules\Gp\Entities\GPInstList;

class ApStopService
{
    /**
     * Үйлчилгээ зогссон байгаа эсэхийг шалгах
     *
     * @param  mixed $validated = [
     *  serviceCode - Үйлчилгээний дугаар
     *  instid - байгууллагын дугаар
     *  acntCode - LINE үед дансны дугаар
     *  prodCode - td нээх үед бүтээгдэхүүний дугаар
     * ]
     * @return array
     */
    public function checkStopSrevice($validated)
    {
        $prodcode = '';
        switch ($validated['serviceCode']) {
            case '10000001':
                // LINE
                if (isset($validated['acntCode'])) {
                    $acnt = ApAcntLn::where('acnt_code', $validated['acntCode'])
                        ->where('instid', $validated['instid'])->first();
                    if ($acnt) {
                        $prodcode = $acnt->prod_code;
                    } else {
                        throw new MeException('RC000022');
                    }
                } else {
                    throw new MeException('Шугамын данс сонгогдоогүй байна.');
                }
                break;
            case '10000002':
                // Хадгаламж барьцаалсан зээл
                $polaris = new PolarisApiRequestService($validated['instid']);
                $prodcode = $polaris->savingLoan->loanAcnt->prodCode;
                break;
            case '20000001':
                // Хадгаламжийн данс нээх
                $prodcode = $validated['prodCode'];
                break;
            default:
                # code...
                break;
        }
        $ss = ApInstStopService::where('instid', $validated['instid'])->where('prod_code', $prodcode)->where('statusid', '<>', -1)->first();

        if ($ss) {
            $now = new Carbon();
            $begindate = new Carbon($ss->begin_date);
            $enddate = new Carbon($ss->end_date);
            if ($now->diffInDays($begindate, false) <= 0) {
                if ($now->diffInDays($enddate, false) >= 0) {
                    $inst = GPInstList::where('id', $validated['instid'])->where('statusid', '<>', -1)->first();
                    // return [
                    //     'message' => $inst->name . ' байгууллага дээр үйлчилгээг ' . $ss->end_date->format('Y-m-d') . ' дуустал түр зогсоосон байна.',
                    //     'desc' => $ss->description,
                    //     'status' => false
                    // ];
                    throw new MeException($inst->name . ' байгууллага дээр үйлчилгээг ' . $ss->end_date->format('Y-m-d') . ' дуустал түр зогсоосон байна.');
                }
            }
        }

        return [
            'message' => 'Үйлчилгээ хэвийн',
            'status' => 1
        ];
    }
}
