<template>
  <div class="register-container">
    <div class="register-card">
      <div class="register-header">
        <div class="logo">
          <div class="logo-icon">{{ $t('auth.brand_mark') }}</div>
          <span class="logo-text">{{ $t('app_name') }}</span>
        </div>
        <h2 class="register-title">{{ $t('auth.register_title') }}</h2>
        <p class="register-desc">{{ $t('auth.register_desc') }}</p>
      </div>

      <el-tabs v-model="registerMode" class="register-tabs">
        <el-tab-pane :label="$t('auth.tab_email_reg')" name="email" />
        <el-tab-pane :label="$t('auth.tab_phone_reg')" name="phone" />
      </el-tabs>

      <!-- 邮箱注册 -->
      <el-form
        v-show="registerMode === 'email'"
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        class="register-form"
        @submit.prevent="handleRegister"
      >
        <el-form-item :label="$t('auth.name')" prop="name">
          <el-input v-model="form.name" :placeholder="$t('auth.name_ph')" :prefix-icon="User" size="large" />
        </el-form-item>

        <el-form-item :label="$t('auth.email')" prop="email">
          <el-input v-model="form.email" :placeholder="$t('auth.email_ph')" :prefix-icon="Message" size="large" />
        </el-form-item>

        <el-form-item :label="$t('auth.phone')" prop="phone">
          <el-input v-model="form.phone" :placeholder="$t('auth.phone_ph')" :prefix-icon="Iphone" size="large" maxlength="11" />
        </el-form-item>

        <el-form-item :label="$t('auth.password')" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            :placeholder="$t('auth.password_set_ph')"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>

        <el-form-item :label="$t('auth.confirm_password')" prop="password_confirmation">
          <el-input
            v-model="form.password_confirmation"
            type="password"
            :placeholder="$t('auth.password_confirm_ph')"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>

        <el-button type="primary" size="large" class="register-btn" :loading="loading" native-type="submit">
          {{ $t('auth.register_cta') }}
        </el-button>
      </el-form>

      <!-- 手机号注册 -->
      <el-form
        v-show="registerMode === 'phone'"
        ref="phoneFormRef"
        :model="phoneForm"
        :rules="phoneRules"
        label-position="top"
        class="register-form"
        @submit.prevent="handlePhoneRegister"
      >
        <el-form-item :label="$t('auth.name')" prop="name">
          <el-input v-model="phoneForm.name" :placeholder="$t('auth.name_ph')" :prefix-icon="User" size="large" />
        </el-form-item>

        <el-form-item :label="$t('auth.phone')" prop="phone">
          <el-input
            v-model="phoneForm.phone"
            :placeholder="$t('auth.phone_required_ph')"
            :prefix-icon="Iphone"
            size="large"
            maxlength="11"
          />
        </el-form-item>

        <el-form-item :label="$t('auth.verify_code')" prop="code">
          <div class="code-row">
            <el-input v-model="phoneForm.code" :placeholder="$t('auth.verify_code_ph')" maxlength="6" size="large" />
            <el-button
              size="large"
              :disabled="phoneCodeCountdown > 0 || phoneCodeSending"
              :loading="phoneCodeSending"
              @click="sendRegisterCode"
            >
              {{ phoneCodeCountdown > 0 ? $t('auth.code_resend_in', { n: phoneCodeCountdown }) : $t('auth.send_verify_code') }}
            </el-button>
          </div>
        </el-form-item>

        <el-form-item :label="$t('auth.password')" prop="password">
          <el-input
            v-model="phoneForm.password"
            type="password"
            :placeholder="$t('auth.password_set_ph')"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>

        <el-form-item :label="$t('auth.confirm_password')" prop="password_confirmation">
          <el-input
            v-model="phoneForm.password_confirmation"
            type="password"
            :placeholder="$t('auth.password_confirm_ph')"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>

        <el-button type="primary" size="large" class="register-btn" :loading="loading" native-type="submit">
          {{ $t('auth.register_cta') }}
        </el-button>
      </el-form>

      <div class="register-footer">
        <span>{{ $t('auth.has_account') }}</span>
        <router-link to="/login" class="login-link">{{ $t('auth.has_account_login') }}</router-link>
      </div>

      <div class="register-meta">
        <span>{{ $t('auth.agree_prefix') }}</span>
        <a href="/terms" class="meta-link">{{ $t('auth.terms') }}</a>
        <span>{{ $t('auth.and') }}</span>
        <a href="/privacy" class="meta-link">{{ $t('auth.privacy') }}</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { User, Message, Iphone, Lock } from '@element-plus/icons-vue';
