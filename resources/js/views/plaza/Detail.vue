<template>
  <div class="oa-article-page">
    <!-- 顶部导航 -->
    <div class="oa-article-topbar" :class="{ 'topbar-hidden': topbarHidden }">
      <div class="topbar-left">
        <el-button text size="small" @click="goBack">
          <el-icon><ArrowLeft /></el-icon> {{ tp('back_to_community') }}
        </el-button>
      </div>
      <div class="topbar-right">
        <el-button text size="small" @click="toggleFontSize" :title="tp('font_size')">
          <el-icon><ZoomIn /></el-icon>
        </el-button>
        <el-button text size="small" :type="useSerif ? 'primary' : 'default'" @click="useSerif = !useSerif" :title="tp('serif_font')">Aa</el-button>
        <el-button text size="small" :type="isSpeaking ? 'primary' : 'default'" @click="toggleSpeech" :title="tp('speech')">TTS</el-button>
        <el-dropdown trigger="click" @command="setTheme">
          <el-button text size="small" :title="tp('theme')">T</el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item v-for="opt in themeOptions" :key="opt.command" :command="opt.command">{{ opt.label }}</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
        <el-dropdown trigger="click" @command="handleShareCommand">
          <el-button text size="small">
            <el-icon><Share /></el-icon> {{ t('actions.share') }} <span v-if="shareRewardPoints > 0" style="color:#e6a23c;font-size:11px">+{{ shareRewardPoints }}</span> <el-icon><ArrowDown /></el-icon>
          </el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="friend">{{ shareTargetLabels.friend }}</el-dropdown-item>
              <el-dropdown-item command="chat">{{ shareTargetLabels.chat }}</el-dropdown-item>
              <el-dropdown-item command="circle">{{ shareTargetLabels.circle }}</el-dropdown-item>
              <el-dropdown-item command="weibo" divided>{{ shareTargetLabels.weibo_reward }}</el-dropdown-item>
              <el-dropdown-item command="copy">{{ shareTargetLabels.copy_reward }}</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
    </div>

    <!-- 加载/错误 -->
    <div v-if="loading" style="text-align:center;padding:80px 0"><el-icon class="is-loading" :size="28"><Loading /></el-icon></div>
    <div v-else-if="error" style="text-align:center;padding:80px 0">
      <el-empty :description="tp('error.not_found')" :image-size="80" />
      <el-button @click="goBack" style="margin-top:12px">{{ tp('back_to_community') }}</el-button>
    </div>

    <!-- 帖子内容 -->
    <div v-else-if="post" class="oa-article-container" :class="['theme-' + currentTheme, { 'focus-active': focusMode }]">
      <!-- 阅读进度条 -->
      <div class="oa-reading-progress" :style="{ width: readingProgress + '%' }"></div>
      <!-- 阅读进度环 -->
      <div class="oa-reading-ring" @click="focusMode = !focusMode">
        <svg width="44" height="44" viewBox="0 0 44 44">
          <circle cx="22" cy="22" r="18" fill="none" stroke="#e8e8e8" stroke-width="3" />
          <circle cx="22" cy="22" r="18" fill="none" stroke="#0f172a" stroke-width="3"
            stroke-linecap="round" :stroke-dasharray="113.1"
            :stroke-dashoffset="113.1 - (readingProgress / 100) * 113.1"
            transform="rotate(-90 22 22)" style="transition: stroke-dashoffset 0.2s" />
        </svg>
        <span class="oa-reading-ring-text">{{ readingProgress }}%</span>
      </div>
      <div v-if="focusMode" class="oa-focus-overlay" @click="focusMode = false"></div>

      <article class="oa-article-body" :style="{ fontSize: fontSize + 'px', fontFamily: useSerif ? 'Georgia, serif' : '' }">
        <header class="oa-article-header" :class="{ 'focus-hidden': focusMode }">
          <h1 class="oa-article-title">{{ plainTitle }}{{ (post.content?.replace(/<[^>]*>/g, '') || '').length > 100 ? '...' : '' }}</h1>
          <div class="oa-article-meta-row">
            <span class="oa-meta-author">
              <el-avatar :size="22" :src="post.user?.avatar_url" style="vertical-align:middle;margin-right:4px">{{ post.user?.name?.charAt(0) || '?' }}</el-avatar>
              {{ post.user?.name || tp('user_fallback') }}
              <template v-if="isLoggedIn">
                <el-button v-if="post.user?.id && post.user.id !== myId" text size="small" style="font-size:11px;padding:0 4px;height:auto;margin-left:4px" :type="isFollowingUser ? 'default' : 'primary'" @click="toggleFollowUser">{{ isFollowingUser ? tp('following') : tp('follow') }}</el-button>
                <el-button v-if="post.user?.id && post.user.id !== myId" text size="small" type="primary" style="font-size:11px;padding:0 4px;height:auto;margin-left:2px" @click="sendPrivateMessage">{{ tp('send_message') }}</el-button>
              </template>
              <span v-else style="margin-left:4px;font-size:11px;color:#0f172a;cursor:pointer" @click="redirectToLoginForDm">{{ tp('login_to_message') }}</span>
            </span>
            <span class="oa-meta-time">{{ formatFullTime(post.created_at) }}</span>
            <span class="oa-meta-sep">·</span>
            <span class="oa-meta-time">{{ tp('reading_minutes', { n: readingTime }) }}</span>
            <span class="oa-meta-time">{{ tp('views_count', { n: post.views_count || 0 }) }}</span>
          </div>
        </header>

        <!-- 🔒 付费墙 -->
        <div v-if="post.is_paid && !post.has_purchased" class="oa-paywall">
            <div class="oa-paywall-blur" v-if="post.content_preview">
                <div class="oa-paywall-preview">{{ post.content_preview }}...</div>
            </div>
            <div class="oa-paywall-overlay">
                <div class="oa-paywall-icon">🔒</div>
                <h3 class="oa-paywall-title">{{ tp('paywall.title') }}</h3>
                <p class="oa-paywall-desc">{{ tp('paywall.desc') }}</p>
                <div class="oa-paywall-price">
                    <PointsIcon v-if="post.price_type === 'points'" :size="26" /> {{ post.price_type === 'points' ? tp('paywall.points_price', { n: post.price }) : '¥' + post.price }}
                </div>
                <div class="oa-paywall-actions">
                    <el-button type="primary" :loading="purchasing" @click="purchasePost">
                        <PointsIcon :size="18" v-if="post.price_type === 'points'" /> {{ post.price_type === 'points' ? tp('paywall.unlock_points') : tp('paywall.unlock_pay') }}
                    </el-button>
                </div>
            </div>
        </div>

        <!-- 正文 -->
        <div v-else class="oa-article-content" v-html="renderedContent"></div>

        <!-- 图片 -->
        <div v-if="post.images?.length" class="plaza-images-grid">
          <div v-for="(img, i) in post.images" :key="i" class="plaza-image-item" @click="openLightbox(img)">
            <img :src="img" class="plaza-image" />
          </div>
        </div>

        <!-- 视频 -->
        <div v-if="post.video" class="plaza-video-wrap">
          <video :src="post.video" controls class="plaza-video"></video>
        </div>

        <!-- 投票 -->
        <div v-if="post.poll" class="plaza-detail-poll">
          <div class="plaza-detail-poll-title">{{ post.poll.question }}</div>
          <div class="plaza-detail-poll-options">
            <div v-for="opt in (post.poll.options || [])" :key="opt.id" class="plaza-detail-poll-option" :class="{ voted: opt.voted }" @click="isLoggedIn ? votePost(opt.id) : redirectToLogin()">
              <span class="plaza-detail-poll-label">{{ opt.label }}</span>
              <span class="plaza-detail-poll-pct">{{ opt.percent || 0 }}%</span>
              <div class="plaza-detail-poll-bar-wrap">
                <div class="plaza-detail-poll-bar" :style="{ width: (opt.percent || 0) + '%' }"></div>
              </div>
            </div>
          </div>
          <div class="plaza-detail-poll-footer">{{ pollFooterText(post.poll.total_votes || 0, post.poll.voted) }}</div>
        </div>

        <!-- 标签 -->
        <div v-if="post.tags?.length" class="plaza-detail-tags" style="margin-top:16px">
          <span v-for="tag in post.tags" :key="tag.id" class="plaza-detail-tag" @click="goTag(tag.slug)">#{{ tag.name }}</span>
        </div>
      </article>

      <!-- 互动工具栏 -->
      <div class="oa-article-toolbar" :class="{ 'focus-hidden': focusMode }">
        <div class="toolbar-left">
          <el-button :type="post.is_liked ? 'primary' : 'default'" size="small" @click="isLoggedIn ? toggleLike() : redirectToLogin()" :loading="likeLoading">
            {{ tp('like') }} {{ post.likes_count }}
          </el-button>
          <el-button :type="post.is_favorited ? 'warning' : 'default'" size="small" @click="isLoggedIn ? toggleFavorite() : redirectToLogin()" :loading="favLoading">
            {{ tp('favorite') }}
          </el-button>
          <el-button text type="warning" @click="openTipDialog" :title="tp('tip')">
            <PointsIcon :size="16" /> {{ tp('tip') }}
          </el-button>
        </div>
        <div class="toolbar-right">
          <el-button v-if="isLoggedIn && post.user?.id === myId" size="small" text type="danger" @click="deletePost">{{ t('actions.delete') }}</el-button>
          <el-button v-else-if="isLoggedIn && post.user?.id !== myId" size="small" text type="warning" @click="reportPost">{{ tp('report') }}</el-button>
        </div>
      </div>

      <el-divider />

      <!-- 评论区 -->
      <div class="oa-comments-section">
        <h3 style="margin:0 0 12px;font-size:16px">{{ tp('comments_title', { n: comments.length }) }}</h3>
        <div class="oa-comment-input">
          <div v-if="!isLoggedIn" class="oa-comment-login-tip" style="text-align:center;padding:20px;background:#f9f9f9;border-radius:8px;margin-bottom:12px">
            <span style="color:#999;font-size:13px">{{ tp('login_to_comment') }}</span>
            <el-button size="small" text type="primary" @click="redirectToLogin" style="margin-left:8px">{{ tp('login_now') }}</el-button>
          </div>
          <div v-else class="oa-comment-input">
            <div style="display:flex;gap:8px;align-items:flex-start">
              <el-avatar :size="32" :src="currentUserAvatar" style="flex-shrink:0">{{ myId ? '?' : '' }}</el-avatar>
              <div style="flex:1">
                <el-input v-model="commentText" type="textarea" :rows="2" :placeholder="tp('comment_placeholder')" maxlength="1000" @focus="loadAiSuggestions" />
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px">
                  <span v-for="(sg, si) in aiSuggestions" :key="si"
                    class="px-2.5 py-1 text-xs rounded-full border border-gray-200 text-gray-500 cursor-pointer hover:bg-primary-50 hover:border-primary-300 hover:text-primary-600 transition"
                    @click="commentText = sg; aiSuggestions = []">
                    {{ sg }}
                  </span>
                  <el-button v-if="aiSuggestions.length" size="small" text @click="aiSuggestions = []" style="font-size:11px">{{ t('actions.cancel') }}</el-button>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
                  <span></span>
                  <el-button size="small" type="primary" :loading="commentSubmitting" @click="submitComment">{{ tp('submit_comment') }}</el-button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="comments.length" class="oa-comment-list">
          <div v-for="c in comments" :key="c.id" class="oa-comment-item" :id="'comment-' + c.id">
            <el-avatar :size="32" :src="c.user?.avatar_url" class="oa-comment-avatar">{{ c.user?.name?.charAt(0) || '?' }}</el-avatar>
            <div class="oa-comment-body">
              <div class="oa-comment-author">{{ c.user?.name || tp('user_fallback') }} <span class="oa-comment-time">{{ formatFullTime(c.created_at) }}</span></div>
              <div class="oa-comment-text">{{ c.content }}</div>
              <div class="oa-comment-actions">
                <el-button text size="small" @click="isLoggedIn ? (replyTo = c, replyText = '') : redirectToLogin()" style="font-size:12px;color:#999">{{ tp('reply') }}</el-button>
                <el-button v-if="c.user_id === myId" text size="small" type="danger" @click="deleteComment(c)" style="font-size:12px;padding:0">{{ t('actions.delete') }}</el-button>
              </div>
              <div v-if="c.replies?.length" class="oa-sub-replies">
                <div v-for="r in c.replies" :key="r.id" class="oa-sub-reply">
                  <el-avatar :size="20" :src="r.user?.avatar_url" style="flex-shrink:0;vertical-align:middle">{{ r.user?.name?.charAt(0) || '?' }}</el-avatar>
                  <span class="oa-reply-author">{{ r.user?.name }}：</span>{{ r.content }}
                </div>
              </div>
              <div v-if="replyTo?.id === c.id" class="oa-reply-box" style="display:flex;gap:6px;margin-top:6px">
                <el-avatar :size="24" :src="currentUserAvatar" style="flex-shrink:0">{{ myId ? '?' : '' }}</el-avatar>
                <el-input v-model="replyText" :placeholder="tp('reply_placeholder')" size="small" style="flex:1" maxlength="1000" />
                <el-button size="small" type="primary" :loading="replySubmitting" @click="submitReply(c)">{{ tp('send') }}</el-button>
                <el-button size="small" @click="replyTo = null">{{ t('actions.cancel') }}</el-button>
              </div>
            </div>
          </div>
        </div>
        <div v-else style="text-align:center;padding:16px;color:#999;font-size:13px">{{ tp('no_comments') }}</div>
      </div>
    </div>
  </div>
  <!-- 打赏对话框 -->
  <TipDialog v-model="showTipDialog" :content-id="post?.id || 0" content-type="forum_post" :receiver-id="post?.user?.id || 0" @tipped="onTipped" @view-transactions="showPoints = true" />
  <!-- 积分交易记录 -->
  <PointsHistory v-model="showPoints" />
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Loading, ArrowLeft, ArrowDown, ZoomIn, Share } from '@element-plus/icons-vue'
import apiClient from '@/api/client'
import TipDialog from '@/components/TipDialog.vue'
import PointsHistory from '@/components/PointsHistory.vue'
import PointsIcon from '@/components/PointsIcon.vue'

