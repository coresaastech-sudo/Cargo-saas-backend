<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstBrch;
use Illuminate\Support\Str;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\Views\VwGpInstBrch;
use Modules\Gp\Http\Requests\GpInstBrchRequest;
use Illuminate\Support\Facades\Log;

class GpInstBrchController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        $validate = $this->validate($request, [
            'instid' => 'nullable'
        ]);

        $user = auth()->user();
        if (empty($validate['instid'])) {
            $validate['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validate['instid'] = auth()->user()->instid;
            }
        }
        return $this->getGridData($request, VwGpInstBrch::select([
            'brchno',
            'name',
            'name2',
            'instid',
        ])->where('statusid', 1)
            ->where('instid', $validate['instid']), [['field' => 'listorder', 'dir' => 'ASC']]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(GpInstBrchRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();
        if (isset($validated['instid'])) {
            if ($user->isadmin != 1) {
                $validated['instid'] = $user->instid;
            }
        } else {
            $validated['instid'] = $user->instid;
        }
        $brch = GpInstBrch::where('instid', $validated['instid'])
            ->where('brchno', $validated['brchno'])->where('statusid', 1)->first();
        if ($brch) {
            $this->error('RC000028');
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['dirname'] = Str::upper($validated['dirname'] ?? '');
        $validated['dirname2'] = Str::upper($validated['dirname2'] ?? '');
        $validated['statusid'] = 1;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;
        GpInstBrch::create($validated);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'brchno' => 'required',
            'instid' => 'nullable'
        ], [
            'brchno.required' => "RC000017",
        ]);

        $user = auth()->user();
        if (empty($validated['instid'])) {
            $validated['instid'] = auth()->user()->instid;
        } else {
            if ($user->isadmin != 1) {
                $validated['instid'] = auth()->user()->instid;
            }
        }

        $GPinst = VwGpInstBrch::where('instid', $validated['instid'])
            ->where('brchno', $validated['brchno'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000019", ['brchno' => $validated['brchno']]);
        }
    }

    /**
     * Update the specified resource in storage.
     * @AC gp012300
     * @param Request $request
     * @return Response
     */
    public function update(GpInstBrchRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['brchno'])) {
            $this->error("RC000017");
        }
        if (empty($validated['instid'])) {
            $this->error("RC000018");
        }
        $user = auth()->user();
        if (isset($validated['instid'])) {
            if ($user->isadmin != 1) {
                $validated['instid'] = $user->instid;
            }
        } else {
            $validated['instid'] = $user->instid;
        }
        $validated['name'] = Str::upper($validated['name'] ?? '');
        $validated['name2'] = Str::upper($validated['name2'] ?? '');
        $validated['dirname'] = Str::upper($validated['dirname'] ?? '');
        $validated['dirname2'] = Str::upper($validated['dirname2'] ?? '');
        $validated['updated_by'] = auth()->user()->id;
        $inst = GpInstBrch::where('statusid', 1)->where('instid', $validated['instid'])->find($validated['brchno']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     * @gp012400
     */
    public function destroy(Request $request)
    {
        $validated = $this->validate($request, [
            'brchno' => 'required',
            'instid' => 'required'
        ], [
            'brchno.required' => "RC000017",
            'instid.required' => "RC000018"
        ]);
        $this->error('Салбар устгах боломжгүй');
        $user = auth()->user();
        if (isset($validated['instid'])) {
            if ($user->isadmin != 1) {
                $validated['instid'] = $user->instid;
            }
        } else {
            $validated['instid'] = $user->instid;
        }
        $dtl = GpInstBrch::where('instid', $validated['instid'])
            ->where('brchno', $validated['brchno'])
            ->where('statusid', 1)->first();
        if ($dtl) {
            // Тухайн салбар дээр хэрэглэгч бүртгэлтэй байгаа эсэхийг шалгана
            $user = GpInstUser::where('instid', $validated['instid'])
                ->where('brchno', $validated['brchno'])->where('statusid', 1)->first();
            if ($user) {
                $this->error('RC000029', ['userid' => $user->id]);
            }
            // PK талбараар хэдэн бүртгэл байгаа тоог авна
            $count = GpInstBrch::where('instid', $validated['instid'])
                ->where('brchno', $validated['brchno'])->count();
            // PK талбар талбар дээрх бүртгэлийн тоогоор statusid тавигдана.
            // PK талбаруудыг дахин давхардалт үүсгэхгүйн тулд
            $dtl->update([
                'statusid' => '-' . $count + 1,
                'updated_by' => auth()->user()->id,
            ]);
        } else {
            $this->error('RC000027');
        }
    }
}
