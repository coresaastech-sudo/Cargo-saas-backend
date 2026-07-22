<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\JobProcessEnum;

class AdBatchRegistration extends Model
{
    use HasFactory;

    protected $fillable = [

        'filename',
        'requestdata',
        'time',
        'count',
        'errorcount',
        'successcount',
        'size',
        'errordesc',
        'statusid',
        'created_by',
        'updated_by',
        'instid'
    ];

    protected $appends = [
        'countinfo',
        'processname'
    ];

    protected $hidden = [
        'requestdata',
    ];

    public function getCountinfoAttribute()
    {
        return "Нийт: $this->count, Амжилттай: $this->successcount, Алдаатай: $this->errorcount";
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