const { t } = useI18n()
const tp = (key, params) => t('plaza_detail_page.' + key, params)

const themeOptions = computed(() => [
  { command: 'default', label: tp('themes.default') },
  { command: 'sepia', label: tp('themes.sepia') },
  { command: 'night', label: tp('themes.night') },
])

const shareTargetLabels = computed(() => ({
  friend: tp('share_targets.friend'),
  chat: tp('share_targets.chat'),
  circle: tp('share_targets.circle'),
  weibo_reward: tp('share_targets.weibo_reward'),
  copy_reward: tp('share_targets.copy_reward'),
}))

function pollFooterText(total, voted) {
  let s = tp('poll.vote_count', { n: total })
  if (voted) s += tp('poll.voted_suffix')
  return s
}

const route = useRoute()
const router = useRouter()
const myId = ref(0)
const isLoggedIn = ref(false)
const currentUserAvatar = ref('')
const loading = ref(true)
const error = ref(false)
const post = ref(null)
const comments = ref([])
const likeLoading = ref(false)
const favLoading = ref(false)
const commentText = ref('')
const commentSubmitting = ref(false)
const replyTo = ref(null)
const replyText = ref('')
const replySubmitting = ref(false)
const isFollowingUser = ref(false)
const fontSize = ref(16)
const fontSizes = [14, 16, 18, 20, 22]
const fontSizeIndex = ref(1)
const useSerif = ref(false)
const currentTheme = ref(localStorage.getItem('plazaTheme') || 'default')
const focusMode = ref(false)
const isSpeaking = ref(false)
const speechRate = ref(1)
const readingProgress = ref(0)
const topbarHidden = ref(false)
const showTipDialog = ref(false)
const showPoints = ref(false)
const purchasing = ref(false)

