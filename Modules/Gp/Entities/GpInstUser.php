<?php

namespace Modules\Gp\Entities;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Auth\Authenticatable as AuthAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Modules\Gp\Traits\Auditable;

class GpInstUser extends Model implements Authenticatable
{
    use HasFactory, AuthAuthenticatable, Auditable;

    protected $table = 'GP_inst_user';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $parentObjectField = 'id';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'username',
        'instid',
        'email',
        'phone',
        'statusid',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'passdate',
        'passwrong',
        'isadmin',
        'regno',
        'iprest',
        'passwordexp',
        'startdate',
        'enddate',
        'mustchGPss',
        'name',
        'lname',
        'brchno',
        'use_google_auth',
        'google_auth_key',
        'password',
        'password_changed_at',
        'passtoken',
        'passtokendate',
        'passtokenstatus',
        'tokenlimit',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'startdate' => 'date:Y-m-d H:i:s',
        'enddate' => 'date:Y-m-d H:i:s',
        'isadmin' => 'integer',
        'use_google_auth' => 'integer'
    ];
    protected $hidden = [
        'password',
        // 'passwrong',
        'passtoken'
    ];

    public function passwordHistories()
    {
        return $this->hasMany(GpUserPassHist::class, 'userid', 'id')->where('GP_user_passhist.type', 'BACK');
    }

    public function changePassword($newPassword)
    {
        $newPassword = Hash::make($newPassword);
        $this->passwordHistories()->create([
            'userid' => $this->userid,
            'passdate' => $this->passdate,
            'password' => $this->password,
            'createdate' => getNow(),
            'type' => 'BACK'
        ]);
        $this->password = $newPassword;
        $this->passdate = getNow();
        $this->passtokenstatus = 0;
        $this->save();
    }
}
