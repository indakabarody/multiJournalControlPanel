<?php
/**
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

class WorkingDayCalculator
{
    /** @var array Map of 'Y-m-d' => true for excluded holidays */
    protected $_holidayMap = [];

    /** @var DateTimeZone Active timezone for calculations */
    protected $_timeZone;

    /** @var array Map of ISO-8601 day of week (1=Mon .. 5=Fri) => true */
    protected $_workingDaysMap = [];

    /**
     * Constructor.
     * Monday (1) to Friday (5) are active by default; Saturday (6) and Sunday (7) are excluded unless configured.
     *
     * @param array $holidayDates List of holiday date strings ('Y-m-d')
     * @param string|null $timezoneName Timezone identifier (default 'UTC')
     * @param array|string|null $workingDays Active ISO-8601 working days (default Monday-Friday: [1,2,3,4,5])
     */
    public function __construct($holidayDates = [], $timezoneName = 'UTC', $workingDays = [1, 2, 3, 4, 5])
    {
        foreach ((array) $holidayDates as $dateStr) {
            if (!empty($dateStr)) {
                $normalized = substr(trim((string) $dateStr), 0, 10);
                $this->_holidayMap[$normalized] = true;
            }
        }

        if (is_string($workingDays)) {
            $workingDays = preg_split('/[\s,]+/', trim($workingDays), -1, PREG_SPLIT_NO_EMPTY);
        }

        if (is_array($workingDays)) {
            foreach ($workingDays as $d) {
                $dayInt = (int) $d;
                if ($dayInt >= 1 && $dayInt <= 7) {
                    $this->_workingDaysMap[$dayInt] = true;
                }
            }
        }

        if (empty($this->_workingDaysMap)) {
            $this->_workingDaysMap = [
                1 => true,
                2 => true,
                3 => true,
                4 => true,
                5 => true,
            ];
        }

        try {
            $this->_timeZone = new DateTimeZone($timezoneName ? $timezoneName : 'UTC');
        } catch (Exception $e) {
            $this->_timeZone = new DateTimeZone('UTC');
        }
    }

    /**
     * Return today's date string ('Y-m-d') in the configured timezone.
     *
     * @param string|null $referenceDate Optional override date ('Y-m-d')
     * @return string
     */
    public function getTodayDateString($referenceDate = null)
    {
        if (!empty($referenceDate)) {
            return substr(trim((string) $referenceDate), 0, 10);
        }
        $now = new DateTime('now', $this->_timeZone);
        return $now->format('Y-m-d');
    }

    /**
     * Determine whether a given date ('Y-m-d' or DateTimeInterface) is a working day.
     * Excludes Saturday (6), Sunday (7), and configured holiday dates.
     *
     * @param DateTimeInterface|string $date
     * @return bool
     */
    public function isWorkingDay($date)
    {
        $dt = $date instanceof DateTimeInterface ? clone $date : new DateTime(substr((string) $date, 0, 10), $this->_timeZone);

        $dayOfWeek = (int) $dt->format('N');
        if (!isset($this->_workingDaysMap[$dayOfWeek])) {
            return false;
        }

        $ymd = $dt->format('Y-m-d');
        if (isset($this->_holidayMap[$ymd])) {
            return false;
        }

        return true;
    }

    /**
     * Add $workingDays working days to $startDate.
     *
     * @param string $startDate Date string ('Y-m-d')
     * @param int $workingDays Number of working days to add
     * @return string Due date in 'Y-m-d' format
     */
    public function addWorkingDays($startDate, $workingDays)
    {
        $dt = new DateTime(substr((string) $startDate, 0, 10), $this->_timeZone);
        $remaining = max(0, (int) $workingDays);
        $safety = 0;

        while ($remaining > 0 && $safety < 3660) {
            $dt->modify('+1 day');
            $safety++;
            if ($this->isWorkingDay($dt)) {
                $remaining--;
            }
        }

        return $dt->format('Y-m-d');
    }

    /**
     * Count working days strictly after $startDate up to and including $endDate.
     *
     * @param string $startDate Start date ('Y-m-d')
     * @param string $endDate End date ('Y-m-d')
     * @return int Number of working days elapsed
     */
    public function countWorkingDaysBetween($startDate, $endDate)
    {
        $startYmd = substr((string) $startDate, 0, 10);
        $endYmd = substr((string) $endDate, 0, 10);

        if ($endYmd <= $startYmd) {
            return 0;
        }

        $cursor = new DateTime($startYmd, $this->_timeZone);
        $target = new DateTime($endYmd, $this->_timeZone);
        $count = 0;

        while ($cursor < $target) {
            $cursor->modify('+1 day');
            if ($this->isWorkingDay($cursor)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Evaluate review round status, due date, and remaining/overdue working days.
     *
     * @param string $startDate Round start date ('Y-m-d')
     * @param int $limitWorkingDays Maximum working days for the round (14-21 for Round 1, 7 for Round 2+)
     * @param int $warningWorkingDays Almost-due threshold in working days (default 3 for Round 1, 2 for Round 2+)
     * @param string|null $referenceDate Optional reference date ('Y-m-d')
     * @return array
     */
    public function evaluateReviewRound($startDate, $limitWorkingDays, $warningWorkingDays = 2, $referenceDate = null)
    {
        $startYmd = substr((string) $startDate, 0, 10);
        $todayYmd = $this->getTodayDateString($referenceDate);
        $dueYmd = $this->addWorkingDays($startYmd, $limitWorkingDays);
        $elapsed = $this->countWorkingDaysBetween($startYmd, $todayYmd);

        if ($todayYmd <= $dueYmd) {
            $remaining = $this->countWorkingDaysBetween($todayYmd, $dueYmd);
            $overdue = 0;
            $status = $remaining <= (int) $warningWorkingDays ? 'almost_due' : 'normal';
            $urgencyScore = $status === 'almost_due' ? 5000 - $remaining : 1000 - $remaining;
        } else {
            $remaining = 0;
            $overdue = $this->countWorkingDaysBetween($dueYmd, $todayYmd);
            if ($overdue >= 1) {
                $status = 'overdue';
                $urgencyScore = 10000 + $overdue;
            } else {
                $status = 'almost_due';
                $urgencyScore = 5000;
            }
        }

        return [
            'startDate' => $startYmd,
            'dueDate' => $dueYmd,
            'elapsedWorkingDays' => $elapsed,
            'remainingWorkingDays' => $remaining,
            'overdueWorkingDays' => $overdue,
            'status' => $status,
            'urgencyScore' => $urgencyScore,
        ];
    }

    /**
     * Evaluate Copyediting stage status based on target publication date H (end of target issue month).
     * H-10 before end of target month triggers almost_due warning; past target date triggers overdue.
     *
     * @param string|null $targetDate Target issue date ('Y-m-d')
     * @param int $reminderCalendarDays Default 10 (H-10)
     * @param string|null $referenceDate Optional reference date ('Y-m-d')
     * @return array
     */
    public function evaluateCopyediting($targetDate, $reminderCalendarDays = 10, $referenceDate = null)
    {
        if (empty($targetDate)) {
            // Default fallback to end of current month if no target issue is assigned
            $todayYmd = $this->getTodayDateString($referenceDate);
            $targetDate = date('Y-m-t', strtotime($todayYmd));
        }

        $targetYmd = substr((string) $targetDate, 0, 10);
        $todayYmd = $this->getTodayDateString($referenceDate);

        $todayDt = new DateTime($todayYmd, $this->_timeZone);
        $targetDt = new DateTime($targetYmd, $this->_timeZone);
        $interval = $todayDt->diff($targetDt);
        $diffDays = (int) $interval->format('%r%a');

        if ($diffDays > (int) $reminderCalendarDays) {
            return [
                'targetDate' => $targetYmd,
                'diffCalendarDays' => $diffDays,
                'remainingCalendarDays' => $diffDays,
                'overdueCalendarDays' => 0,
                'status' => 'normal',
                'urgencyScore' => max(1, 1000 - $diffDays),
            ];
        } elseif ($diffDays >= 0) {
            return [
                'targetDate' => $targetYmd,
                'diffCalendarDays' => $diffDays,
                'remainingCalendarDays' => $diffDays,
                'overdueCalendarDays' => 0,
                'status' => 'almost_due',
                'urgencyScore' => 5000 - $diffDays,
            ];
        } else {
            $overdueDays = abs($diffDays);
            return [
                'targetDate' => $targetYmd,
                'diffCalendarDays' => $diffDays,
                'remainingCalendarDays' => 0,
                'overdueCalendarDays' => $overdueDays,
                'status' => 'overdue',
                'urgencyScore' => 10000 + $overdueDays,
            ];
        }
    }
}
