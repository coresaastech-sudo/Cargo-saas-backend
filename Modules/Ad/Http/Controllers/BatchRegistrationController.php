<?php

namespace Modules\Ad\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdBatchRegistration;
use Modules\Ad\Entities\AdBatchRegistrationDetail;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\BatchRegistrationJob;

class BatchRegistrationController extends Controller
{

    public function ad019080(Request $request)
    {
        return $this->getGridData(
            $request,
            AdBatchRegistration::where('statusid', 1)
                ->where('instid', auth()->user()->instid),
            [
                ['field' => 'id', 'dir' => 'DESC']
            ]
        );
    }

    public function ad019180(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);

        return $this->getGridData($request, AdBatchRegistrationDetail::where('statusid', '!=', -1)
            ->where('batchregistrationid', $validate['id'])
            ->where('instid', auth()->user()->instid), [
            ['field' => 'id', 'dir' => 'ASC']
        ]);
    }

    public function ad019580(Request $request)
    {
        $isworking = $this->isOnEodJob();

        return [
            'isworking' => $isworking
        ];
    }

    /**
     * Багцаар гүйлгээ хийх
     *
     * @param  mixed $request
     * @return string
     */
    public function ad019980(Request $request)
    {
        $v = $this->validate($request, [
            'filename' => 'required',
            'txns' => 'required|array',
            'isLast' => 'required|boolean',
            'batchId' => 'nullable|numeric'
        ], [
            'filename.required' => ResponseCodeEnum::required,
            'txns.required' => ResponseCodeEnum::required,
            'txns.array' => ResponseCodeEnum::array,
            'isLast.required' => ResponseCodeEnum::required
        ]);

        if ($this->isOnEodJob()) {
            $this->error('RC000196');
        }

        $batchId = $v['batchId'] ?? null;
        $count = 0;

        foreach ($v['txns'] as $txn) {
            $count += isset($txn['data']) && is_array($txn['data'])
                ? count($txn['data'])
                : 0;
        }

        if (!$batchId) {
            $adbatch = AdBatchRegistration::create([
                'filename' => $v['filename'],
                'instid' => auth()->user()->instid,
                'count' => 0,
                'requestdata' => '',
                'successcount' => 0,
                'errorcount' => 0,
                'statusid' => 1,
                'created_by' => auth()->user()->id,
                'process' => 0
            ]);
            $batchId = $adbatch->id;
        } else {
            $adbatch = AdBatchRegistration::find($batchId);
        }

        $tempDir = storage_path('app/batch_temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempFile = $tempDir . '/batch_' . $batchId . '.jsonl';
        $handle = fopen($tempFile, 'a');

        foreach ($v['txns'] as $txn) {
            fwrite($handle, json_encode($txn, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        }

        fclose($handle);

        $adbatch->count += $count;
        $adbatch->save();

        if ($v['isLast']) {
            BatchRegistrationJob::dispatch(
                auth()->user()->id,
                auth()->user()->instid,
                $batchId
            )->onQueue('BatchRegistrationJob');
            return 'Багц гүйлгээ эхэлсэн. ' . $batchId;
        }

        return ['status' => 'ok', 'batchId' => $batchId];
    }

    public function isOnEodJob()
    {
        return app(\App\Services\QueueJobInspector::class)
            ->has('BatchRegistrationJob', BatchRegistrationJob::class, auth()->user()->instid);
    }
}
