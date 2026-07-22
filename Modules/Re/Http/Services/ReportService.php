<?php

namespace Modules\Re\Http\Services;

use App\Exceptions\MeException;
use Illuminate\Support\Facades\DB;
use Modules\Re\Entities\ReInstReportTemp;
use Modules\Re\Entities\ReInstReportTempParam;
use Modules\Re\Entities\ReInstReportTempParamIn;
use Modules\Re\Entities\ReInstReportTempParamInRel;
use Modules\Re\Entities\ReInstReportTempContent;
use Modules\Re\Entities\ReInstTable;
use Modules\Re\Entities\ReInstTableField;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Http\Services\CoreService;
use Modules\Gp\Entities\GPInstFormula;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

use Carbon\Carbon;

use Exception;
use Illuminate\Support\Facades\Log;
use Modules\Gp\Entities\GPPhoto;
use SebastianBergmann\CodeCoverage\Report\Xml\Source;

class ReportService
{
    public function generateReportMod($value, $instid, $user)
    {
        $exporttype = 2;
        if (array_key_exists('exporttype', $value)) {
            if (!empty($value['exporttype'])) $exporttype = $value['exporttype'];
        }
        $excel_service = null;
        if ($exporttype === 2) {
            $excel_service = new ExcelService([], $instid, $user);
        } else {
        }

        $inst = GPInstList::where("id", $instid)->first();

        foreach ($value['validate'] as $validate) {
            $report = ReInstReportTemp::where("instid", 1)->where("statusid", 1)->where("ACTION_CODE", $validate['ACTION_CODE'])->first();
            // Log::channel("report_log")->debug($report->name . 'generate started');
            if ($report) {
                $params = ReInstReportTempParam::where("instid", 1)->where("statusid", 1)->whereNull("parentid")->where("templateid", $report->id)->get();
                $parameters = [];
                foreach ($params as $param) {
                    $paraminputrels = ReInstReportTempParamInRel::where("instid", 1)->where("statusid", 1)->where("templateid", $report->id)->where("paramid", $param->id)->get();

                    $paraminputs = [];

                    foreach ($paraminputrels as $paraminputrel) {
                        $tmp = ReInstReportTempParamIn::where("instid", 1)->where("statusid", 1)->where("templateid", $report->id)->where("id", $paraminputrel->inputid)->first();
                        if ($tmp) array_push($paraminputs, $tmp);
                    }

                    $inputvalues = [];

                    foreach ($paraminputs as $paraminput) {
                        $found_key = array_search($paraminput['input'], array_column($validate['inputs'], 'input'));
                        if ($paraminput->forminputtype === 5) {
                            $validate['inputs'][$found_key]['value'] = (explode("T", $validate['inputs'][$found_key]['value']))[0];
                        };
                        array_push($inputvalues, $validate['inputs'][$found_key]);
                    }

                    if (count($paraminputs) !== count($inputvalues)) {
                        // send error input missing
                        continue;
                    }
                    $date = (new Carbon(CoreService::getTxnDate(auth()->user()->instid)))->format('Y-m-d');
                    $result = $this->evaluateparam($param, $inputvalues, $instid, $inst, $user, $date);
                    if (!empty($result)) array_push($parameters, $result);
                }
            }

            $contents = ReInstReportTempContent::where("instid", 1)->whereNull("parentid")->where("statusid", 1)->where("templateid", $report->id)->orderBy("listorder", "asc")->get()->toArray();

            $inputs = [];
            if (array_key_exists('inputs', $validate)) {
                foreach ($validate['inputs'] as $input) {
                    $newinput = ReInstReportTempParamIn::where("instid", 1)->where("statusid", 1)->where("templateid", $report->id)->where("input", $input['input'])->first();
                    array_push($inputs, array_merge((array) $input, (array) json_decode(json_encode($newinput))));
                }
            } else {
                $validate['inputs'] = [];
            }

            $processed_content = $this->processReportContents($report, ['result' => $parameters, 'generated' => true, 'inputs' => $inputs], $contents, $exporttype, $validate['inputs'], $inst, $user, true);
            $generated_content = $this->generatecontent($processed_content['contents'], $processed_content['report'], $processed_content['exporttype'], $processed_content['validate_inputs'], $inst, $user, true);

            if ($exporttype === 2) {
                // Log::channel("report_log")->debug('generated_content', $generated_content['data']);
                $excel_service->createSheet($generated_content['info'], $generated_content['data'], $generated_content['validate_inputs'], $inst, $user);
            } else {
                return ['source' => $generated_content, 'exporttype' => $exporttype];
            }
        }
        if ($exporttype === 2) {
            return ['source' => $excel_service->export(), 'exporttype' => $exporttype];
        } else {
            return ['source' => '', 'exporttype' => $exporttype];
        }
    }

    public function generateReport($validate, $instid, $user)
    {
        $report = ReInstReportTemp::where("instid", 1)->where("statusid", 1)
            ->where("ACTION_CODE", $validate['ACTION_CODE'])->first();

        if ($report) {
            // Log::channel("report_log")->debug($report->name . ' generate started');
            $params = ReInstReportTempParam::where("instid", 1)
                ->where("statusid", 1)->whereNull("parentid")
                ->where("templateid", $report->id)->get();
            $parameters = []; // Бодолт хийгдэж амжилттай болсон параметрууд орно
            $exporttype = $report->exporttype; // үр дүнгээ буцаах төрөл

            if (array_key_exists('exporttype', $validate)) {
                if (!empty($validate['exporttype'])) {
                    $exporttype = $validate['exporttype'];
                }
            }

            foreach ($params as $param) {
                // $paraminputrels = ReInstReportTempParamInRel::where("instid", $instid)
                //     ->where("statusid", 1)->where("templateid", $report->id)
                //     ->where("paramid", $param->id)->get();

                // $paraminputs = []; // Гараас авсан байх ёстой утгууд орно
                // // Тухайн параметрт харгалзах гараас авах оролтуудыг шүүх
                // foreach ($paraminputrels as $paraminputrel) {
                //     $tmp = ReInstReportTempParamIn::where("instid", $instid)
                //         ->where("statusid", 1)->where("templateid", $report->id)
                //         ->where("id", $paraminputrel->inputid)->first();
                //     if ($tmp) {
                //         array_push($paraminputs, $tmp);
                //     }
                // }
                // Log::debug($paraminputs);
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
                // Тухайн параметерт харгалзах гараас авах оролтуудад харгалзах хэрэглэгчийн илгээсэн өгөгдлийг авах
                $inputvalues = []; // Гараас авсан ёстой утгуудад харгалзах утгууд орно /хэрвээ гараас илгээсэн бол/
                foreach ($paraminputs as $paraminput) {
                    $found_key = array_search($paraminput['input'], array_column($validate['inputs'], 'input'));
                    if ($paraminput->forminputtype === 5) {
                        $validate['inputs'][$found_key]['value'] = (explode("T", $validate['inputs'][$found_key]['value']))[0];
                    };
                    array_push($inputvalues, $validate['inputs'][$found_key]);
                }

                if (count($paraminputs) !== count($inputvalues)) {
                    // send error input missing
                    throw new MeException('RC000179');
                } else {
                    // АНХААР !!!
                    // тухайн параметрт харгалзах бодолтыг хийх
                    // Үр дүнгүүд нь төрлөөсөө хамаарө өөр өөр гарж ирнэ
                    // Үр дүнгээс гарж ирсэн мэдээллээр content хэсгийн дизайнд харгалзах газар нь байршуулна.
                    $date = (new Carbon(CoreService::getTxnDate($instid)))->format('Y-m-d');
                    $inst = GPInstList::where("id", $instid)->first();
                    $result = $this->evaluateparam($param, $inputvalues, $instid, $inst, $user, $date);
                    // $this->evaluateparam($param, $inputvalues, $instid, $inst, $user, $date);
                    array_push($parameters, $result);
                }
            }

            // Log::channel("report_log")->debug('Fetched parameters generate started');
            // Контент хусгүүдийг авах.
            $contents = ReInstReportTempContent::where("instid", 1)
                ->whereNull("parentid")->where("statusid", 1)
                ->where("templateid", $report->id)
                ->orderBy("listorder", "asc")->get()->toArray();

            // Гараас оруулсан утгуудыг дизайн дотор дуудсан байж болох учир гараас оруулсан утгуудаа нэг дор болгож авах
            $inputs = [];
            if (array_key_exists('inputs', $validate)) {
                foreach ($validate['inputs'] as $input) {
                    $newinput = ReInstReportTempParamIn::where("instid", 1)
                        ->where("statusid", 1)->where("templateid", $report->id)
                        ->where("input", $input['input'])->first();
                    array_push($inputs, array_merge((array) $input, (array) json_decode(json_encode($newinput))));
                }
            } else {
                $validate['inputs'] = [];
            }
            // Тухайн хэрэглэгчийн байгуулгын мэдээлэл авах
            $inst = GPInstList::where("id", $instid)->first();

            // Log::channel("report_log")->debug('Content generating');

            // АНХААР !!!
            // Контентуудыг Параметртэй нэгтгэж дизайны дагуу зургадсан generatdcontent гаргаж авах.
            $generatecontent = $this->processReportContents($report, ['result' => $parameters, 'generated' => true, 'inputs' => $inputs], $contents, $exporttype, $validate['inputs'], $inst, $user);

            // Log::channel("report_log")->debug($report->name . ' generate ended');

            // Excel төрлийн утга буцаах
            if ($exporttype === 2) {
                $randomFilename = generateRandomString(10) . '.xlsx';
                $generatecontent->save($randomFilename);
                $base64Content = base64_encode(file_get_contents($randomFilename));
                $filePath = public_path($randomFilename);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                // Log::channel("report_log")->debug($report->name . ' exported ');
                return ['source' => 'data:application/vnd.ms-excel;base64,' . $base64Content, 'exporttype' => $exporttype];
            }

            // Log::channel("report_log")->debug($report->name . ' exported ');

            // PDF эсвэл HTML төрлийн утга буцах
            return ['source' => $generatecontent, 'exporttype' => $exporttype];
        } else {
            return null;
        }
    }
    // Тусгай түлхүүр буюу ${special_key} төрлийн утгад харгалзах өгөгдлөөр солих функц
    public function special_key_remover($original, $search, $replacement)
    {
        // Use the str_replace function to replace the search with the replacement
        $search = '${' . $search . '}';
        $modifiedText = str_replace($search, $replacement, $original);

        return $modifiedText;
    }

    // тусгай тэмдэгт ${special_key} байгаа эсэх шалгах функц
    function find_special_keys($text)
    {
        $pattern = '/\${(.*?)}/';
        preg_match_all($pattern, $text, $matches);
        $special_keys = $matches[1];
        return count($special_keys);
    }

