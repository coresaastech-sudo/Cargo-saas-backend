<?php

namespace Modules\Ap\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Ap\Entities\ApAcntCd;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApAcntTxn;
use Modules\Ap\Entities\ApTxnJournal;
use Modules\Ap\Entities\Views\VwApTxnJournal;

class ApCustomerAcntController extends Controller
{
    /**
     * Display a listing of the resource.
     * Acnt DP list
     * @return Response
     */
    public function ap010003(Request $request)
    {
        return $this->getGridData(
            $request,
            ApAcntDp::select(
                'id',
                'acnt_code',
                'acnt_type',
                'prod_name',
                'cust_code',
                'name',
                'current_bal',
                'cur_code',
                'statusid',
                'created_at',
            )
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid)->latest('created_at'),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }


    /**
     * Show the specified resource.
     * Acnt DP show
     * @param int $id
     * @return Response
     */
    public function ap010103(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = ApAcntDp::select(
            'acnt_code',
            'name',
            'acnt_type',
            'acnt_type_name',
            'prod_name',
            'current_bal',
            'avail_bal',
            'avail_limit',
            'brch_name',
            'class_name',
            'cur_code',
            'cust_code',
            'cust_name',
            'daily_basis_code',
            'acrint_bal',
            'int_rate',
            'maturity_date',
            'maturity_option_name',
            'od_class_name',
            'od_limit',
            'od_type',
            'open_date',
            'seg_name',
            'start_date',
            'status_sys_name',
            'statusid',
            'term_basis',
            'term_len',
            'total_avail_bal',
            'instid',

        )->where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Display a listing of the resource.
     * Acnt LN list
     * @return Response
     */
    public function ap010004(Request $request)
    {
        return $this->getGridData(
            $request,
            ApAcntLn::select(
                'id',
                'acnt_code',
                'acnt_type',
                'prod_name',
                'cust_code',
                'name',
                'princ_bal',
                'cur_code',
                'statusid',
                'created_at',
            )
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid)->latest('created_at'),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }


    /**
     * Show the specified resource.
     * Acnt LN show
     * @param int $id
     * @return Response
     */
    public function ap010104(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = ApAcntLn::select(
            'acnt_code',
            'name',
            'acnt_manager_name',
            'acnt_type',
            'princ_bal',
            'prod_code',
            'prod_name',
            'prod_type',
            'adv_amount',
            'adv_date',
            'approv_amount',
            'approv_date',
            'cust_code',
            'cust_name',
            'brch_name',
            'class_name',
            'class_qlt_name',
            'class_trm_name',
            'cur_code',
            'acr_baseint_bal',
            'acr_commint_bal',
            'daily_basis_code',
            'end_date',
            'extend_count',
            'instid',
            'last_txn_date',
            'limit',
            'next_schd_amt',
            'next_schd_date',
            'next_schd_int',
            'prepaid_baseint_bal',
            'purpose_name',
            'repay_acnt_code',
            'repay_acnt_name',
            'sec_acnt_code',
            'sec_acnt_name',
            'seg_name',
            'start_date',
            'statusid',
            'sub_purpose_name',
            'term_basis',
            'term_len',
            'theor_bal',
            'total_bal',
        )->where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }
    /**
     * Display a listing of the resource.
     * Acnt CD list
     * @return Response
     */
    public function ap010005(Request $request)
    {
        return $this->getGridData(
            $request,
            ApAcntCd::select(
                'id',
                'acnt_code',
                'acnt_type',
                'prod_code_name',
                'cust_code',
                'name',
                'avail_balance',
                'cur_code',
                'statusid',
                'created_at',
            )
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }


    /**
     * Show the specified resource.
     * Acnt CD show
     * @param int $id
     * @return Response
     */
    public function ap010105(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = ApAcntCd::select(
            'acnt_code',
            'acnt_type',
            'actual_start_date',
            'avail_balance',
            'name',
            'prod_code',
            'prod_code_name',
            'brch_name',
            'class_name',
            'cur_code',
            'cust_code',
            'daily_basis_code',
            'due_date',
            'end_date',
            'grace_days',
            'id',
            'instid',
            'last_txn_date',
            'min_pay_amt',
            'repayment_acnt',
            'repayment_mode_name',
            'repayment_type_name',
            'seg_code',
            'start_date',
            'statement_date',
            'status_id_name',
            'status_name',
            'statusid',
            'total_exp_amount',
            'total_limit',
        )->where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }
    /**
     * Display a listing of the resource.
     * Гүйлгээний list
     * @return Response
     */
    public function ap010006(Request $request)
    {
        $user = auth()->user();
        $sql = VwApTxnJournal::select(
            'id',
            'txn_date',
            'core_jrno',
            'txn_acnt_code',
            'oper_code',
            'txn_amount',
            'cont_acnt_code',
            'txn_desc',
            'instid',
            'inst_name',
            'statusid',
            'created_at',
        )
            // ->where('statusid', 1)
            ;
        if ($user->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }
        return $this->getGridData(
            $request,
            $sql,
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }
    public function ap010106(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $sql = ApTxnJournal::select(
            'txn_acnt_code',
            'txn_date',
            'txn_amount',
            'cur_code',
            'txn_jrno',
            'txn_corr_jrno',
            'txn_desc',
            'txn_type',
            'identity_type',
            'cont_acnt_code',
            'cont_amount',
            'cont_bank_code',
            'cont_cur_code',
            'cont_rate',
            'core_corr_jrno',
            'core_jrno',
            'err_desc',
            'fee_id',
            'fee_inst_amount',
            'fee_sys_amount',
            'internal_cont_acnt_code',
            'is_preview',
            'is_preview_fee',
            'is_supervisor',
            'jr_item_no_and_incr',
            'oper_code',
            'parent_jrno',
            'rate',
            'source_type',
            'tcust_addr',
            'tcust_contact',
            'tcust_name',
            'tcust_register',
            'tcust_register_mask',
            'statusid',
            'created_by',
            'updated_by',
            'prodcode'
        )->where('id', $validate['id']);

        $user = auth()->user();
        if ($user->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }

        $sql = $sql->first();
        if ($sql) {
            return $sql;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
