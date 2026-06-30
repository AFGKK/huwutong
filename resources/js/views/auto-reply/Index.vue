<template>
    <div>
        <div class="page-header">
            <div>
                <h2>🤖 个人自动回复</h2>
                <p class="text-gray-500 text-sm">设置离开/忙碌/休假时的自动回复消息，以及关键词自动回复</p>
            </div>
            <el-button type="primary" @click="openAdd">
                <el-icon><Plus /></el-icon> 添加规则
            </el-button>
        </div>

        <!-- 当前状态 -->
        <el-card shadow="never" class="mb-4">
            <div class="status-bar">
                <span class="status-label">当前状态：</span>
                <el-tag :type="statusTagType(userStatus.status)" size="large">
                    {{ statusLabel(userStatus.status) }}
                </el-tag>
                <span v-if="userStatus.has_auto_reply" class="status-hint">（已启用自动回复，共 {{ userStatus.reply_count }} 次回复）</span>
                <span v-else class="status-hint">未设置自动回复规则</span>
            </div>
        </el-card>

        <!-- 规则列表 -->
        <el-card shadow="never">
            <el-table :data="rules" v-loading="loading" stripe style="width:100%">
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTagType(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="keyword" label="关键词" width="140">
                    <template #default="{ row }">{{ row.type === 'keyword' ? row.keyword : '-' }}</template>
                </el-table-column>
                <el-table-column prop="reply_content" label="回复内容" min-width="220">
                    <template #default="{ row }">
                        <div class="reply-preview-text">{{ row.reply_content }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="生效时段" width="160">
                    <template #default="{ row }">
                        <span v-if="row.time_start && row.time_end">{{ row.time_start }} ~ {{ row.time_end }}</span>
                        <span v-else-if="row.time_start">从 {{ row.time_start }} 起</span>
                        <span v-else-if="row.time_end">至 {{ row.time_end }} 止</span>
                        <span v-else>全天</span>
                    </template>
                </el-table-column>
                <el-table-column label="过期时间" width="140">
                    <template #default="{ row }">{{ row.expires_at || '-' }}</template>
                </el-table-column>
                <el-table-column prop="reply_count" label="已回复" width="70" />
                <el-table-column label="启用" width="70">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" @change="toggleActive(row)" size="small" />
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="openEdit(row)">编辑</el-button>
                        <el-button text size="small" type="danger" @click="removeRule(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div v-if="!rules.length && !loading" class="empty-state">
                <el-empty description="暂无自动回复规则" />
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="editing ? '编辑规则' : '添加规则'" width="520px">
            <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" size="small">
                <el-form-item label="类型" prop="type">
                    <el-radio-group v-model="form.type">
                        <el-radio value="away">💤 离开</el-radio>
                        <el-radio value="busy">🔴 忙碌</el-radio>
                        <el-radio value="vacation">🏖️ 休假</el-radio>
                        <el-radio value="keyword">🔑 关键词</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item v-if="form.type === 'keyword'" label="关键词" prop="keyword">
                    <el-input v-model="form.keyword" placeholder="触发自动回复的关键词" maxlength="100" />
                    <div class="form-hint">消息包含此关键词时将自动回复</div>
                </el-form-item>
                <el-form-item v-if="form.type === 'keyword'" label="匹配方式">
                    <el-select v-model="form.match_mode" style="width:100%">
                        <el-option label="包含（含关键词即触发）" value="contains" />
                        <el-option label="精确匹配（完全相同）" value="exact" />
                        <el-option label="正则表达式" value="regex" />
                    </el-select>
                </el-form-item>

                <el-form-item label="回复内容" prop="reply_content">
                    <el-input v-model="form.reply_content" type="textarea" :rows="4" maxlength="500" placeholder="输入自动回复内容" show-word-limit />
                </el-form-item>

                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="生效开始">
                            <el-time-picker v-model="form.time_start" format="HH:mm" placeholder="不限制" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="生效结束">
                            <el-time-picker v-model="form.time_end" format="HH:mm" placeholder="不限制" style="width:100%" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="过期时间">
                    <el-date-picker v-model="form.expires_at" type="datetime" placeholder="永不过期" style="width:100%" />
                    <div class="form-hint">设置后到此时间自动停用，适合休假场景</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveRule">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/utils/request'

const rules = ref([])
const loading = ref(false)
const userStatus = reactive({ status: 'online', has_auto_reply: false, reply_count: 0 })

const showDialog = ref(false)
const editing = ref(false)
const saving = ref(false)
const formRef = ref(null)
const form = reactive({
    type: 'away', keyword: '', match_mode: 'contains',
    reply_content: '', time_start: null, time_end: null, expires_at: null,
})
const formRules = {
    reply_content: [{ required: true, message: '请输入回复内容', trigger: 'blur' }],
    keyword: [{ required: true, message: '请输入关键词', trigger: 'blur' }],
}

function typeLabel(t) { return { away: '离开', busy: '忙碌', vacation: '休假', keyword: '关键词' }[t] || t }
function typeTagType(t) { return { away: 'warning', busy: 'danger', vacation: 'info', keyword: 'primary' }[t] || 'info' }
function statusLabel(s) { return { online: '在线', away: '离开', busy: '忙碌', vacation: '休假', offline: '离线' }[s] || s }
function statusTagType(s) { return { online: 'success', away: 'warning', busy: 'danger', vacation: 'info', offline: 'info' }[s] || 'info' }

async function loadRules() {
    loading.value = true
    try {
        const res = await apiClient.get('/user-chat/auto-reply')
        rules.value = res.data?.data || []
    } catch { rules.value = [] }
    finally { loading.value = false }
}

async function loadStatus() {
    try {
        const res = await apiClient.get('/user-chat/auto-reply/status')
        Object.assign(userStatus, res.data?.data || {})
    } catch {}
}

function openAdd() {
    editing.value = false
    form.type = 'away'
    form.keyword = ''
    form.match_mode = 'contains'
    form.reply_content = ''
    form.time_start = null
    form.time_end = null
    form.expires_at = null
    showDialog.value = true
}

function openEdit(row) {
    editing.value = true
    form._id = row.id
    form.type = row.type
    form.keyword = row.keyword || ''
    form.match_mode = row.match_mode || 'contains'
    form.reply_content = row.reply_content
    form.time_start = row.time_start || null
    form.time_end = row.time_end || null
    form.expires_at = row.expires_at || null
    showDialog.value = true
}

async function saveRule() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    saving.value = true
    try {
        const payload = {
            type: form.type,
            keyword: form.type === 'keyword' ? form.keyword : undefined,
            match_mode: form.type === 'keyword' ? form.match_mode : undefined,
            reply_content: form.reply_content,
            time_start: form.time_start || undefined,
            time_end: form.time_end || undefined,
            expires_at: form.expires_at || undefined,
        }
        if (editing.value) {
            await apiClient.put('/user-chat/auto-reply/' + form._id, payload)
            ElMessage.success('已更新')
        } else {
            await apiClient.post('/user-chat/auto-reply', payload)
            ElMessage.success('已创建')
        }
        showDialog.value = false
        loadRules()
        loadStatus()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

async function toggleActive(row) {
    try {
        await apiClient.put('/user-chat/auto-reply/' + row.id, { is_active: !row.is_active })
        row.is_active = !row.is_active
        ElMessage.success(row.is_active ? '已启用' : '已禁用')
        loadStatus()
    } catch { ElMessage.error('操作失败') }
}

async function removeRule(row) {
    try {
        await ElMessageBox.confirm(`确定删除自动回复规则？`, '确认')
        await apiClient.delete('/user-chat/auto-reply/' + row.id)
        ElMessage.success('已删除')
        loadRules()
        loadStatus()
    } catch {}
}

onMounted(() => { loadRules(); loadStatus() })
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.text-gray-500 { color: #909399; }
.text-sm { font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.form-hint { font-size: 12px; color: #909399; margin-top: 4px; }

.status-bar { display: flex; align-items: center; gap: 10px; font-size: 14px; }
.status-label { font-weight: 600; color: #606266; }
.status-hint { font-size: 13px; color: #909399; }

.reply-preview-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.empty-state { padding: 40px 0; }
</style>
