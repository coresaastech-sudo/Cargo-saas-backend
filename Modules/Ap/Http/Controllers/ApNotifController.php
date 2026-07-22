<?php

namespace Modules\Ap\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Cr\Entities\CrCustNotifications;
use Modules\Cr\Entities\Views\VwCrCustNotifications;
use Modules\Gp\Enums\ResponseCodeEnum;

class ApNotifController extends Controller
{

    /**
     * oi000210 "Мэдэгдэл уншгаагүй тоо авах"
     *
     * @param  mixed $request
     * @return void
     */
    public function oi000210()
    {
        $notif = CrCustNotifications::where('custid', auth()->user()->id)
            ->where('custtype', "MEAPP")
            ->where('is_read', 0)->count();
        return ['unread' => $notif];
    }

    /**
     * oi000220 Мэдэгдэл харах
     *
     * @return void
     */
    public function oi000220(Request $request)
    {
        return $this->getGridData(
            $request,
            VwCrCustNotifications::where('custid', auth()->user()->id)
                ->where('custtype', "MEAPP")
                ->orderBy('created_at', 'desc')
        );
    }

    /**
     * oi000230 Мэдэгдэл унших
     *
     * @return void
     */
    public function oi000230(Request $request)
    {
        $validated = $this->validate(
            $request,
            [
                'id' => 'required',
            ],
            [
                'id.required' => ResponseCodeEnum::required
            ]
        );
        $notif = CrCustNotifications::where('custid', auth()->user()->id)
            ->where('custtype', "MEAPP")
            ->where('id', $validated['id'])->first();
        if ($notif) {
            $notif->is_read = 1;
            $notif->save();
        }
    }
}
