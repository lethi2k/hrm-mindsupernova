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

namespace OrangeHRM\Time\Factory;

use OrangeHRM\Time\TimeSheetPeriod\MonthlyTimesheetPeriod;

class TimesheetPeriodFactory
{
    /**
     * @param string $className
     * @return mixed
     */
    public function createTimesheetPeriod(string $className)
    {
        $fqcn = $className;
        if (!str_contains($fqcn, '\\')) {
            $fqcn = 'OrangeHRM\\Time\\TimeSheetPeriod\\' . $className;
        }
        return new $fqcn();
    }

    /**
     * @return MonthlyTimesheetPeriod
     */
    public function setTimesheetPeriod(): MonthlyTimesheetPeriod
    {
        return new MonthlyTimesheetPeriod();
    }
}
