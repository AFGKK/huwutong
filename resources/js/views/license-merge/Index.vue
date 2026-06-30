<template>
    <div class="license-merge-page">
        <div class="page-header">
            <h2>License 继承/合并</h2>
            <p class="text-muted">企业收购场景 — 将源客户的 License 批量迁移到目标客户，保留完整审计链</p>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
                <el-button type="primary" @click="showMergeDialog = true">
                    <el-icon><CopyDocument /></el-icon> 新建合并
                </el-button>
            </div>
        </div>

        <el-card>
            <el-table :data="history" stripe v-loading="loading">
                <el-table-column label="ID" width="60" prop="id" />
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="源客户" min-width="160">
                    <template #default="{ row }">
                        <div>{{ row.source_customer?.user?.name ?? '客户#' + row.source_customer_id }}</div>
                        <small class="text-muted">ID: {{ row.source_customer_id }}</small>
                    </template>
                </el-table-column>
                <el-table-column label="目标客户" min-width="160">
                    <template #default="{ row }">
                        <div>{{ row.target_customer?.user?.name ?? '客户#' + row.target_customer_id }}</div>
                        <small class="text-muted">ID: {{ row.target_customer_id }}</small>
                    </template>
                </el-table-column>
                <el-table-column label="License" width="160" align="center">
                    <template #default="{ row }">
                        <div>迁移 {{ row.merged_licenses }}</div>
                        <div><small>跳过 {{ row.skipped_licenses }}</small></div>
                    </template>
                </el-table-column>
                <el-table-column label="设备迁移" width="100" align="center" prop="migrated_devices" />
                <el-table-column label="操作人" width="120" prop="merged_by?.name" />
                <el-table-column label="时间" width="160">
                    <template #default="{ row }">{{ formatTime(row.merged_at || row.created_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'completed'"
                            size="small" type="warning"
                            @click="confirmRollback(row)">回滚</el-button>
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
        <el-dialog v-model="showMergeDialog" title="新建 License 合并" width="650px">
            <el-alert title="此操作将把源客户的 License 批量迁移到目标客户。可迁移的 License（active/suspended）将连带设备一同迁移，过期/撤销的 License 仅记录审计。此操作不可撤回，建议先执行预览。" type="warning" :closable="false" show-icon class="mb-4" />

            <el-form label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="源客户（被收购方）" required>
                            <el-select v-model="sourceCustomerId" filterable remote
                                :remote-method="searchSource"
                                :loading="searchingSource"
                                placeholder="搜索客户名称/ID"
                                style="width:100%">
                                <el-option v-for="c in sourceCustomers" :key="c.id"
                                    :label="`#${c.id} ${c.user_name} (${c.user_email})`"
                                    :value="c.id">
                                    <div>#{{ c.id }} {{ c.user_name }}</div>
                                    <small>{{ c.user_email }} · License: {{ c.license_count }}</small>
                                </el-option>
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="目标客户（收购方）" required>
                            <el-select v-model="targetCustomerId" filterable remote
                                :remote-method="searchTarget"
                                :loading="searchingTarget"
                                placeholder="搜索客户名称/ID"
                                style="width:100%">
                                <el-option v-for="c in targetCustomers" :key="c.id"
                                    :label="`#${c.id} ${c.user_name} (${c.user_email})`"
                                    :value="c.id">
                                    <div>#{{ c.id }} {{ c.user_name }}</div>
                                    <small>{{ c.user_email }} · License: {{ c.license_count }}</small>
                                </el-option>
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="备注">
                    <el-input v-model="mergeNotes" type="textarea" :rows="2" placeholder="合并说明（可选）" />
                </el-form-item>
            </el-form>

            <!-- 预览结果 -->
            <template v-if="previewData">
                <el-divider />
                <h4>合并预览</h4>
                <el-descriptions :column="3" border size="small" class="mb-3">
                    <el-descriptions-item label="源客户">
                        <strong>{{ previewData.source.name }}</strong>
                    </el-descriptions-item>
                    <el-descriptions-item label="总License数">
                        <strong>{{ previewData.source.total_licenses }}</strong>
                    </el-descriptions-item>
                    <el-descriptions-item label="目标客户">
                        <strong>{{ previewData.target.name }}</strong>
                    </el-descriptions-item>
                </el-descriptions>

                <el-row :gutter="12" class="mb-3">
                    <el-col :span="8">
                        <el-card shadow="never" class="stat-card success">
                            <div class="stat-label">可迁移</div>
                            <div class="stat-value">{{ previewData.summary.to_migrate }}</div>
                            <small>active/suspended 将连带设备迁移</small>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="stat-card warning">
                            <div class="stat-label">仅记录审计</div>
                            <div class="stat-value">{{ previewData.summary.to_retire }}</div>
                            <small>expired/revoked 仅标记合并历史</small>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-label">待迁移设备</div>
                            <div class="stat-value">{{ previewData.summary.devices_to_migrate }}</div>
                        </el-card>
                    </el-col>
                </el-row>
            </template>

            <template #footer>
                <el-button @click="showMergeDialog = false">取消</el-button>
                <el-button v-if="sourceCustomerId && targetCustomerId" @click="doPreview" :loading="previewing">
                    预览
                </el-button>
                <el-button v-if="previewData" type="danger" @click="doMerge" :loading="merging" :disabled="previewData.summary.to_migrate === 0">
                    确认合并
                </el-button>
            </template>
        </el-dialog>

        <!-- 详情弹窗 -->
        <el-dialog v-model="showDetailDialog" title="合并详情" width="700px">
            <template v-if="detailData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item label="状态" :span="2">
                        <el-tag :type="statusType(detailData.status)">{{ statusLabel(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="源客户">
                        {{ detailData.source_customer?.user?.name ?? '#' + detailData.source_customer_id }}
                    </el-descriptions-item>
                    <el-descriptions-item label="目标客户">
                        {{ detailData.target_customer?.user?.name ?? '#' + detailData.target_customer_id }}
                    </el-descriptions-item>
                    <el-descriptions-item label="操作人">{{ detailData.merged_by?.name }}</el-descriptions-item>
                    <el-descriptions-item label="合并时间">{{ formatTime(detailData.merged_at) }}</el-descriptions-item>
                </el-descriptions>

                <template v-if="detailData.summary">
                    <el-divider />
                    <h4>合并摘要</h4>
                    <el-descriptions :column="4" border size="small">
                        <el-descriptions-item label="总License数">{{ detailData.total_licenses }}</el-descriptions-item>
                        <el-descriptions-item label="已迁移">{{ detailData.merged_licenses }}</el-descriptions-item>
                        <el-descriptions-item label="已跳过">{{ detailData.skipped_licenses }}</el-descriptions-item>
                        <el-descriptions-item label="失败">{{ detailData.failed_licenses }}</el-descriptions-item>
                        <el-descriptions-item label="总设备数">{{ detailData.total_devices }}</el-descriptions-item>
                        <el-descriptions-item label="已迁移设备">{{ detailData.migrated_devices }}</el-descriptions-item>
                    </el-descriptions>
                </template>

                <template v-if="detailData.merge_audit?.length">
                    <el-divider />
                    <h4>审计链</h4>
                    <el-table :data="detailData.merge_audit" size="small" max-height="300">
                        <el-table-column prop="action" label="操作" width="140" />
                        <el-table-column prop="license_key" label="License Key" min-width="160" />
                        <el-table-column prop="status" label="状态" width="80" />
                        <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
                        <el-table-column prop="at" label="时间" width="160" />
                    </el-table>
                </template>

                <template v-if="detailData.errors?.length">
                    <el-divider />
                    <h4>错误</h4>
                    <el-alert v-for="(err, i) in detailData.errors" :key="i" :title="err" type="error" show-icon class="mb-2" />
                </template>

                <template v-if="detailData.notes">
                    <el-divider />
                    <h4>备注</h4>
                    <p>{{ detailData.notes }}</p>
                </template>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseMergeApi from '@/api/licenseMerge';

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

function statusLabel(status) {
    const map = { pending: '待处理', previewed: '已预览', completed: '已完成', failed: '失败', rolled_back: '已回滚' };
    return map[status] || status;
}

function statusType(status) {
    const map = { pending: 'warning', previewed: 'info', completed: 'success', failed: 'danger', rolled_back: 'info' };
    return map[status] || 'info';
}

function formatTime(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN');
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
        ElMessage.warning('请先选择源客户和目标客户');
        return;
    }
    if (sourceCustomerId.value === targetCustomerId.value) {
        ElMessage.warning('源客户和目标客户不能相同');
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
            ElMessage.warning('该客户无可迁移的 License（无 active/suspended 状态的 License）');
        } else {
            ElMessage.success(`预览完成：可迁移 ${previewData.value.summary.to_migrate} 个 License`);
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
            `确认将 ${previewData.value.source.name} 的 ${previewData.value.summary.to_migrate} 个 License 合并到 ${previewData.value.target.name}？此操作不可撤销。`,
            '确认合并',
            { type: 'warning', confirmButtonText: '确认合并', confirmButtonClass: 'el-button--danger' }
        );
    } catch {
        return;
    }

    merging.value = true;
    try {
        const res = await licenseMergeApi.execute({
            source_customer_id: sourceCustomerId.value,
            target_customer_id: targetCustomerId.value,
            notes: mergeNotes.value,
        });
        ElMessage.success('License 合并成功');
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
            `确定回滚合并 #${row.id}？将恢复所有 License 和设备到源客户。`,
            '确认回滚',
            { type: 'warning', confirmButtonText: '确认回滚' }
        );
        await licenseMergeApi.rollback(row.id);
        ElMessage.success('合并已回滚');
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
