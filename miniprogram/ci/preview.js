#!/usr/bin/env node
/**
 * 生成预览二维码（体验前快速扫码）
 *
 * 用法:
 *   cd miniprogram && npm i && npm run ci:preview
 *
 * 输出: miniprogram/ci/preview-qrcode.jpg
 */
const path = require('path');
const fs = require('fs');
const { buildProject, cleanup, descFromEnv } = require('./config');

async function main() {
  const { ci, project, appid, tmpKeyPath } = buildProject();
  const out = path.join(__dirname, 'preview-qrcode.jpg');
  const pagePath = process.env.MP_CI_PAGE || 'pages/index/index';
  const robot = Number(process.env.MP_CI_ROBOT || 1);

  console.log(`[preview] appid=${appid} page=${pagePath}`);

  try {
    const result = await ci.preview({
      project,
      desc: descFromEnv('HWT License 查询 — CI 预览'),
      robot,
      qrcodeFormat: 'image',
      qrcodeOutputDest: out,
      pagePath,
      searchQuery: process.env.MP_CI_QUERY || '',
      setting: {
        es6: true,
        minify: true,
        minifyJS: true,
        minifyWXSS: true,
        minifyWXML: true,
      },
      onProgressUpdate: console.log,
    });
    console.log('[preview] ok', result);
    if (fs.existsSync(out)) {
      console.log('[preview] qrcode =>', out);
    }
  } finally {
    cleanup(tmpKeyPath);
  }
}

main().catch((err) => {
  console.error('[preview] failed:', err.message || err);
  process.exit(1);
});
