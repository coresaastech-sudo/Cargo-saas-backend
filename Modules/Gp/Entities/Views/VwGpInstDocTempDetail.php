<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Entities\GpInstDocTempFormInput;
use Modules\Gp\Entities\GpInstDocTempVar;
use Modules\Gp\Enums\StatusCodeEnum;

class VwGpInstDocTempDetail extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_doc_temp_detail';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'name2',
        'template',
        'doctype',
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

    public function docTempFormInput()
    {
        return $this->hasMany(GpInstDocTempFormInput::class, 'doctempid', 'id')->where("statusid", 1);
    }

    public function docTempVar()
    {
        return $this->hasMany(GpInstDocTempVar::class, 'doctempid', 'id')->where("statusid", 1);
    }

}
