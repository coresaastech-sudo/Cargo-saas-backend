<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstBrch extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_branch';
    protected $primaryKey = 'brchno';

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('brchno', '=', $this->getAttribute('brchno'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $keyType = 'string';

    protected $fillable = [
        'brchno',
        'name',
        'name2',
        'dirname',
        'dirname2',
        'begindate',
        'phone',
        'fax',
        'email',
        'isonline',
        'bankcode',
        'blevel',
        'biccode',
        'doestrade',
        'listorder',
        'state',
        'region',
        'subregion',
        'address',
        'zipcode',
        'w3w',
        'instid',
        'taxregion',
        'taxsubregion',
        'statusid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $appends = ['id'];
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];

    public function getIdAttribute()
    {
        return $this->brchno;
    }
}
