<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('nav.security') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('nav.anomaly_detection') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ stats.total ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('anomaly_detection_page.stats.total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-danger">{{ stats.open ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('anomaly_detection_page.stats.open') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-danger-dark">{{ stats.critical ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('anomaly_detection_page.stats.critical') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ stats.resolved ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('anomaly_detection_page.stats.resolved') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-primary">{{ stats.today ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ t('anomaly_detection_page.stats.today') }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" style="cursor:pointer" @click="handleDetect">
                    <div class="flex items-center justify-center h-full">
                        <el-button type="primary" :loading="detecting" circle>
                            <el-icon><Search /></el-icon>
                        </el-button>
                        <span class="ml-2 text-sm">{{ t('anomaly_detection_page.run_detect') }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 异常类型分布 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="8">
                <el-card>
                    <template #header><span class="font-semibold">{{ t('anomaly_detection_page.type_distribution') }}</span></template>
                    <div v-if="Object.keys(stats.by_type ?? {}).length">
                        <div v-for="(count, type) in stats.by_type" :key="type"
                            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <span>{{ typeLabel(type) }}</span>
                            <el-tag :type="count > 0 ? 'danger' : 'info'" size="small">{{ count }}</el-tag>
                        </div>
                    </div>
                    <el-empty v-else :description="t('anomaly_detection_page.empty')" :image-size="60" />
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card>
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">{{ t('anomaly_detection_page.list_title') }}</span>
                            <div class="flex gap-2">
                                <el-select v-model="filters.type" :placeholder="t('anomaly_detection_page.filters.type')" clearable style="width:120px" @change="fetchList">
                                    <el-option v-for="opt in typeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                                <el-select v-model="filters.severity" :placeholder="t('anomaly_detection_page.filters.severity')" clearable style="width:100px" @change="fetchList">
                                    <el-option v-for="opt in severityFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                                <el-select v-model="filters.status" :placeholder="t('anomaly_detection_page.filters.status')" clearable style="width:100px" @change="fetchList">
                                    <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </div>
                        </div>
                    </template>
                    <el-table :data="list" v-loading="loading" stripe size="small">
                        <el-table-column prop="id" label="#" width="50" />
                        <el-table-column :label="t('anomaly_detection_page.columns.type')" width="120">
                            <template #default="{ row }">{{ typeLabel(row.anomaly_type) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('anomaly_detection_page.columns.severity')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="sevTag(row.severity)" size="small">{{ sevLabel(row.severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="description" :label="t('anomaly_detection_page.columns.description')" min-width="220" show-overflow-tooltip />
                        <el-table-column label="IP" width="130">
                            <template #default="{ row }">{{ row.context?.ip_address || row.context?.ip || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="detected_at" :label="t('anomaly_detection_page.columns.detected_at')" width="160" />
                        <el-table-column :label="t('anomaly_detection_page.columns.status')" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'open' ? 'danger' : 'success'" size="small">{{ statusLabel(row.status) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('anomaly_detection_page.columns.actions')" width="100" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'open'" size="small" @click="handleResolve(row)">{{ t('anomaly_detection_page.resolve') }}</el-button>
                                <span v-else class="text-gray-400 text-sm">{{ row.acknowledged_at?.substring(0,10) }}</span>
                            </template>
                        </el-table-column>
                    </el-table>

                    <div class="mt-4 flex justify-center" v-if="total > perPage">
                        <el-pagination v-model:current-page="currentPage" :page-size="perPage" :total="total"
                            layout="prev, pager, next" @current-change="onPageChange" />
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import anomalyApi from '../../api/anomalyDetection';

const { t } = useI18n();

const stats = ref({});
const list = ref([]);
const loading = ref(false);
const detecting = ref(false);
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);

const filters = reactive({
    type: '',
    severity: '',
    status: '',
});

const typeKeys = ['ip_batch_activation', 'unusual_operation', 'rapid_geo_switch', 'brute_force_attempt'];
const severityKeys = ['critical', 'high', 'medium'];
const statusKeys = ['open', 'resolved'];

const typeFilterOptions = computed(() =>
    typeKeys.map((value) => ({ value, label: t(`anomaly_detection_page.types.${value}`) }))
);
const severityFilterOptions = computed(() =>
    severityKeys.map((value) => ({ value, label: t(`anomaly_detection_page.severity.${value}`) }))
);
const statusFilterOptions = computed(() =>
    statusKeys.map((value) => ({ value, label: t(`anomaly_detection_page.status.${value}`) }))
);

function typeLabel(type) {
    const key = `anomaly_detection_page.types.${type}`;
    const label = t(key);
    return label !== key ? label : type;
}
function sevTag(s) { return { critical: 'danger', high: 'warning', medium: 'info' }[s] || 'info'; }
function sevLabel(s) {
    const key = `anomaly_detection_page.severity.${s}`;
    const label = t(key);
    return label !== key ? label : s;
}
function statusLabel(s) {
    const key = `anomaly_detection_page.status.${s}`;
    const label = t(key);
    return label !== key ? label : s;
}

async function fetchDashboard() {
    try {
        const res = await anomalyApi.dashboard();
        stats.value = res.data;
    } catch { /* ignore */ }
}

async function fetchList() {
    loading.value = true;
    try {
        const res = await anomalyApi.list({
            page: currentPage.value,
            per_page: perPage.value,
            ...filters,
        });
        list.value = res.data.data;
        total.value = res.data.total;
    } catch { list.value = []; }
    finally { loading.value = false; }
}

async function handleDetect() {
    detecting.value = true;
    try {
        await anomalyApi.detect();
        ElMessage.success(t('anomaly_detection_page.messages.detect_done'));
        await fetchDashboard();
        await fetchList();
    } catch (e) {
        ElMessage.error(e.message || t('anomaly_detection_page.messages.detect_failed'));
    } finally {
        detecting.value = false;
    }
}

async function handleResolve(row) {
    try {
        await ElMessageBox.confirm(
            t('anomaly_detection_page.dialog.resolve_confirm'),
            t('actions.confirm'),
            { type: 'info' }
        );
        await anomalyApi.resolve(row.id);
        ElMessage.success(t('anomaly_detection_page.messages.resolved'));
        await fetchList();
        await fetchDashboard();
    } catch { /* cancelled */ }
}

function onPageChange(page) {
    currentPage.value = page;
    fetchList();
}

onMounted(() => {
    fetchDashboard();
    fetchList();
});
</script>

<style scoped>
.text-danger { color: #f56c6c; }
.text-danger-dark { color: #c03636; }
.text-success { color: #67c23a; }
.text-primary { color: #0f172a; }
</style>
