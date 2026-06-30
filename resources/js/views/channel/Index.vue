<template>
    <div class="channel-page">
        <div class="page-header">
            <div class="header-left">
                <h2>渠道合作伙伴门户</h2>
                <span class="header-subtitle">经销商管理 · 佣金结算 · 业绩看板</span>
            </div>
            <div class="header-right">
                <el-button type="primary" plain @click="refreshAll" :loading="refreshing">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value">{{ stats.total_partners || '-' }}</div>
                        <div class="stat-label">合作伙伴总数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-success">{{ stats.active_partners || '-' }}</div>
                        <div class="stat-label">活跃伙伴</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-warning">{{ stats.pending_approval || '-' }}</div>
                        <div class="stat-label">待审批</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-primary">¥{{ formatMoney(stats.total_settled) }}</div>
                        <div class="stat-label">累计佣金</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-success">¥{{ formatMoney(stats.total_paid) }}</div>
                        <div class="stat-label">已支付</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value text-danger">¥{{ formatMoney(stats.pending_payouts) }}</div>
                        <div class="stat-label">待提现</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ============ 看板 ============ -->
            <el-tab-pane label="看板" name="dashboard">
                <template #label>
                    <el-icon><DataBoard /></el-icon> 看板
                </template>

                <el-row :gutter="16">
                    <!-- 等级分布 -->
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header>合作伙伴等级分布</template>
                            <div v-if="Object.keys(stats.level_distribution || {}).length">
                                <div v-for="(count, level) in stats.level_distribution" :key="level" class="dist-bar">
                                    <div class="dist-info">
                                        <span>{{ levelLabel(level) }}</span>
                                        <span>{{ count }} 人</span>
                                    </div>
                                    <el-progress
                                        :percentage="percentOf(count, stats.total_partners)"
                                        :color="levelColor(level)"
                                        :stroke-width="18"
                                    />
                                </div>
                            </div>
                            <el-empty v-else description="暂无数据" />
                        </el-card>
                    </el-col>

                    <!-- 月度趋势 -->
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header>月度佣金趋势</template>
                            <div v-if="stats.monthly_trend?.length">
                                <div v-for="m in stats.monthly_trend" :key="m.period" class="trend-bar">
                                    <div class="trend-info">
                                        <span class="trend-period">{{ m.period }}</span>
                                        <span class="trend-amount">¥{{ formatMoney(m.amount) }}</span>
                                    </div>
                                    <el-progress
                                        :percentage="trendPercent(m.amount, stats.monthly_trend)"
                                        color="#409eff"
                                        :stroke-width="14"
                                    />
                                </div>
                            </div>
                            <el-empty v-else description="暂无趋势数据" />
                        </el-card>
                    </el-col>

                    <!-- TOP 合作伙伴 -->
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header>
                                <span class="flex-between">
                                    <span>本月 TOP 合作伙伴</span>
                                    <el-button text type="primary" size="small" @click="activeTab = 'partners'">查看全部</el-button>
                                </span>
                            </template>
                            <el-table :data="stats.top_partners || []" size="small" max-height="400">
                                <el-table-column label="排名" width="50" type="index" />
                                <el-table-column label="姓名" min-width="100" prop="name" />
                                <el-table-column label="等级" width="80">
                                    <template #default="{ row }">
                                        <el-tag :type="levelTag(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="本月佣金" width="120">
                                    <template #default="{ row }">
                                        ¥{{ formatMoney(row.monthly_amount) }}
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ============ 合作伙伴管理 ============ -->
            <el-tab-pane label="合作伙伴" name="partners">
                <template #label>
                    <el-icon><User /></el-icon> 合作伙伴
                </template>

                <div class="tab-toolbar">
                    <el-form :inline="true" size="small">
                        <el-form-item>
                            <el-input v-model="searchPartner" placeholder="搜索编号/姓名/公司" clearable @clear="loadPartners" @keyup.enter="loadPartners" style="width: 220px;" />
                        </el-form-item>
                        <el-form-item>
                            <el-select v-model="filterStatus" placeholder="状态" clearable @change="loadPartners">
                                <el-option label="活跃" value="active" />
                                <el-option label="待审批" value="pending" />
                                <el-option label="已暂停" value="suspended" />
                                <el-option label="已终止" value="terminated" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-select v-model="filterLevel" placeholder="等级" clearable @change="loadPartners">
                                <el-option label="普通" value="regular" />
                                <el-option label="银牌" value="silver" />
                                <el-option label="金牌" value="gold" />
                                <el-option label="铂金" value="platinum" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>

                <el-table :data="partners" v-loading="loadingPartners" stripe>
                    <el-table-column label="编号" width="120" prop="agent_code" />
                    <el-table-column label="姓名/公司" min-width="140">
                        <template #default="{ row }">
                            <div>{{ row.name || row.contact_name }}</div>
                            <div class="text-muted" style="font-size:12px;">{{ row.company || '-' }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="邮箱" width="180" prop="email" />
                    <el-table-column label="等级" width="80">
                        <template #default="{ row }">
                            <el-tag :type="levelTag(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">
                                {{ statusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="佣金率" width="80">
                        <template #default="{ row }">{{ row.effective_rate }}%</template>
                    </el-table-column>
                    <el-table-column label="累计赚取" width="110">
                        <template #default="{ row }">¥{{ formatMoney(row.total_earned) }}</template>
                    </el-table-column>
                    <el-table-column label="可提现" width="110">
                        <template #default="{ row }">
                            <span class="text-success">¥{{ formatMoney(row.available_balance) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="200" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" @click="viewPartnerDetail(row)">详情</el-button>
                            <el-button
                                v-if="row.status === 'pending'"
                                size="small" type="success"
                                @click="handleApprove(row)"
                            >
                                批准
                            </el-button>
                            <el-dropdown trigger="click" v-if="row.status === 'active'">
                                <el-button size="small">
                                    等级 <el-icon><ArrowDown /></el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            v-for="l in ['regular', 'silver', 'gold', 'platinum']"
                                            :key="l"
                                            :disabled="l === row.level"
                                            @click="handleChangeLevel(row, l)"
                                        >
                                            {{ levelLabel(l) }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="partnerPagination">
                    <el-pagination
                        v-model:current-page="partnerPagination.current_page"
                        :page-size="partnerPagination.per_page"
                        :total="partnerPagination.total"
                        layout="total, prev, pager, next"
                        @current-change="loadPartners"
                    />
                </div>

                <!-- 合作伙伴详情 Dialog -->
                <el-dialog v-model="showPartnerDialog" :title="'合作伙伴详情 - ' + (detailAgent?.agent_code || '')" width="800px">
                    <div v-loading="loadingDetail">
                        <el-descriptions :column="3" border size="small" v-if="detailAgent">
                            <el-descriptions-item label="姓名">{{ detailAgent.name || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="邮箱">{{ detailAgent.email || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="联系电话">{{ detailAgent.contact_phone || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="公司">{{ detailAgent.company || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="等级">
                                <el-tag :type="levelTag(detailAgent.level)" size="small">{{ levelLabel(detailAgent.level) }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="状态">
                                <el-tag :type="detailAgent.status === 'active' ? 'success' : 'info'" size="small">
                                    {{ statusLabel(detailAgent.status) }}
                                </el-tag>
                            </el-descriptions-item>
                        </el-descriptions>

                        <el-divider>业绩统计</el-divider>
                        <el-row :gutter="12" v-if="detailStats">
                            <el-col :span="6">
                                <div class="mini-stat">
                                    <div class="mini-value">¥{{ formatMoney(detailStats.total_settled) }}</div>
                                    <div class="mini-label">累计结算</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="mini-stat">
                                    <div class="mini-value">¥{{ formatMoney(detailStats.available_balance) }}</div>
                                    <div class="mini-label">可用余额</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="mini-stat">
                                    <div class="mini-value">{{ detailStats.active_subscriptions }}</div>
                                    <div class="mini-label">关联订阅</div>
                                </div>
                            </el-col>
                            <el-col :span="6">
                                <div class="mini-stat">
                                    <div class="mini-value">{{ detailStats.settlement_count }}</div>
                                    <div class="mini-label">结算次数</div>
                                </div>
                            </el-col>
                        </el-row>

                        <el-divider>月度业绩</el-divider>
                        <el-table :data="monthlyPerformance || []" size="small" max-height="250">
                            <el-table-column prop="period" label="月份" width="100" />
                            <el-table-column label="佣金金额" width="120">
                                <template #default="{ row }">¥{{ formatMoney(row.amount) }}</template>
                            </el-table-column>
                            <el-table-column prop="count" label="结算次数" width="80" />
                        </el-table>

                        <el-divider>最近结算</el-divider>
                        <el-table :data="detailSettlements || []" size="small" max-height="200">
                            <el-table-column label="License" width="150" prop="subscription?.license_key" />
                            <el-table-column label="金额" width="100">
                                <template #default="{ row }">¥{{ formatMoney(row.commission_amount) }}</template>
                            </el-table-column>
                            <el-table-column label="状态" width="80">
                                <template #default="{ row }">
                                    <el-tag size="small" :type="row.status === 'released' ? 'success' : 'warning'">
                                        {{ row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column label="时间" width="160">
                                <template #default="{ row }">{{ row.created_at ? formatDate(row.created_at) : '-' }}</template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-dialog>
            </el-tab-pane>

            <!-- ============ 结算明细 ============ -->
            <el-tab-pane label="结算明细" name="settlements">
                <template #label>
                    <el-icon><Money /></el-icon> 结算
                </template>

                <div class="tab-toolbar">
                    <el-form :inline="true" size="small">
                        <el-form-item>
                            <el-select v-model="settlementFilter.agent_id" clearable placeholder="选择合作伙伴" style="width: 180px;" @change="loadSettlements">
                                <el-option v-for="p in partnerOptions" :key="p.id" :label="p.label" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-select v-model="settlementFilter.status" clearable placeholder="状态" @change="loadSettlements">
                                <el-option label="待结算" value="pending" />
                                <el-option label="待释放" value="pending_release" />
                                <el-option label="已释放" value="released" />
                                <el-option label="已退款" value="refunded" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>

                <el-table :data="settlements" v-loading="loadingSettlements" stripe>
                    <el-table-column prop="period" label="周期" width="80" />
                    <el-table-column label="合作伙伴" min-width="120">
                        <template #default="{ row }">{{ row.agent?.user?.name || row.agent?.agent_code }}</template>
                    </el-table-column>
                    <el-table-column label="License" width="150" prop="subscription?.license_key" />
                    <el-table-column label="发票金额" width="100">
                        <template #default="{ row }">¥{{ formatMoney(row.invoice_amount) }}</template>
                    </el-table-column>
                    <el-table-column label="佣金率" width="70">
                        <template #default="{ row }">{{ row.commission_rate }}%</template>
                    </el-table-column>
                    <el-table-column label="佣金" width="100">
                        <template #default="{ row }">
                            <strong>¥{{ formatMoney(row.commission_amount) }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="settlementStatusType(row.status)" size="small">
                                {{ settlementStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="结算时间" width="150">
                        <template #default="{ row }">{{ row.created_at ? formatDate(row.created_at) : '-' }}</template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="settlementPagination">
                    <el-pagination
                        v-model:current-page="settlementPagination.current_page"
                        :page-size="settlementPagination.per_page"
                        :total="settlementPagination.total"
                        layout="total, prev, pager, next"
                        @current-change="loadSettlements"
                    />
                </div>
            </el-tab-pane>

            <!-- ============ 推广链接 ============ -->
            <el-tab-pane label="推广链接" name="links">
                <template #label>
                    <el-icon><Link /></el-icon> 推广链接
                </template>

                <div class="tab-toolbar">
                    <el-form :inline="true" size="small">
                        <el-form-item>
                            <el-select v-model="linkFilter.agent_id" clearable placeholder="选择合作伙伴" style="width: 180px;" @change="loadReferralLinks">
                                <el-option v-for="p in partnerOptions" :key="p.id" :label="p.label" :value="p.id" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                </div>

                <el-table :data="referralLinks" v-loading="loadingLinks" stripe>
                    <el-table-column label="合作伙伴" min-width="120">
                        <template #default="{ row }">{{ row.agent?.user?.name || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="名称" width="120" prop="name" />
                    <el-table-column label="推广码" width="120">
                        <template #default="{ row }">
                            <code>{{ row.code }}</code>
                        </template>
                    </el-table-column>
                    <el-table-column label="推广链接" min-width="250">
                        <template #default="{ row }">
                            <span class="mono" style="font-size:12px;">{{ row.target_url || '-' }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="活跃" width="60">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? '是' : '否' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="创建时间" width="150">
                        <template #default="{ row }">{{ row.created_at ? formatDate(row.created_at) : '-' }}</template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="linkPagination">
                    <el-pagination
                        v-model:current-page="linkPagination.current_page"
                        :page-size="linkPagination.per_page"
                        :total="linkPagination.total"
                        layout="total, prev, pager, next"
                        @current-change="loadReferralLinks"
                    />
                </div>
            </el-tab-pane>

            <!-- ============ 等级权益 ============ -->
            <el-tab-pane label="等级权益" name="tiers">
                <template #label>
                    <el-icon><Medal /></el-icon> 等级权益
                </template>

                <el-row :gutter="16">
                    <el-col :span="6" v-for="(info, tier) in tierData" :key="tier">
                        <el-card shadow="hover" :class="['tier-card', 'tier-' + tier]">
                            <div class="tier-header">
                                <div class="tier-icon" :style="{ background: tierColors[tier] }">
                                    <el-icon :size="32"><Medal /></el-icon>
                                </div>
                                <div class="tier-name">{{ info.label }}</div>
                            </div>
                            <div class="tier-rate">佣金比例: <strong>{{ info.rate }}</strong></div>
                            <div v-if="info.min_requirements" class="tier-req">达标要求: {{ info.min_requirements }}</div>
                            <el-divider />
                            <div class="tier-benefits">
                                <div v-for="b in info.benefits" :key="b" class="benefit-item">
                                    <el-icon color="#67c23a"><CircleCheck /></el-icon>
                                    <span>{{ b }}</span>
                                </div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Refresh, DataBoard, User, Money, Link,
    Medal, GoldMedal,
    ArrowDown, CircleCheck,
} from '@element-plus/icons-vue';
import channelApi from '@/api/channelPartner';

const activeTab = ref('dashboard');
const refreshing = ref(false);

// ── 看板 ──
const stats = reactive({
    total_partners: 0, active_partners: 0, pending_approval: 0,
    total_settled: 0, total_paid: 0, pending_payouts: 0,
    level_distribution: {}, monthly_trend: [], top_partners: [],
});

// ── 合作伙伴 ──
const loadingPartners = ref(false);
const partners = ref([]);
const partnerPagination = ref(null);
const searchPartner = ref('');
const filterStatus = ref('');
const filterLevel = ref('');

// 详情
const showPartnerDialog = ref(false);
const loadingDetail = ref(false);
const detailAgent = ref(null);
const detailStats = ref(null);
const detailSettlements = ref([]);
const monthlyPerformance = ref([]);

// ── 结算 ──
const loadingSettlements = ref(false);
const settlements = ref([]);
const settlementPagination = ref(null);
const settlementFilter = reactive({ agent_id: null, status: null });

// ── 推广链接 ──
const loadingLinks = ref(false);
const referralLinks = ref([]);
const linkPagination = ref(null);
const linkFilter = reactive({ agent_id: null });

// ── 合作伙伴选项 ──
const partnerOptions = ref([]);

// ── 等级权益 ──
const tierData = {
    regular: {
        label: '普通合作伙伴', rate: '最高 5%',
        benefits: ['基础佣金比例', '标准推广链接', '月度结算'],
    },
    silver: {
        label: '银牌合作伙伴', rate: '最高 10%',
        min_requirements: '月销售额 ¥10,000',
        benefits: ['佣金比例提升至 10%', '专属客户经理', '优先结算'],
    },
    gold: {
        label: '金牌合作伙伴', rate: '最高 20%',
        min_requirements: '月销售额 ¥50,000',
        benefits: ['佣金比例提升至 20%', '专属客户经理', '优先结算', '市场活动支持'],
    },
    platinum: {
        label: '铂金合作伙伴', rate: '最高 30%',
        min_requirements: '月销售额 ¥200,000',
        benefits: ['最高佣金比例 30%', '专属客户经理', '优先结算', '市场活动支持', '技术优先支持', '联合品牌推广'],
    },
};
const tierColors = { regular: '#909399', silver: '#909399', gold: '#e6a23c', platinum: '#409eff' };

// ============= 工具 =============

function formatDate(d) {
    return d ? new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' }) : '-';
}

function formatMoney(v) {
    return v ? Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '0.00';
}

function percentOf(count, total) {
    return total > 0 ? Math.round((count / total) * 100) : 0;
}

function trendPercent(amount, trend) {
    const max = Math.max(...(trend || []).map(m => Number(m.amount) || 0));
    return max > 0 ? Math.round((amount / max) * 100) : 0;
}

function levelLabel(l) {
    return { regular: '普通', silver: '银牌', gold: '金牌', platinum: '铂金' }[l] || l;
}

function levelTag(l) {
    return { regular: 'info', silver: 'info', gold: 'warning', platinum: 'primary' }[l] || 'info';
}

function levelColor(l) {
    return { regular: '#909399', silver: '#909399', gold: '#e6a23c', platinum: '#409eff' }[l];
}

function statusLabel(s) {
    return { active: '活跃', pending: '待审批', suspended: '已暂停', terminated: '已终止' }[s] || s;
}

function settlementStatusType(s) {
    return { pending: 'warning', pending_release: 'info', released: 'success', refunded: 'danger' }[s] || 'info';
}

function settlementStatusLabel(s) {
    return { pending: '待结算', pending_release: '待释放', released: '已释放', refunded: '已退款' }[s] || s;
}

// ============= 加载数据 =============

async function loadDashboard() {
    try {
        const { data: res } = await channelApi.dashboard();
        Object.assign(stats, res.data || {});
    } catch { /* ignore */ }
}

async function loadPartners(page = 1) {
    loadingPartners.value = true;
    try {
        const params = { page, per_page: 20 };
        if (searchPartner.value) params.search = searchPartner.value;
        if (filterStatus.value) params.status = filterStatus.value;
        if (filterLevel.value) params.level = filterLevel.value;
        const { data: res } = await channelApi.partners(params);
        const paginated = res.data;
        partners.value = paginated?.data || paginated || [];
        if (paginated?.current_page) partnerPagination.value = paginated;

        // 构建选项
        partnerOptions.value = (paginated?.data || paginated || []).map(p => ({
            id: p.id,
            label: `${p.agent_code} - ${p.name || p.contact_name}`,
        }));
    } catch {
        partners.value = [];
    } finally {
        loadingPartners.value = false;
    }
}

async function loadSettlements(page = 1) {
    loadingSettlements.value = true;
    try {
        const params = { page, per_page: 20 };
        if (settlementFilter.agent_id) params.agent_id = settlementFilter.agent_id;
        if (settlementFilter.status) params.status = settlementFilter.status;
        const { data: res } = await channelApi.settlements(params);
        const paginated = res.data;
        settlements.value = paginated?.data || paginated || [];
        if (paginated?.current_page) settlementPagination.value = paginated;
    } catch {
        settlements.value = [];
    } finally {
        loadingSettlements.value = false;
    }
}

async function loadReferralLinks(page = 1) {
    loadingLinks.value = true;
    try {
        const params = { page, per_page: 20 };
        if (linkFilter.agent_id) params.agent_id = linkFilter.agent_id;
        const { data: res } = await channelApi.referralLinks(params);
        const paginated = res.data;
        referralLinks.value = paginated?.data || paginated || [];
        if (paginated?.current_page) linkPagination.value = paginated;
    } catch {
        referralLinks.value = [];
    } finally {
        loadingLinks.value = false;
    }
}

// ============= 操作 =============

async function refreshAll() {
    refreshing.value = true;
    await Promise.all([loadDashboard(), loadPartners(), loadSettlements(), loadReferralLinks()]);
    refreshing.value = false;
    ElMessage.success('数据已刷新');
}

async function viewPartnerDetail(row) {
    showPartnerDialog.value = true;
    loadingDetail.value = true;
    detailAgent.value = null;
    detailStats.value = null;
    detailSettlements.value = [];
    monthlyPerformance.value = [];
    try {
        const { data: res } = await channelApi.showPartner(row.id);
        const d = res.data;
        detailAgent.value = d.agent;
        detailStats.value = d.stats;
        detailSettlements.value = d.recent_settlements || [];
        monthlyPerformance.value = d.monthly_performance || [];
    } catch {
        ElMessage.error('加载详情失败');
    } finally {
        loadingDetail.value = false;
    }
}

async function handleApprove(row) {
    try {
        await channelApi.approvePartner(row.id);
        ElMessage.success('合作伙伴已批准');
        loadPartners();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '审批失败');
    }
}

async function handleChangeLevel(row, level) {
    try {
        await channelApi.updatePartnerLevel(row.id, { level });
        ElMessage.success('等级已更新');
        loadPartners();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '更新失败');
    }
}

onMounted(() => {
    loadDashboard();
    loadPartners();
    loadSettlements();
    loadReferralLinks();
});
</script>

<style scoped>
.channel-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 8px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.text-muted { color: var(--el-text-color-secondary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }
.text-primary { color: var(--el-color-primary); }

.stat-item { text-align: center; padding: 8px 0; }
.stat-label { font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 4px; }
.stat-value { font-size: 24px; font-weight: 700; }

.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.dist-bar, .trend-bar {
    margin-bottom: 12px;
}
.dist-info, .trend-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    font-size: 13px;
}
.trend-period { font-weight: 600; }
.trend-amount { color: var(--el-color-primary); }

.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.mini-stat { text-align: center; padding: 12px; background: var(--el-fill-color-lighter); border-radius: 8px; }
.mini-value { font-size: 20px; font-weight: 700; color: var(--el-color-primary); }
.mini-label { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 4px; }

.mono { font-family: 'Courier New', monospace; font-size: 12px; }

/* 等级卡片 */
.tier-card {
    text-align: center;
    transition: transform 0.2s;
}
.tier-card:hover { transform: translateY(-4px); }
.tier-header { margin-bottom: 12px; }
.tier-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    margin-bottom: 8px;
}
.tier-name { font-size: 18px; font-weight: 700; }
.tier-rate { font-size: 15px; margin: 8px 0; }
.tier-req { font-size: 12px; color: var(--el-color-warning); }
.tier-benefits { text-align: left; }
.benefit-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 0;
    font-size: 13px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
