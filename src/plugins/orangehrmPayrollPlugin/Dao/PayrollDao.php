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

namespace OrangeHRM\Payroll\Dao;

use OrangeHRM\Core\Dao\BaseDao;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Mail;
use OrangeHRM\Entity\PayrollConfig;
use OrangeHRM\Entity\PayrollEmailLog;
use OrangeHRM\Entity\PayrollPayslip;
use OrangeHRM\Entity\PayrollRun;
use OrangeHRM\Entity\EmployeeSalary;
use Doctrine\DBAL\ParameterType;

class PayrollDao extends BaseDao
{
    public function saveRun(PayrollRun $run): PayrollRun
    {
        $this->persist($run);
        return $run;
    }

    public function getRun(int $id): ?PayrollRun
    {
        return $this->getRepository(PayrollRun::class)->find($id);
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    public function getPayrollRunIdsInDraft(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $q = $this->getRepository(PayrollRun::class)->createQueryBuilder('r');
        $q->select('r.id')
            ->andWhere($q->expr()->in('r.id', ':ids'))
            ->setParameter('ids', $ids, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
        return array_map('intval', array_column($q->getQuery()->getArrayResult(), 'id'));
    }

    public function removePayrollRun(PayrollRun $run): void
    {
        $this->remove($run);
    }

    public function getRunByYearMonth(string $ym): ?PayrollRun
    {
        $q = $this->getRepository(PayrollRun::class)
            ->createQueryBuilder('r')
            ->andWhere('r.yearMonth = :ym')
            ->setParameter('ym', $ym);
        return $q->getQuery()->getOneOrNullResult();
    }

    /**
     * @return PayrollRun[]
     */
    public function getPayrollRunList(int $offset, int $limit): array
    {
        $q = $this->getRepository(PayrollRun::class)
            ->createQueryBuilder('r')
            ->orderBy('r.yearMonth', 'DESC');
        if ($offset > 0) {
            $q->setFirstResult($offset);
        }
        $q->setMaxResults($limit);
        return $q->getQuery()->getResult();
    }

    public function getPayrollRunCount(): int
    {
        $q = $this->getRepository(PayrollRun::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)');
        return (int) $q->getQuery()->getSingleScalarResult();
    }

    public function savePayslip(PayrollPayslip $payslip): PayrollPayslip
    {
        $this->persist($payslip);
        return $payslip;
    }

    public function updatePayslipAllowanceByRunAndEmp(int $runId, int $empNumber, string $allowance): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE ohrm_payroll_payslip p
             INNER JOIN ohrm_payroll_run r ON p.payroll_run_id = r.id
             SET p.allowance = :allowance
             WHERE r.id = :runId AND p.emp_number = :empNumber',
            [
                'allowance' => $allowance,
                'runId' => $runId,
                'empNumber' => $empNumber,
            ],
            [
                'allowance' => ParameterType::STRING,
                'runId' => ParameterType::INTEGER,
                'empNumber' => ParameterType::INTEGER,
            ]
        );
    }

    public function getPayslipByRunAndEmp(int $runId, int $empNumber): ?PayrollPayslip
    {
        $q = $this->getRepository(PayrollPayslip::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.payrollRun', 'r')
            ->andWhere('r.id = :rid')
            ->andWhere('p.empNumber = :e')
            ->setParameter('rid', $runId)
            ->setParameter('e', $empNumber);
        return $q->getQuery()->getOneOrNullResult();
    }

    /**
     * @return PayrollPayslip[]
     */
    public function getPayslipsForRun(int $runId, int $offset, int $limit): array
    {
        $q = $this->getRepository(PayrollPayslip::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.payrollRun', 'r')
            ->andWhere('r.id = :rid')
            ->setParameter('rid', $runId)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        return $q->getQuery()->getResult();
    }

    public function getPayslipAmountMapForRun(int $runId): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT emp_number, net_salary, allowance
             FROM ohrm_payroll_payslip
             WHERE payroll_run_id = :runId',
            ['runId' => $runId],
            ['runId' => ParameterType::INTEGER]
        );
        $map = [];
        foreach ($rows as $row) {
            $empNumber = (int) ($row['emp_number'] ?? 0);
            if ($empNumber <= 0) {
                continue;
            }
            $map[$empNumber] = [
                'netSalary' => $row['net_salary'] ?? null,
                'allowance' => $row['allowance'] ?? null,
            ];
        }
        return $map;
    }

    public function countPayslipsForRun(int $runId): int
    {
        $q = $this->getRepository(PayrollPayslip::class)
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->innerJoin('p.payrollRun', 'r')
            ->andWhere('r.id = :rid')
            ->setParameter('rid', $runId);
        return (int) $q->getQuery()->getSingleScalarResult();
    }

    public function saveEmailLog(PayrollEmailLog $log): PayrollEmailLog
    {
        $this->persist($log);
        return $log;
    }

    public function getEmailLog(int $id): ?PayrollEmailLog
    {
        return $this->getRepository(PayrollEmailLog::class)->find($id);
    }

