<?php

namespace Modules\Re\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Re\Entities\ReInstReportTemp;
use Modules\Re\Entities\ReInstReportTempContent;
use Modules\Re\Entities\ReInstReportTempParam;
use Modules\Re\Entities\Views\VwReInstReportTempContent;
use Modules\Re\Http\Requests\ReInstReportTempContentRequest;

class ReInstReportTempContentController extends Controller
{
    /**
     * re010004
     * Display a listing of the resource.
     * @return Response
     */
    public function index(Request $request)
    {
        return $this->getGridData($request, ReInstReportTempContent::where('statusid', 1)
            ->where("instid", 1), [['field' => 'listorder', 'dir' => 'ASC']]);
    }

    /**
     * re010304
     * Update Report Template Conetnt
     * @param ReInstReportTempContentRequest $request
     * @return Response
     */
    public function update(ReInstReportTempContentRequest $request)
    {
        $validate = $request->validated();

        $user = auth()->user();

        if (empty($validate['id'])) {
            $this->error('RC000011');
        }

        $report = ReInstReportTemp::where("instid", 1)->where('statusid', 1)->where("id", $validate['templateid'])->first();

        if ($report) {
            if (array_key_exists('height', $validate) && !empty($validate['height']) && strpos($validate['height'], 'px') === false && strpos($validate['height'], '%') === false && strpos($validate['height'], 'auto') === false) $validate['height'] .= 'px';
            if (array_key_exists('width', $validate) && !empty($validate['width']) && strpos($validate['width'], 'px') === false && strpos($validate['width'], '%') === false && strpos($validate['width'], 'auto') === false) $validate['width'] .= 'px';

            if ($validate['position'] === 1 || $validate['position'] === 3 && $report->exporttype === 1) {
                if (!array_key_exists('height', $validate) || empty($validate['height'])) {
                    $validate['height'] = '100%';
                }
                if (!array_key_exists('width', $validate) || empty($validate['width'])) {
                    $validate['width'] = '100%';
                }
            }

            $validate['updated_by'] = $user->id;

            if (array_key_exists('children', $validate)) {
                $children = $validate['children'];
                unset($validate['children']);
            }

            if (array_key_exists('framerow', $validate) && array_key_exists('framecol', $validate) && !empty($validate['framerow']) && !empty($validate['framecol']) && $validate['type'] === 4) {
                $validate['framepos'] = $validate['framerow'] . "x" . $validate['framecol'];
            }

            $content = ReInstReportTempContent::where('instid', 1)->where("statusid", 1)->where('id', $validate['id'])->first();

            if (!empty($content)) {
                $content->update($validate);
                if (!empty($children)) {
                    if (!array_key_exists('source', $validate)) {
                        $search = substr($validate['source'], 2, -1);
                    } else {
                        $search = substr($content->source, 2, -1);
                    }
                    $parent = ReInstReportTempParam::where("instid", 1)->where("templateid", $content->templateid)->where("paramname", $search)->where('statusid', 1)->first();
                    if ($parent) {
                        ReInstReportTempContent::where("instid", 1)->where('statusid', 1)->where("parentid", $validate['id'])->update(["statusid" => -1, "updated_by" => $user->id]);

                        $index = 0;
                        foreach ($children as $child) {
                            $param = ReInstReportTempParam::where("instid", 1)->where("parentid", $parent->id)->where("id", $child['id'])->where("statusid", 1)->first();
                            $cellexp = null;
                            $width = null;
                            if (array_key_exists('cellexpression', $child) && !empty($child['cellexpression'])) $cellexp = $child['cellexpression'];
                            if (array_key_exists('width', $child) && !empty($child['width'])) {
                                $width = $child['width'];
                                if (strpos($width, 'px') === false && strpos($width, '%') === false && strpos($width, 'auto') === false) $width .= 'px';
                            }
                            if ($param) {
                                $search = ReInstReportTempContent::where("relatedparamid", $param->id)->where("templateid", $content->templateid)->where("position", 1)->where("parentid", $content->id)->where("type", 4)->first();
                                if ($search) {
                                    ReInstReportTempContent::where("relatedparamid", $param->id)->where("templateid", $content->templateid)->where("position", 1)->where("parentid", $content->id)->where("type", 4)
                                        ->update([
                                            "contentname" => $child['header'],
                                            "source" => $child['header'],
                                            "listorder" => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                            "statusid" => 1,
                                            "updated_by" => $user->id,
                                            "align" => $child['align'],
                                            "width" => $width
                                        ]);
                                } else {
                                    ReInstReportTempContent::create([
                                        'contentname' => $child['header'],
                                        'templateid' => $content->templateid,
                                        'type' => 4,
                                        'source' => $child['header'],
                                        'richtext' => false,
                                        'orientation' => 1,
                                        'listorder' => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                        'position' => 1,
                                        'parentid' => $content->id,
                                        'statusid' => 1,
                                        'instid' => 1,
                                        'created_by' => $user->id,
                                        'relatedparamid' => $child['id'],
                                        'align' => $child['align'],
                                        "width" => $width
                                    ]);
                                }
                                $search = ReInstReportTempContent::where("relatedparamid", $param->id)->where("templateid", $content->templateid)->where("position", 2)->where("parentid", $content->id)->where("type", 4)->first();
                                if ($search) {
                                    ReInstReportTempContent::where("relatedparamid", $param->id)->where("templateid", $content->templateid)->where("position", 2)->where("parentid", $content->id)->where("type", 4)
                                        ->update([
                                            "contentname" => $param->paramname,
                                            "source" => $child['source'],
                                            "listorder" => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                            "statusid" => 1,
                                            "updated_by" => $user->id,
                                            'align' => $child['align'],
                                            "width" => $width
                                        ]);
                                } else {
                                    ReInstReportTempContent::create([
                                        'contentname' => $param->paramname,
                                        'templateid' => $content->templateid,
                                        'type' => 4,
                                        'source' => $child['source'],
                                        'richtext' => false,
                                        'orientation' => 1,
                                        'listorder' => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                        'position' => 2,
                                        'parentid' => $content->id,
                                        'statusid' => 1,
                                        'instid' => 1,
                                        'created_by' => $user->id,
                                        'relatedparamid' => $child['id'],
                                        'align' => $child['align'],
                                        "width" => $width
                                    ]);
                                }
                                $search = ReInstReportTempContent::where("relatedparamid", $param->id)->where("templateid", $content->templateid)->where("position", 3)->where("parentid", $content->id)->where("type", 4)->first();
                                if ($search && !empty($cellexp)) {
                                    ReInstReportTempContent::where("relatedparamid", $param->id)->where("templateid", $content->templateid)->where("position", 3)->where("parentid", $content->id)->where("type", 4)
                                        ->update([
                                            "contentname" => $param->paramname,
                                            "source" => $cellexp,
                                            "listorder" => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                            "statusid" => 1,
                                            "updated_by" => $user->id,
                                            'align' => $child['align'],
                                            "width" => $width
                                        ]);
                                } else if (!empty($cellexp)) {
                                    ReInstReportTempContent::create([
                                        'contentname' => $param->paramname,
                                        'templateid' => $content->templateid,
                                        'type' => 4,
                                        'source' => $cellexp,
                                        'richtext' => false,
                                        'orientation' => 1,
                                        'listorder' => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                        'position' => 3,
                                        'parentid' => $content->id,
                                        'statusid' => 1,
                                        'instid' => 1,
                                        'created_by' => $user->id,
                                        'relatedparamid' => $child['id'],
                                        'align' => $child['align'],
                                        "width" => $width
                                    ]);
                                }
                                $index++;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     *
     * Store Report Template Content
     * @param ReInstReportTempContentRequest $request
     * @AC re010204
     * @return Response
     */
    public function store(ReInstReportTempContentRequest $request)
    {
        $validate = $request->validated();
        $user = auth()->user();
        $validate['statusid'] = 1;
        $validate['instid'] = 1;
        $validate['created_by'] = $user->id;
        $validate['updated_by'] = $user->id;

        $report = ReInstReportTemp::where("id", $validate['templateid'])->where("instid", 1)->where("statusid", 1)->first();

        if (array_key_exists('frameinfo', $validate) && !empty($validate['frameinfo'])) {
            $parentcontent = ReInstReportTempContent::where("instid", 1)->where("statusid", 1)->where("id", $validate['frameinfo'])->first();
            if (!empty($parentcontent)) {
                $validate['parentid'] = $parentcontent->id;
            }
        }

        if ($report) {
            if (array_key_exists('height', $validate) && !empty($validate['height']) && strpos($validate['height'], 'px') === false && strpos($validate['height'], '%') === false && strpos($validate['height'], 'auto') === false) $validate['height'] .= 'px';
            if (array_key_exists('width', $validate) && !empty($validate['width']) && strpos($validate['width'], 'px') === false && strpos($validate['width'], '%') === false && strpos($validate['width'], 'auto') === false) $validate['width'] .= 'px';

            if ($validate['position'] === 1 || $validate['position'] === 3 && $report->exporttype === 1) {
                if (!array_key_exists('height', $validate) || empty($validate['height'])) {
                    $validate['height'] = '100%';
                }
                if (!array_key_exists('width', $validate) || empty($validate['width'])) {
                    $validate['width'] = '100%';
                }
            }

            if (array_key_exists('framerow', $validate) && array_key_exists('framecol', $validate) && !empty($validate['framerow']) && !empty($validate['framecol']) && $validate['type'] === 4) {
                $validate['framepos'] = $validate['framerow'] . "x" . $validate['framecol'];
            }

            $source = $validate['source'];
            if (gettype($source) == 'array' || gettype($source) == 'object') {
                $source = json_encode($validate['source']);
            }
            $content = ReInstReportTempContent::create(array_merge($validate, ['source' => $source]));

            if ($validate['type'] === 2 || $validate['type'] === 4) {
                $validate['source'] = trim($validate['source']);
                if (array_key_exists('children', $validate)) {
                    $search = substr($validate['source'], 2, -1);
                    $parent = ReInstReportTempParam::where("instid", 1)->where("templateid", $content->templateid)->where("paramname", $search)->where('statusid', 1)->first();

                    ReInstReportTempContent::where("id", $content->id)->update(["relatedparamid" => $parent->id]);

                    if ($parent) {
                        $index = 0;
                        foreach ($validate['children'] as &$child) {
                            $param = ReInstReportTempParam::where("instid", 1)->where("parentid", $parent->id)->where("id", $child['id'])->where("statusid", 1)->first();
                            $cellexp = null;
                            $width = null;
                            if (array_key_exists('width', $child) && !empty($child['width'])) {
                                $width = $child['width'];
                                if (strpos($width, 'px') === false && strpos($width, '%') === false && strpos($width, 'auto') === false) $width .= 'px';
                            }
                            if (array_key_exists('cellexpression', $child) && !empty($child['cellexpression'])) $cellexp = $child['cellexpression'];
                            if ($param) {
                                ReInstReportTempContent::create([
                                    'contentname' => $child['header'],
                                    'templateid' => $content->templateid,
                                    'type' => 4,
                                    'source' => $child['header'],
                                    'richtext' => false,
                                    'orientation' => 1,
                                    'listorder' => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                    'position' => 1,
                                    'parentid' => $content->id,
                                    'statusid' => 1,
                                    'instid' => 1,
                                    'width' => $width,
                                    'created_by' => $user->id,
                                    'relatedparamid' => $child['id'],
                                    'align' => $child['align']
                                ]);
                                ReInstReportTempContent::create([
                                    'contentname' => $param->paramname,
                                    'templateid' => $content->templateid,
                                    'type' => 4,
                                    'source' => $child['source'],
                                    'richtext' => false,
                                    'orientation' => 1,
                                    'listorder' => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                    'position' => 2,
                                    'parentid' => $content->id,
                                    'statusid' => 1,
                                    'instid' => 1,
                                    'width' => $width,
                                    'created_by' => $user->id,
                                    'relatedparamid' => $child['id'],
                                    'align' => $child['align']
                                ]);
                                if (!empty($cellexp)) {
                                    ReInstReportTempContent::create([
                                        'contentname' => $param->paramname,
                                        'templateid' => $content->templateid,
                                        'type' => 4,
                                        'source' => $cellexp,
                                        'richtext' => false,
                                        'orientation' => 1,
                                        'listorder' => $child['listorder'] ? $child['listorder'] : ($index + 1),
                                        'position' => 3,
                                        'parentid' => $content->id,
                                        'statusid' => 1,
                                        'instid' => 1,
                                        'width' => $width,
                                        'created_by' => $user->id,
                                        'relatedparamid' => $child['id'],
                                        'align' => $child['align']
                                    ]);
                                }
                                $index++;
                            }
                        }
                    }
                } else {
                    // throw error
                }
            } else {
                return $content;
            }
        }
    }

    /**
     * re010404
     * Delete Report Template Content
     * @return Response
     */
    public function destroy(Request $request)
    {
        $validate = $this->validate($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $tempContent = ReInstReportTempContent::where("instid", 1)
            ->where("id", $validate['id'])->where('statusid', 1)->first();

        $tempContent->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);

        ReInstReportTempContent::where('instid', 1)->where("parentid", $validate['id'])->update([
            'statusid' => -1,
            'updated_by' => auth()->user()->id
        ]);
    }

    /**
     * re010104
     * Show Report Template Content
     * @return Response
     */
    public function show(Request $request)
    {
        $validate = $this->validateMe($request, [
            'id' => 'required'
        ], [
            'id.required' => "RC000011"
        ]);
        $tempContent = VwReInstReportTempContent::with(["children"])->where("instid", 1)
            ->where("id", $validate["id"])->where("statusid", 1)->first();

        if ($tempContent) {
            return $tempContent;
        } else {
            $this->error("RC000010", $validate);
        }
    }
}
