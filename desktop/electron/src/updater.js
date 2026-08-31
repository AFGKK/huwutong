/**
 * D-32: electron-updater 自动更新
 */
const { autoUpdater } = require('electron-updater');
const { dialog } = require('electron');
const config = require('../config');

let initialized = false;

function setupAutoUpdater(mainWindow, options = {}) {
  if (!config.autoUpdateEnabled) {
    return;
  }

  if (!initialized) {
    autoUpdater.autoDownload = !options.checkOnly;
    autoUpdater.autoInstallOnAppQuit = true;

    autoUpdater.on('update-available', () => {
      dialog.showMessageBox(mainWindow, {
        type: 'info',
        title: '发现新版本',
        message: '正在下载更新…',
      });
    });

    autoUpdater.on('update-downloaded', () => {
      dialog.showMessageBox(mainWindow, {
        type: 'info',
        title: '更新就绪',
        message: '新版本已下载，重启应用后生效。',
        buttons: ['立即重启', '稍后'],
      }).then(({ response }) => {
        if (response === 0) {
          autoUpdater.quitAndInstall();
        }
      });
    });

    autoUpdater.on('error', (error) => {
      if (options.checkOnly) {
        dialog.showMessageBox(mainWindow, {
          type: 'warning',
          title: '检查更新',
          message: `无法检查更新：${error.message}`,
        });
      }
    });

    initialized = true;
  }

  autoUpdater.checkForUpdates().catch(() => {});
}

module.exports = { setupAutoUpdater };
