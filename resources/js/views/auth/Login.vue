<template>
  <div class="login-page" :style="bgStyle">
    <div class="login-card" :style="cardStyle">
      <div class="login-header">
        <div class="brand-logo" v-if="branding.logo_url">
          <img :src="branding.logo_url" :alt="branding.brand_name" class="logo-img" />
        </div>
        <el-icon v-else :size="40" :color="branding.primary_color || '#0f172a'"><Key /></el-icon>
        <h2 :style="{ color: branding.text_color || '#303133' }">
          {{ branding.login_page_title || branding.brand_name || $t('auth.login_fallback_title') }}
        </h2>
        <p class="text-gray-400">{{ branding.login_page_subtitle || $t('auth.login_subtitle') }}</p>
      </div>

      <el-tabs v-model="loginMode" class="login-tabs">
        <el-tab-pane :label="$t('auth.tab_password')" name="password" />
        <el-tab-pane :label="$t('auth.tab_phone')" name="phone" />
      </el-tabs>

      <!-- 邮箱密码登录 -->
      <el-form
        v-show="loginMode === 'password'"
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        size="large"
        @keyup.enter="handleLogin"
      >
        <el-form-item :label="$t('auth.email')" prop="email">
          <el-input
            v-model="form.email"
            :placeholder="$t('auth.email_ph')"
            :prefix-icon="UserFilled"
          />
        </el-form-item>

        <el-form-item :label="$t('auth.password')" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            :placeholder="$t('auth.password_ph')"
            show-password
            :prefix-icon="Lock"
          />
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            :loading="authStore.loading"
            class="w-full"
            size="large"
            :style="{ borderRadius: branding.button_radius || '4px' }"
            @click="handleLogin"
          >
            {{ authStore.loading ? $t('auth.logging_in') : $t('auth.login_btn') }}
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 手机验证码登录 -->
      <el-form
        v-show="loginMode === 'phone'"
        ref="phoneFormRef"
        :model="phoneForm"
        :rules="phoneRules"
        label-position="top"
        size="large"
        @keyup.enter="handlePhoneLogin"
      >
        <el-form-item :label="$t('auth.phone')" prop="phone">
          <el-input
            v-model="phoneForm.phone"
            :placeholder="$t('auth.phone_required_ph')"
            :prefix-icon="Iphone"
            maxlength="11"
          />
        </el-form-item>

        <el-form-item :label="$t('auth.verify_code')" prop="code">
          <div class="code-row">
            <el-input
              v-model="phoneForm.code"
              :placeholder="$t('auth.verify_code_ph')"
              maxlength="6"
            />
            <el-button
              :disabled="phoneCodeCountdown > 0 || phoneCodeSending"
              :loading="phoneCodeSending"
              @click="sendLoginCode"
            >
              {{ phoneCodeCountdown > 0 ? $t('auth.code_resend_in', { n: phoneCodeCountdown }) : $t('auth.send_verify_code') }}
            </el-button>
          </div>
        </el-form-item>

        <el-form-item>
          <el-button
            type="primary"
            :loading="authStore.loading"
            class="w-full"
            size="large"
            :style="{ borderRadius: branding.button_radius || '4px' }"
            @click="handlePhoneLogin"
          >
            {{ authStore.loading ? $t('auth.logging_in') : $t('auth.login_btn') }}
          </el-button>
        </el-form-item>
        <p class="phone-login-hint">{{ $t('auth.phone_login_hint') }}</p>
      </el-form>

      <!-- 分隔线 -->
      <div class="divider"><span class="divider-text">{{ $t('auth.or_other') }}</span></div>

      <!-- 魔法链接 -->
      <div class="alt-login-section">
        <div class="magic-link-row" v-if="!magicLinkSent">
          <el-input v-model="magicEmail" :placeholder="$t('auth.magic_ph')" size="default" class="magic-input" :disabled="magicLoading" />
          <el-button type="primary" plain :loading="magicLoading" size="default" @click="sendMagicLink">{{ $t('auth.magic_send') }}</el-button>
        </div>
        <div v-else class="magic-success">
          <el-icon :size="18" color="#67c23a"><CircleCheck /></el-icon>
          <div class="magic-success-text">
            <span>{{ $t('auth.magic_sent') }} <strong>{{ obfuscateEmail(magicEmail) }}</strong></span>
            <span class="magic-hint">{{ $t('auth.magic_hint') }}</span>
          </div>
          <div class="magic-actions">
            <el-button v-if="magicCountdown > 0" size="small" disabled text>
              {{ $t('auth.magic_resend_in', { n: magicCountdown }) }}
            </el-button>
            <el-button v-else size="small" text type="primary" :loading="magicLoading" @click="sendMagicLink">
              {{ $t('auth.magic_resend') }}
            </el-button>
            <el-button size="small" text @click="magicLinkSent = false; magicEmail = ''; clearMagicTimer()">
              {{ $t('auth.magic_change_email') }}
            </el-button>
          </div>
        </div>
      </div>

      <!-- Passkey / 扫码 / 忘记密码 -->
      <div class="alt-login-row">
        <el-button type="success" plain :disabled="!passkeySupported" size="default" class="flex-1" @click="handlePasskeyLogin">
          <el-icon :size="16" style="margin-right:4px"><Link /></el-icon> Passkey
        </el-button>
        <el-button type="warning" plain size="default" class="flex-1" @click="showQrPanel = !showQrPanel">
          <el-icon :size="16" style="margin-right:4px"><Camera /></el-icon> {{ $t('auth.scan_qr') }}
        </el-button>
        <el-button plain size="default" class="flex-1" @click="$router.push('/forgot-password')">{{ $t('auth.forgot_password') }}</el-button>
      </div>
      <div v-if="!passkeySupported" class="passkey-hint">
        <el-icon :size="12" color="#909399"><Warning /></el-icon>
        <span>{{ $t('auth.passkey_unsupported') }}</span>
      </div>

      <!-- 扫码面板 -->
      <div v-if="showQrPanel" class="qr-panel">
        <div class="qr-placeholder">
          <template v-if="!qrSessionId">
            <el-icon :size="48" color="#909399"><Camera /></el-icon>
            <p>{{ $t('auth.qr_hint') }}</p>
            <el-button type="primary" size="small" :loading="qrLoading" @click="createQrSession">{{ $t('auth.qr_generate') }}</el-button>
          </template>
          <template v-else-if="qrImageUrl">
            <img :src="qrImageUrl" class="qr-code-img" :alt="$t('auth.qr_alt')" />
            <p class="text-sm text-gray-500 mt-2">{{ $t('auth.qr_scan_hint') }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $t('auth.qr_ttl') }}</p>
            <div class="flex items-center justify-center gap-2 mt-3">
              <el-icon class="is-loading" :size="16"><Loading /></el-icon>
              <span class="text-sm text-gray-500">{{ $t('auth.qr_waiting') }}</span>
            </div>
            <el-button size="small" class="mt-3" @click="cancelQr">{{ $t('auth.cancel') }}</el-button>
          </template>
          <template v-else>
            <el-icon class="is-loading" :size="24"><Loading /></el-icon>
            <p>{{ $t('auth.qr_generating') }}</p>
          </template>
        </div>
      </div>

      <!-- 社交登录（仅显示已配置的提供商） -->
      <div class="social-login" v-if="availableProviders.length > 0">
        <span class="social-label">{{ $t('auth.social_login') }}</span>
        <div class="social-icons">
          <template v-for="p in availableProviders" :key="p.provider">
            <el-tooltip :content="$t('auth.provider_login', { name: p.name })" placement="top">
              <el-button circle class="social-btn" :style="{ color: p.color, borderColor: p.color }" @click="oauthLogin(p.provider)">
                <span class="social-icon">{{ providerIcon(p.provider) }}</span>
              </el-button>
            </el-tooltip>
          </template>
        </div>
      </div>

      <div class="login-footer">
        <router-link to="/status" class="status-link">{{ $t('auth.status_page') }}</router-link>
        <router-link to="/appeal" class="appeal-link">{{ $t('auth.appeal') }}</router-link>
        <span class="text-gray-400">{{ branding.footer_text || '© 2026 HWT License' }}</span>
      </div>
    </div>

    <!-- 加载品牌配置 -->
    <div v-if="loadingBranding" class="branding-loading">
      <el-icon class="is-loading" :size="24"><Loading /></el-icon>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, computed, onUnmounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '@/stores/auth';
