<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\SavedSearch;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * 高级搜索服务
 *
 * 提供：
 * - 各实体丰富的筛选器定义（可用条件）
 * - 跨模型高级筛选搜索
 * - 保存搜索增强（常用搜索、团队共享）
 * - 筛选器面板配置
 */
class AdvancedSearchService
{
    /**
     * 获取指定页面的可用筛选器定义
     *
     * @param string $page 页面标识
     * @return array
     */
    public function getFilterDefinitions(string $page): array
    {
        return match ($page) {
            'licenses' => $this->licenseFilters(),
            'customers' => $this->customerFilters(),
            'tickets' => $this->ticketFilters(),
            'products' => $this->productFilters(),
            'invoices' => $this->invoiceFilters(),
            'subscriptions' => $this->subscriptionFilters(),
            default => [],
        };
    }

    /**
     * 执行高级筛选搜索
     *
     * @param string $page 页面标识
     * @param array $filters 筛选条件
     * @param array $options 选项(sort, per_page, page, columns)
     * @return array
     */
    public function advancedSearch(string $page, array $filters = [], array $options = []): array
    {
        $perPage = (int) ($options['per_page'] ?? 20);
        $pageNum = (int) ($options['page'] ?? 1);
        $sort = $options['sort'] ?? [];
        $columns = $options['columns'] ?? ['*'];

        $query = $this->buildQuery($page, $filters);

        if (!$query) {
            return ['items' => [], 'total' => 0, 'page' => $pageNum, 'per_page' => $perPage];
        }

        // Apply sorting
        if (!empty($sort['field'])) {
            $dir = ($sort['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort['field'], $dir);
        } else {
            $query->orderByDesc('created_at');
        }

        $total = $query->count();
        $items = $query->skip(($pageNum - 1) * $perPage)->take($perPage)->get($columns);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $pageNum,
            'per_page' => $perPage,
        ];
    }

    /**
     * 根据页面类型构建查询
     */
    protected function buildQuery(string $page, array $filters = []): ?Builder
    {
        $query = match ($page) {
            'licenses' => License::query(),
            'customers' => Customer::query(),
            'tickets' => Ticket::query(),
            'products' => Product::query(),
            'invoices' => Invoice::query(),
            'subscriptions' => Subscription::query(),
            default => null,
        };

        if (!$query) {
            return null;
        }

        // Apply common filters
        $this->applyCommonFilters($query, $filters);

        // Apply page-specific filters
        $method = 'apply' . ucfirst($page) . 'Filters';
        if (method_exists($this, $method)) {
            $this->$method($query, $filters);
        }

        return $query;
    }

    /**
     * 通用筛选条件
     */
    protected function applyCommonFilters(Builder $query, array $filters): void
    {
        // 关键词搜索
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('id', 'like', "%{$q}%")
                     ->orWhere('name', 'like', "%{$q}%");
            });
        }

        // 日期范围
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // 更新时间范围
        if (!empty($filters['updated_from'])) {
            $query->whereDate('updated_at', '>=', $filters['updated_from']);
        }
        if (!empty($filters['updated_to'])) {
            $query->whereDate('updated_at', '<=', $filters['updated_to']);
        }

        // ID 范围
        if (!empty($filters['id_min'])) {
            $query->where('id', '>=', (int) $filters['id_min']);
        }
        if (!empty($filters['id_max'])) {
            $query->where('id', '<=', (int) $filters['id_max']);
        }

        // 自定义字段过滤（支持 JSON 元数据）
        if (!empty($filters['metadata']) && is_array($filters['metadata'])) {
            foreach ($filters['metadata'] as $key => $value) {
                $query->where("metadata->{$key}", $value);
            }
        }
    }

    /**
     * 许可证筛选条件
     */
    protected function applyLicensesFilters(Builder $query, array $filters): void
    {
        // 关键词 — 覆盖 license_key + 关联客户名
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('license_key', 'like', "%{$q}%")
                     ->orWhereHas('customer', function ($c) use ($q) {
                         $c->where('name', 'like', "%{$q}%")
                           ->orWhere('email', 'like', "%{$q}%");
                     });
            });
        } else {
            // 单个关键词过滤
            if (!empty($filters['keyword'])) {
                $kw = $filters['keyword'];
                $query->where(function ($sub) use ($kw) {
                    $sub->where('license_key', 'like', "%{$kw}%");
                });
            }
        }

        // 状态
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        // 类型
        if (!empty($filters['type'])) {
            if (is_array($filters['type'])) {
                $query->whereIn('type', $filters['type']);
            } else {
                $query->where('type', $filters['type']);
            }
        }

        // 产品
        if (!empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }

        // 客户
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        // 过期日期范围
        if (!empty($filters['expires_from'])) {
            $query->whereDate('expires_at', '>=', $filters['expires_from']);
        }
        if (!empty($filters['expires_to'])) {
            $query->whereDate('expires_at', '<=', $filters['expires_to']);
        }

        // 座位数范围
        if (!empty($filters['seats_min'])) {
            $query->where('seats', '>=', (int) $filters['seats_min']);
        }
        if (!empty($filters['seats_max'])) {
            $query->where('seats', '<=', (int) $filters['seats_max']);
        }

        // 是否浮动许可证
        if (isset($filters['is_floating'])) {
            $query->where('is_floating', $filters['is_floating']);
        }
    }

    /**
     * 客户筛选条件
     */
    protected function applyCustomersFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                     ->orWhere('email', 'like', "%{$q}%")
                     ->orWhere('company', 'like', "%{$q}%")
                     ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['company'])) {
            $query->where('company', 'like', "%{$filters['company']}%");
        }

        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (!empty($filters['tag']) && is_array($filters['tag'])) {
            foreach ($filters['tag'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        if (!empty($filters['has_licenses'])) {
            $query->whereHas('licenses');
        }

        if (!empty($filters['has_subscriptions'])) {
            $query->whereHas('subscriptions');
        }
    }

    /**
     * 工单筛选条件
     */
    protected function applyTicketsFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('subject', 'like', "%{$q}%")
                     ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['priority'])) {
            if (is_array($filters['priority'])) {
                $query->whereIn('priority', $filters['priority']);
            } else {
                $query->where('priority', $filters['priority']);
            }
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (!empty($filters['due_from'])) {
            $query->whereDate('due_at', '>=', $filters['due_from']);
        }
        if (!empty($filters['due_to'])) {
            $query->whereDate('due_at', '<=', $filters['due_to']);
        }

        if (!empty($filters['resolved_from'])) {
            $query->whereDate('resolved_at', '>=', $filters['resolved_from']);
        }
        if (!empty($filters['resolved_to'])) {
            $query->whereDate('resolved_at', '<=', $filters['resolved_to']);
        }
    }

    /**
     * 产品筛选条件
     */
    protected function applyProductsFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                     ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', (float) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', (float) $filters['price_max']);
        }
    }

    /**
     * 发票筛选条件
     */
    protected function applyInvoicesFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('invoice_no', 'like', "%{$q}%")
                     ->orWhereHas('customer', function ($c) use ($q) {
                         $c->where('name', 'like', "%{$q}%");
                     });
            });
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['amount_min'])) {
            $query->where('amount', '>=', (float) $filters['amount_min']);
        }
        if (!empty($filters['amount_max'])) {
            $query->where('amount', '<=', (float) $filters['amount_max']);
        }

        if (!empty($filters['paid_from'])) {
            $query->whereDate('paid_at', '>=', $filters['paid_from']);
        }
        if (!empty($filters['paid_to'])) {
            $query->whereDate('paid_at', '<=', $filters['paid_to']);
        }
    }

    /**
     * 订阅筛选条件
     */
    protected function applySubscriptionsFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('plan', 'like', "%{$q}%")
                     ->orWhereHas('customer', function ($c) use ($q) {
                         $c->where('name', 'like', "%{$q}%");
                     });
            });
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (!empty($filters['billing_period'])) {
            $query->where('billing_period', $filters['billing_period']);
        }

        if (!empty($filters['plan'])) {
            $query->where('plan', $filters['plan']);
        }

        if (!empty($filters['renews_from'])) {
            $query->whereDate('renews_at', '>=', $filters['renews_from']);
        }
        if (!empty($filters['renews_to'])) {
            $query->whereDate('renews_at', '<=', $filters['renews_to']);
        }
    }

    // ─── 筛选器定义 ───

    /**
     * 许可证筛选器定义
     */
    protected function licenseFilters(): array
    {
        $t = fn(string $k) => __('app.advanced_search.' . $k);

        return [
            'q' => [
                'type' => 'text',
                'label' => $t('common.keyword'),
                'placeholder' => $t('licenses.search_placeholder'),
                'operators' => ['contains'],
            ],
            'status' => [
                'type' => 'select',
                'label' => $t('common.status'),
                'multiple' => true,
                'options' => [
                    ['value' => 'active', 'label' => $t('licenses.status_active')],
                    ['value' => 'expired', 'label' => $t('licenses.status_expired')],
                    ['value' => 'suspended', 'label' => $t('licenses.status_suspended')],
                    ['value' => 'revoked', 'label' => $t('licenses.status_revoked')],
                    ['value' => 'pending', 'label' => $t('licenses.status_pending')],
                ],
            ],
            'type' => [
                'type' => 'select',
                'label' => $t('common.type'),
                'multiple' => true,
                'options' => [
                    ['value' => 'standard', 'label' => $t('licenses.type_standard')],
                    ['value' => 'enterprise', 'label' => $t('licenses.type_enterprise')],
                    ['value' => 'trial', 'label' => $t('licenses.type_trial')],
                    ['value' => 'lifetime', 'label' => $t('licenses.type_lifetime')],
                    ['value' => 'subscription', 'label' => $t('licenses.type_subscription')],
                ],
            ],
            'product_id' => [
                'type' => 'model-select',
                'label' => $t('common.product'),
                'model' => 'Product',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'customer_id' => [
                'type' => 'model-select',
                'label' => $t('common.customer'),
                'model' => 'Customer',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'expires_from' => [
                'type' => 'date',
                'label' => $t('licenses.expires_from'),
            ],
            'expires_to' => [
                'type' => 'date',
                'label' => $t('licenses.expires_to'),
            ],
            'seats_min' => [
                'type' => 'number',
                'label' => $t('licenses.seats_min'),
                'min' => 0,
            ],
            'seats_max' => [
                'type' => 'number',
                'label' => $t('licenses.seats_max'),
                'min' => 0,
            ],
            'is_floating' => [
                'type' => 'boolean',
                'label' => $t('licenses.is_floating'),
            ],
            'date_from' => [
                'type' => 'date',
                'label' => $t('common.date_created_from'),
            ],
            'date_to' => [
                'type' => 'date',
                'label' => $t('common.date_created_to'),
            ],
        ];
    }

    /**
     * 客户筛选器定义
     */
    protected function customerFilters(): array
    {
        $t = fn(string $k) => __('app.advanced_search.' . $k);

        return [
            'q' => [
                'type' => 'text',
                'label' => $t('common.keyword'),
                'placeholder' => $t('customers.search_placeholder'),
            ],
            'status' => [
                'type' => 'select',
                'label' => $t('common.status'),
                'multiple' => true,
                'options' => [
                    ['value' => 'active', 'label' => $t('customers.status_active')],
                    ['value' => 'inactive', 'label' => $t('customers.status_inactive')],
                    ['value' => 'blocked', 'label' => $t('customers.status_blocked')],
                ],
            ],
            'company' => [
                'type' => 'text',
                'label' => $t('customers.company'),
            ],
            'country' => [
                'type' => 'text',
                'label' => $t('customers.country'),
            ],
            'has_licenses' => [
                'type' => 'boolean',
                'label' => $t('customers.has_licenses'),
            ],
            'has_subscriptions' => [
                'type' => 'boolean',
                'label' => $t('customers.has_subscriptions'),
            ],
            'date_from' => [
                'type' => 'date',
                'label' => $t('customers.date_registered_from'),
            ],
            'date_to' => [
                'type' => 'date',
                'label' => $t('customers.date_registered_to'),
            ],
        ];
    }

    /**
     * 工单筛选器定义
     */
    protected function ticketFilters(): array
    {
        $t = fn(string $k) => __('app.advanced_search.' . $k);

        return [
            'q' => [
                'type' => 'text',
                'label' => $t('common.keyword'),
                'placeholder' => $t('tickets.search_placeholder'),
            ],
            'status' => [
                'type' => 'select',
                'label' => $t('common.status'),
                'multiple' => true,
                'options' => [
                    ['value' => 'open', 'label' => $t('tickets.status_open')],
                    ['value' => 'in_progress', 'label' => $t('tickets.status_in_progress')],
                    ['value' => 'waiting_customer', 'label' => $t('tickets.status_waiting_customer')],
                    ['value' => 'resolved', 'label' => $t('tickets.status_resolved')],
                    ['value' => 'closed', 'label' => $t('tickets.status_closed')],
                ],
            ],
            'priority' => [
                'type' => 'select',
                'label' => $t('tickets.priority'),
                'multiple' => true,
                'options' => [
                    ['value' => 'low', 'label' => $t('tickets.priority_low')],
                    ['value' => 'medium', 'label' => $t('tickets.priority_medium')],
                    ['value' => 'high', 'label' => $t('tickets.priority_high')],
                    ['value' => 'urgent', 'label' => $t('tickets.priority_urgent')],
                ],
            ],
            'category' => [
                'type' => 'select',
                'label' => $t('tickets.category'),
                'multiple' => true,
                'options' => [
                    ['value' => 'billing', 'label' => $t('tickets.category_billing')],
                    ['value' => 'technical', 'label' => $t('tickets.category_technical')],
                    ['value' => 'account', 'label' => $t('tickets.category_account')],
                    ['value' => 'feature', 'label' => $t('tickets.category_feature')],
                    ['value' => 'bug', 'label' => $t('tickets.category_bug')],
                ],
            ],
            'assigned_to' => [
                'type' => 'model-select',
                'label' => $t('tickets.assigned_to'),
                'model' => 'User',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'customer_id' => [
                'type' => 'model-select',
                'label' => $t('common.customer'),
                'model' => 'Customer',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'due_from' => ['type' => 'date', 'label' => $t('tickets.due_from')],
            'due_to' => ['type' => 'date', 'label' => $t('tickets.due_to')],
            'date_from' => ['type' => 'date', 'label' => $t('common.date_created_from')],
            'date_to' => ['type' => 'date', 'label' => $t('common.date_created_to')],
        ];
    }

    /**
     * 产品筛选器定义
     */
    protected function productFilters(): array
    {
        $t = fn(string $k) => __('app.advanced_search.' . $k);

        return [
            'q' => [
                'type' => 'text',
                'label' => $t('common.keyword'),
                'placeholder' => $t('products.search_placeholder'),
            ],
            'is_active' => [
                'type' => 'boolean',
                'label' => $t('products.is_active'),
            ],
            'category' => [
                'type' => 'text',
                'label' => $t('products.category'),
            ],
            'price_min' => [
                'type' => 'number',
                'label' => $t('products.price_min'),
                'min' => 0,
            ],
            'price_max' => [
                'type' => 'number',
                'label' => $t('products.price_max'),
                'min' => 0,
            ],
            'date_from' => ['type' => 'date', 'label' => $t('common.date_created_from')],
            'date_to' => ['type' => 'date', 'label' => $t('common.date_created_to')],
        ];
    }

    /**
     * 发票筛选器定义
     */
    protected function invoiceFilters(): array
    {
        $t = fn(string $k) => __('app.advanced_search.' . $k);

        return [
            'q' => [
                'type' => 'text',
                'label' => $t('common.keyword'),
                'placeholder' => $t('invoices.search_placeholder'),
            ],
            'status' => [
                'type' => 'select',
                'label' => $t('common.status'),
                'multiple' => true,
                'options' => [
                    ['value' => 'draft', 'label' => $t('invoices.status_draft')],
                    ['value' => 'sent', 'label' => $t('invoices.status_sent')],
                    ['value' => 'paid', 'label' => $t('invoices.status_paid')],
                    ['value' => 'overdue', 'label' => $t('invoices.status_overdue')],
                    ['value' => 'cancelled', 'label' => $t('invoices.status_cancelled')],
                    ['value' => 'refunded', 'label' => $t('invoices.status_refunded')],
                ],
            ],
            'amount_min' => [
                'type' => 'number',
                'label' => $t('invoices.amount_min'),
                'min' => 0,
            ],
            'amount_max' => [
                'type' => 'number',
                'label' => $t('invoices.amount_max'),
                'min' => 0,
            ],
            'paid_from' => ['type' => 'date', 'label' => $t('invoices.paid_from')],
            'paid_to' => ['type' => 'date', 'label' => $t('invoices.paid_to')],
            'date_from' => ['type' => 'date', 'label' => $t('common.date_created_from')],
            'date_to' => ['type' => 'date', 'label' => $t('common.date_created_to')],
        ];
    }

    /**
     * 订阅筛选器定义
     */
    protected function subscriptionFilters(): array
    {
        $t = fn(string $k) => __('app.advanced_search.' . $k);

        return [
            'q' => [
                'type' => 'text',
                'label' => $t('common.keyword'),
                'placeholder' => $t('subscriptions.search_placeholder'),
            ],
            'status' => [
                'type' => 'select',
                'label' => $t('common.status'),
                'multiple' => true,
                'options' => [
                    ['value' => 'active', 'label' => $t('subscriptions.status_active')],
                    ['value' => 'past_due', 'label' => $t('subscriptions.status_past_due')],
                    ['value' => 'cancelled', 'label' => $t('subscriptions.status_cancelled')],
                    ['value' => 'expired', 'label' => $t('subscriptions.status_expired')],
                    ['value' => 'trialing', 'label' => $t('subscriptions.status_trialing')],
                ],
            ],
            'billing_period' => [
                'type' => 'select',
                'label' => $t('subscriptions.billing_period'),
                'options' => [
                    ['value' => 'monthly', 'label' => $t('subscriptions.billing_period_monthly')],
                    ['value' => 'quarterly', 'label' => $t('subscriptions.billing_period_quarterly')],
                    ['value' => 'yearly', 'label' => $t('subscriptions.billing_period_yearly')],
                    ['value' => 'one_time', 'label' => $t('subscriptions.billing_period_one_time')],
                ],
            ],
            'plan' => [
                'type' => 'text',
                'label' => $t('subscriptions.plan'),
            ],
            'renews_from' => ['type' => 'date', 'label' => $t('subscriptions.renews_from')],
            'renews_to' => ['type' => 'date', 'label' => $t('subscriptions.renews_to')],
            'date_from' => ['type' => 'date', 'label' => $t('common.date_created_from')],
            'date_to' => ['type' => 'date', 'label' => $t('common.date_created_to')],
        ];
    }

    // ─── 保存搜索增强 ───

    /**
     * 保存搜索（带使用记录）
     */
    public function saveSearch(int $userId, array $data): SavedSearch
    {
        $data['user_id'] = $userId;
        return SavedSearch::create($data);
    }

    /**
     * 应用保存的搜索（记录使用）
     */
    public function applySavedSearch(int $id, int $userId): ?array
    {
        $search = SavedSearch::where('id', $id)->where('user_id', $userId)->first();
        if (!$search) {
            return null;
        }

        $search->recordUsage();

        return [
            'id' => $search->id,
            'name' => $search->name,
            'page' => $search->page,
            'filters' => $search->filters,
            'columns' => $search->columns,
            'sort' => $search->sort,
        ];
    }

    /**
     * 获取用户的保存搜索（带统计信息）
     */
    public function getUserSearches(int $userId, ?string $page = null): array
    {
        $query = SavedSearch::where('user_id', $userId);

        if ($page) {
            $query->where('page', $page);
        }

        return $query->orderBy('sort_order')
            ->orderByDesc('usage_count')
            ->orderByDesc('created_at')
            ->get()
            ->all();
    }

    /**
     * 获取团队共享的搜索
     */
    public function getSharedSearches(?string $page = null): array
    {
        $query = SavedSearch::where('is_shared', true);

        if ($page) {
            $query->where('page', $page);
        }

        return $query->orderByDesc('usage_count')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->all();
    }

    /**
     * 获取所有页面的可用筛选器定义
     */
    public function getAllFilterDefinitions(): array
    {
        $pages = SavedSearch::$pages;
        $definitions = [];
        foreach ($pages as $page) {
            $definitions[$page] = [
                'label' => SavedSearch::$pageLabels[$page] ?? $page,
                'icon' => SavedSearch::$pageIcons[$page] ?? 'Search',
                'filters' => $this->getFilterDefinitions($page),
            ];
        }
        return $definitions;
    }
}
