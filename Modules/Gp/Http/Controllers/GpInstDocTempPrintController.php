<?php

namespace Modules\Gp\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Gp\Entities\GpInstDocTempActionCode;
use Modules\Gp\Entities\GpInstList;
use Modules\Gp\Entities\GpActionCode;
use Modules\Gp\Entities\GpInstBrch;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Http\Services\CoreService;
use Modules\Re\Http\Services\ReportService;
use Carbon\Carbon;
use Modules\Gp\Entities\GpPhoto;

class GpInstDocTempPrintController extends Controller
{
    /**
     * Show the specified resource.
     * @AC gp016102
     * @return Response
     */
    public function show(Request $request)
    {
        $validated = $this->validateMe($request, [
            'id'              => 'nullable',
            'ACTION_CODE'    => 'required',
            'data'            => 'required',
            'data.*.variable' => 'required',
            'data.*.value'    => 'required',
            'response_type'   => 'nullable',
        ], [
            'ACTION_CODE.required' => "RC000020",
        ]);

        $user = auth()->user();
        $validated['instid'] = $user->instid;

        if (empty($validated['id'])) {
            $docTempActionCode = GpInstDocTempActionCode::with(
                ["docTemp", "docTemp.docTempFormInput", "docTemp.docTempVar"]
            )->where("instid", $validated["instid"])
                ->where("ACTION_CODE", $validated["ACTION_CODE"])
                ->where("statusid", 1)->get();
            if (count($docTempActionCode) == 0) {
                return [];
            } else {
                if (count($docTempActionCode) > 1) {
                    $options = [];
                    foreach ($docTempActionCode as $data) {
                        array_push($options, ["id" => $data->id, "name" => $data->docTemp->name]);
                    }
                    return [
                        "options" => $options
                    ];
                } else {
                    $docTempActionCode = $docTempActionCode[0];
                }
            }
        } else {
            $docTempActionCode = GpInstDocTempActionCode::with(
                ["docTemp", "docTemp.docTempFormInput", "docTemp.docTempVar"]
            )->where("instid", $validated["instid"])
                ->where("ACTION_CODE", $validated["ACTION_CODE"])
                ->where("id", $validated["id"])->first();
        }

        $inst = GpInstList::where('id', $user->instid)->where("statusid", "<>", "-1")->first();
        $ACTION_CODE = GpActionCode::where('ACTION_CODE', $validated['ACTION_CODE'])->where("statusid", "<>", "-1")->first();
        $branch = GpInstBrch::where('brchno', $user->brchno)->where('instid', $user->instid)->first();

        $feesPreviewObjects = array();
        $feesPreviewIndices = array();

        $txnPreviewObjects = array();
        $txnPreviewIndices = array();

        $dateset = false;

        if ($docTempActionCode->docTemp->doctype === 1) {
            foreach ($validated['data'] as $data) {
                if (!is_array($data['value']) && !empty($data['value'])) {
                    $variable = $data['variable'];
                    if (strpos($variable, "feesPreview.") === 0) {
                        $parts = explode(".", $variable);
                        if (count($parts) >= 3) {
                            $index = intval(substr($parts[1], 1, -1));
                            $field = $parts[2];

                            if (!isset($feesPreviewIndices[$index])) {
                                $feesPreviewIndices[$index] = true;
                                $feesPreviewObjects[$index] = array();
                            }

                            $feesPreviewObjects[$index][$field] = $data['value'];
                        }
                    }
                    if (strpos($variable, "txnPreview.") === 0) {
                        $parts = explode(".", $variable);
                        if (count($parts) >= 3) {
                            $index = intval(substr($parts[1], 1, -1));
                            $field = $parts[2];

                            if (!isset($txnPreviewIndices[$index])) {
                                $txnPreviewIndices[$index] = true;
                                $txnPreviewObjects[$index] = array();
                            }

                            $txnPreviewObjects[$index][$field] = $data['value'];
                        }
                    }
                    if ($variable === 'date') {
                        $dateset = true;
                    }
                }
            }
        }

        $fee_str_list = array();
        $fee_amount = array();
        $fee_str = "";
        foreach ($feesPreviewObjects as $fee) {
            $contamount = $fee['contamount'] ?? $fee['conttxnamount'];
            if (!isset($fee_amount[$fee['contcurcode']])) {
                $fee_amount[$fee['contcurcode']] = floatval($contamount);
            } else {
                $fee_amount[$fee['contcurcode']] += floatval($contamount);
            }
            $fee_str .= $fee['txndesc'];
            $fee_str .= " - ";
            $fee_str .= $contamount;
            $fee_str .= " ";
            $fee_str .= $fee['contcurcode'];
            $fee_str_list[] = $fee_str;
            $fee_str = "";
        }
        $feeInfo = implode(", ", $fee_str_list);
        $fee_amount_strings = array();
        foreach ($fee_amount as $currency => $value) {
            $fee_amount_strings[] = $value . ' ' . $currency;
        }
        $feeAmount = implode(", ", $fee_amount_strings);

        $validated['data'][] = ["variable" => "feeAmount", "value" => $feeAmount];
        $validated['data'][] = ["variable" => "feeInfo", "value" => $feeInfo];
        if (!$dateset) $validated['data'][] = ["variable" => "date", "value" => strval(CoreService::getTxnDate(auth()->user()->instid))];

        $template = $docTempActionCode->docTemp->template;

        $reportService = new ReportService();

        $special_keys = $reportService->extractSpecialInfo($template);
        foreach ($special_keys as $key) {
            $value = null;
            if (strpos($key['special_key'], "user.") !== false) {
                $field = (explode(".", $key['special_key']))[1];
                if (property_exists($user, $field)) {
                    $value =  $user->{$field};
                } else if (isset($user[$field])) {
                    $value = $user[$field];
                }
            } else if (strpos($key['special_key'], "branch.") !== false) {
                $field = (explode(".", $key['special_key']))[1];
                if (property_exists($branch, $field)) {
                    $value =  $branch->{$field};
                } else if (isset($branch[$field])) {
                    $value = $branch[$field];
                }
            } else if (strpos($key['special_key'], "inst.") !== false) {
                $field = (explode(".", $key['special_key']))[1];
                if (property_exists($inst, $field)) {
                    $value =  $inst->{$field};
                } else if (isset($inst[$field])) {
                    $value = $inst[$field];
                }
                if ($field == 'logo') {
                    $photo = GpPhoto::where('id', $value)->first();
                    if ($photo) {
                        $value = stream_get_contents($photo->photo);
                    }
                    $value = "<img style='width: 90px;' src='data:image/png;base64,$value' alt='Me core'>";
                }
            } else if (strpos($key['special_key'], "process.") !== false) {
                $field = (explode(".", $key['special_key']))[1];
                if (property_exists($ACTION_CODE, $field)) {
                    $value =  $ACTION_CODE->{$field};
                } else if (isset($ACTION_CODE[$field])) {
                    $value = $ACTION_CODE[$field];
                }
            } else {
                $found = false;
                foreach ($validated['data'] as $suggestedKey) {
                    if ($key['special_key'] === $suggestedKey['variable']) {
                        $value = $suggestedKey['value'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $AC = (explode(".", $key['special_key']))[0];
                    $condition_val = null;
                    if (strpos($AC, "[") !== false) {
                        $condition_val = explode("[", $AC);
                        $AC = $condition_val[0];
                        $condition_val = substr($condition_val[1], 0, -1);
                        $condition_val = explode(",", $condition_val);
                        $condition_val = array_map('trim', $condition_val);
                    }
                    $checkdupTxn = [];
                    foreach ($txnPreviewObjects as $txnPreview) {

                        if (!empty($condition_val)) {
                            if ($txnPreview['txncode'] === $AC && $reportService->objectCondTest($condition_val, $txnPreview)) {
                                $main = (explode(".", $key['special_key']))[1];
                                if (array_key_exists($main, $txnPreview)) {
                                    $value = $txnPreview[$main];
                                }
                            }
                        } else {
                            if ($txnPreview['txncode'] === $AC) {
                                $main = (explode(".", $key['special_key']))[1];
                                if (array_key_exists($main, $txnPreview)) {
                                    $value = $txnPreview[$main];
                                }
                            }

                            if (empty($value)) {
                                if (!isset($checkdupTxn[$txnPreview['txncode']])) {
                                    if ($txnPreview['txncode'] === $validated["ACTION_CODE"]) {
                                        $fields = explode(".", $key['special_key']);
                                        if (count($fields) == 1) {
                                            $main = $fields[0];
                                            if (array_key_exists($main, $txnPreview)) {
                                                $value = $txnPreview[$main];
                                            }
                                        }
                                    }
                                    $checkdupTxn[$txnPreview['txncode']] = true;
                                }
                            }
                        }
                    }
                    $condition_val = null;
                }
            }
            if ($value) {
                $template = str_replace($key['full_special_substring'], $reportService->checkFunction($key['full_special_substring'], $value), $template);
            }
        }

        $pattern = '/\${.*?}/';

        $template = preg_replace($pattern, '', $template);

        $template = '<div><style>@media print { body{ font-family: sans-serif; } }</style>' . $template . '</div>';

        if (empty($validated["response_type"])) {
            if ($docTempActionCode->response_type === 1) {
                return ['print' => $template];
            }
        } else if ($validated["response_type"] === 1) {
            return ['print' => $template];
        }

        return ['pdf' => 'data:application/pdf;base64,' . base64_encode(Pdf::loadHTML($template)->setPaper('a4', 'portrate')->setWarnings(false)->output())];
    }
}
