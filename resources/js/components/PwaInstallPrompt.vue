<template>
  <div>
    <!-- 安装提示横幅 -->
    <div v-if="showInstallPrompt" class="pwa-install-banner" role="alert">
      <div class="pwa-install-content">
        <div class="pwa-install-icon">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="12" width="18" height="9" rx="2" stroke="#409eff" stroke-width="2"/>
            <path d="M12 3v9m0 0l-4-4m4 4l4-4" stroke="#409eff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="pwa-install-text">
          <strong>安装 HWT License</strong>
          <span>添加到主屏幕，获得更好的移动体验</span>
        </div>
        <div class="pwa-install-actions">
          <el-button size="small" type="primary" @click="install">安装</el-button>
          <el-button size="small" @click="dismiss">稍后</el-button>
        </div>
      </div>
    </div>

    <!-- 更新提示 -->
    <div v-if="showUpdatePrompt" class="pwa-update-banner" role="alert">
      <div class="pwa-update-content">
        <el-icon color="#e6a23c"><WarningFilled /></el-icon>
        <span>发现新版本</span>
        <el-button size="small" type="warning" @click="update">立即更新</el-button>
        <el-button size="small" text @click="showUpdatePrompt = false">忽略</el-button>
      </div>
    </div>

    <!-- 离线提示 -->
    <div v-if="!isOnline" class="pwa-offline-banner" role="alert">
      <el-icon color="#f56c6c"><WarnTriangleFilled /></el-icon>
      <span>当前处于离线模式</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { WarningFilled, WarnTriangleFilled } from '@element-plus/icons-vue';

const showInstallPrompt = ref(false);
const showUpdatePrompt = ref(false);
const isOnline = ref(navigator.onLine);
let deferredPrompt = null;
let onlineHandler = null;
let offlineHandler = null;

onMounted(() => {
  // 安装提示 (beforeinstallprompt)
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    // 检查是否已安装
    if (!window.matchMedia('(display-mode: standalone)').matches) {
      showInstallPrompt.value = true;
    }
  });

  // 安装成功
  window.addEventListener('appinstalled', () => {
    showInstallPrompt.value = false;
    deferredPrompt = null;
    console.log('[PWA] App installed');
  });

  // 更新提示
  window.addEventListener('pwa-update-available', () => {
    showUpdatePrompt.value = true;
  });

  // 网络状态
  onlineHandler = () => { isOnline.value = true; };
  offlineHandler = () => { isOnline.value = false; };
  window.addEventListener('online', onlineHandler);
  window.addEventListener('offline', offlineHandler);
});

onUnmounted(() => {
  window.removeEventListener('online', onlineHandler);
  window.removeEventListener('offline', offlineHandler);
});

async function install() {
  if (!deferredPrompt) return;

  deferredPrompt.prompt();
  const result = await deferredPrompt.userChoice;
  console.log('[PWA] Install result:', result.outcome);

  if (result.outcome === 'accepted') {
    showInstallPrompt.value = false;
  }

  deferredPrompt = null;
}

function dismiss() {
  showInstallPrompt.value = false;
  // 24小时内不再提示
  localStorage.setItem('pwa-install-dismissed', Date.now().toString());
}

function update() {
  if (window.PWA) {
    window.PWA.applyUpdate();
  }
  showUpdatePrompt.value = false;
}
</script>

<style scoped>
.pwa-install-banner,
.pwa-update-banner,
.pwa-offline-banner {
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 9999;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  max-width: 420px;
  width: calc(100% - 40px);
}
.pwa-install-banner {
  background: #fff;
  border: 1px solid #ebeef5;
}
.pwa-install-content {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
}
.pwa-install-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  font-size: 13px;
}
.pwa-install-text strong {
  font-size: 14px;
  margin-bottom: 2px;
}
.pwa-install-text span {
  color: #909399;
  font-size: 12px;
}
.pwa-install-actions {
  display: flex;
  gap: 4px;
}
.pwa-update-banner {
  background: #fdf6ec;
  border: 1px solid #faecd8;
}
.pwa-update-content {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  font-size: 13px;
}
.pwa-offline-banner {
  background: #fef0f0;
  border: 1px solid #fde2e2;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  font-size: 13px;
  color: #f56c6c;
}
</style>
