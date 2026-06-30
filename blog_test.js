const API = '/api/public';
let currentView = 'blog';

function switchView(view) {
    currentView = view;
    document.getElementById('tab-blog').className = view === 'blog'
        ? 'px-5 py-2 rounded-full font-medium text-sm bg-primary-600 text-white shadow-sm'
        : 'px-5 py-2 rounded-full font-medium text-sm bg-white text-gray-600 hover:bg-gray-100 border border-gray-200';
    document.getElementById('tab-changelog').className = view === 'changelog'
        ? 'px-5 py-2 rounded-full font-medium text-sm bg-primary-600 text-white shadow-sm'
        : 'px-5 py-2 rounded-full font-medium text-sm bg-white text-gray-600 hover:bg-gray-100 border border-gray-200';
    document.getElementById('blog-grid').classList.toggle('hidden', view !== 'blog');
    document.getElementById('changelog-view').classList.toggle('hidden', view !== 'changelog');
    if (view === 'changelog' && document.getElementById('changelog-view').children.length === 0) loadChangelog();
}

function fmtDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function stripHtml(html) {
    var d = document.createElement('div');
    d.innerHTML = html || '';
    return d.textContent || '';
}

function extractFirstImage(html) {
    if (!html) return '';
    var m = html.match(/<img[^>]+src=["']([^"']+)["']/);
    return m ? m[1] : '';
}

function typeLabel(t) {
    return t === 'blog' ? '博客' : t === 'changelog' ? '更新日志' : '发布说明';
}

function typeColor(t) {
    return t === 'blog' ? 'bg-blue-100 text-blue-700' : t === 'changelog' ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700';
}

function readingTime(html) {
    var text = stripHtml(html || '');
    var words = text.replace(/[\s]+/g, ' ').trim().split(' ').length;
    return Math.max(1, Math.ceil(words / 200));
}

function renderStars(rating) {
    var r = Math.round(rating || 0);
    return Array(5).fill(0).map(function(_, i) {
        return i < r ? '★' : '☆';
    }).join('');
}

console.log('Blog script loaded successfully');
console.log('Functions available:', typeof switchView, typeof fmtDate, typeof readingTime);
