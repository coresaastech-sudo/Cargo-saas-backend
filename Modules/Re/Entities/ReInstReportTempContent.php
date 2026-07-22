<?php

namespace Modules\Re\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Traits\Auditable;

class ReInstReportTempContent extends Model
{
    use HasFactory, Auditable;

    protected $table = 're_inst_report_temp_content';
    protected $primaryKey = 'id';

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
