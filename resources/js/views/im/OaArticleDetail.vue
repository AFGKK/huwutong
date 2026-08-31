<template>
    <div class="oa-article-page">
        <!-- 顶部导航 -->
        <div class="oa-article-topbar" :class="{ 'topbar-hidden': topbarHidden }">
            <div class="topbar-left">
                <el-button text size="small" @click="goBack">
                    <el-icon><ArrowLeft /></el-icon> {{ tp('back') }}
                </el-button>
                <el-divider direction="vertical" />
                <span v-if="article" class="topbar-acc-name">{{ article.account?.name }}</span>
            </div>
            <div class="topbar-right">
                <el-button text size="small" @click="toggleFontSize" :title="tp('font_size')">
                    <el-icon><ZoomIn /></el-icon>
                </el-button>
                <el-button text size="small" @click="useSerif = !useSerif" :type="useSerif ? 'primary' : 'default'" :title="tp('serif_font')">Aa</el-button>
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
                            <el-dropdown-item command="chat">{{ shareTargetLabels.chat }}</el-dropdown-item>
                            <el-dropdown-item command="plaza">{{ shareTargetLabels.plaza }}</el-dropdown-item>
                            <el-dropdown-item command="channel">{{ shareTargetLabels.channel }}</el-dropdown-item>
                            <el-dropdown-item command="wechat" divided>{{ shareTargetLabels.wechat }} <PointsIcon :size="14" />+1</el-dropdown-item>
                            <el-dropdown-item command="weibo">{{ shareTargetLabels.weibo }} <PointsIcon :size="14" />+1</el-dropdown-item>
                            <el-dropdown-item command="copy">{{ shareTargetLabels.copy }} <PointsIcon :size="14" />+1</el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>

        <!-- 文章内容 -->
        <div class="oa-article-container" :class="['theme-' + currentTheme, { 'focus-active': focusMode }]" v-if="article">
            <!-- 阅读进度条 -->
            <div class="oa-reading-progress" :style="{ width: readingProgress + '%' }"></div>
            <!-- 阅读进度环 -->
            <div class="oa-reading-ring" @click="focusMode = !focusMode" :title="focusMode ? tp('exit_focus_mode') : tp('focus_mode')">
                <svg width="44" height="44" viewBox="0 0 44 44">
                    <circle cx="22" cy="22" r="18" fill="none" stroke="#e8e8e8" stroke-width="3" />
                    <circle cx="22" cy="22" r="18" fill="none" stroke="#0f172a" stroke-width="3"
                        stroke-linecap="round" :stroke-dasharray="113.1"
                        :stroke-dashoffset="113.1 - (readingProgress / 100) * 113.1"
                        transform="rotate(-90 22 22)" style="transition: stroke-dashoffset 0.2s" />
                </svg>
                <span class="oa-reading-ring-text">{{ readingProgress }}%</span>
            </div>
            <!-- 专注模式遮罩 -->
            <div v-if="focusMode" class="oa-focus-overlay" @click="focusMode = false"></div>
            <article class="oa-article-body" :style="{ fontSize: fontSize + 'px', fontFamily: useSerif ? 'Georgia, serif' : '' }">
                <header class="oa-article-header" :class="{ 'focus-hidden': focusMode }">
                    <h1 class="oa-article-title">{{ article.title }}</h1>
                    <div class="oa-article-meta-row">
                        <el-tag v-if="article.is_original" size="small" type="danger" style="margin-right:6px">{{ tp('original') }}</el-tag>
                        <span class="oa-meta-author">
                            <img v-if="article.author?.avatar" :src="article.author.avatar" class="oa-meta-avatar" />
                            {{ article.author?.name || tp('anonymous') }}
                        </span>
                        <span class="oa-meta-sep">·</span>
                        <span class="oa-meta-acc">{{ article.account?.name }}</span>
                        <template v-if="isLoggedIn">
                            <el-button v-if="article.is_following === false" size="small" text type="primary" @click="handleFollow" style="margin-left:4px;font-size:12px">{{ tp('follow') }}</el-button>
                            <el-button v-else size="small" text type="default" @click="handleUnfollow" style="margin-left:4px;font-size:12px">{{ tp('following') }}</el-button>
                        </template>
                        <span v-else class="oa-login-hint" @click="redirectToLogin" style="margin-left:4px;font-size:12px;color:#0f172a;cursor:pointer">{{ tp('login_to_follow') }}</span>
                        <span class="oa-meta-sep">·</span>
                        <span class="oa-meta-time">{{ formatFullTime(article.published_at) }}</span>
                        <span class="oa-meta-sep">·</span>
                        <span class="oa-meta-time">{{ tp('reading_minutes', { n: readingTime }) }}</span>
                        <span class="oa-meta-time">{{ tp('word_count', { n: wordCount }) }}</span>
                    </div>
                </header>

                <div v-if="getCoverImage(article)" class="oa-article-cover">
                    <img :src="getCoverImage(article)" :alt="(article.title || tp('article_fallback')) + ' - ' + tp('cover_suffix')" />
                </div>

                <!-- 🔒 付费墙 -->
                <div v-if="article.is_paid && !article.has_purchased" class="oa-paywall">
                    <div class="oa-paywall-blur" v-if="article.content_preview">
                        <div class="oa-paywall-preview">{{ article.content_preview }}...</div>
                    </div>
                    <div class="oa-paywall-overlay">
                        <div class="oa-paywall-icon">*</div>
                        <h3 class="oa-paywall-title">{{ tp('paywall.title') }}</h3>
                        <p class="oa-paywall-desc">{{ tp('paywall.desc') }}</p>
                        <div class="oa-paywall-price">
                            <PointsIcon v-if="article.price_type === 'points'" :size="26" /> {{ article.price_type === 'points' ? article.price + ' ' + tp('paywall.points_unit') : '¥' + article.price }}
                        </div>
                        <template v-if="isLoggedIn">
                            <el-button type="warning" size="large" :loading="purchasingArticle" @click="purchaseArticle">
                                <PointsIcon :size="18" v-if="article.price_type === 'points'" /> {{ article.price_type === 'points' ? tp('paywall.unlock_points') : tp('paywall.unlock_pay') }}
                            </el-button>
                            <p v-if="article.price_type === 'points' && userBalance >= 0" class="oa-paywall-balance">
                                {{ tp('paywall.balance') }}<PointsIcon :size="14" /> {{ userBalance }} {{ tp('paywall.points_unit') }}
                                <span v-if="userBalance < article.price" style="color:#f56c6c">{{ tp('paywall.insufficient') }}</span>
                            </p>
                        </template>
                        <template v-else>
                            <el-button type="primary" size="large" @click="redirectToLogin">{{ tp('paywall.login_unlock') }}</el-button>
                        </template>
                    </div>
                </div>

                <!-- 目录导航 -->
                <div v-if="tocItems.length > 0" class="oa-toc">
                    <div class="oa-toc-header" @click="showToc = !showToc">{{ tp('toc') }} <el-icon><ArrowDown /></el-icon></div>
                    <div v-show="showToc" class="oa-toc-body">
                        <div v-for="(item, idx) in tocItems" :key="idx" class="oa-toc-item"
                            :style="{ paddingLeft: (item.level - 1) * 16 + 'px' }"
                            @click="scrollToHeading(idx)">
                            <span class="oa-toc-dot"></span>{{ item.text }}
                        </div>
                    </div>
                </div>

                <div class="oa-article-content" v-html="article.content" @click="onArticleContentClick" ref="contentRef" v-if="!article.is_paid || article.has_purchased"></div>
                <div v-else-if="article.content_preview" class="oa-article-content oa-content-locked">
                    <p style="color:#999;text-align:center;padding:20px">{{ tp('paywall.content_hidden') }}</p>
                </div>

                <!-- 多图展示 -->
                <div v-if="article.images?.length" class="oa-article-images">
                    <div v-for="(img, i) in article.images" :key="i" class="oa-article-image-item">
                        <img :src="typeof img === 'string' ? img : img.url" @click="previewImage(typeof img === 'string' ? img : img.url)" />
                    </div>
                </div>

                <div v-if="article.tags?.length" class="oa-article-tags">
                    <el-tag v-for="t in article.tags" :key="t" size="small">{{ t }}</el-tag>
                </div>

                <!-- 操作栏（居中） -->
                <div class="oa-article-actions" v-if="article">
                    <el-button :type="article.is_liked ? 'primary' : 'default'" text @click="isLoggedIn ? handleLike() : redirectToLogin()" :title="tp('like')">
                        {{ article.likes_count || 0 }}
                    </el-button>
                    <el-button text disabled :title="tp('read')">
                        {{ article.reads_count || 0 }}
                    </el-button>
                    <el-button :type="article.is_favorited ? 'warning' : 'default'" text @click="isLoggedIn ? handleFavorite() : redirectToLogin()" :title="tp('favorite')">
                        {{ tp('favorite') }}
                    </el-button>
                    <el-button text type="warning" @click="openTipDialog" :title="tp('tip')">
                        <PointsIcon :size="16" /> {{ tp('tip') }}
                    </el-button>
                    <el-button v-if="(article.author?.id || article.user_id) !== myId" text type="warning" @click="isLoggedIn ? reportArticle() : redirectToLogin()">
                        {{ tp('report') }}
                    </el-button>
                </div>

                <!-- 上一篇/下一篇 -->
                <div v-if="article.prev_article || article.next_article" class="oa-article-prev-next">
                    <a v-if="article.prev_article" :href="'/build/oa-article/' + article.prev_article.id" class="oa-pn-link oa-pn-prev">
                        <span class="oa-pn-label">{{ tp('prev_article') }}</span>
                        <span class="oa-pn-title">{{ article.prev_article.title }}</span>
                    </a>
                    <a v-if="article.next_article" :href="'/build/oa-article/' + article.next_article.id" class="oa-pn-link oa-pn-next">
                        <span class="oa-pn-label">{{ tp('next_article') }}</span>
                        <span class="oa-pn-title">{{ article.next_article.title }}</span>
                    </a>
                </div>

                <!-- 推荐阅读 -->
                <div v-if="article.related_articles?.length" class="oa-article-related">
                    <h3>{{ tp('related') }}</h3>
                    <div class="oa-related-grid">
                        <a v-for="r in article.related_articles" :key="r.id" :href="'/build/oa-article/' + r.id" class="oa-related-card">
                            <div v-if="getCoverImage(r)" class="oa-related-cover">
                                <img :src="getCoverImage(r)" />
                            </div>
                            <div v-else class="oa-related-cover oa-related-cover-text">📝</div>
                            <div class="oa-related-info">
                                <div class="oa-related-card-title">{{ r.title }}</div>
                                <div v-if="r.summary" class="oa-related-desc">{{ r.summary.substring(0, 60) }}</div>
                                <div class="oa-related-time">{{ formatTime(r.published_at) }}</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- 评论区 -->
                <div class="oa-article-comments">
                    <h3>{{ tp('comments_title', { n: article.comments?.length || 0 }) }}</h3>
                    <div v-if="isLoggedIn" class="oa-comment-input">
                        <div style="display:flex;gap:8px;align-items:flex-start">
                            <el-avatar :size="32" :src="currentUserAvatar" style="flex-shrink:0">{{ myId ? '?' : '' }}</el-avatar>
                            <div style="flex:1">
                                <div style="display:flex;gap:6px;margin-bottom:6px;position:relative">
                                    <el-button size="small" text @click="showEmojiPicker = !showEmojiPicker" :title="tp('emoji')" style="padding:0 6px">:)</el-button>
                                    <div v-if="showEmojiPicker" style="position:absolute;top:100%;left:0;z-index:100;background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);display:flex;flex-wrap:wrap;gap:2px;width:280px" @mouseleave="showEmojiPicker = false">
                                        <span v-for="emo in ['😊','😂','👍','❤️','🎉','🔥','💪','😍','🤔','👏','🙏','✨','💯','😭','🥰','😎','🤩','😢','🙌','💀','👻','🎈','⭐','🌙','☀️','🌈','🍀','🔥','💡','📌','💎','🆒','🆕','✅','❌','💯','🔔']" :key="emo"
                                            style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:4px;font-size:18px"
                                            :class="{'hover:bg-gray-100': true}"
                                            @click="newCommentText += emo; showEmojiPicker = false" @mouseenter="$el.style.backgroundColor='#f3f4f6'">{{ emo }}</span>
                                    </div>
                                    <el-button size="small" text @click="insertCommentImage" :title="tp('image')" style="padding:0 6px">Img</el-button>
                                    <el-button size="small" text @click="uploadCommentImage" :title="tp('upload_image')" style="padding:0 6px">{{ t('actions.upload') }}</el-button>
                                </div>
                                <el-input v-model="newCommentText" type="textarea" :rows="2" :placeholder="tp('comment_placeholder')" maxlength="1000" @focus="loadOaAiSuggestions" />
                        <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px">
                            <span v-for="(sg, si) in oaAiSuggestions" :key="si"
                                class="px-2.5 py-1 text-xs rounded-full border border-gray-200 text-gray-500 cursor-pointer hover:bg-primary-50 hover:border-primary-300 hover:text-primary-600 transition"
                                @click="newCommentText = sg; oaAiSuggestions = []">
                                {{ sg }}
                            </span>
                            <el-button v-if="oaAiSuggestions.length" size="small" text @click="oaAiSuggestions = []" style="font-size:11px">{{ t('actions.cancel') }}</el-button>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;margin-top:6px">
                            <input v-if="showCommentImageInput" v-model="commentImageUrl" :placeholder="tp('comment_image_url_ph')" size="small" style="flex:1;padding:4px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px" />
                            <el-button size="small" type="primary" :loading="submittingComment" @click="submitComment">{{ tp('submit_comment') }}</el-button>
                        </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="oa-comment-login-tip" style="text-align:center;padding:20px;background:#f9f9f9;border-radius:8px;margin-bottom:12px">
                        <span style="color:#999;font-size:13px">{{ tp('login_to_comment') }}</span>
                        <el-button size="small" text type="primary" @click="redirectToLogin" style="margin-left:8px">{{ tp('login_now') }}</el-button>
                    </div>
                    <div v-if="article.comments?.length" class="oa-comment-list">
                        <div v-for="c in displayedComments" :key="c.id" class="oa-comment-item" :class="{ 'oa-comment-pinned': c.is_pinned }">
                            <img v-if="c.user?.avatar" :src="c.user.avatar" class="oa-comment-avatar" />
                            <span v-else class="oa-comment-avatar-text">{{ c.user?.name?.charAt(0) || '?' }}</span>
                            <div class="oa-comment-body">
                                <div class="oa-comment-author">
                                    <el-tag v-if="c.is_pinned" size="small" type="warning" style="margin-right:4px">{{ tp('pinned') }}</el-tag>
                                    {{ c.user?.name || tp('anonymous') }}
                                    <span v-if="c.user?.region" class="oa-comment-region">{{ c.user.region }}</span>
                                    <span class="oa-comment-time">{{ formatTime(c.created_at) }}</span>
                                    <el-button v-if="c.user_id === myId" text size="small" type="danger" @click="deleteComment(c)" style="font-size:11px;padding:0 4px;margin-left:4px">{{ t('actions.delete') }}</el-button>
                                </div>
                                <div class="oa-comment-text">{{ c.content }}</div>
                                <div v-if="c.image" class="oa-comment-image">
                                    <img :src="c.image" style="max-width:200px;max-height:150px;border-radius:6px;margin-top:4px" @click="$event.target.closest('.oa-comment-image')?.querySelector('img')?.classList.toggle('expanded')" />
                                </div>
                                <div class="oa-comment-footer" style="display:flex;gap:8px;margin-top:4px">
                                    <el-button text size="small" @click="startReply(c)" style="font-size:12px;padding:0;height:auto;color:#999">{{ tp('reply') }}</el-button>
                                    <el-button text size="small" @click="toggleCommentLike(c)" style="font-size:12px;padding:0;height:auto" :type="c.is_liked ? 'primary' : 'default'">
                                        ❤️ {{ c.likes_count || 0 }}
                                    </el-button>
                                </div>
                                <!-- 表情反应 -->
                                <div class="oa-comment-reactions" style="display:flex;gap:4px;margin-top:4px">
                                    <span v-for="emoji in ['👍','❤️','😮','😢','😡']" :key="emoji"
                                        class="oa-reaction-btn"
                                        :class="{ active: getOaReaction(c.id, emoji) }"
                                        @click="toggleOaReaction(c.id, emoji, $event)">
                                        {{ emoji }}
                                        <span v-if="getOaReactionCount(c, emoji)" class="oa-reaction-count">{{ getOaReactionCount(c, emoji) }}</span>
                                    </span>
                                </div>
                                <div v-if="c.replies?.length" class="oa-comment-replies">
                                    <div v-for="r in c.replies" :key="r.id" class="oa-comment-reply">
                                        <el-avatar :size="20" :src="r.user?.avatar_url" style="flex-shrink:0;vertical-align:middle">{{ r.user?.name?.charAt(0) || '?' }}</el-avatar>
                                        <span class="oa-reply-author">{{ r.user?.name }}：</span>
                                        <span class="oa-reply-text">{{ r.content }}</span>
                                        <span class="oa-comment-time">{{ formatTime(r.created_at) }}</span>
                                    </div>
                                </div>
                                <!-- 回复输入框 -->
                                <div v-if="replyingTo === c.id" class="oa-reply-box" style="margin-top:8px;display:flex;gap:6px;align-items:center">
                                    <el-avatar :size="24" :src="currentUserAvatar" style="flex-shrink:0">{{ myId ? '?' : '' }}</el-avatar>
                                    <el-input v-model="replyText" :placeholder="tp('reply_placeholder')" size="small" style="flex:1" maxlength="1000" />
                                    <el-button size="small" type="primary" :loading="replying" @click="submitReply(c)">{{ tp('send') }}</el-button>
                                    <el-button size="small" @click="replyingTo = null">{{ t('actions.cancel') }}</el-button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else style="text-align:center;padding:16px;color:#999;font-size:13px">{{ tp('no_comments') }}</div>
                    <div v-if="hasMoreComments && article.comments?.length > 5" style="text-align:center;margin-top:8px">
                        <el-button size="small" text @click="loadMoreComments" :loading="loadingMoreComments">{{ tp('load_more_comments') }}</el-button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="oa-article-loading">
            <el-icon class="is-loading" :size="32"><Loading /></el-icon>
            <p>{{ t('actions.loading') }}</p>
        </div>
    </div>
    <!-- 图片灯箱 -->
    <el-image-viewer v-if="showImageViewer" :url-list="[previewImageUrl]" @close="showImageViewer = false" :z-index="3000" />
    <!-- 打赏对话框 -->
    <TipDialog v-model="showTipDialog" :content-id="article?.id || 0" content-type="oa_article" :receiver-id="article?.author?.id || 0" @tipped="onTipped" @view-transactions="showPoints = true" />
    <!-- 积分交易记录 -->
    <PointsHistory v-model="showPoints" />
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox, ElImageViewer } from 'element-plus';
import PointsIcon from '@/components/PointsIcon.vue';
import { ArrowLeft, Share, Loading, ZoomIn, ArrowDown } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import i18n from '@/i18n';
import TipDialog from '@/components/TipDialog.vue';
import PointsHistory from '@/components/PointsHistory.vue';

