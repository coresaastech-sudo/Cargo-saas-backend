<?php

namespace Modules\Gl\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGlAccount extends Model
{
    use HasFactory;

    protected $table = 'vw_gl_acnt_list';
    protected $primaryKey = 'acntno';
    public $incrementing = false;
    protected $keyType = 'string';


    protected $fillable = [
        'acntno',
        'class',
        'name',
        'name2',
        'type',
        'statusid',
        'listorder',
        'addinfo',
        'addinfo2',
        'catcode',
        'centerbankaccount',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $auditExclude = [
        'acntno',
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
        'acntno'  => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
