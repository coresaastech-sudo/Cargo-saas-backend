<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpInstPerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'instid',
        'moduleid',
        'AC',
        'isadmin',
        'statusid',
        'created_by',
        'updated_by',
    ];
}
