<?php

namespace Modules\Ad\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwAdRecResAccountBalCalc extends Model
{
    use HasFactory;

    protected $table = 'vw_ad_rec_res_account_bal_calc';

    protected $fillable = [];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'resdate' => 'date:Y-m-d',
        'per' => 'integer',
        'princbal' => 'float',
        'newresbal' => 'float',
        'balance' => 'float',
        'recbalance' => 'float',
        'resbal' => 'float',
        'amount' => 'float',
    ];
}
