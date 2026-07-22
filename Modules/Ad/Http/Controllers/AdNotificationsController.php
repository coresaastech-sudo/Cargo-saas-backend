<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdAutoJob;
use Modules\Ad\Http\Services\AdNotificationService;
use Modules\Ad\Entities\AdNotifications;
use Modules\Ad\Entities\AdSentNotification;
use Modules\Ad\Entities\Views\VwAdNotifications;
use Modules\Ad\Entities\Views\VwAdNotificationUsers;
use Modules\Ad\Http\Services\AdAutoJobService;
use Modules\Cr\Entities\CrCustNotifications;
use Modules\Cr\Entities\Views\VwCrCustIndList;
use Modules\Cr\Entities\Views\VwCrCustNotifications;
use Modules\Gp\Entities\GPInstFormula;

class AdNotificationsController extends Controller
{

    public function ad030010(Request $request)
    {
        $user = auth()->user();
        $validate = $this->validateMe($request, [
            'notification_id' => 'nullable'
        ]);

        $query = VwAdNotificationUsers::where('vw_ad_notification_users.statusid', '<>', -1);
        if ($user->isadmin == 1) {
            $query = $query->where('vw_ad_notification_users.instid', $request->instid);
        } else {
            $query = $query->where('vw_ad_notification_users.instid', $user->instid);
        }

        if (!empty($validate['notification_id'])) {
            $custids = VwCrCustNotifications::where('notification_id', $validate['notification_id'])
                ->where('statusid', '<>', -1);
            if ($user->isadmin == 1) {
                $custids = $custids->where('instid', $request->instid);
            } else {
                $custids = $custids->where('instid', $user->instid);
            }
            $custids = $custids->pluck('custid')->toArray();

            $query = $query->whereIn('vw_ad_notification_users.custid', $custids);
        }

        return $this->getGridData(
            $request,
            $query->orderBy('vw_ad_notification_users.custid', 'DESC')
        );
    }
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData(
            $request,
            VwAdNotifications::where('instid', auth()->user()->instid)->where('statusid', '<>', -1),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @AC ad030200
     * @return Response
     */
    public function store(Request $request)
    {
        $validated = $this->validate($request, [
            'title' => 'required|string|max:100',
            'description' => 'required|string',
            'is_all_cust' => 'nullable|boolean',
            'is_all_emp' => 'nullable|boolean',
            'is_all_meapp_user' => 'nullable|boolean',
            'notiftype' => 'nullable',
            'usetemp' => 'required',
            'execfreq' => 'nullable',
            'reportActionCode' => 'nullable',
            'autojobid' => 'nullable',
            'url' => 'nullable',
            'users' => 'nullable|array', // [1,2]
            'emails' => 'nullable|array', // [1,2]
        ]);

        return $this->sendNotif($validated);
    }

    public function sendNotif($validated)
    {
        $user = auth()->user();

        $service = new AdNotificationService($user->instid);
        $notif = $service->createMainNotif([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'is_all_cust' => $validated['is_all_cust'] ?? 0,
            'is_all_emp' => $validated['is_all_emp'] ?? 0,
            'is_all_meapp_user' => $validated['is_all_meapp_user'] ?? 0,
            'instid' => $user->instid,
            'created_by' => $user->id,
            'autojobid' => $validated['autojobid'],
            'reportActionCode' => $validated['reportActionCode'],
            'statusid' => 1,
            'usetemp' => $validated['usetemp'],
            'execfreq' => $validated['execfreq'],
            'notiftype' => $validated['notiftype'],
            'url' => $validated['url'] ?? '',
        ]);
        if ($notif->execfreq == 1) {
            $query = VwAdNotificationUsers::where('statusid', '<>', -1);

            if ($user->isadmin != 1) {
                $query->where('instid', $user->instid);
            }

            if (@$validated['is_all_cust'] || @$validated['is_all_emp'] || @$validated['is_all_meapp_user']) {
                if (!(@$validated['is_all_cust'] && @$validated['is_all_emp'] && @$validated['is_all_meapp_user'])) {
                    $types = [];
                    if (@$validated['is_all_meapp_user']) {
                        $types[] = 'MEAPP';
                    }
                    if (@$validated['is_all_cust']) {
                        $types = array_merge($types, ['0', '1']); // 0 - Иргэн харилцагч, 1 - Байгууллага харилцагч
                    }
                    if (@$validated['is_all_emp']) {
                        $types[] = 'ADMIN'; // Админ хэрэглэгч
                    }
                    $query->whereIn('type', $types);
                }
            } else if (!empty($validated['users'])) {
                $manualUsers = $validated['users'];
                $query->where(function ($q) use ($manualUsers) {
                    foreach ($manualUsers as $mu) {
                        $q->orWhere(function ($sub) use ($mu) {
                            $sub->where('type', $mu['type'])->where('custid', $mu['custid']);
                        });
                    }
                });
            } else {
                $query->whereRaw('1=0'); // No selection
            }

            $users = $query->get();


            foreach ($users as $custuser) {
                $service->sendNotification($notif, $custuser, $custuser->getAttributes());
            }

            if (!empty($validated['emails'])) {
                $emails = array_map('strtoupper', $validated['emails']);
                $emailUsers = VwAdNotificationUsers::whereIn(DB::raw('UPPER(email)'), $emails)
                    ->where('statusid', '<>', -1);

                if ($user->isadmin != 1) {
                    $emailUsers->where('instid', $user->instid);
                }

                $emailUsers = $emailUsers->get()->unique('email');
                foreach ($emailUsers as $custuser) {
                    try {
                        $service->sendNotification($notif, $custuser, $custuser->getAttributes());
                    } catch (Exception $ex) {
                        Log::error($ex->getMessage());
                    }
                }
            }
        }
        return $notif;
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @AC ad030100
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $user = auth()->user();
        $notification = VwAdNotifications::where('id', $validated['id'])
            ->where('instid', $user->instid)
            ->where('statusid', '<>', -1)
            ->first();

        if ($notification) {
            $notification->users = [];
            return $notification;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @AC ad030300
     * @return Response
     */
    public function update(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required_without:read',
            'title' => 'required_without:read|string|max:100',
            'description' => 'required_without:read|string',
            'users' => 'nullable|array', // [1,2]
            'is_all_cust' => 'nullable|boolean',
            'is_all_emp' => 'nullable|boolean',
            'is_all_meapp_user' => 'nullable|boolean',
            'notiftype' => 'nullable',
            'execfreq' => 'nullable',
            'reportActionCode' => 'required_without:read',
            'autojobid' => 'nullable',
            'read' => 'required_without:id',
            'readUser' => 'required_without:id',
            'readNotifs' => 'nullable|array',
            'send_now' => 'nullable|boolean',
        ]);

        if (isset($validated['read']) && $validated['read']) {
            foreach ($validated['readNotifs'] as $custnotifId) {
                $notifId = CrCustNotifications::where('instid', auth()->user()->instid)->where('id', $custnotifId)->value('notification_id');
                $notif = AdNotifications::where('statusid', '<>', -1)->where('instid', 1)->where('id', $notifId)->first();
                if (!empty($notif) && !empty(trim($notif->url)))
                    continue;
                DB::beginTransaction();
                try {
                    $usernotification = CrCustNotifications::where('instid', auth()->user()->instid)
                        ->where('custid', $validated['readUser'])
                        ->where('is_read', 0)
                        ->where('id', $custnotifId)
                        ->where('statusid', '<>', -1)->first();
                    if (empty($usernotification))
                        continue;
                    $usernotification->update(['is_read' => 1]);
                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();
                    throw $th;
                }
            }
        } else {
            if (empty($validated['id'])) {
                $this->error("RC000011");
            }

            DB::beginTransaction();
            try {
                $notification = AdNotifications::where('id', $validated['id'])
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', '<>', -1)
                    ->first();

                if ($notification && $notification->autojobid) {
                    if ($validated['send_now'] ?? false) {
                        $this->sendByAutoJob($notification, $validated['users'] ?? []);
                    }
                } else {
                    if (isset($validated['users'])) {
                        CrCustNotifications::where('notification_id', $validated['id'])
                            ->where('statusid', '<>', -1)
                            ->update(['statusid' => -1]);

                        $user = auth()->user();
                        foreach ($validated['users'] as $cuser) {
                            $usernotification = CrCustNotifications::where('instid', $user->instid)
                                ->where('custid', $cuser['custid'])
                                ->where('custtype', $cuser['type'])
                                ->where('notification_id', $validated['id'])
                                ->first();
                            if ($usernotification) {
                                $usernotification->update(['statusid' => 1]);
                            } else {
                                CrCustNotifications::create([
                                    'instid' => $user->instid,
                                    'custid' => $cuser['custid'],
                                    'custtype' => $cuser['type'],
                                    'notification_id' => $validated['id'],
                                    'is_read' => 0,
                                    'created_at' => getNow(),
                                    'created_by' => $user->id,
                                ]);
                            }
                        }
                    }
                }

                unset($validated['users']);
                unset($validated['send_now']);
                AdNotifications::where('id', $validated['id'])
                    ->where('instid', auth()->user()->instid)
                    ->where('statusid', '<>', -1)->update($validated);

                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                throw $th;
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        AdNotifications::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }

    /**
     * Send notification.
     * @param int $id
     * @return Response
     */
    public function send(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
            'data' => 'nullable|array', // [1,2]
        ], [
            'id.required' => "RC000011"
        ]);

        $user = auth()->user();

        $service = new AdNotificationService($user->instid);

        $notif = AdNotifications::where('id', $validated['id'])->where('instid', $user->instid)->first();

        if (isset($notif)) {

            foreach ($validated['data'] as $item) {
                try {
                    if (empty($item['type'])) {
                        $item['type'] = "0";
                    }

                    $custuser = VwAdNotificationUsers::where('type', $item['type'])->where('instid', $user->instid)
                        ->where('custid', $item['custid'])->where('statusid', '<>', -1)->first();

                    $service->sendNotification($notif, $custuser, $item);
                } catch (Exception $ex) {
                    Log::error($ex);
                }
            }
        }
    }

    /**
     * Мэдэгдэл илгээгдсэн жагсаалт
     * @param int $id
     * @return Response
     */
    public function ad030020(Request $request)
    {
        return $this->getGridData(
            $request,
            AdSentNotification::where('statusid', '<>', -1)
                ->where('instid', auth()->user()->instid),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }


    /**
     * Томъёо болон авто ажилбараас харилцагчийн мэдээлэл татах
     * @return Response
     */
    public function ad030600(Request $request)
    {
        $user = auth()->user();

        $validate = $this->validateMe($request, [
            'formulaid' => 'nullable|numeric',
            'autojobid' => 'nullable|numeric'
        ]);

        if (empty($validate['formulaid']) && empty($validate['autojobid'])) {
            return $this->getGridData($request, VwAdNotificationUsers::whereRaw('1=0'));
        }

        $formulaString = null;

        if (!empty($validate['formulaid'])) {
            $formula = GPInstFormula::where('id', $validate['formulaid'])
                ->where('instid', $user->instid)
                ->where('statusid', '>', 0)
                ->first();
            if ($formula) {
                $formulaString = $formula->formula;
            }
        } elseif (!empty($validate['autojobid'])) {
            $autoJob = AdAutoJob::where('id', $validate['autojobid'])
                ->where('instid', $user->instid)
                ->where('statusid', '>', 0)
                ->first();
            if ($autoJob) {
                $formula = GPInstFormula::where('id', $autoJob->formulaid)
                    ->where('instid', $user->instid)
                    ->where('statusid', '>', 0)
                    ->first();
                if ($formula) {
                    $formulaString = $formula->formula;
                }
            } else {
                $this->error('RC000010', $validate);
            }
        }

        if (!$formulaString) {
            $this->error('RC000046');
        }

        $custids = collect(DB::select($formulaString))->pluck('custid')->toArray();

        $query = VwAdNotificationUsers::whereIn('custid', $custids)
            ->where('instid', $user->instid)
            ->where('statusid', '>', 0);

        return $this->getGridData($request, $query);
    }

    private function sendByAutoJob($notification, $requestUsers)
    {
        $instid = auth()->user()->instid;

        $autoJob = AdAutoJob::where('id', $notification->autojobid)
            ->where('instid', $instid)
            ->where('statusid', '>', 0)
            ->first();

        if (!$autoJob) return;

        $formula = GPInstFormula::where('id', $autoJob->formulaid)
            ->where('instid', $instid)
            ->where('statusid', '>', 0)
            ->first();

        if (!$formula || !$formula->formula) return;

        $formulaString = $formula->formula;

        $formulaRows = DB::select($formulaString);
        $formulaCustIds = [];
        foreach ($formulaRows as $fr) {
            $formulaCustIds[] = $fr->custid;
        }

        $allFormulaUsers = VwAdNotificationUsers::whereIn('custid', $formulaCustIds)
            ->where('instid', $instid)
            ->where('statusid', '>', 0)
            ->get();

        $requestCustIds = [];
        foreach ($requestUsers as $ru) {
            $requestCustIds[] = $ru['custid'];
        }

        $isModified = count($requestUsers) < $allFormulaUsers->count();
        if (!$isModified) {
            foreach ($allFormulaUsers as $fu) {
                if (!in_array($fu->custid, $requestCustIds)) {
                    $isModified = true;
                    break;
                }
            }
        }

        if (!$isModified) return;

        $requestUsersMap = [];
        foreach ($requestUsers as $ru) {
            $type = isset($ru['type']) ? $ru['type'] : '0';
            $key = $ru['custid'] . '_' . $type;
            $requestUsersMap[$key] = $ru;
        }

        $service = new AdNotificationService($instid);
        $alreadySentKeys = [];

        foreach ($formulaRows as $row) {
            $rowArray = (array) $row;
            $rowCustId = isset($rowArray['custid']) ? $rowArray['custid'] : null;
            if (!$rowCustId) continue;

            $rowType = isset($rowArray['type']) ? $rowArray['type'] : '0';
            $key = $rowCustId . '_' . $rowType;

            if (!isset($requestUsersMap[$key])) continue;

            $requestUser = $requestUsersMap[$key];
            $type = isset($requestUser['type']) ? $requestUser['type'] : $rowType;
            $sentKey = $rowCustId . '_' . $type;

            if (isset($alreadySentKeys[$sentKey])) continue;
            $alreadySentKeys[$sentKey] = true;

            $custUser = VwAdNotificationUsers::where('custid', $rowCustId)
                ->where('type', $type)
                ->where('instid', $instid)
                ->where('statusid', '<>', -1)
                ->first();

            if ($custUser) {
                $service->sendNotification($notification, $custUser, $rowArray);
            }
        }

        $autoJobService = new AdAutoJobService();
        $nextDate = $autoJobService->getNextDate($autoJob);
        $autoJob->update(['lastexecdate' => $nextDate ? $nextDate->toDateTimeString() : getNow()]);
    }
}
