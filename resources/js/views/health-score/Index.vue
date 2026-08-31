<template>
    <div class="health-score-manager">
        <el-page-header :content="pageTitle" @back="router.push('/')" />

        <el-tabs v-model="healthMainTab" type="border-card" class="mt-4">
            <!-- Tab1: 客户健康度 -->
            <el-tab-pane :label="t(`${P}.main_tab_customer_health`)" name="customer-health">
                <el-tabs v-model="activeTab">
                    <el-tab-pane :label="t(`${P}.tabs.dashboard`)" name="dashboard">
                        <el-row :gutter="16" class="mb-4">
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-card">
                                        <div class="stat-value text-3xl font-bold text-primary">{{ stats.total_customers || '-' }}</div>
                                        <div class="stat-label text-gray-500 mt-1">{{ t(`${P}.stats.total`) }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-card">
                                        <div class="stat-value text-3xl font-bold text-green-500">{{ stats.healthy || '-' }}</div>
                                        <div class="stat-label text-gray-500 mt-1">{{ t(`${P}.stats.healthy`, { avg: stats.avg_score || '-' }) }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-card">
                                        <div class="stat-value text-3xl font-bold text-orange-500">{{ stats.warning || '-' }}</div>
                                        <div class="stat-label text-gray-500 mt-1">{{ t(`${P}.grades.warning`) }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="6">
                                <el-card shadow="hover">
                                    <div class="stat-card">
                                        <div class="stat-value text-3xl font-bold text-red-500">{{ stats.critical || '-' }}</div>
                                        <div class="stat-label text-gray-500 mt-1">{{ t(`${P}.stats.critical`, { n: stats.high_risk_churn || 0 }) }}</div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <el-card :header="t(`${P}.dimension_avg`)" class="mb-4">
                            <div class="flex gap-6 flex-wrap">
                                <div v-for="(val, key) in (stats.dimension_averages || {})" :key="key" class="dimension-box">
                                    <div class="dimension-label">{{ dimLabel(key) }}</div>
                                    <el-progress :percentage="val" :color="scoreColor(val)" :stroke-width="16" />
                                </div>
                            </div>
                        </el-card>

                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-button type="primary" @click="handleCalculateAll" :loading="calculatingAll" class="w-full">
                                    {{ calculatingAll ? t(`${P}.calculating`) : t(`${P}.calculate_all`) }}
                                </el-button>
                            </el-col>
                            <el-col :span="12">
                                <el-button @click="refreshDashboard" :loading="loading" class="w-full">{{ t(`${P}.refresh`) }}</el-button>
                            </el-col>
                        </el-row>
                    </el-tab-pane>

                    <el-tab-pane :label="t(`${P}.tabs.ranking`)" name="ranking">
                        <div class="flex gap-4 mb-4">
                            <el-select v-model="gradeFilter" :placeholder="t(`${P}.filter_grade`)" clearable style="width: 140px">
                                <el-option :label="t(`${P}.grades.healthy`)" value="healthy" />
                                <el-option :label="t(`${P}.grades.warning`)" value="warning" />
                                <el-option :label="t(`${P}.grades.critical`)" value="critical" />
                            </el-select>
                            <el-button type="primary" @click="fetchList">{{ t('actions.search') }}</el-button>
                        </div>

                        <el-table :data="scoreList" v-loading="loading" stripe border>
                            <el-table-column :label="t(`${P}.cols.customer`)" min-width="200">
                                <template #default="{ row }">
                                    <div>
                                        <span class="font-medium">{{ row.customer?.user?.name || `ID:${row.customer_id}` }}</span>
                                        <div class="text-xs text-gray-400">{{ row.customer?.user?.email }}</div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.score`)" width="120" sortable prop="score">
                                <template #default="{ row }">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-lg" :style="{ color: scoreColor(row.score) }">{{ Number(row.score).toFixed(1) }}</span>
                                        <el-tag :type="gradeTagType(row.grade)" size="small">{{ gradeLabel(row.grade) }}</el-tag>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.activation`)" width="100" prop="activation_score">
                                <template #default="{ row }">{{ Number(row.activation_score).toFixed(1) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.renewal`)" width="100" prop="renewal_score">
                                <template #default="{ row }">{{ Number(row.renewal_score).toFixed(1) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.ticket`)" width="100" prop="ticket_score">
                                <template #default="{ row }">{{ Number(row.ticket_score).toFixed(1) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.device`)" width="100" prop="device_score">
                                <template #default="{ row }">{{ Number(row.device_score).toFixed(1) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.payment`)" width="100" prop="payment_score">
                                <template #default="{ row }">{{ Number(row.payment_score).toFixed(1) }}</template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.warnings`)" min-width="200">
                                <template #default="{ row }">
                                    <div class="flex flex-wrap gap-1">
                                        <el-tag v-for="w in (row.warnings || [])" :key="w.type" :type="w.severity === 'critical' ? 'danger' : w.severity === 'high' ? 'warning' : 'info'" size="small" effect="dark">
                                            {{ w.message }}
                                        </el-tag>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.calculated`)" width="170" prop="calculated_at">
                                <template #default="{ row }">{{ row.calculated_at ? formatDate(row.calculated_at) : '-' }}</template>
                            </el-table-column>
                        </el-table>

                        <div class="flex justify-center mt-4">
                            <el-pagination
                                v-model:current-page="page"
                                :page-size="perPage"
                                :total="total"
                                layout="prev, pager, next"
                                @current-change="fetchList"
                            />
                        </div>
                    </el-tab-pane>

                    <el-tab-pane :label="t(`${P}.tabs.churn`)" name="churn">
                        <div class="flex gap-4 mb-4">
                            <el-select v-model="riskFilter" :placeholder="t(`${P}.filter_risk`)" clearable style="width: 160px">
                                <el-option :label="t(`${P}.risk.low`)" value="low" />
                                <el-option :label="t(`${P}.risk.medium`)" value="medium" />
                                <el-option :label="t(`${P}.risk.high`)" value="high" />
                                <el-option :label="t(`${P}.risk.critical`)" value="critical" />
                            </el-select>
                            <el-button type="primary" @click="fetchChurnList">{{ t('actions.search') }}</el-button>
                        </div>

                        <el-table :data="churnList" v-loading="loading" stripe border>
                            <el-table-column :label="t(`${P}.cols.customer`)" min-width="200">
                                <template #default="{ row }">
                                    <div>
                                        <span class="font-medium">{{ row.customer?.user?.name || `ID:${row.customer_id}` }}</span>
                                        <div class="text-xs text-gray-400">{{ row.customer?.user?.email }}</div>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.churn_prob`)" width="130">
                                <template #default="{ row }">
                                    <span class="font-mono" :style="{ color: riskColor(row.risk_level) }">
                                        {{ (row.churn_probability * 100).toFixed(1) }}%
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.risk`)" width="120">
                                <template #default="{ row }">
                                    <el-tag :type="riskTagType(row.risk_level)" effect="dark" size="small">
                                        {{ riskLabel(row.risk_level) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.signals`)" min-width="200">
                                <template #default="{ row }">
                                    <div class="flex flex-wrap gap-1">
                                        <el-tag v-for="s in (row.top_signals || [])" :key="s" size="small">
                                            {{ signalLabel(s) }}
                                        </el-tag>
                                    </div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.recommendations`)" min-width="250">
                                <template #default="{ row }">
                                    <ul class="list-disc pl-4 text-sm">
                                        <li v-for="r in (row.recommendations || [])" :key="r.action">{{ r.detail }}</li>
                                    </ul>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${P}.cols.predicted`)" width="170" prop="predicted_at">
                                <template #default="{ row }">{{ row.predicted_at ? formatDate(row.predicted_at) : '-' }}</template>
                            </el-table-column>
                        </el-table>

                        <div class="flex justify-center mt-4">
                            <el-pagination
                                v-model:current-page="churnPage"
                                :page-size="churnPerPage"
                                :total="churnTotal"
                                layout="prev, pager, next"
                                @current-change="fetchChurnList"
                            />
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <!-- Tab2: License 健康 -->
            <el-tab-pane :label="t('license_health_page.main_tab_title')" name="license-health">
                <template v-if="lh_tabVisited">
                    <el-row :gutter="20" class="mb-4">
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="text-3xl font-bold text-gray-800">{{ lh_dashboard.total_licenses ?? '-' }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ t('license_health_page.stats.total') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="text-3xl font-bold" :style="{ color: lh_avgScoreColor }">{{ lh_dashboard.average_score ?? '-' }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ t('license_health_page.stats.avg') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="text-3xl font-bold text-success">{{ lh_dashboard.healthy_count ?? '-' }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ t('license_health_page.stats.healthy') }}</div>
                            </el-card>
                        </el-col>
                        <el-col :span="6">
                            <el-card shadow="hover">
                                <div class="text-3xl font-bold text-danger">{{ lh_dashboard.critical_count ?? '-' }}</div>
                                <div class="text-sm text-gray-500 mt-1">{{ t('license_health_page.stats.critical') }}</div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-card class="mb-4">
                        <el-row :gutter="16" align="middle">
                            <el-col :span="12">
                                <el-button type="primary" @click="lh_handleCalculateAll" :loading="lh_calculating">
                                    <el-icon class="mr-1"><Refresh /></el-icon>{{ t('license_health_page.recalc_all') }}
                                </el-button>
                                <span class="text-sm text-gray-400 ml-2">{{ t('license_health_page.last_calc', { time: lh_dashboard.last_calculated_at || t('license_health_page.never') }) }}</span>
                            </el-col>
                            <el-col :span="12" class="text-right">
                                <el-select v-model="lh_filters.grade" :placeholder="t('license_health_page.filter_grade')" clearable style="width:140px" @change="lh_fetchList">
                                    <el-option :label="t('license_health_page.grades.healthy')" value="healthy" />
                                    <el-option :label="t('license_health_page.grades.warning')" value="warning" />
                                    <el-option :label="t('license_health_page.grades.critical')" value="critical" />
                                </el-select>
                                <el-input v-model="lh_search" :placeholder="t('license_health_page.search_ph')" clearable style="width:200px;margin-left:8px" @clear="lh_fetchList" @keyup.enter="lh_fetchList" />
                            </el-col>
                        </el-row>
                    </el-card>

                    <el-card v-if="lh_dashboard.top_suggestions?.length" class="mb-4">
                        <template #header><span class="font-semibold">{{ t('license_health_page.suggestions_title') }}</span></template>
                        <el-timeline>
                            <el-timeline-item
                                v-for="(s, i) in lh_dashboard.top_suggestions"
                                :key="i"
                                :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'primary'"
                                :timestamp="s.customer_name || ''"
                            >
                                {{ s.message }}
                            </el-timeline-item>
                        </el-timeline>
                    </el-card>

                    <el-card>
                        <template #header><span>{{ t('license_health_page.list_title') }}</span></template>
                        <el-table :data="lh_list" v-loading="lh_loading" stripe style="width:100%">
                            <el-table-column prop="id" label="#" width="60" />
                            <el-table-column :label="t('license_health_page.cols.customer')" min-width="160">
                                <template #default="{ row }">
                                    <div class="font-medium">{{ row.customer?.name || t('license_health_page.customer_n', { id: row.customer_id }) }}</div>
                                    <div class="text-xs text-gray-400">{{ row.customer?.email || '' }}</div>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('license_health_page.cols.score')" width="120" align="center">
                                <template #default="{ row }">
                                    <el-progress
                                        type="circle"
                                        :percentage="Math.round(row.score)"
                                        :width="50"
                                        :stroke-width="6"
                                        :color="lh_scoreColor(row.score)"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('license_health_page.cols.grade')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="lh_gradeTag(row.grade)" size="small">{{ lh_gradeLabel(row.grade) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('license_health_page.cols.churn')" width="100">
                                <template #default="{ row }">
                                    <el-tag :type="lh_churnTag(row.churn_probability)" size="small">
                                        {{ lh_churnLabel(row.churn_probability) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="license_count" :label="t('license_health_page.cols.licenses')" width="80" align="center" />
                            <el-table-column prop="active_devices" :label="t('license_health_page.cols.devices')" width="80" align="center" />
                            <el-table-column prop="score_updated_at" :label="t('license_health_page.cols.updated')" width="160" />
                            <el-table-column :label="t('license_health_page.cols.actions')" width="140" fixed="right">
                                <template #default="{ row }">
                                    <el-button size="small" @click="lh_showDetail(row)">{{ t('actions.view_details') }}</el-button>
                                    <el-button size="small" @click="lh_handleRecalculate(row)" :loading="lh_recalculatingId === row.id">{{ t('license_health_page.recalc') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div class="mt-4 flex justify-center" v-if="lh_total > lh_perPage">
                            <el-pagination
                                v-model:current-page="lh_currentPage"
                                :page-size="lh_perPage"
                                :total="lh_total"
                                layout="prev, pager, next"
                                @current-change="lh_onPageChange"
                            />
                        </div>
                    </el-card>

                    <el-dialog v-model="lh_detailVisible" :title="t('license_health_page.detail_title')" width="650px">
                        <template v-if="lh_detail">
                            <el-row :gutter="16" class="mb-4">
                                <el-col :span="8" v-for="dim in lh_dimensions" :key="dim.key" class="mb-3">
                                    <el-card shadow="never" :body-style="{ padding: '12px' }">
                                        <div class="text-sm text-gray-500">{{ dim.label }}</div>
                                        <div class="text-xl font-bold mt-1" :style="{ color: lh_scoreColor(lh_detail[dim.key] ?? 0) }">
                                            {{ (lh_detail[dim.key] ?? 0).toFixed(1) }}
                                        </div>
                                        <div class="text-xs text-gray-400">{{ t('license_health_page.weight_n', { n: (dim.weight * 100).toFixed(0) }) }}</div>
                                    </el-card>
                                </el-col>
                            </el-row>
                            <el-divider />
                            <h4 class="font-semibold mb-3">{{ t('license_health_page.improve_title') }}</h4>
                            <el-timeline v-if="lh_detail.suggestions?.length">
                                <el-timeline-item
                                    v-for="(s, i) in lh_detail.suggestions"
                                    :key="i"
                                    :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'primary'"
                                >
                                    {{ s.message }}
                                </el-timeline-item>
                            </el-timeline>
                            <el-empty v-else :description="t('license_health_page.no_suggestions')" :image-size="60" />
                        </template>
                    </el-dialog>
                </template>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import healthScoreApi from '@/api/healthScore';

const { t, locale } = useI18n();
const P = 'health_score_page';
const router = useRouter();
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'));

// ── 主 Tab ──
const healthMainTab = ref('customer-health');

const pageTitle = computed(() => {
    if (healthMainTab.value === 'license-health') return t('license_health_page.page_title');
    return t(`${P}.page_title`, { tab: t(`${P}.tabs.${activeTab.value}`) });
});

// ── 客户健康度 ──
const activeTab = ref('dashboard');
const loading = ref(false);
const calculatingAll = ref(false);

const stats = ref({});

const scoreList = ref([]);
const gradeFilter = ref('');
const page = ref(1);
const perPage = ref(20);
const total = ref(0);

const churnList = ref([]);
const riskFilter = ref('');
const churnPage = ref(1);
const churnPerPage = ref(20);
const churnTotal = ref(0);

function dimLabel(key) {
    const k = `${P}.dims.${key}`;
    const translated = t(k);
    return translated === k ? key : translated;
}

function scoreColor(score) {
    const s = Number(score);
    if (s >= 70) return '#67c23a';
    if (s >= 40) return '#e6a23c';
    return '#f56c6c';
}

function gradeLabel(grade) {
    const key = `${P}.grades.${grade}`;
    const translated = t(key);
    return translated === key ? grade : translated;
}

function gradeTagType(grade) {
    return { healthy: 'success', warning: 'warning', critical: 'danger' }[grade] || 'info';
}

function riskLabel(level) {
    const key = `${P}.risk.${level}`;
    const translated = t(key);
    return translated === key ? level : translated;
}

function riskTagType(level) {
    return { low: 'info', medium: 'warning', high: 'danger', critical: 'danger' }[level] || 'info';
}

function riskColor(level) {
    return { low: '#909399', medium: '#e6a23c', high: '#f56c6c', critical: '#b91c1c' }[level] || '#909399';
}

function signalLabel(signal) {
    const key = `${P}.signals.${signal}`;
    const translated = t(key);
    return translated === key ? signal : translated;
}

function formatDate(dateStr) {
    return new Date(dateStr).toLocaleString(dateLocale.value);
}

async function refreshDashboard() {
    loading.value = true;
    try {
        const res = await healthScoreApi.getDashboard();
        stats.value = res.data.data || {};
    } catch (e) {
        console.error('Failed to fetch dashboard', e);
    } finally {
        loading.value = false;
    }
}

async function handleCalculateAll() {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm_calc`), t('actions.confirm'), { type: 'warning' });
        calculatingAll.value = true;
        const res = await healthScoreApi.calculateAll();
        ElMessage.success(res.data.message || t(`${P}.messages.calc_done`));
        await refreshDashboard();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t(`${P}.messages.calc_failed`));
    } finally {
        calculatingAll.value = false;
    }
}

async function fetchList() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (gradeFilter.value) params.grade = gradeFilter.value;
        const res = await healthScoreApi.getList(params);
        scoreList.value = res.data.data || [];
        total.value = res.data.total || 0;
    } catch (e) {
        console.error('Failed to fetch list', e);
    } finally {
        loading.value = false;
    }
}

async function fetchChurnList() {
    loading.value = true;
    try {
        const params = { page: churnPage.value, per_page: churnPerPage.value };
        if (riskFilter.value) params.risk_level = riskFilter.value;
        const res = await healthScoreApi.getChurnList(params);
        churnList.value = res.data.data || [];
        churnTotal.value = res.data.total || 0;
    } catch (e) {
        console.error('Failed to fetch churn list', e);
    } finally {
        loading.value = false;
    }
}

// ── License 健康 ──
const lh_tabVisited = ref(false);
const lh_dashboard = ref({});
const lh_list = ref([]);
const lh_loading = ref(false);
const lh_calculating = ref(false);
const lh_recalculatingId = ref(null);
const lh_search = ref('');
const lh_currentPage = ref(1);
const lh_perPage = ref(20);
const lh_total = ref(0);
const lh_detailVisible = ref(false);
const lh_detail = ref(null);

const lh_filters = reactive({
    grade: '',
});

const lh_dimensions = computed(() => [
    { key: 'activation_score', label: t('license_health_page.dims.activation'), weight: 0.25 },
    { key: 'renewal_score', label: t('license_health_page.dims.renewal'), weight: 0.30 },
    { key: 'ticket_score', label: t('license_health_page.dims.ticket'), weight: 0.20 },
    { key: 'device_score', label: t('license_health_page.dims.device'), weight: 0.15 },
    { key: 'payment_score', label: t('license_health_page.dims.payment'), weight: 0.10 },
]);

const lh_avgScoreColor = computed(() => lh_scoreColor(lh_dashboard.value.average_score ?? 0));

function lh_scoreColor(score) {
    if (score >= 80) return '#67c23a';
    if (score >= 60) return '#e6a23c';
    return '#f56c6c';
}

function lh_gradeTag(grade) {
    return { healthy: 'success', warning: 'warning', critical: 'danger' }[grade] || 'info';
}

function lh_gradeLabel(grade) {
    const key = { healthy: 'healthy', warning: 'warning', critical: 'critical' }[grade];
    return key ? t(`license_health_page.grades.${key}`) : grade;
}

function lh_churnTag(prob) {
    if (!prob) return 'info';
    if (prob >= 0.75) return 'danger';
    if (prob >= 0.5) return 'warning';
    return 'success';
}

function lh_churnLabel(prob) {
    if (!prob) return t('license_health_page.churn.unknown');
    if (prob >= 0.75) return t('license_health_page.churn.high');
    if (prob >= 0.5) return t('license_health_page.churn.medium');
    return t('license_health_page.churn.low');
}

async function lh_fetchDashboard() {
    try {
        const res = await healthScoreApi.getDashboard();
        lh_dashboard.value = res.data;
    } catch {
        lh_dashboard.value = {};
    }
}

async function lh_fetchList() {
    lh_loading.value = true;
    try {
        const res = await healthScoreApi.getList({
            page: lh_currentPage.value,
            per_page: lh_perPage.value,
            grade: lh_filters.grade || undefined,
            search: lh_search.value || undefined,
        });
        lh_list.value = res.data.data;
        lh_total.value = res.data.total;
    } catch {
        lh_list.value = [];
    } finally {
        lh_loading.value = false;
    }
}

async function lh_handleCalculateAll() {
    lh_calculating.value = true;
    try {
        await healthScoreApi.calculateAll();
        ElMessage.success(t('license_health_page.messages.recalc_all_ok'));
        await lh_fetchDashboard();
        await lh_fetchList();
    } catch (e) {
        ElMessage.error(e.message || t('license_health_page.messages.calc_failed'));
    } finally {
        lh_calculating.value = false;
    }
}

async function lh_handleRecalculate(row) {
    lh_recalculatingId.value = row.id;
    try {
        await healthScoreApi.calculate(row.customer_id);
        ElMessage.success(t('license_health_page.messages.recalc_ok'));
        await lh_fetchList();
    } catch (e) {
        ElMessage.error(e.message || t('license_health_page.messages.calc_failed'));
    } finally {
        lh_recalculatingId.value = null;
    }
}

async function lh_showDetail(row) {
    try {
        const res = await healthScoreApi.show(row.customer_id);
        lh_detail.value = res.data;
        lh_detailVisible.value = true;
    } catch {
        ElMessage.error(t('license_health_page.messages.detail_failed'));
    }
}

function lh_onPageChange(page) {
    lh_currentPage.value = page;
    lh_fetchList();
}

watch(healthMainTab, async (val) => {
    if (val === 'license-health' && !lh_tabVisited.value) {
        lh_tabVisited.value = true;
        await nextTick();
        lh_fetchDashboard();
        lh_fetchList();
    }
});

onMounted(() => {
    refreshDashboard();
});
</script>

<style scoped>
.health-score-manager {
    padding: 20px;
}
.stat-card {
    text-align: center;
    padding: 8px 0;
}
.dimension-box {
    flex: 1;
    min-width: 160px;
    padding: 8px 12px;
}
.dimension-label {
    font-size: 13px;
    color: #606266;
    margin-bottom: 6px;
    font-weight: 500;
}
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
</style>
