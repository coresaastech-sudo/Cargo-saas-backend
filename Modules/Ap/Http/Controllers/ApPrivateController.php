<?php

namespace Modules\Ap\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ln\Entities\LnAccount;
use Modules\Gp\Enums\ResponseCodeEnum;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;
use Modules\Ln\Entities\Views\VwLnNrs;
use Modules\Gp\Entities\Views\VwGPInstUser;
use Modules\Ln\Entities\LnAccountCooperation;
use Modules\Ln\Entities\Views\VwLnAccount;
use Modules\Cr\Entities\Views\VwCrCustNotifications;
use Modules\Ap\Entities\Views\VwApAccount;
use Modules\Ad\Http\Services\AdHideService;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;

class ApPrivateController extends Controller
{
    /**
     * regnum-аар customer list авах
     */
    private function getCustomersByRegnum(string $regnum)
    {
        $users = ApCustUser::where('regno', $regnum)->whereIn('statusid', [1, 0])->pluck('id');
        if ($users->isEmpty()) {
            return collect([]);
        }

        $links = ApInstCustUserLink::whereIn('cust_userid', $users)->where('statusid', 1)->pluck('instid');
        if ($links->isEmpty()) {
            return collect([]);
        }

        return ApCustomer::whereIn('instid', $links)
            ->where('regno', $regnum)
            ->where('statusid', 1)
            ->distinct()
            ->get();
    }

    
    /**
     * Customer list
     * Регистрийн дугаараар байгууллага бүрт байгаа харилцагчийн мэдээллийг авна
     * @param Request $request
     * @return Response
     */
    public function ap060000(Request $request)
    {
        $validate = $this->validateMe($request, [
            'regnum' => 'required',
        ], [
            'regnum.required' => ResponseCodeEnum::required,
        ]);

        $customers = $this->getCustomersByRegnum($validate['regnum']);
        if ($customers->isEmpty()) {
            return collect([]);
        }

        $instList = GPInstList::pluck('name', 'id');
        $branchList = GPInstBrch::get()->keyBy(function ($item) {
            return $item->instid . '_' . $item->brchno;
        });

        $data = $customers->map(function ($customer) use ($instList, $branchList) {
            $customer->inst_name = $instList[$customer->instid] ?? null;
            $branchKey = $customer->instid . '_' . $customer->brchno;
            $customer->brchno_name = $branchList[$branchKey]->name ?? null;
            $custtypeid = $customer->custtypecode ?? 0;
            if ($custtypeid == 0) {
                $custInd = CrCustInd::where('id', $customer->corrid)->first();
                $customer->custdata = $custInd;
                if ($custInd) {
                    $customer->cust_inst_name = $instList[$custInd->instid] ?? null;
                    $custBranchKey = $custInd->instid . '_' . $custInd->brchno;
                    $customer->cust_brchno_name = $branchList[$custBranchKey]->name ?? null;
                }
            } elseif ($custtypeid == 1) {
                $custOrg = CrCustOrg::where('id', $customer->corrid)->first();
                $customer->custdata = $custOrg;
                if ($custOrg) {
                    $customer->cust_inst_name = $instList[$custOrg->instid] ?? null;
                    $custBranchKey = $custOrg->instid . '_' . $custOrg->brchno;
                    $customer->cust_brchno_name = $branchList[$custBranchKey]->name ?? null;
                }
            }

            return $customer;
        });

        return $data;
    }


    /**
     * Customer list
     * App хэрэглэгчийн жагсаалт
     * @param Request $request
     * @return Response
     */
    public function ap060010(Request $request)
    {
        $query = ApCustUser::select(        
            'id',
            'email',
            'phone',
            'regno',
            'firstname',
            'lastname',
            'statusid'
        )->where('statusid', '<>', -1);

        return $this->getGridData(
            $request,
            $query->orderBy('id', 'DESC')
        );
    }


