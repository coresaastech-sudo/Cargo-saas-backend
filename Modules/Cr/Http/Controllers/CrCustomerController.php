<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;
use Modules\Cr\Http\Requests\CrCustIndRequest;
use Modules\Cr\Http\Requests\CrCustOrgRequest;
use Illuminate\Support\Str;
use Modules\Ad\Http\Services\AdHideService;
use Modules\Cr\Entities\Views\VwCrCustAllAcntList;
use Modules\Cr\Entities\Views\VwCrCustIndList;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Entities\Views\VwCrCustOrgList;
use Modules\Gp\Entities\GPInstBrch;
use Modules\Gp\Entities\GPInstConst;
use Modules\Gp\Entities\GPInstGp;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPUserAccessToken;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Controllers\GPInstController;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\SendMailJob;

class CrCustomerController extends Controller
{

    public function getCustomerList(Request $request)
    {
        $sql = VwCrCustList::where('statusid', '>=', 0)->where('bl', 0);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }
        $data = $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);

        $service = new AdHideService();
        array_map(function ($item) use ($service) {
            $shouldShow =  $service->hideAcnt($item->custno);
            $item->birthdate = $item->hidden == '1' ? ($shouldShow ? $item->birthdate : '***') : $item->birthdate;
            $item->id1 = $item->hidden == '1' ? ($shouldShow ? $item->id1 : '***') : $item->id1;
            $item->name = $item->hidden == '1' ? ($shouldShow ? $item->name : '***') : $item->name;
            $item->name2 = $item->hidden == '1' ? ($shouldShow ? $item->name2 : '***') : $item->name2;
            $item->lname = $item->hidden == '1' ? ($shouldShow ? $item->lname : '***') : $item->lname;
            $item->lname2 = $item->hidden == '1' ? ($shouldShow ? $item->lname2 : '***') : $item->lname2;
            $item->phone = $item->hidden == '1' ? ($shouldShow ? $item->phone : '***') : $item->phone;
            return $item;
        }, $data->items());

        return $data;
    }
    /**
     * Display a listing of the resource.
     * @AC cr010000
     * @return Response
     */
    public function indexInd(Request $request)
    {
        $sql = VwCrCustIndList::where('statusid', '>=', 0);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }
        $data = $this->getGridData(
            $request,
            $sql,
            [['field' => 'id', 'dir' => 'DESC']],
            [],
            ['custno', 'lname', 'name', 'phone', 'id1']
        );

        $service = new AdHideService();
        array_map(function ($item) use ($service) {
            $shouldShow =  $service->hideAcnt($item->custno);
            $item->birthdate = $item->hidden == '1' ? ($shouldShow ? $item->birthdate : '***') : $item->birthdate;
            $item->id1 = $item->hidden == '1' ? ($shouldShow ? $item->id1 : '***') : $item->id1;
            $item->name = $item->hidden == '1' ? ($shouldShow ? $item->name : '***') : $item->name;
            $item->name2 = $item->hidden == '1' ? ($shouldShow ? $item->name2 : '***') : $item->name2;
            $item->lname = $item->hidden == '1' ? ($shouldShow ? $item->lname : '***') : $item->lname;
            $item->lname2 = $item->hidden == '1' ? ($shouldShow ? $item->lname2 : '***') : $item->lname2;
            $item->phone = $item->hidden == '1' ? ($shouldShow ? $item->phone : '***') : $item->phone;
            return $item;
        }, $data->items());

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function storeInd(CrCustIndRequest $request)
    {
        $validated = $request->validated();
        $validated['id1'] = Str::upper($validated['id1']);
        if (isset($validated['id1']) && !empty($validated['id1'])) {
            $regno = VwCrCustList::where('instid', auth()->user()->instid)
                ->where('id1', $validated['id1'])->first();
            if ($regno) {
                $this->error("RC000086", ['field' => $validated['id1']]);
            }
        }

        $validated['familyname'] = Str::upper($validated['familyname'] ?? '');
        $validated['familyname2'] = Str::upper(trim($validated['familyname2']) ?? cyrillic2latin($validated['familyname']));
        $validated['lname'] = Str::upper($validated['lname'] ?? '');
        $validated['lname2'] = Str::upper(trim($validated['lname2']) ?? cyrillic2latin($validated['lname']));
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper(trim($validated['name2']) ?? cyrillic2latin($validated['name']));
        $validated['custno'] = GPInstController::getCustomerSeq(auth()->user()->instid);
        $validated['prevstatusid'] = 1;
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['txndate'] = $this->resolveCustomerTxnDate($validated['txndate'] ?? null);
        $validated['brchno'] = $validated['brchno'] ?? auth()->user()->brchno;
        $validated['created_name'] = auth()->user()->lname . ' ' . auth()->user()->name;
        $validated['updated_name'] = auth()->user()->lname . ' ' . auth()->user()->name;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        CrCustInd::create($validated);
        return CrCustInd::where('custno', $validated['custno'])
            ->where('instid', auth()->user()->instid)->first();
    }

    /**
     * Show the specified resource.
     * @AC cr010100
     * @param int $id
     * @return Response
     */
    public function showInd(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required_without_all:custno', // Шалгах талбар
            'custno' => 'required_without_all:id' // Шалгах талбар
        ], [
            'id.required' => "RC000011",
            'custno.required' => "RC000011",
        ]);
        $user = auth()->user();
        if (isset($validate['id'])) {
            $GPinst = CrCustInd::where('instid', $user->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        } else {
            $GPinst = CrCustInd::where('instid', $user->instid)->where('custno', $validate['custno'])->where('statusid', '<>', -1)->first();
        }
        if ($GPinst) {
            $txndate = CoreService::getTxnDate($user->instid);
            $GPInstGp =  GPInstGp::select('itemvalue')->where('instid', $user->instid)
                ->where('itemname', 'PeriodOfCustInfo')->first();
            if ($GPInstGp) {
                $period = (int)$GPInstGp->itemvalue;

                // lastrenewdate хоосон бол updated_at-аас авна
                if (empty($GPinst->lastrenewdate)) {
                    $GPinst->lastrenewdate = Carbon::parse($GPinst->updated_at)->format('Y-m-d');
                }
                if ($period > 0) {
                    $lastRenew = Carbon::parse($GPinst->lastrenewdate);
                    $txnDate   = Carbon::parse($txndate);
                    // хоногийн зөрүү
                    $daysDiff = $lastRenew->diffInDays($txnDate, false);
                    if ($daysDiff > $period) {
                        $GPinst->show_renew_custdata = true;
                    }
                }
            }
            $service = new AdHideService();
            $shouldShow =  $service->hideAcnt($GPinst->custno);
            if ($GPinst->hidden != 1 || $shouldShow) {
                $custmsg = new CrCustomerMsgController();
                $GPinst->custmsg = $custmsg->getCustMsg($GPinst->id);
                return $GPinst;
            } else {
                $this->error('RC000256');
            }
            return;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @AC cr010300
     * @return Response
     */
    public function updateInd(CrCustIndRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $cust = CrCustInd::where('statusid', 1)
            ->where('instid', auth()->user()->instid)
            ->find($validated['id']);
        if (!$cust) {
            $this->error('RC000085');
        }
        $validated['id1'] = Str::upper($validated['id1']);
        if ($cust->id1 != $validated['id1']) {
            $regno = VwCrCustList::where('instid', auth()->user()->instid)
                ->where('id1', $validated['id1'])->first();
            if ($regno) {
                $this->error("RC000086", ['field' => $validated['id1']]);
            }
        }

        if ($validated['email'] != $cust->email) {
            $validated['email_verified'] = 0;
        }

        $validated['familyname'] = Str::upper($validated['familyname'] ?? '');
        $validated['familyname2'] = Str::upper($validated['familyname2'] ?? '');
        $validated['lname'] = Str::upper($validated['lname'] ?? '');
        $validated['lname2'] = Str::upper($validated['lname2'] ?? '');
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['brchno'] = $cust->brchno;
        $validated['segcode'] = $cust->segcode;
        $validated['txndate'] = $this->resolveCustomerTxnDate($validated['txndate'] ?? null);
        $validated['lastrenewdate'] = CoreService::getTxnDate(auth()->user()->instid);
        $validated['updated_by'] = auth()->user()->id;
        $validated['updated_name'] = auth()->user()->lname . ' ' . auth()->user()->name;
        $cust->update($validated);
    }

    /**
     * Move to Inactive the specified resource from storage.
     * AC cr010904
     * @return Response
     */
    public function doInactiveInd(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);

        $field = '';
        $id = '';
        if (isset($validate['id']) && !empty($validate['id'])) {
            $field = 'id';
            $id = $validate['id'];
        } else if (isset($validate['custno']) && !empty($validate['custno'])) {
            $field = 'custno';
            $id = $validate['custno'];
        }

        $dtl = CrCustInd::where($field, $id)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if (!$dtl) {
            $this->error('RC000085');
        }
        $actacnt = VwCrCustAllAcntList::where('instid', auth()->user()->instid)
            ->where('custid', $dtl->id)
            ->whereNotIn('statusid', [0, 9])
            ->pluck('acntno');

        if (!empty($actacnt) && count($actacnt) > 0) {
            $stringlist = "";
            foreach ($actacnt as $acntno) {
                if (empty($stringlist)) {
                    $stringlist = $acntno;
                } else {
                    $stringlist = $stringlist . ", " . $acntno;
                }
            }
            $this->error(
                'RC000084',
                [
                    'acnts' => "[$stringlist]"
                ]
            );
        }
        $dtl->update([
            'statusid' => 0,
            'lasttxndate' => CoreService::getTxnDate(auth()->user()->instid),
            'updated_by' => auth()->user()->id,
            'updated_name' => auth()->user()->lname . ' ' . auth()->user()->name,
            'prevstatusid' => $dtl->statusid

        ]);
    }
    public function doActiveInd(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);

        $field = '';
        $id = '';

        if (isset($validate['id']) && !empty($validate['id'])) {
            $field = 'id';
            $id = $validate['id'];
        } else if (isset($validate['custno']) && !empty($validate['custno'])) {
            $field = 'custno';
            $id = $validate['custno'];
        }

        $dtl = CrCustInd::where($field, $id)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 0)->first();
        if (!$dtl) {
            $this->error('RC000085');
        }
        $dtl->update([
            'statusid' => 1,
            'lasttxndate' => null,
            'updated_by' => auth()->user()->id,
            'updated_name' => auth()->user()->lname . ' ' . auth()->user()->name,
            'prevstatusid' => $dtl->statusid
        ]);
    }

    /**
     * @AC cr011000
     */
    public function indexOrg(Request $request)
    {
        $sql = VwCrCustOrgList::where('statusid', '>=', 0);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('instid', auth()->user()->instid);
        }
        $data = $this->getGridData(
            $request,
            $sql,
            [['field' => 'id', 'dir' => 'DESC']],
            [],
            ['custno', 'name', 'phone', 'id1'],
        );

        $service = new AdHideService();
        array_map(function ($item) use ($service) {
            $shouldShow =  $service->hideAcnt($item->custno);
            $item->birthdate = $item->hidden == '1' ? ($shouldShow ? $item->birthdate : '***') : $item->birthdate;
            $item->id1 = $item->hidden == '1' ? ($shouldShow ? $item->id1 : '***') : $item->id1;
            $item->name = $item->hidden == '1' ? ($shouldShow ? $item->name : '***') : $item->name;
            $item->name2 = $item->hidden == '1' ? ($shouldShow ? $item->name2 : '***') : $item->name2;
            $item->lname = $item->hidden == '1' ? ($shouldShow ? $item->lname : '***') : $item->lname;
            $item->lname2 = $item->hidden == '1' ? ($shouldShow ? $item->lname2 : '***') : $item->lname2;
            $item->phone = $item->hidden == '1' ? ($shouldShow ? $item->phone : '***') : $item->phone;
            return $item;
        }, $data->items());

        return $data;
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function storeOrg(CrCustOrgRequest $request)
    {
        $validated = $request->validated();
        $validated['id1'] = Str::upper($validated['id1']);
        if (isset($validated['id1']) && !empty($validated['id1'])) {
            $regno = VwCrCustList::where('instid', auth()->user()->instid)
                ->where('id1', $validated['id1'])->first();
            if ($regno) {
                $this->error("RC000086", ['field' => $validated['id1']]);
            }
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper(trim($validated['name2']) ?? cyrillic2latin($validated['name']));
        $validated['prevstatusid'] = 1;
        $validated['statusid'] = 1;
        $validated['instid'] = auth()->user()->instid;
        $validated['txndate'] = $this->resolveCustomerTxnDate($validated['txndate'] ?? null);
        $validated['brchno'] = $validated['brchno'] ?? auth()->user()->brchno;
        $validated['created_name'] = auth()->user()->lname . ' ' . auth()->user()->name;
        $validated['updated_name'] = auth()->user()->lname . ' ' . auth()->user()->name;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        $validated['custno'] = GPInstController::getCustomerSeq(auth()->user()->instid);
        CrCustOrg::create($validated);

        return CrCustOrg::where('custno', $validated['custno'])
            ->where('instid', auth()->user()->instid)->first();
    }

    /**
     * Show the specified resource.
     * @AC cr011100
     * @param int $id
     * @return Response
     */
    public function showOrg(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required_without_all:custno', // Шалгах талбар
            'custno' => 'required_without_all:id' // Шалгах талбар
        ], [
            'id.required' => "RC000011",
            'custno.required' => "RC000011",
        ]);
        $user = auth()->user();
        if (isset($validate['id'])) {
            $GPinst = CrCustOrg::where('instid', $user->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        } else {
            $GPinst = CrCustOrg::where('instid', $user->instid)->where('custno', $validate['custno'])->where('statusid', '<>', -1)->first();
        }

        if ($GPinst) {
            $txndate = CoreService::getTxnDate($user->instid);
            $GPInstGp =  GPInstGp::select('itemvalue')->where('instid', $user->instid)
                ->where('itemname', 'PeriodOfCustInfo')->first();
            if ($GPInstGp) {
                $period = (int)$GPInstGp->itemvalue;

                // lastrenewdate хоосон бол updated_at-аас авна
                if (empty($GPinst->lastrenewdate)) {
                    $GPinst->lastrenewdate = Carbon::parse($GPinst->updated_at)->format('Y-m-d');
                }
                if ($period > 0) {
                    $lastRenew = Carbon::parse($GPinst->lastrenewdate);
                    $txnDate   = Carbon::parse($txndate);
                    // хоногийн зөрүү
                    $daysDiff = $lastRenew->diffInDays($txnDate, false);
                    if ($daysDiff > $period) {
                        $GPinst->show_renew_custdata = true;
                    }
                }
            }
            $service = new AdHideService();
            $shouldShow =  $service->hideAcnt($GPinst->custno);
            if ($GPinst->hidden != 1 || $shouldShow) {
                $custmsg = new CrCustomerMsgController();
                $GPinst->custmsg = $custmsg->getCustMsg($GPinst->id);
                return $GPinst;
            } else {
                $this->error('RC000256');
            }
            return;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @AC cr011300
     * @param Request $request
     * @return Response
     */
    public function updateOrg(CrCustOrgRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $cust = CrCustOrg::where('statusid', '<>', -1)
            ->where('instid', auth()->user()->instid)
            ->find($validated['id']);
        if (!$cust) {
            $this->error('RC000085');
        }
        $validated['id1'] = Str::upper($validated['id1']);
        if ($cust->id1 != $validated['id1']) {
            $regno = VwCrCustList::where('instid', auth()->user()->instid)
                ->where('id1', $validated['id1'])->first();
            if ($regno) {
                $this->error("RC000086", ['field' => $validated['id1']]);
            }
        }

        if ($validated['email'] != $cust->email) {
            $validated['email_verified'] = 0;
        }

        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['brchno'] = $cust->brchno;
        $validated['segcode'] = $cust->segcode;
        $validated['txndate'] = $this->resolveCustomerTxnDate($validated['txndate'] ?? null);
        $validated['lastrenewdate'] = CoreService::getTxnDate(auth()->user()->instid);
        $validated['updated_by'] = auth()->user()->id;
        $validated['updated_name'] = auth()->user()->lname . ' ' . auth()->user()->name;
        $cust->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function doInactiveOrg(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);

        $field = '';
        $id = '';
        if (isset($validate['id']) && !empty($validate['id'])) {
            $field = 'id';
            $id = $validate['id'];
        } else if (isset($validate['custno']) && !empty($validate['custno'])) {
            $field = 'custno';
            $id = $validate['custno'];
        }

        $dtl = CrCustOrg::where($field, $id)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)->first();
        if (!$dtl) {
            $this->error('RC000085');
        }
        $actacnt = VwCrCustAllAcntList::where('instid', auth()->user()->instid)
            ->where('custid', $dtl->id)
            ->whereNotIn('statusid', [0, 9])
            ->pluck('acntno');

        if (!empty($actacnt) && count($actacnt) > 0) {
            $stringlist = "";
            foreach ($actacnt as $acntno) {
                if (empty($stringlist)) {
                    $stringlist = $acntno;
                } else {
                    $stringlist = $stringlist . ", " . $acntno;
                }
            }
            $this->error(
                'RC000084',
                [
                    'acnts' => "[$stringlist]"
                ]
            );
        }
        $dtl->update([
            'statusid' => 0,
            'lasttxndate' => CoreService::getTxnDate(auth()->user()->instid),
            'updated_by' => auth()->user()->id,
            'updated_name' => auth()->user()->lname . ' ' . auth()->user()->name,
            'prevstatusid' => $dtl->statusid
        ]);
    }

    public function doActiveOrg(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);

        $field = '';
        $id = '';
        if (isset($validate['id']) && !empty($validate['id'])) {
            $field = 'id';
            $id = $validate['id'];
        } else if (isset($validate['custno']) && !empty($validate['custno'])) {
            $field = 'custno';
            $id = $validate['custno'];
        }
        $dtl = CrCustOrg::where($field, $id)
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 0)->first();
        if (!$dtl) {
            $this->error('RC000085');
        }
        $dtl->update([
            'statusid' => 1,
            'lasttxndate' => null,
            'updated_by' => auth()->user()->id,
            'updated_name' => auth()->user()->lname . ' ' . auth()->user()->name,
            'prevstatusid' => $dtl->statusid
        ]);
    }

    /**
     * Into BlackList
     * @return void
     */
    public function intoBlacklistInd(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);
        $this->doBlcklist($validate, 1, 'IND');
    }
    /**
     * Out of BlackList
     * @AC cr010901
     * @return void
     */
    public function outofBlacklistInd(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);
        $this->doBlcklist($validate, 0, 'IND');
    }

    /**
     * Into BlackList
     * @return void
     */
    public function intoBlacklistOrg(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);
        $this->doBlcklist($validate, 1, 'ORG');
    }
    /**
     * Out of BlackList
     * @return void
     */
    public function outofBlacklistOrg(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required_without:custno',
            'custno' => 'required_without:id',
        ], [
            'id.required_without' => "RC000011",
            'custno.required_without' => "RC000011",
        ]);
        $this->doBlcklist($validate, 0, 'ORG');
    }

    /**
     * BlackList оруулах сервис
     *
     * @param int $id Харилцагчийн дугаарлалт
     * @param int $bl blacklist eseh
     * @param string $type Харилцагчийн төрөл (байгууллага - ORG, иргэн-IND)
     *
     * @return void
     */
    public function doBlcklist($data, $bl, $type)
    {

        $field = '';
        $id = '';
        if (isset($data['id']) && !empty($data['id'])) {
            $field = 'id';
            $id = $data['id'];
        } else if (isset($data['custno']) && !empty($data['custno'])) {
            $field = 'custno';
            $id = $data['custno'];
        }
        if (!empty($id)) {
            if ($type == 'ORG') {
                $dtl = CrCustOrg::where($field, $id)
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', '<>', -1)->first();
            } else {
                $dtl = CrCustInd::where($field, $id)
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', '<>', -1)->first();
            }

            if (!$dtl) {
                $this->error('RC000085');
            }
            $dtl->update([
                'bl' => $bl,
                'updated_by' => auth()->user()->id,
                'updated_name' => auth()->user()->lname . ' ' . auth()->user()->name,
                'prevstatusid' => $dtl->statusid
            ]);
        }
    }

    /**
     * Харилцагч имэйл баталгаажуулах
     * @AC cr010400
     * @param Request $request
     * @return Response
     */


    public function verifyEmail(Request $request)
    {
        $validated = $this->validate($request, [
            'custno' => 'required',
            'email' => 'required|email',
        ], [
            'custno.required' => ResponseCodeEnum::required,
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
        ]);

        $user = auth()->user();
        $cust = VwCrCustList::where('custno', $validated['custno'])
            ->where('instid', $user->instid)->where('statusid', 1)->first();

        if (!$cust) {
            $this->error('RC000015');
        }


        $data = array();
        $data['hostname'] = config('app.frontoffice_url');
        $data['newemail'] = $validated['email'];
        $data['oldemail'] = $cust->email;
        $data['userName'] = $cust->name;

        $cust = GPInstList::where('id', $user->instid)->where('statusid', 1)->first();
        if ($cust) {
            $data['companyName'] = $cust->name;
        }


        $token = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));
        $data['token'] = $token;

        $tmpdata = [
            'custno' => $validated['custno'],
            'newemail' => $data['newemail'],
            'oldemail' => $data['oldemail'],
            'instid' => $user->instid,
        ];

        GPUserAccessToken::create([
            'userid' => $cust->id,
            'name' => 'verify email',
            'token' => $token,
            'abilities' => json_encode($tmpdata, JSON_UNESCAPED_UNICODE),
            'last_used_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => null,
            'channel' => 'BACK'
        ]);

        $old_email = [
            "to" => $validated['email'],
            "subject" => "Имэйл хаяг баталгаажуулах",
            "data" => $data,
            "template" => "cr::mail.verifyEmail"
        ];

        dispatch(new SendMailJob($old_email));
    }

    public function confirmEmail(Request $request, $token)
    {
        // $loginConf = new LoginCofirmService();
        $host = config('app.backoffice_url');
        return $this->confirmEmailPage($request, $token, $host);
    }

    private function confirmEmailPage($request, $token, $host)
    {
        $token = GPUserAccessToken::where('token', $token)->where('name', 'verify email')->first();

        if (!$token) {
            return response()->json('Хүчинтэй хугацаа дууссан байна. Дахин оролдоно уу!', 404);
        }

        $abilities = is_array($token->abilities)
            ? $token->abilities
            : json_decode($token->abilities, true);



        $vwcust = VwCrCustList::where('custno', $abilities['custno'])
            ->where('instid', $abilities['instid'])->where('statusid', 1)->first();

        $cust = array();
        if ($vwcust) {
            if ($vwcust->custtypecode == 0) {
                $cust = CrCustInd::where('id', $vwcust->id)
                    ->where('instid', $abilities['instid'])->where('statusid', '<>', -1)->first();
            } else {
                $cust = CrCustOrg::where('id', $vwcust->id)
                    ->where('instid', $abilities['instid'])->where('statusid', '<>', -1)->first();
            }
        }

        if ($cust) {
            $cust->email = $abilities['newemail'];
            $cust->email_verified = 1;
            $cust->save();
            return view('cr::pages.email-verified', compact('host'));
        } else {
            return response()->json('Харилцагч олдсонгүй!', 404);
        }
    }

    /**
     * Change branch IND and ORG the specified resource in storage.
     * @AC cr010500
     * @param Request $request
     * @return Response
     */
    public function cr010500(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required_without_all:custno', // Шалгах талбар
            'custno' => 'required_without_all:id', // Шалгах талбар
            'brchno' => 'required'
        ], [
            'id.required' => "RC000011",
            'custno.required' => "RC000011",
            'brchno.required' => "RC000011",
        ]);
        $user = auth()->user();
        if (isset($validate['id'])) {
            $vwcust = VwCrCustList::where('instid', $user->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        } else {
            $vwcust = VwCrCustList::where('instid', $user->instid)->where('custno', $validate['custno'])->where('statusid', '<>', -1)->first();
        }
        if ($vwcust) {
            if ($vwcust->custtypecode == 0) {
                $cust = CrCustInd::where('id', $vwcust->id)
                    ->where('instid', $user->instid)->where('statusid', '<>', -1)->first();
            } else {
                $cust = CrCustOrg::where('id', $vwcust->id)
                    ->where('instid', $user->instid)->where('statusid', '<>', -1)->first();
            }
        } else {
            $this->error(
                'RC000010',
                [
                    'id' => $validate['id'] ?? $validate['custno']
                ]
            );
        }
        $brchno = GPInstBrch::where('brchno', $validate['brchno'])
            ->where('instid', $user->instid)
            ->where('statusid', 1)
            ->first();
        if (empty($brchno)) {
            $this->error(
                'RC000010',
                [
                    'id' => $validate['brchno']
                ]
            );
        }
        $cust->update([
            'brchno' => $validate['brchno'],
            'updated_by' => $user->id,
            'updated_name' => $user->lname . ' ' . $user->name
        ]);
    }
    /**
     * Change segment IND and ORG the specified resource in storage.
     * @AC cr010600
     * @param Request $request
     * @return Response
     */
    public function cr010600(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required_without_all:custno', // Шалгах талбар
            'custno' => 'required_without_all:id', // Шалгах талбар
            'segcode' => 'required'
        ], [
            'id.required' => "RC000011",
            'custno.required' => "RC000011",
            'segcode.required' => "RC000011",
        ]);
        $user = auth()->user();
        if (isset($validate['id'])) {
            $vwcust = VwCrCustList::where('instid', $user->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        } else {
            $vwcust = VwCrCustList::where('instid', $user->instid)->where('custno', $validate['custno'])->where('statusid', '<>', -1)->first();
        }
        if ($vwcust) {
            if ($vwcust->custtypecode == 0) {
                $cust = CrCustInd::where('id', $vwcust->id)
                    ->where('instid', $user->instid)->where('statusid', '<>', -1)->first();
            } else {
                $cust = CrCustOrg::where('id', $vwcust->id)
                    ->where('instid', $user->instid)->where('statusid', '<>', -1)->first();
            }
        } else {
            $this->error(
                'RC000010',
                [
                    'id' => $validate['id'] ?? $validate['custno']
                ]
            );
        }
        $segcode = GPInstConst::where('value', $validate['segcode'])
            ->where('parent_code', '=', 'seg_type')
            ->where('statusid', 1)
            ->where('is_show_front', 1)
            ->first();
        if (empty($segcode)) {
            $this->error(
                'RC000010',
                [
                    'id' => $validate['segcode']
                ]
            );
        }
        // Дансны сегментийг солих гүйлгээнүүдийг хамт хийх үед хэрэглэнэ.
        // $acnts = VwCrCustAllAcntWithBalance::where('instid', $user->instid)
        //         ->where('custno', $cust->custno)
        //         ->get();

        $cust->update([
            'segcode' => $validate['segcode'],
            'updated_by' => $user->id,
            'updated_name' => $user->lname . ' ' . $user->name
        ]);
    }



    /**
     * Customer Civil Registration Number Reference
     * @AC cr010905
     * @param Request $request
     * @return Response
     */
    public function inquiryCivilid(Request $request)
    {
        $validate = $this->validateMe($request, [
            'regno' => 'required'
        ], [
            'regno.required' => "RC000011",
        ]);

        try {
            $url = env('EBARIMT', 'https://api.ebarimt.mn/api/info/check/getTinInfo?regNo=') . Str::upper($validate['regno']);

            $response = Http::timeout(30)
                ->retry(3, 1000) // 3 удаа retry, 1 секунд хүлээх
                ->get($url);

            if (!$response->successful()) {
                $this->error("RC000010", ['id' => $validate['regno']]);
            }

            $data = $response->json();

            if (!isset($data['data']) || empty($data['data'])) {

                $this->error("RC000010", ['id' => $validate['regno']]);
            }

            $c_civil_id = $data['data'];



            return ['civil_id' => $c_civil_id];
        } catch (Exception $ex) {

            $this->error("RC000010", ['id' => $validate['regno']]);
        }
    }

    private function resolveCustomerTxnDate($txndate)
    {
        $systemTxnDate = CoreService::getTxnDate(auth()->user()->instid);
        if (empty($txndate)) {
            return $systemTxnDate;
        }

        return Carbon::parse($txndate)->gt(Carbon::parse($systemTxnDate))
            ? $systemTxnDate
            : $txndate;
    }
}
