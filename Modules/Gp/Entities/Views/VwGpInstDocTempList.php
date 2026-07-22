<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwGpInstDocTempList extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_doc_temp_list';
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
}
