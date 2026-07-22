<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GlAccount extends Model
{
    use HasFactory, Auditable;

    protected $table = 'gl_account';
    protected $primaryKey = 'acntno';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['acntno', 'instid', 'statusid'];

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('acntno', '=', $this->getAttribute('acntno'))
            ->where('statusid', '=', $this->getAttribute('statusid'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $fillable = [
        'acntno',
        'class',
        'name',
        'name2',
        'type',
        'statusid',
        'listorder',
        'addinfo',
        'addinfo2',
        'catcode',
        'centerbankaccount',
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
        'acntno'  => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
