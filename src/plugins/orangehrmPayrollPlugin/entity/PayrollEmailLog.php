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

namespace OrangeHRM\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="ohrm_payroll_email_log")
 * @ORM\Entity
 */
class PayrollEmailLog
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\ManyToOne(targetEntity="OrangeHRM\Entity\PayrollPayslip")
     * @ORM\JoinColumn(name="payslip_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private PayrollPayslip $payslip;

    /**
     * @ORM\Column(name="recipient_email", type="string", length=200, nullable=true)
     */
    private ?string $recipientEmail = null;

    /**
     * `status` is a reserved keyword in MySQL 9; keep a safe column name.
     * @ORM\Column(name="email_status", type="string", length=20)
     */
    private string $status = self::STATUS_PENDING;

    /**
     * @ORM\Column(name="attempt_count", type="integer")
     */
    private int $attemptCount = 0;

    /**
     * @ORM\Column(name="sent_at", type="datetime", nullable=true)
     */
    private ?DateTime $sentAt = null;

    /**
     * @ORM\Column(name="last_error", type="text", nullable=true)
     */
    private ?string $lastError = null;

    /**
     * @ORM\Column(name="idempotency_key", type="string", length=200)
     */
    private string $idempotencyKey;

    /**
     * @ORM\Column(name="mail_queue_id", type="integer", nullable=true)
     */
    private ?int $mailQueueId = null;

    /**
     * @ORM\Column(name="triggered_by", type="integer", nullable=true)
     */
    private ?int $triggeredBy = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getPayslip(): PayrollPayslip
    {
        return $this->payslip;
    }

    public function setPayslip(PayrollPayslip $payslip): void
    {
        $this->payslip = $payslip;
    }

    public function getRecipientEmail(): ?string
    {
        return $this->recipientEmail;
    }

    public function setRecipientEmail(?string $recipientEmail): void
    {
        $this->recipientEmail = $recipientEmail;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    public function setAttemptCount(int $attemptCount): void
    {
        $this->attemptCount = $attemptCount;
    }

    public function getSentAt(): ?DateTime
    {
        return $this->sentAt;
    }

    public function setSentAt(?DateTime $sentAt): void
    {
        $this->sentAt = $sentAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): void
    {
        $this->lastError = $lastError;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function getMailQueueId(): ?int
    {
        return $this->mailQueueId;
    }

    public function setMailQueueId(?int $mailQueueId): void
    {
        $this->mailQueueId = $mailQueueId;
    }

    public function getTriggeredBy(): ?int
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?int $triggeredBy): void
    {
        $this->triggeredBy = $triggeredBy;
    }
}
