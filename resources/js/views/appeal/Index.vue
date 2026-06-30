<template>
    <div class="appeal-page">
        <div class="appeal-container">
            <!-- 标题 -->
            <div class="appeal-header">
                <div class="appeal-logo">🛡️</div>
                <h1>账号申诉</h1>
                <p class="appeal-desc">您的账号已被停用，请填写以下信息提交申诉，我们将在 3 个工作日内处理</p>
            </div>

            <!-- 成功状态 -->
            <div v-if="submitted" class="appeal-success">
                <el-result icon="success" title="申诉已提交">
                    <template #sub-title>
                        <p>申诉编号：<strong>#{{ appealId }}</strong></p>
                        <p>提交时间：{{ submitTime }}</p>
                        <p class="text-gray">预计处理时间：3 个工作日</p>
                    </template>
                    <template #extra>
                        <el-button type="primary" @click="checkStatus">查询申诉进度</el-button>
                    </template>
                </el-result>
            </div>

            <!-- 查询状态 -->
            <div v-else-if="showStatus" class="appeal-status-card">
                <el-card>
                    <template #header><span>📋 申诉进度查询</span></template>
                    <div v-if="statusLoading" class="text-center"><el-icon class="is-loading" :size="24"><Loading /></el-icon></div>
                    <div v-else-if="statusResult" class="status-steps">
                        <el-steps :active="statusStepIndex" align-center finish-status="success">
                            <el-step title="已提交" :description="statusResult.appeal?.appealed_at" />
                            <el-step title="审核中" />
                            <el-step :title="statusResult.appeal?.status === 'approved' ? '已通过' : statusResult.appeal?.status === 'rejected' ? '未通过' : '待审核'" />
                        </el-steps>
                        <div class="status-detail" v-if="statusResult.appeal">
                            <el-descriptions :column="1" border size="small" class="mt-4">
                                <el-descriptions-item label="申诉编号">#{{ statusResult.appeal.id }}</el-descriptions-item>
                                <el-descriptions-item label="申诉原因">{{ reasonLabel(statusResult.appeal.reason) }}</el-descriptions-item>
                                <el-descriptions-item label="当前状态">
                                    <el-tag :type="statusTagType(statusResult.appeal.status)">{{ statusLabel(statusResult.appeal.status) }}</el-tag>
                                </el-descriptions-item>
                                <el-descriptions-item label="审核意见" v-if="statusResult.appeal.review_comment">{{ statusResult.appeal.review_comment }}</el-descriptions-item>
                                <el-descriptions-item label="审核时间" v-if="statusResult.appeal.reviewed_at">{{ statusResult.appeal.reviewed_at }}</el-descriptions-item>
                            </el-descriptions>
                        </div>
                        <div v-else class="status-empty">未找到申诉记录</div>
                        <el-button class="mt-4" @click="showStatus = false; submitted = false">返回</el-button>
                    </div>
                </el-card>
                <div class="status-query-form" v-if="!statusResult">
                    <el-input v-model="statusQueryEmail" placeholder="输入申诉时填写的邮箱" size="large" class="mb-3" />
                    <el-button type="primary" size="large" :loading="statusLoading" @click="checkStatus" style="width:100%">查询</el-button>
                </div>
            </div>

            <!-- 申诉表单 -->
            <el-card v-else class="appeal-form-card">
                <el-form :model="form" :rules="rules" ref="formRef" label-position="top" size="large" @submit.prevent="submitAppeal">
                    <el-form-item label="账号邮箱" prop="email">
                        <el-input v-model="form.email" placeholder="请输入您的注册邮箱" />
                    </el-form-item>

                    <el-form-item label="申诉原因" prop="reason">
                        <el-radio-group v-model="form.reason">
                            <el-radio value="misunderstanding">账号被误封</el-radio>
                            <el-radio value="behavior_changed">已改正违规行为</el-radio>
                            <el-radio value="urgent_need">账号内有重要数据急需使用</el-radio>
                            <el-radio value="other">其他原因</el-radio>
                        </el-radio-group>
                    </el-form-item>

                    <el-form-item label="详细说明" prop="explanation">
                        <el-input v-model="form.explanation" type="textarea" :rows="5" maxlength="5000"
                            placeholder="请详细描述您的情况，包括可能的误判原因、已采取的改正措施等" show-word-limit />
                    </el-form-item>

                    <el-form-item label="证明材料（选填）">
                        <el-upload v-model:file-list="fileList" :auto-upload="false" list-type="picture-card"
                            accept="image/*,.pdf" multiple :limit="5" :on-exceed="() => $message.warning('最多上传 5 个文件')">
                            <el-icon><Plus /></el-icon>
                        </el-upload>
                        <div class="form-hint">支持图片和 PDF，最多 5 个文件，每个最大 10MB</div>
                    </el-form-item>

                    <el-form-item label="联系电话（选填）" prop="contact_phone">
                        <el-input v-model="form.contact_phone" placeholder="方便我们与您联系" />
                    </el-form-item>

                    <el-divider />

                    <el-form-item>
                        <el-button type="primary" native-type="submit" :loading="submitting" size="large" style="width:100%">
                            {{ submitting ? '提交中...' : '提交申诉' }}
                        </el-button>
                    </el-form-item>
                </el-form>

                <div class="appeal-footer">
                    <el-button text @click="showStatus = true">已有申诉？查询进度</el-button>
                    <el-button text @click="goLogin">返回登录</el-button>
                </div>
            </el-card>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/utils/request'

const formRef = ref(null)
const form = reactive({
    email: '',
    reason: 'misunderstanding',
    explanation: '',
    contact_phone: '',
})
const fileList = ref([])
const rules = {
    email: [{ required: true, message: '请输入注册邮箱', trigger: 'blur' }, { type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
    reason: [{ required: true, message: '请选择申诉原因', trigger: 'change' }],
}

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

import { computed } from 'vue'

function reasonLabel(val) {
    const map = { misunderstanding: '账号被误封', behavior_changed: '已改正违规行为', urgent_need: '账号内有重要数据', other: '其他原因' }
    return map[val] || val
}
function statusLabel(val) {
    const map = { pending: '待审核', reviewing: '审核中', approved: '已通过 ✅', rejected: '未通过 ❌' }
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
        ElMessage.success('申诉已提交')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败，请稍后重试')
    } finally {
        submitting.value = false
    }
}

async function checkStatus() {
    const email = statusQueryEmail.value || form.email
    if (!email) { ElMessage.warning('请输入邮箱'); return }
    statusLoading.value = true
    statusResult.value = null
    try {
        // 通过提交时的邮箱查询 -- 用提交接口检查
        const res = await apiClient.post('/appeal/submit', { email, reason: 'misunderstanding', _check: true })
        // 实际上需要单独的查询接口，这里先简化
        ElMessage.info('请登录后查看申诉进度')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '查询失败')
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
