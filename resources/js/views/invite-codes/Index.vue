<template>
    <div class="invite-codes-page">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">总计</div>
                        <div class="stat-value">{{ stats.total || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">活跃</div>
                        <div class="stat-value text-success">{{ stats.active || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已过期</div>
                        <div class="stat-value text-warning">{{ stats.expired || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">累计使用</div>
                        <div class="stat-value text-primary">{{ stats.total_uses || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" class="search-form">
                <el-form-item label="状态筛选">
                    <el-select v-model="filterStatus" placeholder="全部" clearable @change="fetchCodes">
                        <el-option label="全部" value="" />
                        <el-option label="活跃" value="active" />
                        <el-option label="已用完" value="exhausted" />
                        <el-option label="已过期" value="expired" />
                        <el-option label="已禁用" value="disabled" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="openGenerateDialog">
                        <el-icon><Plus /></el-icon>生成邀请码
                    </el-button>
                    <el-button @click="fetchCodes" :icon="Refresh">刷新</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 邀请码列表 -->
        <el-card shadow="never">
            <el-table :data="codes" v-loading="loading" stripe style="width: 100%">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column label="邀请码" min-width="180">
                    <template #default="{ row }">
                        <span class="code-text">{{ row.code }}</span>
                        <el-button
                            text
                            size="small"
                            :icon="CopyDocument"
                            @click="copyCode(row.code)"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="最大使用次数" width="120" prop="max_uses" />
                <el-table-column label="已使用" width="80" prop="used_count" />
                <el-table-column label="过期时间" width="180">
                    <template #default="{ row }">
                        {{ row.expires_at ? formatDate(row.expires_at) : '永不过期' }}
                    </template>
                </el-table-column>
                <el-table-column label="备注" min-width="150" prop="remarks" />
                <el-table-column label="创建时间" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-if="!loading && !codes.length" description="暂无邀请码" />

            <el-pagination
                v-if="total > 0"
                v-model:current-page="currentPage"
                :page-size="perPage"
                :total="total"
                layout="prev, pager, next"
                class="mt-4 justify-center"
                @current-change="fetchCodes"
            />
        </el-card>

        <!-- 生成邀请码对话框 -->
        <el-dialog v-model="dialogVisible" title="生成邀请码" width="500px">
            <el-form :model="form" label-width="120px">
                <el-form-item label="生成数量">
                    <el-input-number v-model="form.count" :min="1" :max="100" />
                    <span class="form-help">1~100 个</span>
                </el-form-item>
                <el-form-item label="最大使用次数">
                    <el-input-number v-model="form.max_uses" :min="1" :max="1000" />
                    <span class="form-help">每个邀请码可用次数</span>
                </el-form-item>
                <el-form-item label="过期时间">
                    <el-date-picker
                        v-model="form.expires_at"
                        type="datetime"
                        placeholder="永不过期"
                        clearable
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
                <el-form-item label="备注">
                    <el-input
                        v-model="form.remarks"
                        type="textarea"
                        :rows="3"
                        maxlength="500"
                        show-word-limit
                        placeholder="选填：用于区分不同批次"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleGenerate" :loading="generating">
                    生成
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Refresh, CopyDocument } from '@element-plus/icons-vue'
import { getInviteCodes, generateInviteCodes, getInviteCodeStats } from '@/api/invite-codes'

const codes = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const perPage = ref(20)
const filterStatus = ref('')
const stats = ref({})

// 生成对话框
const dialogVisible = ref(false)
const generating = ref(false)
const form = ref({
    count: 10,
    max_uses: 1,
    expires_at: null,
    remarks: '',
})

async function fetchCodes() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value }
        if (filterStatus.value) params.status = filterStatus.value
        const res = await getInviteCodes(params)
        codes.value = res.data?.data?.data || []
        total.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error('获取邀请码列表失败')
    } finally {
        loading.value = false
    }
}

async function fetchStats() {
    try {
        const res = await getInviteCodeStats()
        stats.value = res.data?.data || {}
    } catch {
        stats.value = {}
    }
}

function openGenerateDialog() {
    form.value = { count: 10, max_uses: 1, expires_at: null, remarks: '' }
    dialogVisible.value = true
}

async function handleGenerate() {
    generating.value = true
    try {
        const res = await generateInviteCodes(form.value.count, {
            max_uses: form.value.max_uses,
            expires_at: form.value.expires_at || undefined,
            remarks: form.value.remarks,
        })
        ElMessage.success(`成功生成 ${form.value.count} 个邀请码`)
        dialogVisible.value = false
        fetchCodes()
        fetchStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '生成失败')
    } finally {
        generating.value = false
    }
}

function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        ElMessage.success('已复制邀请码')
    }).catch(() => {
        ElMessage.warning('复制失败，请手动复制')
    })
}

function statusType(status) {
    const map = { active: 'success', exhausted: 'warning', expired: 'info', disabled: 'danger' }
    return map[status] || 'info'
}

function statusLabel(status) {
    const map = { active: '活跃', exhausted: '已用完', expired: '已过期', disabled: '已禁用' }
    return map[status] || status
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    })
}

onMounted(() => {
    fetchCodes()
    fetchStats()
})
</script>

<style scoped>
.invite-codes-page {
    max-width: 1200px;
    margin: 0 auto;
}

.mb-4 { margin-bottom: 16px; }

.search-form {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

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

.code-text {
    font-family: 'Consolas', 'Monaco', monospace;
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 1px;
}

.form-help {
    color: #999;
    font-size: 12px;
    margin-left: 8px;
}

.mt-4 { margin-top: 16px; }
.justify-center { display: flex; justify-content: center; }
</style>
