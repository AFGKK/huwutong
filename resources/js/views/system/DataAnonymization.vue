<template>
    <div class="data-anonymization-page">
        <div class="page-header">
            <h2>{{ t('data_anonymization_page.title') }}</h2>
            <p class="text-secondary">{{ t('data_anonymization_page.subtitle') }}</p>
        </div>

        <!-- 统计概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <span class="stat-label">{{ t('data_anonymization_page.stats.total_deletions') }}</span>
                        <span class="stat-value">{{ stats.total_deletions }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <span class="stat-label">{{ t('data_anonymization_page.stats.completed_deletions') }}</span>
                        <span class="stat-value success">{{ stats.completed_deletions }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <span class="stat-label">{{ t('data_anonymization_page.stats.recent_30_days') }}</span>
                        <span class="stat-value warning">{{ stats.recent_30_days }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <el-button type="danger" plain @click="showAnonymizeDialog = true">
                            <el-icon><Delete /></el-icon> {{ t('data_anonymization_page.manual_anonymize_btn') }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 搜索与筛选 -->
        <el-card class="mb-4">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item :label="t('data_anonymization_page.filter_search_user')">
                    <el-input v-model="filters.search" :placeholder="t('data_anonymization_page.filter_search_ph')" clearable @clear="fetchRecords" @keyup.enter="fetchRecords" />
                </el-form-item>
                <el-form-item :label="t('gdpr_page.requests.col_status')">
                    <el-select v-model="filters.status" :placeholder="t('custom_fields_page.filter_all')" clearable @change="fetchRecords">
                        <el-option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :label="option.label"
                            :value="option.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="fetchRecords">{{ t('actions.search') }}</el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 注销记录列表 -->
        <el-card>
            <el-table :data="records" v-loading="loading" stripe>
                <el-table-column prop="id" :label="t('data_anonymization_page.col_id')" width="70" />
                <el-table-column :label="t('gdpr_page.requests.col_user')" width="180">
                    <template #default="{ row }">
                        <div v-if="row.user">
                            <div>{{ row.user.name }}</div>
                            <div class="text-secondary small">{{ row.user.email }}</div>
                        </div>
                        <span v-else class="text-secondary">{{ t('data_anonymization_page.user_deleted') }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="type" :label="t('data_anonymization_page.col_request_type')" width="120">
                    <template #default="{ row }">
                        <el-tag>{{ row.type_label || t('data_anonymization_page.type_delete_default') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('gdpr_page.requests.col_status')" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)">{{ statusLabel(row.status, row.status_label) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" :label="t('data_anonymization_page.col_reason')" min-width="200" show-overflow-tooltip />
                <el-table-column :label="t('data_anonymization_page.col_processor')" width="150">
                    <template #default="{ row }">
                        <span v-if="row.processor">{{ row.processor.name }}</span>
                        <span v-else class="text-secondary">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('data_anonymization_page.col_created_at')" width="170" />
                <el-table-column prop="completed_at" :label="t('data_anonymization_page.col_completed_at')" width="170" />
                <el-table-column :label="t('gdpr_page.requests.col_actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="showDetail(row)">
                            {{ t('actions.view_details') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="mt-4 flex justify-end" v-if="pagination">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next"
                    @current-change="fetchRecords"
                />
            </div>
        </el-card>

        <!-- 注销详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t('data_anonymization_page.detail_dialog_title')" width="600px">
            <template v-if="selectedRecord">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('gdpr_page.requests.col_user')" :span="2">{{ selectedRecord.user?.name }} ({{ selectedRecord.user?.email }})</el-descriptions-item>
                    <el-descriptions-item :label="t('gdpr_page.requests.col_status')">
                        <el-tag :type="statusType(selectedRecord.status)">{{ statusLabel(selectedRecord.status, selectedRecord.status_label) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('data_anonymization_page.col_reason')">{{ selectedRecord.reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('data_anonymization_page.col_request_type')">{{ selectedRecord.type_label }}</el-descriptions-item>
                    <el-descriptions-item :label="t('data_anonymization_page.col_processor')">{{ selectedRecord.processor?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('data_anonymization_page.col_created_at')">{{ selectedRecord.created_at }}</el-descriptions-item>
                    <el-descriptions-item :label="t('data_anonymization_page.col_completed_at')">{{ selectedRecord.completed_at || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>{{ t('data_anonymization_page.request_data_title') }}</h4>
                <pre class="json-preview">{{ JSON.stringify(selectedRecord.request_data, null, 2) }}</pre>
            </template>
            <template #footer>
                <el-button @click="detailVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 手动匿名化对话框 -->
        <el-dialog v-model="showAnonymizeDialog" :title="t('data_anonymization_page.anonymize_dialog_title')" width="500px">
            <el-form :model="anonymizeForm" label-position="top">
                <el-form-item :label="t('data_anonymization_page.user_id_label')" required>
                    <el-input-number v-model="anonymizeForm.user_id" :min="1" :placeholder="t('data_anonymization_page.user_id_ph')" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('data_anonymization_page.notes_label')">
                    <el-input v-model="anonymizeForm.notes" type="textarea" :rows="3" :placeholder="t('data_anonymization_page.notes_ph')" />
                </el-form-item>
            </el-form>
            <div class="warning-text">
                <el-icon><WarningFilled /></el-icon>
                {{ t('data_anonymization_page.anonymize_warning') }}
            </div>
            <template #footer>
                <el-button @click="showAnonymizeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" :loading="anonymizing" @click="handleAdminAnonymize">
                    {{ t('data_anonymization_page.confirm_anonymize_btn') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, WarningFilled } from '@element-plus/icons-vue';
import deletionApi from '@/api/deletion';

const { t } = useI18n();

const loading = ref(false);
const records = ref([]);
const pagination = ref(null);
const detailVisible = ref(false);
const selectedRecord = ref(null);
const showAnonymizeDialog = ref(false);
const anonymizing = ref(false);

const stats = reactive({
    total_deletions: 0,
    completed_deletions: 0,
    recent_30_days: 0,
    reasons_breakdown: {},
});

const filters = reactive({
    search: '',
    status: '',
});

const anonymizeForm = reactive({
    user_id: undefined,
    notes: '',
});

const statusOptions = computed(() => [
    { label: t('gdpr_page.requests.status_pending'), value: 'pending' },
    { label: t('gdpr_page.requests.status_processing'), value: 'processing' },
    { label: t('gdpr_page.requests.status_completed'), value: 'completed' },
    { label: t('gdpr_page.requests.status_failed'), value: 'failed' },
]);

const statusLabelMap = computed(() => ({
    pending: t('gdpr_page.requests.status_pending'),
    processing: t('gdpr_page.requests.status_processing'),
    completed: t('gdpr_page.requests.status_completed'),
    failed: t('gdpr_page.requests.status_failed'),
    rejected: t('gdpr_page.requests.status_rejected'),
}));

function statusType(status) {
    const map = {
        pending: 'warning',
        processing: 'primary',
        completed: 'success',
        rejected: 'info',
        failed: 'danger',
    };
    return map[status] || 'info';
}

function statusLabel(status, fallback) {
    return statusLabelMap.value[status] || fallback || status;
}

async function fetchStats() {
    try {
        const res = await deletionApi.getStats();
        if (res.data?.success) {
            Object.assign(stats, res.data.data);
        }
    } catch (e) {
        console.error('fetchStats failed', e);
    }
}

async function fetchRecords(page = 1) {
    loading.value = true;
    try {
        const res = await deletionApi.getDeletionRecords({
            page,
            per_page: 20,
            search: filters.search || undefined,
            status: filters.status || undefined,
        });
        if (res.data?.success) {
            records.value = res.data.data.data || [];
            pagination.value = res.data.data;
        }
    } catch (e) {
        ElMessage.error(t('data_anonymization_page.fetch_records_failed'));
    } finally {
        loading.value = false;
    }
}

function resetFilters() {
    filters.search = '';
    filters.status = '';
    fetchRecords();
}

function showDetail(row) {
    selectedRecord.value = row;
    detailVisible.value = true;
}

async function handleAdminAnonymize() {
    if (!anonymizeForm.user_id) {
        ElMessage.warning(t('data_anonymization_page.user_id_required'));
        return;
    }

    try {
        await ElMessageBox.confirm(
            t('data_anonymization_page.confirm_anonymize_body', { user_id: anonymizeForm.user_id }),
            t('data_anonymization_page.confirm_anonymize_title'),
            {
                confirmButtonText: t('data_anonymization_page.confirm_execute'),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
            }
        );
    } catch {
        return;
    }

    anonymizing.value = true;
    try {
        const res = await deletionApi.adminAnonymize({
            user_id: anonymizeForm.user_id,
            notes: anonymizeForm.notes,
        });
        if (res.data?.success) {
            ElMessage.success(t('data_anonymization_page.anonymize_success', { user_id: anonymizeForm.user_id }));
            showAnonymizeDialog.value = false;
            anonymizeForm.user_id = undefined;
            anonymizeForm.notes = '';
            fetchStats();
            fetchRecords();
        } else {
            ElMessage.error(res.data?.message || t('data_anonymization_page.anonymize_failed'));
        }
    } catch (e) {
        ElMessage.error(t('messages.failed') + ': ' + (e.response?.data?.message || e.message));
    } finally {
        anonymizing.value = false;
    }
}

onMounted(() => {
    fetchStats();
    fetchRecords();
});
</script>

<style scoped>
.page-header {
    margin-bottom: 20px;
}
.page-header h2 { margin: 0 0 4px; }
.text-secondary { color: #909399; }
.small { font-size: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px;
}
.stat-label {
    font-size: 13px;
    color: #909399;
    margin-bottom: 8px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }

.json-preview {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 4px;
    font-size: 12px;
    max-height: 300px;
    overflow: auto;
    white-space: pre-wrap;
}

.warning-text {
    color: #e6a23c;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 0;
}
</style>
