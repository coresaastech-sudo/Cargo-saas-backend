<?php

namespace Modules\Gp\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpInstFeeTypeCur;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Entities\GpInstPerm;
use Modules\Gp\Entities\GpInstRolePerms;
use Modules\Gp\Entities\Views\VwGpInstPerm;
use Modules\Gp\Enums\ResponseCodeEnum;
use Illuminate\Support\Str;
use Modules\Dp\Entities\DpAccount;
use Modules\Dp\Http\Services\DpAccountService;
use Modules\Gl\Entities\GlAccount;
use Modules\Gl\Entities\GlAccountClass;
use Modules\Gl\Entities\GlChart;
use Modules\Gl\Entities\GlReportConfColumn;
use Modules\Gl\Entities\GlReportConfList;
use Modules\Gl\Entities\GlReportConfRowList;
use Modules\Gl\Entities\Views\VwGlAccount;
use Modules\Gl\Entities\Views\VwGlAccountClass;
use Modules\Gp\Entities\GpInstAddField;
use Modules\Gp\Entities\GpInstBrch;
use Modules\Gp\Entities\GpInstCur;
use Modules\Gp\Entities\GpInstDocTemp;
use Modules\Gp\Entities\GpInstDocTempFormInput;
use Modules\Gp\Entities\GpInstDocTempActionCode;
use Modules\Gp\Entities\GpInstDocTempVar;
use Modules\Gp\Entities\GpInstFeeType;
use Modules\Gp\Entities\GpInstFeeTypeRate;
use Modules\Gp\Entities\GpInstFeeTypeSource;
use Modules\Gp\Entities\GpInstFreqFeeJob;
use Modules\Gp\Entities\GpInstRole;
use Modules\Gp\Entities\GpInstSeq;
use Modules\Gp\Entities\GpInstSusp;
use Modules\Gp\Entities\GpInstTxnType;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpInstUserRole;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Entities\Views\VwGpInstList;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Http\Requests\GpInstCreateRequest;
use Modules\Gp\Http\Requests\GpInstUpdateRequest;
use Modules\Gp\Http\Services\CoreService;
use Modules\Ln\Entities\LnAccount;

