<?php

namespace Modules\Cr\Entities;

use App\Models\Model;

class CustomerContact extends Model
{
    protected $table = 'cr_customer_contacts';

    protected $fillable = [
        'customer_id',
        'contact_type',
        'value',
        'is_primary',
        'status',
    ];
}
