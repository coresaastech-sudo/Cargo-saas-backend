<?php

namespace Modules\Cr\Entities;

use App\Traits\Uuids;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CrCustImage extends Model
{
    use Uuids;
    use HasFactory;

    protected $table = 'cr_cust_image';

    protected $fillable = [
        'name',
        'filename',
        'image',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

}
