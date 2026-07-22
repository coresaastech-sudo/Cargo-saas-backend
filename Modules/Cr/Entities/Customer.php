<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $table = 'cr_customers';

    protected $fillable = [
        'organization_id',
        'customer_code',
        'customer_type',
        'name',
        'phone',
        'email',
        'register_no',
        'status',
        'created_by',
        'updated_by',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }
}
