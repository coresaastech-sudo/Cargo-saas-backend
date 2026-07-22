<?php

namespace Modules\Gp\Jobs;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Gp\Entities\GpDbBackupLog;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Http\Services\CoreService;
use Spatie\DbDumper\Databases\PostgreSql;

class DbBackupProcessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userid;
    protected $backupid;
    public $instid;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($userid, $instid, $backupid)
    {
        $this->userid = $userid;
        $this->instid = $instid;
        $this->backupid = $backupid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        initJobInfo('DbBackupProcessJob');
        $user = GpInstUser::find($this->userid);
        if (empty($user) || $user->instid != $this->instid) {
            throw new MeException('RC000119');
        }
        App::setLocale('mn');
        // Set the user as the authenticated user
        Auth::setUser($user);
        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }
        $startdate = Carbon::now();
        $backup = GpDbBackupLog::find($this->backupid);
        try {
            $backupDir = "backups";
            Storage::makeDirectory($backupDir);
            // Specify your database details
            $dbName = config('database.connections.pgsql.database');
            $dbUserName = config('database.connections.pgsql.username');
            $dbPassword = config('database.connections.pgsql.password');
            $dbHost = config('database.connections.pgsql.host');
            $dbPort = config('database.connections.pgsql.port');

            // Create the backup using Spatie\DbDumper
            $backupFileName = "backup_" . date('Ymd_His') . ".backup";
            $backupPath = storage_path("app/{$backupDir}/{$backupFileName}");
            $backup->statusid = 2;
            $backup->path = $backupPath;
            $backup->save();
            // method 1
            PostgreSql::create()
                ->setDbName($dbName)
                ->setUserName($dbUserName)
                ->setPassword($dbPassword)
                ->setHost($dbHost)
                ->setPort($dbPort)
                ->setTimeout(3000)
                ->addExtraOption('--format=c')
                ->dumpToFile($backupPath);

            $backup->statusid = 1;
            $backup->size = filesize($backupPath);
        } catch (\Throwable $th) {
            $backup->statusid = 3;
            $backup->errordesc = $th->getMessage();
            Log::error($th);
            //throw $th;
        } finally {
            $enddate = Carbon::now();
            $backup->time = $startdate->diffInSeconds($enddate);
            $backup->save();
        }
        endJobInfo('DbBackupProcessJob');
    }
}
