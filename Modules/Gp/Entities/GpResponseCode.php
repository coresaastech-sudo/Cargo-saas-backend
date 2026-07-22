<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpResponseCode extends Model
{
    use HasFactory;
    protected $table = 'GP_response_msg';
    public $timestamps = false;
    protected $primaryKey = 'code';
    protected $fillable = [
        "code",
        "name",
        "name2",
        "allowsvp",
        "msg_type",
        "statusid",
    ];

    protected $casts = [
        'id' => 'string',
        "code" => 'string',
    ];
}
