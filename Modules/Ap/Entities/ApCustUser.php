<?php

namespace Modules\Ap\Entities;

use App\Exceptions\MeException;
use App\Models\Model;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthAuthenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Hash;
use Modules\Gp\Entities\GPUserPassHist;
use Modules\Gp\Traits\Auditable;

class ApCustUser extends Model implements Authenticatable
{
    use HasFactory, AuthAuthenticatable, Auditable;

    protected $table = 'ap_cust_user';

    protected $fillable = [
        'id',
        'email',
        'phone',
        'password',
        'passdate',
        'passwrong',
        'regno',
        'iprest',
        'mustchGPss',
        'passtoken',
        'passtokendate',
        'passtokenstatus',
        'firstname',
        'lastname',
        'use_google_auth',
        'google_auth_key',
        'password_changed_at',
        'photo_url',
        'address',
        'region',
        'subregion',
        'device_token',
        'use_auth_type',
        'app_id',
        'ebarimt_consumerno',
        'statusid',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'passwrong',
        'passtoken',
        'google_auth_key',
        'password_changed_at',
        'device_token'
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

    public function passwordHistories()
    {
        return $this->hasMany(GPUserPassHist::class, 'userid', 'id')->where('GP_user_passhist.type', 'APP');
    }

    public function changePassword($newPassword)
    {
        $newPassword = Hash::make($newPassword);
        if ($this->password == $newPassword || $this->passwordHistories()->where('password', $newPassword)->first()) {
            throw new MeException("RC000007");
        }
        $this->passwordHistories()->create([
            'userid' => $this->userid,
            'passdate' => $this->passdate,
            'password' => $this->password,
            'type' => 'APP'
        ]);
        $this->password = $newPassword;
        $this->passdate = getNow();
        $this->passtokenstatus = 0;
        $this->save();
    }

}
