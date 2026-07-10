<?php

namespace App\Services\Reports;

use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseComplianceReport;
use App\Models\Device;
use App\Models\SeatAssignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * License 合规报告生成服务
 * 
 * 生成面向客户的合规审计报告，支持:
 * - Excel (.xlsx) 格式 — 完整表格带样式
 * - CSV 格式 — 纯数据
 * - 含产品清单、激活记录、设备绑定、合规状态
 */
class LicenseComplianceReportService
{
    protected string $storageDisk = 'local';

    /**
     * 生成合规报告
     */
    public function generate(LicenseComplianceReport $report): bool
    {
        $report->update(['status' => 'generating']);

        try {
            $filters = $report->filters ?? [];
            $customerId = $report->customer_id;
            $tenantId = $report->tenant_id;

            // 查询数据
            $licenses = $this->queryLicenseData($tenantId, $customerId, $filters);
            $summary = $this->buildSummary($licenses);

            // 根据格式生成文件
            $fileContent = match ($report->format) {
                'xlsx' => $this->generateExcel($licenses, $summary, $report),
                'csv'  => $this->generateCsv($licenses, $summary),
                'pdf'  => $this->generateExcel($licenses, $summary, $report), // PDF via Excel first
                default => $this->generateExcel($licenses, $summary, $report),
            };

            // 保存文件
            $fileName = $this->buildFileName($report);
            $filePath = "reports/compliance/{$report->id}/{$fileName}";
            Storage::disk($this->storageDisk)->put($filePath, $fileContent);

            $report->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => strlen($fileContent),
                'summary_data' => $summary,
                'generated_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Compliance report generation failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $report->update([
                'status' => 'failed',
                'summary_data' => ['error' => $e->getMessage()],
            ]);

