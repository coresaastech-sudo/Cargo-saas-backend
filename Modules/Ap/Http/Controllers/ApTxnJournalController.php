<?php

namespace Modules\Ap\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Ap\Entities\ApTxnJournal;
use Modules\Ap\Entities\Views\VwApTxnJournal;

class ApTxnJournalController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function ap010007(Request $request)
    {

        $user = auth()->user();
        if ($user->isadmin != '1') {
            $result = VwApTxnJournal::select(
                'id',
                'instid',
                'inst_name',
                'created_at',
                'tcust_name',
                'txn_amount',
                'cur_code',
                'txn_desc',
                'statusid',
            )->where('statusid', '<>', -1)->where('instid', $user->instid);
        } else {
            $result = VwApTxnJournal::select(
                'id',
                'instid',
                'inst_name',
                'created_at',
                'tcust_name',
                'txn_amount',
                'cur_code',
                'txn_desc',
                'statusid',
            )->where('statusid', '<>', -1);
        }


        return $this->getGridData(
            $request,
            $result->selectRaw(
                "(CASE WHEN (txn_type = '1') THEN 'Зарлага' ELSE 'Орлого' END) as txn_type_name,
                (CASE WHEN (statusid = 0) THEN 'Хүлээгдэж буй' ELSE
                    CASE WHEN (statusid = 1) THEN 'Амжилттай' ELSE
                        CASE WHEN (statusid = 2) THEN 'Алдаатай' ELSE
                            CASE WHEN (statusid = 3) THEN 'Буцааалт хийгдсэн' ELSE
                                CASE WHEN (statusid = -1) THEN 'Устгагдсан' ELSE 'Төлөв тодорхойгүй' END
                            END
                        END
                    END
                END) as status_name",
            ),
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }


    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function ap010107(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = ApTxnJournal::
        select(
            'id',
            'instid',
            'txn_jrno',
            'jr_item_no_and_incr',
            'txn_corr_jrno',
            'txn_amount',
            'txn_acnt_code',
            'cur_code',
            'txn_date',
            'txn_desc',
            'internal_cont_acnt_code',
            'tcust_name',
            'tcust_register',
            'core_jrno',
            'core_corr_jrno',
            'cont_acnt_code',
            'cont_amount',
            'cont_bank_code',
            'cont_cur_code',
            'cont_rate',
            'err_desc',
            'fee_id',
            'fee_inst_amount',
            'fee_sys_amount',
            'oper_code',
            'rate',
            'created_at',
            'created_by',
            'statusid',
        )->
        selectRaw(
            "(CASE WHEN (txn_type = '1') THEN 'Зарлага' ELSE 'Орлого' END) as txn_type_name,
            (CASE WHEN (statusid = 0) THEN 'Хүлээгдэж буй' ELSE
                CASE WHEN (statusid = 1) THEN 'Амжилттай' ELSE
                    CASE WHEN (statusid = 2) THEN 'Алдаатай' ELSE
                        CASE WHEN (statusid = 3) THEN 'Буцааалт хийгдсэн' ELSE
                            CASE WHEN (statusid = -1) THEN 'Устгагдсан' ELSE 'Төлөв тодорхойгүй' END
                        END
                    END
                END
            END) as status_name",
        )
        ->where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