    // АНХААР !!!
    // Security шалгалт
    // Өгөгдлийн сан дээр хийгдэх query дээр ямар нэг өөр үйлдэл байгаа эсэх шалгах
    function isNonSelectQuery($sqlQuery)
    {
        $uppercaseQuery = strtoupper(trim($sqlQuery));
        $nonSelectKeywords = array('INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'CREATE', 'DROP', 'TRUNCATE');
        foreach ($nonSelectKeywords as $keyword) {
            if (strpos($uppercaseQuery, $keyword) === 0) {
                return true;
            }
        }

        return false;
    }

    // Нөхцөлт query буюу хялбар where нөхцөл тавигдаад хайлт хийгдэх төрөл зорулсан
    // Нөхцөл дээр тавигдсан утгуудыг нь шалгах болон нөхцөлд харгалзах утгуудыг тавих функц
    function processCondition($condition, $bindings, $tablename)
    {
        $pattern = '/\${(\w+)}/';
        preg_match_all($pattern, $condition, $matches);

        $keys = $matches[1];

        foreach ($keys as $key) {
            if (array_key_exists($key, $bindings)) {
                $value = $bindings[$key];
                $columnname = $this->extractColumnName($condition, $key);
                if (count($columnname)) {
                    $columntype = $this->getColumnTypeFromDatabase($columnname[0], $tablename);
                    $formattedValue = $this->formatValueBasedOnType($columntype, $value);
                    // $condition = str_replace('${' . $key . '}', $formattedValue, $condition);
                    $condition = $this->special_key_remover($condition, $key, $formattedValue);
                } else {
                    // $condition = str_replace('${' . $key . '}', '\'' . $value . '\'', $condition);
                    $condition = $this->special_key_remover($condition, $key, '\'' . $value . '\'');
                }
            }
        }
        return $condition;
    }

    // Нөхцөл дээрээс ямар нэг нөхцөл тавигдаж буй талбарын нэрийг авах оролдлого
    // Сайжгуулалт шаарлагатай
    // between гэх мэт нөхцөлийг барьж важ чаддаггүй энгийн линиар буюу '=' ашигласан нөхцөл барьж авдаг

    function extractColumnName($input_string, $special_key)
    {
        $pattern = '/\b([^=]*)\s*=\s*' . preg_quote('${' . $special_key . '}', '/') . '/';
        preg_match_all($pattern, $input_string, $matches);
        return $matches[1];
    }

    // Тухайн table-д нөхцөл тавигдсан талбар байгаа эсэхийг шалгах
    function getColumnTypeFromDatabase($columnname, $tablename)
    {
        try {
            $query = "SELECT data_type FROM information_schema.columns WHERE table_name = ? AND column_name = ?";
            $result = DB::select($query, [$tablename, $columnname]);
            if (count($result) > 0) {
                return $result[0]->data_type;
            } else {
                return null; // or return a default type if you prefer
            }
        } catch (Exception $e) {
            return "Column not found.";
        }
    }
    // Өгөгдлийн сангийн төрлүүдийг харгалзуулан хувьсагчаа програмын хэлний төрөлд хөрвүүлэх
    function formatValueBasedOnType($column_type, $value)
    {
        $column_type = strtolower($column_type);

        switch ($column_type) {
            case 'integer':
            case 'int':
            case 'smallint':
            case 'bigint':
                return (int) $value; // int
            case 'numeric':
            case 'float':
            case 'real':
            case 'double precision':
                return (float) $value; // double
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN); // true of false
            case 'timestamp':
            case 'timestamp without time zone':
            case 'timestamp with time zone':
            case 'date':
            case 'time':
            case 'time without time zone':
            case 'time with time zone':
                return $value; // string and date
            default:
                return $value; // string and date
        }
    }
    // Параметрт харгалзах бодолтуудыг хийх
    public function evaluateparam($param, $inputvalues, $instid, $inst, $user, $date)
    {
        try {
            $eval = ['paramname' => $param->paramname, 'inputvalues' => $inputvalues, 'param' => $param];
            if ($param->hasquery) { // Параметрийн тохируулсан өгөгдлийн сангийн хайлтыг хэрэгжүүлэх
                if (empty($param->query) && !empty($param->formulaid)) {
                    $formula = GPInstFormula::where("id", $param->formulaid)->where("instid", 1)->where("statusid", 1)->first();
                    $param->query = $formula->formula;
                }
                if (!$this->isNonSelectQuery($param->query)) {
                    foreach ($inputvalues as $value) {
                        $tmpvalue = $value['value'];
                        // Гараас multiselect сонгогдоод query дээр ажиллах үед шалгана
                        if (gettype($tmpvalue) == 'array') {
                            $tmpvalue = '';
                            foreach ($value['value'] as $array_value) {
                                if (empty($tmpvalue)) {
                                    $tmpvalue = $array_value;
                                } else {
                                    $tmpvalue = $tmpvalue . ',' . $array_value;
                                }
                            }
                        }
                        $param->query = $this->special_key_remover($param->query, strval($value['input']), strval($tmpvalue));
                    }

                    $param->query = $this->checkInside($param->query, $inputvalues, $inst, $user, $date);

                    if (strpos($param->query, '${instid}')) { // Хэрэв Параметрийн түвшинд instid тохируулсан байвал.
                        $param->query = $this->special_key_remover($param->query, 'instid', $instid);
                    }
                    $param->query = "select * from ( " . $param->query . " ) q where q.instid = " . $instid;
                    // DB::enableQueryLog();
                    // Log::channel("report_log")->debug("DBQuery");
                    // Log::channel("report_log")->debug($param->query);
                    $dbquery = DB::select($param->query);
                    // Log::channel("report_log")->debug("DBQuery Result");
                    // Log::channel("report_log")->debug(json_encode($dbquery));
                    // Log::debug(DB::getQueryLog());
                    // DB::disableQueryLog();
                    if (empty($dbquery)) {
                        return ['result' => [], 'paramname' => $param->paramname, 'inputvalues' => $inputvalues, 'param' => $param];
                    } else {
                        if ($param->type === 1 || $param->type === 2 || $param->type === 3 || $param->type === 4) {
                            // Параметрийн төрөл нь text, number, boolean date төрлүүд бабйх юм бол
                            if (strpos($param->paramname, '.') !== false) {
                                $splitstring = explode('.', $param->paramname);
                                if (property_exists($dbquery[0], $splitstring[count($splitstring) - 1])) {
                                    $eval['result'] = $dbquery[0]->{$splitstring[count($splitstring) - 1]};
                                } else {
                                    $eval['result'] = $dbquery[0][$splitstring[count($splitstring) - 1]];
                                }
                            } else {
                                if (property_exists($dbquery[0], $param->paramname)) {
                                    $eval['result'] = $dbquery[0]->{$param->paramname};
                                } else {
                                    $eval['result'] = $dbquery[0][$param->paramname];
                                }
                            }
                        } else if ($param->type === 5) {
                            // Параметрийн төрөл нь array буюу хүснэгтийн төрөл байх юм бол
                            $data = explode('.', $param->paramname);
                            $finalresult = [];
                            $dbqueryresult = json_decode(json_encode($dbquery));
                            foreach ($dbqueryresult as $result) {
                                array_push($finalresult, $result->{$data[count($data) - 1]});
                            }
                            $eval['result'] = $finalresult;
                        } else if ($param->type === 6 || $param->type === 7) {
                            // Параметрийн төрөл нь Collection/Object/ эсвэл List/Array of Object/ байх юм бол
                            $subparams = ReInstReportTempParam::where("instid", 1)->where("statusid", 1)->where("parentid", $param->id)->get();
                            $eval['subparams'] = $subparams;
                            $eval['result'] = $dbquery;
                            if ($param->type === 6) { // Collection үед хүснэтээр ирсэн мэдээллийг нэг обект болгож байна
                                $eval['result'] = $eval['result'][0];
                            }
                        }
                    }
                } else {
                    return null;
                }
            } else if ($param->tableid) { // Параметрийн тохируулсан хялбаршуулсан нөхцөлт хайлт байвал
                $table = ReInstTable::where("instid", 1)->where("statusid", 1)->where("id", $param->tableid)->first();
                $subparams = ReInstReportTempParam::where("instid", 1)->where("statusid", 1)->where("parentid", $param->id)->get();
                $searchablefield = [];
                // table дээрх талбарыг заасан хэдий ч өөр талбарын нэрээр параметрт ашиглах үед
                // Сайжруулалт хэрэгтэй
                foreach ($subparams as $subparam) {
                    if (!empty($subparam->fieldid)) {
                        $tablefield = ReInstTableField::where("instid", 1)->where("statusid", 1)->where("tableid", $table->id)->where("id", $subparam->fieldid)->first();
                        if (!empty($tablefield)) {
                            if ($tablefield->fieldname !== $subparam->paramname) {
                                array_push($searchablefield, $tablefield->fieldname . " as " . $subparam->paramname);
                            } else {
                                array_push($searchablefield, $tablefield->fieldname);
                            }
                        }
                    }
                }

                // хайлт хийж байгаа хүрээ нь тухайн байгууллагатаа байгааг шалгах
                if ($table->tablename === "GP_inst_list") {
                    // Table болгон instid-тай хэдий ч GP_inst_list ганцаараа id-аар хайгдана
                    $dbquery = DB::table($table->tablename)->select($searchablefield)->where("id", $instid)->where("statusid", "<>", -1);
                } else {
                    // бусад table
                    $dbquery = DB::table($table->tablename)->select($searchablefield)->where("instid", $instid)->where("statusid", "<>", -1);
                }
                // Нөхцөл байх үед
                if ($param->hascondition) {
                    $bindings = [];
                    foreach ($inputvalues as $value) { // Оролтуудыг тухайн нөхцөлд оруулах
                        $bindings[$value['input']] = $value['value'];
                    }
                    if (strpos($param->condition, '${instid}')) { // Хэрэв Параметрийн түвшинд instid тохируулсан байвал.
                        $bindings['instid'] = $instid;
                    }
                    $param->condition = $this->processCondition($param->condition, $bindings, $table->tablename);
                    $dbquery = $dbquery->whereRaw($param->condition);
                }
                // Нэгж обект дата буцаах бол
                if ($param->type === 6 || $param->type === 1 || $param->type === 2 || $param->type === 3 || $param->type === 4) {
                    $dbqueryresult = $dbquery->first();
                } else if ($param->type === 7 || $param->type === 5) { // Хүснэгтэн дата буцаадаг төрөл үед
                    $dbqueryresult = $dbquery->get();
                }

                $eval['result'] = $dbqueryresult;

                if (!empty($subparams)) {
                    $eval['subparams'] = $subparams;
                }
            } else if ($param->evaluate) {
                // Хэрвээ бодолт хийх бол
                // Ганц sql гэлтгүй ямар нэг байдлаар хялбар expression бодолт хийх боломжтой
                // Жишээ нь хоёр он сарын хооронд өдрийн тоог гаргах ч гэдэг юм уу.
                // php дээрх шиг бичиглэл дэмжнэ.
                if ($param->expression === $param->paramname) {
                    if ($param->paramname === 'date') {
                        $eval['result'] = CoreService::getTxnDate(auth()->user()->instid);
                    } else {
                        $eval['result'] = $param->paramname;
                    }
                } else {
                    foreach ($inputvalues as $value) {
                        $string = '${' . $value['input'] . '}';
                        $param->expression = str_replace($string, '\'' . strval($value['value']) . '\'', $param->expression);
                    }
                    if (strpos($param->expression, '${instid}')) {
                        $param->query = str_replace('${instid}', $instid, $param->expression);
                    }
                    $eval['result'] = eval('return ' . $param->expression . ';');
                }
            }
            return $eval;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    // Report Content боловсруулах
    public function processReportContents($report, $data, $contents, $exporttype, $validate_inputs, $inst, $user, $mod = false)
    {
        $in = 0;
        foreach ($contents as &$content) {
            $search = '/\\$\\{(.+?)\\}/';
            preg_match_all($search, $content['source'], $paramKeys, PREG_SET_ORDER); // тухайн контент дотор харгалзах тусгай түлхүүрүүдийг ялгах
            $notFound = [];
            $found = [];
            foreach ($paramKeys as $paramKey) { // тусгай түлхүүрийг боловсруулж харгалзах харгалзах параметрүүдийг олох
                if (strpos($paramKey[1], ',') !== false) {
                    $exploded = explode(',', $paramKey[1]);
                    $paramKey[1] = $exploded[0];
                    $paramKey[2] = $exploded[1];
                }
                $correspondinGPram = null;
                if (array_key_exists('result', $data)) {
                    foreach ($data['result'] as $resultItem) {
                        if (isset($resultItem['paramname']) && isset($paramKey[1])) {
                            if ($resultItem['paramname'] === $paramKey[1]) {
                                $correspondinGPram = $resultItem;
                                break;
                            } elseif (
                                isset(explode('.', $resultItem['paramname'])[0]) &&
                                isset(explode('.', $paramKey[1])[0]) &&
                                explode('.', $resultItem['paramname'])[0] === explode('.', $paramKey[1])[0]
                            ) {
                                $correspondinGPram = $resultItem;
                                break;
                            }
                        }
                    }
                }
                if (!$correspondinGPram) {
                    if (array_key_exists('inputs', $data)) {
                        foreach ($data['inputs'] as $input) {
                            if ($input['input'] === $paramKey[1]) {
                                $correspondinGPram = $input;
                                break;
                            }
                        }
                    }
                }
                if (!$correspondinGPram) {
                    $notFound[] = $paramKey[1];
                } else {
                    $found[] = $correspondinGPram;
                }
            }
            if (count($notFound) || count($found) === 0) { // Хэрвээ тусгай түлхүүр нь парам дотор олдоогүй бол input болон байгууллага хэрэглэгчийн мэдээлэл дотроос хайж тавих
                // sometimes error
                $content['result'] = $this->checkInside($content['source'], $validate_inputs, $inst, $user, (new Carbon(CoreService::getTxnDate($inst->id)))->format('Y-m-d'));
            }
            if (count($found)) {
                // Тухайн контентед харгалзсан параметрийн утгуудаар орлуулан байрлуулах
                $content['result'] = $content['source'];
                if ($content['type'] === 1) {
                    // Контентийн төрөл нь контент үед
                    foreach ($found as $item) {
                        if (!@$item['param']) {
                            while (($index = strpos($content['result'], '${' . $item['input'])) !== false) {
                                $start = $index;
                                $index++;
                                while ($content["result"][$index] !== "}" && $index < strlen($content['result'])) {
                                    $index++;
                                }
                                $end = $index + 1;
                                $maindata = substr($content['result'], $start, $end - $start);
                                $content['result'] = substr_replace($content['result'], $this->checkFunction($maindata, $item['value']), $start, $end - $start);
                            }
                            $content['result'] = str_replace('${' . $item['input'] . "}", $item['value'], $content['result']);
                        } else {
                            if ($item['param']['type'] < 5) {
                                if (is_array(@$item['result'])) {
                                    if (@$item['subparams']) {
                                        foreach ($item['subparams'] as $subparam) {
                                            while (strpos($content['result'], '${' . $item['paramname'] . '.' . $subparam['paramname']) !== false) {
                                                $index = strpos($content['result'], '${' . $item['paramname'] . '.' . $subparam['paramname']);
                                                $start = $index;
                                                $index++;
                                                while ($content['result'][$index] !== "}" && $index < strlen($content['result'])) {
                                                    $index++;
                                                }
                                                $end = $index + 1;
                                                $maindata = substr($content['result'], $start, $end - $start);
                                                $content['result'] = str_replace($maindata, $this->checkFunction($maindata, $item['result']), $content['result']);
                                            }
                                            // $content['result'] = str_replace('${' . $item['paramname'] . "." . $subparam['paramname'] . "}", $item['result'][$subparam['paramname']], $content['result']);
                                        }
                                    }
                                } else {
                                    while (strpos($content['result'], '${' . $item['paramname']) !== false) {
                                        $index = strpos($content['result'], '${' . $item['paramname']);
                                        $start = $index;
                                        $index++;
                                        while ($content['result'][$index] !== "}" && $index < strlen($content['result'])) {
                                            $index++;
                                        }
                                        $end = $index + 1;
                                        $maindata = substr($content['result'], $start, $end - $start);
                                        // Log::debug('$maindata');
                                        // Log::debug($maindata);
                                        $content['result'] = str_replace($maindata, $this->checkFunction($maindata, @$item['result']), $content['result']);
                                    }

                                    // $content['result'] = str_replace('${' . $item['paramname'] . "}", $item['result'], $content['result']);
                                }
                            } else if ($item['param']['type'] === 5) {
                                if (strpos($content['result'], '${' . $item['paramname']) !== false) {
                                    $index = strpos($content['result'], '${' . $item['paramname']);
                                    $start = $index;
                                    $index++;
                                    while ($content['result'][$index] !== "}" && $index < strlen($content['result'])) {
                                        $index++;
                                    }
                                    $end = $index + 1;
                                    $maindata = substr($content['result'], $start, $end - $start);
                                    $item['result'] = implode(', ', $item['result']);
                                    $content['result'] = str_replace($maindata, $this->checkFunction($maindata, $item['result']), $content['result']);
                                }
                            } else if ($item['param']['type'] === 6) {
                                if (!array_key_exists('subparam', $item)) {
                                    $item['subparams'] = ReInstReportTempParam::where("parentid", $item['param']['id'])->where("statusid", "<>", -1)->get();
                                }
                                foreach ($item['subparams'] as $subparam) {
                                    if (strpos($content['result'], '${' . $item['paramname'] . "." . $subparam['paramname']) !== false) {
                                        $index = strpos($content['result'], '${' . $item['paramname'] . "." . $subparam['paramname']);
                                        $start = $index;
                                        $index++;
                                        while ($content['result'][$index] !== "}" && $index < strlen($content['result'])) {
                                            $index++;
                                        }
                                        $end = $index + 1;
                                        $maindata = substr($content['result'], $start, $end - $start);
                                        if (isset($item['result']) && isset($subparam['paramname'])) {
                                            if (is_object($item['result']) && property_exists($item['result'], $subparam['paramname'])) {
                                                $sendingdata = $item['result']->{$subparam['paramname']};
                                            } elseif (is_array($item['result']) && isset($item['result'][$subparam['paramname']])) {
                                                $sendingdata = $item['result'][$subparam['paramname']];
                                            } else {
                                                // Handle the case where property doesn't exist
                                                $sendingdata = ""; // or any other default value
                                            }
                                            if (isset($sendingdata)) $content['result'] = str_replace($maindata, $this->checkFunction($maindata, $sendingdata), $content['result']);
                                            else $content['result'] = str_replace($maindata, "", $content['result']);
                                        } else {
                                            $content['result'] = str_replace($maindata, "", $content['result']);
                                        }
                                    }
                                }
                            } else if ($item['param']['type'] === 7) {
                                //error handling
                            }
                        }
                    }
                    $content['result'] = $this->checkInside($content['result'], $validate_inputs, $inst, $user, (new Carbon(CoreService::getTxnDate(auth()->user()->instid)))->format('Y-m-d'));
                } else if ($content['type'] === 2) { // Контентийн төрөл нь хүснэгт үед
                    if (count($found) > 1) {
                        //error handling
                    } else {
                        $content['children'] = ReInstReportTempContent::where("parentid", $content['id'])->where("statusid", 1)->orderBy("position", "asc")->orderBy("listorder", "asc")->get();

                        $labels = [];
                        $footers = [];
                        $children = [];

                        // $ind = 0;

                        foreach ($content['children']->toArray() as $item) {
                            if ($item['position'] === 1) $labels[] = $item;
                            else if ($item['position'] === 2) $children[] = $item;
                            else if ($item['position'] === 3) $footers[] = $item;
                        }

                        $param = $found[0];
                        $column = count($children);
                        // Зурагдах хүснэгтийн нүднүүдийн мэдээллийг авж байна.
                        if (count($labels) > 0) {
                            for ($i = 0; $i < count($labels); $i++) {
                                $labels[$i] = [
                                    'title' => $labels[$i]['contentname'],
                                    'field' => $children[$i]['contentname'],
                                    'source' => $children[$i]['source'],
                                    'align' => $children[$i]['align'],
                                    'listorder' => $labels[$i]['listorder'],
                                    'width' => $labels[$i]['width']
                                ];
                            }
                        }

                        unset($content['result']);
                        $content['result'] = [];
                        // if(!empty($content['children'])){
                        //     $temp = array_map(function ($x, $i) use ($content, $column) {
                        //         return [
                        //             'title' => $x['contentname'],
                        //             'field' => $content['children'][$column + $i]->{'contentname'},
                        //             'source' => $content['children'][$column + $i]->{'source'},
                        //             'align' => $content['children'][$column + $i]->{'align'},
                        //             'listorder' => $content['children'],
                        //         ];
                        //     }, array_slice($content['children']->toArray(), 0, $column), array_keys(array_slice($content['children']->toArray(), 0, $column)));
                        //     $content['result']['labels'][] = $temp;
                        // }
                        $content['result']['labels'] = $labels;
                        $content['result']['footers'] = $footers;
                        if (!array_key_exists('result', $param)) {
                            $content['result']['data'] = null;
                        } else $content['result']['data'] = $param['result'];
                    }
                }
            }
            if (is_string($content['result'])) {
                $content['result'] = preg_replace_callback('/\[numbertotext:([0-9.]+)\s*,\s*([0-9]+)\]/', function($matches) {
                    return numbertotext(floatval($matches[1]), 0);
                }, $content['result']);
            }
            $contents[$in]['result'] =  $content['result'];
            $in++;
        }
        if ($mod === false) { // Контент үүсгэх үйлдэл
            return $this->generatecontent($contents, $report, $exporttype, $validate_inputs, $inst, $user);
        } else {
            return ['contents' => $contents, 'report' => $report, 'exporttype' => $exporttype, 'validate_inputs' => $validate_inputs, 'inst' => $inst, 'user' => $user];
        }
    }
    // Контент үүсгэх үндсэн функц
    // exporttype -> excel, html, pdf
    // mod -> default утга нь false. true үед excel дээр олон sheet үүсгэж байгаа гэж үзнэ.
    public function generatecontent($contents, $report, $exporttype, $validate_inputs, $inst, $user, $mod = false)
    {
        if (!empty($report['pagemargin']) && !(strpos($report['pagemargin'], 'px') !== false)) {
            $report_exploded = explode($report['pagemargin'], " ");
            $report_to_merge = [];
            foreach ($report_exploded as $item) {
                if ($item !== "auto") {
                    $item = $item . "px";
                }
                array_push($report_to_merge, $item);
            }
            join(" ", $report_exploded);
        } else {
            if (empty($report['pagemargin'])) $report['pagemargin'] = '0';
        }
        $css = '
        @page {
            margin: ' . $report['pagemargin'] . ' !important;
            counter-reset: pcounter;
            @bottom-left {
                content: counter(page);
              }
        }
        body, html {
            width: 100%;
            margin: 0;
            padding: 0;
            height: auto !important;
            overflow: visible !important;
        }
        .contentcontainer{
            box-sizing: border-box;
        }
        #thetable{
            box-sizing: border-box;
        }
        .page-header, .page-header-space {
            height: ' . ($report['headersize'] ?? 0) . ';
        }
        .page-footer, .page-footer-space {
            height: ' . ($report['footersize'] ?? 0) . ';
        }
        .page-footer {
            position: fixed;
            z-index: -1;
            bottom: 0;
            width: 100%;
        }
        .page-header {
            position: fixed;
            z-index: -1;
            top: 0;
            width: 100%;
        }
        .page {
            page-break-after: always;
        }
        @media print {' .
            (intval($report['headerrepeat']) === 1 ? ' .thead {
                display: table-header-group;
            }' : '.thead {
                display: table-row-group;
            }') .
            (intval($report['footerrepeat']) === 1 ? ' .tfoot {
                display: table-footer-group;
            }' : '.tfoot {
                display: table-row-group;
            }') .
            'body {
                font-family: sans-serif;
            }' .
            '}';
        $header = [];
        $body = [];
        $footer = [];
        foreach ($contents as &$content) {
            if ($exporttype !== 2) { // HTML Нэгтгэж гаргах хэсэг
                if ($content['position'] === 1) {
                    if (!is_array($content['result'])) {
                        array_push($header, '<div style="width: ' . $content['width'] . '; height: ' . $content['height'] . '; display: block; box-sizing: border-box; margin: ' . $content['contentmargin'] . ' ;">' . $content['result'] . '</div>');
                    }
                } else if ($content['position'] === 3) {
                    if (!is_array($content['result'])) {
                        array_push($footer, '<div style="width: ' . $content['width'] . '; height: ' . $content['height']  . '; display: block; box-sizing: border-box; margin: ' . $content['contentmargin'] . ' ;">' . $content['result'] . '</div>');
                    }
                } else if ($content['position'] === 2) {
                    if ($content['type'] === 1) {
                        array_push($body, '<div style="background: white; width: ' . $content['width'] . '; height: ' . $content['height'] . '; display: block; box-sizing: border-box; margin: ' . $content['contentmargin'] . ';">' . $content['result'] . '</div>');
                    } else {
                        array_push($body, $this->generatetable($content));
                    }
                }
            } else { // EXCEL TABLE үүсгэхэд хэрэгтэй дата нэгтгэж гаргах хэсэг
                $tmp = $this->generateexceldata($content);
                if (!empty($tmp)) {
                    if ($content['position'] === 1) $header[] = $tmp;
                    if ($content['position'] === 2) $body[] = $tmp;
                    if ($content['position'] === 3) $footer[] = $tmp;
                }
            }
        }

        if ($exporttype !== 2) { // Контентуудыг нэгтгэж нэг html болгох
            $header = join("", $header);
            $body = join("", $body);
            $footer = join("", $footer);

            if ($exporttype == 4) {
                $final = "
                <html
                    xmlns:v='urn:schemas-microsoft-com:vml'
                    xmlns:o='urn:schemas-microsoft-com:office:office'
                    xmlns:w='urn:schemas-microsoft-com:office:word'
                    xmlns:m='http://schemas.microsoft.com/office/2004/12/omml'
                    xmlns='http://www.w3.org/TR/REC-html40'
                >
                    <head>
                        <style type='text/css' media='print'>$css</style>
                        <style>
                            @page Section1 {
                                mso-header-margin:.5in;
                                mso-footer-margin:.5in;
                                mso-header: h1;
                                mso-footer: f1;
                            }
                            div.Section1 { page:Section1; }
                            table#hrdftrtbl
                            {
                                margin:0in 0in 0in 900in;
                                width:1px;
                                height:1px;
                                overflow:hidden;
                            }
                            p.MsoFooter, li.MsoFooter, div.MsoFooter
                            {
                                margin:0in;
                                margin-bottom:.0001pt;
                                mso-pagination:widow-orphan;
                                tab-stops:center 3.0in center 3.0in;
                                font-size:8.0pt;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='Section1'>
                            <div class='contentcontainer'>
                                <table id='thetable'>
                                    " . ((!$report['headerrepeat'] && !empty($header)) ? "<thead class='thead'>
                                        <tr><td>
                                            <div class='page-header-space'>$header</div>
                                        </td></tr>
                                    </thead>" : "") . "
                                    <tbody><tr><td>$body</td></tr></tbody>
                                    <tfoot class='tfoot'><tr><td>
                                        <div class='page-footer-space'>" . (!$report['footerrepeat'] ? $footer : '') . "</div>
                                    </td></tr></tfoot>
                                </table>
                            </div>

                            <table id='hrdftrtbl' border='0' cellspacing='0' cellpadding='0'>
                            <tr>
                                <td>
                                <div style='mso-element:header' id='h1' >
                                <!-- HEADER-tags -->
                                    <div class=MsoHeader >
                                    " . ($report['headerrepeat'] ? $header : '') . "
                                    </div>
                                <!-- end HEADER-tags -->
                                </div>
                                </td>
                                <td>
                                <div style='mso-element:footer' id='f1'><span style='position:relative;z-index:-1'>
                                    <!-- FOOTER-tags -->
                                    <div class='page-footer'>" . ($report['footerrepeat'] ? $footer : '') . "</div>
                                    <span style='mso-no-proof:yes'></span>
                                        <p class=MsoFooter>
                                            <span style='mso-tab-count:2'></span>
                                            <span style='mso-field-code:PAGE'></span>
                                            /
                                            <span style='mso-field-code:NUMPAGES'></span>
                                        </p>
                                </div>
                                </td>
                            </tr>
                            </table>
                        </div>
                    </body>
                </html>";
            } else {
                $final = '
                <html>
                    <head>
                        <style type="text/css" media="print">' . $css . '</style>
                        <script src="https://unpkg.com/pagedjs@0.4.3/dist/paged.polyfill.js"></script>
                    </head>
                    <body>
                        <div class="contentcontainer">
                            <div class="page-header">' . ($report['headerrepeat'] ? $header : '') . '</div>
                            <table id="thetable">
                                <thead class="thead"><tr><td>
                                    <div class="page-header-space">' . (!$report['headerrepeat'] ? $header : '') . '</div>
                                </td></tr></thead>
                                <tbody><tr><td>' . $body . '</td></tr></tbody>
                                <tfoot class="tfoot"><tr><td>
                                    <div class="page-footer-space">' . (!$report['footerrepeat'] ? $footer : '') . '</div>
                                </td></tr></tfoot>
                            </table>
                            <div class="page-footer">' . ($report['footerrepeat'] ? $footer : '') . '</div>
                        </div>
                    </body>
                </html>';
            }

            return $final;
        } else { // Excel Table датануудыг нэгтгэж нэг spreadsheet үүсгэх
            $info = [
                'title' => $report->name,
                'font' => $report->font
            ];

            $finalarray = array_merge($header, $body);
            $finalarray = array_merge($finalarray, $footer);

            if ($mod === false) {
                $final = $this->generatespreadsheet($finalarray, $info, $validate_inputs, $inst, $user);
            } else {
                $final = ['data' => $finalarray, 'info' => $info, 'validate_inputs' => $validate_inputs, 'inst' => $inst, 'user' => $user];
            }
            return $final;
        }
    }

    public function generatetable($table)
    { // HTML table үүсгэх хэсэг
        $tabledata = $table['result'];

        if (isset($tabledata['labels']) === false) return "";

        $widthinfo = implode("", array_map(function ($x) {
            return "<col style=\"width: {$x}\"/>";
        }, explode(" ", $table['colwidth'] ?? '')));

        if ($table['orientation'] === 2) { // Хүснэгт босоо үед
            if ($table['hasheader']) { // Хүснэгтийн толгой үүсгэх
                $labels = implode("", array_map(function ($item) use ($table) {
                    $bordertypes = $table['bordertypes'];
                    $borderwidth = $table['borderwidth'] . 'px';
                    $bordercolor = $table['bordercolor'];
                    $highlightcolor = $table['highlightcolor'] ? $table['highlightcolor'] : "#ffffff";
                    $headerfontsize = $table['headerfontsize'] . 'px';

                    $borderTopStyle = ($bordertypes === 1 || $bordertypes === 2 || $bordertypes === 4) ? 'solid' : 'none';
                    $borderBottomStyle = ($bordertypes === 1 || $bordertypes === 2 || $bordertypes === 4) ? 'solid' : 'none';
                    $borderLeftStyle = ($bordertypes === 1) ? 'solid' : 'none';
                    $borderRightStyle = ($bordertypes === 1) ? 'solid' : 'none';

                    return '<th scope="col" style="border-top-style: ' . $borderTopStyle . '; border-bottom-style: ' . $borderBottomStyle . '; border-left-style: ' . $borderLeftStyle . '; border-right-style: ' . $borderRightStyle . '; border-collapse: collapse; border-width: ' . $borderwidth . '; border-color: ' . $bordercolor . '; background-color: ' . $highlightcolor . '; font-size: ' . $headerfontsize . '; word-wrap: break-word;">' . $item['title'] . '</th>';
                }, $tabledata['labels']));

                $head = '<thead style="' . ($table['tableheaderrepeat'] ? 'display: table-header-group;' : 'display: table-row-group;') . '"><tr>' . $labels . '</tr></thead>';
            } else $head = "";

            if (is_object($tabledata['data']) && method_exists($tabledata['data'], 'toArray')) {
                $tabledata['data'] = $tabledata['data']->toArray();
            } elseif (is_object($tabledata['data'])) {
                $tabledata['data'] = [0 => $tabledata['data']];
            }
            // if (!@$tabledata['data']) {
            //     throw new MeException('Тайлангийн мэдээлэл хоосон байна.');
            // }
            $indexes = array_keys($tabledata['data']);
            // Хүснэгтийн дата үүсгэх
            $data = implode("", array_map(function ($item, $index) use ($table, $tabledata) {
                $bordertypes = $table['bordertypes'];
                $borderwidth = $table['borderwidth'] . 'px';
                $bordercolor = $table['bordercolor'];
                $maincolor = $table['maincolor'];
                $datafontsize = $table['datafontsize'] . 'px';
                $alternativecolor = $table['alternativecolor'];

                $borderTopStyle = ($bordertypes === 1 || $bordertypes === 2 || $bordertypes === 4) ? 'solid' : 'none';
                $borderBottomStyle = ($bordertypes === 1 || $bordertypes === 2 || $bordertypes === 4) ? 'solid' : 'none';
                $borderLeftStyle = ($bordertypes === 1) ? 'solid' : 'none';
                $borderRightStyle = ($bordertypes === 1) ? 'solid' : 'none';

                $tmp = implode("", array_map(function ($label) use ($item, $borderTopStyle, $borderBottomStyle, $borderLeftStyle, $borderRightStyle, $borderwidth, $bordercolor, $maincolor, $datafontsize, $index, $alternativecolor) {
                    $align = ($label['align'] === 1 ? 'left' : ($label['align'] === 2 ? 'center' : ($label['align'] === 3 ? 'right' : 'left')));
                    if (property_exists($item, $label['field'])) {
                        return '<td style="text-align: ' . $align . '; border-top-style: ' . $borderTopStyle . '; border-bottom-style: ' . $borderBottomStyle . '; border-left-style: ' . $borderLeftStyle . '; border-right-style: ' . $borderRightStyle . '; border-collapse: collapse; border-width: ' . $borderwidth . '; border-color: ' . $bordercolor . '; background-color: ' . ($index % 2 === 0 ? $maincolor : ($alternativecolor ? $alternativecolor : '#FFFFFF')) . '; font-size: ' . $datafontsize . '; word-wrap: break-word;">' . $this->checkFunction($label['source'], $item->{$label['field']}) . '</td>';
                    } else {
                        return '<td style="text-align: ' . $align . '; border-top-style: ' . $borderTopStyle . '; border-bottom-style: ' . $borderBottomStyle . '; border-left-style: ' . $borderLeftStyle . '; border-right-style: ' . $borderRightStyle . '; border-collapse: collapse; border-width: ' . $borderwidth . '; border-color: ' . $bordercolor . '; background-color: ' . ($index % 2 === 0 ? $maincolor : ($alternativecolor ? $alternativecolor : '#FFFFFF')) . '; font-size: ' . $datafontsize . '; word-wrap: break-word;"> ' . '</td>';
                    }
                }, $tabledata['labels']));
                return "<tr>{$tmp}</tr>";
            }, $tabledata['data'], $indexes));

            $body = '<tbody>' . $data . '</tbody>';
            $contentmargin = $table['contentmargin'] ? $table['contentmargin'] : '0';

            $final = '<div style="margin: ' . $contentmargin . ';"><table style="width: 100%; table-layout: fixed; border-style: ' . ($table['bordertypes'] === 1 || $table['bordertypes'] === 2 || $table['bordertypes'] === 3 ? 'solid' : 'none') . '; border-collapse: collapse; border-width: ' . $table['borderwidth'] . '; border-color: ' . $table['bordercolor'] . '">' . $widthinfo . $head . $body . '</table></div>';
            return $final;
        }

        if ($table['orientation'] === 1) { // Хүснэгт хэвтээ үед
            if (is_object($tabledata['data']) && method_exists($tabledata['data'], 'toArray')) {
                $tabledata['data'] = $tabledata['data']->toArray();
            } elseif (is_object($tabledata['data'])) {
                $tabledata['data'] = [0 => $tabledata['data']];
            }

            if ($table['hasheader']) {
                $labelindexes = array_keys($tabledata['labels']);
                $data = implode("", array_map(function ($item, $index) use ($table, $tabledata) {
                    $bordertypes = $table['bordertypes'];
                    $borderwidth = $table['borderwidth'] . 'px';
                    $bordercolor = $table['bordercolor'];
                    $maincolor = $table['maincolor'];
                    $datafontsize = $table['datafontsize'] . 'px';
                    $alternativecolor = $table['alternativecolor'];
                    $highlightcolor = $table['highlightcolor'] ? $table['highlightcolor'] : "#ffffff";
                    $headerfontsize = $table['headerfontsize'] . 'px';

                    $borderTopStyle = ($bordertypes === 1 || $bordertypes === 2 || $bordertypes === 4) ? 'solid' : 'none';
                    $borderBottomStyle = ($bordertypes === 1 || $bordertypes === 2 || $bordertypes === 4) ? 'solid' : 'none';
                    $borderLeftStyle = ($bordertypes === 1) ? 'solid' : 'none';
                    $borderRightStyle = ($bordertypes === 1) ? 'solid' : 'none';

                    $header = '<th scope="col" style="border-top-style: ' . $borderTopStyle . '; border-bottom-style: ' . $borderBottomStyle . '; border-left-style: ' . $borderLeftStyle . '; border-right-style: ' . $borderRightStyle . '; border-collapse: collapse; border-width: ' . $borderwidth . '; border-color: ' . $bordercolor . '; background-color: ' . $highlightcolor . '; font-size: ' . $headerfontsize . '; word-wrap: break-word;">' . $item['title'] . '</th>';
                    $body = implode("", array_map(function ($bodyitem) use ($item, $borderTopStyle, $borderBottomStyle, $borderLeftStyle, $borderRightStyle, $borderwidth, $index, $bordercolor, $datafontsize) {
                        $align = ($item['align'] === 1 ? 'left' : ($item['align'] === 2 ? 'center' : ($item['align'] === 3 ? 'right' : 'left')));
                        if (property_exists($bodyitem, $item['field'])) {
                            return '<td style="text-align: ' . $align . '; border-top-style: ' . $borderTopStyle . '; border-bottom-style: ' . $borderBottomStyle . '; border-left-style: ' . $borderLeftStyle . '; border-right-style: ' . $borderRightStyle . '; border-collapse: collapse; border-width: ' . $borderwidth . '; border-color: ' . $bordercolor . '; background-color: ' . '; font-size: ' . $datafontsize . '; word-wrap: break-word;">' . $this->checkFunction($item['source'], $bodyitem->{$item['field']}) . '</td>';
                        } else {
                            return '<td style="text-align: ' . $align . '; border-top-style: ' . $borderTopStyle . '; border-bottom-style: ' . $borderBottomStyle . '; border-left-style: ' . $borderLeftStyle . '; border-right-style: ' . $borderRightStyle . '; border-collapse: collapse; border-width: ' . $borderwidth . '; border-color: ' . $bordercolor . '; background-color: ' . '; font-size: ' . $datafontsize . '; word-wrap: break-word;"> ' . '</td>';
                        }
                    }, $tabledata['data']));

                    return "<tr>$header $body</tr>";
                }, $tabledata['labels'], $labelindexes));

                $head = '';
            } else $head = "";

            $body = '<tbody>' . $data . '</tbody>';
            $contentmargin = $table['contentmargin'] ? $table['contentmargin'] : '0';
            $final = '<div style="margin: ' . $contentmargin . ';"><table style="width: 100%; table-layout: fixed; border-style: ' . ($table['bordertypes'] === 1 || $table['bordertypes'] === 2 || $table['bordertypes'] === 3 ? 'solid' : 'none') . '; border-collapse: collapse; border-width: ' . $table['borderwidth'] . '; border-color: ' . $table['bordercolor'] . '">' . $widthinfo . $head . $body . '</table></div>';
            return $final;
        }
        return "";
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
                    $qrCode = QrCode::create($data ?? '');
                    $result = $writer->write($qrCode);
                    // Output as PNG image
                    $pngData = $result->getString();

                    // Convert PNG data to Base64
                    $base64 = base64_encode($pngData);

                    // For direct embedding in an HTML img tag
                    return 'data:image/png;base64,' . $base64;
                } else if ($source[1] === 'qrImg') {
                    return 'https://quickchart.io/qr?text=' . urlencode($data ?? "");
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
                } else if ($source[1] === 'amounttotext') {
                    return numbertotext(floatval($data), 2, true);
                } else if ($source[1] === 'amounttotext-1') {
                    return numbertotext(floatval($data), 1, true);
                } else if ($source[1] === 'amounttotext-3') {
                    return numbertotext(floatval($data), 3, true);
                } else if ($source[1] === 'amounttotext-4') {
                    return numbertotext(floatval($data), 4, true);
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

    public function checkCellFunction($source, $data)
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
            } else {
                if ($source[1] === 'currency') {
                    return floatval($data);
                } else if ($source[1] === 'currency-1') {
                    return floatval($data);
                } else if ($source[1] === 'currency-3') {
                    return floatval($data);
                } else if ($source[1] === 'currency-4') {
                    return floatval($data);
                } else if ($source[1] === 'numtotext') {
                    return floatval($data);
                } else if ($source[1] === 'numtotext-1') {
                    return floatval($data);
                } else if ($source[1] === 'numtotext-3') {
                    return floatval($data);
                } else if ($source[1] === 'numtotext-4') {
                    return floatval($data);
                } else if ($source[1] === 'amounttotext') {
                    return numbertotext(floatval($data), 2, true);
                } else if ($source[1] === 'amounttotext-1') {
                    return numbertotext(floatval($data), 1, true);
                } else if ($source[1] === 'amounttotext-3') {
                    return numbertotext(floatval($data), 3, true);
                } else if ($source[1] === 'amounttotext-4') {
                    return numbertotext(floatval($data), 4, true);
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

    public function generateexceldata($table)
    {
        $tabledata = $table['result'];
        if ($table['type'] === 1) {
            $data = [];

            if (array_key_exists('maincolor', $table) && !empty($table['maincolor'])) {
                $data['maincolor'] = $table['maincolor'];
            }

            if (array_key_exists('textcolor', $table) && !empty($table['textcolor'])) {
                $data['textcolor'] = $table['textcolor'];
            }

            if (array_key_exists('height', $table) && !empty($table['height'])) {
                $data['height'] = $table['height'];
            }

            if (array_key_exists('datafontsize', $table) && !empty($table['datafontsize'])) {
                $data['datafontsize'] = $table['datafontsize'];
            }

            if (array_key_exists('align', $table) && !empty($table['align'])) {
                $align = ($table['align'] === 1 ? 'left' : ($table['align'] === 2 ? 'center' : ($table['align'] === 3 ? 'right' : 'left')));
                $data['align'] = $align;
            }

            if (array_key_exists('verticalalign', $table) && !empty($table['verticalalign'])) {
                $verticalalign = ($table['verticalalign'] === 1 ? 'top' : ($table['verticalalign'] === 2 ? 'center' : ($table['verticalalign'] === 3 ? 'buttom' : 'top')));
                $data['verticalalign'] = $verticalalign;
            }

            $data['excelshift'] = 1;

            $data['data'] = $tabledata;
            $data['type'] = 'content';
            $data['tablename'] = $table['contentname'];

            return $data;
        } else if ($table['type'] === 2 && $table['orientation'] === 2 && (is_array($tabledata) || is_object($tabledata))) {
            $data = [];
            $data['align'] = [];
            $footers = [];
            $columnsText = array();
            $width = array();

            foreach ($tabledata['labels'] as $item) {
                $columnsText[] = $item['title'];
                if (array_key_exists('width', $item) && !empty($item['width'])) {
                    $width[] = $item['width'];
                } else $width[] = 'auto';
                if (array_key_exists('align', $item) && !empty($item['align'])) {
                    $align = ($item['align'] === 1 ? 'left' : ($item['align'] === 2 ? 'center' : ($item['align'] === 3 ? 'right' : 'left')));
                    $data['align'][] = $align;
                } else $data['align'][] = 3;
            }

            $sheetData = array();
            if (count($columnsText) > 0 && array_key_exists('hasheader', $table) && !empty($table['hasheader']) && $table['hasheader'] === 1) {
                $sheetData[] = $columnsText;
                if (array_key_exists('headerfontsize', $table) && !empty($table['headerfontsize'])) {
                    $data['headerfontsize'] = $table['headerfontsize'];
                }
                if (array_key_exists('highlightcolor', $table) && !empty($table['highlightcolor'])) {
                    $data['highlightcolor'] = $table['highlightcolor'];
                }
                $data['hasheader'] = true;
            }

            if (array_key_exists('datafontsize', $table) && !empty($table['datafontsize'])) {
                $data['datafontsize'] = $table['datafontsize'];
            }

            if (array_key_exists('maincolor', $table) && !empty($table['maincolor'])) {
                $data['maincolor'] = $table['maincolor'];
            }

            if (array_key_exists('alternativecolor', $table) && !empty($table['alternativecolor'])) {
                $data['alternativecolor'] = $table['alternativecolor'];
            }

            if (array_key_exists('bordertypes', $table) && !empty($table['bordertypes'])) {
                $data['bordertypes'] = $table['bordertypes'];
            } else $data['bordertypes'] = 0;

            if (array_key_exists('data', $tabledata) && !empty($tabledata['data'])) {
                foreach ($tabledata['data'] as $item) {
                    $rowData = array();
                    $sheetInsertData = array();
                    foreach ($tabledata['labels'] as $label) {
                        if (isset($item->{$label['field']})) {
                            $rowData[$label['field']] = $this->checkCellFunction($label['source'], $item->{$label['field']});
                        } else {
                            $rowData[$label['field']] = '';
                        }
                        $sheetInsertData[] = $rowData[$label['field']];
                    }
                    $sheetData[] = $sheetInsertData;
                    $data[] = $rowData;
                }
            }


            $data['rowcount'] = count($sheetData);

            if (array_key_exists('hasfooter', $table) && !empty($table['hasfooter'])) {
                for ($i = 0; $i < count($sheetData[0]); $i++) {
                    $footers[$i] = '';
                }
                foreach ($tabledata['footers'] as $item) {
                    $footers[$item['listorder'] - 1] = '=' . $item['source'];
                }
                $data['footers'] = $footers;
                $data['hasfooter'] = true;
                $data['rowcount']++;
            }

            // $data = ['data' => $data, 'columns' => $columns];
            $data["data"] = $sheetData;
            if (isset($data["data"][0])) {
                $data['colcount'] = count($data["data"][0]);
            } else $data['colcount'] = 0;
            $data['tablename'] = $table['contentname'];
            $data["width"] = $width;
            $data['type'] = 'table';

            if (array_key_exists('excelshift', $table) && !empty($table['excelshift'])) {
                $data['excelshift'] = $table['excelshift'];
            } else $data['excelshift'] = 1;

            return $data;
        } else {
            return null;
        }
    }

    // public function generateBulk($validate, $instid, $user){
    //     $result = [];
    //     $spreadsheet = $this->createSpreadsheet();
    //     foreach($validate['sheets'] as $item){
    //         $report = ReInstReportTemp::where("instid", $instid)->where("statusid", 1)->where("ACTION_CODE", $validate['ACTION_CODE'])->first();
    //         if($report){
    //             $result[] = $this->generate($item, $instid, $user);
    //         }
    //     }
    //     // $this->createFile()
    // }

    public function generatespreadsheet($data, $info, $validate_inputs, $inst, $user)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setCreator("Fiba LLC");
        $spreadsheet->getProperties()->setLastModifiedBy("Fiba LLC");

        $operationList = ['${bold}', '${italic}', '${long}', '${roundtwo}', '${percentage}', '${dateformat}', '${vtop}', '${vmiddle}', '${vbottom}', '${hleft}', '${hcenter}', '${hright}', '${border}', '${underline}', '${nowrap}'];

        if (array_key_exists('title', $info) && !empty($info['title'])) {
            $spreadsheet->getProperties()->setTitle($info['title']);
        }
        if (array_key_exists('subject', $info) && !empty($info['subject'])) {
            $spreadsheet->getProperties()->setSubject($info['subject']);
        }
        if (array_key_exists('description', $info) && !empty($info['description'])) {
            $spreadsheet->getProperties()->setDescription($info['description']);
        }

        $font = null;
        if (array_key_exists('font', $info) && !empty($info['font'])) {
            switch (intval($info['font'])) {
                case 2:
                    $font = "Calibri";
                    break;
                case 3:
                    $font = "Arial";
                    break;
                default:
                    $font = "Times New Roman";
                    break;
            }
        }

        $worksheet = $spreadsheet->getActiveSheet();

        $maxwidth = 0;

        $keys = array(1);
        $maxes = array(1);

        foreach ($data as $item) {
            $found = false;
            for ($i = 0; $i < count($keys); $i++) {
                if ($keys[$i] === $item['excelshift']) {
                    if (isset($item['data'][0]) && is_countable($item['data'][0]) && $maxes[$i] < count($item['data'][0])) {
                        $maxes[$i] = count($item['data'][0]);
                    } else {
                        // Log::channel("report_log")->debug("Something Went Wrong");
                        // Log::channel("report_log")->debug($item['data']);
                        // Log::channel("report_log")->debug("Something Went Wrong");
                    }
                    $found = true;
                }
            }
            if ($found === false) {
                $keys[] = $item['excelshift'];
                $maxes[] = count($item['data'][0]);
            }
        }

        $iterators = [];

        for ($i = 0; $i < count($keys); $i++) {
            $maxwidth += $maxes[$i];
            $iterators[] = 2;
        }

        // $iterator = 2;

        foreach ($data as $item) {
            if ($item['type'] === 'content') {
                $max = $iterators[0];
                for ($i = 0; $i < count($keys); $i++) {
                    if ($max < $iterators[$i]) {
                        $max = $iterators[$i];
                    }
                }
                $iterator = $max;
                $worksheet->mergeCells("A" . strval($iterator) . ':' . $this->findingColumn($maxwidth) . strval($iterator)); // Хүснэгт нэгтгэх
                $item['data'] = str_replace("\n", "\r\n", $item['data']);
                if (strpos($item['data'], '${space}') !== false) {
                    $item['data'] = str_replace('${space}', ' ', $item['data']);
                }
                $cellData = $item['data'];
                $item['data'] = str_replace($operationList, '', $item['data']);

                $worksheet->setCellValue("A" . strval($iterator), $item['data']); // Хүснэгтэд текст оруулах

                if ((array_key_exists('bold', $item) && !empty($item['bold']))) {
                    $cellData .= '${bold}';
                }

                if (strpos($cellData, '${bold}') !== false) {
                    $worksheet->getStyle("A" . strval($iterator))->getFont()->setBold(true);
                }
                if (strpos($cellData, '${italic}') !== false) {
                    $worksheet->getStyle("A" . strval($iterator))->getFont()->setItalic(true);
                }


                if (array_key_exists('maincolor', $item) && !empty($item['maincolor'])) { // Background өнгө оруулах /Арийн өнгө оруулах/
                    $color = str_replace("#", "", $item['maincolor']);
                    $worksheet->getStyle("A" . strval($iterator))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                }
                if (array_key_exists('textcolor', $item) && !empty($item['textcolor'])) { // Текст өнгө оруулах
                    $color = str_replace("#", "", $item['textcolor']);
                    $worksheet->getStyle("A" . strval($iterator))->getFont()->getColor()->setRGB($color);
                }

                if (array_key_exists('height', $item) && !empty($item['height'])) { // Текстийн өндөр оруулах
                    $height = str_replace("px", "", $item['height']);
                    $height = (intval($height) * 27) / 36;
                    $worksheet->getRowDimension(strval($iterator))->setRowHeight($height);
                } else {
                    $worksheet->getRowDimension(strval($iterator))->setRowHeight(-1);
                }
                if (array_key_exists('datafontsize', $item) && !empty($item['datafontsize'])) { // Текстийн фонтын өндөр оруулах
                    $fontsize = str_replace("px", "", $item['datafontsize']);
                    $worksheet->getStyle("A" . strval($iterator))->getFont()->setSize($fontsize);
                }
                if (array_key_exists('align', $item) && !empty($item['align'])) {
                    switch ($item['align']) {
                        case 'left': // For the first column
                            $alignment = Alignment::HORIZONTAL_LEFT;
                            break;
                        case 'center':
                            $alignment = Alignment::HORIZONTAL_CENTER;
                            break;
                        default:
                            $alignment = Alignment::HORIZONTAL_RIGHT;
                    }
                    $worksheet->getStyle("A" . strval($iterator))->getAlignment()->setHorizontal($alignment);
                }
                if (array_key_exists('verticalalign', $item) && !empty($item['verticalalign'])) {
                    switch ($item['verticalalign']) {
                        case 'top': // For the first column
                            $alignment = Alignment::VERTICAL_TOP;
                            break;
                        case 'center':
                            $alignment = Alignment::VERTICAL_CENTER;
                            break;
                        default:
                            $alignment = Alignment::VERTICAL_BOTTOM;
                    }
                    $worksheet->getStyle("A" . strval($iterator))->getAlignment()->setVertical($alignment);
                }

                if (!empty($font)) {
                    $worksheet->getStyle("A" . strval($iterator))->getFont()->setName($font);
                }

                $iterator++;

                for ($i = 0; $i < count($keys); $i++) {
                    $iterators[$i] = $iterator;
                }
            } else if ($item['type'] === 'table') {
                // $max = $iterators[0];
                // for($i = 0; $i < count($keys); $i++){
                //     if($max < $iterators[$i]){
                //         $max = $iterators[$i];
                //     }
                // }
                // for($i = 0; $i < count($keys); $i++){
                //     $iterators[$i] = $max;
                // }
                // $iterator = $max;

                $startcol = 0;
                $iterator = $iterators[0];
                $tmp_index = 0;

                for ($i = 0; $i < count($keys); $i++) {
                    if ($keys[$i] !== $item['excelshift']) {
                        $startcol += $maxes[$i];
                    } else {
                        $iterator = $iterators[$i];
                        $tmp_index = $i;
                        break;
                    };
                }

                $hasheader = array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true;
                $hasfooter = array_key_exists('hasfooter', $item) && !empty($item['hasfooter']) && $item['hasfooter'] === true;

                for ($i = 0; $i < count($item['data']); $i++) {
                    for ($j = 0; $j < count($item['data'][$i]); $j++) {
                        $item['data'][$i][$j] = $this->processCellExp($this->checkInside($item['data'][$i][$j], $validate_inputs, $inst, $user, (new Carbon(CoreService::getTxnDate(auth()->user()->instid)))->format('Y-m-d')), $i, $startcol + $j, $iterator, $item['rowcount'], $item['colcount'], $hasheader, $hasfooter, $data, $keys);
                    }
                }

                if (array_key_exists('hasfooter', $item) && !empty($item['hasfooter'])) {
                    $footers = [];
                    $ind = 0;

                    foreach ($item['footers'] as &$footeritem) {
                        $footers[] = $this->processCellExp($this->checkInside($footeritem, $validate_inputs, $inst, $user, (new Carbon(CoreService::getTxnDate(auth()->user()->instid)))->format('Y-m-d')), count($item['data']), $startcol + $ind, $iterator, $item['rowcount'], $item['colcount'], $hasheader, $hasfooter, $data, $keys);
                        $ind++;
                    }
                    $item['data'][] = $footers;
                }

                $ij = 0;

                // Get an array of merged cell ranges
                $mergedCellRanges = $worksheet->getMergeCells();

                if (isset($item['datafontsize'])) $fontsizetmp = str_replace("px", "", $item['datafontsize']);
                if (isset($item['data'][0])) $numCols = count($item['data'][0]);
                else $numCols = 0;
                $numRows = count($item['data']);


                if (array_key_exists('align', $item) && !empty($item['align'])) {
                    for ($col = 0; $col < $numCols; $col++) {
                        $columnLetter = Coordinate::stringFromColumnIndex($startcol + $col + 1);
                        $range = $columnLetter . $iterator . ':' . $columnLetter . ($iterator + $numRows - 1);
                        switch ($item['align'][$col]) {
                            case 'left': // For the first column
                                $alignment = Alignment::HORIZONTAL_LEFT;
                                break;
                            case 'center':
                                $alignment = Alignment::HORIZONTAL_CENTER;
                                break;
                            default:
                                $alignment = Alignment::HORIZONTAL_RIGHT;
                        }
                        $worksheet->getStyle($range)->getAlignment()->setHorizontal($alignment);
                    }
                }

                foreach ($item['data'] as $rowData) {
                    foreach ($rowData as $columnIndex => $cellData) {
                        // Calculate the cell coordinates
                        $cellCoordinate = $this->findingColumn($startcol + 1 + $columnIndex) . ($iterator + $ij);
                        $richText = null;

                        if (!$this->checkMerged($mergedCellRanges, $cellCoordinate)) {
                            // Cell is not merged, so set the value and apply the number format if applicable
                            preg_match_all('/\${(.*?)}/', $cellData, $matches);
                            $col = null;
                            $row = null;
                            $color = null;
                            $ctcolor = null;
                            $height = null;
                            $startCellCoordinate = null;
                            $endCellCoordinate = null;
                            foreach ($matches[1] as $match) {
                                // Use a single regex to match both ${mcol:123,mrow:456} and ${mrow:789,mcol:012} and extract numbers
                                if (preg_match('/mcol:(\d+).*?mrow:(\d+)/', $match, $submatches) || preg_match('/mrow:(\d+).*?mcol:(\d+)/', $match, $submatches)) {
                                    $col = $submatches[1];
                                    $row = $submatches[2];
                                    $cellData = preg_replace('/\${mcol:(\d+).*?mrow:(\d+)}/', '', $cellData);
                                    $cellData = preg_replace('/\${mrow:(\d+).*?mcol:(\d+)}/', '', $cellData);
                                    break; // exit loop once mcol and mrow values are found
                                } elseif (preg_match('/ctcolor:([A-Fa-f0-9]{6})/', $match, $submatches)) {
                                    $cellData = preg_replace('/\${ctcolor:[A-Fa-f0-9]{6}}/', '', $cellData);
                                    $ctcolor = $submatches[1];
                                } elseif (preg_match('/color:([A-Fa-f0-9]{6})/', $match, $submatches)) {
                                    $cellData = preg_replace('/\${color:[A-Fa-f0-9]{6}}/', '', $cellData);
                                    $color = $submatches[1];
                                } elseif (preg_match('/height:([A-Fa-f0-9]{6})/', $match, $submatches)) {
                                    $cellData = preg_replace('/\${height:[A-Fa-f0-9]{6}}/', '', $cellData);
                                    $height = $submatches[1];
                                }
                            }
                            if ($col !== null && $row !== null) {
                                // Calculate the merged cell coordinates based on current cell
                                $startCellCoordinate = $this->findingColumn($startcol + 1 + $columnIndex) . ($iterator + $ij);
                                $endCellCoordinate = $this->findingColumn($startcol + 1 + $columnIndex + $col) . ($iterator + $ij + $row);

                                $worksheet->mergeCells($startCellCoordinate . ':' . $endCellCoordinate);
                            }

                            if (preg_match('/\${tcolor:[A-Fa-f0-9]{6}}/', $cellData)) {
                                $richText = new RichText();

                                $tmpcelldata = str_replace($operationList, '', $cellData);

                                $elements = preg_split('/(\${tcolor:[A-Fa-f0-9]{6}}|\${\/tcolor:[A-Fa-f0-9]{6}})/', $tmpcelldata, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                                $colorApplied = false;
                                foreach ($elements as $element) {
                                    if (preg_match('/\${tcolor:([A-Fa-f0-9]{6})}/', $element, $matches)) {
                                        $colorApplied = $matches[1];
                                    } elseif (preg_match('/\${\/tcolor:([A-Fa-f0-9]{6})}/', $element, $matches)) {
                                        $colorApplied = false;
                                    } else {
                                        if ($colorApplied) {
                                            $textRun = $richText->createTextRun($element);
                                            $textRun->getFont()->setColor(new Color('FF' . $colorApplied));
                                            if (!empty($font)) $textRun->getFont()->setName($font);
                                            if (!empty($fontsizetmp)) $textRun->getFont()->setSize($fontsizetmp);
                                        } else {
                                            $textRun = $richText->createTextRun($element);
                                            $textRun->getFont()->setColor(new Color('FF' . "000000"));
                                            if (!empty($font)) $textRun->getFont()->setName($font);
                                            if (!empty($fontsizetmp)) $textRun->getFont()->setSize($fontsizetmp);
                                        }
                                    }
                                }

                                $worksheet->setCellValue($cellCoordinate, $richText);
                            } else {
                                $tmp = str_replace($operationList, '', $cellData);
                                if (strpos($tmp, '${space}') !== false) {
                                    $tmp = str_replace('${space}', ' ', $cellData);
                                }
                                if (strlen($tmp) === 0) {
                                    $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                                }
                                $worksheet->setCellValue($cellCoordinate, str_replace($operationList, '', $tmp));
                            }

                            if (strpos($cellData, '${bold}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getFont()->setBold(true);
                            }

                            if (strpos($cellData, '${italic}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getFont()->setItalic(true);
                            }

                            if (strpos($cellData, '${underline}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getFont()->setUnderline(true);
                            }

                            if (strpos($cellData, '${vtop}') !== false) {
                                $worksheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                            }

                            if (strpos($cellData, '${vmiddle}') !== false) {
                                $worksheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                            }

                            if (strpos($cellData, '${vbottom}') !== false) {
                                $worksheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);
                            }

                            if (strpos($cellData, '${hleft}') !== false) {
                                $worksheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                            }

                            if (strpos($cellData, '${hcenter}') !== false) {
                                $worksheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            }

                            if (strpos($cellData, '${hright}') !== false) {
                                $worksheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                            }

                            if (strpos($cellData, '${border}') !== false) {
                                $borders = $worksheet->getStyle($cellCoordinate)->getBorders();
                                if (!empty($startCellCoordinate) && !empty($endCellCoordinate)) $borders = $worksheet->getStyle($startCellCoordinate . ':' . $endCellCoordinate)->getBorders();

                                $borders->getLeft()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getLeft()->setColor(new Color(Color::COLOR_BLACK));

                                $borders->getRight()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getRight()->setColor(new Color(Color::COLOR_BLACK));

                                $borders->getTop()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getTop()->setColor(new Color(Color::COLOR_BLACK));

                                $borders->getBottom()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getBottom()->setColor(new Color(Color::COLOR_BLACK));
                            }

                            if (!empty($font)) {
                                $worksheet->getStyle($cellCoordinate)->getFont()->setName($font);
                            }

                            if ($height) {
                                $row = Coordinate::coordinateFromString($cellCoordinate)->getRow();
                                $rowDimension = $worksheet->getRowDimension($row);
                                $rowDimension->setRowHeight(intval($height));
                            }

                            if ($color) {
                                $worksheet->getStyle($cellCoordinate)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                            }

                            if ($ctcolor) {
                                $worksheet->getStyle($cellCoordinate)->getFont()->getColor()->setRGB($ctcolor);
                            }
                            if (strpos($cellData, '${roundtwo}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                            } else if (strpos($cellData, '${long}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                            } else if (strpos($cellData, '${dateformat}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);
                            } else if (strpos($cellData, '${percentage}') !== false) {
                                $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                            } else if (is_numeric($cellData)) {
                                $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                                if (is_float($cellData)) {
                                    $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                                } elseif (is_int($cellData)) {
                                    $worksheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0');
                                }
                            }

                            if ($item['bordertypes'] === 1) {
                                $borders = $worksheet->getStyle($cellCoordinate)->getBorders();
                                if (!empty($startCellCoordinate) && !empty($endCellCoordinate)) $borders = $worksheet->getStyle($startCellCoordinate . ':' . $endCellCoordinate)->getBorders();

                                $borders->getLeft()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getLeft()->setColor(new Color(Color::COLOR_BLACK));

                                $borders->getRight()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getRight()->setColor(new Color(Color::COLOR_BLACK));

                                $borders->getTop()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getTop()->setColor(new Color(Color::COLOR_BLACK));

                                $borders->getBottom()->setBorderStyle(Border::BORDER_THIN);
                                $borders->getBottom()->setColor(new Color(Color::COLOR_BLACK));
                            }
                            if (strpos($cellData, '${nowrap}') === false) {
                                $worksheet->getStyle($cellCoordinate)->getAlignment()->setWrapText(true);
                            }
                        }
                    }

                    $ij++; // Move to the next row
                }

                if (array_key_exists('width', $item) && !empty($item['width'])) {
                    for ($row = 0; $row < count($item['width']); $row++) {
                        if ($item['width'][$row] === 'auto') continue;
                        $width = str_replace("px", "", $item['width'][$row]);
                        // inch to pixel convert
                        $width = intval($width) / 7;
                        $mainrow = $this->findingColumn($startcol + $row + 1);
                        $worksheet->getColumnDimension($mainrow)->setWidth($width);
                    }
                }

                if (array_key_exists('headerfontsize', $item) && !empty($item['headerfontsize']) && array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) {
                    $fontsize = str_replace("px", "", $item['headerfontsize']);
                    for ($col = 0; $col < $numCols; $col++) {
                        $columnLetter = Coordinate::stringFromColumnIndex($startcol + $col + 1);
                        $worksheet->getStyle($columnLetter . $iterator)->getFont()->setSize($fontsize);
                    }
                }

                if (array_key_exists('datafontsize', $item) && !empty($item['datafontsize'])) {
                    $fontsize = str_replace("px", "", $item['datafontsize']);
                    for ($row = (array_key_exists('headerfontsize', $item) && !empty($item['headerfontsize']) && array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) ? 1 : 0; $row < $numRows; $row++) { // Start from 1 because 0 is for headers
                        for ($col = 0; $col < $numCols; $col++) {
                            $columnLetter = Coordinate::stringFromColumnIndex($startcol + $col + 1);
                            $worksheet->getStyle($columnLetter . ($iterator + $row))->getFont()->setSize($fontsize);
                        }
                    }
                }

                if (array_key_exists('highlightcolor', $item) && !empty($item['highlightcolor']) && array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) {
                    $color = str_replace("#", "", $item['highlightcolor']);
                    $startColumn = $this->findingColumn($startcol + 1);
                    $endColumn = $this->findingColumn($startcol + count($item['data'][0]));
                    $rowNumber = $iterator;

                    for ($col = $startColumn; $col <= $endColumn; $col++) {
                        $currentCell = $col . $rowNumber;
                        if (!$this->cellHasBackgroundColor($worksheet, $currentCell)) {
                            $worksheet->getStyle($currentCell)
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($color);
                        }
                    }
                }

                if (array_key_exists('highlightcolor', $item) && !empty($item['highlightcolor']) && array_key_exists('hasfooter', $item) && !empty($item['hasfooter']) && $item['hasfooter'] === true) {
                    $color = str_replace("#", "", $item['highlightcolor']);
                    $startColumn = $this->findingColumn($startcol + 1);
                    $endColumn = $this->findingColumn($startcol + count($item['data'][0]));
                    $rowNumber = $iterator + $item['rowcount'] - 1;

                    for ($col = $startColumn; $col <= $endColumn; $col++) {
                        $currentCell = $col . $rowNumber;
                        if (!$this->cellHasBackgroundColor($worksheet, $currentCell)) {
                            $worksheet->getStyle($currentCell)
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($color);
                        }
                    }
                }
                if (array_key_exists('alternativecolor', $item) && !empty($item['alternativecolor']) && array_key_exists('maincolor', $item) && !empty($item['maincolor'])) {
                    $rowlimit = count($item['data']);
                    if (array_key_exists('hasfooter', $item) && !empty($item['hasfooter']) && $item['hasfooter'] === true) $rowlimit--;
                    for ($row = (array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) ? 1 : 0; $row < $rowlimit; $row++) {
                        $color = ($row % 2 == 1) ? $item['maincolor'] : $item['alternativecolor'];
                        $color = str_replace("#", "", $color);
                        $startColumn = $this->findingColumn($startcol + 1);
                        $endColumn = $this->findingColumn($startcol + count($item['data'][0]));
                        for ($col = $startColumn; $col <= $endColumn; $col++) {
                            $currentCell = $col . strval($iterator + $row);
                            if (!$this->cellHasBackgroundColor($worksheet, $currentCell)) {
                                $worksheet->getStyle($currentCell)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB($color);
                            }
                        }
                    }
                } elseif (array_key_exists('maincolor', $item) && !empty($item['maincolor'])) {
                    $rowlimit = count($item['data']);
                    if (array_key_exists('hasfooter', $item) && !empty($item['hasfooter']) && $item['hasfooter'] === true) $rowlimit--;
                    $color = str_replace("#", "", $item['maincolor']);
                    $startColumn = $this->findingColumn($startcol + 1);
                    $endColumn = $this->findingColumn($startcol + count($item['data'][0]));
                    $start = 0;
                    if (array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) $start = 1;
                    for ($row = $start; $row < $rowlimit; $row++) {
                        for ($col = $startColumn; $col <= $endColumn; $col++) {
                            $currentCell = $col . strval($iterator + $row);
                            if (!$this->cellHasBackgroundColor($worksheet, $currentCell)) {
                                $worksheet->getStyle($currentCell)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB($color);
                            }
                        }
                    }
                }

                $iterator += $item['rowcount'];
                $iterators[$tmp_index] = $iterator;
            } else if ($item['type'] === 'frame') {
                // foreach($item['data'])
            }
        }

        $writer = new Xlsx($spreadsheet);

        return $writer;
    }

    public function cellHasBackgroundColor($worksheet, $cellCoord)
    {
        $fillType = $worksheet->getStyle($cellCoord)->getFill()->getFillType();
        $color = $worksheet->getStyle($cellCoord)->getFill()->getStartColor()->getRGB();

        // If the fill type is none, then the cell has no background color.
        if ($fillType == Fill::FILL_NONE) {
            return false;
        }

        // Additionally, if the color is 'FFFFFF', it's considered as having no background color.
        if ($color == 'FFFFFF') {
            return false;
        }

        return true; // Otherwise, the cell has a background color.
    }

    public function processCellExp($source, $i, $j, $iterator, $rowcount, $colcount, $hasheader, $hasfooter, $list, $keys)
    {
        if (!empty($source) && strlen($source) > 0) {
            $row = ($iterator + $i);
            $col = $this->findingColumn($j + 1);
            $start = $hasheader ? $iterator + 1 : $iterator;
            if ($hasfooter) $end = $iterator + $rowcount - 2;
            else $end = $iterator + $rowcount - 1;

            while (strpos($source, '${col:') !== false || strpos($source, '${col}') !== false) {
                $pos = strpos($source, '${col:');
                if ($pos === false) {
                    $pos = strpos($source, '${col}');
                }
                $endPos = strpos($source, '}', $pos);
                $specialSubstring = substr($source, $pos, $endPos - $pos + 1);
                $hasexp = false;
                $exp = null;

                if (strpos($specialSubstring, ':')) {
                    $sub = trim($specialSubstring, '${}');
                    $parts = explode(':', $sub, 2);
                    $exp = $parts[1];
                    $hasexp = true;
                }

                $rep = $j + 1;

                if ($hasexp) {
                    if (preg_match('/^([-+])(\d+)$/', $exp, $matches)) {
                        $operator = $matches[1];
                        $operand = intval($matches[2]);
                        if ($operator === '+') {
                            $rep = $rep + $operand;
                        } elseif ($operator === '-') {
                            $rep = $rep - $operand;
                        }
                    } else {
                    }
                }

                $rep = $this->findingColumn($rep);

                $source = str_replace($specialSubstring, $rep, $source);
            }

            while (strpos($source, '${start') !== false) {
                $pos = strpos($source, '${start');
                $endPos = strpos($source, '}', $pos);
                $specialSubstring = substr($source, $pos, $endPos - $pos + 1);
                $hasexp = false;
                $exp = null;


                if (strpos($specialSubstring, ':')) {
                    $sub = trim($specialSubstring, '${}');
                    $parts = explode(':', $sub, 2);
                    $exp = $parts[1];
                    $hasexp = true;
                }

                $rep = $start;

                if ($hasexp) {
                    if (preg_match('/^([-+])(\d+)$/', $exp, $matches)) {
                        $operator = $matches[1];
                        $operand = intval($matches[2]);
                        if ($operator === '+') {
                            $rep = $rep + $operand;
                        } elseif ($operator === '-') {
                            $rep = $rep - $operand;
                        }
                    } else {
                    }
                }

                $source = str_replace($specialSubstring, $rep, $source);
            }

            while (strpos($source, '${row') !== false) {
                $pos = strpos($source, '${row');
                $endPos = strpos($source, '}', $pos);
                $specialSubstring = substr($source, $pos, $endPos - $pos + 1);
                $hasexp = false;
                $exp = null;


                if (strpos($specialSubstring, ':')) {
                    $sub = trim($specialSubstring, '${}');
                    $parts = explode(':', $sub, 2);
                    $exp = $parts[1];
                    $hasexp = true;
                }

                $rep = $row;

                if ($hasexp) {
                    if (preg_match('/^([-+])(\d+)$/', $exp, $matches)) {
                        $operator = $matches[1];
                        $operand = intval($matches[2]);
                        if ($operator === '+') {
                            $rep = $rep + $operand;
                        } elseif ($operator === '-') {
                            $rep = $rep - $operand;
                        }
                    } else {
                    }
                }

                $source = str_replace($specialSubstring, $rep, $source);
            }

            while (strpos($source, '${end') !== false) {
                $pos = strpos($source, '${end');
                $endPos = strpos($source, '}', $pos);
                $specialSubstring = substr($source, $pos, $endPos - $pos + 1);
                $hasexp = false;
                $exp = null;


                if (strpos($specialSubstring, ':')) {
                    $sub = trim($specialSubstring, '${}');
                    $parts = explode(':', $sub, 2);
                    $exp = $parts[1];
                    $hasexp = true;
                }

                $rep = $end;

                if ($hasexp) {
                    if (preg_match('/^([-+])(\d+)$/', $exp, $matches)) {
                        $operator = $matches[1];
                        $operand = intval($matches[2]);
                        if ($operator === '+') {
                            $rep = $rep + $operand;
                        } elseif ($operator === '-') {
                            $rep = $rep - $operand;
                        }
                    } else {
                    }
                }

                $source = str_replace($specialSubstring, $rep, $source);
            }

            for ($q = 0; $q < count($list); $q++) {
                $str = '${' . $list[$q]['tablename'] . '.';
                while (strpos($source, $str) !== false) {
                    $it = null;;
                    $pos = strpos($source, $str);
                    $endPos = strpos($source, '}', $pos);
                    if ($endPos !== false) {
                        $specialSubstring = substr($source, $pos, $endPos - $pos + 1);
                        $extractedData = $this->extractBeforeAndAfterDot($specialSubstring);
                        $hasexp = false;
                        $exp = null;
                        $rep = '';

                        if (strpos($extractedData['afterDot'], ':')) {
                            $parts = explode(':', $extractedData['afterDot'], 2);
                            $extractedData['afterDot'] = $parts[0];
                            $exp = $parts[1];
                            $hasexp = true;
                        }

                        if ($extractedData['afterDot'] === 'start') {
                            // for($t = 0; $t < $q; $t++){
                            //     if($list[$t]['type'] === 'content'){
                            //         // get max and add
                            //         $it += 1;
                            //     } else {
                            //         // go forward with main
                            //         $it += $list[$t]['rowcount'];
                            //     }
                            // }
                            $iterators = [];
                            for ($t = 0; $t < count($keys); $t++) {
                                $iterators[] = 2;
                            }
                            for ($t = 0; $t < $q; $t++) {
                                if ($list[$t]['type'] === 'content') {
                                    // get max and add
                                    $max = $iterators[0];
                                    for ($i = 1; $i < count($keys); $i++) {
                                        if ($max < $iterators[$i]) {
                                            $max = $iterators[$i];
                                        }
                                    }
                                    for ($i = 0; $i < count($keys); $i++) {
                                        $iterators[$i] = $max + 1;
                                    }
                                } else {
                                    for ($i = 0; $i < count($keys); $i++) {
                                        if ($keys[$i] === $list[$t]['excelshift']) {
                                            $iterators[$i] += $list[$t]['rowcount'];
                                            break;
                                        };
                                    }
                                }
                            }

                            for ($i = 0; $i < count($keys); $i++) {
                                if ($list[$q]['excelshift'] === $keys[$i]) {
                                    $it = $iterators[$i];
                                }
                            }

                            if (array_key_exists('hasheader', $list[$q]) && !empty($list[$q]['hasheader']) && $list[$q]['hasheader'] === true) $it++;
                            if ($hasexp) {
                                if (preg_match('/^([-+])(\d+)$/', $exp, $matches)) {
                                    $operator = $matches[1];
                                    $operand = intval($matches[2]);
                                    if ($operator === '+') {
                                        $it = $it + $operand;
                                    } elseif ($operator === '-') {
                                        $it = $it - $operand;
                                    }
                                } else {
                                }
                            }
                            $rep = $it;
                        }

                        if ($extractedData['afterDot'] === 'end') {
                            // for($t = 0; $t <= $q; $t++){
                            //     if($list[$t]['type'] === 'content'){
                            //         $it += 1;
                            //     } else {
                            //         $it += $list[$t]['rowcount'];
                            //     }
                            // }
                            $iterators = [];
                            for ($t = 0; $t < count($keys); $t++) {
                                $iterators[] = 2;
                            }
                            for ($t = 0; $t <= $q; $t++) {
                                if ($list[$t]['type'] === 'content') {
                                    // get max and add
                                    $max = $iterators[0];
                                    for ($i = 0; $i < count($keys); $i++) {
                                        if ($max < $iterators[$i]) {
                                            $max = $iterators[$i];
                                        }
                                    }
                                    for ($i = 0; $i < count($keys); $i++) {
                                        $iterators[$i] = $max + 1;
                                    }
                                } else {
                                    for ($i = 0; $i < count($keys); $i++) {
                                        if ($keys[$i] === $list[$t]['excelshift']) {
                                            $iterators[$i] += $list[$t]['rowcount'];
                                            break;
                                        };
                                    }
                                }
                            }

                            for ($i = 0; $i < count($keys); $i++) {
                                if ($list[$q]['excelshift'] === $keys[$i]) {
                                    $it = $iterators[$i];
                                }
                            }

                            $it--;

                            if (array_key_exists('hasfooter', $list[$q]) && !empty($list[$q]['hasfooter']) && $list[$q]['hasfooter'] === true) $it--;

                            if ($hasexp) {
                                if (preg_match('/^([-+])(\d+)$/', $exp, $matches)) {
                                    $operator = $matches[1];
                                    $operand = intval($matches[2]);
                                    if ($operator === '+') {
                                        $it = $it + $operand;
                                    } elseif ($operator === '-') {
                                        $it = $it - $operand;
                                    }
                                } else {
                                }
                            }
                            $rep = $it;
                        }
                        $source = str_replace($specialSubstring, $rep, $source);
                    }
                }
            }

            return $source;
        } else return '';
    }

    public function extractBeforeAndAfterDot($specialSubstring)
    {
        // Remove the leading '${' and trailing '}' if present
        $specialSubstring = trim($specialSubstring, '${}');

        // Split the string by the dot ('.')
        $parts = explode('.', $specialSubstring, 2);

        $beforeDot = isset($parts[0]) ? $parts[0] : '';
        $afterDot = isset($parts[1]) ? $parts[1] : '';

        return ['beforeDot' => $beforeDot, 'afterDot' => $afterDot];
    }

    public function findingColumn($n)
    {
        $baseColumn = 'A';
        $baseColumnIndex = Coordinate::columnIndexFromString($baseColumn);
        $targetColumnIndex = $baseColumnIndex + $n - 1;

        // Return the column string representation
        return Coordinate::stringFromColumnIndex($targetColumnIndex);
    }
    // Doc Temp дээр хэрэглэгддэг
    public function extractSpecialInfo($input)
    {
        $pattern = '/\$\{([\w\s]+(?:\[\w+:[^\]]+\])?(?:\.\w+)?)(?:,\s*([\w\s]+))?\}/';
        preg_match_all($pattern, $input, $matches, PREG_SET_ORDER);

        $result = array();

        foreach ($matches as $match) {
            $full_special_substring = $match[0];
            $special_key = $match[1];
            $special_function = isset($match[2]) ? $match[2] : null;

            $result[] = array(
                'special_key' => $special_key,
                'special_function' => $special_function,
                'full_special_substring' => $full_special_substring
            );
        }

        return $result;
    }
    // Doc Temp дээр хэрэглэгддэг
    public function objectCondTest($conditions, $object)
    {
        $isItTrue = true;
        foreach ($conditions as $condition) {
            list($field, $value) = explode(':', $condition);
            if (!($object[$field] == $value)) {
                $isItTrue = false;
                break;
            }
        }
        return $isItTrue;
    }
    // Тухайн нүдийг өөр нүдтэй merge хийсэн эсэхийг шалгах
    public function checkMerged($mergedCellRanges, $cellCoordinate)
    {
        foreach ($mergedCellRanges as $mergedRange) {
            if (strpos($mergedRange, $cellCoordinate) !== false) {
                return true;
            }
        }
        return false;
    }
    // Контент дотор байгаа input, user, inst, date гэх мэт мэдээллийг хайж оруулах
    public function checkInside($cellData, $validate_inputs, $inst, $user, $date)
    {
        $cellData = $this->setInput($cellData, $validate_inputs);
        $cellData = $this->setInst($cellData, $inst);
        $cellData = $this->setUser($cellData, $user);
        $cellData = $this->setDate($cellData, $date);
        return $cellData;
    }
    // Контент дотор ${input.tdate}, ${input.acntno} гэх мэт гараас оруулсан мэдээлэл харуулах бол
    public function setInput($cellData, $validate_inputs)
    {
        if (strpos($cellData, '${input.') !== false) {
            $pattern = '/\${input\.(.*?)}/';
            preg_match_all($pattern, $cellData, $matches);
            $substrings = $matches[1];
            foreach ($substrings as $substring) {
                $key = '${input.' . $substring . '}';
                $exp = explode(",", $substring);
                foreach ($validate_inputs as $input_val) {
                    if ($input_val['input'] == $exp[0]) {
                        $sendingdata = $input_val['value'];
                        $cellData = str_replace($key, $this->checkFunction($key, $sendingdata) ?? "", $cellData);
                        break;
                    }
                }
            }
        }
        return $cellData;
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
                    $sendingdata =  $inst->{$exp[0]};
                } else {
                    $sendingdata = $inst[$exp[0]];
                }
                if ($key == '${inst.logo}') {
                    $photo = GPPhoto::where('id', $sendingdata)->first();
                    if ($photo) {
                        $sendingdata = stream_get_contents($photo->photo);
                    }
                    $sendingdata = "<img style='width: 60px;' src='data:image/png;base64,$sendingdata' alt='Me core'>";
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
                    $sendingdata =  $user->{$exp[0]};
                } else {
                    $sendingdata = $user[$exp[0]];
                }
                $cellData = str_replace($key, $this->checkFunction($key, $sendingdata), $cellData);
            }
        }
        return $cellData;
    }
    // Контент дотор ${date} байвал системийн date-ээр орлуулах
    public function setDate($cellData, $date)
    {
        if (strpos($cellData, '${date}') !== false) {
            $cellData = str_replace('${date}', $date, $cellData);
        }
        return $cellData;
    }
}
