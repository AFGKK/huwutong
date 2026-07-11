<template>
  <div class="login-page" :style="bgStyle">
    <div class="login-card" :style="cardStyle">
      <div class="login-header">
        <div class="brand-logo" v-if="branding.logo_url">
          <img :src="branding.logo_url" :alt="branding.brand_name" class="logo-img" />
        </div>
        <el-icon v-else :size="40" :color="branding.primary_color || '#409eff'"><Key /></el-icon>
        <h2 :style="{ color: branding.text_color || '#303133' }">
          {{ branding.login_page_title || branding.brand_name || 'HWT License 管理后台' }}
        </h2>
        <p class="text-gray-400">{{ branding.login_page_subtitle || '请登录您的账户' }}</p>
      </div>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        size="large"
        @keyup.enter="handleLogin"
      >
        <el-form-item label="邮箱" prop="email">
          <el-input
            v-model="form.email"
            placeholder="请输入邮箱"
            :prefix-icon="UserFilled"
          />
        </el-form-item>

        <el-form-item label="密码" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请输入密码"
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
            {{ authStore.loading ? '登录中...' : '登 录' }}
          </el-button>
        </el-form-item>
      </el-form>

      <!-- 分隔线 -->
      <div class="divider"><span class="divider-text">或使用其他方式</span></div>

      <!-- 魔法链接 -->
      <div class="alt-login-section">
        <div class="magic-link-row" v-if="!magicLinkSent">
          <el-input v-model="magicEmail" placeholder="输入邮箱接收登录链接" size="default" class="magic-input" :disabled="magicLoading" />
          <el-button type="primary" plain :loading="magicLoading" size="default" @click="sendMagicLink">发送链接</el-button>
        </div>
        <div v-else class="magic-success">
          <el-icon :size="18" color="#67c23a"><CircleCheck /></el-icon>
          <div class="magic-success-text">
            <span>登录链接已发送到 <strong>{{ obfuscateEmail(magicEmail) }}</strong></span>
            <span class="magic-hint">请查收邮件，点击链接即可登录（链接 10 分钟内有效）</span>
          </div>
          <div class="magic-actions">
            <el-button v-if="magicCountdown > 0" size="small" disabled text>
              {{ magicCountdown }}s 后重发
            </el-button>
            <el-button v-else size="small" text type="primary" :loading="magicLoading" @click="sendMagicLink">
              重新发送
            </el-button>
            <el-button size="small" text @click="magicLinkSent = false; magicEmail = ''; clearMagicTimer()">
              更换邮箱
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
          <el-icon :size="16" style="margin-right:4px"><Camera /></el-icon> 扫码
        </el-button>
        <el-button plain size="default" class="flex-1" @click="$router.push('/forgot-password')">忘记密码</el-button>
      </div>
      <div v-if="!passkeySupported" class="passkey-hint">
        <el-icon :size="12" color="#909399"><Warning /></el-icon>
        <span>当前浏览器不支持 Passkey，请使用 Chrome/Edge/Safari 最新版</span>
      </div>

      <!-- 扫码面板 -->
      <div v-if="showQrPanel" class="qr-panel">
        <div class="qr-placeholder">
          <template v-if="!qrSessionId">
            <el-icon :size="48" color="#909399"><Camera /></el-icon>
            <p>打开手机端扫描下方二维码登录</p>
            <el-button type="primary" size="small" :loading="qrLoading" @click="createQrSession">生成二维码</el-button>
          </template>
          <template v-else-if="qrImageUrl">
            <img :src="qrImageUrl" class="qr-code-img" alt="扫码登录" />
            <p class="text-sm text-gray-500 mt-2">请使用手机扫码确认登录</p>
            <p class="text-xs text-gray-400 mt-1">二维码有效期 5 分钟</p>
            <div class="flex items-center justify-center gap-2 mt-3">
              <el-icon class="is-loading" :size="16"><Loading /></el-icon>
              <span class="text-sm text-gray-500">等待扫码...</span>
            </div>
            <el-button size="small" class="mt-3" @click="cancelQr">取消</el-button>
          </template>
          <template v-else>
            <el-icon class="is-loading" :size="24"><Loading /></el-icon>
            <p>生成二维码中...</p>
          </template>
        </div>
      </div>

      <!-- 社交登录（仅显示已配置的提供商） -->
      <div class="social-login" v-if="availableProviders.length > 0">
        <span class="social-label">社交账号登录</span>
        <div class="social-icons">
          <template v-for="p in availableProviders" :key="p.provider">
            <el-tooltip :content="`${p.name} 登录`" placement="top">
              <el-button circle class="social-btn" :style="{ color: p.color, borderColor: p.color }" @click="oauthLogin(p.provider)">
                <span class="social-icon">{{ providerIcon(p.provider) }}</span>
              </el-button>
            </el-tooltip>
          </template>
        </div>
      </div>

      <div class="login-footer">
        <router-link to="/status" class="status-link">系统状态</router-link>
        <router-link to="/appeal" class="appeal-link">账号申诉</router-link>
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
import { useAuthStore } from '@/stores/auth';
import { UserFilled, Lock, Key, Loading, CircleCheck, Link, Camera, Promotion, Warning } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import apiClient from '@/api/client';
import QRCode from 'qrcode';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const formRef = ref(null);
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

