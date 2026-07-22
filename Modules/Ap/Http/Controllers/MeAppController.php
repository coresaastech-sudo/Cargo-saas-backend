<?php

namespace Modules\Ap\Http\Controllers;

use App\Events\ApTxnMonitoringEvent;
use App\Exceptions\MeException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Resolvers\ChannelResolver;
use Carbon\Carbon;
use Error;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Modules\Ap\Enums\ApMonitorTransactionEnum;
use Modules\Gp\Entities\GppiActionCode;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Entities\GpctionCode;
use Modules\Gp\Entities\GpppList;
use Modules\Gp\Enums\ResponseCodeEnum;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Jobs\EBarimtJob;
use PDOException;
use TypeError;

class MeAppController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function process(Request $request)
    {
        // Controller хайж олон хүсэлт илгээх
        if ($request->header('AC')) {
            $process = $this->getActionCodeDetail($request->header('AC'));
            $code = $request->header('AC');
            // Ali app ashiglaj bgag shalgah
            if ($request->hasHeader('X-App-Identifier') && $request->hasHeader('X-App-Secret')) {
                $app = GpppList::where('app_identifier', $request->header('X-App-Identifier'))
                    ->where('app_secret', $request->header('X-App-Secret'))
                    ->where('statusid', 1)
                    ->first();
                if (!$app) {
                    throw new MeException("RC000249");
                }
            }
            if ($process) {
                if ($process->route == 0) {
                    $route = $process->controller . '@' . $process->function;
                } else {
                    $process = GpctionCode::where('ACTION_CODE', $process->ACTION_CODE)
                        ->where('statusid', 1)->first();
                    $route = $process->controller . '@' . $process->function;
                }
                $exception = null;
                $iscreatedwebosocket = false;
                $websocketid = rand(100, 999999);
                $iserror = false;
                try {
                    if (ApMonitorTransactionEnum::isValidValue($code)) {
                        $instid = @request('instid');
                        if ($instid) {
                            $inst = GPInstList::where('id', $instid)->first();
                            $msg = "Зээл олголт эхэллээ.";
                            $txnName = $process->name;
                            // websocket push data
                            try {
                                $tmpdata = [
                                    'time' => Carbon::now(),
                                    'id' => $websocketid,
                                    'status' => 1,
                                    'processName' => $msg,
                                    'txnName' => $txnName,
                                    'createdBy' => auth()->user()->firstname,
                                    'instName' => $inst->name,
                                    'stage' => 1,
                                    'channelName' => ChannelResolver::resolve()
                                ];
                                event(new ApTxnMonitoringEvent($tmpdata, $instid));
                                event(new ApTxnMonitoringEvent($tmpdata, 1));
                                $iscreatedwebosocket = true;
                            } catch (Exception $ex) {
                                Log::debug($ex);
                            }
                        }
                    }

                    $response = App::call($route);
                    $pdfActionCodes = ['cr010505', 'oi000770']; 
                    if (in_array($request->header('AC'), $pdfActionCodes)) {
                        return $response;
                    }


                    if ($iscreatedwebosocket) {
                        try {
                            $tmpdata = [
                                'time' => Carbon::now(),
                                'id' => $websocketid,
                                'status' => 0,
                                'processName' => 'Гүйлгээ амжилттай хийгдлээ.',
                                'responseCode' => ResponseCodeEnum::success,
                                'stage' => 2,
                            ];
                            event(new ApTxnMonitoringEvent($tmpdata, $instid));
                            event(new ApTxnMonitoringEvent($tmpdata, 1));
                        } catch (Exception $ex) {
                            Log::debug($ex);
                        }
                    }
                    return $this->success($response);
                } catch (MeException $ex) {
                    $iserror = true;
                    throw $ex;
                } catch (ValidationException $ex) {
                    $exception = $ex;
                    $iserror = true;
                    Log::debug($ex);
                    $this->validationToMeException($ex->validator->errors()->all(), $ex->validator->errors()->keys());
                } catch (QueryException $ex) {
                    Log::error('QueryException');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    if ($ex->getPrevious() instanceof PDOException) {
                        if ($ex->getCode() == '23505') {
                            throw new MeException("RC000060");
                        }
                    }

                    Log::channel('slack')->critical($ex);
                    throw new MeException("RC000012");
                } catch (Exception $ex) {
                    Log::error('Exception');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    // Log::channel('slack')->critical($ex);
                    throw new MeException("RC000003");
                } catch (Error $ex) {
                    Log::error('Error');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    // Log::channel('slack')->critical($ex);
                    throw new MeException("RC000003");
                } catch (TypeError $ex) {
                    Log::error('TypeError');
                    Log::error($ex);
                    $iserror = true;
                    $exception = $ex;
                    // Log::channel('slack')->critical($ex);
                    throw new MeException("RC000003");
                } finally {
                    if (!empty($exception)) {
                        $this->storeErrorLog($exception);
                    }

                    if ($iserror && $iscreatedwebosocket) {
                        try {
                            $tmpdata = [
                                'time' => Carbon::now(),
                                'id' => $websocketid,
                                'status' => 0,
                                'processName' => $exception ? $exception->getMessage() : 'Алдаа гарлаа',
                                'responseCode' => ResponseCodeEnum::sys_error,
                                'stage' => 2,
                            ];
                            event(new ApTxnMonitoringEvent($tmpdata, $instid));
                            event(new ApTxnMonitoringEvent($tmpdata, 1));
                        } catch (Exception $ex) {
                            Log::debug($ex);
                        }
                    }
                }
            } else {
                throw new MeException("RC000002", ['proc_code' => $code]);
            }
        } else {
            throw new MeException("RC000001");
        }
    }

    public static function getActionCodeDetail($AC)
    {
        $code = Str::lower($AC);
        // Cache::forget('ActionCode_' . $code);
        $key = 'API_ActionCode_' . $code;
        $process = Cache::rememberForever(
            $key,
            function () use ($code, $key) {
                CoreService::storeCacheKey(1, $key);
                return GppiActionCode::where('api_ACTION_CODE', $code)
                    ->where('statusid', 1)
                    ->first();
            }
        );

        return $process;
    }
}
