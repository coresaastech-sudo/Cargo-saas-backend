<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstDocTempFormInput extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_doc_temp_form_input';


    protected $fillable = [
        'id',

        'doctempid',
        'title',
        'input',
        'forminputtype',
        'dropdowndic',
        'length',

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
}