async function purchasePost() {
  if (!isLoggedIn.value) { window.location.href = '/login'; return }
  purchasing.value = true
  try {
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    const res = await apiClient.post(`/moments/${post.value.id}/purchase`, {}, { headers: h })
    post.value.has_purchased = true
    post.value.content = res.data?.data?.content || post.value.content
    ElMessage.success(tp('toast.unlocked'))
  } catch (e) {
    ElMessage.error(e.response?.data?.message || tp('toast.unlock_failed'))
  } finally { purchasing.value = false }
}

const renderedContent = computed(() => {
  if (!post.value?.content) return ''
  // 如果是纯文本(不含HTML标签)，转成HTML显示
  if (!/&lt;/.test(post.value.content) && !/</.test(post.value.content)) {
    return post.value.content.replace(/\n/g, '<br>')
  }
  return post.value.content
})

const readingTime = computed(() => {
  if (!post.value?.content) return 1
  const text = post.value.content.replace(/<[^>]*>/g, '')
  const cjk = (text.match(/[\u4e00-\u9fa5]/g) || []).length
  const words = text.split(/\s+/).filter(Boolean).length
  return Math.max(1, Math.ceil((cjk + words) / 300))
})

const plainTitle = computed(() => {
  if (!post.value?.content) return ''
  return post.value.content.replace(/<[^>]*>/g, '').substring(0, 100)
})

