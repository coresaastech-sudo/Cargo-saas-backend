<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpDicMain extends Model
{
    use HasFactory;
    protected $primaryKey = 'dic_code';
    public $timestamps = false;
    protected $fillable = [
        'dic_code',
        'vw_name',
        'description'
    ];
    protected $casts = [
        'id' => 'string',
        "dic_code" => 'string',
    ];

}
