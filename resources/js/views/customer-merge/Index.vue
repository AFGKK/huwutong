<template>
    <div class="customer-merge-page">
        <div class="page-header">
            <h2>{{ t('customer_merge_page.title') }}</h2>
            <el-button type="primary" @click="showMergeDialog = true">
                <el-icon><Connection /></el-icon> {{ t('license_merge_page.new_merge') }}
            </el-button>
        </div>

        <!-- 合并历史 -->
        <el-card>
            <template #header><span>{{ t('customer_merge_page.history_title') }}</span></template>
            <el-table :data="historyList" stripe v-loading="loading" @row-click="showDetail">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column :label="t('license_merge_page.col_source_customer')" min-width="180">
                    <template #default="{ row }">
                        <div class="customer-cell">
                            <span class="customer-name">#{{ row.source_customer?.id }}</span>
                            <el-tag size="small" type="info">{{ row.source_customer?.user?.name || '—' }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="→" width="40" align="center">
                    <template #default>
                        <el-icon><ArrowRight /></el-icon>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_merge_page.col_target_customer')" min-width="180">
                    <template #default="{ row }">
                        <div class="customer-cell">
                            <span class="customer-name">#{{ row.target_customer?.id }}</span>
                            <el-tag size="small" type="success">{{ row.target_customer?.user?.name || '—' }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="status" :label="t('customers_page.col_status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('customer_merge_page.col_migration_summary')" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.summary">
                            <span class="summary-chip">{{ t('customer_merge_page.summary_license', { n: row.summary.licenses_moved }) }}</span>
                            <span class="summary-chip">{{ t('customer_merge_page.summary_subscriptions', { n: row.summary.subscriptions_moved }) }}</span>
                            <span class="summary-chip">{{ t('customer_merge_page.summary_invoices', { n: row.summary.invoices_moved }) }}</span>
                        </template>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column prop="merged_by" :label="t('license_merge_page.col_operator')" width="120">
                    <template #default="{ row }">
                        {{ row.merged_by?.name || '—' }}
                    </template>
                </el-table-column>
                <el-table-column prop="merged_at" :label="t('license_merge_page.detail_dialog.label_merged_at')" width="170" />
            </el-table>

            <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next"
                    @current-change="loadHistory"
                />
            </div>
        </el-card>

        <!-- 新建合并对话框 -->
        <el-dialog v-model="showMergeDialog" :title="t('customer_merge_page.merge_dialog.title')" width="700px" :close-on-click-modal="false"
            @close="resetMergeForm">
            <el-form :model="mergeForm" label-width="120px" v-loading="merging">
                <el-form-item :label="t('customer_merge_page.merge_dialog.source_customer')" required>
                    <el-autocomplete
                        v-model="mergeForm.sourceKeyword"
                        :fetch-suggestions="searchSource"
                        :placeholder="t('customer_merge_page.merge_dialog.search_customer_ph')"
                        value-key="user_name"
                        style="width: 100%"
                        @select="(item) => mergeForm.sourceCustomer = item"
                    >
                        <template #default="{ item }">
                            <div class="search-item">
                                <strong>#{{ item.id }}</strong>
                                <span class="ml-2">{{ item.user_name }}</span>
                                <el-tag size="small" class="ml-2">{{ item.user_email }}</el-tag>
                                <el-tag size="small" type="info" class="ml-2">{{ item.level }}</el-tag>
                            </div>
                        </template>
                    </el-autocomplete>
                </el-form-item>
                <el-form-item :label="t('customer_merge_page.merge_dialog.target_customer')" required>
                    <el-autocomplete
                        v-model="mergeForm.targetKeyword"
                        :fetch-suggestions="searchTarget"
                        :placeholder="t('customer_merge_page.merge_dialog.search_customer_ph')"
                        value-key="user_name"
                        style="width: 100%"
                        @select="(item) => mergeForm.targetCustomer = item"
                    >
                        <template #default="{ item }">
                            <div class="search-item">
                                <strong>#{{ item.id }}</strong>
                                <span class="ml-2">{{ item.user_name }}</span>
                                <el-tag size="small" class="ml-2">{{ item.user_email }}</el-tag>
                                <el-tag size="small" type="info" class="ml-2">{{ item.level }}</el-tag>
                            </div>
                        </template>
                    </el-autocomplete>
                </el-form-item>
                <el-form-item :label="t('license_merge_page.merge_dialog.notes')">
                    <el-input v-model="mergeForm.notes" type="textarea" :rows="2" maxlength="500" show-word-limit />
                </el-form-item>
            </el-form>

            <!-- 合并预览 -->
            <div v-if="previewData" class="preview-section">
                <el-divider />
                <h4 class="preview-title">{{ t('customer_merge_page.merge_dialog.preview_title') }}</h4>
                <el-alert type="warning" :title="t('customer_merge_page.merge_dialog.preview_alert')" show-icon :closable="false" class="mb-3" />

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-card size="small" shadow="never" class="preview-card">
                            <template #header>
                                <span class="text-danger">{{ t('customer_merge_page.merge_dialog.source_header', { id: previewData.source.id }) }}</span>
                            </template>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_type') }}:</label> {{ previewData.source.type }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_level') }}:</label> {{ previewData.source.level }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_balance') }}:</label> ¥{{ previewData.source.prepaid_balance }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_license') }}:</label> {{ previewData.affected_records.licenses }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_subscriptions') }}:</label> {{ previewData.affected_records.subscriptions }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_invoices') }}:</label> {{ previewData.affected_records.invoices }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card size="small" shadow="never" class="preview-card">
                            <template #header>
                                <span class="text-success">{{ t('customer_merge_page.merge_dialog.target_header', { id: previewData.target.id }) }}</span>
                            </template>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_type') }}:</label> {{ previewData.target.type }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_level') }}:</label> {{ previewData.target.level }}</div>
                            <div class="preview-field"><label>{{ t('customer_merge_page.merge_dialog.label_balance') }}:</label> ¥{{ previewData.target.prepaid_balance }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 冲突提示 -->
                <div v-if="previewData.conflicts?.length" class="conflict-section mt-3">
                    <el-alert :title="t('customer_merge_page.merge_dialog.conflicts_title', { n: previewData.conflicts.length })" type="warning" show-icon :closable="false">
                        <template #default>
                            <ul class="conflict-list">
                                <li v-for="(c, i) in previewData.conflicts" :key="i">
                                    <strong>{{ fieldLabel(c.field) }}:</strong>
                                    {{ t('customer_merge_page.merge_dialog.conflict_source', { value: c.source }) }}，
                                    {{ t('customer_merge_page.merge_dialog.conflict_target', { value: c.target }) }}
                                    <span class="text-muted">{{ t('customer_merge_page.merge_dialog.conflict_resolution') }}</span>
                                </li>
                            </ul>
                        </template>
                    </el-alert>
                </div>
            </div>

            <template #footer>
                <el-button @click="showMergeDialog = false" :disabled="merging">{{ t('actions.cancel') }}</el-button>
                <el-button v-if="!previewData" @click="previewMerge" :loading="previewing">
                    {{ t('customer_merge_page.merge_dialog.preview_btn') }}
                </el-button>
                <template v-if="previewData">
                    <el-button @click="previewData = null">{{ t('customer_merge_page.merge_dialog.reselect') }}</el-button>
                    <el-button type="danger" @click="executeMerge" :loading="merging"
                        :disabled="!mergeForm.sourceCustomer || !mergeForm.targetCustomer">
                        <el-icon><WarningFilled /></el-icon> {{ t('customer_merge_page.merge_dialog.confirm_merge') }}
                    </el-button>
                </template>
            </template>
        </el-dialog>

        <!-- 合并详情对话框 -->
        <el-dialog v-model="showDetailDialog" :title="t('license_merge_page.detail_dialog.title')" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('customers_page.col_status')">
                        <el-tag :type="statusType(detailData.status)" size="small">
                            {{ statusLabel(detailData.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.detail_dialog.label_merged_at')">{{ detailData.merged_at || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('customer_merge_page.detail_dialog.label_source_customer')">#{{ detailData.source_customer?.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('customer_merge_page.detail_dialog.label_target_customer')">#{{ detailData.target_customer?.id }}</el-descriptions-item>
                    <el-descriptions-item :label="t('license_merge_page.col_operator')">{{ detailData.merged_by?.name || '—' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('customer_merge_page.detail_dialog.label_notes')">{{ detailData.notes || '—' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>{{ t('customer_merge_page.detail_dialog.summary_title') }}</h4>
                <div v-if="detailData.summary" class="summary-grid">
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.licenses_moved }}</div>
                        <div class="summary-label">{{ t('customer_merge_page.detail_dialog.stat_licenses_moved') }}</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.subscriptions_moved }}</div>
                        <div class="summary-label">{{ t('customer_merge_page.detail_dialog.stat_subscriptions_moved') }}</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.invoices_moved }}</div>
                        <div class="summary-label">{{ t('customer_merge_page.detail_dialog.stat_invoices_moved') }}</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">¥{{ detailData.summary.prepaid_balance_added }}</div>
                        <div class="summary-label">{{ t('customer_merge_page.detail_dialog.stat_balance_transferred') }}</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.prepaid_transactions_moved }}</div>
                        <div class="summary-label">{{ t('customer_merge_page.detail_dialog.stat_transactions_moved') }}</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.custom_fields_moved }}</div>
                        <div class="summary-label">{{ t('customer_merge_page.detail_dialog.stat_custom_fields_moved') }}</div>
                    </div>
                </div>

                <div v-if="detailData.errors?.length" class="mt-3">
                    <el-alert :title="t('license_merge_page.detail_dialog.errors_title')" type="error" show-icon :closable="false">
                        <ul><li v-for="(e, i) in detailData.errors" :key="i">{{ e }}</li></ul>
                    </el-alert>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import customerMergeApi from '@/api/customerMerge';

