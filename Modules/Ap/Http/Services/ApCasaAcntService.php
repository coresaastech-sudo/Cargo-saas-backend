<?php

namespace Modules\Ap\Http\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApAcntDp;
use Modules\Ap\Transformers\ApCasaDetailResource;
use Modules\Ap\Transformers\ApCasaMobileDetailResource;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Http\Services\CoreService;

class ApCasaAcntService
{
    /**
     * Дансны жагсаалт дээр ирж байгаа датагаар данс үүсгэх
     *
     * @param  mixed $data
     * @return void
     */
    public function createAccountNes($data, $instid, $user)
    {
        $acnt = ApAcntDp::where('acnt_code', $data['acntCode'])->where('instid', $instid)->first();
        if (!$acnt) {
            $acnt = new ApAcntDp();
        }
        $acnt->instid = $instid;
        $acnt->userid = $user->id;
        $acnt->statusid = 1;
        $acnt->created_at = Carbon::now();
        $acnt->created_by = $user->id;
        $acnt->sys_no = $data['sysNo'] ?? null;
        $acnt->name = $data['acntName'] ?? null;
        $acnt->acnt_code = $data['acntCode'] ?? null;
        $acnt->cust_name = $data['custName'] ?? null;
        $acnt->is_secure = $data['isSecure'] ?? null;
        $acnt->cust_code = $data['custCode'] ?? null;
        $acnt->prod_code = $data['prodCode'] ?? null;
        $acnt->avail_bal = $data['availBalance'] ?? null;
        $acnt->current_bal = $data['balance'] ?? null;
        $acnt->is_allow_partial_liq = $data['isAllowPartialLiq'] ?? null;
        $acnt->acnt_type = $data['acntType'] ?? null;
        $acnt->prod_name = $data['prodName'] ?? null;
        $acnt->cur_code = $data['curCode'] ?? null;
        $acnt->status = $data['status'] ?? null;
        $acnt->save();
    }

