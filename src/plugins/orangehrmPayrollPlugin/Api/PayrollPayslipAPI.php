<?php

namespace OrangeHRM\Payroll\Api;

use Exception;
use Throwable;
use OrangeHRM\Core\Api\CommonParams;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\EndpointCollectionResult;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\ParameterBag;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Payroll\Api\Model\PayrollPayslipReviewModel;
use OrangeHRM\Payroll\Dao\PayrollDao;

class PayrollPayslipAPI extends Endpoint implements CollectionEndpoint
{
    public const PARAM_RUN = 'runId';
    private ?PayrollDao $payrollDao = null;

    public function getPayrollDao(): PayrollDao
    {
        if ($this->payrollDao === null) {
            $this->payrollDao = new PayrollDao();
        }
        return $this->payrollDao;
    }

    public function getAll(): EndpointResult
    {
        try {
            $run = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, self::PARAM_RUN);
            $offset = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, CommonParams::PARAMETER_OFFSET, 0);
            $limit = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, CommonParams::PARAMETER_LIMIT, 100);
            $runEntity = $this->getPayrollDao()->getRun($run);
            $yearMonth = $runEntity?->getYearMonth() ?? date('Y-m');
            $persistedAmountMap = $this->getPayrollDao()->getPayslipAmountMapForRun($run);
            $list = $this->getPayrollDao()->getPayslipsForRun($run, $offset, $limit);
            $rows = [];
            foreach ($list as $payslip) {
                $employee = $this->getPayrollDao()->getEmployeeByNumber($payslip->getEmpNumber());
                $baseSalary = $this->resolveBaseSalary($payslip->getEmpNumber());
                $persisted = $persistedAmountMap[$payslip->getEmpNumber()] ?? [];
                $rows[] = $this->buildReviewRow(
                    $payslip->getId(),
                    $payslip->getEmpNumber(),
                    $employee,
                    $baseSalary,
                    $yearMonth,
                    (string) ($persisted['netSalary'] ?? $payslip->getNetSalary()),
                    (string) ($persisted['allowance'] ?? $payslip->getAllowance()),
                    $payslip->getFilePath(),
                    $payslip->getFileFormat(),
                    $payslip->getFileChecksum()
                );
            }
            if ($rows === []) {
                $rows = $this->buildDefaultRows($yearMonth, $persistedAmountMap);
            }
            $c = count($rows);
            return new EndpointCollectionResult(
                PayrollPayslipReviewModel::class,
                $rows,
                new ParameterBag([CommonParams::PARAMETER_TOTAL => $c])
            );
        } catch (Throwable $exception) {
            try {
                $run = $this->getRequestParams()->getInt(RequestParams::PARAM_TYPE_QUERY, self::PARAM_RUN);
                $runEntity = $this->getPayrollDao()->getRun($run);
                $yearMonth = $runEntity?->getYearMonth() ?? date('Y-m');
                $persistedAmountMap = $this->getPayrollDao()->getPayslipAmountMapForRun($run);
                $rows = $this->buildDefaultRows($yearMonth, $persistedAmountMap);
            } catch (Throwable $nestedException) {
                $rows = [];
            }
            return new EndpointCollectionResult(
                PayrollPayslipReviewModel::class,
                $rows,
                new ParameterBag([CommonParams::PARAMETER_TOTAL => count($rows)])
            );
        }
    }

    private function buildDefaultRows(string $yearMonth, array $persistedAmountMap = []): array
    {
        $rows = [];
        foreach ($this->getPayrollDao()->getAllEmployeeNumbers() as $empNumber) {
            $employee = $this->getPayrollDao()->getEmployeeByNumber($empNumber);
            $persisted = $persistedAmountMap[$empNumber] ?? [];
            $rows[] = $this->buildReviewRow(
                $empNumber,
                $empNumber,
                $employee,
                $this->resolveBaseSalary($empNumber),
                $yearMonth,
                (string) ($persisted['netSalary'] ?? null),
                (string) ($persisted['allowance'] ?? null)
            );
        }
        return $rows;
    }

    private function buildReviewRow(
        int $rowId,
        int $empNumber,
        $employee,
        float $baseSalary,
        string $yearMonth,
        ?string $netSalary = null,
        ?string $storedAllowance = null,
        ?string $filePath = null,
        string $fileFormat = 'xlsx',
        ?string $fileChecksum = null
    ): array {
        $standardWorkingDays = 22;
        $summary = $this->getPayrollDao()->getEmployeeTimesheetSummaryByMonth($empNumber, $yearMonth);
        $regularSeconds = (float) ($summary['regularSeconds'] ?? 0);
        $overtimeSeconds = (float) ($summary['overtimeSeconds'] ?? 0);
        $actualWorkingDays = $regularSeconds / (8 * 3600);
        $actualSalary = $standardWorkingDays > 0 ? ($baseSalary / $standardWorkingDays) * $actualWorkingDays : 0.0;
        $hourlyRate = ($standardWorkingDays > 0 && $baseSalary > 0)
            ? $baseSalary / ($standardWorkingDays * 8)
            : 0.0;
        $overtimePay = ($overtimeSeconds / 3600.0) * $hourlyRate;
        $allowance = (float) ($storedAllowance ?? '0');
        return [
            'id' => $rowId,
            'empNumber' => $empNumber,
            'employeeId' => $employee?->getEmployeeId() ?: (string) $empNumber,
            'fullName' => trim(implode(' ', array_filter([
                $employee?->getLastName(),
                $employee?->getMiddleName(),
                $employee?->getFirstName(),
            ]))),
            'nationalId' => $employee?->getDrivingLicenseNo() ?? '',
            'jobTitle' => $employee?->getJobTitle()?->getJobTitleName() ?? '',
            'baseSalary' => number_format($baseSalary, 2, '.', ''),
            'standardWorkingDays' => $standardWorkingDays,
            'actualWorkingDays' => number_format($actualWorkingDays, 2, '.', ''),
            'actualSalary' => number_format($actualSalary, 2, '.', ''),
            'overtime' => $this->formatSecondsToHoursLabel($overtimeSeconds),
            'overtimePay' => number_format($overtimePay, 2, '.', ''),
            'allowance' => number_format($allowance, 2, '.', ''),
            'totalSalary' => number_format($actualSalary + $overtimePay + $allowance, 2, '.', ''),
            'netSalary' => $netSalary,
            'filePath' => $filePath,
            'fileFormat' => $fileFormat,
            'fileChecksum' => $fileChecksum,
        ];
    }

    private function resolveBaseSalary(int $empNumber): float
    {
        $salaries = $this->getPayrollDao()->getEmployeeSalaries($empNumber);
        foreach ($salaries as $salary) {
            $name = (string) ($salary->getSalaryName() ?? '');
            $normalizedName = function_exists('mb_strtolower')
                ? mb_strtolower($name, 'UTF-8')
                : strtolower($name);
            if (
                strpos($normalizedName, 'base') !== false
                || strpos($normalizedName, 'basic') !== false
                || strpos($normalizedName, 'luong co ban') !== false
                || strpos($normalizedName, 'lương cơ bản') !== false
            ) {
                $amount = $this->parseSalaryAmount($salary->getAmount());
                if ($amount !== null) {
                    return $amount;
                }
            }
        }
        foreach ($salaries as $salary) {
            $amount = $this->parseSalaryAmount($salary->getAmount());
            if ($amount !== null) {
                return $amount;
            }
        }
        return 0.0;
    }

    private function parseSalaryAmount(?string $rawAmount): ?float
    {
        if ($rawAmount === null) {
            return null;
        }
        $normalized = str_replace([',', ' '], '', trim($rawAmount));
        $normalized = preg_replace('/[^0-9\.\-]/', '', $normalized) ?? '';
        if ($normalized === '' || $normalized === '-' || !is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }

    private function formatSecondsToHoursLabel(float $seconds): string
    {
        $totalMinutes = (int) round($seconds / 60);
        $hours = (int) floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;
        return str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT);
    }

    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            new ParamRule(self::PARAM_RUN, new Rule(Rules::POSITIVE)),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(CommonParams::PARAMETER_OFFSET, new Rule(Rules::INT_VAL), new Rule(Rules::ZERO_OR_POSITIVE))
            ),
            $this->getValidationDecorator()->notRequiredParamRule(
                new ParamRule(CommonParams::PARAMETER_LIMIT, new Rule(Rules::INT_VAL))
            )
        );
    }

    public function create(): EndpointResult
    {
        $this->throwNotImplementedException();
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        $this->throwNotImplementedException();
    }

    public function delete(): EndpointResult
    {
        $this->throwNotImplementedException();
    }

    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        $this->throwNotImplementedException();
    }
}
