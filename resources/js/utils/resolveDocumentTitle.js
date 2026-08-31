/**
 * Resolve a bilingual document/page title for a Vue Router location.
 * Prefer admin.menu keys derived from the path; fall back to meta.title.
 */
import i18n from '@/i18n';

function pathToMenuKey(path) {
    return String(path || '')
        .replace(/^\//, '')
        .replace(/[/\-]/g, '_')
        .replace(/_+/g, '_');
}

/** Public / blank-layout route name → locale key under `route_titles.*` */
const PUBLIC_TITLE_KEYS = {
    Register: 'register',
    ForgotPassword: 'forgot_password',
    Checkout: 'checkout',
    StatusPage: 'status',
    FollowingFeed: 'following_feed',
    Appeal: 'appeal',
    InteractiveDemo: 'demo',
    TenantSelect: 'tenant_select',
    Setup: 'setup',
    QrConfirm: 'qr_confirm',
    OaEditor: 'oa_editor',
    OaArticleDetail: 'oa_article',
    PlazaDetail: 'plaza_detail',
    Community: 'community',
    UserProfile: 'user_profile',
    Channels: 'channels',
    Login: 'login',
    BlogPublic: 'blog',
    Blog: 'blog',
};

export function resolveDocumentTitle(to, options = {}) {
    const t = i18n.global.t;
    const te = i18n.global.te;

    const isPublicPage = [
        'Login', 'Register', 'ForgotPassword', 'Appeal', 'StatusPage', 'Community',
        'Channels', 'UserProfile', 'PlazaDetail', 'InteractiveDemo', 'FollowingFeed',
        'OaEditor', 'OaArticleDetail', 'Checkout', 'BlogPublic', 'Blog',
    ].includes(to?.name);

    let pageTitle = options.pageTitle || '';

    if (!pageTitle && to?.name && PUBLIC_TITLE_KEYS[to.name]) {
        const key = `route_titles.${PUBLIC_TITLE_KEYS[to.name]}`;
        if (te(key)) pageTitle = t(key);
    }

    if (!pageTitle && to?.path) {
        const segments = String(to.path).replace(/\/$/, '').split('/').filter(Boolean);
        for (let i = segments.length; i >= 1; i--) {
            const key = `admin.menu.${pathToMenuKey('/' + segments.slice(0, i).join('/'))}`;
            if (te(key)) {
                pageTitle = t(key);
                break;
            }
        }
    }

    if (!pageTitle && to?.meta?.titleKey) {
        const key = String(to.meta.titleKey);
        if (te(key)) pageTitle = t(key);
    }

    if (!pageTitle && to?.meta?.title) {
        pageTitle = String(to.meta.title);
    }

    if (!pageTitle) return null;

    if (options.brandOnly) {
        return pageTitle;
    }

    const brand = options.brand
        || (isPublicPage ? t('app_name') : t('admin.brand_suffix'));
    return `${pageTitle} - ${brand}`;
}

/** Re-apply title for the current route after locale change. */
export function refreshDocumentTitle(route) {
    const title = resolveDocumentTitle(route);
    if (title) {
        document.title = title;
    }
    return title;
}
