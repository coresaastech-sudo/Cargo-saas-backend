<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApAcntInt;
use Modules\Ap\Entities\ApAcntLn;
use Modules\Ap\Entities\ApContractSignImage;
use Modules\Ap\Entities\ApCustContract;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Enums\ApAccountTypeEnum;
use Modules\Ap\Http\Services\ApAcntService;
use Modules\Ap\Http\Services\ApContractService;
use Illuminate\Support\Str;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApTxnJournal;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ln\Entities\LnAccountMor;
use Modules\Ln\Entities\Views\VwLnMorDetail;

class ApCustomerContractController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function ap010002(Request $request)
    {
        return $this->getGridData(
            $request,
            ApCustContract::select(
                'id',
                'instid',
                'cust_cif',
                'cust_name',
                'account_no',
                'prod_code',
                'txn_jrno',
                'statusid',
                'created_at',
            )
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid),
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }


    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function ap010102(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = ApCustContract::where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function ap010202(Request $request)
    {
        $validate = $this->validateMe($request, [
            'account_no' => 'required',
            'mode' => 'nullable|in:preview,commit',
        ], [
            'account_no.required' => "RC000011"
        ]);
        $mode = $request->mode ?? 'preview';

        $user = auth()->user();

        $exists = ApCustContract::where('account_no', $validate['account_no'])
            ->where('instid', $user->instid)
            ->first();

        if ($exists) {
            throw new MeException("Зээлийн данс дээр гэрээ үүссэн байна");
        }

        $acntType = ApAccountTypeEnum::fromString(Str::lower('Ln'));

        $acntService = new ApAcntService();
        $acntService->getAccountDetail($validate['account_no'], $acntType, $user->instid, true);

        $acntService->getAccountInt($validate['account_no'], $user->instid, $acntType);

        $ln = ApAcntLn::where('instid', $user->instid)
            ->where('acnt_code', $validate['account_no'])
            ->whereIn('acnt_type', ['LN', 'LINE'])
            ->first();

        if (!$ln) {
            throw new MeException('Зээлийн данс олдсонгүй.');
        }

        $mor = LnAccountMor::where('acntno', $ln->acnt_code)
            ->where('statusid', 1)
            ->where('instid', $user->instid)
            ->first();

        if (!$mor) {
            throw new MeException('Барьцаа хөрөнгө олдсонгүй.');
        }

        $collmor = VwLnMorDetail::where('morno', $mor->morno)
            ->where('statusid', 1)
            ->where('instid', $user->instid)
            ->first();

        if (!$collmor) {
            throw new MeException('Депозит данс олдсонгүй.');
        }

        $apuser = ApCustUser::where('statusid', '>=', 0)
            ->where('id', $ln->userid)
            ->first();

        Auth::setUser($apuser);

        $type_id = ($ln->acnt_type === 'LN') ? ApAccountTypeEnum::td : ApAccountTypeEnum::fromString(Str::lower($ln->acnt_type));

        $cust = ApCustomer::where('cif', $ln['cust_code'])->where('instid', $user->instid)->first();

        $int_rate = ApAcntInt::where('int_type_code', 'SIMPLE_INT')
            ->where('acnt_code', $ln->acnt_code)
            ->where('instid', $user->instid)->first();

        $sign = ApContractSignImage::where('created_by', $apuser->id)
            ->where('statusid', 1)
            ->where('instid', $user->instid)
            ->first();

        $lnService = ApTxnJournal::where('txn_acnt_code', $ln->acnt_code)
            ->where('instid', $user->instid)
            ->whereIn('oper_code', ['13610265', 'ln902021'])
            ->where('statusid', '<>', 1)
            ->first();

        if (!$lnService) {
            throw new MeException('Журнал гүйлгээ олдсонгүй.');
        }

        $service = new ApContractService();

        $contractData = [
            'account_no' => $ln->acnt_code,
            'acnt_code' => $collmor->collacntno,
            'prod_code' => $ln->prod_code,
            'cust_cif' => $cust->cif,
            'cust_name' => $cust->fname,
            'instid' => $cust->instid,
            'amount' => $ln->approv_amount ?? 0,
            'type_id' => $type_id,
            'operation' => $lnService->oper_code ?? null,
            'txn_jrno' => $lnService->core_jrno ?? null,
            'bank_code' => $ln->prod_code,
            'int_rate' => $int_rate->int_rate ?? 0,
            'sign_image_id' => $sign->id ?? null,
        ];

        if ($mode === 'preview') {
            $html = $service->previewContract($contractData, '10000001', $cust);
            return $html;
        }
        return $service->storeCustContract($contractData, '10000001', $cust);
    }
}
