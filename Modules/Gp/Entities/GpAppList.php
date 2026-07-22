<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpAppList extends Model
{
    use HasFactory;
    protected $table = 'GP_app_list';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = [
        'id',
        "app_name",
        "app_identifier",
        "app_secret",
        "app_data",
        "instid",
        "statusid",
        "created_by",
        "updated_by",
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'instid' => 'string',
        'app_identifier' => 'string',
    ];
}
