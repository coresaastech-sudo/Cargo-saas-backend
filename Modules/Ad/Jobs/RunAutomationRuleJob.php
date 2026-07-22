<?php

namespace Modules\Ad\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RunAutomationRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly array $payload = []) {}

    public function handle(): void
    {
        try {
            if (! Schema::hasTable('ad_automation_runs')) {
                return;
            }

            DB::table('ad_automation_runs')->insert(array_merge(
                Arr::except($this->payload, ['posting_code', 'action_code']),
                ['created_at' => now(), 'updated_at' => now()]
            ));
        } catch (Throwable) {
            return;
        }
    }
}