import { UserFilled, Lock, Key, Loading, CircleCheck, Link, Camera, Promotion, Warning, Iphone } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import apiClient from '@/api/client';
import authApi from '@/api/auth';
import QRCode from 'qrcode';

const router = useRouter();
const route = useRoute();
const { t } = useI18n();
const authStore = useAuthStore();
const formRef = ref(null);
const phoneFormRef = ref(null);
const loginMode = ref('password');
const loadingBranding = ref(true);
const magicEmail = ref('');
const magicLoading = ref(false);
const magicLinkSent = ref(false);
const magicCountdown = ref(0);
let magicTimer = null;
const passkeyLoading = ref(false);
const passkeySupported = ref(true);
const showQrPanel = ref(false);
const qrSessionId = ref(null);
const qrImageUrl = ref(null);
const qrLoading = ref(false);
const availableProviders = ref([]);
let qrPollTimer = null;
const phoneCodeSending = ref(false);
const phoneCodeCountdown = ref(0);
let phoneCodeTimer = null;

const branding = reactive({
  brand_name: '',
  primary_color: '#0f172a',
  secondary_color: '#67c23a',
  text_color: '#303133',
  login_page_title: '',
  login_page_subtitle: '',
  login_bg_image: '',
  logo_url: '',
  footer_text: '',
  button_radius: '4px',
});

