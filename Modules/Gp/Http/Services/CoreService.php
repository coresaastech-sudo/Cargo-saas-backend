<?php

namespace Modules\Gp\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Gp\Entities\GpInstGp;
use Modules\Gp\Entities\GpInstSeq;
use Modules\Gp\Enums\CacheGroupEnum;
use Illuminate\Support\Facades\Log;
use Modules\Ad\Entities\AdSvUser;

class CoreService
{

    public static function hmac($message, $binary = false)
    {
        return hash_hmac('sha512', $message, "TWVDb3JlRmliYUlLPQ==", $binary);
    }

    /**
     * Encrypt a message
     *
     * @param string $input - message to encrypt
     * @return string
     */
    public function safeEncrypt($input)
    {
        $iv = "1234567890123456";
        $key = "TWVDb3JlRmliYUlLPQ==";
        return openssl_encrypt($input, "AES-128-CBC", $key, 0, $iv);
    }

    /**
     * Decrypt a message
     *
     * @param string $encrypted - message encrypted with safeEncrypt()
     * @return string
     */
    public function safeDecrypt($encrypted)
    {
        $key = "TWVDb3JlRmliYUlLPQ==";
        $iv = "1234567890123456";
        return openssl_decrypt($encrypted, "AES-128-CBC", $key, 0, $iv);
    }

    public function random($length = 16)
    {
        $random_string = "";
        $valid_chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";

        $num_valid_chars = strlen($valid_chars);

        for ($i = 0; $i < $length; $i++) {
            $random_pick = mt_rand(1, $num_valid_chars);
            $random_char = $valid_chars[$random_pick - 1];
            $random_string .= $random_char;
        }
        return $random_string;
    }

    /**
     * Хэрэглэгчийн дугаар авах
     *
     * @return int
     */
    public static function getCurUserId()
    {
        if (auth()->user()) {
            return auth()->user()->id;
        }
        return 1;
    }

    /**
     * Хэрэглэгчийн байгууллагын дугаар авах
     *
     * @return int
     */
    public static function getCurInstId()
    {
        if (auth()->user()) {
            return auth()->user()->instid;
        }
        return null;
    }

    /**
     * Хэрэглэгчийн салбарын дугаар авах
     *
     * @return int
     */
    public static function getUserBrchno()
    {
        if (auth()->user()) {
            return auth()->user()->brchno;
        }
        return 1;
    }

    /**
     * Бүх жагсаалт дэлгэцийн хайлтын төрөл
     *
     */
    public static function getDefalutListFilterType()
    {
        $gp = GpInstGp::where('itemname', 'defalutListFilterType')->where('instid', self::getCurInstId())->first();
        if (empty($gp)) {
            return null;
        }
        return $gp->itemvalue;
    }

    /**
     * ЗМС ангилал буурсан зээлийн данс илгээх эсэх SendBadLoanCategoryRegistry
     *
     */
    public static function sendBadLoanCategoryRegistry()
    {
        $gp = GpInstGp::where('itemname', 'SendBadLoanCategoryRegistry')->where('instid', self::getCurInstId())->first();
        if (empty($gp)) {
            return null;
        }
        return $gp->itemvalue;
    }

        /**
     * ЗМС батлагдсан төлөвтэй зээлийн данс илгээх эсэх SendBadLoanCategoryRegistry
     *
     */
    public static function sendApprovedLoan()
    {
        $gp = GpInstGp::where('itemname', 'sendApprovedLoan')->where('instid', self::getCurInstId())->first();
        if (empty($gp)) {
            return null;
        }
        return $gp->itemvalue;
    }


    /**
     * Ерөнхий тохиргооноос мэдээлэл авах
     *
     * @param  double $instid
     * @param  string $itemname
     * @return string
     */
    public static function getInstGp($instid, $itemname)
    {
        $key = $itemname . "_" . $instid;
        $gp = Cache::rememberForever(
            $key,
            function () use ($instid, $itemname, $key) {
                $gp = GpInstGp::where('itemname', $itemname)->where('instid', $instid)->first();
                self::storeCacheKey($instid, $key, CacheGroupEnum::GP_inst_gp);
                return $gp;
            }
        );
        if (empty($gp)) {
            return null;
        }
        return $gp->itemvalue;
    }

    public static function getNextJrno()
    {
        return DB::transaction(function () {
            $GPinstseq = GpInstSeq::where('seqid', 'JRNO')
                ->where('instid', self::getCurInstId())->update([
                    'seqno' => DB::raw('seqno::int8 + 1')
                ]);
            if ($GPinstseq) {
                $GPinstseq = GpInstSeq::where('seqid', 'JRNO')
                    ->where('instid', self::getCurInstId())->first();
                return $GPinstseq->seqno;
            } else {
                throw new MeException(':field sequense авах үед алдаа гарлаа.', ['field' => 'JRNO']);
            }
        });
    }

