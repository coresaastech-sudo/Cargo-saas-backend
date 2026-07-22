<?php

namespace Modules\Gp\Http\Controllers;

use App\Events\AdSupervisorEvent;
use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpActionCode;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Ad\Http\Services\AdAutoJobService;
use Modules\Gp\Entities\GpConnConf;
use Modules\Gp\Entities\GpDbBackupLog;
use Modules\Gp\Entities\GpInstRolePerms;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpInstUserRole;
use Modules\Gp\Entities\Views\VwGpInstPerm;
use Modules\Gp\Enums\CacheGroupEnum;
use Modules\Gp\Enums\NotAuthActionCodesEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\DbBackupProcessJob;
use Modules\Gp\Jobs\EBarimtJob;
use PDOException;
use TypeError;
use App\Events\AdTxnMonitoringEvent;
use App\Resolvers\ChannelResolver;
use Modules\Ad\Entities\AdSvUser;
use Modules\Ad\Entities\Views\VwAdSvUser;
use Modules\Ad\Http\Controllers\AdNotificationsController;
use Modules\Ad\Http\Services\AdNotificationService;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Tr\Entities\TrPendTxn;
use Modules\Tr\Http\Controllers\TrJournalController;

class GpController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function process(Request $request)
    {
        // Controller хайж олон хүсэлт илгээх
        if ($request->header('AC')) {
            $process = $this->getActionCodeDetail($request->header('AC'));
            $code = $request->header('AC');
            if ($process) {
                if (!$this->checkActionCode($code)) {
                    throw new MeException("RC000014", ['AC' => $code]);
                }
                $route = $process->controller . '@' . $process->function;
                $exception = null;
                $iscreatedwebosocket = false;
                $websocketid = rand(100, 999999);
                $iserror = false;
                try {
                    if (config('app.transaction_websocket_enabled', false) && $process->txntype == 2) {
                        $instid = auth()->user()->instid;
                        if ($instid) {
                            $inst = GpInstList::where('id', $instid)->first();
                            $msg = "Гүйлгээ эхэллээ.";
                            $txnName = $process->name;
                            // websocket push data
                            try {
                                $tmpdata = [
                                    'time' => Carbon::now(),
                                    'id' => $websocketid,
                                    'status' => 1,
                                    'processName' => $msg,
                                    'txnName' => $txnName,
                                    'createdBy' => auth()->user()->firstname,
                                    'instName' => $inst->name,
                                    'stage' => 1,
                                    'channelName' => ChannelResolver::resolve()
                                ];
                                event(new AdTxnMonitoringEvent($tmpdata, $instid));
                                event(new AdTxnMonitoringEvent($tmpdata, 1));
                                $iscreatedwebosocket = true;
                            } catch (Exception $ex) {
                                Log::debug($ex);
                            }
                        }
                    }
                    $svUser = [];
                    $ispreview = 0;
                    $user = auth()->user();
                    $superSv = $this->checkSv($process, $request, $ispreview, $svUser);
                    if ($superSv) {
                        return $this->success($superSv);
                    }
                    $response = App::call($route);
                    if ($request->header('AC') == 'cr010505') {
                        return $response;
                    }

                    if ($iscreatedwebosocket) {
                        try {
                            $tmpdata = [
                                'time' => Carbon::now(),
                                'id' => $websocketid,
                                'status' => 0,
                                'processName' => 'Гүйлгээ амжилттай хийгдлээ.',
                                'responseCode' => ResponseCodeEnum::success,
                                'stage' => 2,
                            ];
                            event(new AdTxnMonitoringEvent($tmpdata, $instid));
                            event(new AdTxnMonitoringEvent($tmpdata, 1));
                        } catch (Exception $ex) {
                            Log::debug($ex);
                        }
                    }
                    if ($process->txntype == 2 || $process->txntype == 5) {
                        if (count($svUser) > 0) {
                            $response['svUser'] = $svUser;
                            $response['isPreview'] = 1;
                        }
                    }

                    if ($process->txntype == 2) {
                        if (isset($response['isPreview']) && @$response['isPreview'] != 1 || $code == 'tr909999') {
                            EBarimtJob::dispatch($request->header('AC'), $response, auth()->user())->onQueue("sendVAT");
                        }
                    } else {
                        if (isset($user) && isset($user->id)) {
                            $autojobService = new AdAutoJobService();
                            $autojobService->checkAutoJobActionCode($request->header('AC'), $user, $user);
                        }
                    }

                    return $this->success($response);
                } catch (MeException $ex) {
                    $iserror = true;
                    if (
                        $ex->getCode() == 'RC000003'
                    ) {
                        Log::debug($ex);
                    }

                    throw $ex;
                } catch (ValidationException $ex) {
                    $iserror = true;
                    $exception = $ex;
                    Log::debug('ValidationException in process request', [
                        'AC' => $request->header('AC'),
                        'route' => $route,
                        'errors' => $ex->validator->errors()->toArray(),
                        'payload_keys' => array_keys($request->all()),
                        'payload_summary' => $this->validationPayloadSummary($request),
                    ]);
                    Log::debug($ex);
                    $this->validationToMeException($ex->validator->errors()->all(), $ex->validator->errors()->keys());
                } catch (QueryException $ex) {
                    Log::error('QueryException');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    if ($ex->getPrevious() instanceof PDOException) {
                        if ($ex->getCode() == '23505') {
                            throw new MeException("RC000060");
                        }
                    }

                    Log::channel('slack')->critical($ex);
                    throw new MeException("RC000012");
                } catch (Exception $ex) {
                    Log::error('Exception');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    // Log::channel('slack')->critical($ex);
                    throw new MeException("RC000003");
                } catch (Error $ex) {
                    Log::error('Error');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    // Log::channel('slack')->critical($ex);
                    throw new MeException("RC000003");
                } catch (TypeError $ex) {
                    Log::error('TypeError');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    // Log::channel('slack')->critical($ex);
                    throw new MeException("RC000003");
                } finally {
                    if (!empty($exception)) {
                        $this->storeErrorLog($exception);
                    }

                    if ($iserror && $iscreatedwebosocket) {
                        try {
                            $tmpdata = [
                                'time' => Carbon::now(),
                                'id' => $websocketid,
                                'status' => 0,
                                'processName' => $exception ? $exception->getMessage() : 'Алдаа гарлаа',
                                'responseCode' => ResponseCodeEnum::sys_error,
                                'stage' => 2,
                            ];
                            event(new AdTxnMonitoringEvent($tmpdata, $instid));
                            event(new AdTxnMonitoringEvent($tmpdata, 1));
                        } catch (Exception $ex) {
                            Log::debug($ex);
                        }
                    }
                }
            } else {
                throw new MeException("RC000002", ['proc_code' => $code]);
            }
        } else {
            throw new MeException("RC000001");
        }
    }

    private function validationPayloadSummary(Request $request)
    {
        $summary = [];
        foreach (['filters', 'orders', 'functions'] as $key) {
            if ($request->has($key)) {
                $summary[$key] = $this->summarizeArrayInput($request->input($key));
            }
        }
        foreach (['page', 'perPage'] as $key) {
            if ($request->has($key)) {
                $summary[$key] = $request->input($key);
            }
        }
        return $summary;
    }

    private function summarizeArrayInput($value)
    {
        if (!is_array($value)) {
            return [
                'type' => gettype($value),
                'value_length' => is_string($value) ? mb_strlen($value) : null,
            ];
        }

        return array_map(function ($item) {
            if (!is_array($item)) {
                return [
                    'type' => gettype($item),
                    'value_length' => is_string($item) ? mb_strlen($item) : null,
                ];
            }

            $summary = [];
            foreach (['field', 'cond', 'dir'] as $key) {
                if (array_key_exists($key, $item)) {
                    $summary[$key] = $item[$key];
                }
            }
            if (array_key_exists('value', $item)) {
                $summary['value_type'] = gettype($item['value']);
                $summary['value_length'] = is_string($item['value']) ? mb_strlen($item['value']) : null;
                $summary['value_empty'] = empty($item['value']);
            }
            return $summary;
        }, $value);
    }

    /**
     * checkActionCode
     *
     * @param  string $ACTION_CODE
     * @param  int $userid
     * @return boolean
     */
    public static function checkActionCode($ACTION_CODE, $userid = null)
    {
        if (NotAuthActionCodesEnum::isValidValue($ACTION_CODE)) {
            return true;
        }
        if (empty($userid)) {
            $user = auth()->user();
        } else {
            $user = GpInstUser::where('id', $userid)->first();
        }
        $instperm = VwGpInstPerm::where('instid', $user->instid)
            ->where('ACTION_CODE', $ACTION_CODE)
            ->where('statusid', 1)->first();
        if ($instperm) {
            $key = CacheGroupEnum::user_role . "_" . $ACTION_CODE;
            // $AC = Cache::rememberForever(
            //     $key,
            //     function () use ($ACTION_CODE, $key, $user) {
            //         CoreService::storeCacheKey($user->instid, $key, CacheGroupEnum::user_role);
            //         return GpInstRolePerms::where('AC', $ACTION_CODE)
            //             ->whereIn('roleid', function ($query) {
            //                 $query->select('roleid')
            //                     ->from(with(new GpInstUserRole)->getTable())
            //                     ->where('statusid', '1')
            //                     ->where('userid', auth()->user()->id)
            //                     ->where('startdate', '<=', getNow())
            //                     ->where('enddate', '>=', getNow());
            //             })->where('statusid', 1)->first();
            //     }
            // );
            $AC = GpInstRolePerms::where('AC', $ACTION_CODE)
                ->whereIn('roleid', function ($query) use ($user) {
                    $txndate = CoreService::getTxnDate($user->instid);
                    // $txndate = getNow();
                    $query->select('roleid')
                        ->from(with(new GpInstUserRole)->getTable())
                        ->where('statusid', '1')
                        ->where('userid', $user->id)
                        ->where('startdate', '<=', $txndate)
                        ->where('enddate', '>=', $txndate);
                })->where('statusid', 1)->first();
            if ($AC) {
                return true;
            }
        }
        return false;
    }

    public static function getActionCodeDetail($AC)
    {
        $code = Str::lower($AC);
        // // Cache::forget('ActionCode_' . $code);
        // $key = 'ActionCode_' . $code;
        // $process = Cache::rememberForever(
        //     $key,
        //     function () use ($code, $key) {
        //         CoreService::storeCacheKey(1, $key);
        //         return GpActionCode::where('ACTION_CODE', $code)
        //             ->where('statusid', 1)->first();
        //     }
        // );

        return GpActionCode::where('ACTION_CODE', $code)
            ->where('statusid', 1)->first();
    }

    public function cyrillic2latin(Request $request)
    {
        $validate = $this->validate($request, [
            'name' => 'nullable'
        ]);
        $res = cyrillic2latin(@$validate['name']);
        return $res;
    }

    public function gp092000(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }
        $user = auth()->user();
        $query = GpDbBackupLog::where('statusid', '!=', -1);
        if ($user->isadmin != 1) {
            $query = $query->where('instid', $user->instid);
        }
        $data = $query->get();
        if ($user->isadmin == 1) {
            $data = json_decode(json_encode($data), true);
            $isprod = config("app.isprod");
            $resp = $this->sendBackup('fc1000', [
                'type' => $isprod ? 'BACKUP_MECORE' : 'BACKUP_MECORE_TEST'
            ]);
            $resp1 = $this->sendBackup('fc1000', [
                'type' => $isprod ? 'BACKUP_MELP' : 'BACKUP_TEST'
            ]);
            $resp = array_merge($resp, $resp1);
            $data = array_merge($resp, $data);
            $data = collect($data)->sortByDesc('created_at')->values()->all();
        }
        return $data;
    }

    public function sendBackup($AC, $data)
    {
        $user = auth()->user();
        $conn = GpConnConf::where('code', 'BACKUP')
            ->where('instid', $user->instid)
            ->where('statusid', 1)
            ->first();
        if ($conn) {
            $config = json_decode($conn->config, true);
            $response = Http::withHeaders([
                'AC' => $AC,
                'Content-Type' => 'application/json',
            ])->withOptions(['verify' => false])
                ->post($config['url'], $data);
            if ($response->failed()) {
                Log::error([$response->json(), $response->status()]);
            }
            if ($response->status() == 200) {
                if ($response->json()['response_code'] == 'SR0000') {
                    return $response->json()['response'];
                }
            }
            $this->error('Системд алдаа гарлаа.');
        } else {
            return array();
        }
    }

    public function gp092100()
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }

        $eodstatus = 2;
        $eodison = false;
        if (!$this->isOnEodJob(true)) {
            $eodstatus = 1;
        } else {
            $eodison = true;
        }
        $information = [];
        $information[] = $this->getDiskInformation();
        $resp1 = $this->sendBackup('fc1002', []);
        if (!empty($resp1)) {
            $information[] = $resp1;
        }

        return [
            'isworking' => $eodison ? 1 : 0,
            'status' => $eodstatus,
            'information' => $information,
        ];
    }

    public function gp092200()
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }

        $conn = GpConnConf::where('code', 'BACKUP')
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->first();
        if ($conn) {
            $isprod = config("app.isprod");
            $this->sendBackup('fc1003', [
                'type' => $isprod ? 'BACKUP_MECORE' : 'BACKUP_MECORE_TEST'
            ]);
        } else {
            if ($this->isOnEodJob()) {
                $this->error('RC000181');
            }
            $backup = GpDbBackupLog::create([
                'path' => '/temp',
                'time' => 0,
                'size' => 0,
                'errordesc' => '',
                'statusid' => 0,
                'instid' => auth()->user()->instid,
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
            ]);
            DbBackupProcessJob::dispatch(
                auth()->user()->id,
                auth()->user()->instid,
                $backup->id
            )->onQueue('DbBackupJob');
        }
    }

    public function gp092400(Request $request)
    {
        if (!isAdmin()) {
            $this->error('RC000026');
        }

        $validated = $this->validate($request, [
            'id' => 'required',
            'path' => 'required_if:id,0',
        ]);

        if ($validated['id'] == 0 && auth()->user()->isadmin == 1) {
            $this->sendBackup('fc1001', ['path' => $validated['path']]);
        } else {
            if (auth()->user()->isadmin == 1) {
                $backup = GpDbBackupLog::where('id', $validated['id'])->first();
            } else {
                $backup = GpDbBackupLog::where('id', $validated['id'])
                    ->where('instid', auth()->user()->instid)->first();
            }

            if ($backup) {
                if (File::exists($backup->path)) {
                    File::delete($backup->path);
                }
                $backup->statusid = -1;
                $backup->save();
            } else {
                $this->error('RC000010', $validated);
            }
        }
    }

    public function isOnEodJob($isstatus = false)
    {
        $jobInspector = app(\App\Services\QueueJobInspector::class);

        if (!$isstatus) {
            return $jobInspector->has('DbBackupJob');
        }

        return $jobInspector->has('DbBackupJob', DbBackupProcessJob::class, auth()->user()->instid);
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        return response('Таны хүсэлт амжилттай биелэгдлээ. :)');
    }

    function getDiskInformation()
    {
        $serverName = gethostname(); // Get the server name

        // Get all drives dynamically using glob() for Windows systems
        $drives = [];
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            foreach (range('A', 'Z') as $letter) {
                $drive = $letter . ':\\';
                if (is_dir($drive)) {
                    $drives[] = $drive;
                }
            }
        } else {
            // For Unix-based systems, just check root '/'
            $drives[] = '/';
        }

        $diskInfo = [];

        foreach ($drives as $drive) {
            // Check if the drive path exists before fetching its information
            if (File::exists($drive)) {
                $totalSpace = disk_total_space($drive);
                $freeSpace = disk_free_space($drive);
                $usedSpace = $totalSpace - $freeSpace;

                $diskInfo[] = [
                    'drive' => $drive,
                    'total_space' => round($totalSpace / 1073741824, 2) . ' GB', // Convert to GB
                    'free_space' => round($freeSpace / 1073741824, 2) . ' GB',
                    'used_space' => round($usedSpace / 1073741824, 2) . ' GB',
                ];
            }
        }

        return [
            'server_name' => $serverName,
            'disks' => $diskInfo,
        ];
    }

    public function getServerInfo()
    {
        $filePath = storage_path('logs/laravel-' . Carbon::today()->format('Y-m-d') . '.log');
        $logFileSize = '0 KB';
        if (file_exists($filePath)) {
            $fileSize = filesize($filePath); // Get the file size in bytes
            $fileSizeMb = round($fileSize / 1048576, 2); // MB
            $logFileSize = "{$fileSizeMb} MB";
        }

        $jobCount = app(\App\Services\QueueJobInspector::class)->count();
        $failedJob = null;

        try {
            $failedJob = DB::connection(config('queue.failed.database'))
                ->table(config('queue.failed.table', 'failed_jobs'))
                ->latest('failed_at')
                ->first();
        } catch (\Throwable $th) {
            Log::debug($th);
        }

        return response(":fiba: \nLog File Size: $logFileSize\nJob Count: " . $jobCount .
            "\nFailed Job Queue: " . ($failedJob->queue ?? 'None'));
    }

    public function checkSv($process, $request, &$ispreview, &$svUser)
    {
        $user = auth()->user();
        if (in_array($process->txntype, [2, 5, 3])) {
            $allowsv = true;
            if ($process->txntype == 3) {
                $allowsv = $this->checkActionCode('tr010301', $user->id);
            }

            if ($allowsv) {
                $validate = $request->validate([
                    'ispreview' => 'nullable',
                    'svuserid' => 'nullable',
                    'senddata' => 'required_with:svuserid',
                    'svtype' => 'required_with:svuserid',
                    'jrno' => 'nullable'
                ]);
                if (isset($validate['svuserid']) && !empty($validate['svuserid'])) {
                    return $this->createPendingTxn($process, $user, $validate);
                } else if (($validate['ispreview'] ?? 0) != 1 || $process->txntype == 3) {
                    return $this->handleCheckSupervisor($process, $user, $request, $validate, $ispreview, $svUser);
                } else {
                    $ispreview = 1;
                }
            }
        }
    }

    private function createPendingTxn($process, $user, $validate)
    {
        $trjournal = new TrJournalController();
        $trpendtxn = $trjournal->supervisorPendingTxn(
            $validate['senddata'],
            $validate['svtype'],
            $validate['svuserid']
        );

        try {
            $validate['senddata']['pendtxnid'] = $trpendtxn->id;
            $validate['senddata']['txncode'] = "{$process->ACTION_CODE} - {$process->name}";
            $validate['senddata']['description'] =
                "($user->id - $user->name) теллер {$validate['senddata']['jrno']} дугаартай гүйлгээний зөвшөөрөл хүлээж байна.";

            $tmpdata = [
                'id' => $trpendtxn->id,
                'senddata' => $validate['senddata']
            ];

            // Мэдэгдэл илгээх
            $notif = new AdNotificationsController();
            $notification = [
                "title" => "{$validate['senddata']['jrno']} хүлээгдэж буй гүйлгээ бүртгэгдлээ.",
                "description" => $validate['senddata']['description'],
                "is_all" => 0,
                "notiftype" => "WEB",
                "usetemp" => 0,
                "reportActionCode" => 0,
                "execfreq" => 1,
                "autojobid" => 0,
                "users" => [
                    [
                        'custid' => $validate['svuserid'],
                        "type" => "ADMIN"
                    ]
                ],
                'url' => '/menu/tr/tr010400'
            ];

            $notif->sendNotif($notification);
            event(new AdSupervisorEvent($tmpdata, $validate['svuserid']));
        } catch (Exception $ex) {
            Log::error("Supervisor PendingTxn Error: " . $ex->getMessage());
        }

        return [
            'pendTxnId' => $trpendtxn->id,
            'txnJrno' => $validate['senddata']['jrno']
        ];
    }

    private function handleCheckSupervisor($process, $user, $request, $validate, &$ispreview, &$svUser)
    {
        $checksv = true;

        // 1. Хэрэв jrno байгаа бол өмнө хянагдсан эсэхийг шалгах
        if (!empty($validate['jrno'])) {
            $svcount = AdSvUser::where('userid', $user->id)
                ->where('instid', $user->instid)
                ->where('statusid', 1)->count();

            if ($svcount > 0) {
                $trpndtxn = TrPendTxn::where('jrno', $validate['jrno'])
                    ->where('instid', $user->instid)
                    ->where('inittellerno', $user->id)
                    ->where('statusid', 9)
                    ->first();

                if (!empty($trpndtxn)) {
                    if ($process->txntype != 3) {
                        $request->merge([
                            'jrno' => $validate['jrno'],
                            'svtellerno' => $trpndtxn->accepttellerno,
                        ]);
                    }
                    $checksv = false;
                }
            } else {
                $checksv = false;
            }
        }
        // 2. Хэрэв хянах шаардлагатай бол supervisor жагсаалтыг авна
        if ($checksv) {
            $svtype = isset($validate['svtype']) && $validate['svtype'] == 0 ? 0 : 1;
            if ($process->txntype != 3) {
                $svUser = VwAdSvUser::select(['id', 'statusid', 'svtype', 'svuserid', 'svuserid_name'])
                    ->where('userid', $user->id)
                    ->where('svtype', $svtype)
                    ->where('statusid', 1)
                    ->get();

                if ($svUser->count() > 0) {
                    $request->merge(['ispreview' => 1]);
                    if (GpController::checkActionCode('tr010301', $user->id)) {
                        $request->merge(['ispreviewfee' => 1]);
                    }
                    $ispreview = 1;
                } else {
                    $this->handleNoSupervisor($svtype, $user);
                }
            } else {
                $svUser = VwAdSvUser::select([
                    'id',
                    'statusid',
                    'svtype',
                    'svuserid',
                    'svuserid_name',
                ])->where('userid', auth()->user()->id)
                    // ->where('svtype', $svtype)
                    ->where('statusid', 1)->get();

                if (count($svUser) > 0) {
                    return [
                        'svUser' => $svUser,
                        'txnJrno' => CoreService::getNextJrno()
                    ];
                } else {
                    $this->handleNoSupervisor($svtype, $user);
                }
            }
        }
    }

    private function handleNoSupervisor($svtype, $user)
    {
        if ($svtype == 0) {
            $this->error("Хяналт хийх теллер бүртгэлгүй байна.");
        }

        $svcount = AdSvUser::where('userid', $user->id)
            ->where('instid', $user->instid)
            ->where('statusid', 1)->count();

        if ($svcount > 0) {
            $this->error("Танд гүйлгээнд хянуулах тохиргоо хийгдсэн байна.");
        }
    }
}
