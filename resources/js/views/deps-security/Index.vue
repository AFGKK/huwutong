<template>
    <div class="deps-security-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t(`${P}.title`) }}</h2>
                <span class="header-subtitle">{{ t(`${P}.subtitle`) }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" :loading="scanning" @click="handleTriggerScan">
                    <el-icon><Refresh /></el-icon> {{ t(`${P}.scan_now`) }}
                </el-button>
            </div>
        </div>

        <el-row :gutter="16" class="stats-row">
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value" :class="{ 'text-danger': stats.total_open > 0 }">{{ stats.total_open }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.open`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-danger">{{ stats.critical }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.critical`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-warning">{{ stats.high }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.high`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-primary">{{ stats.medium }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.medium`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value">{{ stats.fixed_total }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.fixed`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                    <div class="stat-value text-muted">{{ stats.total_scanned_packages || '...' }}</div>
                    <div class="stat-label">{{ t(`${P}.stats.scanned`) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16" class="info-row">
            <el-col :span="12">
                <el-card shadow="never" class="info-card" :body-style="{ padding: '12px 16px' }">
                    <div class="info-item">
                        <span class="info-label">{{ t(`${P}.last_scan`) }}</span>
                        <span>{{ stats.last_scan_at ? formatDate(stats.last_scan_at) : t(`${P}.never_scanned`) }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="info-card" :body-style="{ padding: '12px 16px' }">
                    <div class="info-item">
                        <el-tag size="small" type="success" effect="plain" v-if="config.dependabot_configured">{{ t(`${P}.dependabot_ok`) }}</el-tag>
                        <el-tag size="small" type="danger" effect="plain" v-else>{{ t(`${P}.dependabot_missing`) }}</el-tag>
                        <span class="info-label" style="margin-left:12px;">Composer v{{ config.composer_version || '?' }} | Node {{ config.node_version || '?' }}</span>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never" class="filter-card">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t('actions.search')">
                    <el-input v-model="filters.search" :placeholder="t(`${P}.search_ph`)" clearable @input="loadVulnerabilities" style="width:200px;" />
                </el-form-item>
                <el-form-item :label="t(`${P}.filters.ecosystem`)">
                    <el-select v-model="filters.ecosystem" :placeholder="t(`${P}.filters.all`)" clearable @change="loadVulnerabilities" style="width:120px;">
                        <el-option label="Composer" value="composer" />
                        <el-option label="NPM" value="npm" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.filters.severity`)">
                    <el-select v-model="filters.severity" :placeholder="t(`${P}.filters.all`)" clearable @change="loadVulnerabilities" style="width:140px;">
                        <el-option :label="t(`${P}.severity.critical`)" value="critical" />
                        <el-option :label="t(`${P}.severity.high`)" value="high" />
                        <el-option :label="t(`${P}.severity.medium`)" value="medium" />
                        <el-option :label="t(`${P}.severity.low`)" value="low" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.filters.status`)">
                    <el-select v-model="filters.status" :placeholder="t(`${P}.status.open`)" clearable @change="loadVulnerabilities" style="width:120px;">
                        <el-option :label="t(`${P}.status.open`)" value="" />
                        <el-option :label="t(`${P}.status.fixed`)" value="fixed" />
                        <el-option :label="t(`${P}.status.ignored`)" value="ignored" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button text type="primary" @click="handleBatchFix" :disabled="!selectedIds.length">
                        {{ t(`${P}.batch_fix`, { n: selectedIds.length }) }}
                    </el-button>
                    <el-button text type="warning" @click="handleBatchIgnore" :disabled="!selectedIds.length">
                        {{ t(`${P}.batch_ignore`) }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never">
            <el-table
                :data="vulnerabilities"
                v-loading="loading"
                stripe
                @selection-change="onSelectionChange"
                row-key="id"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column :label="t(`${P}.cols.severity`)" width="110">
                    <template #default="{ row }">
                        <el-tag :type="severityType(row.severity)" size="small" effect="dark" class="severity-tag">
                            {{ severityLabel(row.severity) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.ecosystem`)" width="100">
                    <template #default="{ row }">
                        <el-tag size="small" effect="plain">
                            {{ row.ecosystem === 'composer' ? 'Composer' : 'NPM' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.package`)" min-width="200" prop="package_name">
                    <template #default="{ row }">
                        <div class="package-cell">
                            <div class="package-name">{{ row.package_name }}</div>
                            <div class="package-version">{{ t(`${P}.current_ver`, { v: row.installed_version }) }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.cve`)" min-width="250" prop="title">
                    <template #default="{ row }">
                        <div class="cve-cell">
                            <div v-if="row.cve" class="cve-id">{{ row.cve }}</div>
                            <div class="cve-title" :title="row.title">{{ row.title }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.patched`)" width="150" prop="patched_version">
                    <template #default="{ row }">
                        <span v-if="row.patched_version" class="fix-version">{{ row.patched_version }}</span>
                        <span v-else class="text-muted">{{ t(`${P}.none`) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.status`)" width="100" prop="status">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'open' ? 'danger' : row.status === 'fixed' ? 'success' : 'info'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.detected`)" width="170" prop="detected_at">
                    <template #default="{ row }">
                        {{ formatDate(row.detected_at || row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.cols.actions`)" width="160" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'open'"
                            text size="small" type="success"
                            @click="handleMarkFixed(row)"
                        >
                            {{ t(`${P}.mark_fixed`) }}
                        </el-button>
                        <el-button
                            v-if="row.status === 'open'"
                            text size="small" type="warning"
                            @click="handleIgnore(row)"
                        >
                            {{ t(`${P}.ignore`) }}
                        </el-button>
                        <el-button
                            v-if="row.status !== 'open'"
                            text size="small" type="primary"
                            @click="handleReopen(row)"
                        >
                            {{ t(`${P}.reopen`) }}
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
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import depsSecurityApi from '@/api/deps-security';

const { t, locale } = useI18n();
const P = 'deps_security_page';
const dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'));

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
    const key = `${P}.severity.${severity}`;
    const translated = t(key);
    return translated === key ? severity : translated;
}

function statusLabel(status) {
    const key = `${P}.status.${status}`;
    const translated = t(key);
    return translated === key ? status : translated;
}

function formatDate(dateStr) {
    if (!dateStr) return null;
    return new Date(dateStr).toLocaleString(dateLocale.value, {
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
            ElMessage.success(t(`${P}.messages.scan_started`));
            stats.last_scan_at = res.data?.started_at;
        }
    } catch {
        ElMessage.error(t(`${P}.messages.scan_failed`));
    } finally {
        scanning.value = false;
    }
}

async function handleMarkFixed(row) {
    try {
        await depsSecurityApi.updateStatus(row.id, { status: 'fixed' });
        ElMessage.success(t(`${P}.messages.marked_fixed`));
        loadVulnerabilities();
        loadStats();
    } catch {
        ElMessage.error(t(`${P}.messages.action_failed`));
    }
}

async function handleIgnore(row) {
    try {
        await depsSecurityApi.updateStatus(row.id, { status: 'ignored' });
        ElMessage.success(t(`${P}.messages.ignored`));
        loadVulnerabilities();
        loadStats();
    } catch {
        ElMessage.error(t(`${P}.messages.action_failed`));
    }
}

async function handleReopen(row) {
    try {
        await depsSecurityApi.updateStatus(row.id, { status: 'open' });
        ElMessage.success(t(`${P}.messages.reopened`));
        loadVulnerabilities();
        loadStats();
    } catch {
        ElMessage.error(t(`${P}.messages.action_failed`));
    }
}

async function handleBatchFix() {
    if (!selectedIds.value.length) return;
    try {
        await ElMessageBox.confirm(t(`${P}.confirm_batch_fix`, { n: selectedIds.value.length }), t(`${P}.confirm_batch_fix_title`), {
            confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info',
        });
        await depsSecurityApi.batchUpdate({ ids: selectedIds.value, status: 'fixed' });
        ElMessage.success(t(`${P}.messages.batch_fixed`));
        selectedIds.value = [];
        loadVulnerabilities();
        loadStats();
    } catch { /* cancelled */ }
}

async function handleBatchIgnore() {
    if (!selectedIds.value.length) return;
    try {
        await ElMessageBox.confirm(t(`${P}.confirm_batch_ignore`, { n: selectedIds.value.length }), t(`${P}.confirm_batch_ignore_title`), {
            confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning',
        });
        await depsSecurityApi.batchUpdate({ ids: selectedIds.value, status: 'ignored' });
        ElMessage.success(t(`${P}.messages.batch_ignored`));
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
.text-primary { color: #0f172a; }
.text-muted { color: #909399; }

.info-row { margin-bottom: 16px; }
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

.package-name { font-weight: 500; font-size: 13px; }
.package-version { font-size: 11px; color: var(--el-text-color-secondary); }

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