const { t } = useI18n();
const tp = (key, params) => t('oa_article_detail_page.' + key, params);

const themeOptions = computed(() => [
    { command: 'default', label: tp('themes.default') },
    { command: 'sepia', label: tp('themes.sepia') },
    { command: 'night', label: tp('themes.night') },
]);

const shareTargetLabels = computed(() => ({
    chat: tp('share_targets.chat'),
    plaza: tp('share_targets.plaza'),
    channel: tp('share_targets.channel'),
    wechat: tp('share_targets.wechat'),
    weibo: tp('share_targets.weibo'),
    copy: tp('share_targets.copy_link'),
}));

function pollTypeLabel(type) {
    return type === 'multiple' ? tp('poll.multiple') : tp('poll.single');
}
function pollFooterText(total, type) {
    return tp('poll.participants', { n: total, type: pollTypeLabel(type) });
}
function pollVoteCount(count) {
    return tp('poll.vote_count', { n: count });
}

function extractFirstImage(html) {
  if (!html) return '';
  var m = html.match(/<img[^>]+src=["']([^"']+)["']/);
  return m ? m[1] : '';
}

function getCoverImage(item) {
  return item?.cover_image || (item?.content ? extractFirstImage(item.content) : '');
}

const route = useRoute();
const router = useRouter();
const article = ref(null);
const newCommentText = ref('');
const submittingComment = ref(false);
const replyingTo = ref(null);
const replyText = ref('');
const replying = ref(false);
const showEmojiPicker = ref(false);
const showCommentImageInput = ref(false);
const commentImageUrl = ref('');
const myId = ref(0);
const isLoggedIn = ref(false);
const currentUserAvatar = ref('');
const fontSizeIndex = ref(1);
const fontSizes = [14, 15, 16, 18, 20, 24];
const showToc = ref(true);
const tocItems = ref([]);
const contentRef = ref(null);
const readingProgress = ref(0);
const showImageViewer = ref(false);
const previewImageUrl = ref('');
const useSerif = ref(false);
const lastScrollTop = ref(0);
const topbarHidden = ref(false);
const commentPage = ref(1);
const hasMoreComments = ref(true);
const currentTheme = ref('default');
const focusMode = ref(false);
const isSpeaking = ref(false);
const speechRate = ref(1);
const oaReactions = ref(JSON.parse(localStorage.getItem('oaCommentReactions') || '{}'));
const showTipDialog = ref(false);
const showPoints = ref(false);

