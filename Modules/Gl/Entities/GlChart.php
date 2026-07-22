<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GlChart extends Model
{
    use HasFactory, Auditable;

    protected $table = 'gl_chart';
    protected $primaryKey = 'acntno';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['acntno', 'instid'];

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('acntno', '=', $this->getAttribute('acntno'))
            ->where('instid', '=', $this->getAttribute('instid'))
            ->where('statusid', '=', $this->getAttribute('statusid'));

        return $query;
    }

    protected $fillable = [
        'acntno',
        'name',
        'name2',
        'statusid',
        'listorder',
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
