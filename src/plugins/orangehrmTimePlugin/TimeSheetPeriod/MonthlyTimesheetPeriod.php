<?php

/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Time\TimeSheetPeriod;

use DateTime;

class MonthlyTimesheetPeriod extends WeeklyTimesheetPeriod
{
    /**
     * @param string $currentDate
     * @param $xml
     * @return array
     */
    public function calculateDaysInTheTimesheetPeriod($currentDate, $xml): array
    {
        if (class_exists('\sfContext')) {
            $clientTimeZoneOffset = \sfContext::getInstance()->getUser()->getUserTimeZoneOffset();
            $timezone = $this->getLocalTimezone($clientTimeZoneOffset);
            if ($timezone !== false) {
                date_default_timezone_set($timezone);
            }
        }

        $startDay = max(1, min(31, (int)$xml->StartDate));
        $current = new DateTime($currentDate);

        $periodStart = (clone $current)->modify('first day of this month');
        if ((int)$current->format('j') < $startDay) {
            $periodStart->modify('first day of previous month');
        }
        $periodStart = $this->setDayWithinMonth($periodStart, $startDay);

        $nextPeriodStart = (clone $periodStart)->modify('first day of next month');
        $nextPeriodStart = $this->setDayWithinMonth($nextPeriodStart, $startDay);
        $periodEnd = (clone $nextPeriodStart)->modify('-1 day');

        $dates = [];
        $cursor = clone $periodStart;
        while ($cursor <= $periodEnd) {
            $dates[] = $cursor->format('Y-m-d H:i');
            $cursor->modify('+1 day');
        }

        return $dates;
    }

    /**
     * @param string $startDay
     * @return string
     */
    public function setTimesheetPeriodAndStartDate($startDay)
    {
        return "<TimesheetPeriod><PeriodType>Monthly</PeriodType><ClassName>MonthlyTimesheetPeriod</ClassName><StartDate>" . $startDay . "</StartDate><Heading>Month</Heading></TimesheetPeriod>";
    }

    private function setDayWithinMonth(DateTime $date, int $day): DateTime
    {
        $maxDay = (int)$date->format('t');
        $targetDay = min($day, $maxDay);
        $date->setDate((int)$date->format('Y'), (int)$date->format('m'), $targetDay);
        $date->setTime(0, 0);
        return $date;
    }
}
