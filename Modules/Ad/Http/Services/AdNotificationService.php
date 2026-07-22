<?php

namespace Modules\Ad\Http\Services;

use DOMDocument;
use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdNotifications;
use Modules\Ad\Entities\AdSentNotification;
use Modules\Cr\Entities\CrCustNotifications;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Jobs\NotificationJob;
use Modules\Gp\Jobs\NotificationSendMailJob;
use Modules\Gp\Jobs\SmsJob;
use Modules\Re\Http\Services\ReportService;
use Modules\Gp\Entities\GpppList;
use Modules\Ap\Entities\ApCustUser;
use App\Exceptions\MeException;

class AdNotificationService
{

    public $providerConfig;
    public $provider;
    public $connection;
    private $token;
    protected $invoiceservice;

    public function __construct($instid)
    {
        $this->provider = VwGPProviderConf::where('code', '2')->where('instid', $instid)->first();
        if (isset($this->provider)) {
            $this->providerConfig = json_decode($this->provider->config, true);
        }
    }


    public function createMainNotif($data)
    {
        $notification = AdNotifications::create($data);
        return $notification;
    }

    public function sendNotification($notification, $user, $params)
    {
        try {
            if (auth()->user()->isadmin == 1)
                $instid = $user['instid'];
            else
                $instid = auth()->user()->instid;
            CrCustNotifications::create([
                'instid' => $instid ?? $notification['instid'], // user->instid bolgoh? (umnu ni shalgaad awchihwal bolno)
                'custid' => $user['custid'], // custid boloh bh ($user['id'])
                'notification_id' => $notification->id,
                'is_read' => 0,
                'created_at' => getNow(),
                // 'created_by' => $user ? $user->id : 1,
                'created_by' => auth()->user()->id,
                'custtype' => $user->type
            ]);

            $reportService = new ReportService();
            $inputs = [];
            foreach ($params as $key => $value) {
                array_push($inputs, ['input' => $key, 'value' => $value]);
            }

            $data = [
                'ACTION_CODE' => $notification->reportActionCode,
                'inputs' => $inputs,
            ];

            // Имэйл илгээх
            if ($notification->notiftype == "MAIL") {
                $body = "";
                if ($notification->usetemp == 1) {
                    $report = $reportService->generateReport($data, $notification['instid'], $user);
                    // $report = $reportService->generateReport($data, auth()->user()->instid, $user);
                    $body = $report['source'];
                } else {
                    $body = replace_param_string($notification->description, $params);
                }

                $email = [
                    "to" => $user->email,
                    "subject" => $notification->title,
                    "template" => $body,
                ];
                $fromEmail = "";
                if (isset($this->providerConfig['fromEmail'])) {
                    $fromEmail = $this->providerConfig['fromEmail'];
                }

                $fromName = "";
                if (isset($this->providerConfig['fromName'])) {
                    $fromName = $this->providerConfig['fromName'];
                }

                AdSentNotification::create([
                    'reciever' => $user->email,
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'type' => $notification->notiftype,
                    'body' => $body,
                    'statusid' => 1,
                    'instid' => $notification['instid'],
                    'created_by' => $user['custid'] ?? $user['id'],
                    'created_at' => getNow(),
                ]);

                NotificationSendMailJob::dispatch($email, $fromEmail, $fromName)->onQueue('sendNotification');
            }

            // PUSH мэдэгдэл илгээх
            else if ($notification->notiftype == "PUSH") {
                if ($notification->usetemp == 1) {
                    $report = $reportService->generateReport($data, $notification['instid'], $user);
                    // $report = $reportService->generateReport($data, auth()->user()->instid, $user);
                    $body = $report['source'];
                    $body = strip_tags($body);
                    $body = htmlspecialchars_decode($body);
                } else {
                    $body = replace_param_string($notification->description, $params);
                }

                $tokens[] = $user['device_token'];
                $appUser = ApCustUser::where('id', $user['custid'])->where('statusid', '!=', -1)->first();

                NotificationJob::dispatch($notification->title, $body, $tokens, $notification['instid'], $appUser['app_id'])->onQueue('sendNotification');
            }

            // SMS мэдэгдэл илгээх
            else if ($notification->notiftype == "SMS") {
                $number = $user->phone;
                if ($notification->usetemp == 1) {
                    $report = $reportService->generateReport($data, $user->instid, $user);
                    // $report = $reportService->generateReport($data, auth()->user()->instid, $user);
                    $body = $report['source'];
                    $body = strip_tags($body);
                    $body = htmlspecialchars_decode($body);
                } else {
                    $body = replace_param_string($notification->description, $params);
                }

                $sentNotification = AdSentNotification::create([
                    'reciever' => $user->phone,
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'type' => $notification->notiftype,
                    'body' => $body,
                    'statusid' => 0,
                    'instid' => $notification['instid'],
                    'created_by' => $user['custid'] ?? $user['id'],
                    'created_at' => getNow(),
                ]);
                SmsJob::dispatch($number, $body, $notification['instid'], $sentNotification->id)->onQueue('sendNotification');
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public function sendAutoJob($autojobid, $user, $params)
    {
        $notifications = AdNotifications::where('autojobid', $autojobid)
            ->where('instid', $user->instid)->where('statusid', '<>', -1)->get();
        $reportService = new ReportService();
        foreach ($notifications as $notification) {
            $inputs = [];
            foreach ($params as $key => $value) {
                array_push($inputs, ['input' => $key, 'value' => $value]);
            }

            $data = [
                'ACTION_CODE' => $notification->reportActionCode,
                'inputs' => $inputs,
            ];

            // Имэйл илгээх
            if ($notification->notiftype == "MAIL") {
                $body = null;
                if ($notification->usetemp == 1) {
                    $report = $reportService->generateReport($data, $user->instid, $user);
                    // $report = $reportService->generateReport($data, auth()->user()->instid, $user);

                    if (!empty($report)) {
                        $body = $report['source'];
                    }
                } else {
                    $body = replace_param_string($notification->description, $params);
                }

                if (!empty($body)) {
                    $email = [
                        "to" => $user->email,
                        "subject" => $notification->title,
                        "template" => $body,
                    ];

                    $fromEmail = "";
                    if (isset($this->providerConfig['fromEmail'])) {
                        $fromEmail = $this->providerConfig['fromEmail'];
                    }

                    $fromName = "";
                    if (isset($this->providerConfig['fromName'])) {
                        $fromName = $this->providerConfig['fromName'];
                    }

                    $body = preg_replace('/<p class="hide">.*?<\/p>/', '<p class="hide"></p>', $body);

                    AdSentNotification::create([
                        'reciever' => $user->email,
                        'title' => $notification->title,
                        'description' => $notification->description,
                        'type' => $notification->notiftype,
                        'body' => $body,
                        'statusid' => 1,
                        'instid' => $notification['instid'],
                        'created_by' => $user['custid'] ?? $user['id'],
                        'created_at' => getNow(),
                    ]);

                    NotificationSendMailJob::dispatch($email, $fromEmail, $fromName)->onQueue('sendNotification');
                }
            }

            // PUSH мэдэгдэл илгээх
            if ($notification->notiftype == "PUSH") {
                if ($notification->usetemp == 1) {
                    $report = $reportService->generateReport($data, $user->instid, $user);
                    // $report = $reportService->generateReport($data, auth()->user()->instid, $user);
                    $body = $report['source'];
                    $body = strip_tags($body);
                    $body = htmlspecialchars_decode($body);
                } else {
                    $body = replace_param_string($notification->description, $params);
                }

                $tokens[] = $user['device_token'];
                $appUser = ApCustUser::where('id', $user['custid'])->where('statusid', '!=', -1)->first();

                NotificationJob::dispatch($notification->title, $body, $tokens, $notification['instid'], $appUser['app_id'])->onQueue('sendNotification');
            }

            // SMS мэдэгдэл илгээх
            if ($notification->notiftype == "SMS") {
                $number = $user->phone;
                if ($notification->usetemp == 1) {
                    $report = $reportService->generateReport($data, $user->instid, $user);
                    // $report = $reportService->generateReport($data, auth()->user()->instid, $user);
                    $body = $report['source'];
                    $body = strip_tags($body);
                    $body = htmlspecialchars_decode($body);
                } else {
                    $body = replace_param_string($notification->description, $params);
                }

                $sentNotification = AdSentNotification::create([
                    'reciever' => $user->phone,
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'type' => $notification->notiftype,
                    'body' => $body,
                    'statusid' => 0,
                    'instid' => $notification['instid'],
                    'created_by' => $user['custid'] ?? $user['id'],
                    'created_at' => getNow(),
                ]);

                SmsJob::dispatch($number, $body, $notification['instid'], $sentNotification->id)->onQueue('sendNotification');
            }
        }
    }

    public function sendNotificationFirebase($title, $message, $firebaseToken, $appid)
    {

        foreach ($firebaseToken as $token) {
            $notifications[] = [
                'message' => [
                    'token' => $token,
                    "notification" => [
                        "title" => $title,
                        "body" => $message,
                    ],
                ]
            ];
        }

        $this->notification($notifications, $appid);
    }



    function notification($notifications, $appid)
    {
        $app = GpppList::where('id', $appid)->where('statusid', 1)->first();
        $appData = json_decode($app->app_data, true);
        $firebaseJson = $appData['notif_config'] ?? null;
        if (!$firebaseJson) {
            throw new MeException('RC000172');
        }
        $firebaseArray = is_string($firebaseJson) ? json_decode($firebaseJson, true) : $firebaseJson;
        $projectId = $firebaseArray['project_id'] ?? null;
        if (!$projectId) {
            throw new MeException('RC000172');
        }
        // $credentialsFilePath = config('app.firebase_filepath');
        $client = new \Google_Client();
        // $client->setAuthConfig($credentialsFilePath);
        $client->setAuthConfig($firebaseArray);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $client->fetchAccessTokenWithAssertion();
        $token = $client->getAccessToken();
        $access_token = $token['access_token'];

        $headers = array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        );

        $multiCurl = array();
        $mh = curl_multi_init();
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, 2);

        foreach ($notifications as $i => $notification) {
            $multiCurl[$i] = curl_init();
            curl_setopt($multiCurl[$i], CURLOPT_URL, $url);
            curl_setopt($multiCurl[$i], CURLOPT_HTTPHEADER, $headers);
            curl_setopt($multiCurl[$i], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($multiCurl[$i], CURLOPT_POST, true);
            curl_setopt($multiCurl[$i], CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($multiCurl[$i], CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($multiCurl[$i], CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($multiCurl[$i], CURLOPT_POSTFIELDS, json_encode($notification, JSON_UNESCAPED_UNICODE));
            curl_multi_add_handle($mh, $multiCurl[$i]);
        }

        $index = null;
        do {
            curl_multi_exec($mh, $index);
            curl_multi_select($mh);
        } while ($index > 0);

        foreach ($multiCurl as $k => $ch) {
            $result[$k] = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);
        }
        curl_multi_close($mh);
    }
}
