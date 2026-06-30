/**
 * HWT License — Service Worker 注册器
 *
 * 处理 SW 注册、更新检测、推送订阅
 *
 * @m3-51 PWAManager
 */

const PWA = {
  swPath: '/sw.js',
  swScope: '/build/',
  isRegistered: false,
  registration: null,

  /**
   * 注册 Service Worker
   */
  async register() {
    if (!('serviceWorker' in navigator)) {
      console.log('[PWA] Service Worker not supported');
      return false;
    }

    try {
      this.registration = await navigator.serviceWorker.register(this.swPath, {
        scope: this.swScope,
        updateViaCache: 'none',
      });

      this.isRegistered = true;
      console.log('[PWA] Service Worker registered:', this.registration.scope);

      // 监听更新
      this.registration.addEventListener('updatefound', () => {
        const newWorker = this.registration.installing;
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            console.log('[PWA] New version available');
            this.showUpdatePrompt();
          }
        });
      });

      // 后台检查更新
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        console.log('[PWA] Controller changed, reloading...');
        window.location.reload();
      });

      // 已注册且是第一次安装
      if (this.registration.active && !navigator.serviceWorker.controller) {
        console.log('[PWA] App ready for offline use');
      }

      return true;
    } catch (error) {
      console.error('[PWA] Service Worker registration failed:', error);
      return false;
    }
  },

  /**
   * 请求推送通知权限
   */
  async requestPushPermission(vapidPublicKey) {
    if (!('PushManager' in window)) {
      console.log('[PWA] Push not supported');
      return null;
    }

    try {
      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        console.log('[PWA] Push permission denied');
        return null;
      }

      if (!this.registration) {
        await this.register();
      }

      const subscription = await this.registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
      });

      // 发送到服务器
      await this.sendSubscriptionToServer(subscription);

      console.log('[PWA] Push subscribed');
      return subscription;
    } catch (error) {
      console.error('[PWA] Push subscription failed:', error);
      return null;
    }
  },

  /**
   * 取消推送订阅
   */
  async unsubscribePush() {
    if (!this.registration) return;

    try {
      const subscription = await this.registration.pushManager.getSubscription();
      if (subscription) {
        await subscription.unsubscribe();

        // 通知服务端
        await fetch('/api/pwa/unsubscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
          body: JSON.stringify({ endpoint: subscription.endpoint }),
        });

        console.log('[PWA] Push unsubscribed');
      }
    } catch (error) {
      console.error('[PWA] Unsubscribe failed:', error);
    }
  },

  /**
   * 发送订阅信息到服务器
   */
  async sendSubscriptionToServer(subscription) {
    try {
      await fetch('/api/pwa/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        body: JSON.stringify(subscription.toJSON()),
      });
    } catch (error) {
      console.error('[PWA] Failed to send subscription:', error);
    }
  },

  /**
   * 显示更新提示
   */
  showUpdatePrompt() {
    const event = new CustomEvent('pwa-update-available', {
      detail: { registration: this.registration },
    });
    window.dispatchEvent(event);
  },

  /**
   * 应用更新
   */
  applyUpdate() {
    if (this.registration?.waiting) {
      this.registration.waiting.postMessage({ type: 'SKIP_WAITING' });
    }
  },

  /**
   * Base64 URL 转 Uint8Array
   */
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  },

  /**
   * 检查是否已安装 PWA
   */
  isInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.navigator.standalone === true;
  },

  /**
   * 检测网络状态
   */
  onNetworkChange(callback) {
    window.addEventListener('online', () => callback(true));
    window.addEventListener('offline', () => callback(false));
    callback(navigator.onLine);
  },
};

// 自动注册
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => PWA.register());
} else {
  PWA.register();
}

// 暴露全局
window.PWA = PWA;

export default PWA;
