<template>
    <div class="public-blog-page" :class="{ 'dark-mode': isDarkMode }">
    <div class="blog-hero">
      <h1>HWT License 开发者博客</h1>
      <p class="hero-desc">集成教程、最佳实践、客户案例与产品更新</p>
      <div class="hero-actions">
        <el-button @click="activeView = 'blog'" :type="activeView === 'blog' ? 'primary' : ''">博客</el-button>
        <el-button @click="activeView = 'changelog'" :type="activeView === 'changelog' ? 'primary' : ''">更新日志</el-button>
        <el-button @click="showSubscribe = true" type="success" plain>
          <el-icon><Message /></el-icon> 订阅更新
        </el-button>
        <el-button @click="handleFollow" :type="isFollowing ? 'info' : 'danger'" plain :loading="followLoading">
          {{ isFollowing ? '✅ 已关注' : '➕ 关注' }} {{ followerCount > 0 ? '(' + followerCount + ')' : '' }}
        </el-button>
        <el-button @click="showReadLater = true" type="warning" plain title="稍后阅读">📚</el-button>
        <el-button @click="isDarkMode = !isDarkMode" :type="isDarkMode ? 'primary' : 'default'" circle title="切换主题">🌙</el-button>
        <el-dropdown trigger="click" @command="setTheme">
          <el-button circle title="主题风格">🎨</el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="default">☀️ 默认</el-dropdown-item>
              <el-dropdown-item command="sepia">📜 羊皮纸</el-dropdown-item>
              <el-dropdown-item command="dark">🌙 深色</el-dropdown-item>
              <el-dropdown-item command="night">🌃 夜间</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>
      <!-- 标签筛选 -->
      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-top:12px">
        <el-input v-model="searchQuery" placeholder="搜索文章..." size="small" clearable style="width:200px" @keyup.enter="loadPosts" @clear="loadPosts" :prefix-icon="Search" />
      </div>
      <!-- 操作栏 -->
      <div class="list-toolbar" v-if="activeView === 'blog'">
        <div class="list-toolbar-left">
          <el-radio-group v-model="listViewMode" size="small">
            <el-radio-button value="grid">▦ 卡片</el-radio-button>
            <el-radio-button value="list">☰ 列表</el-radio-button>
          </el-radio-group>
          <el-select v-model="sortBy" size="small" style="width:110px" @change="loadPosts">
            <el-option label="最新发布" value="latest" />
            <el-option label="最早发布" value="oldest" />
            <el-option label="阅读时间短" value="short" />
            <el-option label="阅读时间长" value="long" />
          </el-select>
        </div>
        <div class="list-toolbar-right">
          <el-date-picker v-model="dateRange" type="daterange" size="small" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" style="width:220px" @change="loadPosts" clearable />
        </div>
      </div>
      <div class="tag-filter" v-if="activeView === 'blog' && allTags.length > 0">
        <el-tag :type="activeTag === '' ? 'primary' : 'info'" size="small" @click="activeTag = ''; loadPosts()">全部</el-tag>
        <el-tag v-for="tag in allTags" :key="tag" :type="activeTag === tag ? 'primary' : 'info'" size="small"
          @click="activeTag = tag; loadPosts()" style="cursor:pointer;margin:2px 4px">{{ tag }}</el-tag>
      </div>
    </div>

    <!-- 主内容区 -->
    <div class="blog-main-content">
      <!-- 博客列表 -->
      <div v-if="activeView === 'blog'" class="blog-list" :class="'view-' + listViewMode">
        <el-row :gutter="24" v-if="pageLoading">
          <el-col :xs="24" :sm="12" :md="8" v-for="i in 6" :key="i" class="mb-4"><div class="skeleton-card"></div></el-col>
        </el-row>
        <!-- 卡片视图 -->
        <el-row :gutter="24" v-else-if="listViewMode === 'grid' && posts.length > 0">
          <el-col :xs="24" :sm="12" :md="8" v-for="post in posts" :key="post.id" class="mb-4">
            <el-card shadow="hover" class="post-card" @click="openPost(post.slug)">
              <div v-if="post.featured_image" class="card-cover">
                <img :src="post.featured_image" :alt="post.title" loading="lazy" />
              </div>
              <div class="post-type">
                <el-tag :type="post.type === 'changelog' ? 'warning' : 'primary'" size="small" effect="dark">
                  {{ post.type === 'blog' ? '博客' : post.type === 'changelog' ? '更新日志' : '发布说明' }}
                </el-tag>
                <span>
                  <el-button text size="small" @click.stop="toggleReadLater(post)" :type="isInReadLater(post.id) ? 'warning' : 'default'" title="稍后阅读" style="padding:0 2px;font-size:14px">{{ isInReadLater(post.id) ? '📚' : '📑' }}</el-button>
                </span>
                <span class="post-date">{{ formatDate(post.published_at) }}</span>
              </div>
              <h3 class="post-title">{{ post.title }}</h3>
              <p class="post-excerpt">{{ post.excerpt || stripHtml(post.content).slice(0, 120) + '...' }}</p>
              <div class="post-footer">
                <span class="post-author">{{ post.author }}</span>
                <span class="post-read-time">⏱️ {{ calcReadingTime(post.content) }} 分钟</span>
                <span class="post-tags">
                  <el-tag v-for="tag in (post.tags || []).slice(0, 2)" :key="tag" size="small" round>{{ tag }}</el-tag>
                </span>
              </div>
            </el-card>
          </el-col>
        </el-row>
        <!-- 列表视图 -->
        <div v-else-if="listViewMode === 'list' && posts.length > 0" class="list-view">
          <div v-for="post in posts" :key="post.id" class="list-item" @click="openPost(post.slug)">
            <div class="list-item-left">
              <h3 class="list-item-title">{{ post.title }}</h3>
              <p class="list-item-excerpt">{{ post.excerpt || stripHtml(post.content).slice(0, 150) + '...' }}</p>
              <div class="list-item-meta">
                <el-tag :type="post.type === 'changelog' ? 'warning' : 'primary'" size="small">{{ post.type === 'blog' ? '博客' : post.type === 'changelog' ? '更新日志' : '发布说明' }}</el-tag>
                <span>{{ post.author }}</span>
                <span>{{ formatDate(post.published_at) }}</span>
                <span>⏱️ {{ calcReadingTime(post.content) }} 分钟</span>
                <el-button text size="small" @click.stop="toggleReadLater(post)" :type="isInReadLater(post.id) ? 'warning' : 'default'" style="padding:0">📚</el-button>
              </div>
            </div>
            <div v-if="post.featured_image" class="list-item-right">
              <img :src="post.featured_image" :alt="post.title" loading="lazy" class="list-item-img" />
            </div>
          </div>
        </div>
        <el-empty v-else description="暂无文章" />
        <!-- 分页 -->
        <div class="pagination-wrap" v-if="totalPosts > pageSize">
          <el-pagination v-model:current-page="page" :page-size="pageSize" :total="totalPosts" layout="prev, pager, next" @current-change="loadPosts" />
        </div>
      </div>
