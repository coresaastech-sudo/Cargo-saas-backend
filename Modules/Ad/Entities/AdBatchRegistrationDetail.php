<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdBatchRegistrationDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'batchregistrationid',
        'rowid',
        'txncode',
        'description',
        'requestdata',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
