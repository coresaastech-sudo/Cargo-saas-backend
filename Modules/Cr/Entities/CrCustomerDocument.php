<?php

namespace Modules\Cr\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrCustomerDocument extends Model
{
    use HasFactory;

    protected $table = 'cr_customer_documents';

    protected $fillable = [
        'id',
        'customer_id',
        'document_type',
        'document_no',
        'file_path',
        'issued_at',
        'expires_at',
        'status',
        'organization_id',
        'branch_id',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

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