!-- 关注博客 -->
        <div class="sidebar-section">
          <div class="sidebar-title">👋 关注博客</div>
          <div style="text-align:center;padding:8px 0">
            <el-button @click="handleFollow" :type="isFollowing ? 'info' : 'danger'" :loading="followLoading" style="width:100%">
              {{ isFollowing ? '✅ 已关注' : '➕ 关注博客' }}
            </el-button>
            <div style="font-size:12px;color:#909399;margin-top:6px">
              👥 {{ followerCount }} 人关注
            </div>
          </div>
        </div>
        <
      <!-- 热门文章侧边栏 -->
      <div v-if="activeView === 'blog'" class="blog-sidebar">
        <div class="sidebar-section">
          <div class="sidebar-title">🔥 热门文章</div>
          <div v-for="p in trendingPosts" :key="p.id" class="sidebar-item" @click="openPost(p.slug)">
            <span class="sidebar-rank" :class="'rank-' + (trendingPosts.indexOf(p) + 1)">{{ trendingPosts.indexOf(p) + 1 }}</span>
            <div class="sidebar-item-content">
              <div class="sidebar-item-title">{{ p.title }}</div>
              <div class="sidebar-item-meta">👁️ {{ p.views_count || 0 }} · {{ formatDate(p.published_at) }}</div>
            </div>
          </div>
        </div>
        <div class="sidebar-section">
          <div class="sidebar-title">📊 阅读成就</div>
          <div style="font-size:12px;color:#606266;line-height:1.8">
            <div>📚 已读 {{ readingStats.totalRead }} 篇</div>
            <div>⏱️ 累计 {{ readingStats.totalMinutes }} 分钟</div>
            <div>🔥 {{ achievements.value.length }} 个成就</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Changelog 按版本 -->
    <div v-else class="changelog-view">
      <div v-for="(versionPosts, version) in changelogByVersion" :key="version" class="version-group">
        <h2 class="version-title">
          <el-tag type="warning" size="large" effect="dark">v{{ version }}</el-tag>
        </h2>
        <el-timeline>
          <el-timeline-item v-for="post in versionPosts" :key="post.id" :timestamp="formatDate(post.published_at)" placement="top">
            <el-card shadow="hover" @click="openPost(post.slug)" class="changelog-card">
              <h4>{{ post.title }}</h4>
              <p class="post-excerpt">{{ post.excerpt || stripHtml(post.content).slice(0, 200) }}</p>
              <el-space v-if="post.tags?.length" class="mt-1">
                <el-tag v-for="tag in post.tags" :key="tag" size="small" round type="warning">{{ tag }}</el-tag>
              </el-space>
            </el-card>
          </el-timeline-item>
        </el-timeline>
      </div>
    </div>

    <!-- 文章详情弹窗 -->
    <el-dialog v-model="showPost" :title="currentPost?.title" fullscreen @closed="onPostDialogClosed" :class="'theme-' + currentTheme">
      <!-- 阅读进度环 -->
      <div class="reading-ring" @click="focusMode = !focusMode" :title="focusMode ? '退出专注模式' : '专注模式'">
        <svg width="44" height="44" viewBox="0 0 44 44">
          <circle cx="22" cy="22" r="18" fill="none" stroke="#e8e8e8" stroke-width="3" />
          <circle cx="22" cy="22" r="18" fill="none" stroke="#409eff" stroke-width="3"
            stroke-linecap="round" :stroke-dasharray="113.1"
            :stroke-dashoffset="113.1 - (readingProgress / 100) * 113.1"
            transform="rotate(-90 22 22)" style="transition: stroke-dashoffset 0.2s" />
        </svg>
          <span class="ml-2">
            <el-button size="small" :type="isFollowing ? 'info' : 'danger'" plain @click="handleFollow" :loading="followLoading" style="padding:2px 8px;font-size:12px">
              {{ isFollowing ? '✅ 已关注' : '➕ 关注' }}
            </el-button>
            <span style="font-size:12px;color:#909399;margin-left:4px">👥 {{ followerCount }}</span>
          </span>
        <span class="reading-ring-text">{{ readingProgress }}%</span>
      </div>
      <!-- 专注模式遮罩 -->
      <div v-if="focusMode" class="focus-overlay" @click="focusMode = false"></div>
      <!-- 顶部进度条 -->
      <div class="reading-progress-bar" :style="{ width: readingProgress + '%' }"></div>
      <div class="post-detail" :class="{ 'focus-active': focusMode }" v-if="currentPost" ref="postDetailRef">
        <!-- 元信息 -->
        <div class="post-meta" :class="{ 'focus-hidden': focusMode }">
          <el-tag :type="currentPost.type === 'changelog' ? 'warning' : 'primary'" size="small">{{ currentPost.type }}</el-tag>
          <span class="ml-2">作者: {{ currentPost.author }}</span>
          <span class="ml-2">{{ formatDate(currentPost.published_at) }}</span>
          <span v-if="currentPost.version" class="ml-2">版本: {{ currentPost.version }}</span>
          <span class="ml-2">⏱️ {{ readingTime }} 分钟阅读</span>
          <span class="ml-2">📝 {{ wordCount }} 字</span>
        </div>
        <!-- 字号切换 + 操作栏 -->
        <div :class="['post-actions-bar', { 'focus-hidden': focusMode }]">
          <div class="post-actions-left">
            <el-button size="small" text @click="fontSizeIndex = Math.max(0, fontSizeIndex - 1)" :disabled="fontSizeIndex === 0" title="减小字号">A-</el-button>
            <el-button size="small" text @click="fontSizeIndex = Math.min(fontSizes.length - 1, fontSizeIndex + 1)" :disabled="fontSizeIndex === fontSizes.length - 1" title="增大字号">A+</el-button>
            <span class="font-size-label">{{ fontSizes[fontSizeIndex] }}px</span>
            <el-button size="small" text @click="fontSerif = !fontSerif" :type="fontSerif ? 'primary' : 'default'" title="切换衬线字体">Aa</el-button>
            <el-button size="small" :type="isSpeaking ? 'primary' : 'default'" text @click="toggleSpeech" title="语音朗读">🔊</el-button>
            <el-button v-if="isSpeaking" size="small" text @click="speechRate = Math.max(0.5, speechRate - 0.25)">慢</el-button>
            <el-button v-if="isSpeaking" size="small" text @click="speechRate = Math.min(2, speechRate + 0.25)">快</el-button>
            <el-button size="small" text @click="generatePoster" title="生成海报分享">🖼️</el-button>
            <el-button size="small" :type="focusMode ? 'primary' : 'default'" text @click="focusMode = !focusMode" title="专注模式">🧘</el-button>
            <el-button size="small" text @click="saveHighlight" title="高亮选中文字（先选中文字）🖊">🖊</el-button>
            <el-button size="small" :type="readingTimerActive ? 'primary' : 'default'" text @click="toggleReadingTimer" title="阅读计时">⏱️</el-button>
            <el-button size="small" text @click="showReadingStats = true" title="阅读统计">📊</el-button>
          </div>
          <div class="post-actions-right">
            <el-button size="small" :type="isLiked ? 'primary' : 'default'" text @click="handleLike">👍 {{ likes }}</el-button>
            <el-button size="small" text @click="copyAsMarkdown" title="复制为 Markdown">📄 MD</el-button>
            <el-dropdown trigger="click" @command="handleShare">
              <el-button size="small" text>📤 分享 <el-icon><ArrowDown /></el-icon></el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item command="wechat">💚 微信</el-dropdown-item>
                  <el-dropdown-item command="weibo">🔴 微博</el-dropdown-item>
                  <el-dropdown-item command="copy">🔗 复制链接</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </div>
        </div>
        <!-- 目录导航 -->
        <div v-if="tocItems.length > 0" class="post-toc">
          <div class="toc-header" @click="showToc = !showToc">📑 目录 <el-icon><ArrowDown /></el-icon></div>
          <div v-show="showToc" class="toc-body">
            <div v-for="(item, idx) in tocItems" :key="idx" class="toc-item"
              :style="{ paddingLeft: (item.level - 1) * 16 + 'px' }"
              @click="scrollToHeading(idx)">
              <span class="toc-dot"></span>{{ item.text }}
            </div>
          </div>
        </div>
        <!-- AI 智能摘要 -->
        <div v-if="aiSummary" class="ai-summary-box">
          <div class="ai-summary-header">🤖 AI 摘要</div>
          <div class="ai-summary-text">{{ aiSummary }}</div>
        </div>
        <div v-else-if="!aiSummaryLoading && currentPost?.content" style="margin-bottom:8px">
          <el-button size="small" text @click="generateAiSummary" :loading="aiSummaryLoading">🤖 生成 AI 摘要</el-button>
        </div>
        <!-- 文章内容 -->
        <div class="post-content" :style="{ fontSize: fontSizes[fontSizeIndex] + 'px', fontFamily: fontSerif ? 'Georgia, serif' : '' }" v-html="currentPost.content" @click="onPostContentClick" @scroll="onContentScroll" ref="contentRef"></div>
        <!-- 相关文章 -->
        <div v-if="relatedPosts.length > 0" class="related-posts">
          <h3>📖 相关文章</h3>
          <div class="related-grid">
            <a v-for="r in relatedPosts" :key="r.id" class="related-card" @click="openPost(r.slug)">
              <div class="related-card-title">{{ r.title }}</div>
              <div class="related-card-meta">{{ formatDate(r.published_at) }} · ⏱️ {{ calcReadingTime(r.content) }} 分钟</div>
            </a>
          </div>
        </div>
        <!-- 评论区 -->
        <div class="post-comments">
          <h3>💬 评论 ({{ comments.length }})
            <span style="font-size:12px;font-weight:400;margin-left:8px">
              <el-radio-group v-model="commentSort" size="small" @change="sortComments">
                <el-radio-button value="newest">最新</el-radio-button>
                <el-radio-button value="oldest">最早</el-radio-button>
              </el-radio-group>
            </span>
          </h3>
          <div class="comment-input">
            <el-input v-model="newCommentText" type="textarea" :rows="2" placeholder="写下你的评论..." maxlength="1000" :disabled="!isLoggedIn" />
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px">
              <span v-if="!isLoggedIn" style="font-size:12px;color:#909399">请登录后评论</span>
              <span v-else></span>
              <el-button size="small" type="primary" :loading="submittingComment" @click="submitComment" :disabled="!isLoggedIn">发表评论</el-button>
            </div>
          </div>
          <div v-if="comments.length > 0" class="comment-list">
            <div v-for="c in comments" :key="c.id" class="comment-item">
              <div class="comment-avatar">{{ c.user?.name?.charAt(0) || '?' }}</div>
              <div class="comment-body">
                <div class="comment-author">{{ c.user?.name || '匿名' }} <span class="comment-time">{{ formatTime(c.created_at) }}</span></div>
                <div class="comment-text">{{ c.content }}</div>
                <div class="comment-actions">
                  <el-button text size="small" @click="startReply(c)" style="font-size:12px;color:#999">💬 回复</el-button>
                  <el-button v-if="c.user_id === currentUserId" text size="small" type="danger" @click="deleteComment(c)" style="font-size:12px;padding:0">删除</el-button>
                </div>
                <!-- 表情反应 -->
                <div class="comment-reactions">
                  <span v-for="emoji in reactionEmojis" :key="emoji" class="reaction-btn"
                    :class="{ active: getReaction(c.id, emoji) }"
                    @click="toggleReaction(c.id, emoji)">
                    {{ emoji }}
                    <span v-if="getReactionCount(c.reactions, emoji)" class="reaction-count">{{ getReactionCount(c.reactions, emoji) }}</span>
                  </span>
                </div>
                <div v-if="c.replies?.length" class="comment-replies">
                  <div v-for="r in c.replies" :key="r.id" class="comment-reply">
                    <span class="reply-author">{{ r.user?.name }}：</span>{{ r.content }}
                  </div>
                </div>
                <div v-if="replyingTo === c.id" class="reply-box" style="display:flex;gap:6px;margin-top:6px">
                  <el-input v-model="replyText" placeholder="输入回复..." size="small" style="flex:1" maxlength="1000" />
                  <el-button size="small" type="primary" :loading="replying" @click="submitReply(c)">发送</el-button>
                  <el-button size="small" @click="replyingTo = null">取消</el-button>
                </div>
              </div>
            </div>
          </div>
          <div v-else style="text-align:center;padding:16px;color:#999;font-size:13px">暂无评论，来说两句吧</div>
        </div>
      </div>
    </el-dialog>

    <!-- 订阅对话框 -->
    <el-dialog v-model="showSubscribe" title="订阅更新通知" width="450px">
      <el-form :model="subForm" label-position="top" @submit.prevent="handleSubscribe">
        <el-form-item label="邮箱" required>
          <el-input v-model="subForm.email" placeholder="your@email.com" type="email" />
        </el-form-item>
        <el-form-item label="姓名">
          <el-input v-model="subForm.name" placeholder="（选填）" />
        </el-form-item>
        <el-form-item label="订阅类型" required>
          <el-checkbox-group v-model="subForm.subscribed_types">
            <el-checkbox value="blog">博客文章</el-checkbox>
            <el-checkbox value="changelog">更新日志</el-checkbox>
            <el-checkbox value="release_note">发布说明</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" native-type="submit" :loading="subscribing">确认订阅</el-button>
        </el-form-item>
      </el-form>
    </el-dialog>

    <!-- 稍后阅读对话框 -->
    <el-dialog v-model="showReadLater" title="📚 稍后阅读" width="500px">
      <div v-if="readLaterList.length === 0" style="text-align:center;padding:32px;color:#999">暂无收藏</div>
      <div v-else>
        <div v-for="item in readLaterList" :key="item.id" style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #eee">
          <div style="flex:1;cursor:pointer" @click="openPost(item.slug)">
            <div style="font-size:14px;font-weight:500">{{ item.title }}</div>
            <div style="font-size:12px;color:#999">{{ formatDate(item.addedAt) }} · ⏱️ {{ calcReadingTime(item.content || '') }} 分钟</div>
          </div>
          <el-button text size="small" type="danger" @click="removeReadLater(item.id)" style="flex-shrink:0">✕</el-button>
        </div>
      </div>
    </el-dialog>

    <!-- RSS 链接 -->
    <div class="rss-bar">
      <span>📡 RSS 订阅：</span>
      <a :href="baseUrl + '/api/rss/all'">全部</a>
      <a :href="baseUrl + '/api/rss/blog'">博客</a>
      <a :href="baseUrl + '/api/rss/changelog'">更新日志</a>
    </div>
    <!-- 社交链接 -->
    <div class="social-links">
      <a href="https://github.com" target="_blank" rel="noopener">🐙 GitHub</a>
      <a href="https://twitter.com" target="_blank" rel="noopener">🐦 Twitter</a>
      <a href="https://weibo.com" target="_blank" rel="noopener">🔴 微博</a>
    </div>

    <!-- 回到顶部 -->
    <el-backtop :right="24" :bottom="80" />

    <!-- 图片灯箱 -->
    <el-image-viewer v-if="showImageViewer" :url-list="[previewImageUrl]" @close="showImageViewer = false" :z-index="3000" />

    <!-- 海报分享对话框 -->
    <el-dialog v-model="showPosterDialog" title="🖼️ 分享海报" width="420px" :close-on-click-modal="true">
      <div style="text-align:center">
        <canvas ref="posterCanvas" style="max-width:100%;border-radius:8px;box-shadow:0 2px 12px rgba(0,0,0,.1)"></canvas>
      </div>
      <template #footer>
        <el-button size="small" @click="downloadPoster">📥 下载海报</el-button>
        <el-button size="small" type="primary" @click="copyPoster">🔗 复制分享链接</el-button>
      </template>
    </el-dialog>

    <!-- 阅读统计对话框 -->
    <el-dialog v-model="showReadingStats" title="📊 阅读统计" width="420px">
      <div class="stats-dashboard">
        <div class="sd-row">
          <div class="sd-card"><div class="sd-num">{{ readingStats.totalRead }}</div><div class="sd-label">总阅读</div></div>
          <div class="sd-card"><div class="sd-num">{{ readingStats.monthRead }}</div><div class="sd-label">本月</div></div>
          <div class="sd-card"><div class="sd-num">{{ readingStats.totalMinutes }}</div><div class="sd-label">总分钟</div></div>
          <div class="sd-card"><div class="sd-num">{{ readingStats.monthMinutes }}</div><div class="sd-label">本月分钟</div></div>
        </div>
        <div v-if="readingStats.topTags.length > 0" style="margin-top:12px">
          <div style="font-size:13px;font-weight:500;margin-bottom:8px;color:#303133">🏷️ 常用标签</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <el-tag v-for="[tag, count] in readingStats.topTags" :key="tag" size="small">{{ tag }} ({{ count }})</el-tag>
          </div>
        </div>
        <!-- 成就徽章 -->
        <div v-if="achievements.length > 0" style="margin-top:12px">
          <div style="font-size:13px;font-weight:500;margin-bottom:8px;color:#303133">🏆 成就</div>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <el-tag v-for="a in achievements" :key="a.id" size="small" effect="dark" type="success">{{ a.icon }} {{ a.label }}</el-tag>
          </div>
        </div>
        <!-- 阅读热力图 -->
        <div style="margin-top:16px">
          <div style="font-size:13px;font-weight:500;margin-bottom:6px;color:#303133">📊 阅读热力图（近90天）</div>
          <div class="heatmap">
            <div v-for="d in heatmapData" :key="d.date" class="heatmap-cell"
              :class="'level-' + Math.min(d.count, 4)"
              :title="d.date + ': ' + d.count + ' 篇文章'">
            </div>
          </div>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch, nextTick } from 'vue';
