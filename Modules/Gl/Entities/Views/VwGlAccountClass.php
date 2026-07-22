<?php

namespace Modules\Gl\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGlAccountClass extends Model
{
    use HasFactory;

    protected $table = 'vw_gl_acnt_class_list';
    protected $primaryKey = 'class';
    public $incrementing = false;
    protected $keyType = 'bigInteger';


    protected $fillable = [
        'class',
        'name',
        'name2',
        'type',
        'balmoving',
        'listorder',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $auditExclude = [
        'class',
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }
    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
