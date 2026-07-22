<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Modules\Ap\Entities\ApCustUser;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Ap\Entities\Views\VwApCustUserList;
use Illuminate\Support\Str;
use Modules\Ad\Http\Services\AdNotificationService;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Http\Services\InstCustConnService;
use Modules\Ap\Http\Services\PolarisApiRequestService;
use Modules\Cr\Entities\CrCustInd;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GPResponseMsg;
use Modules\Gp\Entities\GPUserAccessToken;
use Modules\Gp\Entities\Views\VwGPProviderConf;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Http\Services\GoogleAuthenticator\GoogleAuthenticator;
use Modules\Gp\Jobs\SendMailJob;

class ApCustomerUserController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function ap010001(Request $request)
    {
        return $this->getGridData(
            $request,
            VwApCustUserList::where('statusid', 1)
                ->where('instid', auth()->user()->instid),
                // ->latest('created_at'),
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }


    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function ap010101(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $GPinst = ApCustUser::select(
            'id',
            'email',
            'firstname',
            'lastname',
            'regno',
            'phone',
            'statusid',
            'created_at',
            'created_by',
            'updated_at',
            'updated_by',
        )->where('id', $validate['id'])
            ->where('statusid', '!=', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function ap010201(Request $request)
    {
        $validated = $this->validateMe($request, [
            'regno' => 'required',
            'email' => 'required|email',
            'phone' => 'required|max:60',
            'firstname' => 'required|max:50',
            'lastname' => 'nullable|max:50',
            'address' => 'nullable|max:100',
            'region' => 'nullable',
            'subregion' => 'nullable',
        ], [
            'regno.required' => ResponseCodeEnum::required,
            'phone.required' => ResponseCodeEnum::required,
            'email.required' => ResponseCodeEnum::required,
            'email.email' => ResponseCodeEnum::email,
            'firstname.required' => ResponseCodeEnum::required,
            'firstname.max' => ResponseCodeEnum::max,
            'lastname.max' => ResponseCodeEnum::max,
            'address.max' => ResponseCodeEnum::max,
        ]);

        $user = auth()->user();

        $apUser = VwApCustUserList::where('instid', auth()->user()->instid)
            ->where('regno', $validated['regno'])
            ->where('statusid', 1)->first();
        if ($apUser) {
            $this->error("RC000175", [
                'id' => $apUser->regno
            ]);
        }
        //Шинээр хэрэглэгч суурь системээс лявлаж байвал бүртгэх үйлдэл хийнэ.
        $validated['instid'] = auth()->user()->instid;
        $validated['regno'] = mb_strtoupper($validated['regno']);
        $validated['email'] = Str::lower($validated['email']);
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;

        $cust = ApCustomer::where('instid', $validated['instid'])
            ->where('regno', $validated['regno'])->first();
        if (!$cust) {
            $this->error('RC000176');
        }

        $userExist = ApCustUser::where('regno', $validated['regno'])
            ->where('statusid', '<>', '-1')->first();
        $instConnCust = new InstCustConnService();
        if ($userExist) {
            // $request['userid'] = $userExist->id;
            $request->merge([
                'id' => $userExist->id,
                'phone' => $userExist->phone,
                'email' => $validated['email'],
            ]);
            $user = $this->ap010301($request);
            $instConnCust->connect($validated['instid'], $userExist->id);
            $instConnCust->getCustAccounts($cust->cif, $validated['instid']);
            return $user;
        } else {
            $userExist = ApCustUser::where('email', $request['email'])
                ->where('statusid', '<>', '-1')->first();

            if ($userExist) {
                $this->error('VC000016');
            }

            $googleAuth = new GoogleAuthenticator();
            $random_password = '#@1' . Str::random(8);
            DB::beginTransaction();
            try {
                $user = new ApCustUser();
                $validated = array_change_key_case($validated);
                foreach ($user->getFillable() as $field) {
                    if (array_key_exists($field, $validated)) {
                        $user->$field = $validated[$field];
                    }
                }

                $app_id = null;
                $provider = VwGPProviderConf::where('code', '2')->where('instid', $validated['instid'])->first();
                if (isset($provider)) {
                    $providerConfig = json_decode($provider->config, true);

                    if (isset($providerConfig['app_id'])) {
                        $app_id = $providerConfig['app_id'];
                    }
                }

                $user->app_id = $app_id;
                $user->google_auth_key = $googleAuth->generateSecret();
                $user->statusid = 0;
                $user->mustchGPss = '1';
                $user->passtoken = rand(100000, 999999);
                $user->password = Hash::make(meapp_hmac($random_password));
                $user->created_by = auth()->user() ? auth()->user()->id : 1;
                $user->save();
                $data = array();
                $data['random_password'] = $random_password;

                $data['hostname'] = config('app.frontoffice_url') . "/mobile-api/cust-user";
                $data['firstname'] = $user->firstname;
                // MailService::sendMail($user->email, 'mailRegister', $data, 'Бүртгэл амжилттай хийгдлээ.');

                $email = [
                    "to" => $user->email,
                    "subject" => "Бүртгэл амжилттай хийгдлээ.",
                    "data" => $data,
                    "template" => "ap::mail.mailRegister"
                ];
                $instConnCust->connect($validated['instid'], $user->id);
                dispatch(new SendMailJob($email));
                DB::commit();
                $instConnCust->getCustAccounts($cust->cif, $validated['instid']);
                return mask_email($user->email) . ' цахим хаягт баталгаажуулах линк илгээсэн. Бүртгэл баталгаажсны дараа системд нэвтрэхийг анхаарна уу.';
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        }
        // GPInstCurPair::create($validated);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function ap010301(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
            'phone'  => 'required',
            'email' => 'required|email',
        ], [
            'id.required' => ResponseCodeEnum::required,
            'phone.required' => ResponseCodeEnum::required,
            'email.required' => ResponseCodeEnum::required,
        ]);
        $apUser = ApCustUser::where('statusid', '!=', -1)->find($validated['id']);
        if (empty($apUser)) {
            $this->error("RC000010",  ['id' => $validated['id']]);
        }
        $is_change_mail = false;
        $email = Str::lower($validated['email']);
        if ($email != $apUser->email) {
            $chckUser = ApCustUser::where('email', $email)
                ->where('statusid', '<>', '-1')->first();
            if ($chckUser) {
                $this->error('RC000086', ['field' => $email]);
            }
            $is_change_mail = true;
            $this->changeMail($apUser, $email, false);
        }

        // $apUser->email = $email;
        $apUser->phone = $validated['phone'];
        $apUser->updated_by = auth()->user()->id;
        $apUser->save();

        if ($is_change_mail) {
            $response = GPResponseMsg::where('code', 'RC000177')->first();
            return $response->name;
        }
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function ap010401(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000017",
        ]);
        $user = auth()->user();
        $dtl = VwApCustUserList::where('id', $validated['id'])
            ->where('instid', $user->instid)
            ->where('statusid', 1)->first();
        if (empty($dtl)) {
            $this->error("RC000010",  $validated['id']);
        }

        $count = ApInstCustUserLink::where('instid',  $user->instid)
            ->where('cust_userid', $dtl->id)->where('statusid', '<>', 1)->count();

        $link = ApInstCustUserLink::where('instid',  $user->instid)
            ->where('cust_userid', $dtl->id)
            ->where('statusid', 1)->update([
                'statusid' =>  $count ? ($count + 1) * -1 : -1,
                'updated_by' =>  $user->id,
            ]);

        return $link;
    }

    public function ap010501(Request $request)
    {
        $validated = $this->validate($request, [
            'regno' => 'required',
            'custtypecode' => 'nullable|in:0,1',
        ], [
            'regno.required' => ResponseCodeEnum::required,
            'custtypecode.in' => ResponseCodeEnum::numeric,
        ]);

        $validated['regno'] = Str::upper($validated['regno']);
        $user = auth()->user();
        $instConnCust = new InstCustConnService();
        $cust = ApCustomer::where('instid', $user->instid)
            ->where('regno', $validated['regno'])->first();

        if (!$cust) {
            $cust_resp = $instConnCust->createCustomerInfo($validated['regno'], $validated['custtypecode']);
        }

        $userExist = ApCustUser::where('regno', $validated['regno'])
            ->where('statusid', '!=', -1)->first();

        if ($userExist) {
            if ($instConnCust->isConnect($user->instid, $userExist->id)) {
                $this->error('RC000028');
            }
            if (empty($cust->email)) {
                $cust->email = $userExist->email;
            }
            return [
                'data' => $userExist,
                'cust' => $cust ?? $cust_resp
            ];
        } else {
            return [
                'data' => null,
                'cust' => $cust ?? $cust_resp
            ];
        }
    }

    public function changeMail($user, $newemail, $isown)
    {
        $data = array();
        $auser = auth()->user();
        $data['hostname'] = config('app.frontoffice_url');
        $data['isown'] = $isown;
        $data['newemail'] = $newemail;
        $data['oldemail'] = $user->email;
        if (!$isown) {
            $inst = GPInstList::where('id', $auser->instid)->first();
            if ($inst) {
                $data['instname'] = $inst->instname;
                $data['phone'] = $inst->phone;
            } else {
                throw new MeException('Энэ үйлдлийг хийх эрхгүй байна.');
            }
        }
        $token = sha1(mt_rand(1, 90000)) . sha1(mt_rand(1, 90000));
        $data['token'] = $token;
        $tmpdata = [
            'userid' => $user->id,
            'newemail' => $data['newemail'],
            'oldemail' => $data['oldemail'],
        ];
        GPUserAccessToken::create([
            'userid' => 0,
            'name' => 'change mail',
            'token' => $token,
            'abilities' => json_encode($tmpdata, JSON_UNESCAPED_UNICODE),
            'last_used_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => null,
            'channel' => 'APP'
        ]);

        $old_email = [
            "to" => $user->email,
            "subject" => "ME апп систем. Имэйл хаяг солих хүсэлт",
            "data" => $data,
            "template" => "ap::mail.changeMailNotif"
        ];
        dispatch(new SendMailJob($old_email));

        $new_email = [
            "to" => $newemail,
            "subject" => "ME апп систем. Имэйл хаяг солих хүсэлт",
            "data" => $data,
            "template" => "ap::mail.changeMailConfirm"
        ];
        dispatch(new SendMailJob($new_email));

        if ($user->device_token) {
            $service = new AdNotificationService($auser->instid);
            $service->sendNotificationFirebase('И мэйл хаяг шинэчлэх хүсэлт', 'Таны бүртгэлтэй мэйл хаягийг шинэчлэх хүсэлт илгээсэн байна. ', [$user->device_token], $user->app_id);
        }
    }

    public function confirmMail(Request $request, $token)
    {
        $token_lifetime = 15;
        $host = config('app.frontoffice_url');
        $auth = GPUserAccessToken::where('channel', 'APP')
            ->where('name', 'change mail')
            ->where('token', $token)->first();
        if ($auth) {
            $lastused = new Carbon($auth->last_used_at);
            $now = new Carbon();
            if (isset($auth->abilities) && $now->diffInMinutes($lastused) < $token_lifetime) {
                $data = json_decode($auth->abilities, true);
                $user = ApCustUser::where('id', $data['userid'])->first();
                $user->email = $data['newemail'];
                $user->save();
                GPUserAccessToken::where('token', $token)->delete();
                return view('ap::pages.confirmSuccessEmail', compact('host'));
            }
        }
        return view('ap::pages.confirmErrorEmail', compact('host'));
    }
}
