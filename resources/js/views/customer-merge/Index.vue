<template>
    <div class="customer-merge-page">
        <div class="page-header">
            <h2>客户合并</h2>
            <el-button type="primary" @click="showMergeDialog = true">
                <el-icon><Connection /></el-icon> 新建合并
            </el-button>
        </div>

        <!-- 合并历史 -->
        <el-card>
            <template #header><span>合并历史记录</span></template>
            <el-table :data="historyList" stripe v-loading="loading" @row-click="showDetail">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column label="源客户" min-width="180">
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
                <el-table-column label="目标客户" min-width="180">
                    <template #default="{ row }">
                        <div class="customer-cell">
                            <span class="customer-name">#{{ row.target_customer?.id }}</span>
                            <el-tag size="small" type="success">{{ row.target_customer?.user?.name || '—' }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="迁移摘要" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.summary">
                            <span class="summary-chip">License: {{ row.summary.licenses_moved }}</span>
                            <span class="summary-chip">订阅: {{ row.summary.subscriptions_moved }}</span>
                            <span class="summary-chip">发票: {{ row.summary.invoices_moved }}</span>
                        </template>
                        <span v-else class="text-muted">—</span>
                    </template>
                </el-table-column>
                <el-table-column prop="merged_by" label="操作人" width="120">
                    <template #default="{ row }">
                        {{ row.merged_by?.name || '—' }}
                    </template>
                </el-table-column>
                <el-table-column prop="merged_at" label="合并时间" width="170" />
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
        <el-dialog v-model="showMergeDialog" title="新建客户合并" width="700px" :close-on-click-modal="false"
            @close="resetMergeForm">
            <el-form :model="mergeForm" label-width="120px" v-loading="merging">
                <el-form-item label="源客户（被合并）" required>
                    <el-autocomplete
                        v-model="mergeForm.sourceKeyword"
                        :fetch-suggestions="searchSource"
                        placeholder="搜索客户ID、用户名或邮箱"
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
                <el-form-item label="目标客户（主账号）" required>
                    <el-autocomplete
                        v-model="mergeForm.targetKeyword"
                        :fetch-suggestions="searchTarget"
                        placeholder="搜索客户ID、用户名或邮箱"
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
                <el-form-item label="备注">
                    <el-input v-model="mergeForm.notes" type="textarea" :rows="2" maxlength="500" show-word-limit />
                </el-form-item>
            </el-form>

            <!-- 合并预览 -->
            <div v-if="previewData" class="preview-section">
                <el-divider />
                <h4 class="preview-title">合并影响预览</h4>
                <el-alert type="warning" title="此操作不可逆！请仔细核对以下信息" show-icon :closable="false" class="mb-3" />

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-card size="small" shadow="never" class="preview-card">
                            <template #header>
                                <span class="text-danger">源客户 #{{ previewData.source.id }}</span>
                            </template>
                            <div class="preview-field"><label>类型:</label> {{ previewData.source.type }}</div>
                            <div class="preview-field"><label>等级:</label> {{ previewData.source.level }}</div>
                            <div class="preview-field"><label>余额:</label> ¥{{ previewData.source.prepaid_balance }}</div>
                            <div class="preview-field"><label>License:</label> {{ previewData.affected_records.licenses }}</div>
                            <div class="preview-field"><label>订阅:</label> {{ previewData.affected_records.subscriptions }}</div>
                            <div class="preview-field"><label>发票:</label> {{ previewData.affected_records.invoices }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card size="small" shadow="never" class="preview-card">
                            <template #header>
                                <span class="text-success">目标客户 #{{ previewData.target.id }}</span>
                            </template>
                            <div class="preview-field"><label>类型:</label> {{ previewData.target.type }}</div>
                            <div class="preview-field"><label>等级:</label> {{ previewData.target.level }}</div>
                            <div class="preview-field"><label>余额:</label> ¥{{ previewData.target.prepaid_balance }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 冲突提示 -->
                <div v-if="previewData.conflicts?.length" class="conflict-section mt-3">
                    <el-alert :title="`发现 ${previewData.conflicts.length} 项字段冲突`" type="warning" show-icon :closable="false">
                        <template #default>
                            <ul class="conflict-list">
                                <li v-for="(c, i) in previewData.conflicts" :key="i">
                                    <strong>{{ fieldLabel(c.field) }}:</strong>
                                    源={{ c.source }}，目标={{ c.target }}
                                    <span class="text-muted">（将以目标客户为准）</span>
                                </li>
                            </ul>
                        </template>
                    </el-alert>
                </div>
            </div>

            <template #footer>
                <el-button @click="showMergeDialog = false" :disabled="merging">取消</el-button>
                <el-button v-if="!previewData" @click="previewMerge" :loading="previewing">
                    预览合并影响
                </el-button>
                <template v-if="previewData">
                    <el-button @click="previewData = null">重新选择</el-button>
                    <el-button type="danger" @click="executeMerge" :loading="merging"
                        :disabled="!mergeForm.sourceCustomer || !mergeForm.targetCustomer">
                        <el-icon><WarningFilled /></el-icon> 确认合并（不可逆）
                    </el-button>
                </template>
            </template>
        </el-dialog>

        <!-- 合并详情对话框 -->
        <el-dialog v-model="showDetailDialog" title="合并详情" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(detailData.status)" size="small">
                            {{ statusLabel(detailData.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="合并时间">{{ detailData.merged_at || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="源客户">#{{ detailData.source_customer?.id }}</el-descriptions-item>
                    <el-descriptions-item label="目标客户">#{{ detailData.target_customer?.id }}</el-descriptions-item>
                    <el-descriptions-item label="操作人">{{ detailData.merged_by?.name || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="备注">{{ detailData.notes || '—' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>合并汇总</h4>
                <div v-if="detailData.summary" class="summary-grid">
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.licenses_moved }}</div>
                        <div class="summary-label">License 迁移</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.subscriptions_moved }}</div>
                        <div class="summary-label">订阅迁移</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.invoices_moved }}</div>
                        <div class="summary-label">发票迁移</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">¥{{ detailData.summary.prepaid_balance_added }}</div>
                        <div class="summary-label">余额转移</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.prepaid_transactions_moved }}</div>
                        <div class="summary-label">交易记录</div>
                    </div>
                    <div class="summary-stat">
                        <div class="summary-value">{{ detailData.summary.custom_fields_moved }}</div>
                        <div class="summary-label">自定义字段</div>
                    </div>
                </div>

                <div v-if="detailData.errors?.length" class="mt-3">
                    <el-alert title="合并错误" type="error" show-icon :closable="false">
                        <ul><li v-for="(e, i) in detailData.errors" :key="i">{{ e }}</li></ul>
                    </el-alert>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import customerMergeApi from '@/api/customerMerge';

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

function statusType(status) {
    return { pending: 'warning', completed: 'success', failed: 'danger', reversed: 'info' }[status] || 'info';
}
function statusLabel(status) {
    return { pending: '处理中', completed: '已完成', failed: '失败', reversed: '已回滚' }[status] || status;
}
function fieldLabel(field) {
    return { type: '客户类型', level: '客户等级', billing_method: '结算方式', user_id: '关联用户' }[field] || field;
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
        ElMessage.warning('请先选择源客户和目标客户');
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
            ElMessage.error(res.data?.message || '预览失败');
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '预览请求失败');
    } finally {
        previewing.value = false;
    }
}

// 执行合并
async function executeMerge() {
    try {
        await ElMessageBox.confirm(
            '合并操作**不可逆**！源客户将被标记为"已合并"状态，所有License/订阅/发票将转移至目标客户。确定要继续吗？',
            '确认合并',
            { confirmButtonText: '确认合并', cancelButtonText: '取消', type: 'warning', dangerouslyUseHTMLString: true }
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
        ElMessage.success(`合并成功！共迁移 ${res.data?.data?.summary?.licenses_moved || 0} 个License`);
        showMergeDialog.value = false;
        await loadHistory(1);
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '合并失败');
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
.summary-chip { display: inline-block; font-size: 12px; margin: 1px 3px; padding: 1px 6px; background: #f0f5ff; border-radius: 3px; color: #409eff; }
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
.summary-value { font-size: 22px; font-weight: 700; color: #409eff; }
.summary-label { font-size: 12px; color: #909399; margin-top: 4px; }

.search-item { display: flex; align-items: center; font-size: 13px; }
</style>
