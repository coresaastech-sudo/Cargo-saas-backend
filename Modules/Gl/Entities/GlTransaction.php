<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class GlTransaction extends Model
{
    use HasFactory, Auditable;

    protected $table = 'gl_transaction';
    public $incrementing = false;
    protected $primaryKey = 'journal';

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('journal', '=', $this->getAttribute('journal'))
            ->where('entry', '=', $this->getAttribute('entry'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $fillable = [
        'journal',
        'entry',
        'year',
        'period',
        'day',
        'branch',
        'unit',
        'currency',
        'account',
        'amount',
        'description',
        'correctoin',
        'statusid',
        'postdate',
        'tellerno',
        'txndate',
        'isclosebalance',
        'instid',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
        'amount' => 'float',
    ];

}
