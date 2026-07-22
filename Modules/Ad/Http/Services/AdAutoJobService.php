<?php

namespace Modules\Ad\Http\Services;

use Carbon\Carbon;
use Modules\Ad\Entities\AdAutoJob;
use Modules\Ad\Entities\Views\VwAdNotificationUsers;

class AdAutoJobService
{

    public function checkAutoJobActionCode($ACTION_CODE, $user, $data)
    {
        $now = getNow();
        $autoJob = AdAutoJob::where('job_type', 'AFTER_PC')
            ->where('ACTION_CODE', $ACTION_CODE)
            ->where('instid', $user->instid)
            ->where('statusid', '<>', -1)
            ->first();

        if (!empty($autoJob)) {
            if ($autoJob->hastimelimit == 1) {
                $startdate = Carbon::parse($autoJob->startdate);
                $enddate = Carbon::parse($autoJob->enddate);
                if ($startdate->lessThan($now) && $enddate->greaterThan($now)) {
                    $this->doJob($autoJob, $user, $data);
                }
            } else {
                $this->doJob($autoJob, $user, $data);
            }
        }
    }

    public function doJob($autoJob, $user, $data)
    {
        $custuser = VwAdNotificationUsers::where('type', $user->custtypecode)->where('instid', $user->instid)
        ->where('custid', $user->id)->where('statusid', '<>', -1)->first();

        $notificationService = new AdNotificationService($user->instid);
        $notificationService->sendAutoJob($autoJob->id, $custuser ?? $user, $data);
    }

    public function checkAutoJobSchedule($autoJob)
    {
        if ($autoJob->lastexecdate) {
            $now = Carbon::now();
            $lastExec = Carbon::parse($autoJob->lastexecdate);

            switch ($autoJob->execfreq) {
                case 'D':
                    if ($lastExec->gte($now)) {
                        return null;
                    }
                    break;
                case 'S':
                    if ($lastExec->isSameDay($now)) {
                        return null;
                    }
                    break;
                case 'W':
                    if ($lastExec->format('o-W') === $now->format('o-W')) {
                        return null;
                    }
                    break;
                case 'M':
                    if ($lastExec->format('Y-m') === $now->format('Y-m')) {
                        return null;
                    }
                    break;
                case 'H':
                    $lastExecHalf = $lastExec->day <= 15 ? 1 : 2;
                    $nowHalf = $now->day <= 15 ? 1 : 2;
                    if ($lastExec->format('Y-m') === $now->format('Y-m') && $lastExecHalf === $nowHalf) {
                        return null;
                    }
                    break;
                case 'E':
                    if ($lastExec->gte($now)) {
                        return null;
                    }
                    break;
                case 'Q':
                    if ($lastExec->gte($now)) {
                        return null;
                    }
            }
        }

        if ($this->checkExecuteable($autoJob)) {
            return $autoJob;
        }
    }

    public function checkExecuteable($autoJob)
    {
        $now = Carbon::now();
        if ($autoJob->hastimelimit == 1) {
            $startdate = Carbon::parse($autoJob->startdate);
            $enddate = Carbon::parse($autoJob->enddate);
            if ($startdate->greaterThan($now) || $enddate->lessThan($now)) {
                return false;
            }
        } else {
            return true;
        }

        switch ($autoJob->execfreq) {
            case 'D':
                // if ($dayDifference == 1) {
                return true;
                // }
            case 'W':
                $dayOfWeek = $now->startOfWeek()->addDays($autoJob->execday - 1);

                if ($dayOfWeek->isSameDay($now)) {
                    return true;
                }
                break;
            case 'H':
                $dayOfMonth = $now->startOfMonth()->addDays($autoJob->execday - 1);
                $dayOfMonth2 = $now->startOfMonth()->addDays($autoJob->execday - 1 + 15);

                if ($dayOfMonth->isSameDay($now) || $dayOfMonth2->isSameDay($now)) {
                    return true;
                }
                break;
            case 'M':
                $dayOfMonth = $now->startOfMonth()->addDays($autoJob->execday - 1);
                if ($dayOfMonth->isSameDay($now)) {
                    return true;
                }
                break;
            case 'Q':
                return true;
            default:
                return false;
        }
    }
    public function getNextDate($autoJob)
    {
        $now = Carbon::now();
        switch ($autoJob->execfreq) {
            case 'D':
                if ($autoJob->exectime) {
                    $time = Carbon::createFromFormat('H:i:s', $autoJob->exectime);
                    $nextDate = $now->hour($time->hour);
                    $nextDate = $now->minute($time->minute);
                    $nextDate = $now->second($time->second);
                }
                return $nextDate;
                break;
            case 'W':
                $nextDate = $now->next($autoJob->execday); // Weekly
                if ($autoJob->exectime) {
                    $time = Carbon::createFromFormat('H:i:s', $autoJob->exectime);
                    $nextDate = $now->hour($time->hour);
                    $nextDate = $now->minute($time->minute);
                    $nextDate = $now->second($time->second);
                }
                return $nextDate;
                break;
            case 'H':
                $nextDate = $now->firstOfMonth()->addDays($autoJob->execday - 1);

                if ($now->day > 15) {
                    $nextDate = $now->firstOfMonth()->addDays(($autoJob->execday - 1) + 15);
                }

                if ($autoJob->exectime) {
                    $time = Carbon::createFromFormat('H:i:s', $autoJob->exectime);
                    $nextDate = $now->hour($time->hour);
                    $nextDate = $now->minute($time->minute);
                    $nextDate = $now->second($time->second);
                }
                return $nextDate;
                break;
            case 'M':
                $nextDate = $now->firstOfMonth()->addDays($autoJob->execday - 1);

                if ($autoJob->exectime) {
                    $time = Carbon::createFromFormat('H:i:s', $autoJob->exectime);
                    $nextDate = $now->hour($time->hour);
                    $nextDate = $now->minute($time->minute);
                    $nextDate = $now->second($time->second);
                }
                return $nextDate;
                break;
            case 'S':
                $nextDate = $now;

                if ($autoJob->exectime) {
                    $time = Carbon::createFromFormat('H:i:s', $autoJob->exectime);
                    $nextDate = $now->hour($time->hour);
                    $nextDate = $now->minute($time->minute);
                    $nextDate = $now->second($time->second);
                    return $nextDate;
                } else {
                    return null;
                }
                break;
            case 'E':
                if (!$autoJob->exectime) return null;

                $time = Carbon::createFromFormat('H:i:s', $autoJob->exectime);
                $scheduledTime = Carbon::today()->setTime($time->hour, $time->minute, 0);

                while ($scheduledTime->lte(Carbon::now())) {
                    $scheduledTime->addHours($autoJob->execday);
                }

                return $scheduledTime;

            case 'Q':
                if ($autoJob->lastexecdate) {
                    $lastexecdate = new Carbon($autoJob->lastexecdate);
                    $nextDate = $lastexecdate;
                    $nextDate->addMinutes($autoJob->execday);

                    return $nextDate;
                } else {
                    return new Carbon();
                }

                break;
            default:
                return null;
                break;
        }
    }
}
