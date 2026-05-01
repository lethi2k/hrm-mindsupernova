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

namespace OrangeHRM\Time\Report;

use DateTimeInterface;
use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Report\ReportData;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Core\Traits\Service\NormalizerServiceTrait;
use OrangeHRM\Core\Traits\Service\NumberHelperTrait;
use OrangeHRM\Time\Api\Model\ProjectModel;
use OrangeHRM\Time\Dto\ProjectReportSearchFilterParams;
use OrangeHRM\Time\Traits\Service\ProjectServiceTrait;

class ProjectReportData implements ReportData
{
    use ProjectServiceTrait;
    use NumberHelperTrait;
    use DateTimeHelperTrait;
    use NormalizerServiceTrait;

    private const STANDARD_WORKDAY_SECONDS = 8 * 3600;

    private ProjectReportSearchFilterParams $filterParams;
    private ?array $normalizedData = null;

    public function __construct(ProjectReportSearchFilterParams $filterParams)
    {
        $this->filterParams = $filterParams;
    }

    /**
     * @inheritDoc
     */
    public function normalize(): array
    {
        if (!is_null($this->normalizedData)) {
            return $this->normalizedData;
        }

        $projectActivities = $this->getProjectService()
            ->getProjectDao()
            ->getProjectReportCriteriaList($this->filterParams);
        $fromDateYmd = $this->getDateTimeHelper()->formatDateTimeToYmd($this->filterParams->getFromDate());
        $timesheetPeriod = '-';
        if (!is_null($fromDateYmd)) {
            $timesheetPeriod = date('Y-m', strtotime($fromDateYmd));
        }
        $dateRange = $this->getDateRangeYmd($this->filterParams->getFromDate(), $this->filterParams->getToDate());
        $groupedRows = [];
        foreach ($projectActivities as $projectActivity) {
            $empNumber = (int)($projectActivity['empNumber'] ?? 0);
            $groupKey = $empNumber . '_' . ($projectActivity['activityName'] ?? '');
            if (!isset($groupedRows[$groupKey])) {
                $groupedRows[$groupKey] = [
                    'empNumber' => $empNumber,
                    'fullName' => $projectActivity['fullName'] ?? '',
                    'activityName' => $projectActivity['activityName'] ?? '',
                    'durationsByDate' => [],
                ];
            }

            $itemDate = $projectActivity['itemDate'] ?? null;
            $dailyDuration = (float)($projectActivity['totalDuration'] ?? 0);
            if (!is_null($itemDate) && $dailyDuration > 0) {
                $dateKey = $itemDate instanceof DateTimeInterface ? $itemDate->format('Y-m-d') : date('Y-m-d', strtotime((string)$itemDate));
                if (!isset($groupedRows[$groupKey]['durationsByDate'][$dateKey])) {
                    $groupedRows[$groupKey]['durationsByDate'][$dateKey] = 0.0;
                }
                $groupedRows[$groupKey]['durationsByDate'][$dateKey] += $dailyDuration;
            }
        }

        $result = [];
        foreach ($groupedRows as $row) {
            $regularSeconds = $this->calculateRegularSeconds($row['durationsByDate']);
            $overtimeSeconds = $this->calculateOvertimeSeconds($row['durationsByDate']);
            $leaveSeconds = $this->calculateLeaveSeconds($row['durationsByDate'], $dateRange);
            $result[] = [
                ProjectReport::PARAMETER_EMPLOYEE_NAME => $row['fullName'],
                ProjectReport::PARAMETER_ACTIVITY_NAME => $row['activityName'],
                ProjectReport::PARAMETER_TIMESHEET_PERIOD => $timesheetPeriod,
                ProjectReport::PARAMETER_WORKING_HOURS => $this->getNumberHelper()
                    ->numberFormat($regularSeconds / 3600, 2),
                ProjectReport::PARAMETER_OVERTIME => $this->getNumberHelper()
                    ->numberFormat($overtimeSeconds / 3600, 2),
                ProjectReport::PARAMETER_LEAVE_HOURS => $this->getNumberHelper()
                    ->numberFormat($leaveSeconds / 3600, 2),
            ];
        }
        $this->normalizedData = $result;
        return $this->normalizedData;
    }

    /**
     * @param array<string, float> $durationsByDate
     */
    private function calculateRegularSeconds(array $durationsByDate): float
    {
        $regularSeconds = 0.0;
        foreach ($durationsByDate as $date => $duration) {
            if ($this->isWeekend($date)) {
                continue;
            }
            $regularSeconds += min((float)$duration, self::STANDARD_WORKDAY_SECONDS);
        }
        return $regularSeconds;
    }

    /**
     * @param array<string, float> $durationsByDate
     */
    private function calculateOvertimeSeconds(array $durationsByDate): float
    {
        $overtimeSeconds = 0.0;
        foreach ($durationsByDate as $date => $duration) {
            if ($this->isWeekend($date)) {
                $overtimeSeconds += (float)$duration;
                continue;
            }
            $overtimeSeconds += max(0, (float)$duration - self::STANDARD_WORKDAY_SECONDS);
        }
        return $overtimeSeconds;
    }

    /**
     * @param array<string, float> $durationsByDate
     * @param string[] $dateRange
     */
    private function calculateLeaveSeconds(array $durationsByDate, array $dateRange): float
    {
        $leaveSeconds = 0.0;
        $dates = $dateRange !== [] ? $dateRange : array_keys($durationsByDate);
        foreach ($dates as $date) {
            if ($this->isWeekend($date)) {
                continue;
            }
            $duration = (float)($durationsByDate[$date] ?? 0);
            $leaveSeconds += max(0, self::STANDARD_WORKDAY_SECONDS - $duration);
        }
        return $leaveSeconds;
    }

    private function isWeekend(string $date): bool
    {
        $dayOfWeek = (int)date('w', strtotime($date));
        return $dayOfWeek === 0 || $dayOfWeek === 6;
    }

    /**
     * @return string[]
     */
    private function getDateRangeYmd(?DateTimeInterface $fromDate, ?DateTimeInterface $toDate): array
    {
        if (is_null($fromDate) || is_null($toDate)) {
            return [];
        }
        if ($fromDate > $toDate) {
            return [];
        }
        $dates = [];
        $current = clone $fromDate;
        while ($current <= $toDate) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }
        return $dates;
    }

    /**
     * @inheritDoc
     */
    public function getMeta(): ?ParameterBag
    {
        $project = $this->getProjectService()->getProjectDao()->getProjectById($this->filterParams->getProjectId());
        $total = $this->getProjectService()
            ->getProjectDao()
            ->getTotalDurationForProjectReport($this->filterParams);

        return new ParameterBag(
            [
                CommonParams::PARAMETER_TOTAL => count($this->normalize()),
                'sum' => [
                    'hours' => floor($total / 3600),
                    'minutes' => ($total / 60) % 60,
                    'label' => $this->getNumberHelper()->numberFormat($total / 3600, 2),
                ],
                'project' => $this->getNormalizerService()
                    ->normalize(ProjectModel::class, $project),
            ]
        );
    }
}
