<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>运营管理</el-breadcrumb-item>
            <el-breadcrumb-item>License 健康评分</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ dashboard.total_licenses ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">License 总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold" :style="{ color: avgScoreColor }">{{ dashboard.average_score ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">平均健康分</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ dashboard.healthy_count ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">健康 (≥80)</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-danger">{{ dashboard.critical_count ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">危险 (&lt;60)</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="mb-4">
            <el-row :gutter="16" align="middle">
                <el-col :span="12">
                    <el-button type="primary" @click="handleCalculateAll" :loading="calculating">
                        <el-icon class="mr-1"><Refresh /></el-icon>重新计算所有
                    </el-button>
                    <span class="text-sm text-gray-400 ml-2">上次计算: {{ dashboard.last_calculated_at || '从未' }}</span>
                </el-col>
                <el-col :span="12" class="text-right">
                    <el-select v-model="filters.grade" placeholder="健康等级" clearable style="width:140px" @change="fetchList">
                        <el-option label="健康" value="healthy" />
                        <el-option label="注意" value="warning" />
                        <el-option label="危险" value="critical" />
                    </el-select>
                    <el-input v-model="search" placeholder="搜索客户名称" clearable style="width:200px;margin-left:8px" @clear="fetchList" @keyup.enter="fetchList" />
                </el-col>
            </el-row>
        </el-card>

        <!-- 综合改进建议 -->
        <el-card v-if="dashboard.top_suggestions?.length" class="mb-4">
            <template #header><span class="font-semibold">⚠️ 综合改进建议</span></template>
            <el-timeline>
                <el-timeline-item
                    v-for="(s, i) in dashboard.top_suggestions"
                    :key="i"
                    :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'primary'"
                    :timestamp="s.customer_name || ''"
                >
                    {{ s.message }}
                </el-timeline-item>
            </el-timeline>
        </el-card>

        <!-- 健康评分列表 -->
        <el-card>
            <template #header><span>客户健康评分列表</span></template>
            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column label="客户" min-width="160">
                    <template #default="{ row }">
                        <div class="font-medium">{{ row.customer?.name || '客户#' + row.customer_id }}</div>
                        <div class="text-xs text-gray-400">{{ row.customer?.email || '' }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="健康评分" width="120" align="center">
                    <template #default="{ row }">
                        <el-progress
                            type="circle"
                            :percentage="Math.round(row.score)"
                            :width="50"
                            :stroke-width="6"
                            :color="scoreColor(row.score)"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="等级" width="80">
                    <template #default="{ row }">
                        <el-tag :type="gradeTag(row.grade)" size="small">{{ gradeLabel(row.grade) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="流失风险" width="100">
                    <template #default="{ row }">
                        <el-tag :type="churnTag(row.churn_probability)" size="small">
                            {{ churnLabel(row.churn_probability) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="license_count" label="License数" width="80" align="center" />
                <el-table-column prop="active_devices" label="活跃设备" width="80" align="center" />
                <el-table-column prop="score_updated_at" label="更新于" width="160" />
                <el-table-column label="操作" width="140" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button size="small" @click="handleRecalculate(row)" :loading="recalculatingId === row.id">重算</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-center" v-if="total > perPage">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="prev, pager, next"
                    @current-change="onPageChange"
                />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="健康评分详情" width="650px">
            <template v-if="detail">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="8" v-for="dim in dimensions" :key="dim.key" class="mb-3">
                        <el-card shadow="never" :body-style="{ padding: '12px' }">
                            <div class="text-sm text-gray-500">{{ dim.label }}</div>
                            <div class="text-xl font-bold mt-1" :style="{ color: scoreColor(detail[dim.key] ?? 0) }">
                                {{ (detail[dim.key] ?? 0).toFixed(1) }}
                            </div>
                            <div class="text-xs text-gray-400">权重 {{ (dim.weight * 100).toFixed(0) }}%</div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-divider />
                <h4 class="font-semibold mb-3">改进建议</h4>
                <el-timeline v-if="detail.suggestions?.length">
                    <el-timeline-item
                        v-for="(s, i) in detail.suggestions"
                        :key="i"
                        :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'primary'"
                    >
                        {{ s.message }}
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无改进建议" :image-size="60" />
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import healthScoreApi from '../../api/healthScore';

const dashboard = ref({});
const list = ref([]);
const loading = ref(false);
const calculating = ref(false);
const recalculatingId = ref(null);
const search = ref('');
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);
const detailVisible = ref(false);
const detail = ref(null);

const filters = reactive({
    grade: '',
});

const dimensions = [
    { key: 'activation_score', label: '激活活跃度', weight: 0.25 },
    { key: 'renewal_score', label: '续费健康度', weight: 0.30 },
    { key: 'ticket_score', label: '工单体验', weight: 0.20 },
    { key: 'device_score', label: '设备安全', weight: 0.15 },
    { key: 'payment_score', label: '支付健康度', weight: 0.10 },
];

const avgScoreColor = computed(() => scoreColor(dashboard.value.average_score ?? 0));

function scoreColor(score) {
    if (score >= 80) return '#67c23a';
    if (score >= 60) return '#e6a23c';
    return '#f56c6c';
}

function gradeTag(grade) {
    return { healthy: 'success', warning: 'warning', critical: 'danger' }[grade] || 'info';
}

function gradeLabel(grade) {
    return { healthy: '健康', warning: '注意', critical: '危险' }[grade] || grade;
}

function churnTag(prob) {
    if (!prob) return 'info';
    if (prob >= 0.75) return 'danger';
    if (prob >= 0.5) return 'warning';
    return 'success';
}

function churnLabel(prob) {
    if (!prob) return '未知';
    if (prob >= 0.75) return '高风险';
    if (prob >= 0.5) return '中风险';
    if (prob >= 0.3) return '低风险';
    return '低风险';
}

async function fetchDashboard() {
    try {
        const res = await healthScoreApi.getDashboard();
        dashboard.value = res.data;
    } catch {
        dashboard.value = {};
    }
}

async function fetchList() {
    loading.value = true;
    try {
        const res = await healthScoreApi.getList({
            page: currentPage.value,
            per_page: perPage.value,
            grade: filters.grade || undefined,
            search: search.value || undefined,
        });
        list.value = res.data.data;
        total.value = res.data.total;
    } catch {
        list.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleCalculateAll() {
    calculating.value = true;
    try {
        await healthScoreApi.calculateAll();
        ElMessage.success('全部健康评分已重新计算');
        await fetchDashboard();
        await fetchList();
    } catch (e) {
        ElMessage.error(e.message || '计算失败');
    } finally {
        calculating.value = false;
    }
}

async function handleRecalculate(row) {
    recalculatingId.value = row.id;
    try {
        await healthScoreApi.calculate(row.customer_id);
        ElMessage.success('已重新计算');
        await fetchList();
    } catch (e) {
        ElMessage.error(e.message || '计算失败');
    } finally {
        recalculatingId.value = null;
    }
}

async function showDetail(row) {
    try {
        const res = await healthScoreApi.show(row.customer_id);
        detail.value = res.data;
        detailVisible.value = true;
    } catch {
        ElMessage.error('加载详情失败');
    }
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
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
</style>