            return false;
        }
    }

    /**
     * 查询 License 数据
     */
    protected function queryLicenseData(int $tenantId, ?int $customerId, array $filters): array
    {
        $query = License::with(['product', 'activations', 'seatAssignments.user'])
            ->where('tenant_id', $tenantId);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        // 日期范围
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        if ($startDate) $query->where('created_at', '>=', $startDate);
        if ($endDate) $query->where('created_at', '<=', $endDate);

        // 状态筛选
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // 产品筛选
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        return $query->orderBy('created_at')->get()->toArray();
    }

    /**
     * 构建摘要数据
     */
    protected function buildSummary(array $licenses): array
    {
        $totalLicenses = count($licenses);
        $activeCount = 0;
        $expiredCount = 0;
        $totalActivations = 0;
        $compliantCount = 0;
        $overusedCount = 0;

        foreach ($licenses as $lic) {
            $activations = $lic['activations'] ?? [];
            $activeCount += ($lic['status'] === 'active') ? 1 : 0;
            $expiredCount += (isset($lic['expires_at']) && strtotime($lic['expires_at']) < time()) ? 1 : 0;
            $totalActivations += count($activations);

            $maxSeats = $lic['seats'] ?? $lic['max_devices'] ?? 1;
            $usedSeats = count($activations);

            if ($usedSeats <= $maxSeats) {
                $compliantCount++;
            } else {
                $overusedCount++;
            }
        }

        return [
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeCount,
            'expired_licenses' => $expiredCount,
            'total_activations' => $totalActivations,
            'compliant_licenses' => $compliantCount,
            'overused_licenses' => $overusedCount,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 生成 Excel 文件
     */
    protected function generateExcel(array $licenses, array $summary, LicenseComplianceReport $report): string
    {
        $spreadsheet = new Spreadsheet();

        // ─── Sheet 1: 摘要页 ───
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('合规摘要');

        $sheet->setCellValue('A1', 'License 合规审计报告');
        $sheet->mergeCells('A1:F1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A2', '报告类型: ' . $report->type_label);
        $sheet->setCellValue('A3', '生成时间: ' . now()->format('Y-m-d H:i:s'));
        $sheet->setCellValue('A4', '报告周期: ' . ($report->report_period_start?->format('Y-m-d') ?? '全部') . ' 至 ' . ($report->report_period_end?->format('Y-m-d') ?? '至今'));

        // 摘要数据
        $sheet->setCellValue('A6', '指标');
        $sheet->setCellValue('B6', '数值');
        $sheet->getStyle('A6:B6')->getFont()->setBold(true);
        $sheet->getStyle('A6:B6')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $sheet->getStyle('A6:B6')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 7;
        foreach ([
            'License 总数' => $summary['total_licenses'],
            '活跃 License' => $summary['active_licenses'],
            '已过期 License' => $summary['expired_licenses'],
            '激活记录总数' => $summary['total_activations'],
            '合规 License' => $summary['compliant_licenses'],
            '超额使用 License' => $summary['overused_licenses'],
        ] as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $row++;
        }

        // ─── Sheet 2: License 清单 ───
        $ws2 = $spreadsheet->createSheet();
        $ws2->setTitle('License 清单');

        $headers = ['License Key', '产品名称', '客户', '状态', '创建时间', '过期时间', '最大席位', '已用席位', '合规状态'];
        $col = 'A';
        foreach ($headers as $header) {
            $ws2->setCellValue("{$col}1", $header);
            $ws2->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        $ws2->getStyle('A1:I1')->getFont()->setBold(true);
        $ws2->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $ws2->getStyle('A1:I1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($licenses as $lic) {
            $activations = $lic['activations'] ?? [];
            $maxSeats = $lic['seats'] ?? $lic['max_devices'] ?? 1;
            $usedSeats = count($activations);
            $compliant = $usedSeats <= $maxSeats ? '合规' : '⚠️ 超额';

            $ws2->setCellValue("A{$row}", $lic['license_key'] ?? $lic['key'] ?? '');
            $ws2->setCellValue("B{$row}", $lic['product']['name'] ?? $lic['product_name'] ?? '');
            $ws2->setCellValue("C{$row}", $lic['customer_name'] ?? $lic['customer_id'] ?? '');
            $ws2->setCellValue("D{$row}", $lic['status'] ?? '');
            $ws2->setCellValue("E{$row}", $lic['created_at'] ?? '');
            $ws2->setCellValue("F{$row}", $lic['expires_at'] ?? '永久');
            $ws2->setCellValue("G{$row}", $maxSeats);
            $ws2->setCellValue("H{$row}", $usedSeats);
            $ws2->setCellValue("I{$row}", $compliant);

            // 颜色标记超额
            if (!$compliant) {
                $ws2->getStyle("A{$row}:I{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
            }

            $row++;
        }

        // ─── Sheet 3: 激活记录 ───
        $ws3 = $spreadsheet->createSheet();
        $ws3->setTitle('激活记录');

        $headers3 = ['License Key', '设备名称', '设备ID', 'IP 地址', '激活时间', '最后验证', '状态'];
        $col = 'A';
        foreach ($headers3 as $header) {
            $ws3->setCellValue("{$col}1", $header);
            $ws3->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        $ws3->getStyle('A1:G1')->getFont()->setBold(true);
        $ws3->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
        $ws3->getStyle('A1:G1')->getFont()->getColor()->setRGB('FFFFFF');

        $row = 2;
        foreach ($licenses as $lic) {
            $licenseKey = $lic['license_key'] ?? $lic['key'] ?? '';
            foreach ($lic['activations'] ?? [] as $act) {
                $ws3->setCellValue("A{$row}", $licenseKey);
                $ws3->setCellValue("B{$row}", $act['device_name'] ?? '');
                $ws3->setCellValue("C{$row}", $act['device_id'] ?? $act['fingerprint'] ?? '');
                $ws3->setCellValue("D{$row}", $act['ip_address'] ?? '');
                $ws3->setCellValue("E{$row}", $act['created_at'] ?? '');
                $ws3->setCellValue("F{$row}", $act['last_verified_at'] ?? '');
                $ws3->setCellValue("G{$row}", $act['status'] ?? 'active');
                $row++;
            }
        }

        // 写入文件
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }

    /**
     * 生成 CSV 文件
     */
    protected function generateCsv(array $licenses, array $summary): string
    {
        $output = fopen('php://temp', 'r+');

        // CSV header
        fputcsv($output, ['License Key', '产品', '客户', '状态', '创建时间', '过期时间', '最大席位', '已用席位', '合规状态']);

        foreach ($licenses as $lic) {
            $activations = $lic['activations'] ?? [];
            $maxSeats = $lic['seats'] ?? $lic['max_devices'] ?? 1;
            $usedSeats = count($activations);
            $compliant = $usedSeats <= $maxSeats ? '合规' : '超额';

            fputcsv($output, [
                $lic['license_key'] ?? $lic['key'] ?? '',
                $lic['product']['name'] ?? '',
                $lic['customer_name'] ?? '',
                $lic['status'] ?? '',
                $lic['created_at'] ?? '',
                $lic['expires_at'] ?? '永久',
                $maxSeats,
                $usedSeats,
                $compliant,
            ]);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }

    /**
     * 构建文件名
     */
    protected function buildFileName(LicenseComplianceReport $report): string
    {
        $date = now()->format('Ymd_His');
        $type = $report->type;
        $ext = $report->format === 'pdf' ? 'xlsx' : $report->format;
        return "compliance_report_{$type}_{$date}.{$ext}";
    }

    /**
     * 获取下载链接有效期
     */
    public function getDownloadUrl(LicenseComplianceReport $report): ?string
    {
        if (!$report->isReady()) return null;

        return route('api.license.compliance.download', ['report' => $report->id], true);
    }
}
