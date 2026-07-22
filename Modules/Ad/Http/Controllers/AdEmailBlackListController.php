<?php

namespace Modules\Ad\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdEmailBlacklist;
use Modules\Ad\Http\Requests\AdEmailBlacklistRequest;
use Modules\Ad\Http\Services\AdAwsSesService;

class AdEmailBlackListController extends Controller
{
    /**
     * Display a listing of the resource.
     * index
     * @return Response
     */
    public function ad020005(Request $request)
    {
        return $this->getGridData($request, AdEmailBlacklist::where('statusid', 1)
            ->where('instid', 1), [['field' => 'id', 'dir' => 'DESC']]);
    }

    /**
     * Store a newly created resource in storage.
     * store
     * @param Request $request
     * @return Response
     */
    public function ad020205(AdEmailBlacklistRequest $request)
    {
        $validated = $request->validated();
        $user = auth()->user();

        $validated['lastupdatetime'] = $validated['lastupdatetime'] ?? now();
        $validated['source'] = $validated['source'] ?? 'MeCoreTerminal';
        $validated['statusid'] = 1;
        $validated['instid'] = 1;
        $validated['created_by'] = $user->id;
        $validated['updated_by'] = $user->id;

        $service = new AdAwsSesService();
        $result = $service->addToSuppressionList($validated['emailaddress'], $validated['reason']);

        if ($result['success']) {
            AdEmailBlacklist::create($validated);
        } else {
            $this->error("RC000003");
        }
    }

    /**
     * Show the specified resource.
     * show
     * @param int $id
     * @return Response
     */
    public function ad020105(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $GPinst = AdEmailBlacklist::where('id', $validate['id'])
            ->where('statusid', 1)->first();
        if ($GPinst) {
            return $GPinst;
        } else {
            $this->error("RC000010", $validate);
        }
    }

    /**
     * Update the specified resource in storage.
     * update
     * @param Request $request
     * @return Response
     */
    public function ad020305(AdEmailBlacklistRequest $request)
    {
        $validated = $request->validated();
        if (empty($validated['id'])) {
            $this->error("RC000011");
        }
        $user = auth()->user();
        $validated['updated_by'] = $user->id;
        $inst = AdEmailBlacklist::where('instid', 1)
            ->where('statusid', 1)
            ->find($validated['id']);
        $inst->update($validated);
    }

    /**
     * Remove the specified resource from storage.
     * destroy
     * @return Response
     */
    public function ad020405(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required',
        ], [
            'id.required' => "RC000011",
        ]);
        $user = auth()->user();
        $dtl = AdEmailBlacklist::where('instid', 1)->where('id', $validated['id'])->where('statusid', 1)->first();

        $service = new AdAwsSesService();
        $result = $service->removeFromSuppressionList($dtl->emailaddress);

        if ($result['success']) {
            $count = AdEmailBlacklist::where('instid', 1)
                ->where('statusid', '<>', 1)
                ->where('emailaddress', '=', $dtl->emailaddress)
                ->count();

            // Статус шинэчлэх (1 байвал -1 болгох, эсвэл -2, -3 гэх мэт)
            $newStatus = $count ? ($count + 1) * -1 : -1;
            $dtl->update([
                'statusid' => $newStatus,
                'updated_by' => $user->id,
            ]);
        } else {
            $this->error("RC000003");
        }
    }



    /**
     * Get email suppressed destination list
     * ad020505
     * @return Response
     */
    public function ad020505(Request $request)
    {
        $bulkData = [];
        $service = new AdAwsSesService();

        $data = $service->getSuppressedDestinations();

        foreach ($data['SuppressedDestinationSummaries'] as $value) {
            $bulkData[] = [
                'emailaddress' => $value['EmailAddress'],
                'lastupdatetime' => $value['LastUpdateTime'],
                'reason' => $value['Reason'],
                'desc' => '',
                'source' => 'AWS',
                'statusid' => 1,
                'instid' => 1,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AdEmailBlacklist::upsert(
            $bulkData,
            ['emailaddress', 'statusid'], // Migration-д байгаа unique constraint-тай тааруулах
            ['lastupdatetime', 'reason', 'desc', 'source', 'instid', 'updated_by', 'updated_at']
        );
    }
}
