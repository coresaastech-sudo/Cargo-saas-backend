<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GlAccountClass extends Model
{
    use HasFactory, Auditable;

    protected $table = 'gl_account_class';
    protected $primaryKey = 'class';
    public $incrementing = false;
    protected $keyType = 'bigInteger';
    protected $guarded = ['class', 'instid'];

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('class', '=', $this->getAttribute('class'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $fillable = [
        'class',
        'name',
        'name2',
        'type',
        'balmoving',
        'listorder',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    /**
     *Theattributesthatshouldbecast.
     *
     *@vararray
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