const { t } = useI18n();

const loading = ref(false);
const historyList = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

// 合并对话框
const showMergeDialog = ref(false);
const merging = ref(false);
const previewing = ref(false);
const previewData = ref(null);
const mergeForm = reactive({
    sourceKeyword: '',
    targetKeyword: '',
    sourceCustomer: null,
    targetCustomer: null,
    notes: '',
});

// 详情对话框
const showDetailDialog = ref(false);
const detailData = ref(null);

const statusKeys = ['pending', 'completed', 'failed', 'reversed'];
const fieldKeys = ['type', 'level', 'billing_method', 'user_id'];

const statusLabels = computed(() => Object.fromEntries(
    statusKeys.map((key) => [key, t(`customer_merge_page.status.${key}`)]),
));

const fieldLabels = computed(() => Object.fromEntries(
    fieldKeys.map((key) => [key, t(`customer_merge_page.fields.${key}`)]),
));

function statusType(status) {
    return { pending: 'warning', completed: 'success', failed: 'danger', reversed: 'info' }[status] || 'info';
}
function statusLabel(status) {
    return statusLabels.value[status] || status;
}
function fieldLabel(field) {
    return fieldLabels.value[field] || field;
}

async function loadHistory(page) {
    loading.value = true;
    try {
        const res = await customerMergeApi.getHistory({ page: page || pagination.current_page, per_page: pagination.per_page });
        const data = res.data?.data || {};
        historyList.value = data.data || [];
        pagination.current_page = data.current_page || 1;
        pagination.per_page = data.per_page || 20;
        pagination.total = data.total || 0;
    } catch (err) {
        console.error('Failed to load merge history', err);
    } finally {
        loading.value = false;
    }
}

