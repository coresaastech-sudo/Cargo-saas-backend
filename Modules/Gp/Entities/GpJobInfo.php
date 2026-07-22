<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpJobInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'job',
        'successcount',
        'jobcount',
        'statusid',
        'lastexecdate',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];
}
