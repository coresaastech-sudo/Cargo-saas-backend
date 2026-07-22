<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlBalanceHist extends Model
{
    use HasFactory;

    protected $table = 'gl_balance_hist';
    public $incrementing = false;
    protected $primaryKey = 'account';

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('branch', '=', $this->getAttribute('branch'))
            ->where('unit', '=', $this->getAttribute('unit'))
            ->where('account', '=', $this->getAttribute('account'))
            ->where('currency', '=', $this->getAttribute('currency'))
            ->where('year', '=', $this->getAttribute('year'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $fillable = [
        'branch',
        'unit',
        'account',
        'currency',
        'year',
        'obal',
        'dt01',
        'ct01',
        'dt02',
        'ct02',
        'dt03',
        'ct03',
        'dt04',
        'ct04',
        'dt05',
        'ct05',
        'dt06',
        'ct06',
        'dt07',
        'ct07',
        'dt08',
        'ct08',
        'dt09',
        'ct09',
        'dt10',
        'ct10',
        'dt11',
        'ct11',
        'dt12',
        'ct12',
        'dt13',
        'ct13',
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
        'obal' => 'double',
        'dt01' => 'double',
        'ct01' => 'double',
        'dt02' => 'double',
        'ct02' => 'double',
        'dt03' => 'double',
        'ct03' => 'double',
        'dt04' => 'double',
        'ct04' => 'double',
        'dt05' => 'double',
        'ct05' => 'double',
        'dt06' => 'double',
        'ct06' => 'double',
        'dt07' => 'double',
        'ct07' => 'double',
        'dt08' => 'double',
        'ct08' => 'double',
        'dt09' => 'double',
        'ct09' => 'double',
        'dt10' => 'double',
        'ct10' => 'double',
        'dt11' => 'double',
        'ct11' => 'double',
        'dt12' => 'double',
        'ct12' => 'double',
        'dt13' => 'double',
        'ct13' => 'double',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