import { ElMessage, ElImageViewer } from 'element-plus';
import { Message, Search } from '@element-plus/icons-vue';
import blogApi, { followBlog, unfollowBlog, getFollowStatus, getFollowerCount } from '@/api/blog';
import apiClient from '@/api/client';

const baseUrl = window.location.origin;
const READING_POSITION_KEY = 'blog_read_position_';
const activeView = ref('blog');
const posts = ref([]);
const changelogByVersion = ref({});
const currentPost = ref(null);
const showPost = ref(false);
const showSubscribe = ref(false);
const subscribing = ref(false);
const isLiked = ref(false);
const likes = ref(0);
const fontSizeIndex = ref(1);
const fontSizes = [14, 15, 16, 18, 20, 24];
const readingProgress = ref(0);
const showToc = ref(true);
const tocItems = ref([]);
const postDetailRef = ref(null);
const contentRef = ref(null);
const page = ref(1);
const pageSize = ref(9);
const totalPosts = ref(0);
const activeTag = ref('');
const allTags = ref([]);
const showImageViewer = ref(false);
const previewImageUrl = ref('');
const searchQuery = ref('');
const pageLoading = ref(true);
const isDarkMode = ref(false);
const comments = ref([]);
const relatedPosts = ref([]);
const newCommentText = ref('');
const submittingComment = ref(false);
const replyingTo = ref(null);
const replyText = ref('');
const replying = ref(false);
const isLoggedIn = ref(!!localStorage.getItem('auth_token'));
const currentUserId = ref(0);
const commentSort = ref('newest');
const fontSerif = ref(false);
const currentTheme = ref(localStorage.getItem('blogTheme') || 'default');
const focusMode = ref(false);
const isSpeaking = ref(false);
const speechRate = ref(1);
const showPosterDialog = ref(false);
const posterCanvas = ref(null);
const posterDataUrl = ref('');
const scrollSaveTimer = 0;
const reactionEmojis = ['👍', '❤️', '😮', '😢', '😡'];
const commentReactions = ref(JSON.parse(localStorage.getItem('blogCommentReactions') || '{}'));
const readingTimerActive = ref(false);
const readingTimerRemaining = ref(0);
const readingTimerDuration = ref(600);
const readingTimerInterval = ref(null);
const showReadingStats = ref(false);
const readingHistory = ref(JSON.parse(localStorage.getItem('blogReadingHistory') || '[]'));
const aiSummary = ref('');
const aiSummaryLoading = ref(false);
const noteHighlights = ref(JSON.parse(localStorage.getItem('blogHighlights') || '[]'));
const readLaterList = ref(JSON.parse(localStorage.getItem('blogReadLater') || '[]'));
const showReadLater = ref(false);
const listViewMode = ref('grid');
const sortBy = ref('latest');
const dateRange = ref(null);
const trendingPosts = ref([]);
const isFollowing = ref(false);
const followerCount = ref(0);
const followLoading = ref(false);

