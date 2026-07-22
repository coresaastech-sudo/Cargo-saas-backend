<?php

namespace Modules\Gp\Jobs;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Ad\Entities\AdBatchRegistration;
use Modules\Ad\Entities\AdBatchRegistrationDetail;
use Modules\Gp\Entities\GpInstUser;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Entities\GpProviderConf;
use Modules\Gp\Http\Controllers\GpController;
use Illuminate\Support\Str;

class BatchRegistrationJob implements ShouldQueue
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
        initJobInfo('BatchRegistrationJob');
        $user = GpInstUser::find($this->userid);
        if (empty($user) || $user->instid != $this->instid) {
            throw new MeException('RC000119');
        }
        App::setLocale('mn');
        Auth::setUser($user);
        if (!defined('REQUEST_CHANNEL')) {
            define('REQUEST_CHANNEL', 'BACK');
        }
        $startdate = Carbon::now();
        $backup = AdBatchRegistration::find($this->backupid);
        try {
            $tempDir = storage_path('app/batch_temp');
            $tempFile = $tempDir . '/batch_' . $this->backupid . '.jsonl';

            if (!file_exists($tempFile)) {
                throw new MeException('Батчийн файл олдсонгүй');
            }

            $provider = GpProviderConf::where('code', 'ad019980')
                ->where('instid', 1)
                ->where('statusid', 1)->first();
            if (empty($provider)) {
                throw new MeException('ad019980 дугаартай провайдерийн бүртгэл хийгдээгүй байна.');
            }
            $config = json_decode($provider->config, true);
            $GPcontroller = new GpController();

            $handle = fopen($tempFile, 'r');
            while (($line = fgets($handle)) !== false) {
                $value = json_decode($line, true);
                if (!$value) continue;

                $mapfields = @$config[$value['AC']];
                if (!empty($mapfields)) {
                    foreach ($mapfields as $key => $mapfield) {
                        $mapfields[Str::lower($key)] = $mapfield;
                    }
                    $process = GpActionCode::where('ACTION_CODE', $value['AC'])
                        ->where('statusid', 1)->first();
                    if ($process) {
                        if (!$GPcontroller->checkActionCode($value['AC'])) {
                            throw new MeException("RC000014", ['AC' => $value['AC']]);
                        }
                        $route = $process->controller . '@' . $process->function;
                        foreach ($value['data'] as $key1 => $detaildata) {
                            $detailreg = null;
                            try {
                                $detailreg = AdBatchRegistrationDetail::create([
                                    'batchregistrationid' => $this->backupid,
                                    'rowid' => 0,
                                    'txncode' => $value['AC'],
                                    'requestdata' => json_encode($detaildata, JSON_UNESCAPED_UNICODE),
                                    'statusid' => 0,
                                    'instid' => $this->instid,
                                    'created_by' => $this->userid,
                                ]);

                                $tmpdata = [];
                                foreach ($detaildata as $key => $itemvalue) {
                                    $fieldt = @$mapfields[Str::lower($key)];
                                    if ($fieldt && gettype($fieldt) != 'string') {
                                        if ($fieldt['iscyrillic'] && isset($tmpdata[Str::lower($fieldt["convertfield"])])) {
                                            $itemvalue = cyrillic2latin($tmpdata[Str::lower($fieldt["convertfield"])]);
                                            $fieldt = $fieldt["field"];
                                        }
                                    }
                                    if (isset($fieldt)) {
                                        $tmpdata[$fieldt] = $itemvalue;
                                    } else {
                                        $tmpdata[$fieldt] = null;
                                    }
                                }

                                if ($value['AC'] == 'gl012200') {
                                    $tmpdata = ['data' => [$tmpdata]];
                                }
                                request()->replace($tmpdata);
                                $response = App::call($route);

                                if ($response) {
                                    if (@$response->id) {
                                        $detailreg->rowid = $response->id;
                                    } elseif (is_numeric($response)) {
                                        $detailreg->rowid = $response;
                                    }
                                }
                                $detailreg->statusid = 1;
                            } catch (ValidationException $ex) {
                                $detailreg->description = $ex->getMessage()
                                    . " => " . json_encode($ex->validator->errors()->all())
                                    . " => " . json_encode($ex->validator->errors()->keys());
                                throw $ex;
                            } catch (\Throwable $th) {
                                if (!empty($detailreg)) {
                                    $detailreg->description = $th->getMessage();
                                }
                                throw $th;
                            } finally {
                                if ($detailreg->statusid == 1) {
                                    $backup->successcount++;
                                } else {
                                    $backup->errorcount++;
                                }
                                $detailreg->save();
                            }
                        }
                    } else {
                        throw new MeException("RC000002", ['proc_code' => $value['AC']]);
                    }
                } else {
                    throw new MeException($value['AC'] . ' тохиргоо хийгдээгүй байна.');
                }
            }
            fclose($handle);
            $backup->process = 1;
        } catch (\Throwable $th) {
            $backup->process = 3;
            $backup->errordesc = $th->getMessage();
            Log::error($th);
        } finally {
            $enddate = Carbon::now();
            $backup->time = $startdate->diffInSeconds($enddate);
            $backup->save();

            $tempDir = storage_path('app/batch_temp');
            $tempFile = $tempDir . '/batch_' . $this->backupid . '.jsonl';
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
        endJobInfo('BatchRegistrationJob');
    }
}
