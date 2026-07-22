<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpInstConst extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'GP_const';

    protected $fillable = [
        "id",
        'instid',
        "code",
        "value",
        "name",
        "name2",
        "value_add1",
        "value_add2",
        "parent_code",
        "listorder",
        "statusid",
        "is_show_front",
        "created_by",
        "created_at"
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'date:Y-m-d H:i:s',
        'id'         => 'integer',
        'listorder'  => 'integer',
        'statusid'   => 'integer',
        'is_show_front' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];
}