// 搜索客户
let sourceTimer = null;
let targetTimer = null;

async function searchSource(query, cb) {
    clearTimeout(sourceTimer);
    sourceTimer = setTimeout(async () => {
        if (!query) { cb([]); return; }
        try {
            const res = await customerMergeApi.searchCustomers({ keyword: query });
            cb(res.data?.data || []);
        } catch { cb([]); }
    }, 300);
}

async function searchTarget(query, cb) {
    clearTimeout(targetTimer);
    targetTimer = setTimeout(async () => {
        if (!query) { cb([]); return; }
        try {
            const res = await customerMergeApi.searchCustomers({ keyword: query });
            cb(res.data?.data || []);
        } catch { cb([]); }
    }, 300);
}

// 预览
async function previewMerge() {
    if (!mergeForm.sourceCustomer?.id || !mergeForm.targetCustomer?.id) {
        ElMessage.warning(t('license_merge_page.messages.select_customers'));
        return;
    }
    previewing.value = true;
    previewData.value = null;
    try {
        const res = await customerMergeApi.previewMerge({
            source_customer_id: mergeForm.sourceCustomer.id,
            target_customer_id: mergeForm.targetCustomer.id,
        });
        if (res.data?.data) {
            previewData.value = res.data.data;
        } else {
            ElMessage.error(res.data?.message || t('customer_merge_page.messages.preview_failed'));
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('customer_merge_page.messages.preview_request_failed'));
    } finally {
        previewing.value = false;
    }
}

