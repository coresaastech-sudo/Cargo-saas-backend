<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstCur extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_cur_list';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'curcode',
        'name',
        'name2',
        'avgrate',
        'gl',
        'listorder',
        'margintype',
        'marginup',
        'margindown',
        'endrate',
        'avgrateend',
        'midrate',
        'yeslimit',
        'ismetal',
        'isbase',
        'ismain',
        'marketrate',
        'valuedateterm',
        'showsidemenu',
        'showonline',
        'equivacct',
        'fxprof',
        'fxloss',
        'rvprof',
        'rvloss',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'avgrate'=>'float',
        'marginup'=>'float',
        'margindown'=>'float',
        'endrate'=>'float',
        'avgrateend'=>'float',
        'midrate'=>'float',
        'marketrate'=>'float',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
