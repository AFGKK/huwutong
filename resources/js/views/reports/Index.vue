<template>
    <div class="report-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <el-button @click="refreshAll" :icon="Refresh" :loading="loading" circle size="small" />
        </div>

        <el-tabs v-model="reportCenterTab" type="border-card">
            <!-- ═══ 营收报表 ═══ -->
            <el-tab-pane :label="t(`${P}.tab_revenue`)" name="revenue">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never" class="metric-card">
                            <div class="metric-label">{{ t(`${P}.mrr`) }}</div>
                            <div class="metric-value primary">¥{{ formatNum(dashboard.mrr) }}</div>
                            <div class="metric-sub">
                                ARR: ¥{{ formatNum(dashboard.arr) }}
                                <el-tag size="small" type="success" class="ml-2">{{ t(`${P}.annualized`) }}</el-tag>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never" class="metric-card">
                            <div class="metric-label">{{ t(`${P}.month_revenue`) }}</div>
                            <div class="metric-value success">¥{{ formatNum(dashboard.month_revenue) }}</div>
                            <div class="metric-sub">{{ t(`${P}.cumulative`) }}: ¥{{ formatNum(dashboard.total_revenue) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never" class="metric-card">
                            <div class="metric-label">{{ t(`${P}.active_subs`) }}</div>
                            <div class="metric-value primary">{{ dashboard.subscriptions?.active || 0 }}</div>
                            <div class="metric-sub">
                                {{ t(`${P}.total`) }}: {{ dashboard.subscriptions?.total || 0 }}
                                <el-tag v-if="dashboard.subscriptions?.grace > 0" size="small" type="warning" class="ml-2">
                                    {{ t(`${P}.grace_n`, { n: dashboard.subscriptions?.grace }) }}
                                </el-tag>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never" class="metric-card">
                            <div class="metric-label">{{ t(`${P}.active_customers`) }}</div>
                            <div class="metric-value success">{{ dashboard.customers?.active || 0 }}</div>
                            <div class="metric-sub">{{ t(`${P}.total`) }}: {{ dashboard.customers?.total || 0 }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header><span>{{ t(`${P}.mrr_breakdown`) }}</span></template>
                            <div v-loading="loading">
                                <div v-for="(value, key) in dashboard.mrr_breakdown" :key="key" class="mrr-row">
                                    <span class="mrr-label">{{ periodLabel(key) }}</span>
                                    <div class="mrr-bar-bg">
                                        <div class="mrr-bar" :style="{ width: mrrBarWidth(value) + '%', background: mrrColor(key) }"></div>
                                    </div>
                                    <span class="mrr-value">¥{{ formatNum(value) }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="16">
                        <el-card shadow="never">
                            <template #header>
                                <div class="section-header">
                                    <span>{{ t(`${P}.revenue_trend`) }}</span>
                                    <el-radio-group v-model="trendPeriod" size="small" @change="fetchRevenueTrend">
                                        <el-radio-button value="monthly">{{ t(`${P}.by_month`) }}</el-radio-button>
                                        <el-radio-button value="daily">{{ t(`${P}.by_day`) }}</el-radio-button>
                                    </el-radio-group>
                                </div>
                            </template>
                            <div v-loading="loadingTrend">
                                <div v-if="revenueTrend.length > 0" class="trend-chart">
                                    <div v-for="(item, idx) in revenueTrend" :key="idx" class="trend-bar-group">
                                        <div class="trend-bar-wrapper">
                                            <div class="trend-bar" :style="{ height: trendBarHeight(item.revenue) + 'px' }"
                                                :title="item.period + ': ¥' + formatNum(item.revenue)"></div>
                                        </div>
                                        <div class="trend-label">{{ formatPeriod(item.period) }}</div>
                                        <div class="trend-value">¥{{ formatNum(item.revenue) }}</div>
                                        <div v-if="item.growth_rate" class="trend-growth" :class="item.growth_rate >= 0 ? 'up' : 'down'">
                                            {{ item.growth_rate > 0 ? '+' : '' }}{{ item.growth_rate }}%
                                        </div>
                                    </div>
                                </div>
                                <el-empty v-else :description="t(`${P}.empty_revenue`)" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t(`${P}.sub_analytics`) }}</span></template>
                            <div v-loading="loading">
                                <el-descriptions :column="2" border size="small">
                                    <el-descriptions-item :label="t(`${P}.desc.total_subs`)">{{ analytics.subscriptions?.total || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.active_subs`)">{{ analytics.subscriptions?.active || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.grace`)">{{ analytics.subscriptions?.grace || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.expired`)">{{ analytics.subscriptions?.expired || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.canceled`)">{{ analytics.subscriptions?.canceled || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.expiring_30d`)">{{ subAnalytics.expiring_soon_30d || '-' }}</el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.avg_days`)">
                                        {{ subAnalytics.avg_subscription_days ? t(`${P}.days_n`, { n: subAnalytics.avg_subscription_days }) : '-' }}
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t(`${P}.desc.total_plans`)">{{ dashboard.total_plans || '-' }}</el-descriptions-item>
                                </el-descriptions>

                                <el-divider>{{ t(`${P}.by_billing`) }}</el-divider>
                                <el-table :data="subAnalytics.by_period || []" size="small" stripe>
                                    <el-table-column prop="billing_period" :label="t(`${P}.cols.period`)">
                                        <template #default="{ row }">{{ periodLabel(row.billing_period) }}</template>
                                    </el-table-column>
                                    <el-table-column prop="count" :label="t(`${P}.cols.count`)" align="right" />
                                    <el-table-column prop="total_value" :label="t(`${P}.cols.total_value`)" align="right">
                                        <template #default="{ row }">¥{{ formatNum(row.total_value) }}</template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t(`${P}.plan_dist`) }}</span></template>
                            <div v-loading="loading">
                                <el-table :data="planDistribution" size="small" stripe v-if="planDistribution.length > 0">
                                    <el-table-column prop="name" :label="t(`${P}.cols.plan`)" min-width="100" />
                                    <el-table-column prop="subscriber_count" :label="t(`${P}.cols.subscribers`)" align="right" width="90" />
                                    <el-table-column :label="t(`${P}.cols.monthly_rev`)" align="right" width="120">
                                        <template #default="{ row }">¥{{ formatNum(row.revenue_monthly) }}</template>
                                    </el-table-column>
                                    <el-table-column label="ARPU" align="right" width="100">
                                        <template #default="{ row }">
                                            ¥{{ row.subscriber_count > 0 ? formatNum(row.revenue_monthly / row.subscriber_count) : '-' }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-empty v-else :description="t(`${P}.empty_plans`)" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t(`${P}.ltv_title`) }}</span></template>
                            <div v-loading="loading">
                                <el-row :gutter="12" class="mb-3">
                                    <el-col :span="8">
                                        <el-statistic :title="t(`${P}.ltv.avg`)" :value="ltv.average_ltv" :precision="2" prefix="¥" />
                                    </el-col>
                                    <el-col :span="8">
                                        <el-statistic :title="t(`${P}.ltv.median`)" :value="ltv.median_ltv" :precision="2" prefix="¥" />
                                    </el-col>
                                    <el-col :span="8">
                                        <el-statistic :title="t(`${P}.ltv.max`)" :value="ltv.max_ltv" :precision="2" prefix="¥" />
                                    </el-col>
                                </el-row>
                                <el-row :gutter="12" class="mb-3">
                                    <el-col :span="8">
                                        <el-card shadow="never" class="tier-card">
                                            <div class="tier-value">¥10,000+</div>
                                            <div class="tier-count">{{ t(`${P}.people_n`, { n: ltv.tiers?.high || 0 }) }}</div>
                                        </el-card>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-card shadow="never" class="tier-card">
                                            <div class="tier-value">¥1,000-9,999</div>
                                            <div class="tier-count">{{ t(`${P}.people_n`, { n: ltv.tiers?.medium || 0 }) }}</div>
                                        </el-card>
                                    </el-col>
                                    <el-col :span="8">
                                        <el-card shadow="never" class="tier-card">
                                            <div class="tier-value">¥1-999</div>
                                            <div class="tier-count">{{ t(`${P}.people_n`, { n: ltv.tiers?.low || 0 }) }}</div>
                                        </el-card>
                                    </el-col>
                                </el-row>
                                <el-divider>{{ t(`${P}.top_customers`) }}</el-divider>
                                <el-table :data="ltv.top_customers || []" size="small" stripe max-height="250">
                                    <el-table-column prop="name" :label="t(`${P}.cols.customer`)" min-width="100" />
                                    <el-table-column prop="email" :label="t(`${P}.cols.email`)" min-width="150" />
                                    <el-table-column prop="total_paid" :label="t(`${P}.cols.total_paid`)" align="right">
                                        <template #default="{ row }">¥{{ formatNum(row.total_paid) }}</template>
                                    </el-table-column>
                                    <el-table-column prop="invoice_count" :label="t(`${P}.cols.invoices`)" align="right" width="80" />
                                </el-table>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <template #header><span>{{ t(`${P}.churn_title`) }}</span></template>
                            <div v-loading="loading">
                                <el-table :data="churnData" size="small" stripe v-if="churnData.length > 0" max-height="400">
                                    <el-table-column prop="label" :label="t(`${P}.cols.month`)" width="100" />
                                    <el-table-column prop="start_active" :label="t(`${P}.cols.start_active`)" align="right" width="90" />
                                    <el-table-column prop="new_subscriptions" :label="t(`${P}.cols.new`)" align="right" width="70" />
                                    <el-table-column prop="churned" :label="t(`${P}.cols.churned`)" align="right" width="70">
                                        <template #default="{ row }">
                                            <span :class="row.churned > 0 ? 'text-danger' : ''">{{ row.churned }}</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t(`${P}.cols.churn_rate`)" align="right" width="80">
                                        <template #default="{ row }">
                                            <el-tag :type="churnTagType(row.churn_rate)" size="small">
                                                {{ row.churn_rate }}%
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-empty v-else :description="t(`${P}.empty_churn`)" />
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never">
                    <template #header><span>{{ t(`${P}.mrr_history`) }}</span></template>
                    <div v-loading="loadingMrt">
                        <el-table :data="mrrTrendData" size="small" stripe max-height="350" v-if="mrrTrendData.length > 0">
                            <el-table-column prop="label" :label="t(`${P}.cols.month`)" width="100" />
                            <el-table-column prop="mrr" label="MRR" align="right">
                                <template #default="{ row }">¥{{ formatNum(row.mrr) }}</template>
                            </el-table-column>
                            <el-table-column prop="arr" label="ARR" align="right">
                                <template #default="{ row }">¥{{ formatNum(row.arr) }}</template>
                            </el-table-column>
                            <el-table-column prop="active_subscriptions" :label="t(`${P}.cols.active_subs`)" align="right" />
                        </el-table>
                        <el-empty v-else :description="t(`${P}.empty_trend`)" />
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- ═══ 报表工具（懒加载） ═══ -->
            <el-tab-pane :label="t(`${P}.tab_builder`)" name="builder">
                <template v-if="rb_tabVisited">
                    <div class="rb-content">
                        <!-- 顶部统计 -->
                        <el-row :gutter="20" class="mb-4">
                            <el-col :span="6" v-for="card in rb_statCards" :key="card.key">
                                <el-card shadow="hover" class="rb-stat-card">
                                    <div class="rb-stat-content">
                                        <div class="rb-stat-icon" :class="card.iconClass">
                                            <el-icon><component :is="card.icon" /></el-icon>
                                        </div>
                                        <div class="rb-stat-info">
                                            <span class="rb-stat-value">{{ rb_dashboardStats[card.key] }}</span>
                                            <span class="rb-stat-label">{{ card.label }}</span>
                                        </div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <!-- 主标签页：报表生成 / 报表调度 -->
                        <el-tabs v-model="rb_rptMainTab" type="border-card">
                            <!-- ─── 报表生成 ─── -->
                            <el-tab-pane :label="t('report_builder_page.tabs.reports')" name="builder">
                                <el-tabs v-model="rb_activeTab" type="border-card">
                                    <!-- ─── 我的报表 ─── -->
                                    <el-tab-pane :label="t('report_builder_page.tabs.reports')" name="reports">
                                        <div class="rb-tab-toolbar">
                                            <el-form :inline="true" :model="rb_listQuery" size="small">
                                                <el-form-item>
                                                    <el-select v-model="rb_listQuery.category" :placeholder="t('report_builder_page.filters.all_categories')" clearable @change="rb_fetchReports">
                                                        <el-option :label="t('report_builder_page.filters.all_categories')" value="" />
                                                        <el-option v-for="cat in rb_categories" :key="cat" :label="rb_categoryLabel(cat)" :value="cat" />
                                                    </el-select>
                                                </el-form-item>
                                                <el-form-item>
                                                    <el-select v-model="rb_listQuery.data_source" :placeholder="t('report_builder_page.filters.all_data_sources')" clearable @change="rb_fetchReports">
                                                        <el-option :label="t('report_builder_page.filters.all_data_sources')" value="" />
                                                        <el-option v-for="(ds, key) in rb_dataSources" :key="key" :label="ds.label" :value="key" />
                                                    </el-select>
                                                </el-form-item>
                                                <el-form-item>
                                                    <el-button type="primary" @click="rb_showCreateDialog = true">
                                                        <el-icon><Plus /></el-icon> {{ t('report_builder_page.buttons.new_report') }}
                                                    </el-button>
                                                </el-form-item>
                                            </el-form>
                                        </div>

                                        <el-table :data="rb_reports" v-loading="rb_loading" stripe style="width: 100%">
                                            <el-table-column prop="name" :label="t('report_builder_page.columns.report_name')" min-width="160" show-overflow-tooltip />
                                            <el-table-column :label="t('report_builder_page.columns.category')" width="100">
                                                <template #default="{ row }">
                                                    <el-tag :type="rb_categoryTag(row.category)" size="small">{{ rb_categoryLabel(row.category) }}</el-tag>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.data_source')" width="120">
                                                <template #default="{ row }">
                                                    <span>{{ rb_dataSourceLabel(row.data_source) }}</span>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.chart')" width="80">
                                                <template #default="{ row }">
                                                    <el-tag size="small">{{ row.chart_type }}</el-tag>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.scheduled')" width="60">
                                                <template #default="{ row }">
                                                    <el-icon v-if="row.is_scheduled" color="#67c23a"><Clock /></el-icon>
                                                    <span v-else>-</span>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.shared')" width="60">
                                                <template #default="{ row }">
                                                    <el-icon v-if="row.is_shared" color="#0f172a"><Share /></el-icon>
                                                    <span v-else>-</span>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.last_generated')" width="160">
                                                <template #default="{ row }">
                                                    <span v-if="row.last_generated_at">{{ rb_formatDate(row.last_generated_at) }}</span>
                                                    <span v-else class="text-muted">{{ t('report_builder_page.status.not_generated') }}</span>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.actions')" width="280" fixed="right">
                                                <template #default="{ row }">
                                                    <el-button size="small" type="primary" link @click="rb_runReport(row)">
                                                        <el-icon><DataAnalysis /></el-icon> {{ t('report_builder_page.row_actions.run') }}
                                                    </el-button>
                                                    <el-button size="small" type="primary" link @click="rb_editReport(row)">
                                                        <el-icon><Edit /></el-icon> {{ t('actions.edit') }}
                                                    </el-button>
                                                    <el-button size="small" type="primary" link @click="rb_showExportDialog(row)">
                                                        <el-icon><Download /></el-icon> {{ t('actions.export') }}
                                                    </el-button>
                                                    <el-popconfirm :title="t('report_builder_page.delete_confirm.report')" @confirm="rb_handleDelete(row)">
                                                        <template #reference>
                                                            <el-button size="small" type="danger" link>
                                                                <el-icon><Delete /></el-icon>
                                                            </el-button>
                                                        </template>
                                                    </el-popconfirm>
                                                </template>
                                            </el-table-column>
                                        </el-table>

                                        <div class="mt-3 rb-flex-center" v-if="rb_totalPages > 1">
                                            <el-pagination
                                                background
                                                layout="total, prev, pager, next"
                                                :total="rb_totalItems"
                                                :page-size="rb_perPage"
                                                :current-page="rb_currentPage"
                                                @current-change="rb_changePage"
                                            />
                                        </div>
                                    </el-tab-pane>

                                    <!-- ─── 报表运行结果 ─── -->
                                    <el-tab-pane :label="t('report_builder_page.tabs.result')" name="result" v-if="rb_reportResult">
                                        <div class="mb-3">
                                            <el-button @click="rb_reportResult = null"><el-icon><Back /></el-icon> {{ t('actions.back') }}</el-button>
                                            <el-tag class="ml-2">{{ t('report_builder_page.result.rows', { n: rb_reportResult.total_rows }) }}</el-tag>
                                        </div>
                                        <el-table :data="rb_reportResult.rows" stripe border max-height="500" style="width: 100%" v-if="rb_reportResult.rows?.length">
                                            <el-table-column v-for="col in rb_resultColumns" :key="col" :prop="col" :label="col" min-width="120" show-overflow-tooltip />
                                        </el-table>
                                        <div v-if="rb_reportResult.summary && Object.keys(rb_reportResult.summary).length" class="mt-3">
                                            <el-descriptions :column="3" border :title="t('report_builder_page.result.summary')">
                                                <el-descriptions-item v-for="(val, key) in rb_reportResult.summary" :key="key" :label="key">
                                                    {{ rb_formatValue(val.total, val.format) }}
                                                    <small class="text-muted ml-1">({{ t('report_builder_page.result.avg') }}: {{ rb_formatValue(val.avg, val.format) }})</small>
                                                </el-descriptions-item>
                                            </el-descriptions>
                                        </div>
                                        <div v-if="rb_reportResult.chart?.datasets?.length" class="mt-4">
                                            <h4 class="mb-2">{{ t('report_builder_page.result.chart_preview', { type: rb_reportResult.chart.type }) }}</h4>
                                            <el-alert
                                                :title="t('report_builder_page.result.chart_ready')"
                                                type="success"
                                                :description="t('report_builder_page.result.chart_meta', { labels: rb_reportResult.chart.labels?.length || 0, datasets: rb_reportResult.chart.datasets.length })"
                                                show-icon
                                            />
                                        </div>
                                    </el-tab-pane>

                                    <!-- ─── 看板管理 ─── -->
                                    <el-tab-pane :label="t('report_builder_page.tabs.dashboards')" name="dashboards">
                                        <div class="rb-tab-toolbar">
                                            <el-button type="primary" @click="rb_showDashboardDialog = true">
                                                <el-icon><Plus /></el-icon> {{ t('report_builder_page.buttons.new_dashboard') }}
                                            </el-button>
                                        </div>
                                        <el-table :data="rb_dashboards" v-loading="rb_dashLoading" stripe>
                                            <el-table-column prop="name" :label="t('report_builder_page.columns.dashboard_name')" min-width="160" />
                                            <el-table-column prop="description" :label="t('report_builder_page.columns.description')" min-width="200" show-overflow-tooltip />
                                            <el-table-column :label="t('report_builder_page.columns.default')" width="80">
                                                <template #default="{ row }">
                                                    <el-tag v-if="row.is_default" type="success" size="small">{{ t('report_builder_page.columns.default') }}</el-tag>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.shared')" width="80">
                                                <template #default="{ row }">
                                                    <el-icon v-if="row.is_shared" color="#0f172a"><Share /></el-icon>
                                                    <span v-else>-</span>
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.widget_count')" width="100">
                                                <template #default="{ row }">
                                                    {{ row.layout?.widgets?.length || 0 }}
                                                </template>
                                            </el-table-column>
                                            <el-table-column :label="t('report_builder_page.columns.actions')" width="200" fixed="right">
                                                <template #default="{ row }">
                                                    <el-button size="small" type="primary" link @click="rb_editDashboard(row)">
                                                        <el-icon><Edit /></el-icon> {{ t('actions.edit') }}
                                                    </el-button>
                                                    <el-popconfirm :title="t('report_builder_page.delete_confirm.dashboard')" @confirm="rb_handleDeleteDashboard(row)">
                                                        <template #reference>
                                                            <el-button size="small" type="danger" link>
                                                                <el-icon><Delete /></el-icon>
                                                            </el-button>
                                                        </template>
                                                    </el-popconfirm>
                                                </template>
                                            </el-table-column>
                                        </el-table>
                                    </el-tab-pane>
                                </el-tabs>
                            </el-tab-pane>

                            <!-- ─── 报表调度 ─── -->
                            <el-tab-pane :label="t('report_scheduler_page.title')" name="scheduler">
                                <template v-if="rb_rs_tabVisited">
                                    <div class="rb-rs-page-header">
                                        <h2>{{ t('report_scheduler_page.title') }}</h2>
                                        <p class="text-muted">{{ t('report_scheduler_page.subtitle') }}</p>
                                    </div>

                                    <!-- ── 统计概览 ── -->
                                    <el-row :gutter="16" class="mb-4">
                                        <el-col :span="6">
                                            <el-card shadow="never" class="rb-rs-stat-card">
                                                <div class="stat-value">{{ rb_rs_dashboard.stats.total_schedules }}</div>
                                                <div class="stat-label">{{ t('report_scheduler_page.stats.total_schedules') }}</div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-card shadow="never" class="rb-rs-stat-card">
                                                <div class="rb-rs-stat-value active">{{ rb_rs_dashboard.stats.active_schedules }}</div>
                                                <div class="stat-label">{{ t('report_scheduler_page.stats.active') }}</div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-card shadow="never" class="rb-rs-stat-card">
                                                <div class="rb-rs-stat-value" :class="rb_rs_dashboard.stats.due_count > 0 ? 'warn' : ''">{{ rb_rs_dashboard.stats.due_count }}</div>
                                                <div class="stat-label">{{ t('report_scheduler_page.stats.due') }}</div>
                                            </el-card>
                                        </el-col>
                                        <el-col :span="6">
                                            <el-card shadow="never" class="rb-rs-stat-card">
                                                <div class="rb-rs-stat-value success">{{ rb_rs_dashboard.stats.success_rate }}%</div>
                                                <div class="stat-label">{{ t('report_scheduler_page.stats.success_rate') }}</div>
                                            </el-card>
                                        </el-col>
                                    </el-row>

                                    <el-tabs v-model="rb_rs_activeTab">
                                        <!-- ── 调度列表 ── -->
                                        <el-tab-pane :label="t('report_scheduler_page.tabs.schedules')" name="schedules">
                                            <el-card shadow="never">
                                                <template #header>
                                                    <div class="rb-card-header">
                                                        <span>{{ t('report_scheduler_page.cards.schedules') }}</span>
                                                        <el-button type="primary" size="small" :icon="Plus" @click="rb_rs_showCreateDialog">{{ t('report_scheduler_page.buttons.new_schedule') }}</el-button>
                                                    </div>
                                                </template>

                                                <el-table :data="rb_rs_schedules" border stripe v-loading="rb_rs_loading">
                                                    <el-table-column prop="id" label="ID" width="60" />
                                                    <el-table-column :label="t('report_scheduler_page.columns.report')" min-width="180">
                                                        <template #default="{ row }">
                                                            <div>
                                                                <strong>{{ row.report?.name || t('report_scheduler_page.status.unknown') }}</strong>
                                                                <el-tag size="small" type="info" class="ml-2">{{ row.report?.data_source }}</el-tag>
                                                            </div>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.schedule_plan')" width="160">
                                                        <template #default="{ row }">
                                                            <code class="rb-cron-badge">{{ row.cron_expression }}</code>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.format')" width="80">
                                                        <template #default="{ row }">
                                                            <el-tag size="small">{{ row.export_format }}</el-tag>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.status')" width="80">
                                                        <template #default="{ row }">
                                                            <el-switch
                                                                :model-value="row.is_active"
                                                                :loading="rb_rs_togglingId === row.id"
                                                                @change="rb_rs_toggleSchedule(row)"
                                                            />
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.run_count')" width="80" align="center">
                                                        <template #default="{ row }">
                                                            <div class="rb-run-stats">
                                                                <el-tooltip :content="t('report_scheduler_page.run_stats.tooltip', { success: row.success_count, failure: row.failure_count })">
                                                                    <span>
                                                                        {{ row.run_count }}
                                                                        <span v-if="row.failure_count > 0" class="text-danger">({{ row.failure_count }})</span>
                                                                    </span>
                                                                </el-tooltip>
                                                            </div>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.next_run')" width="160">
                                                        <template #default="{ row }">
                                                            <span v-if="row.next_run_at">{{ rb_rs_formatDate(row.next_run_at) }}</span>
                                                            <span v-else class="text-muted">-</span>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.last_run')" width="160">
                                                        <template #default="{ row }">
                                                            <span v-if="row.last_run_at">{{ rb_rs_formatDate(row.last_run_at) }}</span>
                                                            <span v-else class="text-muted">{{ t('report_scheduler_page.status.never') }}</span>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_builder_page.columns.actions')" width="220" fixed="right">
                                                        <template #default="{ row }">
                                                            <el-button size="small" @click="rb_rs_editSchedule(row)">{{ t('actions.edit') }}</el-button>
                                                            <el-button size="small" type="primary" plain @click="rb_rs_triggerSchedule(row)">{{ t('report_scheduler_page.row_actions.trigger') }}</el-button>
                                                            <el-popconfirm :title="t('report_scheduler_page.delete_confirm')" @confirm="rb_rs_deleteSchedule(row)">
                                                                <template #reference>
                                                                    <el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button>
                                                                </template>
                                                            </el-popconfirm>
                                                        </template>
                                                    </el-table-column>
                                                </el-table>
                                            </el-card>
                                        </el-tab-pane>

                                        <!-- ── 投递日志 ── -->
                                        <el-tab-pane :label="t('report_scheduler_page.tabs.logs')" name="logs">
                                            <el-card shadow="never">
                                                <template #header>
                                                    <div class="rb-card-header">
                                                        <span>{{ t('report_scheduler_page.cards.delivery_logs') }}</span>
                                                        <div class="rb-filter-bar">
                                                            <el-select v-model="rb_rs_logFilters.status" clearable :placeholder="t('report_scheduler_page.filters.status')" size="small" style="width:120px" @change="rb_rs_fetchLogs">
                                                                <el-option v-for="opt in rb_rs_logStatusFilterOptions" :key="opt.value || 'all'" :label="opt.label" :value="opt.value" />
                                                            </el-select>
                                                            <el-date-picker
                                                                v-model="rb_rs_logDateRange"
                                                                type="daterange"
                                                                :range-separator="t('licenses_page.date_range_sep')"
                                                                :start-placeholder="t('report_scheduler_page.filters.date_start')"
                                                                :end-placeholder="t('report_scheduler_page.filters.date_end')"
                                                                size="small"
                                                                value-format="YYYY-MM-DD"
                                                                @change="rb_rs_fetchLogs"
                                                            />
                                                        </div>
                                                    </div>
                                                </template>

                                                <el-table :data="rb_rs_deliveryLogs" border stripe v-loading="rb_rs_logLoading">
                                                    <el-table-column prop="id" label="ID" width="60" />
                                                    <el-table-column :label="t('report_scheduler_page.columns.report')" min-width="150">
                                                        <template #default="{ row }">
                                                            {{ row.report?.name || t('report_scheduler_page.status.unknown') }}
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.status')" width="100">
                                                        <template #default="{ row }">
                                                            <el-tag :type="rb_rs_logStatusType(row.status)" size="small">
                                                                {{ rb_rs_logStatusLabel(row.status) }}
                                                            </el-tag>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.format')" width="80">
                                                        <template #default="{ row }">{{ row.export_format }}</template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.recipients')" min-width="200">
                                                        <template #default="{ row }">
                                                            <div v-if="row.recipients && row.recipients.length > 0" class="rb-recipient-list">
                                                                <el-tag v-for="r in row.recipients.slice(0, 3)" :key="r.email" size="small" type="info" class="rb-recipient-tag">
                                                                    {{ r.email }}
                                                                </el-tag>
                                                                <span v-if="row.recipients.length > 3" class="text-muted">+{{ row.recipients.length - 3 }}</span>
                                                            </div>
                                                            <span v-else class="text-muted">-</span>
                                                        </template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.attempts')" width="80" align="center">
                                                        <template #default="{ row }">{{ row.attempts }}</template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.file_size')" width="100">
                                                        <template #default="{ row }">{{ rb_rs_formatFileSize(row.file_size) }}</template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.time')" width="160">
                                                        <template #default="{ row }">{{ rb_rs_formatDate(row.created_at) }}</template>
                                                    </el-table-column>
                                                    <el-table-column :label="t('report_scheduler_page.columns.error')" min-width="200">
                                                        <template #default="{ row }">
                                                            <el-tooltip v-if="row.error_message" :content="row.error_message">
                                                                <el-tag type="danger" size="small" effect="dark" class="rb-error-msg">
                                                                    {{ rb_rs_truncate(row.error_message, 50) }}
                                                                </el-tag>
                                                            </el-tooltip>
                                                        </template>
                                                    </el-table-column>
                                                </el-table>

                                                <div v-if="rb_rs_logTotal > rb_rs_logPerPage" class="rb-pagination-wrap">
                                                    <el-pagination
                                                        v-model:current-page="rb_rs_logPage"
                                                        :page-size="rb_rs_logPerPage"
                                                        :total="rb_rs_logTotal"
                                                        layout="prev, pager, next, total"
                                                        @current-change="rb_rs_fetchLogs"
                                                    />
                                                </div>
                                            </el-card>
                                        </el-tab-pane>

                                        <!-- ── 最近投递 ── -->
                                        <el-tab-pane :label="t('report_scheduler_page.tabs.recent')" name="recent">
                                            <el-card shadow="never">
                                                <template #header><span>{{ t('report_scheduler_page.cards.recent_activity') }}</span></template>
                                                <el-timeline>
                                                    <el-timeline-item
                                                        v-for="log in rb_rs_recentDeliveries"
                                                        :key="log.id"
                                                        :type="rb_rs_logStatusType(log.status)"
                                                        :timestamp="rb_rs_formatDate(log.created_at)"
                                                    >
                                                        <div class="rb-timeline-content">
                                                            <strong>{{ log.report?.name || t('report_scheduler_page.timeline.report_fallback') }}</strong>
                                                            <el-tag :type="rb_rs_logStatusType(log.status)" size="small" class="ml-2">
                                                                {{ rb_rs_logStatusLabel(log.status) }}
                                                            </el-tag>
                                                        </div>
                                                        <div class="rb-timeline-meta">
                                                            {{ log.export_format }} · {{ t('report_scheduler_page.timeline.recipient_count', { n: log.recipients?.length || 0 }) }}
                                                        </div>
                                                        <div v-if="log.error_message" class="rb-timeline-error">
                                                            {{ log.error_message }}
                                                        </div>
                                                    </el-timeline-item>
                                                    <el-timeline-item v-if="rb_rs_recentDeliveries.length === 0" type="info">
                                                        {{ t('report_scheduler_page.empty.no_logs') }}
                                                    </el-timeline-item>
                                                </el-timeline>
                                            </el-card>
                                        </el-tab-pane>
                                    </el-tabs>

                                    <!-- ── 新建/编辑调度对话框 ── -->
                                    <el-dialog
                                        v-model="rb_rs_dialogVisible"
                                        :title="rb_rs_isEditing ? t('report_scheduler_page.dialogs.edit') : t('report_scheduler_page.dialogs.create')"
                                        width="600px"
                                        :close-on-click-modal="false"
                                    >
                                        <el-form label-position="top" size="small" :model="rb_rs_form">
                                            <el-row :gutter="16">
                                                <el-col :span="24">
                                                    <el-form-item :label="t('report_scheduler_page.form.select_report')" required>
                                                        <el-select
                                                            v-model="rb_rs_form.report_id"
                                                            filterable
                                                            :placeholder="t('report_scheduler_page.form.select_report_ph')"
                                                            style="width:100%"
                                                            :disabled="rb_rs_isEditing"
                                                        >
                                                            <el-option
                                                                v-for="r in rb_rs_schedulableReports"
                                                                :key="r.id"
                                                                :label="`${r.name} (${r.data_source})`"
                                                                :value="r.id"
                                                            />
                                                        </el-select>
                                                    </el-form-item>
                                                </el-col>
                                            </el-row>
                                            <el-row :gutter="16">
                                                <el-col :span="12">
                                                    <el-form-item :label="t('report_scheduler_page.form.cron_expression')" required>
                                                        <el-input v-model="rb_rs_form.cron_expression" :placeholder="t('report_scheduler_page.form.cron_ph')">
                                                            <template #append>
                                                                <el-tooltip :content="t('report_scheduler_page.form.cron_hint')">
                                                                    <el-icon><QuestionFilled /></el-icon>
                                                                </el-tooltip>
                                                            </template>
                                                        </el-input>
                                                    </el-form-item>
                                                </el-col>
                                                <el-col :span="12">
                                                    <el-form-item :label="t('report_builder_page.form.export_format')">
                                                        <el-select v-model="rb_rs_form.export_format">
                                                            <el-option label="CSV" value="csv" />
                                                            <el-option label="JSON" value="json" />
                                                            <el-option label="XLSX" value="xlsx" />
                                                            <el-option label="PDF" value="pdf" />
                                                        </el-select>
                                                    </el-form-item>
                                                </el-col>
                                            </el-row>
                                            <el-row :gutter="16">
                                                <el-col :span="12">
                                                    <el-form-item :label="t('report_scheduler_page.form.email_subject')">
                                                        <el-input v-model="rb_rs_form.subject" :placeholder="t('report_scheduler_page.form.subject_ph')" maxlength="200" />
                                                    </el-form-item>
                                                </el-col>
                                                <el-col :span="12">
                                                    <el-form-item :label="t('report_scheduler_page.form.max_retries')">
                                                        <el-input-number v-model="rb_rs_form.max_retries" :min="0" :max="10" />
                                                    </el-form-item>
                                                </el-col>
                                            </el-row>
                                            <el-form-item :label="t('report_scheduler_page.form.email_body')">
                                                <el-input v-model="rb_rs_form.message" type="textarea" :rows="3" :placeholder="t('report_scheduler_page.form.message_ph')" maxlength="2000" />
                                            </el-form-item>
                                            <el-form-item :label="t('report_scheduler_page.form.recipients')">
                                                <div class="rb-recipient-editor">
                                                    <div v-for="(r, i) in rb_rs_form.recipients" :key="i" class="rb-recipient-row">
                                                        <el-input v-model="r.email" :placeholder="t('report_scheduler_page.form.email_ph')" style="width:240px" />
                                                        <el-input v-model="r.name" :placeholder="t('report_scheduler_page.form.name_ph')" style="width:160px" class="ml-2" />
                                                        <el-button type="danger" :icon="Delete" text @click="rb_rs_removeRecipient(i)" />
                                                    </div>
                                                    <el-button size="small" @click="rb_rs_addRecipient" :icon="Plus">{{ t('report_scheduler_page.form.add_recipient') }}</el-button>
                                                </div>
                                            </el-form-item>
                                            <el-form-item>
                                                <el-checkbox v-model="rb_rs_form.include_chart">{{ t('report_scheduler_page.form.include_chart') }}</el-checkbox>
                                                <el-checkbox v-model="rb_rs_form.is_active" class="ml-2">{{ t('report_scheduler_page.form.enable_on_create') }}</el-checkbox>
                                            </el-form-item>
                                        </el-form>
                                        <template #footer>
                                            <el-button @click="rb_rs_dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                                            <el-button type="primary" :loading="rb_rs_saving" @click="rb_rs_saveSchedule">
                                                {{ rb_rs_isEditing ? t('actions.save') : t('actions.create') }}
                                            </el-button>
                                        </template>
                                    </el-dialog>
                                </template>
                            </el-tab-pane>
                        </el-tabs>

                        <!-- ─── 新建/编辑报表对话框 ─── -->
                        <el-dialog
                            v-model="rb_showCreateDialog"
                            :title="rb_editMode ? t('report_builder_page.dialogs.edit_report') : t('report_builder_page.dialogs.create_report')"
                            width="800px"
                            top="5vh"
                        >
                            <el-form ref="rb_reportFormRef" :model="rb_reportForm" :rules="rb_reportRules" label-width="100px" v-loading="rb_formLoading">
                                <el-row :gutter="20">
                                    <el-col :span="12">
                                        <el-form-item :label="t('report_builder_page.form.report_name')" prop="name">
                                            <el-input v-model="rb_reportForm.name" :placeholder="t('report_builder_page.form.report_name_ph')" maxlength="200" />
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item :label="t('report_builder_page.form.category')" prop="category">
                                            <el-select v-model="rb_reportForm.category" :placeholder="t('report_builder_page.form.select_category')" style="width:100%">
                                                <el-option v-for="cat in rb_categories" :key="cat" :label="rb_categoryLabel(cat)" :value="cat" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-form-item :label="t('report_builder_page.form.description')">
                                    <el-input v-model="rb_reportForm.description" type="textarea" :rows="2" maxlength="2000" show-word-limit />
                                </el-form-item>
                                <el-row :gutter="20">
                                    <el-col :span="12">
                                        <el-form-item :label="t('report_builder_page.form.data_source')" prop="data_source">
                                            <el-select v-model="rb_reportForm.data_source" :placeholder="t('report_builder_page.form.select_data_source')" style="width:100%" @change="rb_onDataSourceChange">
                                                <el-option v-for="(ds, key) in rb_dataSources" :key="key" :label="ds.label" :value="key" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                    <el-col :span="12">
                                        <el-form-item :label="t('report_builder_page.form.chart_type')" prop="chart_type">
                                            <el-select v-model="rb_reportForm.chart_type" :placeholder="t('report_builder_page.form.chart_type')" style="width:100%">
                                                <el-option v-for="opt in rb_chartTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                            </el-select>
                                        </el-form-item>
                                    </el-col>
                                </el-row>
                                <el-form-item :label="t('report_builder_page.form.metrics')" prop="metrics">
                                    <div class="rb-metrics-grid">
                                        <el-checkbox-group v-model="rb_selectedMetrics">
                                            <el-checkbox
                                                v-for="(mDef, mKey) in rb_currentMetrics"
                                                :key="mKey"
                                                :label="mKey"
                                                :value="mKey"
                                            >
                                                {{ mDef.label }}
                                            </el-checkbox>
                                        </el-checkbox-group>
                                    </div>
                                    <div class="text-muted mt-1" v-if="!rb_reportForm.data_source">{{ t('report_builder_page.form.select_data_source_first') }}</div>
                                </el-form-item>
                                <el-form-item :label="t('report_builder_page.form.dimensions')">
                                    <div class="rb-metrics-grid">
                                        <el-checkbox-group v-model="rb_selectedDimensions">
                                            <el-checkbox
                                                v-for="(dDef, dKey) in rb_currentDimensions"
                                                :key="dKey"
                                                :label="dKey"
                                                :value="dKey"
                                            >
                                                {{ dDef.label }}
                                            </el-checkbox>
                                        </el-checkbox-group>
                                    </div>
                                    <div class="text-muted mt-1" v-if="!rb_reportForm.data_source">{{ t('report_builder_page.form.select_data_source_first') }}</div>
                                </el-form-item>
                                <el-form-item :label="t('report_builder_page.form.schedule')">
                                    <el-switch v-model="rb_reportForm.is_scheduled" :active-text="t('report_builder_page.form.enable_schedule')" />
                                    <template v-if="rb_reportForm.is_scheduled">
                                        <el-input v-model="rb_reportForm.schedule_cron" :placeholder="t('report_builder_page.form.cron_ph')" class="ml-2" style="width: 200px" />
                                        <el-tag class="ml-2">{{ t('report_builder_page.form.cron_example') }}</el-tag>
                                    </template>
                                </el-form-item>
                                <el-form-item :label="t('report_builder_page.form.share')">
                                    <el-switch v-model="rb_reportForm.is_shared" :active-text="t('report_builder_page.form.share_with_team')" />
                                </el-form-item>
                            </el-form>

                            <template #footer>
                                <el-button @click="rb_showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                                <el-button type="primary" :loading="rb_formLoading" @click="rb_submitReport">
                                    {{ rb_editMode ? t('actions.update') : t('actions.create') }}
                                </el-button>
                            </template>
                        </el-dialog>

                        <!-- ─── 编辑看板对话框 ─── -->
                        <el-dialog
                            v-model="rb_showDashboardDialog"
                            :title="rb_dashboardEditMode ? t('report_builder_page.dialogs.edit_dashboard') : t('report_builder_page.dialogs.create_dashboard')"
                            width="500px"
                        >
                            <el-form ref="rb_dashFormRef" :model="rb_dashForm" :rules="rb_dashRules" label-width="80px">
                                <el-form-item :label="t('report_builder_page.form.name')" prop="name">
                                    <el-input v-model="rb_dashForm.name" :placeholder="t('report_builder_page.form.dashboard_name_ph')" maxlength="200" />
                                </el-form-item>
                                <el-form-item :label="t('report_builder_page.form.description')">
                                    <el-input v-model="rb_dashForm.description" type="textarea" :rows="2" maxlength="1000" />
                                </el-form-item>
                                <el-form-item :label="t('report_builder_page.form.share')">
                                    <el-switch v-model="rb_dashForm.is_shared" />
                                </el-form-item>
                                <el-form-item :label="t('report_builder_page.form.default')">
                                    <el-switch v-model="rb_dashForm.is_default" />
                                </el-form-item>
                            </el-form>
                            <template #footer>
                                <el-button @click="rb_showDashboardDialog = false">{{ t('actions.cancel') }}</el-button>
                                <el-button type="primary" :loading="rb_formLoading" @click="rb_submitDashboard">
                                    {{ rb_dashboardEditMode ? t('actions.update') : t('actions.create') }}
                                </el-button>
                            </template>
                        </el-dialog>

                        <!-- ─── 导出对话框 ─── -->
                        <el-dialog v-model="rb_showExportDlg" :title="t('report_builder_page.dialogs.export_report')" width="400px">
                            <el-form label-width="80px">
                                <el-form-item :label="t('report_builder_page.form.export_format')">
                                    <el-radio-group v-model="rb_exportFormat">
                                        <el-radio value="csv">CSV</el-radio>
                                        <el-radio value="json">JSON</el-radio>
                                    </el-radio-group>
                                </el-form-item>
                            </el-form>
                            <template #footer>
                                <el-button @click="rb_showExportDlg = false">{{ t('actions.cancel') }}</el-button>
                                <el-button type="primary" :loading="rb_exporting" @click="rb_doExport">{{ t('actions.export') }}</el-button>
                            </template>
                        </el-dialog>
                    </div>
                </template>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import {
    Refresh, Plus, Delete, Edit, Download, Share, Clock,
    Document, CopyDocument, Histogram, DataAnalysis, Back, QuestionFilled,
} from '@element-plus/icons-vue'
import reportApi from '@/api/report'
import reportBuilderApi from '@/api/reportBuilder'
import reportSchedulerApi from '@/api/reportScheduler'

const { t, locale } = useI18n()
const P = 'reports_page'
const isZh = computed(() => locale.value?.startsWith('zh'))

// ════════════════════════════════════════════
//  Top-level tab
// ════════════════════════════════════════════
const reportCenterTab = ref('revenue')

// ════════════════════════════════════════════
//  营收报表 (revenue) — original reports
// ════════════════════════════════════════════

const loading = ref(false)
const loadingTrend = ref(false)
const loadingMrt = ref(false)
const trendPeriod = ref('monthly')

const dashboard = reactive({
    total_revenue: 0, month_revenue: 0, year_revenue: 0,
    mrr: 0, arr: 0, pending_amount: 0, total_plans: 0,
    mrr_breakdown: {},
    subscriptions: { total: 0, active: 0, grace: 0, expired: 0, canceled: 0 },
    customers: { total: 0, active: 0 },
})

const revenueTrend = ref([])
const mrrTrendData = ref([])

const analytics = reactive({
    subscriptions: { total: 0, active: 0, grace: 0, expired: 0, canceled: 0 },
})

const subAnalytics = reactive({
    by_period: [],
    by_status: {},
    cancel_trend: [],
    expiring_soon_30d: 0,
    avg_subscription_days: 0,
})

const planDistribution = ref([])
const ltv = reactive({
    total_customers: 0, total_revenue: 0, average_ltv: 0,
    max_ltv: 0, median_ltv: 0, tiers: {},
    top_customers: [],
})
const churnData = ref([])

function formatNum(n) {
    if (n === null || n === undefined) return '0'
    if (isZh.value) {
        if (n >= 100000000) return (n / 100000000).toFixed(2) + t(`${P}.yi`)
        if (n >= 10000) return (n / 10000).toFixed(2) + t(`${P}.wan`)
        return Number(n).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    }
    if (n >= 1000000) return (n / 1000000).toFixed(2) + 'M'
    if (n >= 1000) return (n / 1000).toFixed(2) + 'k'
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function periodLabel(p) {
    const key = `${P}.periods.${p}`
    const translated = t(key)
    return translated === key ? p : translated
}

function mrrBarWidth(val) {
    const max = Math.max(
        dashboard.mrr_breakdown?.monthly || 0,
        dashboard.mrr_breakdown?.quarterly || 0,
        dashboard.mrr_breakdown?.semi_annually || 0,
        dashboard.mrr_breakdown?.yearly || 0,
        1
    )
    return (val / max) * 100
}

function mrrColor(key) {
    const map = { monthly: '#0f172a', quarterly: '#67c23a', semi_annually: '#e6a23c', yearly: '#f56c6c' }
    return map[key] || '#909399'
}

function trendBarHeight(val) {
    const max = Math.max(...revenueTrend.value.map(i => i.revenue || 0), 1)
    return Math.max(4, (val / max) * 180)
}

function formatPeriod(p) {
    if (!p) return ''
    if (p.length === 7) {
        const [y, m] = p.split('-')
        return y.slice(2) + '/' + m
    }
    return p.slice(5)
}

function churnTagType(rate) {
    if (rate > 10) return 'danger'
    if (rate > 5) return 'warning'
    return 'success'
}

async function fetchDashboard() {
    try {
        const { data: res } = await reportApi.dashboard()
        if (res.success) {
            Object.assign(dashboard, res.data)
            Object.assign(analytics, { subscriptions: res.data.subscriptions })
        }
    } catch { /* handled by interceptor */ }
}

async function fetchRevenueTrend() {
    loadingTrend.value = true
    try {
        const { data: res } = await reportApi.revenueTrend({
            period: trendPeriod.value,
            months: trendPeriod.value === 'monthly' ? 12 : 30,
        })
        if (res.success) {
            revenueTrend.value = res.data.trend || []
        }
    } catch { /* empty */ }
    finally { loadingTrend.value = false }
}

async function fetchMrrTrend() {
    loadingMrt.value = true
    try {
        const { data: res } = await reportApi.mrrTrend({ months: 12 })
        if (res.success) {
            mrrTrendData.value = res.data || []
        }
    } catch { /* empty */ }
    finally { loadingMrt.value = false }
}

async function fetchSubAnalytics() {
    try {
        const { data: res } = await reportApi.subscriptionAnalytics()
        if (res.success) {
            Object.assign(subAnalytics, res.data)
        }
    } catch { /* empty */ }
}

async function fetchPlanDistribution() {
    try {
        const { data: res } = await reportApi.planDistribution()
        if (res.success) {
            planDistribution.value = res.data || []
        }
    } catch { /* empty */ }
}

async function fetchLtv() {
    try {
        const { data: res } = await reportApi.customerLtv()
        if (res.success) {
            Object.assign(ltv, res.data)
        }
    } catch { /* empty */ }
}

async function fetchChurn() {
    try {
        const { data: res } = await reportApi.churnAnalysis()
        if (res.success) {
            churnData.value = res.data || []
        }
    } catch { /* empty */ }
}

async function refreshAll() {
    loading.value = true
    await Promise.all([
        fetchDashboard(),
        fetchRevenueTrend(),
        fetchMrrTrend(),
        fetchSubAnalytics(),
        fetchPlanDistribution(),
        fetchLtv(),
        fetchChurn(),
    ])
    loading.value = false
}

onMounted(refreshAll)

// ════════════════════════════════════════════
//  报表工具 (builder) — rb_ prefix
// ════════════════════════════════════════════

const rb_tabVisited = ref(false)

const rb_activeTab = ref('reports')
const rb_loading = ref(false)
const rb_dashLoading = ref(false)
const rb_formLoading = ref(false)
const rb_exporting = ref(false)

const rb_dataSources = ref({})
const rb_categories = ref([])
const rb_reports = ref([])
const rb_dashboards = ref([])
const rb_reportResult = ref(null)

const rb_listQuery = reactive({
    category: '',
    data_source: '',
})

const rb_currentPage = ref(1)
const rb_perPage = ref(50)
const rb_totalItems = ref(0)
const rb_totalPages = computed(() => Math.ceil(rb_totalItems.value / rb_perPage.value))

const rb_dashboardStats = reactive({
    total_reports: 0,
    total_templates: 0,
    scheduled_count: 0,
    total_snapshots: 0,
})

const rb_statCardMeta = [
    { key: 'total_reports', labelKey: 'report_builder_page.stats.total_reports', icon: Document, iconClass: 'bg-blue' },
    { key: 'total_templates', labelKey: 'report_builder_page.stats.total_templates', icon: CopyDocument, iconClass: 'bg-green' },
    { key: 'scheduled_count', labelKey: 'report_builder_page.stats.scheduled_count', icon: Clock, iconClass: 'bg-orange' },
    { key: 'total_snapshots', labelKey: 'report_builder_page.stats.total_snapshots', icon: Histogram, iconClass: 'bg-purple' },
]

const rb_statCards = computed(() => rb_statCardMeta.map((m) => ({
    key: m.key,
    label: t(m.labelKey),
    icon: m.icon,
    iconClass: m.iconClass,
})))

const rb_chartTypeKeys = ['table', 'bar', 'line', 'pie', 'area', 'radar', 'number']

const rb_chartTypeOptions = computed(() => rb_chartTypeKeys.map((value) => ({
    value,
    label: t(`report_builder_page.chart_types.${value}`),
})))

const rb_categoryLabelMap = computed(() => ({
    financial: t('report_builder_page.categories.financial'),
    license: t('report_builder_page.categories.license'),
    customer: t('report_builder_page.categories.customer'),
    audit: t('report_builder_page.categories.audit'),
    custom: t('report_builder_page.categories.custom'),
}))

const rb_resultColumns = computed(() => {
    if (!rb_reportResult.value?.rows?.length) return []
    return Object.keys(rb_reportResult.value.rows[0])
})

const rb_showCreateDialog = ref(false)
const rb_editMode = ref(false)
const rb_editingId = ref(null)
const rb_reportFormRef = ref(null)

const rb_reportForm = reactive({
    name: '',
    description: '',
    category: '',
    data_source: '',
    chart_type: 'table',
    metrics: {},
    dimensions: [],
    filters: {},
    sorts: [],
    is_scheduled: false,
    schedule_cron: '',
    is_shared: false,
})

const rb_selectedMetrics = ref([])
const rb_selectedDimensions = ref([])

const rb_reportRules = computed(() => ({
    name: [{ required: true, message: t('report_builder_page.rules.report_name_required'), trigger: 'blur' }],
    category: [{ required: true, message: t('report_builder_page.rules.category_required'), trigger: 'change' }],
    data_source: [{ required: true, message: t('report_builder_page.rules.data_source_required'), trigger: 'change' }],
}))

const rb_currentMetrics = computed(() => {
    if (!rb_reportForm.data_source || !rb_dataSources.value[rb_reportForm.data_source]) return {}
    return rb_dataSources.value[rb_reportForm.data_source].metrics || {}
})

const rb_currentDimensions = computed(() => {
    if (!rb_reportForm.data_source || !rb_dataSources.value[rb_reportForm.data_source]) return {}
    return rb_dataSources.value[rb_reportForm.data_source].dimensions || {}
})

function rb_onDataSourceChange() {
    rb_selectedMetrics.value = []
    rb_selectedDimensions.value = []
    const metrics = rb_currentMetrics.value
    Object.entries(metrics).forEach(([key, def]) => {
        if (def.default) rb_selectedMetrics.value.push(key)
    })
}

const rb_showDashboardDialog = ref(false)
const rb_dashboardEditMode = ref(false)
const rb_dashEditId = ref(null)
const rb_dashFormRef = ref(null)

const rb_dashForm = reactive({
    name: '',
    description: '',
    is_shared: false,
    is_default: false,
})

const rb_dashRules = computed(() => ({
    name: [{ required: true, message: t('report_builder_page.rules.dashboard_name_required'), trigger: 'blur' }],
}))

const rb_showExportDlg = ref(false)
const rb_exportFormat = ref('csv')
const rb_exportReportId = ref(null)

function rb_formatDate(d) {
    if (!d) return '-'
    const dt = new Date(d)
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'
    return dt.toLocaleString(loc, { hour12: false })
}

function rb_formatValue(val, format) {
    if (val === undefined || val === null) return '-'
    if (format === 'currency') return `¥${Number(val).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US', { minimumFractionDigits: 2 })}`
    if (format === 'percentage') return `${val}%`
    if (typeof val === 'number') return val.toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US')
    return val
}

function rb_categoryLabel(cat) {
    return rb_categoryLabelMap.value[cat] || cat
}

function rb_categoryTag(cat) {
    const map = { financial: 'danger', license: 'warning', customer: 'success', audit: 'info', custom: '' }
    return map[cat] || ''
}

function rb_dataSourceLabel(src) {
    return rb_dataSources.value[src]?.label || src
}

async function rb_fetchDashboard() {
    try {
        const { data } = await reportBuilderApi.getDashboard()
        if (data.success) {
            Object.assign(rb_dashboardStats, data.data.stats)
            rb_dataSources.value = data.data.data_sources || {}
            rb_categories.value = data.data.categories || []
        }
    } catch (e) { /* ignore */ }
}

async function rb_fetchReports() {
    rb_loading.value = true
    try {
        const params = { page: rb_currentPage.value, per_page: rb_perPage.value }
        if (rb_listQuery.category) params.category = rb_listQuery.category
        if (rb_listQuery.data_source) params.data_source = rb_listQuery.data_source
        const { data } = await reportBuilderApi.getReports(params)
        if (data.success) {
            rb_reports.value = data.data.data || []
            rb_totalItems.value = data.data.total || 0
            rb_currentPage.value = data.data.current_page || 1
        }
    } catch (e) {
        ElMessage.error(t('report_builder_page.messages.load_reports_failed'))
    } finally {
        rb_loading.value = false
    }
}

async function rb_fetchDashboards() {
    rb_dashLoading.value = true
    try {
        const { data } = await reportBuilderApi.getDashboards()
        if (data.success) {
            rb_dashboards.value = data.data || []
        }
    } catch (e) {
        ElMessage.error(t('report_builder_page.messages.load_dashboards_failed'))
    } finally {
        rb_dashLoading.value = false
    }
}

function rb_changePage(page) {
    rb_currentPage.value = page
    rb_fetchReports()
}

function rb_runReport(report) {
    reportBuilderApi.generateReport(report.id).then(({ data }) => {
        if (data.success) {
            rb_reportResult.value = data.data
            rb_activeTab.value = 'result'
            ElMessage.success(t('report_builder_page.messages.generate_ok'))
        }
    }).catch(e => {
        ElMessage.error(e.response?.data?.message || t('report_builder_page.messages.generate_failed'))
    })
}

function rb_editReport(report) {
    rb_editMode.value = true
    rb_editingId.value = report.id
    Object.assign(rb_reportForm, {
        name: report.name,
        description: report.description || '',
        category: report.category,
        data_source: report.data_source,
        chart_type: report.chart_type,
        is_scheduled: !!report.is_scheduled,
        schedule_cron: report.schedule_cron || '',
        is_shared: !!report.is_shared,
    })
    rb_selectedMetrics.value = Object.keys(report.metrics || {})
    rb_selectedDimensions.value = report.dimensions || []
    rb_showCreateDialog.value = true
}

function rb_resetForm() {
    rb_editMode.value = false
    rb_editingId.value = null
    rb_reportForm.name = ''
    rb_reportForm.description = ''
    rb_reportForm.category = ''
    rb_reportForm.data_source = ''
    rb_reportForm.chart_type = 'table'
    rb_reportForm.is_scheduled = false
    rb_reportForm.schedule_cron = ''
    rb_reportForm.is_shared = false
    rb_selectedMetrics.value = []
    rb_selectedDimensions.value = []
}

async function rb_submitReport() {
    const valid = await rb_reportFormRef.value?.validate().catch(() => false)
    if (!valid) return

    rb_formLoading.value = true
    try {
        const metrics = {}
        const mDefs = rb_currentMetrics.value
        rb_selectedMetrics.value.forEach(key => {
            metrics[key] = mDefs[key] || { type: 'count', label: key }
        })

        const payload = {
            ...rb_reportForm,
            metrics,
            dimensions: rb_selectedDimensions.value,
        }

        if (rb_editMode.value && rb_editingId.value) {
            await reportBuilderApi.updateReport(rb_editingId.value, payload)
            ElMessage.success(t('report_builder_page.messages.report_updated'))
        } else {
            await reportBuilderApi.createReport(payload)
            ElMessage.success(t('report_builder_page.messages.report_created'))
        }

        rb_showCreateDialog.value = false
        rb_resetForm()
        rb_fetchReports()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        rb_formLoading.value = false
    }
}

function rb_showExportDialog(report) {
    rb_exportReportId.value = report.id
    rb_exportFormat.value = 'csv'
    rb_showExportDlg.value = true
}

async function rb_doExport() {
    if (!rb_exportReportId.value) return
    rb_exporting.value = true
    try {
        const { data } = await reportBuilderApi.exportReport(rb_exportReportId.value, rb_exportFormat.value)
        if (data.success) {
            ElMessage.success(t('report_builder_page.messages.export_ok', { filename: data.data.filename }))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('report_builder_page.messages.export_failed'))
    } finally {
        rb_exporting.value = false
        rb_showExportDlg.value = false
    }
}

async function rb_handleDelete(report) {
    try {
        await reportBuilderApi.deleteReport(report.id)
        ElMessage.success(t('report_builder_page.messages.report_deleted'))
        rb_fetchReports()
    } catch (e) {
        ElMessage.error(t('report_builder_page.messages.delete_failed'))
    }
}

function rb_editDashboard(dash) {
    rb_dashboardEditMode.value = true
    rb_dashEditId.value = dash.id
    Object.assign(rb_dashForm, {
        name: dash.name,
        description: dash.description || '',
        is_shared: !!dash.is_shared,
        is_default: !!dash.is_default,
    })
    rb_showDashboardDialog.value = true
}

function rb_resetDashForm() {
    rb_dashboardEditMode.value = false
    rb_dashEditId.value = null
    rb_dashForm.name = ''
    rb_dashForm.description = ''
    rb_dashForm.is_shared = false
    rb_dashForm.is_default = false
}

async function rb_submitDashboard() {
    const valid = await rb_dashFormRef.value?.validate().catch(() => false)
    if (!valid) return

    rb_formLoading.value = true
    try {
        if (rb_dashboardEditMode.value && rb_dashEditId.value) {
            await reportBuilderApi.updateDashboard(rb_dashEditId.value, { ...rb_dashForm })
            ElMessage.success(t('report_builder_page.messages.dashboard_updated'))
        } else {
            await reportBuilderApi.createDashboard({ ...rb_dashForm })
            ElMessage.success(t('report_builder_page.messages.dashboard_created'))
        }
        rb_showDashboardDialog.value = false
        rb_resetDashForm()
        rb_fetchDashboards()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        rb_formLoading.value = false
    }
}

async function rb_handleDeleteDashboard(dash) {
    try {
        await reportBuilderApi.deleteDashboard(dash.id)
        ElMessage.success(t('report_builder_page.messages.dashboard_deleted'))
        rb_fetchDashboards()
    } catch (e) {
        ElMessage.error(t('report_builder_page.messages.delete_failed'))
    }
}

// ════════════════════════════════════════════
//  报表调度 (scheduler) — rb_rs_ prefix
// ════════════════════════════════════════════

const rb_rptMainTab = ref('builder')
const rb_rs_tabVisited = ref(false)

const rb_rs_activeTab = ref('schedules')
const rb_rs_loading = ref(false)
const rb_rs_logLoading = ref(false)
const rb_rs_saving = ref(false)
const rb_rs_togglingId = ref(null)
const rb_rs_dialogVisible = ref(false)
const rb_rs_isEditing = ref(false)

const rb_rs_schedules = ref([])
const rb_rs_schedulableReports = ref([])
const rb_rs_deliveryLogs = ref([])
const rb_rs_recentDeliveries = ref([])
const rb_rs_logTotal = ref(0)
const rb_rs_logPage = ref(1)
const rb_rs_logPerPage = ref(20)
const rb_rs_logDateRange = ref(null)

const rb_rs_logFilters = reactive({
    status: '',
})

const rb_rs_dashboard = reactive({
    stats: {
        total_schedules: 0,
        active_schedules: 0,
        due_count: 0,
        total_deliveries: 0,
        success_rate: 100,
    },
})

const rb_rs_form = reactive({
    report_id: null,
    cron_expression: '0 8 * * *',
    export_format: 'csv',
    recipients: [],
    subject: '',
    message: '',
    include_chart: true,
    is_active: true,
    max_retries: 3,
})

const rb_rs_editingId = ref(null)

const rb_rs_logStatusKeys = ['pending', 'processing', 'completed', 'failed']
const rb_rs_logStatusFilterKeys = ['processing', 'completed', 'failed']

const rb_rs_logStatusMap = computed(() =>
    Object.fromEntries(rb_rs_logStatusKeys.map((key) => [key, t(`report_scheduler_page.log_status.${key}`)]))
)

const rb_rs_logStatusFilterOptions = computed(() => [
    { value: '', label: t('report_scheduler_page.filters.all') },
    ...rb_rs_logStatusFilterKeys.map((key) => ({
        value: key,
        label: t(`report_scheduler_page.log_status.${key}`),
    })),
])

// ─── 内层懒加载：调度 tab ───
watch(rb_rptMainTab, (val) => {
    if (val === 'scheduler' && !rb_rs_tabVisited.value) {
        rb_rs_tabVisited.value = true
        rb_rs_loadDashboard()
        rb_rs_loadSchedules()
        rb_rs_fetchLogs()
    }
})

async function rb_rs_loadDashboard() {
    try {
        const { data: res } = await reportSchedulerApi.getDashboard()
        if (res.success) {
            Object.assign(rb_rs_dashboard.stats, res.data.stats)
            rb_rs_recentDeliveries.value = res.data.recent_deliveries || []
        }
    } catch { /* ignore */ }
}

async function rb_rs_loadSchedules() {
    rb_rs_loading.value = true
    try {
        const { data: res } = await reportSchedulerApi.getSchedules({ per_page: 100 })
        if (res.success) {
            rb_rs_schedules.value = res.data.items || []
        }
    } catch { /* ignore */ }
    finally { rb_rs_loading.value = false }
}

async function rb_rs_loadSchedulableReports() {
    try {
        const { data: res } = await reportSchedulerApi.getSchedulableReports()
        if (res.success) {
            rb_rs_schedulableReports.value = res.data || []
        }
    } catch { /* ignore */ }
}

async function rb_rs_fetchLogs() {
    rb_rs_logLoading.value = true
    try {
        const params = {
            page: rb_rs_logPage.value,
            per_page: rb_rs_logPerPage.value,
            status: rb_rs_logFilters.status || undefined,
        }
        if (rb_rs_logDateRange.value) {
            params.date_from = rb_rs_logDateRange.value[0]
            params.date_to = rb_rs_logDateRange.value[1]
        }
        const { data: res } = await reportSchedulerApi.getDeliveryLogs(params)
        if (res.success) {
            rb_rs_deliveryLogs.value = res.data.items || []
            rb_rs_logTotal.value = res.data.total || 0
        }
    } catch { /* ignore */ }
    finally { rb_rs_logLoading.value = false }
}

function rb_rs_showCreateDialog() {
    rb_rs_isEditing.value = false
    rb_rs_form.report_id = null
    rb_rs_form.cron_expression = '0 8 * * *'
    rb_rs_form.export_format = 'csv'
    rb_rs_form.recipients = []
    rb_rs_form.subject = ''
    rb_rs_form.message = ''
    rb_rs_form.include_chart = true
    rb_rs_form.is_active = true
    rb_rs_form.max_retries = 3
    rb_rs_loadSchedulableReports()
    rb_rs_dialogVisible.value = true
}

function rb_rs_editSchedule(schedule) {
    rb_rs_isEditing.value = true
    rb_rs_form.report_id = schedule.report_id
    rb_rs_form.cron_expression = schedule.cron_expression
    rb_rs_form.export_format = schedule.export_format
    rb_rs_form.recipients = schedule.recipients ? JSON.parse(JSON.stringify(schedule.recipients)) : []
    rb_rs_form.subject = schedule.subject || ''
    rb_rs_form.message = schedule.message || ''
    rb_rs_form.include_chart = schedule.include_chart !== false
    rb_rs_form.is_active = schedule.is_active
    rb_rs_form.max_retries = schedule.max_retries
    rb_rs_editingId.value = schedule.id
    rb_rs_dialogVisible.value = true
}

async function rb_rs_saveSchedule() {
    if (!rb_rs_form.report_id) {
        ElMessage.warning(t('report_scheduler_page.rules.report_required'))
        return
    }
    if (!rb_rs_form.cron_expression?.trim()) {
        ElMessage.warning(t('report_scheduler_page.rules.cron_required'))
        return
    }
    rb_rs_saving.value = true
    try {
        const payload = {
            report_id: rb_rs_form.report_id,
            cron_expression: rb_rs_form.cron_expression.trim(),
            export_format: rb_rs_form.export_format,
            recipients: rb_rs_form.recipients.filter(r => r.email),
            subject: rb_rs_form.subject || null,
            message: rb_rs_form.message || null,
            include_chart: rb_rs_form.include_chart,
            is_active: rb_rs_form.is_active,
            max_retries: rb_rs_form.max_retries,
        }

        let res
        if (rb_rs_isEditing.value) {
            res = await reportSchedulerApi.updateSchedule(rb_rs_editingId.value, payload)
        } else {
            res = await reportSchedulerApi.createSchedule(payload)
        }

        if (res.data.success) {
            ElMessage.success(rb_rs_isEditing.value ? t('report_scheduler_page.messages.schedule_updated') : t('report_scheduler_page.messages.schedule_created'))
            rb_rs_dialogVisible.value = false
            await rb_rs_loadSchedules()
            await rb_rs_loadDashboard()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('messages.failed'))
    } finally {
        rb_rs_saving.value = false
    }
}

async function rb_rs_deleteSchedule(schedule) {
    try {
        const { data: res } = await reportSchedulerApi.deleteSchedule(schedule.id)
        if (res.success) {
            ElMessage.success(t('report_scheduler_page.messages.schedule_deleted'))
            await rb_rs_loadSchedules()
            await rb_rs_loadDashboard()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('messages.failed'))
    }
}

async function rb_rs_toggleSchedule(schedule) {
    rb_rs_togglingId.value = schedule.id
    try {
        const { data: res } = await reportSchedulerApi.toggleSchedule(schedule.id)
        if (res.success) {
            schedule.is_active = res.data.is_active
            ElMessage.success(res.data.is_active ? t('report_scheduler_page.messages.schedule_enabled') : t('report_scheduler_page.messages.schedule_paused'))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('messages.failed'))
    } finally {
        rb_rs_togglingId.value = null
    }
}

async function rb_rs_triggerSchedule(schedule) {
    try {
        const { data: res } = await reportSchedulerApi.triggerSchedule(schedule.id)
        if (res.success) {
            ElMessage.success(t('report_scheduler_page.messages.trigger_ok'))
            await rb_rs_loadSchedules()
            await rb_rs_loadDashboard()
            await rb_rs_fetchLogs()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('report_scheduler_page.messages.trigger_failed'))
    }
}

function rb_rs_addRecipient() {
    rb_rs_form.recipients.push({ email: '', name: '' })
}

function rb_rs_removeRecipient(index) {
    rb_rs_form.recipients.splice(index, 1)
}

function rb_rs_formatDate(date) {
    if (!date) return '-'
    const d = new Date(date)
    const pad = n => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

function rb_rs_formatFileSize(bytes) {
    if (!bytes) return '-'
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function rb_rs_truncate(str, len) {
    if (!str) return ''
    return str.length > len ? str.substring(0, len) + '...' : str
}

function rb_rs_logStatusType(status) {
    const map = { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger' }
    return map[status] || 'info'
}

function rb_rs_logStatusLabel(status) {
    return rb_rs_logStatusMap.value[status] || status
}

// ─── 外层懒加载：报表工具 tab ───
function rb_initBuilderData() {
    rb_fetchDashboard()
    rb_fetchReports()
    rb_fetchDashboards()
}

watch(reportCenterTab, (val) => {
    if (val === 'builder' && !rb_tabVisited.value) {
        rb_tabVisited.value = true
        nextTick(() => rb_initBuilderData())
    }
})
</script>

<style scoped>
/* ─── 营收报表 styles ─── */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}
.page-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.ml-2 { margin-left: 8px; }

.metric-card {
    transition: transform .2s;
}
.metric-card:hover {
    transform: translateY(-2px);
}
.metric-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 4px;
}
.metric-value {
    font-size: 26px;
    font-weight: 700;
    line-height: 1.3;
}
.metric-value.primary { color: #0f172a; }
.metric-value.success { color: #67c23a; }
.metric-value.warning { color: #e6a23c; }
.metric-sub {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
    display: flex;
    align-items: center;
}

.mrr-row {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    gap: 8px;
}
.mrr-label {
    width: 60px;
    font-size: 13px;
    color: #606266;
    flex-shrink: 0;
}
.mrr-bar-bg {
    flex: 1;
    height: 20px;
    background: #f0f2f5;
    border-radius: 4px;
    overflow: hidden;
}
.mrr-bar {
    height: 100%;
    border-radius: 4px;
    transition: width .5s ease;
    min-width: 4px;
}
.mrr-value {
    width: 80px;
    text-align: right;
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    flex-shrink: 0;
}

.trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    min-height: 240px;
    padding: 8px 0;
}
.trend-bar-group {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    min-width: 0;
}
.trend-bar-wrapper {
    flex: 1;
    display: flex;
    align-items: flex-end;
    width: 100%;
}
.trend-bar {
    width: 100%;
    max-width: 36px;
    margin: 0 auto;
    background: linear-gradient(to top, #0f172a, #94a3b8);
    border-radius: 3px 3px 0 0;
    transition: height .4s ease;
    cursor: pointer;
    min-height: 4px;
}
.trend-bar:hover {
    opacity: .8;
}
.trend-label {
    font-size: 10px;
    color: #909399;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}
.trend-value {
    font-size: 10px;
    font-weight: 600;
    color: #303133;
    text-align: center;
}
.trend-growth {
    font-size: 9px;
}
.trend-growth.up { color: #67c23a; }
.trend-growth.down { color: #f56c6c; }

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tier-card {
    text-align: center;
}
.tier-value {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}
.tier-count {
    font-size: 12px;
    color: #909399;
    margin-top: 4px;
}

.text-danger { color: #f56c6c; }

/* ─── 报表工具 (rb_ 前缀) styles ─── */
.rb-content {
    padding: 16px;
}

.rb-content .rb-stat-card {
    border-radius: 8px;
}
.rb-content .rb-stat-content {
    display: flex;
    align-items: center;
    gap: 16px;
}
.rb-content .rb-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
}
.rb-content .bg-blue { background: linear-gradient(135deg, #0f172a, #1e293b); }
.rb-content .bg-green { background: linear-gradient(135deg, #67c23a, #529b2e); }
.rb-content .bg-orange { background: linear-gradient(135deg, #e6a23c, #b88230); }
.rb-content .bg-purple { background: linear-gradient(135deg, #909399, #606266); }
.rb-content .rb-stat-info {
    display: flex;
    flex-direction: column;
}
.rb-content .rb-stat-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}
.rb-content .rb-stat-label {
    font-size: 13px;
    color: #909399;
}
.rb-content .rb-tab-toolbar {
    margin-bottom: 16px;
}
.rb-content .rb-metrics-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.rb-content .mt-1 { margin-top: 4px; }
.rb-content .mt-3 { margin-top: 12px; }
.rb-content .mt-4 { margin-top: 16px; }
.rb-content .mb-2 { margin-bottom: 8px; }
.rb-content .rb-flex-center {
    display: flex;
    justify-content: center;
}

/* ─── Scheduler styles ─── */
.rb-content .rb-rs-page-header h2 {
    margin: 0 0 4px;
    font-size: 20px;
}
.rb-content .rb-rs-page-header .text-muted {
    margin: 0 0 16px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}

.rb-content .rb-rs-stat-card {
    text-align: center;
    padding: 8px 0;
}
.rb-content .rb-rs-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.rb-content .rb-rs-stat-value.active { color: var(--el-color-success); }
.rb-content .rb-rs-stat-value.warn { color: var(--el-color-warning); }
.rb-content .rb-rs-stat-value.success { color: var(--el-color-success); }

.rb-content .rb-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.rb-content .rb-filter-bar {
    display: flex;
    gap: 8px;
    align-items: center;
}

.rb-content .rb-cron-badge {
    background: var(--el-fill-color);
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
    color: var(--el-color-primary);
}

.rb-content .rb-run-stats {
    font-size: 13px;
}

.rb-content .rb-recipient-list {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
}
.rb-content .rb-recipient-tag {
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.rb-content .rb-error-msg {
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rb-content .rb-recipient-editor {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.rb-content .rb-recipient-row {
    display: flex;
    align-items: center;
    gap: 4px;
}

.rb-content .rb-timeline-content {
    display: flex;
    align-items: center;
    gap: 8px;
}
.rb-content .rb-timeline-meta {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}
.rb-content .rb-timeline-error {
    font-size: 12px;
    color: var(--el-color-danger);
    margin-top: 2px;
}

.rb-content .rb-pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

/* ─── Shared utilities ─── */
.text-muted {
    color: #909399;
    font-size: 12px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}
.stat-label {
    font-size: 13px;
    color: #909399;
}
</style>
