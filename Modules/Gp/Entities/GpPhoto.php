<?php

namespace Modules\Gp\Entities;

use App\Traits\Uuids;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpPhoto extends Model
{
    use Uuids;
    use HasFactory;

    protected $fillable = [
        'name',
        'photo',
        'created_by',
        'updated_by',
    ];

}