    /**
     * @return PayrollEmailLog[]
     */
    public function getEmailLogsForRun(int $runId, int $offset, int $limit): array
    {
        $q = $this->getRepository(PayrollEmailLog::class)
            ->createQueryBuilder('l')
            ->innerJoin('l.payslip', 'p')
            ->innerJoin('p.payrollRun', 'r')
            ->andWhere('r.id = :rid')
            ->setParameter('rid', $runId)
            ->orderBy('l.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        return $q->getQuery()->getResult();
    }

    public function countEmailLogsForRun(int $runId): int
    {
        $q = $this->getRepository(PayrollEmailLog::class)
            ->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->innerJoin('l.payslip', 'p')
            ->innerJoin('p.payrollRun', 'r')
            ->andWhere('r.id = :rid')
            ->setParameter('rid', $runId);
        return (int) $q->getQuery()->getSingleScalarResult();
    }

    public function getConfig(): ?PayrollConfig
    {
        return $this->getRepository(PayrollConfig::class)->find(1);
    }

    public function saveConfig(PayrollConfig $config): PayrollConfig
    {
        $this->persist($config);
        return $config;
    }

    /**
     * @return int[]
     */
    public function getActiveEmployeeNumbers(): array
    {
        $q = $this->getRepository(Employee::class)
            ->createQueryBuilder('e')
            ->select('e.empNumber')
            ->andWhere('e.employeeTerminationRecord IS NULL');
        $rows = $q->getQuery()->getArrayResult();
        return array_map(static function ($r) {
            return (int) $r['empNumber'];
        }, $rows);
    }

    public function getEmployeeByNumber(int $empNumber): ?Employee
    {
        return $this->getRepository(Employee::class)->find($empNumber);
    }

    public function getEmployeeByEmpNumber(int $empNumber): ?Employee
    {
        return $this->getEmployeeByNumber($empNumber);
    }

    /**
     * @return int[]
     */
    public function getAllEmployeeNumbers(): array
    {
        $q = $this->getRepository(Employee::class)
            ->createQueryBuilder('e')
            ->select('e.empNumber');
        $rows = $q->getQuery()->getArrayResult();
        return array_map(static function ($r) {
            return (int) $r['empNumber'];
        }, $rows);
    }

    /**
     * @return EmployeeSalary[]
     */
    public function getEmployeeSalaries(int $empNumber): array
    {
        $q = $this->getRepository(EmployeeSalary::class)
            ->createQueryBuilder('s')
            ->innerJoin('s.employee', 'emp')
            ->andWhere('emp.empNumber = :e')
            ->setParameter('e', $empNumber);
        return $q->getQuery()->getResult();
    }

    public function getMail(int $id): ?Mail
    {
        return $this->getRepository(Mail::class)->find($id);
    }

    public function getActualWorkingDaysByEmployeeAndMonth(int $empNumber, string $yearMonth): int
    {
        $from = $yearMonth . '-01';
        $to = date('Y-m-t', strtotime($from));
        $count = $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT `date`)
             FROM ohrm_timesheet_item
             WHERE employee_id = :emp
               AND duration IS NOT NULL
               AND duration > 0
               AND `date` >= :fromDate
               AND `date` <= :toDate',
            [
                'emp' => $empNumber,
                'fromDate' => $from,
                'toDate' => $to,
            ],
            [
                'emp' => ParameterType::INTEGER,
                'fromDate' => ParameterType::STRING,
                'toDate' => ParameterType::STRING,
            ]
        );
        return (int) $count;
    }

    public function getEmployeeTimesheetSummaryByMonth(int $empNumber, string $yearMonth): array
    {
        $from = $yearMonth . '-01';
        $to = date('Y-m-t', strtotime($from));
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT `date`, SUM(duration) AS totalDuration
             FROM ohrm_timesheet_item
             WHERE employee_id = :emp
               AND duration IS NOT NULL
               AND duration > 0
               AND `date` >= :fromDate
               AND `date` <= :toDate
             GROUP BY `date`',
            [
                'emp' => $empNumber,
                'fromDate' => $from,
                'toDate' => $to,
            ],
            [
                'emp' => ParameterType::INTEGER,
                'fromDate' => ParameterType::STRING,
                'toDate' => ParameterType::STRING,
            ]
        );

        $standardWorkdaySeconds = 8 * 3600;
        $regularSeconds = 0.0;
        $overtimeSeconds = 0.0;

        foreach ($rows as $row) {
            $date = (string) ($row['date'] ?? '');
            $workedSeconds = (int) ($row['totalDuration'] ?? 0);
            if ($workedSeconds <= 0 || $date === '') {
                continue;
            }
            $dayOfWeek = (int) date('w', strtotime($date));
            if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                $overtimeSeconds += $workedSeconds * 2;
                continue;
            }
            $regularSeconds += min($workedSeconds, $standardWorkdaySeconds);
            $overtimeSeconds += max(0, $workedSeconds - $standardWorkdaySeconds) * 1.5;
        }

        return [
            'regularSeconds' => $regularSeconds,
            'overtimeSeconds' => $overtimeSeconds,
        ];
    }

    public function getEmployeeBaseSalaryAmount(int $empNumber): ?float
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT ebsal_basic_salary AS amount, salary_component AS component
             FROM hs_hr_emp_basicsalary
             WHERE emp_number = :emp
             ORDER BY id DESC',
            ['emp' => $empNumber],
            ['emp' => ParameterType::INTEGER]
        );
        if ($rows === []) {
            return null;
        }
        foreach ($rows as $row) {
            $component = (string) ($row['component'] ?? '');
            $normalizedComponent = function_exists('mb_strtolower')
                ? mb_strtolower($component, 'UTF-8')
                : strtolower($component);
            $amount = $row['amount'] ?? null;
            if (
                ($normalizedComponent !== '')
                && (
                    strpos($normalizedComponent, 'base') !== false
                    || strpos($normalizedComponent, 'basic') !== false
                    || strpos($normalizedComponent, 'luong co ban') !== false
                    || strpos($normalizedComponent, 'lương cơ bản') !== false
                )
                && $amount !== null
                && is_numeric($amount)
            ) {
                return (float) $amount;
            }
        }
        foreach ($rows as $row) {
            $amount = $row['amount'] ?? null;
            if ($amount !== null && is_numeric($amount)) {
                return (float) $amount;
            }
        }
        return null;
    }
}