// ── 付费文章 ──
const purchasingArticle = ref(false);
const userBalance = ref(-1);

// ── 阅读行为追踪 ──
const readStartTime = ref(null)
const maxScrollDepth = ref(0)
const readTrackingTimer = ref(null)
const readReported = ref(false)

const readingTime = computed(() => {
  const text = article.value ? stripHtml(article.value.content) : '';
  const cjk = (text.match(/[\u4e00-\u9fa5]/g) || []).length;
  const words = text.replace(/[\u4e00-\u9fa5]/g, 'x').split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.ceil((cjk + words) / 300));
});
const wordCount = computed(() => {
  const text = article.value ? stripHtml(article.value.content) : '';
  return text.replace(/\s/g, '').length;
});

const displayedComments = computed(() => {
    return article.value?.comments?.slice(0, commentPage.value * 5) || [];
});

const loadingMoreComments = ref(false);

function loadMoreComments() {
    commentPage.value++;
    if (article.value?.comments && commentPage.value * 5 >= article.value.comments.length) {
        hasMoreComments.value = false;
    }
}

// ── 评论表情反应 ──
function getOaReaction(commentId, emoji) {
    return oaReactions.value[commentId + '_' + emoji] || false;
}
function getOaReactionCount(comment, emoji) {
    return comment.reactions?.[emoji] || 0;
}
function toggleOaReaction(commentId, emoji) {
    const key = commentId + '_' + emoji;
    oaReactions.value[key] = !oaReactions.value[key];
    localStorage.setItem('oaCommentReactions', JSON.stringify(oaReactions.value));
    const comment = article.value?.comments?.find(c => c.id === commentId);
    if (comment) {
        if (!comment.reactions) comment.reactions = {};
        comment.reactions[emoji] = (comment.reactions[emoji] || 0) + (oaReactions.value[key] ? 1 : -1);
        if (comment.reactions[emoji] <= 0) delete comment.reactions[emoji];
    }
}

function formatFullTime(date) {
    if (!date) return '';
    const d = new Date(date);
    return tp('date_full', {
        year: d.getFullYear(),
        month: d.getMonth() + 1,
        day: d.getDate(),
        hours: String(d.getHours()).padStart(2, '0'),
        minutes: String(d.getMinutes()).padStart(2, '0'),
    });
}
function formatTime(date) {
    if (!date) return '';
    const d = new Date(date);
    const now = new Date();
    const diff = now - d;
    if (diff < 60000) return t('time.just_now');
    if (diff < 3600000) return t('time.minutes_ago', { minutes: Math.floor(diff / 60000) });
    if (diff < 86400000) return t('time.hours_ago', { hours: Math.floor(diff / 3600000) });
    return `${d.getMonth() + 1}/${d.getDate()}`;
}

