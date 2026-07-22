<?php

namespace Modules\Cr\Http\Services;

use Modules\Cr\Entities\Views\VwCrCustAdd;
use Modules\Cr\Entities\Views\VwCrCustAddress;
use Modules\Cr\Entities\Views\VwCrCustIndList;
use Modules\Cr\Entities\Views\VwCrCustList;
use Modules\Cr\Entities\Views\VwCrCustOrgList;
use Modules\Gp\Entities\Views\VwGPInstAdd;
use Modules\Ia\Entities\Views\VwIaCtAccountAdd;
use Modules\Ln\Entities\Views\VwLnMorAdd;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;

class CustomerService
{

    /**
     * Харилцагчдын хаягын жагсаалт авах
     * @return Response
     */
    public function getCustomerAddresses($data, $instid)
    {
        $result = [];
        foreach ($data as $custid) {
            $cust = VwCrCustList::where('id', $custid)->where('instid', $instid)->where('statusid', 1)->first();
            if ($cust) {
                $addresses = VwCrCustAddress::where('custid', $custid)
                    ->where('instid', $instid)
                    ->where('statusid', 1)->get();
                $result[] = [
                    'custid' => $cust->id,
                    'custno' => $cust->custno,
                    'addresses' => $addresses ?? []
                ];
            }
        }

        return $result;
    }

    /**
     * Харилцагчдын дэлгэрэнгүй мэдээлэл авах
     * @return Response
     */
    public function getCustomerDetails($data, $instid, $withAddress = false)
    {
        $result = [];
        foreach ($data as $custid) {
            $cust = VwCrCustList::where('id', $custid)->where('instid', $instid)->where('statusid', 1)->first();
            if ($cust) {
                if ($cust->custtypecode == 0) {
                    // Иргэн
                    $custDetail = CrCustInd::where('id', $custid)->where('instid', $instid)->where('statusid', 1)->first();
                } else {
                    // Байгууллага
                    $custDetail = CrCustOrg::where('id', $custid)->where('instid', $instid)->where('statusid', 1)->first();
                }

                if ($withAddress) {
                    $addresses = VwCrCustAddress::where('custid', $cust->id)
                        ->where('instid', $instid)
                        ->where('statusid', 1)
                        ->distinct('id')->get();
                    if (isset($custDetail)) {
                        $custDetail['addresses'] =  $addresses ?? [];
                    }
                }

                $result[] = $custDetail;
            }
        }

        return $result;
    }

    /**
     * Харилцагчдын нэмэлт мэдээлэл авах
     * @return Response
     */
    public function getAdditionals($type, $custids, $instid)
    {
        $data = [];
        $field = 'custid';
        if ($type == "cr" || $type == "crorg") {
            $data =  VwCrCustAdd::select('custid', 'code', 'keyfield', 'itemvalue')->whereIn('custid', $custids)->where('instid', $instid)->where('statusid', '<>', -1)->get();
            $field = 'custid';
        } else if ($type == "ln") {
            $data = VwLnMorAdd::select('morno','code', 'keyfield', 'itemvalue')->whereIn('morno', $custids)->where('instid', $instid)->where('statusid', '<>', -1)->get();
            $field = 'morno';
        } else if ($type == "gp") {
            $data = VwGPInstAdd::select('instid', 'code', 'keyfield', 'itemvalue')->whereIn('custid', $custids)->where('instid', $instid)->where('statusid', '<>', -1)->get();
           $field = 'instid';
         } else if ($type == "ia") {
            $data = VwIaCtAccountAdd::select('acntno', 'code', 'keyfield', 'itemvalue')->whereIn('custid', $custids)->where('instid', $instid)->where('statusid', '<>', -1)->get();
            $field = 'acntno';
        } else {
            $data = [];
        }

        // Шинэ массив үүсгэх
        $grouped = [];

        foreach ($data as $item) {
            // custid-г шалгах
            if (!isset($grouped[$item[$field]])) {
                $grouped[$item[$field]] = [$field => $item[$field]]; // custid-г эхэнд нь нэмэх
            }

            // code болон itemvalue-г нэмэх
            $grouped[$item[$field]][$item['code']] = $item['itemvalue'];
        }

        // Шинэ массивыг JSON формат руу хөрвүүлэх
        $finalResult = array_values($grouped); // values() нь түлхүүрийг хасаж, зөвхөн утгуудыг хадгална

        return $finalResult;
    }
}
