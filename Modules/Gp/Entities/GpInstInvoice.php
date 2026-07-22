<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GpInstInvoice extends Model
{
    use HasFactory;

    protected $table = 'GP_inst_invoice';

    protected $fillable = [
        'id',
        'invoiceno',
        'startdate',
        'enddate',
        'base_amount',
        'inflation_rate',
        'discount_amount',
        'tax_amount',
        'invoice_amount',
        'expirydate',
        'freq',
        'cutoffday',
        'gracepriod',
        'description',
        'is_sendmail',
        'paid_amount',
        'paiddate',
        'taxid',
        'bankaccountno',
        'error',
        'apfee',
        'instid',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'startdate' => 'date:Y-m-d',
        'enddate' => 'date:Y-m-d',
        'expirydate' => 'date:Y-m-d',
        'paiddate' => 'date:Y-m-d',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'base_amount' => 'float',
        'inflation_rate' => 'float',
        'discount_amount' => 'float',
        'tax_amount' => 'float',
        'invoice_amount' => 'float',
        'paid_amount' => 'float',
        'apfee' => 'float',
    ];
}
