<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpModuleList extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $primaryKey = 'moduleid';
    protected $table = 'GP_module_list';

    protected $fillable = [
        "moduleid",
        "parentid",
        "name",
        "name2",
        "weburl",
        "webversion",
        "moduleversion",
        "listorder",
        "typeid",
        "statusid",
        "AC",
        'isadmin',
    ];
    protected $casts = [
        'id' => 'string',
        "moduleid" => 'string',
        "isadmin" => 'integer',
    ];

}
