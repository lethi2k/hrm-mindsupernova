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

namespace OrangeHRM\Payroll\Service;

use DateTime;
use Exception;
use OrangeHRM\Admin\Service\OrganizationService;
use OrangeHRM\Core\Service\EmailQueueService;
use OrangeHRM\Core\Service\EmailService;
use OrangeHRM\Core\Traits\CacheTrait;
use OrangeHRM\Core\Traits\ORM\EntityManagerHelperTrait;
use OrangeHRM\Core\Traits\Service\DateTimeHelperTrait;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\PayrollConfig;
use OrangeHRM\Entity\PayrollEmailLog;
use OrangeHRM\Entity\PayrollPayslip;
use OrangeHRM\Entity\PayrollRun;
use OrangeHRM\Entity\EmployeeSalary;
use OrangeHRM\Payroll\Dao\PayrollDao;

class PayrollService
{
    use EntityManagerHelperTrait;
    use DateTimeHelperTrait;
    use CacheTrait;

    private ?PayrollDao $payrollDao = null;
    private ?PayrollPayslipFileService $payslipFileService = null;
    private ?OrganizationService $organizationService = null;
    private ?EmailQueueService $emailQueueService = null;

    public function getPayrollDao(): PayrollDao
    {
        if ($this->payrollDao === null) {
            $this->payrollDao = new PayrollDao();
        }
        return $this->payrollDao;
    }

    public function getPayslipFileService(): PayrollPayslipFileService
    {
        if ($this->payslipFileService === null) {
            $this->payslipFileService = new PayrollPayslipFileService();
        }
        return $this->payslipFileService;
    }

    public function getOrganizationService(): OrganizationService
    {
        if ($this->organizationService === null) {
            $this->organizationService = new OrganizationService();
        }
        return $this->organizationService;
    }

    public function getEmailQueueService(): EmailQueueService
    {
        if ($this->emailQueueService === null) {
            $this->emailQueueService = new EmailQueueService();
        }
        return $this->emailQueueService;
    }

    public function getOrCreateConfig(): PayrollConfig
    {
        $c = $this->getPayrollDao()->getConfig();
        if ($c === null) {
            $c = new PayrollConfig();
            $c->setId(1);
            $this->getPayrollDao()->saveConfig($c);
            $this->getEntityManager()->flush();
        }
        return $c;
    }

