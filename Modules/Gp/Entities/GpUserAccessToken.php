<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Ap\Entities\ApCustUser;

class GpUserAccessToken extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [
        'userid',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'created_at',
        'updated_at',
        'channel',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(CustUser::class, 'userid', 'id');
    // }

    public function backUser()
    {
        return $this->belongsTo(GpInstUser::class, 'userid', 'id');
    }

    public function meAppUser()
    {
        return $this->belongsTo(ApCustUser::class, 'userid', 'id');
    }

    public function getAuthIdentifier()
    {
        return $this->getAttribute('userid');
    }
}
