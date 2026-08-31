<template>
    <div class="revenue-dashboard">
        <div class="page-header">
            <div>
                <h2>{{ t('revenue_page.title') }}</h2>
                <p class="text-muted">{{ t('revenue_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-radio-group v-model="trendMonths" size="small" @change="loadAll" class="mr-3">
                    <el-radio-button :value="12">{{ t('revenue_page.months.m12') }}</el-radio-button>
                    <el-radio-button :value="24">{{ t('revenue_page.months.m24') }}</el-radio-button>
                    <el-radio-button :value="36">{{ t('revenue_page.months.m36') }}</el-radio-button>
                </el-radio-group>
                <el-button type="primary" @click="loadAll" :loading="loading" :icon="Refresh">{{ t('revenue_page.refresh') }}</el-button>
            </div>
        </div>

        <!-- 第一行：关键财务指标 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('revenue_page.metrics.revenue_month') }}</div>
                            <div class="metric-value">¥{{ formatMoney(overview.revenue.month) }}</div>
                            <div class="metric-sub">{{ t('revenue_page.metrics.cumulative') }} ¥{{ formatMoney(overview.revenue.total) }}</div>
                        </div>
                        <el-icon :size="36" color="#0f172a"><TrendCharts /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('revenue_page.metrics.net_revenue_month') }}</div>
                            <div class="metric-value" :class="overview.net_revenue.month >= 0 ? 'success' : 'danger'">
                                ¥{{ formatMoney(overview.net_revenue.month) }}
                            </div>
                            <div class="metric-sub">{{ t('revenue_page.metrics.net_margin_rate', { rate: calcRate(overview.net_revenue.month, overview.revenue.month) }) }}</div>
                        </div>
                        <el-icon :size="36" :color="overview.net_revenue.month >= 0 ? '#67c23a' : '#f56c6c'"><Money /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('revenue_page.metrics.mrr') }}</div>
                            <div class="metric-value">¥{{ formatMoney(overview.mrr) }}</div>
                            <div class="metric-sub">{{ t('revenue_page.metrics.arr') }} ¥{{ formatMoney(overview.arr) }}</div>
                        </div>
                        <el-icon :size="36" color="#b37feb"><Coin /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('revenue_page.metrics.active_subs_agents') }}</div>
                            <div class="metric-value">{{ overview.subscriptions.active }} / {{ overview.active_agents }}</div>
                            <div class="metric-sub">{{ t('revenue_page.metrics.subs_new_churn', { new: overview.subscriptions.new, churned: overview.subscriptions.churned }) }}</div>
                        </div>
                        <el-icon :size="36" color="#e6a23c"><User /></el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第二行：月收入趋势 + 支付方式分布 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="16">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><TrendCharts /></el-icon> {{ t('revenue_page.sections.revenue_trend') }} <small class="text-muted">{{ t('revenue_page.sections.revenue_trend_hint') }}</small></span>
                        </div>
                    </template>
                    <div v-loading="loading" class="trend-table-wrap">
                        <el-table :data="revenueTrend" stripe size="small" max-height="350" v-if="revenueTrend.length">
                            <el-table-column prop="period" :label="cols.period" width="90" />
                            <el-table-column :label="cols.revenue" width="130">
                                <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                            </el-table-column>
                            <el-table-column :label="cols.commission_expense" width="120">
                                <template #default="{ row }">
                                    <span class="text-warning">¥{{ formatMoney(row.commission) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="cols.refunds" width="110">
                                <template #default="{ row }">
                                    <span class="text-danger">¥{{ formatMoney(row.refunds) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="cols.net_revenue" width="130">
                                <template #default="{ row }">
                                    <span :class="row.net_revenue >= 0 ? 'text-success' : 'text-danger'">
                                        ¥{{ formatMoney(row.net_revenue) }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="cols.transaction_count" width="90" prop="transaction_count" />
                        </el-table>
                        <el-empty v-else :description="t('messages.no_data')" />
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><Coin /></el-icon> {{ t('revenue_page.sections.payment_methods') }}</span>
                        </div>
                    </template>
                    <div v-loading="loading" class="payment-methods">
                        <div v-for="m in paymentMethods.methods" :key="m.method" class="payment-row">
                            <div class="payment-label">{{ m.method_label }}</div>
                            <div class="payment-bar-bg">
                                <div class="payment-bar" :style="{ width: m.percentage + '%', background: methodColor(m.method) }"></div>
                            </div>
                            <div class="payment-value">{{ m.percentage }}%</div>
                            <div class="payment-amount">¥{{ formatMoney(m.total) }}</div>
                        </div>
                        <el-empty v-if="!paymentMethods.methods.length" :description="t('revenue_page.empty.no_payment_data')" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第三行：渠道ROI分析 -->
        <el-card class="mb-4" shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Link /></el-icon> {{ t('revenue_page.sections.channel_roi') }}</span>
                    <el-tag type="info" size="small">{{ t('revenue_page.sections.overall_roi', { roi: channelRoi.overall.roi > 999 ? '∞' : channelRoi.overall.roi + '%' }) }}</el-tag>
                </div>
            </template>
            <div v-loading="loading">
                <el-table :data="channelRoi.channels" stripe v-if="channelRoi.channels.length">
                    <el-table-column :label="cols.channel" min-width="120">
                        <template #default="{ row }">
                            <div class="channel-name">
                                <div class="channel-dot" :style="{ background: row.color }"></div>
                                <span>{{ row.name }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="cols.revenue" width="140">
                        <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.refunds" width="110">
                        <template #default="{ row }">¥{{ formatMoney(row.refunds) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.commission" width="120">
                        <template #default="{ row }">¥{{ formatMoney(row.commission) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.net_revenue" width="140">
                        <template #default="{ row }">
                            <span :class="row.net_revenue >= 0 ? '' : 'text-danger'">¥{{ formatMoney(row.net_revenue) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="cols.roi" width="100">
                        <template #default="{ row }">
                            <el-tag :type="roiType(row.roi)" size="small" effect="dark">
                                {{ row.roi > 999 ? '∞' : row.roi + '%' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="cols.arpu" width="120">
                        <template #default="{ row }">¥{{ formatMoney(row.arpu) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.agent_count" width="80" prop="agent_count" />
                    <el-table-column :label="cols.subscription_count" width="130">
                        <template #default="{ row }">{{ row.active_subscription_count }}/{{ row.subscription_count }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-else :description="t('revenue_page.empty.no_channel_data')" />

                <!-- 汇总行 -->
                <div class="overall-row" v-if="channelRoi.overall">
                    <div class="overall-label">{{ t('revenue_page.summary') }}</div>
                    <div class="overall-value">¥{{ formatMoney(channelRoi.overall.revenue) }}</div>
                    <div class="overall-value">¥{{ formatMoney(channelRoi.overall.refunds) }}</div>
                    <div class="overall-value">¥{{ formatMoney(channelRoi.overall.commission) }}</div>
                    <div class="overall-value" :class="channelRoi.overall.net_revenue >= 0 ? '' : 'text-danger'">
                        ¥{{ formatMoney(channelRoi.overall.net_revenue) }}
                    </div>
                    <div class="overall-value">
                        <el-tag :type="roiType(channelRoi.overall.roi)" size="small">
                            {{ channelRoi.overall.roi > 999 ? '∞' : channelRoi.overall.roi + '%' }}
                        </el-tag>
                    </div>
                    <div class="overall-value">-</div>
                    <div class="overall-value">{{ channelRoi.overall.agent_count }}</div>
                    <div class="overall-value">{{ channelRoi.overall.subscription_count }}</div>
                </div>
            </div>
        </el-card>

        <!-- 第四行：渠道月度趋势 + 渠道质量 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><DataBoard /></el-icon> {{ t('revenue_page.sections.channel_trend') }}</span>
                            <el-radio-group v-model="trendChannel" size="small" @change="onTrendChannelChange">
                                <el-radio-button v-for="c in channelRoi.channels" :key="c.name" :value="c.name">
                                    {{ c.name }}
                                </el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div v-loading="loading" class="trend-table-wrap">
                        <el-table :data="trendChannelData" stripe size="small" max-height="350" v-if="trendChannelData.length">
                            <el-table-column prop="period" :label="cols.period" width="90" />
                            <el-table-column :label="cols.revenue" width="140">
                                <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                            </el-table-column>
                            <el-table-column :label="cols.commission" width="120">
                                <template #default="{ row }">¥{{ formatMoney(row.commission) }}</template>
                            </el-table-column>
                            <el-table-column :label="cols.net_revenue" width="140">
                                <template #default="{ row }">
                                    <span :class="row.net_revenue >= 0 ? '' : 'text-danger'">¥{{ formatMoney(row.net_revenue) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-else :description="t('messages.no_data')" />
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><DataAnalysis /></el-icon> {{ t('revenue_page.sections.channel_quality') }}</span>
                        </div>
                    </template>
                    <div v-loading="loading">
                        <el-table :data="channelQuality" stripe size="small" v-if="channelQuality.length">
                            <el-table-column :label="cols.channel" width="90">
                                <template #default="{ row }">{{ row.channel_name }}</template>
                            </el-table-column>
                            <el-table-column :label="cols.customers" width="80" prop="total_customers" />
                            <el-table-column :label="cols.ltv" width="100">
                                <template #default="{ row }">¥{{ formatMoney(row.avg_ltv) }}</template>
                            </el-table-column>
                            <el-table-column :label="cols.churn_rate" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.churn_rate > 30 ? 'danger' : row.churn_rate > 15 ? 'warning' : 'success'" size="small">
                                        {{ row.churn_rate }}%
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="cols.renewal_rate" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.renewal_rate > 50 ? 'success' : row.renewal_rate > 20 ? 'warning' : 'danger'" size="small">
                                        {{ row.renewal_rate }}%
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="cols.subscription_days" width="90" prop="avg_subscription_days" />
                        </el-table>
                        <el-empty v-else :description="t('messages.no_data')" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第五行：代理层级收益分布 -->
        <el-card shadow="hover" v-if="agentLevels.length">
            <template #header>
                <div class="card-header">
                    <span><el-icon><User /></el-icon> {{ t('revenue_page.sections.agent_levels') }}</span>
                </div>
            </template>
            <el-table :data="agentLevels" stripe v-loading="loading">
                <el-table-column :label="cols.level" width="120">
                    <template #default="{ row }">
                        <div class="channel-name">
                            <div class="channel-dot" :style="{ background: levelColor(row.level) }"></div>
                            <span>{{ row.level_label }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="cols.agent_count" width="80" prop="agent_count" />
                <el-table-column :label="cols.total_commission" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.total_commission) }}</template>
                </el-table-column>
                <el-table-column :label="cols.withdrawn" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.total_payout) }}</template>
                </el-table-column>
                <el-table-column :label="cols.avg_commission" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.avg_commission_per_agent) }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 第六行：MRR 瀑布图 (M3-59) -->
        <el-card class="mb-4" shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Coin /></el-icon> {{ t('revenue_page.sections.mrr_waterfall') }} <small class="text-muted">{{ t('revenue_page.sections.mrr_waterfall_hint') }}</small></span>
                </div>
            </template>
            <MrrWaterfallChart
                :chartData="mrrWaterfallData"
                :loading="mrrLoading"
                :showControls="true"
                @refresh="loadMrrWaterfall"
            />
        </el-card>

        <!-- MRR 汇总 + 下钻 -->
        <el-row :gutter="16" class="mb-4" v-if="mrrSummaryData">
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">{{ t('revenue_page.metrics.mrr') }}</div>
                            <div class="metric-value">¥{{ formatMoney(mrrSummaryData.mrr) }}</div>
                        </div>
                        <el-icon :size="36" color="#b37feb"><Coin /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card shadow="hover">
                    <template #header><span style="font-size:13px;font-weight:500">{{ t('revenue_page.sections.mrr_composition') }}</span></template>
                    <div class="mrr-composition">
                        <div class="mrr-comp-item" v-if="mrrSummaryData.new_mrr">
                            <span class="mrr-dot" style="background:#67c23a"></span>
                            <span>{{ t('revenue_page.mrr.new') }}</span>
                            <span class="mrr-val text-success">+¥{{ formatMoney(mrrSummaryData.new_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.expansion_mrr">
                            <span class="mrr-dot" style="background:#0f172a"></span>
                            <span>{{ t('revenue_page.mrr.expansion') }}</span>
                            <span class="mrr-val text-success">+¥{{ formatMoney(mrrSummaryData.expansion_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.contraction_mrr">
                            <span class="mrr-dot" style="background:#e6a23c"></span>
                            <span>{{ t('revenue_page.mrr.contraction') }}</span>
                            <span class="mrr-val text-warning">-¥{{ formatMoney(mrrSummaryData.contraction_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.churned_mrr">
                            <span class="mrr-dot" style="background:#f56c6c"></span>
                            <span>{{ t('revenue_page.mrr.churn') }}</span>
                            <span class="mrr-val text-danger">-¥{{ formatMoney(mrrSummaryData.churned_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.reactivation_mrr">
                            <span class="mrr-dot" style="background:#909399"></span>
                            <span>{{ t('revenue_page.mrr.reactivation') }}</span>
                            <span class="mrr-val text-success">+¥{{ formatMoney(mrrSummaryData.reactivation_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-divider"></div>
                        <div class="mrr-comp-item">
                            <span style="font-weight:600">{{ t('revenue_page.mrr.net_change') }}</span>
                            <span class="mrr-val" :class="mrrSummaryData.net_mrr_change >= 0 ? 'text-success' : 'text-danger'">
                                {{ mrrSummaryData.net_mrr_change >= 0 ? '+' : '' }}¥{{ formatMoney(mrrSummaryData.net_mrr_change) }}
                            </span>
                        </div>
                        <div class="mrr-comp-item">
                            <span style="font-weight:600">{{ t('revenue_page.mrr.active_subscriptions') }}</span>
                            <span class="mrr-val">{{ mrrSummaryData.total_subscriptions }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- MRR 变化下钻明细 -->
        <el-card shadow="hover" class="mb-4">
            <MrrDrilldownPanel yearMonth="" :changeType="mrrFilterType" />
        </el-card>

        <!-- 第七行：代理商收益排行榜 (M3-73) -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><User /></el-icon> {{ t('revenue_page.sections.leaderboard', { limit: leaderboardLimit }) }}</span>
                    <el-select v-model="leaderboardLimit" size="small" style="width:100px" @change="loadLeaderboard">
                        <el-option :value="10" label="Top 10" />
                        <el-option :value="20" label="Top 20" />
                        <el-option :value="50" label="Top 50" />
                    </el-select>
                </div>
            </template>
            <el-table :data="leaderboardData" stripe v-loading="leaderboardLoading" size="small">
                <el-table-column :label="cols.rank" width="60" type="index" />
                <el-table-column :label="cols.name" min-width="140">
                    <template #default="{ row }">{{ row.name }}</template>
                </el-table-column>
                <el-table-column prop="agent_code" :label="cols.code" width="120" />
                <el-table-column :label="cols.level" width="80">
                    <template #default="{ row }">
                        <el-tag :color="levelColor(row.level)" class="text-white" size="small">{{ row.level_label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="cols.total_earned" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.total_earned) }}</template>
                </el-table-column>
                <el-table-column :label="cols.withdrawn" width="130">
                    <template #default="{ row }">¥{{ formatMoney(row.total_withdrawn) }}</template>
                </el-table-column>
                <el-table-column :label="cols.available_balance" width="130">
                    <template #default="{ row }">¥{{ formatMoney(row.available_balance) }}</template>
                </el-table-column>
                <el-table-column prop="downline_count" :label="cols.downline" width="60" />
                <el-table-column :label="cols.commission_rate" width="70">
                    <template #default="{ row }">{{ row.revenue_share }}%</template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!leaderboardData.length && !leaderboardLoading" :description="t('revenue_page.empty.no_leaderboard')" />
        </el-card>

        <!-- 第八行：月度结算报表 (M3-73) -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><FolderOpened /></el-icon> {{ t('revenue_page.sections.monthly_report') }}</span>
                    <el-date-picker v-model="reportMonth" type="month" value-format="YYYY-MM" style="width:150px" @change="loadMonthlyReport" />
                </div>
            </template>
            <div v-loading="reportLoading">
                <el-descriptions :column="4" border size="small" class="mb-3" v-if="monthlyReport">
                    <el-descriptions-item :label="cols.month">{{ monthlyReport.year_month }}</el-descriptions-item>
                    <el-descriptions-item :label="cols.revenue">¥{{ formatMoney(monthlyReport.revenue) }}</el-descriptions-item>
                    <el-descriptions-item :label="cols.refunds">¥{{ formatMoney(monthlyReport.refunds) }}</el-descriptions-item>
                    <el-descriptions-item :label="cols.commission_expense">¥{{ formatMoney(monthlyReport.commissions) }}</el-descriptions-item>
                    <el-descriptions-item :label="cols.payouts">¥{{ formatMoney(monthlyReport.payouts) }}</el-descriptions-item>
                    <el-descriptions-item :label="cols.net_revenue">
                        <span :class="monthlyReport.net_revenue >= 0 ? 'text-success' : 'text-danger'">
                            ¥{{ formatMoney(monthlyReport.net_revenue) }}
                        </span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="cols.mom_growth">
                        <el-tag :type="monthlyReport.growth_rate >= 0 ? 'success' : 'danger'" size="small">
                            {{ monthlyReport.growth_rate >= 0 ? '+' : '' }}{{ monthlyReport.growth_rate }}%
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="cols.new_active_subs">{{ monthlyReport.new_subscriptions }}/{{ monthlyReport.active_subscriptions }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="mb-2" v-if="monthlyReport?.channel_breakdown?.length">{{ t('revenue_page.sections.channel_breakdown') }}</h4>
                <el-table :data="monthlyReport?.channel_breakdown ?? []" stripe size="small" v-if="monthlyReport?.channel_breakdown?.length">
                    <el-table-column :label="cols.channel" min-width="100">
                        <template #default="{ row }">{{ row.name }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.revenue" width="130">
                        <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.commission" width="120">
                        <template #default="{ row }">¥{{ formatMoney(row.commission) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.net_revenue" width="130">
                        <template #default="{ row }">¥{{ formatMoney(row.net_revenue) }}</template>
                    </el-table-column>
                    <el-table-column :label="cols.roi" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.roi > 0 ? 'success' : 'danger'" size="small">{{ row.roi }}%</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!monthlyReport" :description="t('revenue_page.empty.select_month_report')" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
  Refresh, TrendCharts, Money, Coin, User,
  Link, DataBoard, DataAnalysis, FolderOpened,
} from '@element-plus/icons-vue';
import revenueApi from '@/api/revenueDashboard';
import MrrWaterfallChart from '@/components/MrrWaterfallChart.vue';
import MrrDrilldownPanel from '@/components/MrrDrilldownPanel.vue';

const { t, locale } = useI18n();

const loading = ref(false);
const trendMonths = ref(24);
const trendChannel = ref('');

const cols = computed(() => ({
    period: t('revenue_page.cols.period'),
    revenue: t('revenue_page.cols.revenue'),
    commission: t('revenue_page.cols.commission'),
    commission_expense: t('revenue_page.cols.commission_expense'),
    refunds: t('revenue_page.cols.refunds'),
    net_revenue: t('revenue_page.cols.net_revenue'),
    transaction_count: t('revenue_page.cols.transaction_count'),
    channel: t('revenue_page.cols.channel'),
    roi: t('revenue_page.cols.roi'),
    arpu: t('revenue_page.cols.arpu'),
    agent_count: t('revenue_page.cols.agent_count'),
    subscription_count: t('revenue_page.cols.subscription_count'),
    customers: t('revenue_page.cols.customers'),
    ltv: t('revenue_page.cols.ltv'),
    churn_rate: t('revenue_page.cols.churn_rate'),
    renewal_rate: t('revenue_page.cols.renewal_rate'),
    subscription_days: t('revenue_page.cols.subscription_days'),
    level: t('revenue_page.cols.level'),
    total_commission: t('revenue_page.cols.total_commission'),
    withdrawn: t('revenue_page.cols.withdrawn'),
    avg_commission: t('revenue_page.cols.avg_commission'),
    rank: t('revenue_page.cols.rank'),
    name: t('revenue_page.cols.name'),
    code: t('revenue_page.cols.code'),
    total_earned: t('revenue_page.cols.total_earned'),
    available_balance: t('revenue_page.cols.available_balance'),
    downline: t('revenue_page.cols.downline'),
    commission_rate: t('revenue_page.cols.commission_rate'),
    month: t('revenue_page.cols.month'),
    payouts: t('revenue_page.cols.payouts'),
    mom_growth: t('revenue_page.cols.mom_growth'),
    new_active_subs: t('revenue_page.cols.new_active_subs'),
}));

// MRR 瀑布图 (M3-59)
const mrrWaterfallData = ref([]);
const mrrLoading = ref(false);
const mrrSummaryData = ref(null);
const mrrFilterType = ref('');

const overview = reactive({
  revenue: { total: 0, month: 0, year: 0 },
  refunds: { total: 0, month: 0, refund_rate: 0 },
  commissions: { total: 0, month: 0, commission_rate: 0 },
  payouts: { total: 0, month: 0 },
  net_revenue: { total: 0, month: 0 },
  mrr: 0,
  arr: 0,
  subscriptions: { active: 0, new: 0, churned: 0 },
  active_agents: 0,
});

const channelRoi = reactive({
  channels: [],
  overall: null,
  definitions: {},
});

const channelTrend = ref({});
const channelQuality = ref([]);
const revenueTrend = ref([]);
const paymentMethods = reactive({ methods: [], total_amount: 0, total_count: 0 });
const agentLevels = ref([]);

// M3-73 代理商排行榜
const leaderboardData = ref([]);
const leaderboardLoading = ref(false);
const leaderboardLimit = ref(20);

// M3-73 月度结算报表
const monthlyReport = ref(null);
const reportLoading = ref(false);
const reportMonth = ref('');

const trendChannelData = computed(() => {
  if (!trendChannel.value || !channelTrend.value[trendChannel.value]) return [];
  return channelTrend.value[trendChannel.value];
});

function formatMoney(val) {
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  return (parseFloat(val) || 0).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function calcRate(numerator, denominator) {
  if (!denominator) return 0;
  return ((numerator / denominator) * 100).toFixed(1);
}

function roiType(val) {
  if (val > 999) return 'success';
  if (val > 100) return 'success';
  if (val > 0) return 'warning';
  return 'danger';
}

function methodColor(method) {
  const colors = {
    alipay: '#00a1e9',
    wechat: '#07c160',
    paypal: '#003087',
    stripe: '#635bff',
    bank_transfer: '#909399',
    balance: '#e6a23c',
  };
  return colors[method] || '#0f172a';
}

function levelColor(level) {
  const colors = { regular: '#909399', silver: '#909399', gold: '#e6a23c', platinum: '#0f172a' };
  return colors[level] || '#909399';
}

function onTrendChannelChange() {
  // computed handles it
}

async function loadAll() {
  loading.value = true;
  try {
    const [
      overviewRes, channelRoiRes, channelTrendRes,
      qualityRes, trendRes, paymentRes, agentRes,
    ] = await Promise.all([
      revenueApi.overview(),
      revenueApi.channelRoi(),
      revenueApi.channelTrend({ months: trendMonths.value }),
      revenueApi.channelQuality(),
      revenueApi.revenueTrend({ months: trendMonths.value }),
      revenueApi.paymentMethods(),
      revenueApi.agentLevels(),
    ]);

    Object.assign(overview, overviewRes.data.data);
    Object.assign(channelRoi, channelRoiRes.data.data);
    channelTrend.value = channelTrendRes.data.data || {};
    channelQuality.value = qualityRes.data.data || [];
    revenueTrend.value = trendRes.data.data || [];
    Object.assign(paymentMethods, paymentRes.data.data);
    agentLevels.value = agentRes.data.data || [];

    if (!trendChannel.value && channelRoi.channels.length) {
      trendChannel.value = channelRoi.channels[0].name;
    }
  } catch (e) {
    ElMessage.error(t('messages.load_failed'));
  } finally {
    loading.value = false;
  }

  loadMrrWaterfall(6);
  loadMrrSummary();
}

async function loadLeaderboard() {
  leaderboardLoading.value = true;
  try {
    const res = await revenueApi.agentLeaderboard({ limit: leaderboardLimit.value });
    leaderboardData.value = res.data.data || [];
  } catch (e) {
    console.error(e);
  } finally {
    leaderboardLoading.value = false;
  }
}

async function loadMonthlyReport() {
  if (!reportMonth.value) return;
  reportLoading.value = true;
  try {
    const res = await revenueApi.monthlyReport({ year_month: reportMonth.value });
    monthlyReport.value = res.data.data;
  } catch (e) {
    ElMessage.error(t('revenue_page.messages.load_report_failed'));
  } finally {
    reportLoading.value = false;
  }
}

async function loadMrrWaterfall(months) {
  mrrLoading.value = true;
  try {
    const { getMrrWaterfall } = await import('@/api/mrr.js');
    const res = await getMrrWaterfall({ months: months || 6 });
    mrrWaterfallData.value = res.data.success ? (res.data.data || []) : [];
  } catch (e) {
    mrrWaterfallData.value = [];
  } finally {
    mrrLoading.value = false;
  }
}

async function loadMrrSummary() {
  try {
    const { getMrrSummary } = await import('@/api/mrr.js');
    const res = await getMrrSummary({ year_month: '' });
    mrrSummaryData.value = res.data.success ? (res.data.data || null) : null;
  } catch (e) {
    mrrSummaryData.value = null;
  }
}

onMounted(() => {
  loadAll();
  loadLeaderboard();
  reportMonth.value = new Date().toISOString().slice(0, 7);
  loadMonthlyReport();
});
</script>

<style scoped>
.revenue-dashboard {
  max-width: 1400px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}

.page-header h2 {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
}

.text-muted {
  color: #909399;
  font-size: 13px;
  margin: 4px 0 0;
}

.header-actions {
  display: flex;
  align-items: center;
}

.mr-3 {
  margin-right: 12px;
}

.mb-4 {
  margin-bottom: 16px;
}

.metric-card .metric-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.metric-label {
  font-size: 13px;
  color: #909399;
  margin-bottom: 4px;
}

.metric-value {
  font-size: 22px;
  font-weight: 700;
  line-height: 1.2;
  color: #303133;
}

.metric-value.success { color: #67c23a; }
.metric-value.danger { color: #f56c6c; }

.metric-sub {
  font-size: 12px;
  color: #909399;
  margin-top: 4px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}

.card-header span {
  display: flex;
  align-items: center;
  gap: 6px;
  font-weight: 600;
}

.card-header small {
  font-weight: 400;
}

.channel-name {
  display: flex;
  align-items: center;
  gap: 8px;
}

.channel-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Payment methods */
.payment-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}

.payment-label {
  width: 70px;
  font-size: 13px;
  color: #606266;
  flex-shrink: 0;
}

.payment-bar-bg {
  flex: 1;
  height: 18px;
  background: #f0f2f5;
  border-radius: 4px;
  overflow: hidden;
}

.payment-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s;
}

.payment-value {
  width: 48px;
  text-align: right;
  font-size: 13px;
  font-weight: 600;
  color: #303133;
}

.payment-amount {
  width: 80px;
  text-align: right;
  font-size: 12px;
  color: #909399;
}

/* Overall row */
.overall-row {
  display: flex;
  align-items: center;
  padding: 10px 16px;
  background: #f5f7fa;
  border-radius: 4px;
  margin-top: 8px;
  font-weight: 600;
  font-size: 13px;
}

.overall-label {
  width: 120px;
  flex-shrink: 0;
}

.overall-value {
  width: 140px;
  text-align: right;
}

.overall-value:first-of-type { width: 110px; }
.overall-value:nth-of-type(5) { width: 100px; }
.overall-value:nth-of-type(6) { width: 120px; }
.overall-value:nth-of-type(7) { width: 80px; }
.overall-value:nth-of-type(8) { width: 130px; }

.text-success { color: #67c23a; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }

.trend-table-wrap {
  overflow-x: auto;
}
</style>
