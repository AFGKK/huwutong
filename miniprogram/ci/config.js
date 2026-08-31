/**
 * miniprogram-ci 公共配置
 *
 * 密钥来源（优先级）：
 * 1. 环境变量 MP_CI_PRIVATE_KEY_PATH → 本地 pem 文件路径
 * 2. 环境变量 MP_CI_PRIVATE_KEY → pem 全文（CI Secret，可含 \\n）
 * 3. 默认文件 miniprogram/ci/private.key（gitignore）
 */
const fs = require('fs');
const path = require('path');

const PROJECT_PATH = path.resolve(__dirname, '..');
const DEFAULT_KEY = path.join(__dirname, 'private.key');

function readAppId() {
  if (process.env.MP_CI_APPID) return process.env.MP_CI_APPID.trim();
  try {
    const pc = JSON.parse(fs.readFileSync(path.join(PROJECT_PATH, 'project.config.json'), 'utf8'));
    return pc.appid || '';
  } catch (e) {
    return '';
  }
}

function resolvePrivateKey() {
  const keyPath = process.env.MP_CI_PRIVATE_KEY_PATH || DEFAULT_KEY;
  if (fs.existsSync(keyPath)) {
    return { privateKeyPath: keyPath };
  }

  const raw = process.env.MP_CI_PRIVATE_KEY || '';
  if (raw.trim()) {
    const normalized = raw.includes('BEGIN')
      ? raw.replace(/\\n/g, '\n')
      : raw;
    const tmp = path.join(__dirname, '.private.key.tmp');
    fs.writeFileSync(tmp, normalized, { mode: 0o600 });
    return { privateKeyPath: tmp, tmpKeyPath: tmp };
  }

  throw new Error(
    '缺少上传密钥。请下载代码上传密钥并保存为 miniprogram/ci/private.key，\n' +
    '或设置环境变量 MP_CI_PRIVATE_KEY / MP_CI_PRIVATE_KEY_PATH。\n' +
    '获取：微信公众平台 → 开发管理 → 开发设置 → 小程序代码上传密钥'
  );
}

function buildProject() {
  const ci = require('miniprogram-ci');
  const appid = readAppId();
  if (!appid || /^wx0+$/.test(appid) || appid.includes('000000')) {
    throw new Error('无效 AppID，请检查 project.config.json 或 MP_CI_APPID');
  }

  const key = resolvePrivateKey();
  const project = new ci.Project({
    appid,
    type: 'miniProgram',
    projectPath: PROJECT_PATH,
    privateKeyPath: key.privateKeyPath,
    ignores: [
      'node_modules/**/*',
      'ci/**/*',
      'package.json',
      'package-lock.json',
      'project.private.config.json',
      '**/*.md',
      '**/.DS_Store',
    ],
  });

  return { ci, project, appid, tmpKeyPath: key.tmpKeyPath || null };
}

function cleanup(tmpKeyPath) {
  if (tmpKeyPath && fs.existsSync(tmpKeyPath)) {
    try { fs.unlinkSync(tmpKeyPath); } catch (e) { /* ignore */ }
  }
}

function versionFromEnv() {
  return process.env.MP_CI_VERSION
    || process.env.GITHUB_SHA?.slice(0, 7)
    || new Date().toISOString().slice(0, 10);
}

function descFromEnv(fallback) {
  return process.env.MP_CI_DESC || fallback;
}

module.exports = {
  PROJECT_PATH,
  buildProject,
  cleanup,
  versionFromEnv,
  descFromEnv,
};