    public function createRun(string $yearMonth, int $userId): PayrollRun
    {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
            throw new Exception('Invalid year month');
        }
        if ($this->getPayrollDao()->getRunByYearMonth($yearMonth) !== null) {
            throw new Exception('Payroll run for this month already exists');
        }
        $now = $this->getDateTimeHelper()->getNow();
        $run = new PayrollRun();
        $run->setYearMonth($yearMonth);
        $run->setStatus(PayrollRun::STATUS_DRAFT);
        $run->setCreatedBy($userId);
        $run->setCreatedAt($now);
        $run->setUpdatedAt($now);
        $this->getPayrollDao()->saveRun($run);
        $this->upsertPayslipsForRun($run);
        $this->getEntityManager()->flush();
        return $run;
    }

    public function confirmReview(int $runId, int $userId, array $reviewRows = []): PayrollRun
    {
        $run = $this->getPayrollDao()->getRun($runId);
        if ($run === null) {
            throw new Exception('Not found');
        }
        if ($run->getStatus() !== PayrollRun::STATUS_DRAFT) {
            throw new Exception('Review confirmation is only allowed in draft state');
        }
        if ($this->getPayrollDao()->countPayslipsForRun($run->getId()) === 0) {
            $this->upsertPayslipsForRun($run);
        }
        $this->applyReviewRowOverrides($run, $reviewRows);
        $run->setReviewedBy($userId);
        $run->setReviewedAt($this->getDateTimeHelper()->getNow());
        $run->setUpdatedAt($this->getDateTimeHelper()->getNow());
        $this->getPayrollDao()->saveRun($run);
        $this->getEntityManager()->flush();
        return $run;
    }

    private function sumNetForEmployee(int $empNumber): string
    {
        $list = $this->getPayrollDao()->getEmployeeSalaries($empNumber);
        $sum = 0.0;
        /** @var EmployeeSalary $s */
        foreach ($list as $s) {
            $raw = $s->getAmount();
            if ($raw === null) {
                continue;
            }
            if (is_numeric($raw)) {
                $sum += (float) $raw;
            }
        }
        if ($sum <= 0 && count($list) > 0) {
            return (string) ($list[0]->getAmount() ?? '0');
        }
        return number_format($sum, 2, '.', '');
    }

    private function upsertPayslipsForRun(PayrollRun $run): void
    {
        $empNumbers = $this->getPayrollDao()->getActiveEmployeeNumbers();
        foreach ($empNumbers as $emp) {
            $payslip = $this->getPayrollDao()->getPayslipByRunAndEmp($run->getId(), $emp);
            if ($payslip === null) {
                $payslip = new PayrollPayslip();
                $payslip->setPayrollRun($run);
                $payslip->setEmpNumber($emp);
            }
            $keepConfirmedAmount = $run->isReviewConfirmed()
                && $payslip->getNetSalary() !== null
                && $payslip->getNetSalary() !== '';
            if (!$keepConfirmedAmount) {
                $payslip->setNetSalary($this->sumNetForEmployee($emp));
            }
            $payslip->setFileFormat(PayrollPayslip::FORMAT_XLSX);
            $this->getPayrollDao()->savePayslip($payslip);
        }
    }

    private function applyReviewRowOverrides(PayrollRun $run, array $reviewRows): void
    {
        if ($reviewRows === []) {
            return;
        }
        foreach ($reviewRows as $row) {
            $emp = (int) ($row['empNumber'] ?? 0);
            if ($emp <= 0) {
                continue;
            }
            $payslip = $this->getPayrollDao()->getPayslipByRunAndEmp($run->getId(), $emp);
            if ($payslip === null) {
                $payslip = new PayrollPayslip();
                $payslip->setPayrollRun($run);
                $payslip->setEmpNumber($emp);
                $payslip->setFileFormat(PayrollPayslip::FORMAT_XLSX);
            }
            $allowance = isset($row['allowance']) ? (float) $row['allowance'] : 0.0;
            if ($allowance < 0) {
                $allowance = 0.0;
            }
            $actualSalary = isset($row['actualSalary']) ? (float) $row['actualSalary'] : null;
            $totalSalary = isset($row['totalSalary']) ? (float) $row['totalSalary'] : null;
            $netSalary = $totalSalary !== null
                ? $totalSalary
                : (($actualSalary !== null ? $actualSalary : 0.0) + $allowance);
            $formattedAllowance = number_format($allowance, 2, '.', '');
            $payslip->setAllowance($formattedAllowance);
            $payslip->setNetSalary(number_format($netSalary, 2, '.', ''));
            $this->getPayrollDao()->savePayslip($payslip);
            // Use direct SQL update as well, to ensure allowance is persisted
            // even if runtime metadata cache is stale.
            $this->getPayrollDao()->updatePayslipAllowanceByRunAndEmp(
                $run->getId(),
                $emp,
                $formattedAllowance
            );
        }
    }

    /**
     * Queue mail per employee (review confirmed only)
     */
    public function sendPayslips(int $runId, string $fileFormat, int $userId): PayrollRun
    {
        if (!in_array($fileFormat, [PayrollPayslip::FORMAT_XLSX, PayrollPayslip::FORMAT_PDF], true)) {
            throw new Exception('Invalid file format');
        }
        $run = $this->getPayrollDao()->getRun($runId);
        if ($run === null) {
            throw new Exception('Not found');
        }
        if (!$run->isReviewConfirmed()) {
            throw new Exception('Please review and confirm payroll before send');
        }
        $config = $this->getOrCreateConfig();
        $ymLabel = $this->formatYearMonth($run->getYearMonth());
        $company = $this->getOrganizationService()->getOrganizationGeneralInformation();
        $companyName = $company?->getName() ?? 'Company';
        $subjectTpl = $config->getDefaultSubject();
        $bodyTpl = $config->getDefaultBody();
        $run->setStatus(PayrollRun::STATUS_SENDING);
        $run->setUpdatedAt($this->getDateTimeHelper()->getNow());
        $this->getPayrollDao()->saveRun($run);
        $this->getEntityManager()->flush();

        $payslips = $this->getPayrollDao()->getPayslipsForRun($run->getId(), 0, 20000);
        foreach ($payslips as $payslip) {
            $payslip->setFileFormat($fileFormat);
            $emp = $this->getPayrollDao()->getEmployeeByNumber($payslip->getEmpNumber());
            if ($emp === null) {
                continue;
            }
            $key = 'send_v1_r' . $run->getId() . '_e' . $payslip->getEmpNumber();
            if ($this->getExistingLogByIdempotency($payslip, $key) !== null) {
                continue;
            }
            $toEmail = $emp->getWorkEmail() ?: $emp->getOtherEmail();
            if ($toEmail === null || $toEmail === '') {
                $log = $this->makeLog($payslip, $key, PayrollEmailLog::STATUS_SKIPPED, $userId);
                $log->setLastError('No email on record');
                $this->getPayrollDao()->saveEmailLog($log);
                continue;
            }
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                $log = $this->makeLog($payslip, $key, PayrollEmailLog::STATUS_SKIPPED, $userId);
                $log->setRecipientEmail($toEmail);
                $log->setLastError('Invalid email');
                $this->getPayrollDao()->saveEmailLog($log);
                continue;
            }

            $extraData = $this->buildPayslipExtraData($payslip, $emp);
            $out = $this->getPayslipFileService()->generatePayslipFile($payslip, $emp, $ymLabel, $extraData);
            $payslip->setFilePath($out['path']);
            $payslip->setFileChecksum($out['checksum']);
            $this->getPayrollDao()->savePayslip($payslip);
            $empName = $this->formatEmployeeName($emp);
            $tokens = [
                'companyName' => $companyName,
                'employeeName' => $empName,
                'yearMonth' => $ymLabel,
                'netSalary' => (string) $payslip->getNetSalary(),
            ];
            $subject = $this->interpolate($subjectTpl, $tokens);
            $body = nl2br($this->interpolate($bodyTpl, $tokens));
            $fileName = $this->safeFilename($empName) . ($fileFormat === PayrollPayslip::FORMAT_PDF ? '.pdf' : '.xlsx');
            $log = $this->makeLog($payslip, $key, PayrollEmailLog::STATUS_PENDING, $userId);
            $log->setRecipientEmail($toEmail);
            $log->setAttemptCount(1);
            $this->getPayrollDao()->saveEmailLog($log);
            $this->getEntityManager()->flush();

            $mail = $this->getEmailQueueService()->addToQueue(
                $subject,
                $body,
                [$toEmail],
                \OrangeHRM\Entity\Mail::CONTENT_TYPE_TEXT_HTML,
                [],
                [],
                [['path' => $out['path'], 'name' => $fileName]]
            );
            $log->setMailQueueId($mail->getId());
            $this->getPayrollDao()->saveEmailLog($log);
            $this->getEntityManager()->flush();
        }
        $this->touchMailCache();
        $this->maybeMarkRunComplete($run->getId());
        return $this->getPayrollDao()->getRun($runId);
    }

    public function touchMailCache(): void
    {
        $item = $this->getCache()->getItem('core.send_email');
        if (!$item->isHit()) {
            $item->expiresAfter(600);
        }
        $item->set(true);
        $this->getCache()->save($item);
    }

    public function maybeMarkRunComplete(int $runId): void
    {
        $run = $this->getPayrollDao()->getRun($runId);
        if ($run === null) {
            return;
        }
        if ($run->getStatus() !== PayrollRun::STATUS_SENDING) {
            return;
        }
        $logs = $this->getPayrollDao()->getEmailLogsForRun($runId, 0, 1000000);
        if ($logs === []) {
            $run->setStatus(PayrollRun::STATUS_SENT);
            $run->setUpdatedAt($this->getDateTimeHelper()->getNow());
            $this->getPayrollDao()->saveRun($run);
            $this->getEntityManager()->flush();
            return;
        }
        $config = $this->getOrCreateConfig();
        foreach ($logs as $l) {
            if ($l->getStatus() === PayrollEmailLog::STATUS_PENDING) {
                return;
            }
            if ($l->getStatus() === PayrollEmailLog::STATUS_FAILED
                && $l->getAttemptCount() < $config->getMaxRetries()) {
                return;
            }
        }
        $run->setStatus(PayrollRun::STATUS_SENT);
        $run->setUpdatedAt($this->getDateTimeHelper()->getNow());
        $this->getPayrollDao()->saveRun($run);
        $this->getEntityManager()->flush();
    }

    public function syncMailQueueToLogs(): int
    {
        $q = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('l')
            ->from(PayrollEmailLog::class, 'l')
            ->where('l.mailQueueId IS NOT NULL')
            ->andWhere('l.status = :st')
            ->setParameter('st', PayrollEmailLog::STATUS_PENDING);
        /** @var PayrollEmailLog[] $logs */
        $logs = $q->getQuery()->getResult();
        $n = 0;
        $affectedRunIds = [];
        foreach ($logs as $log) {
            $mail = $this->getPayrollDao()->getMail((int) $log->getMailQueueId());
            if ($mail === null) {
                continue;
            }
            $mst = (string) $mail->getStatus();
            if ($mst === \OrangeHRM\Entity\Mail::STATUS_SENT) {
                $log->setStatus(PayrollEmailLog::STATUS_SENT);
                $log->setSentAt($this->getDateTimeHelper()->getNow());
                $affectedRunIds[] = $log->getPayslip()->getPayrollRun()->getId();
                $n++;
            } elseif ($mst === \OrangeHRM\Entity\Mail::STATUS_FAILED) {
                $c = $this->getOrCreateConfig();
                if ($log->getAttemptCount() < $c->getMaxRetries()) {
                    $log->setIdempotencyKey(
                        'send_retry' . (string) (new DateTime())->getTimestamp() . 'e' . $log->getPayslip()->getEmpNumber()
                    );
                    $this->requeuePayslipEmail($log, $c);
                } else {
                    $log->setStatus(PayrollEmailLog::STATUS_FAILED);
                    $log->setLastError('Email transport failed (max retries)');
                }
                $affectedRunIds[] = $log->getPayslip()->getPayrollRun()->getId();
            } else {
                continue;
            }
            $this->getPayrollDao()->saveEmailLog($log);
        }
        if ($logs !== []) {
            $this->getEntityManager()->flush();
        }
        foreach (array_values(array_unique($affectedRunIds)) as $rid) {
            $this->maybeMarkRunComplete($rid);
        }
        return $n;
    }

    public function requeuePayslipEmail(PayrollEmailLog $oldLog, PayrollConfig $config): void
    {
        $payslip = $oldLog->getPayslip();
        $run = $payslip->getPayrollRun();
        $emp = $this->getPayrollDao()->getEmployeeByNumber($payslip->getEmpNumber());
        if ($emp === null) {
            return;
        }
        $to = $oldLog->getRecipientEmail();
        if ($to === null) {
            return;
        }
        $ymLabel = $this->formatYearMonth($run->getYearMonth());
        $company = $this->getOrganizationService()->getOrganizationGeneralInformation();
        $companyName = $company?->getName() ?? 'Company';
        $subject = $this->interpolate($config->getDefaultSubject(), [
            'companyName' => $companyName,
            'employeeName' => $this->formatEmployeeName($emp),
            'yearMonth' => $ymLabel,
            'netSalary' => (string) $payslip->getNetSalary(),
        ]);
        $body = nl2br($this->interpolate($config->getDefaultBody(), [
            'companyName' => $companyName,
            'employeeName' => $this->formatEmployeeName($emp),
            'yearMonth' => $ymLabel,
            'netSalary' => (string) $payslip->getNetSalary(),
        ]));
        $extraData = $this->buildPayslipExtraData($payslip, $emp);
        $out = $this->getPayslipFileService()->generatePayslipFile($payslip, $emp, $ymLabel, $extraData);
        $ext = $payslip->getFileFormat() === PayrollPayslip::FORMAT_PDF ? '.pdf' : '.xlsx';
        $file = $this->safeFilename($this->formatEmployeeName($emp)) . $ext;
        $oldLog->setMailQueueId(null);
        $oldLog->setStatus(PayrollEmailLog::STATUS_PENDING);
        $oldLog->setAttemptCount($oldLog->getAttemptCount() + 1);
        $m = $this->getEmailQueueService()->addToQueue(
            $subject,
            $body,
            [$to],
            \OrangeHRM\Entity\Mail::CONTENT_TYPE_TEXT_HTML,
            [],
            [],
            [['path' => $out['path'], 'name' => $file]]
        );
        $oldLog->setMailQueueId($m->getId());
        $this->getPayrollDao()->saveEmailLog($oldLog);
        $this->getEntityManager()->flush();
        $this->touchMailCache();
    }

    private function makeLog(
        PayrollPayslip $payslip,
        string $idempotency,
        string $status,
        int $triggeredBy
    ): PayrollEmailLog {
        $l = new PayrollEmailLog();
        $l->setPayslip($payslip);
        $l->setIdempotencyKey($idempotency);
        $l->setStatus($status);
        $l->setTriggeredBy($triggeredBy);
        return $l;
    }

    private function getExistingLogByIdempotency(PayrollPayslip $payslip, string $key): ?PayrollEmailLog
    {
        $q = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('l')
            ->from(PayrollEmailLog::class, 'l')
            ->where('l.payslip = :p')
            ->andWhere('l.idempotencyKey = :k')
            ->setParameter('p', $payslip)
            ->setParameter('k', $key)
            ->setMaxResults(1);
        $r = $q->getQuery()->getOneOrNullResult();
        return $r instanceof PayrollEmailLog ? $r : null;
    }

    private function interpolate(string $text, array $t): string
    {
        foreach ($t as $k => $v) {
            $text = str_replace('{{' . $k . '}}', (string) $v, $text);
        }
        return $text;
    }

    private function formatYearMonth(string $ym): string
    {
        $p = explode('-', $ym);
        if (count($p) !== 2) {
            return $ym;
        }
        return $p[1] . '/' . $p[0];
    }

    private function formatEmployeeName(Employee $e): string
    {
        $parts = array_filter([$e->getLastName(), $e->getMiddleName(), $e->getFirstName()]);
        return trim(implode(' ', $parts));
    }

    private function safeFilename(string $name): string
    {
        $s = preg_replace('/[^A-Za-z0-9\p{L}_\-\.]+/u', '-', $name) ?: 'employee';
        return trim($s, '-');
    }

    private function buildPayslipExtraData(PayrollPayslip $payslip, Employee $emp): array
    {
        $baseSalary = $this->resolveBaseSalary($emp->getEmpNumber());
        $standardWorkingDays = 22;
        $run = $payslip->getPayrollRun();
        $summary = $this->getPayrollDao()->getEmployeeTimesheetSummaryByMonth(
            $emp->getEmpNumber(),
            $run->getYearMonth()
        );
        $actualWorkingDays = (float) ($summary['regularSeconds'] ?? 0) / (8 * 3600);
        $overtimeSeconds = (float) ($summary['overtimeSeconds'] ?? 0);
        $hourlyRate = ($standardWorkingDays > 0 && $baseSalary > 0)
            ? $baseSalary / ($standardWorkingDays * 8)
            : 0.0;
        $overtimePay = ($overtimeSeconds / 3600.0) * $hourlyRate;
        return [
            'baseSalary'          => $baseSalary,
            'standardWorkingDays' => $standardWorkingDays,
            'actualWorkingDays'   => $actualWorkingDays,
            'overtimePay'         => $overtimePay,
            'nationalId'          => $emp->getDrivingLicenseNo() ?? '',
            'jobTitle'            => $emp->getJobTitle()?->getJobTitleName() ?? '',
        ];
    }

    private function resolveBaseSalary(int $empNumber): float
    {
        $salaries = $this->getPayrollDao()->getEmployeeSalaries($empNumber);
        $parse = static function (?string $raw): ?float {
            if ($raw === null) {
                return null;
            }
            $normalized = preg_replace('/[^0-9.\-]/', '', str_replace([',', ' '], '', trim($raw))) ?? '';
            return ($normalized !== '' && is_numeric($normalized)) ? (float) $normalized : null;
        };
        foreach ($salaries as $salary) {
            $name = mb_strtolower((string) ($salary->getSalaryName() ?? ''), 'UTF-8');
            if (
                strpos($name, 'base') !== false || strpos($name, 'basic') !== false
                || strpos($name, 'luong co ban') !== false || strpos($name, 'lương cơ bản') !== false
            ) {
                $amount = $parse($salary->getAmount());
                if ($amount !== null) {
                    return $amount;
                }
            }
        }
        foreach ($salaries as $salary) {
            $amount = $parse($salary->getAmount());
            if ($amount !== null) {
                return $amount;
            }
        }
        return 0.0;
    }

    public function sendTestEmail(int $runId, string $fileFormat, string $testEmailAddr): string
    {
        if (!in_array($fileFormat, [PayrollPayslip::FORMAT_XLSX, PayrollPayslip::FORMAT_PDF], true)) {
            throw new Exception('Invalid file format');
        }
        if (!filter_var($testEmailAddr, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid test email address');
        }
        $run = $this->getPayrollDao()->getRun($runId);
        if ($run === null) {
            throw new Exception('Payroll run not found');
        }
        $config = $this->getOrCreateConfig();
        $ymLabel = $this->formatYearMonth($run->getYearMonth());
        $company = $this->getOrganizationService()->getOrganizationGeneralInformation();
        $companyName = $company?->getName() ?? 'Company';

        $payslips = $this->getPayrollDao()->getPayslipsForRun($run->getId(), 0, 1);
        if (empty($payslips)) {
            throw new Exception('No payslips found for this run. Please complete the review step first.');
        }
        $payslip = $payslips[0];
        $payslip->setFileFormat($fileFormat);
        $emp = $this->getPayrollDao()->getEmployeeByNumber($payslip->getEmpNumber());
        if ($emp === null) {
            throw new Exception('Employee not found');
        }
        $empName = $this->formatEmployeeName($emp);
        $tokens = [
            'companyName' => $companyName,
            'employeeName' => $empName,
            'yearMonth' => $ymLabel,
            'netSalary' => (string) $payslip->getNetSalary(),
        ];
        $subject = '[TEST] ' . $this->interpolate($config->getDefaultSubject(), $tokens);
        $body = nl2br($this->interpolate($config->getDefaultBody(), $tokens));
        $extraData = $this->buildPayslipExtraData($payslip, $emp);
        $out = $this->getPayslipFileService()->generatePayslipFile($payslip, $emp, $ymLabel, $extraData);
        $fileName = $this->safeFilename($empName) . ($fileFormat === PayrollPayslip::FORMAT_PDF ? '.pdf' : '.xlsx');
        $emailService = new EmailService();
        if (!$emailService->isConfigSet()) {
            throw new Exception('Email chưa được cấu hình. Vui lòng cấu hình SMTP tại Admin → Configuration → Email Configuration.');
        }
        $emailService->setMessageSubject($subject);
        $emailService->setMessageBody($body);
        $emailService->setMessageTo([$testEmailAddr]);
        $emailService->setMessageAttachments([['path' => $out['path'], 'name' => $fileName]]);
        $sent = $emailService->sendEmail();
        if (!$sent) {
            throw new Exception('Gửi email thất bại. Vui lòng kiểm tra cấu hình SMTP.');
        }
        return 'Email thử nghiệm đã được gửi thành công đến ' . $testEmailAddr;
    }

    public function scheduledAutoSendForPreviousMonthIfDue(): int
    {
        $c = $this->getOrCreateConfig();
        if (!$c->isScheduleEnabled()) {
            return 0;
        }
        $now = $this->getDateTimeHelper()->getNow();
        $d = (int) $now->format('d');
        $h = (int) $now->format('G');
        if ($d !== (int) $c->getScheduleDay() || $h < (int) $c->getScheduleHour()) {
            return 0;
        }
        $prev = (clone $now)->modify('first day of last month');
        $ym = $prev->format('Y-m');
        $run = $this->getPayrollDao()->getRunByYearMonth($ym);
        if ($run === null || !$run->isReviewConfirmed()) {
            return 0;
        }
        $this->sendPayslips($run->getId(), PayrollPayslip::FORMAT_XLSX, 0);
        return 1;
    }
}
