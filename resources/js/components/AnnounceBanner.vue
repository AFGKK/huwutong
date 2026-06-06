<template>
    <div v-if="banners.length" class="announce-banner-wrapper">
        <div
            v-for="banner in banners"
            :key="banner.id"
            :class="['announce-banner', `announce-banner--${banner.type}`, `announce-banner--${banner.position}`]"
        >
            <div class="announce-banner__content">
                <span v-if="banner.title" class="announce-banner__title">{{ banner.title }}：</span>
                <span v-html="banner.content" class="announce-banner__text"></span>
                <el-link
                    v-if="banner.link_url"
                    :href="banner.link_url"
                    type="primary"
                    :underline="false"
                    class="announce-banner__link"
                    target="_blank"
                >
                    {{ banner.link_text || '查看详情' }}
                    <el-icon class="el-icon--right"><TopRight /></el-icon>
                </el-link>
            </div>
            <el-button
                v-if="banner.can_close"
                text
                size="small"
                class="announce-banner__close"
                @click="dismiss(banner.id)"
            >
                <el-icon><Close /></el-icon>
            </el-button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getActiveBanners } from '@/api/announce-banners';
import { Close, TopRight } from '@element-plus/icons-vue';

const banners = ref([]);
const dismissedIds = ref(new Set());

async function fetchBanners() {
    try {
        const res = await getActiveBanners();
        if (res.data) {
            banners.value = (res.data || []).filter(b => !dismissedIds.value.has(b.id));
        }
    } catch {
        // 静默失败，不影响页面
    }
}

function dismiss(id) {
    dismissedIds.value.add(id);
    banners.value = banners.value.filter(b => b.id !== id);
    // 保存到 localStorage 避免刷新后再次显示（只保存 24 小时）
    const key = `banner_dismissed_${id}`;
    localStorage.setItem(key, String(Date.now()));
}

// 恢复已关闭但未过期的横幅
function restoreDismissed() {
    const now = Date.now();
    const oneDay = 24 * 60 * 60 * 1000;
    for (const key of Object.keys(localStorage)) {
        if (key.startsWith('banner_dismissed_')) {
            const ts = parseInt(localStorage.getItem(key), 10);
            if (now - ts > oneDay) {
                localStorage.removeItem(key);
            } else {
                const id = parseInt(key.replace('banner_dismissed_', ''), 10);
                dismissedIds.value.add(id);
            }
        }
    }
}

onMounted(() => {
    restoreDismissed();
    fetchBanners();
});
</script>

<style scoped>
.announce-banner-wrapper {
    width: 100%;
}

.announce-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 8px 40px 8px 16px;
    font-size: 14px;
    line-height: 1.5;
    position: relative;
    gap: 8px;
}

.announce-banner--top {
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.announce-banner--bottom {
    border-top: 1px solid rgba(0, 0, 0, 0.06);
}

.announce-banner--info {
    background: #ecf5ff;
    color: #606266;
}

.announce-banner--success {
    background: #f0f9eb;
    color: #606266;
}

.announce-banner--warning {
    background: #fdf6ec;
    color: #606266;
}

.announce-banner--danger {
    background: #fef0f0;
    color: #606266;
}

.announce-banner__content {
    display: flex;
    align-items: center;
    gap: 4px;
    flex: 1;
    justify-content: center;
    flex-wrap: wrap;
}

.announce-banner__title {
    font-weight: 600;
}

.announce-banner__text {
    word-break: break-all;
}

.announce-banner__link {
    margin-left: 4px;
    font-size: 13px;
}

.announce-banner__close {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}
</style>
