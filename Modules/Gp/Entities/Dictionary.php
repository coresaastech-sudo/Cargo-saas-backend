<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dictionary extends Model
{
    protected $table = 'gp_dictionaries';

    protected $fillable = [
        'organization_id',
        'dictionary_code',
        'name',
        'description',
        'status',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(DictionaryItem::class);
    }
}
