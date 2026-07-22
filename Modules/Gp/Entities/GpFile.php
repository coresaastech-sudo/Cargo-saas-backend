<?php

namespace Modules\Gp\Entities;

use App\Traits\Uuids;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpFile extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'name',
        'file',
        'created_by',
        'updated_by',
        'instid',
        'type',
    ];

}
