<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\JobProcessEnum;

class AdBatchTxn extends Model
{
    use HasFactory;

    protected $table = 'ad_batch_txn';

    protected $fillable = [
        'fileid',
        'filename',
        'txncount',
        'txnsuccesscount',
        'txnerrorcount',
        'process',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    protected $appends = [
        'countinfo',
        'processname'
    ];

    public function getCountinfoAttribute()
    {
        return "Нийт: $this->txncount, Амжилттай: $this->txnsuccesscount, Алдаатай: $this->txnerrorcount";
    }

    public function getProcessnameAttribute()
    {
        return __('messages.' . JobProcessEnum::toString($this->process));
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
