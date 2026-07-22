<?php

namespace Modules\Gp\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpAuditLog;
use Modules\Gp\Entities\GpAuditLogDetail;

class AuditLogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $modelNamespace;
    protected $actionType;
    protected $userId;
    protected $instId;
    protected $objectid;
    protected $parent_objectid;
    protected $AC;
    protected $ip;
    protected $originalAttributes;
    protected $updatedAttributes;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
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
    ) {
        $this->modelNamespace = $modelNamespace;
        $this->actionType = $actionType;
        $this->userId = $userId;
        $this->instId = $instId;
        $this->objectid = $objectid;
        $this->parent_objectid = $parent_objectid;
        $this->AC = $AC;
        $this->ip = $ip;
        $this->originalAttributes = $originalAttributes;
        $this->updatedAttributes = $updatedAttributes;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('AuditLogJob');
        $objectid = $this->objectid;
        $parent_objectid =  $this->parent_objectid;
        $AC = $this->AC;
        $ip = $this->ip;
        $userid = $this->userId ?? 0;
        $instid = $this->instId ?? 0;

        $AuditLog = GpAuditLog::create([
            'userid' => $userid,
            'instid' => $instid,
            'ip' => $ip,
            'AC' => $AC,
            'parent_objectid' => $parent_objectid,
            'objectid' => $objectid,
            'object_type' => $this->modelNamespace,
            'action_type' => $this->actionType,
        ]);

        foreach ($this->originalAttributes as $field => $value) {
            if (is_string($value) && Carbon::hasFormat($value, 'Y-m-d')) {
                $this->originalAttributes[$field] = Carbon::parse($this->originalAttributes[$field])->format('Y-m-d H:i:s');
            }
        }

        foreach ($this->updatedAttributes as $field => $value) {
            if (is_string($value) && Carbon::hasFormat($value, 'Y-m-d')) {
                $this->updatedAttributes[$field] = Carbon::parse($this->updatedAttributes[$field])->format('Y-m-d H:i:s');
            }
        }

        $changedFields = [];
        foreach ($this->updatedAttributes as $field => $value) {
            if (@$this->originalAttributes[$field] != $value) {
                $changedFields[$field] = [
                    'old_value' => @$this->originalAttributes[$field],
                    'new_value' => $value,
                ];
            }
        }

        foreach ($changedFields as $field => $values) {
            GpAuditLogDetail::create([
                'audit_logid' => $AuditLog->id,
                'fieldname' => $field,
                'new_val' => $this->actionType !== 'delete' ? $values['new_value'] : null,
                'old_val' => $this->actionType !== 'create' ? $values['old_value'] : null,
            ]);
        }
        endJobInfo('AuditLogJob');
    }
}
