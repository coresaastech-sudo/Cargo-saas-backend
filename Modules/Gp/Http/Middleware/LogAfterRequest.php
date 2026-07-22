<?php

namespace Modules\Gp\Http\Middleware;

use App\Resolvers\IpAddressResolver;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GpLogRequestList;
use Modules\Gp\Entities\GpUserAccessToken;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class LogAfterRequest
{

    protected $notresponse = [
        "gp020000" => "List",
        "ln010000" => "List",
        "ln011000" => "List",
        "ln010010" => "List",
        "ln040001" => "List",
        "ln050000" => "List",
        "dp010000" => "List",
        "gl010000" => "List",
        "ad051000" => "List",
        "cr010000" => "List",
        "cr011000" => "List",
        "ln030000" => "List",
        "ln031000" => "List",
        "ca010000" => "List",
        "ad060000" => "List",
        "ad020003" => "List",
        "ia011000" => "List",
        "ia020000" => "List",
        "ia021000" => "List",
        "ia030000" => "List",
        "ia040000" => "List",
        "ia041000" => "List",
        "dp011000" => "List",
        "dp020001" => "List",
        "dp020002" => "List",
        "dp030000" => "List",
        "ln010020" => "List",
        "re010010" => "List",
        "re010001" => "List",
        "re010011" => "List",
        "gp016000" => "List",
        "re010501" => "Report",
        "gp016102" => "Report",
    ];

    public function handle($request, Closure $next)
    {
        $method = $request->getMethod();
        if ($method === "OPTIONS") {
            return response('');
        }
        $response = $next($request);

        switch ($request->header('AC')) {
            case 'gp080000':
            case 'gp080001':
            case 'gp080002':
            case 'gp080003':
            case 'gp080004':
            case 'gp080102':
            case 'cr010505':
            case 'ad011000':
            case 'ad011100':
            case 'ad019580':
            case 'gp040099':
            case 'gp013502':
            case 'lo030100':
            case 'gp100001':
            case 'ad020001':
                return $response;
                break;
            default:
                # code...
                break;
        }
        switch ($request->path()) {
            case '/':
                return $response;
                break;

            default:
                # code...
                break;
        }

        try {
            $r = new GpLogRequestList();
            $user = auth()->user();

            // Төлөөлөл хэрэглэгийн userid-г actor_userid баганад хадгалана.
            $token = $request->bearerToken();
            if ($token) {
                $auth = GpUserAccessToken::where('token', $token)
                    ->where('channel', 'BACK')->where('name', 'login')->first();
                if ($auth) {
                    if (!empty($auth->abilities)) {
                        $r->actor_userid = $auth['userid'];
                    }
                }
            }

            if ($user && gettype($user) != 'string') {
                $r->userid = $user->id;
                $r->instid = $user->instid;
            }
            $r->ip = IpAddressResolver::resolve();
            $r->url = $request->fullUrl();
            $r->AC = $request->header('AC');
            $r->method = $method;
            $data = $request->all();
            if (empty($data)) {
                $r->request = $request->getContent();
            } else {
                if (array_key_exists('password', $data)) {
                    $data['password'] = "******";
                }
                $r->request = json_encode($data, JSON_UNESCAPED_UNICODE);
            }

            // Check if response is BinaryFileResponse
            if ($response instanceof BinaryFileResponse) {
                $r->responsecode = $response->getStatusCode(); // Get status directly
                $r->response = "Binary file content"; // Binary file responses don’t have content in the usual sense
            } else if ($response instanceof Response) {
                $r->responsecode = $response->getStatusCode(); // Standard status code retrieval
                if (isset($this->notresponse[$request->header('AC')])) {
                    $r->response = $this->notresponse[$request->header('AC')];
                } else {
                    $content = $response->getContent();
                    $decode = @json_decode($content);
                    if ($decode) {
                        $content = json_encode($decode, JSON_UNESCAPED_UNICODE);
                    }
                    $r->response = $content;
                }
            }
            $r->responsetime = microtime(true) - LARAVEL_START;
            $r->save();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
        DB::commit();
        return $response;
    }

    public function terminate($request, $response) {}
}
