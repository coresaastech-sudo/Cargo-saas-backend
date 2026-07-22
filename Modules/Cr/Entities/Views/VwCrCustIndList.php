<?php

namespace Modules\Cr\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwCrCustIndList extends Model
{
    use HasFactory;
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $table = 'vw_cr_custind_lists';
    protected $fillable = [
        'id',
        'custno',
        'segcode',
        'birthdate',
        'lname',
        'lname2',
        'name',
        'name2',
        'bl',
        'id1',
        'custtypecode',
        'hidden',
        'instid',
        'statusid',
        'phone',
        'loancount',
        'brchno',
        'brchno_name',
        'txndate',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'txndate' => 'date:Y-m-d',
    ];
}
