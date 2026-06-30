<template>
  <div class="feed-page">
    <div class="feed-page-header">
      <h2>🔥 关注动态</h2>
      <p>查看您关注的账号最新发布的内容</p>
      <div class="feed-page-controls">
        <el-input v-model="searchQuery" size="small" placeholder="🔍 搜索动态..." clearable style="width:220px" @input="filterFeed" />
        <el-button size="small" @click="loadData" :loading="loading">🔄 刷新</el-button>
      </div>
    </div>

    <div v-if="loading" style="text-align:center;padding:60px 0"><el-icon class="is-loading" :size="28"><Loading /></el-icon></div>
    <div v-else-if="filteredItems.length === 0" style="text-align:center;padding:60px 0;color:#909399">
      <el-empty :description="searchQuery ? '没有匹配的动态' : '关注的账号还没有发布内容'" :image-size="60" />
    </div>
    <div v-else class="feed-page-list">
      <div v-for="item in filteredItems" :key="item.id" class="feed-page-card" @click="openArticle(item)">
        <div class="feed-page-card-header">
          <el-avatar :size="32" :src="item.account?.avatar" class="feed-page-avatar">{{ item.account?.name?.charAt(0) || '?' }}</el-avatar>
          <div>
            <div class="feed-page-account-name">{{ item.account?.name || '未知账号' }}</div>
            <div class="feed-page-time">{{ formatDate(item.published_at) }}</div>
          </div>
          <el-tag v-if="item.account?.slug === 'hwt-blog'" size="small" type="primary" style="margin-left:auto">博客</el-tag>
        </div>
        <div class="feed-page-card-body">
          <div v-if="getCoverImage(item)" class="feed-page-cover"><img :src="getCoverImage(item)" :alt="item.title" /></div>
          <div class="feed-page-content">
            <h3 class="feed-page-title">{{ item.title }}</h3>
            <p class="feed-page-summary" v-if="item.summary">{{ item.summary }}</p>
          </div>
        </div>
        <div class="feed-page-card-footer">
          <span>👍 {{ item.likes_count }}</span>
          <span>⭐ {{ item.favorites_count }}</span>
          <span>💬 {{ item.comments_count }}</span>
          <span style="margin-left:auto;font-size:12px;color:#409eff">阅读全文 →</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Loading } from '@element-plus/icons-vue'
import { getFollowingFeed } from '@/api/interaction'

function extractFirstImage(html) {
  if (!html) return ''
  var m = html.match(/<img[^>]+src=["']([^"']+)["']/)
  return m ? m[1] : ''
}

function getCoverImage(item) {
  return item?.cover_image || (item?.content ? extractFirstImage(item.content) : '')
}

const loading = ref(false)
const items = ref([])
const filteredItems = ref([])
const searchQuery = ref('')

async function loadData() {
  loading.value = true
  try {
    const res = await getFollowingFeed({ limit: 50 })
    items.value = res.data?.data?.items || []
    filteredItems.value = items.value
  } catch { /* ignore */ }
  finally { loading.value = false }
}

function filterFeed() {
  const q = searchQuery.value.toLowerCase()
  if (!q) { filteredItems.value = items.value; return }
  filteredItems.value = items.value.filter(item =>
    item.title?.toLowerCase().includes(q) ||
    item.summary?.toLowerCase().includes(q) ||
    item.account?.name?.toLowerCase().includes(q)
  )
}

function openArticle(item) {
  window.open('/build/oa-article/' + item.id, '_blank')
}

function formatDate(date) {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = now - d
  if (diff < 3600000) return Math.round(diff / 60000) + ' 分钟前'
  if (diff < 86400000) return Math.round(diff / 3600000) + ' 小时前'
  if (diff < 172800000) return '昨天'
  return d.toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit' })
}

onMounted(loadData)
</script>

<style scoped>
.feed-page { max-width: 800px; margin: 0 auto; padding: 24px 16px; }
.feed-page-header { margin-bottom: 24px; }
.feed-page-header h2 { margin: 0 0 4px; font-size: 22px; }
.feed-page-header p { margin: 0 0 12px; color: #909399; font-size: 13px; }
.feed-page-controls { display: flex; gap: 8px; align-items: center; }
.feed-page-list { display: flex; flex-direction: column; gap: 12px; }
.feed-page-card {
  border: 1px solid #eee; border-radius: 10px; padding: 16px;
  cursor: pointer; transition: all .2s; background: #fff;
}
.feed-page-card:hover { border-color: #409eff; box-shadow: 0 2px 12px rgba(64,158,255,0.08); }
.feed-page-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.feed-page-avatar { flex-shrink: 0; }
.feed-page-account-name { font-size: 14px; font-weight: 600; color: #303133; }
.feed-page-time { font-size: 11px; color: #c0c4cc; }
.feed-page-card-body { display: flex; gap: 14px; }
.feed-page-cover { width: 140px; height: 90px; border-radius: 6px; overflow: hidden; flex-shrink: 0; }
.feed-page-cover img { width: 100%; height: 100%; object-fit: cover; }
.feed-page-content { flex: 1; min-width: 0; }
.feed-page-title { margin: 0 0 6px; font-size: 16px; font-weight: 600; color: #303133; line-height: 1.4; }
.feed-page-summary { margin: 0; font-size: 13px; color: #909399; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.feed-page-card-footer { display: flex; align-items: center; gap: 14px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f0f0f0; font-size: 12px; color: #c0c4cc; }
</style>
