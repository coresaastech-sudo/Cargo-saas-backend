<?php

namespace Modules\Ap\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Modules\Ap\Entities\ApCustomer;
use Modules\Ap\Entities\ApInstCustUserLink;
use Modules\Cr\Entities\CrCustInd;
use Modules\Cr\Entities\CrCustOrg;
use Modules\Gp\Http\Services\CoreService;

class InstCustConnService
{
    public function connect($instid, $cust_userid)
    {
        $conncust = ApInstCustUserLink::where('instid', $instid)
            ->where('cust_userid', $cust_userid)->where('statusid', 1)->first();
        if (!$conncust) {
            $conncust = new ApInstCustUserLink();
            $conncust->instid = $instid;
            $conncust->cust_userid = $cust_userid;
            $conncust->created_by = auth()->user() ? auth()->user()->id : 1;
            $conncust->statusid = 1;
            $conncust->save();
        }
    }

    public function disConnect($instid, $cust_userid)
    {
        $conncust = ApInstCustUserLink::where('instid', $instid)
            ->where('cust_userid', $cust_userid)->where('statusid', 1)->first();
        if ($conncust) {
            $conncust->statusid = -1;
            $conncust->save();
        }
    }

    /**
     * Тухайн байгууллага дээр бүртгэлтэй эсэх
     *
     * @param  mixed $instid байгууллагын бүртгэлийн дугаар
     * @param  mixed $cust_userid хэрэглэгчийн дугаар
     * @return boolean
     */
    public function isConnect($instid, $cust_userid)
    {
        $conncust = ApInstCustUserLink::where('instid', $instid)
            ->where('cust_userid', $cust_userid)->where('statusid', 1)->first();
        if ($conncust) {
            return true;
        } else {
            return false;
        }
    }

