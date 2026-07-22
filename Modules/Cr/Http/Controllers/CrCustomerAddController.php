<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Cr\Entities\CrCustAdd;
use Modules\Cr\Entities\Views\VwCrCustAdd;

use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Gp\Entities\GPInstAddField;
use Modules\Gp\Enums\ResponseCodeEnum;
use Illuminate\Support\Facades\Validator;

class CrCustomerAddController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);

        return $this->getGridData(
            $request,
            VwCrCustAdd::where('statusid', '<>', -1)
                ->where('custid', $validated['custid'])
                ->where('instid', auth()->user()->instid),
            [['field' => 'id', 'dir' => 'ASC']]
        );
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @return Response
     */
    public function update(Request $request)
    {
        $validate = $this->validateMe($request, [
            'custid' => 'required'
        ], [
            'custid.required' => ResponseCodeEnum::required
        ]);
        $datas = $request->all();
        $cust = VwCrCustList::where('id', $validate['custid'])
            ->where('instid', auth()->user()->instid)->first();
        if ($cust) {
            $this->validateRequiredWorkEmail($request);
            foreach ($datas as $key => $value) {
                if (gettype($key) == 'integer') {
                    $dtl = CrCustAdd::where('keyfield', $key)
                        ->where('custid', $validate['custid'])
                        ->where('instid', auth()->user()->instid)
                        ->where('statusid', '<>', -1)->first();
                    if ($dtl) {
                        $dtl->update([
                            'itemvalue' => $value,
                            'updated_by' => auth()->user()->id
                        ]);
                    } else {
                        $cnst = GPInstAddField::where('id', $key)
                            ->where('instid', auth()->user()->instid)
                            ->where('statusid', '<>', -1)->first();
                        if ($cnst) {
                            CrCustAdd::create([
                                'keyfield' => $key,
                                'itemvalue' => $value,
                                'statusid' => 1,
                                'custid' => $validate['custid'],
                                'custtypecode' => $cust->custtypecode,
                                'instid' => auth()->user()->instid,
                                'created_by' => auth()->user()->id,
                                'updated_by' => auth()->user()->id,
                            ]);
                        }
                    }
                } else {
                    $dtl = VwCrCustAdd::where('code', $key)
                        ->where('custid', $validate['custid'])
                        ->where('instid', auth()->user()->instid)
                        ->where('statusid', '<>', -1)->first();
                    if ($dtl) {
                        $bs = CrCustAdd::where('id', $dtl->id)
                        ->where('instid', auth()->user()->instid)->first();
                        if ($bs) {
                            $bs->update([
                                'itemvalue' => $value,
                                'updated_by' => auth()->user()->id
                            ]);
                        }
                    } else {
                        $cnst = GPInstAddField::where('code', $key)
                            ->where('instid', auth()->user()->instid)
                            ->where('statusid', '<>', -1)->first();
                        if ($cnst) {
                            CrCustAdd::create([
                                'keyfield' => $cnst->id,
                                'itemvalue' => $value,
                                'statusid' => 1,
                                'custid' => $validate['custid'],
                                'custtypecode' => $cust->custtypecode,
                                'instid' => auth()->user()->instid,
                                'created_by' => auth()->user()->id,
                                'updated_by' => auth()->user()->id,
                            ]);
                        }
                    }
                }
            }
        } else {
            $this->error('RC000015');
        }
    }

    private function validateRequiredWorkEmail(Request $request): void
    {
        $field = GPInstAddField::where('code', 'c_job_mail')
            ->where('typecode', 'cr')
            ->where('instid', auth()->user()->instid)
            ->where('statusid', 1)
            ->first();

        if (empty($field)) {
            return;
        }

        $key = (string) $field->id;
        $validator = Validator::make(
            ['c_job_mail' => $request->input($key, $request->input('c_job_mail'))],
            ['c_job_mail' => 'required|email'],
            [
                'c_job_mail.required' => ResponseCodeEnum::required,
                'c_job_mail.email' => ResponseCodeEnum::email,
            ]
        );

        if ($validator->fails()) {
            $this->validationToMeException($validator->errors()->all(), ['c_job_mail']);
        }
    }
}