    /**
     * List of sent notifications by customer ID
     * Харилцагчийн id-аар илгээсэн мэдэгдлийн жагсаалт
     * @param Request $request
     * @return Response
     */
    public function ap060020(Request $request)
    {
        $validated = $this->validateMe($request, [
            'custid' => 'required',
        ], [
            'custid.required' => ResponseCodeEnum::required,
        ]);

        return $this->getGridData(
            $request,
            VwCrCustNotifications::where('custid', $validated['custid'])->where('statusid', '<>', -1)
                ->orderBy('created_at', 'desc')
        );
    }


    /**
     * Information on loans managed by the loan officer
     * Зээлийн мэргэжилтэн хариуцсан зээлүүдийн мэдээлэл
     * @param Request $request
     * @return Response
     */
    public function ap060030(Request $request)
    {
        $validated = $this->validateMe($request, [
            'type' => 'nullable|string',
        ], [
            'type.required' => ResponseCodeEnum::required,
        ]);

        $instid = auth()->user()->instid;
        $type   = $validated['type'] ?? null;

        $filters = $request->input('filters', []);
        $userFilters = [];
        $loanFilters = [];

        if (is_array($filters)) {
            $userFields = [
                'id', 'username', 'instid', 'email', 'phone', 'statusid', 
                'isadmin', 'regno', 'iprest', 'startdate', 'enddate', 
                'name', 'lname', 'brchno', 'created_at'
            ];
            $loanFields = [
                'acntno', 'brchno', 'custno', 'segcode', 'prodcode', 'curcode', 
                'catcode', 'loantype', 'purpcode', 'created_by', 
                'arreardate', 'openeddate'
            ];

            foreach ($filters as $filter) {
                if (isset($filter['field'])) {
                    if (in_array($filter['field'], $userFields)) {
                        $userFilters[] = $filter;
                    }
                    if (in_array($filter['field'], $loanFields)) {
                        $loanFilters[] = $filter;
                    }
                }
            }
        }

        $request->merge(['filters' => $userFilters]);

        $user = $this->getGridData(
            $request,
            VwGPInstUser::where('instid', $instid)
                ->where('isadmin', 0)
                ->where('statusid', '<>', -1),
            [['field' => 'id', 'dir' => 'DESC']]
        );

        $groupField = $type ?: 'created_by';
        $sysdate = \Modules\Gp\Http\Services\CoreService::getTxnDate($instid);
        
        $loanStatsQuery = DB::table('ln_account')
            ->select(
                DB::raw("{$groupField} as user_id"),
                DB::raw("SUM(CASE WHEN GREATEST(COALESCE('{$sysdate}'::date - arreardate, 0), COALESCE('{$sysdate}'::date - arreardateint, 0)) > 0 THEN approvamount ELSE 0 END) as overdue_approvamount"),
                DB::raw("SUM(CASE WHEN GREATEST(COALESCE('{$sysdate}'::date - arreardate, 0), COALESCE('{$sysdate}'::date - arreardateint, 0)) > 0 THEN princbal ELSE 0 END) as overdue_princbal"),
                DB::raw("SUM(CASE WHEN GREATEST(COALESCE('{$sysdate}'::date - arreardate, 0), COALESCE('{$sysdate}'::date - arreardateint, 0)) > 0 THEN 1 ELSE 0 END) as overdue_loan_count"),
                DB::raw("MAX(GREATEST(COALESCE('{$sysdate}'::date - arreardate, 0), COALESCE('{$sysdate}'::date - arreardateint, 0))) as numarreardatediff"),
                DB::raw('SUM(approvamount) as total_loan_approvamount'),
                DB::raw('SUM(princbal) as total_loan_princbal'),
                DB::raw('COUNT(*) as total_loan_count')
            )
            ->where('instid', $instid)
            ->where('statusid', '<>', -1)
            ->whereRaw("{$groupField} IS NOT NULL AND {$groupField} > 0");

        if (!empty($loanFilters)) {
            $loanStatsQuery = $this->applyFilters($loanStatsQuery, $loanFilters);
        }

        $loanStats = $loanStatsQuery
            ->groupBy($groupField)
            ->get()
            ->keyBy('user_id');

        $mappedItems = collect($user->items())->map(function ($item) use ($loanStats) {
            $userId  = $item->id;
            $stats   = $loanStats[$userId] ?? null;

            $arr = $item->toArray();
            $arr['overdue_princbal']          = $stats ? (float) $stats->overdue_princbal : 0;
            $arr['overdue_loan_count']      = $stats ? (int)   $stats->overdue_loan_count : 0;
            $arr['total_loan_princbal']       = $stats ? (float) $stats->total_loan_princbal : 0;
            $arr['total_loan_count']        = $stats ? (int)   $stats->total_loan_count : 0;
            $arr['numarreardatediff']       = $stats ? (int) $stats->numarreardatediff : 0;
            return $arr;
        });

        $user->setCollection($mappedItems);

        return $user;
    }


