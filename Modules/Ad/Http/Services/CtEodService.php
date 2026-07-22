<?php

namespace Modules\Ad\Http\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Ia\Entities\IaCtAccount;
use Modules\Ia\Entities\IaCtAccountHist;

class CtEodService
{

    /**
     * Тэнцэлийн гадуурх дансны үлдэгдэл түр хадгалах ad800057
     */
    public function CreateTmpCTBals($sysdate, $lastitem, $instid)
    {
        $csysdate = Carbon::parse($sysdate);

        $sql = IaCtAccount::select([
            'acntno',
            'statusid',
            'currentbal',
            'capint',
            'currentcount',
        ])
            ->where(function ($query) {
                $query->whereRaw('COALESCE(tmp_currentbal::varchar, \'0\') <> COALESCE(currentbal::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_statusid::varchar, \'0\') <> COALESCE(statusid::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_capint::varchar, \'0\') <> COALESCE(capint::varchar, \'0\')')
                    ->orWhereRaw('COALESCE(tmp_currentcount::varchar, \'0\') <> COALESCE(currentcount::varchar, \'0\')');
            })
            ->where('instid', '=', $instid);
        if ($lastitem && $lastitem->acntno) {
            $sql = $sql->where('acntno', '>=', $lastitem->acntno);
        }
        $results = $sql->orderBy('acntno', 'ASC')->get();
        return $results;
    }

    /**
     * Оруулах гэж байгаа өдрөөр бичлэг байвал түүнийг устгах ad800121
     */
    public function CtAcntHistDel($sysdate, $lastitem, $instid)
    {
        IaCtAccountHist::where('instid', $instid)
            ->where('txndate', $sysdate)->delete();
    }

    /**
     * Хаагдсанаас бусад CT дансны мэдээлэл авах ad800121
     */
    public function CtAcntHistAdd($sysdate, $lastitem, $instid, $userid)
    {
        $caldate = getNow();
        $results = IaCtAccount::select(
            'acntno',
            DB::raw("'$sysdate' as txndate"),
            'brchno',
            'custno',
            'segcode',
            'typecode',
            'curcode',
            'name',
            'name2',
            'catcode',
            'currentbal',
            DB::raw('COALESCE (openeddate, created_at) AS openeddate'),
            'closeddate',
            'applicationdate',
            'applicationamount',
            'approvaldate',
            'approvalamount',
            'expirydate',
            'expirycondition',
            'fintxncount',
            'lasttxndate',
            'loanacntno',
            'hide',
            'currentcount',
            'subcode',
            'lasttellertxndate',
            'txndef',
            'tellerfunc',
            'colltype',
            'collpendsdate',
            'collpendedate',
            'relacntmod',
            'relacntno',
            'reserved',
            'capint',
            'statusid',
            'tmp_statusid',
            'tmp_currentbal',
            'tmp_currentcount',
            'tmp_capint',
            'instid',
            'created_by',
            DB::raw("'$caldate' AS created_at"),
        )
            ->where('instid', $instid)->where('statusid', '!=', 0)->get();
        return $results;
    }
}
