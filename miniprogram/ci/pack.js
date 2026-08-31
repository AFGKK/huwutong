#!/usr/bin/env node
/**
 * 本地冒烟：校验 AppID + 上传密钥可读 + Project 可实例化
 * （不实际上传）
 */
const fs = require('fs');
const path = require('path');
const { PROJECT_PATH, buildProject, cleanup } = require('./config');

function mustExist(rel) {
  const p = path.join(PROJECT_PATH, rel);
  if (!fs.existsSync(p)) throw new Error('缺少文件: ' + rel);
  console.log('[pack] ok', rel);
}

async function main() {
  mustExist('app.json');
  mustExist('app.js');
  mustExist('project.config.json');
  mustExist('pages/index/index.js');
  mustExist('pages/webview/webview.js');
  mustExist('utils/webview.js');

  const { project, appid, tmpKeyPath } = buildProject();
  console.log('[pack] Project ready', { appid, path: PROJECT_PATH });
  console.log('[pack] project instance', typeof project);
  cleanup(tmpKeyPath);
  console.log('[pack] pass — 可执行 npm run ci:preview / ci:upload');
}

main().catch((err) => {
  console.error('[pack] failed:', err.message || err);
  process.exit(1);
});
