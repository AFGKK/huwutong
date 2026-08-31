<template>
    <div class="license-merge-page">
        <div class="page-header">
            <h2>{{ t('license_merge_page.title') }}</h2>
            <p class="text-muted">{{ t('license_merge_page.subtitle') }}</p>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t('license_merge_page.refresh') }}
                </el-button>
                <el-button type="primary" @click="showMergeDialog = true">
                    <el-icon><CopyDocument /></el-icon> {{ t('license_merge_page.new_merge') }}
                </el-button>
            </div>
        </div>

        <el-card>
            <el-table :data="history" stripe v-loading="loading">
                <el-table-column label="ID" width="60" prop="id" />
                <el-table-column :label="t('licenses_page.col_status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_merge_page.col_source_customer')" min-width="160">
                    <template #default="{ row }">
                        <div>{{ row.source_customer?.user?.name ?? customerFallback(row.source_customer_id) }}</div>
                        <small class="text-muted">{{ t('license_merge_page.customer_id_prefix', { id: row.source_customer_id }) }}</small>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_merge_page.col_target_customer')" min-width="160">
                    <template #default="{ row }">
                        <div>{{ row.target_customer?.user?.name ?? customerFallback(row.target_customer_id) }}</div>
                        <small class="text-muted">{{ t('license_merge_page.customer_id_prefix', { id: row.target_customer_id }) }}</small>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_merge_page.col_license')" width="160" align="center">
                    <template #default="{ row }">
                        <div>{{ t('license_merge_page.migrated_count', { n: row.merged_licenses }) }}</div>
                        <div><small>{{ t('license_merge_page.skipped_count', { n: row.skipped_licenses }) }}</small></div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_merge_page.col_device_migration')" width="100" align="center" prop="migrated_devices" />
                <el-table-column :label="t('license_merge_page.col_operator')" width="120" prop="merged_by?.name" />
                <el-table-column :label="t('license_merge_page.col_time')" width="160">
                    <template #default="{ row }">{{ formatTime(row.merged_at || row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="t('licenses_page.col_actions')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">{{ t('license_merge_page.btn_detail') }}</el-button>
                        <el-button v-if="row.status === 'completed'"
                            size="small" type="warning"
                            @click="confirmRollback(row)">{{ t('license_merge_page.rollback') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination
                    v-if="pagination.total > 0"
                    :current-page="pagination.current_page"
                    :total="pagination.total"
                    :page-size="pagination.per_page"
                    layout="total, prev, pager, next"
                    @current-change="onPageChange"
                />
            </div>
        </el-card>

        <!-- 新建合并弹窗 -->
        <el-dialog v-model="showMergeDialog" :title="t('license_merge_page.merge_dialog.title')" width="650px">
            <el-alert :title="t('license_merge_page.merge_dialog.alert')" type="warning" :closable="false" show-icon class="mb-4" />

            <el-form label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('license_merge_page.merge_dialog.source_customer')" required>
                            <el-select v-model="sourceCustomerId" filterable remote
                                :remote-method="searchSource"
                                :loading="searchingSource"
                                :placeholder="t('license_merge_page.merge_dialog.search_customer_ph')"
                                style="width:100%">
                                <el-option v-for="c in sourceCustomers" :key="c.id"
                                    :label="`#${c.id} ${c.user_name} (${c.user_email})`"
                                    :value="c.id">
                                    <div>#{{ c.id }} {{ c.user_name }}</div>
                                    <small>{{ c.user_email }} · {{ t('license_merge_page.customer_option_license_count', { n: c.license_count }) }}</small>
                                </el-option>
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('license_merge_page.merge_dialog.target_customer')" required>
                            <el-select v-model="targetCustomerId" filterable remote
                                :remote-method="searchTarget"
                                :loading="searchingTarget"
                                :placeholder="t('license_merge_page.merge_dialog.search_customer_ph')"
                                style="width:100%">
                                <el-option v-for="c in targetCustomers" :key="c.id"
                                    :label="`#${c.id} ${c.user_name} (${c.user_email})`"
                                    :value="c.id">
                                    <div>#{{ c.id }} {{ c.user_name }}</div>
                                    <small>{{ c.user_email }} · {{ t('license_merge_page.customer_option_license_count', { n: c.license_count }) }}</small>
                                </el-option>
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="t('license_merge_page.merge_dialog.notes')">
                    <el-input v-model="mergeNotes" type="textarea" :rows="2" :placeholder="t('license_merge_page.merge_dialog.notes_ph')" />
                </el-form-item>
            </el-form>

            <!-- 预览结果 -->
            <template v-if="previewData">
                <el-divider />
                <h4>{{ t('license_merge_page.merge_dialog.preview_title') }}</h4>
                <el-descriptions :column="3" border size="small" class="mb-3">
                    <el-descriptions-item :label="t('license_merge_page.merge_dialog.label_source')">
                        <strong>{{ previewData.source.name }}</strong>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.merge_dialog.label_total_licenses')">
                        <strong>{{ previewData.source.total_licenses }}</strong>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.merge_dialog.label_target')">
                        <strong>{{ previewData.target.name }}</strong>
                    </el-descriptions-item>
                </el-descriptions>

                <el-row :gutter="12" class="mb-3">
                    <el-col :span="8">
                        <el-card shadow="never" class="stat-card success">
                            <div class="stat-label">{{ t('license_merge_page.merge_dialog.stat_to_migrate') }}</div>
                            <div class="stat-value">{{ previewData.summary.to_migrate }}</div>
                            <small>{{ t('license_merge_page.merge_dialog.stat_to_migrate_hint') }}</small>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="stat-card warning">
                            <div class="stat-label">{{ t('license_merge_page.merge_dialog.stat_audit_only') }}</div>
                            <div class="stat-value">{{ previewData.summary.to_retire }}</div>
                            <small>{{ t('license_merge_page.merge_dialog.stat_audit_only_hint') }}</small>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-label">{{ t('license_merge_page.merge_dialog.stat_devices') }}</div>
                            <div class="stat-value">{{ previewData.summary.devices_to_migrate }}</div>
                        </el-card>
                    </el-col>
                </el-row>
            </template>

            <template #footer>
                <el-button @click="showMergeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button v-if="sourceCustomerId && targetCustomerId" @click="doPreview" :loading="previewing">
                    {{ t('license_merge_page.merge_dialog.preview') }}
                </el-button>
                <el-button v-if="previewData" type="danger" @click="doMerge" :loading="merging" :disabled="previewData.summary.to_migrate === 0">
                    {{ t('license_merge_page.merge_dialog.confirm_merge') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" :title="t('license_merge_page.detail_dialog.title')" width="700px">
            <template v-if="detailData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item :label="t('licenses_page.col_status')" :span="2">
                        <el-tag :type="statusType(detailData.status)">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.col_source_customer')">
                        {{ detailData.source_customer?.user?.name ?? customerFallback(detailData.source_customer_id) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.col_target_customer')">
                        {{ detailData.target_customer?.user?.name ?? customerFallback(detailData.target_customer_id) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.col_operator')">{{ detailData.merged_by?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_merged_at')">{{ formatTime(detailData.merged_at) }}</el-descriptions-item>
                </el-descriptions>

                <template v-if="detailData.summary">
                    <el-divider />
                    <h4>{{ t('license_merge_page.detail_dialog.summary_title') }}</h4>
                    <el-descriptions :column="4" border size="small">
                        <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_total_licenses')">{{ detailData.total_licenses }}</el-descriptions-item>
                        <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_merged')">{{ detailData.merged_licenses }}</el-descriptions-item>
                        <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_skipped')">{{ detailData.skipped_licenses }}</el-descriptions-item>
                        <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_failed')">{{ detailData.failed_licenses }}</el-descriptions-item>
                        <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_total_devices')">{{ detailData.total_devices }}</el-descriptions-item>
                        <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_migrated_devices')">{{ detailData.migrated_devices }}</el-descriptions-item>
                    </el-descriptions>
                </template>

                <template v-if="detailData.merge_audit?.length">
                    <el-divider />
                    <h4>{{ t('license_merge_page.detail_dialog.audit_title') }}</h4>
                    <el-table :data="detailData.merge_audit" size="small" max-height="300">
                        <el-table-column prop="action" :label="t('licenses_page.col_actions')" width="140" />
                        <el-table-column prop="license_key" :label="t('licenses_page.license_key')" min-width="160" />
                        <el-table-column prop="status" :label="t('licenses_page.col_status')" width="80" />
                        <el-table-column prop="reason" :label="t('license_merge_page.detail_dialog.col_reason')" min-width="160" show-overflow-tooltip />
                        <el-table-column prop="at" :label="t('license_merge_page.col_time')" width="160" />
                    </el-table>
                </template>

                <template v-if="detailData.errors?.length">
                    <el-divider />
                    <h4>{{ t('license_merge_page.detail_dialog.errors_title') }}</h4>
                    <el-alert v-for="(err, i) in detailData.errors" :key="i" :title="err" type="error" show-icon class="mb-2" />
                </template>

                <template v-if="detailData.notes">
                    <el-divider />
                    <h4>{{ t('license_merge_page.detail_dialog.notes_title') }}</h4>
                    <p>{{ detailData.notes }}</p>
                </template>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseMergeApi from '@/api/licenseMerge';

const { t, locale } = useI18n();

const loading = ref(false);
const previewing = ref(false);
const merging = ref(false);
const searchingSource = ref(false);
const searchingTarget = ref(false);
const showMergeDialog = ref(false);
const showDetailDialog = ref(false);

const history = ref([]);
const detailData = ref(null);
const previewData = ref(null);

const sourceCustomerId = ref(null);
const targetCustomerId = ref(null);
const sourceCustomers = ref([]);
const targetCustomers = ref([]);
const mergeNotes = ref('');

const pagination = reactive({
    current_page: 1, total: 0, per_page: 20,
});

const statusKeys = ['pending', 'previewed', 'completed', 'failed', 'rolled_back'];

const statusLabels = computed(() => Object.fromEntries(
    statusKeys.map((key) => [key, t(`license_merge_page.status.${key}`)]),
));

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function statusType(status) {
    const map = { pending: 'warning', previewed: 'info', completed: 'success', failed: 'danger', rolled_back: 'info' };
    return map[status] || 'info';
}

function customerFallback(id) {
    return t('license_merge_page.customer_fallback', { id });
}

function formatTime(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(dateStr).toLocaleString(loc, { hour12: false });
}

async function loadAll() {
    loading.value = true;
    try {
        const res = await licenseMergeApi.getHistory({ page: pagination.current_page, per_page: pagination.per_page });
        const data = res.data?.data || {};
        history.value = data.data || [];
        pagination.current_page = data.current_page || 1;
        pagination.total = data.total || 0;
    } catch (err) {
        console.error('Failed to load merge history', err);
    } finally {
        loading.value = false;
    }
}

function onPageChange(page) {
    pagination.current_page = page;
    loadAll();
}

async function searchSource(keyword) {
    if (!keyword) return;
    searchingSource.value = true;
    try {
        const res = await licenseMergeApi.searchCustomers(keyword);
        sourceCustomers.value = res.data?.data || [];
    } catch (err) {
        console.error(err);
    } finally {
        searchingSource.value = false;
    }
}

async function searchTarget(keyword) {
    if (!keyword) return;
    searchingTarget.value = true;
    try {
        const res = await licenseMergeApi.searchCustomers(keyword);
        targetCustomers.value = res.data?.data || [];
    } catch (err) {
        console.error(err);
    } finally {
        searchingTarget.value = false;
    }
}

async function doPreview() {
    if (!sourceCustomerId.value || !targetCustomerId.value) {
        ElMessage.warning(t('license_merge_page.messages.select_customers'));
        return;
    }
    if (sourceCustomerId.value === targetCustomerId.value) {
        ElMessage.warning(t('license_merge_page.messages.same_customer'));
        return;
    }

    previewing.value = true;
    previewData.value = null;
    try {
        const res = await licenseMergeApi.preview({
            source_customer_id: sourceCustomerId.value,
            target_customer_id: targetCustomerId.value,
        });
        previewData.value = res.data?.data;
        if (previewData.value?.summary?.to_migrate === 0) {
            ElMessage.warning(t('license_merge_page.messages.no_migratable'));
        } else {
            ElMessage.success(t('license_merge_page.messages.preview_done', { n: previewData.value.summary.to_migrate }));
        }
    } catch (err) {
        console.error('Preview failed', err);
    } finally {
        previewing.value = false;
    }
}

async function doMerge() {
    try {
        await ElMessageBox.confirm(
            t('license_merge_page.messages.merge_confirm', {
                source: previewData.value.source.name,
                count: previewData.value.summary.to_migrate,
                target: previewData.value.target.name,
            }),
            t('license_merge_page.messages.merge_confirm_title'),
            {
                type: 'warning',
                confirmButtonText: t('license_merge_page.merge_dialog.confirm_merge'),
                confirmButtonClass: 'el-button--danger',
                cancelButtonText: t('actions.cancel'),
            },
        );
    } catch {
        return;
    }

    merging.value = true;
    try {
        await licenseMergeApi.execute({
            source_customer_id: sourceCustomerId.value,
            target_customer_id: targetCustomerId.value,
            notes: mergeNotes.value,
        });
        ElMessage.success(t('license_merge_page.messages.merge_success'));
        showMergeDialog.value = false;
        previewData.value = null;
        sourceCustomerId.value = null;
        targetCustomerId.value = null;
        mergeNotes.value = '';
        sourceCustomers.value = [];
        targetCustomers.value = [];
        await loadAll();
    } catch (err) {
        console.error('Merge failed', err);
    } finally {
        merging.value = false;
    }
}

async function viewDetail(row) {
    showDetailDialog.value = true;
    detailData.value = null;
    try {
        const res = await licenseMergeApi.getDetail(row.id);
        detailData.value = res.data?.data;
    } catch (err) {
        console.error('Failed to load detail', err);
    }
}

async function confirmRollback(row) {
    try {
        await ElMessageBox.confirm(
            t('license_merge_page.messages.rollback_confirm', { id: row.id }),
            t('license_merge_page.messages.rollback_confirm_title'),
            {
                type: 'warning',
                confirmButtonText: t('license_merge_page.messages.rollback_confirm_btn'),
                cancelButtonText: t('actions.cancel'),
            },
        );
        await licenseMergeApi.rollback(row.id);
        ElMessage.success(t('license_merge_page.messages.rollback_success'));
        await loadAll();
    } catch (err) {
        // cancelled
    }
}

onMounted(loadAll);
</script>

<style scoped>
.license-merge-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; flex-direction: column; }
.page-header h2 { margin: 0; font-size: 20px; }
.page-header .text-muted { margin: 4px 0 0; color: #909399; font-size: 13px; }
.header-actions { display: flex; gap: 8px; margin-top: 8px; }

.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }

.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }

.stat-card .stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-card .stat-value { font-size: 20px; font-weight: 700; }
.stat-card.success .stat-value { color: #67c23a; }
.stat-card.warning .stat-value { color: #e6a23c; }

.text-muted { color: #909399; font-size: 12px; }
</style>
