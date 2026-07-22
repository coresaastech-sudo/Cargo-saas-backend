<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpInstRolePerms extends Model
{
    use HasFactory;

    protected $fillable = [
        'roleid',
        'AC',
        'isadmin',
        'statusid',
        'created_by',
        'updated_by',
    ];

}
