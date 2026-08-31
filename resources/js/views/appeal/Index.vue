<template>
    <div class="appeal-page">
        <div class="appeal-container">
            <!-- 标题 -->
            <div class="appeal-header">
                <div class="appeal-logo">🛡️</div>
                <h1>{{ $t('appeal_page.title') }}</h1>
                <p class="appeal-desc">{{ $t('appeal_page.desc') }}</p>
            </div>

            <!-- 成功状态 -->
            <div v-if="submitted" class="appeal-success">
                <el-result icon="success" :title="$t('appeal_page.submitted')">
                    <template #sub-title>
                        <p>{{ $t('appeal_page.appeal_id') }}：<strong>#{{ appealId }}</strong></p>
                        <p>{{ $t('appeal_page.submit_time') }}：{{ submitTime }}</p>
                        <p class="text-gray">{{ $t('appeal_page.eta') }}</p>
                    </template>
                    <template #extra>
                        <el-button type="primary" @click="checkStatus">{{ $t('appeal_page.check_progress') }}</el-button>
                    </template>
                </el-result>
            </div>

            <!-- 查询状态 -->
            <div v-else-if="showStatus" class="appeal-status-card">
                <el-card>
                    <template #header><span>📋 {{ $t('appeal_page.progress_title') }}</span></template>
                    <div v-if="statusLoading" class="text-center"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
                    <div v-else-if="statusResult" class="status-steps">
                        <el-steps :active="statusStepIndex" align-center finish-status="success">
                            <el-step :title="$t('appeal_page.step_submitted')" :description="statusResult.appeal?.appealed_at" />
                            <el-step :title="$t('appeal_page.step_reviewing')" />
                            <el-step :title="statusResult.appeal?.status === 'approved' ? $t('appeal_page.step_approved') : statusResult.appeal?.status === 'rejected' ? $t('appeal_page.step_rejected') : $t('appeal_page.step_pending')" />
                        </el-steps>
                        <div class="status-detail" v-if="statusResult.appeal">
                            <el-descriptions :column="1" border size="small" class="mt-4">
                                <el-descriptions-item :label="$t('appeal_page.appeal_id')">#{{ statusResult.appeal.id }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('appeal_page.reason')">{{ reasonLabel(statusResult.appeal.reason) }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('appeal_page.status')">
                                    <el-tag :type="statusTagType(statusResult.appeal.status)">{{ statusLabel(statusResult.appeal.status) }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item :label="$t('appeal_page.review_comment')" v-if="statusResult.appeal.review_comment">{{ statusResult.appeal.review_comment }}</el-descriptions-item>
                                <el-descriptions-item :label="$t('appeal_page.reviewed_at')" v-if="statusResult.appeal.reviewed_at">{{ statusResult.appeal.reviewed_at }}</el-descriptions-item>
                            </el-descriptions>
                        </div>
                        <div v-else class="status-empty">{{ $t('appeal_page.not_found') }}</div>
                        <el-button class="mt-4" @click="showStatus = false; submitted = false">{{ $t('appeal_page.back') }}</el-button>
                    </div>
                </el-card>
                <div class="status-query-form" v-if="!statusResult">
                    <el-input v-model="statusQueryEmail" :placeholder="$t('appeal_page.email_ph')" size="large" class="mb-3" />
                    <el-button type="primary" size="large" :loading="statusLoading" @click="checkStatus" style="width:100%">{{ $t('appeal_page.query') }}</el-button>
                </div>
            </div>

            <!-- 申诉表单 -->
            <el-card v-else class="appeal-form-card">
                <el-form :model="form" :rules="rules" ref="formRef" label-position="top" size="large" @submit.prevent="submitAppeal">
                    <el-form-item :label="$t('appeal_page.email_label')" prop="email">
                        <el-input v-model="form.email" :placeholder="$t('appeal_page.email_input_ph')" />
                    </el-form-item>

                    <el-form-item :label="$t('appeal_page.reason_label')" prop="reason">
                        <el-radio-group v-model="form.reason">
                            <el-radio value="misunderstanding">{{ $t('appeal_page.reason_misunderstanding') }}</el-radio>
                            <el-radio value="behavior_changed">{{ $t('appeal_page.reason_behavior') }}</el-radio>
                            <el-radio value="urgent_need">{{ $t('appeal_page.reason_urgent') }}</el-radio>
                            <el-radio value="other">{{ $t('appeal_page.reason_other') }}</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item :label="$t('appeal_page.explanation')" prop="explanation">
                        <el-input v-model="form.explanation" type="textarea" :rows="5" maxlength="5000"
                            :placeholder="$t('appeal_page.explanation_ph')" show-word-limit />
                    </el-form-item>

                    <el-form-item :label="$t('appeal_page.evidence')">
                        <el-upload v-model:file-list="fileList" :auto-upload="false" list-type="picture-card"
                            accept="image/*,.pdf" multiple :limit="5" :on-exceed="() => $message.warning($t('appeal_page.evidence_max'))">
                            <el-icon><Plus /></el-icon>
                        </el-upload>
                        <div class="form-hint">{{ $t('appeal_page.evidence_hint') }}</div>
                    </el-form-item>

                    <el-form-item :label="$t('appeal_page.phone')" prop="contact_phone">
                        <el-input v-model="form.contact_phone" :placeholder="$t('appeal_page.phone_ph')" />
                    </el-form-item>

                    <el-divider />

                    <el-form-item>
                        <el-button type="primary" native-type="submit" :loading="submitting" size="large" style="width:100%">
                            {{ submitting ? $t('appeal_page.submitting') : $t('appeal_page.submit') }}
                        </el-button>
                    </el-form-item>
                </el-form>

                <div class="appeal-footer">
                    <el-button text @click="showStatus = true">{{ $t('appeal_page.has_appeal') }}</el-button>
                    <el-button text @click="goLogin">{{ $t('appeal_page.back_login') }}</el-button>
                </div>
            </el-card>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import apiClient from '@/utils/request'

const { t } = useI18n()
const formRef = ref(null)
const form = reactive({
    email: '',
    reason: 'misunderstanding',
    explanation: '',
    contact_phone: '',
})
const fileList = ref([])
const rules = computed(() => ({
    email: [{ required: true, message: t('appeal_page.email_required'), trigger: 'blur' }, { type: 'email', message: t('appeal_page.email_invalid'), trigger: 'blur' }],
    reason: [{ required: true, message: t('appeal_page.reason_required'), trigger: 'change' }],
}))

const submitting = ref(false)
const submitted = ref(false)
const appealId = ref(null)
const submitTime = ref('')

const showStatus = ref(false)
const statusLoading = ref(false)
const statusQueryEmail = ref('')
const statusResult = ref(null)

const statusStepIndex = computed(() => {
    if (!statusResult.value?.appeal) return 0
    const status = statusResult.value.appeal.status
    if (status === 'approved' || status === 'rejected') return 2
    if (status === 'reviewing') return 1
    return 0
})

function reasonLabel(val) {
    const map = {
        misunderstanding: t('appeal_page.reason_misunderstanding'),
        behavior_changed: t('appeal_page.reason_behavior'),
        urgent_need: t('appeal_page.reason_urgent'),
        other: t('appeal_page.reason_other'),
    }
    return map[val] || val
}
function statusLabel(val) {
    const map = {
        pending: t('appeal_page.step_pending'),
        reviewing: t('appeal_page.step_reviewing'),
        approved: t('appeal_page.step_approved'),
        rejected: t('appeal_page.step_rejected'),
    }
    return map[val] || val
}
function statusTagType(val) {
    const map = { pending: 'warning', reviewing: 'info', approved: 'success', rejected: 'danger' }
    return map[val] || 'info'
}

async function submitAppeal() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
        // 如果有文件，先上传
        let attachments = []
        if (fileList.value.length) {
            for (const f of fileList.value) {
                if (f.url) {
                    attachments.push(f.url)
                } else if (f.raw) {
                    const fd = new FormData()
                    fd.append('file', f.raw)
                    try {
                        const upRes = await apiClient.post('/im/upload', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
                        if (upRes.data?.data?.url) attachments.push(upRes.data.data.url)
                    } catch {}
                }
            }
        }

        const res = await apiClient.post('/appeal/submit', {
            email: form.email,
            reason: form.reason,
            explanation: form.explanation,
            attachments: attachments.length ? attachments : undefined,
            contact_phone: form.contact_phone || undefined,
        })
        const data = res.data?.data || {}
        appealId.value = data.appeal_id
        submitTime.value = data.appealed_at
        submitted.value = true
        ElMessage.success(t('appeal_page.submit_ok'))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('appeal_page.submit_fail'))
    } finally {
        submitting.value = false
    }
}

