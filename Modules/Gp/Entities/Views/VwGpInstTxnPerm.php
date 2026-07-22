<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwGpInstTxnPerm extends Model
{
    use HasFactory;
    protected $table = 'vw_GP_inst_txn_perms';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "name",
        "name2",
        "instid",
        "txnopt",
        "qualfier",
        "txntype",
        "isadmin",
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'instid' => 'integer',
        'isadmin' => 'integer',
    ];
}
