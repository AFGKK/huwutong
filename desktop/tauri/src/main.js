const invoke = window.__TAURI__?.core?.invoke;

const $ = (id) => document.getElementById(id);

function showResult(data) {
  $('result').classList.remove('hidden');
  $('result-body').textContent = JSON.stringify(data, null, 2);
}

async function lookup() {
  if (!invoke) {
    showResult({ error: '请在 Tauri 应用中运行（npm run dev）' });
    return;
  }
  const apiBase = $('api-base').value.trim();
  const licenseKey = $('license-key').value.trim();
  if (!licenseKey) {
    showResult({ error: '请输入 License Key' });
    return;
  }
  try {
    const data = await invoke('lookup_license', { apiBase, licenseKey });
    showResult(data);
  } catch (e) {
    showResult({ error: String(e) });
  }
}

async function validate() {
  if (!invoke) {
    showResult({ error: '请在 Tauri 应用中运行（npm run dev）' });
    return;
  }
  const apiBase = $('api-base').value.trim();
  const licenseKey = $('license-key').value.trim();
  const apiKey = $('api-key').value.trim();
  if (!licenseKey) {
    showResult({ error: '请输入 License Key' });
    return;
  }
  if (!apiKey) {
    showResult({ error: 'SDK 验证需要填写 API Key' });
    return;
  }
  try {
    const valid = await invoke('validate_license', { apiBase, licenseKey, apiKey });
    showResult({ sdk_validate: valid, license_key: licenseKey });
  } catch (e) {
    showResult({ error: String(e) });
  }
}

$('btn-lookup').addEventListener('click', lookup);
$('btn-validate').addEventListener('click', validate);
$('license-key').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') lookup();
});

if (window.__TAURI__) {
  $('platform').textContent = 'Tauri 桌面版';
} else {
  $('platform').textContent = '浏览器预览';
}
