<?php

namespace Modules\Ap\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Model;
use Modules\Gp\Traits\Auditable;

class ApDanResponse extends Model
{
    use HasFactory, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    public $table = "ap_dan_response";
    public $primaryKey = "id";
    public $timestamps = false;

    public $fillable = [
        'code',
        'access_token',
        'statusid',
        'description',
        'isused',
        'userid',
        'services',
        'instid',
        'created_at',
        'created_by',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'date:Y-m-d H:i:s',
        'created_by' => 'integer',
    ];
}
