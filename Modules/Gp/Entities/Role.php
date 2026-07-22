<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'gp_roles';

    protected $fillable = [
        'organization_id',
        'role_code',
        'name',
        'description',
        'is_admin',
        'status',
    ];

    protected $casts = ['is_admin' => 'boolean'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'gp_user_roles')
            ->withPivot(['organization_id', 'status'])
            ->withTimestamps();
    }

    public function actions(): BelongsToMany
    {
        return $this->belongsToMany(ActionDefinition::class, 'gp_role_actions', 'role_id', 'action_code')
            ->withTimestamps();
    }
}
