<template>
    <div class="forgot-page">
        <div class="forgot-card-wrap">
            <div class="forgot-header">
                <div v-if="branding.logo_url" class="brand-logo-wrap">
                    <img :src="branding.logo_url" class="brand-logo" alt="Logo" />
                </div>
                <div v-else class="brand-icon-wrap">
                    <svg class="brand-icon-svg" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v10a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="forgot-title">{{ $t('auth.forgot_title') }}</h2>
                <p class="forgot-subtitle">{{ $t('auth.forgot_subtitle') }}</p>
            </div>
            <div class="forgot-card-body">
                <div v-if="sent" class="success-state">
                    <div class="success-icon-wrap">
                        <svg class="success-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="success-title">{{ $t('auth.forgot_sent_title') }}</p>
                    <p class="success-desc">{{ $t('auth.forgot_sent_desc') }} <strong>{{ email }}</strong></p>
                    <el-button @click="resetForm" style="margin-top:12px">{{ $t('auth.magic_resend') }}</el-button>
                </div>
                <el-form v-else ref="formRef" :model="form" :rules="rules" label-position="top" @submit.prevent="handleForgotPassword">
                    <el-form-item :label="$t('auth.forgot_email_label')" prop="email">
                        <el-input v-model="form.email" :placeholder="$t('auth.forgot_email_ph')" :prefix-icon="Message" size="large" />
                    </el-form-item>
                    <el-form-item style="margin-top:24px">
                        <el-button type="primary" native-type="submit" :loading="loading" class="submit-btn" size="large">{{ $t('auth.forgot_send') }}</el-button>
                    </el-form-item>
                </el-form>
            </div>
            <div class="back-link">
                <el-button text size="small" @click="$router.push('/login')">{{ $t('auth.back_to_login') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Message } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const router = useRouter()
const { t } = useI18n()
const formRef = ref(null)
const loading = ref(false)
const sent = ref(false)
const email = ref('')

const branding = reactive({
    logo_url: '',
    site_name: t('app_name'),
})

const form = reactive({
    email: '',
})

const rules = computed(() => ({
    email: [
        { required: true, message: t('auth.email_required'), trigger: 'blur' },
        { type: 'email', message: t('auth.email_invalid'), trigger: 'blur' },
    ],
}))

async function loadBranding() {
    try {
        const res = await apiClient.get('/branding')
        if (res.data?.logo_url) branding.logo_url = res.data.logo_url
        if (res.data?.site_name) branding.site_name = res.data.site_name
    } catch {}
}

async function handleForgotPassword() {
    if (!formRef.value) return
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    loading.value = true
    try {
        await apiClient.post('/forgot-password', { email: form.email })
        email.value = form.email
        sent.value = true
        ElMessage.success(t('auth.forgot_sent_toast'))
    } catch (e) {
        const msg = e.response?.data?.message || t('auth.forgot_send_fail')
        ElMessage.error(msg)
    } finally {
        loading.value = false
    }
}

function resetForm() {
    sent.value = false
    form.email = ''
}

onMounted(() => {
    loadBranding()
})
</script>

<style>
.forgot-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    padding: 16px;
}
.forgot-card-wrap {
    width: 100%;
    max-width: 420px;
}
.forgot-header {
    text-align: center;
    margin-bottom: 32px;
}
.brand-logo-wrap {
    margin-bottom: 12px;
}
.brand-logo {
    height: 40px;
    margin: 0 auto;
    object-fit: contain;
}
.brand-icon-wrap {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    box-shadow: 0 4px 10px rgba(99,102,241,0.25);
}
.brand-icon-svg {
    width: 20px;
    height: 20px;
    color: white;
}
.forgot-title {
    font-size: 22px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 6px;
}
.forgot-subtitle {
    font-size: 14px;
    color: rgba(255,255,255,0.8);
    margin: 0;
}
.forgot-card-body {
    background: #fff;
    border-radius: 14px;
    padding: 28px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}
.submit-btn {
    width: 100%;
    height: 42px;
    font-size: 15px;
}
.success-state {
    text-align: center;
    padding: 16px 0;
}
.success-icon-wrap {
    width: 56px;
    height: 56px;
    background: #ecfdf5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
}
.success-icon {
    width: 28px;
    height: 28px;
    color: #10b981;
}
.success-title {
    color: #111827;
    font-weight: 500;
    margin: 0 0 4px;
}
.success-desc {
    font-size: 14px;
    color: #6b7280;
    margin: 0;
}
.back-link {
    text-align: center;
    margin-top: 24px;
}
.forgot-card-body .el-input__wrapper {
    border-radius: 10px;
    box-shadow: 0 0 0 1px #e5e7eb inset;
}
.forgot-card-body .el-input__wrapper:hover {
    box-shadow: 0 0 0 1px #c7d2fe inset;
}
.forgot-card-body .el-input__wrapper.is-focus {
    box-shadow: 0 0 0 1px #6366f1 inset;
}
.forgot-card-body .el-button--primary {
    --el-button-bg-color: #6366f1;
    --el-button-border-color: #6366f1;
    --el-button-hover-bg-color: #4f46e5;
    --el-button-hover-border-color: #4f46e5;
}
</style>
