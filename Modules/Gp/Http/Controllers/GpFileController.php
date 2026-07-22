<?php

namespace Modules\Gp\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpFile;

class GpFileController extends Controller
{
    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {

        $this->validate($request, [
            'files' => 'required|file',
        ]);
        try {
            $file = addslashes($request->file('files'));
            $b_file = pg_escape_bytea(base64_encode(file_get_contents($file)));
            $photo = GpFile::create([
                'file' => $b_file,
                'statusid' => 1,
                'instid' => auth()->user()->instid,
                'filename' => $request->file('files')->getClientOriginalName(),
                'name' => $request->file('files')->getClientOriginalName(),
                'created_by' => auth()->user()->id,
                'type' => 'file'
            ]);
            return $this->success($photo->id);
        } catch (\Throwable $th) {
            Log::debug($th);
            // throw $th;
            $this->error('RC000013');
        }
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Response
     */
    public function show(Request $request)
    {
        $v = $this->validate($request, [
            'id' => 'required'
        ]);

        $file = GpFile::where('id', $v['id'])->first();

        if ($file) {
            file_put_contents($file->name, base64_decode(stream_get_contents($file->file)));
            return response()->download($file->name, $file->name, [], 'inline')->deleteFileAfterSend(true);
        } else {
            return response('Not found', 404);
        }
    }
}
