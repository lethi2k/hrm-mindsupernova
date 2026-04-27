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
 * @ORM\Table(name="ohrm_payroll_run")
 * @ORM\Entity
 */
class PayrollRun
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';

    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * `year_month` can conflict on newer MySQL versions; use a safe name.
     * @ORM\Column(name="payroll_month", type="string", length=7)
     */
    private string $yearMonth;

    /**
     * `status` is a reserved keyword in MySQL 9; keep a safe column name.
     * @ORM\Column(name="run_status", type="string", length=20)
     */
    private string $status = self::STATUS_DRAFT;

    /**
     * @ORM\Column(name="created_by", type="integer")
     */
    private int $createdBy;

    /**
     * @ORM\Column(name="approved_by", type="integer", nullable=true)
     */
    private ?int $approvedBy = null;

    /**
     * @ORM\Column(name="approved_at", type="datetime", nullable=true)
     */
    private ?DateTime $approvedAt = null;

    /**
     * @ORM\Column(name="created_at", type="datetime")
     */
    private DateTime $createdAt;

    /**
     * @ORM\Column(name="updated_at", type="datetime")
     */
    private DateTime $updatedAt;

    /**
     * @ORM\Column(name="reviewed_by", type="integer", nullable=true)
     */
    private ?int $reviewedBy = null;

    /**
     * @ORM\Column(name="reviewed_at", type="datetime", nullable=true)
     */
    private ?DateTime $reviewedAt = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getYearMonth(): string
    {
        return $this->yearMonth;
    }

    public function setYearMonth(string $yearMonth): void
    {
        $this->yearMonth = $yearMonth;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getCreatedBy(): int
    {
        return $this->createdBy;
    }

    public function setCreatedBy(int $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getApprovedBy(): ?int
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?int $approvedBy): void
    {
        $this->approvedBy = $approvedBy;
    }

    public function getApprovedAt(): ?DateTime
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?DateTime $approvedAt): void
    {
        $this->approvedAt = $approvedAt;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getReviewedBy(): ?int
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?int $reviewedBy): void
    {
        $this->reviewedBy = $reviewedBy;
    }

    public function getReviewedAt(): ?DateTime
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?DateTime $reviewedAt): void
    {
        $this->reviewedAt = $reviewedAt;
    }

    public function isReviewConfirmed(): bool
    {
        return $this->reviewedAt !== null;
    }
}
