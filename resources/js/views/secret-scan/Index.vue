<template>
    <div>
        <!-- 面包屑 -->
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin/dashboard' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>安全中心</el-breadcrumb-item>
            <el-breadcrumb-item>密钥泄露扫描</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-gray-800">{{ stats.total_findings ?? '-' }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">总发现</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-danger">{{ stats.critical ?? '-' }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">严重</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-warning">{{ stats.open ?? '-' }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">待处理</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value text-3xl font-bold text-success">{{ stats.dismissed ?? 0 }}</div>
                    <div class="stat-label text-sm text-gray-500 mt-1">已处理</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="mb-4">
            <el-row :gutter="16" align="middle">
                <el-col :span="12">
                    <el-button type="primary" @click="handleFullScan" :loading="scanning">
                        <el-icon class="mr-1"><Search /></el-icon>全量扫描
                    </el-button>
                    <el-button @click="handleQuickScan" :loading="quickScanning" class="ml-2">
                        快速扫描
                    </el-button>
                </el-col>
                <el-col :span="12" class="text-right">
                    <el-select v-model="filters.severity" placeholder="严重级别" clearable class="mr-2" style="width:120px" @change="fetchEntries">
                        <el-option label="严重" value="critical" />
                        <el-option label="高" value="high" />
                        <el-option label="中" value="medium" />
                    </el-select>
                    <el-select v-model="filters.status" placeholder="状态" clearable style="width:120px" @change="fetchEntries">
                        <el-option label="待处理" value="open" />
                        <el-option label="已忽略" value="dismissed" />
                        <el-option label="已吊销" value="revoked" />
                    </el-select>
                </el-col>
            </el-row>
        </el-card>

        <!-- 扫描结果列表 -->
        <el-card>
            <el-table :data="entries" v-loading="loading" stripe style="width:100%">
                <el-table-column prop="id" label="#" width="60" />
                <el-table-column prop="file" label="文件" min-width="250">
                    <template #default="{ row }">
                        <el-tooltip :content="row.file" placement="top">
                            <span class="text-sm font-mono">{{ row.file }}</span>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column prop="pattern_label" label="泄露类型" width="180" />
                <el-table-column prop="matched_preview" label="匹配内容" width="160">
                    <template #default="{ row }">
                        <el-tag type="danger" class="font-mono text-xs">{{ row.matched_preview }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="severity" label="级别" width="80">
                    <template #default="{ row }">
                        <el-tag :type="severityTag(row.severity)" size="small">
                            {{ severityLabel(row.severity) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusTag(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="发现时间" width="170" />
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'open'" type="warning" size="small" @click="handleResolve(row, 'dismissed')">忽略</el-button>
                        <el-button v-if="row.status === 'open'" type="danger" size="small" @click="handleResolve(row, 'revoked')">已吊销</el-button>
                        <span v-else class="text-gray-400 text-sm">{{ row.resolver?.name ?? '-' }}</span>
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

        <!-- 扫描进度对话框 -->
        <el-dialog v-model="scanDialog.visible" title="扫描结果" width="600px">
            <el-alert v-if="scanDialog.error" :title="scanDialog.error" type="error" show-icon class="mb-3" />
            <div v-else>
                <el-result
                    :icon="scanDialog.total > 0 ? 'warning' : 'success'"
                    :title="scanDialog.total > 0 ? '检测到密钥泄露' : '未检测到密钥泄露'"
                    :sub-title="`已扫描 ${scanDialog.scanned} 个文件`"
                >
                    <template #extra>
                        <p v-if="scanDialog.total > 0" class="text-danger font-bold text-lg">
                            发现 {{ scanDialog.total }} 个密钥泄露
                        </p>
                    </template>
                </el-result>
            </div>
            <template #footer>
                <el-button @click="scanDialog.visible = false">关闭</el-button>
                <el-button v-if="scanDialog.total > 0" type="primary" @click="scanDialog.visible = false; fetchEntries()">查看详情</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search } from '@element-plus/icons-vue';
import secretScanApi from '../../api/secret-scan';

const stats = ref({});
const entries = ref([]);
const loading = ref(false);
const scanning = ref(false);
const quickScanning = ref(false);
const currentPage = ref(1);
const perPage = ref(20);
const total = ref(0);

const filters = reactive({
    severity: '',
    status: '',
});

const scanDialog = reactive({
    visible: false,
    scanned: 0,
    total: 0,
    error: '',
});

function severityTag(val) {
    return { critical: 'danger', high: 'warning', medium: 'info' }[val] || 'info';
}

function severityLabel(val) {
    return { critical: '严重', high: '高', medium: '中' }[val] || val;
}

function statusTag(val) {
    return { open: 'danger', dismissed: 'info', revoked: 'success' }[val] || 'info';
}

function statusLabel(val) {
    return { open: '待处理', dismissed: '已忽略', revoked: '已吊销' }[val] || val;
}

async function fetchDashboard() {
    try {
        const res = await secretScanApi.dashboard();
        stats.value = res.data;
    } catch {
        // ignore
    }
}

async function fetchEntries() {
    loading.value = true;
    try {
        const res = await secretScanApi.entries({
            page: currentPage.value,
            per_page: perPage.value,
            search: '',
            severity: filters.severity || undefined,
            status: filters.status || undefined,
        });
        entries.value = res.data.data;
        total.value = res.data.total;
    } catch {
        entries.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleFullScan() {
    scanning.value = true;
    scanDialog.visible = true;
    scanDialog.error = '';
    try {
        const res = await secretScanApi.scan();
        scanDialog.scanned = res.data.scanned;
        scanDialog.total = res.data.total_findings;
        ElMessage.success(`扫描完成，发现 ${res.data.new_findings} 个新泄露`);
        await fetchDashboard();
        await fetchEntries();
    } catch (e) {
        scanDialog.error = e.message || '扫描失败';
    } finally {
        scanning.value = false;
    }
}

async function handleQuickScan() {
    quickScanning.value = true;
    try {
        const res = await secretScanApi.quickScan();
        ElMessage.success(`快速扫描完成，发现 ${res.data.new_findings} 个新泄露`);
        await fetchDashboard();
        await fetchEntries();
    } catch (e) {
        ElMessage.error(e.message || '快速扫描失败');
    } finally {
        quickScanning.value = false;
    }
}

async function handleResolve(row, action) {
    const label = action === 'dismissed' ? '忽略' : '已吊销';
    try {
        await ElMessageBox.confirm(`确认将此发现标记为"${label}"？`, '确认', {
            type: 'warning',
            confirmButtonText: '确认',
            cancelButtonText: '取消',
        });
        await secretScanApi.resolve(row.id, { action });
        ElMessage.success(`已标记为${label}`);
        await fetchEntries();
        await fetchDashboard();
    } catch {
        // cancelled
    }
}

function onPageChange(page) {
    currentPage.value = page;
    fetchEntries();
}

onMounted(() => {
    fetchDashboard();
    fetchEntries();
});
</script>

<style scoped>
.stat-card {
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
}
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
.font-mono { font-family: 'Courier New', Courier, monospace; }
</style>
