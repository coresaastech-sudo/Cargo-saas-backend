<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $table = 'gp_organizations';

    protected $fillable = [
        'organization_code',
        'name',
        'name2',
        'register_no',
        'phone',
        'email',
        'status',
        'settings',
    ];

    protected $casts = ['settings' => 'array'];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