    /**
     * Дансны дэлгэрэнгүй polaris дата-р шинэчлэх
     *
     * @param  mixed $data
     * @return void
     */
    public function updateAcntNesData($data, $instid)
    {
        $acnt = ApAcntDp::where('acnt_code', $data['acntCode'])->where('instid', $instid)->first();
        if (empty($acnt)) {
            $onlineteller = CoreService::getInstGp($instid, 'ONLINETELLERNUMBER');
            $user = GPInstUser::where('instid', $instid)->find(
                $onlineteller
            );

            $acnt = new ApAcntDp();
            $acnt->acnt_code = $data['acntCode'];
            $acnt->instid = $instid;
            $acnt->statusid = 1;
            $acnt->created_at = Carbon::now();
            $acnt->created_by = $user ? $user->id : $onlineteller;
        }

        $acnt->name = $data['name'] ?? null;
        $acnt->name2 = $data['name2'] ?? null;
        $acnt->cust_name = $data['custName'] ?? null;
        $acnt->company_code = $data['companyCode'] ?? null;
        $acnt->dormancy_date = formatDate($data['dormancyDate'] ?? null);
        $acnt->prod_code = $data['prodCode'] ?? null;
        $acnt->prod_name = $data['prodName'] ?? null;
        $acnt->brch_code = $data['brchCode'] ?? null;
        $acnt->brch_name = $data['brchName'] ?? null;
        $acnt->cur_code = $data['curCode'] ?? null;
        $acnt->maturity_date = formatDate($data['maturityDate'] ?? null);
        $acnt->status_custom = $data['statusCustom'] ?? null;
        $acnt->joint_or_single = $data['jointOrSingle'] ?? null;
        $acnt->status_date = formatDate($data['statusDate'] ?? null);
        $acnt->status_sys = $data['statusSys'] ?? null;
        $acnt->status_sys_name = $data['statusSysName'] ?? null;
        $acnt->cust_code = $data['custCode'] ?? null;
        $acnt->seg_code = $data['segCode'] ?? null;
        $acnt->seg_name = $data['segName'] ?? null;
        $acnt->acnt_type = $data['acntType'] ?? null;
        $acnt->acnt_type_name = $data['acntTypeName'] ?? null;
        $acnt->flag_stopped = $data['flagStopped'] ?? null;
        $acnt->flag_dormant = $data['flagDormant'] ?? null;
        $acnt->flag_stopped_int = $data['flagStoppedInt'] ?? null;
        $acnt->flag_stopped_payment = $data['flagStoppedPayment'] ?? null;
        $acnt->flag_frozen = $data['flagFrozen'] ?? null;
        $acnt->flag_no_credit = $data['flagNoCredit'] ?? null;
        $acnt->flag_no_debit = $data['flagNoDebit'] ?? null;
        $acnt->salary_acnt = $data['salaryAcnt'] ?? null;
        $acnt->corporate_acnt = $data['corporateAcnt'] ?? null;
        $acnt->open_date = formatDate($data['openDate'] ?? null);
        $acnt->closed_by = $data['closedBy'] ?? null;
        $acnt->start_date = formatDate($data['startDate'] ?? null);
        $acnt->closed_date = formatDate($data['closedDate'] ?? null);
        $acnt->last_dt_date = formatDate($data['lastDtDate'] ?? null);
        $acnt->last_ct_date = formatDate($data['lastCtDate'] ?? null);
        $acnt->last_seq_txn = $data['lastSeqTxn'] ?? null;
        $acnt->monthly_wd_count = $data['monthlyWdCount'] ?? null;
        $acnt->cap_method = $data['capMethod'] ?? null;
        $acnt->cap_method_name = $data['capMethodName'] ?? null;
        $acnt->cap_acnt_code = $data['capAcntCode'] ?? null;
        $acnt->cap_cur_code = $data['capCurCode'] ?? null;
        $acnt->min_amount = $data['minAmount'] ?? null;
        $acnt->max_amount = $data['maxAmount'] ?? null;
        $acnt->paymt_default = $data['paymtDefault'] ?? null;
        $acnt->od_contract_code = $data['odContractCode'] ?? null;
        $acnt->od_class_no = $data['odClassNo'] ?? null;
        $acnt->od_class_name = $data['odClassName'] ?? null;
        $acnt->acnt_manager = $data['acntManager'] ?? null;
        $acnt->od_type = $data['odType'] ?? null;
        $acnt->od_flag_wroff_int = $data['odFlagWroffInt'] ?? null;
        $acnt->od_flag_wroff = $data['odFlagWroff'] ?? null;
        $acnt->acrint_bal = $data['acrintBal'] ?? null;
        $acnt->avail_bal = $data['availBal'] ?? null;
        $acnt->avail_limit = $data['availLimit'] ?? null;
        $acnt->blocked_bal = $data['blockedBal'] ?? null;
        $acnt->current_bal = $data['currentBal'] ?? null;
        $acnt->daily_basis_code = $data['dailyBasisCode'] ?? null;
        $acnt->cust_type = $data['custType'] ?? null;
        $acnt->od_limit = $data['odLimit'] ?? null;
        $acnt->passbook_facility = $data['passbookFacility'] ?? null;
        $acnt->penalty_rcv = $data['penaltyRcv'] ?? null;
        $acnt->total_avail_bal = $data['totalAvailBal'] ?? null;
        $acnt->unex = $data['unex'] ?? null;
        $acnt->unexint_rcv = $data['unexintRcv'] ?? null;
        $acnt->unexint_rcv_bill = $data['unexintRcvBill'] ?? null;
        $acnt->is_secure = $data['isSecure'] ?? null;
        $acnt->read_name = $data['readName'] ?? null;
        $acnt->read_bal = $data['readBal'] ?? null;
        $acnt->read_tran = $data['readTran'] ?? null;
        $acnt->do_tran = $data['doTran'] ?? null;
        $acnt->get_with_secure = $data['getWithSecure'] ?? null;
        $acnt->status = $data['statusSys'] ?? null;
        $acnt->is_allow_partial_liq = $data['isAllowPartialLiq'] ?? null;
        $acnt->save();
    }

    public function detailAcntData($acnt_code, $instid, $isBackOff = false)
    {
        if ($isBackOff) {
            return new ApCasaDetailResource(ApAcntDp::where('acnt_code', $acnt_code)->where('instid', $instid)->first());
        }
        return new ApCasaMobileDetailResource(ApAcntDp::where('acnt_code', $acnt_code)->where('instid', $instid)->first());
    }

