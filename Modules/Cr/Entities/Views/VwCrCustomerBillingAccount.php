<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCrCustomerBillingAccount extends Model
{
    use HasFactory;

    protected $table = 'vw_cr_customer_billing_accounts';

    protected $fillable = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