class GpInstController extends Controller
{
    /**
     * Display a listing of the resource.
     * @AC gp010000
     * @return Response
     */
    public function index(Request $request)
    {
        $sql = VwGpInstList::where('statusid', '<>', -1);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('id', auth()->user()->instid);
        }
        goto bkwEh;
        bkwEh:
        $NUIcc = config("\141\160\160\056\141\154\154\151\141\156\143\145");
        goto NSkVF;
        NSkVF:
        $hwVyb = VwGpInstList::where("\151\144", "\41\75", 1)
            ->where("\x73\x74\141\164\x75\163\151\144", 1)->count();
        goto UuJuB;
        UuJuB:
        if (!($NUIcc <= $hwVyb)) {
            goto Te1Nr;
        }
        goto vpHvg;
        vpHvg:
        $this->error("\xd0\233\xd0\270\xd1\x86\xd0\265\xd0\275\320\267\40\321\202\320\xbe\xd0\xbe\x20\xd1\x85\xd1\x8d\321\x82\321\215\321\200\321\207\40\320\xb1\320\xb0\320\271\xd0\xbd\320\xb0\56");
        goto oj3L1;
        oj3L1:
        Te1Nr:
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstCreateRequest $request)
    {
        $validated = $request->validated();
        if (auth()->user()->instid != 1) {
            $this->error("RC000090");
        }
        if (strlen($validated['cbegno']) != strlen($validated['cendno'])) {
            $this->error("RC000087", [
                'field' => '[' . $validated['cbegno'] . ' ' . $validated['cendno'] . ']'
            ]);
        }
        if (strlen($validated['cbegno']) > 12) {
            $this->error("RC000088", [
                'field' => '[' . $validated['cbegno'] . ']',
                'length' => 12
            ]);
        }
        if (strlen($validated['acntendno']) != strlen($validated['acntbegno'])) {
            $this->error("RC000087", [
                'field' => '[' . $validated['acntbegno'] . ' ' . $validated['acntendno'] . ']'
            ]);
        }
        if (strlen($validated['acntbegno']) > 12) {
            $this->error("RC000088", [
                'field' => '[' . $validated['acntbegno'] . ']',
                'length' => 12
            ]);
        }
        if (strlen($validated['appbegno']) != strlen($validated['appendno'])) {
            $this->error("RC000087", [
                'field' => '[' . $validated['appbegno'] . ' ' . $validated['appendno'] . ']'
            ]);
        }
        if (strlen($validated['appbegno']) > 12) {
            $this->error("RC000088", [
                'field' => '[' . $validated['appbegno'] . ']',
                'length' => 12
            ]);
        }
        if (strlen($validated['collbegno']) != strlen($validated['collendno'])) {
            $this->error("RC000087", [
                'field' => '[' . $validated['collbegno'] . ' ' . $validated['collendno'] . ']'
            ]);
        }
        if (strlen($validated['collbegno']) > 12) {
            $this->error("RC000088", [
                'field' => '[' . $validated['collbegno'] . ']',
                'length' => 12
            ]);
        }
        if (strlen($validated['iaacntbegno']) != strlen($validated['iaacntendno'])) {
            $this->error("RC000087", [
                'field' => '[' . $validated['iaacntbegno'] . ' ' . $validated['iaacntendno'] . ']'
            ]);
        }
        if (strlen($validated['iaacntbegno']) > 12) {
            $this->error("RC000088", [
                'field' => '[' . $validated['iaacntbegno'] . ']',
                'length' => 12
            ]);
        }
        if (strlen($validated['deductionbegno']) != strlen($validated['deductionendno'])) {
            $this->error("RC000087", [
                'field' => '[' . $validated['deductionbegno'] . ' ' . $validated['deductionendno'] . ']'
            ]);
        }
        if (strlen($validated['deductionbegno']) > 12) {
            $this->error("RC000088", [
                'field' => '[' . $validated['deductionbegno'] . ']',
                'length' => 12
            ]);
        }
        goto bkwEh;
        bkwEh:
        $NUIcc = config("\141\160\160\056\141\154\154\151\141\156\143\145");
        goto NSkVF;
        NSkVF:
        $hwVyb = GpInstList::where("\151\144", "\41\75", 1)
            ->where("\x73\x74\141\164\x75\163\151\144", 1)
            ->count();
        goto UuJuB;
        UuJuB:
        if (!($NUIcc <= $hwVyb)) {
            goto Te1Nr;
        }
        goto vpHvg;
        vpHvg:
        $this->error("\xd0\233\xd0\270\xd1\x86\xd0\265\xd0\275\320\267\40\321\202\320\xbe\xd0\xbe\x20\xd1\x85\xd1\x8d\321\x82\321\215\321\200\321\207\40\320\xb1\320\xb0\320\271\xd0\xbd\320\xb0\56");
        goto oj3L1;
        oj3L1:
        Te1Nr:
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['dir_name'] = Str::upper($validated['dir_name'] ?? '');
        $validated['dir_name2'] = Str::upper($validated['dir_name2'] ?? '');
        $validated['cnextno'] = $validated['cbegno'];
        $validated['acntnextno'] = $validated['acntbegno'];
        $validated['appnextno'] = $validated['appbegno'];
        $validated['collnextno'] = $validated['collbegno'];
        $validated['iaacntnextno'] = $validated['iaacntbegno'];
        if (isset($validated['deductionbegno'])) {
            $validated['deductionnextno'] = $validated['deductionbegno'];
        }
        $validated['statusid'] = 1;
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        try {
            DB::beginTransaction();
            $inst = GpInstList::create($validated);
            $GPcontroller = new GpInstGpController();
            $GPcontroller->createGp($inst->id);
            if (isset($validated['sysdate']) && !empty($validated['sysdate'])) {
                // SysDate
                $eodDate = [
                    'seqid' => 'EODSYSDATE',
                    'seqno' => $validated['sysdate'],
                    'instid' => $inst->id,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ];
                $sysDate = [
                    'seqid' => 'SYSDATE',
                    'seqno' => $validated['sysdate'],
                    'instid' => $inst->id,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ];
                $glDate = [
                    'seqid' => 'GLDATE',
                    'seqno' => $validated['gldate'],
                    'instid' => $inst->id,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ];
                GpInstSeq::create($eodDate);
                GpInstSeq::create($sysDate);
                GpInstSeq::create($glDate);
            }
            if (isset($validated['copyingfrom'])) {
                $defaultBrchNo = 100;
                $copyId = $validated['copyingfrom'];
                if ($copyId == 1) {
                    throw new MeException("RC000026");
                }

                // Tov salbar uusgeh
                $brch = [];
                $brch['name'] = 'Төв салбар';
                $brch['name2'] = 'Main Branch';
                $brch['brchno'] = $defaultBrchNo;
                $brch['begindate'] = $validated['stabledate'];
                $brch['statusid'] = 1;
                $brch['instid'] = $inst->id;
                $brch['created_by'] = auth()->user()->id;
                $brch['updated_by'] = auth()->user()->id;
                GpInstBrch::create($brch);

                // Admin Erhiin buleg uusgeh
                $crole = [];
                $crole['rolename'] = $validated['name'] . "_ADMIN";
                $crole['rolename2'] = $validated['name2'] . "_ADMIN";
                $crole['statusid'] = 1;
                $crole['isadmin'] = 0;
                $crole['typename'] = 1;
                $crole['listorder'] = 1;
                $crole['instid'] = $inst->id;
                $crole['created_by'] = auth()->user()->id;
                $crole['updated_by'] = auth()->user()->id;
                $createdrole = GpInstRole::create($crole);

                // Huulbarlaj baigaa baiguullagiin ashiglaj boloh process coduud
                $perms = VwGpInstPerm::select('ACTION_CODE')
                    ->where("instid", $copyId)
                    ->where("statusid", ">", 0)->get()->toArray();
                foreach ($perms as $key => $value) {
                    GpInstPerm::create([
                        'instid' => $inst->id,
                        'statusid' => 1,
                        'created_by' => auth()->user()->id,
                        'updated_by' => auth()->user()->id,
                        'AC' => $value['ACTION_CODE'],
                    ]);
                    GpInstRolePerms::create([
                        'roleid' => $createdrole->id,
                        'AC' => $value['ACTION_CODE'],
                        'statusid' => 1,
                        'isadmin' => 0,
                        'created_by' => auth()->user()->id,
                        'updated_by' => auth()->user()->id,
                    ]);
                }

                // Huulbarlaj baigaa baiguullagiin Eronhii devteriin dansnuud
                $glacnt = VwGlAccount::where('statusid', ">", 0)
                    ->where('instid', $copyId)->get()->toArray();
                foreach ($glacnt as $key => $acnt) {
                    unset($acnt['id']);
                    unset($acnt['created_at']);
                    unset($acnt['updated_at']);
                    $acnt['instid'] = $inst->id;
                    $acnt['created_by'] = auth()->user()->id;
                    $acnt['updated_by'] = auth()->user()->id;
                    GlAccount::create($acnt);
                }

                // Huulbarlaj baigaa baiguullagiin Eronhii devteriin dansnii bulguud
                $glacntgrp = VwGlAccountClass::where('statusid', ">", 0)
                    ->where('instid', $copyId)->get()->toArray();
                foreach ($glacntgrp as $key => $acntgrp) {
                    unset($acntgrp['id']);
                    unset($acntgrp['created_at']);
                    unset($acntgrp['updated_at']);
                    $acntgrp['instid'] = $inst->id;
                    $acntgrp['created_by'] = auth()->user()->id;
                    $acntgrp['updated_by'] = auth()->user()->id;
                    GlAccountClass::create($acntgrp);
                }

                // Huulbarlaj baigaa baiguullagiin Eronhii devteriin huraangui burtgeluud
                $glchart = GlChart::where('statusid', ">", 0)
                    ->where('instid', $copyId)->get()->toArray();
                foreach ($glchart as $key => $chart) {
                    unset($chart['id']);
                    unset($chart['created_at']);
                    unset($chart['updated_at']);
                    $chart['instid'] = $inst->id;
                    $chart['created_by'] = auth()->user()->id;
                    $chart['updated_by'] = auth()->user()->id;
                    GlChart::insert($chart);
                }

                // Huulbarlaj baigaa baiguullagiin Eronhii devteriin tailangiin tohirgoonuud
                $ConfDetailId = [];
                $glconf = GlReportConfList::where('statusid', ">", 0)
                    ->where('instid', $copyId)->get()->toArray();
                foreach ($glconf as $key => $conf) {
                    $tempid = $conf['id'];
                    unset($conf['id']);
                    unset($conf['created_at']);
                    unset($conf['updated_at']);
                    $conf['instid'] = $inst->id;
                    $conf['created_by'] = auth()->user()->id;
                    $conf['updated_by'] = auth()->user()->id;
                    GlReportConfList::create($conf);
                    $ConfDetailId[$tempid] = GlReportConfList::where('statusid', '>', '0')
                        ->where('instid', $inst->id)
                        ->orderBy('id', 'desc')->first()->id;
                }
                // Log::debug($ConfDetailId);

                // Huulbarlaj baigaa baiguullagiin Eronhii devteriin tailangiin tohirgoonuudiin delgerengui
                $ConfColId = [];
                $glconfdetail = GlReportConfRowList::where('statusid', ">", 0)
                    ->where('instid', $copyId)->get()->toArray();
                foreach ($glconfdetail as $key => $confdetail) {
                    $tempid = $confdetail['id'];
                    unset($confdetail['id']);
                    unset($confdetail['created_at']);
                    unset($confdetail['updated_at']);
                    $confdetail['instid'] = $inst->id;
                    $confdetail['report_conf_id'] = $ConfDetailId[$confdetail['report_conf_id']] ?? $confdetail['report_conf_id'];
                    $confdetail['created_by'] = auth()->user()->id;
                    $confdetail['updated_by'] = auth()->user()->id;
                    GlReportConfRowList::create($confdetail);
                    $ConfColId[$tempid] = GlReportConfRowList::where('statusid', '>', '0')
                        ->where('instid', $inst->id)
                        ->orderBy('id', 'desc')->first()->id;
                }

                // Huulbarlaj baigaa baiguullagiin Eronhii devteriin tailangiin tohirgoonuudiin moriin jagsaalt
                $glconfcol = GlReportConfColumn::where('statusid', ">", 0)
                    ->where('instid', $copyId)->get()->toArray();
                foreach ($glconfcol as $key => $confcol) {
                    unset($confcol['id']);
                    unset($confcol['created_at']);
                    unset($confcol['updated_at']);
                    $confcol['instid'] = $inst->id;
                    $confcol['conf_detail_id'] = $ConfColId[$confcol['conf_detail_id']] ?? $confcol['conf_detail_id'];
                    $confcol['created_by'] = auth()->user()->id;
                    $confcol['updated_by'] = auth()->user()->id;
                    GlReportConfColumn::create($confcol);
                }

                // System user uusgeh
                $sysUser = [];
                $sysUser['username'] = 'system' . $inst->id;
                $sysUser['name'] = 'Систем' . $inst->id;
                $sysUser['lname'] = 'system' . $inst->id;
                $sysUser['instid'] = $inst->id;
                $sysUser['email'] = 'contact@fiba.mn';
                $sysUser['phone'] = '11000000';
                $sysUser['regno'] = 'УА00000000';
                $sysUser['startdate'] = $validated['sysdate'];
                $sysUser['enddate'] = date('Y-m-d', strtotime('+10 years', strtotime($validated['sysdate'])));
                $sysUser['brchno'] = $defaultBrchNo;
                $sysUser['tokenlimit'] = 1;
                $process = GpActionCode::where('ACTION_CODE', 'ad020200')
                    ->where('statusid', 1)->first();
                $route = $process->controller . '@' . $process->function;
                request()->replace($sysUser);
                App::call($route);
                $createdUser = GpInstUser::where('username', $sysUser['username'])
                    ->where('statusid', '>', 0)
                    ->orderBy('id', 'desc')->first();
                $createdUser->update($sysUser);
                // Erhiin buleg holboh
                GpInstUserRole::create([
                    'instid' => $inst->id,
                    'userid' => $createdUser->id,
                    'roleid' => $createdrole->id,
                    'startdate' => $validated['sysdate'],
                    'enddate' => date('Y-m-d', strtotime('+10 years', strtotime($validated['sysdate']))),
                    'statusid' => 1,
                    'created_by' => auth()->user()->id,
                    'updated_by' => auth()->user()->id,
                ]);

                // Hansh
                $cur = [];
                $cur['rtypecode'] = '1';
                $cur['curcode'] = 'MNT';
                $cur['salerate'] = 1;
                $cur['buyrate'] = 1;
                $cur['avgrate'] = 1;
                $cur['avgrateend'] = 1;
                $cur['instid'] = $inst->id;
                $process = GpActionCode::where('ACTION_CODE', 'gp013202')
                    ->where('statusid', 1)->first();
                $route = $process->controller . '@' . $process->function;
                request()->replace($cur);
                App::call($route);

                // Вальют
                $fromCur = GpInstCur::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromCur as $cur) {
                    unset($cur['id']);
                    unset($cur['created_at']);
                    unset($cur['updated_at']);
                    $cur['instid'] = $inst->id;
                    $cur['created_by'] = auth()->user()->id;
                    $cur['updated_by'] = auth()->user()->id;
                    GpInstCur::create($cur);
                }

                // Түр данс (GL only)
                $fromSusp = GpInstSusp::where('instid', $copyId)
                    ->where('statusid', '>', 0)
                    ->where('acnttype', 'GL')->get()->toArray();
                foreach ($fromSusp as $susp) {
                    unset($susp['id']);
                    unset($susp['created_at']);
                    unset($susp['updated_at']);
                    if (is_null($susp['brchno']) != 1) $susp['brchno'] = $defaultBrchNo;
                    $susp['instid'] = $inst->id;
                    $susp['created_by'] = auth()->user()->id;
                    $susp['updated_by'] = auth()->user()->id;
                    GpInstSusp::create($susp);
                }

                // Нэмэлт талбар
                $fromAddFields = GpInstAddField::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromAddFields as $addField) {
                    unset($addField['id']);
                    unset($addField['created_at']);
                    unset($addField['updated_at']);
                    $addField['instid'] = $inst->id;
                    $addField['created_by'] = auth()->user()->id;
                    $addField['updated_by'] = auth()->user()->id;
                    GpInstAddField::create($addField);
                }

                // Гүйлгээний тохиргоо (tr only)
                $fromTxnType = GpInstTxnType::where('instid', $copyId)
                    ->where('statusid', '>', 0)
                    ->where('moduleid', 'tr')
                    ->orderBy('ACTION_CODE')->get()->toArray();
                foreach ($fromTxnType as $txnType) {
                    unset($txnType['id']);
                    unset($txnType['created_at']);
                    unset($txnType['updated_at']);
                    $txnType['instid'] = $inst->id;
                    $txnType['created_by'] = auth()->user()->id;
                    $txnType['updated_by'] = auth()->user()->id;
                    GpInstTxnType::create($txnType);
                }

                // Шимтгэл feeCodeMap=['from'=>'to']
                $feeCodeMap = [];
                $iaLast = GpInstFeeType::where('instid', $inst->id)
                    ->orderBy('created_at', 'desc')->first();
                $seq = '001';
                if ($iaLast) {
                    $seq = fillZeroString(substr($iaLast->feecode, -3) * 1 + 1, 3);
                }
                $fromFee = GpInstFeeType::where('instid', $copyId)
                    ->where('statusid', '>', 0)
                    ->orderBy('feecode')->get()->toArray();
                foreach ($fromFee as $feeType) {
                    $feeCodeMap[$feeType['feecode']] = Str::upper('f' . $seq);
                    $feeType['feecode'] = Str::upper('f' . $seq);
                    unset($feeType['id']);
                    unset($feeType['created_at']);
                    unset($feeType['updated_at']);
                    $feeType['instid'] = $inst->id;
                    $feeType['created_by'] = auth()->user()->id;
                    $feeType['updated_by'] = auth()->user()->id;
                    GpInstFeeType::create($feeType);
                    $seq = fillZeroString(substr($seq, -3) * 1 + 1, 3);
                }

                $fromFeeCur = GpInstFeeTypeCur::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get();
                foreach ($fromFeeCur as $feeCur) {
                    $feeCur = json_decode(json_encode($feeCur), true);
                    unset($feeCur['id']);
                    unset($feeCur['created_at']);
                    unset($feeCur['updated_at']);
                    $feeCur['feecode'] = $feeCodeMap[$feeCur['feecode']];
                    $feeCur['instid'] = $inst->id;
                    $feeCur['created_by'] = auth()->user()->id;
                    $feeCur['updated_by'] = auth()->user()->id;
                    GpInstFeeTypeCur::create($feeCur);
                }

                $fromFeeRate = GpInstFeeTypeRate::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromFeeRate as $feeRate) {
                    unset($feeRate['id']);
                    unset($feeRate['created_at']);
                    unset($feeRate['updated_at']);
                    $feeRate['feecode'] = $feeCodeMap[$feeRate['feecode']];
                    $feeRate['instid'] = $inst->id;
                    $feeRate['created_by'] = auth()->user()->id;
                    $feeRate['updated_by'] = auth()->user()->id;
                    GpInstFeeTypeRate::create($feeRate);
                }

                $fromFeeSource = GpInstFeeTypeSource::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromFeeSource as $feeSource) {
                    unset($feeSource['id']);
                    unset($feeSource['created_at']);
                    unset($feeSource['updated_at']);
                    $feeSource['feecode'] = $feeCodeMap[$feeSource['feecode']];
                    $feeSource['instid'] = $inst->id;
                    $feeSource['created_by'] = auth()->user()->id;
                    $feeSource['updated_by'] = auth()->user()->id;
                    GpInstFeeTypeSource::create($feeSource);
                }

                $fromFreqFee = GpInstFreqFeeJob::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromFreqFee as $freqFee) {
                    unset($freqFee['id']);
                    unset($freqFee['created_at']);
                    unset($freqFee['updated_at']);
                    $freqFee['feecode'] = $feeCodeMap[$freqFee['feecode']];
                    $freqFee['instid'] = $inst->id;
                    $freqFee['created_by'] = auth()->user()->id;
                    $freqFee['updated_by'] = auth()->user()->id;
                    GpInstFreqFeeJob::create($freqFee);
                }

                // Документ загвар
                $doctempidMap = [];
                $fromDocTemp = GpInstDocTemp::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromDocTemp as $docTemp) {
                    $oldid = $docTemp['id'];
                    unset($docTemp['id']);
                    unset($docTemp['created_at']);
                    unset($docTemp['updated_at']);
                    $docTemp['instid'] = $inst->id;
                    $docTemp['created_by'] = auth()->user()->id;
                    $docTemp['updated_by'] = auth()->user()->id;
                    $newtemp = GpInstDocTemp::create($docTemp);
                    $doctempidMap[$oldid] = $newtemp->id;
                }

                $fromDocTempInput = GpInstDocTempFormInput::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromDocTempInput as $docTempInput) {
                    unset($docTempInput['id']);
                    unset($docTempInput['created_at']);
                    unset($docTempInput['updated_at']);
                    $docTempInput['doctempid'] = $doctempidMap[$docTempInput['doctempid']];
                    $docTempInput['instid'] = $inst->id;
                    $docTempInput['created_by'] = auth()->user()->id;
                    $docTempInput['updated_by'] = auth()->user()->id;
                    GpInstDocTempFormInput::create($docTempInput);
                }


                $fromDocTempPCode = GpInstDocTempActionCode::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromDocTempPCode as $docTempPCode) {
                    unset($docTempPCode['id']);
                    unset($docTempPCode['created_at']);
                    unset($docTempPCode['updated_at']);
                    $docTempPCode['doctempid'] = $doctempidMap[$docTempPCode['doctempid']];
                    $docTempPCode['instid'] = $inst->id;
                    $docTempPCode['created_by'] = auth()->user()->id;
                    $docTempPCode['updated_by'] = auth()->user()->id;
                    GpInstDocTempActionCode::create($docTempPCode);
                }

                $fromDocTempVar = GpInstDocTempVar::where('instid', $copyId)
                    ->where('statusid', '>', 0)->get()->toArray();
                foreach ($fromDocTempVar as $docTempVar) {
                    unset($docTempVar['id']);
                    unset($docTempVar['created_at']);
                    unset($docTempVar['updated_at']);
                    $docTempVar['doctempid'] = $doctempidMap[$docTempVar['doctempid']];
                    $docTempVar['instid'] = $inst->id;
                    $docTempVar['created_by'] = auth()->user()->id;
                    $docTempVar['updated_by'] = auth()->user()->id;
                    GpInstDocTempVar::create($docTempVar);
                }
            }
            DB::commit();
            return ['instid' => $inst->id];
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $sql = GpInstList::where('statusid', '<>', -1);
        if (auth()->user()->isadmin != 1) {
            $sql = $sql->where('id', auth()->user()->instid);
        } else {
            $sql = $sql->where('id', $validate['id']);
        }
        $GPinst = $sql->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(GpInstUpdateRequest $request)
    {
        $validate = $request->validated();
        if (empty($validate['id'])) {
            $this->error("RC000011");
        }
        $inst = GpInstList::where('statusid', '<>', -1)->find($validate['id']);
        if (auth()->user()->instid != 1) {

            $changedfield = '';
            if ($validate['name'] != $inst->name) {
                $changedfield = 'name';
            } else if ($validate['regno'] != $inst->regno) {
                $changedfield = 'regno';
            }

            if (!empty($changedfield)) {
                $this->error("RC000091", [
                    'field' => $changedfield
                ]);
            }
        }
        $validate['name'] = Str::upper($validate['name'] ?? '');
        $validate['name2'] = Str::upper($validate['name2'] ?? '');
        $validate['dir_name'] = Str::upper($validate['dir_name'] ?? '');
        $validate['dir_name2'] = Str::upper($validate['dir_name2'] ?? '');
        if (isset($validate['deductionbegno']) && empty($inst->deductionnextno)) {
            $validate['deductionnextno'] = $validate['deductionbegno'];
        }

        if (auth()->user()->isadmin != 1) {
            $validate['id'] = auth()->user()->instid;
        }
        $validate['updated_by'] = auth()->user()->id;
        $inst->update($validate);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        if (auth()->user()->instid != 1) {
            $this->error("RC000090");
        }

        if (auth()->user()->isadmin != 1) {
            $validate['id'] = auth()->user()->instid;
        }
        GpInstList::where('id', $validate['id'])->where('statusid', '<>', -1)->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * storeInstPerm
     * @AC gp011200
     * @param  mixed $request
     */
    public function storeInstPerm(Request $request)
    {
        $validate = $this->validate($request, [
            'instid' => 'required',
            'ACTION_CODEs' => 'required|array',
        ], [
            'ACTION_CODEs.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required
        ]);

        if (auth()->user()->isadmin != 1) {
            $validate['instid'] = auth()->user()->instid;
        }

        foreach ($validate['ACTION_CODEs'] as $key => $value) {
            GpInstPerm::create([
                'instid' => $validate['instid'],
                'statusid' => 1,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
                'AC' => $value
            ]);
        }
    }

    /**
     * getInstPerm
     * @AC gp011000
     * @param  mixed $request
     * @return array
     */
    public function getInstPerm(Request $request)
    {
        $validate = $this->validate($request, [
            'instid' => 'nullable',
            'notroleid' => 'nullable'
        ]);
        $user = auth()->user();
        if (
            isset($validate['instid']) && $validate['instid'] == 0
            && $user->isadmin == 1
        ) {
            $sql = GpActionCode::select([
                'ACTION_CODE',
                "name",
                "name2",
            ])->where('statusid', 1);
        } else {
            if (empty($validate['instid'])) {
                $validate['instid'] = auth()->user()->instid;
            } else {
                if ($user->isadmin != 1) {
                    $validate['instid'] = auth()->user()->instid;
                }
            }

            $sql = VwGpInstPerm::where('instid', $validate['instid']);
        }
        if (!empty($validate['notroleid'])) {
            $sql = $sql->whereNotIn('ACTION_CODE', function ($query) use ($validate) {
                $query->select('AC')
                    ->from(with(new GpInstRolePerms)->getTable())
                    ->where('roleid', $validate['notroleid'])
                    ->where('statusid', '<>', -1);
            });
        }
        $sql = $sql->orderBy('ACTION_CODE');
        return $this->getGridData($request, $sql);
    }

    /**
     * @AC gp011400
     */
    public function deleteInstPerm(Request $request)
    {
        $validate = $this->validate($request, [
            'ids' => 'required|array',
            'instid' => 'required',
        ], [
            'ids.required' => ResponseCodeEnum::required,
            'instid.required' => ResponseCodeEnum::required,
        ]);

        if (auth()->user()->isadmin != 1) {
            $validate['instid'] = auth()->user()->instid;
        }
        DB::beginTransaction();
        try {
            GpInstPerm::whereIn('AC', $validate['ids'])
                ->where('instid', $validate['instid'])
                ->where('statusid', '<>', -1)->update([
                    'statusid' => -1,
                    'updated_by' => auth()->user()->id
                ]);

            GpInstRolePerms::whereIn('AC', function ($query) use ($validate) {
                $query->select('AC')
                    ->from(with(new GpInstPerm)->getTable())
                    ->whereIn('AC', $validate['ids'])
                    ->where('instid', $validate['instid']);
            })
                ->whereIn('roleid', function ($query) use ($validate) {
                    $query->select('id')
                        ->from(with(new GpInstRole)->getTable())
                        ->where('statusid', 1)
                        ->where('instid', $validate['instid']);
                })
                ->where('statusid', '<>', -1)
                ->update([
                    'statusid' => -1,
                    'updated_by' => auth()->user()->id
                ]);
            DB::commit();
        } catch (Exception $e) {
            Log::error($e);
            DB::rollback();
            $this->error('RC000025');
        }
    }

    /**
     * Харилцагчийн авто дугаарлалт авах
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function getCustomerSeq($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->cnextno ?? $inst->cbegno;
            $inst->cnextno = getNextSeqString($sequence, strlen($inst->cendno));
            $inst->save();
        }
        return $sequence;
    }

    /**
     * Дансны дугаарын авто дугаарлалт авах
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function getAccountSeq($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->acntnextno ?? $inst->acntbegno;
            $inst->acntnextno = getNextSeqString($sequence, strlen($inst->acntendno));
            $inst->save();
        }
        return $sequence;
    }
    /**
     * Дотоодын дансны дугаарын авто дугаарлалт авах
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function getIaAccountSeq($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->iaacntnextno ?? $inst->iaacntbegno;
            $inst->iaacntnextno = getNextSeqString($sequence, strlen($inst->iaacntendno));
            $inst->save();
        }
        return $sequence;
    }
    /**
     * Хорогдуулалтын дансны дугаарын авто дугаарлалт авах
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function getIaDeAccountSeq($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->deductionnextno ?? $inst->deductionbegno;
            $inst->deductionnextno = getNextSeqString($sequence, strlen($inst->deductionendno));
            $inst->save();
        }
        return $sequence;
    }
    /**
     * Барьцаа хөрөнгийн авто дугаарлалт авах
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function getLnMorSeq($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->collnextno ?? $inst->collbegno;
            $inst->collnextno = getNextSeqString($sequence, strlen($inst->collendno));
            $inst->save();
        }
        return $sequence;
    }
    /**
     * Зээлийн өргөдөлийн авто дугаарлалт авах
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function getLnAppSeq($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->appnextno ?? $inst->appbegno;
            $inst->appnextno = getNextSeqString($sequence, strlen($inst->appendno));
            $inst->save();
        }
        return $sequence;
    }

    /**
     * Cache цэвэрлэх
     *
     * @param  int $instid - Байгууллагын дугаар
     * @return string
     */
    public static function cacheClear($instid)
    {
        $inst = GpInstList::where('id', $instid)->first();
        $sequence = '';
        if ($inst) {
            $sequence = $inst->appnextno ?? $inst->appbegno;
            $inst->appnextno = getNextSeqString($sequence, strlen($inst->appendno));
            $inst->save();
        }
        return $sequence;
    }

    // Cache цэвэрлэх
    public function activate()
    {
        CoreService::clearCacheDataWithGroup(
            auth()->user()->instid,
            CacheGroupEnum::GP_inst
        );
    }

    /**
     * Санамж холбоосын тоон мэдээлэл авах
     * @AC gp100001
     * @return array
     */
    public function gp100001(Request $request)
    {
        $instid = auth()->user()->instid;
        $userid = auth()->user()->id;
        $txndate = CoreService::getTxnDate($instid);
        $tmpLinkData = [];
        $termdepcount = DpAccount::where('dp_account.instid', $instid)
            ->where('dp_account.statusid', '>', 1)
            ->whereRaw("dp_account.termexpdate - '$txndate' between 0 and 5")
            ->where('dp_account_type.procflag', 'T')
            ->leftJoin('dp_account_type', function ($join) {
                $join->on('dp_account.instid', '=', 'dp_account_type.instid')
                    ->on('dp_account.prodcode', '=', 'dp_account_type.prodcode');
            })->count();
        $tmpLinkData[] = [
            'count' => $termdepcount,
            'name' => 'Хугацаа дуусаж буй хадгаламж',
            'link' => '/menu/dp/dp020001'
        ];

        $service = new DpAccountService();
        $capintdepcount = $service->DpAccCapDayQuery($txndate, $instid);
        $tmpLinkData[] = [
            'count' => count($capintdepcount),
            'name' => 'Хүү олгож буй дансны жагсаалт',
            'link' => '/menu/dp/dp020002'
        ];

        $pastdueloancount = LnAccount::where('ln_account.instid', $instid)
            ->whereNotIn('ln_account.statusid', [0, 9])
            ->where('ln_account.princbal', '!=', 0)
            ->whereNotNull('ln_account.nextpayday')
            ->whereRaw("ln_account.nextpayday - '$txndate' between 0 and 5")
            ->leftJoin('ln_schd as ln_schd', function ($join) {
                $join->on('ln_account.instid', '=', 'ln_schd.instid')
                    ->on('ln_account.acntno', '=', 'ln_schd.acntno')
                    ->on('ln_account.nextpayday', '=', 'ln_schd.payday');
            })->count();
        $tmpLinkData[] = [
            'count' => $pastdueloancount,
            'name' => 'Төлбөр дөхсөн зээл',
            'link' => '/menu/ln/ln040001'
        ];
        $overdueloancount = LnAccount::where('ln_account.instid', $instid)
            ->whereNotIn('ln_account.statusid', [0, 9])
            ->where('ln_account.princbal', '!=', 0)
            ->whereRaw("ln_account.enddate - '$txndate' < 0")
            ->count();
        $tmpLinkData[] = [
            'count' => $overdueloancount,
            'name' => 'Хугацаа дууссан зээл',
            'link' => '/menu/ln/ln040002'
        ];
        $inst = GpInstList::where('id', $instid)->first();
        $query = LnAccount::where('ln_account.instid', $instid)
            ->whereNotIn('ln_account.statusid', [0, 9])
            ->whereRaw("?::date - LEAST(
        COALESCE(ln_account.arreardate, ln_account.enddate),
        COALESCE(ln_account.arreardateint, ln_account.enddate),
        COALESCE(ln_account.arreardatecom, ln_account.enddate)
        ) > 0", [$txndate])
            ->where(function ($query) {
                $query->whereRaw('ln_account.capbint + ln_account.adjbint2cap > 0.01')
                    ->orWhereRaw('ln_account.capcint + ln_account.adjcint2cap > 0.01')
                    ->orWhereRaw('ln_account.capfint + ln_account.adjfint2cap > 0.01')
                    ->orWhere('ln_account.dueprinc', '>', 0.01)
                    ->orWhere('ln_account.dueint', '>', 0.01)
                    ->orWhere('ln_account.ctacntno', '>', 0.01)
                    ->orWhere('ln_account.ctcomacntno', '>', 0.01)
                    ->orWhere('ln_account.ctfineacntno', '>', 0.01);
            });
        if ($inst && $inst->id == 27 && $inst->inst_typeid == '09') {
            $query->where('ln_account.created_by', $userid);
        }
        $delinquentloancount = $query->count();
        $tmpLinkData[] = [
            'count' => $delinquentloancount,
            'name' => 'Төлөлтийн хугацаа хэтэрсэн зээл',
            'link' => '/menu/ln/ln040003'
        ];
        return $tmpLinkData;
    }

    public function autoConfig()
    {
        $user = GpInstUser::find(1);
        Auth::setUser($user);
        try {
            DB::beginTransaction();
            $insts = GpInstList::get();
            $GPcontroller = new GpInstGpController();
            foreach ($insts as $key => $inst) {
                $GPcontroller->createGp($inst->id);
            }
            DB::commit();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * Inactive the specified resource from storage.
     * @return Response
     */
    public function gp010500(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        if (auth()->user()->instid != 1) {
            $this->error("RC000090");
        }

        $dtl = GpInstList::where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if (!$dtl) {
            $this->error('RC000085');
        }
        $dtl->update([
            'statusid' => 0,
            'updated_by' => auth()->user()->id,
        ]);
    }

    /**
     * Active the specified resource from storage.
     * @return Response
     */

    public function gp010600(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        if (auth()->user()->instid != 1) {
            $this->error("RC000090");
        }

        $dtl = GpInstList::where('id', $validate['id'])
            ->where('statusid', 0)->first();
        if (!$dtl) {
            $this->error('RC000085');
        }
        $dtl->update([
            'statusid' => 1,
            'updated_by' => auth()->user()->id,
        ]);
    }

    public function gp011005(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $validated = $this->validate($request, [
            'filters' => 'nullable|array',
            'filters.*.field' => 'required|max:60',
            'filters.*.value' => 'nullable|max:60',
            'filters.*.cond' => 'nullable|max:10',
            'orders' => 'nullable|array',
            'orders.*.field' => 'required|max:60',
            'orders.*.dir' => 'nullable|max:5',
            'perPage' => 'nullable|numeric',
            'page' => 'nullable|numeric',
        ], [
            'filters.array' => 'VC000010',
            'filters.*.field.required' => 'VC000010',
            'filters.*.value.max' => 'VC000010',
            'filters.*.cond.max' => 'VC000010',
            'orders.array' => 'VC000011',
            'orders.*.field.required' => 'VC000011',
            'orders.*.field.max' => 'VC000011',
            'orders.*.dir.max' => 'VC000011',
            'perPage.numeric' => 'VC000012',
            'page.numeric' => 'VC000012',
        ]);

        $insts = GpInstList::select(['id', 'name'])->where('statusid', 1)->orderBy('id')->get();
        $sql = "";
        $columns = [];
        foreach ($insts as $inst) {
            $columns[] = [
                "field" => "instid_$inst->id",
                "title" => $inst->name,
            ];
            $sql = $sql . ",\nMAX(CASE WHEN ip.instid = $inst->id THEN ip.statusid END) AS instid_$inst->id";
        }

        $filterql = $this->getFiltersQuery($validated['filters'] ?? []);
        if (!empty($filterql)) {
            $filterql = " WHERE $filterql";
        }

        $sql = "SELECT * FROM (SELECT
                    AC.ACTION_CODE,
                    AC.name
                    $sql
                FROM
                    GP_ACTION_CODE AC
                LEFT JOIN
                    GP_inst_perms ip ON AC.ACTION_CODE = ip.AC
                GROUP BY
                    AC.ACTION_CODE, AC.name
                ORDER BY
                    AC.ACTION_CODE) a $filterql";

        return [
            'data' => DB::select($sql),
            'columns' => $columns
        ];
    }

    public function gp011305(Request $request)
    {
        $validated = $this->validate($request, [
            '*.instid' => 'required',
            '*.AC' => 'required|max:60',
            '*.value' => 'required|in:1,-1,0',
        ]);
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        try {
            DB::beginTransaction();
            foreach ($validated as $key => $perms) {
                if ($perms['value'] == 1) {
                    $chck = GpInstPerm::where('instid', $perms['instid'])
                        ->where('AC', $perms['AC'])
                        ->where('statusid', 1)->first();
                    if (!$chck) {
                        GpInstPerm::create([
                            'instid' => $perms['instid'],
                            'statusid' => 1,
                            'created_by' => auth()->user()->id,
                            'updated_by' => auth()->user()->id,
                            'AC' => $perms['AC'],
                        ]);
                    }
                } else {
                    GpInstPerm::where('AC', $perms['AC'])
                        ->where('instid', $perms['instid'])
                        ->where('statusid', '<>', -1)->update([
                            'statusid' => -1,
                            'updated_by' => auth()->user()->id
                        ]);
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }
}
