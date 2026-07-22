<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwInstRolePermList extends Model
{
    use HasFactory;
    protected $table = 'vw_inst_role_perm_list';
    public $timestamps = false;
    protected $primaryKey = 'ACTION_CODE';
    protected $fillable = [
        "ACTION_CODE",
        "name",
        "name2",
        "statusid",
        "roleid",
    ];

    protected $casts = [
        'id' => 'string',
        "ACTION_CODE" => 'string',
    ];
}
