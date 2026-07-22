<?php

namespace Modules\Ap\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Modules\Ap\Entities\ApCustBankAccount;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApCustUser;

class ApCustomerListController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function ap010000(Request $request)
    {
        return $this->getGridData(
            $request,
            ApCustomer::select(
                'id',
                'instid',
                'cif',
                'lname',
                'fname',
                'gender',
                'regno',
                'phone',
                'statusid',
                'created_at',
            )
                ->where('statusid', 1)
                ->where('instid', auth()->user()->instid),
            // ->latest('created_at'),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }


    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function ap010100(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = ApCustomer::select(
            'id',
            'instid',
            'cif',
            'familyname',
            'familyname2',
            'lname',
            'lname2',
            'fname',
            'fname2',
            'gender',
            'regno',
            'nationality',
            'birthday',
            'ethnicity',
            'citizenship',
            'birthplace',
            'segment',
            'education',
            'maritalstatus',
            'phone',
            'phone2',
            'email',
            'fax',
            'region',
            'subregion',
            'address',
            'shortname',
            'shortname2',
            'ispolitical',
            'statusid',

        )->where('instid', auth()->user()->instid)
            ->where('id', $validate['id'])
            ->where('statusid', '=', 1)
            ->first();
        if ($GPinst) {
            $custUserIds = $this->getCustomerUserIds($GPinst->instid, $GPinst->regno);

            $GPinst->bank_accounts = $this->getBankAccounts($custUserIds, $GPinst->instid);

            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    private function getCustomerUserIds($instid, $regno)
    {
        return ApCustUser::query()
            ->join('ap_inst_cust_user_link', 'ap_inst_cust_user_link.cust_userid', '=', 'ap_cust_user.id')
            ->where('ap_inst_cust_user_link.instid', $instid)
            ->where('ap_inst_cust_user_link.statusid', 1)
            ->where('ap_cust_user.regno', $regno)
            ->where('ap_cust_user.statusid', '<>', -1)
            ->pluck('ap_cust_user.id');
    }

    private function getBankAccounts($custUserIds, $instid)
    {
        if ($custUserIds->isEmpty()) {
            return [];
        }

        $accounts = ApCustBankAccount::whereIn('cust_user_id', $custUserIds)
            ->where('statusid', '>', 0)
            ->orderByDesc('id')
            ->get()
            ->unique(fn($account) => $account->acnt_code . '|' . $account->bank_code)
            ->values();

        if ($accounts->isEmpty()) {
            return $accounts;
        }

        $bankNames = $this->getBankNameMap(
            $accounts->pluck('bank_code')->filter()->unique()->values()->all(),
            $instid
        );

        return $accounts->map(function ($account) use ($bankNames) {
            $account->bank_name = $bankNames[$account->bank_code] ?? null;

            return $account;
        });
    }

    private function getBankNameMap(array $bankCodes, $instid)
    {
        if (empty($bankCodes)) {
            return collect();
        }

        return DB::table('vw_dict_GP_const_071')
            ->selectRaw('INSTID as instid, VALUE as value, NAME as name')
            ->whereIn('instid', array_values(array_unique([1, (int) $instid])))
            ->whereIn('value', $bankCodes)
            ->get()
            ->sortByDesc(fn($item) => (int) $item->instid === (int) $instid)
            ->unique('value')
            ->pluck('name', 'value');
    }
}
