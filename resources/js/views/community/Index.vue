<template>
    <div class="community-page">
        <!-- 顶部导航 -->
        <div class="community-header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">🌐 社区</h1>
                        <p class="text-sm text-gray-500 mt-1">发现热门讨论，分享你的经验</p>
                    </div>
                    <button v-if="isLoggedIn" class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-medium rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all shadow-md hover:shadow-lg" @click="showNewPost = true">
                        📝 发帖
                    </button>
                    <a v-else href="/build/login" class="w-full sm:w-auto text-center px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-medium rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all shadow-md hover:shadow-lg">
                        登录发帖 →
                    </a>
                </div>

                <!-- Tab + 分类 -->
                <div class="flex gap-1 border-b border-gray-100 overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
                    <button v-for="tab in tabs" :key="tab.key"
                        class="px-4 py-2.5 text-sm font-medium transition rounded-t-lg whitespace-nowrap inline-flex items-center gap-1"
                        :class="activeTab === tab.key ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/50' : 'text-gray-500 hover:text-gray-700'"
                        @click="activeTab = tab.key; page = 1; loadPosts()">
                        <template v-if="tab.key === 'following'">
                            <svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="15" height="15" style="vertical-align:middle;display:inline"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg>
                        </template>
                        <template v-else-if="tab.key === 'my'">
                            <svg viewBox="0 0 24 24" width="15" height="15" style="vertical-align:middle;display:inline" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </template>
                        {{ tab.label }}
                    </button>
                    <el-dropdown v-if="allTags.length" trigger="click" class="ml-auto">
                        <button class="px-3 py-2 text-xs text-gray-400 hover:text-gray-600 transition whitespace-nowrap">
                            🏷️ 标签 <el-icon><ArrowDown /></el-icon>
                        </button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item @click="tagFilter = ''; page = 1; loadPosts()">全部标签</el-dropdown-item>
                                <el-dropdown-item v-for="t in allTags" :key="t" @click="tagFilter = t; page = 1; loadPosts()">{{ t }}</el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>
        </div>

        <!-- 发帖对话框 -->
        <el-dialog v-model="showNewPost" :title="editingPost ? '✏️ 编辑帖子' : '📝 发布动态'" width="860px" top="5vh">
            <div class="flex gap-5">
                <!-- 左侧：编辑器 -->
                <div class="flex-1 min-w-0 space-y-4">
                    <!-- 模板选择 -->
                    <div v-if="!editingPost" class="flex gap-2">
                        <button v-for="tpl in templates" :key="tpl.key"
                            class="px-3 py-1.5 text-xs rounded-lg border transition"
                            :class="postTemplate === tpl.key ? 'border-primary-500 bg-primary-50 text-primary-600' : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                            @click="postTemplate = tpl.key; if(tpl.key!=='discuss') newPostContent = tpl.template">
                            {{ tpl.icon }} {{ tpl.label }}
                        </button>
                    </div>
                    <el-input v-model="newPostContent" type="textarea" :rows="6" placeholder="分享你的想法..." maxlength="5000" show-word-limit />
                    <div class="flex gap-2 flex-wrap">
                        <el-upload :auto-upload="false" :on-change="onFileSelect" accept="image/*" :show-file-list="false">
                            <el-button size="small"><el-icon><Picture /></el-icon> 图片</el-button>
                        </el-upload>
                        <el-upload :auto-upload="false" :on-change="onVideoSelect" accept="video/*" :show-file-list="false">
                            <el-button size="small"><el-icon><VideoCamera /></el-icon> 视频</el-button>
                        </el-upload>
                        <el-button size="small" @click="toggleVoiceInput" :type="voiceListening ? 'danger' : 'default'" :icon="voiceListening ? null : null" title="语音转文字">
                            <template v-if="voiceListening">🔴 录音中... {{ voiceDuration }}s</template>
                            <template v-else>🎤 语音</template>
                        </el-button>
                        <el-button size="small" @click="formatContent" title="清理多余空行、统一标点、规范化链接">
                            ✨ 排版
                        </el-button>
                        <el-select v-model="postTags" multiple filterable allow-create default-first-option placeholder="添加标签" size="small" class="flex-1" clearable>
                            <el-option v-for="t in allTags" :key="t" :label="t" :value="t" />
                        </el-select>
                    </div>
                    <!-- 投票选项 -->
                    <div v-if="postTemplate === 'poll'" class="space-y-2 pl-2">
                        <div v-for="(opt, i) in pollOptions" :key="i" class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-5">{{ 'ABCDEFGHIJ'[i] }}.</span>
                            <el-input v-model="pollOptions[i]" :placeholder="'选项 ' + (i+1)" size="small" maxlength="100" />
                            <el-button v-if="pollOptions.length > 2" size="small" text type="danger" @click="pollOptions.splice(i, 1)">✕</el-button>
                        </div>
                        <el-button v-if="pollOptions.length < 10" size="small" text @click="pollOptions.push('')">+ 添加选项</el-button>
                    </div>
                    <!-- 图片预览（支持拖拽排序） -->
                    <div v-if="combinedImages.length" class="flex items-center gap-1 mb-1">
                        <span class="text-xs text-gray-400">🖼 {{ combinedImages.length }} 张</span>
                        <span class="text-[10px] text-gray-300">（拖拽调整顺序）</span>
                    </div>
                    <draggable v-model="combinedImages" item-key="uid" class="flex gap-2 flex-wrap" @end="onImageDragEnd">
                        <template #item="{ element, index }">
                            <div class="relative w-16 h-16 rounded-lg overflow-hidden border cursor-grab active:cursor-grabbing group">
                                <img v-if="element.type === 'existing' || element.type === 'newImg'" :src="element.url" class="w-full h-full object-cover" />
                                <video v-else :src="element.url" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                                    <span class="text-white text-xs font-bold drop-shadow">⠿</span>
                                </div>
                                <button class="absolute top-0.5 right-0.5 w-4 h-4 bg-black/50 text-white rounded-full text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition" @click="removeCombinedImage(index)">✕</button>
                            </div>
                        </template>
                    </draggable>
                    <!-- 定时发布 -->
                    <div class="flex items-center gap-3">
                        <el-checkbox v-model="enableSchedule">定时发布</el-checkbox>
                        <el-date-picker v-if="enableSchedule" v-model="scheduledAt" type="datetime" placeholder="选择发布时间" size="small" :disabled-date="d => d < new Date()" />
                    </div>
                    <!-- 付费设置 -->
                    <div class="flex items-center gap-3 pt-1">
                        <el-checkbox v-model="postIsPaid">付费内容</el-checkbox>
                        <template v-if="postIsPaid">
                            <el-input-number v-model="postPrice" :min="1" :max="99999" size="small" style="width:100px" />
                            <el-select v-model="postPriceType" size="small" style="width:90px">
                                <el-option value="points"><span style="display:inline-flex;align-items:center;gap:4px"><PointsIcon :size="16" /> 积分</span></el-option>
                                <el-option label="💰 金额" value="money" />
                            </el-select>
                            <el-input v-model="postContentPreview" placeholder="付费预览摘要（选填）" size="small" style="width:200px" maxlength="300" />
                        </template>
                    </div>
                </div>

                <!-- 右侧：实时卡片预览 -->
                <div class="w-72 flex-shrink-0 hidden sm:block">
                    <div class="text-xs text-gray-400 font-medium mb-2">📱 预览效果</div>
                    <div class="preview-card">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-xs overflow-hidden flex-shrink-0">
                                <span>{{ currentUser?.name?.charAt(0) || '?' }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-gray-900 truncate">{{ currentUser?.name || '匿名' }}</div>
                                <div class="text-xs text-gray-400">刚刚</div>
                            </div>
                        </div>
                        <div class="preview-body line-clamp-4" v-html="previewContent"></div>
                        <div v-if="previewImages.length" class="preview-images" :class="'img-count-' + Math.min(previewImages.length, 4)">
                            <img v-for="(img, i) in previewImages.slice(0, 4)" :key="i" :src="img" />
                        </div>
                        <div v-if="previewTags.length" class="flex items-center gap-1 mt-2">
                            <span v-for="tag in previewTags" class="preview-tag">{{ tag }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-50 text-xs text-gray-400">
                            <span>🤍 0</span><span>💬 0</span><span class="ml-auto">☆ <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:12px;height:12px;vertical-align:middle;fill:currentColor;display:inline"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/></svg></span>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <el-button v-if="!editingPost" size="small" @click="saveDraft">💾 存草稿</el-button>
                        <span v-if="autoSaveStatus === 'saved'" class="text-[11px] text-green-500 transition">✅ 已自动保存</span>
                    </div>
                    <div>
                        <el-button size="small" @click="showNewPost = false">取消</el-button>
                        <el-button type="primary" size="small" :loading="submitting" @click="submitPost">{{ editingPost ? '保存修改' : '发布' }}</el-button>
                    </div>
                </div>
            </template>
        </el-dialog>



        <!-- 帖子列表 + 侧边栏 -->
        <div class="max-w-7xl mx-auto px-4 py-6">
            <div class="community-layout">
                <!-- 主内容区 -->
                <div class="community-main">
                    <!-- 搜索栏 -->
                    <div class="flex items-center gap-2 mb-4">
                        <el-input v-model="searchQuery" placeholder="搜索帖子标题、内容、评论..." size="default"
                            clearable prefix-icon="Search" class="!w-72 sm:!w-80"
                            @input="onSearchInput" @clear="onSearchClear" />
                        <template v-if="activeTab === 'my'">
                            <el-select v-model="myPostStatus" size="small" style="width:110px" @change="page=1; loadPosts()">
                                <el-option label="全部" value="" />
                                <el-option label="已发布" value="published" />
                                <el-option label="草稿" value="draft" />
                                <el-option label="定时发布" value="scheduled" />
                            </el-select>
                        </template>
                        <span v-if="searchQuery && !loading" class="text-xs text-gray-400">
                            找到 {{ posts.length }} 条结果
                        </span>
                    </div>
                    <!-- 骨架屏 -->
                    <div v-if="loading" class="post-grid-2col">
                        <div v-for="i in 6" :key="i" class="skeleton-card">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="skeleton-avatar"></div>
                                <div class="flex-1">
                                    <div class="skeleton-line w-24"></div>
                                    <div class="skeleton-line w-16 mt-1.5"></div>
                                </div>
                            </div>
                            <div class="skeleton-line w-full"></div>
                            <div class="skeleton-line w-3/4 mt-2"></div>
                            <div class="skeleton-image mt-3"></div>
                            <div class="flex gap-3 mt-3 pt-3 border-t border-gray-50">
                                <div class="skeleton-line w-12"></div>
                                <div class="skeleton-line w-10"></div>
                                <div class="skeleton-line w-8 ml-auto"></div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!loading && posts.length" class="post-grid-2col">
                        <div v-for="post in posts" :key="post.id" class="post-card" @click="openDetail(post)">
                            <!-- 帖子头部 -->
                            <div class="flex items-center gap-2 mb-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-sm overflow-hidden cursor-pointer shadow-sm" @click.stop="viewUser(post.user)">
                                    <img v-if="post.user?.avatar_url" :src="post.user.avatar_url" class="w-full h-full object-cover" @error="$event.target.style.display='none'; $event.target.parentElement.querySelector('.avatar-fallback').style.display='flex'" />
                                    <span v-else class="avatar-fallback">{{ (post.user?.name || '?').charAt(0) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 truncate">
                                        {{ post.user?.name || '匿名' }}
                                        <span v-if="post.user?.level" class="text-[11px]" :title="'Lv.' + post.user.level + ' ' + getLevelName(post.user.level)">{{ getLevelBadge(post.user.level) }}</span>
                                    </div>
                                    <div class="text-xs text-gray-400 flex items-center gap-1">
                                        <span>{{ timeAgo(post.created_at) }}</span>
                                        <span v-if="post.template && post.template !== 'discuss'" class="px-1.5 py-0.5 bg-gray-50 rounded text-[10px] text-gray-400">{{ post.template }}</span>
                                        <span v-if="post.status === 'draft'" class="px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px] font-medium">草稿</span>
                                        <span v-else-if="post.status === 'scheduled'" class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded text-[10px] font-medium">定时</span>
                                        <span v-if="post.is_pinned" class="px-1.5 py-0.5 bg-orange-50 text-orange-500 rounded text-[10px] font-medium">📌 置顶</span>
                                    </div>
                                </div>
                                <button v-if="isLoggedIn && post.user_id !== myId" class="follow-btn-sm" :class="{ following: post.user?.is_following }" @click.stop="toggleFollow(post.user)">
                                    {{ post.user?.is_following ? '已关注' : '+ 关注' }}
                                </button>
                                <!-- 操作菜单 -->
                                <el-dropdown v-if="isLoggedIn" trigger="click" @click.stop>
                                    <button class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-50 text-gray-400 hover:text-gray-600 transition">⋯</button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item v-if="post.user_id === myId" @click.stop="editPost(post)">✏️ 编辑</el-dropdown-item>
                                            <el-dropdown-item v-if="post.user_id === myId" @click.stop="deletePost(post)">🗑️ 删除</el-dropdown-item>
                                            <el-dropdown-item v-if="isAdmin" divided @click.stop="togglePin(post)">{{ post.is_pinned ? '📌 取消置顶' : '📌 置顶' }}</el-dropdown-item>
                                            <el-dropdown-item @click.stop="sharePost(post)">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:14px;height:14px;vertical-align:middle;margin-right:5px;fill:currentColor">
                                                    <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
                                                </svg> 分享
                                            </el-dropdown-item>
                                            <el-dropdown-item @click.stop="reportPost(post)">🚫 举报</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </div>

                            <!-- 内容 -->
                            <div class="post-body mb-3 line-clamp-4" v-html="post.content"></div>

                            <!-- 图片网格 -->
                            <div v-if="post.images?.length" class="post-images" :class="'img-count-' + Math.min(post.images.length, 4)">
                                <img v-for="(img, i) in post.images.slice(0, 4)" :key="i" :src="img" @click.stop="openDetail(post)" @error="$event.target.style.display='none'" />
                            </div>

                            <!-- 视频 -->
                            <div v-if="post.video" class="mb-3">
                                <video :src="post.video" class="w-full max-h-48 rounded-xl object-cover" controls @click.stop />
                            </div>

                            <!-- 底部操作栏 -->
                            <div class="flex items-center gap-1 pt-3 border-t border-gray-50">
                                <span class="action-btn" :class="{ liked: post.is_liked }" @click.stop="toggleLike(post)">
                                    {{ post.is_liked ? '❤️' : '🤍' }} {{ post.likes_count || 0 }}
                                </span>
                                <span class="action-btn" @click.stop="openDetail(post)">💬 {{ post.replies_count || 0 }}</span>
                                <span class="action-btn" :class="{ faved: post.is_favorited }" @click.stop="toggleFavorite(post)">
                                    {{ post.is_favorited ? '⭐' : '☆' }}
                                </span>
                                <span class="action-btn" @click.stop="sharePost(post)">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" style="width:14px;height:14px;vertical-align:middle;fill:currentColor">
                                        <path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92s2.92-1.31 2.92-2.92-1.31-2.92-2.92-2.92z"/>
                                    </svg>
                                </span>
                                <span v-if="post.tags?.length" class="flex items-center gap-1 ml-auto">
                                    <span v-for="tag in post.tags" class="tag-pill" @click.stop="tagFilter = tag.name || tag; page = 1; loadPosts()">{{ tag.name || tag }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- 无限滚动：底部哨兵 -->
                    <div ref="sentinelRef" class="text-center py-6">
                        <span v-if="loadingMore" class="inline-block w-5 h-5 border-2 border-primary-400 border-t-transparent rounded-full animate-spin"></span>
                        <span v-else-if="!hasMore && posts.length" class="text-xs text-gray-300">— 没有更多了 —</span>
                    </div>
                    <el-empty v-if="!loading && !posts.length" description="暂无内容" :image-size="80" />
                </div>

                <!-- 侧边栏 -->
                <aside class="community-sidebar">
                    <!-- 热门话题 -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">🔥 热门话题</h3>
                        <div class="sidebar-divider"></div>
                        <div v-if="trendingTags.length" class="space-y-2">
                            <div v-for="(tag, i) in trendingTags.slice(0, 8)" :key="tag.id" class="sidebar-tag-item" @click="tagFilter = tag.name; page = 1; loadPosts()">
                                <span class="sidebar-tag-rank" :class="'rank-' + (i + 1)">{{ i + 1 }}</span>
                                <span class="flex-1 text-sm truncate">#{{ tag.name }}</span>
                                <span class="text-xs text-gray-400">{{ tag.posts_count }} 帖</span>
                            </div>
                        </div>
                        <div v-else class="text-xs text-gray-400 py-2 text-center">暂无数据</div>
                    </div>

                    <!-- 贡献榜 -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title">👑 贡献榜</h3>
                        <div class="sidebar-divider"></div>
                        <div v-if="topContributors.length" class="space-y-2">
                            <div v-for="(user, i) in topContributors" :key="user.id" class="sidebar-user-item" @click="viewUser(user)">
                                <span class="sidebar-rank-badge" :class="'rank-' + (i + 1)">{{ i + 1 }}</span>
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary-300 to-primary-500 flex items-center justify-center text-white text-xs font-bold overflow-hidden flex-shrink-0">
                                    <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ (user.name || '?').charAt(0) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</div>
                                    <div class="text-[10px] text-gray-400">❤️ {{ user.likes_count }} 获赞</div>
                                </div>
                                <button v-if="isLoggedIn" class="follow-btn" :class="{ following: user.is_following }" @click.stop="toggleFollow(user)">
                                    {{ user.is_following ? '已关注' : '+ 关注' }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-xs text-gray-400 py-2 text-center">暂无数据</div>
                    </div>

                    <!-- 为你推荐 -->
                    <div class="sidebar-card" v-if="isLoggedIn">
                        <h3 class="sidebar-title"><el-icon style="font-size:15px;vertical-align:middle"><Document /></el-icon> <span style="vertical-align:middle">为你推荐</span></h3>
                        <div class="sidebar-divider"></div>
                        <div v-if="recommendedPosts.length" class="space-y-2">
                            <div v-for="rp in recommendedPosts" :key="rp.id" class="sidebar-post-item" @click="openDetail(rp)">
                                <div class="text-sm text-gray-800 line-clamp-2 leading-relaxed">{{ rp.content?.replace(/<[^>]*>/g, '').substring(0, 80) }}</div>
                                <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400">
                                    <span>{{ rp.user?.name || '匿名' }}</span>
                                    <span>❤️ {{ rp.likes_count || 0 }}</span>
                                    <span>💬 {{ rp.replies_count || 0 }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-xs text-gray-400 py-2 text-center">暂无推荐</div>
                    </div>

                    <!-- 推荐关注 -->
                    <div class="sidebar-card">
                        <h3 class="sidebar-title"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="15" height="15" style="vertical-align:middle;display:inline"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg><span style="vertical-align:middle">推荐关注</span></h3>
                        <div class="sidebar-divider"></div>
                        <div v-if="suggestedUsers.length" class="space-y-2">
                            <div v-for="user in suggestedUsers" :key="user.id" class="sidebar-user-item" @click="viewUser(user)">
                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-primary-300 to-primary-500 flex items-center justify-center text-white text-xs font-bold overflow-hidden flex-shrink-0">
                                    <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ (user.name || '?').charAt(0) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 truncate">{{ user.name }}</div>
                                    <div class="text-[10px] text-gray-400">📝 {{ user.posts_count }} 帖</div>
                                </div>
                                <button v-if="isLoggedIn" class="follow-btn" :class="{ following: user.is_following }" @click.stop="toggleFollow(user)">
                                    {{ user.is_following ? '已关注' : '+ 关注' }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="text-xs text-gray-400 py-2 text-center">暂无推荐</div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- 分享对话框 -->
        <el-dialog v-model="showShareDialog" title="分享" width="400px">
            <div class="space-y-3">
                <el-input v-model="shareLink" readonly>
                    <template #append><el-button @click="copyShareLink">复制链接</el-button></template>
                </el-input>
            </div>
            <template #footer>
                <el-button @click="showShareDialog = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 收藏夹选择对话框 -->
        <el-dialog v-model="showCollectionDialog" title="⭐ 收藏到" width="400px">
            <div class="space-y-2">
                <div v-for="col in collections" :key="col.id"
                    class="px-4 py-3 rounded-lg border border-gray-100 cursor-pointer hover:bg-primary-50 hover:border-primary-200 transition"
                    @click="addToCollection(col)">
                    <span class="text-sm font-medium">{{ col.name || '默认收藏夹' }}</span>
                    <span class="text-xs text-gray-400 ml-2">({{ col.count || 0 }})</span>
                </div>
                <div class="flex gap-2 mt-3">
                    <el-input v-model="newCollectionName" placeholder="新建收藏夹" size="small" />
                    <el-button size="small" @click="createCollection" :disabled="!newCollectionName.trim()">新建</el-button>
                </div>
            </div>
            <template #footer>
                <el-button @click="showCollectionDialog = false">取消</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Picture, VideoCamera, ArrowDown, Search, Female, Document } from '@element-plus/icons-vue'
import PointsIcon from '@/components/PointsIcon.vue';
import draggable from 'vuedraggable'
import apiClient from '@/api/client.js'

const isLoggedIn = !!localStorage.getItem('auth_token')
const myId = isLoggedIn ? (JSON.parse(localStorage.getItem('user') || '{}')?.id || 0) : 0
const isAdmin = isLoggedIn ? (JSON.parse(localStorage.getItem('user') || '{}')?.roles || []).some(r => r === 'admin' || r?.name === 'admin') : false

// ── 搜索 ──
const searchQuery = ref('')
let searchTimer = null
function onSearchInput() {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => { page.value = 1; loadPosts() }, 400)
}
function onSearchClear() {
    searchQuery.value = ''
    page.value = 1
    loadPosts()
}
// ── 个性化推荐 ──
const recommendedPosts = ref([])
async function loadRecommendations() {
    if (!isLoggedIn.value) { recommendedPosts.value = []; return }
    try {
        const res = await apiClient.get('/moments/recommendations', {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        recommendedPosts.value = res.data?.data || []
    } catch { recommendedPosts.value = [] }
}

// ── Tabs ──
const allTabs = [
    { key: 'all', label: '全部' },
    { key: 'pinned', label: '精华' },
    { key: 'smart', label: '智能推荐' },
    { key: 'recommended', label: '协同推荐' },
    { key: 'hot', label: '热门' },
    { key: 'weekly_hot', label: '本周热门' },
    { key: 'following', label: '关注' },
    { key: 'my', label: '我的' },
]
const tabs = computed(() => isLoggedIn ? allTabs : allTabs.filter(t => t.key !== 'following' && t.key !== 'my'))
const activeTab = ref('all')
const tagFilter = ref('')
const allTags = ref([])

// ── Sidebar ──
const trendingTags = ref([])
const topContributors = ref([])
const suggestedUsers = ref([])

async function loadSidebarData() {
    try {
        const headers = isLoggedIn ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
        const [tagsRes, contribRes, suggestRes] = await Promise.all([
            apiClient.get('/moments/public/tags', { headers }),
            apiClient.get('/moments/public/top-contributors', { headers }),
            apiClient.get('/moments/public/suggested-users', { headers }),
        ])
        trendingTags.value = tagsRes.data?.data || []
        topContributors.value = (contribRes.data?.data || []).map(u => ({ ...u, is_following: false }))
        suggestedUsers.value = (suggestRes.data?.data || []).map(u => ({ ...u, is_following: false }))
        loadRecommendations()
    } catch {
        trendingTags.value = []
        topContributors.value = []
        suggestedUsers.value = []
    }
}

// ── Posts ──
const posts = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const page = ref(1)
const hasMore = ref(false)
const myPostStatus = ref('')

// ── 无限滚动 ──
const sentinelRef = ref(null)
let scrollObserver = null

function setupInfiniteScroll() {
    scrollObserver?.disconnect()
    scrollObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hasMore.value && !loadingMore.value && !loading.value) {
            loadMore()
        }
    }, { rootMargin: '200px' })
    if (sentinelRef.value) scrollObserver.observe(sentinelRef.value)
}

// ── 实时预览 ──
const currentUser = computed(() => {
    if (!isLoggedIn) return null
    try { return JSON.parse(localStorage.getItem('user') || '{}') } catch { return null }
})
const previewContent = computed(() => {
    return newPostContent.value || '<span style="color:#ccc">输入内容后这里实时预览...</span>'
})
const previewImages = computed(() => combinedImages.value.map(i => i.url))
const previewTags = computed(() => postTags.value)

// ── 拖拽排序 ──
const combinedImages = computed({
    get: () => {
        const items = []
        existingImages.value.forEach((url, i) => items.push({ uid: 'e-' + i, type: 'existing', url, _idx: i }))
        newFilesPreview.value.forEach((p, i) => items.push({ uid: 'n-' + i, type: p.type === 'image' ? 'newImg' : 'newVideo', url: p.url, raw: p.raw || p._raw, _idx: i }))
        return items
    },
    set: (val) => {
        const newExisting = []
        const newPreview = []
        val.forEach(item => {
            if (item.type === 'existing') {
                newExisting.push(existingImages.value[item._idx])
            } else {
                const src = newFilesPreview.value[item._idx]
                if (src) newPreview.push(src)
            }
        })
        existingImages.value = newExisting
        newFilesPreview.value = newPreview
        // also fix newFiles order
        const reordered = []
        newPreview.forEach(p => {
            const found = newFiles.value.find(f => f.raw === p.raw || f._tempId === p._tempId)
            if (found) reordered.push(found)
        })
        if (reordered.length) newFiles.value = reordered
    }
})

function onImageDragEnd() {
    // 已通过 setter 处理
}

function removeCombinedImage(index) {
    const item = combinedImages.value[index]
    if (item.type === 'existing') {
        removedImages.value.push(existingImages.value[item._idx])
        existingImages.value.splice(item._idx, 1)
    } else {
        const src = newFilesPreview.value[item._idx]
        if (src) {
            if (src.url) URL.revokeObjectURL(src.url)
            newFilesPreview.value.splice(item._idx, 1)
            newFiles.value.splice(item._idx, 1)
        }
    }
}

// ── 发帖 ──
const showNewPost = ref(false)
const editingPost = ref(null)
const newPostContent = ref('')
const newFiles = ref([])
const newFilesPreview = ref([])
const existingImages = ref([])
const removedImages = ref([])
const postTemplate = ref('discuss')
const postTags = ref([])
const enableSchedule = ref(false)
const scheduledAt = ref(null)
// 付费
const postIsPaid = ref(false)
const postPrice = ref(10)
const postPriceType = ref('points')
const postContentPreview = ref('')
const submitting = ref(false)

// ── Poll ──
const pollOptions = ref(['', ''])

// ── Favorites ──
const showCollectionDialog = ref(false)
const collections = ref([])
const newCollectionName = ref('')
const favoriteTargetId = ref(null)

// ── Lightbox ──


// ── Share ──
const showShareDialog = ref(false)
const shareLink = ref('')
const sharePostId = ref(null)

// ── 自动保存草稿 ──
const DRAFT_KEY = 'community_autodraft'
const autoSaveTimer = ref(null)
const autoSaveStatus = ref('') // '' | 'saved' | 'saving'
let draftDirty = false

function triggerAutoSave() {
    if (!showNewPost.value) return
    draftDirty = true
    if (autoSaveTimer.value) return
    autoSaveTimer.value = setTimeout(() => {
        doAutoSave()
    }, 3000)
}

function doAutoSave() {
    if (!draftDirty) { autoSaveTimer.value = null; return }
    const data = {
        content: newPostContent.value,
        tags: postTags.value,
        template: postTemplate.value,
        saved_at: new Date().toISOString(),
    }
    localStorage.setItem(DRAFT_KEY, JSON.stringify(data))
    autoSaveStatus.value = 'saved'
    draftDirty = false
    autoSaveTimer.value = null
    setTimeout(() => { if (autoSaveStatus.value === 'saved') autoSaveStatus.value = '' }, 2000)
}

function checkAutoDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY)
        if (!raw) return
        const draft = JSON.parse(raw)
        if (!draft?.content?.trim()) return
        // 只在新建帖子且内容为空时提示恢复（编辑模式不提示）
        if (!editingPost.value && !newPostContent.value.trim()) {
            return draft // 返回草稿数据供调用方使用
        }
    } catch { /* ignore */ }
    return null
}

function restoreAutoDraft(draft) {
    if (!draft) return
    newPostContent.value = draft.content || ''
    postTags.value = draft.tags || []
    postTemplate.value = draft.template || 'discuss'
    clearAutoDraft()
    ElMessage.success('草稿已恢复')
}

function clearAutoDraft() {
    localStorage.removeItem(DRAFT_KEY)
    autoSaveStatus.value = ''
}

// 监听内容变化触发自动保存
watch([newPostContent, postTags, postTemplate], () => {
    if (showNewPost.value && !editingPost.value) triggerAutoSave()
})

// 打开对话框时检查是否有自动草稿
watch(showNewPost, (val) => {
    if (val && !editingPost.value) {
        const draft = checkAutoDraft()
        if (draft) {
            ElMessageBox.confirm('检测到未发布的草稿，是否恢复？', '恢复草稿', {
                confirmButtonText: '恢复', cancelButtonText: '忽略', type: 'info',
            }).then(() => restoreAutoDraft(draft)).catch(() => clearAutoDraft())
        }
    }
})

// ── 等级徽章 ──
const levelBadges = ['', '🌱', '🌿', '🌳', '⭐', '🌟', '🏆', '👑', '💎', '🔥', '⚡']
const levelNames = ['', '新手', '入门', '进阶', '达人', '精英', '大师', '王者', '传奇', '炽热', '超凡']
function getLevelBadge(level) { return levelBadges[level] || '🌱' }
function getLevelName(level) { return levelNames[level] || '新手' }

// ── 模板 ──
const templates = [
    { key: 'discuss', icon: '💬', label: '讨论', template: '' },
    { key: 'poll', icon: '📊', label: '投票', template: '📊 投票：\nA. \nB. \nC.' },
    { key: 'qa', icon: '❓', label: '问答', template: '❓ 提问：\n\n' },
    { key: 'checkin', icon: '✅', label: '打卡', template: '✅ 打卡第 1 天！\n' },
    { key: 'announce', icon: '📢', label: '公告', template: '📢 公告：\n\n' },
]

// ── Helpers ──
function timeAgo(t) {
    if (!t) return ''
    const d = new Date(t)
    const now = Date.now()
    const diff = Math.floor((now - d.getTime()) / 1000)
    if (diff < 60) return '刚刚'
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
    if (diff < 2592000) return Math.floor(diff / 86400) + '天前'
    return d.toLocaleDateString('zh-CN')
}

function getImages(post) {
    if (!post.images) return []
    try {
        return typeof post.images === 'string' ? JSON.parse(post.images) : post.images
    } catch { return [] }
}

// ── 加载帖子 ──
async function loadPosts() {
    loading.value = true
    page.value = 1
    try {
        const headers = isLoggedIn ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
        const params = { sort: activeTab.value, per_page: 20 }
        if (tagFilter.value) params.tag = tagFilter.value
        if (searchQuery.value) params.q = searchQuery.value
        if (activeTab.value === 'my' && myPostStatus.value) params.status = myPostStatus.value

        const endpoint = activeTab.value === 'following' ? '/moments/following' : activeTab.value === 'my' ? '/moments/my' : (isLoggedIn ? '/moments' : '/moments/public')
        const res = await apiClient.get(endpoint, { params, headers })
        const data = res.data?.data?.data || res.data?.data || []
        posts.value = data
        hasMore.value = (res.data?.meta?.last_page || 1) > 1
    } catch { posts.value = [] }
    finally { loading.value = false; setTimeout(setupInfiniteScroll, 100) }
}

async function loadMore() {
    if (loadingMore.value || !hasMore.value) return
    loadingMore.value = true
    page.value++
    try {
        const headers = isLoggedIn ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
        const params = { sort: activeTab.value, per_page: 20, page: page.value }
        if (tagFilter.value) params.tag = tagFilter.value
        if (searchQuery.value) params.q = searchQuery.value
        if (activeTab.value === 'my' && myPostStatus.value) params.status = myPostStatus.value

        const endpoint = activeTab.value === 'following' ? '/moments/following' : activeTab.value === 'my' ? '/moments/my' : (isLoggedIn ? '/moments' : '/moments/public')
        const res = await apiClient.get(endpoint, { params, headers })
        const data = res.data?.data?.data || res.data?.data || []
        posts.value.push(...data)
        hasMore.value = page.value < (res.data?.meta?.last_page || 1)
    } catch { /* ignore */ }
    finally { loadingMore.value = false }
    // 重新连接无限滚动观察器
    setupInfiniteScroll()
}

// ── 加载标签（发帖时调用，无需在初始化时加载） ──
async function loadTags() {
    try {
        const endpoint = isLoggedIn ? '/moments/tag-suggestions' : '/moments/public/tag-suggestions'
        const res = await apiClient.get(endpoint)
        allTags.value = res.data?.data?.tags || res.data?.data || []
    } catch { allTags.value = [] }
}

// ── 文件上传 ──
function onFileSelect(file) {
    const id = Date.now() + Math.random()
    newFiles.value.push({ raw: file.raw, type: 'image', _tempId: id })
    newFilesPreview.value.push({ type: 'image', url: URL.createObjectURL(file.raw), _raw: file.raw, _tempId: id })
}

function onVideoSelect(file) {
    const id = Date.now() + Math.random()
    newFiles.value.push({ raw: file.raw, type: 'video', _tempId: id })
    newFilesPreview.value.push({ type: 'video', url: URL.createObjectURL(file.raw), _raw: file.raw, _tempId: id })
}

function removeFile(i) {
    if (newFilesPreview.value[i]?.url) URL.revokeObjectURL(newFilesPreview.value[i].url)
    newFiles.value.splice(i, 1)
    newFilesPreview.value.splice(i, 1)
}

// ── 发布/编辑 ──
async function submitPost() {
    if (!newPostContent.value.trim()) { ElMessage.warning('请输入内容'); return }
    submitting.value = true
    try {
        const fd = new FormData()
        fd.append('content', newPostContent.value)
        fd.append('template', postTemplate.value)
        if (postTags.value.length) {
            postTags.value.forEach((tag, i) => fd.append(`tags[${i}]`, tag))
        }
        if (enableSchedule.value && scheduledAt.value) {
            fd.append('status', 'scheduled')
            fd.append('scheduled_at', scheduledAt.value.toISOString())
        }
        if (postIsPaid.value) {
            fd.append('is_paid', '1')
            fd.append('price', String(postPrice.value))
            fd.append('price_type', postPriceType.value)
            if (postContentPreview.value) fd.append('content_preview', postContentPreview.value)
        }

        // 投票选项
        if (postTemplate.value === 'poll') {
            const validOptions = pollOptions.value.filter(o => o.trim())
            if (validOptions.length >= 2) {
                fd.append('poll_options', JSON.stringify(validOptions))
            }
        }

        newFiles.value.forEach((f, i) => {
            fd.append(f.type === 'video' ? 'video' : `images[${i}]`, f.raw)
        })

        if (editingPost.value) {
            fd.append('_method', 'PUT')
            // 保留未被删除的已有图片
            if (existingImages.value.length) {
                fd.append('images', JSON.stringify(existingImages.value))
            }
            if (removedImages.value.length) {
                fd.append('removed_images', JSON.stringify(removedImages.value))
            }
            await apiClient.post(`/moments/${editingPost.value.id}`, fd, {
                headers: { 'Content-Type': 'multipart/form-data', Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            ElMessage.success('修改已保存')
        } else {
            await apiClient.post('/moments', fd, {
                headers: { 'Content-Type': 'multipart/form-data', Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            clearAutoDraft() // 发布成功清除自动草稿
            ElMessage.success('发布成功')
        }

        resetPostForm()
        await loadPosts()
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
    finally { submitting.value = false }
}

function saveDraft() {
    if (!newPostContent.value.trim()) { ElMessage.warning('请输入内容'); return }
    try {
        const drafts = JSON.parse(localStorage.getItem('community_drafts') || '[]')
        drafts.unshift({
            id: Date.now(),
            content: newPostContent.value,
            tags: postTags.value,
            template: postTemplate.value,
            saved_at: new Date().toISOString(),
        })
        localStorage.setItem('community_drafts', JSON.stringify(drafts.slice(0, 20)))
        ElMessage.success('草稿已保存')
    } catch { ElMessage.error('保存失败') }
}

function resetPostForm() {
    showNewPost.value = false
    editingPost.value = null
    newPostContent.value = ''
    newFiles.value = []
    newFilesPreview.value.forEach(f => { if (f.url) URL.revokeObjectURL(f.url) })
    newFilesPreview.value = []
    existingImages.value = []
    removedImages.value = []
    postTemplate.value = 'discuss'
    postTags.value = []
    pollOptions.value = ['', '']
    enableSchedule.value = false
    scheduledAt.value = null
    postIsPaid.value = false
    postPrice.value = 10
    postPriceType.value = 'points'
    postContentPreview.value = ''
}

// ── 一键排版 ──
// ── 语音转文字 ──
const voiceListening = ref(false)
const voiceDuration = ref(0)
let voiceRecognition = null
let voiceTimer = null
function toggleVoiceInput() {
    if (voiceListening.value) { stopVoiceInput(); return }
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition
    if (!SpeechRecognition) { ElMessage.warning('当前浏览器不支持语音识别（推荐 Chrome）'); return }
    voiceRecognition = new SpeechRecognition()
    voiceRecognition.lang = 'zh-CN'
    voiceRecognition.continuous = true
    voiceRecognition.interimResults = true
    voiceListening.value = true
    voiceDuration.value = 0
    voiceTimer = setInterval(() => { voiceDuration.value++ }, 1000)
    voiceRecognition.onresult = (e) => {
        let text = ''
        for (let i = e.resultIndex; i < e.results.length; i++) {
            text += e.results[i][0].transcript
        }
        if (text) newPostContent.value = (newPostContent.value ? newPostContent.value + ' ' : '') + text
    }
    voiceRecognition.onerror = () => { stopVoiceInput(); ElMessage.error('语音识别出错') }
    voiceRecognition.onend = () => { if (voiceListening.value) voiceRecognition.start() }
    voiceRecognition.start()
}
function stopVoiceInput() {
    if (voiceRecognition) { voiceRecognition.onend = null; voiceRecognition.stop(); voiceRecognition = null }
    clearInterval(voiceTimer)
    voiceListening.value = false
    if (voiceDuration.value >= 2) ElMessage.success('语音已转文字')
}

function formatContent() {
    let text = newPostContent.value
    if (!text.trim()) { ElMessage.info('没有内容需要排版'); return }

    const original = text
    // 1. 多余空行：连续 3 个以上换行缩为 2 个
    text = text.replace(/\n{3,}/g, '\n\n')
    // 2. 行首行尾空格
    text = text.replace(/[^\S\n]+$/gm, '').replace(/^[^\S\n]+/gm, '')
    // 3. 全角英文/数字 → 半角（中文标点周围保留全角）
    text = text.replace(/[Ａ-Ｚａ-ｚ０-９]/g, c => String.fromCharCode(c.charCodeAt(0) - 65248))
    // 4. 全角空格 → 半角
    text = text.replace(/\u3000/g, ' ')
    // 5. 中文后的全角逗号句号保持全角，英文后的改为半角
    text = text.replace(/([\u4e00-\u9fa5]),/g, '$1，')
    text = text.replace(/([\u4e00-\u9fa5])\./g, '$1。')
    text = text.replace(/,([\u4e00-\u9fa5])/g, '，$1')
    text = text.replace(/\.([\u4e00-\u9fa5])/g, '。$1')
    // 6. URL 规范化：去除尾部多余的 /
    text = text.replace(/(https?:\/\/[^\s<]+?)\/+(\s|$)/g, '$1$2')
    // 7. 连续空格缩为 1 个（不影响代码块）
    text = text.replace(/[ ]{2,}/g, ' ')

    newPostContent.value = text
    if (text !== original) {
        ElMessage.success('✅ 排版完成')
    } else {
        ElMessage.info('内容已经很整洁了')
    }
}

// ── 编辑帖子 ──
function editPost(post) {
    editingPost.value = post
    newPostContent.value = post.content
    postTags.value = post.tags?.map(t => t.name || t) || []
    postTemplate.value = post.template || 'discuss'
    existingImages.value = post.images || []
    removedImages.value = []
    showNewPost.value = true
}

// ── 删除已有图片 ──
function removeExistingImage(i) {
    removedImages.value.push(existingImages.value[i])
    existingImages.value.splice(i, 1)
}

// ── 删除帖子 ──
async function deletePost(post) {
    try {
        await ElMessageBox.confirm('确定删除此帖子？', '删除确认', { type: 'warning' })
        await apiClient.delete(`/moments/${post.id}`, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已删除')
        posts.value = posts.value.filter(p => p.id !== post.id)
    } catch { /* ignore */ }
}

// ── 收藏 ──
async function toggleFavorite(post) {
    if (!isLoggedIn) { window.location.href = '/build/login'; return }
    if (post.is_favorited) {
        // 取消收藏
        try {
            await apiClient.post(`/moments/${post.id}/favorite`, {}, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            post.is_favorited = false
            ElMessage.success('已取消收藏')
        } catch { /* ignore */ }
    } else {
        favoriteTargetId.value = post.id
        await loadCollections()
        showCollectionDialog.value = true
    }
}

async function loadCollections() {
    try {
        const res = await apiClient.get('/moments/favorites/collections', {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        collections.value = res.data?.data || []
    } catch { collections.value = [] }
}

async function addToCollection(col) {
    try {
        await apiClient.post(`/moments/${favoriteTargetId.value}/favorite`, { collection_id: col.id }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('已收藏')
        showCollectionDialog.value = false
        const post = posts.value.find(p => p.id === favoriteTargetId.value)
        if (post) post.is_favorited = true
    } catch { ElMessage.error('收藏失败') }
}

async function createCollection() {
    if (!newCollectionName.value.trim()) return
    try {
        await apiClient.post('/moments/favorites/collections', { name: newCollectionName.value }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('收藏夹已创建')
        newCollectionName.value = ''
        await loadCollections()
    } catch { ElMessage.error('创建失败') }
}

// ── 点赞 ──
async function toggleLike(post) {
    if (!isLoggedIn) { window.location.href = '/build/login'; return }
    try {
        await apiClient.post(`/moments/${post.id}/like`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        post.is_liked = !post.is_liked
        post.likes_count = (post.likes_count || 0) + (post.is_liked ? 1 : -1)
    } catch { /* ignore */ }
}

// ── 置顶/取消置顶 ──
async function togglePin(post) {
    try {
        const res = await apiClient.post(`/moments/${post.id}/pin`, {}, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        post.is_pinned = res.data?.data?.is_pinned ?? !post.is_pinned
        ElMessage.success(post.is_pinned ? '已置顶' : '已取消置顶')
    } catch { ElMessage.error('操作失败') }
}

// ── 分享 ──
function sharePost(post) {
    sharePostId.value = post.id
    shareLink.value = `${window.location.origin}/build/plaza/${post.id}`
    showShareDialog.value = true
}

function copyShareLink() {
    navigator.clipboard.writeText(shareLink.value).then(() => {
        ElMessage.success('链接已复制')
    }).catch(() => {
        ElMessage.info('请手动复制链接')
    })
}

// ── 关注/取消关注 ──
async function toggleFollow(user) {
    if (!isLoggedIn) { window.location.href = '/build/login'; return }
    try {
        if (user.is_following) {
            await apiClient.post(`/moments/users/${user.id}/unfollow`, {}, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            user.is_following = false
        } else {
            await apiClient.post(`/moments/users/${user.id}/follow`, {}, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            user.is_following = true
        }
    } catch { /* ignore */ }
}

// ── 举报 ──
async function reportPost(post) {
    if (!isLoggedIn) { window.location.href = '/build/login'; return }
    try {
        const reason = await ElMessageBox.prompt('请描述举报原因', '举报帖子', {
            inputType: 'textarea', confirmButtonText: '提交举报', cancelButtonText: '取消'
        }).then(r => r.value)
        if (!reason) return
        await apiClient.post('/user-chat/reports', {
            target_type: 'moment',
            target_id: post.id,
            reason,
        }, { headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } })
        ElMessage.success('举报已提交，感谢您的反馈')
    } catch { /* ignore */ }
}

// ── 打开详情 ──
function openDetail(post) {
    window.open(`/build/plaza/${post.id}`, '_blank')
}

// ── 查看用户主页 ──
function viewUser(user) {
    if (user?.id) window.open(`/build/plaza/user/${user.id}`, '_blank')
}

// ── Init ──
onMounted(() => {
    loadPosts()
    loadSidebarData()
})

onUnmounted(() => {
    scrollObserver?.disconnect()
})

</script>

<style scoped>
.community-page {
    min-height: calc(100vh - 80px);
    background: #f5f5f5;
    padding: 96px 0 60px;
}
.community-header {
    background: #fff;
    border-bottom: 1px solid #eee;
    position: sticky;
    top: 80px;
    z-index: 50;
}

/* ── 两栏布局 ── */
.community-layout {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}
.community-main {
    flex: 1;
    min-width: 0;
}
.community-sidebar {
    width: 300px;
    flex-shrink: 0;
    position: sticky;
    top: 180px;
}

/* ── 2 列帖子网格 ── */
.post-grid-2col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.post-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #eee;
    padding: 14px 16px;
    transition: all 0.2s ease;
    cursor: pointer;
    break-inside: avoid;
}
.post-card:hover {
    border-color: #ddd;
    box-shadow: 0 4px 12px rgba(0,0,0,.08);
    transform: translateY(-1px);
}

/* ── 编辑实时预览卡片 ── */
.preview-card { background: #fff; border-radius: 12px; border: 1px solid #eee; padding: 12px 14px; font-size: 13px; line-height: 1.6; color: #333; }
.preview-body { display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; }
.preview-images { display: grid; gap: 2px; margin-top: 8px; border-radius: 6px; overflow: hidden; }
.preview-images.img-count-1 { grid-template-columns: 1fr; }
.preview-images.img-count-2, .preview-images.img-count-4 { grid-template-columns: 1fr 1fr; }
.preview-images.img-count-3 { grid-template-columns: 1fr 1fr 1fr; }
.preview-images img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 4px; }
.preview-tag { display: inline-flex; padding: 1px 6px; background: #ecf5ff; color: #409eff; border-radius: 8px; font-size: 10px; }
.preview-card .line-clamp-4 { -webkit-line-clamp: 4; }
.post-body {
    font-size: 14px;
    line-height: 1.7;
    color: #333;
}
.line-clamp-4 {
    display: -webkit-box;
    -webkit-line-clamp: 4;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 3px 10px;
    border-radius: 6px;
    font-size: 12px;
    color: #999;
    transition: all 0.15s ease;
    cursor: pointer;
    user-select: none;
}
.action-btn:hover { background: #f5f5f5; }
.action-btn.liked { color: #ef4444; }
.action-btn.liked:hover { background: #fef2f2; }
.action-btn.faved { color: #f59e0b; }
.action-btn.faved:hover { background: #fffbeb; }

/* ── 骨架屏 ── */
.skeleton-card { background: #fff; border-radius: 12px; border: 1px solid #eee; padding: 14px 16px; }
.skeleton-avatar { width: 36px; height: 36px; border-radius: 50%; background: #eee; animation: sk-pulse 1.5s ease-in-out infinite; }
.skeleton-line { height: 12px; border-radius: 6px; background: #eee; animation: sk-pulse 1.5s ease-in-out infinite; }
.skeleton-image { width: 100%; height: 140px; border-radius: 8px; background: #eee; animation: sk-pulse 1.5s ease-in-out infinite; }
@keyframes sk-pulse { 0%, 100% { opacity: .4; } 50% { opacity: .8; } }
.tag-pill {
    display: inline-flex;
    padding: 1px 8px;
    background: #ecf5ff;
    color: #409eff;
    border-radius: 10px;
    font-size: 11px;
    transition: all 0.15s ease;
    cursor: pointer;
}
.tag-pill:hover {
    background: #409eff;
    color: #fff;
}

/* ── 图片网格 ── */
.post-images { display: grid; gap: 3px; margin: 0 auto 8px; border-radius: 6px; overflow: hidden; justify-items: center; }
.img-count-1 { grid-template-columns: 1fr; max-width: 100%; }
.img-count-2, .img-count-4 { grid-template-columns: 1fr 1fr; }
.img-count-3 { grid-template-columns: 1fr 1fr 1fr; }
.post-images img { width: 100%; cursor: pointer; transition: opacity .15s; border-radius: 4px; }
.post-images.img-count-1 img { aspect-ratio: auto; max-height: 360px; width: auto; max-width: 100%; object-fit: contain; background: #f9f9f9; display: block; margin: 0 auto; }
.post-images.img-count-2 img, .post-images.img-count-3 img, .post-images.img-count-4 img { aspect-ratio: 1; object-fit: cover; }
.post-images img:hover { opacity: .85; }

/* ── 灯箱 ── */
.community-lightbox {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,.88);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    animation: fadeIn .2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.lightbox-img {
    max-width: 90vw;
    max-height: 90vh;
    object-fit: contain;
    cursor: default;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}
.lightbox-close {
    position: absolute;
    top: 20px;
    right: 24px;
    color: #fff;
    font-size: 28px;
    background: rgba(255,255,255,.1);
    border: none;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background .2s;
}
.lightbox-close:hover { background: rgba(255,255,255,.2); }

/* ── 侧边栏卡片 ── */
.sidebar-card {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #eee;
    padding: 16px;
    margin-bottom: 16px;
}
.sidebar-title {
    font-size: 14px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
}
.sidebar-divider {
    height: 1px;
    background: #f0f0f0;
    margin-bottom: 12px;
}
.sidebar-tag-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sidebar-tag-item:hover {
    background: #f5f5f5;
}
.sidebar-tag-rank {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
    color: #999;
    background: #f5f5f5;
    flex-shrink: 0;
}
.sidebar-tag-rank.rank-1 {
    color: #fff;
    background: linear-gradient(135deg, #f59e0b, #ef4444);
    box-shadow: 0 2px 4px rgba(239,68,68,.2);
}
.sidebar-tag-rank.rank-2 {
    color: #fff;
    background: linear-gradient(135deg, #94a3b8, #64748b);
}
.sidebar-tag-rank.rank-3 {
    color: #fff;
    background: linear-gradient(135deg, #d4a574, #a0522d);
}

/* ── 贡献榜 ── */
.sidebar-user-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sidebar-user-item:hover {
    background: #f5f5f5;
}
.sidebar-post-item {
    padding: 8px 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    border-bottom: 1px solid #f5f5f5;
}
.sidebar-post-item:last-child { border-bottom: none; }
.sidebar-post-item:hover { background: #f5f5f5; }
.sidebar-rank-badge {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 700;
    color: #999;
    background: #f5f5f5;
    flex-shrink: 0;
}
.sidebar-rank-badge.rank-1 {
    color: #fff;
    background: linear-gradient(135deg, #f59e0b, #f97316);
    box-shadow: 0 2px 4px rgba(245,158,11,.3);
}
.sidebar-rank-badge.rank-2 {
    color: #fff;
    background: linear-gradient(135deg, #94a3b8, #64748b);
}
.sidebar-rank-badge.rank-3 {
    color: #fff;
    background: linear-gradient(135deg, #d4a574, #8B4513);
}

/* ── 关注按钮 ── */
.follow-btn { padding: 2px 10px; border-radius: 14px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all .15s ease; white-space: nowrap; border: 1px solid #409eff; color: #409eff; background: #fff; flex-shrink: 0; }
.follow-btn:hover { background: #409eff; color: #fff; }
.follow-btn.following { border-color: #ddd; color: #999; background: #f5f5f5; }
.follow-btn.following:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }
.follow-btn-sm { padding: 1px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; cursor: pointer; transition: all .15s ease; white-space: nowrap; border: 1px solid #409eff; color: #409eff; background: #fff; flex-shrink: 0; line-height: 1.6; }
.follow-btn-sm:hover { background: #409eff; color: #fff; }
.follow-btn-sm.following { border-color: #ddd; color: #999; background: #f5f5f5; }
.follow-btn-sm.following:hover { border-color: #ef4444; color: #ef4444; background: #fef2f2; }

/* ── 响应式 ── */
@media (max-width: 1024px) {
    .community-sidebar {
        display: none;
    }
    .post-grid-2col {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 640px) {
    .community-page { padding-top: 80px; }
    .post-grid-2col {
        grid-template-columns: 1fr;
    }
    .post-card { padding: 12px 14px; }
    .post-body { font-size: 13px; }
    .action-btn { font-size: 11px; padding: 2px 8px; }
}
</style>
