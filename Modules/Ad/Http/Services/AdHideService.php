<?php

namespace Modules\Ad\Http\Services;

use Modules\Ad\Entities\AdHide;
use Modules\Gp\Entities\GPInstUser;
use Modules\Gp\Entities\GPInstUserRole;

class AdHideService
{
    public function hideAcnt($acntno)
    {
        $user = auth()->user();
        // $user = GPInstUser::where('id', $uid)->where('statusid', 1)->first();
        $watchs = AdHide::where('modulekey', $acntno)->where('instid', $user->instid)->where('statusid', 1)->get();
        $show = false;
        if (empty($watchs)) {
            $show = true;
        } else {
            foreach ($watchs as $key => $watch) {
                switch ($watch->valuetype) {
                    case 'U':
                        if ($watch->userid == $user->id) {
                            $show = true;
                        } else {
                            $show = false;
                        }
                        break;
                    case 'B':
                        if ($watch->brchno == $user->brchno) {
                            $show = true;
                        } else {
                            $show = false;
                        }
                        break;
                    case 'R':
                        $role = GPInstUserRole::where('instid', $user->instid)
                            ->where('roleid', $watch->roleid)
                            ->where('userid', $user->id)->where('statusid', 1)->first();
                        if ($role) {
                            $show = true;
                        } else {
                            $show = false;
                        }
                        break;
                    case 'BU':
                        if ($watch->userid == $user->id || $watch->brchno == $user->brchno) {
                            $show = true;
                        } else {
                            $show = false;
                        }
                        break;
                    case 'UR':
                        $role = GPInstUserRole::where('instid', $user->instid)
                            ->where('roleid', $watch->roleid)
                            ->where('userid', $user->id)->where('statusid', 1)->first();
                        if ($watch->userid == $user->id || $role) {
                            $show = true;
                        } else {
                            $show = false;
                        }
                        break;
                    case 'BR':
                        $role = GPInstUserRole::where('instid', $user->instid)->where('userid', $user->id)
                            ->where('roleid', $watch->roleid)
                            ->where('statusid', 1)->first();
                        if ($watch->brchno == $user->brchno || $role) {
                            $show = true;
                        } else {
                            $show = false;
                        }
                        break;
                    default:
                        # code...
                        break;
                }

                if ($show) {
                    return $show;
                }
            }
        }
        return $show;
    }
}
