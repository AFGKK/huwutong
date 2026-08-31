<template>
    <div>
        <div class="page-header">
            <div>
                <h2>{{ t('auto_reply_page.title') }}</h2>
                <p class="text-gray-500 text-sm">{{ t('auto_reply_page.subtitle') }}</p>
            </div>
            <el-button type="primary" @click="openAdd">
                <el-icon><Plus /></el-icon> {{ t('auto_reply_page.add_rule') }}
            </el-button>
        </div>

        <!-- 当前状态 -->
        <el-card shadow="never" class="mb-4">
            <div class="status-bar">
                <span class="status-label">{{ t('auto_reply_page.current_status') }}</span>
                <el-tag :type="statusTagType(userStatus.status)" size="large">
                    {{ statusLabel(userStatus.status) }}
                </el-tag>
                <span v-if="userStatus.has_auto_reply" class="status-hint">
                    （{{ t('auto_reply_page.auto_reply_active', { count: userStatus.reply_count }) }}）
                </span>
                <span v-else class="status-hint">{{ t('auto_reply_page.no_rules_configured') }}</span>
            </div>
        </el-card>

        <!-- 规则列表 -->
        <el-card shadow="never">
            <el-table :data="rules" v-loading="loading" stripe style="width:100%">
                <el-table-column :label="t('auto_reply_page.cols.type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="typeTagType(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="keyword" :label="t('auto_reply_page.cols.keyword')" width="140">
                    <template #default="{ row }">{{ row.type === 'keyword' ? row.keyword : '-' }}</template>
                </el-table-column>
                <el-table-column prop="reply_content" :label="t('auto_reply_page.cols.reply_content')" min-width="220">
                    <template #default="{ row }">
                        <div class="reply-preview-text">{{ row.reply_content }}</div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('auto_reply_page.cols.effective_period')" width="160">
                    <template #default="{ row }">
                        <span v-if="row.time_start && row.time_end">
                            {{ t('auto_reply_page.time.range', { start: row.time_start, end: row.time_end }) }}
                        </span>
                        <span v-else-if="row.time_start">{{ t('auto_reply_page.time.from', { time: row.time_start }) }}</span>
                        <span v-else-if="row.time_end">{{ t('auto_reply_page.time.until', { time: row.time_end }) }}</span>
                        <span v-else>{{ t('auto_reply_page.time.all_day') }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('auto_reply_page.cols.expires_at')" width="140">
                    <template #default="{ row }">{{ row.expires_at || '-' }}</template>
                </el-table-column>
                <el-table-column prop="reply_count" :label="t('auto_reply_page.cols.reply_count')" width="70" />
                <el-table-column :label="t('auto_reply_page.cols.active')" width="70">
                    <template #default="{ row }">
                        <el-switch :model-value="row.is_active" @change="toggleActive(row)" size="small" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('auto_reply_page.cols.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button text size="small" type="danger" @click="removeRule(row)">{{ t('actions.delete') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div v-if="!rules.length && !loading" class="empty-state">
                <el-empty :description="t('auto_reply_page.empty')" />
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog
            v-model="showDialog"
            :title="editing ? t('auto_reply_page.edit_rule') : t('auto_reply_page.add_rule')"
            width="520px"
        >
            <el-form :model="form" :rules="formRules" ref="formRef" label-width="100px" size="small">
                <el-form-item :label="t('auto_reply_page.form.type')" prop="type">
                    <el-radio-group v-model="form.type">
                        <el-radio v-for="opt in ruleTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio>
                    </el-radio-group>
                </el-form-item>

                <el-form-item v-if="form.type === 'keyword'" :label="t('auto_reply_page.form.keyword')" prop="keyword">
                    <el-input v-model="form.keyword" :placeholder="t('auto_reply_page.form.keyword_ph')" maxlength="100" />
                    <div class="form-hint">{{ t('auto_reply_page.form.keyword_hint') }}</div>
                </el-form-item>
                <el-form-item v-if="form.type === 'keyword'" :label="t('auto_reply_page.form.match_mode')">
                    <el-select v-model="form.match_mode" style="width:100%">
                        <el-option v-for="opt in matchModeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>

                <el-form-item :label="t('auto_reply_page.form.reply_content')" prop="reply_content">
                    <el-input
                        v-model="form.reply_content"
                        type="textarea"
                        :rows="4"
                        maxlength="500"
                        :placeholder="t('auto_reply_page.form.reply_content_ph')"
                        show-word-limit
                    />
                </el-form-item>

                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="t('auto_reply_page.form.time_start')">
                            <el-time-picker
                                v-model="form.time_start"
                                format="HH:mm"
                                :placeholder="t('auto_reply_page.form.no_limit_ph')"
                                style="width:100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('auto_reply_page.form.time_end')">
                            <el-time-picker
                                v-model="form.time_end"
                                format="HH:mm"
                                :placeholder="t('auto_reply_page.form.no_limit_ph')"
                                style="width:100%"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="t('auto_reply_page.form.expires_at')">
                    <el-date-picker
                        v-model="form.expires_at"
                        type="datetime"
                        :placeholder="t('auto_reply_page.form.never_expire_ph')"
                        style="width:100%"
                    />
                    <div class="form-hint">{{ t('auto_reply_page.form.expires_hint') }}</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="saveRule">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getAutoReplies,
    getAutoReplyStatus,
    createAutoReply,
    updateAutoReply,
    deleteAutoReply,
} from '@/api/autoReply'

const { t } = useI18n()

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

const formRules = computed(() => ({
    reply_content: [{ required: true, message: t('auto_reply_page.validation.reply_content_required'), trigger: 'blur' }],
    keyword: [{ required: true, message: t('auto_reply_page.validation.keyword_required'), trigger: 'blur' }],
}))

const ruleTypeOptions = computed(() => [
    { value: 'away', label: t('auto_reply_page.rule_type.away') },
    { value: 'busy', label: t('auto_reply_page.rule_type.busy') },
    { value: 'vacation', label: t('auto_reply_page.rule_type.vacation') },
    { value: 'keyword', label: t('auto_reply_page.rule_type.keyword') },
])

const matchModeOptions = computed(() => [
    { value: 'contains', label: t('auto_reply_page.form.match_contains') },
    { value: 'exact', label: t('auto_reply_page.form.match_exact') },
    { value: 'regex', label: t('auto_reply_page.form.match_regex') },
])

function labelFromMap(prefix, key) {
    const i18nKey = `${prefix}.${key}`
    const translated = t(i18nKey)
    return translated !== i18nKey ? translated : key
}

function typeLabel(type) { return labelFromMap('auto_reply_page.rule_type', type) }
function typeTagType(type) { return { away: 'warning', busy: 'danger', vacation: 'info', keyword: 'primary' }[type] || 'info' }
function statusLabel(status) { return labelFromMap('auto_reply_page.user_status', status) }
function statusTagType(status) { return { online: 'success', away: 'warning', busy: 'danger', vacation: 'info', offline: 'info' }[status] || 'info' }

async function loadRules() {
    loading.value = true
    try {
        const res = await getAutoReplies()
        rules.value = res.data?.data || []
    } catch { rules.value = [] }
    finally { loading.value = false }
}

async function loadStatus() {
    try {
        const res = await getAutoReplyStatus()
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
            await updateAutoReply(form._id, payload)
            ElMessage.success(t('auto_reply_page.messages.updated'))
        } else {
            await createAutoReply(payload)
            ElMessage.success(t('auto_reply_page.messages.created'))
        }
        showDialog.value = false
        loadRules()
        loadStatus()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('auto_reply_page.messages.save_failed'))
    } finally {
        saving.value = false
    }
}

async function toggleActive(row) {
    try {
        await updateAutoReply(row.id, { is_active: !row.is_active })
        row.is_active = !row.is_active
        ElMessage.success(row.is_active ? t('auto_reply_page.messages.enabled') : t('auto_reply_page.messages.disabled'))
        loadStatus()
    } catch { ElMessage.error(t('messages.failed')) }
}

async function removeRule(row) {
    try {
        await ElMessageBox.confirm(t('auto_reply_page.confirm_delete'), t('actions.confirm'))
        await deleteAutoReply(row.id)
        ElMessage.success(t('auto_reply_page.messages.deleted'))
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
