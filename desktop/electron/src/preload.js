/**
 * D-32: Electron preload — 暴露最小桌面 API 给 /build SPA
 */
const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('hwtDesktop', {
  platform: process.platform,
  isDesktop: true,
  version: '1.0.0',
});
