<?php

namespace Modules\Gp\Entities;

use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Gp\Enums\JobProcessEnum;

class GpDbBackupLog extends Model
{
    use HasFactory;
    protected $table = 'GP_db_backup_log';
    protected $fillable = [
        'path',
        'time',
        'size',
        'errordesc',
        'statusid',
        'instid',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['statusname'];

    public function getStatusnameAttribute()
    {
        return __('messages.' . JobProcessEnum::toString($this->statusid));
    }

    protected $casts = [];

}