    /**
     * Get loans by regnum
     * Регистрийн дугаараар байгууллага бүрт байгаа харилцагчийн зээлийн мэдээллийг авна
     * @param Request $request
     * @return Response
     */
    public function ap060100(Request $request)
    {
        $validate = $this->validateMe($request, [
            'regnum' => 'required',
        ], [
            'regnum.required' => ResponseCodeEnum::required,
        ]);

        $customers = $this->getCustomersByRegnum($validate['regnum']);
        if ($customers->isEmpty()) {
            return collect([]);
        }
        $custPairs = $customers->map(function ($c) {
            return ['custno' => $c->cif, 'instid' => $c->instid];
        });

        $lns = LnAccount::where(function ($query) use ($custPairs) {
            foreach ($custPairs as $pair) {
                $query->orWhere(function ($q) use ($pair) {
                    $q->where('custno', $pair['custno'])
                        ->where('instid', $pair['instid']);
                });
            }
        })
        ->where('statusid', '<>', -1)
        ->get();

        $lnCusts = LnAccount::query()
            ->join('ln_account_cust as lac', 'lac.acntno', '=', 'ln_account.acntno')
            ->where(function ($query) use ($custPairs) {
                foreach ($custPairs as $pair) {
                    $query->orWhere(function ($q) use ($pair) {
                        $q->where('lac.custno', $pair['custno'])
                          ->where('lac.instid', $pair['instid']);
                    });
                }
            })
            ->where('lac.statusid', 1)
            ->select(['ln_account.*', 'lac.rolecode'])
            ->get();

        $instList = GPInstList::pluck('name', 'id');
        $branchList = GPInstBrch::get()->keyBy(function ($item) {
            return $item->instid . '_' . $item->brchno;
        });

        $data = $lns->merge($lnCusts)
            ->unique('acntno')
            ->values()
            ->map(function ($item) use ($instList, $branchList) {
                $item->inst_name = $instList[$item->instid] ?? null;
                $branchKey = $item->instid . '_' . $item->brchno;
                $item->brchno_name = $branchList[$branchKey]->name ?? null;
                $item->repaymentdata = VwLnNrs::where('instid', $item->instid)
                    ->where('acntno', $item->acntno)
                    ->where('statusid', '<>', -1)
                    ->orderBy('index')
                    ->get();

                return $item;
            });

        return $data;
    }


    /**
     * Collaborative customer-related loan list
     * Хамтын ажиллагаатай харилцагч холбоотой зээлийн жагсаалт
     * @param Request $request
     * @return Response
     */
    public function ap060101(Request $request)
    {
        $validated = $this->validate($request, [
            'custnos' => 'required|array',
        ], [
            'custnos.required' => "RC000082"
        ]);

        $lns = LnAccountCooperation::whereIn('custno', $validated['custnos'])
            ->where('statusid', 1)
            ->get();

        return VwLnAccount::whereIn('acntno', $lns->pluck('acntno'))
            ->where('statusid', '<>', -1)
            ->get();
    }