const branding = reactive({
  brand_name: 'HWT License 管理后台',
  primary_color: '#409eff',
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

const rules = {
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '请输入有效的邮箱地址', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { min: 6, message: '密码至少 6 位', trigger: 'blur' },
  ],
};

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
    background: `linear-gradient(135deg, ${branding.primary_color || '#667eea'} 0%, ${adjustColor(branding.primary_color || '#764ba2', -30)} 100%)`,
  };
});

const cardStyle = computed(() => ({
  '--brand-primary': branding.primary_color || '#409eff',
  borderRadius: '12px',
}));

async function loadBranding() {
  loadingBranding.value = true;
  try {
    const { data } = await apiClient.get('/branding', { params: { domain: window.location.hostname } });
    const config = data?.data?.config;
    if (config) {
      branding.brand_name = config.brand_name || 'HWT License 管理后台';
      branding.primary_color = config.primary_color || '#409eff';
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
        document.title = `${config.brand_name} - 登录`;
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

function adjustColor(hex, amount) {
  if (!hex) return '#764ba2';
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
    ElMessage.warning('请输入有效的邮箱地址'); return;
  }
  magicLoading.value = true;
  try {
    await apiClient.post('/auth/magic-link/send', { email: magicEmail.value });
    magicLinkSent.value = true;
    startMagicCountdown(60);
    ElMessage.success('登录链接已发送');
  } catch {
    ElMessage.error('发送失败，请稍后重试');
  }
  finally { magicLoading.value = false; }
}

// ─── Passkey 登录 ───
async function handlePasskeyLogin() {
  if (!window.PublicKeyCredential) { ElMessage.warning('当前浏览器不支持 Passkey（WebAuthn）'); return; }
  if (passkeyLoading.value) return;
  passkeyLoading.value = true;
  try {
    // 1. Get login options from server
    const { data: optRes } = await apiClient.post('/auth/webauthn/login/options', { email: form.email || undefined });
    const opts = optRes.data;

    // 2. Check if user has any passkeys registered
    if (opts.allowCredentials && opts.allowCredentials.length === 0) {
      ElMessage.warning('该账号尚未注册 Passkey，请先在账户设置中添加');
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
      ElMessage.success('Passkey 登录成功'); router.push(loginRedirectPath());
    }
  } catch (e) {
    if (e.name === 'NotAllowedError') {
      // 用户取消操作，无需提示
    } else if (e.name === 'NotFoundError') {
      ElMessage.warning('未找到可用的 Passkey，请确认已注册');
    } else if (e.response?.data?.message) {
      ElMessage.error(e.response.data.message);
    } else {
      ElMessage.error('Passkey 登录失败，请重试');
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
          ElMessage.success('扫码登录成功'); router.push(loginRedirectPath());
        } else if (r.data?.status === 'expired') {
          clearInterval(qrPollTimer); qrPollTimer = null;
          ElMessage.warning('二维码已过期'); qrSessionId.value = null; qrImageUrl.value = null;
        }
      } catch { clearInterval(qrPollTimer); qrPollTimer = null; qrSessionId.value = null; qrImageUrl.value = null; }
    }, 2000);
  } catch { ElMessage.error('创建扫码会话失败'); }
  finally { qrLoading.value = false; }
}
function cancelQr() {
  if (qrPollTimer) { clearInterval(qrPollTimer); qrPollTimer = null; }
  qrSessionId.value = null; qrImageUrl.value = null; showQrPanel.value = false;
}

// ─── OAuth ───
function oauthLogin(provider) {
  ElMessage.info(`${provider} 登录需配置 OAuth App 凭据后可用`);
}

function providerIcon(provider) {
  const icons = { wechat: '微', qq: 'QQ', apple: '🍎', google: 'G', github: 'GB', alipay: '支' };
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
});
</script>

<style scoped>
.login-page { display: flex; align-items: center; justify-content: center; min-height: 100vh; position: relative; transition: background 0.5s; }
.login-card { width: 420px; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.15); z-index: 1; }
.login-header { text-align: center; margin-bottom: 32px; }
.login-header h2 { margin: 16px 0 8px; font-size: 22px; }
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