function goBack() {
    if (window.history.length > 1) {
        router.back();
    } else {
        router.push('/channels');
    }
}

function setTheme(theme) {
    currentTheme.value = theme;
}

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html || '';
    return div.textContent || '';
}

function toggleSpeech() {
    if (!window.speechSynthesis) { ElMessage.warning(tp('toast.speech_unsupported')); return; }
    if (isSpeaking.value) { window.speechSynthesis.cancel(); isSpeaking.value = false; return; }
    const text = stripHtml(article.value?.content || '');
    if (!text) { ElMessage.warning(tp('toast.no_speech_content')); return; }
    const u = new SpeechSynthesisUtterance(text.substring(0, 5000));
    u.lang = 'zh-CN'; u.rate = speechRate.value;
    u.onend = () => { isSpeaking.value = false; };
    u.onerror = () => { isSpeaking.value = false; ElMessage.error(tp('toast.speech_error')); };
    window.speechSynthesis.speak(u); isSpeaking.value = true; ElMessage.info(tp('toast.speech_started'));
}

function scrollToHeading(idx) {
    const el = contentRef.value;
    if (!el) return;
    const items = el.querySelectorAll('h1, h2, h3');
    if (items && items[idx]) items[idx].scrollIntoView({ behavior: 'smooth' });
}

function onArticleContentClick(event) {
    const target = event.target

    // 投票选项点击
    const pollOption = target.closest('[data-poll-option]')
    if (pollOption) {
        const pollId = parseInt(pollOption.dataset.pollId)
        const optionId = parseInt(pollOption.dataset.pollOptionId)
        if (pollId && optionId && isLoggedIn.value) {
            handlePollVote(pollId, [optionId])
        } else if (!isLoggedIn.value) {
            redirectToLogin()
        }
        return
    }

    // 图片点击 → 灯箱预览
    if (target?.tagName === 'IMG' && target.closest('.oa-article-content')) {
        previewImageUrl.value = target.src
        showImageViewer.value = true
        return
    }
    // 代码块右上角复制
    const pre = target?.closest?.('pre')
    if (!pre) return
    const rect = pre.getBoundingClientRect()
    const x = event.clientX - rect.left
    const y = event.clientY - rect.top
    if (x > rect.width - 50 && y < 30) {
        const code = pre.textContent || ''
        navigator.clipboard.writeText(code).then(() => {
            ElMessage.success(tp('toast.code_copied'))
        }).catch(() => ElMessage.error(tp('toast.copy_failed')))
    }
}

function onArticleScroll() {
    const scrollTop = window.scrollY || document.documentElement.scrollTop
    const scrollHeight = document.documentElement.scrollHeight - window.innerHeight
    readingProgress.value = scrollHeight > 0 ? Math.min(100, Math.round(scrollTop / scrollHeight * 100)) : 0
    // 追踪最大滚动深度
    if (readingProgress.value > maxScrollDepth.value) {
        maxScrollDepth.value = readingProgress.value
        // 每10%上报一次
        if (maxScrollDepth.value % 10 === 0 && maxScrollDepth.value > 0 && !readReported.value) {
            reportReadBehavior()
        }
    }
    // 滚动隐藏顶栏
    if (scrollTop > 100) {
        topbarHidden.value = scrollTop > lastScrollTop.value
    } else {
        topbarHidden.value = false
    }
    lastScrollTop.value = scrollTop
}

// ── 阅读行为上报 ──
function reportReadBehavior() {
    if (!article.value?.id || !isLoggedIn.value || readReported.value) return
    readReported.value = true
    const duration = readStartTime.value ? Math.round((Date.now() - readStartTime.value) / 1000) : 0
    const completed = maxScrollDepth.value >= 90
    const data = {
        read_duration: duration,
        scroll_depth: maxScrollDepth.value,
        completed: completed,
    }
    apiClient.post('/official-accounts/articles/' + article.value.id + '/read-behavior', data).catch(() => {})
}

// 离开页面时最后上报一次
onBeforeUnmount(() => {
    if (readStartTime.value && !readReported.value) {
        reportReadBehavior()
    }
    if (readTrackingTimer.value) clearInterval(readTrackingTimer.value)
})