const subForm = reactive({
  email: '', name: '', subscribed_types: ['blog', 'changelog'],
});

const readingTime = computed(() => {
  const text = currentPost.value ? stripHtml(currentPost.value.content) : '';
  const words = text.replace(/[\u4e00-\u9fa5]/g, 'x').split(/\s+/).filter(Boolean).length;
  const cjk = (text.match(/[\u4e00-\u9fa5]/g) || []).length;
  return Math.max(1, Math.ceil((cjk + words) / 300));
});
const wordCount = computed(() => {
  const text = currentPost.value ? stripHtml(currentPost.value.content) : '';
  return text.replace(/\s/g, '').length;
});

function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

function stripHtml(html) {
  const div = document.createElement('div');
  div.innerHTML = html || '';
  return div.textContent || '';
}

function calcReadingTime(html) {
  const text = stripHtml(html);
  const cjk = (text.match(/[\u4e00-\u9fa5]/g) || []).length;
  const words = text.replace(/[\u4e00-\u9fa5]/g, 'x').split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.ceil((cjk + words) / 300));
}

function openPost(slug) {
  blogApi.getBySlug(slug).then(({ data }) => {
    if (data?.data) {
      currentPost.value = data.data;
      showPost.value = true;
      document.title = data.data.title + ' - HWT License 开发者博客';
      // 设置 meta description
      let desc = data.data.excerpt || stripHtml(data.data.content).slice(0, 160);
      setMetaDescription(desc);
      showToc.value = true;
      readingProgress.value = 0;
      fontSizeIndex.value = 1;
      isLiked.value = false;
      likes.value = data.data.likes_count || 0;
      // 提取目录 + 图片懒加载
      const div = document.createElement('div');
      div.innerHTML = data.data.content || '';
      // 给所有图片添加 loading="lazy"
      div.querySelectorAll('img').forEach(img => img.setAttribute('loading', 'lazy'));
      // 给代码块添加语言标签 + JS 运行按钮
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
            runBtn.className = 'code-run-btn'; runBtn.textContent = '▶️ 运行';
            runBtn.onclick = function(e) { e.stopPropagation();
              const p = this.closest('pre'); const code = p?.querySelector('code')?.textContent || '';
              const out = p?.querySelector('.code-runner-output');
              if (out) { out.remove(); if (this.textContent === '⏹ 停止') { this.textContent = '▶️ 运行'; return; } }
              const d = document.createElement('div'); d.className = 'code-runner-output';
              d.innerHTML = '<div style="padding:8px;font-size:12px;color:#999">⏳ 运行中...</div>';
              p?.appendChild(d); this.textContent = '⏹ 停止';
              const logs = []; const origLog = console.log; console.log = function() { logs.push(Array.from(arguments).join(' ')); };
              try { const r = new Function(code)(); if (r !== undefined) logs.push('=> ' + r);
                d.innerHTML = '<div class="code-runner-header">▶ 运行结果</div><pre class="code-runner-pre">' +
                  (logs.length ? logs.map(l => l.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')).join('\n') : '<span style="color:#999">（无输出）</span>') + '</pre>';
              } catch (err) {
                d.innerHTML = '<div class="code-runner-header" style="color:#f56c6c">✕ 运行错误</div><pre class="code-runner-pre" style="color:#f56c6c">' +
                  err.message.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</pre>';
              } finally { console.log = origLog; this.textContent = '▶️ 运行'; }
            };
            pre.appendChild(runBtn);
          }
        }
      });
      const headings = div.querySelectorAll('h1, h2, h3');
      tocItems.value = Array.from(headings).map(h => ({
        level: parseInt(h.tagName[1]),
        text: h.textContent || ''
      }));
      // 将处理后的 HTML 设置回去
      data.data.content = div.innerHTML;
      // 内链悬浮预览 — 在 DOM 渲染后绑定事件
      setTimeout(() => setupLinkPreview(), 500);
      // 恢复高亮笔记
      setTimeout(() => setupHighlights(), 800);
      // 加载评论和推荐文章
      loadComments(data.data.id);
      loadRelatedPosts(data.data.id);
      // 记录浏览量
      apiClient.post('/blog/' + data.data.id + '/view').catch(() => {});
      // 记录阅读统计
      // 刷新关注状态
      loadFollowStatus();
      const history = JSON.parse(localStorage.getItem('blogReadingHistory') || '[]');
      history.unshift({ id: data.data.id, title: data.data.title, date: new Date().toISOString(), minutes: readingTime.value, tags: data.data.tags || [] });
      if (history.length > 200) history.length = 200;
      localStorage.setItem('blogReadingHistory', JSON.stringify(history));
      readingHistory.value = history;
      // 恢复阅读位置
      const savedPos = localStorage.getItem(READING_POSITION_KEY + data.data.id);
      if (savedPos) {
        setTimeout(() => {
          const el = contentRef.value?.$el || contentRef.value;
          if (el) setTimeout(() => { el.scrollTop = parseInt(savedPos); }, 100);
        }, 300);
      }
      if (isLoggedIn.value) {
        try {
          const userRes = await apiClient.get('/user');
          currentUserId.value = userRes.data?.data?.id || 0;
        } catch { isLoggedIn.value = false; }
      }
    }
  }).catch(() => {
    ElMessage.error('加载失败');
    currentPost.value = null;
  });
}

function onPostDialogClosed() {
  document.title = 'HWT License 开发者博客';
  setMetaDescription('');
  // 清除阅读位置
  if (currentPost.value?.id) {
    localStorage.removeItem(READING_POSITION_KEY + currentPost.value.id);
  }
}

function setMetaDescription(desc) {
  let meta = document.querySelector('meta[name="description"]');
  if (!meta) { meta = document.createElement('meta'); meta.name = 'description'; document.head.appendChild(meta); }
  meta.content = desc || '集成教程、最佳实践、客户案例与产品更新';
}

function onContentScroll() {
  const el = contentRef.value?.$el || contentRef.value;
  if (!el) return;
  const scrollTop = el.scrollTop || 0;
  const scrollHeight = el.scrollHeight - el.clientHeight;
  readingProgress.value = scrollHeight > 0 ? Math.min(100, Math.round(scrollTop / scrollHeight * 100)) : 0;
  // 保存阅读位置（节流）
  if (currentPost.value?.id && scrollTop > 0) {
    const key = READING_POSITION_KEY + currentPost.value.id;
    const now = Date.now();
    if (!scrollSaveTimer || now - scrollSaveTimer > 2000) {
      localStorage.setItem(key, scrollTop.toString());
      scrollSaveTimer = now;
    }
  }
}

function scrollToHeading(idx) {
  const items = contentRef.value?.querySelectorAll('h1, h2, h3');
  if (items && items[idx]) items[idx].scrollIntoView({ behavior: 'smooth' });
}

function handleLike() {
  isLiked.value = !isLiked.value;
  likes.value += isLiked.value ? 1 : -1;
  ElMessage.success(isLiked.value ? '❤️ 已点赞' : '已取消点赞');
}

function copyAsMarkdown() {
  if (!currentPost.value?.content) return;
  let md = currentPost.value.content
    .replace(/<h1[^>]*>(.*?)<\/h1>/gi, '# $1\n\n')
    .replace(/<h2[^>]*>(.*?)<\/h2>/gi, '## $1\n\n')
    .replace(/<h3[^>]*>(.*?)<\/h3>/gi, '### $1\n\n')
    .replace(/<strong>(.*?)<\/strong>/gi, '**$1**')
    .replace(/<em>(.*?)<\/em>/gi, '*$1*')
    .replace(/<code>(.*?)<\/code>/gi, '`$1`')
    .replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gi, '[$2]($1)')
    .replace(/<img[^>]*src="([^"]*)"[^>]*>/gi, '![image]($1)')
    .replace(/<li>(.*?)<\/li>/gi, '- $1\n')
    .replace(/<p[^>]*>(.*?)<\/p>/gi, '$1\n\n')
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<[^>]*>/g, '')
    .replace(/\n{3,}/g, '\n\n').trim()
  navigator.clipboard.writeText(md).then(() => ElMessage.success('✅ Markdown 已复制')).catch(() => ElMessage.error('复制失败'))
}

function handleShare(cmd) {
  const url = window.location.origin + '/build/blog';
  const title = currentPost.value?.title || '';
  if (cmd === 'wechat') {
    ElMessage.info('💚 请截图后分享到微信');
  } else if (cmd === 'weibo') {
    window.open('https://service.weibo.com/share/share.php?title=' + encodeURIComponent(title) + '&url=' + encodeURIComponent(url));
  } else if (cmd === 'copy') {
    navigator.clipboard.writeText(url).then(() => ElMessage.success('✅ 链接已复制')).catch(() => ElMessage.error('复制失败'));
  }
}

