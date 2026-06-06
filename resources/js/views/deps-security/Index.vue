<template>
    <div class="deps-security-page">
        <div class="page-header">
            <div class="header-left">
                <h2>第三方依赖安全监控</h2>
                <span class="header-subtitle">Composer / NPM 依赖漏洞检测与跟踪</span>
            </div>
            <div class="header-right">
                <el-button type="primary" :loading="scanning" @click="handleTriggerScan">
                    <el-icon><Refresh /></el-icon> 立即扫描
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value" :class="{ 'text-danger': stats.total_open > 0 }">{{ stats.total_open }}</div>
                    <div class="stat-label">未修复漏洞</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-danger">{{ stats.critical }}</div>
                    <div class="stat-label">严重 (Critical)</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-warning">{{ stats.high }}</div>
                    <div class="stat-label">高危 (High)</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-primary">{{ stats.medium }}</div>
                    <div class="stat-label">中危 (Medium)</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value">{{ stats.fixed_total }}</div>
                    <div class="stat-label">已修复</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-muted">{{ stats.total_scanned_packages || '...' }}</div>
                    <div class="stat-label">已扫描包数</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 扫描状态与配置 -->
        <el-row :gutter="16" class="info-row">
            <el-col :span="12">
                <el-card shadow="never" class="info-card" :body-style="{ padding: '12px 16px' }">
                    <div class="info-item">
                        <span class="info-label">最后扫描：</span>
                        <span>{{ stats.last_scan_at ? formatDate(stats.last_scan_at) : '尚未扫描' }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="info-card" :body-style="{ padding: '12px 16px' }">
                    <div class="info-item">
                        <el-tag size="small" type="success" effect="plain" v-if="config.dependabot_configured">Dependabot 已配置</el-tag>
                        <el-tag size="small" type="danger" effect="plain" v-else>Dependabot 未配置</el-tag>
                        <span class="info-label" style="margin-left:12px;">Composer v{{ config.composer_version || '?' }} | Node {{ config.node_version || '?' }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="搜索包名/CVE/标题" clearable @input="loadVulnerabilities" style="width:200px;" />
                </el-form-item>
                <el-form-item label="生态">
                    <el-select v-model="filters.ecosystem" placeholder="全部" clearable @change="loadVulnerabilities" style="width:120px;">
                        <el-option label="Composer" value="composer" />
                        <el-option label="NPM" value="npm" />
                    </el-select>
                </el-form-item>
                <el-form-item label="严重级别">
                    <el-select v-model="filters.severity" placeholder="全部" clearable @change="loadVulnerabilities" style="width:140px;">
                        <el-option label="严重" value="critical" />
                        <el-option label="高危" value="high" />
                        <el-option label="中危" value="medium" />
                        <el-option label="低危" value="low" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="未修复" clearable @change="loadVulnerabilities" style="width:120px;">
                        <el-option label="未修复" value="" />
                        <el-option label="已修复" value="fixed" />
                        <el-option label="已忽略" value="ignored" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button text type="primary" @click="handleBatchFix" :disabled="!selectedIds.length">
                        标记为已修复 ({{ selectedIds.length }})
                    </el-button>
                    <el-button text type="warning" @click="handleBatchIgnore" :disabled="!selectedIds.length">
                        忽略选中
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 漏洞列表 -->
        <el-card shadow="never">
            <el-table
                :data="vulnerabilities"
                v-loading="loading"
                stripe
                @selection-change="onSelectionChange"
                row-key="id"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column label="严重级别" width="110">
                    <template #default="{ row }">
                        <el-tag :type="severityType(row.severity)" size="small" effect="dark" class="severity-tag">
                            {{ severityLabel(row.severity) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="生态" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">
                            {{ row.ecosystem === 'composer' ? 'Composer' : 'NPM' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="包名" min-width="200" prop="package_name">
                    <template #default="{ row }">
                        <div class="package-cell">
                            <div class="package-name">{{ row.package_name }}</div>
                            <div class="package-version">当前: {{ row.installed_version }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="CVE / 漏洞标题" min-width="250" prop="title">
                    <template #default="{ row }">
                        <div class="cve-cell">
                            <div v-if="row.cve" class="cve-id">{{ row.cve }}</div>
                            <div class="cve-title" :title="row.title">{{ row.title }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="修复版本" width="150" prop="patched_version">
                    <template #default="{ row }">
                        <span v-if="row.patched_version" class="fix-version">{{ row.patched_version }}</span>
                        <span v-else class="text-muted">暂无</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'open' ? 'danger' : row.status === 'fixed' ? 'success' : 'info'" size="small">
                            {{ row.status === 'open' ? '未修复' : row.status === 'fixed' ? '已修复' : row.status === 'ignored' ? '已忽略' : '误报' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="发现时间" width="170" prop="detected_at">
                    <template #default="{ row }">
                        {{ formatDate(row.detected_at || row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'open'"
                            text size="small" type="success"
                            @click="handleMarkFixed(row)"
                        >
                            已修复
                        </el-button>
                        <el-button
                            v-if="row.status === 'open'"
                            text size="small" type="warning"
                            @click="handleIgnore(row)"
                        >
                            忽略
                        </el-button>
                        <el-button
                            v-if="row.status !== 'open'"
                            text size="small" type="primary"
                            @click="handleReopen(row)"
                        >
                            重新打开
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div v-if="total > 0" class="pagination-bar">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadVulnerabilities"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import depsSecurityApi from '@/api/deps-security';

const loading = ref(false);
const scanning = ref(false);
const vulnerabilities = ref([]);
const total = ref(0);
const currentPage = ref(1);
const perPage = ref(20);
const selectedIds = ref([]);
const stats = reactive({
    total_open: 0,
    critical: 0,
    high: 0,
    medium: 0,
    low: 0,
    fixed_total: 0,
    last_scan_at: null,
    total_scanned_packages: 0,
});
const config = reactive({
    dependabot_configured: false,
    composer_version: null,
    node_version: null,
});

const filters = reactive({
    search: '',
    ecosystem: '',
    severity: '',
    status: '',
});

function severityType(severity) {
    return { critical: 'danger', high: 'warning', medium: 'primary', low: 'info' }[severity] || 'info';
}

function severityLabel(severity) {
    return { critical: '严重', high: '高危', medium: '中危', low: '低危' }[severity] || severity;
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function onSelectionChange(selection) {
    selectedIds.value = selection.map(s => s.id);
}

async function loadStats() {
    try {
        const { data: res } = await depsSecurityApi.stats();
        if (res.success) {
            Object.assign(stats, res.data);
        }
    } catch {
        // ignore
    }
}

async function loadConfig() {
    try {
        const { data: res } = await depsSecurityApi.config();
        if (res.success) {
            Object.assign(config, res.data);
        }
    } catch {
        // ignore
    }
}

async function loadVulnerabilities() {
    loading.value = true;
    try {
        const params = { per_page: perPage.value, page: currentPage.value };
        if (filters.search) params.search = filters.search;
        if (filters.ecosystem) params['filter.ecosystem'] = filters.ecosystem;
        if (filters.severity) params['filter.severity'] = filters.severity;
        if (filters.status) params['filter.status'] = filters.status;

        const { data: res } = await depsSecurityApi.list(params);
        if (res.success) {
            vulnerabilities.value = res.data?.data || [];
            total.value = res.meta?.total || 0;
        }
    } catch {
        vulnerabilities.value = [];
        total.value = 0;
    } finally {
        loading.value = false;
    }
}

async function handleTriggerScan() {
    scanning.value = true;
    try {
        const { data: res } = await depsSecurityApi.triggerScan();
        if (res.success) {
            ElMessage.success('扫描已启动，将在后台执行');
            stats.last_scan_at = res.data?.started_at;
        }
    } catch {
        ElMessage.error('启动扫描失败');
    } finally {
        scanning.value = false;
    }
}

async function handleMarkFixed(row) {
    try {
        await depsSecurityApi.updateStatus(row.id, { status: 'fixed' });
        ElMessage.success('已标记为已修复');
        loadVulnerabilities();
        loadStats();
    } catch {
        ElMessage.error('操作失败');
    }
}

async function handleIgnore(row) {
    try {
        await depsSecurityApi.updateStatus(row.id, { status: 'ignored' });
        ElMessage.success('已忽略');
        loadVulnerabilities();
        loadStats();
    } catch {
        ElMessage.error('操作失败');
    }
}

async function handleReopen(row) {
    try {
        await depsSecurityApi.updateStatus(row.id, { status: 'open' });
        ElMessage.success('已重新打开');
        loadVulnerabilities();
        loadStats();
    } catch {
        ElMessage.error('操作失败');
    }
}

async function handleBatchFix() {
    if (!selectedIds.value.length) return;
    try {
        await ElMessageBox.confirm(`确定将选中的 ${selectedIds.value.length} 个漏洞标记为已修复？`, '确认批量修复', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'info',
        });
        await depsSecurityApi.batchUpdate({ ids: selectedIds.value, status: 'fixed' });
        ElMessage.success('批量标记成功');
        selectedIds.value = [];
        loadVulnerabilities();
        loadStats();
    } catch { /* cancelled */ }
}

async function handleBatchIgnore() {
    if (!selectedIds.value.length) return;
    try {
        await ElMessageBox.confirm(`确定忽略选中的 ${selectedIds.value.length} 个漏洞？`, '确认批量忽略', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
        });
        await depsSecurityApi.batchUpdate({ ids: selectedIds.value, status: 'ignored' });
        ElMessage.success('批量忽略成功');
        selectedIds.value = [];
        loadVulnerabilities();
        loadStats();
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadConfig();
    loadStats();
    loadVulnerabilities();
});
</script>

<style scoped>
.deps-security-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.stats-row { margin-bottom: 12px; }
.stat-card { text-align: center; }
.stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.text-danger { color: #F56C6C; }
.text-warning { color: #E6A23C; }
.text-primary { color: #409EFF; }
.text-muted { color: #909399; }

.info-row { margin-bottom: 16px; }
.info-card { }
.info-item {
    display: flex;
    align-items: center;
    font-size: 13px;
    color: var(--el-text-color-secondary);
}
.info-label { color: var(--el-text-color-regular); font-weight: 500; }

.filter-card { margin-bottom: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }

.severity-tag { font-weight: 700; min-width: 60px; text-align: center; }

.package-cell { }
.package-name { font-weight: 500; font-size: 13px; }
.package-version { font-size: 11px; color: var(--el-text-color-secondary); }

.cve-cell { }
.cve-id {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    color: var(--el-color-primary);
    font-weight: 600;
}
.cve-title {
    font-size: 13px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 250px;
}

.fix-version {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 13px;
    color: var(--el-color-success);
    font-weight: 600;
}

.pagination-bar {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

:deep(.el-card__body) { padding: 16px; }
</style>