async function loadArticle() {
    try {
        const id = route.params.id;
        const r = await apiClient.get('/official-accounts/articles/' + id);
        article.value = r.data?.data || null;
        if (article.value?.title) {
            const title = article.value.title + ' - ' + (article.value.account?.name || 'HWT');
            const desc = article.value.summary || stripHtml(article.value.content).slice(0, 160);
            document.title = title;

            // 更新 description
            let meta = document.querySelector('meta[name="description"]');
            if (!meta) { meta = document.createElement('meta'); meta.name = 'description'; document.head.appendChild(meta); }
            meta.content = desc;

            // 更新 og:title / og:description
            const setMeta = (prop, content) => {
                if (!content) return;
                let el = document.querySelector('meta[' + prop + ']');
                if (!el) { el = document.createElement('meta'); el.setAttribute(prop.split('=')[0], prop.split('=')[1] || ''); document.head.appendChild(el); }
                el.content = content;
            };
            setMeta('property="og:title"', title);
            setMeta('property="og:description"', desc);
            setMeta('name="twitter:title"', title);
            setMeta('name="twitter:description"', desc);
            if (article.value.cover_image) {
                setMeta('property="og:image"', article.value.cover_image);
                setMeta('name="twitter:image"', article.value.cover_image);
            }

            // 更新 JSON-LD 结构化数据
            let script = document.getElementById('seo-ld-json');
            if (!script) {
                script = document.createElement('script');
                script.id = 'seo-ld-json';
                script.type = 'application/ld+json';
                document.head.appendChild(script);
            }
            script.textContent = JSON.stringify({
                '@context': 'https://schema.org',
                '@type': 'Article',
                'headline': article.value.title,
                'description': desc,
                'image': article.value.cover_image || null,
                'datePublished': article.value.published_at || null,
                'author': { '@type': 'Person', 'name': article.value.author?.name || tp('anonymous') },
                'publisher': { '@type': 'Organization', 'name': article.value.account?.name || tp('publisher_fallback') },
                'mainEntityOfPage': { '@type': 'WebPage', '@id': window.location.href },
            });
        }

        // 阅读行为追踪：记录开始时间
        if (article.value?.id && isLoggedIn.value) {
            readStartTime.value = Date.now()
            maxScrollDepth.value = 0
            readReported.value = false
            // 每30秒自动上报一次进度
            if (readTrackingTimer.value) clearInterval(readTrackingTimer.value)
            readTrackingTimer.value = setInterval(() => {
                if (article.value?.id && isLoggedIn.value && !readReported.value) {
                    const duration = Math.round((Date.now() - readStartTime.value) / 1000)
                    apiClient.post('/official-accounts/articles/' + article.value.id + '/read-behavior', {
                        read_duration: duration,
                        scroll_depth: maxScrollDepth.value,
                        completed: maxScrollDepth.value >= 90,
                    }).catch(() => {})
                }
            }, 30000)
        }

        // 提取目录 + 图片懒加载 + 代码语言标签
        if (article.value?.content) {
            const div = document.createElement('div');
            div.innerHTML = article.value.content;
            div.querySelectorAll('img').forEach(img => img.setAttribute('loading', 'lazy'));
            div.querySelectorAll('pre code[class*="language-"]').forEach(el => {
                const lang = el.className.match(/language-(\w+)/)?.[1] || '';
                if (lang) {
                    const pre = el.closest('pre');
                    if (!pre) return;
                    const badge = document.createElement('div');
                    badge.className = 'code-lang-badge';
                    badge.textContent = lang;
                    pre.prepend(badge);
                    if (lang === 'javascript' || lang === 'js') {
                        const runBtn = document.createElement('button');
                        runBtn.className = 'code-run-btn'; runBtn.textContent = '▶ ' + tp('code.run');
                        runBtn.onclick = function(e) { e.stopPropagation();
                            const p = this.closest('pre'); const code = p?.querySelector('code')?.textContent || '';
                            const out = p?.querySelector('.code-runner-output');
                            if (out) { out.remove(); if (this.textContent === tp('code.stop')) { this.textContent = '▶ ' + tp('code.run'); return; } }
                            const d = document.createElement('div'); d.className = 'code-runner-output';
                            d.innerHTML = '<div style="padding:8px;font-size:12px;color:#999">' + tp('code.running') + '</div>';
                            p?.appendChild(d); this.textContent = tp('code.stop');
                            const logs = []; const origLog = console.log; console.log = function() { logs.push(Array.from(arguments).join(' ')); };
                            try { const r = new Function(code)(); if (r !== undefined) logs.push('=> ' + r);
                                d.innerHTML = '<div class="code-runner-header">▶ ' + tp('code.result') + '</div><pre class="code-runner-pre">' +
                                    (logs.length ? logs.map(l => l.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')).join('\n') : '<span style="color:#999">' + tp('code.no_output') + '</span>') + '</pre>';
                            } catch (err) {
                                d.innerHTML = '<div class="code-runner-header" style="color:#f56c6c">✕ ' + tp('code.error') + '</div><pre class="code-runner-pre" style="color:#f56c6c">' +
                                    err.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</pre>';
                            } finally { console.log = origLog; this.textContent = '▶ ' + tp('code.run'); }
                        };
                        pre.appendChild(runBtn);
                    }
                }
            });
            // 增强嵌入组件渲染
            div.querySelectorAll('[data-link-href]').forEach(el => {
                const href = el.getAttribute('data-link-href');
                const label = el.getAttribute('data-link-label') || t('actions.view_details');
                if (href) {
                    el.innerHTML = '<div style="padding:16px;border:1px solid #e4e7ed;border-radius:8px;background:#fff;margin:12px 0;text-align:center">' +
                        '<div style="font-size:14px;font-weight:500;color:#303133;margin-bottom:8px">' + (el.textContent.trim() || tp('embed.link_content')) + '</div>' +
                        '<a href="' + href + '" target="_blank" rel="noopener" style="display:inline-block;padding:6px 20px;background:#0f172a;color:#fff;border-radius:4px;text-decoration:none;font-size:13px">' + label + '</a></div>';
                    el.style.cssText = '';
                }
            });
            div.querySelectorAll('.product-card, [class*="product"]').forEach(el => {
                if (!el.getAttribute('data-enhanced')) {
                    el.setAttribute('data-enhanced', '1');
                    el.style.cssText = 'border:1px solid #e4e7ed;border-radius:8px;padding:16px;margin:12px 0;background:#fff;';
                }
            });
            // 投票卡片渲染
            div.querySelectorAll('[data-type="poll-card"]').forEach(el => {
                const pollId = el.getAttribute('data-poll-id')
                if (!pollId) return
                const cached = pollResults.value[pollId]
                if (cached) {
                    // 已有投票结果，直接渲染带结果
                    renderPollWithResults(el, cached)
                } else {
                    // 未投票，渲染可点击选项
                    renderPollOptions(el, pollId)
                }
            });
            const headings = div.querySelectorAll('h1, h2, h3');
            tocItems.value = Array.from(headings).map(h => ({
                level: parseInt(h.tagName[1]),
                text: h.textContent || ''
            }));
            article.value.content = div.innerHTML;
        }

        // 如果是付费文章，获取用户余额
        if (article.value?.is_paid && isLoggedIn.value) {
            try {
                const bal = await apiClient.get('/points/balance');
                userBalance.value = bal.data?.data?.balance || 0;
            } catch { /* ignore */ }
        }

        // 加载文章投票数据
        if (article.value?.id && isLoggedIn.value) {
            try {
                const pollsRes = await apiClient.get('/official-accounts/articles/' + article.value.id + '/polls')
                const polls = pollsRes.data?.data || []
                polls.forEach(p => { pollResults.value[p.id] = p })
            } catch { /* ignore */ }
        }
    } catch {
        ElMessage.error(tp('toast.load_failed'));
    }
}

// ── 付费文章购买 ──
async function purchaseArticle() {
    if (!article.value?.id) return;
    purchasingArticle.value = true;
    try {
        const r = await apiClient.post('/official-accounts/articles/' + article.value.id + '/purchase');
        if (r.data?.success) {
            ElMessage.success(tp('toast.unlocked'));
            // 重新加载文章获取完整内容
            await loadArticle();
        } else {
            ElMessage.error(r.data?.error?.message || tp('toast.unlock_failed'));
        }
    } catch (e) {
        const msg = e.response?.data?.error?.message || e.response?.data?.message || tp('toast.unlock_failed');
        if (
            msg.includes(i18n.global.t('oa_article_detail_page.toast.insufficient_marker', {}, { locale: 'zh_CN' }))
            || msg.toLowerCase().includes('insufficient')
        ) {
            ElMessage.warning(tp('toast.insufficient_points'));
        } else {
            ElMessage.error(msg);
        }
    } finally {
        purchasingArticle.value = false;
    }
}

// ── 投票 ──
const pollResults = ref({})
async function handlePollVote(pollId, optionIds) {
    try {
        const r = await apiClient.post('/official-accounts/polls/' + pollId + '/vote', { option_ids: optionIds })
        const data = r.data?.data
        if (data) {
            pollResults.value[pollId] = data
            // 更新文章内容中的投票显示
            updatePollDisplay(pollId, data)
            ElMessage.success(tp('toast.vote_ok'))
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || tp('toast.vote_failed'))
    }
}
function updatePollDisplay(pollId, data) {
    const container = document.querySelector(`[data-poll-id="${pollId}"]`)
    if (!container) return
    const total = data.total_votes || 0
    const myVotes = data.my_votes || []
    container.querySelectorAll('[data-poll-option]').forEach(el => {
        const oid = parseInt(el.dataset.pollOptionId)
        const opt = (data.options || []).find(o => o.id === oid)
        if (!opt) return
        const pct = opt.percentage || 0
        const count = opt.votes_count || 0
        const isMine = myVotes.includes(oid)
        el.style.cursor = 'default'
        el.innerHTML = `<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;margin:4px 0;background:#f0f5ff;border-radius:6px;font-size:14px;color:#303133;position:relative;overflow:hidden;${isMine ? 'border:1px solid #0f172a;font-weight:600' : ''}">
            <div style="position:absolute;top:0;left:0;height:100%;background:rgba(15,23,42,0.12);transition:width 0.5s;width:${pct}%"></div>
            <span style="width:16px;height:16px;border-radius:50%;background:#0f172a;color:#fff;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px;position:relative">${isMine ? '✓' : ''}</span>
            <span style="flex:1;position:relative">${opt.label}</span>
            <span style="font-size:13px;font-weight:600;color:#0f172a;position:relative">${pct}%</span>
            <span style="font-size:11px;color:#909399;position:relative">${pollVoteCount(count)}</span>
        </div>`
    })
    const footer = container.querySelector('.poll-footer')
    if (footer) footer.textContent = pollFooterText(total, data.type)
}

// ── 投票卡片渲染辅助 ──
function renderPollWithResults(el, data) {
    const total = data.total_votes || 0
    const myVotes = data.my_votes || []
    const opts = (data.options || []).map(opt => {
        const pct = opt.percentage || 0
        const isMine = myVotes.includes(opt.id)
        return `<div data-poll-option style="cursor:default;margin:4px 0">
            <div style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f0f5ff;border-radius:6px;font-size:14px;color:#303133;position:relative;overflow:hidden;${isMine ? 'border:1px solid #0f172a;font-weight:600' : ''}">
                <div style="position:absolute;top:0;left:0;height:100%;background:rgba(15,23,42,0.12);transition:width 0.5s;width:${pct}%"></div>
                <span style="width:16px;height:16px;border-radius:50%;background:#0f172a;color:#fff;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;font-size:10px;position:relative">${isMine ? '✓' : ''}</span>
                <span style="flex:1;position:relative">${opt.label}</span>
                <span style="font-size:13px;font-weight:600;color:#0f172a;position:relative">${pct}%</span>
                <span style="font-size:11px;color:#909399;position:relative">${pollVoteCount(opt.votes_count || 0)}</span>
            </div>
        </div>`
    }).join('')
    el.innerHTML = `<div style="padding:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <span style="font-size:20px">📊</span>
            <span style="font-size:15px;font-weight:600;color:#303133">${data.question}</span>
        </div>
        <div>${opts}</div>
        <div class="poll-footer" style="font-size:12px;color:#909399;margin-top:10px;text-align:center">${pollFooterText(total, data.type)}</div>
    </div>`
}
function renderPollOptions(el, pollId) {
    el.setAttribute('data-poll-id', pollId)
    const question = el.getAttribute('data-poll-question') || tp('poll.default_question')
    const type = el.getAttribute('data-poll-type') || 'single'
    const totalVotes = parseInt(el.getAttribute('data-poll-total') || '0')
    // 解析选项
    let options = []
    try { options = JSON.parse(el.getAttribute('data-poll-options') || '[]') } catch {}
    const optsHtml = options.map((o, i) =>
        `<div data-poll-option data-poll-id="${pollId}" data-poll-option-id="${o.id || i}" style="display:flex;align-items:center;gap:8px;padding:8px 12px;margin:4px 0;background:#f5f7fa;border-radius:6px;cursor:pointer;font-size:14px;color:#303133;transition:all .15s" onmouseover="this.style.background='#e8edf5'" onmouseout="this.style.background='#f5f7fa'">
            <span style="width:16px;height:16px;border-radius:50%;border:2px solid #c0c4cc;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0">${type === 'multiple' ? '<span style="font-size:10px">✓</span>' : ''}</span>
            <span style="flex:1">${o.label || o}</span>
        </div>`
    ).join('')
    el.innerHTML = `<div style="padding:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <span style="font-size:20px">📊</span>
            <span style="font-size:15px;font-weight:600;color:#303133">${question}</span>
        </div>
        <div>${optsHtml}</div>
        <div class="poll-footer" style="font-size:12px;color:#909399;margin-top:10px;text-align:center">${pollFooterText(totalVotes, type)}</div>
    </div>`
}

async function handleLike() {
    if (!article.value) return;
    try {
        const r = await apiClient.post('/official-accounts/articles/' + article.value.id + '/like');
        const result = r.data?.data;
        if (result) {
            article.value.is_liked = result.liked;
            article.value.likes_count += result.liked ? 1 : -1;
        }
    } catch { /* ignore */ }
}

async function handleFavorite() {
    if (!article.value) return;
    try {
        const r = await apiClient.post('/official-accounts/articles/' + article.value.id + '/favorite');
        article.value.is_favorited = r.data?.data?.favorited;
        if (article.value.is_favorited) {
            ElMessage.success(tp('toast.favorited'));
        } else {
            ElMessage.success(tp('toast.unfavorited'));
        }
    } catch { /* ignore */ }
}

const fontSize = ref(16);
function toggleFontSize() {
    const sizes = [14, 16, 18, 20];
    const idx = sizes.indexOf(fontSize.value);
    fontSize.value = sizes[(idx + 1) % sizes.length];
}

async function handleFollow() {
    if (!article.value?.account?.id) return;
    try {
        await apiClient.post('/official-accounts/' + article.value.account.id + '/follow');
        article.value.is_following = true;
        ElMessage.success(tp('toast.followed'));
    } catch (e) { ElMessage.error(e.response?.data?.message || tp('toast.follow_failed')); }
}
async function handleUnfollow() {
    if (!article.value?.account?.id) return;
    try {
        await apiClient.post('/official-accounts/' + article.value.account.id + '/unfollow');
        article.value.is_following = false;
        ElMessage.success(tp('toast.unfollowed'));
    } catch (e) { ElMessage.error(e.response?.data?.message || tp('toast.action_failed')); }
}

async function deleteComment(comment) {
    try {
        await apiClient.delete('/official-accounts/comments/' + comment.id);
        if (article.value?.comments) {
            article.value.comments = article.value.comments.filter(c => c.id !== comment.id);
        }
        ElMessage.success(tp('toast.deleted'));
    } catch (e) { ElMessage.error(e.response?.data?.message || tp('toast.delete_failed')); }
}

// ── 分享 ──
const shareRewardPoints = ref(0);

async function rewardShare(platform) {
    try {
        const r = await apiClient.post('/points/share-reward', {
            content_type: 'oa_article',
            content_id: article.value.id,
            platform: platform,
        });
        const data = r.data?.data;
        if (data?.rewarded) {
            shareRewardPoints.value += data.points || 0;
            ElMessage.success(tp('toast.share_reward', { n: data.points || 0 }));
        } else if (data?.reason === 'daily_limit') {
            ElMessage.info(tp('toast.share_daily_limit'));
        }
    } catch { /* ignore */ }
}

async function handleShareCommand(command) {
    const art = article.value
    if (!art?.id) return

    // 复制链接 - 游客可用
    if (command === 'copy') {
        const url = window.location.origin + '/build/oa-article/' + art.id
        navigator.clipboard.writeText(url).then(() => ElMessage.success(tp('toast.link_copied')))
        rewardShare('copy')
        return
    }

    // 以下分享方式需要登录
    if (!isLoggedIn.value) {
        redirectToLogin()
        return
    }

    if (command === 'plaza') {
        try {
            await apiClient.post('/official-accounts/articles/' + art.id + '/share', { target: 'plaza' })
            ElMessage.success(tp('toast.shared_plaza'))
        } catch (e) { ElMessage.error(e.response?.data?.message || tp('toast.share_failed')) }
        return
    }

    if (command === 'wechat') {
        const res = await apiClient.post('/official-accounts/articles/' + art.id + '/share', { target: 'wechat' })
        const data = res.data?.data
        const text = (data?.share_text || art.title) + ' ' + (data?.share_url || window.location.href)
        navigator.clipboard.writeText(text).then(() => ElMessage.success(tp('toast.wechat_copied')))
        rewardShare('wechat')
        return
    }

    if (command === 'weibo') {
        await apiClient.post('/official-accounts/articles/' + art.id + '/share', { target: 'weibo' })
        const text = encodeURIComponent(art.title + ' ' + window.location.href)
        window.open('https://service.weibo.com/share/share.php?title=' + text, '_blank')
        rewardShare('weibo')
        return
    }

    if (command === 'chat' || command === 'channel') {
        try {
            const label = command === 'chat' ? tp('dialog.conv_id') : tp('dialog.channel_id')
            const { value: id } = await ElMessageBox.prompt(tp('dialog.enter_target', { label }), command === 'chat' ? tp('dialog.share_to_chat') : tp('dialog.share_to_channel'), {
                inputPlaceholder: tp('dialog.input_ph', { label }),
            })
            if (id) {
                const payload = command === 'chat' ? { conversation_id: parseInt(id) } : { channel_id: parseInt(id) }
                await apiClient.post('/official-accounts/articles/' + art.id + '/share', { target: command, ...payload })
                ElMessage.success(tp('toast.shared'))
            }
        } catch { /* cancelled */ }
    }
}

// ── 举报 ──
async function reportArticle() {
    const art = article.value
    if (!art?.id) return
    try {
        const { value: reason } = await ElMessageBox.prompt(tp('dialog.report_reason'), tp('dialog.report_title'), {
            inputPlaceholder: tp('dialog.report_reason_ph'),
        })
        if (reason) {
            await apiClient.post('/user-chat/reports', {
                reportable_type: 'article',
                reportable_id: art.id,
                reason: reason.trim(),
            })
            ElMessage.success(tp('toast.report_submitted'))
        }
    } catch { /* cancelled */ }
}

function previewImage(url) {
    window.open(url, '_blank');
}

function toggleEmojiPicker() {
    showEmojiPicker.value = !showEmojiPicker.value;
}
function insertCommentImage() {
    showCommentImageInput.value = !showCommentImageInput.value;
    if (!showCommentImageInput.value) commentImageUrl.value = '';
}
function uploadCommentImage() {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('files[]', file);
        try {
            const { data: res } = await apiClient.post('/files/upload/simple', formData);
            const url = res?.data?.files?.[0]?.url;
            if (url) { commentImageUrl.value = url; showCommentImageInput.value = true; ElMessage.success(tp('toast.image_uploaded')); }
        } catch { ElMessage.error(tp('toast.upload_failed')); }
    };
    input.click();
}
async function toggleCommentLike(comment) {
    try {
        const r = await apiClient.post('/official-accounts/comments/' + comment.id + '/like');
        const data = r.data?.data;
        if (data) {
            comment.is_liked = data.liked;
            comment.likes_count = data.likes_count;
        }
    } catch { /* ignore */ }
}

async function submitComment() {
    if (!newCommentText.value.trim()) { ElMessage.warning(tp('toast.comment_required')); return; }
    submittingComment.value = true;
    try {
        const payload = { content: newCommentText.value };
        if (commentImageUrl.value.trim()) payload.image = commentImageUrl.value.trim();
        const r = await apiClient.post('/official-accounts/articles/' + article.value.id + '/comment', payload);
        const comment = r.data?.data;
        if (comment && article.value) {
            if (comment.status === 'pending') {
                ElMessage.success(tp('toast.comment_pending'));
            } else {
                if (!article.value.comments) article.value.comments = [];
                article.value.comments.unshift(comment);
                ElMessage.success(tp('toast.comment_ok'));
            }
            newCommentText.value = '';
            commentImageUrl.value = '';
            showCommentImageInput.value = false;
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || tp('toast.comment_failed'));
    } finally { submittingComment.value = false; }
}

function startReply(comment) {
    replyingTo.value = replyingTo.value === comment.id ? null : comment.id;
    replyText.value = '';
}
async function submitReply(comment) {
    if (!replyText.value.trim()) { ElMessage.warning(tp('toast.reply_required')); return; }
    replying.value = true;
    try {
        const r = await apiClient.post('/official-accounts/comments/' + comment.id + '/reply', { content: replyText.value });
        const reply = r.data?.data;
        if (reply) {
            if (!comment.replies) comment.replies = [];
            comment.replies.push(reply);
            replyText.value = '';
            replyingTo.value = null;
            ElMessage.success(tp('toast.reply_ok'));
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || tp('toast.reply_failed'));
    } finally { replying.value = false; }
}

// ── 打赏 ──
function openTipDialog() {
    if (!isLoggedIn.value) { redirectToLogin(); return }
    showTipDialog.value = true;
}
function onTipped(data) {
    if (data?.points) {
        // 更新点赞/收藏等状态（积分变动已由API处理）
    }
}

// ── 跳转登录 ──
function redirectToLogin() {
    ElMessage.info(tp('toast.login_required'));
    window.location.href = '/login';
}

// ── AI 评论建议 ──
const oaAiSuggestions = ref([])
let oaAiTimer = null
async function loadOaAiSuggestions() {
    if (oaAiSuggestions.value.length || !article.value?.content) return
    clearTimeout(oaAiTimer)
    oaAiTimer = setTimeout(async () => {
        try {
            const content = (article.value.content || '').replace(/<[^>]*>/g, '').substring(0, 300)
            const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            const res = await apiClient.post('/user-chat/ai-conversation', {
                message: t('oa_article_detail_page.ai_comment_prompt') + content
            }, { headers: h })
            const reply = res.data?.data?.reply || ''
            oaAiSuggestions.value = reply.split('|').filter(Boolean).map(s => s.trim()).slice(0, 3)
        } catch { /* ignore */ }
    }, 300)
}

onMounted(async () => {
    window.addEventListener('scroll', onArticleScroll)
    try {
        const userRes = await apiClient.get('/user');
        const user = userRes.data?.data || {};
        myId.value = user.id || 0;
        isLoggedIn.value = !!user.id;
        currentUserAvatar.value = user.avatar_url || '';
    } catch {
        isLoggedIn.value = false;
        myId.value = 0;
        currentUserAvatar.value = '';
    }
    await loadArticle();
});
</script>

<style scoped>
.oa-article-page { min-height: 100vh; background: #fff; display: flex; flex-direction: column; padding-top: 71px; }
.oa-article-page a { text-decoration: none !important; }
.chat-dark-mode .oa-article-page { background: #12121e; }
.oa-article-topbar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 24px; border-bottom: 1px solid #eee; background: #fafafa; position: sticky; top: 0; z-index: 10;
}
.chat-dark-mode .oa-article-topbar { background: #1a1a2e; border-color: #2a2a3e; }
.oa-article-topbar.topbar-hidden { transform: translateY(-100%); }
.oa-article-topbar { transition: transform .3s ease; }
.topbar-left, .topbar-right { display: flex; align-items: center; gap: 8px; }
.topbar-acc-name { font-size: 14px; font-weight: 500; color: #0f172a; }
.oa-article-container { flex: 1; display: flex; justify-content: center; padding: 20px 16px 60px; }
.oa-article-body { max-width: 720px; width: 100%; }
.oa-article-header { text-align: center; margin-bottom: 28px; }
.oa-article-title { font-size: 26px; font-weight: 700; line-height: 1.4; margin: 0 0 16px; }
.oa-article-meta-row { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 14px; color: #999; flex-wrap: wrap; }
.oa-meta-author { display: flex; align-items: center; gap: 4px; color: #0f172a; font-weight: 500; }
.oa-meta-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.oa-meta-sep { color: #ddd; }
.oa-meta-acc { color: #666; }
.oa-meta-time { color: #999; }
.oa-article-cover { margin-bottom: 24px; border-radius: 8px; overflow: hidden; }
.oa-article-cover img { width: 100%; max-height: 400px; object-fit: cover; display: block; }
.oa-article-content { font-size: 16px; line-height: 1.9; color: #333; }
.chat-dark-mode .oa-article-content { color: #ccc; }
.oa-article-content img { max-width: 100%; border-radius: 6px; margin: 16px auto; display: block; }
.oa-article-content pre { background: #1e1e1e; color: #d4d4d4; padding: 16px 44px 16px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; position: relative; }
.oa-article-content pre::after { content: '📋'; position: absolute; top: 6px; right: 8px; font-size: 14px; cursor: pointer; opacity: 0; transition: opacity .2s; padding: 2px 6px; border-radius: 4px; background: rgba(255,255,255,.1); line-height: 1.4; pointer-events: none; }
.oa-article-content pre:hover::after { opacity: .85; }
.oa-article-content code { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
/* 代码高亮 VS Code Dark */
.oa-article-content .hljs-keyword { color: #c586c0; }
.oa-article-content .code-lang-badge { position: absolute; top: 0; left: 0; font-size: 11px; background: rgba(255,255,255,.08); color: #999; padding: 2px 10px; border-radius: 6px 0 6px 0; line-height: 1.6; font-family: sans-serif; z-index: 1; }
.oa-article-content .code-run-btn { position: absolute; top: 4px; right: 40px; font-size: 11px; background: #2ea043; color: #fff; border: none; padding: 2px 10px; border-radius: 4px; cursor: pointer; z-index: 2; line-height: 1.6; opacity: 0; transition: opacity .2s; }
.oa-article-content pre:hover .code-run-btn { opacity: 1; }
.oa-article-content .code-run-btn:hover { background: #238636; }
.oa-article-content .code-runner-output { border-top: 1px solid #333; font-size: 12px; line-height: 1.5; }
.oa-article-content .code-runner-header { padding: 6px 12px; font-size: 11px; color: #999; background: rgba(255,255,255,.05); }
.oa-article-content .code-runner-pre { padding: 8px 12px; margin: 0; background: transparent !important; color: #d4d4d4 !important; font-family: 'Fira Code', Consolas, monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; }
.oa-article-content .hljs-string { color: #ce9178; }
.oa-article-content .hljs-number { color: #b5cea8; }

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
.oa-paywall-balance { font-size: 12px; color: #909399; margin-top: 8px; }
.oa-content-locked { opacity: 0.5; filter: grayscale(0.3); }
.oa-article-content .hljs-comment { color: #6a9955; font-style: italic; }
.oa-article-content .hljs-built_in { color: #4ec9b0; }
.oa-article-content .hljs-title { color: #dcdcaa; }
.oa-article-content .hljs-params { color: #9cdcfe; }
.oa-article-content .hljs-literal { color: #569cd6; }
.oa-article-content .hljs-type { color: #4ec9b0; }
.oa-article-content .hljs-tag { color: #569cd6; }
.oa-article-content .hljs-name { color: #569cd6; }
.oa-article-content .hljs-attr { color: #9cdcfe; }
/* 目录导航 */
.oa-toc { margin-bottom: 16px; border: 1px solid #e4e7ed; border-radius: 6px; overflow: hidden; }
.chat-dark-mode .oa-toc { border-color: #2a2a3e; }
.oa-toc-header { display: flex; align-items: center; gap: 4px; padding: 8px 12px; cursor: pointer; font-size: 14px; font-weight: 500; background: #f5f7fa; user-select: none; }
.chat-dark-mode .oa-toc-header { background: #1a1a2e; color: #ccc; }
.oa-toc-body { padding: 4px 0; max-height: 240px; overflow-y: auto; }
.oa-toc-item { display: flex; align-items: center; gap: 6px; padding: 5px 12px; font-size: 13px; cursor: pointer; color: #606266; transition: color 0.2s; }
.oa-toc-item:hover { color: #0f172a; background: #f0f7ff; }
.chat-dark-mode .oa-toc-item { color: #999; }
.chat-dark-mode .oa-toc-item:hover { color: #0f172a; background: #16213e; }
.oa-toc-dot { width: 4px; height: 4px; border-radius: 50%; background: #c0c4cc; flex-shrink: 0; }
/* 打印样式 */
@media print { .oa-article-topbar,.oa-article-actions,.oa-article-prev-next,.oa-article-related,.oa-article-comments,.oa-reading-progress,.oa-toc{display:none!important} .oa-article-container{padding:0} .oa-article-body{max-width:100%} .oa-article-page{min-height:auto} }
/* 阅读进度条 */
.oa-reading-progress { position: fixed; top: 0; left: 0; height: 3px; background: #0f172a; z-index: 1000; transition: width 0.1s; }
/* 阅读进度环 */
.oa-reading-ring { position: fixed; bottom: 24px; right: 24px; width: 44px; height: 44px; cursor: pointer; z-index: 1001; opacity: .7; transition: opacity .3s; }
.oa-reading-ring:hover { opacity: 1; }
.oa-reading-ring-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 10px; font-weight: 600; color: #0f172a; pointer-events: none; }
/* 专注模式 */
.oa-focus-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 999; cursor: pointer; }
.focus-active { position: relative; z-index: 1000; }
.focus-hidden { display: none !important; }
/* 主题 */
.theme-sepia .oa-article-body { background: #fbf7e9; }
.theme-sepia .oa-article-content { color: #5f4b32; }
.theme-night .oa-article-body { background: #0a0a1a; }
.theme-night .oa-article-content { color: #7a8ba8; }
.chat-dark-mode .theme-night .oa-article-content { color: #7a8ba8; }
.oa-article-tags { margin-top: 20px; display: flex; gap: 6px; flex-wrap: wrap; }
/* 多图展示 */
.oa-article-images { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-top: 16px; }
.oa-article-image-item { border-radius: 6px; overflow: hidden; cursor: pointer; border: 1px solid #eee; }
.chat-dark-mode .oa-article-image-item { border-color: #2a2a3e; }
.oa-article-image-item img { width: 100%; height: 120px; object-fit: cover; display: block; transition: transform 0.2s; }
.oa-article-image-item img:hover { transform: scale(1.05); }
.oa-article-actions { display: flex; gap: 12px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; justify-content: center; }
.chat-dark-mode .oa-article-actions { border-color: #2a2a3e; }
.oa-article-prev-next { display: flex; gap: 16px; margin-top: 24px; }
.oa-pn-link { flex: 1; padding: 14px; border-radius: 8px; border: 1px solid #eee; text-decoration: none !important; color: inherit; transition: all 0.2s; display: block; }
.oa-pn-link:hover { border-color: #0f172a; background: #f0f7ff; }
.chat-dark-mode .oa-pn-link { border-color: #2a2a3e; }
.chat-dark-mode .oa-pn-link:hover { border-color: #0f172a; background: #16213e; }
.oa-pn-label { display: block; font-size: 12px; color: #999; margin-bottom: 4px; }
.oa-pn-title { font-size: 14px; font-weight: 500; }
.oa-pn-next { text-align: right; }
.oa-article-related { margin-top: 28px; }
.oa-article-related h3 { font-size: 18px; margin-bottom: 12px; }
.oa-related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.oa-related-card { border-radius: 8px; border: 1px solid #eee; overflow: hidden; cursor: pointer; text-decoration: none !important; color: inherit; transition: all 0.2s; }
.oa-related-card:hover { border-color: #0f172a; box-shadow: 0 2px 8px rgba(15,23,42,0.1); }
.chat-dark-mode .oa-related-card { border-color: #2a2a3e; }
.oa-related-cover { width: 100%; height: 120px; overflow: hidden; background: #f5f7fa; display: flex; align-items: center; justify-content: center; font-size: 32px; }
.chat-dark-mode .oa-related-cover { background: #1a1a2e; }
.oa-related-cover img { width: 100%; height: 100%; object-fit: cover; }
.oa-related-cover-text { color: #ccc; }
.oa-related-info { padding: 10px 12px; }
.oa-related-card-title { font-size: 14px; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.oa-related-desc { font-size: 12px; color: #999; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.oa-related-time { font-size: 11px; color: #ccc; margin-top: 4px; }
.oa-article-comments { margin-top: 32px; padding-top: 24px; border-top: 1px solid #eee; }
.chat-dark-mode .oa-article-comments { border-color: #2a2a3e; }
.oa-article-comments h3 { font-size: 18px; margin-bottom: 16px; }
.oa-comment-input { margin-bottom: 20px; }
.oa-comment-list { display: flex; flex-direction: column; gap: 16px; }
.oa-comment-item { display: flex; gap: 10px; }
.oa-comment-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.oa-comment-avatar-text { width: 36px; height: 36px; border-radius: 50%; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.oa-comment-body { flex: 1; min-width: 0; }
.oa-comment-author { font-size: 14px; font-weight: 600; color: #0f172a; }
.oa-comment-time { font-size: 12px; color: #999; margin-left: 8px; font-weight: 400; }
.oa-comment-region { font-size: 12px; color: #999; margin-left: 6px; font-weight: 400; }
.oa-comment-region::before { content: '· '; }
.oa-comment-text { font-size: 14px; margin: 4px 0; }
/* 表情反应 */
.oa-comment-reactions { display: flex; gap: 4px; margin-top: 4px; }
.oa-reaction-btn { display: inline-flex; align-items: center; gap: 2px; padding: 1px 6px; border-radius: 10px; font-size: 13px; cursor: pointer; border: 1px solid #eee; transition: all .2s; line-height: 1.6; user-select: none; }
.oa-reaction-btn:hover { border-color: #0f172a; background: #f0f7ff; }
.oa-reaction-btn.active { border-color: #0f172a; background: #e6f0ff; }
.oa-reaction-count { font-size: 11px; color: #909399; min-width: 8px; }
.chat-dark-mode .oa-reaction-btn { border-color: #2a2a3e; }
.chat-dark-mode .oa-reaction-btn:hover { border-color: #0f172a; background: #16213e; }
.chat-dark-mode .oa-reaction-btn.active { border-color: #0f172a; background: #16213e; }
.oa-comment-replies { margin-top: 8px; padding: 10px; background: #f5f7fa; border-radius: 6px; }
.chat-dark-mode .oa-comment-replies { background: #1a1a2e; }
.oa-comment-reply { padding: 4px 0; font-size: 13px; display: flex; align-items: flex-start; gap: 4px; }
.oa-reply-author { font-weight: 600; color: #67c23a; white-space: nowrap; }
.oa-reply-text { flex: 1; color: #333; }
.chat-dark-mode .oa-reply-text { color: #ccc; }
.oa-article-loading { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #999; gap: 12px; }
</style>
