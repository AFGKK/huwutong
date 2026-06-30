/**
 * HWT License — 交互式产品演示嵌入脚本
 *
 * 可嵌入到官网 (demo.huwutong.com) 的浮动按钮/弹窗
 * 无需注册即可体验产品
 *
 * @m3-70 InteractiveDemo
 */
(function () {
  'use strict';

  const config = window.HWT_DEMO_CONFIG || {};
  const DEMO_URL = config.demoUrl || 'https://demo.huwutong.com';
  const MODE = config.mode || 'floating';
  const POSITION = config.position || 'bottom-right';
  const BUTTON_TEXT = config.buttonText || '在线体验';
  const THEME_COLOR = config.themeColor || '#409eff';

  // ─── 创建样式 ───
  const style = document.createElement('style');
  style.textContent = `
    .hwt-demo-btn {
      position: fixed;
      ${POSITION.includes('bottom') ? 'bottom: 24px;' : 'top: 24px;'}
      ${POSITION.includes('right') ? 'right: 24px;' : 'left: 24px;'}
      z-index: 99999;
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 20px;
      background: ${THEME_COLOR};
      color: #fff;
      border: none;
      border-radius: 50px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 16px rgba(64,158,255,0.3);
      transition: transform 0.2s, box-shadow 0.2s;
    }
    .hwt-demo-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 24px rgba(64,158,255,0.4);
    }
    .hwt-demo-btn svg { width: 20px; height: 20px; }
    .hwt-demo-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.5);
      z-index: 99998;
      display: none;
      animation: hwtFadeIn 0.3s;
    }
    .hwt-demo-overlay.active { display: block; }
    .hwt-demo-iframe {
      position: fixed;
      top: 5%; left: 5%; right: 5%; bottom: 5%;
      width: 90%; height: 90%;
      z-index: 99999;
      border: none;
      border-radius: 12px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      display: none;
      animation: hwtSlideUp 0.4s;
    }
    .hwt-demo-iframe.active { display: block; }
    .hwt-demo-close {
      position: fixed;
      top: calc(5% + 8px); right: calc(5% + 8px);
      z-index: 100000;
      width: 36px; height: 36px;
      background: rgba(0,0,0,0.5);
      color: #fff;
      border: none;
      border-radius: 50%;
      font-size: 20px;
      cursor: pointer;
      display: none;
      align-items: center;
      justify-content: center;
    }
    .hwt-demo-close.active { display: flex; }
    .hwt-demo-close:hover { background: rgba(0,0,0,0.7); }

    @keyframes hwtFadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes hwtSlideUp {
      from { transform: translateY(30px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
  `;
  document.head.appendChild(style);

  // ─── 创建 SVG 图标 ───
  const iconSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>';

  // ─── 创建按钮 ───
  const btn = document.createElement('button');
  btn.className = 'hwt-demo-btn';
  btn.innerHTML = iconSvg + BUTTON_TEXT;
  document.body.appendChild(btn);

  // ─── 创建遮罩层 ───
  const overlay = document.createElement('div');
  overlay.className = 'hwt-demo-overlay';
  document.body.appendChild(overlay);

  // ─── 创建 iframe ───
  const iframe = document.createElement('iframe');
  iframe.className = 'hwt-demo-iframe';
  iframe.src = DEMO_URL + '/build/demo';
  iframe.allow = 'camera;microphone;clipboard-read;clipboard-write';
  iframe.setAttribute('loading', 'lazy');
  document.body.appendChild(iframe);

  // ─── 创建关闭按钮 ───
  const closeBtn = document.createElement('button');
  closeBtn.className = 'hwt-demo-close';
  closeBtn.innerHTML = '✕';
  closeBtn.setAttribute('aria-label', '关闭演示');
  document.body.appendChild(closeBtn);

  // ─── 事件绑定 ───
  function openDemo() {
    overlay.classList.add('active');
    iframe.classList.add('active');
    closeBtn.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeDemo() {
    overlay.classList.remove('active');
    iframe.classList.remove('active');
    closeBtn.classList.remove('active');
    document.body.style.overflow = '';
  }

  btn.addEventListener('click', openDemo);
  closeBtn.addEventListener('click', closeDemo);
  overlay.addEventListener('click', closeDemo);

  // 监听 iframe 消息（CTA注册、关闭等）
  window.addEventListener('message', function (event) {
    if (event.data?.type === 'HWT_DEMO_CLOSE') {
      closeDemo();
    }
    if (event.data?.type === 'HWT_DEMO_REGISTER') {
      // 注册事件，可触发自定义回调
      if (typeof config.onRegister === 'function') {
        config.onRegister(event.data.data);
      }
    }
  });

  // 如果配置了自动打开
  if (config.autoOpen) {
    setTimeout(openDemo, config.autoOpenDelay || 3000);
  }

  console.log('[HWT Demo] Embed script loaded');
})();
