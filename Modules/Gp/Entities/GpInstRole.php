<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpInstRole extends Model
{
    use HasFactory;
    protected $table = 'GP_inst_role';
    protected $fillable = [
        'rolename',
        'rolename2',
        'statusid',
        'listorder',
        'instid',
        'isadmin',
        'created_by',
        'updated_by',
    ];

}