async function checkStatus() {
    const email = statusQueryEmail.value || form.email
    if (!email) { ElMessage.warning(t('appeal_page.email_warn')); return }
    statusLoading.value = true
    statusResult.value = null
    try {
        const res = await apiClient.get('/appeal/lookup', { params: { email } })
        statusResult.value = res.data?.data || {}
        if (!statusResult.value.has_appeal) {
            ElMessage.info(t('appeal_page.lookup_empty'))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('appeal_page.lookup_fail'))
    } finally {
        statusLoading.value = false
    }
}

function goLogin() {
    window.location.href = '/login'
}
</script>

<style scoped>
.appeal-page { min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
.appeal-container { width: 100%; max-width: 580px; }
.appeal-header { text-align: center; color: #fff; margin-bottom: 32px; }
.appeal-logo { font-size: 56px; margin-bottom: 12px; }
.appeal-header h1 { font-size: 28px; font-weight: 700; margin: 0 0 8px; }
.appeal-desc { font-size: 14px; opacity: 0.85; margin: 0; }
.appeal-form-card { border-radius: 12px; }
.appeal-success { background: #fff; border-radius: 12px; padding: 40px; }
.appeal-success .text-gray { color: #909399; }
.appeal-footer { display: flex; justify-content: center; gap: 16px; margin-top: 16px; }
.form-hint { font-size: 12px; color: #909399; margin-top: 6px; }
.mt-4 { margin-top: 16px; }
.mb-3 { margin-bottom: 12px; }
.text-center { text-align: center; }
.status-detail { margin-top: 16px; }
.status-empty { text-align: center; padding: 40px; color: #909399; }
.status-steps { padding: 20px 0; }
.status-query-form { background: #fff; border-radius: 12px; padding: 32px; margin-top: 24px; }
</style>
