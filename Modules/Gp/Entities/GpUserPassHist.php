<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpUserPassHist extends Model
{
    use HasFactory;

    protected $table = 'GP_user_passhist';
    public $timestamps = false;

    protected $fillable = [
        'userid',
        'password',
        'passdate',
        'createdate',
        'type'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'userid' => 'integer',
    ];
}
