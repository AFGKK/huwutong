<template>
    <div class="data-anonymization-page">
        <div class="page-header">
            <h2>数据匿名化与账号注销</h2>
            <p class="text-secondary">管理用户数据匿名化请求，执行 GDPR 被遗忘权合规操作</p>
        </div>

        <!-- 统计概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <span class="stat-label">总注销数</span>
                        <span class="stat-value">{{ stats.total_deletions }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <span class="stat-label">已完成</span>
                        <span class="stat-value success">{{ stats.completed_deletions }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <span class="stat-label">近30天</span>
                        <span class="stat-value warning">{{ stats.recent_30_days }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stat-item">
                        <el-button type="danger" plain @click="showAnonymizeDialog = true">
                            <el-icon><Delete /></el-icon> 手动匿名化
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 搜索与筛选 -->
        <el-card class="mb-4">
            <el-form :inline="true" :model="filters" size="default">
                <el-form-item label="搜索用户">
                    <el-input v-model="filters.search" placeholder="姓名/邮箱" clearable @clear="fetchRecords" @keyup.enter="fetchRecords" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部" clearable @change="fetchRecords">
                        <el-option label="待处理" value="pending" />
                        <el-option label="处理中" value="processing" />
                        <el-option label="已完成" value="completed" />
                        <el-option label="失败" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="fetchRecords">查询</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 注销记录列表 -->
        <el-card>
            <el-table :data="records" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="70" />
                <el-table-column label="用户" width="180">
                    <template #default="{ row }">
                        <div v-if="row.user">
                            <div>{{ row.user.name }}</div>
                            <div class="text-secondary small">{{ row.user.email }}</div>
                        </div>
                        <span v-else class="text-secondary">已删除</span>
                    </template>
                </el-table-column>
                <el-table-column prop="type" label="请求类型" width="120">
                    <template #default="{ row }">
                        <el-tag>{{ row.type_label || '删除' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)">{{ row.status_label || row.status }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
                <el-table-column label="处理人" width="150">
                    <template #default="{ row }">
                        <span v-if="row.processor">{{ row.processor.name }}</span>
                        <span v-else class="text-secondary">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="170" />
                <el-table-column prop="completed_at" label="完成时间" width="170" />
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="showDetail(row)">
                            详情
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
        <el-dialog v-model="detailVisible" title="注销详情" width="600px">
            <template v-if="selectedRecord">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="用户" :span="2">{{ selectedRecord.user?.name }} ({{ selectedRecord.user?.email }})</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(selectedRecord.status)">{{ selectedRecord.status_label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="原因">{{ selectedRecord.reason || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="请求类型">{{ selectedRecord.type_label }}</el-descriptions-item>
                    <el-descriptions-item label="处理人">{{ selectedRecord.processor?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ selectedRecord.created_at }}</el-descriptions-item>
                    <el-descriptions-item label="完成时间">{{ selectedRecord.completed_at || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>请求数据</h4>
                <pre class="json-preview">{{ JSON.stringify(selectedRecord.request_data, null, 2) }}</pre>
            </template>
            <template #footer>
                <el-button @click="detailVisible = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 手动匿名化对话框 -->
        <el-dialog v-model="showAnonymizeDialog" title="手动数据匿名化" width="500px">
            <el-form :model="anonymizeForm" label-position="top">
                <el-form-item label="用户ID" required>
                    <el-input-number v-model="anonymizeForm.user_id" :min="1" placeholder="输入要匿名化的用户ID" style="width: 100%" />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="anonymizeForm.notes" type="textarea" :rows="3" placeholder="匿名化原因（可选）" />
                </el-form-item>
            </el-form>
            <div class="warning-text">
                <el-icon><WarningFilled /></el-icon>
                注意：此操作不可逆。执行后将匿名化该用户的所有个人数据。
            </div>
            <template #footer>
                <el-button @click="showAnonymizeDialog = false">取消</el-button>
                <el-button type="danger" :loading="anonymizing" @click="handleAdminAnonymize">
                    确认匿名化
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, WarningFilled } from '@element-plus/icons-vue';
import deletionApi from '@/api/deletion';

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

async function fetchStats() {
    try {
        const res = await deletionApi.getStats();
        if (res.data?.success) {
            Object.assign(stats, res.data.data);
        }
    } catch (e) {
        console.error('获取统计失败', e);
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
        ElMessage.error('获取记录失败');
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
        ElMessage.warning('请输入用户ID');
        return;
    }

    try {
        await ElMessageBox.confirm(
            `确定要对用户 #${anonymizeForm.user_id} 执行数据匿名化？此操作不可逆。`,
            '确认匿名化',
            { confirmButtonText: '确认执行', cancelButtonText: '取消', type: 'warning' }
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
            ElMessage.success(`用户 #${anonymizeForm.user_id} 数据已匿名化`);
            showAnonymizeDialog.value = false;
            anonymizeForm.user_id = undefined;
            anonymizeForm.notes = '';
            fetchStats();
            fetchRecords();
        } else {
            ElMessage.error(res.data?.message || '匿名化失败');
        }
    } catch (e) {
        ElMessage.error('操作失败: ' + (e.response?.data?.message || e.message));
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
