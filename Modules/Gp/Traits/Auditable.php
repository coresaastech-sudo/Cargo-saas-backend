<?php

namespace Modules\Gp\Traits;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Queue;
use Modules\Gp\Jobs\AuditLogJob;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function ($model) {
            self::logAction($model, 'create');
        });

        static::updated(function ($model) {
            self::logAction($model, 'update');
        });

        static::deleted(function ($model) {
            self::logAction($model, 'delete');
        });
    }

    public function scopeWithAudits(Builder $query)
    {
        return $query->with(['audits']);
    }

    protected static function logAction($model, $actionType)
    {

        $user = auth()->user();
        $userId = ($user && gettype($user) != 'string') ? $user->id : 0;
        $instId = ($user && gettype($user) != 'string') ? $user->instid : 0;
        $objectid = $model->{$model->primaryKey} ?? '0';
        $parent_objectid = $model->{$model->parentObjectField} ?? null;
        $AC = request()->header('AC') ? request()->header('AC') : null;
        $ip = request()->ip() ? request()->ip() : null;
        $originalAttributes = $model->getOriginal();
        $updatedAttributes = $model->getAttributes();
        $modelNamespace = get_class($model);
        Queue::pushOn(
            'AuditLogJob',
            new AuditLogJob(
                $modelNamespace,
                $actionType,
                $userId,
                $instId,  
                $objectid,
                $parent_objectid,
                $AC,
                $ip,
                $originalAttributes,
                $updatedAttributes
            )
        );
    }
}
