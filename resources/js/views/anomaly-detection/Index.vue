<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>安全中心</el-breadcrumb-item>
            <el-breadcrumb-item>异常检测</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-gray-800">{{ stats.total ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">总异常</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-danger">{{ stats.open ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">待处理</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-danger-dark">{{ stats.critical ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">严重</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-success">{{ stats.resolved ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">已处理</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="text-3xl font-bold text-primary">{{ stats.today ?? '-' }}</div>
                    <div class="text-sm text-gray-500 mt-1">今日新增</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" style="cursor:pointer" @click="handleDetect">
                    <div class="flex items-center justify-center h-full">
                        <el-button type="primary" :loading="detecting" circle>
                            <el-icon><Search /></el-icon>
                        </el-button>
                        <span class="ml-2 text-sm">执行检测</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 异常类型分布 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="8">
                <el-card>
                    <template #header><span class="font-semibold">异常类型分布</span></template>
                    <div v-if="Object.keys(stats.by_type ?? {}).length">
                        <div v-for="(count, type) in stats.by_type" :key="type"
                            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <span>{{ typeLabel(type) }}</span>
                            <el-tag :type="count > 0 ? 'danger' : 'info'" size="small">{{ count }}</el-tag>
                        </div>
                    </div>
                    <el-empty v-else description="暂无异常" :image-size="60" />
                </el-card>
            </el-col>
            <el-col :span="16">
                <el-card>
                    <template #header>
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">异常列表</span>
                            <div class="flex gap-2">
                                <el-select v-model="filters.type" placeholder="类型" clearable style="width:120px" @change="fetchList">
                                    <el-option label="IP批量激活" value="ip_batch_activation" />
                                    <el-option label="非常规操作" value="unusual_operation" />
                                    <el-option label="地理位置切换" value="rapid_geo_switch" />
                                    <el-option label="暴力尝试" value="brute_force_attempt" />
                                </el-select>
                                <el-select v-model="filters.severity" placeholder="级别" clearable style="width:100px" @change="fetchList">
                                    <el-option label="严重" value="critical" />
                                    <el-option label="高" value="high" />
                                    <el-option label="中" value="medium" />
                                </el-select>
                                <el-select v-model="filters.status" placeholder="状态" clearable style="width:100px" @change="fetchList">
                                    <el-option label="待处理" value="open" />
                                    <el-option label="已处理" value="resolved" />
                                </el-select>
                            </div>
                        </div>
                    </template>
                    <el-table :data="list" v-loading="loading" stripe size="small">
                        <el-table-column prop="id" label="#" width="50" />
                        <el-table-column label="类型" width="120">
                            <template #default="{ row }">{{ typeLabel(row.anomaly_type) }}</template>
                        </el-table-column>
                        <el-table-column label="级别" width="70">
                            <template #default="{ row }">
                                <el-tag :type="sevTag(row.severity)" size="small">{{ sevLabel(row.severity) }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="description" label="描述" min-width="220" show-overflow-tooltip />
                        <el-table-column label="IP" width="130">
                            <template #default="{ row }">{{ row.context?.ip_address || row.context?.ip || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="detected_at" label="检测时间" width="160" />
                        <el-table-column label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'open' ? 'danger' : 'success'" size="small">{{ row.status === 'open' ? '待处理' : '已处理' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="100" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'open'" size="small" @click="handleResolve(row)">处理</el-button>
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
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import anomalyApi from '../../api/anomalyDetection';

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

const typeLabels = {
    ip_batch_activation: 'IP批量激活',
    unusual_operation: '非常规操作',
    rapid_geo_switch: '地理位置切换',
    brute_force_attempt: '暴力尝试',
};

function typeLabel(t) { return typeLabels[t] || t; }
function sevTag(s) { return { critical: 'danger', high: 'warning', medium: 'info' }[s] || 'info'; }
function sevLabel(s) { return { critical: '严重', high: '高', medium: '中' }[s] || s; }

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
        const res = await anomalyApi.detect();
        ElMessage.success('检测完成');
        await fetchDashboard();
        await fetchList();
    } catch (e) {
        ElMessage.error(e.message || '检测失败');
    } finally {
        detecting.value = false;
    }
}

async function handleResolve(row) {
    try {
        await ElMessageBox.confirm(`标记此异常为已处理？`, '确认', { type: 'info' });
        await anomalyApi.resolve(row.id);
        ElMessage.success('已处理');
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
.text-primary { color: #409eff; }
</style>
