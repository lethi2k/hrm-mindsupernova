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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
     * @param array $extraData Keys: baseSalary, standardWorkingDays, actualWorkingDays, nationalId, jobTitle
     * @return array{path: string, checksum: string, format: string}
     */
    public function generatePayslipFile(
        PayrollPayslip $payslip,
        Employee $employee,
        string $yearMonthLabel,
        array $extraData = []
    ): array {
        if ($payslip->getFileFormat() === PayrollPayslip::FORMAT_PDF) {
            return $this->buildPdf($payslip, $employee, $yearMonthLabel);
        }
        return $this->buildXlsx($payslip, $employee, $yearMonthLabel, $extraData);
    }

    /**
     * @return array{path: string, checksum: string, format: string}
     */
    private function buildXlsx(
        PayrollPayslip $payslip,
        Employee $employee,
        string $yearMonthLabel,
        array $extraData = []
    ): array {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // --- Header rows 2–6 (merged A:S) ---
        foreach (['A2:T2', 'A3:T3', 'A4:T4', 'A5:T5', 'A6:T6'] as $m) {
            $sheet->mergeCells($m);
        }
        $sheet->setCellValue('A2', 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM');
        $sheet->setCellValue('A3', 'Độc lập - Tự do - Hạnh phúc');
        $sheet->setCellValue('A4', '**********');
        $sheet->setCellValue('A5', 'BẢNG XÁC NHẬN CÔNG VÀ CHI PHÍ PHÁT TRIỂN PHẦN MỀM');
        $sheet->setCellValue('A6', 'Tháng ' . $yearMonthLabel);

        $centerBold = [
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        $sheet->getStyle('A2:T6')->applyFromArray($centerBold);
        foreach ([2, 3, 4, 5, 6] as $r) {
            $sheet->getRowDimension($r)->setRowHeight(15.75);
        }

        // --- Column header rows 9–10 ---
        // Columns A-T (20 cols): added I=LÀM THÊM GIỜ, shifted old I-S → J-T
        foreach (['A9:A10', 'B9:B10', 'C9:C10', 'D9:D10', 'E9:E10',
                  'F9:F10', 'G9:G10', 'H9:H10', 'I9:I10', 'J9:J10',
                  'K9:K10', 'L9:L10', 'M9:S9', 'T9:T10'] as $m) {
            $sheet->mergeCells($m);
        }

        $headers = [
            'A9' => 'ID',
            'B9' => 'HỌ VÀ TÊN',
            'C9' => 'CCCD',
            'D9' => 'CHỨC VỤ',
            'E9' => 'LƯƠNG CƠ BẢN',
            'F9' => 'NGÀY CÔNG CHUẨN',
            'G9' => 'NGÀY CÔNG THỰC TẾ',
            'H9' => 'LƯƠNG THỰC TẾ',
            'I9' => "LÀM THÊM GIỜ",
            'J9' => 'PHỤ CẤP(Hoa Hồng Dự án)',
            'K9' => 'TỔNG LƯƠNG',
            'L9' => 'MỨC ĐÓNG BHXH',
            'M9' => 'CÁC KHOẢN TRỪ',
            'T9' => 'THỰC NHẬN',
            'M10' => "BHXH\n(8%)",
            'N10' => "BHYT\n(1,5%)",
            'O10' => "BHTN\n(1%)",
            'P10' => 'GIẢM TRỪ GIA CẢNH',
            'Q10' => 'THU NHẬP TÍNH THUẾ',
            'R10' => 'THUẾ TNCN',
            'S10' => 'TỔNG KHẤU TRỪ',
        ];
        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        $thStyle = [
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '156082']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ];
        $sheet->getStyle('A9:T10')->applyFromArray($thStyle);
        $sheet->getRowDimension(9)->setRowHeight(28.5);
        $sheet->getRowDimension(10)->setRowHeight(31.5);

        // --- Data row 11 ---
        $baseSalary        = (float) ($extraData['baseSalary'] ?? 0);
        $standardWorkingDays = (int) ($extraData['standardWorkingDays'] ?? 22);
        $actualWorkingDays = (int) round((float) ($extraData['actualWorkingDays'] ?? $standardWorkingDays));
        $overtimePay       = round((float) ($extraData['overtimePay'] ?? 0));
        $nationalId        = (string) ($extraData['nationalId'] ?? '');
        $jobTitle          = (string) ($extraData['jobTitle'] ?? '');
        $allowance         = (float) ($payslip->getAllowance() ?? '0');
        $fullName = trim(implode(' ', array_filter([
            $employee->getLastName(),
            $employee->getMiddleName(),
            $employee->getFirstName(),
        ])));

        $sheet->setCellValue('A11', 1);
        $sheet->setCellValue('B11', $fullName);
        $sheet->setCellValue('C11', $nationalId);
        $sheet->setCellValue('D11', $jobTitle);
        $sheet->setCellValue('E11', $baseSalary > 0 ? $baseSalary : null);
        $sheet->setCellValue('F11', $standardWorkingDays);
        $sheet->setCellValue('G11', $actualWorkingDays);
        $sheet->setCellValue('H11', '=ROUND((E11/F11)*G11,0)');
        $sheet->setCellValue('I11', $overtimePay > 0 ? $overtimePay : null);  // LÀM THÊM GIỜ
        $sheet->setCellValue('J11', $allowance > 0 ? $allowance : null);      // PHỤ CẤP
        $sheet->setCellValue('K11', '=H11+I11+J11');                          // TỔNG LƯƠNG
        // L11: MỨC ĐÓNG BHXH — empty (user fills)
        // M11-P11: BHXH/BHYT/BHTN/GIẢM TRỪ — empty (user fills)
        // Q11: THU NHẬP TÍNH THUẾ — empty
        $sheet->setCellValue('R11', '=K11*10%');                              // THUẾ TNCN
        $sheet->setCellValue('S11', '=SUM(M11:O11)+R11');                    // TỔNG KHẤU TRỪ
        $sheet->setCellValue('T11', '=K11-S11');                              // THỰC NHẬN

        $dataStyle = [
            'font' => ['size' => 11],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ];
        $sheet->getStyle('A11:T11')->applyFromArray($dataStyle);
        $sheet->getStyle('A11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['C', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'] as $col) {
            $sheet->getStyle("{$col}11")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $sheet->getRowDimension(11)->setRowHeight(44.25);

        $numFmt = '#,##0';
        foreach (['E', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'] as $col) {
            $sheet->getStyle("{$col}11")->getNumberFormat()->setFormatCode($numFmt);
        }

        // Column widths
        $widths = [
            'A' => 5.88,  'B' => 26.63, 'C' => 16.75, 'D' => 13.25,
            'E' => 15.13, 'F' => 11.38, 'G' => 11.25, 'H' => 14.0,
            'I' => 13.0,  'J' => 13.0,  'K' => 16.38, 'L' => 10.25,
            'M' => 9.38,  'N' => 9.13,  'O' => 8.75,  'P' => 11.13,
            'Q' => 11.25, 'R' => 11.25, 'S' => 13.38, 'T' => 17.25,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        // --- Save ---
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
            'path'     => $path,
            'checksum' => $checksum,
            'format'   => PayrollPayslip::FORMAT_XLSX,
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
            'path'     => $path,
            'checksum' => $checksum,
            'format'   => PayrollPayslip::FORMAT_PDF,
        ];
    }
}
