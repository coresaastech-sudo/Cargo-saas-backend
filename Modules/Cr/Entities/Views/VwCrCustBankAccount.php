<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCrCustBankAccount extends Model
{
    use HasFactory;
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $table = 'vw_cr_cust_bank_account';
    protected $fillable = [];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [];
}