import authApi from '@/api/auth';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const authStore = useAuthStore();
const formRef = ref(null);
const phoneFormRef = ref(null);
const loading = ref(false);
const registerMode = ref('email');
const phoneCodeSending = ref(false);
const phoneCodeCountdown = ref(0);
let phoneCodeTimer = null;

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
});

const phoneForm = reactive({
  name: '',
  phone: '',
  code: '',
  password: '',
  password_confirmation: '',
});

const validatePassword = (_rule, value, callback) => {
  if (value && value.length < 8) {
    callback(new Error(t('auth.password_min8')));
  } else {
    callback();
  }
};

const validatePasswordConfirm = (formModel) => (_rule, value, callback) => {
  if (value !== formModel.password) {
    callback(new Error(t('auth.password_mismatch')));
  } else {
    callback();
  }
};

const rules = computed(() => ({
  name: [{ required: true, message: t('auth.name_required'), trigger: 'blur' }],
  email: [
    { required: true, message: t('auth.email_required'), trigger: 'blur' },
    { type: 'email', message: t('auth.email_invalid'), trigger: 'blur' },
  ],
  phone: [
    { pattern: /^$|^1[3-9]\d{9}$/, message: t('auth.phone_invalid'), trigger: 'blur' },
  ],
  password: [
    { required: true, message: t('auth.password_set_required'), trigger: 'blur' },
    { validator: validatePassword, trigger: 'blur' },
  ],
  password_confirmation: [
    { required: true, message: t('auth.password_confirm_required'), trigger: 'blur' },
    { validator: validatePasswordConfirm(form), trigger: 'blur' },
  ],
}));

const phoneRules = computed(() => ({
  name: [{ required: true, message: t('auth.name_required'), trigger: 'blur' }],
  phone: [
    { required: true, message: t('auth.phone_required'), trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: t('auth.phone_invalid'), trigger: 'blur' },
  ],
  code: [
    { required: true, message: t('auth.verify_code_required'), trigger: 'blur' },
    { len: 6, message: t('auth.verify_code_len'), trigger: 'blur' },
  ],
  password: [
    { required: true, message: t('auth.password_set_required'), trigger: 'blur' },
    { validator: validatePassword, trigger: 'blur' },
  ],
  password_confirmation: [
    { required: true, message: t('auth.password_confirm_required'), trigger: 'blur' },
    { validator: validatePasswordConfirm(phoneForm), trigger: 'blur' },
  ],
}));

function invitePayload() {
  if (route.query.referral_code) return { invite_code: route.query.referral_code };
  if (route.query.ref) return { invite_code: route.query.ref };
  return {};
}

function startPhoneCodeCountdown(seconds = 60) {
  phoneCodeCountdown.value = seconds;
  if (phoneCodeTimer) clearInterval(phoneCodeTimer);
  phoneCodeTimer = setInterval(() => {
    phoneCodeCountdown.value -= 1;
    if (phoneCodeCountdown.value <= 0) {
      clearInterval(phoneCodeTimer);
      phoneCodeTimer = null;
    }
  }, 1000);
}

