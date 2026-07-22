<?php

namespace Modules\Gp\Entities;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Auth\Authenticatable as AuthAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Modules\Gp\Traits\Auditable;

class GpInstUserIp extends Model implements Authenticatable
{
    use HasFactory, AuthAuthenticatable, Auditable;

    protected $table = 'GP_inst_user_ip';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'id';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'ip_address',
        'userid',
        'instid',
        'statusid',
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
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
