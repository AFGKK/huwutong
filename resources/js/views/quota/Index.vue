<template>
    <div class="quota-page">
        <div class="page-header">
            <div>
                <h2>{{ t('quota_page.title') }}</h2>
                <p class="text-muted">{{ t('quota_page.subtitle') }}</p>
            </div>
            <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('quota_page.refresh') }}</el-button>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('quota_page.stats.total_keys') }}</div>
                    <div class="stat-value">{{ overview.total_keys || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('quota_page.stats.active_keys') }}</div>
                    <div class="stat-value" style="color:#67c23a">{{ overview.active_keys || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label" style="color:#e6a23c">{{ t('quota_page.stats.near_quota') }}</div>
                    <div class="stat-value" style="color:#e6a23c">{{ overview.keys_near_quota || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :xs="12" :sm="6">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t('quota_page.stats.expiring_soon') }}</div>
                    <div class="stat-value" style="color:#f56c6c">{{ overview.keys_expiring_soon || 0 }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 搜索筛选 -->
        <el-card shadow="hover" class="mb-4">
            <el-form :inline="true" @submit.prevent="doSearch">
                <el-form-item :label="t('actions.search')">
                    <el-input v-model="search" :placeholder="t('quota_page.search_placeholder')" clearable style="width:200px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" native-type="submit" :icon="Search">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 密钥配额列表 -->
        <el-card shadow="hover">
            <el-table :data="keys" v-loading="loading" stripe>
                <el-table-column :label="t('quota_page.columns.key_name')" min-width="140" prop="name" />
                <el-table-column :label="t('quota_page.columns.key_id')" width="150">
                    <template #default="{ row }"><code style="font-size:12px">{{ row.key_id }}</code></template>
                </el-table-column>
                <el-table-column :label="t('quota_page.columns.rate_limit')" width="80" align="center">
                    <template #default="{ row }">{{ formatRateLimit(row.rate_limit) }}</template>
                </el-table-column>
                <el-table-column :label="t('quota_page.columns.usage_quota')" min-width="160">
                    <template #default="{ row }">
                        <div v-if="row.usage_quota" class="quota-bar">
                            <el-progress
                                :percentage="Math.min(row.usage_percent || 0, 100)"
                                :status="(row.usage_percent || 0) >= 80 ? 'exception' : 'success'"
                                :stroke-width="16"
                                :text-inside="true"
                            >
                                {{ row.usage_count }}/{{ row.usage_quota }}
                            </el-progress>
                        </div>
                        <span v-else class="text-muted">{{ t('quota_page.unlimited') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('quota_page.columns.daily_quota')" min-width="160">
                    <template #default="{ row }">
                        <div v-if="row.daily_quota" class="quota-bar">
                            <el-progress
                                :percentage="Math.min(row.daily_usage_percent || 0, 100)"
                                :status="(row.daily_usage_percent || 0) >= 80 ? 'exception' : ''"
                                :stroke-width="16"
                                :text-inside="true"
                            >
                                {{ row.daily_usage }}/{{ row.daily_quota }}
                            </el-progress>
                        </div>
                        <span v-else class="text-muted">{{ t('quota_page.unlimited') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('quota_page.columns.status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                            {{ statusLabel(row.is_active) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('quota_page.columns.expires_at')" width="120">
                    <template #default="{ row }">
                        <span v-if="row.expires_at" :class="isExpiring(row.expires_at) ? 'expiry-warning' : ''">
                            {{ fmtDate(row.expires_at) }}
                        </span>
                        <span v-else class="text-muted">{{ t('quota_page.permanent') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('quota_page.columns.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" link type="primary" @click="editQuota(row)">{{ t('quota_page.edit_quota') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!keys.length && !loading" :description="t('quota_page.empty')" :image-size="60" />

            <div class="pagination-wrap" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="loadKeys"
                    @size-change="loadKeys"
                />
            </div>
        </el-card>

        <!-- 编辑配额对话框 -->
        <el-dialog v-model="editVisible" :title="t('quota_page.edit_dialog.title')" width="480px">
            <el-form :model="editForm" label-position="top">
                <el-form-item :label="t('quota_page.edit_dialog.key')">
                    <el-tag>{{ editForm.name }}</el-tag>
                </el-form-item>
                <el-form-item :label="t('quota_page.edit_dialog.rate_limit')">
                    <el-input-number v-model="editForm.rate_limit" :min="1" :max="10000" :step="100" :placeholder="t('quota_page.unlimited')" style="width:100%" />
                    <div class="form-hint">{{ t('quota_page.edit_dialog.rate_limit_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('quota_page.edit_dialog.usage_quota')">
                    <el-input-number v-model="editForm.usage_quota" :min="1" :step="1000" :placeholder="t('quota_page.unlimited')" style="width:100%" />
                    <div class="form-hint">{{ t('quota_page.edit_dialog.usage_quota_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('quota_page.edit_dialog.daily_quota')">
                    <el-input-number v-model="editForm.daily_quota" :min="1" :step="100" :placeholder="t('quota_page.unlimited')" style="width:100%" />
                    <div class="form-hint">{{ t('quota_page.edit_dialog.daily_quota_hint') }}</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="editVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveQuota">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Search } from '@element-plus/icons-vue';
import apiKeyApi from '@/api/apiKey';

const { t, locale } = useI18n();

const loading = ref(false);
const saving = ref(false);
const keys = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const search = ref('');
const editVisible = ref(false);

const overview = reactive({
    total_keys: 0, active_keys: 0, keys_near_quota: 0, keys_expiring_soon: 0, keys_expired: 0,
});

const editForm = reactive({
    id: null, name: '', rate_limit: null, usage_quota: null, daily_quota: null,
});

const statusLabels = computed(() => ({
    true: t('actions.enable'),
    false: t('actions.disable'),
}));

function statusLabel(isActive) {
    return statusLabels.value[isActive] ?? String(isActive);
}

function formatRateLimit(limit) {
    if (!limit) return t('quota_page.unlimited');
    return t('quota_page.rate_per_sec', { n: limit });
}

function fmtDate(time) {
    if (!time) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(time).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
    });
}

function isExpiring(time) {
    if (!time) return false;
    const days = (new Date(time) - new Date()) / (1000 * 60 * 60 * 24);
    return days <= 14 && days > 0;
}

async function loadOverview() {
    try {
        const { data: res } = await apiKeyApi.myOverview();
        const d = res.data || {};
        overview.total_keys = d.total_keys || 0;
        overview.active_keys = d.active_keys || 0;
        overview.keys_near_quota = d.keys_near_quota || 0;
        overview.keys_expiring_soon = d.keys_expiring_soon || 0;
    } catch { /* ignore */ }
}

async function loadKeys() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (search.value) params.search = search.value;
        const { data: res } = await apiKeyApi.list(params);
        keys.value = res.data?.data || res.data || [];
        total.value = res.meta?.total || res.data?.total || keys.value.length;
    } catch {
        keys.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadKeys();
}

function editQuota(row) {
    editForm.id = row.id;
    editForm.name = row.name;
    editForm.rate_limit = row.rate_limit ?? null;
    editForm.usage_quota = row.usage_quota ?? null;
    editForm.daily_quota = row.daily_quota ?? null;
    editVisible.value = true;
}

async function saveQuota() {
    saving.value = true;
    try {
        await apiKeyApi.update(editForm.id, {
            rate_limit: editForm.rate_limit || null,
            usage_quota: editForm.usage_quota || null,
            daily_quota: editForm.daily_quota || null,
        });
        ElMessage.success(t('quota_page.messages.updated'));
        editVisible.value = false;
        loadKeys();
        loadOverview();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('quota_page.messages.save_failed'));
    } finally {
        saving.value = false;
    }
}

async function loadAll() {
    loading.value = true;
    await Promise.all([loadOverview(), loadKeys()]);
    loading.value = false;
}

onMounted(loadAll);
</script>

<style scoped>
.quota-page { padding: 20px; }
.page-header {
    display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;
}
.page-header h2 { margin: 0; font-size: 20px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0; }
.mb-4 { margin-bottom: 16px; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0 0; }
.quota-bar { padding: 2px 0; }
.expiry-warning { color: #e6a23c; font-weight: 500; }
.form-hint { font-size: 12px; color: #909399; margin-top: 4px; }
</style>
