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
        return [
            'q' => [
                'type' => 'text',
                'label' => '关键词',
                'placeholder' => '搜索许可证密钥或客户...',
                'operators' => ['contains'],
            ],
            'status' => [
                'type' => 'select',
                'label' => '状态',
                'multiple' => true,
                'options' => [
                    ['value' => 'active', 'label' => '激活'],
                    ['value' => 'expired', 'label' => '已过期'],
                    ['value' => 'suspended', 'label' => '已暂停'],
                    ['value' => 'revoked', 'label' => '已撤销'],
                    ['value' => 'pending', 'label' => '待激活'],
                ],
            ],
            'type' => [
                'type' => 'select',
                'label' => '类型',
                'multiple' => true,
                'options' => [
                    ['value' => 'standard', 'label' => '标准'],
                    ['value' => 'enterprise', 'label' => '企业'],
                    ['value' => 'trial', 'label' => '试用'],
                    ['value' => 'lifetime', 'label' => '永久'],
                    ['value' => 'subscription', 'label' => '订阅'],
                ],
            ],
            'product_id' => [
                'type' => 'model-select',
                'label' => '产品',
                'model' => 'Product',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'customer_id' => [
                'type' => 'model-select',
                'label' => '客户',
                'model' => 'Customer',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'expires_from' => [
                'type' => 'date',
                'label' => '过期日期(起)',
            ],
            'expires_to' => [
                'type' => 'date',
                'label' => '过期日期(止)',
            ],
            'seats_min' => [
                'type' => 'number',
                'label' => '最少座位数',
                'min' => 0,
            ],
            'seats_max' => [
                'type' => 'number',
                'label' => '最多座位数',
                'min' => 0,
            ],
            'is_floating' => [
                'type' => 'boolean',
                'label' => '浮动许可证',
            ],
            'date_from' => [
                'type' => 'date',
                'label' => '创建日期(起)',
            ],
            'date_to' => [
                'type' => 'date',
                'label' => '创建日期(止)',
            ],
        ];
    }

    /**
     * 客户筛选器定义
     */
    protected function customerFilters(): array
    {
        return [
            'q' => [
                'type' => 'text',
                'label' => '关键词',
                'placeholder' => '搜索姓名、邮箱、公司...',
            ],
            'status' => [
                'type' => 'select',
                'label' => '状态',
                'multiple' => true,
                'options' => [
                    ['value' => 'active', 'label' => '活跃'],
                    ['value' => 'inactive', 'label' => '非活跃'],
                    ['value' => 'blocked', 'label' => '已封锁'],
                ],
            ],
            'company' => [
                'type' => 'text',
                'label' => '公司名称',
            ],
            'country' => [
                'type' => 'text',
                'label' => '国家',
            ],
            'has_licenses' => [
                'type' => 'boolean',
                'label' => '拥有许可证',
            ],
            'has_subscriptions' => [
                'type' => 'boolean',
                'label' => '拥有订阅',
            ],
            'date_from' => [
                'type' => 'date',
                'label' => '注册日期(起)',
            ],
            'date_to' => [
                'type' => 'date',
                'label' => '注册日期(止)',
            ],
        ];
    }

    /**
     * 工单筛选器定义
     */
    protected function ticketFilters(): array
    {
        return [
            'q' => [
                'type' => 'text',
                'label' => '关键词',
                'placeholder' => '搜索主题、描述...',
            ],
            'status' => [
                'type' => 'select',
                'label' => '状态',
                'multiple' => true,
                'options' => [
                    ['value' => 'open', 'label' => '开放'],
                    ['value' => 'in_progress', 'label' => '处理中'],
                    ['value' => 'waiting_customer', 'label' => '等待客户'],
                    ['value' => 'resolved', 'label' => '已解决'],
                    ['value' => 'closed', 'label' => '已关闭'],
                ],
            ],
            'priority' => [
                'type' => 'select',
                'label' => '优先级',
                'multiple' => true,
                'options' => [
                    ['value' => 'low', 'label' => '低'],
                    ['value' => 'medium', 'label' => '中'],
                    ['value' => 'high', 'label' => '高'],
                    ['value' => 'urgent', 'label' => '紧急'],
                ],
            ],
            'category' => [
                'type' => 'select',
                'label' => '分类',
                'multiple' => true,
                'options' => [
                    ['value' => 'billing', 'label' => '账单'],
                    ['value' => 'technical', 'label' => '技术'],
                    ['value' => 'account', 'label' => '账户'],
                    ['value' => 'feature', 'label' => '功能请求'],
                    ['value' => 'bug', 'label' => '缺陷'],
                ],
            ],
            'assigned_to' => [
                'type' => 'model-select',
                'label' => '指派人',
                'model' => 'User',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'customer_id' => [
                'type' => 'model-select',
                'label' => '客户',
                'model' => 'Customer',
                'value_field' => 'id',
                'label_field' => 'name',
            ],
            'due_from' => ['type' => 'date', 'label' => '截止日期(起)'],
            'due_to' => ['type' => 'date', 'label' => '截止日期(止)'],
            'date_from' => ['type' => 'date', 'label' => '创建日期(起)'],
            'date_to' => ['type' => 'date', 'label' => '创建日期(止)'],
        ];
    }

    /**
     * 产品筛选器定义
     */
    protected function productFilters(): array
    {
        return [
            'q' => [
                'type' => 'text',
                'label' => '关键词',
                'placeholder' => '搜索产品名称...',
            ],
            'is_active' => [
                'type' => 'boolean',
                'label' => '启用状态',
            ],
            'category' => [
                'type' => 'text',
                'label' => '分类',
            ],
            'price_min' => [
                'type' => 'number',
                'label' => '最低价格',
                'min' => 0,
            ],
            'price_max' => [
                'type' => 'number',
                'label' => '最高价格',
                'min' => 0,
            ],
            'date_from' => ['type' => 'date', 'label' => '创建日期(起)'],
            'date_to' => ['type' => 'date', 'label' => '创建日期(止)'],
        ];
    }

    /**
     * 发票筛选器定义
     */
    protected function invoiceFilters(): array
    {
        return [
            'q' => [
                'type' => 'text',
                'label' => '关键词',
                'placeholder' => '搜索发票号或客户...',
            ],
            'status' => [
                'type' => 'select',
                'label' => '状态',
                'multiple' => true,
                'options' => [
                    ['value' => 'draft', 'label' => '草稿'],
                    ['value' => 'sent', 'label' => '已发送'],
                    ['value' => 'paid', 'label' => '已付款'],
                    ['value' => 'overdue', 'label' => '逾期'],
                    ['value' => 'cancelled', 'label' => '已取消'],
                    ['value' => 'refunded', 'label' => '已退款'],
                ],
            ],
            'amount_min' => [
                'type' => 'number',
                'label' => '最低金额',
                'min' => 0,
            ],
            'amount_max' => [
                'type' => 'number',
                'label' => '最高金额',
                'min' => 0,
            ],
            'paid_from' => ['type' => 'date', 'label' => '付款日期(起)'],
            'paid_to' => ['type' => 'date', 'label' => '付款日期(止)'],
            'date_from' => ['type' => 'date', 'label' => '创建日期(起)'],
            'date_to' => ['type' => 'date', 'label' => '创建日期(止)'],
        ];
    }

    /**
     * 订阅筛选器定义
     */
    protected function subscriptionFilters(): array
    {
        return [
            'q' => [
                'type' => 'text',
                'label' => '关键词',
                'placeholder' => '搜索方案或客户...',
            ],
            'status' => [
                'type' => 'select',
                'label' => '状态',
                'multiple' => true,
                'options' => [
                    ['value' => 'active', 'label' => '活跃'],
                    ['value' => 'past_due', 'label' => '逾期'],
                    ['value' => 'cancelled', 'label' => '已取消'],
                    ['value' => 'expired', 'label' => '已过期'],
                    ['value' => 'trialing', 'label' => '试用'],
                ],
            ],
            'billing_period' => [
                'type' => 'select',
                'label' => '计费周期',
                'options' => [
                    ['value' => 'monthly', 'label' => '每月'],
                    ['value' => 'quarterly', 'label' => '每季'],
                    ['value' => 'yearly', 'label' => '每年'],
                    ['value' => 'one_time', 'label' => '一次性'],
                ],
            ],
            'plan' => [
                'type' => 'text',
                'label' => '方案名称',
            ],
            'renews_from' => ['type' => 'date', 'label' => '续费日期(起)'],
            'renews_to' => ['type' => 'date', 'label' => '续费日期(止)'],
            'date_from' => ['type' => 'date', 'label' => '创建日期(起)'],
            'date_to' => ['type' => 'date', 'label' => '创建日期(止)'],
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
