<template>
    <div class="channels-page" :class="{ 'showing-detail': selectedChannel }">
        <!-- 头部 -->
        <div class="channels-header">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900">📢 互物号</h1>
                        <p class="text-sm text-gray-500 mt-1">关注你感兴趣的互物号，获取最新文章与动态</p>
                    </div>
                    <button v-if="isLoggedIn && !selectedChannel" class="text-sm text-primary-600 hover:text-primary-700 font-medium whitespace-nowrap" @click="switchToSubscribed">我的订阅 →</button>
                    <button v-if="selectedChannel" class="text-sm text-gray-500 hover:text-gray-700 font-medium" @click="selectedChannel = null">← 返回列表</button>
                </div>

                <!-- 统一搜索 + Tab 行 -->
                <div class="mb-4" v-if="!selectedChannel && (activeTab === 'articles' || activeTab === 'discover' || activeTab === 'subscribed')">
                    <div class="flex items-center gap-2 sm:gap-3 flex-wrap bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 mb-3">
                        <div class="flex-1 min-w-[180px]">
                            <el-input v-model="searchQuery" :placeholder="activeTab === 'articles' ? '搜索文章标题...' : '搜索互物号...'" clearable size="default" class="!w-full" @input="onUnifiedSearch" @clear="onUnifiedClear">
                                <template #prefix>
                                    <el-icon><Search /></el-icon>
                                </template>
                            </el-input>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <el-dropdown trigger="click" v-if="activeTab === 'discover'">
                                <button class="px-3 py-2 text-xs text-gray-400 hover:text-gray-600 bg-white border border-gray-200 rounded-lg transition whitespace-nowrap flex items-center gap-1">
                                    {{ categories.find(c => c.value === activeCategory)?.label || '全部分类' }} <el-icon><ArrowDown /></el-icon>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="cat in categories" :key="cat.value" @click="activeCategory = cat.value; loadChannels()">{{ cat.label }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-dropdown trigger="click" v-if="activeTab === 'articles'">
                                <button class="px-3 py-2 text-xs text-gray-400 hover:text-gray-600 bg-white border border-gray-200 rounded-lg transition whitespace-nowrap flex items-center gap-1">
                                    {{ feedSort === 'ai' ? 'AI 推荐' : feedSort === 'sequence' ? '序列预测' : feedSort === 'recommended' ? '综合推荐' : feedSort === 'latest' ? '最新' : feedSort === 'hot' ? '最热' : '本周热门' }} <el-icon><ArrowDown /></el-icon>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item v-for="s in sortOptions" :key="s.value" @click="feedSort = s.value; loadFeed(1)">{{ s.label }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <span v-if="searchQuery && !loading" class="text-xs text-gray-400 whitespace-nowrap">
                                {{ activeTab === 'articles' ? '找到 ' + feedTotal + ' 篇' : '找到 ' + channels.length + ' 个互物号' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Tab -->
                <div class="flex gap-1 border-b border-gray-100 -mx-4 px-4 sm:mx-0 sm:px-0 overflow-x-auto">
                    <button v-for="t in tabs" :key="t.key"
                        class="px-4 py-2.5 text-sm font-medium whitespace-nowrap transition rounded-t-lg relative inline-flex items-center gap-1"
                        :class="activeTab === t.key ? 'text-primary-600 border-b-2 border-primary-600 bg-primary-50/50' : 'text-gray-500 hover:text-gray-700'"
                        @click="switchTab(t.key)">
                        <span v-if="t.icon" v-html="t.icon" class="inline-flex items-center"></span>
                        <span>{{ t.label }}</span>
                        <span v-if="t.key === 'manage' && pendingCount > 0"
                            class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center px-1 bg-red-500 text-white text-[10px] font-bold rounded-full leading-none shadow-sm">
                            {{ pendingCount > 99 ? '99+' : pendingCount }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ── 发现页：互物号列表 ── -->
        <Transition name="fade-slide" mode="out-in">
        <div v-if="(activeTab === 'discover' || activeTab === 'subscribed') && !selectedChannel" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" key="discover">
            <!-- 统计横幅（数据加载完成后显示） -->
            <div v-if="!loading" class="flex items-center gap-4 mb-5 text-sm text-gray-500">
                <span class="flex items-center gap-1.5 px-3 py-1.5 bg-white rounded-full shadow-sm border border-gray-100">📡 <strong class="text-gray-800">{{ channels.length }}</strong> 个互物号</span>
                <span class="flex items-center gap-1.5 px-3 py-1.5 bg-white rounded-full shadow-sm border border-gray-100">📄 <strong class="text-gray-800">{{ channels.reduce((s, c) => s + (c.articles_count||0), 0) }}</strong> 篇文章</span>
            </div>

            <!-- 骨架屏 -->
            <div v-if="loading" class="space-y-3">
                <div v-for="i in 4" :key="i" class="ch-skeleton">
                    <div class="flex items-center gap-4 p-4 sm:p-5">
                        <div class="ch-sk-avatar"></div>
                        <div class="flex-1">
                            <div class="ch-sk-line w-48"></div>
                            <div class="ch-sk-line w-80 mt-2"></div>
                            <div class="flex gap-4 mt-3">
                                <div class="ch-sk-line w-16"></div>
                                <div class="ch-sk-line w-16"></div>
                                <div class="ch-sk-line w-40"></div>
                            </div>
                        </div>
                        <div class="ch-sk-line w-16 h-8 rounded-full"></div>
                    </div>
                </div>
            </div>
            <div v-if="!loading" class="space-y-3">
                <div v-for="ch in channels" :key="ch.id"
                    class="bg-white rounded-xl border border-gray-100 hover:border-gray-200 transition-all duration-200 hover:shadow-md cursor-pointer overflow-hidden"
                    @click="viewChannelDetail(ch)">
                    <div class="flex items-center gap-4 p-4 sm:p-5">
                        <!-- 头像 -->
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-xl sm:text-2xl overflow-hidden shadow-sm flex-shrink-0 relative">
                            <img :src="ch.avatar" class="absolute inset-0 w-full h-full object-cover rounded-2xl" style="display:none" @load="$event.target.style.display='';$event.target.parentElement.querySelector('span').style.display='none'" @error="$event.target.style.display='none'" />
                            <span class="relative">{{ (ch.name || '号').charAt(0) }}</span>
                        </div>
                        <!-- 信息 -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-base sm:text-lg font-bold text-gray-900 truncate">{{ ch.name }}</span>
                                <span v-if="ch.is_verified" class="px-1.5 py-0.5 bg-green-50 text-green-600 rounded text-[10px] font-medium border border-green-100">
                                    ✓
                                    <template v-if="ch.verified_info">
                                        {{ ch.verified_info.type === 'enterprise' ? '企业认证' : '个人认证' }} · {{ ch.verified_info.name }}
                                    </template>
                                    <template v-else>认证</template>
                                </span>
                                <span v-if="ch.category" class="px-2 py-0.5 bg-gray-50 text-gray-500 rounded text-[10px] border border-gray-100">{{ ch.category }}</span>
                            </div>
                            <p class="text-sm text-gray-500 mt-1 line-clamp-1 leading-relaxed">{{ ch.description || '暂无简介' }}</p>
                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                                <span class="flex items-center gap-1"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="16" height="16" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg> <strong class="text-gray-600">{{ ch.followers_count || 0 }}</strong></span>
                                <span class="flex items-center gap-1">📄 <strong class="text-gray-600">{{ ch.articles_count || 0 }}</strong></span>
                                <span v-if="ch.latest_article" class="text-primary-500/70 truncate max-w-[180px] sm:max-w-[280px]">📌 {{ ch.latest_article.title }}</span>
                            </div>
                        </div>
                        <!-- 关注按钮 -->
                        <div class="flex-shrink-0" @click.stop="toggleFollow(ch)">
                            <button v-if="isLoggedIn"
                                class="wx-follow-btn" :class="{ following: ch.is_following }">
                                {{ ch.is_following ? '已关注' : '+ 关注' }}
                            </button>
                            <a v-else
                                class="wx-follow-btn no-underline inline-block text-center"
                                href="/build/login">+ 关注</a>
                        </div>
                    </div>
                </div>
            </div>
            <el-empty v-if="!loading && !channels.length" description="暂无互物号" :image-size="80" class="py-16" />
        </div>
        </Transition>

        <!-- ── 全部文章流 ── -->
        <Transition name="fade-slide" mode="out-in">
        <div v-if="activeTab === 'articles' && !selectedChannel" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" key="articles">
            <!-- 🏷️ 标签云 -->
            <div v-if="popularTags.length" class="mb-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <button class="tag-chip" :class="{ 'tag-chip-active': !selectedFeedTag }" @click="onTagFilterClick('')">
                        🏷️ 全部
                    </button>
                    <button v-for="tag in popularTags" :key="tag.name"
                        class="tag-chip" :class="{ 'tag-chip-active': selectedFeedTag === tag.name }"
                        @click="onTagFilterClick(tag.name)">
                        {{ tag.name }}
                        <span class="tag-chip-count">{{ tag.count }}</span>
                    </button>
                </div>
            </div>

            <!-- 筛选栏 -->
            <div class="flex items-center gap-3 mb-5">
                <span class="text-xs text-gray-400">共 {{ feedTotal }} 篇</span>
            </div>

            <!-- 骨架屏 -->
            <div v-if="feedLoading && !feedArticles.length" class="space-y-3">
                <div v-for="i in 3" :key="i" class="bg-white rounded-xl border border-gray-100 overflow-hidden p-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-5 h-5 bg-gray-100 rounded-full animate-pulse"></div>
                                <div class="h-3 bg-gray-100 rounded w-24 animate-pulse"></div>
                            </div>
                            <div class="h-4 bg-gray-100 rounded w-3/4 animate-pulse mb-2"></div>
                            <div class="h-3 bg-gray-100 rounded w-full animate-pulse mb-1"></div>
                            <div class="h-3 bg-gray-100 rounded w-2/3 animate-pulse mb-3"></div>
                            <div class="flex gap-3">
                                <div class="h-3 bg-gray-100 rounded w-16 animate-pulse"></div>
                                <div class="h-3 bg-gray-100 rounded w-16 animate-pulse"></div>
                                <div class="h-3 bg-gray-100 rounded w-16 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="w-28 h-20 bg-gray-100 rounded-lg animate-pulse flex-shrink-0"></div>
                    </div>
                </div>
            </div>

            <!-- 文章列表（头条风格） -->
            <div v-if="!feedLoading || feedArticles.length" class="tt-article-list">
                <div v-for="art in feedArticles" :key="art.id" class="tt-article-item" @click="viewArticle(art)">
                    <div class="tt-article-body">
                        <!-- 来源标签 -->
                        <div class="tt-article-source">
                            <img v-if="art.account?.avatar" :src="art.account.avatar" class="w-5 h-5 rounded-full object-cover inline-block mr-1 flex-shrink-0" @error="$event.target.style.display='none'" />
                            <span class="tt-source-name">{{ art.account?.name || '互物号' }}</span>
                            <span v-if="art.is_original" class="tt-source-tag tt-tag-original">原创</span>
                            <span v-for="tag in (art.tags||[]).slice(0, 1)" :key="tag" class="tt-source-tag">{{ tag }}</span>
                        </div>
                        <h3 class="tt-article-title"><span v-if="art.is_global_pinned" class="tt-source-tag tt-tag-pinned" style="margin-right:6px">置顶</span>{{ art.title }}</h3>
                        <div class="tt-article-meta">
                            <span>{{ formatDate(art.published_at) }}</span>
                            <span class="tt-meta-dot">·</span>
                            <span>{{ art.reads_count || 0 }} 阅读</span>
                            <span class="tt-meta-dot">·</span>
                            <span>{{ art.comments_count || 0 }} 评论</span>
                        </div>
                    </div>
                    <img v-if="art.cover_image" :src="art.cover_image" class="tt-article-thumb" @error="$event.target.style.display='none'" />
                </div>
                <!-- 加载更多 -->
                <div v-if="feedHasMore" class="text-center py-5">
                    <el-button :loading="feedLoadingMore" size="small" @click="loadMoreFeed">
                        {{ feedLoadingMore ? '加载中...' : '加载更多' }}
                    </el-button>
                </div>
                <el-empty v-if="!feedLoading && !feedArticles.length" description="暂无文章" :image-size="60" class="py-12" />
            </div>
        </div>
        </Transition>

        <!-- ── 频道详情页 ── -->
        <Transition name="fade-slide" mode="out-in">
        <div v-if="selectedChannel" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6" key="detail">
            <!-- 频道头部 -->
            <div class="rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6 relative"
                :class="selectedChannel.cover_image ? '' : 'bg-white'"
                :style="selectedChannel.cover_image ? { backgroundImage: `url(${selectedChannel.cover_image})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}">
                <!-- 封面遮罩 -->
                <div class="absolute inset-0" :class="selectedChannel.cover_image ? 'bg-white/85' : 'bg-white'"></div>
                <div class="relative z-10">
                <!-- 频道信息 -->
                <div class="px-5 sm:px-6 py-5 relative">
                    <!-- 头像重叠 -->
                    <div class="flex items-end -mt-2 mb-4">
                        <div class="w-20 h-20 rounded-2xl border-[3px] border-white bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-2xl overflow-hidden shadow-lg flex-shrink-0 relative">
                            <img :src="selectedChannel.avatar" class="absolute inset-0 w-full h-full object-cover rounded-2xl" style="display:none" @load="$event.target.style.display='';$event.target.parentElement.querySelector('span').style.display='none'" @error="$event.target.style.display='none'" />
                            <span class="relative">{{ (selectedChannel.name || '号').charAt(0) }}</span>
                        </div>
                        <div class="ml-4 pb-0.5 flex-1 min-w-0">
                            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2 flex-wrap">
                                {{ selectedChannel.name }}
                                <span v-if="selectedChannel.is_verified" class="px-1.5 py-0.5 bg-green-50 text-green-600 rounded text-[10px] font-medium border border-green-100">
                                    ✓
                                    <template v-if="selectedChannel.verified_info">
                                        {{ selectedChannel.verified_info.type === 'enterprise' ? '企业认证' : '个人认证' }} · {{ selectedChannel.verified_info.name }}
                                    </template>
                                    <template v-else>认证</template>
                                </span>
                            </h2>
                            <div class="flex flex-wrap items-center gap-3 mt-1 text-xs text-gray-500">
                                <span class="flex items-center gap-1">🆔 {{ selectedChannel.wx_id || selectedChannel.id }}</span>
                                <span class="flex items-center gap-1">📅 {{ formatDate(selectedChannel.created_at) }}</span>
                            </div>
                        </div>
                        <!-- 操作按钮 -->
                        <div class="flex items-center gap-2 pb-0.5 flex-shrink-0">
                            <button v-if="isLoggedIn"
                                class="wx-follow-btn text-sm px-6 py-2" :class="{ following: selectedChannel.is_following }"
                                @click="toggleFollow(selectedChannel)">
                                {{ selectedChannel.is_following ? '已关注' : '+ 关注' }}
                            </button>
                            <a v-else
                                class="wx-follow-btn text-sm px-6 py-2 inline-block text-center no-underline"
                                href="/build/login">
                                + 关注
                            </a>
                            <button v-if="isLoggedIn && !selectedChannel.is_owner"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium bg-white text-gray-600 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-all"
                                @click="openChat(selectedChannel)">
                                💬 发消息
                            </button>
                            <a v-if="!isLoggedIn && !selectedChannel.is_owner"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium bg-white text-gray-600 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 transition-all no-underline"
                                href="/build/login">
                                💬 发消息
                            </a>
                            <button v-if="isLoggedIn && selectedChannel.is_owner && selectedChannel.status === 'active'"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium bg-gradient-to-r from-primary-500 to-primary-600 text-white rounded-lg hover:from-primary-600 hover:to-primary-700 transition-all shadow-sm hover:shadow-md"
                                @click="createArticle(selectedChannel)">
                                ✏️ 写文章
                            </button>
                            <button v-else-if="isLoggedIn && selectedChannel.is_owner && selectedChannel.status === 'suspended'"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-medium bg-orange-50 text-orange-600 rounded-lg hover:bg-orange-100 transition-all"
                                @click="appealChannel(selectedChannel)">
                                🔓 申请解封
                            </button>
                        </div>
                    </div>
                    <!-- 简介 -->
                    <p class="text-sm text-gray-500 leading-relaxed mb-4">{{ selectedChannel.description || '暂无简介' }}</p>
                    <!-- 统计指标 -->
                    <div class="flex items-center gap-3 pt-4">
                        <div class="flex-1 bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                <span class="text-lg font-bold text-gray-900">{{ selectedChannel.followers_count || 0 }}</span>
                            </div>
                            <div class="text-[10px] text-blue-400 mt-0.5 font-medium">关注者</div>
                        </div>
                        <div class="flex-1 bg-gradient-to-br from-emerald-50 to-emerald-100/50 rounded-xl py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                <span class="text-lg font-bold text-gray-900">{{ selectedChannel.articles_count || articles.length }}</span>
                            </div>
                            <div class="text-[10px] text-emerald-400 mt-0.5 font-medium">篇文章</div>
                        </div>
                        <div class="flex-1 bg-gradient-to-br from-amber-50 to-amber-100/50 rounded-xl py-3 px-4 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <svg class="w-4 h-4 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <span class="text-lg font-bold text-gray-900">{{ articles.reduce((s, a) => s + (a.reads_count || 0), 0) }}</span>
                            </div>
                            <div class="text-[10px] text-amber-400 mt-0.5 font-medium">总阅读</div>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- 文章搜索 -->
            <div class="flex items-center gap-3 mb-4 bg-white rounded-xl border border-gray-100 px-4 py-3">
                <el-input v-model="articleSearch" placeholder="搜索文章标题..." :prefix-icon="Search" clearable size="default" class="flex-1 max-w-sm" @input="onArticleSearch" />
                <span class="px-2.5 py-1 bg-gray-50 rounded-full text-xs text-gray-500 border border-gray-100 whitespace-nowrap">共 {{ totalArticles }} 篇</span>
            </div>
            <!-- 文章列表骨架屏 -->
            <div v-if="articlesLoading && !articles.length" class="space-y-3">
                <div v-for="i in 3" :key="i" class="bg-white rounded-xl border border-gray-100 overflow-hidden p-4">
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <div class="h-4 bg-gray-100 rounded w-3/4 animate-pulse mb-2"></div>
                            <div class="h-3 bg-gray-100 rounded w-full animate-pulse mb-1"></div>
                            <div class="h-3 bg-gray-100 rounded w-2/3 animate-pulse mb-3"></div>
                            <div class="flex gap-3">
                                <div class="h-3 bg-gray-100 rounded w-16 animate-pulse"></div>
                                <div class="h-3 bg-gray-100 rounded w-12 animate-pulse"></div>
                                <div class="h-3 bg-gray-100 rounded w-12 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="w-28 h-20 bg-gray-100 rounded-lg animate-pulse flex-shrink-0"></div>
                    </div>
                </div>
            </div>
            <!-- 文章列表（头条风格） -->
            <div v-if="!articlesLoading || articles.length" class="tt-article-list">
                <div v-for="art in articles" :key="art.id" class="tt-article-item" @click="viewArticle(art)">
                    <div class="tt-article-body">
                        <h3 class="tt-article-title">
                            <span v-if="art.is_pinned" class="tt-source-tag tt-tag-pinned" style="margin-right:6px">置顶</span>{{ art.title }}
                        </h3>
                        <div class="tt-article-meta">
                            <span>{{ formatDate(art.published_at || art.created_at) }}</span>
                            <span class="tt-meta-dot">·</span>
                            <span>{{ art.comments_count || 0 }} 评论</span>
                            <span v-if="art.reads_count > 0" class="tt-meta-dot">·</span>
                            <span v-if="art.reads_count > 0">{{ art.reads_count }} 阅读</span>
                        </div>
                    </div>
                    <!-- 管理菜单（号主可见） -->
                    <div v-if="isLoggedIn && selectedChannel?.is_owner" class="tt-article-actions" @click.stop>
                        <el-dropdown trigger="click" @command="(cmd) => handleArticleAction(cmd, art)">
                            <button class="tt-action-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                            </button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="pin">
                                        <span>{{ art.is_pinned ? ' 取消置顶' : ' 置顶文章' }}</span>
                                    </el-dropdown-item>
                                    <el-dropdown-item command="edit">
                                        ✏️ 编辑文章
                                    </el-dropdown-item>
                                    <el-dropdown-item command="collection">
                                        📚 移入合集
                                    </el-dropdown-item>
                                    <el-dropdown-item command="stats">
                                        📊 阅读分析
                                    </el-dropdown-item>
                                    <el-dropdown-item command="delete" divided style="color:#ef4444">
                                        🗑️ 删除
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                    <img v-if="art.cover_image || art.image_url" :src="art.cover_image || art.image_url" class="tt-article-thumb" @error="$event.target.style.display='none'" />
                </div>
                <!-- 加载更多 -->
                <div v-if="articleHasMore" class="text-center py-5">
                    <el-button :loading="articleLoadingMore" size="small" @click="loadMoreArticles">
                        {{ articleLoadingMore ? '加载中...' : '加载更多' }}
                    </el-button>
                </div>
                <el-empty v-if="!articlesLoading && !articles.length" description="暂无文章" :image-size="60" class="py-12" />
            </div>
        </div>
        </Transition>

        <!-- ── 管理面板 ── -->
        <div v-if="activeTab === 'manage' && isLoggedIn" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- 操作栏 -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6 flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    <strong class="text-gray-900">{{ myChannels.length }}</strong> 个互物号 ·
                    <strong class="text-gray-900">{{ mySubmissions.length }}</strong> 篇投稿
                    <span v-if="pendingCount > 0" class="ml-2 px-2 py-0.5 bg-red-50 text-red-500 rounded-full text-xs font-medium">🔔 {{ pendingCount }} 待审核</span>
                </div>
                <button class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-gradient-to-r from-primary-500 to-primary-600 text-white text-sm font-medium rounded-xl hover:from-primary-600 hover:to-primary-700 transition-all shadow-md hover:shadow-lg" @click="showCreateDialog = true">
                    ➕ 创建互物号
                </button>
            </div>

            <!-- 我的互物号 -->
            <div class="mb-8">
                <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">
                    📢 我创建的互物号
                    <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-xs font-normal">{{ myChannels.length }}</span>
                </h3>
                <div v-if="myChannels.length" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="ch in myChannels" :key="ch.id" class="bg-white rounded-xl border border-gray-100 hover:border-gray-200 transition-all duration-200 hover:shadow-sm p-4">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center text-white font-bold text-base overflow-hidden shadow-sm flex-shrink-0 relative">
                                <img :src="ch.avatar" class="absolute inset-0 w-full h-full object-cover rounded-xl" style="display:none" @load="$event.target.style.display='';$event.target.parentElement.querySelector('span').style.display='none'" @error="$event.target.style.display='none'" />
                                <span class="relative">{{ (ch.name||'号').charAt(0) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-gray-900 truncate">{{ ch.name }}
                                    <span v-if="ch.status === 'pending'" class="ml-1.5 px-1.5 py-0.5 bg-yellow-50 text-yellow-600 rounded text-[10px] font-medium border border-yellow-100">待审核</span>
                                    <span v-else-if="ch.status === 'rejected'" class="ml-1.5 px-1.5 py-0.5 bg-red-50 text-red-500 rounded text-[10px] font-medium border border-red-100">已拒绝</span>
                                    <span v-else-if="ch.status === 'suspended'" class="ml-1.5 px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px] font-medium border border-gray-200">已禁用</span>
                                    <span v-if="ch.is_verified" class="ml-1.5 px-1.5 py-0.5 bg-green-50 text-green-600 rounded text-[10px] font-medium border border-green-100">
                                        ✓
                                        <template v-if="ch.verified_info">
                                            {{ ch.verified_info.type === 'enterprise' ? '企业认证' : '个人认证' }} · {{ ch.verified_info.name }}
                                        </template>
                                        <template v-else>认证</template>
                                    </span>
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="16" height="16" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg> {{ ch.followers_count || 0 }} · 📄 {{ ch.articles_count || 0 }} 文章</div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button class="px-3 py-1.5 text-xs font-medium text-gray-500 bg-gray-50 rounded-lg hover:bg-gray-100 transition" @click="editChannel(ch)" title="编辑"><svg viewBox="0 0 24 24" width="14" height="14" style="vertical-align:middle" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></button>
                                <template v-if="ch.status === 'suspended'">
                                    <button class="px-3 py-1.5 text-xs font-medium text-orange-600 bg-orange-50 rounded-lg hover:bg-orange-100 transition" @click="appealChannel(ch)">🔓 申请解封</button>
                                </template>
                                <template v-else-if="ch.status === 'active' && !ch.is_verified">
                                    <button class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition" @click="applyVerifyChannel(ch)">申请认证</button>
                                    <button class="px-3 py-1.5 text-xs font-medium text-white bg-primary-500 rounded-lg hover:bg-primary-600 transition shadow-sm" @click="createArticle(ch)">📝 写文章</button>
                                </template>
                                <template v-else>
                                    <button class="px-3 py-1.5 text-xs font-medium text-white bg-primary-500 rounded-lg hover:bg-primary-600 transition shadow-sm" @click="createArticle(ch)">📝 写文章</button>
                                </template>
                            </div>
                        </div>
                        <!-- 数据卡片 -->
                        <div class="grid grid-cols-3 gap-2 pt-3 border-t border-gray-50">
                            <div class="text-center">
                                <div class="text-sm font-bold text-gray-800">{{ formatNum(ch.total_reads || 0) }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">总阅读</div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm font-bold text-gray-800">{{ formatNum(ch.total_likes || 0) }}</div>
                                <div class="text-[10px] text-gray-400 mt-0.5">总点赞</div>
                            </div>
                            <div class="text-center cursor-pointer" @click.stop="toggleTrendChart(ch.id)">
                                <div class="text-sm font-bold flex items-center justify-center gap-0.5" :class="ch.reads_growth > 0 ? 'text-green-600' : ch.reads_growth < 0 ? 'text-red-500' : 'text-gray-400'">
                                    {{ ch.reads_growth > 0 ? '↑' : ch.reads_growth < 0 ? '↓' : '–' }}{{ Math.abs(ch.reads_growth || 0) }}%
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5">7日趋势 📈</div>
                            </div>
                        </div>
                        <!-- 迷你趋势图 -->
                        <div v-if="trendChartId === ch.id && ch.reads_trend?.length" class="pt-3 border-t border-gray-50 mt-3">
                            <div class="text-[10px] text-gray-400 mb-2">近7天阅读趋势</div>
                            <svg :viewBox="'0 0 220 50'" class="w-full h-auto" style="max-height:50px">
                                <template v-for="(d, i) in ch.reads_trend" :key="i">
                                    <rect :x="8 + i * 30" :y="48 - Math.max(barHeight(d.count, ch.reads_trend), 2)" 
                                        :width="20" :height="Math.max(barHeight(d.count, ch.reads_trend), 2)"
                                        :fill="d.count > 0 ? '#3b82f6' : '#e5e7eb'" rx="2" />
                                    <text :x="8 + i * 30 + 10" y="62" text-anchor="middle" 
                                        class="text-[8px]" fill="#999" font-size="8">{{ d.date.slice(3) }}</text>
                                </template>
                            </svg>
                        </div>
                    </div>
                </div>
                <div v-else class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                    <div class="text-3xl mb-3">📢</div>
                    <p class="text-sm text-gray-400 mb-4">还没有创建互物号</p>
                    <button class="px-5 py-2 bg-primary-500 text-white text-sm rounded-lg hover:bg-primary-600 transition shadow-sm" @click="showCreateDialog = true">立即创建</button>
                </div>
            </div>

            <!-- ── 管理控制台：侧边导航 + 内容区 ── -->
            <div class="flex gap-6">
                <!-- 左侧导航 -->
                <div class="w-56 flex-shrink-0">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm sticky top-[220px] py-2">
                        <template v-for="group in manageNavGroups" :key="group.label">
                            <div class="px-4 pt-4 pb-1.5 text-xs font-bold text-gray-500 uppercase tracking-wider border-t border-gray-50 first:border-t-0 first:pt-0">{{ group.label }}</div>
                            <button v-for="item in group.items" :key="item.key"
                                class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-left transition-all duration-150 border-l-[3px] border-transparent"
                                :class="manageSection === item.key ? 'bg-primary-50/60 text-primary-700 font-medium border-l-primary-500' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-800 hover:border-l-gray-200'"
                                @click="manageSection = item.key">
                                <span v-html="item.icon"></span>
                                <span>{{ item.label }}</span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- 右侧内容区 -->
                <div class="flex-1 min-w-0">
                    <!-- ── 📈 数据概览 ── -->
                    <div v-if="manageSection === 'overview'">
                        <!-- 账号选择 -->
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                                📊 数据概览
                                <span class="text-xs text-gray-400 font-normal">实时更新</span>
                            </h3>
                            <el-select v-model="dashboardAccountId" placeholder="选择互物号..." size="small" style="width:220px"
                                filterable @change="loadDashboard">
                                <el-option v-for="ch in myChannels" :key="ch.id" :label="ch.name" :value="ch.id">
                                    <span>{{ ch.name }}</span>
                                    <span class="text-xs text-gray-400 ml-1">· {{ ch.followers_count || 0 }} 关注</span>
                                </el-option>
                            </el-select>
                        </div>

                        <!-- 骨架加载 -->
                        <div v-if="dashboardLoading" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-gray-100 p-4 animate-pulse">
                                <div class="h-3 bg-gray-100 rounded w-16 mb-3"></div>
                                <div class="h-7 bg-gray-100 rounded w-24"></div>
                            </div>
                        </div>

                        <!-- 📊 指标卡片 -->
                        <div v-else-if="dashData" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-sm">👁️</span>
                                    <span class="text-xs text-gray-500 font-medium">总阅读</span>
                                </div>
                                <div class="text-2xl font-bold text-gray-900">{{ formatNum(dashData.total_reads) }}</div>
                                <div class="text-xs mt-1" :class="(dashData.read_change_rate||0) >= 0 ? 'text-green-500' : 'text-red-500'">
                                    {{ (dashData.read_change_rate||0) >= 0 ? '↑' : '↓' }}{{ Math.abs(dashData.read_change_rate||0) }}% 较昨日
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center text-sm"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="20" height="20" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg></span>
                                    <span class="text-xs text-gray-500 font-medium">关注者</span>
                                </div>
                                <div class="text-2xl font-bold text-gray-900">{{ formatNum(dashData.followers_count) }}</div>
                                <div class="text-xs mt-1 text-green-500" v-if="dashData.today_new_followers > 0">
                                    ↑ {{ dashData.today_new_followers }} 今日新增
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center text-sm">❤️</span>
                                    <span class="text-xs text-gray-500 font-medium">总点赞</span>
                                </div>
                                <div class="text-2xl font-bold text-gray-900">{{ formatNum(dashData.total_likes) }}</div>
                                <div class="text-xs mt-1 text-gray-400">共 {{ formatNum(dashData.total_shares) }} 次分享</div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-sm">💬</span>
                                    <span class="text-xs text-gray-500 font-medium">评论</span>
                                </div>
                                <div class="text-2xl font-bold text-gray-900">{{ formatNum(dashData.total_comments) }}</div>
                                <div class="text-xs mt-1 text-gray-400">{{ formatNum(dashData.articles_count) }} 篇文章</div>
                            </div>
                        </div>
                        <div v-else class="bg-white rounded-xl border border-gray-100 p-8 text-center mb-6">
                            <div class="text-3xl mb-3">📊</div>
                            <p class="text-sm text-gray-400">请选择一个互物号查看数据</p>
                        </div>

                        <!-- 📈 趋势图表 -->
                        <div v-if="dashData?.trends" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                            <!-- 粉丝增长趋势 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                                <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="16" height="16" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg> 粉丝增长趋势 <span class="text-[10px] text-gray-400 font-normal">近14天</span></h4>
                                <svg :viewBox="'0 0 320 80'" class="w-full h-auto" style="max-height:80px">
                                    <template v-for="(d, i) in dashData.trends.followers" :key="i">
                                        <rect :x="6 + i * 22" :y="76 - Math.max(barHeight(d.count, dashData.trends.followers), 2)"
                                            :width="16" :height="Math.max(barHeight(d.count, dashData.trends.followers), 2)"
                                            :fill="d.count > 0 ? '#8b5cf6' : '#e5e7eb'" rx="3" />
                                        <text v-if="i % 2 === 0" :x="6 + i * 22 + 8" y="92" text-anchor="middle" fill="#999" font-size="7">{{ d.date.slice(3) }}</text>
                                    </template>
                                    <line x1="0" y1="78" x2="320" y2="78" stroke="#f0f0f0" stroke-width="1" />
                                </svg>
                            </div>
                            <!-- 阅读趋势 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                                <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">👁️ 阅读趋势 <span class="text-[10px] text-gray-400 font-normal">近14天</span></h4>
                                <svg :viewBox="'0 0 320 80'" class="w-full h-auto" style="max-height:80px">
                                    <template v-for="(d, i) in dashData.trends.reads" :key="i">
                                        <rect :x="6 + i * 22" :y="76 - Math.max(barHeight(d.count, dashData.trends.reads), 2)"
                                            :width="16" :height="Math.max(barHeight(d.count, dashData.trends.reads), 2)"
                                            :fill="d.count > 0 ? '#3b82f6' : '#e5e7eb'" rx="3" />
                                        <text v-if="i % 2 === 0" :x="6 + i * 22 + 8" y="92" text-anchor="middle" fill="#999" font-size="7">{{ d.date.slice(3) }}</text>
                                    </template>
                                    <line x1="0" y1="78" x2="320" y2="78" stroke="#f0f0f0" stroke-width="1" />
                                </svg>
                            </div>
                            <!-- 分享趋势 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                                <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">🔗 分享趋势 <span class="text-[10px] text-gray-400 font-normal">近14天</span></h4>
                                <svg :viewBox="'0 0 320 80'" class="w-full h-auto" style="max-height:80px">
                                    <template v-for="(d, i) in dashData.trends.shares" :key="i">
                                        <rect :x="6 + i * 22" :y="76 - Math.max(barHeight(d.count, dashData.trends.shares), 2)"
                                            :width="16" :height="Math.max(barHeight(d.count, dashData.trends.shares), 2)"
                                            :fill="d.count > 0 ? '#10b981' : '#e5e7eb'" rx="3" />
                                        <text v-if="i % 2 === 0" :x="6 + i * 22 + 8" y="92" text-anchor="middle" fill="#999" font-size="7">{{ d.date.slice(3) }}</text>
                                    </template>
                                    <line x1="0" y1="78" x2="320" y2="78" stroke="#f0f0f0" stroke-width="1" />
                                </svg>
                            </div>
                            <!-- 点赞趋势 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                                <h4 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-1.5">❤️ 点赞趋势 <span class="text-[10px] text-gray-400 font-normal">近14天</span></h4>
                                <svg :viewBox="'0 0 320 80'" class="w-full h-auto" style="max-height:80px">
                                    <template v-for="(d, i) in dashData.trends.likes" :key="i">
                                        <rect :x="6 + i * 22" :y="76 - Math.max(barHeight(d.count, dashData.trends.likes), 2)"
                                            :width="16" :height="Math.max(barHeight(d.count, dashData.trends.likes), 2)"
                                            :fill="d.count > 0 ? '#ef4444' : '#e5e7eb'" rx="3" />
                                        <text v-if="i % 2 === 0" :x="6 + i * 22 + 8" y="92" text-anchor="middle" fill="#999" font-size="7">{{ d.date.slice(3) }}</text>
                                    </template>
                                    <line x1="0" y1="78" x2="320" y2="78" stroke="#f0f0f0" stroke-width="1" />
                                </svg>
                            </div>
                        </div>

                        <!-- 🏆 热门文章排行 -->
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 mb-4 flex items-center gap-2">🏆 热门文章排行</h3>
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                                <div v-for="(art, i) in topArticles" :key="art.id" class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 last:border-0 hover:bg-gray-50/60 transition cursor-pointer" @click="viewArticle(art)">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center font-bold text-sm flex-shrink-0" :class="[
                                        i === 0 ? 'bg-yellow-50 text-yellow-600 border border-yellow-200' :
                                        i === 1 ? 'bg-gray-50 text-gray-500 border border-gray-200' :
                                        i === 2 ? 'bg-orange-50 text-orange-600 border border-orange-200' :
                                        'bg-transparent text-gray-300'
                                    ]">{{ i + 1 }}</div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium text-gray-900 truncate">{{ art.title }}</div>
                                        <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-3">
                                            <span>👁️ {{ art.views_count || 0 }}</span>
                                            <span>❤️ {{ art.likes_count || 0 }}</span>
                                        </div>
                                    </div>
                                    <span class="text-xs text-gray-400 flex-shrink-0">{{ formatDate(art.published_at || art.created_at) }}</span>
                                </div>
                                <div v-if="!topArticles.length" class="py-10 text-center">
                                    <div class="text-3xl mb-2">🏆</div>
                                    <div class="text-sm text-gray-300">暂无数据</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 🔔 投稿审核 ── -->
                    <div v-if="manageSection === 'pending'">
                        <div class="bg-white rounded-xl border border-amber-100 p-6">
                            <div v-if="pendingSubmissions.length" class="space-y-3">
                                <div v-for="sub in pendingSubmissions" :key="sub.id" class="border-b border-gray-50 pb-3 last:border-0 last:pb-0">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 truncate">{{ sub.title || '无标题' }}</div>
                                            <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
                                                <span>👤 {{ sub.user?.name || '匿名' }}</span>
                                                <span>·</span>
                                                <span>{{ sub.account?.name || '未知互物号' }}</span>
                                                <span>·</span>
                                                <span>{{ formatDate(sub.created_at) }}</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 flex-shrink-0">
                                            <button class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 rounded-lg hover:bg-green-600 transition shadow-sm" @click="reviewSubmission(sub.id, 'approve')">✅ 通过</button>
                                            <button class="px-3 py-1.5 text-xs font-medium text-white bg-red-400 rounded-lg hover:bg-red-500 transition shadow-sm" @click="reviewSubmission(sub.id, 'reject')">❌ 驳回</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-8">
                                <div class="text-3xl mb-2">✅</div>
                                <p class="text-sm text-gray-400">暂无待审核投稿</p>
                            </div>
                        </div>
                    </div>

                    <!-- ── 📝 文章管理 ── -->
                    <div v-if="manageSection === 'articles'">
                        <div class="bg-white rounded-xl border border-gray-100 p-5">
                            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                                <h3 class="text-base font-semibold text-gray-900">📝 文章管理</h3>
                                <el-select v-model="articleManageAccountId" placeholder="选择互物号..." size="small" style="width:180px"
                                    filterable @change="loadManageArticles">
                                    <el-option v-for="ch in myChannels" :key="ch.id" :label="ch.name" :value="ch.id">
                                        <span>{{ ch.name }}</span>
                                        <span class="text-xs text-gray-400 ml-1">· {{ ch.articles_count || 0 }} 篇</span>
                                    </el-option>
                                </el-select>
                            </div>
                            <!-- 状态筛选 -->
                            <div class="flex items-center gap-2 mb-4">
                                <button v-for="s in articleStatusFilters" :key="s.key"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg transition"
                                    :class="articleStatusFilter === s.key ? 'bg-primary-50 text-primary-600 border border-primary-200' : 'bg-gray-50 text-gray-500 hover:bg-gray-100 border border-transparent'"
                                    @click="articleStatusFilter = s.key; loadManageArticles()">
                                    {{ s.label }}
                                </button>
                            </div>
                            <div v-if="articles.length" class="space-y-2">
                                <div v-for="art in articles" :key="art.id"
                                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-50 hover:border-gray-200 hover:bg-gray-50/50 transition cursor-pointer"
                                    @click="viewArticle(art)">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span v-if="art.is_pinned" class="tt-source-tag tt-tag-pinned" style="margin-left:0">置顶</span>
                                            <span class="text-sm font-medium text-gray-900 truncate">{{ art.title }}</span>
                                            <span class="text-[10px] px-1.5 py-0.5 rounded"
                                                :class="art.status === 'published' ? 'bg-green-50 text-green-600' : art.status === 'draft' ? 'bg-gray-50 text-gray-500' : 'bg-amber-50 text-amber-600'">
                                                {{ art.status === 'published' ? '已发布' : art.status === 'draft' ? '草稿' : '待审核' }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5">
                                            {{ formatDate(art.published_at || art.created_at) }}
                                            <span v-if="art.reads_count > 0"> · {{ art.reads_count }} 阅读</span>
                                            <span v-if="art.comments_count > 0"> · {{ art.comments_count }} 评论</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0" @click.stop>
                                        <button class="px-2.5 py-1.5 text-xs rounded-md transition" :class="art.status === 'published' ? 'text-amber-500 hover:text-amber-700 hover:bg-amber-50' : 'text-green-500 hover:text-green-700 hover:bg-green-50'" @click="togglePublishArticle(art)">
                                            {{ art.status === 'published' ? '🔽' : '🔼' }}
                                        </button>
                                        <button class="px-2.5 py-1.5 text-xs text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-md transition" @click="handleManageArticleAction('pin', art)">
                                            <span class="tt-source-tag tt-tag-pinned" style="font-size:10px;padding:0 5px">{{ art.is_pinned ? '已置顶' : '置顶' }}</span>
                                        </button>
                                        <button class="px-2.5 py-1.5 text-xs text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-md transition" @click="handleManageArticleAction('edit', art)">
                                            ✏️
                                        </button>
                                        <button class="px-2.5 py-1.5 text-xs text-red-400 hover:text-red-600 hover:bg-red-50 rounded-md transition" @click="handleManageArticleAction('delete', art)">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-12">
                                <div class="text-3xl mb-2">📝</div>
                                <p class="text-sm text-gray-400">暂无文章</p>
                                <button class="mt-3 px-4 py-2 text-xs font-medium text-primary-600 bg-primary-50 rounded-lg hover:bg-primary-100 transition" @click="createArticle(selectedChannel)">写第一篇</button>
                            </div>
                        </div>
                    </div>

                    <!-- ──  自定义菜单 ── -->
                    <div v-if="manageSection === 'menus'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900">📋 自定义菜单</h3>
                            <button class="text-xs text-white bg-primary-500 px-3 py-1.5 rounded-lg hover:bg-primary-600 transition" @click="showMenuDialog(null)">➕ 添加菜单</button>
                        </div>
                        <div v-if="menusLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else-if="!menus.length" class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                            <div class="text-3xl mb-2">📋</div>
                            <p class="text-sm text-gray-400 mb-3">暂无自定义菜单</p>
                            <button class="px-4 py-2 text-sm text-white bg-primary-500 rounded-lg hover:bg-primary-600 transition" @click="showMenuDialog(null)">➕ 添加菜单</button>
                        </div>
                        <div v-else class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <div v-for="(m, i) in menus" :key="m.id" class="relative flex-1 text-center" :class="{ 'border-l border-gray-200': i > 0 }">
                                        <div class="px-2 py-1.5 text-sm font-medium text-gray-800 cursor-pointer hover:text-primary-600 rounded transition flex items-center justify-center gap-1" @click="showMenuDialog(m)">
                                            <span class="truncate max-w-[80px]">{{ m.name }}</span>
                                            <span class="text-[10px] text-gray-300 hover:text-red-400" @click.stop="deleteMenu(m)">×</span>
                                        </div>
                                        <div v-if="m.children?.length" class="absolute top-full left-0 right-0 z-10 bg-white border border-gray-200 rounded-lg shadow-lg mt-1 overflow-hidden" style="min-width:120px">
                                            <div v-for="(child, ci) in m.children" :key="child.id" class="px-3 py-2 text-xs text-gray-700 hover:bg-gray-50 cursor-pointer border-b border-gray-50 last:border-0 flex items-center justify-between gap-1" @click="showMenuDialog(child)">
                                                <span class="truncate">{{ child.name }}</span>
                                                <span class="text-[10px] text-gray-300 hover:text-red-400" @click.stop="deleteMenu(child)">×</span>
                                            </div>
                                            <div class="px-3 py-2 text-xs text-primary-500 hover:bg-blue-50 cursor-pointer font-medium" @click="showMenuDialog(null, m.id)">+ 添加子菜单</div>
                                        </div>
                                        <div v-else class="absolute top-full left-0 right-0 z-10 mt-1" style="min-width:120px">
                                            <div class="px-3 py-2 text-xs text-primary-500 bg-white border border-gray-200 rounded-lg shadow-lg cursor-pointer hover:bg-blue-50 font-medium" @click="showMenuDialog(null, m.id)">+ 添加子菜单</div>
                                        </div>
                                    </div>
                                    <div class="flex-1 text-center border-l border-gray-200">
                                        <div class="px-2 py-1.5 text-sm text-primary-500 cursor-pointer hover:bg-blue-50 rounded font-medium" @click="showMenuDialog(null, null)">+ 添加</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 💬 评论管理 ── -->
                    <div v-if="manageSection === 'comments'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900">💬 评论管理</h3>
                            <span class="text-xs text-gray-400">{{ comments.length }} 条评论</span>
                        </div>
                        <div v-if="commentsLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else-if="!comments.length" class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                            <div class="text-3xl mb-2">💬</div>
                            <p class="text-sm text-gray-400">暂无评论</p>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="c in comments" :key="c.id" class="bg-white rounded-lg border px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 text-xs text-gray-400 mb-1">
                                            <span class="font-medium text-gray-600">{{ c.user?.name || '匿名' }}</span>
                                            <span>·</span>
                                            <span class="truncate max-w-[180px]">{{ c.article?.title || '文章' }}</span>
                                            <span>·</span>
                                            <span>{{ formatDate(c.created_at) }}</span>
                                        </div>
                                        <p class="text-sm text-gray-800 line-clamp-2">{{ c.content }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded-full" :class="{
                                            'bg-green-50 text-green-600': c.status === 'approved',
                                            'bg-amber-50 text-amber-600': c.status === 'pending',
                                            'bg-red-50 text-red-500': c.status === 'rejected'
                                        }">{{ { approved: '已通过', pending: '待审核', rejected: '已驳回' }[c.status] || c.status }}</span>
                                        <button v-if="c.status === 'pending'" class="px-2 py-1 text-[10px] font-medium text-white bg-green-500 rounded-md hover:bg-green-600 transition" @click="approveComment(c.id)">通过</button>
                                        <button v-if="c.status === 'pending'" class="px-2 py-1 text-[10px] font-medium text-white bg-red-400 rounded-md hover:bg-red-500 transition" @click="rejectComment(c.id)">驳回</button>
                                        <button class="px-2 py-1 text-[10px] font-medium text-gray-400 hover:text-red-500 transition" @click="deleteComment(c.id)">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 关注者列表 ── -->
                    <div v-if="manageSection === 'followers'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-1"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="16" height="16" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg> 关注者列表</h3>
                            <div class="flex items-center gap-3">
                                <el-select v-model="followerAccountId" size="small" class="!w-44" @change="loadFollowers()" v-if="myChannels.length > 1">
                                    <el-option v-for="ch in myChannels" :key="ch.id" :label="ch.name" :value="ch.id" />
                                </el-select>
                                <el-input v-model="followerSearch" placeholder="搜索关注者..." prefix-icon="Search" clearable size="small" class="!w-48" @input="onFollowerSearch" />
                                <span class="text-xs text-gray-400">共 {{ followerTotal }} 人</span>
                            </div>
                        </div>
                        <div v-if="followersLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else-if="!followers.length" class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                            <div class="text-3xl mb-2"><svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="32" height="32" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg></div>
                            <p class="text-sm text-gray-400">暂无关注者</p>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="f in followers" :key="f.id" class="bg-white rounded-lg border px-4 py-3 flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-300 to-primary-500 flex items-center justify-center text-white text-sm font-bold overflow-hidden flex-shrink-0 relative">
                                    <img v-if="f.user?.avatar_url" :src="f.user.avatar_url" class="absolute inset-0 w-full h-full object-cover" style="display:none" @load="$event.target.style.display='';$event.target.parentElement.querySelector('span').style.display='none'" @error="$event.target.style.display='none'" />
                                    <span>{{ (f.user?.name || '?').charAt(0) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate">{{ f.user?.name || '匿名' }}</div>
                                    <div class="text-xs text-gray-400 truncate">{{ f.user?.email || '' }}</div>
                                    <!-- 标签展示 -->
                                    <div v-if="f.tags?.length" class="flex gap-1 mt-1">
                                        <el-tag v-for="t in f.tags" :key="t.id" size="small"
                                            :style="{ background: t.color, borderColor: t.color, color: '#fff', fontSize: '10px', padding: '0 4px', height: '18px', lineHeight: '18px' }">
                                            {{ t.name }}
                                        </el-tag>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400 flex-shrink-0 whitespace-nowrap">关注于 {{ formatDate(f.created_at) }}</div>
                                <button class="px-2 py-1 text-[10px] text-gray-400 hover:text-primary-500 transition flex-shrink-0" @click="openAssignTagDialog(f)" title="打标签">🏷️</button>
                            </div>
                        </div>
                        <div v-if="followerHasMore" class="text-center pt-3">
                            <el-button :loading="followersLoadingMore" size="small" @click="loadMoreFollowers">{{ followersLoadingMore ? '加载中...' : '加载更多' }}</el-button>
                        </div>
                    </div>

                    <!-- ── 🏷️ 粉丝标签管理 ── -->
                    <div v-if="manageSection === 'follower-tags'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-1.5 pb-3 border-b border-gray-50"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#409eff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> 粉丝标签</h3>
                            <div class="flex items-center gap-3">
                                <el-select v-model="tagAccountId" size="small" class="!w-44" @change="loadFollowerTags()" v-if="myChannels.length > 1" placeholder="选择互物号">
                                    <el-option v-for="ch in myChannels" :key="ch.id" :label="ch.name" :value="ch.id" />
                                </el-select>
                                <button class="text-xs text-white bg-primary-500 px-4 py-2 rounded-lg hover:bg-primary-600 transition shadow-sm hover:shadow-md inline-flex items-center gap-1.5" @click="showCreateTagDialog = true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> 创建标签</button>
                            </div>
                        </div>
                        <div v-if="followerTagsLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else-if="!followerTags.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center mx-auto mb-4"><svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></div>
                            <p class="text-sm text-gray-400 mb-4">暂无标签，创建后可在关注者列表中为粉丝打标签</p>
                            <button class="px-5 py-2 text-sm font-medium text-white bg-primary-500 rounded-lg hover:bg-primary-600 transition shadow-sm" @click="showCreateTagDialog = true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> 创建标签</button>
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="tag in followerTags" :key="tag.id" class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 pl-5 pr-4 py-3 flex items-center gap-4" :style="{ borderLeft: '4px solid ' + (tag.color || '#409eff') }">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 flex items-center gap-2.5">
                                        {{ tag.name }}
                                        <span class="text-xs text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full border border-gray-100">{{ tag.relations_count || 0 }} 人</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <button class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-primary-500 hover:bg-primary-50 transition" @click="editFollowerTag(tag)" title="编辑"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
                                    <button class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition" @click="deleteFollowerTag(tag)" title="删除"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 创建/编辑标签对话框 ── -->
                    <el-dialog v-model="showCreateTagDialog" :title="editingTag ? '✏️ 编辑标签' : '➕ 创建标签'" width="380px" top="25vh" @close="showCreateTagDialog = false; editingTag = null; tagForm = { name: '', color: '#409eff' }">
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-700 block mb-1">名称 *</label>
                                <el-input v-model="tagForm.name" placeholder="标签名称，如 VIP、活跃粉丝" maxlength="50" />
                            </div>
                            <div class="border-t border-gray-50 pt-3">
                                <label class="text-sm font-medium text-gray-700 block mb-1.5">颜色</label>
                                <div class="flex items-center gap-2">
                                    <div v-for="c in tagColors" :key="c" class="w-7 h-7 rounded-full cursor-pointer border-2 transition-all duration-150 shadow-sm"
                                        :class="tagForm.color === c ? 'border-gray-800 scale-110 ring-2 ring-offset-1 ring-gray-300' : 'border-transparent hover:scale-110'"
                                        :style="{ background: c }" @click="tagForm.color = c"></div>
                                    <input type="color" v-model="tagForm.color" class="w-7 h-7 rounded-full cursor-pointer border-0 p-0" title="自定义颜色" />
                                </div>
                            </div>
                        </div>
                        <template #footer>
                            <el-button @click="showCreateTagDialog = false; editingTag = null; tagForm = { name: '', color: '#409eff' }">取消</el-button>
                            <el-button type="primary" :loading="savingTag" @click="saveFollowerTag">{{ editingTag ? '保存' : '创建' }}</el-button>
                        </template>
                    </el-dialog>

                    <!-- ── 给粉丝打标签对话框 ── -->
                    <el-dialog v-model="showAssignTagDialog" title="🏷️ 给粉丝打标签" width="400px" top="25vh">
                        <div class="space-y-3">
                            <div class="text-sm text-gray-500 mb-2">
                                为 <strong class="text-gray-800">{{ assignTagFollowerName }}</strong> 选择标签：
                            </div>
                            <div v-if="availableTags.length" class="flex flex-wrap gap-2">
                                <el-tag v-for="tag in availableTags" :key="tag.id"
                                    :style="{ background: tag.color, borderColor: tag.color, color: '#fff', cursor: 'pointer' }"
                                    :type="assignTagIds.includes(tag.id) ? 'primary' : 'info'"
                                    effect="plain" size="small"
                                    @click="toggleAssignTag(tag.id)">
                                    {{ tag.name }}
                                </el-tag>
                            </div>
                            <div v-else class="text-sm text-gray-400 text-center py-4">暂无标签，请先在「粉丝标签」管理中创建</div>
                        </div>
                        <template #footer>
                            <el-button @click="showAssignTagDialog = false">取消</el-button>
                            <el-button type="primary" :loading="savingAssignTag" @click="saveAssignTag">确定</el-button>
                        </template>
                    </el-dialog>

                    <!-- ── 🤖 自动回复 ── -->
                    <div v-if="manageSection === 'autoreply'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900">🤖 自动回复</h3>
                            <button class="text-xs text-white bg-primary-500 px-3 py-1.5 rounded-lg hover:bg-primary-600 transition" @click="showAutoReplyDialog(null)">➕ 添加回复</button>
                        </div>
                        <div v-if="autoRepliesLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else-if="!autoReplies.length" class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                            <div class="text-3xl mb-2">🤖</div>
                            <p class="text-sm text-gray-400 mb-3">暂无自动回复规则</p>
                            <button class="px-4 py-2 text-sm text-white bg-primary-500 rounded-lg hover:bg-primary-600 transition" @click="showAutoReplyDialog(null)">➕ 添加自动回复</button>
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="r in autoReplies" :key="r.id" class="bg-white rounded-lg border px-4 py-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <span class="px-1.5 py-0.5 text-[10px] font-medium rounded" :class="{
                                                'bg-blue-50 text-blue-600': r.type === 'welcome',
                                                'bg-purple-50 text-purple-600': r.type === 'keyword',
                                                'bg-gray-50 text-gray-500': r.type === 'default'
                                            }">{{ { welcome: '关注回复', keyword: '关键词', default: '默认回复' }[r.type] || r.type }}</span>
                                            <span v-if="r.keyword" class="text-xs text-gray-500">「{{ r.keyword }}」</span>
                                            <span v-if="r.match_type === 1" class="text-[10px] text-gray-400">(模糊)</span>
                                            <span v-else-if="r.keyword" class="text-[10px] text-gray-400">(精确)</span>
                                        </div>
                                        <p class="text-sm text-gray-800 line-clamp-2">{{ r.content }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5 flex-shrink-0">
                                        <el-switch :model-value="r.is_active" size="small" @change="toggleAutoReply(r)" />
                                        <button class="px-2 py-1 text-[10px] text-gray-400 hover:text-primary-500 transition" @click="showAutoReplyDialog(r)">✏️</button>
                                        <button class="px-2 py-1 text-[10px] text-gray-400 hover:text-red-500 transition" @click="deleteAutoReply(r)">🗑️</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 🖼️ 素材库 ── -->
                    <div v-if="manageSection === 'materials'">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-base font-semibold text-gray-900">🖼️ 素材库</h3>
                            <el-upload :auto-upload="false" :on-change="onMaterialFileSelect" accept="image/*" :show-file-list="false">
                                <button class="px-4 py-2 text-sm font-medium text-white bg-primary-500 rounded-lg hover:bg-primary-600 transition shadow-sm">📤 上传图片</button>
                            </el-upload>
                        </div>
                        <div v-if="materialFile" class="flex items-center gap-2 mb-4 bg-blue-50 rounded-lg px-4 py-2">
                            <span class="text-sm text-gray-600 truncate flex-1">{{ materialFile.name }}</span>
                            <button class="px-3 py-1.5 text-xs font-medium text-white bg-green-500 rounded-lg hover:bg-green-600 transition" :loading="uploadingMaterial" @click="uploadMaterial">确认上传</button>
                        </div>
                        <div v-if="materialsLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else-if="!materials.length" class="bg-white rounded-xl border border-gray-100 p-8 text-center">
                            <div class="text-3xl mb-2">🖼️</div>
                            <p class="text-sm text-gray-400 mb-3">暂无素材</p>
                        </div>
                        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                            <div v-for="m in materials" :key="m.id" class="bg-white rounded-lg border border-gray-100 hover:shadow-sm transition overflow-hidden group relative">
                                <div v-if="m.type === 'image'" class="aspect-square bg-gray-50 flex items-center justify-center overflow-hidden">
                                    <img :src="m.file_url" class="w-full h-full object-cover" @error="$event.target.style.display='none'" />
                                </div>
                                <div v-else class="aspect-square bg-gray-50 flex items-center justify-center p-4">
                                    <p class="text-xs text-gray-500 line-clamp-4 text-center">{{ m.content }}</p>
                                </div>
                                <div class="px-2 py-1.5 flex items-center justify-between gap-1">
                                    <span class="text-[10px] text-gray-400 truncate">{{ m.file_name || m.type }}</span>
                                    <button class="text-[10px] text-gray-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100" @click="deleteMaterial(m)">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 💰 收益提现 ── -->
                    <div v-if="manageSection === 'earnings'">
                        <div v-if="earningsLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
                        <div v-else>
                            <!-- 统计卡片 -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                                    <div class="text-2xl font-bold text-amber-500 flex items-center justify-center gap-1"><PointsIcon :size="22" /> {{ formatEarnings(earningsStats.total_points) }}</div>
                                    <div class="text-xs text-gray-400 mt-1">累计积分收益</div>
                                </div>
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                                    <div class="text-2xl font-bold text-green-600">¥{{ formatEarnings(earningsStats.total_money) }}</div>
                                    <div class="text-xs text-gray-400 mt-1">💰 已结算金额</div>
                                </div>
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                                    <div class="text-2xl font-bold text-blue-600">¥{{ formatEarnings(earningsAccount?.available_balance || 0) }}</div>
                                    <div class="text-xs text-gray-400 mt-1">💳 可提现余额</div>
                                </div>
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 text-center">
                                    <div class="text-2xl font-bold text-gray-500">¥{{ formatEarnings(earningsAccount?.pending_balance || 0) }}</div>
                                    <div class="text-xs text-gray-400 mt-1">⏳ 待结算金额</div>
                                </div>
                            </div>

                            <!-- 提现操作 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">💳 申请提现</h4>
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">提现金额</label>
                                        <el-input-number v-model="withdrawForm.amount" :min="1" :max="earningsAccount?.available_balance || 0" size="small" style="width:140px" />
                                    </div>
                                    <div>
                                        <label class="text-xs text-gray-500 block mb-1">提现方式</label>
                                        <el-select v-model="withdrawForm.channel" size="small" style="width:120px">
                                            <el-option label="🏦 银行卡" value="bank" />
                                            <el-option label="💳 支付宝" value="alipay" />
                                            <el-option label="💚 微信" value="wechat" />
                                        </el-select>
                                    </div>
                                    <div v-if="withdrawForm.channel === 'alipay'">
                                        <label class="text-xs text-gray-500 block mb-1">支付宝账号</label>
                                        <el-input v-model="withdrawForm.alipay_account" placeholder="支付宝账号" size="small" style="width:160px" />
                                    </div>
                                    <div v-if="withdrawForm.channel === 'wechat'">
                                        <label class="text-xs text-gray-500 block mb-1">微信账号</label>
                                        <el-input v-model="withdrawForm.wechat_account" placeholder="微信账号" size="small" style="width:160px" />
                                    </div>
                                    <template v-if="withdrawForm.channel === 'bank'">
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">开户行</label>
                                            <el-input v-model="withdrawForm.bank_name" placeholder="银行名称" size="small" style="width:140px" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">户名</label>
                                            <el-input v-model="withdrawForm.bank_account_name" placeholder="户名" size="small" style="width:120px" />
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 block mb-1">卡号</label>
                                            <el-input v-model="withdrawForm.bank_account_no" placeholder="银行卡号" size="small" style="width:180px" />
                                        </div>
                                    </template>
                                    <el-button size="small" type="primary" :loading="withdrawing" @click="submitWithdrawal" :disabled="!withdrawForm.amount || withdrawForm.amount < 1">
                                        提交提现
                                    </el-button>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-2">📌 平台收取 1% 提现手续费，最低提现 ¥1</p>
                            </div>

                            <!-- 提现记录 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                                <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">📋 提现记录</h4>
                                </div>
                                <div v-if="withdrawalsLoading" class="text-center py-6 text-sm text-gray-400">加载中...</div>
                                <div v-else-if="!withdrawals.length" class="text-center py-8 text-sm text-gray-400">暂无提现记录</div>
                                <div v-else>
                                    <div v-for="w in withdrawals" :key="w.id" class="flex items-center justify-between px-4 py-3 border-b border-gray-50 last:border-0">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                ¥{{ w.amount }}
                                                <span class="text-[10px] text-gray-400 ml-1">(手续费 ¥{{ w.fee }})</span>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-0.5">
                                                {{ { bank: '🏦 银行卡', alipay: '💳 支付宝', wechat: '💚 微信' }[w.channel] || w.channel }}
                                                · {{ formatDate(w.created_at) }}
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded-full"
                                            :class="{
                                                'bg-amber-50 text-amber-600': ['pending_review','pending','processing'].includes(w.status),
                                                'bg-green-50 text-green-600': w.status === 'completed',
                                                'bg-red-50 text-red-500': ['failed','rejected'].includes(w.status),
                                                'bg-gray-50 text-gray-500': w.status === 'cancelled'
                                            }">
                                            {{ { pending_review: '待审核', pending: '处理中', processing: '处理中', completed: '已到账', failed: '失败', rejected: '已驳回', cancelled: '已取消' }[w.status] || w.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- 近期收益明细 -->
                            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mt-4">
                                <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-900">📄 近期收益明细</h4>
                                    <span class="text-xs text-gray-400">共 {{ earningsStats.purchase_count }} 笔</span>
                                </div>
                                <div v-if="!earningsRecent.length" class="text-center py-6 text-sm text-gray-400">暂无收益记录</div>
                                <div v-else>
                                    <div v-for="e in earningsRecent" :key="e.id" class="flex items-center justify-between px-4 py-3 border-b border-gray-50 last:border-0">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm text-gray-900 truncate">{{ e.article_title || '文章' }}</div>
                                            <div class="text-xs text-gray-400 mt-0.5">
                                                👤 {{ e.buyer_name || '匿名' }} · {{ formatDate(e.created_at) }}
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0 ml-3">
                                            <div class="text-sm font-semibold" :class="e.price_type === 'points' ? 'text-amber-500' : 'text-green-600'" style="display:flex;align-items:center;gap:3px">
                                                <PointsIcon v-if="e.price_type === 'points'" :size="16" /> {{ e.price_type === 'points' ? e.net_amount : '¥' + e.net_amount }}
                                            </div>
                                            <div class="text-[10px] text-gray-400">{{ e.price_type === 'points' ? '积分' : '金额' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ── 📡 跨平台分发 ── -->
                    <div v-if="manageSection === 'distribution'">
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                                📡 跨平台分发
                                <span class="px-2 py-0.5 bg-blue-50 text-blue-600 rounded-full text-xs font-medium">{{ platformAccounts.length }} 个平台</span>
                            </h3>
                            <p class="text-xs text-gray-400 mt-1">绑定外部平台账号，一键将文章同步到微信、微博等平台</p>
                        </div>

                        <!-- 平台账号列表 -->
                        <div class="space-y-3 mb-5">
                            <div v-for="pa in platformAccounts" :key="pa.id" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-lg">
                                        {{ { wechat_mp: '💚', weibo: '🔴', zhihu: '🔵', toutiao: '🟠', other: '🌐' }[pa.platform] || '🌐' }}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ pa.platform_user_name || pa.label || pa.platform }}</div>
                                        <div class="text-xs text-gray-400">{{ { wechat_mp: '微信公众号', weibo: '微博', zhihu: '知乎', toutiao: '今日头条', other: '其他' }[pa.platform] || pa.platform }}</div>
                                    </div>
                                </div>
                                <button class="text-xs text-red-500 hover:text-red-600 px-2 py-1" @click="deletePlatformAccount(pa.id)">删除</button>
                            </div>
                            <div v-if="!platformAccounts.length" class="text-center py-8 text-sm text-gray-400 bg-white rounded-xl border border-dashed border-gray-200">
                                尚未绑定任何平台账号
                            </div>
                        </div>

                        <!-- 绑定平台账号 -->
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">🔗 绑定新平台</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">平台</label>
                                    <el-select v-model="paForm.platform" size="small" style="width:100%">
                                        <el-option label="💚 微信公众号" value="wechat_mp" />
                                        <el-option label="🔴 微博" value="weibo" />
                                        <el-option label="🔵 知乎" value="zhihu" />
                                        <el-option label="🟠 今日头条" value="toutiao" />
                                        <el-option label="🌐 其他" value="other" />
                                    </el-select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">平台名称/备注</label>
                                    <el-input v-model="paForm.label" placeholder="如：我的公众号" size="small" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">App ID</label>
                                    <el-input v-model="paForm.app_id" placeholder="应用 App ID" size="small" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">App Secret</label>
                                    <el-input v-model="paForm.app_secret" placeholder="应用密钥" size="small" show-password />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">平台用户 ID</label>
                                    <el-input v-model="paForm.platform_user_id" placeholder="平台上的用户/公众号 ID" size="small" />
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500 block mb-1">平台用户名</label>
                                    <el-input v-model="paForm.platform_user_name" placeholder="平台显示名称" size="small" />
                                </div>
                            </div>
                            <el-button size="small" type="primary" :loading="paSaving" @click="savePlatformAccount">绑定平台</el-button>
                        </div>

                        <!-- 🚀 文章分发 -->
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-5" v-if="platformAccounts.length">
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">🚀 分发文章</h4>
                            <p class="text-xs text-gray-400 mb-3">选择一篇已发布文章，一键同步到已绑定的外部平台</p>
                            <div class="flex items-center gap-3 mb-3">
                                <el-select v-model="distArticleId" placeholder="选择要分发的文章..." size="small" style="flex:1" filterable
                                    :loading="distArticlesLoading" @focus="loadDistributableArticles">
                                    <el-option v-for="art in distArticles" :key="art.id" :label="art.title" :value="art.id">
                                        <span style="display:flex;align-items:center;gap:6px;">
                                            <span style="font-size:13px;">{{ art.title }}</span>
                                            <span style="font-size:10px;color:#909399;">{{ formatDate(art.published_at || art.created_at) }}</span>
                                        </span>
                                    </el-option>
                                </el-select>
                                <el-button size="small" type="primary" :loading="distArticleExecuting" :disabled="!distArticleId" @click="executeDistribute">
                                    📡 一键分发
                                </el-button>
                            </div>
                            <!-- 分发目标平台预览 -->
                            <div v-if="distArticleId" class="flex flex-wrap gap-2">
                                <el-tag v-for="pa in platformAccounts" :key="pa.id" size="small" :type="pa._distStatus === 'success' ? 'success' : pa._distStatus === 'failed' ? 'danger' : pa._distStatus === 'distributing' ? 'warning' : 'info'">
                                    {{ { wechat_mp: '💚', weibo: '🔴', zhihu: '🔵', toutiao: '🟠', other: '🌐' }[pa.platform] || '🌐' }}
                                    {{ pa.platform_user_name || pa.label || pa.platform }}
                                    {{ pa._distStatus === 'success' ? '✅' : pa._distStatus === 'failed' ? '❌' : pa._distStatus === 'distributing' ? '🔄' : '' }}
                                </el-tag>
                            </div>
                        </div>

                        <!-- 文章分发列表 -->
                        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-50 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-gray-900">📤 分发记录</h4>
                            </div>
                            <div v-if="!distributions.length" class="text-center py-6 text-sm text-gray-400">暂无分发记录</div>
                            <div v-else>
                                <div v-for="d in distributions" :key="d.id" class="flex items-center justify-between px-4 py-3 border-b border-gray-50 last:border-0">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm text-gray-900 truncate">
                                            {{ { wechat_mp: '💚 微信', weibo: '🔴 微博', zhihu: '🔵 知乎', toutiao: '🟠 头条', other: '🌐 其他' }[d.platform] || d.platform }}
                                            · {{ d.article?.title || '文章 #' + d.article_id }}
                                        </div>
                                        <div class="text-xs text-gray-400 mt-0.5">{{ formatDate(d.created_at) }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a v-if="d.external_url" :href="d.external_url" target="_blank" class="text-xs text-primary-500 hover:underline">查看</a>
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded-full"
                                            :class="{
                                                'bg-green-50 text-green-600': d.status === 'success',
                                                'bg-amber-50 text-amber-600': d.status === 'pending',
                                                'bg-red-50 text-red-500': d.status === 'failed'
                                            }">
                                            {{ { success: '已分发', pending: '分发中', failed: '失败' }[d.status] || d.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 阅读分析对话框 ── -->
        <el-dialog v-model="showArticleStatsDialog" title="📊 阅读分析" width="600px" top="15vh" :close-on-click-modal="false">
            <div v-if="articleStatsLoading" class="text-center py-8 text-sm text-gray-400">加载中...</div>
            <div v-else-if="articleStatsData">
                <!-- 概要 -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-lg font-bold text-gray-800">{{ articleStatsData.total_reads || 0 }}</div>
                        <div class="text-[10px] text-gray-400 mt-1">总阅读</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-lg font-bold text-blue-600">{{ articleStatsData.avg_read_duration || 0 }}s</div>
                        <div class="text-[10px] text-gray-400 mt-1">平均阅读时长</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-lg font-bold text-green-600">{{ articleStatsData.completion_rate || 0 }}%</div>
                        <div class="text-[10px] text-gray-400 mt-1">完读率</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <div class="text-lg font-bold text-amber-500">{{ articleStatsData.completed_count || 0 }}</div>
                        <div class="text-[10px] text-gray-400 mt-1">读完人数</div>
                    </div>
                </div>

                <!-- 滚动深度留存曲线 -->
                <div class="mb-5">
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">📉 滚动深度分布</h4>
                    <div class="space-y-2">
                        <div v-for="(count, label) in articleStatsData.scroll_distribution" :key="label" class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 w-16 flex-shrink-0">{{ label }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                    :style="{ width: barPct(count, articleStatsData.scroll_distribution) + '%' }"
                                    :class="barColor(label)"></div>
                            </div>
                            <span class="text-xs text-gray-400 w-10 text-right flex-shrink-0">{{ count }}</span>
                        </div>
                    </div>
                </div>

                <!-- 阅读时长分布 -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-800 mb-3">⏱️ 阅读时长分布</h4>
                    <div class="space-y-2">
                        <div v-for="(count, label) in articleStatsData.duration_distribution" :key="label" class="flex items-center gap-3">
                            <span class="text-xs text-gray-500 w-16 flex-shrink-0">{{ label }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500 bg-primary-400"
                                    :style="{ width: barPct(count, articleStatsData.duration_distribution) + '%' }"></div>
                            </div>
                            <span class="text-xs text-gray-400 w-10 text-right flex-shrink-0">{{ count }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <el-button size="small" @click="showArticleStatsDialog = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- ── 创建/编辑互物号对话框 ── -->
        <el-dialog v-model="showCreateDialog" :title="editingChannel ? '✏️ 编辑互物号' : '➕ 创建互物号'" width="500px" top="15vh" @close="resetChannelForm">
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">名称 *</label>
                    <el-input v-model="channelForm.name" placeholder="互物号名称" maxlength="30" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">简介</label>
                    <el-input v-model="channelForm.description" type="textarea" :rows="3" placeholder="介绍一下你的互物号..." maxlength="500" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">分类</label>
                    <el-select v-model="channelForm.category_id" placeholder="选择分类" class="!w-full">
                        <el-option v-for="cat in categories.filter(c => c.value !== 'all')" :key="cat.value" :label="cat.label" :value="cat.value" />
                    </el-select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">头像</label>
                    <el-upload :auto-upload="false" :on-change="onAvatarSelect" accept="image/*" :show-file-list="false">
                        <div class="flex items-center gap-3">
                            <div class="w-14 h-14 rounded-xl bg-gray-50 flex items-center justify-center text-2xl overflow-hidden border">
                                <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" />
                                <span v-else>📷</span>
                            </div>
                            <span class="text-sm text-gray-500">点击上传头像</span>
                        </div>
                    </el-upload>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">封面横幅</label>
                    <el-upload :auto-upload="false" :on-change="onCoverSelect" accept="image/*" :show-file-list="false">
                        <div class="flex items-center gap-3">
                            <div class="w-36 h-20 rounded-xl bg-gray-50 flex items-center justify-center overflow-hidden border bg-cover bg-center"
                                :style="coverPreview ? { backgroundImage: `url(${coverPreview})` } : {}">
                                <span v-if="!coverPreview" class="text-2xl">🖼️</span>
                            </div>
                            <span class="text-sm text-gray-500">点击上传封面图（建议比例 3:1）</span>
                        </div>
                    </el-upload>
                </div>
            </div>
            <template #footer>
                <el-button @click="resetChannelForm(); showCreateDialog = false">取消</el-button>
                <el-button type="primary" :loading="savingChannel" @click="saveChannel">{{ editingChannel ? '保存' : '创建' }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 申请解封对话框 ── -->
        <el-dialog v-model="showAppealDialog" title="🔓 申请解封互物号" width="480px" top="25vh">
            <div class="space-y-4">
                <div class="bg-orange-50 border border-orange-100 rounded-lg p-3 text-sm text-orange-700">
                    您的互物号「{{ appealChannelName }}」已被管理员禁用。请填写申请解封的原因，管理员审核通过后将恢复使用。
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">申请原因 *</label>
                    <el-input v-model="appealReason" type="textarea" :rows="4" placeholder="请详细说明申请解封的原因..." maxlength="500" />
                </div>
            </div>
            <template #footer>
                <el-button @click="showAppealDialog = false">取消</el-button>
                <el-button type="primary" :loading="appealing" @click="submitAppeal">提交申请</el-button>
            </template>
        </el-dialog>

        <!-- ── 申请认证对话框 ── -->
        <el-dialog v-model="showVerifyDialog" title="申请认证互物号" width="480px" top="25vh">
            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-100 rounded-lg p-3 text-sm text-blue-700">
                    申请认证后，您的互物号将显示 ✓ 认证徽章，提升可信度。请填写认证信息，管理员审核通过后即可获得认证。
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">认证类型 *</label>
                    <el-select v-model="verifyType" class="!w-full" placeholder="选择认证类型">
                        <el-option label="企业认证" value="enterprise" />
                        <el-option label="个人认证" value="personal" />
                    </el-select>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">{{ verifyType === 'enterprise' ? '企业名称' : '真实姓名' }} *</label>
                    <el-input v-model="verifyName" :placeholder="verifyType === 'enterprise' ? '请输入企业名称' : '请输入真实姓名'" maxlength="100" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">认证说明 *</label>
                    <el-input v-model="verifyReason" type="textarea" :rows="3" placeholder="请说明认证理由..." maxlength="500" />
                </div>
            </div>
            <template #footer>
                <el-button @click="showVerifyDialog = false">取消</el-button>
                <el-button type="primary" :loading="verifySubmitting" @click="submitVerify">提交申请</el-button>
            </template>
        </el-dialog>

        <!-- ── 自动回复对话框 ── -->
        <el-dialog v-model="showAutoReplyDialogVisible" :title="editingAutoReply ? '✏️ 编辑自动回复' : '➕ 添加自动回复'" width="480px" top="15vh">
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">类型 *</label>
                    <el-select v-model="autoReplyForm.type" class="!w-full" placeholder="选择类型">
                        <el-option label="关注回复（关注时自动发送）" value="welcome" />
                        <el-option label="关键词回复（匹配关键词时触发）" value="keyword" />
                        <el-option label="默认回复（无匹配时使用）" value="default" />
                    </el-select>
                </div>
                <div v-if="autoReplyForm.type === 'keyword'">
                    <label class="text-sm font-medium text-gray-700 block mb-1">关键词 *</label>
                    <el-input v-model="autoReplyForm.keyword" placeholder="输入关键词" maxlength="100" />
                    <div class="flex items-center gap-2 mt-1">
                        <el-switch v-model="autoReplyForm.match_type" :active-value="1" :inactive-value="0" size="small" />
                        <span class="text-xs text-gray-400">{{ autoReplyForm.match_type ? '模糊匹配（包含关键词）' : '精确匹配（完全匹配）' }}</span>
                    </div>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">回复内容 *</label>
                    <el-input v-model="autoReplyForm.content" type="textarea" :rows="3" placeholder="输入自动回复内容" maxlength="1000" />
                </div>
                <div class="flex items-center gap-2">
                    <el-switch v-model="autoReplyForm.is_active" :active-value="true" :inactive-value="false" />
                    <span class="text-sm text-gray-500">启用</span>
                </div>
            </div>
            <template #footer>
                <el-button @click="showAutoReplyDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="savingAutoReply" @click="saveAutoReply">{{ editingAutoReply ? '保存' : '创建' }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 菜单编辑对话框 ── -->
        <el-dialog v-model="showMenuDialogVisible" :title="editingMenu ? '✏️ 编辑菜单项' : '➕ 添加菜单项'" width="420px" top="15vh">
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">名称 *</label>
                    <el-input v-model="menuForm.name" placeholder="菜单名称" maxlength="40" />
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-700 block mb-1">类型</label>
                    <el-select v-model="menuForm.type" class="!w-full">
                        <el-option label="点击推事件（click）" value="click" />
                        <el-option label="跳转URL（view）" value="view" />
                        <el-option label="小程序（miniprogram）" value="miniprogram" />
                    </el-select>
                </div>
                <div v-if="menuForm.type === 'click'">
                    <label class="text-sm font-medium text-gray-700 block mb-1">Key</label>
                    <el-input v-model="menuForm.key" placeholder="事件Key，用于识别点击" maxlength="128" />
                </div>
                <div v-if="menuForm.type === 'view'">
                    <label class="text-sm font-medium text-gray-700 block mb-1">URL</label>
                    <el-input v-model="menuForm.key" placeholder="跳转链接" maxlength="128" />
                </div>
                <div v-if="menuForm.type === 'miniprogram'">
                    <label class="text-sm font-medium text-gray-700 block mb-1">AppID</label>
                    <el-input v-model="menuForm.app_id" placeholder="小程序AppID" maxlength="50" />
                    <label class="text-sm font-medium text-gray-700 block mb-1 mt-2">页面路径</label>
                    <el-input v-model="menuForm.page_path" placeholder="小程序页面路径" maxlength="255" />
                </div>
            </div>
            <template #footer>
                <el-button @click="showMenuDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="savingMenu" @click="saveMenu">{{ editingMenu ? '保存' : '创建' }}</el-button>
            </template>
        </el-dialog>

        <!-- ── 移入合集对话框 ── -->
        <el-dialog v-model="showSetCollectionDialog" title="📚 移入合集" width="400px" top="20vh">
            <div class="space-y-3">
                <div class="text-sm text-gray-500 mb-2">
                    将文章「<strong class="text-gray-800">{{ setCollectionArticle?.title }}</strong>」移入合集：
                </div>
                <el-select v-model="selectedCollectionId" placeholder="选择合集（留空即移出合集）" class="!w-full" clearable>
                    <el-option v-for="c in setCollectionAccountCollections" :key="c.id" :label="`${c.name}（${c.articles_count || 0}篇）`" :value="c.id" />
                </el-select>
                <p class="text-xs text-gray-400">选择「清空」将从当前合集中移出文章</p>
            </div>
            <template #footer>
                <el-button size="small" @click="showSetCollectionDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="savingSetCollection" @click="saveArticleCollection">确定</el-button>
            </template>
        </el-dialog>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PointsIcon from '@/components/PointsIcon.vue';
import { ArrowDown, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/api/client.js'

const route = useRoute()
const router = useRouter()
const isLoggedIn = ref(!!localStorage.getItem('auth_token'))
const searchQuery = ref('')
const channels = ref([])
const loading = ref(false)
const activeTab = ref('articles')
const activeCategory = ref('all')

const tabs = computed(() => {
    const list = [
        { key: 'articles', label: '📄 文章' },
        { key: 'discover', icon: `<svg t="1783301043544" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="72163" width="16" height="16" style="vertical-align:middle"><path d="M911.8 839.6L750.9 678.7c120.1-160.6 87.2-388.1-73.3-508.2s-388.1-87.2-508.2 73.3c-46.9 62.8-72.3 139-72.3 217.4 0 200.5 162.5 363.1 363.1 363.1 78.4 0 154.6-25.4 217.4-72.3l160.8 160.8c20.2 20.3 53.1 20.3 73.4 0.1 20.2-20.2 20.2-53.1 0-73.3z m-451.7-119c-143.2 2-260.9-112.4-263-255.6-2-143.2 112.4-260.9 255.6-263h7.3c143.2 0 259.3 116.1 259.3 259.3S603.4 720.6 460.1 720.6z" p-id="72164" fill="#2c2c2c"></path></svg>`, label: '发现' },
    ]
    if (isLoggedIn.value) {
        list.push({ key: 'subscribed', icon: `<svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="16" height="16" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg>`, label: '已关注' })
        list.push({ key: 'manage', label: '⚙️ 管理' })
    }
    return list
})

const categories = ref([])
const categoriesLoading = ref(false)

// 频道详情
const selectedChannel = ref(null)
const articles = ref([])
const articlesLoading = ref(false)
const articlePage = ref(1)
const articleHasMore = ref(false)
const articleLoadingMore = ref(false)
const articleSearch = ref('')
const articleSearchTimer = ref(null)
const totalArticles = ref(0)

// 创建/编辑
const showCreateDialog = ref(false)
const showAppealDialog = ref(false)
const appealChannelId = ref(null)
const appealChannelName = ref('')
const appealReason = ref('')
const appealing = ref(false)
const showVerifyDialog = ref(false)
const verifyChannelId = ref(null)
const verifyType = ref('enterprise')
const verifyName = ref('')
const verifyReason = ref('')
const verifySubmitting = ref(false)
const editingChannel = ref(null)
const savingChannel = ref(false)
const channelForm = ref({ name: '', description: '', category_id: '' })
const avatarFile = ref(null)
const avatarPreview = ref('')
const coverFile = ref(null)
const coverPreview = ref('')

// 我的管理
const myChannels = ref([])
const mySubmissions = ref([])
const topArticles = ref([])
const pendingSubmissions = ref([])
const pendingCount = computed(() => pendingSubmissions.value.length)

// 迷你趋势图
const trendChartId = ref(null)

// 自动回复
const showAutoReplies = ref(false)
const autoReplies = ref([])
const autoRepliesLoading = ref(false)
const showAutoReplyDialogVisible = ref(false)
const editingAutoReply = ref(null)
const savingAutoReply = ref(false)
const autoReplyForm = ref({ type: 'keyword', keyword: '', match_type: 0, content: '', is_active: true })
const autoReplyAccountId = ref(null)

// 自定义菜单
const showMenus = ref(false)
const menus = ref([])
const menusLoading = ref(false)
const showMenuDialogVisible = ref(false)
const editingMenu = ref(null)
const savingMenu = ref(false)
const menuForm = ref({ name: '', type: 'click', key: '', app_id: '', page_path: '' })
const menuParentId = ref(null)
const menuAccountId = ref(null)

// 素材库
const showMaterials = ref(false)
const materials = ref([])
const materialsLoading = ref(false)
const materialFile = ref(null)
const uploadingMaterial = ref(false)
const materialAccountId = ref(null)

// 管理面板当前功能分区
const manageSection = ref('overview')
// 数据概览
const dashboardAccountId = ref(null)
const articleManageAccountId = ref(null)
const articleStatusFilter = ref('all')
const articleStatusFilters = [
    { key: 'all', label: '全部' },
    { key: 'published', label: '已发布' },
    { key: 'draft', label: '草稿' },
    { key: 'pending', label: '待审核' },
]
const dashData = ref(null)
const dashboardLoading = ref(false)

async function loadManageArticles() {
    if (!myChannels.value.length) return
    const accountId = articleManageAccountId.value || myChannels.value[0]?.id
    if (!accountId) return
    articlesLoading.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const params = { per_page: 50 }
        if (articleStatusFilter.value !== 'all') params.status = articleStatusFilter.value
        const res = await apiClient.get(`/official-accounts/${accountId}/articles`, { params, headers: h })
        const body = res.data
        const list = body?.data || body?.data?.data || []
        articles.value = list
        totalArticles.value = body?.meta?.total || list.length
    } catch { articles.value = [] }
    finally { articlesLoading.value = false }
}
async function loadDashboard() {
    const id = dashboardAccountId.value
    if (!id) { dashData.value = null; return }
    dashboardLoading.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${id}/dashboard`, { headers: h })
        dashData.value = res.data?.data || null
    } catch { dashData.value = null }
    finally { dashboardLoading.value = false }
}
const manageNavGroups = [
    { label: '运营数据', items: [
        { key: 'overview', icon: '📈', label: '数据概览' },
        { key: 'pending', icon: '🔔', label: '投稿审核' },
    ]},
    { label: '内容管理', items: [
        { key: 'articles', icon: '📝', label: '文章管理' },
        { key: 'menus', icon: '📋', label: '自定义菜单' },
    ]},
    { label: '互动运营', items: [
        { key: 'comments', icon: '💬', label: '评论管理' },
        { key: 'followers', icon: `<svg t="1783225465916" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46732" width="16" height="16" style="vertical-align:middle"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z" fill="#2c2c2c" p-id="46733"></path></svg>`, label: '关注者列表' },
        { key: 'follower-tags', icon: '🏷️', label: '粉丝标签' },
    ]},
    { label: '系统设置', items: [
        { key: 'autoreply', icon: '🤖', label: '自动回复' },
        { key: 'materials', icon: '🖼️', label: '素材库' },
        { key: 'earnings', icon: '💰', label: '收益提现' },
        { key: 'distribution', icon: '📡', label: '跨平台分发' },
    ]},
]

// 文章移入合集对话框
const showSetCollectionDialog = ref(false)
const setCollectionArticle = ref(null)
const setCollectionAccountCollections = ref([])
const selectedCollectionId = ref(null)
const savingSetCollection = ref(false)

// ── 阅读分析 ──
const showArticleStatsDialog = ref(false)
const articleStatsLoading = ref(false)
const articleStatsData = ref(null)

// 评论管理
const showComments = ref(false)
const comments = ref([])
const commentsLoading = ref(false)
const selectedCommentAccountId = ref(null)

// 关注者列表
const showFollowers = ref(false)
const followers = ref([])
const followersLoading = ref(false)
const followersLoadingMore = ref(false)
const followerPage = ref(1)
const followerHasMore = ref(false)
const followerTotal = ref(0)
const followerAccountId = ref(null)
const followerSearch = ref('')
const followerSearchTimer = ref(null)

// ── 粉丝标签系统 ──
const followerTags = ref([])
const followerTagsLoading = ref(false)
const tagAccountId = ref(null)
const showCreateTagDialog = ref(false)
const editingTag = ref(null)
const savingTag = ref(false)
const tagForm = ref({ name: '', color: '#409eff' })
const tagColors = ['#409eff', '#67c23a', '#e6a23c', '#f56c6c', '#909399', '#722ed1', '#eb2f96', '#13c2c2', '#fa8c16']

// ── 收益提现 ──
const earningsStats = ref({ total_points: 0, total_money: 0, pending_money: 0, purchase_count: 0, earnings_account: null })
const earningsAccount = computed(() => earningsStats.value?.earnings_account || null)
const earningsRecent = ref([])
const earningsLoading = ref(false)
const withdrawals = ref([])
const withdrawalsLoading = ref(false)
const withdrawing = ref(false)
const withdrawForm = ref({ amount: null, channel: 'alipay', alipay_account: '', wechat_account: '', bank_name: '', bank_account_name: '', bank_account_no: '' })

const showAssignTagDialog = ref(false)
const assignTagFollowerId = ref(null)
const assignTagFollowerName = ref('')
const assignTagIds = ref([])
const availableTags = ref([])
const savingAssignTag = ref(false)

// ── 跨平台分发 ──
const platformAccounts = ref([])
const distributions = ref([])
const paSaving = ref(false)
const paForm = ref({ platform: 'wechat_mp', label: '', app_id: '', app_secret: '', platform_user_id: '', platform_user_name: '' })
// 文章分发
const distArticleId = ref(null)
const distArticles = ref([])
const distArticlesLoading = ref(false)
const distArticleExecuting = ref(false)

// 全部文章流
const feedArticles = ref([])
const feedLoading = ref(false)
const feedLoadingMore = ref(false)
const feedPage = ref(1)
const feedHasMore = ref(false)
const feedTotal = ref(0)
const feedSearch = ref('')
const feedSearchTimer = ref(null)
const feedSort = ref(localStorage.getItem('auth_token') ? 'ai' : 'latest')
const sortOptions = [
    { value: 'ai', label: 'AI 推荐' },
    { value: 'sequence', label: '序列预测' },
    { value: 'recommended', label: '综合推荐' },
    { value: 'latest', label: '最新' },
    { value: 'hot', label: '最热' },
    { value: 'trending', label: '本周热门' },
]

// ── 标签云 ──
const popularTags = ref([])
const selectedFeedTag = ref('')

function formatDate(t) {
    if (!t) return ''
    return new Date(t).toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' })
}
function goLogin() {
    window.location.href = '/build/login'
}
function formatNum(n) {
    if (!n) return '0'
    if (n >= 10000) return (n / 10000).toFixed(1) + 'w'
    if (n >= 1000) return (n / 1000).toFixed(1) + 'k'
    return String(n)
}
function barHeight(count, trend) {
    const max = Math.max(...trend.map(d => d.count), 1)
    return (count / max) * 40
}
function toggleTrendChart(id) {
    trendChartId.value = trendChartId.value === id ? null : id
}

function switchToSubscribed() {
    activeTab.value = 'subscribed'
    activeCategory.value = 'all'
    searchQuery.value = ''
    loadChannels()
}

let unifiedSearchTimer = null
function onUnifiedSearch() {
    clearTimeout(unifiedSearchTimer)
    unifiedSearchTimer = setTimeout(() => {
        if (activeTab.value === 'articles') {
            feedPage.value = 1
            feedHasMore.value = false
            loadFeed(1)
        } else {
            loadChannels()
        }
    }, 300)
}
function onUnifiedClear() {
    searchQuery.value = ''
    if (activeTab.value === 'articles') {
        feedPage.value = 1
        loadFeed(1)
    } else {
        loadChannels()
    }
}
function switchTab(key) {
    activeTab.value = key
    activeCategory.value = 'all'
    searchQuery.value = ''
    feedPage.value = 1
    feedHasMore.value = false
    if (key === 'manage') { loadMyData() }
    else if (key === 'articles') { loadFeed(1); loadPopularTags() }
    else if (key === 'discover' || key === 'subscribed') { loadChannels() }
}

async function loadChannels() {
    loading.value = true
    try {
        const headers = isLoggedIn.value ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
        const params = { per_page: 30 }
        if (searchQuery.value) params.q = searchQuery.value
        if (activeCategory.value !== 'all') params.category = activeCategory.value

        let endpoint = '/official-accounts'
        if (activeTab.value === 'subscribed') {
            endpoint = '/official-accounts/my'
        }
        if (!isLoggedIn.value) {
            endpoint = '/official-accounts/public'
        }

        const res = await apiClient.get(endpoint, { params, headers })
        channels.value = res.data?.data?.data || res.data?.data || []
    } catch (err) {
        // Token 过期 → 降级到公开端点
        if (err?.response?.status === 401) {
            isLoggedIn.value = false
            localStorage.removeItem('auth_token')
            try {
                const res = await apiClient.get('/official-accounts/public', { params })
                channels.value = res.data?.data?.data || res.data?.data || []
            } catch { channels.value = [] }
        } else {
            channels.value = []
        }
    }
    finally { loading.value = false }
}

// ── 全部文章流 ──
async function loadFeed(page = 1, append = false) {
    if (!append) feedLoading.value = true
    try {
        const params = { per_page: 15, page, sort: feedSort.value }
        if (searchQuery.value) params.q = searchQuery.value
        if (selectedFeedTag.value) params.tag = selectedFeedTag.value
        const res = await apiClient.get('/official-accounts/public/articles', { params })
        const body = res.data
        const list = body?.data || []
        const meta = body?.meta
        if (append) {
            feedArticles.value = [...feedArticles.value, ...list]
        } else {
            feedArticles.value = list
        }
        feedTotal.value = meta?.total || list.length
        feedHasMore.value = meta ? (meta.current_page < meta.last_page) : false
        feedPage.value = page
    } catch { if (!append) feedArticles.value = [] }
    finally {
        feedLoading.value = false
        feedLoadingMore.value = false
    }
}

function onFeedSearch() {
    if (feedSearchTimer.value) clearTimeout(feedSearchTimer.value)
    feedSearchTimer.value = setTimeout(() => {
        feedPage.value = 1
        feedHasMore.value = false
        loadFeed(1)
    }, 400)
}

async function loadMoreFeed() {
    if (feedLoadingMore.value || !feedHasMore.value) return
    feedLoadingMore.value = true
    await loadFeed(feedPage.value + 1, true)
}


// ── 热门标签 ──
async function loadPopularTags() {
    try {
        const res = await apiClient.get('/official-accounts/public/popular-tags')
        popularTags.value = res.data?.data || []
    } catch {
        popularTags.value = []
    }
}

function onTagFilterClick(tag) {
    selectedFeedTag.value = selectedFeedTag.value === tag ? '' : tag
    feedPage.value = 1
    feedHasMore.value = false
    loadFeed(1)
}

async function toggleFollow(ch) {
    if (!isLoggedIn.value) { window.location.href = '/build/login'; return }
    try {
        if (ch.is_following) {
            await apiClient.post(`/official-accounts/${ch.id}/unfollow`, {}, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            ch.is_following = false
            ch.followers_count = Math.max(0, (ch.followers_count || 1) - 1)
        } else {
            await apiClient.post(`/official-accounts/${ch.id}/follow`, {}, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            ch.is_following = true
            ch.followers_count = (ch.followers_count || 0) + 1
        }
    } catch (err) {
        if (err?.response?.status === 401) {
            isLoggedIn.value = false
            localStorage.removeItem('auth_token')
            window.location.href = '/build/login'
        }
    }
}

function viewChannelDetail(ch) {
    selectedChannel.value = ch
    articlePage.value = 1
    articleHasMore.value = false
    articleSearch.value = ''
    loadArticles(ch.id, 1)
    loadChannelCollections(ch.id)
}

async function loadChannelCollections(channelId) {
    channelCollectionsLoading.value = true
    try {
        const headers = isLoggedIn.value ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
        const res = await apiClient.get(`/official-accounts/public/${channelId}/collections`, { headers })
        channelCollections.value = res.data?.data || []
    } catch {
        channelCollections.value = []
    } finally {
        channelCollectionsLoading.value = false
    }
}

async function loadArticles(channelId, page = 1, append = false) {
    if (!append) articlesLoading.value = true
    try {
        const headers = isLoggedIn.value ? { Authorization: 'Bearer ' + localStorage.getItem('auth_token') } : {}
        const params = { per_page: 10, page }
        if (articleSearch.value) params.q = articleSearch.value
        const res = await apiClient.get(`/official-accounts/public/${channelId}/articles`, { params, headers })
        const body = res.data
        const list = body?.data || body?.data?.data || []
        const meta = body?.meta
        if (append) {
            articles.value = [...articles.value, ...list]
        } else {
            articles.value = list
        }
        totalArticles.value = meta?.total || articles.value.length
        articleHasMore.value = meta ? (meta.current_page < meta.last_page) : false
        articlePage.value = page
    } catch { if (!append) articles.value = [] }
    finally {
        articlesLoading.value = false
        articleLoadingMore.value = false
    }
}

async function loadMoreArticles() {
    if (articleLoadingMore.value || !articleHasMore.value) return
    articleLoadingMore.value = true
    await loadArticles(selectedChannel.value?.id, articlePage.value + 1, true)
}

function onArticleSearch() {
    if (articleSearchTimer.value) clearTimeout(articleSearchTimer.value)
    articleSearchTimer.value = setTimeout(() => {
        if (selectedChannel.value) {
            articlePage.value = 1
            articleHasMore.value = false
            loadArticles(selectedChannel.value.id, 1)
        }
    }, 400)
}

async function viewArticle(art) {
    window.open(`/build/oa-article/${art.id}`, '_blank')
}

// ── 文章快速管理（置顶/删除） ──
async function handleArticleAction(cmd, art) {
    if (!selectedChannel.value?.is_owner) return
    const headers = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    if (cmd === 'pin') {
        try {
            const res = await apiClient.post(`/official-accounts/articles/${art.id}/pin`, {}, { headers })
            art.is_pinned = res.data?.data?.is_pinned ?? !art.is_pinned
            ElMessage.success(art.is_pinned ? '已置顶' : '已取消置顶')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '操作失败')
        }
    } else if (cmd === 'delete') {
        try {
            await ElMessageBox.confirm('确定要删除文章「' + art.title + '」吗？删除后不可恢复。', '确认删除', {
                confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
            })
            await apiClient.delete(`/official-accounts/articles/${art.id}`, { headers })
            articles.value = articles.value.filter(a => a.id !== art.id)
            totalArticles.value = Math.max(0, totalArticles.value - 1)
            ElMessage.success('已删除')
        } catch (e) {
            if (e !== 'cancel' && e !== 'close') {
                ElMessage.error(e.response?.data?.message || '删除失败')
            }
        }
    } else if (cmd === 'edit') {
        window.open(`/build/oa-editor?id=${art.id}&account_id=${selectedChannel.value?.id}`, '_blank')
    } else if (cmd === 'collection') {
        await showSetCollectionDialogForArticle(art)
    }
}

// ── 文章管理：置顶/编辑/删除 ──
async function handleManageArticleAction(cmd, art) {
    const headers = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    if (cmd === 'pin') {
        try {
            const res = await apiClient.post(`/official-accounts/articles/${art.id}/pin`, {}, { headers })
            art.is_pinned = res.data?.data?.is_pinned ?? !art.is_pinned
            ElMessage.success(art.is_pinned ? '已置顶' : '已取消置顶')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '操作失败')
        }
    } else if (cmd === 'delete') {
        try {
            await ElMessageBox.confirm('确定要删除文章「' + art.title + '」吗？删除后不可恢复。', '确认删除', {
                confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
            })
            await apiClient.delete(`/official-accounts/articles/${art.id}`, { headers })
            articles.value = articles.value.filter(a => a.id !== art.id)
            totalArticles.value = Math.max(0, totalArticles.value - 1)
            ElMessage.success('已删除')
        } catch (e) {
            if (e !== 'cancel' && e !== 'close') {
                ElMessage.error(e.response?.data?.message || '删除失败')
            }
        }
    } else if (cmd === 'edit') {
        window.open(`/build/oa-editor?id=${art.id}&account_id=${articleManageAccountId.value}`, '_blank')
    }
}

// ── 文章管理：发布/下架 ──
async function togglePublishArticle(art) {
    const headers = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.post(`/official-accounts/articles/${art.id}/toggle-status`, {}, { headers })
        art.status = res.data?.data?.status
        ElMessage.success(art.status === 'published' ? '已发布' : '已下架')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ── 投稿审核 ──
async function reviewSubmission(subId, action) {
    const headers = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.post(`/official-accounts/submissions/${subId}/review`, { action }, { headers })
        ElMessage.success(action === 'approve' ? '已通过，文章已发布' : '已驳回')
        pendingSubmissions.value = pendingSubmissions.value.filter(s => s.id !== subId)
        // 刷新我的投稿列表
        loadMyData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function loadPendingSubmissions() {
    if (!isLoggedIn.value) return
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    const all = []
    for (const ch of myChannels.value) {
        try {
            const res = await apiClient.get(`/official-accounts/${ch.id}/submissions/pending`, { headers })
            const subs = res.data?.data?.submissions || []
            all.push(...subs)
        } catch { /* skip */ }
    }
    pendingSubmissions.value = all
}

// ── 评论管理 ──
async function loadComments() {
    if (!myChannels.value.length) return
    commentsLoading.value = true
    comments.value = []
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    // 从第一个互物号加载评论（或全部汇总）
    const accountId = selectedCommentAccountId.value || myChannels.value[0]?.id
    if (!accountId) { commentsLoading.value = false; return }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/comments`, { headers: h })
        comments.value = res.data?.data?.data || res.data?.data || []
    } catch { /* ignore */ }
    finally { commentsLoading.value = false }
}

async function approveComment(commentId) {
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.post(`/official-accounts/comments/${commentId}/approve`, {}, { headers: h })
        const c = comments.value.find(c => c.id === commentId)
        if (c) c.status = 'approved'
        ElMessage.success('评论已通过')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function rejectComment(commentId) {
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.post(`/official-accounts/comments/${commentId}/reject`, {}, { headers: h })
        const c = comments.value.find(c => c.id === commentId)
        if (c) c.status = 'rejected'
        ElMessage.success('评论已驳回')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function deleteComment(commentId) {
    try {
        await ElMessageBox.confirm('确定要删除此评论吗？', '确认删除', {
            confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
        })
        const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        await apiClient.delete(`/official-accounts/comments/${commentId}`, { headers: h })
        comments.value = comments.value.filter(c => c.id !== commentId)
        ElMessage.success('已删除')
    } catch (e) {
        if (e !== 'cancel' && e !== 'close') {
            ElMessage.error(e.response?.data?.message || '删除失败')
        }
    }
}

// ── 关注者列表 ──
async function loadFollowers(page = 1, append = false) {
    if (!myChannels.value.length) return
    if (!append) followersLoading.value = true
    const accountId = followerAccountId.value || myChannels.value[0]?.id
    if (!accountId) { followersLoading.value = false; return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const params = { per_page: 10, page }
        if (followerSearch.value) params.q = followerSearch.value
        const res = await apiClient.get(`/official-accounts/${accountId}/followers`, { params, headers: h })
        const body = res.data
        const meta = body?.meta
        const list = body?.data || []
        if (append) {
            followers.value = [...followers.value, ...list]
        } else {
            followers.value = list
        }
        followerTotal.value = meta?.total || list.length
        followerHasMore.value = meta ? (meta.current_page < meta.last_page) : false
        followerPage.value = page
        // 加载每个粉丝的标签
        loadFollowersTags(list)
    } catch { if (!append) followers.value = [] }
    finally {
        followersLoading.value = false
        followersLoadingMore.value = false
    }
}

async function loadFollowersTags(followerList) {
    if (!followerList?.length) return
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    for (const f of followerList) {
        try {
            const res = await apiClient.get(`/official-accounts/follower-tags/${f.id}/relations`, { headers: h })
            f.tags = res.data?.data || []
        } catch { f.tags = [] }
    }
}

function onFollowerSearch() {
    if (followerSearchTimer.value) clearTimeout(followerSearchTimer.value)
    followerSearchTimer.value = setTimeout(() => {
        followerPage.value = 1
        loadFollowers(1)
    }, 400)
}

async function loadMoreFollowers() {
    if (followersLoadingMore.value || !followerHasMore.value) return
    followersLoadingMore.value = true
    await loadFollowers(followerPage.value + 1, true)
}

// ── 粉丝标签系统 ──
async function loadFollowerTags() {
    if (!isLoggedIn.value || !myChannels.value.length) return
    followerTagsLoading.value = true
    const accountId = tagAccountId.value || myChannels.value[0]?.id
    if (!accountId) { followerTagsLoading.value = false; return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/follower-tags`, { headers: h })
        followerTags.value = res.data?.data || []
    } catch { followerTags.value = [] }
    finally { followerTagsLoading.value = false }
}

function editFollowerTag(tag) {
    editingTag.value = tag
    tagForm.value = { name: tag.name, color: tag.color || '#409eff' }
    showCreateTagDialog.value = true
}

async function saveFollowerTag() {
    if (!tagForm.value.name.trim()) { ElMessage.warning('请输入标签名称'); return }
    savingTag.value = true
    const accountId = tagAccountId.value || myChannels.value[0]?.id
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        if (editingTag.value) {
            await apiClient.put(`/official-accounts/follower-tags/${editingTag.value.id}`, tagForm.value, { headers: h })
            ElMessage.success('标签已更新')
        } else {
            await apiClient.post(`/official-accounts/${accountId}/follower-tags`, tagForm.value, { headers: h })
            ElMessage.success('标签已创建')
        }
        showCreateTagDialog.value = false
        editingTag.value = null
        tagForm.value = { name: '', color: '#409eff' }
        await loadFollowerTags()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally { savingTag.value = false }
}

async function deleteFollowerTag(tag) {
    try {
        await ElMessageBox.confirm(`确定要删除标签「${tag.name}」吗？已打该标签的粉丝将取消此标签。`, '确认删除', {
            confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning',
        })
        const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        await apiClient.delete(`/official-accounts/follower-tags/${tag.id}`, { headers: h })
        followerTags.value = followerTags.value.filter(t => t.id !== tag.id)
        ElMessage.success('标签已删除')
    } catch (e) { if (e !== 'cancel' && e !== 'close') ElMessage.error(e.response?.data?.message || '删除失败') }
}

async function loadAvailableTags() {
    const accountId = tagAccountId.value || myChannels.value[0]?.id
    if (!accountId) return
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/follower-tags`, { headers: h })
        availableTags.value = res.data?.data || []
    } catch { availableTags.value = [] }
}

async function openAssignTagDialog(follower) {
    assignTagFollowerId.value = follower.id
    assignTagFollowerName.value = follower.user?.name || '匿名'
    assignTagIds.value = (follower.tags || []).map(t => t.id)
    await loadAvailableTags()
    showAssignTagDialog.value = true
}

function toggleAssignTag(tagId) {
    const idx = assignTagIds.value.indexOf(tagId)
    if (idx >= 0) assignTagIds.value.splice(idx, 1)
    else assignTagIds.value.push(tagId)
}

async function saveAssignTag() {
    if (!assignTagFollowerId.value) return
    savingAssignTag.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.post('/official-accounts/follower-tags/assign', {
            follower_id: assignTagFollowerId.value,
            tag_ids: assignTagIds.value,
        }, { headers: h })
        ElMessage.success('标签已更新')
        showAssignTagDialog.value = false
        await loadFollowers()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally { savingAssignTag.value = false }
}

// ── 收益提现 ──
async function loadEarnings() {
    if (!isLoggedIn.value) return
    earningsLoading.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get('/official-accounts/earnings/my', { headers: h })
        const d = res.data?.data || {}
        earningsStats.value = d.stats || { total_points: 0, total_money: 0, pending_money: 0, purchase_count: 0, earnings_account: null }
        earningsRecent.value = d.recent || []
    } catch { /* ignore */ }
    finally { earningsLoading.value = false }
}

async function loadWithdrawals() {
    withdrawalsLoading.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get('/official-accounts/earnings/withdrawals', { headers: h })
        withdrawals.value = res.data?.data?.data || res.data?.data || []
    } catch { withdrawals.value = [] }
    finally { withdrawalsLoading.value = false }
}

// ── 跨平台分发方法 ──
async function loadPlatformAccounts() {
    const accountId = selectedChannel?.value?.id || (myChannels.value[0]?.id)
    if (!accountId) { platformAccounts.value = []; return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/platform-accounts`, { headers: h })
        platformAccounts.value = res.data?.data || []
    } catch { platformAccounts.value = [] }
}

async function loadDistributions(articleId = null) {
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        if (articleId) {
            const distRes = await apiClient.get(`/official-accounts/articles/${articleId}/distributions`, { headers: h })
            distributions.value = distRes.data?.data || []
        } else {
            // 加载最近一篇已发布文章的分发记录
            const articleRes = await apiClient.get(`/official-accounts/${selectedChannel?.value?.id}/articles`, { headers: h, params: { per_page: 1, status: 'published' } })
            const articles = articleRes.data?.data?.data || []
            if (articles.length) {
                const distRes = await apiClient.get(`/official-accounts/articles/${articles[0].id}/distributions`, { headers: h })
                distributions.value = distRes.data?.data || []
            } else {
                distributions.value = []
            }
        }
    } catch { distributions.value = [] }
}

async function savePlatformAccount() {
    const accountId = selectedChannel?.value?.id || (myChannels.value[0]?.id)
    if (!accountId) return
    paSaving.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.post(`/official-accounts/${accountId}/platform-accounts`, paForm.value, { headers: h })
        ElMessage.success('平台账号已绑定')
        paForm.value = { platform: 'wechat_mp', label: '', app_id: '', app_secret: '', platform_user_id: '', platform_user_name: '' }
        await loadPlatformAccounts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '绑定失败')
    } finally { paSaving.value = false }
}

async function deletePlatformAccount(platformId) {
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.delete(`/official-accounts/platform-accounts/${platformId}`, { headers: h })
        ElMessage.success('已删除')
        await loadPlatformAccounts()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '删除失败')
    }
}

// 加载可分发文章列表
async function loadDistributableArticles() {
    const accountId = selectedChannel?.value?.id || (myChannels.value[0]?.id)
    if (!accountId) return
    distArticlesLoading.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/articles`, { headers: h, params: { per_page: 50, status: 'published' } })
        distArticles.value = res.data?.data?.data || []
    } catch { distArticles.value = [] }
    finally { distArticlesLoading.value = false }
}

// 执行分发
async function executeDistribute() {
    if (!distArticleId.value) return
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    distArticleExecuting.value = true

    // 重置各平台分发状态
    platformAccounts.value.forEach(pa => { pa._distStatus = null })

    let successCount = 0, failCount = 0
    for (const pa of platformAccounts.value) {
        pa._distStatus = 'distributing'
        try {
            await apiClient.post(`/official-accounts/articles/${distArticleId.value}/distribute`, {
                platform_account_id: pa.id,
                platform: pa.platform,
            }, { headers: h })
            pa._distStatus = 'success'
            successCount++
        } catch {
            pa._distStatus = 'failed'
            failCount++
        }
    }

    distArticleExecuting.value = false
    if (successCount > 0 && failCount === 0) {
        ElMessage.success(`✅ 已成功分发到 ${successCount} 个平台`)
    } else if (successCount > 0) {
        ElMessage.warning(`⚠️ ${successCount} 成功，${failCount} 失败`)
    } else {
        ElMessage.error('❌ 全部分发失败，请检查平台配置')
    }
    // 刷新分发记录
    await loadDistributions(distArticleId.value)
}

function formatEarnings(val) {
    if (val === null || val === undefined) return '0'
    if (typeof val === 'number') return val.toFixed(2)
    return String(val)
}

async function submitWithdrawal() {
    const f = withdrawForm.value
    if (!f.amount || f.amount < 1) { ElMessage.warning('请输入有效金额'); return }
    if (f.channel === 'alipay' && !f.alipay_account.trim()) { ElMessage.warning('请输入支付宝账号'); return }
    if (f.channel === 'wechat' && !f.wechat_account.trim()) { ElMessage.warning('请输入微信账号'); return }
    if (f.channel === 'bank' && (!f.bank_name.trim() || !f.bank_account_name.trim() || !f.bank_account_no.trim())) {
        ElMessage.warning('请完整填写银行卡信息'); return
    }

    withdrawing.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const payload = {
            amount: f.amount,
            channel: f.channel,
            alipay_account: f.alipay_account || undefined,
            wechat_account: f.wechat_account || undefined,
            bank_name: f.bank_name || undefined,
            bank_account_name: f.bank_account_name || undefined,
            bank_account_no: f.bank_account_no || undefined,
        }
        await apiClient.post('/official-accounts/earnings/withdraw', payload, { headers: h })
        ElMessage.success('提现申请已提交，等待审核')
        withdrawForm.value = { amount: null, channel: 'alipay', alipay_account: '', wechat_account: '', bank_name: '', bank_account_name: '', bank_account_no: '' }
        await loadEarnings()
        await loadWithdrawals()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提现申请失败')
    } finally { withdrawing.value = false }
}

// ── 自动回复 ──
async function loadAutoReplies() {
    if (!myChannels.value.length) return
    autoRepliesLoading.value = true
    const accountId = autoReplyAccountId.value || myChannels.value[0]?.id
    if (!accountId) { autoRepliesLoading.value = false; return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/auto-replies`, { headers: h })
        autoReplies.value = res.data?.data || []
    } catch { autoReplies.value = [] }
    finally { autoRepliesLoading.value = false }
}

function showAutoReplyDialog(reply) {
    if (reply) {
        editingAutoReply.value = reply
        autoReplyForm.value = {
            type: reply.type,
            keyword: reply.keyword || '',
            match_type: reply.match_type || 0,
            content: reply.content || '',
            is_active: reply.is_active !== false,
        }
    } else {
        editingAutoReply.value = null
        autoReplyForm.value = { type: 'keyword', keyword: '', match_type: 0, content: '', is_active: true }
    }
    showAutoReplyDialogVisible.value = true
}

async function saveAutoReply() {
    if (!autoReplyForm.value.content.trim()) { ElMessage.warning('请输入回复内容'); return }
    if (autoReplyForm.value.type === 'keyword' && !autoReplyForm.value.keyword.trim()) { ElMessage.warning('请输入关键词'); return }
    savingAutoReply.value = true
    const accountId = autoReplyAccountId.value || myChannels.value[0]?.id
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        if (editingAutoReply.value) {
            await apiClient.put(`/official-accounts/auto-replies/${editingAutoReply.value.id}`, autoReplyForm.value, { headers: h })
            ElMessage.success('已更新')
        } else {
            await apiClient.post(`/official-accounts/${accountId}/auto-replies`, autoReplyForm.value, { headers: h })
            ElMessage.success('已创建')
        }
        showAutoReplyDialogVisible.value = false
        loadAutoReplies()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally { savingAutoReply.value = false }
}

async function toggleAutoReply(reply) {
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.put(`/official-accounts/auto-replies/${reply.id}`, { is_active: !reply.is_active }, { headers: h })
        reply.is_active = res.data?.data?.is_active ?? !reply.is_active
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function deleteAutoReply(reply) {
    try {
        await ElMessageBox.confirm(`确定要删除「${reply.type === 'welcome' ? '关注回复' : reply.type === 'keyword' ? '关键词回复「' + reply.keyword + '」' : '默认回复'}」吗？`, '确认删除', {
            confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
        })
        const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        await apiClient.delete(`/official-accounts/auto-replies/${reply.id}`, { headers: h })
        autoReplies.value = autoReplies.value.filter(r => r.id !== reply.id)
        ElMessage.success('已删除')
    } catch (e) {
        if (e !== 'cancel' && e !== 'close') {
            ElMessage.error(e.response?.data?.message || '删除失败')
        }
    }
}

// ── 自定义菜单 ──
async function loadMenus() {
    if (!myChannels.value.length) return
    menusLoading.value = true
    const accountId = menuAccountId.value || myChannels.value[0]?.id
    if (!accountId) { menusLoading.value = false; return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/menus`, { headers: h })
        menus.value = res.data?.data || []
    } catch { menus.value = [] }
    finally { menusLoading.value = false }
}

function showMenuDialog(menu, parentId) {
    editingMenu.value = menu
    menuParentId.value = parentId || null
    if (menu) {
        menuForm.value = {
            name: menu.name || '',
            type: menu.type || 'click',
            key: menu.key || '',
            app_id: menu.app_id || '',
            page_path: menu.page_path || '',
        }
    } else {
        menuForm.value = { name: '', type: 'click', key: '', app_id: '', page_path: '' }
    }
    showMenuDialogVisible.value = true
}

async function saveMenu() {
    if (!menuForm.value.name.trim()) { ElMessage.warning('请输入菜单名称'); return }
    savingMenu.value = true
    const accountId = menuAccountId.value || myChannels.value[0]?.id
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const payload = { ...menuForm.value }
        if (menuParentId.value) payload.parent_id = menuParentId.value

        if (editingMenu.value) {
            await apiClient.put(`/official-accounts/menus/${editingMenu.value.id}`, payload, { headers: h })
            ElMessage.success('已更新')
        } else {
            await apiClient.post(`/official-accounts/${accountId}/menus`, payload, { headers: h })
            ElMessage.success('已创建')
        }
        showMenuDialogVisible.value = false
        loadMenus()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally { savingMenu.value = false }
}

async function deleteMenu(menu) {
    try {
        await ElMessageBox.confirm(`确定要删除菜单「${menu.name}」吗？${menu.children ? '所有子菜单也将被删除。' : ''}`, '确认删除', {
            confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
        })
        const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        await apiClient.delete(`/official-accounts/menus/${menu.id}`, { headers: h })
        loadMenus()
        ElMessage.success('已删除')
    } catch (e) {
        if (e !== 'cancel' && e !== 'close') {
            ElMessage.error(e.response?.data?.message || '删除失败')
        }
    }
}

// ── 素材库 ──
async function loadMaterials() {
    if (!myChannels.value.length) return
    materialsLoading.value = true
    const accountId = materialAccountId.value || myChannels.value[0]?.id
    if (!accountId) { materialsLoading.value = false; return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/materials?per_page=50`, { headers: h })
        materials.value = res.data?.data?.data || res.data?.data || []
    } catch { materials.value = [] }
    finally { materialsLoading.value = false }
}

function onMaterialFileSelect(file) {
    materialFile.value = file.raw
}

async function uploadMaterial() {
    if (!materialFile.value) { ElMessage.warning('请先选择文件'); return }
    uploadingMaterial.value = true
    const accountId = materialAccountId.value || myChannels.value[0]?.id
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const fd = new FormData()
        fd.append('file', materialFile.value)
        await apiClient.post(`/official-accounts/${accountId}/materials/upload`, fd, {
            headers: { ...h }
        })
        ElMessage.success('已上传')
        materialFile.value = null
        loadMaterials()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '上传失败')
    } finally { uploadingMaterial.value = false }
}

async function deleteMaterial(m) {
    try {
        await ElMessageBox.confirm(`确定要删除素材「${m.file_name || m.id}」吗？`, '确认删除', {
            confirmButtonText: '删除', cancelButtonText: '取消', type: 'warning'
        })
        const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        await apiClient.delete(`/official-accounts/materials/${m.id}`, { headers: h })
        materials.value = materials.value.filter(x => x.id !== m.id)
        ElMessage.success('已删除')
    } catch (e) {
        if (e !== 'cancel' && e !== 'close') {
            ElMessage.error(e.response?.data?.message || '删除失败')
        }
    }
}

async function showSetCollectionDialogForArticle(art) {
    setCollectionArticle.value = art
    selectedCollectionId.value = art.collection_id || null
    // 加载该文章所属账号的合集列表
    const accountId = selectedChannel.value?.id
    if (!accountId) { ElMessage.warning('无法确定互物号'); return }
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/${accountId}/collections`, { headers: h })
        setCollectionAccountCollections.value = res.data?.data || []
        showSetCollectionDialog.value = true
    } catch {
        ElMessage.error('加载合集列表失败')
    }
}

async function saveArticleCollection() {
    if (!setCollectionArticle.value) return
    savingSetCollection.value = true
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        await apiClient.post(`/official-accounts/articles/${setCollectionArticle.value.id}/set-collection`, {
            collection_id: selectedCollectionId.value || null
        }, { headers: h })
        setCollectionArticle.value.collection_id = selectedCollectionId.value
        ElMessage.success(selectedCollectionId.value ? '已移入合集' : '已移出合集')
        showSetCollectionDialog.value = false
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    } finally { savingSetCollection.value = false }
}

// ── 阅读分析 ──
async function loadArticleRetention(art) {
    showArticleStatsDialog.value = true
    articleStatsLoading.value = true
    articleStatsData.value = null
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    try {
        const res = await apiClient.get(`/official-accounts/articles/${art.id}/retention`, { headers: h })
        articleStatsData.value = res.data?.data || null
    } catch {
        ElMessage.error('加载阅读分析失败')
        showArticleStatsDialog.value = false
    }
    finally { articleStatsLoading.value = false }
}

function barPct(count, dist) {
    const max = Math.max(...Object.values(dist), 1)
    return Math.round((count / max) * 100)
}
function barColor(label) {
    if (label.startsWith('0-')) return 'bg-red-400'
    if (label.startsWith('25-')) return 'bg-orange-400'
    if (label.startsWith('50-')) return 'bg-yellow-400'
    if (label.startsWith('75-')) return 'bg-green-400'
    return 'bg-primary-400'
}

// ── 管理功能 ──

async function loadMyData() {
    if (!isLoggedIn.value) return
    try {
        const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        const [myRes, subRes, topRes] = await Promise.all([
            apiClient.get('/official-accounts/my-owned', { headers: h }),
            apiClient.get('/official-accounts/my-submissions', { headers: h }),
            apiClient.get('/official-accounts/ranking', { headers: h }).catch(() => ({ data: { data: [] } })),
        ])
        myChannels.value = myRes.data?.data?.data || myRes.data?.data || []
        mySubmissions.value = subRes.data?.data?.data || subRes.data?.data || []
        topArticles.value = topRes.data?.data?.data || topRes.data?.data || []
        // 加载待审核投稿
        loadPendingSubmissions()
    } catch { /* ignore */ }
}

function statusClass(status) {
    return { pending: 'bg-amber-50 text-amber-600', approved: 'bg-green-50 text-green-600', rejected: 'bg-red-50 text-red-600' }[status] || 'bg-gray-50 text-gray-500'
}
function statusLabel(status) {
    return { pending: '待审核', approved: '已通过', rejected: '已驳回' }[status] || status
}

function onAvatarSelect(file) {
    avatarFile.value = file.raw
    avatarPreview.value = URL.createObjectURL(file.raw)
}
function onCoverSelect(file) {
    coverFile.value = file.raw
    coverPreview.value = URL.createObjectURL(file.raw)
}

async function saveChannel() {
    if (!channelForm.value.name.trim()) { ElMessage.warning('请输入名称'); return }
    savingChannel.value = true
    try {
        const fd = new FormData()
        fd.append('name', channelForm.value.name)
        fd.append('description', channelForm.value.description)
        if (channelForm.value.category_id) fd.append('category_id', channelForm.value.category_id)
        if (avatarFile.value) fd.append('avatar', avatarFile.value)
        if (coverFile.value) fd.append('cover_image', coverFile.value)

        if (editingChannel.value) {
            fd.append('_method', 'PUT')
            await apiClient.post(`/official-accounts/${editingChannel.value.id}`, fd, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            ElMessage.success('修改已保存')
        } else {
            await apiClient.post('/official-accounts', fd, {
                headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
            })
            ElMessage.success('互物号已创建')
        }
        showCreateDialog.value = false
        resetChannelForm()
        await loadMyData()
    } catch (e) {
        const errData = e.response?.data
        // 优先显示具体的验证错误
        if (errData?.error?.details) {
            const details = errData.error.details
            const firstField = Object.keys(details)[0]
            const msg = details[firstField]?.[0]
            if (msg) { ElMessage.error(msg); return }
        }
        ElMessage.error(errData?.message || '操作失败')
    }
    finally { savingChannel.value = false }
}

function editChannel(ch) {
    editingChannel.value = ch
    channelForm.value = { name: ch.name, description: ch.description || '', category_id: ch.category_id || '' }
    avatarPreview.value = ch.avatar || ''
    avatarFile.value = null
    coverPreview.value = ch.cover_image || ''
    coverFile.value = null
    showCreateDialog.value = true
}

function createArticle(ch) {
    window.open(`/build/oa-editor?account_id=${ch.id}`, '_blank')
}

function openChat(ch) {
    window.open('/build/user-chat', '_blank')
}

function appealChannel(ch) {
    appealChannelId.value = ch.id
    appealChannelName.value = ch.name
    appealReason.value = ''
    showAppealDialog.value = true
}

async function submitAppeal() {
    if (!appealReason.value.trim()) { ElMessage.warning('请填写申请原因'); return }
    appealing.value = true
    try {
        await apiClient.post(`/official-accounts/${appealChannelId.value}/appeal`, { reason: appealReason.value }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('解封申请已提交，请等待管理员审核')
        showAppealDialog.value = false
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败')
    } finally { appealing.value = false }
}

function applyVerifyChannel(ch) {
    verifyChannelId.value = ch.id
    verifyType.value = 'enterprise'
    verifyName.value = ''
    verifyReason.value = ''
    showVerifyDialog.value = true
}

async function submitVerify() {
    if (!verifyType.value) { ElMessage.warning('请选择认证类型'); return }
    if (!verifyName.value.trim()) { ElMessage.warning('请输入' + (verifyType.value === 'enterprise' ? '企业名称' : '真实姓名')); return }
    if (!verifyReason.value.trim()) { ElMessage.warning('请填写认证说明'); return }
    verifySubmitting.value = true
    try {
        await apiClient.post(`/official-accounts/${verifyChannelId.value}/apply-verify`, {
            reason: verifyReason.value,
            type: verifyType.value,
            name: verifyName.value,
        }, {
            headers: { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
        })
        ElMessage.success('认证申请已提交，请等待管理员审核')
        showVerifyDialog.value = false
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败')
    } finally { verifySubmitting.value = false }
}

function resetChannelForm() {
    editingChannel.value = null
    channelForm.value = { name: '', description: '', category_id: '' }
    avatarFile.value = null
    if (avatarPreview.value) { URL.revokeObjectURL(avatarPreview.value); avatarPreview.value = '' }
    coverFile.value = null
    if (coverPreview.value && coverPreview.value.startsWith('blob:')) { URL.revokeObjectURL(coverPreview.value); coverPreview.value = '' }
    else { coverPreview.value = '' }
}

async function loadCategories() {
    if (categoriesLoading.value) return
    categoriesLoading.value = true
    try {
        const res = await apiClient.get('/official-accounts/public/categories')
        const list = res.data?.data || []
        categories.value = [
            { value: 'all', label: '全部' },
            ...list.map(c => ({ value: c.slug || c.id, label: c.name }))
        ]
    } catch {
        // 离线回退：使用默认分类
        categories.value = [
            { value: 'all', label: '全部' },
            { value: 'tech', label: '技术' },
            { value: 'product', label: '产品' },
            { value: 'news', label: '资讯' },
            { value: 'tutorial', label: '教程' },
        ]
    } finally { categoriesLoading.value = false }
}

// 侧栏切换时自动加载对应数据
watch(manageSection, (section) => {
    if (!isLoggedIn.value) return
    const h = { Authorization: 'Bearer ' + localStorage.getItem('auth_token') }
    if (section === 'overview') {
        if (!dashboardAccountId.value && myChannels.value.length) {
            dashboardAccountId.value = myChannels.value[0].id
        }
        loadDashboard()
    }
    else if (section === 'comments') { showComments.value = true; loadComments() }
    else if (section === 'articles') { if (!articleManageAccountId.value && myChannels.value.length) { articleManageAccountId.value = myChannels.value[0].id }; loadManageArticles() }
    else if (section === 'followers') { showFollowers.value = true; loadFollowers() }
    else if (section === 'autoreply') { showAutoReplies.value = true; loadAutoReplies() }
    else if (section === 'menus') { showMenus.value = true; loadMenus() }
    else if (section === 'materials') { showMaterials.value = true; loadMaterials() }
    else if (section === 'follower-tags') { loadFollowerTags() }
    else if (section === 'earnings') { loadEarnings(); loadWithdrawals() }
    else if (section === 'distribution') { loadPlatformAccounts(); loadDistributions() }
})

onMounted(() => {
    loadFeed()
    loadCategories()
    loadChannels()
    loadPopularTags()
    // 支持 ?tab=manage、?section=distribution 等参数
    if (route.query.tab) {
        activeTab.value = route.query.tab
        if (route.query.tab === 'manage') loadMyData()
    }
    if (route.query.section) {
        activeTab.value = 'manage'
        manageSection.value = route.query.section
    }
    if (route.query.account_id) {
        // 可预选指定互物号
        activeTab.value = 'manage'
    }
    // 支持 ?account=4 直接进入互物号详情页
    if (route.query.account) {
        var aid = parseInt(route.query.account)
        if (aid > 0) {
            activeTab.value = 'discover'
            var doSelect = function() {
                var found = channels.value.find(function(c) { return c.id === aid })
                if (found) { viewChannelDetail(found); return true }
                return false
            }
            if (!doSelect()) {
                var unwatch = watch(channels, function() { if (doSelect()) unwatch() })
            }
        }
    }
})
</script>

<style scoped>
.channels-page { min-height: calc(100vh - 80px); background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); padding: 96px 0 60px; }
.channels-header {
    background: rgba(255,255,255,.85);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 80px;
    z-index: 50;
}
.wx-follow-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 18px;
    font-size: 13px;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid #07c160;
    background: #fff;
    color: #07c160;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 76px;
}
.wx-follow-btn:hover { background: #f0faf4; box-shadow: 0 2px 8px -2px rgba(7,193,96,.25); }
.wx-follow-btn.following {
    background: #f5f5f5;
    color: #999;
    border-color: #e5e5e5;
}
.wx-follow-btn.following:hover { background: #fef2f2; border-color: #fca5a5; color: #ef4444; }

/* 搜索框 */
.channels-header :deep(.el-input__wrapper) {
    border-radius: 10px !important;
    box-shadow: 0 1px 2px rgba(0,0,0,.04), inset 0 1px 2px rgba(0,0,0,.02) !important;
    border: 1px solid #e5e7eb !important;
    background: #fff !important;
    transition: all 0.2s ease !important;
}
.channels-header :deep(.el-input__wrapper:hover) {
    border-color: #d1d5db !important;
    box-shadow: 0 2px 8px -4px rgba(0,0,0,.08) !important;
}
.channels-header :deep(.el-input__wrapper.is-focus) {
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59,130,246,.1) !important;
}

/* 状态标签 */
.status-tag { font-size: 12px; padding: 2px 10px; border-radius: 999px; font-weight: 500; }

/* ── 骨架屏 ── */
.ch-skeleton { background: #fff; border-radius: 12px; border: 1px solid #eee; overflow: hidden; }
.ch-sk-avatar { width: 56px; height: 56px; border-radius: 16px; background: #eee; animation: sk-pulse 1.5s ease-in-out infinite; flex-shrink: 0; }
.ch-sk-line { height: 14px; border-radius: 7px; background: #eee; animation: sk-pulse 1.5s ease-in-out infinite; }
.ch-sk-line.h-8 { height: 32px; }

@keyframes sk-pulse { 0%, 100% { opacity: .4; } 50% { opacity: .8; } }

/* 响应式 */
@media (max-width: 640px) {
    .channels-page { padding-top: 80px; }
}

/* ── 头条风格文章列表 ── */
.tt-article-list {  }
.tt-article-item {
    display: flex; align-items: flex-start; gap: 20px;
    padding: 18px 20px; cursor: pointer;
    background: #fff; border-radius: 12px;
    border: 1px solid #f0f0f0;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
    transition: box-shadow .25s ease, transform .2s ease, border-color .2s ease;
    margin-bottom: 12px;
}
.tt-article-item:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    border-color: #e5e5e5;
    transform: translateY(-2px);
}
.tt-article-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 10px; }
.tt-article-source { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tt-source-name { font-size: 13px; color: #999; font-weight: 400; }
.tt-source-tag {
    font-size: 11px; padding: 1px 7px; border-radius: 3px;
    background: #f0f0f0; color: #999; line-height: 1.5;
}
.tt-tag-original { background: #fff1f0; color: #f5222d; }
.tt-tag-pinned { background: #fff1f0; color: #f62a0f; }
.tt-article-title {
    font-size: 17px; font-weight: 600; color: #1a1a1a;
    line-height: 1.5; display: -webkit-box;
    -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    margin: 0;
}
.tt-article-title:hover { color: #1a73e8; }
.tt-article-meta {
    font-size: 12px; color: #b0b0b0;
    display: flex; align-items: center; gap: 4px;
}
.tt-meta-dot { color: #ddd; }
.tt-article-thumb {
    width: 130px; height: 86px; border-radius: 10px;
    object-fit: cover; flex-shrink: 0; margin-top: 3px;
    background: #f5f5f5;
}
@media (max-width: 640px) {
    .tt-article-item { padding: 14px 16px; gap: 14px; }
    .tt-article-title { font-size: 15px; }
    .tt-article-thumb { width: 100px; height: 70px; border-radius: 8px; }
}

/* ── 标签云 ── */
.tag-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 12px;
    font-size: 12px;
    font-weight: 500;
    border-radius: 999px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.tag-chip:hover {
    border-color: #93c5fd;
    color: #3b82f6;
    background: #eff6ff;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px -4px rgba(59,130,246,.2);
}
.tag-chip-active {
    border-color: #3b82f6;
    color: #fff;
    background: #3b82f6;
}
.tag-chip-active:hover {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}
.tag-chip-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    font-size: 9px;
    font-weight: 600;
    border-radius: 999px;
    background: rgba(0,0,0,.08);
}
.tag-chip-active .tag-chip-count {
    background: rgba(255,255,255,.25);
}

/* ── 隐藏滚动条 ── */
.scrollbar-none::-webkit-scrollbar { display: none; }
.scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }

/* ── 页面过渡动效 ── */
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.25s ease;
}
.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

/* ── 文章列表入场动画（交错效果） ── */
.tt-article-item {
    animation: article-enter 0.35s ease both;
}
.tt-article-item:nth-child(1) { animation-delay: 0s; }
.tt-article-item:nth-child(2) { animation-delay: 0.04s; }
.tt-article-item:nth-child(3) { animation-delay: 0.08s; }
.tt-article-item:nth-child(4) { animation-delay: 0.12s; }
.tt-article-item:nth-child(5) { animation-delay: 0.16s; }
.tt-article-item:nth-child(6) { animation-delay: 0.2s; }
.tt-article-item:nth-child(7) { animation-delay: 0.24s; }
.tt-article-item:nth-child(8) { animation-delay: 0.28s; }
@keyframes article-enter {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── 推荐卡片悬浮效果 ── */
.channels-page .w-44.bg-white.rounded-xl:hover {
    transform: translateY(-2px);
}

/* ── 合集卡片悬浮 ── */
.channels-page .w-52.bg-white.rounded-xl:hover {
    transform: translateY(-2px);
}

/* ── Tab切换时的微交互 ── */
.channels-header button[class*="rounded-t-lg"] {
    transition: all 0.2s ease;
}
.channels-header button[class*="rounded-t-lg"]:hover:not([class*="text-primary-600"]) {
    background: rgba(0,0,0,.02);
}

/* ── 跟随按钮微动效 ── */
.wx-follow-btn {
    transition: all 0.2s ease, transform 0.15s ease;
}
.wx-follow-btn:active {
    transform: scale(0.95);
}

/* ── 文章管理操作按钮 ── */
.tt-article-actions { position: relative; flex-shrink: 0; margin-top: 2px; z-index: 2; }
.tt-action-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px;
    border: 1px solid transparent; background: transparent;
    color: #ccc; cursor: pointer; transition: all .15s;
}
.tt-action-btn:hover { background: #f5f5f5; border-color: #e5e7eb; color: #666; }
</style>
