<?php

namespace Modules\Re\Entities\Views;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\StatusCodeEnum;
use Modules\Re\Entities\ReInstReportTempContent;

class VwReInstReportTempContent extends Model
{
    use HasFactory;
    protected $table = 'vw_re_inst_report_temp_content';
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'templateid',
        'type',
        'contentname',
        'parentid',
        'source',
        'richtext',
        'orientation',
        'x',
        'y',
        'contentmargin',
        'height',
        'width',
        'bordertypes',
        'bordercolor',
        'borderwidth',
        'highlightcolor',
        'maincolor',
        'alternativecolor',
        'tableheaderrepeat',
        'colcount',
        'relatedparamid',
        'colwidth',
        'align',
        'headerfontsize',
        'datafontsize',
        'textcolor',
        'verticalalign',
        'hasfooter',
        'cellexpression',
        'frameinfo',
        'framepos',
        'excelshift',
        'position',
        'hasheader',
        'listorder',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . StatusCodeEnum::toString($this->statusid));
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'date:Y-m-d H:i:s',
        'created_at' => 'date:Y-m-d H:i:s',
    ];
    public function children()
    {
        return $this->hasMany(ReInstReportTempContent::class, 'parentid', 'id')->where("statusid", 1)->orderBy("listorder", 'ASC');
    }
}
