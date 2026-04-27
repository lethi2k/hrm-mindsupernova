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

use Dompdf\Dompdf;
use Dompdf\Options;
use OrangeHRM\Config\Config;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\PayrollPayslip;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PayrollPayslipFileService
{
    private function getBaseDir(): string
    {
        $dir = Config::get(Config::SRC_DIR) . '/cache/payroll_payslips';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir;
    }

    /**
     * @return array{path: string, checksum: string, format: string}
     */
    public function generatePayslipFile(
        PayrollPayslip $payslip,
        Employee $employee,
        string $yearMonthLabel
    ): array {
        if ($payslip->getFileFormat() === PayrollPayslip::FORMAT_PDF) {
            return $this->buildPdf($payslip, $employee, $yearMonthLabel);
        }
        return $this->buildXlsx($payslip, $employee, $yearMonthLabel);
    }

    /**
     * @return array{path: string, checksum: string, format: string}
     */
    private function buildXlsx(
        PayrollPayslip $payslip,
        Employee $employee,
        string $yearMonthLabel
    ): array {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Payslip');
        $sheet->setCellValue('A2', 'Month');
        $sheet->setCellValue('B2', $yearMonthLabel);
        $sheet->setCellValue('A3', 'Employee');
        $name = $employee->getLastName() . ' ' . $employee->getFirstName();
        $sheet->setCellValue('B3', $name);
        $sheet->setCellValue('A4', 'Net salary');
        $sheet->setCellValue('B4', (string) ($payslip->getNetSalary() ?? ''));

        $run = $payslip->getPayrollRun();
        $rel = sprintf(
            'run-%d/emp-%d/%s.payslip.xlsx',
            $run->getId(),
            $payslip->getEmpNumber(),
            preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) $payslip->getEmpNumber())
        );
        $path = $this->getBaseDir() . '/' . $rel;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $checksum = hash_file('sha256', $path);

        return [
            'path' => $path,
            'checksum' => $checksum,
            'format' => PayrollPayslip::FORMAT_XLSX,
        ];
    }

    /**
     * @return array{path: string, checksum: string, format: string}
     */
    private function buildPdf(
        PayrollPayslip $payslip,
        Employee $employee,
        string $yearMonthLabel
    ): array {
        $name = trim($employee->getFirstName() . ' ' . $employee->getLastName());
        $html = '<html><body><h1>Payslip</h1><p>Month: ' . htmlspecialchars(
            $yearMonthLabel
        ) . '</p><p>Employee: ' . htmlspecialchars(
            $name
        ) . '</p><p>Net: ' . htmlspecialchars(
            (string) ($payslip->getNetSalary() ?? '')
        ) . '</p></body></html>';

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $run = $payslip->getPayrollRun();
        $rel = sprintf('run-%d/emp-%d/payslip.pdf', $run->getId(), $payslip->getEmpNumber());
        $path = $this->getBaseDir() . '/' . $rel;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        file_put_contents($path, $dompdf->output());
        $checksum = hash_file('sha256', $path);

        return [
            'path' => $path,
            'checksum' => $checksum,
            'format' => PayrollPayslip::FORMAT_PDF,
        ];
    }
}
