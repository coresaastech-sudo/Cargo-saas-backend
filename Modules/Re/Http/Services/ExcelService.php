<?php

namespace Modules\Re\Http\Services;

use Illuminate\Support\Facades\DB;
use Modules\Gp\Entities\GPInstList;
use Modules\Gp\Http\Services\CoreService;
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

class ExcelService
{
    protected $spreadsheet;
    protected $activesheet;
    protected $inst;
    protected $user;
    protected $operationList = ['${bold}', '${italic}', '${long}', '${percentage}', '${dateformat}', '${vtop}', '${vmiddle}', '${vbottom}', '${hleft}', '${hcenter}', '${hright}', '${border}', '${underline}', '${nowrap}'];
    protected $font;
    // Construct хийж Spreadsheet үүсгэнэ
    public function __construct($info, $instid, $user)
    {
        $this->spreadsheet = new Spreadsheet();
        $this->user = $user;
        $this->inst = GPInstList::where("id", $instid)->first();
        $this->spreadsheet->getProperties()->setCreator($this->inst->name);
        $this->spreadsheet->getProperties()->setLastModifiedBy($this->inst->name);

        if (array_key_exists('title', $info) && !empty($info['title'])) {
            $this->spreadsheet->getProperties()->setTitle($info['title']);
        }
        if (array_key_exists('subject', $info) && !empty($info['subject'])) {
            $this->spreadsheet->getProperties()->setSubject($info['subject']);
        }
        if (array_key_exists('description', $info) && !empty($info['description'])) {
            $this->spreadsheet->getProperties()->setDescription($info['description']);
        }
    }
    // Spreadsheet файл дотроо шинэ sheet үүсгэж өгөгдлөө оруулах
    // $info - тухайн sheet талаарх ерөнхий мэдээлэл
    // $validate_inputs - гараас оруулсан утгууд
    // $data - sheet үүсгэх хүснэгтэн дата
    public function createSheet($info, $data, $validate_inputs)
    {
        // Sheet үүсгэх
        if (empty($this->activesheet)) {
            $this->activesheet = $this->spreadsheet->getActiveSheet();
        } else {
            $this->activesheet = $this->spreadsheet->createSheet();
        }
        if (array_key_exists('title', $info) && !empty($info['title'])) {
            $this->activesheet->setTitle($info['title']);
        }
        // Фонт сонгох
        $this->font = "Times New Roman";
        if (array_key_exists('font', $info) && !empty($info['font'])) {
            switch (intval($info['font'])) {
                case 2:
                    $this->font = "Calibri";
                    break;
                case 3:
                    $this->font = "Arial";
                    break;
                default:
                    $this->font = "Times New Roman";
                    break;
            }
        }
        // Хүснэгтүүдийн хамгийн олон багантай хүснэгтийн багнын тоог олж илрүүлэх
        $maxwidth = 0;
        $keys = array(1);
        $maxes = array(1);

        foreach ($data as $item) {
            $found = false;
            for ($i = 0; $i < count($keys); $i++) {
                // Log::channel("report_log")->debug('createSheet', $item);
                if ($keys[$i] == intval($item['excelshift'])) {
                    if (
                        is_countable(@$item['data'][0])
                        && $maxes[$i] < count(@$item['data'][0])
                    ) {
                        $maxes[$i] = count($item['data'][0]);
                    }
                    $found = true;
                }
            }
            if ($found === false) {
                $keys[] = intval($item['excelshift']);
                $maxes[] = count($item['data'][0]);
            }
        }
        // Paralel олон хүснэгт зэрэгцүүлэн байрлуулах боломжтой ба тэдгээрийн iterator утгууд
        $iterators = [];

        for ($i = 0; $i < count($keys); $i++) {
            $maxwidth += $maxes[$i];
            $iterators[] = 2;
        }
        foreach ($data as $item) {
            if ($item['type'] === 'content') { // Төрөл нь Content үед
                $max = $iterators[0];
                for ($i = 0; $i < count($keys); $i++) {
                    if ($max < $iterators[$i]) {
                        $max = $iterators[$i];
                    }
                }
                $iterator = $max;
                $this->activesheet->mergeCells("A" . strval($iterator) . ':' . $this->findingColumn($maxwidth) . strval($iterator)); // Хүснэгт нэгтгэх
                $item['data'] = str_replace("\n", "\r\n", $item['data']);
                if (strpos($item['data'], '${space}') !== false) {
                    $item['data'] = str_replace('${space}', ' ', $item['data']);
                }
                $cellData = $item['data'];
                $item['data'] = str_replace($this->operationList, '', $item['data']);

                $this->activesheet->setCellValue("A" . strval($iterator), $item['data']); // Хүснэгтэд текст оруулах

                if ((array_key_exists('bold', $item) && !empty($item['bold']))) {
                    $cellData .= '${bold}';
                }

                if (strpos($cellData, '${bold}') !== false) {
                    $this->activesheet->getStyle("A" . strval($iterator))->getFont()->setBold(true);
                }
                if (strpos($cellData, '${italic}') !== false) {
                    $this->activesheet->getStyle("A" . strval($iterator))->getFont()->setItalic(true);
                }

                if (array_key_exists('maincolor', $item) && !empty($item['maincolor'])) { // Background өнгө оруулах /Арийн өнгө оруулах/
                    $color = str_replace("#", "", $item['maincolor']);
                    $this->activesheet->getStyle("A" . strval($iterator))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                }
                if (array_key_exists('textcolor', $item) && !empty($item['textcolor'])) { // Текст өнгө оруулах
                    $color = str_replace("#", "", $item['textcolor']);
                    $this->activesheet->getStyle("A" . strval($iterator))->getFont()->getColor()->setRGB($color);
                }

                if (array_key_exists('height', $item) && !empty($item['height'])) { // Текстийн өндөр оруулах
                    $height = str_replace("px", "", $item['height']);
                    $height = (intval($height) * 27) / 36;
                    $this->activesheet->getRowDimension(strval($iterator))->setRowHeight($height);
                } else {
                    $this->activesheet->getRowDimension(strval($iterator))->setRowHeight(-1);
                }
                if (array_key_exists('datafontsize', $item) && !empty($item['datafontsize'])) { // Текстийн фонтын өндөр оруулах
                    $fontsize = str_replace("px", "", $item['datafontsize']);
                    $this->activesheet->getStyle("A" . strval($iterator))->getFont()->setSize($fontsize);
                }
                // Хэвтээ Alignmet оруулах
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
                    $this->activesheet->getStyle("A" . strval($iterator))->getAlignment()->setHorizontal($alignment);
                }
                // Босоо Alignmet оруулах
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
                    $this->activesheet->getStyle("A" . strval($iterator))->getAlignment()->setVertical($alignment);
                }

                if (!empty($font)) {
                    $this->activesheet->getStyle("A" . strval($iterator))->getFont()->setName($font);
                }

                $iterator++;

                for ($i = 0; $i < count($keys); $i++) {
                    $iterators[$i] = $iterator;
                }
            } else if ($item['type'] === 'table') { // Төрөл нь хүснэгт үед
                $startcol = 0;
                $iterator = $iterators[0];
                $tmp_index = 0;
                // Excel shift нь олон хүснэгт зэрэгцүүлэн оруулахад хэрэг болдог
                for ($i = 0; $i < count($keys); $i++) {
                    if ($keys[$i] !== $item['excelshift']) {
                        $startcol += $maxes[$i];
                    } else {
                        $iterator = $iterators[$i];
                        $tmp_index = $i;
                        break;
                    };
                }
                // Тухайн хүснэгт толгой болон хөл хэсэг тодорхойлогдсон эсэхийг шалгах
                $hasheader = array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true;
                $hasfooter = array_key_exists('hasfooter', $item) && !empty($item['hasfooter']) && $item['hasfooter'] === true;

                for ($i = 0; $i < count($item['data']); $i++) { // Хүснэгтийн нүднүүдийг толгойн хамтаар оруулах
                    for ($j = 0; $j < count($item['data'][$i]); $j++) {
                        $item['data'][$i][$j] = $this->processCellExp($this->checkInside($item['data'][$i][$j], $validate_inputs, $this->inst, $this->user, (new Carbon(CoreService::getTxnDate($this->user->instid)))->format('Y-m-d')), $i, $startcol + $j, $iterator, $item['rowcount'], $item['colcount'], $hasheader, $hasfooter, $data, $keys);
                    }
                }

                // Хүснэгтий хөл хэсгийг ялган авах.
                if (array_key_exists('hasfooter', $item) && !empty($item['hasfooter'])) {
                    $footers = [];
                    $ind = 0;

                    foreach ($item['footers'] as &$footeritem) {
                        $footers[] = $this->processCellExp($this->checkInside($footeritem, $validate_inputs, $this->inst, $this->user, (new Carbon(CoreService::getTxnDate($this->user->instid)))->format('Y-m-d')), count($item['data']), $startcol + $ind, $iterator, $item['rowcount'], $item['colcount'], $hasheader, $hasfooter, $data, $keys);
                        $ind++;
                    }
                    $item['data'][] = $footers;
                }

                $ij = 0;

                // Аль нэг нүд нь өмнөх оруулсан датаны улмаас өөр нүднүүдтэй merge хийгдсэн байх магдлалтай учир нэгдмэл нүднүүдийн мэдээллийг авах
                $mergedCellRanges = $this->activesheet->getMergeCells();

                if (isset($item['datafontsize'])) $fontsizetmp = str_replace("px", "", $item['datafontsize']);
                $numCols = @$item['data'][0] ? count($item['data'][0]) : 0;
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
                        $this->activesheet->getStyle($range)->getAlignment()->setHorizontal($alignment);
                    }
                }

                foreach ($item['data'] as $rowData) { // Датаг нүд болгоноор оруулж тэдгээрийн Формад Дизайны тохиргоор оруулах
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
                            foreach ($matches[1] as $match) { // mcol mrow ctcolor color зэргийг ялгах
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
                            if ($col !== null && $row !== null) { // mcol mrow merge хийх
                                // Calculate the merged cell coordinates based on current cell
                                $startCellCoordinate = $this->findingColumn($startcol + 1 + $columnIndex) . ($iterator + $ij);
                                $endCellCoordinate = $this->findingColumn($startcol + 1 + $columnIndex + $col) . ($iterator + $ij + $row);

                                $this->activesheet->mergeCells($startCellCoordinate . ':' . $endCellCoordinate);
                            }

                            if (preg_match('/\${tcolor:[A-Fa-f0-9]{6}}/', $cellData)) {
                                $richText = new RichText();

                                $tmpcelldata = str_replace($this->operationList, '', $cellData);

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

                                $this->activesheet->setCellValue($cellCoordinate, $richText);
                            } else {
                                $tmp = str_replace($this->operationList, '', $cellData);
                                if (strpos($tmp, '${space}') !== false) {
                                    $tmp = str_replace('${space}', ' ', $cellData);
                                }
                                if (strlen($tmp) === 0) {
                                    $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                                }
                                $this->activesheet->setCellValue($cellCoordinate, str_replace($this->operationList, '', $tmp));
                            }
                            // bold болгох
                            if (strpos($cellData, '${bold}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getFont()->setBold(true);
                            }
                            // italic болгох
                            if (strpos($cellData, '${italic}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getFont()->setItalic(true);
                            }
                            // underline болгох
                            if (strpos($cellData, '${underline}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getFont()->setUnderline(true);
                            }
                            // vertical top болгох
                            if (strpos($cellData, '${vtop}') !== false) {
                                $this->activesheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                            }
                            // vertical middle болгох
                            if (strpos($cellData, '${vmiddle}') !== false) {
                                $this->activesheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                            }
                            // vertical bottom болгох
                            if (strpos($cellData, '${vbottom}') !== false) {
                                $this->activesheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setVertical(Alignment::VERTICAL_BOTTOM);
                            }
                            // horizontal left болгох
                            if (strpos($cellData, '${hleft}') !== false) {
                                $this->activesheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                            }
                            // horizontal center болгох
                            if (strpos($cellData, '${hcenter}') !== false) {
                                $this->activesheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            }
                            // horizontal right болгох
                            if (strpos($cellData, '${hright}') !== false) {
                                $this->activesheet->getStyle((!empty($startCellCoordinate) && !empty($endCellCoordinate)) ? $startCellCoordinate . ':' . $endCellCoordinate : $cellCoordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                            }
                            // тухайн нүдийг border хийх
                            if (strpos($cellData, '${border}') !== false) {
                                $borders = $this->activesheet->getStyle($cellCoordinate)->getBorders();
                                if (!empty($startCellCoordinate) && !empty($endCellCoordinate)) $borders = $this->activesheet->getStyle($startCellCoordinate . ':' . $endCellCoordinate)->getBorders();

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
                                $this->activesheet->getStyle($cellCoordinate)->getFont()->setName($font);
                            }

                            if ($color) {
                                $this->activesheet->getStyle($cellCoordinate)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                            }

                            if ($height) {
                                $row = Coordinate::coordinateFromString($cellCoordinate)->getRow();
                                $rowDimension = $this->activesheet->getRowDimension($row);
                                $rowDimension->setRowHeight(intval($height));
                            }

                            if ($ctcolor) {
                                $this->activesheet->getStyle($cellCoordinate)->getFont()->getColor()->setRGB($ctcolor);
                            }

                            if (strpos($cellData, '${roundtwo}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                            } else if (strpos($cellData, '${long}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                            } else if (strpos($cellData, '${dateformat}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);
                            } else if (strpos($cellData, '${percentage}') !== false) {
                                $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                            } else if (is_numeric($cellData)) {
                                $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
                                if (is_float($cellData)) {
                                    $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0.00');
                                } elseif (is_int($cellData)) {
                                    $this->activesheet->getStyle($cellCoordinate)->getNumberFormat()->setFormatCode('#,##0');
                                }
                            }
                            // default border type нэмэх
                            if ($item['bordertypes'] === 1) {
                                $borders = $this->activesheet->getStyle($cellCoordinate)->getBorders();
                                if (!empty($startCellCoordinate) && !empty($endCellCoordinate)) $borders = $this->activesheet->getStyle($startCellCoordinate . ':' . $endCellCoordinate)->getBorders();

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
                                $this->activesheet->getStyle($cellCoordinate)->getAlignment()->setWrapText(true);
                            }
                        }
                    }

                    $ij++; // Move to the next row
                }
                // table width тохируулсан бол оруулах
                if (array_key_exists('width', $item) && !empty($item['width'])) {
                    for ($row = 0; $row < count($item['width']); $row++) {
                        if ($item['width'][$row] === 'auto') continue;
                        $width = str_replace("px", "", $item['width'][$row]);
                        $width = (intval($width) * 10) / 75;
                        $mainrow = $this->findingColumn($startcol + $row + 1);
                        $this->activesheet->getColumnDimension($mainrow)->setWidth($width);
                    }
                }
                // headerfontsize тохируулсан бол оруулах
                if (array_key_exists('headerfontsize', $item) && !empty($item['headerfontsize']) && array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) {
                    $fontsize = str_replace("px", "", $item['headerfontsize']);
                    for ($col = 0; $col < $numCols; $col++) {
                        $columnLetter = Coordinate::stringFromColumnIndex($startcol + $col + 1);
                        $this->activesheet->getStyle($columnLetter . $iterator)->getFont()->setSize($fontsize);
                    }
                }
                // datafontsize тохируулсан бол оруулах
                if (array_key_exists('datafontsize', $item) && !empty($item['datafontsize'])) {
                    $fontsize = str_replace("px", "", $item['datafontsize']);
                    for ($row = (array_key_exists('headerfontsize', $item) && !empty($item['headerfontsize']) && array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) ? 1 : 0; $row < $numRows; $row++) { // Start from 1 because 0 is for headers
                        for ($col = 0; $col < $numCols; $col++) {
                            $columnLetter = Coordinate::stringFromColumnIndex($startcol + $col + 1);
                            $this->activesheet->getStyle($columnLetter . ($iterator + $row))->getFont()->setSize($fontsize);
                        }
                    }
                }
                // highlightcolor тохируулсан бол толгой хэсэгт оруулах
                if (array_key_exists('highlightcolor', $item) && !empty($item['highlightcolor']) && array_key_exists('hasheader', $item) && !empty($item['hasheader']) && $item['hasheader'] === true) {
                    $color = str_replace("#", "", $item['highlightcolor']);
                    $startColumn = $this->findingColumn($startcol + 1);
                    $endColumn = $this->findingColumn($startcol + count($item['data'][0]));
                    $rowNumber = $iterator;

                    for ($col = $startColumn; $col <= $endColumn; $col++) {
                        $currentCell = $col . $rowNumber;
                        if (!$this->cellHasBackgroundColor($this->activesheet, $currentCell)) {
                            $this->activesheet->getStyle($currentCell)
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($color);
                        }
                    }
                }
                // highlightcolor тохируулсан бол хөл хэсэгт оруулах
                if (array_key_exists('highlightcolor', $item) && !empty($item['highlightcolor']) && array_key_exists('hasfooter', $item) && !empty($item['hasfooter']) && $item['hasfooter'] === true) {
                    $color = str_replace("#", "", $item['highlightcolor']);
                    $startColumn = $this->findingColumn($startcol + 1);
                    $endColumn = $this->findingColumn($startcol + count($item['data'][0]));
                    $rowNumber = $iterator + $item['rowcount'] - 1;

                    for ($col = $startColumn; $col <= $endColumn; $col++) {
                        $currentCell = $col . $rowNumber;
                        if (!$this->cellHasBackgroundColor($this->activesheet, $currentCell)) {
                            $this->activesheet->getStyle($currentCell)
                                ->getFill()->setFillType(Fill::FILL_SOLID)
                                ->getStartColor()->setARGB($color);
                        }
                    }
                }
                // Сөөлжлөх өнгө тохируулсан бол үндсэн өнгөтэй сөөлжлүүлэн өнгө оруулах
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
                            if (!$this->cellHasBackgroundColor($this->activesheet, $currentCell)) {
                                $this->activesheet->getStyle($currentCell)
                                    ->getFill()->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setARGB($color);
                            }
                        }
                    }
                } elseif (array_key_exists('maincolor', $item) && !empty($item['maincolor'])) { // үндсэн өнгө оруулах
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
                            if (!$this->cellHasBackgroundColor($this->activesheet, $currentCell)) {
                                $this->activesheet->getStyle($currentCell)
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
    }
    // Тухайн нүдийг background өнгө байгаа эсэхийг шалгах
    // Хэрэв тухайн нүдийг цагаанаар будсан тохиолдолд background өнгө байхгүй гэж үзнэ.
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
    // Excel file export хийх
    public function export($filename = null)
    {
        if ($filename === null) {
            $filename = generateRandomString(10) . '.xlsx';
        }
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($filename);
        $base64Content = base64_encode(file_get_contents($filename));
        $filePath = public_path($filename);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        return $base64Content;
    }
    // Тоон утгаар 4 дахь, 3 дахь, N-th баганыг олох
    public function findingColumn($n)
    {
        $baseColumn = 'A';
        $baseColumnIndex = Coordinate::columnIndexFromString($baseColumn);
        $targetColumnIndex = $baseColumnIndex + $n - 1;

        // Return the column string representation
        return Coordinate::stringFromColumnIndex($targetColumnIndex);
    }
    // Expression буюу тухайн нүдэнд томъёо оруулах арга
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
    // Дотор нь input, inst, user, date гэсэн утгууд байгаабол орлуулан илгээх
    public function checkInside($cellData, $validate_inputs, $inst, $user, $date)
    {
        $cellData = $this->setInput($cellData, $validate_inputs);
        $cellData = $this->setInst($cellData, $inst);
        $cellData = $this->setUser($cellData, $user);
        $cellData = $this->setDate($cellData, $date);
        return $cellData;
    }

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
                        $cellData = str_replace($key, $this->checkFunction($key, $sendingdata), $cellData);
                        break;
                    }
                }
            }
        }
        return $cellData;
    }

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
                $cellData = str_replace($key, $this->checkFunction($key, $sendingdata), $cellData);
            }
        }
        return $cellData;
    }

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

    public function setDate($cellData, $date)
    {
        if (strpos($cellData, '${date}') !== false) {
            $cellData = str_replace('${date}', $date, $cellData);
        }
        return $cellData;
    }

    public function checkMerged($mergedCellRanges, $cellCoordinate)
    {
        foreach ($mergedCellRanges as $mergedRange) {
            if (strpos($mergedRange, $cellCoordinate) !== false) {
                return true;
            }
        }
        return false;
    }
    // Тухайн утгад харгалзах функц тодорхойлсон бол.
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
            } else {
                if ($source[1] === 'qr') {
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
