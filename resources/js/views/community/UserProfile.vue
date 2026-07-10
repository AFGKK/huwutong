<template>
  <div class="user-profile-page">
    <!-- 头部 -->
    <div class="profile-header">
      <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6">
          <div class="w-20 h-20 rounded-full bg-primary-50 flex items-center justify-center text-primary-600 font-bold text-2xl overflow-hidden ring-4 ring-white shadow-lg">
            <img v-if="user?.avatar" :src="user.avatar" class="w-full h-full object-cover" />
            <span v-else>{{ (user?.name || '?').charAt(0) }}</span>
          </div>
          <div class="flex-1 text-center sm:text-left">
            <h1 class="text-2xl font-bold text-gray-900">{{ user?.name || '用户' }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ user?.bio || '这个人很懒，什么都没写...' }}</p>
            <div class="flex items-center justify-center sm:justify-start gap-6 mt-4 text-sm text-gray-500">
              <span>📝 <strong>{{ stats.posts_count || 0 }}</strong> 帖子</span>
              <span>❤️ <strong>{{ stats.likes_count || 0 }}</strong> 获赞</span>
              <span>⭐ <strong>{{ stats.favorites_count || 0 }}</strong> 收藏</span>
            </div>
            <div class="mt-4">
              <button v-if="isLoggedIn && user?.id && user.id !== myId"
                class="px-5 py-2 text-sm font-medium rounded-lg transition"
                :class="isFollowing ? 'bg-gray-100 text-gray-500 hover:bg-gray-200' : 'bg-primary-600 text-white hover:bg-primary-700'"
                @click="toggleFollow">
                {{ isFollowing ? '已关注' : '+ 关注' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 帖子列表 -->
    <div class="max-w-4xl mx-auto px-4 py-6">
      <div class="flex items-center gap-4 mb-6 border-b border-gray-100">
        <button v-for="tab in tabs" :key="tab.key"
          class="px-4 py-3 text-sm font-medium transition border-b-2"
          :class="activeTab === tab.key ? 'text-primary-600 border-primary-600' : 'text-gray-500 border-transparent hover:text-gray-700'"
          @click="activeTab = tab.key; loadUserPosts()">
          {{ tab.label }}
        </button>
      </div>

      <div v-loading="loading" class="space-y-4">
        <div v-for="post in posts" :key="post.id" class="bg-white rounded-xl border border-gray-100 p-5 hover:shadow-sm transition cursor-pointer" @click="openDetail(post)">
          <div class="text-sm text-gray-800 leading-relaxed mb-3 line-clamp-3" v-html="post.content"></div>
          <div v-if="post.images" class="flex gap-2 mb-3">
            <img v-for="(img, i) in getImages(post).slice(0, 3)" :key="i" :src="img" class="w-20 h-20 rounded-lg object-cover border" />
          </div>
          <div class="flex items-center gap-4 text-xs text-gray-400 pt-2 border-t border-gray-50">
            <span>❤️ {{ post.likes_count || 0 }}</span>
            <span>💬 {{ post.replies_count || 0 }}</span>
            <span class="ml-auto">{{ timeAgo(post.created_at) }}</span>
          </div>
        </div>
        <div v-if="hasMore && !loading" class="text-center py-4">
          <el-button :loading="loadingMore" @click="loadMore">加载更多</el-button>
        </div>
        <el-empty v-if="!loading && !posts.length" description="暂无内容" :image-size="60" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import apiClient from '@/api/client.js'

const route = useRoute()
const userId = Number(route.params.id)
const isLoggedIn = !!localStorage.getItem('auth_token')
const myId = isLoggedIn ? (JSON.parse(localStorage.getItem('user') || '{}')?.id || 0) : 0

const user = ref(null)
const stats = ref({})
const isFollowing = ref(false)
const posts = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)
const activeTab = ref('posts')
const tabs = [
  { key: 'posts', label: '📝 帖子' },
  { key: 'liked', label: '❤️ 赞过' },
]

function getImages(post) {
  if (!post.images) return []
  return typeof post.images === 'string' ? JSON.parse(post.images) : post.images
}

function timeAgo(t) {
  if (!t) return ''
  const d = new Date(t)
  const diff = Math.floor((Date.now() - d.getTime()) / 1000)
  if (diff < 60) return '刚刚'
  if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
  if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
  if (diff < 2592000) return Math.floor(diff / 86400) + '天前'
  return d.toLocaleDateString('zh-CN')
}

async function loadUser() {
  try {
    const headers = isLoggedIn ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
    const res = await apiClient.get(`/moments/users/${userId}`, { headers })
    const data = res.data?.data || {}
    user.value = data.user || data
    stats.value = data.stats || {}
    isFollowing.value = data.is_following || false
  } catch { /* ignore */ }
}

async function loadUserPosts() {
  loading.value = true
  page.value = 1
  try {
    const headers = isLoggedIn ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
    const endpoint = activeTab.value === 'liked' ? `/moments/users/${userId}/likes` : `/moments?user_id=${userId}`
    const res = await apiClient.get(endpoint, {
      params: { per_page: 20 },
      headers,
    })
    posts.value = res.data?.data?.data || res.data?.data || []
    hasMore.value = (res.data?.meta?.last_page || 1) > 1
  } catch { posts.value = [] }
  finally { loading.value = false }
}

async function loadMore() {
  if (loadingMore.value || !hasMore.value) return
  loadingMore.value = true
  page.value++
  try {
    const headers = isLoggedIn ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
    const endpoint = activeTab.value === 'liked' ? `/moments/users/${userId}/likes` : `/moments?user_id=${userId}`
    const res = await apiClient.get(endpoint, {
      params: { per_page: 20, page: page.value },
      headers,
    })
    const data = res.data?.data?.data || res.data?.data || []
    posts.value.push(...data)
    hasMore.value = page.value < (res.data?.meta?.last_page || 1)
  } catch { /* ignore */ }
  finally { loadingMore.value = false }
}

async function toggleFollow() {
  try {
    if (isFollowing.value) {
      await apiClient.post(`/moments/users/${userId}/unfollow`, {}, {
        headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
      })
      isFollowing.value = false
    } else {
      await apiClient.post(`/moments/users/${userId}/follow`, {}, {
        headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
      })
      isFollowing.value = true
    }
  } catch { ElMessage.error('操作失败') }
}

function openDetail(post) {
  window.open(`/build/plaza/${post.id}`, '_blank')
}

onMounted(() => {
  loadUser()
  loadUserPosts()
})
</script>

<style scoped>
.user-profile-page { min-height: 100vh; background: #f8fafc; }
.profile-header { background: #fff; border-bottom: 1px solid #f1f5f9; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
