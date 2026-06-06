<template>
    <div class="legal-consent-page">
        <el-tabs v-model="activeTab">
            <!-- 协议管理 -->
            <el-tab-pane label="协议管理" name="manage">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>隐私协议 & 服务条款</span>
                            <el-button type="primary" :icon="Plus" @click="openCreateDialog">
                                新建版本
                            </el-button>
                        </div>
                    </template>

                    <el-table :data="consents" v-loading="loading" stripe style="width: 100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="类型" width="160">
                            <template #default="{ row }">
                                <el-tag :type="row.type === 'privacy_policy' ? 'primary' : 'success'" size="small">
                                    {{ row.type === 'privacy_policy' ? '隐私协议' : '服务条款' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="version" label="版本" width="100" />
                        <el-table-column label="当前版本" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="row.is_current" type="success" size="small" effect="dark">生效中</el-tag>
                                <el-tag v-else type="info" size="small">草稿</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="生效时间" width="180">
                            <template #default="{ row }">
                                {{ row.effective_at ? formatDate(row.effective_at) : '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="viewConsent(row)">查看</el-button>
                                <el-button
                                    v-if="!row.is_current"
                                    size="small"
                                    type="warning"
                                    @click="handlePublish(row)"
                                >
                                    发布
                                </el-button>
                                <el-popconfirm
                                    v-if="!row.is_current"
                                    title="确定删除此版本？"
                                    @confirm="handleDelete(row)"
                                >
                                    <template #reference>
                                        <el-button size="small" type="danger" plain>删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!loading && !consents.length" description="暂无协议版本" />

                    <el-pagination
                        v-if="total > 0"
                        v-model:current-page="currentPage"
                        :page-size="perPage"
                        :total="total"
                        layout="prev, pager, next"
                        class="mt-4 justify-center"
                        @current-change="fetchConsents"
                    />
                </el-card>
            </el-tab-pane>

            <!-- 同意记录 -->
            <el-tab-pane label="同意记录" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>用户协议确认记录</span>
                        </div>
                    </template>

                    <el-table :data="logs" v-loading="loadingLogs" stripe style="width: 100%">
                        <el-table-column label="用户" width="160">
                            <template #default="{ row }">
                                {{ row.user?.name || '-' }}
                                <span class="text-muted">({{ row.user?.email || '-' }})</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="协议类型" width="160">
                            <template #default="{ row }">
                                <el-tag :type="row.legalConsent?.type === 'privacy_policy' ? 'primary' : 'success'" size="small">
                                    {{ row.legalConsent?.type === 'privacy_policy' ? '隐私协议' : '服务条款' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="版本" width="80" prop="legalConsent?.version" />
                        <el-table-column label="确认时间" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.consented_at || row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="IP" width="150" prop="ip_address" />
                    </el-table>

                    <el-empty v-if="!loadingLogs && !logs.length" description="暂无确认记录" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- 创建/编辑对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? '编辑协议' : '新建协议版本'" width="700px">
            <el-form :model="form" label-width="120px">
                <el-form-item label="协议类型">
                    <el-select v-model="form.type" :disabled="isEditing">
                        <el-option label="隐私协议 (Privacy Policy)" value="privacy_policy" />
                        <el-option label="服务条款 (Terms of Service)" value="terms_of_service" />
                    </el-select>
                </el-form-item>
                <el-form-item label="版本号">
                    <el-input v-model="form.version" placeholder="如 1.0.0" :disabled="isEditing" />
                </el-form-item>
                <el-form-item label="生效时间" v-if="!isEditing">
                    <el-date-picker
                        v-model="form.effective_at"
                        type="datetime"
                        placeholder="立即生效"
                        clearable
                        value-format="YYYY-MM-DD HH:mm:ss"
                    />
                </el-form-item>
                <el-form-item label="协议内容">
                    <el-input
                        v-model="form.content"
                        type="textarea"
                        :rows="12"
                        placeholder="支持 Markdown 格式..."
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">
                    {{ isEditing ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 查看详情对话框 -->
        <el-dialog v-model="viewDialogVisible" title="协议详情" width="700px">
            <template v-if="viewingConsent">
                <div class="view-meta">
                    <el-tag :type="viewingConsent.type === 'privacy_policy' ? 'primary' : 'success'" size="small">
                        {{ viewingConsent.type === 'privacy_policy' ? '隐私协议' : '服务条款' }}
                    </el-tag>
                    <span class="ml-2">v{{ viewingConsent.version }}</span>
                    <el-tag v-if="viewingConsent.is_current" type="success" size="small" class="ml-2" effect="dark">生效中</el-tag>
                </div>
                <el-divider />
                <div class="view-content">{{ viewingConsent.content }}</div>
            </template>
            <template #footer>
                <el-button @click="viewDialogVisible = false">关闭</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
    getLegalConsents,
    createLegalConsent,
    updateLegalConsent,
    publishLegalConsent,
    getConsentLogs,
} from '@/api/legal-consent'
import apiClient from '@/api/client'

const activeTab = ref('manage')

// 协议列表
const consents = ref([])
const loading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const perPage = ref(20)
const typeFilter = ref('')

// 同意记录
const logs = ref([])
const loadingLogs = ref(false)

// 对话框
const dialogVisible = ref(false)
const isEditing = ref(false)
const editingId = ref(null)
const saving = ref(false)
const form = ref({
    type: 'privacy_policy',
    version: '',
    content: '',
    effective_at: null,
})

// 查看对话框
const viewDialogVisible = ref(false)
const viewingConsent = ref(null)

async function fetchConsents() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value }
        if (typeFilter.value) params.type = typeFilter.value
        const res = await getLegalConsents(params)
        consents.value = res.data?.data?.data || []
        total.value = res.data?.data?.total || 0
    } catch (e) {
        ElMessage.error('获取协议列表失败')
    } finally {
        loading.value = false
    }
}

async function fetchLogs() {
    loadingLogs.value = true
    try {
        const res = await getConsentLogs({ per_page: 50 })
        logs.value = res.data?.data?.data || []
    } catch (e) {
        ElMessage.error('获取同意记录失败')
    } finally {
        loadingLogs.value = false
    }
}

function openCreateDialog() {
    isEditing.value = false
    editingId.value = null
    form.value = { type: 'privacy_policy', version: '', content: '', effective_at: null }
    dialogVisible.value = true
}

function viewConsent(row) {
    viewingConsent.value = row
    viewDialogVisible.value = true
}

async function handleSave() {
    saving.value = true
    try {
        if (isEditing.value) {
            await updateLegalConsent(editingId.value, { content: form.value.content })
            ElMessage.success('协议已更新')
        } else {
            await createLegalConsent(form.value)
            ElMessage.success('协议版本已创建')
        }
        dialogVisible.value = false
        fetchConsents()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

async function handlePublish(row) {
    try {
        await ElMessageBox.confirm(
            `确定发布 ${row.type === 'privacy_policy' ? '隐私协议' : '服务条款'} v${row.version}？旧版本将自动失效。`,
            '发布确认',
            { confirmButtonText: '确认发布', cancelButtonText: '取消', type: 'warning' },
        )
        await publishLegalConsent(row.id)
        ElMessage.success('协议已发布')
        fetchConsents()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error('发布失败')
        }
    }
}

async function handleDelete(row) {
    try {
        await apiClient.delete(`/legal-consents/${row.id}`)
        ElMessage.success('已删除')
        fetchConsents()
    } catch (e) {
        ElMessage.error('删除失败')
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    })
}

onMounted(() => {
    fetchConsents()
    fetchLogs()
})
</script>

<style scoped>
.legal-consent-page {
    max-width: 1100px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.text-muted {
    color: #999;
    font-size: 12px;
}

.ml-2 {
    margin-left: 8px;
}

.mt-4 {
    margin-top: 16px;
}

.justify-center {
    display: flex;
    justify-content: center;
}

.view-meta {
    margin-bottom: 8px;
    display: flex;
    align-items: center;
}

.view-content {
    white-space: pre-wrap;
    font-size: 14px;
    line-height: 1.8;
    max-height: 60vh;
    overflow-y: auto;
}
</style>
