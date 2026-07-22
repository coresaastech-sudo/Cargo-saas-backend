<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstDocTempActionCode extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_doc_temp_ACTION_CODE';


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

    public function docTemp()
    {
        return $this->belongsTo(GpInstDocTemp::class, 'doctempid', 'id');
    }

}

