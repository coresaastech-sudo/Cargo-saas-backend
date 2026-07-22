<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GpInstSeq extends Model
{
    use HasFactory, Auditable;

    protected $table = 'GP_inst_seq';
    protected $primaryKey = 'seqid';
    protected $keyType = 'string';

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('seqid', '=', $this->getAttribute('seqid'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $fillable = [
        'seqid',
        'seqno',
        'instid',
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
        'seqno' => 'string',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];

    public function getIdAttribute()
    {
        return $this->seqid;
    }
}
