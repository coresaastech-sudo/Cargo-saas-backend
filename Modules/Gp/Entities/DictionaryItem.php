<?php

namespace Modules\Gp\Entities;

use App\Models\Model;

class DictionaryItem extends Model
{
    protected $table = 'gp_dictionary_items';

    protected $fillable = [
        'dictionary_id',
        'item_code',
        'name',
        'value',
        'sort_order',
        'status',
    ];

    protected $casts = ['value' => 'array'];
}