    /**
     * Loan list by overdue days
     * Хугацаа хэтэрсэн хоногоор зээлийн жагсаалт
     * @param Request $request
     * @return Response
     */
    public function ap060111(Request $request)
    {
        $validated = $this->validate($request, [
            'range' => 'nullable|array',
        ], [
            'range.array' => ResponseCodeEnum::array,
        ]);

        $query = VwApAccount::where('statusid', '<>', -1);
        if (isset($validated['range']) && count($validated['range']) === 2) {
            $start = $validated['range']['min'];
            $end   = $validated['range']['max'];
            $query->whereRaw(
                'GREATEST(numarreardatediff, numarreardateintdiff) BETWEEN ? AND ?',
                [$start, $end]
            );
        }

        $data = $this->getGridData(
            $request,
            $query,
            [['field' => 'acntno', 'dir' => 'DESC']],
            [],
            ['acntno', 'name', 'cust_name', 'custno', 'id1', 'phone']
        );
        $service = new AdHideService();
        array_map(function ($item) use ($service) {
            $shouldShow =  $service->hideAcnt($item->acntno);
            $shouldShowCust =  $service->hideAcnt($item->custno);
            $item->cust_name = ($item->hide == '1' || $item->hidden == '1') ? (($shouldShow || $shouldShowCust) ? $item->cust_name : '***') : $item->cust_name;
            $item->id1 = ($item->hide == '1' || $item->hidden == '1') ? (($shouldShow || $shouldShowCust) ? $item->id1 : '***') : $item->id1;
            $item->name = ($item->hide == '1' || $item->hidden == '1') ? (($shouldShow || $shouldShowCust) ? $item->name : '***') : $item->name;
            $item->custno = ($item->hide == '1' || $item->hidden == '1') ? (($shouldShow || $shouldShowCust) ? $item->custno : '***') : $item->custno;
            $item->phone = ($item->hide == '1' || $item->hidden == '1') ? (($shouldShow || $shouldShowCust) ? $item->phone : '***') : $item->phone;
            return $item;
        }, $data->items());

        return $data;
    }

    
    /**
     * @AC ap060301
     * Зээлийн данс хариуцагч ажилтан томилох / lnaccount update
     */
    public function ap060301(Request $request)
    {
        $validated = $this->validateMe($request, [
            'acntno'         => 'required_without_all:accountmanager',
            'accountmanager' => 'required_without_all:acntno',
            'data'           => 'required|array',
        ], [
            'accountmanager.required' => ResponseCodeEnum::required,
            'data.required'           => ResponseCodeEnum::required,
            'data.array'              => ResponseCodeEnum::array,
        ]);

        $user = auth()->user();
        if (isset($validated['accountmanager']) && !empty($validated['accountmanager'])) {
            $lnaccounts = LnAccount::where('instid', $user->instid)
                ->where('statusid', '<>', -1)
                ->where('created_by', $validated['accountmanager'])
                ->get();
            if ($lnaccounts->isEmpty()) {
                $this->error('RC000010', ['id' => $validated['accountmanager']]);
            }
            foreach ($lnaccounts as $lnaccount) {
                $this->applyAccountData($lnaccount, $validated['data'], $user);
            }
            return $lnaccounts->fresh();
        }

        $lnaccount = LnAccount::where('instid', $user->instid)
            ->where('statusid', '<>', -1)
            ->where('acntno', $validated['acntno'])
            ->first();

        if (!$lnaccount) {
            $this->error('RC000034', ['mainacntno' => $validated['acntno']]);
        }
        $this->applyAccountData($lnaccount, $validated['data'], $user);

        return $lnaccount->fresh();
    }


    /**
     * Зөвшөөрөгдсөн талбаруудыг шалгаж, lnaccount-д утга оноох
     */
    private function applyAccountData(LnAccount $lnaccount, array $data, $user): void
    {
        $allowedFields = ['auditmanager', 'analysismanager', 'riskmanager', 'sellermanager', 'trackno'];
        $managerFields = ['auditmanager', 'analysismanager', 'riskmanager', 'sellermanager'];
        foreach ($data as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                $this->error('RC000091', ['field' => $field]);
            }
            if (in_array($field, $managerFields)) {
                if ($value != 0) {
                    $manager = VwGPInstUser::where('id', $value)->where('instid', $user->instid)
                        ->where('statusid', '<>', -1)->first();
                    if (!$manager) {
                        $this->error('RC000010', ['id' => $value]);


                    }
                }
                if (!array_key_exists('trackno', $data)) {
                    $this->error('RC000090');
                }
            }
            $lnaccount->$field = $value;
        }
        $lnaccount->save();
    }
}