#!/usr/bin/env node
/**
 * 上传体验版 / 正式版代码（需代码上传密钥）
 *
 * 用法:
 *   cd miniprogram && npm i && npm run ci:upload
 *
 * 环境变量:
 *   MP_CI_VERSION  版本号（默认日期或 GITHUB_SHA）
 *   MP_CI_DESC     备注
 *   MP_CI_ROBOT    机器人编号 1-30（默认 1）
 */
const path = require('path');
const { buildProject, cleanup, versionFromEnv, descFromEnv } = require('./config');

async function main() {
  const { ci, project, appid, tmpKeyPath } = buildProject();
  const version = versionFromEnv();
  const desc = descFromEnv('HWT License 查询 — CI 上传');
  const robot = Number(process.env.MP_CI_ROBOT || 1);

  console.log(`[upload] appid=${appid} version=${version} robot=${robot}`);

  try {
    const result = await ci.upload({
      project,
      version,
      desc,
      robot,
      setting: {
        es6: true,
        es7: true,
        minify: true,
        codeProtect: false,
        minifyJS: true,
        minifyWXSS: true,
        minifyWXML: true,
        autoPrefixWXSS: true,
      },
      onProgressUpdate: console.log,
    });
    console.log('[upload] ok', result);
  } finally {
    cleanup(tmpKeyPath);
  }
}

main().catch((err) => {
  console.error('[upload] failed:', err.message || err);
  process.exit(1);
});