// 执行合并
async function executeMerge() {
    try {
        await ElMessageBox.confirm(
            t('customer_merge_page.messages.merge_confirm'),
            t('license_merge_page.messages.merge_confirm_title'),
            { confirmButtonText: t('customer_merge_page.merge_dialog.confirm_merge'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
    } catch {
        return;
    }

    merging.value = true;
    try {
        const res = await customerMergeApi.executeMerge({
            source_customer_id: mergeForm.sourceCustomer.id,
            target_customer_id: mergeForm.targetCustomer.id,
            notes: mergeForm.notes,
        });
        ElMessage.success(t('customer_merge_page.messages.merge_success', { n: res.data?.data?.summary?.licenses_moved || 0 }));
        showMergeDialog.value = false;
        await loadHistory(1);
    } catch (err) {
        ElMessage.error(err.response?.data?.message || t('customer_merge_page.messages.merge_failed'));
    } finally {
        merging.value = false;
    }
}

function resetMergeForm() {
    mergeForm.sourceKeyword = '';
    mergeForm.targetKeyword = '';
    mergeForm.sourceCustomer = null;
    mergeForm.targetCustomer = null;
    mergeForm.notes = '';
    previewData.value = null;
}

async function showDetail(row) {
    try {
        const res = await customerMergeApi.getDetail(row.id);
        detailData.value = res.data?.data || row;
        showDetailDialog.value = true;
    } catch {
        detailData.value = row;
        showDetailDialog.value = true;
    }
}

onMounted(() => loadHistory(1));
</script>

<style scoped>
.customer-merge-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; }
.customer-name { font-weight: 600; margin-right: 6px; }
.summary-chip { display: inline-block; font-size: 12px; margin: 1px 3px; padding: 1px 6px; background: #f0f5ff; border-radius: 3px; color: #0f172a; }
.text-muted { color: #c0c4cc; }
.ml-2 { margin-left: 8px; }
.mb-3 { margin-bottom: 12px; }
.mt-3 { margin-top: 12px; }
.text-danger { color: #f56c6c; font-weight: 600; }
.text-success { color: #67c23a; font-weight: 600; }

.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }

.preview-section { margin-top: 10px; }
.preview-title { margin: 0 0 12px; font-size: 16px; }
.preview-card { margin-bottom: 8px; }
.preview-field { font-size: 13px; margin: 3px 0; }
.preview-field label { color: #909399; display: inline-block; width: 80px; }

.conflict-list { margin: 8px 0 0; padding-left: 20px; }
.conflict-list li { font-size: 13px; margin: 4px 0; }

.summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.summary-stat { text-align: center; padding: 12px; background: #f5f7fa; border-radius: 6px; }
.summary-value { font-size: 22px; font-weight: 700; color: #0f172a; }
.summary-label { font-size: 12px; color: #909399; margin-top: 4px; }

.search-item { display: flex; align-items: center; font-size: 13px; }
</style>