// ── 评论 ──
async function loadComments(blogId) {
  try {
    const res = await apiClient.get('/blog/' + blogId + '/comments');
    comments.value = res.data?.data?.data || res.data?.data || [];
  } catch { comments.value = []; }
}
function sortComments() {
  if (commentSort.value === 'oldest') {
    comments.value = [...comments.value].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
  } else {
    comments.value = [...comments.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
  }
}
async function loadRelatedPosts(blogId) {
  try {
    const res = await apiClient.get('/blog/' + blogId + '/related');
    relatedPosts.value = res.data?.data || [];
  } catch { relatedPosts.value = []; }
}
async function submitComment() {
  if (!newCommentText.value.trim() || !currentPost.value) return;
  submittingComment.value = true;
  try {
    await apiClient.post('/blog/' + currentPost.value.id + '/comments', { content: newCommentText.value });
    ElMessage.success('评论成功');
    newCommentText.value = '';
    loadComments(currentPost.value.id);
  } catch { ElMessage.error('评论失败'); }
  finally { submittingComment.value = false; }
}
function startReply(comment) {
  replyingTo.value = comment.id;
  replyText.value = '';
}
async function submitReply(comment) {
  if (!replyText.value.trim()) return;
  replying.value = true;
  try {
    await apiClient.post('/blog/' + currentPost.value.id + '/comments', { content: replyText.value, parent_id: comment.id });
    ElMessage.success('回复成功');
    replyText.value = '';
    replyingTo.value = null;
    loadComments(currentPost.value.id);
  } catch { ElMessage.error('回复失败'); }
  finally { replying.value = false; }
}
async function deleteComment(comment) {
  try {
    await apiClient.delete('/blog/' + currentPost.value.id + '/comments/' + comment.id);
    ElMessage.success('已删除');
    loadComments(currentPost.value.id);
  } catch { ElMessage.error('删除失败'); }
}
function formatTime(date) {
  if (!date) return '';
  return new Date(date).toLocaleString('zh-CN');
}

// ── 评论表情反应 ──
function getReaction(commentId, emoji) {
  return commentReactions.value[commentId + '_' + emoji] || false;
}
function getReactionCount(reactions, emoji) {
  return reactions?.[emoji] || 0;
}
function toggleReaction(commentId, emoji) {
  const key = commentId + '_' + emoji;
  commentReactions.value[key] = !commentReactions.value[key];
  localStorage.setItem('blogCommentReactions', JSON.stringify(commentReactions.value));
  // 更新 UI 上的计数
  const comment = comments.value.find(c => c.id === commentId);
  if (comment) {
    if (!comment.reactions) comment.reactions = {};
    comment.reactions[emoji] = (comment.reactions[emoji] || 0) + (commentReactions.value[key] ? 1 : -1);
    if (comment.reactions[emoji] <= 0) delete comment.reactions[emoji];
  }
}

// ── 创新功能 ──
function setTheme(theme) {
  currentTheme.value = theme;
  localStorage.setItem('blogTheme', theme);
}
function toggleSpeech() {
  if (!window.speechSynthesis) { ElMessage.warning('浏览器不支持语音朗读'); return; }
  if (isSpeaking.value) {
    window.speechSynthesis.cancel();
    isSpeaking.value = false;
    return;
  }
  const text = stripHtml(currentPost.value?.content || '');
  if (!text) { ElMessage.warning('没有可朗读的内容'); return; }
  const utterance = new SpeechSynthesisUtterance(text.substring(0, 5000));
  utterance.lang = 'zh-CN';
  utterance.rate = speechRate.value;
  utterance.onend = () => { isSpeaking.value = false; };
  utterance.onerror = () => { isSpeaking.value = false; ElMessage.error('朗读出错'); };
  window.speechSynthesis.speak(utterance);
  isSpeaking.value = true;
  ElMessage.info('🔊 开始朗读');
}
watch(speechRate, (val) => {
  if (isSpeaking.value && window.speechSynthesis) {
    window.speechSynthesis.cancel();
    toggleSpeech();
  }
});

// ── 海报分享 ──
async function generatePoster() {
  if (!currentPost.value) return;
  showPosterDialog.value = true;
  await nextTick();
  const canvas = posterCanvas.value;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const W = 400, H = 560;
  canvas.width = W; canvas.height = H;
  
  // 背景
  const grad = ctx.createLinearGradient(0, 0, W, H);
  grad.addColorStop(0, '#409eff'); grad.addColorStop(1, '#67c23a');
  ctx.fillStyle = grad; ctx.fillRect(0, 0, W, H);
  
  // 白色内容区
  ctx.fillStyle = '#fff';
  ctx.beginPath(); ctx.roundRect(20, 20, W - 40, H - 40, 12); ctx.fill();
  
  // 标题
  ctx.fillStyle = '#303133'; ctx.font = 'bold 18px sans-serif';
  const title = currentPost.value.title || '';
  wrapText(ctx, title, 40, 80, W - 80, 24, 2);
  
  // 摘录
  ctx.fillStyle = '#606266'; ctx.font = '13px sans-serif';
  const excerpt = currentPost.value.excerpt || stripHtml(currentPost.value.content).slice(0, 100);
  wrapText(ctx, excerpt, 40, 160, W - 80, 20, 4);
  
  // 信息
  ctx.fillStyle = '#909399'; ctx.font = '12px sans-serif';
  ctx.fillText('作者: ' + (currentPost.value.author || 'HWT'), 40, H - 140);
  ctx.fillText(formatDate(currentPost.value.published_at), 40, H - 120);
  
  // Logo
  ctx.fillStyle = '#409eff'; ctx.font = 'bold 16px sans-serif';
  ctx.fillText('HWT License', 40, H - 90);
  
  // QR Code
  const qrUrl = window.location.origin + '/build/blog#' + currentPost.value.slug;
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' + encodeURIComponent(qrUrl);
  img.onload = () => {
    ctx.drawImage(img, W - 130, H - 160, 80, 80);
    posterDataUrl.value = canvas.toDataURL('image/png');
  };
  img.onerror = () => {
    ctx.fillStyle = '#999'; ctx.font = '11px sans-serif';
    ctx.fillText('QR Code', W - 110, H - 120);
    posterDataUrl.value = canvas.toDataURL('image/png');
  };
}
function wrapText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
  const chars = text.split('');
  let line = '', lineCount = 0;
  for (const ch of chars) {
    const test = line + ch;
    if (ctx.measureText(test).width > maxWidth && line) {
      ctx.fillText(line, x, y); y += lineHeight; line = ch; lineCount++;
      if (lineCount >= maxLines) { ctx.fillText('...', x, y); return; }
    } else { line = test; }
  }
  if (line) ctx.fillText(line, x, y);
}
function downloadPoster() {
  if (!posterDataUrl.value) return;
  const a = document.createElement('a');
  a.href = posterDataUrl.value; a.download = (currentPost.value?.title || 'poster') + '.png'; a.click();
}
function copyPoster() {
  const url = window.location.origin + '/build/blog#' + currentPost.value?.slug;
  navigator.clipboard.writeText(url).then(() => ElMessage.success('✅ 链接已复制')).catch(() => ElMessage.error('复制失败'));
}

// ── 阅读计时器 ──
function toggleReadingTimer() {
  if (readingTimerActive.value) {
    clearInterval(readingTimerInterval.value); readingTimerActive.value = false; readingTimerRemaining.value = 0; return;
  }
  const durations = [300, 600, 900, 1200, 1800];
  const idx = durations.indexOf(readingTimerDuration.value);
  readingTimerDuration.value = durations[(idx + 1) % durations.length];
  readingTimerRemaining.value = readingTimerDuration.value;
  readingTimerActive.value = true;
  ElMessage.info('⏱️ 阅读计时: ' + (readingTimerDuration.value / 60) + ' 分钟');
  readingTimerInterval.value = setInterval(() => {
    readingTimerRemaining.value--;
    if (readingTimerRemaining.value <= 0) {
      clearInterval(readingTimerInterval.value); readingTimerActive.value = false;
      ElMessage.success('🎉 阅读目标达成！');
    }
  }, 1000);
}
function formatTimer(secs) {
  const m = Math.floor(secs / 60), s = secs % 60;
  return m + ':' + (s < 10 ? '0' : '') + s;
}
// ── 阅读统计 ──
const readingStats = computed(() => {
  const h = readingHistory.value;
  const now = new Date(), thisMonth = now.getMonth(), thisYear = now.getFullYear();
  const monthData = h.filter(r => { const d = new Date(r.date); return d.getMonth() === thisMonth && d.getFullYear() === thisYear; });
  const totalRead = h.length;
  const monthRead = monthData.length;
  const totalMinutes = h.reduce((s, r) => s + (r.minutes || 0), 0);
  const monthMinutes = monthData.reduce((s, r) => s + (r.minutes || 0), 0);
  const tags = {};
  h.forEach(r => (r.tags || []).forEach(t => { tags[t] = (tags[t] || 0) + 1; }));
  const topTags = Object.entries(tags).sort((a, b) => b[1] - a[1]).slice(0, 5);
  return { totalRead, monthRead, totalMinutes, monthMinutes, topTags };
});

