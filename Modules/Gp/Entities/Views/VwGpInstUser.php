<?php

namespace Modules\Gp\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VwGpInstUser extends Model
{
    use HasFactory;
    protected $table = 'vw_inst_user_list';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'inst_name',
        'inst_name2',
        'username',
        'instid',
        'email',
        'phone',
        'statusid',
        'created_by',
        'updated_by',
        'isadmin',
        'regno',
        'iprest',
        'startdate',
        'enddate',
        'mustchGPss',
        'name',
        'lname',
        'brchno',
        'created_at',
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
        'use_google_auth' => 'integer'
    ];
    protected $hidden = [
        'password',
        'passwrong',
        'isadmin'
    ];
}
