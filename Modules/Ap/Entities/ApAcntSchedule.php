<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ApAcntSchedule extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'ap_acnt_schedules';

    public $fillable = [
        'schd_date',
        'amount',
        'int_amount',
        'total_amount',
        'theor_bal',
        'acnt_code',
        'id',
        'instid',
    ];

    protected $casts = [
        'schd_date' => 'date:Y-m-d',
        'amount' => 'double',
        'int_amount' => 'double',
        'total_amount' => 'double',
        'theor_bal' => 'double',
        'acnt_code',
        'id' => 'int',
        'instid' => 'int',
    ];
}
