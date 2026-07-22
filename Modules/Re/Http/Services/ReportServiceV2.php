<?php

namespace Modules\Re\Http\Services;

use App\Exceptions\MeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPInstFormula;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Http\Services\CoreService;
use Modules\Re\Entities\ReInstReportTemp;
use Modules\Re\Entities\ReInstReportTempDim;
use Modules\Re\Entities\ReInstReportTempContent;
use Modules\Re\Entities\ReInstReportTempParam;
use Modules\Re\Entities\ReInstReportTempParamIn;
use Modules\Re\Entities\ReInstReportTempParamInRel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\App;
use Modules\Gp\Entities\GPPhoto;
use Modules\Gp\Entities\GpctionCode;

class ReportServiceV2
{
    private $formuladata = [];

    function findAndExplode($array, $string)
    {
        if ($array['content'] == "")
            return null;
        $replacejson = '';
        $pagenum = [
            "type" => "span",
            "attributes" => "style='mso-field-code:PAGE'",
            "content" => ""
        ];
        $totalpage = [
            "type" => "span",
            "attributes" => "style='mso-field-code:NUMPAGES'",
            "content" => ""
        ];
        if ($string == "&PAGENUM&")
            $replacejson = $pagenum;
        else
            $replacejson = $totalpage;

        foreach ($array['content'] as $key => $subArr) {
            if (!is_array($subArr['content'])) {
                if (strpos($subArr['content'], $string) !== false) {
                    $copy = $subArr;
                    $parts = explode($string, $subArr['content']);

                    $first_part = array_slice($array['content'], 0, $key + 1, true);
                    $second_part = array_slice($array['content'], $key + 1, null, true);

                    $copy['content'] = $parts[0];
                    $first_part[$key] = $copy;

                    $first_part["newindex2"] = $replacejson;

                    $copy['content'] = $parts[1];
                    $first_part["newindex3"] = $copy;

                    $array['content'] = array_values($first_part + $second_part);
                    return $array;
                }
                continue;
            }
            $mixed = $this->findAndExplode($subArr, $string);
            if ($mixed !== null) {
                $array['content'][$key] = $mixed;
                return $array;
            }
        }
    }

