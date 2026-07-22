<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Modules\Ad\Entities\AdCgwTxnDescCombination;
use Modules\Gp\Entities\GPInstUser;
use Illuminate\Support\Str;
use Modules\Dp\Entities\DpAccountType;
use Modules\Dp\Entities\DpAccount;
use Modules\Gp\Enums\AccountTypeEnum;
use Modules\Ia\Entities\IaAccount;
use Modules\Ia\Entities\IaAccountType;
use Modules\Ln\Entities\LnAccount;
use Modules\Ln\Entities\LnAccountType;

class AdCgwTxnDescCombinationController extends Controller
{
    /**
     * Display a listing of the resource.
     * ad101000
     * @return Response
     */
    public function ad070002(Request $request)
    {
        return $this->getGridData(
            $request,
            AdCgwTxnDescCombination::where('instid', auth()->user()->instid)->where('statusid', '<>', -1),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Show the specified resource.
     * ad101100
     * @param int $id
     * @return Response
     */
    public function ad070102(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011",
        ]);

        $cgwtxn = AdCgwTxnDescCombination::where('instid', auth()->user()->instid)
            ->where('id', $validated['id'])
            ->where('statusid', '<>', -1)->first();
        if ($cgwtxn) {
            return $cgwtxn;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Store a newly created resource in storage.
     * ad101200
     * @param Request $request
     * @return Response
     */
    public function ad070202(Request $request)
    {
        $validate = $this->validate($request, [
            'value' => 'required',
            'type' => 'required',
            'is_income' => 'required|numeric',
            'prodcode' => 'required_if:type,1',
            'acntno' => 'required_unless:type,1',
            'acnttype' => 'required',
        ]);
        $instid = auth()->user()->instid;
        $acnttype = $validate['acnttype'] ?? null;

        if ($validate['type'] == 1) {
            if ($acnttype == AccountTypeEnum::dp) {
                $prod = DpAccountType::where('instid', $instid)->where('prodcode', $validate['prodcode'])->where('statusid', 1)->first();
            } else if ($acnttype == AccountTypeEnum::ln) {
                $prod = LnAccountType::where('instid', $instid)->where('prodcode', $validate['prodcode'])->where('statusid', 1)->first();
            } else {
                $prod = IaAccountType::where('instid', $instid)->where('typecode', $validate['prodcode'])->where('statusid', 1)->first();
            }
            if ($prod) {
                $validate['name'] = Str::upper($prod->name);
                $validate['name2'] = Str::upper($prod->name2);
            }
        }

        if ($validate['type'] == 2) {
            if ($acnttype == AccountTypeEnum::dp) {
                $account = DpAccount::where('instid',  $instid)->where('acntno', $validate['acntno'])->where('statusid', 1)->first();
            } else if ($acnttype == AccountTypeEnum::ln) {
                $account = LnAccount::where('instid',  $instid)->where('acntno', $validate['acntno'])->where('statusid', 1)->first();
            } else {
                $account = IaAccount::where('instid',  $instid)->where('acntno', $validate['acntno'])->where('statusid', 1)->first();
            }
            if ($account) {
                $validate['name'] =  Str::upper($account->name);
                $validate['name2'] = Str::upper($account->name2);
            }
        }

        $user = GPInstUser::where('instid', $instid)
            ->where('statusid', '<>', -1)->first();
        if ($user) {
            AdCgwTxnDescCombination::create([
                'value' => Str::lower($validate['value']),
                'type' => $validate['type'],
                'prodcode' => $validate['prodcode'] ?? null,
                'is_income' => $validate['is_income'] ?? null,
                'acntno' => $validate['acntno'] ?? null,
                'acnttype' => $acnttype,
                'name' => $validate['name'] ?? null,
                'name2' => $validate['name2'] ?? null,
                'statusid' => 1,
                'instid' => $instid,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
            ]);
        }
    }

    /**
     * Update resource in storage.
     * ad070302
     * @param Request $request
     * @return Response
     */
    public function ad070302(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required',
            'value' => 'required',
            'type' => 'required',
            'is_income' => 'required|numeric',
            'prodcode' => 'required_if:type,1',
            'acntno' => 'required_unless:type,1',
            'acnttype' => 'required',
        ]);
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $instid = auth()->user()->instid;
        $acnttype = $validate['acnttype'] ?? null;

        if ($validate['type'] == 1) {
            $validate['acntno'] = null;
            if ($acnttype == AccountTypeEnum::dp) {
                $prod = DpAccountType::where('instid', $instid)->where('prodcode', $validate['prodcode'])->where('statusid', 1)->first();
            } else if ($acnttype == AccountTypeEnum::ln) {
                $prod = LnAccountType::where('instid', $instid)->where('prodcode', $validate['prodcode'])->where('statusid', 1)->first();
            } else {
                $prod = IaAccountType::where('instid', $instid)->where('typecode', $validate['prodcode'])->where('statusid', 1)->first();
            }
            if ($prod) {
                $validate['name'] = Str::upper($prod->name);
                $validate['name2'] = Str::upper($prod->name2);
            }
        }

        if ($validate['type'] == 2) {
            $validate['prodcode'] = null;
            if ($acnttype == AccountTypeEnum::dp) {
                $account = DpAccount::where('instid',  $instid)->where('acntno', $validate['acntno'])->where('statusid', 1)->first();
            } else if ($acnttype == AccountTypeEnum::ln) {
                $account = LnAccount::where('instid',  $instid)->where('acntno', $validate['acntno'])->where('statusid', 1)->first();
            } else {
                $account = IaAccount::where('instid',  $instid)->where('acntno', $validate['acntno'])->where('statusid', 1)->first();
            }
            if ($account) {
                $validate['name'] =  Str::upper($account->name);
                $validate['name2'] = Str::upper($account->name2);
            }
        }

        $validate['value'] = Str::lower($validate['value']);
        $validate['updated_by'] = auth()->user()->id;

        AdCgwTxnDescCombination::where('id', $validate['id'])
            ->where('statusid', '<>', -1)->update($validate);
    }

    /**
     * Delete resource in storage.
     * ad101400
     * @param Request $request
     * @return Response
     */
    public function ad070402(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        AdCgwTxnDescCombination::where('id', $validate['id'])
            ->where('statusid', '<>', -1)->update([
                    'statusid' => -1,
                    'updated_by' => auth()->user()->id
                ]);
    }
}
