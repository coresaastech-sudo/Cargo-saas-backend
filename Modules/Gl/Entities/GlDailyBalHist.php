<?php

namespace Modules\Gl\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GlDailyBalHist extends Model
{
    use HasFactory;

    protected $table = 'gl_daily_bal_hist';
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
            ->where('period', '=', $this->getAttribute('period'))
            ->where('instid', '=', $this->getAttribute('instid'));

        return $query;
    }

    protected $fillable = [
        'branch',
        'unit',
        'account',
        'currency',
        'year',
        'period',
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
        'dt14',
        'ct14',
        'dt15',
        'ct15',
        'dt16',
        'ct16',
        'dt17',
        'ct17',
        'dt18',
        'ct18',
        'dt19',
        'ct19',
        'dt20',
        'ct20',
        'dt21',
        'ct21',
        'dt22',
        'ct22',
        'dt23',
        'ct23',
        'dt24',
        'ct24',
        'dt25',
        'ct25',
        'dt26',
        'ct26',
        'dt27',
        'ct27',
        'dt28',
        'ct28',
        'dt29',
        'ct29',
        'dt30',
        'ct30',
        'dt31',
        'ct31',
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
        'dt14' => 'double',
        'ct14' => 'double',
        'dt15' => 'double',
        'ct15' => 'double',
        'dt16' => 'double',
        'ct16' => 'double',
        'dt17' => 'double',
        'ct17' => 'double',
        'dt18' => 'double',
        'ct18' => 'double',
        'dt19' => 'double',
        'ct19' => 'double',
        'dt20' => 'double',
        'ct20' => 'double',
        'dt21' => 'double',
        'ct21' => 'double',
        'dt22' => 'double',
        'ct22' => 'double',
        'dt23' => 'double',
        'ct23' => 'double',
        'dt24' => 'double',
        'ct24' => 'double',
        'dt25' => 'double',
        'ct25' => 'double',
        'dt26' => 'double',
        'ct26' => 'double',
        'dt27' => 'double',
        'ct27' => 'double',
        'dt28' => 'double',
        'ct28' => 'double',
        'dt29' => 'double',
        'ct29' => 'double',
        'dt30' => 'double',
        'ct30' => 'double',
        'dt31' => 'double',
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
}
