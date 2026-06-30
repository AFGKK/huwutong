<template>
    <div class="crm-page">
        <div class="page-header">
            <div class="header-left">
                <h2>客户关系管理 (CRM)</h2>
                <span class="header-subtitle">客户分群 · RFM 分析 · 流失预测</span>
            </div>
            <div class="header-right">
                <el-button type="primary" plain @click="refreshAll" :loading="refreshing">
                    <el-icon><Refresh /></el-icon> 刷新数据
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ============ 看板 ============ -->
            <el-tab-pane label="CRM 看板" name="dashboard">
                <template #label>
                    <el-icon><DataBoard /></el-icon> 看板
                </template>

                <div v-loading="loadingDashboard">
                    <!-- 客户分布 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>分群分布</template>
                                <div v-if="dashboard.segments?.length">
                                    <div v-for="s in dashboard.segments" :key="s.id" class="segment-bar">
                                        <div class="segment-info">
                                            <span class="segment-name" :style="{ color: s.color || '#409eff' }">
                                                {{ s.name }}
                                            </span>
                                            <span class="segment-count">{{ s.member_count }} 人</span>
                                        </div>
                                        <el-progress
                                            :percentage="percentOf(s.member_count)"
                                            :color="s.color || '#409eff'"
                                            :stroke-width="16"
                                        />
                                    </div>
                                </div>
                                <el-empty v-else description="暂无分群" />
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>RFM 分群分布</template>
                                <div v-if="Object.keys(dashboard.rfm_distribution || {}).length">
                                    <div v-for="(count, seg) in dashboard.rfm_distribution" :key="seg" class="segment-bar">
                                        <div class="segment-info">
                                            <span class="segment-name">{{ rfmLabel(seg) }}</span>
                                            <span class="segment-count">{{ count }} 人</span>
                                        </div>
                                        <el-progress :percentage="percentOf(count)" :stroke-width="16" />
                                    </div>
                                </div>
                                <div v-else class="empty-hint">
                                    <el-empty description="暂无 RFM 数据，请点击上方刷新" />
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="text-danger">流失风险分布</span>
                                </template>
                                <div v-if="Object.keys(dashboard.churn_distribution || {}).length">
                                    <el-row :gutter="8">
                                        <el-col v-for="(count, risk) in dashboard.churn_distribution" :key="risk" :span="6">
                                            <div class="churn-card" :class="'risk-' + risk">
                                                <div class="churn-count">{{ count }}</div>
                                                <div class="churn-label">{{ churnLabel(risk) }}</div>
                                            </div>
                                        </el-col>
                                    </el-row>
                                </div>
                                <el-empty v-else description="暂无预测数据" />
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="flex-between">
                                        <span>高危流失客户</span>
                                        <el-button text type="primary" size="small" @click="activeTab = 'churn'">查看全部</el-button>
                                    </span>
                                </template>
                                <el-table :data="dashboard.at_risk_customers || []" size="small" max-height="320">
                                    <el-table-column label="客户" min-width="120">
                                        <template #default="{ row }">{{ row.customer_name }}</template>
                                    </el-table-column>
                                    <el-table-column label="风险得分" width="90">
                                        <template #default="{ row }">
                                            <el-tag :type="row.churn_score >= 80 ? 'danger' : 'warning'" size="small">
                                                {{ row.churn_score }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column label="预测流失" width="110">
                                        <template #default="{ row }">{{ row.predicted_churn_date || '-' }}</template>
                                    </el-table-column>
                                    <el-table-column label="信号" min-width="150">
                                        <template #default="{ row }">
                                            <div class="signal-list">
                                                <el-tag v-for="sig in (row.signals || []).slice(0, 2)" :key="sig" size="small" type="warning" effect="plain" style="margin: 1px;">
                                                    {{ sig }}
                                                </el-tag>
                                            </div>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- ============ 客户分群 ============ -->
            <el-tab-pane label="客户分群" name="segments">
                <template #label>
                    <el-icon><Collection /></el-icon> 分群
                </template>

                <div class="tab-toolbar">
                    <el-button type="primary" size="small" @click="openCreateSegment">
                        <el-icon><Plus /></el-icon> 创建分群
                    </el-button>
                    <el-button size="small" @click="handleRefreshSegments" :loading="refreshingSegments">
                        <el-icon><Refresh /></el-icon> 刷新分群计算
                    </el-button>
                </div>

                <el-table :data="segments" v-loading="loadingSegments" stripe>
                    <el-table-column label="名称" min-width="140">
                        <template #default="{ row }">
                            <span :style="{ color: row.color || '#409eff', fontWeight: 600 }">
                                {{ row.name }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="标识" width="100">
                        <template #default="{ row }">
                            <code>{{ row.slug }}</code>
                        </template>
                    </el-table-column>
                    <el-table-column label="描述" min-width="180">
                        <template #default="{ row }">{{ row.description || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="动态" width="60">
                        <template #default="{ row }">
                            <el-tag :type="row.is_dynamic ? 'primary' : 'info'" size="small">
                                {{ row.is_dynamic ? '是' : '否' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="活跃" width="60">
                        <template #default="{ row }">
                            <el-switch
                                v-model="row.is_active"
                                size="small"
                                @change="(v) => handleToggleSegment(row, v)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="成员数" width="80">
                        <template #default="{ row }">{{ row.member_count || 0 }}</template>
                    </el-table-column>
                    <el-table-column label="规则" min-width="200">
                        <template #default="{ row }">
                            <div class="rules-tags" v-if="row.rules">
                                <el-tag v-if="row.rules.type" size="small">{{ row.rules.type }}</el-tag>
                                <el-tag v-if="row.rules.level" size="small">{{ row.rules.level }}</el-tag>
                                <el-tag v-if="row.rules.status" size="small">{{ row.rules.status }}</el-tag>
                                <el-tag v-if="row.rules.min_subscriptions != null" size="small">≥{{ row.rules.min_subscriptions }}订阅</el-tag>
                            </div>
                            <span v-else class="text-muted">手动分配</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" text @click="openEditSegment(row)">编辑</el-button>
                            <el-button size="small" text @click="viewSegmentCustomers(row)">成员</el-button>
                            <el-popconfirm title="删除此分群？" @confirm="handleDeleteSegment(row)">
                                <template #reference>
                                    <el-button size="small" text type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 分群 Dialog -->
                <el-dialog v-model="showSegmentDialog" :title="editingSegment ? '编辑分群' : '创建分群'" width="580px">
                    <el-form ref="segmentFormRef" :model="segmentForm" :rules="segmentRules" label-position="top">
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item label="名称" prop="name">
                                    <el-input v-model="segmentForm.name" placeholder="分群名称" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="标识" prop="slug">
                                    <el-input v-model="segmentForm.slug" placeholder="unique_slug" :disabled="!!editingSegment" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item label="描述" prop="description">
                            <el-input v-model="segmentForm.description" placeholder="分群描述" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item label="颜色">
                                    <el-color-picker v-model="segmentForm.color" show-alpha />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="动态分群">
                                    <el-switch v-model="segmentForm.is_dynamic" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="启用">
                                    <el-switch v-model="segmentForm.is_active" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-divider>匹配规则</el-divider>

                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item label="客户类型">
                                    <el-select v-model="segmentForm.rules.type" clearable placeholder="不限" style="width:100%">
                                        <el-option label="企业" value="enterprise" />
                                        <el-option label="个人" value="individual" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="客户等级">
                                    <el-select v-model="segmentForm.rules.level" clearable placeholder="不限" style="width:100%">
                                        <el-option label="Free" value="free" />
                                        <el-option label="Pro" value="pro" />
                                        <el-option label="Enterprise" value="enterprise" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item label="状态">
                                    <el-select v-model="segmentForm.rules.status" clearable placeholder="不限" style="width:100%">
                                        <el-option label="活跃" value="active" />
                                        <el-option label="非活跃" value="inactive" />
                                        <el-option label="已暂停" value="suspended" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item label="最少订阅数">
                                    <el-input-number v-model="segmentForm.rules.min_subscriptions" :min="0" :step="1" style="width:100%" placeholder="不限" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item label="最多订阅数">
                                    <el-input-number v-model="segmentForm.rules.max_subscriptions" :min="0" :step="1" style="width:100%" placeholder="不限" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                    <template #footer>
                        <el-button @click="showSegmentDialog = false">取消</el-button>
                        <el-button type="primary" @click="handleSaveSegment" :loading="savingSegment">保存</el-button>
                    </template>
                </el-dialog>

                <!-- 分群成员 Dialog -->
                <el-dialog v-model="showMembersDialog" :title="'成员列表 - ' + (viewingSegment?.name || '')" width="700px">
                    <el-table :data="segmentMembers" v-loading="loadingMembers" stripe size="small">
                        <el-table-column label="客户 ID" width="70" prop="id" />
                        <el-table-column label="名称" min-width="120" prop="name" />
                        <el-table-column label="邮箱" min-width="160" prop="email" />
                        <el-table-column label="类型" width="80">
                            <template #default="{ row }">
                                <el-tag size="small" :type="row.type === 'enterprise' ? 'warning' : 'info'">
                                    {{ row.type === 'enterprise' ? '企业' : '个人' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="等级" width="80" prop="level" />
                        <el-table-column label="状态" width="80" prop="status" />
                    </el-table>
                    <template #footer>
                        <el-button @click="showMembersDialog = false">关闭</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- ============ RFM 分析 ============ -->
            <el-tab-pane label="RFM 分析" name="rfm">
                <template #label>
                    <el-icon><TrendCharts /></el-icon> RFM
                </template>

                <div class="tab-toolbar">
                    <el-select v-model="rfmFilter" clearable placeholder="RFM 分群筛选" size="small" style="width: 180px;" @change="loadRfmScores">
                        <el-option v-for="seg in rfmSegments" :key="seg" :label="rfmLabel(seg)" :value="seg" />
                    </el-select>
                    <el-button size="small" @click="handleRecalculateRfm" :loading="calculatingRfm" style="margin-left: 8px;">
                        <el-icon><Refresh /></el-icon> 重算 RFM
                    </el-button>
                </div>

                <el-table :data="rfmScores" v-loading="loadingRfm" stripe>
                    <el-table-column label="客户" min-width="140">
                        <template #default="{ row }">{{ row.customer_name }}</template>
                    </el-table-column>
                    <el-table-column label="R (近期)" width="120">
                        <template #default="{ row }">
                            <div class="rfm-score">
                                <span class="score-badge" :class="'level-' + (row.recency_score || 0)">{{ row.recency_score || '-' }}</span>
                                <span class="score-detail">{{ row.recency_days != null ? row.recency_days + '天前' : '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="F (频次)" width="120">
                        <template #default="{ row }">
                            <div class="rfm-score">
                                <span class="score-badge" :class="'level-' + (row.frequency_score || 0)">{{ row.frequency_score || '-' }}</span>
                                <span class="score-detail">{{ row.frequency_count }} 次</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="M (金额)" width="140">
                        <template #default="{ row }">
                            <div class="rfm-score">
                                <span class="score-badge" :class="'level-' + (row.monetary_score || 0)">{{ row.monetary_score || '-' }}</span>
                                <span class="score-detail">¥{{ formatMoney(row.monetary_total) }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="总分" width="70">
                        <template #default="{ row }">
                            <strong>{{ row.rfm_total }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column label="RFM 分群" width="140">
                        <template #default="{ row }">
                            <el-tag :type="rfmTagType(row.rfm_segment)" size="small">
                                {{ rfmLabel(row.rfm_segment) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="计算时间" width="150">
                        <template #default="{ row }">
                            {{ row.calculated_at ? formatDate(row.calculated_at) : '-' }}
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="rfmPagination">
                    <el-pagination
                        v-model:current-page="rfmPagination.current_page"
                        :page-size="rfmPagination.per_page"
                        :total="rfmPagination.total"
                        layout="total, prev, pager, next"
                        @current-change="loadRfmScores"
                    />
                </div>
            </el-tab-pane>

            <!-- ============ 流失预测 ============ -->
            <el-tab-pane label="流失预测" name="churn">
                <template #label>
                    <el-icon><WarnTriangleFilled /></el-icon> 流失预测
                </template>

                <div class="tab-toolbar">
                    <el-select v-model="churnFilter" clearable placeholder="流失风险" size="small" style="width: 160px;" @change="loadChurnPredictions">
                        <el-option label="低风险" value="low" />
                        <el-option label="中风险" value="medium" />
                        <el-option label="高风险" value="high" />
                        <el-option label="危急" value="critical" />
                    </el-select>
                    <el-button size="small" @click="handleRecalculateChurn" :loading="calculatingChurn" style="margin-left: 8px;">
                        <el-icon><Refresh /></el-icon> 重算流失预测
                    </el-button>
                </div>

                <el-table :data="churnPredictions" v-loading="loadingChurn" stripe>
                    <el-table-column label="客户" min-width="140">
                        <template #default="{ row }">
                            <div>{{ row.customer_name }}</div>
                            <div class="text-muted" style="font-size:12px;">{{ row.customer_email }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column label="流失风险" width="110">
                        <template #default="{ row }">
                            <el-tag :type="churnTagType(row.churn_risk)" size="medium">
                                {{ churnLabel(row.churn_risk) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="得分" width="70">
                        <template #default="{ row }">
                            <el-progress
                                :percentage="row.churn_score"
                                :stroke-width="14"
                                :color="churnColor(row.churn_score)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="预测流失日" width="110">
                        <template #default="{ row }">{{ row.predicted_churn_date || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="流失信号" min-width="220">
                        <template #default="{ row }">
                            <div class="signal-list">
                                <el-tag v-for="sig in (row.signals || [])" :key="sig" size="small" type="warning" effect="plain" style="margin: 1px;">
                                    {{ sig }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="建议措施" min-width="220">
                        <template #default="{ row }">
                            <span style="font-size: 13px;">{{ row.recommended_action || '-' }}</span>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="churnPagination">
                    <el-pagination
                        v-model:current-page="churnPagination.current_page"
                        :page-size="churnPagination.per_page"
                        :total="churnPagination.total"
                        layout="total, prev, pager, next"
                        @current-change="loadChurnPredictions"
                    />
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Plus, Refresh, DataBoard, Collection, TrendCharts, WarnTriangleFilled } from '@element-plus/icons-vue';
import crmApi from '@/api/crm';

const activeTab = ref('dashboard');

// ── 看板 ──
const loadingDashboard = ref(false);
const refreshing = ref(false);
const dashboard = reactive({
    segments: [],
    rfm_distribution: {},
    churn_distribution: {},
    at_risk_customers: [],
    total_customers: 0,
});

// ── 分群 ──
const loadingSegments = ref(false);
const segments = ref([]);
const showSegmentDialog = ref(false);
const editingSegment = ref(null);
const savingSegment = ref(false);
const segmentFormRef = ref(null);
const refreshingSegments = ref(false);
const segmentForm = reactive({
    name: '', slug: '', description: '', color: '#409eff', icon: '',
    is_dynamic: true, is_active: true,
    rules: { type: null, level: null, status: null, min_subscriptions: null, max_subscriptions: null },
});
const segmentRules = {
    name: [{ required: true, message: '请输入分群名称', trigger: 'blur' }],
    slug: [{ required: true, message: '请输入标识', trigger: 'blur' }],
};
// 成员列表
const showMembersDialog = ref(false);
const viewingSegment = ref(null);
const segmentMembers = ref([]);
const loadingMembers = ref(false);

// ── RFM ──
const loadingRfm = ref(false);
const calculatingRfm = ref(false);
const rfmScores = ref([]);
const rfmPagination = ref(null);
const rfmFilter = ref('');
const rfmSegments = ['Champions', 'Loyal', 'Recent', 'Frequent', 'Big Spenders', 'Promising', 'Need Attention', 'About to Sleep', 'Lost', 'Others'];

// ── 流失预测 ──
const loadingChurn = ref(false);
const calculatingChurn = ref(false);
const churnPredictions = ref([]);
const churnPagination = ref(null);
const churnFilter = ref('');

// ============= 工具方法 =============

function formatDate(d) {
    return d ? new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' }) : '-';
}

function formatMoney(v) {
    return v ? Number(v).toLocaleString('zh-CN', { minimumFractionDigits: 2 }) : '0.00';
}

function percentOf(count) {
    const total = dashboard.total_customers || 1;
    return Math.round((count / total) * 100);
}

function rfmLabel(s) {
    const map = {
        Champions: '冠军客户', Loyal: '忠诚客户', Recent: '新客户', Frequent: '高频客户',
        'Big Spenders': '大额客户', Promising: '潜力客户', 'Need Attention': '需关注',
        'About to Sleep': '即将沉睡', Lost: '已流失', Others: '其他',
    };
    return map[s] || s;
}

function rfmTagType(s) {
    const map = { Champions: 'success', Loyal: 'primary', Recent: '', Frequent: 'warning', 'Big Spenders': 'danger' };
    return map[s] || 'info';
}

function churnLabel(s) {
    const map = { low: '低风险', medium: '中风险', high: '高风险', critical: '危急' };
    return map[s] || s;
}

function churnTagType(s) {
    const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
    return map[s] || 'info';
}

function churnColor(score) {
    if (score >= 80) return '#f56c6c';
    if (score >= 50) return '#e6a23c';
    if (score >= 25) return '#409eff';
    return '#67c23a';
}

// ============= 加载数据 =============

async function loadDashboard() {
    loadingDashboard.value = true;
    try {
        const { data: res } = await crmApi.dashboard();
        Object.assign(dashboard, res.data || {});
    } catch { /* ignore */ } finally {
        loadingDashboard.value = false;
    }
}

async function loadSegments() {
    loadingSegments.value = true;
    try {
        const { data: res } = await crmApi.segments({ per_page: 50 });
        const paginated = res.data;
        segments.value = paginated?.data || paginated || [];
    } catch { segments.value = []; } finally {
        loadingSegments.value = false;
    }
}

async function loadRfmScores(page = 1) {
    loadingRfm.value = true;
    try {
        const params = { page, per_page: 20 };
        if (rfmFilter.value) params.rfm_segment = rfmFilter.value;
        const { data: res } = await crmApi.rfmScores(params);
        const paginated = res.data;
        rfmScores.value = paginated?.data || paginated || [];
        if (paginated?.current_page) rfmPagination.value = paginated;
    } catch { rfmScores.value = []; } finally {
        loadingRfm.value = false;
    }
}

async function loadChurnPredictions(page = 1) {
    loadingChurn.value = true;
    try {
        const params = { page, per_page: 20 };
        if (churnFilter.value) params.churn_risk = churnFilter.value;
        const { data: res } = await crmApi.churnPredictions(params);
        const paginated = res.data;
        churnPredictions.value = paginated?.data || paginated || [];
        if (paginated?.current_page) churnPagination.value = paginated;
    } catch { churnPredictions.value = []; } finally {
        loadingChurn.value = false;
    }
}

// ============= 操作 =============

async function refreshAll() {
    refreshing.value = true;
    await Promise.all([
        loadDashboard(),
        loadSegments(),
        loadRfmScores(),
        loadChurnPredictions(),
    ]);
    refreshing.value = false;
    ElMessage.success('数据已刷新');
}

// ── 分群 ──

function openCreateSegment() {
    editingSegment.value = null;
    Object.assign(segmentForm, {
        name: '', slug: '', description: '', color: '#409eff', icon: '',
        is_dynamic: true, is_active: true,
        rules: { type: null, level: null, status: null, min_subscriptions: null, max_subscriptions: null },
    });
    showSegmentDialog.value = true;
}

function openEditSegment(seg) {
    editingSegment.value = seg;
    Object.assign(segmentForm, {
        name: seg.name, slug: seg.slug, description: seg.description || '',
        color: seg.color || '#409eff', icon: seg.icon || '',
        is_dynamic: seg.is_dynamic, is_active: seg.is_active,
        rules: {
            type: seg.rules?.type || null,
            level: seg.rules?.level || null,
            status: seg.rules?.status || null,
            min_subscriptions: seg.rules?.min_subscriptions ?? null,
            max_subscriptions: seg.rules?.max_subscriptions ?? null,
        },
    });
    showSegmentDialog.value = true;
}

async function handleSaveSegment() {
    const valid = await segmentFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    savingSegment.value = true;
    try {
        const data = {
            ...segmentForm,
            rules: segmentForm.is_dynamic ? segmentForm.rules : null,
        };
        if (editingSegment.value) {
            await crmApi.updateSegment(editingSegment.value.id, data);
            ElMessage.success('分群已更新');
        } else {
            await crmApi.createSegment(data);
            ElMessage.success('分群已创建');
        }
        showSegmentDialog.value = false;
        loadSegments();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '操作失败');
    } finally {
        savingSegment.value = false;
    }
}

async function handleToggleSegment(row, val) {
    try {
        await crmApi.updateSegment(row.id, { is_active: val });
        ElMessage.success(val ? '分群已启用' : '分群已停用');
    } catch {
        row.is_active = !val;
        ElMessage.error('操作失败');
    }
}

async function handleDeleteSegment(row) {
    try {
        await crmApi.deleteSegment(row.id);
        ElMessage.success('分群已删除');
        loadSegments();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '删除失败');
    }
}

async function handleRefreshSegments() {
    refreshingSegments.value = true;
    try {
        // 刷新所有分群 — 逐个调用
        for (const seg of segments.value) {
            if (seg.is_dynamic && seg.is_active) {
                await crmApi.refreshSegment(seg.id);
            }
        }
        ElMessage.success('所有动态分群已刷新');
        loadSegments();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '刷新失败');
    } finally {
        refreshingSegments.value = false;
    }
}

async function viewSegmentCustomers(seg) {
    viewingSegment.value = seg;
    showMembersDialog.value = true;
    loadingMembers.value = true;
    try {
        const { data: res } = await crmApi.segmentCustomers(seg.id, { per_page: 100 });
        const paginated = res.data;
        segmentMembers.value = paginated?.data || paginated || [];
    } catch {
        segmentMembers.value = [];
    } finally {
        loadingMembers.value = false;
    }
}

// ── RFM ──

async function handleRecalculateRfm() {
    calculatingRfm.value = true;
    try {
        const { data: res } = await crmApi.recalculateRfm();
        ElMessage.success(res.message || 'RFM 评分已重算');
        loadRfmScores();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '重算失败');
    } finally {
        calculatingRfm.value = false;
    }
}

// ── 流失预测 ──

async function handleRecalculateChurn() {
    calculatingChurn.value = true;
    try {
        const { data: res } = await crmApi.recalculateChurn();
        ElMessage.success(res.message || '流失预测已重算');
        loadChurnPredictions();
        loadDashboard();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '重算失败');
    } finally {
        calculatingChurn.value = false;
    }
}

onMounted(() => {
    loadDashboard();
    loadSegments();
    loadRfmScores();
    loadChurnPredictions();
});
</script>

<style scoped>
.crm-page { padding: 20px; }

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
.text-danger { color: var(--el-color-danger); }

.tab-toolbar {
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.segment-bar {
    margin-bottom: 12px;
}
.segment-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
    font-size: 13px;
}
.segment-name { font-weight: 600; }
.segment-count { color: var(--el-text-color-secondary); }

.empty-hint { padding: 20px; }

.churn-card {
    text-align: center;
    padding: 16px 8px;
    border-radius: 8px;
    color: #fff;
    font-weight: 600;
}
.churn-card.risk-low { background: var(--el-color-success); }
.churn-card.risk-medium { background: var(--el-color-warning); }
.churn-card.risk-high { background: var(--el-color-danger); }
.churn-card.risk-critical { background: #b71c1c; }
.churn-count { font-size: 28px; }
.churn-label { font-size: 13px; margin-top: 4px; }

.rfm-score {
    display: flex;
    align-items: center;
    gap: 8px;
}
.score-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
}
.score-badge.level-1 { background: #909399; }
.score-badge.level-2 { background: #909399; }
.score-badge.level-3 { background: #409eff; }
.score-badge.level-4 { background: #67c23a; }
.score-badge.level-5 { background: #f56c6c; }
.score-detail { font-size: 12px; color: var(--el-text-color-secondary); }

.signal-list {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
}

.rules-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

:deep(.el-card__body) { padding: 16px; }
</style>
