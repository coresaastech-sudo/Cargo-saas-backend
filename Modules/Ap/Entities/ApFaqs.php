<?php

namespace Modules\Ap\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ApFaqs extends Model
{
    use HasFactory;

    protected $table = 'ap_faqs';

    protected $fillable = [
        'id',
        'question', 
        'question2', 
        'answer', 
        'answer2',
        'listorder', 
        'statusid',
        'instid',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_by',
        'updated_by'
    ];
}
