<template>
    <div class="crm-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('crm_page.title') }}</h2>
                <span class="header-subtitle">{{ t('crm_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" plain @click="refreshAll" :loading="refreshing">
                    <el-icon><Refresh /></el-icon> {{ t('crm_page.refresh_data') }}
                </el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ============ 看板 ============ -->
            <el-tab-pane :label="t('crm_page.tabs.dashboard_full')" name="dashboard">
                <template #label>
                    <el-icon><DataBoard /></el-icon> {{ t('crm_page.tabs.dashboard') }}
                </template>

                <div v-loading="loadingDashboard">
                    <!-- 客户分布 -->
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>{{ t('crm_page.dashboard.segment_distribution') }}</template>
                                <div v-if="dashboard.segments?.length">
                                    <div v-for="s in dashboard.segments" :key="s.id" class="segment-bar">
                                        <div class="segment-info">
                                            <span class="segment-name" :style="{ color: s.color || '#0f172a' }">
                                                {{ s.name }}
                                            </span>
                                            <span class="segment-count">{{ t('crm_page.member_count_fmt', { n: s.member_count }) }}</span>
                                        </div>
                                        <el-progress
                                            :percentage="percentOf(s.member_count)"
                                            :color="s.color || '#0f172a'"
                                            :stroke-width="16"
                                        />
                                    </div>
                                </div>
                                <el-empty v-else :description="t('crm_page.dashboard.no_segments')" />
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>{{ t('crm_page.dashboard.rfm_distribution') }}</template>
                                <div v-if="Object.keys(dashboard.rfm_distribution || {}).length">
                                    <div v-for="(count, seg) in dashboard.rfm_distribution" :key="seg" class="segment-bar">
                                        <div class="segment-info">
                                            <span class="segment-name">{{ rfmLabel(seg) }}</span>
                                            <span class="segment-count">{{ t('crm_page.member_count_fmt', { n: count }) }}</span>
                                        </div>
                                        <el-progress :percentage="percentOf(count)" :stroke-width="16" />
                                    </div>
                                </div>
                                <div v-else class="empty-hint">
                                    <el-empty :description="t('crm_page.dashboard.no_rfm_data')" />
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="text-danger">{{ t('crm_page.dashboard.churn_risk_distribution') }}</span>
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
                                <el-empty v-else :description="t('crm_page.dashboard.no_prediction_data')" />
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header>
                                    <span class="flex-between">
                                        <span>{{ t('crm_page.dashboard.at_risk_customers') }}</span>
                                        <el-button text type="primary" size="small" @click="activeTab = 'churn'">{{ t('crm_page.dashboard.view_all') }}</el-button>
                                    </span>
                                </template>
                                <el-table :data="dashboard.at_risk_customers || []" size="small" max-height="320">
                                    <el-table-column :label="t('crm_page.cols.customer')" min-width="120">
                                        <template #default="{ row }">{{ row.customer_name }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t('crm_page.cols.risk_score')" width="90">
                                        <template #default="{ row }">
                                            <el-tag :type="row.churn_score >= 80 ? 'danger' : 'warning'" size="small">
                                                {{ row.churn_score }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('crm_page.cols.predicted_churn')" width="110">
                                        <template #default="{ row }">{{ row.predicted_churn_date || '-' }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t('crm_page.cols.signals')" min-width="150">
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
            <el-tab-pane :label="t('crm_page.tabs.segments_full')" name="segments">
                <template #label>
                    <el-icon><Collection /></el-icon> {{ t('crm_page.tabs.segments') }}
                </template>

                <div class="tab-toolbar">
                    <el-button type="primary" size="small" @click="openCreateSegment">
                        <el-icon><Plus /></el-icon> {{ t('crm_page.create_segment') }}
                    </el-button>
                    <el-button size="small" @click="handleRefreshSegments" :loading="refreshingSegments">
                        <el-icon><Refresh /></el-icon> {{ t('crm_page.refresh_segments') }}
                    </el-button>
                </div>

                <el-table :data="segments" v-loading="loadingSegments" stripe>
                    <el-table-column :label="t('crm_page.cols.name')" min-width="140">
                        <template #default="{ row }">
                            <span :style="{ color: row.color || '#0f172a', fontWeight: 600 }">
                                {{ row.name }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.slug')" width="100">
                        <template #default="{ row }">
                            <code>{{ row.slug }}</code>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.description')" min-width="180">
                        <template #default="{ row }">{{ row.description || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.dynamic')" width="60">
                        <template #default="{ row }">
                            <el-tag :type="row.is_dynamic ? 'primary' : 'info'" size="small">
                                {{ row.is_dynamic ? t('crm_page.yes') : t('crm_page.no') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.active')" width="60">
                        <template #default="{ row }">
                            <el-switch
                                v-model="row.is_active"
                                size="small"
                                @change="(v) => handleToggleSegment(row, v)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.member_count')" width="80">
                        <template #default="{ row }">{{ row.member_count || 0 }}</template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.rules')" min-width="200">
                        <template #default="{ row }">
                            <div class="rules-tags" v-if="row.rules">
                                <el-tag v-if="row.rules.type" size="small">{{ row.rules.type }}</el-tag>
                                <el-tag v-if="row.rules.level" size="small">{{ row.rules.level }}</el-tag>
                                <el-tag v-if="row.rules.status" size="small">{{ row.rules.status }}</el-tag>
                                <el-tag v-if="row.rules.min_subscriptions != null" size="small">{{ t('crm_page.min_subscriptions_rule', { n: row.rules.min_subscriptions }) }}</el-tag>
                            </div>
                            <span v-else class="text-muted">{{ t('crm_page.manual_assignment') }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <el-button size="small" text @click="openEditSegment(row)">{{ t('actions.edit') }}</el-button>
                            <el-button size="small" text @click="viewSegmentCustomers(row)">{{ t('crm_page.members') }}</el-button>
                            <el-popconfirm :title="t('crm_page.delete_segment_confirm')" @confirm="handleDeleteSegment(row)">
                                <template #reference>
                                    <el-button size="small" text type="danger">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 分群 Dialog -->
                <el-dialog v-model="showSegmentDialog" :title="editingSegment ? t('crm_page.edit_segment') : t('crm_page.create_segment')" width="580px">
                    <el-form ref="segmentFormRef" :model="segmentForm" :rules="segmentRules" label-position="top">
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item :label="t('crm_page.cols.name')" prop="name">
                                    <el-input v-model="segmentForm.name" :placeholder="t('crm_page.segment_name_ph')" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item :label="t('crm_page.cols.slug')" prop="slug">
                                    <el-input v-model="segmentForm.slug" placeholder="unique_slug" :disabled="!!editingSegment" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item :label="t('crm_page.cols.description')" prop="description">
                            <el-input v-model="segmentForm.description" :placeholder="t('crm_page.segment_desc_ph')" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item :label="t('crm_page.color')">
                                    <el-color-picker v-model="segmentForm.color" show-alpha />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('crm_page.dynamic_segment')">
                                    <el-switch v-model="segmentForm.is_dynamic" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('crm_page.enabled')">
                                    <el-switch v-model="segmentForm.is_active" />
                                </el-form-item>
                            </el-col>
                        </el-row>

                        <el-divider>{{ t('crm_page.match_rules') }}</el-divider>

                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item :label="t('crm_page.customer_type')">
                                    <el-select v-model="segmentForm.rules.type" clearable :placeholder="t('crm_page.no_limit')" style="width:100%">
                                        <el-option v-for="opt in customerTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('crm_page.customer_level')">
                                    <el-select v-model="segmentForm.rules.level" clearable :placeholder="t('crm_page.no_limit')" style="width:100%">
                                        <el-option v-for="opt in customerLevelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('crm_page.cols.status')">
                                    <el-select v-model="segmentForm.rules.status" clearable :placeholder="t('crm_page.no_limit')" style="width:100%">
                                        <el-option v-for="opt in customerStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item :label="t('crm_page.min_subscriptions')">
                                    <el-input-number v-model="segmentForm.rules.min_subscriptions" :min="0" :step="1" style="width:100%" :placeholder="t('crm_page.no_limit')" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12">
                                <el-form-item :label="t('crm_page.max_subscriptions')">
                                    <el-input-number v-model="segmentForm.rules.max_subscriptions" :min="0" :step="1" style="width:100%" :placeholder="t('crm_page.no_limit')" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                    <template #footer>
                        <el-button @click="showSegmentDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="handleSaveSegment" :loading="savingSegment">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>

                <!-- 分群成员 Dialog -->
                <el-dialog v-model="showMembersDialog" :title="t('crm_page.members_title', { name: viewingSegment?.name || '' })" width="700px">
                    <el-table :data="segmentMembers" v-loading="loadingMembers" stripe size="small">
                        <el-table-column :label="t('crm_page.cols.customer_id')" width="70" prop="id" />
                        <el-table-column :label="t('crm_page.cols.name')" min-width="120" prop="name" />
                        <el-table-column :label="t('crm_page.cols.email')" min-width="160" prop="email" />
                        <el-table-column :label="t('crm_page.cols.type')" width="80">
                            <template #default="{ row }">
                                <el-tag size="small" :type="row.type === 'enterprise' ? 'warning' : 'info'">
                                    {{ row.type === 'enterprise' ? t('crm_page.type_enterprise') : t('crm_page.type_individual') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('crm_page.cols.level')" width="80" prop="level" />
                        <el-table-column :label="t('crm_page.cols.status')" width="80" prop="status" />
                    </el-table>
                    <template #footer>
                        <el-button @click="showMembersDialog = false">{{ t('actions.close') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- ============ RFM 分析 ============ -->
            <el-tab-pane :label="t('crm_page.tabs.rfm_full')" name="rfm">
                <template #label>
                    <el-icon><TrendCharts /></el-icon> {{ t('crm_page.tabs.rfm') }}
                </template>

                <div class="tab-toolbar">
                    <el-select v-model="rfmFilter" clearable :placeholder="t('crm_page.rfm_filter_ph')" size="small" style="width: 180px;" @change="loadRfmScores">
                        <el-option v-for="seg in rfmSegments" :key="seg" :label="rfmLabel(seg)" :value="seg" />
                    </el-select>
                    <el-button size="small" @click="handleRecalculateRfm" :loading="calculatingRfm" style="margin-left: 8px;">
                        <el-icon><Refresh /></el-icon> {{ t('crm_page.recalculate_rfm') }}
                    </el-button>
                </div>

                <el-table :data="rfmScores" v-loading="loadingRfm" stripe>
                    <el-table-column :label="t('crm_page.cols.customer')" min-width="140">
                        <template #default="{ row }">{{ row.customer_name }}</template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.r_recency')" width="120">
                        <template #default="{ row }">
                            <div class="rfm-score">
                                <span class="score-badge" :class="'level-' + (row.recency_score || 0)">{{ row.recency_score || '-' }}</span>
                                <span class="score-detail">{{ row.recency_days != null ? t('crm_page.days_ago', { n: row.recency_days }) : '-' }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.f_frequency')" width="120">
                        <template #default="{ row }">
                            <div class="rfm-score">
                                <span class="score-badge" :class="'level-' + (row.frequency_score || 0)">{{ row.frequency_score || '-' }}</span>
                                <span class="score-detail">{{ t('crm_page.times_count', { n: row.frequency_count }) }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.m_monetary')" width="140">
                        <template #default="{ row }">
                            <div class="rfm-score">
                                <span class="score-badge" :class="'level-' + (row.monetary_score || 0)">{{ row.monetary_score || '-' }}</span>
                                <span class="score-detail">¥{{ formatMoney(row.monetary_total) }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.total_score')" width="70">
                        <template #default="{ row }">
                            <strong>{{ row.rfm_total }}</strong>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.rfm_segment')" width="140">
                        <template #default="{ row }">
                            <el-tag :type="rfmTagType(row.rfm_segment)" size="small">
                                {{ rfmLabel(row.rfm_segment) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.calculated_at')" width="150">
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
            <el-tab-pane :label="t('crm_page.tabs.churn')" name="churn">
                <template #label>
                    <el-icon><WarnTriangleFilled /></el-icon> {{ t('crm_page.tabs.churn') }}
                </template>

                <div class="tab-toolbar">
                    <el-select v-model="churnFilter" clearable :placeholder="t('crm_page.churn_filter_ph')" size="small" style="width: 160px;" @change="loadChurnPredictions">
                        <el-option v-for="opt in churnRiskOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-button size="small" @click="handleRecalculateChurn" :loading="calculatingChurn" style="margin-left: 8px;">
                        <el-icon><Refresh /></el-icon> {{ t('crm_page.recalculate_churn') }}
                    </el-button>
                </div>

                <el-table :data="churnPredictions" v-loading="loadingChurn" stripe>
                    <el-table-column :label="t('crm_page.cols.customer')" min-width="140">
                        <template #default="{ row }">
                            <div>{{ row.customer_name }}</div>
                            <div class="text-muted" style="font-size:12px;">{{ row.customer_email }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.churn_risk')" width="110">
                        <template #default="{ row }">
                            <el-tag :type="churnTagType(row.churn_risk)" size="medium">
                                {{ churnLabel(row.churn_risk) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.score')" width="70">
                        <template #default="{ row }">
                            <el-progress
                                :percentage="row.churn_score"
                                :stroke-width="14"
                                :color="churnColor(row.churn_score)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.predicted_churn_date')" width="110">
                        <template #default="{ row }">{{ row.predicted_churn_date || '-' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.churn_signals')" min-width="220">
                        <template #default="{ row }">
                            <div class="signal-list">
                                <el-tag v-for="sig in (row.signals || [])" :key="sig" size="small" type="warning" effect="plain" style="margin: 1px;">
                                    {{ sig }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('crm_page.cols.recommended_action')" min-width="220">
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
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Plus, Refresh, DataBoard, Collection, TrendCharts, WarnTriangleFilled } from '@element-plus/icons-vue';
import crmApi from '@/api/crm';

const { t, locale } = useI18n();

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
    name: '', slug: '', description: '', color: '#0f172a', icon: '',
    is_dynamic: true, is_active: true,
    rules: { type: null, level: null, status: null, min_subscriptions: null, max_subscriptions: null },
});
const segmentRules = computed(() => ({
    name: [{ required: true, message: t('crm_page.messages.name_required'), trigger: 'blur' }],
    slug: [{ required: true, message: t('crm_page.messages.slug_required'), trigger: 'blur' }],
}));
// 成员列表
const showMembersDialog = ref(false);
const viewingSegment = ref(null);
const segmentMembers = ref([]);
const loadingMembers = ref(false);

const customerTypeOptions = computed(() => [
    { label: t('crm_page.type_enterprise'), value: 'enterprise' },
    { label: t('crm_page.type_individual'), value: 'individual' },
]);

const customerLevelOptions = computed(() => [
    { label: t('crm_page.level_free'), value: 'free' },
    { label: t('crm_page.level_pro'), value: 'pro' },
    { label: t('crm_page.level_enterprise'), value: 'enterprise' },
]);

const customerStatusOptions = computed(() => [
    { label: t('crm_page.st_active'), value: 'active' },
    { label: t('crm_page.st_inactive'), value: 'inactive' },
    { label: t('crm_page.st_suspended'), value: 'suspended' },
]);

const churnRiskOptions = computed(() => [
    { label: t('crm_page.churn_risk.low'), value: 'low' },
    { label: t('crm_page.churn_risk.medium'), value: 'medium' },
    { label: t('crm_page.churn_risk.high'), value: 'high' },
    { label: t('crm_page.churn_risk.critical'), value: 'critical' },
]);

// ── RFM ──
const loadingRfm = ref(false);
const calculatingRfm = ref(false);
const rfmScores = ref([]);
const rfmPagination = ref(null);
const rfmFilter = ref('');
const rfmSegments = ['Champions', 'Loyal', 'Recent', 'Frequent', 'Big Spenders', 'Promising', 'Need Attention', 'About to Sleep', 'Lost', 'Others'];

const rfmLabels = computed(() => ({
    Champions: t('crm_page.rfm.Champions'),
    Loyal: t('crm_page.rfm.Loyal'),
    Recent: t('crm_page.rfm.Recent'),
    Frequent: t('crm_page.rfm.Frequent'),
    'Big Spenders': t('crm_page.rfm.Big Spenders'),
    Promising: t('crm_page.rfm.Promising'),
    'Need Attention': t('crm_page.rfm.Need Attention'),
    'About to Sleep': t('crm_page.rfm.About to Sleep'),
    Lost: t('crm_page.rfm.Lost'),
    Others: t('crm_page.rfm.Others'),
}));

const churnLabels = computed(() => ({
    low: t('crm_page.churn_risk.low'),
    medium: t('crm_page.churn_risk.medium'),
    high: t('crm_page.churn_risk.high'),
    critical: t('crm_page.churn_risk.critical'),
}));

// ── 流失预测 ──
const loadingChurn = ref(false);
const calculatingChurn = ref(false);
const churnPredictions = ref([]);
const churnPagination = ref(null);
const churnFilter = ref('');

// ============= 工具方法 =============

function formatDate(d) {
    if (!d) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(d).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function formatMoney(v) {
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return v ? Number(v).toLocaleString(loc, { minimumFractionDigits: 2 }) : '0.00';
}

function percentOf(count) {
    const total = dashboard.total_customers || 1;
    return Math.round((count / total) * 100);
}

function rfmLabel(s) {
    return rfmLabels.value[s] || s;
}

function rfmTagType(s) {
    const map = { Champions: 'success', Loyal: 'primary', Recent: '', Frequent: 'warning', 'Big Spenders': 'danger' };
    return map[s] || 'info';
}

function churnLabel(s) {
    return churnLabels.value[s] || s;
}

function churnTagType(s) {
    const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
    return map[s] || 'info';
}

function churnColor(score) {
    if (score >= 80) return '#f56c6c';
    if (score >= 50) return '#e6a23c';
    if (score >= 25) return '#0f172a';
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
    ElMessage.success(t('crm_page.messages.data_refreshed'));
}

// ── 分群 ──

function openCreateSegment() {
    editingSegment.value = null;
    Object.assign(segmentForm, {
        name: '', slug: '', description: '', color: '#0f172a', icon: '',
        is_dynamic: true, is_active: true,
        rules: { type: null, level: null, status: null, min_subscriptions: null, max_subscriptions: null },
    });
    showSegmentDialog.value = true;
}

function openEditSegment(seg) {
    editingSegment.value = seg;
    Object.assign(segmentForm, {
        name: seg.name, slug: seg.slug, description: seg.description || '',
        color: seg.color || '#0f172a', icon: seg.icon || '',
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
            ElMessage.success(t('crm_page.messages.segment_updated'));
        } else {
            await crmApi.createSegment(data);
            ElMessage.success(t('crm_page.messages.segment_created'));
        }
        showSegmentDialog.value = false;
        loadSegments();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('messages.failed'));
    } finally {
        savingSegment.value = false;
    }
}

async function handleToggleSegment(row, val) {
    try {
        await crmApi.updateSegment(row.id, { is_active: val });
        ElMessage.success(val ? t('crm_page.messages.segment_enabled') : t('crm_page.messages.segment_disabled'));
    } catch {
        row.is_active = !val;
        ElMessage.error(t('messages.failed'));
    }
}

async function handleDeleteSegment(row) {
    try {
        await crmApi.deleteSegment(row.id);
        ElMessage.success(t('crm_page.messages.segment_deleted'));
        loadSegments();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('crm_page.messages.delete_failed'));
    }
}

async function handleRefreshSegments() {
    refreshingSegments.value = true;
    try {
        for (const seg of segments.value) {
            if (seg.is_dynamic && seg.is_active) {
                await crmApi.refreshSegment(seg.id);
            }
        }
        ElMessage.success(t('crm_page.messages.segments_refreshed'));
        loadSegments();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('crm_page.messages.refresh_failed'));
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
        ElMessage.success(res.message || t('crm_page.messages.rfm_recalculated'));
        loadRfmScores();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('crm_page.messages.recalculate_failed'));
    } finally {
        calculatingRfm.value = false;
    }
}

// ── 流失预测 ──

async function handleRecalculateChurn() {
    calculatingChurn.value = true;
    try {
        const { data: res } = await crmApi.recalculateChurn();
        ElMessage.success(res.message || t('crm_page.messages.churn_recalculated'));
        loadChurnPredictions();
        loadDashboard();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('crm_page.messages.recalculate_failed'));
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
.score-badge.level-3 { background: #0f172a; }
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
