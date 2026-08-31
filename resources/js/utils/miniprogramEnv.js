/**
 * 小程序 web-view 环境（Vue SPA）
 */
const FLAG = 'hwt_from_miniprogram';

export function isMiniProgram() {
  try {
    if (typeof window !== 'undefined' && window.__wxjs_environment === 'miniprogram') {
      return true;
    }
  } catch {
    /* ignore */
  }

  const ua = typeof navigator !== 'undefined' ? navigator.userAgent || '' : '';
  if (/miniProgram/i.test(ua)) return true;

  try {
    const q = new URLSearchParams(window.location.search || '');
    if (q.get('from') === 'miniprogram') {
      sessionStorage.setItem(FLAG, '1');
      return true;
    }
  } catch {
    /* ignore */
  }

  try {
    return sessionStorage.getItem(FLAG) === '1';
  } catch {
    return false;
  }
}

export function markFromMiniProgram() {
  try {
    sessionStorage.setItem(FLAG, '1');
  } catch {
    /* ignore */
  }
}

export function shouldEscapePayment(method) {
  if (!isMiniProgram()) return false;
  const m = String(method || '').toLowerCase();
  if (m === 'balance' || m === 'prepaid' || m === 'mock') return false;
  return ['wxpay', 'wechat', 'alipay', 'stripe', 'paypal'].includes(m) || !m;
}

export async function copyCurrentUrl() {
  const url = window.location.href.split('#')[0];
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(url);
    return url;
  }
  const ta = document.createElement('textarea');
  ta.value = url;
  ta.setAttribute('readonly', '');
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.select();
  document.execCommand('copy');
  document.body.removeChild(ta);
  return url;
}
