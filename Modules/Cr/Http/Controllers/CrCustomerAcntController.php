<?php

namespace Modules\Cr\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Ad\Http\Services\AdHideService;
use Modules\Cr\Entities\Views\VwCrCustAllAcntList;
use Modules\Ia\Entities\Views\VwIaRecPay;
use Modules\Ln\Entities\Views\VwLnMorList;

class CrCustomerAcntController extends Controller
{
    /**
     * cr012001 Харилцагчийн нийт дансны жагсаалт
     * @return array
     */
    public function index(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);

        $list = $this->getGridData(
            $request,
            VwCrCustAllAcntList::where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid']),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );

        $service = new AdHideService();
        array_map(function ($item) use ($service) {
            if ($item->hide == '1') {
                $service = new AdHideService();
                $shouldShow =  $service->hideAcnt($item->acntno);
                $item->typecode = $item->hide == '1' ? ($shouldShow ? $item->typecode : '***') : $item->typecode;
                $item->acnttype_name = $item->hide == '1' ? ($shouldShow ? $item->acnttype_name : '***') : $item->acnttype_name;
                $item->name = $item->hide == '1' ? ($shouldShow ? $item->name : '***') : $item->name;
                return $item;
            }
            return $item;
        }, $list->items());
        return $list;
    }

    /**
     * cr012004 Харилцагчийн нийт авлаг өглөгийн жагсаалт
     * @return array
     */
    public function cr012004(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);

        $list = $this->getGridData(
            $request,
            VwIaRecPay::where('instid', auth()->user()->instid)
                ->where('custid', $validated['custid']),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );

        // $service = new AdHideService();
        // array_map(function ($item) use ($service) {
        //     if ($item->hide == '1') {
        //         $service = new AdHideService();
        //         $shouldShow =  $service->hideAcnt($item->acntno);
        //         $item->typecode = $item->hide == '1' ? ($shouldShow ? $item->typecode : '***') : $item->typecode;
        //         $item->acnttype_name = $item->hide == '1' ? ($shouldShow ? $item->acnttype_name : '***') : $item->acnttype_name;
        //         $item->name = $item->hide == '1' ? ($shouldShow ? $item->name : '***') : $item->name;
        //         return $item;
        //     }
        //     return $item;
        // }, $list->items());
        return $list;
    }

    /**
     * cr012002 Харилцагчийн нийт барьцаа хөрөнгийн жагсаалт
     * @return array
     */

    public function indexColl(Request $request)
    {
        $validated = $this->validate($request, [
            'custid' => 'required'
        ], [
            'custid.required' => "RC000082"
        ]);

        return $this->getGridData(
            $request,
            VwLnMorList::where('instid', auth()->user()->instid)
                ->where('custno', $validated['custid']),
            [['field' => 'created_at', 'dir' => 'ASC']]
        );
    }
}
