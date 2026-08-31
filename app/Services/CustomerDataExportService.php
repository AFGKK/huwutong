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
            $export->markAsFailed(__('app.customer_data_export.file_lost'));
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
            __('app.customer_data_export.csv_license_key') => $l->license_key,
            __('app.customer_data_export.csv_product') => $l->product?->name ?? '-',
            __('app.customer_data_export.csv_type') => $l->type,
            __('app.customer_data_export.csv_status') => $l->status,
            __('app.customer_data_export.csv_activated_at') => $l->activated_at?->format('Y-m-d H:i:s') ?? '-',
            __('app.customer_data_export.csv_expires_at') => $l->expires_at?->format('Y-m-d H:i:s') ?? '-',
            __('app.customer_data_export.csv_seats') => $l->seats,
            __('app.customer_data_export.csv_max_devices') => $l->max_devices,
            __('app.customer_data_export.csv_activation_count') => $l->activations->count(),
            __('app.customer_data_export.csv_created_at') => $l->created_at->format('Y-m-d H:i:s'),
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
            __('app.customer_data_export.csv_invoice_no') => $inv->invoice_no ?? $inv->id,
            __('app.customer_data_export.csv_amount') => number_format((float) ($inv->amount ?? 0), 2),
            __('app.customer_data_export.csv_currency') => $inv->currency ?? 'USD',
            __('app.customer_data_export.csv_status') => $inv->status,
            __('app.customer_data_export.csv_payment_method') => $inv->payment_method ?? '-',
            __('app.customer_data_export.csv_paid_at') => $inv->paid_at?->format('Y-m-d H:i:s') ?? '-',
            __('app.customer_data_export.csv_created_at') => $inv->created_at->format('Y-m-d H:i:s'),
            __('app.customer_data_export.csv_due_at') => $inv->due_at?->format('Y-m-d') ?? '-',
        ])->toArray();
    }

    protected function fetchActivations(Customer $customer, array $filters): array
    {
        $query = LicenseActivation::whereHas('license', fn($q) => $q->where('customer_id', $customer->id))
            ->with('license:id,license_key');

        // LicenseActivation table doesn't have a status column
        // Filters are applied on the license level if needed

        return $query->get()->map(fn($a) => [
            __('app.customer_data_export.csv_license_key') => $a->license?->license_key ?? '-',
            __('app.customer_data_export.csv_device_id') => $a->device_id ?? '-',
            __('app.customer_data_export.csv_ip_address') => $a->ip_address ?? '-',
            __('app.customer_data_export.csv_action') => $a->action ?? '-',
            __('app.customer_data_export.csv_fingerprint') => $a->fingerprint ?? '-',
            __('app.customer_data_export.csv_activated_at') => $a->created_at->format('Y-m-d H:i:s'),
        ])->toArray();
    }

    protected function fetchCustomerInfo(Customer $customer): array
    {
        $totalSpent = Invoice::where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->sum(\DB::raw('COALESCE(amount, 0)'));

        return [[
            __('app.customer_data_export.csv_customer_id') => $customer->id,
            __('app.customer_data_export.csv_type') => $customer->type ?? '-',
            __('app.customer_data_export.csv_level') => $customer->level ?? '-',
            __('app.customer_data_export.csv_status') => $customer->status,
            __('app.customer_data_export.csv_registered_at') => $customer->created_at->format('Y-m-d H:i:s'),
            __('app.customer_data_export.csv_total_spent') => number_format((float) $totalSpent, 2),
            __('app.customer_data_export.csv_license_count') => $customer->licenses()->count(),
            __('app.customer_data_export.csv_invoice_count') => $customer->invoices()->count(),
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