async function loadPost() {
  loading.value = true; error.value = false
  try {
    const id = route.params.id
    // 先尝试公开接口，失败则用认证接口
    let postRes, commentRes
    try {
      postRes = await apiClient.get(`/moments/public/${id}`)
      commentRes = await apiClient.get(`/moments/public/${id}/comments`)
    } catch {
      postRes = await apiClient.get(`/moments/${id}`)
      commentRes = await apiClient.get(`/moments/${id}/comments`)
    }
    post.value = postRes.data?.data || null
    comments.value = commentRes.data?.data?.data || commentRes.data?.data || []
    if (!post.value) error.value = true
  } catch { error.value = true }
  finally { loading.value = false }
}

// ── 跳转登录 ──
function redirectToLogin() {
  ElMessage.info(tp('toast.login_required'));
  window.location.href = '/build/login';
}

function redirectToLoginForDm() {
  const uid = post.value?.user?.id
  const target = uid ? `/user-chat?user_id=${uid}` : '/user-chat'
  ElMessage.info(tp('toast.login_required'))
  window.location.href = `/build/login?redirect=${encodeURIComponent(target)}`
}

function sendPrivateMessage() {
  const uid = post.value?.user?.id
  if (!uid || uid === myId.value) return
  if (!isLoggedIn.value) {
    redirectToLoginForDm()
    return
  }
  window.location.href = `/build/user-chat?user_id=${uid}`
}

