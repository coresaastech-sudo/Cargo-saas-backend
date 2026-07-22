<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpResponseMsg extends Model
{
    use HasFactory;
    protected $table = 'GP_response_msg';
    public $timestamps = false;
    protected $fillable = [];

}
