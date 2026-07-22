<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Gp\Services\DictionaryService;

class DictionaryController extends Controller
{
    public function options(Request $request, DictionaryService $dictionaries): array
    {
        $code = $request->input('dictionary_code') ?: $request->input('code');

        return $dictionaries->options($code, $request->user()?->organization_id);
    }
}
