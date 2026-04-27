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

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="ohrm_payroll_payslip")
 * @ORM\Entity
 */
class PayrollPayslip
{
    public const FORMAT_XLSX = 'xlsx';
    public const FORMAT_PDF = 'pdf';

    /**
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private int $id;

    /**
     * @ORM\ManyToOne(targetEntity="OrangeHRM\Entity\PayrollRun")
     * @ORM\JoinColumn(name="payroll_run_id", referencedColumnName="id", onDelete="CASCADE")
     */
    private PayrollRun $payrollRun;

    /**
     * @ORM\Column(name="emp_number", type="integer")
     */
    private int $empNumber;

    /**
     * @ORM\Column(name="net_salary", type="string", length=100, nullable=true)
     */
    private ?string $netSalary = null;

    /**
     * @ORM\Column(name="allowance", type="string", length=100, nullable=true)
     */
    private ?string $allowance = null;

    /**
     * @ORM\Column(name="file_path", type="string", length=1000, nullable=true)
     */
    private ?string $filePath = null;

    /**
     * @ORM\Column(name="file_format", type="string", length=10)
     */
    private string $fileFormat = self::FORMAT_XLSX;

    /**
     * @ORM\Column(name="file_checksum", type="string", length=64, nullable=true)
     */
    private ?string $fileChecksum = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getPayrollRun(): PayrollRun
    {
        return $this->payrollRun;
    }

    public function setPayrollRun(PayrollRun $payrollRun): void
    {
        $this->payrollRun = $payrollRun;
    }

    public function getEmpNumber(): int
    {
        return $this->empNumber;
    }

    public function setEmpNumber(int $empNumber): void
    {
        $this->empNumber = $empNumber;
    }

    public function getNetSalary(): ?string
    {
        return $this->netSalary;
    }

    public function setNetSalary(?string $netSalary): void
    {
        $this->netSalary = $netSalary;
    }

    public function getAllowance(): ?string
    {
        return $this->allowance;
    }

    public function setAllowance(?string $allowance): void
    {
        $this->allowance = $allowance;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getFileFormat(): string
    {
        return $this->fileFormat;
    }

    public function setFileFormat(string $fileFormat): void
    {
        $this->fileFormat = $fileFormat;
    }

    public function getFileChecksum(): ?string
    {
        return $this->fileChecksum;
    }

    public function setFileChecksum(?string $fileChecksum): void
    {
        $this->fileChecksum = $fileChecksum;
    }
}
