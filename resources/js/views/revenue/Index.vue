<template>
    <div class="revenue-dashboard">
        <div class="page-header">
            <div>
                <h2>平台收益总览</h2>
                <p class="text-muted">渠道 ROI 分析、收益趋势与质量评估</p>
            </div>
            <div class="header-actions">
                <el-radio-group v-model="trendMonths" size="small" @change="loadAll" class="mr-3">
                    <el-radio-button :value="12">12个月</el-radio-button>
                    <el-radio-button :value="24">24个月</el-radio-button>
                    <el-radio-button :value="36">36个月</el-radio-button>
                </el-radio-group>
                <el-button type="primary" @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <!-- ── 第一行：关键财务指标 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">本月收入</div>
                            <div class="metric-value">¥{{ formatMoney(overview.revenue.month) }}</div>
                            <div class="metric-sub">累计 ¥{{ formatMoney(overview.revenue.total) }}</div>
                        </div>
                        <el-icon :size="36" color="#409eff"><TrendCharts /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">本月净收入</div>
                            <div class="metric-value" :class="overview.net_revenue.month >= 0 ? 'success' : 'danger'">
                                ¥{{ formatMoney(overview.net_revenue.month) }}
                            </div>
                            <div class="metric-sub">净收益率 {{ calcRate(overview.net_revenue.month, overview.revenue.month) }}%</div>
                        </div>
                        <el-icon :size="36" :color="overview.net_revenue.month >= 0 ? '#67c23a' : '#f56c6c'"><Money /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">MRR (月经常性收入)</div>
                            <div class="metric-value">¥{{ formatMoney(overview.mrr) }}</div>
                            <div class="metric-sub">ARR ¥{{ formatMoney(overview.arr) }}</div>
                        </div>
                        <el-icon :size="36" color="#b37feb"><Coin /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :xs="24" :sm="12" :md="6">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-content">
                        <div class="metric-info">
                            <div class="metric-label">活跃订阅 / 代理</div>
                            <div class="metric-value">{{ overview.subscriptions.active }} / {{ overview.active_agents }}</div>
                            <div class="metric-sub">本月新增 {{ overview.subscriptions.new }} | 流失 {{ overview.subscriptions.churned }}</div>
                        </div>
                        <el-icon :size="36" color="#e6a23c"><User /></el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第二行：月收入趋势 + 支付方式分布 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="16">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><TrendCharts /></el-icon> 月度收益趋势 <small class="text-muted">(收入/佣金/退款/净收入)</small></span>
                        </div>
                    </template>
                    <div v-loading="loading" class="trend-table-wrap">
                        <el-table :data="revenueTrend" stripe size="small" max-height="350" v-if="revenueTrend.length">
                            <el-table-column prop="period" label="月份" width="90" />
                            <el-table-column label="收入" width="130">
                                <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                            </el-table-column>
                            <el-table-column label="佣金支出" width="120">
                                <template #default="{ row }">
                                    <span class="text-warning">¥{{ formatMoney(row.commission) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="退款" width="110">
                                <template #default="{ row }">
                                    <span class="text-danger">¥{{ formatMoney(row.refunds) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="净收入" width="130">
                                <template #default="{ row }">
                                    <span :class="row.net_revenue >= 0 ? 'text-success' : 'text-danger'">
                                        ¥{{ formatMoney(row.net_revenue) }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column label="交易笔数" width="90" prop="transaction_count" />
                        </el-table>
                        <el-empty v-else description="暂无数据" />
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><Coin /></el-icon> 支付方式分布</span>
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
                        <el-empty v-if="!paymentMethods.methods.length" description="无数据" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第三行：渠道ROI分析 ── -->
        <el-card class="mb-4" shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Link /></el-icon> 渠道 ROI 分析</span>
                    <el-tag type="info" size="small">综合 ROI: {{ channelRoi.overall.roi > 999 ? '∞' : channelRoi.overall.roi + '%' }}</el-tag>
                </div>
            </template>
            <div v-loading="loading">
                <el-table :data="channelRoi.channels" stripe v-if="channelRoi.channels.length">
                    <el-table-column label="渠道" min-width="120">
                        <template #default="{ row }">
                            <div class="channel-name">
                                <div class="channel-dot" :style="{ background: row.color }"></div>
                                <span>{{ row.name }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="收入" width="140">
                        <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                    </el-table-column>
                    <el-table-column label="退款" width="110">
                        <template #default="{ row }">¥{{ formatMoney(row.refunds) }}</template>
                    </el-table-column>
                    <el-table-column label="佣金" width="120">
                        <template #default="{ row }">¥{{ formatMoney(row.commission) }}</template>
                    </el-table-column>
                    <el-table-column label="净收入" width="140">
                        <template #default="{ row }">
                            <span :class="row.net_revenue >= 0 ? '' : 'text-danger'">¥{{ formatMoney(row.net_revenue) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="ROI" width="100">
                        <template #default="{ row }">
                            <el-tag :type="roiType(row.roi)" size="small" effect="dark">
                                {{ row.roi > 999 ? '∞' : row.roi + '%' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="ARPU" width="120">
                        <template #default="{ row }">¥{{ formatMoney(row.arpu) }}</template>
                    </el-table-column>
                    <el-table-column label="代理数" width="80" prop="agent_count" />
                    <el-table-column label="订阅数" width="130">
                        <template #default="{ row }">{{ row.active_subscription_count }}/{{ row.subscription_count }}</template>
                    </el-table-column>
                </el-table>
                <el-empty v-else description="暂无渠道数据" />

                <!-- 汇总行 -->
                <div class="overall-row" v-if="channelRoi.overall">
                    <div class="overall-label">汇总</div>
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

        <!-- ── 第四行：渠道月度趋势 + 渠道质量 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><DataBoard /></el-icon> 渠道月度趋势</span>
                            <el-radio-group v-model="trendChannel" size="small" @change="onTrendChannelChange">
                                <el-radio-button v-for="c in channelRoi.channels" :key="c.name" :value="c.name">
                                    {{ c.name }}
                                </el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div v-loading="loading" class="trend-table-wrap">
                        <el-table :data="trendChannelData" stripe size="small" max-height="350" v-if="trendChannelData.length">
                            <el-table-column prop="period" label="月份" width="90" />
                            <el-table-column label="收入" width="140">
                                <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                            </el-table-column>
                            <el-table-column label="佣金" width="120">
                                <template #default="{ row }">¥{{ formatMoney(row.commission) }}</template>
                            </el-table-column>
                            <el-table-column label="净收入" width="140">
                                <template #default="{ row }">
                                    <span :class="row.net_revenue >= 0 ? '' : 'text-danger'">¥{{ formatMoney(row.net_revenue) }}</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-else description="暂无数据" />
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span><el-icon><DataAnalysis /></el-icon> 渠道质量分析</span>
                        </div>
                    </template>
                    <div v-loading="loading">
                        <el-table :data="channelQuality" stripe size="small" v-if="channelQuality.length">
                            <el-table-column label="渠道" width="90">
                                <template #default="{ row }">{{ row.channel_name }}</template>
                            </el-table-column>
                            <el-table-column label="客户数" width="80" prop="total_customers" />
                            <el-table-column label="LTV" width="100">
                                <template #default="{ row }">¥{{ formatMoney(row.avg_ltv) }}</template>
                            </el-table-column>
                            <el-table-column label="流失率" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.churn_rate > 30 ? 'danger' : row.churn_rate > 15 ? 'warning' : 'success'" size="small">
                                        {{ row.churn_rate }}%
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="续费率" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.renewal_rate > 50 ? 'success' : row.renewal_rate > 20 ? 'warning' : 'danger'" size="small">
                                        {{ row.renewal_rate }}%
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="订阅天数" width="90" prop="avg_subscription_days" />
                        </el-table>
                        <el-empty v-else description="暂无数据" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ── 第五行：代理层级收益分布 ── -->
        <el-card shadow="hover" v-if="agentLevels.length">
            <template #header>
                <div class="card-header">
                    <span><el-icon><User /></el-icon> 代理层级收益分布</span>
                </div>
            </template>
            <el-table :data="agentLevels" stripe v-loading="loading">
                <el-table-column label="等级" width="120">
                    <template #default="{ row }">
                        <div class="channel-name">
                            <div class="channel-dot" :style="{ background: levelColor(row.level) }"></div>
                            <span>{{ row.level_label }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="代理数" width="80" prop="agent_count" />
                <el-table-column label="总佣金" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.total_commission) }}</template>
                </el-table-column>
                <el-table-column label="已提现" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.total_payout) }}</template>
                </el-table-column>
                <el-table-column label="人均佣金" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.avg_commission_per_agent) }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- ── 第六行：MRR 瀑布图 (M3-59) ── -->
        <el-card class="mb-4" shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Coin /></el-icon> MRR 瀑布图 <small class="text-muted">(月环比：起始MRR → 新增 → 扩展 → 收缩 → 流失 → 结束MRR)</small></span>
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
                            <div class="metric-label">MRR (月经常性收入)</div>
                            <div class="metric-value">¥{{ formatMoney(mrrSummaryData.mrr) }}</div>
                        </div>
                        <el-icon :size="36" color="#b37feb"><Coin /></el-icon>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card shadow="hover">
                    <template #header><span style="font-size:13px;font-weight:500">MRR 构成</span></template>
                    <div class="mrr-composition">
                        <div class="mrr-comp-item" v-if="mrrSummaryData.new_mrr">
                            <span class="mrr-dot" style="background:#67c23a"></span>
                            <span>新增</span>
                            <span class="mrr-val text-success">+¥{{ formatMoney(mrrSummaryData.new_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.expansion_mrr">
                            <span class="mrr-dot" style="background:#409eff"></span>
                            <span>扩展</span>
                            <span class="mrr-val text-success">+¥{{ formatMoney(mrrSummaryData.expansion_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.contraction_mrr">
                            <span class="mrr-dot" style="background:#e6a23c"></span>
                            <span>收缩</span>
                            <span class="mrr-val text-warning">-¥{{ formatMoney(mrrSummaryData.contraction_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.churned_mrr">
                            <span class="mrr-dot" style="background:#f56c6c"></span>
                            <span>流失</span>
                            <span class="mrr-val text-danger">-¥{{ formatMoney(mrrSummaryData.churned_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-item" v-if="mrrSummaryData.reactivation_mrr">
                            <span class="mrr-dot" style="background:#909399"></span>
                            <span>重新激活</span>
                            <span class="mrr-val text-success">+¥{{ formatMoney(mrrSummaryData.reactivation_mrr) }}</span>
                        </div>
                        <div class="mrr-comp-divider"></div>
                        <div class="mrr-comp-item">
                            <span style="font-weight:600">净变化</span>
                            <span class="mrr-val" :class="mrrSummaryData.net_mrr_change >= 0 ? 'text-success' : 'text-danger'">
                                {{ mrrSummaryData.net_mrr_change >= 0 ? '+' : '' }}¥{{ formatMoney(mrrSummaryData.net_mrr_change) }}
                            </span>
                        </div>
                        <div class="mrr-comp-item">
                            <span style="font-weight:600">活跃订阅</span>
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

        <!-- ── 第七行：代理商收益排行榜 (M3-73) ── -->
        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span><el-icon><User /></el-icon> 🏆 代理商收益排行榜 Top {{ leaderboardLimit }}</span>
                    <el-select v-model="leaderboardLimit" size="small" style="width:100px" @change="loadLeaderboard">
                        <el-option :value="10" label="Top 10" />
                        <el-option :value="20" label="Top 20" />
                        <el-option :value="50" label="Top 50" />
                    </el-select>
                </div>
            </template>
            <el-table :data="leaderboardData" stripe v-loading="leaderboardLoading" size="small">
                <el-table-column label="排名" width="60" type="index" />
                <el-table-column label="名称" min-width="140">
                    <template #default="{ row }">{{ row.name }}</template>
                </el-table-column>
                <el-table-column prop="agent_code" label="编码" width="120" />
                <el-table-column label="等级" width="80">
                    <template #default="{ row }">
                        <el-tag :color="levelColor(row.level)" class="text-white" size="small">{{ row.level_label }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="累计收益" width="140">
                    <template #default="{ row }">¥{{ formatMoney(row.total_earned) }}</template>
                </el-table-column>
                <el-table-column label="已提现" width="130">
                    <template #default="{ row }">¥{{ formatMoney(row.total_withdrawn) }}</template>
                </el-table-column>
                <el-table-column label="可用余额" width="130">
                    <template #default="{ row }">¥{{ formatMoney(row.available_balance) }}</template>
                </el-table-column>
                <el-table-column prop="downline_count" label="下级" width="60" />
                <el-table-column label="佣金率" width="70">
                    <template #default="{ row }">{{ row.revenue_share }}%</template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!leaderboardData.length && !leaderboardLoading" description="暂无排行榜数据" />
        </el-card>

        <!-- ── 第八行：月度结算报表 (M3-73) ── -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><FolderOpened /></el-icon> 月度结算报表</span>
                    <el-date-picker v-model="reportMonth" type="month" value-format="YYYY-MM" style="width:150px" @change="loadMonthlyReport" />
                </div>
            </template>
            <div v-loading="reportLoading">
                <el-descriptions :column="4" border size="small" class="mb-3" v-if="monthlyReport">
                    <el-descriptions-item label="月份">{{ monthlyReport.year_month }}</el-descriptions-item>
                    <el-descriptions-item label="收入">¥{{ formatMoney(monthlyReport.revenue) }}</el-descriptions-item>
                    <el-descriptions-item label="退款">¥{{ formatMoney(monthlyReport.refunds) }}</el-descriptions-item>
                    <el-descriptions-item label="佣金支出">¥{{ formatMoney(monthlyReport.commissions) }}</el-descriptions-item>
                    <el-descriptions-item label="已提现">¥{{ formatMoney(monthlyReport.payouts) }}</el-descriptions-item>
                    <el-descriptions-item label="净收入">
                        <span :class="monthlyReport.net_revenue >= 0 ? 'text-success' : 'text-danger'">
                            ¥{{ formatMoney(monthlyReport.net_revenue) }}
                        </span>
                    </el-descriptions-item>
                    <el-descriptions-item label="月环比">
                        <el-tag :type="monthlyReport.growth_rate >= 0 ? 'success' : 'danger'" size="small">
                            {{ monthlyReport.growth_rate >= 0 ? '+' : '' }}{{ monthlyReport.growth_rate }}%
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="新订阅/活跃">{{ monthlyReport.new_subscriptions }}/{{ monthlyReport.active_subscriptions }}</el-descriptions-item>
                </el-descriptions>

                <h4 class="mb-2" v-if="monthlyReport?.channel_breakdown?.length">渠道拆分</h4>
                <el-table :data="monthlyReport?.channel_breakdown ?? []" stripe size="small" v-if="monthlyReport?.channel_breakdown?.length">
                    <el-table-column label="渠道" min-width="100">
                        <template #default="{ row }">{{ row.name }}</template>
                    </el-table-column>
                    <el-table-column label="收入" width="130">
                        <template #default="{ row }">¥{{ formatMoney(row.revenue) }}</template>
                    </el-table-column>
                    <el-table-column label="佣金" width="120">
                        <template #default="{ row }">¥{{ formatMoney(row.commission) }}</template>
                    </el-table-column>
                    <el-table-column label="净收入" width="130">
                        <template #default="{ row }">¥{{ formatMoney(row.net_revenue) }}</template>
                    </el-table-column>
                    <el-table-column label="ROI" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.roi > 0 ? 'success' : 'danger'" size="small">{{ row.roi }}%</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!monthlyReport" description="选择月份查看结算报表" />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
  Refresh, TrendCharts, Money, Coin, User,
  Link, DataBoard, DataAnalysis, Wallet, FolderOpened,
} from '@element-plus/icons-vue';
import revenueApi from '@/api/revenueDashboard';
import MrrWaterfallChart from '@/components/MrrWaterfallChart.vue';
import MrrDrilldownPanel from '@/components/MrrDrilldownPanel.vue';

const loading = ref(false);
const trendMonths = ref(24);
const trendChannel = ref('');

// ── MRR 瀑布图 (M3-59) ──
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

// ── M3-73 代理商排行榜 ──
const leaderboardData = ref([]);
const leaderboardLoading = ref(false);
const leaderboardLimit = ref(20);

// ── M3-73 月度结算报表 ──
const monthlyReport = ref(null);
const reportLoading = ref(false);
const reportMonth = ref('');

const trendChannelData = computed(() => {
  if (!trendChannel.value || !channelTrend.value[trendChannel.value]) return [];
  return channelTrend.value[trendChannel.value];
});

function formatMoney(val) {
  return (parseFloat(val) || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
  return colors[method] || '#409eff';
}

function levelColor(level) {
  const colors = { regular: '#909399', silver: '#909399', gold: '#e6a23c', platinum: '#409eff' };
  return colors[level] || '#909399';
}

function onTrendChannelChange() {
  // computed handles it
}

async function loadAll() {
  loading.value = true;
  try {
    // Load all data in parallel
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

    // Set default trend channel
    if (!trendChannel.value && channelRoi.channels.length) {
      trendChannel.value = channelRoi.channels[0].name;
    }
  } catch (e) {
    ElMessage.error('加载数据失败');
  } finally {
    loading.value = false;
  }

  // 加载MRR数据
  loadMrrWaterfall(6);
  loadMrrSummary();
}

// ── M3-73 代理商排行榜 ──
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

// ── M3-73 月度结算报表 ──
async function loadMonthlyReport() {
  if (!reportMonth.value) return;
  reportLoading.value = true;
  try {
    const res = await revenueApi.monthlyReport({ year_month: reportMonth.value });
    monthlyReport.value = res.data.data;
  } catch (e) {
    ElMessage.error('加载报表失败');
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