    function insertFinal($report, $jsons)
    {
        $name = $report['name'];
        $margin = $report['pagemargin'];
        $size = $report['dimensionid'];
        $orientation = $report['orientation'];
        $headH = $report['headersize'] ? $report['headersize'] : 0;
        $footH = $report['footersize'] ? $report['footersize'] : 0;
        $headRep = $report['headerrepeat'];
        $footRep = $report['footerrepeat'];
        $header = json_encode($jsons['header'] ?? []);
        $content = json_encode($jsons['content'] ?? []);
        $footer = json_encode($jsons['footer'] ?? []);
        $page = ReInstReportTempDim::where("instid", 1)->where("statusid", 1)->where("id", $size)->first();
        $footerin = $footer;
        $dir = "";
        $size = [$page['width'], $page['height']];
        if ($orientation == 1) {
            $dir = "portrait";
            $size[0] = $page['width'];
            $size[1] = $page['height'];
        } else if ($orientation == 2) {
            $dir = "landscape";
            $size[0] = $page['height'];
            $size[1] = $page['width'];
        }
        $pageNum = strpos($footer, "&PAGENUM&");
        if ($footRep && $pageNum !== false) {
            $footerin = '[' . json_encode($this->findAndExplode($jsons['footer'][0], "&PAGENUM&")) . ']';
        }
        $totalpage = strpos($footer, "&TOTALPAGES&");
        if ($footRep && $totalpage !== false) {
            $footerin = '[' . json_encode($this->findAndExplode(json_decode($footerin, true)[0], "&TOTALPAGES&")) . ']';
        }
        $final = "{
            \"name\": \"$name\",
            \"final\": [
                {
                    \"type\": \"html\",
                    \"attributes\": \"lang='en'\",
                    \"content\": [
                        {
                            \"type\": \"head\",
                            \"attributes\": \"\",
                            \"content\": [
                                {
                                    \"type\": \"style\",
                                    \"attributes\": \"type='text/css'\",
                                    \"content\": [
                                        {
                                            \"type\": \"text\",
                                            \"content\":\"td { white-space:normal} p{margin-top: 0; margin-bottom: 0;} span{margin-top: 0; margin-bottom: 0;} body, html { height: auto !important; overflow: visible !important; } @page Section1 {margin: $margin $margin $margin $margin; size: " . $size[0] . "mm " . $size[1] . "mm; mso-page-orientation: $dir; mso-header-margin: $headH; mso-header: h1;mso-footer-margin: $footH; mso-footer: f1; line-height: 1em;}div.Section1 {page:Section1;}p.headerFooter{ margin: 0in;}\"
                                        }
                                    ]
                                }
                            ]
                        },
                        {
                            \"type\": \"body\",
                            \"attributes\": \"\",
                            \"content\": [
                                {
                                    \"type\": \"div\",
                                    \"attributes\": \"class=Section1\",
                                    \"content\": [
                                        {
                                            \"type\": \"table\",
                                            \"attributes\": \"style='margin-left:50in;'\",
                                            \"content\": [
                                                {
                                                    \"type\": \"tr\",
                                                    \"attributes\": \"style='height:1pt;mso-height-rule:exactly'\",
                                                    \"content\": [
                                                        {
                                                            \"type\": \"td\",
                                                            \"attributes\": \"\",
                                                            \"content\": [
                                                                {
                                                                    \"type\": \"div\",
                                                                    \"attributes\": \"style='mso-element:header' id=h1\",
                                                                    \"content\": [
                                                                        {
                                                                            \"type\": \"p\",
                                                                            \"attributes\": \"class=headerFooter\",
                                                                            \"content\": $header
                                                                        }
                                                                    ]
                                                                },
                                                                {
                                                                    \"type\": \"text\",
                                                                    \"content\": \"**nbsp**\"
                                                                }
                                                            ]
                                                        },
                                                        {
                                                            \"type\": \"td\",
                                                            \"attributes\": \"\",
                                                            \"content\": [
                                                                {
                                                                    \"type\": \"div\",
                                                                    \"attributes\": \"style='mso-element:footer' id=f1\",
                                                                    \"content\": [
                                                                        {
                                                                            \"type\": \"p\",
                                                                            \"attributes\": \"class=headerFooter\",
                                                                            \"content\": $footerin
                                                                        }
                                                                    ]
                                                                },
                                                                {
                                                                    \"type\": \"text\",
                                                                    \"content\": \"**nbsp**\"
                                                                }
                                                            ]
                                                        }
                                                    ]
                                                }
                                            ]
                                        },
                                        {
                                            \"type\": \"div\",
                                            \"attributes\": \"\",
                                            \"content\": $content
                                        }
                                    ]
                                }
                            ]
                        }
                    ]
                }
            ]
        }";
        return $final;
    }

    function recursiveReplace($jsonArray, $replacements)
    {
        // Log::debug($replacements);
        // Log::debug($jsonArray);
        foreach ($jsonArray as $key3 => $value) {
            $countkey = $key3;
            if ($value['var']) {
                if ($value['type'] == 'tr') {
                    // Log::debug($countkey);
                    $fnd = $value;
                    $outop = false;
                    foreach ($replacements['data'] as $repl) {
                        $copy = $fnd;
                        $op = false;
                        foreach ($repl as $key => $param) {
                            if (
                                is_numeric($param) && $key != 'itemno'
                                && strpos($key, 'day') === false
                                && strpos($key, 'count') === false
                                && strpos($key, 'acntno') === false
                                && strpos($key, 'jrno') === false
                                && strpos($key, 'brchno') === false
                            ) {
                                $param = number_format($param, 2);
                            }
                            foreach ($copy['content'] as $key2 => $td) {
                                if ($td['var']) {
                                    // Log::debug($td);
                                    $searchkey = '${' . $replacements['param'] . '.' . $key . '}';
                                    // Log::debug($searchkey);
                                    if (strpos($td['content'], $searchkey) !== false) {
                                        $op = true;
                                        $td['content'] = str_replace($searchkey, $param ?? '', $td['content']);
                                    }
                                    $copy['content'][$key2] = $td;
                                    // Log::debug($td);
                                }
                            }
                        }
                        // Log::debug($copy);
                        if ($op) {
                            $outop = true;
                            // $jsonArray[] = $copy;
                            $pos = array_search($countkey, array_keys($jsonArray));
                            $first_part = array_slice($jsonArray, 0, $pos + 1, true);
                            $second_part = array_slice($jsonArray, $pos + 1, null, true);
                            $first_part["newindex"] = $copy;
                            $jsonArray = array_values($first_part + $second_part);
                            $countkey++;
                        }
                    }
                    $ind = array_search($fnd, $jsonArray);
                    if ($ind !== false && $outop) {
                        unset($jsonArray[$ind]);
                        $jsonArray = array_values($jsonArray);
                    }
                    // Log::debug($jsonArray);
                } else {
                    $jsonArray[$key3]['content'] = $this->recursiveReplace($value['content'], $replacements);
                }
            }
        }
        return $jsonArray;
    }

    public function generateReport($validate)
    {
        $user = auth()->user();
        $instid = $user->instid;
        $inst = GPInstList::where("id", $instid)->first();
        $report = ReInstReportTemp::where("instid", 1)->where("statusid", 1)
            ->where("ACTION_CODE", $validate['ACTION_CODE'])->first();

        $params = ReInstReportTempParam::where("instid", 1)
            ->where("statusid", 1)->whereNull("parentid")
            ->where("templateid", $report->id)->get();
        $paramdatas = [];
        // Log::debug('$params');
        // Log::debug($params);
        $validate['inputs'] = array_merge($validate['inputs'] ?? [], [
            [
                'input' => 'instid',
                'value' => $instid
            ],
            [
                'input' => 'sysdate',
                'value' => CoreService::getTxnDate($instid)
            ],
            [
                'input' => 'userid',
                'value' => $user->id
            ],
            [
                'input' => 'AC',
                'value' => $validate['ACTION_CODE']
            ],
        ]);
        // Log::debug('$validate[inputs]');
        // Log::debug($validate['inputs']);
        foreach ($params as $paramkey => $param) {
            // Тухайн параметрт харгалзах гараас авах оролтуудыг шүүх
            $paraminputs = ReInstReportTempParamIn::where("instid", 1)
                ->where("statusid", 1)->where("templateid", $report->id)
                ->whereIn("id", function ($query) use ($instid, $report, $param) {
                    $query->select('inputid')
                        ->from(with(new ReInstReportTempParamInRel)->getTable())
                        ->where("instid", 1)
                        ->where("statusid", 1)->where("templateid", $report->id)
                        ->where("paramid", $param->id);
                })->get();
            // Log::debug('$paraminputs');
            // Log::debug($paraminputs);

            $inputvalues = [
                [
                    'input' => 'instid',
                    'value' => $instid
                ],
                [
                    'input' => 'sysdate',
                    'value' => CoreService::getTxnDate($instid)
                ],
                [
                    'input' => 'userid',
                    'value' => $user->id
                ],
                [
                    'input' => 'AC',
                    'value' => $validate['ACTION_CODE']
                ],
            ];
            $arrayfields = [];
            foreach ($paraminputs as $paraminput) {
                $found_key = array_search($paraminput->input, array_column($validate['inputs'], 'input'));
                if (gettype($validate['inputs'][$found_key]['value']) == 'array') {
                    foreach ($validate['inputs'][$found_key]['value'] as $key => $value) {
                        $tmpfield = $validate['inputs'][$found_key]['input'] . '_' . ($key + 1);
                        if (isset($arrayfields[$validate['inputs'][$found_key]['input']])) {
                            $arrayfields[$validate['inputs'][$found_key]['input']][] = ":" . $tmpfield;
                        } else {
                            $arrayfields[$validate['inputs'][$found_key]['input']] = [":" . $tmpfield];
                        }
                        array_push($inputvalues, [
                            'input' => $tmpfield,
                            'value' => $value
                        ]);
                    }
                } else {
                    if ($paraminput->forminputtype == 5) {
                        // Огноо төрлийн input бол форматын дагуу хөрвүүлнэ.
                        $validate['inputs'][$found_key]['value'] = (explode("T", $validate['inputs'][$found_key]['value']))[0];
                    }
                    array_push($inputvalues, $validate['inputs'][$found_key]);
                }
            }
            if ($param->hasquery == 1) {
                $formula = GPInstFormula::where("id", $param->formulaid)
                    ->where("instid", 1)->where("statusid", 1)->first();
                if ($formula) {
                    foreach ($arrayfields as $fieldkey => $arrayfield) {
                        $formula->formula = str_replace(":$fieldkey", implode(", ", $arrayfield), $formula->formula);
                    }
                    // Log::debug($formula->formula);
                    $bindparam = [];
                    foreach ($inputvalues as $inputvalue) {
                        if (preg_match('/:' . $inputvalue['input'] . '\b/', $formula->formula)) {
                            $bindparam[$inputvalue['input']] = $inputvalue['value'];
                        }
                    }
                    try {
                        $sqldata = DB::select($formula->formula, $bindparam);
                    } catch (\Throwable $th) {
                        Log::debug($bindparam);
                        // Bind утгуудыг SQL-д орлуулах
                        $fullSql = $formula->formula;
                        foreach ($bindparam as $key => $value) {
                            $formattedValue = $value;
                            // String бол quotes дотор оруулах
                            if (is_string($value)) {
                                $formattedValue = "'" . addslashes($value) . "'";
                            } elseif (is_null($value)) {
                                $formattedValue = 'NULL';
                            }

                            if (is_numeric($key)) {
                                // Позициональ (?) утгуудыг орлуулах
                                $fullSql = preg_replace('/\?/', $formattedValue, $fullSql, 1);
                            } else {
                                // Named parameters (:key) утгуудыг орлуулах
                                $fullSql = preg_replace('/:' . $key . '\b/', $formattedValue, $fullSql);
                            }
                        }

                        throw new MeException(
                            $formula->id . ' дугаартай томъёо ажиллуулахад алдаа гарлаа. Алдаа: ' . $th->getMessage()
                        );

                        // throw new MeException($formula->id .
                        //     ' дугаартай томъёо ажиллуулахад алдаа гарлаа. ' . $th->getMessage());
                    }
                    $paramdatas[] = [
                        'type' => $param->type,
                        'param' => $param->paramname,
                        'data' => $sqldata
                    ];
                } else {
                    throw new MeException($param->id . ' дугаартай оролт дээр formula бүртгэлгүй байна.');
                }
            }
            if ($param->type == 8 && $param->expression) {
                $process = GpctionCode::where('ACTION_CODE', $param->expression)->first();
                if ($process) {
                    $bindparam = [];
                    foreach ($validate['inputs'] as $inputvalue) {
                        $bindparam[$inputvalue['input']] = $inputvalue['value'];
                    }
                    request()->replace($bindparam);
                    $resdata = App::call($process->controller . '@' . $process->function);
                    if ($resdata) {
                        foreach ($resdata as $resdatakey => $resdatavalue) {
                            $validate['inputs'][] = [
                                'input' => $param->paramname . "." . $resdatakey,
                                'value' => $resdatavalue,
                            ];
                        }
                    }
                }
            }
        }

        // Log::debug('$params');
        // Log::debug($params);
        $content = ReInstReportTempContent::where("instid", 1)
            ->whereNull("parentid")->where("statusid", 1)
            ->where("templateid", $report->id)
            ->orderBy("listorder", "asc")->first();
        // Log::debug('$content');
        // Log::debug($content);
        // Log::debug('$report');
        // Log::debug($report);
        if (!$content) {
            throw new MeException('Агуулга бүртгэгдээгүй байна.');
        }
        // variable decoding goes here
        if ($report->exporttype == 2) {

            foreach ($validate['inputs'] as $key => $inputvalue) {
                $searchkey = '${' . $inputvalue['input'] . '}';
                if (strpos($content->source, $searchkey) !== false) {
                    $content->source = str_replace($searchkey, $inputvalue['value'], $content->source);
                }
            }

            // ${inst.*} ${user.*} талбаруудыг бөглөнө.
            $content->source = $this->setInst($content->source, $inst);
            $content->source = $this->setUser($content->source, $user);
            // Log::debug($content->source);
            try {
                $mainbody = json_decode($content->source, true);
                // Log::debug('$mainbody');
                // Log::debug($mainbody);
                $sheets = $mainbody['sheets'];
                // Log::debug('generalsheet');
                // Log::debug($sheets);
                if (count($sheets)) {
                    $generalsheet = $sheets[0];
                    $fieldToSortBy = 'index'; // Change this to the field you want to sort by
                    usort($generalsheet['rows'], sortByField($fieldToSortBy));
                    $fixedmainrows = $generalsheet['rows'];
                    // Log::debug('$generalsheet[rows]');
                    // Log::debug($generalsheet['rows']);
                    $tmprowdataindex = 0;
                    $tmpgeneralrows = [];
                    $tmpmergecells = [];
                    foreach ($generalsheet['rows'] as $rowkey => $rowdata) {
                        // Log::debug('$rowkey');
                        // Log::debug($rowkey);
                        $realrowindex = $rowdata['index'];
                        // Log::debug('$realrowindex');
                        // Log::debug($realrowindex);
                        // mergecell талбаруудын index-г зөв байрлалд байрлуулах
                        if ($rowdata['index'] != $tmprowdataindex) {
                            $pattern = '/\d+/'; // Match one or more digits
                            $patterncharacter = '/[A-Z]+/'; // Match one or more uppercase letters
                            foreach ($generalsheet['mergedCells'] as $mergedCellkey => $mergedCell) {
                                preg_match_all($pattern, $mergedCell, $matches);
                                $numbers = $matches[0];
                                if ($numbers[0] == ($rowdata['index'] + 1)) {
                                    preg_match_all($patterncharacter, $mergedCell, $matches);
                                    $letters = $matches[0];
                                    $diffindex = ($tmprowdataindex + 1) - $numbers[0];
                                    $mergedCell = $letters[0] . ($numbers[0] + $diffindex) . ":" . $letters[1] . ($numbers[1] + $diffindex);
                                    $mainbody['sheets'][0]['mergedCells'][$mergedCellkey] = $mergedCell;
                                }
                            }
                        }
                        $rowdata['index'] = $tmprowdataindex;
                        usort($rowdata['cells'], sortByField($fieldToSortBy));
                        $tmpinsertcells = [];
                        $tmpparentinsertcellskey = [];
                        $isskiprow = false;
                        foreach ($rowdata['cells'] as $cellkey => $cellvalue) {
                            foreach ($paramdatas as $paramkey => $paramdata) {
                                $tmpparam = '${' . $paramdata['param'];
                                // Параметрын утгын төрлийг шалгана.
                                if ($paramdata['type'] == 6) {
                                    $tmpparam = $tmpparam . '.';
                                }
                                $searchindex = strpos(($cellvalue['value'] ?? ''), $tmpparam);
                                if ($searchindex !== false) {
                                    $paramparts = explode('.', $cellvalue['value']);
                                    if (count($paramparts) != 3) {
                                        if (!isset($tmpinsertcells[$paramkey])) {
                                            $paramparts = explode('-', $paramdata['param']);
                                            $isparent = false;
                                            if (count($paramparts) > 1) {
                                                if ($paramparts[0] == 'parent') {
                                                    $isparent = true;
                                                    $tmpparentinsertcellskey[$paramkey] = $paramdata['param'];
                                                }
                                            }
                                            $tmpinsertcells[$paramkey] = [
                                                'startrowindex' => $tmprowdataindex,
                                                'param' => $paramdata['param'],
                                                'haschild' => $isparent,
                                                'childcolumns' => [],
                                                'data' => []
                                            ];
                                        }
                                        $tmpinsertcells[$paramkey]['data'][] = $cellvalue;
                                    } else {
                                        $generalsheet['rows'][$rowkey]['cells'][$cellkey]['value'] = null;
                                        // $rowdata['cells'] = $generalsheet['rows'][$rowkey]['cells'];
                                        $isskiprow = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($isskiprow) {
                            continue;
                        }

                        // Log::debug('$tmpparentinsertcellskey');
                        // Log::debug($tmpparentinsertcellskey);
                        // child талбаруудыг хайж олох
                        if (count($tmpparentinsertcellskey)) {
                            $tmprowdata = $fixedmainrows[$rowkey + 1];
                            if ($tmprowdata) {
                                $pattern = '/\d+/'; // Match one or more digits
                                $patterncharacter = '/[A-Z]+/'; // Match one or more uppercase letters
                                $tmpchildmergerangecells = [];
                                foreach ($mainbody['sheets'][0]['mergedCells'] as $mergedCellkey => $mergedCell) {
                                    preg_match_all($pattern, $mergedCell, $matches);
                                    $numbers = $matches[0];
                                    // Log::debug('$matches');
                                    // Log::debug($matches);
                                    if ($numbers[0] == ($tmprowdata['index'] + 1) && $numbers[1] == ($tmprowdata['index'] + 1)) {
                                        preg_match_all($patterncharacter, $mergedCell, $matches);
                                        $letters = $matches[0];
                                        $tmpchildmergerangecells[] = $letters[0] . '-' . $letters[1];
                                    }
                                }
                                usort($tmprowdata['cells'], sortByField($fieldToSortBy));
                                foreach ($tmpparentinsertcellskey as $tmpparentinsertcellskeykey => $tmpparentinsertcellskeyparam) {
                                    foreach ($tmprowdata['cells'] as $cellkey => $cellvalue) {
                                        $tmpparam = '${' . $tmpparentinsertcellskeyparam;
                                        // Параметрын утгын төрлийг шалгана.
                                        if ($paramdata['type'] == 6) {
                                            $tmpparam = $tmpparam . '.';
                                        }
                                        $searchindex = strpos(($cellvalue['value'] ?? ''), $tmpparam);
                                        if ($searchindex !== false) {
                                            $paramparts = explode('.', $cellvalue['value']);
                                            if (count($paramparts) == 3) {
                                                if (!isset($tmpinsertcells[$tmpparentinsertcellskeykey]['childcolumnparamdataindex'])) {
                                                    foreach ($paramdatas as $paramkey => $paramdata) {
                                                        if ($paramdata['param'] == $paramparts[1]) {
                                                            $tmpinsertcells[$tmpparentinsertcellskeykey]['childcolumnparamdataindex'] = $paramkey;
                                                            break;
                                                        }
                                                    }
                                                }

                                                // Sub table column энд бичигдэнэ
                                                // Log::debug($paramparts);
                                                $tmpinsertcells[$tmpparentinsertcellskeykey]['childcolumns'][] = $cellvalue;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        if (count($tmpinsertcells)) {
                            // Log::debug('$tmpinsertcells');
                            // Log::debug($tmpinsertcells);
                            $pattern = '/\d+/'; // Match one or more digits
                            $patterncharacter = '/[A-Z]+/'; // Match one or more uppercase letters
                            $tmpmergerangecells = [];
                            foreach ($mainbody['sheets'][0]['mergedCells'] as $mergedCellkey => $mergedCell) {
                                preg_match_all($pattern, $mergedCell, $matches);
                                $numbers = $matches[0];
                                if ($numbers[0] == ($tmprowdataindex + 1) && $numbers[1] == ($tmprowdataindex + 1)) {
                                    preg_match_all($patterncharacter, $mergedCell, $matches);
                                    $letters = $matches[0];
                                    $tmpmergerangecells[] = $letters[0] . '-' . $letters[1];
                                }
                            }
                            foreach ($tmpinsertcells as $key => $tmpinsertcell) {
                                $tmpparam = $paramdatas[$key];
                                $startrowindexdata = $tmpinsertcell['startrowindex'];
                                if ($tmpparam['type'] == 6) {
                                    $tmprowdata = [];
                                    $tmprowkey = $rowkey;
                                    $gentmprowdata = $rowdata;
                                    if (count($tmpparam['data'])) {
                                        $childtmpparams = [];
                                        if ($tmpinsertcell['haschild']) {
                                            $childtmpparams = $paramdatas[$tmpinsertcell['childcolumnparamdataindex']];
                                        }
                                        foreach ($tmpparam['data'] as $datakey => $paramvalue) {
                                            $this->insertRowData(
                                                $mainbody,
                                                $tmpinsertcell['data'],
                                                $paramvalue,
                                                $startrowindexdata,
                                                $datakey,
                                                $generalsheet,
                                                $tmprowkey,
                                                $tmpgeneralrows
                                            );
                                            $startrowindexdata++;
                                            foreach ($tmpmergerangecells as $tmpmergerangecell) {
                                                $parts = explode('-', $tmpmergerangecell);
                                                $tmpmergecells[] = $parts[0] . ($startrowindexdata) . ':' . $parts[1] . $startrowindexdata;
                                            }

                                            if ($tmpinsertcell['haschild']) {
                                                if ($childtmpparams['type'] == 6) {
                                                    foreach ($childtmpparams['data'] as $childparamkey => $childparamvalue) {
                                                        if (
                                                            isset($childparamvalue->parentid)
                                                            && $childparamvalue->parentid == $paramvalue->id
                                                        ) {
                                                            $this->insertRowData(
                                                                $mainbody,
                                                                $tmpinsertcell['childcolumns'],
                                                                $childparamvalue,
                                                                $startrowindexdata,
                                                                $childparamkey,
                                                                $generalsheet,
                                                                $tmprowkey,
                                                                $tmpgeneralrows,
                                                            );
                                                            $startrowindexdata++;
                                                            foreach ($tmpchildmergerangecells as $tmpmergerangecell) {
                                                                $parts = explode('-', $tmpmergerangecell);
                                                                $tmpmergecells[] = $parts[0] . ($startrowindexdata) . ':' . $parts[1] . $startrowindexdata;
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                            // Хамгийн доор байгаа мөрийн индекс мэдэх.
                                            if ($startrowindexdata > $tmprowdataindex) {
                                                $tmprowdataindex = $startrowindexdata;
                                            }
                                            // $tmprowdata[] = $gentmprowdata;
                                        }
                                        $tmprowdataindex--;
                                    } else {
                                        $tmpcelldata = [];
                                        foreach ($tmpinsertcell['data'] as $tmpvaluekey => $tmpvalue) {
                                            $parts = explode('.', $tmpvalue['value']);
                                            $field = substr($parts[1], 0, -1);
                                            $tmpvalue['value'] = null;
                                            $tmpcelldata[] = $tmpvalue;
                                        }
                                        // тухайн бичилт үүсэх баганын дугааруудын хязгааруудыг олох.
                                        $startindex = $tmpcelldata[0]['index'];
                                        $endindex = $tmpcelldata[count($tmpcelldata) - 1]['index'];
                                        foreach ($mainbody['sheets'][0]['rows'] as $realrowvalueskey => $realrowvalues) {
                                            if ($startrowindexdata == $realrowvalues['index']) {
                                                foreach ($realrowvalues['cells'] as $realrowvaluekey => $realrowvalue) {
                                                    if ($realrowvalue['index'] > $endindex) {
                                                        $tmpcelldata[] = $realrowvalue;
                                                    }

                                                    if ($realrowvalue['index'] < $startindex) {
                                                        $tmpcelldata[] = $realrowvalue;
                                                    }
                                                }
                                                break;
                                            }
                                        }
                                        $gentmprowdata['cells'] = $tmpcelldata;
                                        $gentmprowdata['index'] = $startrowindexdata;
                                        $tmpgeneralrows[] = $gentmprowdata;
                                        foreach ($generalsheet['rows'] as $generalsheetkey => $generalsheetrows) {
                                            if ($generalsheetrows['index'] == $startrowindexdata) {
                                                $generalsheet['rows'][$generalsheetkey] = $gentmprowdata;
                                            }
                                        }
                                    }

                                    $mainbody['sheets'][0]['rows'] = $generalsheet['rows'];
                                    // Log::debug('$generalsheet[rows]');
                                    // Log::debug($generalsheet['rows']);
                                }
                                // Log::debug('$tmprowdata');
                                // Log::debug($tmprowdata);
                            }
                        } else {
                            $tmpgeneralrows[] = $rowdata;
                            $generalsheet['rows'][$tmprowdataindex] = $rowdata;
                            // Log::debug('$rowdata');
                            // Log::debug($rowdata);
                        }

                        $tmprowdataindex++;
                    }
                    $mainbody['sheets'][0]['rows'] = $tmpgeneralrows;
                    // Log::debug('$generalsheet[rows]');
                    // Log::debug($generalsheet['rows']);
                    $mainbody['sheets'][0]['mergedCells'] = array_merge($mainbody['sheets'][0]['mergedCells'], $tmpmergecells);
                }
            } catch (\Throwable $th) {
                Log::error($th);
                throw new MeException($th->getMessage());
            }

            if ($content) {
                if (isset($mainbody)) {
                    $mainbody = json_encode($mainbody);
                    // Log::debug('$this->formuladata');
                    // Log::debug($this->formuladata);
                    foreach ($this->formuladata as $formulafield => $formulavalue) {
                        $result = preg_replace_callback('/\$\{(\w+)\.(\w+)\}/', function ($matches) {
                            // $matches[1] нь table1 байх, $matches[2] нь col1
                            $string = "";
                            for ($i = 1; $i < count($matches); $i++) {
                                if (empty($string)) {
                                    $string = $matches[$i];
                                } else {
                                    $string = $string . '.' . $matches[$i];
                                }
                            }
                            return '${formula.' . $string;
                        }, $formulafield);
                        $mainbody = str_replace('"' . $result . '.sum}"', $formulavalue['sum'], $mainbody);
                        $mainbody = str_replace($result . '.sum}', $formulavalue['sum'], $mainbody);
                        $mainbody = str_replace('"' . $result . '.count}"', $formulavalue['count'], $mainbody);
                        $mainbody = str_replace($result . '.count}"', $formulavalue['count'], $mainbody);
                        $mainbody = str_replace('"' . $result . '.min}"', $formulavalue['min'], $mainbody);
                        $mainbody = str_replace($result . '.min}"', $formulavalue['min'], $mainbody);
                        $mainbody = str_replace('"' . $result . '.max}"', $formulavalue['max'], $mainbody);
                        $mainbody = str_replace($result . '.max}"', $formulavalue['max'], $mainbody);
                        $mainbody = str_replace('"' . $result . '.avg}"', $formulavalue['sum'] / $formulavalue['count'], $mainbody);
                        $mainbody = str_replace($result . '.avg}"', $formulavalue['sum'] / $formulavalue['count'], $mainbody);
                    }

                    $mainbody = preg_replace('/\$\{formula\.[^}]+\.(sum|count|min|max|avg)\}/', '0', $mainbody);
                    $mainbody = preg_replace_callback('/\$=([\d\+\-\*\/\s]+)/', function ($matches) {
                        return eval('return ' . $matches[1] . ';');
                    }, $mainbody);
                    $mainbody = preg_replace('/\$\{[^}]+\}/', '', $mainbody);
                    $mainbody = json_decode($mainbody, true);
                    if (isset($mainbody['sheets']) && count($mainbody['sheets']) > 0) {
                        foreach ($mainbody['sheets'][0]['rows'] as $rowkey => $rowdata) {
                            foreach ($rowdata['cells'] as $cellkey => $cellvalue) {
                                $numeric = true;
                                if (strlen($cellvalue['value'] ?? '') > 1) {
                                    $first = substr($cellvalue['value'], 0, 1);
                                    $second = substr($cellvalue['value'], 1, 1);
                                    if ($first == '0' && $second != ".") {
                                        $numeric = false;
                                    }
                                }
                                if (is_numeric($cellvalue['value'] ?? '') && $numeric) {
                                    $mainbody['sheets'][0]['rows'][$rowkey]['cells'][$cellkey]['value'] = $cellvalue['value'] * 1;
                                }
                            }
                        }
                    }

                    return $mainbody;
                }
            } else {
                throw new MeException('Тайлангийн агуулга бүртгэгдээгүй байна.');
            }
        } else {
            $content->source = $this->setInst($content->source, $inst);
            $content->source = $this->setUser($content->source, $user);
            foreach ($validate['inputs'] as $key => $inputvalue) {
                $searchkey = '${' . $inputvalue['input'] . '}';
                if (strpos($content->source, $searchkey) !== false) {
                    $content->source = str_replace($searchkey, $inputvalue['value'], $content->source);
                }
            }
            foreach ($paramdatas as $datum) {
                $jsondec = json_decode($content->source, true);
                if (count($datum['data']) > 1) { //Olon yum bvl husnegt sunana
                    $jsondec['content'] = $this->recursiveReplace($jsondec['content'], $datum);
                    $content->source = json_encode($jsondec);
                } else {
                    if (count($datum['data'])) {
                        foreach ($datum['data'][0] as $key => $param) {
                            $searchkey = '${' . $datum['param'] . '.' . $key . '}';
                            if (strpos($content->source, $searchkey) !== false) {
                                $content->source = str_replace($searchkey, $param ?? '', $content->source);
                            }
                        }
                    }
                }
                // Sum, count, max, min, avg
                $calculated = [];
                $listOfKeys = [];
                foreach ($datum['data'] as $data) {
                    foreach ($data as $key => $param) {
                        if (is_numeric($param)) {
                            $param = $param * 1;
                            $listOfKeys[] = $key;
                            if (isset($calculated[$key])) {
                                $calculated[$key]['sum'] += $param;
                                $calculated[$key]['count']++;
                                $calculated[$key]['max'] = max($calculated[$key]['max'], $param);
                                $calculated[$key]['min'] = min($calculated[$key]['min'], $param);
                                $calculated[$key]['avg'] = $calculated[$key]['sum'] / $calculated[$key]['count'];
                            } else {
                                $calculated[$key] = [
                                    'sum' => $param,
                                    'count' => 1,
                                    'max' => $param,
                                    'min' => $param,
                                    'avg' => 0,
                                ];
                            }
                        }
                    }
                }
                if (count($datum['data'])) {
                    foreach ($datum['data'][0] as $key => $param) {
                        if (!in_array($key, $listOfKeys))
                            continue;
                        if (
                            $key != 'itemno' && strpos($key, 'day') === false && strpos($key, 'count') === false
                            && strpos($key, 'acntno') === false
                            && strpos($key, 'jrno') === false
                            && strpos($key, 'brchno') === false
                        ) {
                            $calculated[$key]['sum'] = number_format($calculated[$key]['sum'], 2);
                            $calculated[$key]['max'] = number_format($calculated[$key]['max'], 2);
                            $calculated[$key]['min'] = number_format($calculated[$key]['min'], 2);
                            $calculated[$key]['avg'] = number_format($calculated[$key]['avg'], 2);
                        }
                        $content->source = str_replace('${formula.' . $datum['param'] . '.' . $key . '.sum}', $calculated[$key]['sum'], $content->source);
                        $content->source = str_replace('${formula.' . $datum['param'] . '.' . $key . '.count}', $calculated[$key]['count'], $content->source);
                        $content->source = str_replace('${formula.' . $datum['param'] . '.' . $key . '.max}', $calculated[$key]['max'], $content->source);
                        $content->source = str_replace('${formula.' . $datum['param'] . '.' . $key . '.min}', $calculated[$key]['min'], $content->source);
                        $content->source = str_replace('${formula.' . $datum['param'] . '.' . $key . '.avg}', $calculated[$key]['avg'], $content->source);
                    }
                }
            }
            $content->source = preg_replace('/\$\{[^}]+\}/', '', $content->source);
            if (is_string($content->source)) {
                $content->source = preg_replace_callback('/\[numbertotext:([0-9.]+)\s*,\s*([0-9]+)\]/', function ($matches) {
                    return numbertotext(floatval($matches[1]), 0);
                }, $content->source);
            }
            $jsondec = json_decode($content->source, true);
            return ['data' => $this->insertFinal($report, $jsondec), 'type' => 'html'];
        }
    }

    public function insertRowData(
        &$mainbody,
        $tmpinsertcell,
        &$paramvalue,
        $startrowindexdata,
        &$datakey,
        &$generalsheet,
        &$tmprowkey,
        &$tmpgeneralrows
    ) {
        $tmpcelldata = [];
        foreach ($tmpinsertcell as $tmpvaluekey => $tmpvalue) {
            $parts = explode('.', $tmpvalue['value']);
            $field = substr(trim($parts[count($parts) - 1]), 0, -1);
            $tmpcellformulavalue = $tmpvalue['value'];
            // if (count($parts) > 2) {
            //     Log::debug($startrowindexdata);
            //     Log::debug($tmpinsertcell);
            //     Log::debug($mainbody['sheets'][0]['mergedCells']);
            // }
            $pattern = '/\$\{([А-яa-zA-Z0-9.\-_]+)\}/';
            $tmpvalue['value'] = preg_replace($pattern, ($paramvalue->$field ?? ""), $tmpvalue['value'] ?? "");
            if (isset($paramvalue->isbold) && $paramvalue->isbold == 1) {
                $tmpvalue['bold'] = true;
            }
            preg_match('/\${color:([A-Fa-f0-9]{6})}/', $tmpvalue['value'], $matches);
            $color = isset($matches[1]) ? $matches[1] : null;
            if ($color) {
                $tmpvalue['background'] = "#$color";
            }
            $tmpvalue['value'] = preg_replace('/\${color:[A-Fa-f0-9]{6}}/', '', $tmpvalue['value']);
            $numeric = true;
            if (strlen($tmpvalue['value']) > 1) {
                $first = substr($tmpvalue['value'], 0, 1);
                $second = substr($tmpvalue['value'], 1, 1);
                if ($first == '0' && $second != ".") {
                    $numeric = false;
                }
            }
            if (is_numeric($tmpvalue['value'])) {
                if ($numeric) {
                    $tmpvalue['value'] = $tmpvalue['value'] * 1;
                    if (isset($this->formuladata[$tmpcellformulavalue])) {
                        $this->formuladata[$tmpcellformulavalue]['sum'] = $this->formuladata[$tmpcellformulavalue]['sum'] + $tmpvalue['value'];
                        $this->formuladata[$tmpcellformulavalue]['count']++;
                        $this->formuladata[$tmpcellformulavalue]['max'] = max($tmpvalue['value'], $this->formuladata[$tmpcellformulavalue]['max']);
                        $this->formuladata[$tmpcellformulavalue]['min'] = min($tmpvalue['value'], $this->formuladata[$tmpcellformulavalue]['min']);
                    } else {
                        $this->formuladata[$tmpcellformulavalue] = [
                            'sum' => $tmpvalue['value'],
                            'count' => 1,
                            'min' => $tmpvalue['value'],
                            'max' => $tmpvalue['value'],
                        ];
                    }
                } else {
                    $tmpvalue['format'] = '@';
                }
            }
            $tmpcelldata[] = $tmpvalue;
        }
        // тухайн бичилт үүсэх баганын дугааруудын хязгааруудыг олох.
        $startindex = $tmpcelldata[0]['index'];
        $endindex = $tmpcelldata[count($tmpcelldata) - 1]['index'];
        // Log::debug([
        //     $startindex,
        //     $endindex
        // ]);
        foreach ($mainbody['sheets'][0]['rows'] as $realrowvalueskey => $realrowvalues) {
            if ($startrowindexdata == $realrowvalues['index']) {
                // Log::debug([
                //     $startrowindexdata,
                //     $realrowvalues
                // ]);
                foreach ($realrowvalues['cells'] as $realrowvaluekey => $realrowvalue) {
                    if ($realrowvalue['index'] > $endindex) {
                        $tmpcelldata[] = $realrowvalue;
                    }

                    if ($realrowvalue['index'] < $startindex) {
                        $tmpcelldata[] = $realrowvalue;
                    }
                }
                break;
            }
        }
        $gentmprowdata['cells'] = $tmpcelldata;
        $gentmprowdata['index'] = $startrowindexdata;
        $tmpgeneralrows[] = $gentmprowdata;
        // Log::debug('tmprowkey');
        // Log::debug($tmprowkey);
        // Log::debug('gentmprowdata');
        // Log::debug($gentmprowdata);
        if ($datakey == 0) {
            // Log::debug('gentmprowdata');
            // Log::debug($gentmprowdata);
            // Log::debug($mainbody['sheets'][0]['mergedCells']);
            foreach ($generalsheet['rows'] as $generalsheetkey => $generalsheetrows) {
                if ($generalsheetrows['index'] == $gentmprowdata['index']) {
                    $generalsheet['rows'][$generalsheetkey] = $gentmprowdata;
                }
            }
        } else {

            foreach ($generalsheet['rows'] as $generalsheetkey => $generalsheetrows) {
                if ($generalsheetrows['index'] == $gentmprowdata['index']) {
                    $generalsheet['rows'][$generalsheetkey] = $gentmprowdata;
                    break;
                }
            }
            array_splice($generalsheet['rows'], $tmprowkey, 0, [$gentmprowdata]);
            $tmprowkey++;
        }
    }

    // Контент дотор ${inst.name}, ${inst.dirname} гэх мэт тайлан гаргаж байгаа байгууллагын мэдээлэл оруулах бол
    public function setInst($cellData, $inst)
    {
        if (strpos($cellData, '${inst.') !== false) {
            $pattern = '/\${inst\.(.*?)}/';
            preg_match_all($pattern, $cellData, $matches);
            $substrings = $matches[1];
            foreach ($substrings as $substring) {
                $key = '${inst.' . $substring . '}';
                $exp = explode(",", $substring);
                if (property_exists($inst, $exp[0])) {
                    $sendingdata = $inst->{$exp[0]};
                } else {
                    $sendingdata = $inst[$exp[0]];
                }
                if ($key == '${inst.logo}') {
                    $photo = GPPhoto::where('id', $sendingdata)->first();
                    if ($photo) {
                        $sendingdata = stream_get_contents($photo->photo);
                    }
                    $imageData = base64_decode($sendingdata);
                    $imageSize = getimagesizefromstring($imageData);
                    $height = 60 * $imageSize[1] / $imageSize[0];
                    $sendingdata = "<img width='60' height='$height' src='data:image/png;base64,$sendingdata' alt='Me core'>";
                }
                $cellData = str_replace($key, $this->checkFunction($key, $sendingdata), $cellData);
            }
        }
        return $cellData;
    }
    // Контент дотор ${user.name}, ${user.email} гэх мэт тайлан гаргаж байгаа ажилтаны мэдээлэл оруулах бол
    public function setUser($cellData, $user)
    {
        if (strpos($cellData, '${user.') !== false) {
            $pattern = '/\${user\.(.*?)}/';
            preg_match_all($pattern, $cellData, $matches);
            $substrings = $matches[1];
            foreach ($substrings as $substring) {
                $key = '${user.' . $substring . '}';
                $exp = explode(",", $substring);
                if (property_exists($user, $exp[0])) {
                    $sendingdata = $user->{$exp[0]};
                } else {
                    $sendingdata = $user[$exp[0]];
                }
                $cellData = str_replace($key, $this->checkFunction($key, $sendingdata), $cellData);
            }
        }
        return $cellData;
    }

    // онцгой тэмдэгт дотор бичигдсэн функцийг гүйцэтгэх
    // qr, currency, currency-1, currency-3, currency-4, numbertotext, numbertotext-1
    public function checkFunction($source, $data)
    {
        $source = str_replace('${', '', $source);
        $source = str_replace('}', '', $source);
        if (strpos($source, ',') !== false) {
            $source = explode(',', $source);
            $source[0] = trim($source[0]);
            $source[1] = trim($source[1]);
            if (strpos($source[1], "yyyy") !== false || strpos($source[1], "mm") !== false || strpos($source[1], "dd") !== false) {
                $date = Carbon::parse($data);
                $date = explode("/", $date->format('m/d/Y'));
                $source[1] = str_replace('yyyy', $date[2], $source[1]);
                $source[1] = str_replace('mm', strlen($date[0]) === 2 ? $date[0] : ("0" . $date[0]), $source[1]);
                $source[1] = str_replace('dd', strlen($date[1]) === 2 ? $date[1] : ("0" . $date[1]), $source[1]);
                return $source[1];
            } else if (strpos($source[1], "DIC_") !== false) {
                // get dictionary for the future
                // TODO Dictionary авах
            } else {
                if ($source[1] === 'qr') { // qr base64 image source generate
                    $writer = new PngWriter();
                    $qrCode = QrCode::create($data);
                    $result = $writer->write($qrCode);
                    // Output as PNG image
                    $pngData = $result->getString();

                    // Convert PNG data to Base64
                    $base64 = base64_encode($pngData);

                    // For direct embedding in an HTML img tag
                    return 'data:image/png;base64,' . $base64;
                } else if ($source[1] === 'currency') {
                    return number_format((float) $data, 2, '.', '\'');
                } else if ($source[1] === 'currency-1') {
                    return number_format((float) $data, 1, '.', '\'');
                } else if ($source[1] === 'currency-3') {
                    return number_format((float) $data, 3, '.', '\'');
                } else if ($source[1] === 'currency-4') {
                    return number_format((float) $data, 4, '.', '\'');
                } else if ($source[1] === 'numtotext') {
                    return numbertotext(floatval($data), 2);
                } else if ($source[1] === 'numtotext-1') {
                    return numbertotext(floatval($data), 1);
                } else if ($source[1] === 'numtotext-3') {
                    return numbertotext(floatval($data), 3);
                } else if ($source[1] === 'numtotext-4') {
                    return numbertotext(floatval($data), 4);
                } else if ($source[1] === 'boolean') {
                    $b = (int) $data;
                    if ($b === 0) {
                        return "Үгүй";
                    } else {
                        return "Тийм";
                    }
                } else if ($source[1] === 'boolean-eng') {
                    $b = (int) $data;
                    if ($b === 0) {
                        return "No";
                    } else {
                        return "Yes";
                    }
                } else {
                    return $data;
                }
            }
        } else {
            return $data;
        }
    }
}