async function sendRegisterCode() {
  const phoneOk = await phoneFormRef.value?.validateField('phone').then(() => true).catch(() => false);
  if (!phoneOk) return;

  phoneCodeSending.value = true;
  try {
    await authApi.sendPhoneCode({ phone: phoneForm.phone, scene: 'register' });
    ElMessage.success(t('auth.verify_code_sent'));
    startPhoneCodeCountdown(60);
  } catch (e) {
    ElMessage.error(e?.response?.data?.error?.message || e?.response?.data?.message || t('auth.code_send_fail'));
  } finally {
    phoneCodeSending.value = false;
  }
}

async function handleRegister() {
  if (!formRef.value) return;

  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  loading.value = true;
  try {
    const payload = {
      name: form.name,
      email: form.email,
      phone: form.phone || undefined,
      password: form.password,
      password_confirmation: form.password_confirmation,
      ...invitePayload(),
    };
    const response = await authApi.register(payload);

    const data = response.data || response;
    const payloadData = data.data || data;
    if (payloadData.requires_verification) {
      ElMessage.success(t('auth.register_verify_email'));
      setTimeout(() => router.push({ path: '/login', query: { email: form.email, verify: '1' } }), 1200);
      return;
    }
    ElMessage.success(t('auth.register_redirect'));
    if (payloadData.token && payloadData.user) {
      authStore.applySession(payloadData.user, payloadData.token);
    } else if (payloadData.token) {
      localStorage.setItem('auth_token', payloadData.token);
    }
    const redirectTo = route.query.redirect || '/dashboard';
    setTimeout(() => router.push(redirectTo), 1000);
  } catch (err) {
    const errorData = err?.response?.data?.error;
    if (errorData?.details) {
      const firstMsg = Object.values(errorData.details)[0]?.[0] || errorData.message;
      ElMessage.error(firstMsg);
    } else {
      ElMessage.error(errorData?.message || t('auth.register_fail'));
    }
  } finally {
    loading.value = false;
  }
}

async function handlePhoneRegister() {
  if (!phoneFormRef.value) return;
  const valid = await phoneFormRef.value.validate().catch(() => false);
  if (!valid) return;

  loading.value = true;
  try {
    const result = await authStore.phoneRegister({
      name: phoneForm.name,
      phone: phoneForm.phone,
      code: phoneForm.code,
      password: phoneForm.password,
      password_confirmation: phoneForm.password_confirmation,
      ...invitePayload(),
    });
    if (result.ok) {
      const redirectTo = route.query.redirect || '/dashboard';
      setTimeout(() => router.push(redirectTo), 600);
    }
  } finally {
    loading.value = false;
  }
}

onUnmounted(() => {
  if (phoneCodeTimer) clearInterval(phoneCodeTimer);
});
</script>

<style scoped>
.register-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
}

.register-card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  padding: 40px 32px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.register-header {
  text-align: center;
  margin-bottom: 16px;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 24px;
}

.logo-icon {
  width: 36px;
  height: 36px;
  background: #0f172a;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: bold;
  font-size: 16px;
}

.logo-text {
  font-size: 22px;
  font-weight: bold;
  color: #1a1a2e;
}

.register-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0 0 8px;
}

.register-desc {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.register-tabs {
  margin-bottom: 8px;
}

.register-tabs :deep(.el-tabs__header) {
  margin-bottom: 16px;
}

.register-form {
  margin-bottom: 24px;
}

.code-row {
  display: flex;
  gap: 8px;
  width: 100%;
}

.code-row .el-input {
  flex: 1;
}

.register-btn {
  width: 100%;
  margin-top: 8px;
}

.register-footer {
  text-align: center;
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 16px;
}

.login-link {
  color: #0f172a;
  text-decoration: none;
  font-weight: 500;
}

.login-link:hover {
  text-decoration: underline;
}

.register-meta {
  text-align: center;
  font-size: 12px;
  color: #9ca3af;
}

.meta-link {
  color: #0f172a;
  text-decoration: none;
}

.meta-link:hover {
  text-decoration: underline;
}
</style>
