<?php

namespace Modules\Ap\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Ap\Entities\ApFaqs;
use Modules\Gp\Enums\ResponseCodeEnum;
// use Illuminate\Support\Facades\Log;

class ApFaqController extends Controller
{
    /**
     * ap050000 - Түгээмэл асуулт, хариулт жагсаалт (Grid List)
     */
    public function ap050000(Request $request)
    {
        return $this->getGridData(
            $request,
            ApFaqs::where('statusid', 1)
                  ->where('instid', auth()->user()->instid ?? 0),
            [['field' => 'created_at', 'dir' => 'DESC']]
        );
    }

    /**
     * ap050100 - Түгээмэл асуулт, хариулт дэлгэрэнгүй (Detail)
     */
    public function ap050100(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required|integer|exists:ap_faqs,id'
        ], [
            'id.required' => ResponseCodeEnum::required,
        ]);

        $faq = ApFaqs::where('id', $validate['id'])
            ->where('statusid', '!=', -1)
            ->first();

        if ($faq) {
            return $faq;
        }

        $this->error("RC000010", $validate);
    }

    /**
     * ap050200 - Түгээмэл асуулт, хариулт бүртгэх (Create)
     */
    public function ap050200(Request $request)
    {
        $validated = $this->validateMe($request, [
            'question'   => 'required|string|max:500',
            'question2'  => 'nullable|string|max:500',
            'answer'     => 'required|string',
            'answer2'    => 'nullable|string',
            'listorder' => 'nullable|integer|min:0',
        ], [
            'question.required' => ResponseCodeEnum::required,
            'answer.required' => ResponseCodeEnum::required,
        ]);

        $user = auth()->user();

        $faq = ApFaqs::create([
            'question'    => $validated['question'],
            'question2'   => $validated['question2'] ?? $validated['question'],
            'answer'      => $validated['answer'],
            'answer2'     => $validated['answer2'] ?? $validated['answer'],
            'listorder'  => $validated['listorder'] ?? 0,
            'statusid'    => 1,
            'instid'      => $user->instid ?? 0,
            'created_by'  => $user->id,
        ]);

        return $faq;
    }

    /**
     * ap050300 - Түгээмэл асуулт, хариулт засварлах (Update)
     */
    public function ap050300(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id'         => 'required|integer|exists:ap_faqs,id',
            'question'   => 'required|string|max:500',
            'question2'  => 'nullable|string|max:500',
            'answer'     => 'required|string',
            'answer2'    => 'nullable|string',
            'listorder' => 'nullable|integer|min:0',
        ], [
            'id.required' => ResponseCodeEnum::required,
            'question.required' => ResponseCodeEnum::required,
            'answer.required' => ResponseCodeEnum::required,
        ]);

        $faq = ApFaqs::where('id', $validated['id'])->where('statusid', '!=', -1)->first();
        if (!$faq) {
            $this->error("RC000010");
        }

        $faq->update([
            'question'    => $validated['question'],
            'question2'   => $validated['question2'] ?? $validated['question'],
            'answer'      => $validated['answer'],
            'answer2'     => $validated['answer2'] ?? $validated['answer'],
            'listorder'  => $validated['listorder'] ?? $faq->listorder,
            'updated_by'  => auth()->user()->id ?? 1,
        ]);

        return $faq->fresh();
    }

    /**
     * ap050400 - Түгээмэл асуулт, хариулт устгах (Delete)
     */
    public function ap050400(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id' => 'required|integer'
        ], [
            'id.required' => ResponseCodeEnum::required,
        ]);

        $faq = ApFaqs::where('id', $validated['id'])->where('statusid', '!=', -1)->first();
        if (!$faq) {
            $this->error("RC000010");
        }

        $faq->update([
            'statusid'   => -1,
            'updated_by' => auth()->user()->id ?? 1,
        ]);
    }

    /**
     * oi000800 - Mobile-с FAQ жагсаалт авах 
     */
    public function oi000800(Request $request)
    {
        $validated = $this->validateMe($request, [
            'instid' => 'required|numeric'
        ]);

        $faqs = ApFaqs::where('statusid', 1)
                      ->where('instid', $validated['instid'])
                      ->orderBy('listorder', 'ASC')
                      ->select([
                          'id',
                          'question',
                          'question2',
                          'answer',
                          'answer2',
                          'listorder'
                      ])
                      ->get();

        return $faqs;
    }
}