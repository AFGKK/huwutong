<?php

namespace App\Services;

use App\Models\ChurnPrediction;
use App\Models\CsmCommunication;
use App\Models\CsmHealthScore;
use App\Models\CsmTask;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CsmService
{
    /**
     * 计算客户健康评分
     */
    public function calculateHealthScore(Customer $customer): CsmHealthScore
    {
        $tenantId = $customer->tenant_id;
        $factors = [];
        $totalWeight = 0;
        $weightedScore = 0;

        // 1. 订阅状态 (权重25)
        $subscriptions = $customer->subscriptions;
        $hasActiveSub = $subscriptions->contains(fn($s) => $s->isActive());
        $isInGrace = $subscriptions->contains(fn($s) => $s->isInGracePeriod());
        $isExpired = $subscriptions->every(fn($s) => $s->isExpired());
        $isTrial = $subscriptions->contains(fn($s) => $s->isInTrial());

        if ($isExpired) {
            $subscriptionScore = 0;
            $subDesc = '所有订阅已过期';
        } elseif ($isInGrace) {
            $subscriptionScore = 25;
            $subDesc = '订阅在宽限期内';
        } elseif ($isTrial) {
            $subscriptionScore = 60;
            $subDesc = '试用期';
        } elseif ($hasActiveSub) {
            $subscriptionScore = 100;
            $subDesc = '订阅活跃';
        } else {
            $subscriptionScore = 50;
            $subDesc = '无活跃订阅';
        }
        $weightedScore += $subscriptionScore * 25;
        $totalWeight += 25;
        $factors['subscription_status'] = ['score' => $subscriptionScore, 'weight' => 25, 'description' => $subDesc];

        // 2. License活跃率 (权重20)
        $licenses = $customer->licenses;
        $totalLicenses = $licenses->count();
        if ($totalLicenses > 0) {
            $activeLicenses = $licenses->filter(fn($l) => $l->status === 'active')->count();
            $activationRate = round(($activeLicenses / $totalLicenses) * 100);
            $licenseScore = min(100, $activationRate);
            $licenseDesc = "{$activeLicenses}/{$totalLicenses} 活跃";
        } else {
            $licenseScore = 0;
            $licenseDesc = '无License';
        }
        $weightedScore += $licenseScore * 20;
        $totalWeight += 20;
        $factors['license_activation'] = ['score' => $licenseScore, 'weight' => 20, 'description' => $licenseDesc];

        // 3. 流失预测 (权重20)
        $prediction = ChurnPrediction::where('customer_id', $customer->id)
            ->where('predicted_at', '>=', now()->subDays(7))
            ->orderByDesc('predicted_at')
            ->first();
        if ($prediction) {
            $churnScore = match ($prediction->risk_level) {
                'low' => 90,
                'medium' => 50,
                'high' => 15,
                'critical' => 5,
                default => 50,
            };
            $churnDesc = "流失风险: {$prediction->risk_level}, 概率: {$prediction->churn_probability}";
        } else {
            $churnScore = 70; // 无数据时偏保守
            $churnDesc = '无流失预测数据';
        }
        $weightedScore += $churnScore * 20;
        $totalWeight += 20;
        $factors['churn_prediction'] = ['score' => $churnScore, 'weight' => 20, 'description' => $churnDesc];

        // 4. 发票支付情况 (权重15)
        $invoices = Invoice::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subMonths(6))
            ->get();
        $totalInvoiceCount = $invoices->count();
        if ($totalInvoiceCount > 0) {
            $paidCount = $invoices->filter(fn($inv) => $inv->status === 'paid')->count();
            $paymentRate = round(($paidCount / $totalInvoiceCount) * 100);
            $overdueCount = $invoices->filter(fn($inv) => $inv->status === 'overdue')->count();
            $paymentScore = max(0, min(100, $paymentRate - ($overdueCount * 15)));
            $paymentDesc = "支付率 {$paymentRate}%, 逾期 {$overdueCount} 笔";
        } else {
            $paymentScore = 80;
            $paymentDesc = '无发票记录';
        }
        $weightedScore += $paymentScore * 15;
        $totalWeight += 15;
        $factors['payment_status'] = ['score' => $paymentScore, 'weight' => 15, 'description' => $paymentDesc];

        // 5. 工单/支持 (权重12)
        $recentTickets = Ticket::where('customer_id', $customer->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();
        $openTickets = $recentTickets->filter(fn($t) => $t->isOpen())->count();
        $resolvedTickets = $recentTickets->filter(fn($t) => $t->isResolved())->count();
        $totalRecent = $recentTickets->count();

        if ($totalRecent > 0) {
            $resolutionRate = round(($resolvedTickets / $totalRecent) * 100);
            $ticketScore = max(0, min(100, $resolutionRate - ($openTickets * 10)));
            $ticketDesc = "近30天 {$totalRecent} 工单, {$openTickets} 未关闭";
        } else {
            $ticketScore = 90;
            $ticketDesc = '近30天无工单';
        }
        $weightedScore += $ticketScore * 12;
        $totalWeight += 12;
        $factors['support_tickets'] = ['score' => $ticketScore, 'weight' => 12, 'description' => $ticketDesc];

        // 6. 近期活动/活跃度 (权重8)
        $lastLicenseActivity = $customer->licenses()
            ->whereNotNull('updated_at')
            ->max('updated_at');
        $lastLogin = $customer->user?->last_login_at;
        $recentActivity = max($lastLicenseActivity, $lastLogin);
        if ($recentActivity) {
            $daysSinceActivity = (int) Carbon::parse($recentActivity)->diffInDays(now());
            $activityScore = match (true) {
                $daysSinceActivity <= 1 => 100,
                $daysSinceActivity <= 7 => 80,
                $daysSinceActivity <= 30 => 60,
                $daysSinceActivity <= 90 => 40,
                default => 10,
            };
            $activityDesc = "{$daysSinceActivity}天前活跃";
        } else {
            $activityScore = 30;
            $activityDesc = '无活动记录';
        }
        $weightedScore += $activityScore * 8;
        $totalWeight += 8;
        $factors['recent_activity'] = ['score' => $activityScore, 'weight' => 8, 'description' => $activityDesc];

        // 计算总评分
        $finalScore = $totalWeight > 0 ? round($weightedScore / $totalWeight) : 0;
        $healthLevel = $this->determineHealthLevel($finalScore);

        // 生成摘要
        $summary = $this->generateSummary($healthLevel, $factors, $customer);

        // 保存历史记录（每次计算新增一条，便于趋势分析）
        return CsmHealthScore::create(
            [
                'customer_id' => $customer->id,
                'tenant_id' => $customer->tenant_id,
                'health_score' => $finalScore,
                'health_level' => $healthLevel,
                'factors' => $factors,
                'summary' => $summary,
                'calculated_at' => now(),
            ]
        );
    }

    /**
     * 确定健康等级
     */
    public function determineHealthLevel(int $score): string
    {
        return match (true) {
            $score >= 80 => 'healthy',
            $score >= 60 => 'attention',
            $score >= 30 => 'at_risk',
            default => 'churned',
        };
    }

    /**
     * 生成健康摘要
     */
    protected function generateSummary(string $level, array $factors, Customer $customer): string
    {
        $lowFactors = [];
        foreach ($factors as $key => $f) {
            if ($f['score'] < 50) {
                $labels = [
                    'subscription_status' => '订阅状态',
                    'license_activation' => 'License活跃率',
                    'churn_prediction' => '流失风险',
                    'payment_status' => '支付状况',
                    'support_tickets' => '工单支持',
                    'recent_activity' => '近期活跃度',
                ];
                $lowFactors[] = $labels[$key] ?? $key;
            }
        }

        $levelLabel = CsmHealthScore::LEVELS[$level] ?? $level;
        $subScore = $factors['subscription_status']['score'] ?? 0;
        $summary = "健康等级: {$levelLabel} (评分: {$subScore})";

        if (!empty($lowFactors)) {
            $summary .= ' | 需关注: ' . implode(', ', $lowFactors);
        }

        return $summary;
    }

    /**
     * 批量计算所有客户健康评分
     */
    public function batchCalculateHealthScores(int $tenantId): array
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();
        $results = [];

        foreach ($customers as $customer) {
            try {
                $score = $this->calculateHealthScore($customer);
                $results[] = [
                    'customer_id' => $customer->id,
                    'health_score' => $score->health_score,
                    'health_level' => $score->health_level,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 获取CSM仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        // 健康分布
        $healthDistribution = CsmHealthScore::where('tenant_id', $tenantId)
            ->selectRaw('health_level, COUNT(*) as cnt')
            ->groupBy('health_level')
            ->pluck('cnt', 'health_level')
            ->toArray();

        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $scoredCustomers = array_sum($healthDistribution);

        // 待完成任务统计
        $taskStats = [
            'total_open' => CsmTask::where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'in_progress'])->count(),
            'overdue' => CsmTask::where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'in_progress'])
                ->where('due_at', '<', now())->count(),
            'high_priority' => CsmTask::where('tenant_id', $tenantId)
                ->whereIn('status', ['open', 'in_progress'])
                ->whereIn('priority', ['high', 'urgent'])->count(),
            'completed_today' => CsmTask::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereDate('completed_at', today())->count(),
        ];

        // 即将续费的订阅
        $upcomingRenewals = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereBetween('next_billing_at', [now(), now()->addDays(30)])
            ->with('customer.user:id,name,email', 'product')
            ->orderBy('next_billing_at')
            ->limit(10)
            ->get()
            ->toArray();

        // 近期沟通记录
        $recentCommunications = CsmCommunication::where('tenant_id', $tenantId)
            ->with('customer.user:id,name', 'user:id,name')
            ->orderByDesc('contacted_at')
            ->limit(10)
            ->get()
            ->toArray();

        // 即将到期的License
        $expiringLicenses = License::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->with('customer.user:id,name', 'product')
            ->orderBy('expires_at')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'health_distribution' => $healthDistribution,
            'total_customers' => $totalCustomers,
            'scored_customers' => $scoredCustomers,
            'task_stats' => $taskStats,
            'upcoming_renewals' => $upcomingRenewals,
            'recent_communications' => $recentCommunications,
            'expiring_licenses' => $expiringLicenses,
        ];
    }

    /**
     * 获取客户列表（含健康评分）
     */
    public function getCustomersWithHealth(int $tenantId, array $filters = [], string $sort = '-health_score', int $perPage = 20)
    {
        $query = Customer::where('customers.tenant_id', $tenantId)
            ->leftJoin('csm_health_scores', function ($join) {
                $join->on('customers.id', '=', 'csm_health_scores.customer_id')
                    ->whereRaw('csm_health_scores.id IN (SELECT MAX(id) FROM csm_health_scores GROUP BY customer_id)');
            })
            ->leftJoin('churn_predictions', function ($join) {
                $join->on('customers.id', '=', 'churn_predictions.customer_id')
                    ->whereRaw('churn_predictions.id IN (SELECT MAX(id) FROM churn_predictions GROUP BY customer_id)');
            })
            ->leftJoin('users', 'customers.user_id', '=', 'users.id')
            ->select(
                'customers.*',
                'csm_health_scores.health_score',
                'csm_health_scores.health_level',
                'csm_health_scores.calculated_at',
                'churn_predictions.risk_level as churn_risk',
                'churn_predictions.churn_probability as churn_score',
                'users.name as user_name',
                'users.email as user_email',
                'users.last_login_at',
            );

        // 筛选
        if (!empty($filters['health_level'])) {
            $query->where('csm_health_scores.health_level', $filters['health_level']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%");
            });
        }
        if (!empty($filters['churn_risk'])) {
            $query->where('churn_predictions.risk_level', $filters['churn_risk']);
        }

        // 排序
        $sortDir = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sort, '-');
        $allowedSorts = ['health_score', 'health_level', 'calculated_at', 'last_login_at', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir);
        } else {
            $query->orderByDesc('csm_health_scores.health_score');
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取客户详细CSM数据
     */
    public function getCustomerDetail(int $customerId): array
    {
        $customer = Customer::with(['user:id,name,email,last_login_at', 'subscriptions.product', 'licenses.product'])->findOrFail($customerId);

        $healthScore = CsmHealthScore::where('customer_id', $customerId)
            ->orderByDesc('calculated_at')->first();

        $churnPrediction = ChurnPrediction::where('customer_id', $customerId)
            ->orderByDesc('predicted_at')->first();

        $tasks = CsmTask::where('customer_id', $customerId)
            ->with('assignee:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $communications = CsmCommunication::where('customer_id', $customerId)
            ->with('user:id,name')
            ->orderByDesc('contacted_at')
            ->limit(20)
            ->get();

        // 健康评分历史（最近30条）
        $healthHistory = CsmHealthScore::where('customer_id', $customerId)
            ->orderByDesc('calculated_at')
            ->limit(30)
            ->get(['health_score', 'health_level', 'calculated_at']);

        return [
            'customer' => $customer,
            'health_score' => $healthScore,
            'churn_prediction' => $churnPrediction,
            'tasks' => $tasks,
            'communications' => $communications,
            'health_history' => $healthHistory,
        ];
    }

    /**
     * 创建CSM任务
     */
    public function createTask(array $data): CsmTask
    {
        return CsmTask::create($data);
    }

    /**
     * 更新CSM任务
     */
    public function updateTask(CsmTask $task, array $data): CsmTask
    {
        if (isset($data['status']) && $data['status'] === 'completed' && !$task->completed_at) {
            $data['completed_at'] = now();
        }
        $task->update($data);
        return $task->fresh();
    }

    /**
     * 记录客户沟通
     */
    public function recordCommunication(array $data): CsmCommunication
    {
        return CsmCommunication::create($data);
    }

    /**
     * 获取任务列表
     */
    public function getTasks(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = CsmTask::where('csm_tasks.tenant_id', $tenantId)
            ->with(['customer.user:id,name,email', 'assignee:id,name'])
            ->orderByRaw("CASE status WHEN 'open' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 WHEN 'cancelled' THEN 4 ELSE 5 END")
            ->orderBy('due_at');

        if (!empty($filters['status'])) {
            $query->where('csm_tasks.status', $filters['status']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('csm_tasks.assigned_to', $filters['assigned_to']);
        }
        if (!empty($filters['priority'])) {
            $query->where('csm_tasks.priority', $filters['priority']);
        }
        if (!empty($filters['category'])) {
            $query->where('csm_tasks.category', $filters['category']);
        }

        return $query->paginate($perPage);
    }

    /**
     * 自动创建续费提醒任务
     */
    public function createRenewalReminders(int $tenantId): int
    {
        $subscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('auto_renew', true)
            ->whereBetween('next_billing_at', [now()->addDays(7), now()->addDays(14)])
            ->get();

        $count = 0;
        foreach ($subscriptions as $sub) {
            // Check if a renewal task already exists
            $existingTask = CsmTask::where('tenant_id', $tenantId)
                ->where('customer_id', $sub->customer_id)
                ->where('category', 'renewal')
                ->whereIn('status', ['open', 'in_progress'])
                ->first();

            if ($existingTask) {
                continue;
            }

            CsmTask::create([
                'tenant_id' => $tenantId,
                'customer_id' => $sub->customer_id,
                'assigned_to' => User::where('tenant_id', $tenantId)->first()?->id ?? 1,
                'title' => "续费提醒: {$sub->product?->name} ({$sub->plan})",
                'description' => "客户订阅将于 {$sub->next_billing_at->format('Y-m-d')} 自动续费，金额 {$sub->price} {$sub->currency}。请提前确认支付方式有效。",
                'priority' => 'normal',
                'category' => 'renewal',
                'related_type' => 'renewal',
                'related_id' => $sub->id,
                'due_at' => $sub->next_billing_at->subDays(3),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * 健康评分趋势（近 N 天按日聚合）
     */
    public function getHealthTrend(int $tenantId, int $days = 90): array
    {
        $rows = CsmHealthScore::where('tenant_id', $tenantId)
            ->where('calculated_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(calculated_at) as date, AVG(health_score) as avg_score, COUNT(DISTINCT customer_id) as customers')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $levelTrend = CsmHealthScore::where('tenant_id', $tenantId)
            ->where('calculated_at', '>=', now()->subDays($days))
            ->selectRaw('DATE(calculated_at) as date, health_level, COUNT(*) as cnt')
            ->groupBy('date', 'health_level')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        return [
            'points' => $rows->map(fn ($r) => [
                'date' => $r->date,
                'avg_score' => round((float) $r->avg_score, 1),
                'customers' => (int) $r->customers,
            ])->values()->all(),
            'level_by_date' => $levelTrend->map(fn ($items, $date) => [
                'date' => $date,
                'healthy' => (int) ($items->firstWhere('health_level', 'healthy')?->cnt ?? 0),
                'attention' => (int) ($items->firstWhere('health_level', 'attention')?->cnt ?? 0),
                'at_risk' => (int) ($items->firstWhere('health_level', 'at_risk')?->cnt ?? 0),
                'churned' => (int) ($items->firstWhere('health_level', 'churned')?->cnt ?? 0),
            ])->values()->all(),
        ];
    }

    /**
     * 续费/到期日历（含红黄绿灯续费预测）
     */
    public function getRenewalCalendar(int $tenantId, ?string $yearMonth = null): array
    {
        $month = $yearMonth ? Carbon::parse($yearMonth . '-01') : now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $events = [];
        $summary = ['green' => 0, 'yellow' => 0, 'red' => 0];

        $subscriptions = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereBetween('next_billing_at', [$start, $end])
            ->with('customer.user:id,name,email', 'product:id,name')
            ->get();

        foreach ($subscriptions as $sub) {
            $risk = $this->resolveRenewalRiskLevel($sub->customer_id, Carbon::parse($sub->next_billing_at));
            $summary[$risk]++;
            $events[] = [
                'date' => Carbon::parse($sub->next_billing_at)->format('Y-m-d'),
                'type' => 'subscription_renewal',
                'customer_id' => $sub->customer_id,
                'customer_name' => $sub->customer?->user?->name ?? 'N/A',
                'product_name' => $sub->product?->name ?? $sub->plan,
                'amount' => (float) $sub->price,
                'currency' => $sub->currency,
                'risk_level' => $risk,
                'auto_renew' => (bool) $sub->auto_renew,
            ];
        }

        $licenses = License::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [$start, $end])
            ->with('customer.user:id,name,email', 'product:id,name')
            ->get();

        foreach ($licenses as $license) {
            $risk = $this->resolveRenewalRiskLevel($license->customer_id, Carbon::parse($license->expires_at));
            $summary[$risk]++;
            $events[] = [
                'date' => Carbon::parse($license->expires_at)->format('Y-m-d'),
                'type' => 'license_expiry',
                'customer_id' => $license->customer_id,
                'customer_name' => $license->customer?->user?->name ?? 'N/A',
                'product_name' => $license->product?->name ?? '-',
                'license_key' => $license->license_key,
                'risk_level' => $risk,
            ];
        }

        usort($events, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'year_month' => $month->format('Y-m'),
            'events' => $events,
            'summary' => $summary,
        ];
    }

    /**
     * 活动时间线（沟通 + 任务 + 健康评分）
     */
    public function getActivityTimeline(int $tenantId, ?int $customerId = null, int $limit = 50): array
    {
        $items = [];

        $commQuery = CsmCommunication::where('tenant_id', $tenantId)
            ->with('customer.user:id,name', 'user:id,name');
        if ($customerId) {
            $commQuery->where('customer_id', $customerId);
        }
        foreach ($commQuery->orderByDesc('contacted_at')->limit($limit)->get() as $comm) {
            $items[] = [
                'type' => 'communication',
                'subtype' => $comm->type,
                'title' => $comm->subject ?: CsmCommunication::TYPES[$comm->type] ?? $comm->type,
                'description' => $comm->content,
                'customer_name' => $comm->customer?->user?->name,
                'customer_id' => $comm->customer_id,
                'actor' => $comm->user?->name,
                'occurred_at' => $comm->contacted_at?->toIso8601String(),
            ];
        }

        $taskQuery = CsmTask::where('tenant_id', $tenantId)
            ->with('customer.user:id,name', 'assignee:id,name');
        if ($customerId) {
            $taskQuery->where('customer_id', $customerId);
        }
        foreach ($taskQuery->orderByDesc('updated_at')->limit($limit)->get() as $task) {
            $items[] = [
                'type' => 'task',
                'subtype' => $task->status,
                'title' => $task->title,
                'description' => $task->description,
                'customer_name' => $task->customer?->user?->name,
                'customer_id' => $task->customer_id,
                'actor' => $task->assignee?->name,
                'occurred_at' => ($task->completed_at ?? $task->updated_at)?->toIso8601String(),
            ];
        }

        $healthQuery = CsmHealthScore::where('tenant_id', $tenantId)
            ->with('customer.user:id,name');
        if ($customerId) {
            $healthQuery->where('customer_id', $customerId);
        }
        foreach ($healthQuery->orderByDesc('calculated_at')->limit($limit)->get() as $score) {
            $items[] = [
                'type' => 'health_score',
                'subtype' => $score->health_level,
                'title' => "健康评分 {$score->health_score}",
                'description' => $score->summary,
                'customer_name' => $score->customer?->user?->name,
                'customer_id' => $score->customer_id,
                'occurred_at' => $score->calculated_at?->toIso8601String(),
            ];
        }

        usort($items, fn ($a, $b) => strcmp($b['occurred_at'] ?? '', $a['occurred_at'] ?? ''));

        return array_slice($items, 0, $limit);
    }

    protected function resolveRenewalRiskLevel(int $customerId, Carbon $eventDate): string
    {
        $daysUntil = (int) now()->startOfDay()->diffInDays($eventDate->startOfDay(), false);

        $health = CsmHealthScore::where('customer_id', $customerId)
            ->orderByDesc('calculated_at')
            ->first();

        $churn = ChurnPrediction::where('customer_id', $customerId)
            ->orderByDesc('predicted_at')
            ->first();

        if ($daysUntil <= 7
            || in_array($health?->health_level, ['at_risk', 'churned'], true)
            || ($churn?->churn_risk === 'high')) {
            return 'red';
        }

        if ($daysUntil <= 14
            || $health?->health_level === 'attention'
            || ($churn?->churn_risk === 'medium')) {
            return 'yellow';
        }

        return 'green';
    }
}
