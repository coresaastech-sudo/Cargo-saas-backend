<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpFailedJobsList extends Model
{
    use HasFactory;

    protected $table = 'failed_jobs';

    protected $fillable = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'failed_at' => 'date:Y-m-d H:i:s',
    ];
}