const form = reactive({
  email: '',
  password: '',
});

const phoneForm = reactive({
  phone: '',
  code: '',
});

const rules = computed(() => ({
  email: [
    { required: true, message: t('auth.email_required'), trigger: 'blur' },
    { type: 'email', message: t('auth.email_invalid'), trigger: 'blur' },
  ],
  password: [
    { required: true, message: t('auth.password_ph'), trigger: 'blur' },
    { min: 6, message: t('auth.password_min'), trigger: 'blur' },
  ],
}));

const phoneRules = computed(() => ({
  phone: [
    { required: true, message: t('auth.phone_required'), trigger: 'blur' },
    { pattern: /^1[3-9]\d{9}$/, message: t('auth.phone_invalid'), trigger: 'blur' },
  ],
  code: [
    { required: true, message: t('auth.verify_code_required'), trigger: 'blur' },
    { len: 6, message: t('auth.verify_code_len'), trigger: 'blur' },
  ],
}));

const bgStyle = computed(() => {
  // 优先使用品牌的背景图片，否则用渐变色
  if (branding.login_bg_image) {
    return {
      backgroundImage: `url(${branding.login_bg_image})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    };
  }
  return {
    background: `linear-gradient(135deg, ${branding.primary_color || '#0f172a'} 0%, ${adjustColor(branding.primary_color || '#1e293b', -30)} 100%)`,
  };
});

const cardStyle = computed(() => ({
  '--brand-primary': branding.primary_color || '#0f172a',
  borderRadius: '12px',
}));

async function loadBranding() {
  loadingBranding.value = true;
  try {
    const { data } = await apiClient.get('/branding', { params: { domain: window.location.hostname } });
    const config = data?.data?.config;
    if (config) {
      branding.brand_name = config.brand_name || t('admin.title');
      branding.primary_color = config.primary_color || '#0f172a';
      branding.secondary_color = config.secondary_color || '#67c23a';
      branding.text_color = config.text_color || '#303133';
      branding.login_page_title = config.login_page_title || '';
      branding.login_page_subtitle = config.login_page_subtitle || '';
      branding.login_bg_image = config.login_bg_image || '';
      branding.logo_url = config.logo_url || '';
      branding.footer_text = config.footer_text || '';
      branding.button_radius = config.button_radius || '4px';

      const cssVars = data?.data?.css_variables;
      if (cssVars) {
        const root = document.documentElement;
        Object.entries(cssVars).forEach(([key, val]) => {
          root.style.setProperty(key, val);
        });
      }

      if (config.brand_name) {
        document.title = `${config.brand_name} - ${t('route_titles.login')}`;
      }

      if (config.favicon_url) {
        const link = document.querySelector('link[rel="icon"]') || document.createElement('link');
        link.rel = 'icon';
        link.href = config.favicon_url;
        document.head.appendChild(link);
      }
    }
  } catch (e) {
    // 使用默认品牌
  } finally {
    loadingBranding.value = false;
  }
}

// 加载已配置的 OAuth 提供商
async function loadOAuthProviders() {
  try {
    const { data: res } = await apiClient.get('/oauth/available-providers');
    availableProviders.value = res.data || [];
  } catch { /* ignore */ }
}

function loginRedirectPath() {
  const redirect = route.query.redirect;
  if (typeof redirect === 'string' && redirect.startsWith('/')) {
    return redirect;
  }
  return '/dashboard';
}

async function handleLogin() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  const success = await authStore.login(form);
  if (success) {
    router.push(loginRedirectPath());
  }
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

async function sendLoginCode() {
  const phoneOk = await phoneFormRef.value?.validateField('phone').then(() => true).catch(() => false);
  if (!phoneOk) return;

  phoneCodeSending.value = true;
  try {
    await authApi.sendPhoneCode({ phone: phoneForm.phone, scene: 'login' });
    ElMessage.success(t('auth.verify_code_sent'));
    startPhoneCodeCountdown(60);
  } catch (e) {
    ElMessage.error(e?.response?.data?.error?.message || e?.response?.data?.message || t('auth.code_send_fail'));
  } finally {
    phoneCodeSending.value = false;
  }
}

async function handlePhoneLogin() {
  const valid = await phoneFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  const success = await authStore.phoneLogin({
    phone: phoneForm.phone,
    code: phoneForm.code,
  });
  if (success) {
    router.push(loginRedirectPath());
  }
}

function adjustColor(hex, amount) {
  if (!hex) return '#1e293b';
  hex = hex.replace('#', '');
  if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
  const num = parseInt(hex, 16);
  const r = Math.min(255, Math.max(0, ((num >> 16) & 0xFF) + amount));
  const g = Math.min(255, Math.max(0, ((num >> 8) & 0xFF) + amount));
  const b = Math.min(255, Math.max(0, (num & 0xFF) + amount));
  return `rgb(${r},${g},${b})`;
}

// ─── 魔法链接 ───
function clearMagicTimer() {
  if (magicTimer) { clearInterval(magicTimer); magicTimer = null; }
  magicCountdown.value = 0;
}
function startMagicCountdown(seconds = 60) {
  clearMagicTimer();
  magicCountdown.value = seconds;
  magicTimer = setInterval(() => {
    magicCountdown.value--;
    if (magicCountdown.value <= 0) { clearMagicTimer(); }
  }, 1000);
}
function obfuscateEmail(email) {
  if (!email || !email.includes('@')) return email;
  const [name, domain] = email.split('@');
  if (name.length <= 2) return `${name[0]}***@${domain}`;
  return `${name[0]}***${name[name.length-1]}@${domain}`;
}
async function sendMagicLink() {
  if (!magicEmail.value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(magicEmail.value)) {
    ElMessage.warning(t('auth.email_invalid')); return;
  }
  magicLoading.value = true;
  try {
    await apiClient.post('/auth/magic-link/send', { email: magicEmail.value });
    magicLinkSent.value = true;
    startMagicCountdown(60);
    ElMessage.success(t('auth.magic_sent_toast'));
  } catch {
    ElMessage.error(t('auth.magic_send_fail'));
  }
  finally { magicLoading.value = false; }
}

// ─── Passkey 登录 ───
async function handlePasskeyLogin() {
  if (!window.PublicKeyCredential) { ElMessage.warning(t('auth.passkey_unsupported_webauthn')); return; }
  if (passkeyLoading.value) return;
  passkeyLoading.value = true;
  try {
    // 1. Get login options from server
    const { data: optRes } = await apiClient.post('/auth/webauthn/login/options', { email: form.email || undefined });
    const opts = optRes.data;

    // 2. Check if user has any passkeys registered
    if (opts.allowCredentials && opts.allowCredentials.length === 0) {
      ElMessage.warning(t('auth.passkey_not_registered'));
      return;
    }

    // 3. Call browser WebAuthn API
    const cred = await navigator.credentials.get({
      publicKey: {
        challenge: base64ToUint8(opts.challenge), rpId: opts.rpId,
        allowCredentials: (opts.allowCredentials || []).map(c => ({ ...c, id: base64ToUint8(c.id) })),
        userVerification: opts.userVerification || 'preferred', timeout: opts.timeout || 300000,
      },
    });

    // 4. Verify with server
    const { data: vr } = await apiClient.post('/auth/webauthn/login/verify', {
      id: cred.id, rawId: bufToBase64(cred.rawId),
      response: {
        clientDataJSON: bufToBase64(cred.response.clientDataJSON),
        authenticatorData: bufToBase64(cred.response.authenticatorData),
        signature: bufToBase64(cred.response.signature),
        userHandle: cred.response.userHandle ? bufToBase64(cred.response.userHandle) : null,
      },
    });
    if (vr.success) {
      const { user, token } = vr.data;
      authStore.user = user; authStore.token = token;
      localStorage.setItem('auth_token', token); localStorage.setItem('user', JSON.stringify(user));
      ElMessage.success(t('auth.passkey_login_ok')); router.push(loginRedirectPath());
    }
  } catch (e) {
    if (e.name === 'NotAllowedError') {
      // 用户取消操作，无需提示
    } else if (e.name === 'NotFoundError') {
      ElMessage.warning(t('auth.passkey_not_found'));
    } else if (e.response?.data?.message) {
      ElMessage.error(e.response.data.message);
    } else {
      ElMessage.error(t('auth.passkey_login_fail'));
    }
  }
  finally { passkeyLoading.value = false; }
}

// ─── 扫码登录 ───
async function createQrSession() {
  qrLoading.value = true;
  qrImageUrl.value = null;
  try {
    const { data: res } = await apiClient.post('/auth/qrcode/session');
    qrSessionId.value = res.data.session_id;
    // 生成二维码（编码为确认 URL）
    const confirmUrl = window.location.origin + '/auth/qrcode/confirm?session_id=' + qrSessionId.value;
    qrImageUrl.value = await QRCode.toDataURL(confirmUrl, { width: 200, margin: 1, color: { dark: '#1f2937' } });
    qrPollTimer = setInterval(async () => {
      try {
        const { data: r } = await apiClient.get(`/auth/qrcode/session/${qrSessionId.value}`);
        if (r.data?.token) {
          clearInterval(qrPollTimer); qrPollTimer = null;
          authStore.user = r.data.user; authStore.token = r.data.token;
          localStorage.setItem('auth_token', r.data.token); localStorage.setItem('user', JSON.stringify(r.data.user));
          ElMessage.success(t('auth.qr_login_ok')); router.push(loginRedirectPath());
        } else if (r.data?.status === 'expired') {
          clearInterval(qrPollTimer); qrPollTimer = null;
          ElMessage.warning(t('auth.qr_expired')); qrSessionId.value = null; qrImageUrl.value = null;
        }
      } catch { clearInterval(qrPollTimer); qrPollTimer = null; qrSessionId.value = null; qrImageUrl.value = null; }
    }, 2000);
  } catch { ElMessage.error(t('auth.qr_session_fail')); }
  finally { qrLoading.value = false; }
}
function cancelQr() {
  if (qrPollTimer) { clearInterval(qrPollTimer); qrPollTimer = null; }
  qrSessionId.value = null; qrImageUrl.value = null; showQrPanel.value = false;
}

// ─── OAuth ───
async function oauthLogin(provider) {
  const p = availableProviders.value.find(x => x.provider === provider);
  if (p && p.configured === false) {
    ElMessage.warning(t('auth.oauth_not_configured', { name: p.name || provider }));
    return;
  }
  try {
    const path = loginRedirectPath() || '/dashboard';
    const returnTo = path.startsWith('/build') ? path : `/build${path.startsWith('/') ? path : `/${path}`}`;
    const { data: res } = await apiClient.get(`/oauth/authorize-url/${provider}`, {
      params: { intent: 'login', return_to: returnTo },
    });
    const url = res.data?.authorize_url;
    if (!url) {
      ElMessage.error(res.error?.message || res.message || t('auth.oauth_url_fail'));
      return;
    }
    window.location.href = url;
  } catch (e) {
    ElMessage.error(e.response?.data?.error?.message || e.response?.data?.message || t('auth.oauth_unavailable', { name: provider }));
  }
}

function providerIcon(provider) {
  const icons = { wechat: 'We', qq: 'QQ', apple: '🍎', google: 'G', github: 'GH', alipay: 'Pay' };
  return icons[provider] || provider.charAt(0).toUpperCase();
}

function base64ToUint8(str) { return Uint8Array.from(atob(str), c => c.charCodeAt(0)); }
function bufToBase64(buf) { const b = new Uint8Array(buf); let bin = ''; b.forEach(v => bin += String.fromCharCode(v)); return btoa(bin); }

onMounted(() => {
  loadBranding();
  loadOAuthProviders();
  // 检测浏览器是否支持 WebAuthn
  passkeySupported.value = !!window.PublicKeyCredential;
});
onUnmounted(() => {
  if (qrPollTimer) clearInterval(qrPollTimer);
  clearMagicTimer();
  if (phoneCodeTimer) clearInterval(phoneCodeTimer);
});
</script>

<style scoped>
.login-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; position: relative; transition: background 0.5s; }
.login-card { width: 420px; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); z-index: 1; }
.login-header { text-align: center; margin-bottom: 32px; }
.login-header h2 { margin: 16px 0 8px; font-size: 22px; }
.login-tabs { margin-bottom: 8px; }
.login-tabs :deep(.el-tabs__header) { margin-bottom: 16px; }
.code-row { display: flex; gap: 8px; width: 100%; }
.code-row .el-input { flex: 1; }
.phone-login-hint { margin: -8px 0 8px; font-size: 12px; color: #909399; }
.brand-logo { display: flex; justify-content: center; margin-bottom: 16px; }
.logo-img { max-height: 48px; max-width: 200px; }
.w-full { width: 100%; }
.divider { display: flex; align-items: center; margin: 20px 0 16px; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e4e7ed; }
.divider-text { padding: 0 16px; font-size: 13px; color: #909399; white-space: nowrap; }
.alt-login-section { margin-bottom: 12px; }
.magic-link-row { display: flex; gap: 8px; }
.magic-input { flex: 1; }
.magic-success { display: flex; align-items: flex-start; gap: 8px; padding: 12px; background: #f0f9eb; border-radius: 6px; font-size: 13px; color: #67c23a; flex-wrap: wrap; }
.magic-success-text { display: flex; flex-direction: column; gap: 2px; flex: 1; }
.magic-hint { font-size: 12px; color: #95d475; }
.magic-actions { display: flex; gap: 8px; width: 100%; margin-top: 4px; justify-content: flex-end; }
.passkey-hint { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #909399; margin: -8px 0 12px; padding: 0 2px; }
.alt-login-row { display: flex; gap: 8px; margin-bottom: 16px; }
.flex-1 { flex: 1; }
.qr-panel { margin-bottom: 16px; padding: 20px; background: #fafafa; border-radius: 8px; text-align: center; }
.qr-placeholder p { margin: 8px 0 4px; color: #606266; font-size: 14px; }
.qr-code-img { width: 180px; height: 180px; display: block; margin: 0 auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.text-small { font-size: 12px; color: #909399 !important; }
.qr-status { margin-top: 12px; }
.qr-status p { margin: 8px 0; color: #909399; }
.social-login { text-align: center; margin: 16px 0; }
.social-label { font-size: 13px; color: #909399; }
.social-icons { display: flex; justify-content: center; gap: 12px; margin-top: 12px; }
.social-btn { width: 40px; height: 40px; font-size: 16px; font-weight: 700; }
.social-icon { font-style: normal; font-size: 15px; }
.login-footer { text-align: center; margin-top: 24px; font-size: 13px; }
.branding-loading { position: fixed; top: 16px; right: 16px; z-index: 100; background: rgba(255,255,255,0.8); padding: 8px; border-radius: 50%; }
</style>
