<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstDocTemp extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_doc_temp';


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

    public function docTempFormInput()
    {
        return $this->hasMany(GpInstDocTempFormInput::class, 'doctempid', 'id')->where("statusid", 1);
    }

    public function docTempVar()
    {
        return $this->hasMany(GpInstDocTempVar::class, 'doctempid', 'id')->where("statusid", 1);
    }
}
