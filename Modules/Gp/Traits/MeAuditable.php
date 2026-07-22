<?php

namespace Modules\Gp\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

use Modules\Gp\Entities\GpAuditLog;
use Modules\Gp\Entities\GpAuditLogDetail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait MeAuditable
{
    public static function bootMeAuditable()
    {
        Log::debug(static::class);
        // self::bootModelLog();
        static::created(function ($model) {
            self::logAction($model, 'create');
        });

        static::saved(function ($model) {
            self::logAction($model, 'save');
        });

        static::updated(function ($model) {
            self::logAction($model, 'update');
        });

        static::deleted(function ($model) {
            self::logAction($model, 'delete');
        });

        static::creating(function ($model) {
            Log::debug($model);
        });

        static::updating(function ($model) {
            Log::debug($model);
        });

        static::saving(function ($model) {
            Log::debug($model);
            self::logAction($model, 'saving');
        });

        static::deleting(function ($model) {
            Log::debug($model);
        });
    }

    public function audit($action, $log = null)
    {
        $info = array_intersect_key($this->toArray(), array_flip($this->getInfo()));

        Log::debug($info);
        // $this->audits()->create([
        //     'action' => $action,
        //     'user' => Auth::user() ? Auth::user()->{Config::get('audit.user')} : null,
        //     'log' => $log,
        //     'info' => $info,
        //     'ip' => Request::ip() ?: '127.0.0.1'
        // ]);

        return $this;
    }

    public function scopeWithAudits(Builder $query)
    {
        return $query->with(['audits']);
    }

    protected static function logAction($modelOrQuery, $actionType)
    {
        Log::debug('$modelOrQuery');
        Log::debug($modelOrQuery);

        if ($modelOrQuery instanceof Builder) {
            $models = $modelOrQuery->get();
        } else {
            $models = [$modelOrQuery];
        }

        foreach ($models as $model) {
            $modelNamespace = get_class($model);

            $AuditLog = GpAuditLog::create([
                'userid' => auth()->user() ? auth()->user()->id : null,
                'instid' => auth()->user() ? auth()->user()->instid : null,
                'ip' => request()->ip() ? request()->ip() : null,
                'AC' => request()->header('AC') ? request()->header('AC') : null,
                'parent_objectid' => $model->{$model->parentObjectField} ?? null,
                'objectid' => $model->{$model->primaryKey},
                'object_type' => $modelNamespace,
                'action_type' => $actionType,
            ]);

            $originalAttributes = $model->getOriginal();
            $updatedAttributes = $model->getAttributes();

            foreach ($originalAttributes as $field => $value) {
                if (Carbon::hasFormat($value, 'Y-m-d')) {
                    $originalAttributes[$field] = Carbon::parse($originalAttributes[$field])->format('Y-m-d H:i:s');
                }
            }

            foreach ($updatedAttributes as $field => $value) {
                if (Carbon::hasFormat($value, 'Y-m-d')) {
                    $updatedAttributes[$field] = Carbon::parse($updatedAttributes[$field])->format('Y-m-d H:i:s');
                }
            }

            $changedFields = [];
            foreach ($updatedAttributes as $field => $value) {
                if ($originalAttributes[$field] != $value) {
                    $changedFields[$field] = [
                        'old_value' => $originalAttributes[$field],
                        'new_value' => $value,
                    ];
                }
            }

            foreach ($changedFields as $field => $values) {
                GpAuditLogDetail::create([
                    'audit_logid' => $AuditLog->id,
                    'fieldname' => $field,
                    'new_val' => $actionType !== 'delete' ? $values['new_value'] : null,
                    'old_val' => $actionType !== 'create' ? $values['old_value'] : null,
                ]);
            }
        }
    }
}
