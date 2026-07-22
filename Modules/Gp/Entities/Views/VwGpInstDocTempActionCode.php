<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Entities\GpInstDocTemp;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstDocTempActionCode extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_doc_temp_ActionCode';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'doctempid',
        'ACTION_CODE',
        'response_type',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }

    public function docTemp()
    {
        return $this->belongsTo(GpInstDocTemp::class, 'doctempid', 'id');
    }

}
