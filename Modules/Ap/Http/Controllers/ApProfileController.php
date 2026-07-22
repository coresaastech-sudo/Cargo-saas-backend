<?php

namespace Modules\Ap\Http\Controllers;

use App\Exceptions\MeException;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\QueryException;
use Modules\Ap\Entities\ApCustUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Ad\Entities\AdLoginActivityLog;
use Modules\Ap\Entities\ApCustomer;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApProfileController extends Controller
{
    /**
     * oi000100 - "Хэрэглэгчийн профайл"
     *
     * @return void
     */
    public function oi000100()
    {
        $user = auth()->user();
        $user = ApCustUser::where('id', $user->id)->where('statusid', '<>', '-1')->first();
        if (!empty($user->photo_url)) {
            try {
                $user['photo_base64'] = base64_encode(file_get_contents(config('app.url') . $user->photo_url));
            } catch (Exception $ex) {
                $user['photo_base64'] = "";
            }
        }
        return $user;
    }

    /**
     * oi000120 - "Байгууллагын мэдээлэл авах"
     *
     * @return void
     */
    public function oi000120(Request $request)
    {
        $validated = $this->validate($request, [
            'instid' => 'required'
        ], [
            'instid.required' => ResponseCodeEnum::required
        ]);
        $user = auth()->user();
        $cust = ApCustomer::where('regno', $user->regno)
            ->where('instid', $validated['instid'])
            ->where('statusid', '<>', '-1')->first();
        if (empty($cust)) {
            $this->error('RC000015');
        }
        return $cust;
    }

    /**
     * oi000140 - activity log
     *
     * @return void
     */
    public function oi000140()
    {
        return AdLoginActivityLog::where('userid', auth()->user()->id)->where('channel', 1)
            ->orderBy('created_at', 'desc')->paginate(10);
    }

    public function oi000110(Request $request)
    {
        $validated = $this->validate($request, [
            'photo_url' => 'required'
        ]);

        $user = ApCustUser::where('id', auth()->user()->id)->where('statusid', '<>', '-1')->first();
        if (!$user) {
            if (!$user) {
                throw new MeException("RC000010", [
                    'id' => auth()->user()->id
                ]);
            }
        }

        $user['photo_url'] = $validated['photo_url'];
        $user['updated_at'] = Carbon::now();
        $user['updated_by'] = auth()->user()  ? auth()->user()->id : 1;
        $user->save();
        return $user;
    }

    public function oi000490(Request $request)
    {
        $validated = $this->validate($request, [
            'phoneuser' => 'max:60',
            'firstname' => 'required|max:50',
            'lastname' => 'required|max:50',
            'userid' => 'required|numeric',
            'roles' => 'nullable|array',
            'address' => 'nullable|max:100',
            'photo_url' => 'nullable',
            'roles.*.roleid' => 'required|max:10',
            'roles.*.statusid' => 'required|numeric|max:1',
            'roles.*.startdate' => 'nullable|date_format:Y-m-d',
            'roles.*.enddate' => 'nullable|date_format:Y-m-d',
            'region' => 'nullable',
            'subregion' => 'nullable',
            'email' => 'required',
        ]);

        try {
            DB::beginTransaction();
            $user = ApCustUser::where('id', $validated['userid'])->where('statusid', '<>', -1)->first();
            if (!$user) {
                throw new MeException("RC000010", [
                    'id' => $validated['userid']
                ]);
            }

            $validated = array_change_key_case($validated);
            foreach ($user->getFillable() as $field) {
                if (array_key_exists($field, $validated)) {
                    if ($field != 'email') {
                        $user->$field = $validated[$field];
                    }
                }
            }

            $user['updated_at'] = Carbon::now();
            $user['updated_by'] = auth()->user() ? auth()->user()->id : 1;

            $user->save();

            DB::commit();
            return "RC000206";
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }
}
