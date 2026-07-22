<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpLogRequestList extends Model
{
    use HasFactory;

    protected $table = 'log_requests';

    protected $fillable = [
        'userid',
        'request',
        'response',
        'url',
        'ip',
        'created_at',
        'updated_at',
        'responsecode',
        'responsetime',
        'method',
        'AC',
        'instid',
        'actor_userid',
    ];
}