// ── AI 评论建议 ──
const aiSuggestions = ref([])
let aiSuggestionTimer = null
async function loadAiSuggestions() {
  if (aiSuggestions.value.length || !post.value?.content) return
  clearTimeout(aiSuggestionTimer)
  aiSuggestionTimer = setTimeout(async () => {
    try {
      const content = (post.value.content || '').replace(/<[^>]*>/g, '').substring(0, 300)
      const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
      const res = await apiClient.post('/user-chat/ai-conversation', {
        message: t('plaza_detail_page.ai_comment_prompt') + content
      }, { headers: h })
      const reply = res.data?.data?.reply || ''
      aiSuggestions.value = reply.split('|').filter(Boolean).map(s => s.trim()).slice(0, 3)
    } catch { /* ignore */ }
  }, 300)
}

async function loadUser() {
  try { const res = await apiClient.get('/user'); const user = res.data?.data || {}; myId.value = user.id || 0; isLoggedIn.value = !!user.id; currentUserAvatar.value = user.avatar_url || '' } catch { myId.value = 0; isLoggedIn.value = false; currentUserAvatar.value = '' }
}

function goBack() {
  // 如果有上一页则返回，否则回到社区
  if (window.history.length > 1) {
    router.back()
  } else {
    window.location.href = '/build/community'
  }
}

function goTag(slug) {
  window.location.href = '/build/community?tag=' + encodeURIComponent(slug)
}

function toggleFontSize() { fontSizeIndex.value = (fontSizeIndex.value + 1) % fontSizes.length; fontSize.value = fontSizes[fontSizeIndex.value] }
function setTheme(t) { currentTheme.value = t; localStorage.setItem('plazaTheme', t); }

async function loadFollowStatus() {
  if (!post.value?.user?.id || post.value.user.id === myId.value) return
  try {
    const res = await apiClient.get('/moments/users/' + post.value.user.id + '/follow-status')
    const d = res.data?.data || {}
    isFollowingUser.value = d.is_following || false
  } catch { /* ignore */ }
}

