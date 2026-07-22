<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpActionCode extends Model
{
    use HasFactory;
    protected $table = 'GP_ACTION_CODE';
    public $timestamps = false;
    protected $primaryKey = 'ACTION_CODE';
    protected $fillable = [
        "ACTION_CODE",
        "name",
        "name2",
        "controller",
        "function",
        "statusid",
        'txnopt'
    ];

    protected $casts = [
        'id' => 'string',
        'txnopt' => 'integer',
        "ACTION_CODE" => 'string',
    ];
}