// ── AI 智能摘要 ──
async function generateAiSummary() {
  if (!currentPost.value?.content) return; aiSummaryLoading.value = true;
  try {
    const text = stripHtml(currentPost.value.content).substring(0, 2000);
    const res = await apiClient.post('/user-chat/ai-conversation', { message: '请用2-3句话概括以下文章核心内容：\n' + text });
    aiSummary.value = res.data?.data?.reply || res.data?.reply || '生成失败';
  } catch { aiSummary.value = '生成失败'; } finally { aiSummaryLoading.value = false; }
}

// ── 稍后阅读 ──
function isInReadLater(id) { return readLaterList.value.some(i => i.id === id); }
function toggleReadLater(post) {
  if (isInReadLater(post.id)) { removeReadLater(post.id); ElMessage.info('已移除'); return; }
  readLaterList.value.unshift({ id: post.id, title: post.title, slug: post.slug, content: post.content, addedAt: new Date().toISOString() });
  localStorage.setItem('blogReadLater', JSON.stringify(readLaterList.value)); ElMessage.success('📚 已加入稍后阅读');
}
function removeReadLater(id) { readLaterList.value = readLaterList.value.filter(i => i.id !== id); localStorage.setItem('blogReadLater', JSON.stringify(readLaterList.value)); }

// ── 文章批注/高亮 ──
function setupHighlights() {
  setTimeout(() => {
    const saved = noteHighlights.value; const el = document.querySelector('.post-content'); if (!el || !saved.length) return;
    saved.forEach(h => {
      try {
        const range = document.createRange(); const start = findTextOffset(el, h.startOffset);
        const end = findTextOffset(el, h.endOffset);
        if (start && end) { range.setStart(start.node, start.off); range.setEnd(end.node, end.off);
          const mark = document.createElement('mark'); mark.className = 'user-highlight'; mark.dataset.note = h.note || ''; mark.title = h.note || '';
          range.surroundContents(mark); }
      } catch (e) {}
    });
  }, 600);
}
function findTextOffset(root, offset) { let c = 0; const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT); while (w.nextNode()) { const l = w.currentNode.textContent.length; if (c + l > offset) return { node: w.currentNode, off: offset - c }; c += l; } return null; }
function saveHighlight() {
  const sel = window.getSelection(); if (!sel.rangeCount || !sel.toString().trim()) return;
  const r = sel.getRangeAt(0); const s = getAbsOffset(r.startContainer, r.startOffset); const e = getAbsOffset(r.endContainer, r.endOffset);
  const note = prompt('添加备注（选填）：') || '';
  noteHighlights.value.push({ startOffset: s, endOffset: e, note, date: new Date().toISOString() });
  localStorage.setItem('blogHighlights', JSON.stringify(noteHighlights.value));
  const mark = document.createElement('mark'); mark.className = 'user-highlight'; mark.dataset.note = note; mark.title = note;
  r.surroundContents(mark); sel.removeAllRanges(); ElMessage.success('✅ 已高亮');
}
function getAbsOffset(node, off) { let c = 0; const w = document.createTreeWalker(document.querySelector('.post-content'), NodeFilter.SHOW_TEXT); while (w.nextNode()) { if (w.currentNode === node) return c + off; c += w.currentNode.textContent.length; } return off; }

// ── 阅读成就 ──
const achievements = computed(() => {
  const h = readingHistory.value; const days = new Set(h.map(r => r.date?.substring(0, 10)).filter(Boolean));
  let streak = 0; const sorted = Array.from(days).sort().reverse();
  if (sorted.length) { streak = 1; for (let i = 0; i < sorted.length - 1; i++) { const d = (new Date(sorted[i]) - new Date(sorted[i+1])) / 86400000; if (d <= 1.5) streak++; else break; } }
  const count = h.length; const mc = h.filter(r => { const d = new Date(r.date); return d.getMonth() === new Date().getMonth() && d.getFullYear() === new Date().getFullYear(); }).length;
  return [
    { id: 'first', icon: '🎉', label: '第一次阅读', earned: count >= 1 },
    { id: 'streak3', icon: '🔥', label: '连续阅读3天', earned: streak >= 3 },
    { id: 'streak7', icon: '🔥', label: '连续阅读7天', earned: streak >= 7 },
    { id: 'read10', icon: '📖', label: '读完10篇文章', earned: count >= 10 },
    { id: 'read50', icon: '🏅', label: '读完50篇文章', earned: count >= 50 },
    { id: 'monthly', icon: '👑', label: '月度阅读冠军', earned: mc >= 10 },
  ].filter(a => a.earned);
});

// ── 阅读热力图（90天）──
const heatmapData = computed(() => {
  const h = readingHistory.value; const data = {}; const now = new Date();
  for (let i = 89; i >= 0; i--) { const d = new Date(now); d.setDate(d.getDate() - i); data[d.toISOString().substring(0, 10)] = 0; }
  h.forEach(r => { const day = r.date?.substring(0, 10); if (data[day] !== undefined) data[day]++; });
  return Object.entries(data).map(([d, c]) => ({ date: d, count: c }));
});

// ── 获取成就徽章文本 ──
function achievementText() {
  const earned = achievements.value;
  return earned.length ? earned.map(a => a.icon + ' ' + a.label).join(' · ') : '开始阅读以获得成就';
}

