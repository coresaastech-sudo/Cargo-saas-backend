<?php

namespace Modules\Ad\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdResAccountBal extends Model
{
    use HasFactory;
    public $table = 'ad_res_account_bal';

    protected $fillable = [
        'acntno',
        'acnttype',
        'balance',
        'clscode',
        'resdate',
        'resbal',
        'rescur',
        'res_acntno',
        'res_acnttype',
        'cont_acntno',
        'cont_acnttype',
        'amount',
        'rescls',
        'errordesc',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'balance' => 'float',
        'resbal' => 'float',
        'amount' => 'float',
        'resdate' => 'date:Y-m-d',
    ];
}