async function toggleFollowUser() {
  if (!post.value?.user?.id) return
  try {
    if (isFollowingUser.value) {
      await apiClient.post('/moments/users/' + post.value.user.id + '/unfollow')
      isFollowingUser.value = false
      ElMessage.success(tp('toast.unfollowed'))
    } else {
      await apiClient.post('/moments/users/' + post.value.user.id + '/follow')
      isFollowingUser.value = true
      ElMessage.success(tp('toast.followed'))
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')) }
}

function openLightbox(src) { window.open(src, '_blank') }

function handleScroll() {
  const scrollTop = window.scrollY || document.documentElement.scrollTop
  const scrollHeight = document.documentElement.scrollHeight - window.innerHeight
  readingProgress.value = scrollHeight > 0 ? Math.min(100, Math.round(scrollTop / scrollHeight * 100)) : 0
  topbarHidden.value = scrollTop > 100
}

function toggleSpeech() {
  if (isSpeaking.value) { window.speechSynthesis.cancel(); isSpeaking.value = false; return }
  if (!post.value?.content) return
  const text = post.value.content.replace(/<[^>]*>/g, '').substring(0, 3000)
  const u = new SpeechSynthesisUtterance(text); u.lang = 'zh-CN'; u.rate = speechRate.value
  u.onend = () => { isSpeaking.value = false }; window.speechSynthesis.speak(u); isSpeaking.value = true
}

async function toggleLike() {
  if (!post.value) return; likeLoading.value = true
  try {
    const res = await apiClient.post(`/moments/${post.value.id}/like`)
    const d = res.data?.data || {}
    post.value.is_liked = d.liked ?? !post.value.is_liked
    post.value.likes_count = d.likes_count ?? (post.value.is_liked ? post.value.likes_count + 1 : Math.max(0, post.value.likes_count - 1))
  } catch { ElMessage.error(t('messages.failed')) }
  finally { likeLoading.value = false }
}

async function toggleFavorite() {
  if (!post.value) return; favLoading.value = true
  try {
    const res = await apiClient.post(`/moments/${post.value.id}/favorite`)
    const d = res.data?.data || {}
    post.value.is_favorited = d.favorited ?? !post.value.is_favorited
    ElMessage.success(post.value.is_favorited ? tp('toast.favorited') : tp('toast.unfavorited'))
  } catch { ElMessage.error(t('messages.failed')) }
  finally { favLoading.value = false }
}

async function votePost(optionId) {
  if (!post.value?.poll) return
  if (post.value.poll.voted) { ElMessage.warning(tp('toast.already_voted')); return }
  try {
    const res = await apiClient.post(`/moments/${post.value.id}/vote`, { option_id: optionId })
    const data = res.data?.data
    if (data) {
      post.value.poll.voted = data.voted
      post.value.poll.total_votes = data.total_votes
      data.options.forEach((opt, i) => {
        if (post.value.poll.options[i]) {
          post.value.poll.options[i].votes = opt.votes
          post.value.poll.options[i].percent = opt.percent
          post.value.poll.options[i].voted = opt.voted
        }
      })
    }
    ElMessage.success(tp('toast.vote_ok'))
  } catch (e) {
    if (e.response?.data?.code === 'ALREADY_VOTED') {
      ElMessage.warning(tp('toast.already_voted'))
    } else {
      ElMessage.error(e.response?.data?.message || tp('toast.vote_failed'))
    }
  }
}

async function submitComment() {
  if (!commentText.value.trim()) return; commentSubmitting.value = true
  try {
    await apiClient.post(`/moments/${post.value.id}/comment`, { content: commentText.value })
    ElMessage.success(tp('toast.comment_ok')); commentText.value = ''
    const res = await apiClient.get(`/moments/${post.value.id}/comments`)
    comments.value = res.data?.data?.data || res.data?.data || []
  } catch { ElMessage.error(tp('toast.comment_failed')) }
  finally { commentSubmitting.value = false }
}

async function submitReply(comment) {
  if (!replyText.value.trim()) return; replySubmitting.value = true
  try {
    await apiClient.post(`/moments/comments/${comment.id}/reply`, { content: replyText.value })
    ElMessage.success(tp('toast.reply_ok')); replyText.value = ''; replyTo.value = null
    const res = await apiClient.get(`/moments/${post.value.id}/comments`)
    comments.value = res.data?.data?.data || res.data?.data || []
  } catch { ElMessage.error(tp('toast.reply_failed')) }
  finally { replySubmitting.value = false }
}

async function deleteComment(c) {
  try {
    await ElMessageBox.confirm(tp('confirm.delete_comment'), t('actions.confirm'))
    await apiClient.delete(`/moments/comments/${c.id}`)
    ElMessage.success(tp('toast.deleted'))
    const res = await apiClient.get(`/moments/${post.value.id}/comments`)
    comments.value = res.data?.data?.data || res.data?.data || []
  } catch (e) { if (e !== 'cancel') ElMessage.error(tp('toast.delete_failed')) }
}

async function deletePost() {
  try {
    await ElMessageBox.confirm(tp('confirm.delete_post'), t('actions.confirm'), { type: 'warning' })
    await apiClient.delete(`/moments/${post.value.id}`)
    ElMessage.success(tp('toast.deleted')); goBack()
  } catch { /* cancelled */ }
}

function reportPost() { ElMessage.info(tp('toast.report_submitted')) }

function handleShareCommand(cmd) {
  const url = window.location.href
  const title = (post.value?.content || '').replace(/<[^>]*>/g, '').substring(0, 50) || tp('post_fallback')
  const commentAnchor = route.hash ? tp('share.comment_anchor', { id: route.hash.replace('#comment-', '') }) : ''
  const shareTitle = title + commentAnchor
  if (cmd === 'copy') {
    navigator.clipboard.writeText(url).then(() => {
      ElMessage.success(tp('toast.link_copied'))
      rewardShare('copy')
    }).catch(() => ElMessage.error(tp('toast.copy_failed')))
    return
  }
  // 以下分享方式需要登录
  if (!isLoggedIn.value) { redirectToLogin(); return }

  if (cmd === 'weibo') {
    window.open('https://service.weibo.com/share/share.php?title=' + encodeURIComponent(shareTitle) + '&url=' + encodeURIComponent(url))
    rewardShare('weibo')
  } else if (cmd === 'friend') {
    ElMessage.info(tp('toast.select_friend'))
  } else if (cmd === 'chat') {
    navigator.clipboard.writeText(url).then(() => {
      ElMessage.success(tp('toast.link_copied_chat'))
    })
  } else if (cmd === 'circle') {
    navigator.clipboard.writeText(url).then(() => {
      ElMessage.success(tp('toast.link_copied_plaza'))
    })
    window.open('/build/community', '_blank')
  } else {
    ElMessage.info(tp('toast.wechat_share'))
  }
}

// ── 打赏 ──
function openTipDialog() {
  if (!isLoggedIn.value) { redirectToLogin(); return }
  showTipDialog.value = true
}
function onTipped(data) {
  // 积分变动已由API处理
}

function formatFullTime(date) {
  if (!date) return ''
  const d = new Date(date)
  return tp('date_full', {
    year: d.getFullYear(),
    month: d.getMonth() + 1,
    day: d.getDate(),
    hours: String(d.getHours()).padStart(2, '0'),
    minutes: String(d.getMinutes()).padStart(2, '0'),
  })
}

onMounted(() => {
  loadPost()
  loadUser()
  window.addEventListener('scroll', handleScroll)
  if (route.hash) {
    setTimeout(() => {
      const el = document.getElementById(route.hash.replace('#', ''))
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    }, 500)
  }
})
watch(post, (val) => { if (val) loadFollowStatus() })
onUnmounted(() => { window.removeEventListener('scroll', handleScroll); window.speechSynthesis?.cancel() })
</script>

<style>
.oa-article-page { max-width: 820px; margin: 0 auto; padding: 80px 0 0; min-height: 100vh; background: #fff; }
.oa-article-topbar {
  position: sticky; top: 80px; z-index: 100; display: flex; align-items: center;
  justify-content: space-between; padding: 8px 16px; background: rgba(255,255,255,.95);
  border-bottom: 1px solid #eee; backdrop-filter: blur(8px); transition: transform .3s;
}
.topbar-hidden { transform: translateY(-100%); }
.topbar-left { display: flex; align-items: center; gap: 4px; }
.topbar-acc-name { font-size: 13px; color: #606266; }
.topbar-right { display: flex; align-items: center; gap: 4px; }
.oa-article-container { padding: 0 20px 40px; max-width: 720px; margin: 0 auto; position: relative; }
.oa-reading-progress { position: fixed; top: 0; left: 0; height: 3px; background: #0f172a; z-index: 1000; transition: width .2s; }
.oa-reading-ring { position: fixed; bottom: 24px; right: 24px; cursor: pointer; z-index: 99; background: rgba(255,255,255,.9); border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,.1); padding: 4px; }
.oa-reading-ring-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 10px; font-weight: 700; color: #0f172a; }
.oa-focus-overlay { position: fixed; inset: 0; background: rgba(255,255,255,.95); z-index: 50; cursor: pointer; }
.oa-article-body { line-height: 1.8; padding-top: 20px; }
.oa-article-header { margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #eee; }
.oa-article-title { font-size: 26px; font-weight: 700; margin: 0 0 12px; line-height: 1.3; }
.oa-article-meta-row { display: flex; align-items: center; gap: 4px; font-size: 13px; color: #909399; flex-wrap: wrap; }
.oa-meta-author { display: flex; align-items: center; gap: 4px; font-weight: 500; color: #606266; }
.oa-meta-sep { color: #ddd; }
.oa-meta-time { color: #909399; }
.focus-hidden { opacity: 0; transition: opacity .3s; }
.focus-active .oa-article-content { max-width: 680px; margin: 0 auto; }
.oa-article-content img { max-width: 100%; border-radius: 6px; margin: 16px auto; display: block; }
.oa-article-content { font-size: inherit; line-height: 1.8; color: #303133; }
.oa-article-toolbar { display: flex; align-items: center; justify-content: space-between; margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; }
.plaza-images-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px; margin: 16px 0; }
.plaza-image-item { border-radius: 8px; overflow: hidden; cursor: pointer; }
.plaza-image { width: 100%; height: 200px; object-fit: cover; transition: transform .2s; }
.plaza-image:hover { transform: scale(1.02); }
.plaza-video-wrap { margin: 16px 0; }
.plaza-video { width: 100%; max-height: 400px; border-radius: 8px; }

/* 投票 */
.plaza-detail-poll { margin: 16px 0; padding: 16px; background: #f8f9fa; border-radius: 8px; }
.theme-night .plaza-detail-poll { background: #252540; }
.theme-sepia .plaza-detail-poll { background: #f5f0e6; }
.plaza-detail-poll-title { font-weight: 600; margin-bottom: 10px; font-size: 15px; color: #333; }
.theme-night .plaza-detail-poll-title { color: #ccc; }
.theme-sepia .plaza-detail-poll-title { color: #5f4b32; }
.plaza-detail-poll-options { display: flex; flex-direction: column; gap: 6px; }
.plaza-detail-poll-option { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border-radius: 6px; cursor: pointer; position: relative; overflow: hidden; background: #fff; border: 1px solid #eee; transition: all .15s; }
.theme-night .plaza-detail-poll-option { background: #1a1a2e; border-color: #333; }
.theme-sepia .plaza-detail-poll-option { background: #fff; border-color: #e0d5c5; }
.plaza-detail-poll-option:hover { border-color: #0f172a; }
.plaza-detail-poll-option.voted { border-color: #0f172a; background: #f1f5f9; }
.theme-night .plaza-detail-poll-option.voted { background: #1a2a4e; }
.theme-sepia .plaza-detail-poll-option.voted { background: #f0eef0; }
.plaza-detail-poll-label { flex: 1; z-index: 1; font-weight: 500; color: #333; }
.theme-night .plaza-detail-poll-label { color: #ccc; }
.theme-sepia .plaza-detail-poll-label { color: #5f4b32; }
.plaza-detail-poll-pct { z-index: 1; font-weight: 600; color: #0f172a; min-width: 36px; text-align: right; font-size: 14px; }
.plaza-detail-poll-bar-wrap { position: absolute; inset: 0; pointer-events: none; }
.plaza-detail-poll-bar { height: 100%; background: rgba(15,23,42,.08); border-radius: 6px; transition: width .3s; }
.plaza-detail-poll-footer { font-size: 12px; color: #999; margin-top: 6px; }

/* 详情标签 */
.plaza-detail-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 16px; }
.plaza-detail-tag { font-size: 12px; color: #0f172a; background: #f1f5f9; padding: 2px 10px; border-radius: 12px; cursor: pointer; transition: all .15s; }
.plaza-detail-tag:hover { background: #0f172a; color: #fff; }
.theme-night .plaza-detail-tag { background: #1a2a4e; color: #66b1ff; }
.theme-night .plaza-detail-tag:hover { background: #0f172a; color: #fff; }
.theme-sepia .plaza-detail-tag { background: #e8ddd0; color: #5f4b32; }
.theme-sepia .plaza-detail-tag:hover { background: #0f172a; color: #fff; }
.oa-comment-list { display: flex; flex-direction: column; gap: 12px; }
.oa-comment-item { display: flex; gap: 10px; }
.oa-comment-avatar { flex-shrink: 0; }
.oa-comment-body { flex: 1; }
.oa-comment-author { font-size: 13px; font-weight: 500; color: #333; }
.oa-comment-time { font-size: 11px; color: #999; font-weight: 400; margin-left: 8px; }
.oa-comment-text { font-size: 14px; line-height: 1.6; margin: 4px 0; }
.oa-comment-actions { display: flex; gap: 8px; }
.oa-sub-replies { margin-top: 8px; padding-left: 12px; border-left: 2px solid #eee; }
.oa-sub-reply { font-size: 13px; line-height: 1.5; padding: 4px 0; }
.oa-reply-author { font-weight: 500; color: #0f172a; }
.oa-reply-box { display: flex; gap: 6px; margin-top: 6px; }

.theme-sepia .oa-article-page { background: #fbf7ed; }
.theme-sepia .oa-article-content { color: #5f4b32; }
.theme-night .oa-article-page { background: #1a1a2e; }
.theme-night .oa-article-topbar { background: rgba(26,26,46,.95); border-color: #333; }
.theme-night .topbar-acc-name, .theme-night .oa-article-title { color: #e0e0e0; }
.theme-night .oa-article-content, .theme-night .oa-comment-author { color: #ccc; }
.theme-night .oa-article-header, .theme-night .oa-article-toolbar { border-color: #333; }
.theme-night .oa-comment-text { color: #999; }

/* ── 付费墙 ── */
.oa-paywall { position: relative; margin: 24px 0; border-radius: 12px; overflow: hidden; background: #f9f9f9; border: 1px solid #e8e8e8; }
.oa-paywall-blur { overflow: hidden; max-height: 120px; position: relative; }
.oa-paywall-blur::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 60px; background: linear-gradient(transparent, #f9f9f9); }
.oa-paywall-preview { padding: 16px 20px; font-size: 14px; line-height: 1.8; color: #999; filter: blur(6px); user-select: none; }
.oa-paywall-overlay { position: relative; text-align: center; padding: 24px 20px 32px; }
.oa-paywall-icon { font-size: 48px; margin-bottom: 8px; }
.oa-paywall-title { font-size: 20px; font-weight: 700; color: #303133; margin-bottom: 4px; }
.oa-paywall-desc { font-size: 13px; color: #909399; margin-bottom: 16px; }
.oa-paywall-price { font-size: 28px; font-weight: 800; color: #e6a23c; margin-bottom: 20px; }
</style>