    public function updateAcntCoreData($data, $isntid)
    {
        $acnt = ApAcntDp::where('acnt_code', $data->acntno)->where('instid', $isntid)->first();
        $acnt->name = $data->name;
        $acnt->name2 = $data->name2;
        $acnt->cust_name = $data->cust_name;
        $acnt->company_code = $isntid;
        $acnt->dormancy_date = $data->dormancyDate;
        $acnt->prod_code = $data->prodcode;
        $acnt->prod_name = $data->prodcode_name;
        $acnt->brch_code = $data->brchno;
        $acnt->brch_name = $data->brchno_name;
        $acnt->cur_code = $data->curcode;
        $acnt->start_date =  $data->openeddate;
        $acnt->maturity_date = $data->termexpdate;
        $acnt->term_len = $data->termlen;
        $acnt->status = $data->statusid;
        // $acnt->status_custom = $data->statusCustom;
        // $acnt->joint_or_single = $data->jointOrSingle;
        // $acnt->status_date = $data->statusDate;
        $acnt->status_sys = $data->statusid;
        $acnt->status_sys_name = $data->statusName;
        $acnt->cust_code = $data->custno;
        $acnt->seg_code = $data->segcode;
        $acnt->seg_name = $data->segcode;
        $acnt->acnt_type = $data->acntType;
        $acnt->acnt_type_name = $data->acntTypeName;
        $acnt->flag_stopped = $data->flagStopped;
        $acnt->flag_dormant = $data->flagDormant;
        $acnt->flag_stopped_int = $data->flagStoppedInt;
        $acnt->flag_stopped_payment = $data->flagStoppedPayment;
        $acnt->flag_frozen = $data->flagFrozen;
        $acnt->flag_no_credit = $data->flagNoCredit;
        $acnt->flag_no_debit = $data->flagNoDebit;
        $acnt->salary_acnt = $data->salaryAcnt;
        $acnt->corporate_acnt = $data->corporateAcnt;
        $acnt->open_date = $data->openeddate;
        // $acnt->closed_by = $data->closedBy;
        $acnt->closed_date = $data->closeddate;
        $acnt->last_dt_date = $data->lasttxndate;
        $acnt->last_ct_date = $data->lasttxndate;
        // $acnt->last_seq_txn = $data->lastSeqTxn;
        $acnt->monthly_wd_count = $data->monthlyWdCount;
        $acnt->cap_method = $data->crcapmethod;
        $acnt->cap_method_name = $data->crcapmethod;
        $acnt->cap_acnt_code = $data->crcapacnt;
        $acnt->cap_cur_code = $data->capCurCode;
        $acnt->min_amount = $data->minbalance;
        $acnt->max_amount = $data->maxbalance;
        $acnt->paymt_default = $data->paymtDefault;
        $acnt->od_contract_code = $data->odorderno;
        $acnt->od_class_no = $data->odClassNo;
        $acnt->od_class_name = $data->odClassName;
        $acnt->acnt_manager = $data->acntManager;
        $acnt->od_type = $data->odType;
        $acnt->od_flag_wroff_int = $data->odFlagWroffInt;
        $acnt->od_flag_wroff = $data->odFlagWroff;
        $acnt->acrint_bal = $data->crint2cap;
        $acnt->avail_bal = $data->availBal;
        $acnt->avail_limit = $data->availLimit;
        $acnt->blocked_bal = $data->blockedBal;
        $acnt->current_bal = $data->currentbal;
        $acnt->daily_basis_code = $data->dailyBasisCode;
        $acnt->cust_type = $data->custType;
        $acnt->od_limit = $data->odLimit;
        $acnt->passbook_facility = $data->passbookFacility;
        $acnt->penalty_rcv = $data->penaltyRcv;
        $acnt->total_avail_bal = $data->totalAvailBal;
        $acnt->unex = $data->unex;
        $acnt->unexint_rcv = $data->unexintRcv;
        $acnt->unexint_rcv_bill = $data->unexintRcvBill;
        $acnt->is_secure = $data->isSecure;
        $acnt->read_name = $data->readName;
        $acnt->read_bal = $data->readBal;
        $acnt->read_tran = $data->readTran;
        $acnt->do_tran = $data->doTran;
        $acnt->get_with_secure = $data->getWithSecure;
        $acnt->is_allow_partial_liq = $data->isAllowPartialLiq;
        $acnt->save();
    }
}
