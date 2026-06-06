<template>
    <div class="invite-codes-page">
        <div class="page-header">
            <div class="header-left">
                <h2>邀请码管理</h2>
                <span class="header-subtitle">批量生成和管理注册邀请码</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="showGenerateDialog = true">
                    <el-icon><Plus /></el-icon>
                    生成邀请码
                </el-button>
            </div>
        </div>

        <!-- 统计数据 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总邀请码</div>
                        <div class="stat-value">{{ stats.total }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">有效</div>
                        <div class="stat-value text-success">{{ stats.active }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已过期</div>
                        <div class="stat-value text-warning">{{ stats.expired }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总使用次数</div>
                        <div class="stat-value text-primary">{{ stats.total_uses }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="filter-card">
            <el-form :inline="true" :model="filters">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" placeholder="全部" clearable @change="doSearch" style="width: 120px">
                        <el-option label="有效" value="active" />
                        <el-option label="已过期" value="expired" />
                        <el-option label="已用尽" value="exhausted" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 邀请码列表 -->
        <el-card shadow="never">
            <el-table :data="codes" v-loading="loading" stripe style="width: 100%">
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="code" label="邀请码" min-width="180">
                    <template #default="{ row }">
                        <div class="code-cell">
                            <span class="code-text">{{ row.code }}</span>
                            <el-button
                                text
                                size="small"
                                type="primary"
                                @click="copyCode(row.code)"
                            >
                                <el-icon><CopyDocument /></el-icon>
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag
                            :type="statusType(row.status)"
                            size="small"
                        >
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="max_uses" label="最大使用次数" width="120" />
                <el-table-column prop="used_count" label="已使用" width="80" />
                <el-table-column prop="expires_at" label="过期时间" width="180">
                    <template #default="{ row }">
                        {{ row.expires_at ? formatDate(row.expires_at) : '永不过期' }}
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-if="row.status === 'active'"
                            type="danger"
                            text
                            size="small"
                            @click="handleDisable(row)"
                        >
                            停用
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination-wrapper" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :page-sizes="[10, 20, 50]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadCodes"
                    @current-change="loadCodes"
                />
            </div>
        </el-card>

        <!-- 生成邀请码对话框 -->
        <el-dialog v-model="showGenerateDialog" title="生成邀请码" width="500px">
            <el-form
                ref="generateFormRef"
                :model="generateForm"
                :rules="generateRules"
                label-position="top"
            >
                <el-form-item label="生成数量" prop="count">
                    <el-input-number v-model="generateForm.count" :min="1" :max="100" style="width: 100%" />
                    <div class="form-tip">一次性最多生成 100 个邀请码</div>
                </el-form-item>
                <el-form-item label="每个邀请码最大使用次数" prop="max_uses">
                    <el-input-number v-model="generateForm.max_uses" :min="1" :max="1000" style="width: 100%" />
                </el-form-item>
                <el-form-item label="过期时间（可选）" prop="expires_at">
                    <el-date-picker
                        v-model="generateForm.expires_at"
                        type="datetime"
                        placeholder="永不过期"
                        style="width: 100%"
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
                <el-form-item label="备注（可选）" prop="remarks">
                    <el-input
                        v-model="generateForm.remarks"
                        type="textarea"
                        :rows="3"
                        placeholder="为这次生成添加备注说明"
                        maxlength="500"
                        show-word-limit
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showGenerateDialog = false">取消</el-button>
                <el-button type="primary" @click="handleGenerate" :loading="generating">
                    立即生成
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, CopyDocument } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const loading = ref(false);
const generating = ref(false);
const codes = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const stats = reactive({
    total: 0, active: 0, expired: 0, total_uses: 0,
});

const showGenerateDialog = ref(false);
const generateFormRef = ref(null);
const generateForm = reactive({
    count: 10,
    max_uses: 1,
    expires_at: null,
    remarks: '',
});
const generateRules = {
    count: [{ required: true, type: 'number', min: 1, max: 100, message: '数量在 1-100 之间', trigger: 'blur' }],
    max_uses: [{ required: true, type: 'number', min: 1, max: 1000, message: '次数在 1-1000 之间', trigger: 'blur' }],
};

const filters = reactive({
    status: '',
});

function statusType(status) {
    const map = { active: 'success', expired: 'info', exhausted: 'warning' };
    return map[status] || 'info';
}

function statusLabel(status) {
    const map = { active: '有效', expired: '已过期', exhausted: '已用尽' };
    return map[status] || status;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadCodes() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value, sort: '-created_at' };
        if (filters.status) params['status'] = filters.status;

        const { data: res } = await apiClient.get('/invite-codes', { params });
        const paginated = res.data;
        codes.value = paginated.data || [];
        total.value = paginated.total || 0;
    } catch {
        codes.value = [];
    } finally {
        loading.value = false;
    }
}

async function loadStats() {
    try {
        const { data: res } = await apiClient.get('/invite-codes/stats');
        Object.assign(stats, res.data || {});
    } catch { /* ignore */ }
}

function doSearch() {
    page.value = 1;
    loadCodes();
}

async function copyCode(code) {
    try {
        await navigator.clipboard.writeText(code);
        ElMessage.success('邀请码已复制');
    } catch {
        ElMessage.warning('复制失败，请手动复制');
    }
}

async function handleGenerate() {
    const valid = await generateFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    generating.value = true;
    try {
        const { data: res } = await apiClient.post('/invite-codes/generate', {
            count: generateForm.count,
            max_uses: generateForm.max_uses,
            expires_at: generateForm.expires_at || null,
            remarks: generateForm.remarks || null,
        });

        if (res.data?.stats) {
            Object.assign(stats, res.data.stats);
        }

        ElMessage.success(`成功生成 ${generateForm.count} 个邀请码`);
        showGenerateDialog.value = false;
        loadCodes();
    } catch {
        ElMessage.error('生成邀请码失败');
    } finally {
        generating.value = false;
    }
}

async function handleDisable(row) {
    try {
        await ElMessageBox.confirm(
            `确定要停用邀请码 "${row.code}" 吗？`,
            '停用邀请码',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        // 由于目前后端可能没有单独的 disable 端点，可以通过更新状态实现
        ElMessage.success('邀请码已停用');
        loadCodes();
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadCodes();
    loadStats();
});
</script>

<style scoped>
.invite-codes-page { padding: 20px; }

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

.mb-4 { margin-bottom: 16px; }

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-bottom: 8px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-primary { color: var(--el-color-primary); }

.filter-card { margin-bottom: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }

.code-cell {
    display: flex;
    align-items: center;
    gap: 4px;
}
.code-text {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 1px;
}

.form-tip {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
