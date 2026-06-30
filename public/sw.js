/**
 * HWT License — Service Worker
 *
 * PWA 离线缓存 + 推送通知
 * - 预缓存关键资源（App Shell）
 * - 运行时缓存 API 响应（Stale-While-Revalidate）
 * - 离线回退页面
 * - 推送通知处理
 * - 后台同步
 *
 * @m3-51 PWAManager
 */

const CACHE_VERSION = 'v1';
const STATIC_CACHE = `hwt-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `hwt-dynamic-${CACHE_VERSION}`;
const API_CACHE = `hwt-api-${CACHE_VERSION}`;
const IMAGE_CACHE = `hwt-images-${CACHE_VERSION}`;
const ARTICLE_CACHE = `hwt-articles-${CACHE_VERSION}`;

// ─── 预缓存清单 (App Shell) ───
const PRECACHE_URLS = [
  '/build/',
  '/build/login',
  '/build/dashboard',
  '/build/assets/app.css',
  '/build/assets/admin.js',
  '/manifest.json',
];

// ─── 安装阶段: 预缓存 App Shell ───
self.addEventListener('install', (event) => {
  console.log('[SW] Installing version:', CACHE_VERSION);

  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(PRECACHE_URLS);
    }).then(() => {
      // 强制等待页面使用新 SW
      return self.skipWaiting();
    })
  );
});

// ─── 激活阶段: 清理旧缓存 ───
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating version:', CACHE_VERSION);

  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => {
            return name.startsWith('hwt-') && name !== STATIC_CACHE
              && name !== DYNAMIC_CACHE && name !== API_CACHE
              && name !== IMAGE_CACHE;
          })
          .map((name) => {
            console.log('[SW] Deleting old cache:', name);
            return caches.delete(name);
          })
      );
    }).then(() => {
      // 立即控制所有页面
      return self.clients.claim();
    })
  );
});

// ─── 请求拦截: 缓存策略 ───
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // 忽略非 GET 请求
  if (request.method !== 'GET') return;

  // 忽略浏览器扩展请求
  if (!url.protocol.startsWith('http')) return;

  // ── API 请求: Stale-While-Revalidate + 网络优先 ──
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(networkFirstWithFallback(request, API_CACHE));
    return;
  }

  // ── 静态资源: Cache-First ──
  if (
    url.pathname.startsWith('/build/assets/') ||
    url.pathname.endsWith('.css') ||
    url.pathname.endsWith('.js') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.woff')
  ) {
    event.respondWith(cacheFirst(request, STATIC_CACHE));
    return;
  }

  // ── 图片: Cache-First ──
  if (
    url.pathname.endsWith('.png') ||
    url.pathname.endsWith('.jpg') ||
    url.pathname.endsWith('.jpeg') ||
    url.pathname.endsWith('.gif') ||
    url.pathname.endsWith('.svg') ||
    url.pathname.endsWith('.webp') ||
    url.pathname.endsWith('.ico')
  ) {
    event.respondWith(cacheFirst(request, IMAGE_CACHE));
    return;
  }

  // ── 导航请求: Network-First with offline fallback ──
  if (request.mode === 'navigate') {
    event.respondWith(networkFirstWithFallback(request, DYNAMIC_CACHE));
    return;
  }

  // ── 其他: Network-First ──
  event.respondWith(networkFirst(request, DYNAMIC_CACHE));
});

// ─── 缓存策略实现 ───

/**
 * Cache-First: 优先从缓存读取，缓存未命中则网络获取
 */
async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    return caches.match('/build/offline.html');
  }
}

/**
 * Network-First: 优先网络获取，失败时回退到缓存
 */
async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) return cached;
    return caches.match('/build/offline.html');
  }
}

/**
 * Network-First with offline fallback: 导航请求专用
 */
async function networkFirstWithFallback(request, cacheName) {
  try {
    const response = await fetch(request);

    // 只缓存成功的导航响应
    if (response.ok || response.type === 'opaqueredirect') {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }

    return response;
  } catch (error) {
    console.log('[SW] Network failed, serving cached:', request.url);

    const cached = await caches.match(request);
    if (cached) return cached;

    // 检查是否导航请求，返回离线页
    if (request.mode === 'navigate') {
      return caches.match('/build/offline.html');
    }

    return new Response('Offline', { status: 503, statusText: 'Service Unavailable' });
  }
}

// ─── 推送通知 ───
self.addEventListener('push', (event) => {
  console.log('[SW] Push received');

  let data = { title: 'HWT License', body: '有新通知', icon: '/build/assets/pwa-icon-192.png' };

  try {
    if (event.data) {
      const parsed = event.data.json();
      data = { ...data, ...parsed };
    }
  } catch (e) {
    data.body = event.data?.text() || data.body;
  }

  const options = {
    body: data.body,
    icon: data.icon || '/build/assets/pwa-icon-192.png',
    badge: '/build/assets/pwa-badge.png',
    vibrate: [200, 100, 200],
    data: {
      url: data.url || '/build/',
      date: Date.now(),
    },
    actions: data.actions || [
      { action: 'open', title: '查看详情' },
      { action: 'dismiss', title: '忽略' },
    ],
    tag: data.tag || 'notification',
    renotify: true,
    requireInteraction: true,
    silent: false,
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// ─── 通知点击 ───
self.addEventListener('notificationclick', (event) => {
  const { notification, action } = event;
  notification.close();

  if (action === 'dismiss') return;

  const url = notification.data?.url || '/build/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // 查找已打开的页面
      for (const client of clientList) {
        if (client.url.includes('/build/') && 'focus' in client) {
          client.postMessage({ type: 'NOTIFICATION_CLICK', url });
          return client.focus();
        }
      }
      // 打开新窗口
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});

// ─── 消息处理 ───
self.addEventListener('message', (event) => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data?.type === 'CLEAR_CACHE') {
    caches.keys().then((names) => {
      names.filter(n => n.startsWith('hwt-')).forEach(n => caches.delete(n));
    });
  }

  // ── 保存文章离线 ──
  if (event.data?.type === 'CACHE_ARTICLE') {
    const { articleId, data } = event.data;
    if (articleId && data) {
      caches.open(ARTICLE_CACHE).then((cache) => {
        const request = new Request('/api/cached-articles/' + articleId);
        const response = new Response(JSON.stringify(data), {
          headers: { 'Content-Type': 'application/json' }
        });
        cache.put(request, response);
      });
    }
  }

  // ── 获取已缓存的文章列表 ──
  if (event.data?.type === 'GET_CACHED_ARTICLES') {
    caches.open(ARTICLE_CACHE).then((cache) => {
      cache.keys().then((requests) => {
        Promise.all(requests.map(req => cache.match(req))).then((responses) => {
          Promise.all(responses.map(r => r ? r.json() : null)).then((articles) => {
            self.clients.matchAll().then(clients => {
              clients.forEach(client => {
                client.postMessage({
                  type: 'CACHED_ARTICLES',
                  articles: articles.filter(Boolean)
                });
              });
            });
          });
        });
      });
    });
  }

  // ── 删除缓存的文章 ──
  if (event.data?.type === 'UNCACHE_ARTICLE') {
    const { articleId } = event.data;
    if (articleId) {
      caches.open(ARTICLE_CACHE).then((cache) => {
        cache.delete('/api/cached-articles/' + articleId);
      });
    }
  }
});

// ─── 后台同步 ───
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-licenses') {
    console.log('[SW] Background sync: sync-licenses');
    // 后台同步 License 数据
    event.waitUntil(syncLicenses());
  }
});

async function syncLicenses() {
  try {
    const cache = await caches.open(API_CACHE);
    const requests = await cache.keys();
    // 处理待同步请求
    console.log('[SW] Syncing', requests.length, 'cached requests');
  } catch (error) {
    console.error('[SW] Sync failed:', error);
  }
}