    public static function getGlNextJrno()
    {
        $seq = DB::transaction(function () {
            $GPinstseq = GpInstSeq::where('seqid', 'GLJRNO')
                ->where('instid', self::getCurInstId())->update([
                    'seqno' => DB::raw('seqno::int8 + 1')
                ]);
            if ($GPinstseq) {
                $GPinstseq = GpInstSeq::where('seqid', 'GLJRNO')
                    ->where('instid', self::getCurInstId())->first();
                return $GPinstseq->seqno;
            } else {
                throw new MeException(':field sequense авах үед алдаа гарлаа.', ['field' => 'JRNO']);
            }
        });

        return fillZeroString($seq, "6");
    }

    /**
     * Гүйлгээний огноо авах
     *
     * @return string
     */
    public static function getTxnDate($instid)
    {
        // $key = 'TXN_DATE' . $instid;
        // if (!Cache::has($key)) {
        //     $GPinstseq = GpInstSeq::where('seqid', 'SYSDATE')
        //         ->where('instid', $instid)->first();
        //     if (empty($GPinstseq)) {
        //         return '1900-01-01';
        //     }
        //     Cache::put($key, $GPinstseq->seqno);
        //     self::storeCacheKey($instid, $key);
        // }
        // return Cache::get($key);
        $GPinstseq = GpInstSeq::where('seqid', 'SYSDATE')
            ->where('instid', $instid)->first();
        if (empty($GPinstseq)) {
            return '1900-01-01';
        }
        return substr($GPinstseq->seqno, 0, 10);
    }
    /**
     * Ерөнхий дэвтэрийн огноо авах
     *
     * @return string
     */
    public static function getGlDate($instid)
    {
        // $key = 'TXN_DATE' . $instid;
        // if (!Cache::has($key)) {
        //     $GPinstseq = GpInstSeq::where('seqid', 'SYSDATE')
        //         ->where('instid', $instid)->first();
        //     if (empty($GPinstseq)) {
        //         return '1900-01-01';
        //     }
        //     Cache::put($key, $GPinstseq->seqno);
        //     self::storeCacheKey($instid, $key);
        // }
        // return Cache::get($key);
        $GPinstseq = GpInstSeq::where('seqid', 'GLDATE')
            ->where('instid', $instid)->first();
        if (empty($GPinstseq)) {
            return '1900-01-01';
        }
        return substr($GPinstseq->seqno, 0, 10);
    }

    public static function addTxnDate($instid = null)
    {
        if (empty($instid)) {
            $instid = self::getCurInstId();
        }
        $key = 'TXN_DATE' . $instid;
        $GPinstseq = GpInstSeq::where('seqid', 'SYSDATE')
            ->where('instid', self::getCurInstId())->first();
        $nextdate = new Carbon($GPinstseq->seqno);
        $GPinstseq->seqno = $nextdate->addDay();
        Cache::put($key, $GPinstseq->seqno);
        $GPinstseq->save();
    }

    public static function getEodSysdate($instid)
    {
        $seq = GpInstSeq::where('instid', $instid)
            ->where('seqid', 'EODSYSDATE')->first();
        if (empty($seq)) {
            $txndate = CoreService::getTxnDate($instid);
        } else {
            $txndate = $seq->seqno;
        }
        return $txndate;
    }

    /**
     * Тухайн байгууллага дээр ямар түлхүүр үгээр cache хийсэн байгааг cache-д хадгална
     *
     * @param  mixed $instid    Байгууллагын дугаар
     * @param  string $itemkey  cache хийсэн түлхүүр
     * @param  string $group    cache түлхүүрүүдийг грүүп болгон ангилаж хадгална
     * @return void
     */
    public static function storeCacheKey($instid, $itemkey, $group = CacheGroupEnum::core)
    {
        $key = 'intitution_' . $instid;
        if (!Cache::has($key)) {
            Cache::put($key, [$group => [$itemkey]]);
        } else {
            $cacheKeys = Cache::get($key);
            if (isset($cacheKeys[$group])) {
                $cacheKeys[$group][] = $itemkey;
            } else {
                $cacheKeys[$group] = [$itemkey];
            }
            Cache::put($key, $cacheKeys);
        }
    }

    //
    public static function getCacheKey($instid, $group = CacheGroupEnum::core)
    {
        $key = 'intitution_' . $instid;
        if (empty($group)) {
            return Cache::get($key);
        } else {
            $data = Cache::get($key);
            if (isset($data[$group])) {
                return $data[$group];
            } else {
                return array();
            }
        }
    }


    /**
     * Тухайн грүүп дэх бүх cache цэвэрлэнэ.
     *
     * @param  mixed $instid
     * @param  mixed $group
     * @return void
     */
    public static function clearCacheDataWithGroup($instid, $group = CacheGroupEnum::core)
    {
        $cachekeys = self::getCacheKey($instid, $group);
        foreach ($cachekeys as $cachekey) {
            if (is_array($cachekey)) {
                foreach ($cachekey as $cachekey2) {
                    Cache::forget($cachekey2);
                }
            } else {
                Cache::forget($cachekey);
            }
        }
    }
}