    public function createCustomerInfo($regno, $custtypecode = 0)
    {
        $user = auth()->user();
        $providertype = CoreService::getInstGp($user->instid, 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
            if ($custtypecode == 1) {
                $custdata = CrCustOrg::where('id1', $regno)->where('statusid', 1)->where('instid', $user->instid)->first();
            } else {
                $custdata = CrCustInd::where('id1', $regno)->where('statusid', 1)->where('instid', $user->instid)->first();
            }
            if ($custdata) {
                $cust = new ApCustomer();
                $cust->instid = $user->instid;
                $cust->corrid = $custdata->id;
                $cust->cif = $custdata->custno;
                $cust->custtypecode = $custtypecode;
                $cust->regno = $custdata->id1;
                $cust->register_mask_code = $custdata->id1typecode ?? null;
                $cust->email = $custdata->email ?? null;
                $cust->industry = $custdata->inducode ?? null;
                $cust->segment = $custdata->segcode ?? null;
                $cust->ispolitical = $custdata->ispolitical ?? null;
                $cust->birthday = $custdata->birthdate ? new Carbon($custdata->birthdate) : null;

                if ($custtypecode == 1) {
                    // Байгууллага (CrCustOrg)
                    $cust->fname = $custdata->name ?? null;
                    $cust->fname2 = $custdata->name2 ?? null;
                    $cust->phone = $custdata->workphone ?? null;
                } else {
                    // Хувь хүн (CrCustInd)
                    $cust->familyname = $custdata->familyname ?? null;
                    $cust->familyname2 = $custdata->familyname2 ?? null;
                    $cust->lname = $custdata->lname ?? null;
                    $cust->lname2 = $custdata->lname2 ?? null;
                    $cust->fname = $custdata->name ?? null;
                    $cust->fname2 = $custdata->name2 ?? null;
                    $cust->gender = $custdata->sexcode ?? null;
                    $cust->nationality = $custdata->nationcode ?? null;
                    $cust->lang = $custdata->langcode ?? null;
                    $cust->employment = $custdata->profession ?? null;
                    $cust->education = $custdata->educode ?? null;
                    $cust->maritalstatus = $custdata->maritalstatuscode ?? null;
                    $cust->phone = $custdata->handphone ?? null;
                    $cust->familysize = $custdata->familymembercount ?? null;
                    $cust->shortname = ($custdata->lname ? mb_substr($custdata->lname, 0, 1) . ". " : '') . ($custdata->name ?? '');
                    $cust->shortname2 = ($custdata->lname2 ? mb_substr($custdata->lname2, 0, 1) . ". " : '') . ($custdata->name2 ?? '');
                }

                $cust->statusid = 1;
                $cust->created_by = $user->id;
                $cust->updated_by = $user->id;
                $cust->save();
                return $cust;
            } else {
                throw new MeException('RC000085');
            }
        } else {
            $polaris = new PolarisApiRequestService();
            $custdata = $polaris->sendRequest(13610335, [$regno]);
            $custdata = $polaris->sendRequest(13610310, [$custdata['custCode']]);

            $cust = new ApCustomer();
            $cust->instid = $user->instid;
            $cust->corrid = $custdata['id'] ?? null; //
            $cust->cif = $custdata['custCode'] ?? null;
            $cust->familyname = $custdata['familyName'] ?? null;
            $cust->familyname2 = $custdata['familyName2'] ?? null;
            $cust->lname = $custdata['lastName'] ?? null;
            $cust->lname2 = $custdata['lastName2'] ?? null;
            $cust->fname = $custdata['firstName'] ?? null;
            $cust->fname2 = $custdata['firstName2'] ?? null;
            $cust->gender = $custdata['sexName'] ?? null;
            $cust->regno = $custdata['registerCode'] ?? null;
            $cust->register_mask_code = $custdata['registerMaskCode'] ?? null;
            $cust->nationality = $custdata['nationalityName'] ?? null;
            $cust->birthday = new Carbon($custdata['birthDate'] ?? null);
            $cust->lang = $custdata['langCode'] ?? null;
            $cust->ethnicity = $custdata['ethnicGrpName'] ?? null;
            $cust->citizenship = $custdata['countryName'] ?? null; //
            $cust->birthplace = $custdata['birthPlaceName'] ?? null;
            $cust->segment = $custdata['custSegCode'] ?? null; //
            $cust->employment = $custdata['employmentId'] ?? null;
            // $cust->categories = $custdata['name'] ?? null;
            $cust->education = $custdata['eduName'] ?? null;
            $cust->maritalstatus = $custdata['maritalStatusName'] ?? null;
            $cust->phone = $custdata['mobile'] ?? null;
            $cust->phone2 = $custdata['phone'] ?? null;
            $cust->email = $custdata['email'] ?? null; //
            $cust->fax = mb_substr($custdata['fax'] ?? '', 0, 14); //
            $cust->familysize = $custdata['familyCnt'] ?? null; //
            $cust->industry = $custdata['industryName'] ?? null; //
            $cust->shortname = $custdata['shortName'] ?? null; //
            $cust->shortname2 = $custdata['shortName2'] ?? null; //
            $cust->isbl = $custdata['isBl'] ?? null;
            $cust->iscompanycustomer = $custdata['isCompanyCustomer'] ?? null;
            $cust->ispolitical = $custdata['isPolitical'] ?? null;
            $cust->isvatpayer = $custdata['isVatPayer'] ?? null;
            $cust->monthlyincome = $custdata['monthlyIncome'] ?? null;
            $cust->immovabletype = $custdata['immovableType'] ?? null;
            $cust->ownership = $custdata['ownerShip'] ?? null;
            $cust->region = $custdata['name'] ?? null; //
            $cust->subregion = $custdata['name'] ?? null; //
            $cust->address = $custdata['name'] ?? null; //
            $cust->statusid = 1;
            $cust->created_by = $user->id;
            $cust->updated_by = $user->id;
            $cust->save();
            return $cust;
        }
    }

    public function getCustAccounts($custCode, $instid)
    {
        $user = auth()->user();
        $providertype = CoreService::getInstGp($user->instid, 'MEAPPPROVIDER');
        if ($providertype == 'MECORE') {
        } else {
            $polaris = new PolarisApiRequestService($instid);
            $respdata = $polaris->sendRequest(13610312, [$custCode, 0, -1], $instid);
        }
    }
}
