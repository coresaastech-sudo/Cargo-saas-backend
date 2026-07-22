<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $table = 'gp_branches';

    protected $fillable = [
        'organization_id',
        'branch_code',
        'name',
        'phone',
        'email',
        'address',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
