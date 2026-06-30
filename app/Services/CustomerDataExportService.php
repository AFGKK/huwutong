<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDataExport;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerDataExportService
{
    protected string $disk = 'local';

    /**
     * 获取客户可导出的数据类型
     */
    public function getAvailableTypes(Customer $customer): array
    {
        $types = [];
        foreach (CustomerDataExport::TYPES as $type) {
            $count = $this->getRecordCount($customer, $type);
            $types[] = [
                'type' => $type,
                'label' => CustomerDataExport::typeLabel($type),
                'record_count' => $count,
                'can_export' => $count > 0,
            ];
        }
        return $types;
    }

    /**
     * 创建导出请求
     */
    public function createExport(Customer $customer, string $type, string $format = 'csv', array $filters = []): CustomerDataExport
    {
        $export = CustomerDataExport::create([
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'type' => $type,
            'format' => $format,
            'filters' => $filters,
            'status' => 'pending',
        ]);

        $this->processExport($export);

        return $export->fresh();
    }

    /**
     * 处理导出（同步执行，简单场景用队列可后续扩展）
     */
    public function processExport(CustomerDataExport $export): void
    {
        $export->markAsProcessing();

        try {
            $customer = $export->customer;
            $type = $export->type;

            $data = $this->fetchData($customer, $type, $export->filters ?? []);
            $fileName = $this->generateFileName($customer, $type, $export->format);
            $filePath = $this->generateCsv($data, $fileName, $type);

            $fullPath = storage_path('app/' . $filePath);
            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            $export->markAsCompleted($filePath, $fileName, $fileSize, count($data));
        } catch (\Exception $e) {
            $export->markAsFailed($e->getMessage());
        }
    }

    /**
     * 获取导出历史
     */
    public function getExportHistory(Customer $customer): \Illuminate\Database\Eloquent\Collection
    {
        return CustomerDataExport::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 下载文件
     */
    public function download(CustomerDataExport $export): ?array
    {
        if ($export->status !== 'completed' || $export->isExpired()) {
            return null;
        }

        $path = $export->file_path;
        if (!Storage::disk($this->disk)->exists($path)) {
            $export->markAsFailed('文件已丢失');
            return null;
        }

        return [
            'content' => Storage::disk($this->disk)->get($path),
            'name' => $export->file_name,
            'mime' => $export->format === 'csv' ? 'text/csv' : 'application/pdf',
        ];
    }

    /**
     * 清理过期文件
     */
    public function cleanupExpired(): int
    {
        $count = 0;
        CustomerDataExport::where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunk(50, function ($exports) use (&$count) {
                foreach ($exports as $export) {
                    if ($export->file_path && Storage::disk($this->disk)->exists($export->file_path)) {
                        Storage::disk($this->disk)->delete($export->file_path);
                    }
                    $export->delete();
                    $count++;
                }
            });
        return $count;
    }

    /**
     * 统计数据
     */
    public function getStats(): array
    {
        return [
            'total_exports' => CustomerDataExport::count(),
            'by_type' => collect(CustomerDataExport::TYPES)->mapWithKeys(fn($t) => [
                $t => CustomerDataExport::where('type', $t)->count()
            ]),
            'by_status' => collect(CustomerDataExport::STATUSES)->mapWithKeys(fn($s) => [
                $s => CustomerDataExport::where('status', $s)->count()
            ]),
            'total_files_size' => CustomerDataExport::where('status', 'completed')->sum('file_size'),
            'average_records' => round(CustomerDataExport::where('status', 'completed')->avg('record_count') ?? 0),
        ];
    }

    // ─── 内部方法 ───

    protected function fetchData(Customer $customer, string $type, array $filters): array
    {
        return match ($type) {
            'licenses' => $this->fetchLicenses($customer, $filters),
            'invoices' => $this->fetchInvoices($customer, $filters),
            'activations' => $this->fetchActivations($customer, $filters),
            'customers' => $this->fetchCustomerInfo($customer),
            default => [],
        };
    }

    protected function fetchLicenses(Customer $customer, array $filters): array
    {
        $query = License::where('customer_id', $customer->id)
            ->with(['product:id,name', 'activations']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->get()->map(fn($l) => [
            'License Key' => $l->license_key,
            '产品' => $l->product?->name ?? '-',
            '类型' => $l->type,
            '状态' => $l->status,
            '激活时间' => $l->activated_at?->format('Y-m-d H:i:s') ?? '-',
            '过期时间' => $l->expires_at?->format('Y-m-d H:i:s') ?? '-',
            '授权席位' => $l->seats,
            '最大设备数' => $l->max_devices,
            '激活数' => $l->activations->count(),
            '创建时间' => $l->created_at->format('Y-m-d H:i:s'),
        ])->toArray();
    }

    protected function fetchInvoices(Customer $customer, array $filters): array
    {
        $query = Invoice::where('customer_id', $customer->id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->get()->map(fn($inv) => [
            '发票号' => $inv->invoice_no ?? $inv->id,
            '金额' => number_format((float) ($inv->amount ?? 0), 2),
            '币种' => $inv->currency ?? 'USD',
            '状态' => $inv->status,
            '支付方式' => $inv->payment_method ?? '-',
            '支付时间' => $inv->paid_at?->format('Y-m-d H:i:s') ?? '-',
            '创建时间' => $inv->created_at->format('Y-m-d H:i:s'),
            '到期时间' => $inv->due_at?->format('Y-m-d') ?? '-',
        ])->toArray();
    }

    protected function fetchActivations(Customer $customer, array $filters): array
    {
        $query = LicenseActivation::whereHas('license', fn($q) => $q->where('customer_id', $customer->id))
            ->with('license:id,license_key');

        // LicenseActivation table doesn't have a status column
        // Filters are applied on the license level if needed

        return $query->get()->map(fn($a) => [
            'License Key' => $a->license?->license_key ?? '-',
            '设备ID' => $a->device_id ?? '-',
            'IP地址' => $a->ip_address ?? '-',
            '操作' => $a->action ?? '-',
            '指纹' => $a->fingerprint ?? '-',
            '激活时间' => $a->created_at->format('Y-m-d H:i:s'),
        ])->toArray();
    }

    protected function fetchCustomerInfo(Customer $customer): array
    {
        $totalSpent = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum(\DB::raw('COALESCE(amount, 0)'));

        return [[
            '客户ID' => $customer->id,
            '类型' => $customer->type ?? '-',
            '等级' => $customer->level ?? '-',
            '状态' => $customer->status,
            '注册时间' => $customer->created_at->format('Y-m-d H:i:s'),
            '总消费' => number_format((float) $totalSpent, 2),
            'License数' => $customer->licenses()->count(),
            '发票数' => $customer->invoices()->count(),
        ]];
    }

    protected function generateFileName(Customer $customer, string $type, string $format): string
    {
        $prefix = match ($type) {
            'licenses' => 'licenses',
            'invoices' => 'invoices',
            'activations' => 'activations',
            'customers' => 'customer_info',
            default => 'export',
        };
        return sprintf('%s_%s_%s.%s', $prefix, $customer->id, now()->format('Ymd_His'), $format);
    }

    protected function generateCsv(array $data, string $fileName, string $type): string
    {
        $dir = 'exports/' . $type . '/' . date('Y/m');
        Storage::disk($this->disk)->makeDirectory($dir);
        $path = $dir . '/' . $fileName;

        $stream = fopen('php://temp', 'r+');

        if (!empty($data)) {
            // BOM for Excel (UTF-8)
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, array_keys($data[0]));

            foreach ($data as $row) {
                fputcsv($stream, $row);
            }
        }

        rewind($stream);
        Storage::disk($this->disk)->put($path, stream_get_contents($stream));
        fclose($stream);

        return $path;
    }

    protected function getRecordCount(Customer $customer, string $type): int
    {
        return match ($type) {
            'licenses' => License::where('customer_id', $customer->id)->count(),
            'invoices' => Invoice::where('customer_id', $customer->id)->count(),
            'activations' => LicenseActivation::whereHas('license', fn($q) => $q->where('customer_id', $customer->id))->count(),
            'customers' => 1,
            default => 0,
        };
    }
}
