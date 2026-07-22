<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpApiActionCode extends Model
{
    use HasFactory;
    protected $table = 'GP_api_ACTION_CODE';
    public $timestamps = false;
    protected $primaryKey = 'api_ACTION_CODE';
    protected $fillable = [
        "api_ACTION_CODE",
        "name",
        "name2",
        "controller",
        "function",
        "statusid",
        'txnopt',
        "route",
        "ACTION_CODE"
    ];

    protected $casts = [
        'txnopt' => 'integer',
        "api_ACTION_CODE" => 'string',
    ];
}
