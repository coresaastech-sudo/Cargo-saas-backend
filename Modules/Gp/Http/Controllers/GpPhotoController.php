<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ap\Entities\ApContractSignImage;
use Modules\Ap\Entities\ApUserImage;
use Modules\Cr\Entities\CrCustImage;
use Modules\Cr\Entities\CrCustSignImage;
use Modules\Gp\Entities\GpPhoto;

class GpPhotoController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        $v = $this->validate($request, [
            'image' => 'required|mimes:png,jpg,jpeg',
            'type' => 'required|in:GP,CR,SN,MC,MU',
            'instid' => 'nullable'
        ]);
        // MC - ME CONTRACT
        // MU - ME USER
        // Зураг файлыг унших
        $file = $request->file('image');
        $fileContents = $file->get();
        $encoded = base64_encode($fileContents);

        switch ($v['type']) {
            case 'GP':
                $file_field = 'photo';
                $class = GpPhoto::class;
                break;
            case 'CR':
                $file_field = 'image';
                $class = CrCustImage::class;
                break;
            case 'SN':
                $file_field = 'image';
                $class = CrCustSignImage::class;
                break;
            case 'MC':
                $file_field = 'image';
                $class = ApContractSignImage::class;
                break;
            case 'MU':
                $file_field = 'image';
                $class = ApUserImage::class;
                break;
            default:
                $file_field = 'photo';
                # code...
                break;
        }

        $photo = $class::create([
            $file_field => $encoded,
            'statusid' => 1,
            'instid' => @$v['instid'] ? @$v['instid'] : auth()->user()->instid,
            'filename' => $request->file('image')->getClientOriginalName(),
            'name' => $request->file('image')->getClientOriginalName(),
            'created_by' => auth()->user()->id
        ]);
        return $this->success($photo->id);
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $v = $this->validate($request, [
            'id' => 'required',
            'type' => 'required|in:GP,CR,SN,MC,MU'
        ]);
        switch ($v['type']) {
            case 'GP':
                $photo = GpPhoto::where('id', $v['id'])->first();
                $file_field = 'photo';
                break;
            case 'CR':
                $photo = CrCustImage::where('id', $v['id'])->first();
                $file_field = 'image';
                break;
            case 'SN':
                $photo = CrCustSignImage::where('id', $v['id'])->first();
                $file_field = 'image';
                break;
            case 'MC':
                $photo = ApContractSignImage::where('id', $v['id'])->first();
                $file_field = 'image';
                break;
            case 'MU':
                $photo = ApUserImage::where('id', $v['id'])->first();
                $file_field = 'image';
                break;
            default:
                # code...
                break;
        }

        if ($photo) {
            return response()->streamDownload(function () use ($photo, $file_field) {
                echo base64_decode(stream_get_contents($photo->$file_field));
            }, $photo->name, [
                'Content-Type' => getMimeTypeFromFilename($photo->name),
            ]);
        } else {
            return response('Not found', 404);
        }
    }
}
