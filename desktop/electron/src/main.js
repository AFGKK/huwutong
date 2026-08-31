/**
 * D-32: 互物通 Electron 管理壳 — 主进程
 */
const {
  app,
  BrowserWindow,
  Tray,
  Menu,
  shell,
  nativeImage,
} = require('electron');
const path = require('path');
const fs = require('fs');
const config = require('../config');
const { setupAutoUpdater } = require('./updater');

let mainWindow = null;
let tray = null;
let isQuitting = false;

function loadAppIcon() {
  const candidates = [
    path.join(config.assetsDir, 'icon.png'),
    path.join(config.assetsDir, 'icon.ico'),
    path.join(__dirname, '../../../public/build/assets/pwa-icon-192.png'),
  ];

  for (const file of candidates) {
    if (fs.existsSync(file)) {
      return nativeImage.createFromPath(file);
    }
  }

  // 1x1 蓝色占位 PNG
  const fallback = Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
    'base64',
  );

  return nativeImage.createFromBuffer(fallback).resize({ width: 32, height: 32 });
}

function createWindow() {
  const icon = loadAppIcon();

  mainWindow = new BrowserWindow({
    ...config.window,
    show: false,
    icon,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  mainWindow.loadURL(config.loginUrl);

  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });

  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.on('close', (event) => {
    if (!isQuitting) {
      event.preventDefault();
      mainWindow.hide();
    }
  });
}

function showWindow() {
  if (!mainWindow) {
    createWindow();
    return;
  }
  if (mainWindow.isMinimized()) {
    mainWindow.restore();
  }
  mainWindow.show();
  mainWindow.focus();
}

function buildTrayMenu() {
  return Menu.buildFromTemplate([
    { label: '显示主窗口', click: showWindow },
    { label: '仪表盘', click: () => mainWindow?.loadURL(config.dashboardUrl) },
    { type: 'separator' },
    {
      label: '检查更新',
      click: () => setupAutoUpdater(mainWindow, { checkOnly: true }),
      enabled: app.isPackaged && config.autoUpdateEnabled,
    },
    { type: 'separator' },
    {
      label: '退出',
      click: () => {
        isQuitting = true;
        app.quit();
      },
    },
  ]);
}

function createTray() {
  const icon = loadAppIcon();
  tray = new Tray(icon);
  tray.setToolTip(config.window.title);
  tray.setContextMenu(buildTrayMenu());
  tray.on('double-click', showWindow);
}

function registerAppEvents() {
  app.on('second-instance', () => showWindow());

  app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
      // 托盘模式：Windows/Linux 保持运行
    }
  });

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    } else {
      showWindow();
    }
  });

  app.on('before-quit', () => {
    isQuitting = true;
  });
}

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  app.whenReady().then(async () => {
    createWindow();
    createTray();
    registerAppEvents();

    if (app.isPackaged && config.autoUpdateEnabled) {
      setupAutoUpdater(mainWindow);
    }
  });
}