// ── 内链悬浮预览 ──
let linkPreviewEl = null;
let linkPreviewTimer = null;
function setupLinkPreview() {
  const container = document.querySelector('.post-content');
  if (!container) return;
  if (!linkPreviewEl) {
    linkPreviewEl = document.createElement('div');
    linkPreviewEl.className = 'link-preview-card';
    document.body.appendChild(linkPreviewEl);
  }
  container.querySelectorAll('a[href*="/build/blog"]').forEach(a => {
    a.addEventListener('mouseenter', onLinkEnter);
    a.addEventListener('mouseleave', onLinkLeave);
  });
}
function onLinkEnter(e) {
  const a = e.currentTarget;
  const href = a.getAttribute('href') || '';
  const match = href.match(/\/build\/blog(?:\/|#)(.+)/);
  if (!match) return;
  const slug = match[1].split(/[?#]/)[0];
  if (!slug) return;
  clearTimeout(linkPreviewTimer);
  linkPreviewTimer = setTimeout(async () => {
    try {
      const res = await blogApi.getBySlug(slug);
      const post = res.data?.data;
      if (!post) return;
      linkPreviewEl.innerHTML = '<div class="lp-title">' + (post.title || '') + '</div>' +
        '<div class="lp-desc">' + (post.excerpt || '').substring(0, 100) + '</div>' +
        '<div class="lp-meta">⏱️ ' + calcReadingTime(post.content) + ' 分钟</div>';
      const rect = a.getBoundingClientRect();
      linkPreviewEl.style.display = 'block';
      linkPreviewEl.style.left = Math.min(rect.left, window.innerWidth - 320) + 'px';
      linkPreviewEl.style.top = (rect.bottom + 8) + 'px';
    } catch { /* ignore */ }
  }, 300);
}
function onLinkLeave() {
  clearTimeout(linkPreviewTimer);
  if (linkPreviewEl) linkPreviewEl.style.display = 'none';
}

async function handleSubscribe() {
  if (!subForm.email) { ElMessage.warning('请输入邮箱'); return; }
  if (!subForm.subscribed_types.length) { ElMessage.warning('请选择订阅类型'); return; }

  subscribing.value = true;
  try {
    const { data } = await blogApi.subscribe(subForm);
    if (data.success) {
      ElMessage.success(data.message);
      showSubscribe.value = false;
    }
  } catch {
    ElMessage.error('订阅失败');
  } finally {
    subscribing.value = false;
  }
}

// ── 关注功能 ──
async function loadFollowStatus() {
  try {
    if (isLoggedIn.value) {
      const res = await getFollowStatus();
      const d = res.data?.data || {};
      isFollowing.value = d.is_following || false;
      followerCount.value = d.followers_count || 0;
    } else {
      const res = await getFollowerCount();
      followerCount.value = res.data?.data?.followers_count || 0;
    }
  } catch { /* ignore */ }
}

async function handleFollow() {
  if (!isLoggedIn.value) {
    ElMessage.warning('请先登录后关注');
    return;
  }
  followLoading.value = true;
  try {
    if (isFollowing.value) {
      const res = await unfollowBlog();
      isFollowing.value = false;
      followerCount.value = res.data?.data?.followers_count || 0;
      ElMessage.success('已取消关注');
    } else {
      const res = await followBlog();
      isFollowing.value = true;
      followerCount.value = res.data?.data?.followers_count || 0;
      ElMessage.success('🎉 关注成功！');
    }
  } catch (err) {
    const msg = err?.response?.data?.message || '操作失败';
    if (msg.includes('已关注')) {
      isFollowing.value = true;
    }
    ElMessage.error(msg);
  } finally {
    followLoading.value = false;
  }
}

function onPostContentClick(event) {
  const target = event.target
  // 图片点击 → 灯箱预览
  if (target?.tagName === 'IMG') {
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
      ElMessage.success('✅ 代码已复制')
    }).catch(() => ElMessage.error('复制失败'))
  }
}

async function loadPosts() {
  pageLoading.value = true;
  try {
    const params = { page: page.value, per_page: pageSize.value, sort: sortBy.value };
    if (activeTag.value) params.tag = activeTag.value;
    if (searchQuery.value) params.search = searchQuery.value;
    const [postsRes, changelogRes, followRes] = await Promise.all([
      blogApi.getPublished({ page: 1, per_page: pageSize.value }),
      blogApi.getChangelogByVersion(),
      getFollowerCount().catch(() => ({ data: { data: { followers_count: 0 } } })),
    ]);
    const data = postsRes?.data?.data || postsRes?.data || {};
    posts.value = data.data || data || [];
    totalPosts.value = data.total || posts.value.length;
    if (changelogRes?.data) changelogByVersion.value = changelogRes.data;
    followerCount.value = followRes?.data?.data?.followers_count || 0;
    // 收集所有标签+热门
    const tagSet = new Set();
    posts.value.forEach(p => (p.tags || []).forEach(t => tagSet.add(t)));
    allTags.value = Array.from(tagSet);
    trendingPosts.value = [...posts.value].sort((a,b)=>(b.views_count||0)-(a.views_count||0)).slice(0, 5);
    pageLoading.value = false;
    // 登录后加载关注状态
    if (isLoggedIn.value) await loadFollowStatus();
  } catch { pageLoading.value = false; }
}

onMounted(async () => {
  try {
    const [postsRes, changelogRes] = await Promise.all([
      blogApi.getPublished({ page: 1, per_page: pageSize.value }),
      blogApi.getChangelogByVersion(),
    ]);
    const data = postsRes?.data?.data || postsRes?.data || {};
    posts.value = data.data || data || [];
    totalPosts.value = data.total || posts.value.length;
    if (changelogRes?.data) changelogByVersion.value = changelogRes.data;
    // 收集所有标签+热门
    const tagSet = new Set();
    posts.value.forEach(p => (p.tags || []).forEach(t => tagSet.add(t)));
    allTags.value = Array.from(tagSet);
    trendingPosts.value = [...posts.value].sort((a,b)=>(b.views_count||0)-(a.views_count||0)).slice(0, 5);
    pageLoading.value = false;
  } catch { pageLoading.value = false; /* */ }
});
</script>

<style scoped>
.public-blog-page { max-width: 1200px; margin: 0 auto; padding: 40px 20px; }
.blog-hero { text-align: center; margin-bottom: 40px; }
.blog-hero h1 { font-size: 32px; margin: 0 0 8px; }
.hero-desc { color: #909399; margin-bottom: 20px; }
.hero-actions { display: flex; gap: 8px; justify-content: center; }
.mb-4 { margin-bottom: 16px; }
.ml-1 { margin-left: 4px; }
.ml-2 { margin-left: 8px; }
.mt-1 { margin-top: 4px; }
.post-card { cursor: pointer; transition: transform 0.2s; } 
.post-card:hover { transform: translateY(-4px); }
.post-type { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.post-date { color: #909399; font-size: 12px; }
.post-title { font-size: 18px; margin: 0 0 8px; min-height: 48px; }
.post-excerpt { font-size: 13px; color: #606266; line-height: 1.5; min-height: 40px; }
.post-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
.post-author { color: #909399; font-size: 12px; }
.post-tags { display: flex; gap: 4px; }
.post-detail { max-width: 800px; margin: 0 auto; }
.post-meta { margin-bottom: 20px; color: #909399; font-size: 14px; }
.post-content { line-height: 1.8; font-size: 15px; }
.post-content :deep(pre) { background: #1e1e1e; color: #d4d4d4; padding: 16px 44px 16px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; position: relative; }
.post-content :deep(pre)::after { content: '📋'; position: absolute; top: 6px; right: 8px; font-size: 14px; cursor: pointer; opacity: 0; transition: opacity .2s; padding: 2px 6px; border-radius: 4px; background: rgba(255,255,255,.1); line-height: 1.4; pointer-events: none; }
.post-content :deep(pre:hover)::after { opacity: .85; }
.post-content :deep(code) { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
.post-content :deep(img) { max-width: 100%; border-radius: 6px; }
/* 代码高亮 */
.post-content :deep(.hljs-keyword) { color: #c586c0; }
.post-content :deep(.hljs-string) { color: #ce9178; }
.post-content :deep(.hljs-number) { color: #b5cea8; }
.post-content :deep(.hljs-comment) { color: #6a9955; font-style: italic; }
.post-content :deep(.hljs-built_in) { color: #4ec9b0; }
.post-content :deep(.hljs-title) { color: #dcdcaa; }
.post-content :deep(.hljs-params) { color: #9cdcfe; }
.post-content :deep(.hljs-attr) { color: #9cdcfe; }
.post-content :deep(.hljs-literal) { color: #569cd6; }
.post-content :deep(.hljs-type) { color: #4ec9b0; }
.post-content :deep(.hljs-tag) { color: #569cd6; }
.post-content :deep(.hljs-name) { color: #569cd6; }
/* 代码块语言标签 */
.post-content :deep(.code-lang-badge) { position: absolute; top: 0; left: 0; font-size: 11px; background: rgba(255,255,255,.08); color: #999; padding: 2px 10px; border-radius: 6px 0 6px 0; line-height: 1.6; font-family: sans-serif; z-index: 1; }
/* JS 运行按钮 + 输出 */
.post-content :deep(.code-run-btn) { position: absolute; top: 4px; right: 40px; font-size: 11px; background: #2ea043; color: #fff; border: none; padding: 2px 10px; border-radius: 4px; cursor: pointer; z-index: 2; line-height: 1.6; opacity: 0; transition: opacity .2s; }
.post-content :deep(pre:hover .code-run-btn) { opacity: 1; }
.post-content :deep(.code-run-btn:hover) { background: #238636; }
.post-content :deep(.code-runner-output) { border-top: 1px solid #333; font-size: 12px; line-height: 1.5; }
.post-content :deep(.code-runner-header) { padding: 6px 12px; font-size: 11px; color: #999; background: rgba(255,255,255,.05); }
.post-content :deep(.code-runner-pre) { padding: 8px 12px; margin: 0; background: transparent !important; color: #d4d4d4 !important; font-family: 'Fira Code', Consolas, monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; }
/* 阅读进度环 */
.reading-ring { position: fixed; bottom: 24px; right: 24px; width: 44px; height: 44px; cursor: pointer; z-index: 2001; opacity: .7; transition: opacity .3s; }
.reading-ring:hover { opacity: 1; }
.reading-ring-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 10px; font-weight: 600; color: #409eff; pointer-events: none; }
/* 专注模式 */
.focus-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 1999; cursor: pointer; }
.focus-active { position: relative; z-index: 2000; background: #fff; border-radius: 4px; padding: 0 20px !important; }
.focus-hidden { display: none !important; }
.dark-mode .focus-active { background: #121212; }
/* 主题 */
.theme-default .post-detail { }
.theme-sepia .post-detail, .theme-sepia .post-content { background: #fbf7e9; color: #5f4b32; }
.theme-sepia .post-content :deep(pre) { background: #e8dcc8; color: #5f4b32; }
.theme-night .post-detail, .theme-night .post-content { background: #0a0a1a; color: #7a8ba8; }
.theme-night .post-content :deep(pre) { background: #0d0d2b; color: #7a8ba8; }
/* 阅读进度条 */
.reading-progress-bar { position: sticky; top: 0; left: 0; height: 3px; background: #409eff; z-index: 100; transition: width 0.1s; border-radius: 0 2px 2px 0; }
/* 字号切换 + 操作栏 */
.post-actions-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding: 6px 0; border-bottom: 1px solid #eee; }
.post-actions-left, .post-actions-right { display: flex; align-items: center; gap: 4px; }
.font-size-label { font-size: 12px; color: #909399; min-width: 36px; text-align: center; }
/* 目录导航 */
.post-toc { margin-bottom: 16px; border: 1px solid #e4e7ed; border-radius: 6px; overflow: hidden; }
.toc-header { display: flex; align-items: center; gap: 4px; padding: 8px 12px; cursor: pointer; font-size: 14px; font-weight: 500; background: #f5f7fa; user-select: none; }
.toc-body { padding: 4px 0; max-height: 240px; overflow-y: auto; }
.toc-item { display: flex; align-items: center; gap: 6px; padding: 5px 12px; font-size: 13px; cursor: pointer; color: #606266; transition: color 0.2s; }
.toc-item:hover { color: #409eff; background: #f0f7ff; }
.toc-dot { width: 4px; height: 4px; border-radius: 50%; background: #c0c4cc; flex-shrink: 0; }
/* 分页 + 列表布局 */
.pagination-wrap { display: flex; justify-content: center; margin-top: 24px; }
.blog-main-content { display: flex; gap: 24px; }
.blog-list { flex: 1; min-width: 0; }
.blog-sidebar { width: 280px; flex-shrink: 0; }
@media (max-width: 768px) { .blog-main-content { flex-direction: column; } .blog-sidebar { width: 100%; } }
/* 列表视图 */
.list-view { display: flex; flex-direction: column; gap: 8px; }
.list-item { display: flex; gap: 16px; padding: 16px; border: 1px solid #eee; border-radius: 8px; cursor: pointer; transition: all .2s; }
.list-item:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,.1); }
.dark-mode .list-item { border-color: #333; }
.list-item-left { flex: 1; min-width: 0; }
.list-item-title { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
.dark-mode .list-item-title { color: #e0e0e0; }
.list-item-excerpt { font-size: 13px; color: #606266; line-height: 1.5; margin-bottom: 8px; }
.dark-mode .list-item-excerpt { color: #999; }
.list-item-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #909399; flex-wrap: wrap; }
.list-item-right { flex-shrink: 0; }
.list-item-img { width: 120px; height: 80px; object-fit: cover; border-radius: 6px; }
/* 卡片封面 */
.card-cover { margin: -12px -12px 8px; border-radius: 6px 6px 0 0; overflow: hidden; max-height: 140px; }
.card-cover img { width: 100%; height: 140px; object-fit: cover; display: block; }
/* 工具栏 */
.list-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; gap: 8px; flex-wrap: wrap; }
.list-toolbar-left, .list-toolbar-right { display: flex; align-items: center; gap: 8px; }
/* 侧边栏 */
.sidebar-section { margin-bottom: 20px; }
.sidebar-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 2px solid #409eff; }
.dark-mode .sidebar-title { color: #e0e0e0; }
.sidebar-item { display: flex; align-items: flex-start; gap: 8px; padding: 6px 0; cursor: pointer; transition: background .2s; border-radius: 4px; }
.sidebar-item:hover { background: #f5f7fa; }
.dark-mode .sidebar-item:hover { background: #1a1a2e; }
.sidebar-rank { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; flex-shrink: 0; margin-top: 2px; }
.sidebar-rank.rank-1 { background: #f56c6c; }
.sidebar-rank.rank-2 { background: #e6a23c; }
.sidebar-rank.rank-3 { background: #409eff; }
.sidebar-rank { background: #c0c4cc; }
.sidebar-item-content { flex: 1; min-width: 0; }
.sidebar-item-title { font-size: 13px; font-weight: 500; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.dark-mode .sidebar-item-title { color: #ccc; }
.sidebar-item-meta { font-size: 11px; color: #909399; margin-top: 2px; }
/* 相关文章 */
.related-posts { margin-top: 32px; padding-top: 24px; border-top: 1px solid #eee; }
.related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.related-card { padding: 12px; border: 1px solid #eee; border-radius: 6px; cursor: pointer; text-decoration: none; color: inherit; transition: all 0.2s; display: block; }
.related-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.1); }
.related-card-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
.related-card-meta { font-size: 12px; color: #999; }
/* 评论区 */
.post-comments { margin-top: 32px; padding-top: 24px; border-top: 1px solid #eee; }
.comment-input { margin-bottom: 16px; }
.comment-list { display: flex; flex-direction: column; gap: 12px; }
.comment-item { display: flex; gap: 10px; }
.comment-avatar { width: 32px; height: 32px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500; flex-shrink: 0; }
.comment-body { flex: 1; }
.comment-author { font-size: 13px; font-weight: 500; color: #333; }
.comment-time { font-size: 11px; color: #999; font-weight: 400; margin-left: 8px; }
.comment-text { font-size: 14px; line-height: 1.6; margin: 4px 0; }
.comment-actions { display: flex; gap: 8px; }
/* 评论表情反应 */
.comment-reactions { display: flex; gap: 4px; margin-top: 4px; }
.reaction-btn { display: inline-flex; align-items: center; gap: 2px; padding: 1px 6px; border-radius: 10px; font-size: 13px; cursor: pointer; border: 1px solid #eee; transition: all .2s; line-height: 1.6; user-select: none; }
.reaction-btn:hover { border-color: #409eff; background: #f0f7ff; }
.reaction-btn.active { border-color: #409eff; background: #e6f0ff; }
.reaction-count { font-size: 11px; color: #909399; min-width: 8px; }
.comment-replies { margin-top: 8px; padding-left: 12px; border-left: 2px solid #eee; }
.comment-reply { font-size: 13px; line-height: 1.5; padding: 4px 0; }
.reply-author { font-weight: 500; color: #409eff; }
/* 骨架屏 */
.skeleton-card { height: 180px; border-radius: 6px; background: linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size: 200% 100%; animation: skeleton-loading 1.5s infinite; }
@keyframes skeleton-loading { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
/* 打印样式 */
@media print { .public-blog-page .blog-hero,.public-blog-page .rss-bar,.public-blog-page .hero-actions,.post-actions-bar,.post-toc,.related-posts,.post-comments,.el-backtop,.el-dialog__header,.el-dialog__footer,.social-links { display:none!important } .post-detail{max-width:100%} .post-content{font-size:14px!important} }
/* 社交链接 */
.social-links { display:flex; gap:16px; justify-content:center; padding:16px 0 8px; }
.social-links a { color:#909399; font-size:13px; text-decoration:none; transition:color .2s; }
.social-links a:hover { color:#409eff; }
/* 暗色模式 */
.dark-mode { background:#121212; color:#e0e0e0; min-height:100vh; }
.dark-mode .blog-hero h1 { color:#e0e0e0; }
.dark-mode .post-card { background:#1e1e1e; border-color:#333; }
.dark-mode .post-card .post-title { color:#e0e0e0; }
.dark-mode .post-card .post-excerpt { color:#999; }
.dark-mode .post-card .post-author { color:#888; }
.dark-mode .post-content { color:#ccc; }
.dark-mode .post-meta { color:#888; }
.dark-mode .post-actions-bar { border-color:#333; }
.dark-mode .post-toc { border-color:#333; }
.dark-mode .toc-header { background:#1e1e1e; color:#ccc; }
.dark-mode .toc-item { color:#999; }
.dark-mode .toc-item:hover { color:#409eff; background:#1a1a2e; }
.dark-mode .post-comments { border-color:#333; }
.dark-mode .comment-author { color:#ccc; }
.dark-mode .comment-text { color:#999; }
.dark-mode .oa-toc-header { background:#1a1a2e; color:#ccc; }
.dark-mode .rss-bar a { color:#409eff; }
/* 用户高亮 + AI摘要 + 热力图 */
.user-highlight { background: #ffd54f; padding: 0 2px; border-radius: 2px; cursor: pointer; }
.dark-mode .user-highlight { background: #5c4a00; }
.heatmap { display: flex; flex-wrap: wrap; gap: 2px; margin-top: 4px; }
.heatmap-cell { width: 12px; height: 12px; border-radius: 2px; background: #ebedf0; }
.heatmap-cell.level-1 { background: #c6e48b; }
.heatmap-cell.level-2 { background: #7bc96f; }
.heatmap-cell.level-3 { background: #239a3b; }
.heatmap-cell.level-4 { background: #196127; }
.dark-mode .heatmap-cell { background: #333; }
.ai-summary-box { background: #f0f7ff; border: 1px solid #d0e4ff; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
.ai-summary-header { font-size: 13px; font-weight: 600; color: #409eff; margin-bottom: 6px; }
.ai-summary-text { font-size: 14px; line-height: 1.7; color: #303133; }
.dark-mode .ai-summary-box { background: #16213e; border-color: #1a3a5c; }
.dark-mode .ai-summary-text { color: #ccc; }

.dark-mode .social-links a { color:#888; }
.dark-mode .social-links a:hover { color:#409eff; }/* 内链悬浮预览 */
.link-preview-card { position: fixed; z-index: 9999; display: none; width: 300px; background: #fff; border: 1px solid #e4e7ed; border-radius: 8px; padding: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.12); font-size: 13px; line-height: 1.5; pointer-events: none; }
.dark-mode .link-preview-card { background: #1e1e1e; border-color: #333; }
.link-preview-card .lp-title { font-weight: 600; color: #303133; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.dark-mode .link-preview-card .lp-title { color: #e0e0e0; }
.link-preview-card .lp-desc { font-size: 12px; color: #606266; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.dark-mode .link-preview-card .lp-desc { color: #999; }
.link-preview-card .lp-meta { font-size: 11px; color: #909399; margin-top: 4px; }
/* 阅读统计看板 */
.stats-dashboard { padding: 4px 0; }
.sd-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.sd-card { text-align: center; padding: 16px; border: 1px solid #eee; border-radius: 8px; }
.sd-num { font-size: 28px; font-weight: 700; color: #409eff; }
.sd-label { font-size: 12px; color: #909399; margin-top: 4px; }
.dark-mode .sd-card { border-color: #333; }
.version-group { margin-bottom: 32px; }
.version-title { margin-bottom: 16px; }
.changelog-card { cursor: pointer; }
.changelog-card h4 { margin: 0 0 4px; }
.rss-bar {
  text-align: center; margin-top: 48px; padding: 16px;
  background: #f5f7fa; border-radius: 8px; font-size: 13px;
  display: flex; justify-content: center; gap: 12px; align-items: center;
}
.rss-bar a { color: #409eff; text-decoration: none; }
.rss-bar a:hover { text-decoration: underline; }
</style>
