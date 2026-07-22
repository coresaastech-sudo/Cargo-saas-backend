<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\Views\VwGpInstUser;
use Modules\Gp\Http\Requests\GpInstUserRequest;
use Illuminate\Support\Str;
use Modules\Ca\Entities\CaCashBal;
use Modules\Gp\Entities\GpUserActList;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Http\Services\GoogleAuthenticator\GoogleAuthenticator;
use Modules\Gp\Jobs\SendMailJob;

class GpInstUserController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $sql = VwGpInstUser::where('statusid', '<>', -1);
        if ($user->isadmin != 1) {
            $sql = $sql->where('instid', $user->instid);
            // $this->error('RC000026');
        }
        return $this->getGridData($request, $sql, [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstUserRequest $request)
    {
        $validated = $request->validated();
        // Нэвтрэх нэр давхардаж байгаа эсэхийг шалгах
        $validated['username'] = Str::lower($validated['username']);
        $GPuser = GpInstUser::where('username', $validated['username'])->where('statusid', '<>', -1)->first();
        if ($GPuser) {
            $this->error('VC000016');
        }
        $validated['statusid'] = 1;
        if (empty($validated['isadmin'])) {
            $validated['isadmin'] = 0;
        }
        $googleAuth = new GoogleAuthenticator();
        $validated['google_auth_key'] = $googleAuth->generateSecret();
        $validated['created_by'] = auth()->user()->id;
        $validated['updated_by'] = auth()->user()->id;
        $random_password = '#@1' . Str::random(8);

        $validated['password'] = Hash::make(CoreService::hmac($random_password));
        $token = generateRandomString(50);
        $validated['passtoken'] = $token;
        $validated['passtokendate'] = getNow();
        $validated['passtokenstatus'] = 1;
        $validated['created_at'] = getNow();
        GpInstUser::create($validated);

        $data = array();
        $data['token_life_time'] = "15";
        $data['token'] = $token;
        $data['url'] = config('app.backoffice_url');
        $data['username'] = $validated['username'];
        $email = [
            "to" => $validated['email'],
            "subject" => "Бүртгэл амжилттай хийгдлээ.",
            "data" => $data,
            "template" => "GP::emails.new-register"
        ];
        dispatch(new SendMailJob($email));
        return $validated['email'] . ' цахим хаягт нууц үг тохируулах мэдээлэл илгээсэн.';
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $GPinst = GpInstUser::where('id', $validated['id'])->where('statusid', '<>', -1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validated);
        }
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Response
     */
    public function update(GpInstUserRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        // Нэвтрэх нэр давхардаж байгаа эсэхийг шалгах
        $validated['username'] = Str::lower($validated['username']);
        $GPuser = GpInstUser::where('id', '!=', $validated['id'])
            ->where('username', $validated['username'])
            ->where('statusid', '<>', -1)->first();
        if ($GPuser) {
            $this->error('VC000016');
        }
        $validated['updated_by'] = auth()->user()->id;
        GpInstUser::where('id', $validated['id'])->where('statusid', '<>', -1)->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @AC gp020400
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);

        $cashbal = CaCashBal::where('userid', $validated['id'])
            ->where('bal', '!=', 0)
            ->where('statusid', 1)->first();

        if ($cashbal) {
            $this->error("RC000227", [
                'id' => $validated['id'],
                'acntno' => $cashbal->acntcode
            ]);
        }

        GpInstUser::where('id', $validated['id'])
            ->where('statusid', '<>', -1)->update([
                'statusid' => -1,
                'updated_by' => auth()->user()->id
            ]);
    }
}
