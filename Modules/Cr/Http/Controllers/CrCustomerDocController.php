<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Cr\Entities\CrCustDoc;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Http\Requests\CrCustDocRequest;
use Modules\Gp\Enums\ResponseCodeEnum;

class CrCustomerDocController extends Controller
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
            CrCustDoc::select(['id', 'name', 'name2', 'filename'])
                ->where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid'])
                ->where('statusid', '<>', -1),
            [['field' => 'id', 'dir' => 'DESC']]
        );
    }

    /**
     * Файл татах
     *
     * @param  mixed $request
     * @return mixed
     * @AC cr010505
     */
    public function downloadFile(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required',
        ], [
            'id.required' => ResponseCodeEnum::required,
        ]);
        $doc = CrCustDoc::where('id', $validated['id'])
            ->where('instid', auth()->user()->instid)
            ->where('statusid', '<>', -1)->first();
        if ($doc) {
            return response()->streamDownload(function () use ($doc) {
                echo base64_decode(stream_get_contents($doc->file));
            }, $doc->filename, [
                'Content-Type' => getMimeTypeFromFilename($doc->filename),
            ]);
        } else {
            $this->error('VC000009');
        }
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'files' => 'required'
        ], [
            'custid.required' => ResponseCodeEnum::required,
            'name.required' => ResponseCodeEnum::required,
            'files.required' => ResponseCodeEnum::required,
        ]);
        // return $validated;
        $cust = VwCrCustList::where('id', $validated['custid'])->first();
        if ($cust) {
            $isfile = $request->file('files');
            if ($isfile !== null && $isfile->isValid()) {
                if (filesize($isfile) === 0) {
                    $this->error('RC000081');
                } else {
                    // $file = addslashes($request->file('files'));
                    // $file = pg_escape_bytea(base64_encode(file_get_contents($file)));
                    $file = $request->file('files');
                    $fileContents = $file->get();
                    $encoded = base64_encode($fileContents);
                    $validated['filename'] = $request->file('files')->getClientOriginalName();
                    $validated['file'] = $encoded;
                    $validated['statusid'] = 1;
                    $validated['instid'] = auth()->user()->instid;
                    $validated['created_by'] = auth()->user()->id;
                    $validated['updated_by'] = auth()->user()->id;
                    $validated['custtypecode'] = $cust->custtypecode;
                    CrCustDoc::create($validated);
                }
            } else {
                $this->error('RC000081');
            }
        } else {
            $this->error('RC000015');
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
        $GPinst = CrCustDoc::select([
            'id',
            'name',
            'name2',
            'filename'
        ])
            ->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
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
    public function update(Request $request)
    {
        $validated = $this->validate($request, [
            'id' => 'required',
            'name' => 'required',
            'name2' => 'nullable',
            'files' => 'nullable'
        ], [
            'id.required' => "RC000011",
            'name.required' => ResponseCodeEnum::required,
        ]);
        $isfile = $request->file('files');
        if ($isfile !== null && $isfile->isValid()) {
            if (filesize($isfile) === 0) {
                $this->error('RC000081');
            } else {
                // $file = addslashes($request->file('files'));
                // $file = pg_escape_bytea(base64_encode(file_get_contents($file)));
                $file = $request->file('image');
                $fileContents = $file->get();
                $encoded = base64_encode($fileContents);
                $validated['filename'] = $request->file('files')->getClientOriginalName();
                $validated['file'] = $encoded;
            }
        } else {
            $this->error('RC000081');
        }
        $validated['updated_by'] = auth()->user()->id;
        $inst = CrCustDoc::where('instid', auth()->user()->instid)->where('statusid', '<>', -1)->find($validated['id']);
        $inst->update($validated);
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
        $dtl = CrCustDoc::where('instid', auth()->user()->instid)->where('id', $validate['id'])->where('statusid', '<>', -1)->first();
        $dtl->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id,
        ]);
    }
}
