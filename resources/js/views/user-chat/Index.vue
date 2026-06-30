<template>
    <div class="user-chat-page">
        <div class="chat-layout">
            <div v-if="isMobile && activeConv" class="mobile-overlay" @click="activeConv = null"></div>

            <!-- 左侧面板 -->
            <div class="chat-sidebar" :class="{ 'sidebar-hidden': isMobile && activeConv }">
                <div class="sidebar-tabs">
                    <el-tabs v-model="sidebarTab" @tab-change="onSidebarTabChange">
                        <el-tab-pane label="💬 消息" name="messages" />
                        <el-tab-pane label="�‍💼 客服" name="agentWorkspace" />
                        <el-tab-pane label="�👥 好友" name="friends" />
                        <el-tab-pane label="📡 圈子" name="channels" />
                        <el-tab-pane label="🌐 广场" name="plaza" />
                        <el-tab-pane label="📢 公众号" name="officialAccounts" />
                        <el-tab-pane label="👤 我的" name="myProfile" />
                        <el-tab-pane name="more">
                            <template #label>
                                <el-dropdown trigger="click" @command="handleMoreTab" @click.stop>
                                    <span class="more-tab-label">🔔 更多 <el-icon><ArrowDown /></el-icon></span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="notifications">🔔 通知</el-dropdown-item>
                                            <el-dropdown-item command="favorites">⭐ 收藏</el-dropdown-item>
                                            <el-dropdown-item command="pending">⏳ 待处理</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                            </template>
                        </el-tab-pane>
                    </el-tabs>
                </div>

                <!-- ====== 消息列表 ====== -->
                <template v-if="sidebarTab === 'messages'">
                    <div class="sidebar-header">
                        <h3>消息</h3>
                        <div class="sidebar-header-actions">
                            <el-button size="small" text @click="showBlockedList = true" title="黑名单"><el-icon><RemoveFilled /></el-icon></el-button>
                            <el-button size="small" text @click="showSensitiveWords = true" title="敏感词管理"><el-icon><EditPen /></el-icon></el-button>
                            <el-button size="small" text @click="showDndSettings = true" title="免打扰设置"><el-icon><MuteNotification /></el-icon></el-button>
                            <el-button size="small" text @click="showPrivacySettings = true" title="隐私设置"><el-icon><Lock /></el-icon></el-button>
                            <el-button size="small" text @click="showDashboard = true" title="IM 数据看板"><el-icon><DataBoard /></el-icon></el-button>
                            <el-button size="small" text @click="showAiFriendAdmin = true" title="AI 好友管理"><el-icon><MagicStick /></el-icon></el-button>
                            <el-button size="small" text @click="showSearchPanel = !showSearchPanel" :type="showSearchPanel ? 'primary' : 'default'" title="全局搜索"><el-icon><Search /></el-icon></el-button>
                            <el-button size="small" type="primary" circle @click="showNewChat = true"><el-icon><Plus /></el-icon></el-button>
                            <el-button size="small" text @click="cycleTheme" :title="'主题: ' + (themeMode === 'light' ? '浅色' : themeMode === 'dark' ? '深色' : '跟随系统')">
                                <el-icon><Sunny v-if="themeMode === 'light'" /><MoonNight v-else-if="themeMode === 'dark'" /><Monitor v-else /></el-icon>
                            </el-button>
                            <el-dropdown trigger="click" @command="setMyStatus">
                                <el-button size="small" text :title="'状态: '+ ({online:'在线',busy:'忙碌',invisible:'隐身'}[myStatus]||'在线')">
                                    <el-icon :color="myStatus === 'online' ? '#67c23a' : myStatus === 'busy' ? '#e6a23c' : '#999'">
                                        <User />
                                    </el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="online"><el-icon style="color:#67c23a"><Select /></el-icon> 在线</el-dropdown-item>
                                        <el-dropdown-item command="busy"><el-icon style="color:#e6a23c"><RemoveFilled /></el-icon> 忙碌</el-dropdown-item>
                                        <el-dropdown-item command="invisible"><el-icon style="color:#999"><Mute /></el-icon> 隐身</el-dropdown-item>
                                        <el-dropdown-item divided>
                                            <div style="display:flex;align-items:center;gap:6px;font-size:12px" @click.stop>
                                                <span>🤖 自动回复</span>
                                                <el-switch :model-value="autoReplyEnabled" size="small" @change="toggleAutoReply" />
                                            </div>
                                        </el-dropdown-item>
                                        <el-dropdown-item command="goto_auto_reply" style="font-size:12px;color:#909399">⚙️ 管理回复规则</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    <div class="sidebar-search">
                        <el-input v-model="searchKeyword" placeholder="搜索消息..." size="small" clearable @input="onSearch" />
                    </div>
                    <!-- 全局搜索面板 -->
                    <div v-if="showSearchPanel" class="global-search-panel">
                        <el-input v-model="globalSearchKeyword" placeholder="搜索所有消息，支持自然语言..." size="small" clearable @keydown.enter="onGlobalSearch">
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                        <div class="search-options">
                            <el-radio-group v-model="searchMode" size="small">
                                <el-radio-button value="fulltext">关键词</el-radio-button>
                                <el-radio-button value="semantic">AI 语义</el-radio-button>
                            </el-radio-group>
                            <el-button v-if="globalSearchKeyword.trim()" text size="small" type="primary" @click="onGlobalSearch"><el-icon><Search /></el-icon> 搜索</el-button>
                        </div>
                        <div v-if="globalSearchResults.length > 0" class="search-results">
                            <div v-for="r in globalSearchResults" :key="r.id" class="search-result-item" @click="jumpToMessage(r)">
                                <div class="search-result-conv">{{ r.conversation_name || r.conversation?.name || '会话' }}</div>
                                <div class="search-result-content" v-html="highlightKeyword(r.content)"></div>
                                <div class="search-result-time">{{ formatTime(r.created_at) }}</div>
                            </div>
                        </div>
                        <div v-else-if="globalSearchKeyword && !searchingGlobal" class="empty-chat">
                            <el-empty description="未找到消息" :image-size="40" />
                        </div>
                        <div v-if="searchingGlobal" class="search-loading"><el-icon class="is-loading"><Loading /></el-icon> 搜索中...</div>
                        <div v-if="searchKeywords && searchMode === 'semantic'" class="search-keywords">🔍 提取关键词: <strong>{{ searchKeywords }}</strong></div>
                    </div>
                    <!-- 会话文件夹 -->
                    <div class="folder-bar">
                        <el-select v-model="activeFolder" size="small" placeholder="全部分组" clearable style="width:120px" @change="onFolderChange">
                            <el-option label="全部分组" value="" />
                            <el-option v-for="f in folders" :key="f.id" :label="f.name" :value="f.id" />
                        </el-select>
                        <el-button text size="small" @click="showFolderDialog = true" title="管理分组"><el-icon><Setting /></el-icon></el-button>
                        <el-button text size="small" @click="toggleBatchMode" :type="batchMode ? 'primary' : 'default'" title="批量操作">
                            <el-icon><Select /></el-icon>
                        </el-button>
                        <el-button text size="small" @click="openHiddenConversations" title="私密空间" style="color:#909399">
                            <el-icon><Lock /></el-icon>
                        </el-button>
                    </div>
                    <!-- AI-018: 智能分类 -->
                    <div class="category-bar">
                        <el-radio-group v-model="convCategory" size="small" @change="onCategoryChange">
                            <el-radio-button value="">全部</el-radio-button>
                            <el-radio-button value="urgent"><el-icon style="color:#f56c6c"><Warning /></el-icon> 紧急</el-radio-button>
                            <el-radio-button value="work"><el-icon style="color:#409eff"><Briefcase /></el-icon> 工作</el-radio-button>
                            <el-radio-button value="normal"><el-icon style="color:#67c23a"><ChatDotRound /></el-icon> 普通</el-radio-button>
                            <el-radio-button value="promotion"><el-icon style="color:#e6a23c"><PriceTag /></el-icon> 促销</el-radio-button>
                            <el-radio-button value="spam"><el-icon style="color:#999"><Delete /></el-icon> 垃圾</el-radio-button>
                            <el-radio-button value="archived"><el-icon style="color:#909399"><FolderDelete /></el-icon> 归档</el-radio-button>
                        </el-radio-group>
                    </div>
                    <!-- 批量归档 -->
                    <div class="batch-archive-bar" v-if="batchMode">
                        <span class="batch-count">已选 {{ selectedConvIds.length }} 个</span>
                        <div class="batch-actions">
                            <el-button size="small" text @click="cancelBatch">取消</el-button>
                            <el-button v-if="convCategory === 'archived'" size="small" @click="batchUnarchive">取消归档</el-button>
                            <el-button v-else size="small" type="warning" @click="batchArchive">归档所选</el-button>
                            <el-button size="small" type="primary" @click="batchArchiveInactive">归档30天未更新</el-button>
                        </div>
                    </div>
                    <div class="self-chat-entry" @click="openSelfChat">
                        <el-icon style="margin-right:6px;font-size:16px"><Message /></el-icon>
                        <span>📁 文件传输助手</span>
                    </div>
                    <div class="self-chat-entry" style="border-top:1px solid #f0f0f0;margin-top:2px" @click="openAIChat">
                        <el-icon style="margin-right:6px;font-size:16px;color:#409eff"><MagicStick /></el-icon>
                        <span style="color:#409eff">🤖 AI 助手</span>
                    </div>
                    <div class="conversation-list" v-loading="loading">
                        <div v-for="conv in (convCategory === 'archived' ? filteredConversations : (convCategory ? classifiedConversations : filteredConversations))" :key="conv.id"
                            class="conv-item" :class="{ active: activeConv?.id === conv.id }"
                            @click="onConvClick(conv)" @contextmenu.prevent="onConvContextMenu($event, conv)">
                            <el-checkbox v-if="batchMode" v-model="selectedConvIds" :value="conv.id" :label="conv.id" size="small" @click.stop class="conv-checkbox" />
                            <div class="conv-avatar-wrap">
                                <div class="conv-avatar">{{ conv.name?.charAt(0) || '?' }}</div>
                                <span v-if="conv.online_status === 'online'" class="online-dot"></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ conv.name }}</span>
                                    <span class="conv-time">{{ formatTime(conv.updated_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ conv.last_message?.content || '暂无消息' }}</span>
                                    <span v-if="conv.unread_count > 0" class="unread-badge">{{ conv.unread_count > 99 ? '99+' : conv.unread_count }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="(convCategory === 'archived' ? filteredConversations : (convCategory ? classifiedConversations : filteredConversations)).length === 0 && !loading" class="empty-chat">
                            <el-empty :description="convCategory ? '该分类下暂无会话' : '暂无会话'" :image-size="60" />
                        </div>
                    </div>
                </template>

                <!-- ====== 好友列表 ====== -->
                <template v-if="sidebarTab === 'friends'">
                    <div class="sidebar-header">
                        <h3>好友</h3>
                        <div style="display:flex;gap:4px">
                            <el-button size="small" circle @click="showCreateAiFriend = true" title="创建 AI 好友"><el-icon><MagicStick /></el-icon></el-button>
                            <el-button size="small" type="primary" circle @click="showAddFriend = true"><el-icon><User /></el-icon></el-button>
                        </div>
                    </div>
                    <!-- AI 好友分组 -->
                    <div v-if="aiFriends.length" class="ai-friend-section">
                        <div class="ai-friend-header"><el-icon style="color:#409eff"><MagicStick /></el-icon> AI 助手 <span class="ai-friend-count">{{ aiFriends.length }}</span></div>
                        <div v-for="f in aiFriends" :key="'ai-'+f.id" class="conv-item ai-friend-item" @click="startAiFriendChat(f)">
                            <div class="conv-avatar-wrap">
                                <img v-if="f.avatar" :src="f.avatar" class="conv-avatar-img" />
                                <div v-else class="conv-avatar" style="background:#409eff">{{ f.name?.charAt(0) || 'A' }}</div>
                                <span class="ai-badge">🤖</span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ f.name }}</span>
                                    <span class="ai-category-tag">{{ categoryLabel(f.category) }}</span>
                                </div>
                                <div class="conv-bottom"><span class="conv-last">{{ f.description || f.welcome_message || 'AI 助手' }}</span></div>
                            </div>
                            <el-dropdown trigger="click" @command="(cmd) => handleAiFriendAction(cmd, f)" @click.stop>
                                <el-button text size="small" @click.stop><el-icon><MoreFilled /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="delete" divided>删除</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    <div class="sidebar-divider"></div>
                    <!-- 搜索好友 -->
                    <div class="sidebar-search">
                        <el-input v-model="friendSearch" placeholder="搜索好友..." size="small" clearable />
                    </div>
                    <div class="friend-filter-bar">
                        <el-select v-model="friendGroupFilter" placeholder="全部分组" size="small" clearable style="width:130px">
                            <el-option label="全部分组" :value="null" />
                            <el-option v-for="g in friendGroups" :key="g.id" :label="g.name" :value="g.id" />
                        </el-select>
                        <el-button size="small" text @click="showFriendGroups = true">管理分组</el-button>
                        <el-button v-if="pendingRequests.length" size="small" text @click="showPendingRequests = true">请求({{ pendingRequests.length }})</el-button>
                    </div>
                    <div class="conversation-list" v-loading="loadingFriends">
                        <div v-for="f in filteredFriends" :key="f.id" class="conv-item" @click="startFriendChat(f)">
                            <div class="conv-avatar-wrap">
                                <div class="conv-avatar" :style="{ background: f.online === 'online' ? '#52c41a' : '#999' }">{{ f.name?.charAt(0) || '?' }}</div>
                                <span v-if="f.online === 'online'" class="online-dot"></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ f.remark || f.name || '用户' }}</span>
                                    <span class="conv-time">{{ f.online === 'online' ? '在线' : '离线' }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span v-if="f.friend_group_name" class="friend-group-tag">{{ f.friend_group_name }}</span>
                                </div>
                            </div>
                            <el-dropdown trigger="click" @command="(cmd) => handleFriendAction(cmd, f)">
                                <el-button text size="small" @click.stop><el-icon><MoreFilled /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="remark">设置备注</el-dropdown-item>
                                        <el-dropdown-item command="group">移动分组</el-dropdown-item>
                                        <el-dropdown-item command="remove" divided>删除好友</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                        <div v-if="!filteredFriends.length && !loadingFriends" class="empty-chat">
                            <el-empty description="暂无好友" :image-size="60" />
                        </div>
                    </div>
                </template>

                <!-- ====== 通知列表 ====== -->
                <template v-if="sidebarTab === 'notifications'">
                    <div class="sidebar-header">
                        <h3>通知</h3>
                        <el-button v-if="notifications.some(n => !n.is_read)" size="small" text @click="markAllNotifRead">全部已读</el-button>
                    </div>
                    <div class="conversation-list">
                        <div v-for="n in notifications" :key="n.id" class="conv-item notif-item" :class="{ 'notif-unread': !n.is_read }" @click="handleNotifClick(n)">
                            <div class="notif-icon"><el-icon :size="18" :color="n.is_read ? '#999' : '#409eff'"><Bell /></el-icon></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name" :style="{ fontWeight: n.is_read ? 'normal' : 'bold' }">{{ n.title }}</span>
                                    <span class="conv-time">{{ formatTime(n.created_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ n.content }}</span>
                                    <span v-if="!n.is_read" class="unread-dot"></span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!notifications.length" class="empty-chat"><el-empty description="暂无通知" :image-size="60" /></div>
                    </div>
                </template>

                <!-- ====== 收藏列表 ====== -->
                <template v-if="sidebarTab === 'favorites'">
                    <div class="sidebar-header"><h3>⭐ 收藏</h3></div>
                    <div class="oa-sidebar-tabs" style="display:flex;gap:4px;padding:4px 12px;border-bottom:1px solid #eee">
                        <el-tag :type="favTab === 'messages' ? 'primary' : 'info'" size="small" style="cursor:pointer" @click="favTab = 'messages'">消息</el-tag>
                        <el-tag :type="favTab === 'articles' ? 'primary' : 'info'" size="small" style="cursor:pointer" @click="favTab = 'articles'; loadOaFavorites()">文章</el-tag>
                    </div>
                    <!-- 收藏的消息 -->
                    <div v-show="favTab === 'messages'" class="conversation-list" v-loading="loadingFavorites">
                        <div v-for="fav in favorites" :key="fav.id" class="conv-item" @click="jumpToFavorite(fav)">
                            <div class="conv-avatar-wrap"><div class="conv-avatar" style="background:#e6a23c">★</div></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ fav.sender_name || '用户' }}</span>
                                    <span class="conv-time">{{ formatTime(fav.created_at) }}</span>
                                </div>
                                <div class="conv-bottom"><span class="conv-last">{{ fav.content }}</span></div>
                            </div>
                        </div>
                        <div v-if="!favorites.length && !loadingFavorites" class="empty-chat"><el-empty description="暂无收藏消息" :image-size="60" /></div>
                    </div>
                    <!-- 收藏的 OA 文章 -->
                    <div v-show="favTab === 'articles'" class="conversation-list" v-loading="loadingOaFavorites">
                        <div v-for="fav in oaFavorites" :key="fav.id" class="conv-item" @click="openOaArticleDetailFromFav(fav)">
                            <div class="conv-avatar-wrap"><div class="conv-avatar" style="background:#67c23a">📄</div></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ fav.article?.title?.substring(0, 30) || '文章' }}</span>
                                    <span class="conv-time">{{ formatTime(fav.created_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ fav.article?.account?.name || '公众号' }} · {{ fav.article?.summary?.substring(0, 40) || '' }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!oaFavorites.length && !loadingOaFavorites" class="empty-chat"><el-empty description="暂无收藏文章" :image-size="60" /></div>
                    </div>
                </template>

                <!-- ====== 频道/社区列表 ====== -->
                <template v-if="sidebarTab === 'channels'">
                    <channel-panel
                        :channels="myChannels"
                        :loading="loadingChannels"
                        :browse-channels="browseChannels"
                        :channel-categories="channelCategories"
                        @select="selectChannel"
                        @refresh="loadMyChannels"
                        @browse="loadBrowseChannels"
                        @load-categories="loadChannelCategories"
                    />
                </template>

                <!-- ====== 广场面板 ====== -->
                <template v-if="sidebarTab === 'plaza'">
                    <plaza-panel :my-id="myId" @select-category="onPlazaCategory" @view-my-post="viewMyPlazaPost" />
                </template>

                <!-- ====== 公众号面板 ====== -->
                <template v-if="sidebarTab === 'officialAccounts'">
                    <oa-panel
                        ref="oaPanelRef"
                        @select-account="selectOaAccount"
                        @view-submission="viewOaSubmission"
                        @view-pending-reviews="viewPendingReviews"
                        @open-discover="openDiscover"
                        @view-oa-article="openOaArticleDetail"
                    />
                </template>

                <!-- ====== 我的面板 ====== -->
                <template v-if="sidebarTab === 'myProfile'">
                    <div class="sidebar-header" style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px">
                        <h3 style="margin:0;font-size:15px">👤 我的</h3>
                        <el-button text size="small" @click="openAccountProfile">⚙️ 账户中心</el-button>
                    </div>
                    <div class="conversation-list" style="overflow-y:auto;flex:1">
                        <!-- 用户信息 -->
                        <div class="my-profile-card" style="text-align:center;padding:20px 16px;">
                            <el-avatar :size="56" :src="userInfo?.avatar_url" style="margin:0 auto 8px">{{ userInfo?.name?.charAt(0) || '?' }}</el-avatar>
                            <div style="font-weight:600;font-size:15px">{{ userInfo?.name || '用户' }}</div>
                            <div style="font-size:12px;color:#909399;margin-top:2px">{{ userInfo?.email || '' }}</div>
                            <div style="margin-top:8px;display:flex;justify-content:center;gap:12px;font-size:12px">
                                <span>🪙 <strong>{{ pointsBalance }}</strong> 积分</span>
                                <el-button text size="small" type="primary" style="font-size:12px" @click="openPointsHistory">交易记录</el-button>
                            </div>
                        </div>

                        <!-- 功能入口按钮 -->
                        <div class="my-profile-actions">
                            <el-button :type="myProfileSection === 'daily' ? 'warning' : 'default'" size="small" @click="myProfileSection = 'daily'" style="flex:1">
                                📅 每日签到
                            </el-button>
                            <el-button :type="myProfileSection === 'social' ? 'primary' : 'default'" size="small" @click="openMyInteractions" style="flex:1">
                                👥 朋友圈
                            </el-button>
                        </div>

                        <!-- 每日签到 / 今日任务 -->
                        <div v-if="myProfileSection === 'daily'" class="my-profile-content">
                            <div class="my-section-header">🎯 今日任务</div>
                            <div class="daily-task-item" v-for="task in dailyTasks" :key="task.key">
                                <div class="daily-task-info">
                                    <span class="daily-task-icon">{{ task.done ? '✅' : '⭕' }}</span>
                                    <span class="daily-task-label" :class="{ 'task-done': task.done }">{{ task.label }}</span>
                                    <span class="daily-task-reward">+{{ task.reward }}分</span>
                                </div>
                                <div class="daily-task-bar-wrap">
                                    <div class="daily-task-bar" :style="{ width: (task.progress / task.total * 100) + '%' }"></div>
                                </div>
                                <div class="daily-task-progress">{{ task.progress }}/{{ task.total }}</div>
                            </div>
                            <div class="daily-summary">
                                🎉 今日已得 <strong>{{ dailyEarned }}</strong> / <strong>{{ dailyMax }}</strong> 积分
                            </div>
                        </div>

                        <!-- 朋友圈 -->
                        <div v-if="myProfileSection === 'social'" class="my-profile-content">
                            <div class="my-section-header">👥 我的互动</div>
                            <div class="social-menu-item" @click="openSocialTab('following')">
                                <span class="social-menu-icon">🔥</span>
                                <span class="social-menu-label">关注动态</span>
                                <el-icon><ArrowRight /></el-icon>
                            </div>
                            <div class="social-menu-item" @click="openSocialTab('reading')">
                                <span class="social-menu-icon">📋</span>
                                <span class="social-menu-label">阅读清单</span>
                                <span class="social-menu-badge">{{ readingListCount || 0 }}</span>
                                <el-icon><ArrowRight /></el-icon>
                            </div>
                            <div class="social-menu-item" @click="openSocialTab('follows')">
                                <span class="social-menu-icon">❤️</span>
                                <span class="social-menu-label">关注</span>
                                <el-icon><ArrowRight /></el-icon>
                            </div>
                            <div class="social-menu-item" @click="openSocialTab('favorites')">
                                <span class="social-menu-icon">⭐</span>
                                <span class="social-menu-label">收藏</span>
                                <el-icon><ArrowRight /></el-icon>
                            </div>
                            <div class="social-menu-item" @click="openSocialTab('likes')">
                                <span class="social-menu-icon">👍</span>
                                <span class="social-menu-label">点赞</span>
                                <el-icon><ArrowRight /></el-icon>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ====== 待处理列表 ====== -->
                <template v-if="sidebarTab === 'pending'">
                    <div class="sidebar-header"><h3>⏳ 待处理</h3></div>
                    <div class="conversation-list" v-loading="loadingPending">
                        <div v-for="p in pendingMessages" :key="p.id" class="conv-item" @click="jumpToPending(p)">
                            <div class="conv-avatar-wrap"><div class="conv-avatar" style="background:#e6a23c">⏳</div></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ p.sender_name }} → {{ p.conversation_name || '会话' }}</span>
                                    <span class="conv-time">{{ formatTime(p.created_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ p.content }}</span>
                                </div>
                            </div>
                            <el-button text size="small" @click.stop="removePending(p)"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div v-if="!pendingMessages.length && !loadingPending" class="empty-chat"><el-empty description="暂无待处理消息" :image-size="60" /></div>
                    </div>
                </template>

                <!-- ====== 客服工作台 ====== -->
                <template v-if="sidebarTab === 'agentWorkspace'">
                    <agent-workspace
                        @select-conversation="selectAgentConversation"
                    />
                </template>
            </div>

            <!-- ====== 右侧聊天窗口 ====== -->
            <div class="chat-main">
                <!-- ═══════ 我的互动视图 ═══════ -->
                <template v-if="showMyInteractions">
                    <div class="my-interactions-view">
                        <div class="oa-article-detail-header">
                            <div class="oa-detail-top-row">
                                <button class="oa-detail-back" @click="showMyInteractions = false">← 返回</button>
                                <span style="font-weight:600;font-size:15px">👥 朋友圈</span>
                                <div></div>
                            </div>
                        </div>
                        <div class="my-interactions-body">
                            <el-tabs v-model="myInteractionTab">
                                <el-tab-pane label="🔥 关注动态" name="following">
                                    <div v-loading="loadingFollowing" style="min-height:200px">
                                        <div v-for="item in followingFeed" :key="'oa_'+item.id" class="my-feed-card" @click="openArticleFromFeed(item)">
                                            <div class="my-feed-cover" v-if="getCoverImage(item)">
                                                <img :src="getCoverImage(item)" />
                                            </div>
                                            <div class="my-feed-body">
                                                <div class="my-feed-account">
                                                    <el-avatar :size="22" :src="item.account?.avatar" />
                                                    <span class="my-feed-acc-name">{{ item.account?.name || '公众号' }}</span>
                                                    <span class="my-feed-time">{{ formatTime(item.published_at) }}</span>
                                                </div>
                                                <div class="my-feed-title">{{ item.title }}</div>
                                                <div class="my-feed-summary" v-if="item.summary">{{ item.summary.substring(0, 80) }}</div>
                                                <div class="my-feed-meta">
                                                    <span>👁️ {{ item.reads_count || 0 }}</span>
                                                    <span>❤️ {{ item.likes_count || 0 }}</span>
                                                    <span>💬 {{ item.comments_count || 0 }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <el-empty v-if="!followingFeed.length && !loadingFollowing" description="还没有关注公众号，去发现一些优质内容吧" :image-size="50" />
                                    </div>
                                </el-tab-pane>
                                <el-tab-pane label="📋 阅读清单" name="reading">
                                    <div v-loading="loadingReadingList" style="min-height:200px">
                                        <div v-for="item in readingList" :key="'rl_'+item.id" class="my-feed-card" @click="openArticleFromFeed(item.article || item)">
                                            <div class="my-feed-cover" v-if="getCoverImage(item.article || item)">
                                                <img :src="getCoverImage(item.article || item)" />
                                            </div>
                                            <div class="my-feed-body">
                                                <div class="my-feed-account">
                                                    <span class="my-feed-acc-name">{{ item.article?.title || item.title }}</span>
                                                    <el-tag :type="item.is_completed ? 'success' : 'warning'" size="small" style="margin-left:auto">{{ item.is_completed ? '已读' : '待读' }}</el-tag>
                                                </div>
                                                <div class="my-feed-summary" v-if="item.article?.summary">{{ item.article.summary.substring(0, 60) }}</div>
                                            </div>
                                        </div>
                                        <el-empty v-if="!readingList.length && !loadingReadingList" description="阅读清单为空" :image-size="50" />
                                    </div>
                                </el-tab-pane>
                                <el-tab-pane label="❤️ 关注" name="follows">
                                    <div v-loading="loadingFollows" style="min-height:200px">
                                        <div v-for="item in myFollowedAccounts" :key="'acc_'+item.id" class="my-feed-card" @click="selectFollowedAccount(item)">
                                            <div class="my-feed-cover my-feed-cover-avatar" v-if="item.avatar">
                                                <img :src="item.avatar" style="border-radius:50%;width:48px;height:48px;margin:16px auto;display:block" />
                                            </div>
                                            <div class="my-feed-body">
                                                <div class="my-feed-title">{{ item.name }}</div>
                                                <div class="my-feed-summary" v-if="item.description">{{ item.description.substring(0, 60) }}</div>
                                                <div class="my-feed-meta">
                                                    <span>👥 {{ item.followers_count || 0 }} 粉丝</span>
                                                    <span>📄 {{ item.articles_count || 0 }} 文章</span>
                                                </div>
                                                <div class="my-feed-meta" v-if="item.latest_article" style="margin-top:4px">
                                                    <span style="color:#409eff">最新：{{ item.latest_article.title }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <el-empty v-if="!myFollowedAccounts.length && !loadingFollows" description="还没有关注任何公众号" :image-size="50" />
                                    </div>
                                </el-tab-pane>
                                <el-tab-pane label="⭐ 收藏" name="favorites">
                                    <div v-loading="loadingFavs" style="min-height:200px">
                                        <div v-for="item in myFavorites" :key="'fav_'+item.id" class="my-feed-card" @click="openArticleFromFeed(item)">
                                            <div class="my-feed-cover" v-if="item.cover">
                                                <img :src="item.cover" />
                                            </div>
                                            <div class="my-feed-body">
                                                <div class="my-feed-account">
                                                    <el-tag size="small" style="margin-right:4px">{{ typeIcon(item.type) }}</el-tag>
                                                    <span class="my-feed-acc-name" style="font-size:12px;color:#909399">{{ typeName(item.type) }}</span>
                                                    <span class="my-feed-time">{{ formatTime(item.interacted_at) }}</span>
                                                </div>
                                                <div class="my-feed-title">{{ item.title }}</div>
                                            </div>
                                        </div>
                                        <el-empty v-if="!myFavorites.length && !loadingFavs" description="还没有收藏任何文章" :image-size="50" />
                                    </div>
                                </el-tab-pane>
                                <el-tab-pane label="👍 点赞" name="likes">
                                    <div v-loading="loadingLikes" style="min-height:200px">
                                        <div v-for="item in myLikes" :key="'like_'+item.id" class="my-feed-card" @click="openArticleFromFeed(item)">
                                            <div class="my-feed-cover" v-if="item.cover">
                                                <img :src="item.cover" />
                                            </div>
                                            <div class="my-feed-body">
                                                <div class="my-feed-account">
                                                    <el-tag size="small" style="margin-right:4px">{{ typeIcon(item.type) }}</el-tag>
                                                    <span class="my-feed-acc-name">{{ item.title }}</span>
                                                    <span class="my-feed-time">{{ formatTime(item.interacted_at) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <el-empty v-if="!myLikes.length && !loadingLikes" description="还没有点赞过任何文章" :image-size="50" />
                                    </div>
                                </el-tab-pane>
                            </el-tabs>
                        </div>
                    </div>
                </template>
                <template v-else-if="activeConv">
                    <!-- ═══════ 频道聊天视图 ═══════ -->
                    <template v-if="activeConv.is_channel">
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <el-button v-if="isMobile" text size="small" @click="activeConv = null" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                                <span class="chat-title">{{ activeConv.channel_icon }} {{ activeConv.channel_name }}</span>
                                <span class="channel-badge">圈子</span>
                            </div>
                            <div class="chat-actions">
                                <span class="text-muted" style="font-size:12px">{{ activeConv.member_count || 0 }} 人</span>
                                <el-button v-if="channelSearchActive" text size="small" @click="channelSearchActive = false" title="关闭搜索">
                                    <el-icon><Search /></el-icon>
                                </el-button>
                                <el-button v-else text size="small" @click="channelSearchActive = true" title="搜索消息">
                                    <el-icon><Search /></el-icon>
                                </el-button>
                                <el-button v-if="!activeConv.is_member" size="small" type="primary" @click="joinChannel(activeConv.channel_id)">加入</el-button>
                                <!-- 频道设置菜单 -->
                                <el-dropdown v-else trigger="click" @command="handleChannelSetting" @click.stop>
                                    <el-button size="small" text><el-icon><Setting /></el-icon> 设置</el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="edit">
                                                <el-icon><EditPen /></el-icon> 编辑圈子
                                            </el-dropdown-item>
                                            <el-dropdown-item command="members">
                                                <el-icon><User /></el-icon> 成员管理
                                            </el-dropdown-item>
                                            <el-dropdown-item command="pinned">
                                                <el-icon><StarFilled /></el-icon> 置顶消息
                                            </el-dropdown-item>
                                            <el-dropdown-item :command="activeConv.is_muted ? 'unmute' : 'mute'">
                                                <el-icon><MuteNotification v-if="!activeConv.is_muted" /><Bell v-else /></el-icon>
                                                {{ activeConv.is_muted ? '取消免打扰' : '消息免打扰' }}
                                            </el-dropdown-item>
                                            <el-dropdown-item divided command="leave">
                                                <el-icon><Close /></el-icon> <span style="color:#e6a23c">离开圈子</span>
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-button text size="small" @click="handleDeleteConv"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <div class="chat-messages-wrap">
                            <div class="messages-area" ref="channelMsgAreaRef">
                                <div v-if="loadingChannelMessages" class="load-more"><el-icon class="is-loading"><Loading /></el-icon></div>
                                <!-- 消息搜索 -->
                                <div v-if="channelSearchActive" class="channel-search-bar">
                                    <el-input v-model="channelSearchQuery" placeholder="搜索消息..." size="small" clearable prefix-icon="Search" @keydown.enter="searchChannelMessages" style="flex:1" />
                                    <el-button size="small" type="primary" @click="searchChannelMessages">搜索</el-button>
                                    <el-button size="small" @click="channelSearchActive = false; channelSearchResults = []; channelSearchQuery = ''">取消</el-button>
                                </div>
                                <div v-if="channelSearchResults.length" class="channel-search-results">
                                    <div style="font-size:12px;color:#999;padding:4px 0;border-bottom:1px solid #eee">找到 {{ channelSearchResults.length }} 条结果</div>
                                    <div v-for="r in channelSearchResults" :key="r.id" class="channel-search-result-item" @click="scrollToChannelMsg(r)">
                                        <span style="font-size:11px;color:#409eff">{{ r.user?.name || '用户' }}</span>
                                        <span style="font-size:12px;color:#333;margin-left:6px">{{ r.content?.substring(0, 60) }}</span>
                                        <span style="font-size:10px;color:#999;margin-left:auto">{{ formatTime(r.created_at) }}</span>
                                    </div>
                                </div>
                                <!-- 置顶消息横幅 -->
                                <div v-if="channelPinnedMessages.length" class="pinned-banner" @click="showPinnedMessages = true">
                                    <div class="pinned-header">📌 置顶消息 <span class="pinned-count">{{ channelPinnedMessages.length }}</span></div>
                                    <div class="pinned-list">
                                        <div v-for="pm in channelPinnedMessages.slice(0, 3)" :key="pm.id" class="pinned-item">
                                            <span class="pinned-sender">{{ pm.user?.name || '用户' }}:</span>
                                            <span class="pinned-content">{{ pm.content?.substring(0, 40) }}</span>
                                        </div>
                                        <div v-if="channelPinnedMessages.length > 3" class="pinned-more">还有 {{ channelPinnedMessages.length - 3 }} 条置顶消息</div>
                                    </div>
                                </div>
                                <!-- 时间分组模板 -->
                                <template v-for="(group, gi) in groupedChannelMessages" :key="gi">
                                    <div class="time-separator">
                                        <span class="time-separator-text">{{ group.label }}</span>
                                    </div>
                                    <div v-for="msg in group.messages" :key="msg.id"
                                        class="msg-item" :class="{ 'msg-self': msg.user_id === myId, 'msg-pinned': msg.is_pinned }"
                                        @contextmenu.prevent="openChannelMsgContextMenu($event, msg)">
                                        <div class="msg-avatar" @click="showUserProfile(msg.user)">
                                            <img v-if="msg.user?.avatar_url" :src="msg.user.avatar_url" class="msg-avatar-img" @error="$event.target.style.display='none'" />
                                            <span v-else>{{ msg.user?.name?.charAt(0) || '?' }}</span>
                                        </div>
                                        <div class="msg-bubble" :class="{ 'msg-recalled': msg.is_recalled }">
                                            <div v-if="msg.is_pinned" class="pinned-indicator">📌 置顶</div>
                                            <div v-if="msg.is_recalled" class="msg-recalled-text">{{ msg.user_id === myId ? '你' : (msg.user?.name || '对方') }} 撤回了一条消息</div>
                                            <template v-else>
                                                <div class="msg-sender">{{ msg.user?.name || '用户' }}</div>
                                                <!-- 引用消息 -->
                                                <div v-if="msg.channel_reply_to" class="msg-reply" @click.stop>
                                                    <div class="reply-sender">{{ msg.channel_reply_to.user?.name || '用户' }}</div>
                                                    <div class="reply-text">{{ msg.channel_reply_to.content?.substring(0, 60) }}</div>
                                                </div>
                                                <!-- 图片消息 -->
                                                <div v-if="msg.message_type === 'image' && msg.content" class="msg-image">
                                                    <img :src="msg.attachments?.[0]?.url || msg.content" class="chat-image" @click="previewImage(msg.attachments?.[0]?.url || msg.content)" />
                                                </div>
                                                <!-- 视频消息 -->
                                                <div v-else-if="msg.message_type === 'video' && msg.attachments?.[0]?.url" class="msg-video">
                                                    <video :src="msg.attachments[0].url" controls class="chat-video" />
                                                </div>
                                                <!-- 文件消息 -->
                                                <div v-else-if="msg.message_type === 'file' && msg.attachments?.[0]" class="msg-file" @click="previewAttachment(msg.attachments[0])">
                                                    <div class="file-icon">📎</div>
                                                    <div class="file-info">
                                                        <div class="file-name">{{ msg.attachments[0].name || '文件' }}</div>
                                                        <div class="file-meta">{{ formatFileSize(msg.attachments[0].size) }}</div>
                                                    </div>
                                                </div>
                                                <!-- 语音消息 -->
                                                <div v-else-if="msg.message_type === 'voice' && msg.content" class="msg-voice-wrap">
                                                    <div class="msg-voice" :class="{ 'msg-voice-self': msg.user_id === myId }" @click="playChannelVoice(msg)">
                                                        <el-icon :size="20" style="margin-right:6px">
                                                            <CaretRight v-if="channelVoicePlayingId !== msg.id" /><Loading v-else />
                                                        </el-icon>
                                                        <div class="voice-wave">
                                                            <span v-for="i in 20" :key="i" class="voice-bar" :style="{ height: (8 + Math.sin(i * 0.8) * 6) + 'px' }"></span>
                                                        </div>
                                                        <span class="voice-duration">{{ msg.metadata?.duration || msg.attachments?.[0]?.duration || 0 }}″</span>
                                                    </div>
                                                </div>
                                                <!-- 文本消息 -->
                                                <div v-else class="msg-text">{{ msg.content }}</div>
                                                <!-- 翻译结果 -->
                                                <div v-if="msg.translated_text" class="msg-translated">
                                                    <el-divider style="margin:4px 0" />
                                                    <div style="font-size:11px;color:#909399;margin-bottom:2px">🌐 翻译</div>
                                                    <div>{{ msg.translated_text }}</div>
                                                </div>
                                                <!-- 附件列表 -->
                                                <div v-if="msg.attachments?.length && msg.message_type === 'text'" class="msg-attachments">
                                                    <div v-for="(att, i) in msg.attachments" :key="i" class="msg-attachment-item">
                                                        <a :href="att.url || att" target="_blank">📎 {{ att.name || '附件 ' + (i+1) }}</a>
                                                    </div>
                                                </div>
                                            </template>
                                            <div class="msg-footer">
                                                <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                                                <span v-if="msg.user_id === myId && !msg.is_recalled" class="msg-status" :class="msg._read ? 'status-read' : 'status-sent'">
                                                    {{ msg._read ? '已读' : '已发送' }}
                                                </span>
                                            </div>
                                            <!-- 消息操作按钮 -->
                                            <div v-if="!msg.is_recalled && activeConv.is_member" class="msg-actions channel-msg-actions">
                                                <el-button text size="small" @click.stop="channelReplyToMsg = msg" title="回复">
                                                    <el-icon><ChatDotRound /></el-icon>
                                                </el-button>
                                                <el-button text size="small" @click.stop="copyChannelMsg(msg)" title="复制">
                                                    <el-icon><CopyDocument /></el-icon>
                                                </el-button>
                                                <el-button text size="small" @click.stop="channelToggleFavorite(msg)" :title="msg.is_favorited ? '取消收藏' : '收藏'">
                                                    <el-icon :color="msg.is_favorited ? '#e6a23c' : ''"><StarFilled v-if="msg.is_favorited" /><Star v-else /></el-icon>
                                                </el-button>
                                                <el-button text size="small" @click.stop="channelForwardMsg(msg)" title="转发">
                                                    <el-icon><Share /></el-icon>
                                                </el-button>
                                                <el-button v-if="!msg.is_pinned && myRoleInChannel !== 'member'" text size="small" @click.stop="pinChannelMessage(msg)" title="置顶">
                                                    <el-icon><StarFilled /></el-icon>
                                                </el-button>
                                                <el-button v-else-if="msg.is_pinned && myRoleInChannel !== 'member'" text size="small" type="warning" @click.stop="unpinChannelMessage(msg)" title="取消置顶">
                                                    <el-icon><StarFilled /></el-icon>
                                                </el-button>
                                                <el-button v-if="msg.user_id === myId" text size="small" @click.stop="recallChannelMessage(msg)" title="撤回">
                                                    <el-icon><RefreshLeft /></el-icon>
                                                </el-button>
                                                <el-button text size="small" type="danger" @click.stop="deleteChannelMessage(msg)" title="删除">
                                                    <el-icon><Delete /></el-icon>
                                                </el-button>
                                                <el-button v-if="msg.user_id !== myId" text size="small" @click.stop="channelReportMsg(msg)" title="举报">
                                                    <el-icon><Warning /></el-icon>
                                                </el-button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div v-if="!channelMessages.length && !loadingChannelMessages" class="empty-chat" style="padding:60px 0"><el-empty description="暂无消息" :image-size="60" /></div>
                            </div>
                        </div>
                        <div v-if="activeConv.is_member !== false" class="chat-input-area">
                            <!-- 频道回复预览 -->
                            <div v-if="channelReplyToMsg" class="reply-preview">
                                <div class="reply-preview-content">
                                    <span class="reply-preview-label">回复 {{ channelReplyToMsg.user?.name || '用户' }}:</span>
                                    <span class="reply-preview-text">{{ channelReplyToMsg.content?.substring(0, 60) }}</span>
                                </div>
                                <el-button text size="small" @click="channelReplyToMsg = null"><el-icon><Close /></el-icon></el-button>
                            </div>
                            <!-- 频道消息输入 -->
                            <div class="channel-input-area">
                                <el-input v-model="channelInput" type="textarea" :rows="2" placeholder="发送到圈子..." @keydown.enter.exact.prevent="sendChannelMessage" class="channel-input-field" />
                                <div class="channel-input-toolbar">
                                    <div class="channel-toolbar-left">
                                        <el-upload :show-file-list="false" :http-request="uploadChannelFile" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.zip" style="flex-shrink:0">
                                            <el-button text size="small" title="上传文件/图片"><el-icon><Picture /></el-icon></el-button>
                                        </el-upload>
                                        <el-button text size="small" @click="toggleChannelVoiceRecord" :title="channelIsRecording ? '停止录音' : '语音消息'" :type="channelIsRecording ? 'danger' : 'default'" style="flex-shrink:0">
                                            <template v-if="channelIsRecording">{{ channelRecordingDuration }}s</template>
                                            <el-icon v-else><Microphone /></el-icon>
                                        </el-button>
                                        <el-button text size="small" @click="toggleChannelVideoRecord" :title="channelIsVideoRecording ? '停止录像' : '录制视频'" :type="channelIsVideoRecording ? 'danger' : 'default'" style="flex-shrink:0">
                                            <template v-if="channelIsVideoRecording">{{ channelVideoRecordingDuration }}s</template>
                                            <el-icon v-else><VideoCamera /></el-icon>
                                        </el-button>
                                        <el-popover trigger="click" :width="280">
                                            <template #reference>
                                                <el-button text size="small" title="表情"><el-icon style="font-size:18px">😊</el-icon></el-button>
                                            </template>
                                            <div class="channel-emoji-picker">
                                                <div class="emoji-quick-row">
                                                    <span v-for="e in commonEmojis.slice(0, 40)" :key="e" class="emoji-option" @click="insertChannelEmoji(e)">{{ e }}</span>
                                                </div>
                                                <div v-if="customEmojis.length" class="emoji-custom-row">
                                                    <div class="emoji-section-label">自定义</div>
                                                    <span v-for="e in customEmojis.slice(0, 20)" :key="e.id" class="emoji-option" @click="insertChannelEmoji(':' + e.shortcode + ':')">
                                                        <img :src="e.image_url" :title="':' + e.shortcode + ':'" class="custom-emoji-inline" />
                                                    </span>
                                                </div>
                                            </div>
                                        </el-popover>
                                    </div>
                                    <div class="channel-toolbar-right">
                                        <span class="text-muted">Enter 发送</span>
                                        <el-button type="primary" size="small" :loading="channelSending" @click="sendChannelMessage" :disabled="!channelInput.trim() && !channelPendingFiles.length">发送</el-button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══════ 公众号文章列表视图 ═══════ -->
                    <template v-else-if="activeConv.is_oa">
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <el-button v-if="isMobile" text size="small" @click="activeConv = null" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                                <div class="oa-header-avatar">
                                    <img v-if="activeConv.oa_avatar" :src="activeConv.oa_avatar" class="oa-header-img" />
                                    <span v-else class="oa-header-text">{{ activeConv.oa_name?.charAt(0) || '?' }}</span>
                                </div>
                                <div class="oa-header-info">
                                    <span class="chat-title">{{ activeConv.oa_name }}</span>
                                    <span class="oa-header-desc">{{ activeConv.oa_description }}</span>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <el-button v-if="selectedOaAccount?.owner_id === myId" text size="small" @click="openOaSettings" title="公众号设置">
                                    <el-icon><Setting /></el-icon> 设置
                                </el-button>
                                <el-button text size="small" @click="handleDeleteConv"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <!-- 公众号管理面板（仅号主可见） -->
                        <div v-if="selectedOaAccount?.owner_id === myId && oaDashboard" class="oa-manage-bar">
                            <div class="oa-stats-row">
                                <div class="oa-stat-item" title="关注粉丝">
                                    <span class="oa-stat-num">{{ oaDashboard.followers_count }}</span>
                                    <span class="oa-stat-label">粉丝</span>
                                </div>
                                <div class="oa-stat-item" title="已发布文章">
                                    <span class="oa-stat-num">{{ oaDashboard.articles_count }}</span>
                                    <span class="oa-stat-label">文章</span>
                                </div>
                                <div class="oa-stat-item" title="总点赞">
                                    <span class="oa-stat-num">{{ oaDashboard.total_likes }}</span>
                                    <span class="oa-stat-label">点赞</span>
                                </div>
                                <div class="oa-stat-item" title="总阅读">
                                    <span class="oa-stat-num">{{ oaDashboard.total_reads }}</span>
                                    <span class="oa-stat-label">阅读</span>
                                </div>
                                <div class="oa-stat-item" title="总分享">
                                    <span class="oa-stat-num">{{ oaDashboard.total_shares }}</span>
                                    <span class="oa-stat-label">分享</span>
                                </div>
                                <div class="oa-stat-item" title="总评论">
                                    <span class="oa-stat-num">{{ oaDashboard.total_comments }}</span>
                                    <span class="oa-stat-label">评论</span>
                                </div>
                            </div>
                            <!-- 趋势图表 -->
                            <div class="oa-trends-row" v-if="oaDashboard.trends">
                                <div class="oa-trend-chart" v-for="(trend, key) in trendCharts" :key="key">
                                    <div class="oa-trend-header">
                                        <span class="oa-trend-title">{{ trend.label }}</span>
                                        <span class="oa-trend-num">{{ trend.total }}</span>
                                    </div>
                                    <div class="oa-trend-bars">
                                        <div v-for="(day, di) in (oaDashboard.trends?.[trend.key] || [])" :key="di" class="oa-trend-bar-wrap" :title="day.date + ': ' + day.count">
                                            <div class="oa-trend-bar" :style="{height: Math.max(2, (day.count / trendMax(trend.key)) * 36) + 'px', background: trend.color}"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="oa-manage-actions">
                                <el-button size="small" text @click="openOaEditor" title="创作新文章">
                                    <el-icon><EditPen /></el-icon> 创作
                                </el-button>
                                <el-button size="small" text @click="showOaArticleManager = true" title="管理文章">
                                    <el-icon><Document /></el-icon> 文章管理
                                </el-button>
                                <el-button size="small" text @click="loadOaComments" title="管理评论">
                                    <el-icon><ChatLineSquare /></el-icon> 评论管理
                                    <el-tag v-if="oaCommentCount > 0" size="small" type="danger" style="margin-left:4px">{{ oaCommentCount }}</el-tag>
                                </el-button>
                                <el-button size="small" text @click="showOaFollowers = true" title="粉丝管理">
                                    👥 粉丝
                                </el-button>
                                <el-button size="small" text @click="showOaAutoReply = true" title="自动回复">
                                    🤖 自动回复
                                </el-button>
                                <el-button size="small" text @click="showOaMenuManager = true; loadOaMenus()" title="自定义菜单">
                                    📋 菜单
                                </el-button>
                                <el-button size="small" text @click="showOaMaterialManager = true; loadOaMaterials()" title="素材管理">
                                    📁 素材
                                </el-button>
                                <el-badge :is-dot="oaUnreadCount > 0" style="line-height:1">
                                    <el-button size="small" text @click="showOaMessages = true; loadOaConversations()" title="消息">
                                        💬 消息 <span v-if="oaUnreadCount > 0" style="color:#f56c6c;font-weight:600">({{ oaUnreadCount }})</span>
                                    </el-button>
                                </el-badge>
                                <el-button size="small" text @click="showOaQrCode = true; loadOaQrCode()" title="二维码">
                                    📱 二维码
                                </el-button>
                            </div>
                        </div>
                        <div class="chat-messages-wrap">
                            <!-- 文章列表 -->
                            <div v-if="!showOaArticleDetail" class="messages-area oa-masonry-area" v-loading="loadingOaArticles" @scroll="onOaScroll">
                                <!-- 搜索与排序工具栏 -->
                                <div class="oa-article-toolbar">
                                    <el-input v-model="oaArticleSearch" placeholder="搜索标题、摘要或全文..." size="small" clearable prefix-icon="Search" style="width:240px" />
                                    <el-select v-model="oaArticleSort" size="small" style="width:100px" @change="loadOaArticles(activeConv.oa_account_id)">
                                        <el-option label="最新" value="latest" />
                                        <el-option label="最热" value="hot" />
                                    </el-select>
                                </div>
                                <!-- 为你推荐 -->
                                <div v-if="oaRecommendations.length" class="oa-recommend-section">
                                    <div class="oa-recommend-header">
                                        <span>🎯 为你推荐</span>
                                        <el-button text size="small" @click="loadRecommendations" title="换一批">🔄 换一批</el-button>
                                    </div>
                                    <div class="oa-recommend-scroll">
                                        <div v-for="rec in oaRecommendations" :key="rec.id" class="oa-recommend-card" @click="openOaArticleRecommend(rec)">
                                            <div v-if="getCoverImage(rec)" class="oa-rec-cover">
                                                <img :src="getCoverImage(rec)" />
                                            </div>
                                            <div class="oa-rec-body">
                                                <div class="oa-rec-title">{{ rec.title }}</div>
                                                <div class="oa-rec-meta">
                                                    <span>{{ rec.account?.name }}</span>
                                                    <span>👁️ {{ rec.reads_count }}</span>
                                                </div>
                                                <div v-if="rec.match_tags?.length" class="oa-rec-tags">
                                                    <el-tag v-for="t in rec.match_tags.slice(0,3)" :key="t" size="small">{{ t }}</el-tag>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- 合集筛选 -->
                                <div v-if="oaCollections.length" class="oa-collection-filter">
                                    <span class="oa-tag-filter-item"
                                        :class="{ active: !selectedOaCollection }"
                                        @click="selectedOaCollection = ''">📚 全部文章</span>
                                    <span v-for="col in oaCollections" :key="col.id"
                                        class="oa-tag-filter-item oa-collection-item"
                                        :class="{ active: selectedOaCollection === col.id }"
                                        @click="selectedOaCollection = selectedOaCollection === col.id ? '' : col.id">
                                        📁 {{ col.name }}
                                        <span class="oa-col-count">{{ col.articles_count }}</span>
                                    </span>
                                </div>
                                <!-- 标签筛选 -->
                                <div v-if="oaTags.length" class="oa-tag-filter">
                                    <span class="oa-tag-filter-item"
                                        :class="{ active: !selectedOaTag }"
                                        @click="selectedOaTag = ''">全部</span>
                                    <span v-for="tag in oaTags" :key="tag"
                                        class="oa-tag-filter-item"
                                        :class="{ active: selectedOaTag === tag }"
                                        @click="selectedOaTag = selectedOaTag === tag ? '' : tag">
                                        {{ tag }}
                                    </span>
                                </div>
                                <!-- 瀑布流 -->
                                <div v-if="filteredOaArticles.length" class="oa-article-masonry">
                                    <div v-for="(art, idx) in filteredOaArticles" :key="art.id" class="oa-article-card" :class="{ 'oa-article-read': art.is_read }" :style="{ animationDelay: (idx % 6) * 0.05 + 's' }" @click="openOaArticleDetail(art)">
                                        <div v-if="getCoverImage(art)" class="oa-article-cover">
                                            <img :src="getCoverImage(art)" class="oa-cover-img" loading="lazy" @error="$event.target.style.display='none'" />
                                            <span v-if="art.is_read" class="oa-read-badge">已读</span>
                                            <span v-if="art.in_reading_list" class="oa-reading-list-badge" @click.stop="toggleReadingList(art)">🔖</span>
                                        </div>
                                        <div v-else class="oa-article-cover oa-cover-noimg">
                                            <span class="oa-noimg-icon">📄</span>
                                            <span v-if="art.is_read" class="oa-read-badge">已读</span>
                                        </div>
                                        <div class="oa-article-body">
                                            <div class="oa-article-title">
                                                <el-tag v-if="art.is_pinned" size="small" type="warning" style="margin-right:4px">📌 置顶</el-tag>
                                                <span :class="{ 'oa-title-unread': !art.is_read }">{{ art.title }}</span>
                                            </div>
                                            <div v-if="art.summary" class="oa-article-summary">{{ art.summary }}</div>
                                            <div class="oa-article-meta-row">
                                                <span class="oa-article-author" v-if="art.author">
                                                    <img v-if="art.author.avatar" :src="art.author.avatar" class="oa-author-avatar" />
                                                    {{ art.author.name }}
                                                </span>
                                                <span class="oa-article-stats-list">
                                                    <span title="阅读">👁️ {{ art.reads_count || 0 }}</span>
                                                    <span title="评论">💬 {{ art.comments_count || 0 }}</span>
                                                    <span title="点赞">🤍 {{ art.likes_count || 0 }}</span>
                                                    <span title="收藏">☆ {{ art.favorites_count || 0 }}</span>
                                                </span>
                                                <span class="oa-article-time">{{ formatTime(art.published_at) }}</span>
                                            </div>
                                            <div v-if="art.tags?.length" class="oa-article-tags">
                                                <el-tag v-for="t in art.tags" :key="t" size="small">{{ t }}</el-tag>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="!filteredOaArticles.length && !loadingOaArticles" class="empty-chat" style="padding:60px 0"><el-empty :description="oaArticleSearch ? '未找到匹配文章' : '暂无文章'" :image-size="60" /></div>
                                <!-- 无限滚动加载 -->
                                <div v-if="oaArticles.length && oaArticles.length < oaArticleTotal" class="oa-load-more">
                                    <el-icon v-if="loadingMoreOaArticles" class="is-loading"><Loading /></el-icon>
                                    <span v-else style="color:#999;font-size:12px">下拉加载更多</span>
                                </div>
                                <div v-if="oaArticles.length && oaArticles.length >= oaArticleTotal && oaArticleTotal > 0" class="oa-load-more oa-load-end">
                                    — 已经到底了 —
                                </div>
                            </div>
                            <!-- 文章详情视图 -->
                            <div v-else-if="oaArticleDetail" class="oa-article-detail-view">
                                <div class="oa-article-detail-header">
                                    <div class="oa-detail-top-row">
                                        <button class="oa-detail-back" @click="showOaArticleDetail = false">← 返回列表</button>
                                        <div class="oa-detail-top-actions">
                                            <el-dropdown trigger="click" @command="shareOaArticleTo">
                                                <el-button text size="small">
                                                    <el-icon><Share /></el-icon> 分享 <el-icon><ArrowDown /></el-icon>
                                                </el-button>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item command="chat">💬 好友/聊天</el-dropdown-item>
                                                        <el-dropdown-item command="plaza">🌐 广场</el-dropdown-item>
                                                        <el-dropdown-item command="channel">📡 圈子</el-dropdown-item>
                                                        <el-dropdown-item command="wechat" divided>💚 微信</el-dropdown-item>
                                                        <el-dropdown-item command="weibo">🔴 微博</el-dropdown-item>
                                                        <el-dropdown-item command="copy">🔗 复制链接</el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </div>
                                    </div>
                                    <h1 class="oa-detail-title">{{ oaArticleDetail.title }}</h1>
                                    <div class="oa-detail-meta-row">
                                        <el-tag v-if="oaArticleDetail.is_original" size="small" type="danger" style="margin-right:6px">原创</el-tag>
                                        <span class="oa-detail-author">
                                            <img v-if="oaArticleDetail.author?.avatar" :src="oaArticleDetail.author.avatar" class="oa-detail-author-avatar" />
                                            {{ oaArticleDetail.author?.name || '匿名' }}
                                        </span>
                                        <span class="oa-detail-sep">·</span>
                                        <span class="oa-detail-acc-name">{{ oaArticleDetail.account?.name }}</span>
                                        <el-button v-if="oaArticleDetail.is_following === false" size="small" text type="primary" @click="handleOaFollow(oaArticleDetail)" style="margin-left:4px;font-size:12px">+ 关注</el-button>
                                        <el-button v-else-if="oaArticleDetail.is_following === true" size="small" text type="default" @click="handleOaUnfollow(oaArticleDetail)" style="margin-left:4px;font-size:12px">已关注</el-button>
                                        <span class="oa-detail-sep">·</span>
                                        <span class="oa-detail-time">{{ formatFullTime(oaArticleDetail.published_at) }}</span>
                                    </div>
                                </div>
                                <div class="oa-article-detail-body">
                                    <div class="oa-detail-cover" v-if="getCoverImage(oaArticleDetail)">
                                        <img :src="getCoverImage(oaArticleDetail)" class="oa-detail-cover-img" @error="$event.target.style.display='none'" />
                                    </div>
                                    <div class="oa-detail-content" v-html="oaArticleDetail.content"></div>
                                    <div v-if="oaArticleDetail.tags?.length" class="oa-detail-tags">
                                        <el-tag v-for="t in oaArticleDetail.tags" :key="t" size="small">{{ t }}</el-tag>
                                    </div>
                                    <!-- 操作栏 -->
                                    <div class="oa-detail-actions-bar">
                                        <el-button text @click="handleOaLike" :type="oaArticleDetail.is_liked ? 'primary' : 'default'">
                                            🤍 {{ oaArticleDetail.likes_count || 0 }}
                                        </el-button>
                                        <el-button text disabled>👁️ {{ oaArticleDetail.reads_count || 0 }}</el-button>
                                        <el-button text @click="toggleOaFavorite(oaArticleDetail)" :type="oaArticleDetail.is_favorited ? 'warning' : 'default'">
                                            {{ oaArticleDetail.is_favorited ? '⭐' : '☆' }} 收藏
                                        </el-button>
                                        <el-button text @click="openOaArticleStandalone(oaArticleDetail)">
                                            <el-icon><Share /></el-icon> 独立页面
                                        </el-button>
                                        <el-button text @click="toggleOfflineSave(oaArticleDetail)" :type="oaArticleDetail._offlineSaved ? 'primary' : 'default'">
                                            📥 {{ oaArticleDetail._offlineSaved ? '已离线' : '离线保存' }}
                                        </el-button>
                                        <el-button v-if="(oaArticleDetail.author?.id || oaArticleDetail.user_id) !== myId" text type="warning" @click="reportOaArticle(oaArticleDetail)">
                                            ⚠️ 举报
                                        </el-button>
                                    </div>
                                    <!-- 上一篇/下一篇 -->
                                    <div class="oa-detail-prev-next">
                                        <div v-if="oaArticleDetail.prev_article" class="oa-pn-item oa-pn-prev" @click="openOaArticleDetailById(oaArticleDetail.prev_article.id)">
                                            <span class="oa-pn-label">← 上一篇</span>
                                            <span class="oa-pn-title">{{ oaArticleDetail.prev_article.title }}</span>
                                        </div>
                                        <div v-if="oaArticleDetail.next_article" class="oa-pn-item oa-pn-next" @click="openOaArticleDetailById(oaArticleDetail.next_article.id)">
                                            <span class="oa-pn-label">下一篇 →</span>
                                            <span class="oa-pn-title">{{ oaArticleDetail.next_article.title }}</span>
                                        </div>
                                    </div>
                                    <!-- 推荐文章（多样式） -->
                                    <div v-if="oaArticleDetail.related_articles?.length" class="oa-detail-related">
                                        <h4>📖 推荐阅读</h4>
                                        <div class="oa-related-grid">
                                            <div v-for="r in oaArticleDetail.related_articles" :key="r.id" class="oa-related-card" @click="openOaArticleDetailById(r.id)">
                                                <div v-if="r.cover_image" class="oa-related-cover">
                                                    <img :src="r.cover_image" />
                                                </div>
                                                <div v-else class="oa-related-cover oa-related-cover-text">📝</div>
                                                <div class="oa-related-info">
                                                    <div class="oa-related-title">{{ r.title }}</div>
                                                    <div v-if="r.summary" class="oa-related-desc">{{ r.summary.substring(0, 60) }}</div>
                                                    <div class="oa-related-time">{{ formatTime(r.published_at) }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- 读者也在读 -->
                                    <div v-if="oaArticleDetail.also_read_articles?.length" class="oa-detail-related">
                                        <h4>🤝 读者也在读</h4>
                                        <div class="oa-related-grid">
                                            <div v-for="r in oaArticleDetail.also_read_articles" :key="r.id" class="oa-related-card" @click="openOaArticleDetailById(r.id)">
                                                <div v-if="getCoverImage(r)" class="oa-related-cover">
                                                    <img :src="getCoverImage(r)" />
                                                </div>
                                                <div v-else class="oa-related-cover oa-related-cover-text">📝</div>
                                                <div class="oa-related-info">
                                                    <div class="oa-related-title">{{ r.title }}</div>
                                                    <div v-if="r.account?.name" class="oa-related-desc" style="color:#409eff">{{ r.account.name }}</div>
                                                    <div class="oa-related-time">{{ formatTime(r.published_at) }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- 评论区 -->
                                    <div class="oa-detail-comments">
                                        <h4>💬 评论 ({{ oaArticleDetail.comments?.length || 0 }})</h4>
                                        <div class="oa-detail-comment-input">
                                            <el-input v-model="newCommentText" type="textarea" :rows="2" placeholder="写下你的评论..." maxlength="1000" />
                                            <el-button size="small" type="primary" style="margin-top:6px" :loading="submittingComment" @click="submitArticleComment(oaArticleDetail.id)">发表评论</el-button>
                                        </div>
                                        <div v-if="oaArticleDetail.comments?.length" class="oa-detail-comment-list">
                                            <div v-for="c in oaArticleDetail.comments" :key="c.id" class="oa-detail-comment-item">
                                                <img v-if="c.user?.avatar" :src="c.user.avatar" class="oa-detail-comment-avatar" />
                                                <span v-else class="oa-detail-comment-avatar-text">{{ c.user?.name?.charAt(0) || '?' }}</span>
                                                <div class="oa-detail-comment-body">
                                                    <div class="oa-detail-comment-author">{{ c.user?.name || '匿名' }} <span v-if="c.user?.region" class="oa-comment-region">{{ c.user.region }}</span> <span class="oa-detail-comment-time">{{ formatTime(c.created_at) }}</span>
                                                        <el-button v-if="c.user_id === myId" text size="small" type="danger" @click.stop="deleteOaComment(c)" style="font-size:11px;padding:0 4px;margin-left:4px">删除</el-button>
                                                    </div>
                                                    <div class="oa-detail-comment-text">{{ c.content }}</div>
                                                    <div class="oa-detail-comment-footer" style="margin-top:2px">
                                                        <el-button text size="small" @click.stop="startDetailReply(c)" style="font-size:12px;padding:0;height:auto;color:#999">💬 回复</el-button>
                                                    </div>
                                                    <div v-if="c.replies?.length" class="oa-detail-comment-replies">
                                                        <div v-for="r in c.replies" :key="r.id" class="oa-detail-comment-reply">
                                                            <span class="oa-reply-author">{{ r.user?.name }}：</span>
                                                            <span class="oa-reply-text">{{ r.content }}</span>
                                                            <span class="oa-detail-comment-time">{{ formatTime(r.created_at) }}</span>
                                                        </div>
                                                    </div>
                                                    <!-- 回复输入框 -->
                                                    <div v-if="detailReplyingTo === c.id" class="oa-detail-reply-box" style="margin-top:6px;display:flex;gap:6px">
                                                        <el-input v-model="detailReplyText" placeholder="输入回复..." size="small" style="flex:1" maxlength="1000" />
                                                        <el-button size="small" type="primary" :loading="detailReplying" @click.stop="submitDetailReply(c)">发送</el-button>
                                                        <el-button size="small" @click.stop="detailReplyingTo = null">取消</el-button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else style="text-align:center;padding:16px;color:#999;font-size:13px">暂无评论，来说两句吧</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══════ 客服工作台 - 会话视图 ═══════ -->
                    <!-- ═══════ 广场 Feed（小红书信息流） ═══════ -->
                    <template v-else-if="activeConv.is_plaza">
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <el-button v-if="isMobile" text size="small" @click="activeConv = null" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                                <span class="chat-title">🌐 广场</span>
                                <el-tag size="small" type="" style="margin-left:8px">
                                    {{ {all:'全部',hot:'热门',trending:'推荐'}[activeConv.plaza_category] || '全部' }}
                                </el-tag>
                            </div>
                            <div class="chat-actions">
                                <el-button text size="small" @click="loadPlazaFeed" title="刷新"><el-icon><RefreshLeft /></el-icon></el-button>
                                <el-button text size="small" @click="activeConv = null"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <!-- 快速发帖 - 固定在顶部 -->
                        <div class="plaza-quick-post">
                            <img v-if="myAvatar" :src="myAvatar" class="plaza-quick-post-avatar-img" />
                            <div v-else class="plaza-quick-post-avatar">{{ myName?.charAt(0) || '?' }}</div>
                            <div class="plaza-quick-post-input">
                                <template v-if="!showQuickPost">
                                    <span class="plaza-quick-post-placeholder" @click="showQuickPost = true">📝 分享新鲜事...</span>
                                    <el-button size="small" type="primary" class="plaza-quick-post-btn" @click="showQuickPost = true">发表</el-button>
                                </template>
                                <template v-else>
                                    <div class="plaza-quick-expanded-wrap">
                                        <div class="plaza-quick-expanded-header">
                                            <span class="plaza-quick-expand-title">📝 发表动态</span>
                                            <el-button text size="small" @click="showQuickPost = false; quickPostText = ''" title="关闭">
                                                <el-icon><Close /></el-icon>
                                            </el-button>
                                        </div>
                                        <el-input v-model="quickPostText" type="textarea" :rows="3" placeholder="分享新鲜事..." maxlength="5000" resize="none" @keydown.enter.ctrl="submitQuickPost" />
                                        <div class="plaza-quick-actions">
                                            <div class="plaza-quick-left">
                                                <el-button size="small" text @click="openFullEditor" title="完整编辑器">
                                                    <el-icon><Plus /></el-icon> 更多
                                                </el-button>
                                                <span class="plaza-quick-count">{{ quickPostText.length }}/5000</span>
                                            </div>
                                            <div class="plaza-quick-right">
                                                <el-button size="small" text @click="showQuickPost = false; quickPostText = ''">取消</el-button>
                                                <el-button size="small" type="primary" :loading="quickPosting" @click="submitQuickPost" :disabled="!quickPostText.trim()">发表</el-button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                        <div class="chat-messages-wrap">
                            <div class="plaza-feed-container" v-loading="plazaLoading" @scroll="onPlazaScroll" @touchstart="onTouchStart" @touchmove="onTouchMove" @touchend="onTouchEnd" ref="plazaFeedRef">
                                <!-- 下拉刷新提示 -->
                                <div v-if="pullRefreshHint" class="plaza-pull-hint">{{ pullRefreshHint }}</div>
                                <!-- 瀑布流信息流 -->
                                <div v-if="plazaPosts.length" class="plaza-feed-masonry">
                                    <div v-for="(p, idx) in plazaPosts" :key="p.id" class="plaza-feed-card" :style="{ animationDelay: (idx % 6) * 0.05 + 's' }" @click="openPlazaDetail(p)">
                                        <!-- 图片区域 -->
                                        <div class="plaza-card-img-wrap" :class="{ 'no-img': !p.images?.length }">
                                            <img v-if="p.images?.length" :src="p.images[0]" class="plaza-card-img" loading="lazy" @error="$event.target.style.display='none'" />
                                            <div v-else class="plaza-card-noimg">📝</div>
                                            <div v-if="p.images?.length > 1" class="plaza-card-img-count">+{{ p.images.length - 1 }}</div>
                                            <!-- 视频标记 -->
                                            <div v-if="p.video" class="plaza-card-video-badge">▶</div>
                                        </div>
                                        <!-- 文字内容 -->
                                        <div class="plaza-card-body">
                                            <div class="plaza-card-tpl-badge" v-if="p.template && p.template !== 'discuss'">
                                                <el-tag size="small" :type="tplTagType(p.template)" style="font-size:10px;padding:0 4px;height:18px;line-height:18px">
                                                    {{ tplLabel(p.template) }}
                                                </el-tag>
                                            </div>
                                            <div class="plaza-card-text" v-html="stripHtml(p.content).substring(0, 80) + (stripHtml(p.content).length > 80 ? '...' : '')"></div>
                                            <!-- 视频自动播放 -->
                                            <div v-if="p.video" class="plaza-card-video-preview" @click.stop="openPlazaDetail(p)">
                                                <video :src="p.video" muted playsinline :data-post-id="p.id" class="plaza-auto-video" @mouseenter="playVideo($event.target)" @mouseleave="pauseVideo($event.target)" />
                                            </div>
                                            <!-- 投票预览 -->
                                            <div v-if="p.poll" class="plaza-card-poll" @click.stop>
                                                <div class="plaza-poll-question">📊 {{ p.poll.question }}</div>
                                                <div class="plaza-poll-options">
                                                    <div v-for="opt in (p.poll.options || [])" :key="opt.id" class="plaza-poll-option" :class="{ voted: opt.voted }" @click="plazaVote(p, opt.id)">
                                                        <span class="plaza-poll-label">{{ opt.label }}</span>
                                                        <span class="plaza-poll-pct">{{ opt.percent || 0 }}%</span>
                                                        <div class="plaza-poll-bar-wrap">
                                                            <div class="plaza-poll-bar" :style="{ width: (opt.percent || 0) + '%' }"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="plaza-poll-footer">{{ p.poll.total_votes || 0 }} 票{{ p.poll.voted ? ' · 已投票' : '' }}</div>
                                            </div>
                                            <!-- 标签 -->
                                            <div v-if="p.tags?.length" class="plaza-card-tags" @click.stop>
                                                <span v-for="tag in p.tags" :key="tag.id" class="plaza-card-tag" @click="filterByTag(tag.slug)">#{{ tag.name }}</span>
                                            </div>
                                            <div class="plaza-card-footer">
                                                <div class="plaza-card-user">
                                                    <img v-if="p.user?.avatar_url" :src="p.user.avatar_url" class="plaza-card-avatar" loading="lazy" />
                                                    <span v-else class="plaza-card-avatar-text">{{ p.user?.name?.charAt(0) || '?' }}</span>
                                                    <span class="plaza-card-name">{{ p.user?.name || '用户' }}</span>
                                                    <el-button v-if="p.user?.id && p.user.id !== myId" text size="small" style="font-size:10px;padding:0 4px;height:auto" :type="followingUsers[p.user.id] ? 'default' : 'primary'" @click.stop="toggleFollowUser(p.user)">{{ followingUsers[p.user.id] ? '已关注' : '+ 关注' }}</el-button>
                                                </div>
                                                <div class="plaza-card-actions-bar">
                                                    <span class="plaza-card-action" :class="{ liked: p.is_liked }" @click.stop="plazaToggleLike(p)">
                                                        {{ p.is_liked ? '❤️' : '🤍' }} {{ p.likes_count || 0 }}
                                                    </span>
                                                    <span class="plaza-card-action" :class="{ favorited: p.is_favorited }" @click.stop="plazaToggleFavorite(p)">
                                                        {{ p.is_favorited ? '⭐' : '☆' }} {{ p.favorites_count || 0 }}
                                                    </span>
                                                    <span class="plaza-card-action" @click.stop="openPlazaDetail(p)">
                                                        💬 {{ p.replies_count || 0 }}
                                                    </span>
                                                    <span class="plaza-card-action" @click.stop="shareToChat(p)" title="分享">📤</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="!plazaLoading" class="empty-chat" style="padding:60px 0">
                                    <el-empty description="广场还没有内容，快来发布第一条吧" :image-size="60" />
                                </div>
                                <!-- 加载更多 -->
                                <div v-if="plazaLoadingMore" class="plaza-load-more">
                                    <el-icon class="is-loading"><Loading /></el-icon> 加载中...
                                </div>
                                <div v-else-if="!plazaHasMore && plazaPosts.length > 0" class="plaza-load-more plaza-load-end">
                                    — 已经到底了 —
                                </div>
                            </div>
                            <!-- 内联详情页 -->
                            <div v-if="plazaShowDetailView && plazaShowDetail" class="plaza-detail-page">
                                <div class="plaza-detail-topbar">
                                    <button class="plaza-detail-back" @click="closePlazaDetail">← 返回广场</button>
                                    <div class="plaza-detail-top-actions">
                                        <el-button v-if="(plazaShowDetail.user?.id || plazaShowDetail.user_id) === myId" text size="small" type="danger" @click="plazaDeletePost(plazaShowDetail)">🗑️ 删除</el-button>
                                        <el-button v-if="(plazaShowDetail.user?.id || plazaShowDetail.user_id) === myId" text size="small" @click="openPlazaEdit(plazaShowDetail)">✏️ 编辑</el-button>
                                        <el-button text size="small" @click="openPlazaForward(plazaShowDetail)">📨 转发</el-button>
                                    </div>
                                </div>
                                <div class="plaza-detail-body">
                                    <div class="plaza-detail-user-row">
                                        <img v-if="plazaShowDetail.user?.avatar_url" :src="plazaShowDetail.user.avatar_url" class="plaza-detail-user-avatar" />
                                        <span v-else class="plaza-detail-user-avatar-text">{{ plazaShowDetail.user?.name?.charAt(0) || '?' }}</span>
                                        <div class="plaza-detail-user-meta">
                                            <span class="plaza-detail-user-name">{{ plazaShowDetail.user?.name || '用户' }}</span>
                                            <span class="plaza-detail-post-time">{{ formatTime(plazaShowDetail.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div class="plaza-detail-text" v-html="plazaRenderContent(plazaShowDetail.content)"></div>
                                    <div v-if="plazaShowDetail.images?.length" class="plaza-detail-images-grid">
                                        <div v-for="(img, i) in plazaShowDetail.images" :key="i" class="plaza-detail-image-item" @click="openPlazaLightbox(img, i, plazaShowDetail.images)">
                                            <img :src="img" class="plaza-detail-image" />
                                        </div>
                                    </div>
                                    <div v-if="plazaShowDetail.video" class="plaza-detail-video-section">
                                        <video :src="plazaShowDetail.video" controls class="plaza-detail-video-player"></video>
                                    </div>
                                    <!-- 投票 -->
                                    <div v-if="plazaShowDetail.poll" class="plaza-detail-poll">
                                        <div class="plaza-detail-poll-title">📊 {{ plazaShowDetail.poll.question }}</div>
                                        <div class="plaza-detail-poll-options">
                                            <div v-for="opt in (plazaShowDetail.poll.options || [])" :key="opt.id" class="plaza-detail-poll-option" :class="{ voted: opt.voted }" @click="plazaVote(plazaShowDetail, opt.id)">
                                                <span class="plaza-detail-poll-label">{{ opt.label }}</span>
                                                <span class="plaza-detail-poll-pct">{{ opt.percent || 0 }}%</span>
                                                <div class="plaza-detail-poll-bar-wrap">
                                                    <div class="plaza-detail-poll-bar" :style="{ width: (opt.percent || 0) + '%' }"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="plaza-detail-poll-footer">{{ plazaShowDetail.poll.total_votes || 0 }} 票{{ plazaShowDetail.poll.voted ? ' · 已投票' : '' }}</div>
                                    </div>
                                    <!-- 标签 -->
                                    <div v-if="plazaShowDetail.tags?.length" class="plaza-detail-tags">
                                        <span v-for="tag in plazaShowDetail.tags" :key="tag.id" class="plaza-detail-tag" @click="filterByTag(tag.slug)">#{{ tag.name }}</span>
                                    </div>
                                    <div class="plaza-detail-action-bar">
                                        <el-button :type="plazaShowDetail.is_liked ? 'primary' : 'default'" size="small" @click="plazaToggleLike(plazaShowDetail)">
                                            {{ plazaShowDetail.is_liked ? '❤️' : '🤍' }} {{ plazaShowDetail.likes_count || 0 }}
                                        </el-button>
                                        <el-button disabled size="small">👁️ {{ plazaShowDetail.views_count || 0 }}</el-button>
                                        <el-button :type="plazaShowDetail.is_favorited ? 'warning' : 'default'" size="small" @click="plazaToggleFavorite(plazaShowDetail)">
                                            {{ plazaShowDetail.is_favorited ? '⭐' : '☆' }} 收藏
                                        </el-button>
                                        <el-button v-if="(plazaShowDetail.user?.id || plazaShowDetail.user_id) !== myId" size="small" text type="warning" @click="openPlazaReport(plazaShowDetail)">
                                            ⚠️ 举报
                                        </el-button>
                                    </div>
                                    <el-divider style="margin:16px 0" />
                                    <div class="plaza-detail-comments-section">
                                        <h4 style="margin:0 0 12px 0;font-size:15px">💬 评论 ({{ plazaShowDetail.replies_count || 0 }})</h4>
                                        <div class="plaza-detail-comments" v-loading="plazaCommentsLoading">
                                            <div v-if="plazaComments.length" class="plaza-comment-list">
                                                <div v-for="c in plazaComments" :key="c.id" class="plaza-comment-item">
                                                    <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" class="plaza-comment-avatar" />
                                                    <span v-else class="plaza-comment-avatar-text">{{ c.user?.name?.charAt(0) || '?' }}</span>
                                                    <div class="plaza-comment-body">
                                                        <span class="plaza-comment-author">{{ c.user?.name || '用户' }}</span>
                                                        <span class="plaza-comment-text">{{ c.content }}</span>
                                                        <div class="plaza-comment-footer">
                                                            <span class="plaza-comment-time">{{ formatTime(c.created_at) }}</span>
                                                            <span class="plaza-comment-reply-btn" @click="plazaReplyTo = c">回复</span>
                                                            <span v-if="c.user_id === myId" class="plaza-comment-del-btn" @click="plazaDeleteComment(c)">删除</span>
                                                        </div>
                                                        <div v-if="c.replies?.length" class="plaza-sub-replies">
                                                            <div v-for="r in c.replies" :key="r.id" class="plaza-comment-item plaza-sub-item">
                                                                <img v-if="r.user?.avatar_url" :src="r.user.avatar_url" class="plaza-comment-avatar" />
                                                                <span v-else class="plaza-comment-avatar-text">{{ r.user?.name?.charAt(0) || '?' }}</span>
                                                                <div class="plaza-comment-body">
                                                                    <span class="plaza-comment-author">{{ r.user?.name || '用户' }}</span>
                                                                    <span class="plaza-comment-text">{{ r.content }}</span>
                                                                    <div class="plaza-comment-footer">
                                                                        <span class="plaza-comment-time">{{ formatTime(r.created_at) }}</span>
                                                                        <span class="plaza-comment-reply-btn" @click="plazaReplyTo = c">回复</span>
                                                                        <span v-if="r.user_id === myId" class="plaza-comment-del-btn" @click="plazaDeleteComment(r)">删除</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div v-if="plazaReplyTo?.id === c.id" class="plaza-reply-input">
                                                            <el-input v-model="plazaReplyText" size="small" :placeholder="'回复 ' + (c.user?.name || '用户') + '...'" @keydown.enter.prevent="plazaSubmitReply(c)" />
                                                            <el-button size="small" text @click="plazaReplyTo = null">取消</el-button>
                                                            <el-button size="small" type="primary" :loading="plazaReplySubmitting" @click="plazaSubmitReply(c)">回复</el-button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else style="text-align:center;padding:16px;color:#999;font-size:13px">暂无评论</div>
                                        </div>
                                        <div class="plaza-comment-input" style="margin-top:12px">
                                            <el-input v-model="plazaCommentText" size="small" placeholder="写评论..." @keydown.enter.prevent="plazaSubmitComment(plazaShowDetail)" />
                                            <el-button size="small" type="primary" :loading="plazaCommentSubmitting" @click="plazaSubmitComment(plazaShowDetail)">发送</el-button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 帖子详情弹窗（小红书样式） -->
                        <el-dialog :model-value="!!plazaShowDetail && !plazaShowDetailView" @update:model-value="val => { if(!val) { plazaShowDetail = null; plazaShowDetailView = false } }" :title="'🌐 ' + (plazaShowDetail?.user?.name || '用户') + ' 的帖子'" width="520px" top="5vh" :close-on-click-modal="true" class="plaza-detail-dialog">
                            <template v-if="plazaShowDetail">
                                <div class="plaza-detail-header">
                                    <img v-if="plazaShowDetail.user?.avatar_url" :src="plazaShowDetail.user.avatar_url" class="plaza-detail-avatar" />
                                    <span v-else class="plaza-detail-avatar-text">{{ plazaShowDetail.user?.name?.charAt(0) || '?' }}</span>
                                    <div class="plaza-detail-user-info">
                                        <div class="plaza-detail-username">{{ plazaShowDetail.user?.name || '用户' }}</div>
                                        <div class="plaza-detail-time">{{ formatTime(plazaShowDetail.created_at) }}</div>
                                    </div>
                                    <el-button v-if="(plazaShowDetail.user?.id || plazaShowDetail.user_id) === myId" text size="small" type="danger" @click="plazaDeletePost(plazaShowDetail)" style="margin-left:auto">删除</el-button>
                                    <el-button v-if="(plazaShowDetail.user?.id || plazaShowDetail.user_id) === myId" text size="small" @click="openPlazaEdit(plazaShowDetail)" title="编辑">✏️</el-button>
                                    <el-button text size="small" @click="openPlazaForward(plazaShowDetail)" title="转发">📨</el-button>
                                </div>
                                <div class="plaza-detail-content">{{ plazaShowDetail.content }}</div>
                                <div v-if="plazaShowDetail.images?.length" class="plaza-detail-images">
                                    <div v-for="(img, i) in plazaShowDetail.images" :key="i" class="plaza-detail-img-wrap" @click="openPlazaLightbox(img, i, plazaShowDetail.images)">
                                        <img :src="img" class="plaza-detail-img" />
                                    </div>
                                </div>
                                <div v-if="plazaShowDetail.video" class="plaza-detail-video">
                                    <video :src="plazaShowDetail.video" controls class="plaza-video-player"></video>
                                </div>
                                <div class="plaza-detail-stats">
                                    <span>🤍 {{ plazaShowDetail.likes_count || 0 }}</span>
                                    <span style="margin-left:16px">💬 {{ plazaShowDetail.replies_count || 0 }} 评论</span>
                                    <span style="margin-left:16px">👁️ {{ plazaShowDetail.views_count || 0 }} 次浏览</span>
                                    <span style="margin-left:16px">⭐ {{ plazaShowDetail.favorites_count || 0 }} 收藏</span>
                                </div>
                                <div class="plaza-detail-actions">
                                    <el-button :type="plazaShowDetail.is_liked ? 'primary' : 'default'" size="small" @click="plazaToggleLike(plazaShowDetail)">
                                        {{ plazaShowDetail.is_liked ? '❤️' : '🤍' }} {{ plazaShowDetail.likes_count || 0 }}
                                    </el-button>
                                    <el-button :type="plazaShowDetail.is_favorited ? 'warning' : 'default'" size="small" @click="plazaToggleFavorite(plazaShowDetail)">
                                        {{ plazaShowDetail.is_favorited ? '⭐' : '☆' }} 收藏
                                    </el-button>
                                    <el-button v-if="(plazaShowDetail.user?.id || plazaShowDetail.user_id) !== myId" size="small" text type="warning" @click="openPlazaReport(plazaShowDetail)">
                                        ⚠️ 举报
                                    </el-button>
                                </div>
                                <el-divider style="margin:12px 0" />
                                <!-- 评论列表 -->
                                <div class="plaza-detail-comments" v-loading="plazaCommentsLoading">
                                    <div v-if="plazaComments.length" class="plaza-comment-list">
                                        <div v-for="c in plazaComments" :key="c.id" class="plaza-comment-item">
                                            <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" class="plaza-comment-avatar" />
                                            <span v-else class="plaza-comment-avatar-text">{{ c.user?.name?.charAt(0) || '?' }}</span>
                                            <div class="plaza-comment-body">
                                                <span class="plaza-comment-author">{{ c.user?.name || '用户' }}</span>
                                                <span class="plaza-comment-text">{{ c.content }}</span>
                                                <div class="plaza-comment-footer">
                                                    <span class="plaza-comment-time">{{ formatTime(c.created_at) }}</span>
                                                    <span class="plaza-comment-reply-btn" @click="plazaReplyTo = c">回复</span>
                                                </div>
                                                <!-- 子回复 -->
                                                <div v-if="c.replies?.length" class="plaza-sub-replies">
                                                    <div v-for="r in c.replies" :key="r.id" class="plaza-comment-item plaza-sub-item">
                                                        <img v-if="r.user?.avatar_url" :src="r.user.avatar_url" class="plaza-comment-avatar" />
                                                        <span v-else class="plaza-comment-avatar-text">{{ r.user?.name?.charAt(0) || '?' }}</span>
                                                        <div class="plaza-comment-body">
                                                            <span class="plaza-comment-author">{{ r.user?.name || '用户' }}</span>
                                                            <span class="plaza-comment-text">{{ r.content }}</span>
                                                            <div class="plaza-comment-footer">
                                                                <span class="plaza-comment-time">{{ formatTime(r.created_at) }}</span>
                                                                <span class="plaza-comment-reply-btn" @click="plazaReplyTo = c">回复</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- 回复输入框 -->
                                                <div v-if="plazaReplyTo?.id === c.id" class="plaza-reply-input">
                                                    <el-input v-model="plazaReplyText" size="small" :placeholder="'回复 ' + (c.user?.name || '用户') + '...'" @keydown.enter.prevent="plazaSubmitReply(c)" />
                                                    <el-button size="small" text @click="plazaReplyTo = null">取消</el-button>
                                                    <el-button size="small" type="primary" :loading="plazaReplySubmitting" @click="plazaSubmitReply(c)">回复</el-button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-else style="text-align:center;padding:12px;color:#999;font-size:13px">暂无评论</div>
                                </div>
                                <div class="plaza-comment-input">
                                    <el-input v-model="plazaCommentText" size="small" placeholder="写评论..." @keydown.enter.prevent="plazaSubmitComment(plazaShowDetail)" />
                                    <el-button size="small" type="primary" :loading="plazaCommentSubmitting" @click="plazaSubmitComment(plazaShowDetail)">发送</el-button>
                                </div>
                            </template>
                        </el-dialog>

                        <!-- 广场转发对话框 -->
                        <el-dialog v-model="plazaForwardShow" title="📨 转发到聊天" width="420px">
                            <el-select v-model="plazaForwardConvId" filterable remote :remote-method="searchPlazaForwardConvs" :loading="plazaForwardSearching" placeholder="搜索或选择会话..." style="width:100%">
                                <el-option v-for="c in plazaForwardConvs" :key="c.id" :label="c.name" :value="c.id">
                                    <span>{{ c.type === 'group' ? '👥' : '👤' }} {{ c.name }}</span>
                                </el-option>
                            </el-select>
                            <div v-if="plazaForwardPost" class="plaza-forward-preview">
                                <div class="plaza-forward-label">转发内容预览：</div>
                                <div class="plaza-forward-card">
                                    <div class="plaza-forward-user">🌐 {{ plazaForwardPost.user?.name || '用户' }}</div>
                                    <div class="plaza-forward-text">{{ plazaForwardPost.content?.substring(0, 80) }}{{ plazaForwardPost.content?.length > 80 ? '...' : '' }}</div>
                                </div>
                            </div>
                            <template #footer>
                                <el-button @click="plazaForwardShow = false">取消</el-button>
                                <el-button type="primary" :loading="plazaForwarding" @click="submitPlazaForward">转发</el-button>
                            </template>
                        </el-dialog>

                        <!-- 广场编辑对话框 -->
                        <el-dialog v-model="plazaEditShow" title="✏️ 编辑帖子" width="480px" :close-on-click-modal="false">
                            <el-form label-width="0">
                                <el-input v-model="plazaEditContent" type="textarea" :rows="5" placeholder="修改内容..." maxlength="5000" />
                                <div class="plaza-edit-images" v-if="plazaEditImages.length">
                                    <div v-for="(img, i) in plazaEditImages" :key="i" class="plaza-edit-img-wrap">
                                        <img :src="img" class="plaza-edit-img" />
                                        <el-button class="plaza-edit-remove" text size="small" type="danger" @click="plazaEditImages.splice(i, 1)">×</el-button>
                                    </div>
                                </div>
                                <div style="margin-top:8px;display:flex;gap:6px">
                                    <el-upload :show-file-list="false" :http-request="plazaEditUploadImage" accept="image/*">
                                        <el-button size="small"><el-icon><Picture /></el-icon> 添加图片</el-button>
                                    </el-upload>
                                    <el-upload :show-file-list="false" :http-request="plazaEditUploadVideo" accept="video/*">
                                        <el-button size="small"><el-icon><VideoCamera /></el-icon> {{ plazaEditVideo ? '更换视频' : '添加视频' }}</el-button>
                                    </el-upload>
                                    <el-button v-if="plazaEditVideo" size="small" text type="danger" @click="plazaEditVideo = ''">移除视频</el-button>
                                </div>
                                <div v-if="plazaEditVideo" class="plaza-edit-video-preview">
                                    <video :src="plazaEditVideo" controls style="max-width:100%;max-height:200px;border-radius:6px;margin-top:6px"></video>
                                </div>
                            </el-form>
                            <template #footer>
                                <el-button size="small" @click="plazaEditShow = false">取消</el-button>
                                <el-button size="small" type="primary" :loading="plazaEditSaving" @click="submitPlazaEdit">保存</el-button>
                            </template>
                        </el-dialog>

                        <!-- 举报对话框 -->
                        <el-dialog v-model="plazaReportShow" title="⚠️ 举报帖子" width="400px">
                            <el-form label-position="top">
                                <el-form-item label="举报原因" required>
                                    <el-select v-model="plazaReportReason" placeholder="请选择举报原因..." style="width:100%">
                                        <el-option label="垃圾广告" value="spam" />
                                        <el-option label="骚扰谩骂" value="harassment" />
                                        <el-option label="色情低俗" value="pornographic" />
                                        <el-option label="违法违规" value="illegal" />
                                        <el-option label="冒充他人" value="impersonation" />
                                        <el-option label="侵犯版权" value="copyright" />
                                        <el-option label="其他" value="other" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item label="补充说明">
                                    <el-input v-model="plazaReportDesc" type="textarea" :rows="3" placeholder="可选，最多1000字" maxlength="1000" />
                                </el-form-item>
                            </el-form>
                            <template #footer>
                                <el-button size="small" @click="plazaReportShow = false">取消</el-button>
                                <el-button size="small" type="danger" :loading="plazaReportSubmitting" @click="submitPlazaReport">提交举报</el-button>
                            </template>
                        </el-dialog>

                        <!-- 图片 Lightbox -->
                        <div v-if="plazaLightboxImages.length" class="plaza-lightbox-overlay" @click.self="closePlazaLightbox">
                            <div class="plaza-lightbox-close" @click="closePlazaLightbox">✕</div>
                            <div class="plaza-lightbox-nav plaza-lightbox-prev" @click="prevPlazaLightbox" v-if="plazaLightboxImages.length > 1">‹</div>
                            <div class="plaza-lightbox-content">
                                <img :src="plazaLightboxImages[plazaLightboxIndex]" class="plaza-lightbox-img" />
                                <div class="plaza-lightbox-counter">{{ plazaLightboxIndex + 1 }} / {{ plazaLightboxImages.length }}</div>
                            </div>
                            <div class="plaza-lightbox-nav plaza-lightbox-next" @click="nextPlazaLightbox" v-if="plazaLightboxImages.length > 1">›</div>
                        </div>

                        <!-- 收藏夹选择 -->
                        <el-dialog v-model="showCollectionPicker" title="📁 选择收藏夹" width="360px" :close-on-click-modal="false">
                            <div class="collection-picker-list">
                                <div class="collection-picker-item" @click="doFavoriteWithCollection(null)">
                                    <span class="collection-picker-icon">📦</span>
                                    <span class="collection-picker-name">未分类</span>
                                </div>
                                <div v-for="col in collections" :key="col.id" class="collection-picker-item" @click="doFavoriteWithCollection(col.id)">
                                    <span class="collection-picker-icon">{{ col.icon || '📁' }}</span>
                                    <span class="collection-picker-name">{{ col.name }}</span>
                                    <span class="collection-picker-count">{{ col.favorites_count || 0 }}</span>
                                </div>
                            </div>
                            <div style="margin-top:8px;display:flex;gap:6px">
                                <el-input v-model="newCollectionName" size="small" placeholder="新建收藏夹..." />
                                <el-button size="small" type="primary" @click="createAndPick">新建</el-button>
                            </div>
                            <template #footer>
                                <el-button size="small" @click="showCollectionPicker = false">取消</el-button>
                            </template>
                        </el-dialog>
                    </template>

                    <template v-else-if="activeConv.is_handoff">
                        <div class="chat-header agent-chat-header">
                            <div class="chat-header-left">
                                <el-button v-if="isMobile" text size="small" @click="activeConv = null" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                                <div class="agent-customer-avatar">{{ activeConv.name?.charAt(0) || '?' }}</div>
                                <span class="chat-title">{{ activeConv.name || '客服会话' }}</span>
                                <el-tag v-if="activeConv.customer?.tags?.length" size="small" type="warning" style="margin-left:6px">
                                    {{ activeConv.customer.tags[0] }}
                                </el-tag>
                            </div>
                            <div class="chat-actions">
                                <el-button size="small" text @click="showAgentCustomerInfo = !showAgentCustomerInfo">
                                    {{ showAgentCustomerInfo ? '🙈 隐藏' : '📋 信息' }}
                                </el-button>
                                <el-button size="small" text @click="activeConv = null"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <div class="chat-main-split" :class="{ 'with-info': showAgentCustomerInfo }">
                            <div class="chat-messages-wrap">
                                <div class="messages-area" ref="agentMsgAreaRef">
                                    <div v-for="msg in agentMessages" :key="msg.id" class="msg-item"
                                        :class="{ 'msg-self': msg.is_agent || msg.user_id === myId }">
                                        <div class="msg-avatar">
                                            <span>{{ msg.is_agent || msg.user_id === myId ? '我' : (activeConv.name?.charAt(0) || '?') }}</span>
                                        </div>
                                        <div class="msg-bubble">
                                            <div class="msg-text">{{ msg.content }}</div>
                                            <div class="msg-time">{{ formatTime(msg.created_at) }}</div>
                                        </div>
                                    </div>
                                    <div v-if="!agentMessages.length" class="empty-chat" style="padding:60px 0">
                                        <el-empty description="暂无消息，开始对话吧" :image-size="60" />
                                    </div>
                                </div>
                                <div class="chat-input-area agent-input-area">
                                    <el-input v-model="agentInput" type="textarea" :rows="2"
                                        placeholder="输入消息... (Enter发送)" @keydown.enter.exact.prevent="sendAgentMessage" />
                                    <div class="input-actions">
                                        <div class="input-action-left">
                                            <el-dropdown trigger="click" @command="insertQuickReply">
                                                <el-button size="small" text><el-icon><ChatDotRound /></el-icon> 快捷回复</el-button>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item
                                                            v-for="r in agentQuickReplies"
                                                            :key="r.id"
                                                            :command="r.content"
                                                            :divided="r._divider">
                                                            <span style="font-weight:500">{{ r.title }}</span>
                                                            <span style="color:#909399;font-size:11px;margin-left:6px">[{{ r.category || '通用' }}]</span>
                                                        </el-dropdown-item>
                                                        <el-dropdown-item v-if="!agentQuickReplies.length" disabled>暂无快捷回复</el-dropdown-item>
                                                        <el-dropdown-item divided command="__manage__">
                                                            <span style="color:#409eff">⚙️ 管理快捷回复</span>
                                                        </el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </div>
                                        <el-button type="primary" size="small" :loading="agentSending"
                                            @click="sendAgentMessage" :disabled="!agentInput.trim()">发送</el-button>
                                    </div>
                                </div>
                            </div>
                            <!-- 客户信息侧栏 -->
                            <div v-if="showAgentCustomerInfo" class="agent-customer-info-panel">
                                <div class="info-panel-header">📋 客户信息</div>
                                <div class="info-panel-body">
                                    <div class="info-row">
                                        <span class="info-label">名称</span>
                                        <span class="info-value">{{ activeConv.name }}</span>
                                    </div>
                                    <div class="info-row" v-if="activeConv.customer?.user?.email">
                                        <span class="info-label">邮箱</span>
                                        <span class="info-value">{{ activeConv.customer.user.email }}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">会话ID</span>
                                        <span class="info-value" style="font-size:11px">#{{ activeConv.handoff_id }}</span>
                                    </div>
                                    <el-divider style="margin:8px 0" />
                                    <div class="info-row">
                                        <span class="info-label">🏷️ 标签</span>
                                        <div class="info-tags">
                                            <el-tag v-for="t in agentConvTags" :key="t" size="small" style="margin:2px">{{ t }}</el-tag>
                                            <el-tag v-if="!agentConvTags.length" size="small" type="info">暂无</el-tag>
                                        </div>
                                    </div>
                                    <el-divider style="margin:8px 0" />
                                    <div class="info-actions">
                                        <el-button size="small" @click="quickTag('VIP')">🏅 VIP</el-button>
                                        <el-button size="small" @click="quickTag('投诉')">⚠️ 投诉</el-button>
                                        <el-button size="small" @click="quickTag('询价')">💰 询价</el-button>
                                        <el-button size="small" @click="quickTag('售后')">🔧 售后</el-button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══════ 发现视图 ═══════ -->
                    <template v-else-if="activeConv.is_discover">
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <el-button v-if="isMobile" text size="small" @click="activeConv = null" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                                <span class="chat-title">🔍 发现</span>
                            </div>
                            <div class="chat-actions">
                                <el-button text size="small" @click="activeConv = null"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <div class="discover-search-bar">
                            <el-input v-model="discoverKeyword" size="large" placeholder="搜索公众号与文章..." clearable
                                @keydown.enter="doDiscoverSearch" @clear="doDiscoverSearch">
                                <template #prefix><el-icon><Search /></el-icon></template>
                            </el-input>
                            <div class="discover-tabs">
                                <el-radio-group v-model="discoverTab" size="small" @change="doDiscoverSearch">
                                    <el-radio-button value="all">全部</el-radio-button>
                                    <el-radio-button value="account">公众号</el-radio-button>
                                    <el-radio-button value="article">文章</el-radio-button>
                                    <el-radio-button value="product">商品</el-radio-button>
                                    <el-radio-button value="merchant">商家</el-radio-button>
                                </el-radio-group>
                            </div>
                        </div>
                        <div class="chat-messages-wrap">
                            <div class="discover-results" v-loading="discoverLoading">
                                <!-- 公众号结果 -->
                                <template v-if="discoverTab !== 'article' && discoverTab !== 'product' && discoverTab !== 'merchant'">
                                    <div v-if="discoverAccounts.length" class="discover-section">
                                        <h4 class="discover-section-title">📢 公众号</h4>
                                        <div v-for="acc in discoverAccounts" :key="'acc_'+acc.id" class="discover-account-card">
                                            <div class="discover-acc-avatar">
                                                <img v-if="acc.avatar" :src="acc.avatar" class="discover-acc-img" />
                                                <span v-else>{{ acc.name?.charAt(0) || '?' }}</span>
                                            </div>
                                            <div class="discover-acc-info">
                                                <div class="discover-acc-name">{{ acc.name }}</div>
                                                <div class="discover-acc-desc">{{ acc.description }}</div>
                                                <div class="discover-acc-meta">👥 {{ acc.followers_count || 0 }} 关注</div>
                                            </div>
                                            <el-button v-if="!acc.is_following" size="small" type="primary" @click.stop="followDiscoverAccount(acc)">关注</el-button>
                                            <el-tag v-else size="small" type="success">已关注</el-tag>
                                        </div>
                                    </div>
                                </template>
                                <!-- 文章结果 -->
                                <template v-if="discoverTab !== 'account' && discoverTab !== 'product' && discoverTab !== 'merchant'">
                                    <div v-if="discoverArticles.length" class="discover-section">
                                        <h4 class="discover-section-title">📰 文章</h4>
                                        <div v-for="art in discoverArticles" :key="'art_'+art.id" class="discover-article-card" @click="openDiscoverArticle(art)">
                                            <div v-if="art.cover_image" class="discover-art-cover">
                                                <img :src="art.cover_image" class="discover-art-img" />
                                                <span class="discover-art-type-badge">{{ typeLabel(art.message_type) }}</span>
                                            </div>
                                            <div class="discover-art-body">
                                                <div class="discover-art-title">{{ art.title }}</div>
                                                <div v-if="art.summary" class="discover-art-summary">{{ art.summary }}</div>
                                                <div class="discover-art-meta">
                                                    <span class="discover-art-source">{{ art.account_name }}</span>
                                                    <span>❤️ {{ art.likes_count || 0 }}</span>
                                                    <span>👁️ {{ art.reads_count || 0 }}</span>
                                                    <span class="discover-art-time">{{ formatTime(art.published_at) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <!-- 商品结果 -->
                                <template v-if="discoverTab !== 'account' && discoverTab !== 'article' && discoverTab !== 'merchant'">
                                    <div v-if="discoverProducts.length" class="discover-section">
                                        <h4 class="discover-section-title">🏷️ 商品</h4>
                                        <div class="discover-prod-grid">
                                            <div v-for="p in discoverProducts" :key="'prod_'+p.id" class="prod-card">
                                                <a :href="'/products/' + p.slug" target="_blank" class="prod-card-link">
                                                    <div class="prod-card-img-wrap">
                                                        <img v-if="p.image_url" :src="p.image_url" class="prod-card-img" @error="$event.target.style.display='none'" />
                                                        <div v-else class="prod-card-placeholder">📦</div>
                                                        <div v-if="p.tags?.length" class="prod-card-badges">
                                                            <span v-for="t in p.tags.slice(0,2)" :key="t" class="prod-card-badge" :class="'badge-'+t">{{ t }}</span>
                                                        </div>
                                                    </div>
                                                    <div class="prod-card-body">
                                                        <div class="prod-card-name">{{ p.name }}</div>
                                                        <div v-if="p.description" class="prod-card-desc">{{ p.description?.substring(0, 60) }}{{ p.description?.length > 60 ? '...' : '' }}</div>
                                                        <div class="prod-card-footer">
                                                            <span v-if="p.sku_price_min != null" class="prod-card-price">¥{{ p.sku_price_max != null && p.sku_price_max != p.sku_price_min ? p.sku_price_min + '~' + p.sku_price_max : p.sku_price_min }}</span>
                                                            <span v-else-if="p.base_price" class="prod-card-price">¥{{ p.base_price }}</span>
                                                            <span v-else class="prod-card-price">面议</span>
                                                            <span v-if="p.sales_count" class="prod-card-sales">已售 {{ p.sales_count }}</span>
                                                        </div>
                                                        <div v-if="p.category_name" class="prod-card-cat">{{ p.category_name }}</div>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <!-- 商家结果 -->
                                <template v-if="discoverTab !== 'account' && discoverTab !== 'article' && discoverTab !== 'product'">
                                    <div v-if="discoverMerchants.length" class="discover-section">
                                        <h4 class="discover-section-title">🏪 商家</h4>
                                        <div v-for="m in discoverMerchants" :key="'m_'+m.id" class="discover-merchant-card">
                                            <div class="discover-mch-header">
                                                <div class="discover-mch-avatar">
                                                    <img v-if="m.avatar" :src="m.avatar" class="discover-mch-img" />
                                                    <span v-else>{{ m.name?.charAt(0) || '?' }}</span>
                                                </div>
                                                <div class="discover-mch-info">
                                                    <div class="discover-mch-name">{{ m.name }}</div>
                                                    <div class="discover-mch-desc">{{ m.description }}</div>
                                                    <div class="discover-mch-meta">
                                                        <span>👥 {{ m.followers_count || 0 }} 关注</span>
                                                        <span class="discover-mch-prod-count">📦 {{ m.products?.length || 0 }} 件商品</span>
                                                    </div>
                                                </div>
                                                <el-button v-if="!m.is_following" size="small" type="primary" @click.stop="followDiscoverAccount(m)">关注</el-button>
                                                <el-tag v-else size="small" type="success">已关注</el-tag>
                                            </div>
                                            <div v-if="m.products?.length" class="discover-mch-products">
                                                <div v-for="p in m.products" :key="'mp_'+p.id" class="discover-mch-prod-card">
                                                    <a :href="'/products/' + (p.slug || p.id)" target="_blank" class="discover-mch-prod-link">
                                                        <div class="discover-mch-prod-img-wrap">
                                                            <img v-if="p.image_url" :src="p.image_url" class="discover-mch-prod-img" @error="$event.target.style.display='none'" />
                                                            <div v-else class="discover-mch-prod-placeholder">📦</div>
                                                        </div>
                                                        <div class="discover-mch-prod-info">
                                                            <div class="discover-mch-prod-name">{{ p.name }}</div>
                                                            <div v-if="p.description" class="discover-mch-prod-desc">{{ p.description?.substring(0, 50) }}</div>
                                                            <div class="discover-mch-prod-footer">
                                                                <span v-if="p.sku_price_min != null" class="discover-mch-prod-price">¥{{ p.sku_price_max != null && p.sku_price_max != p.sku_price_min ? p.sku_price_min + '~' + p.sku_price_max : p.sku_price_min }}</span>
                                                                <span v-else-if="p.base_price" class="discover-mch-prod-price">¥{{ p.base_price }}</span>
                                                                <span class="discover-mch-prod-link-text">查看详情 →</span>
                                                            </div>
                                                        </div>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div v-if="!discoverAccounts.length && !discoverArticles.length && !discoverProducts.length && !discoverMerchants.length && !discoverLoading" class="empty-chat" style="padding:60px 0">
                                    <el-empty :description="discoverKeyword ? '未找到相关内容' : '输入关键词搜索公众号与文章'" :image-size="60" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══════ 审核管理视图 ═══════ -->
                    <template v-else-if="activeConv.is_oa_review">
                        <div class="chat-header">
                            <div class="chat-header-left">
                                <el-button v-if="isMobile" text size="small" @click="activeConv = null; showReviewPanel = false" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                                <span class="chat-title">🔍 审核管理 — {{ activeConv.oa_name }}</span>
                            </div>
                            <div class="chat-actions">
                                <el-button text size="small" @click="activeConv = null; showReviewPanel = false"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <div class="chat-messages-wrap">
                            <div class="messages-area" v-loading="loadingReviews">
                                <!-- 审核统计 -->
                                <div v-if="reviewStats" class="review-stats-bar">
                                    <div class="review-stat-item">
                                        <span class="review-stat-num review-stat-pending">{{ reviewStats.pending }}</span>
                                        <span class="review-stat-label">待审核</span>
                                    </div>
                                    <div class="review-stat-item">
                                        <span class="review-stat-num review-stat-approved">{{ reviewStats.approved }}</span>
                                        <span class="review-stat-label">已通过</span>
                                    </div>
                                    <div class="review-stat-item">
                                        <span class="review-stat-num review-stat-rejected">{{ reviewStats.rejected }}</span>
                                        <span class="review-stat-label">已拒绝</span>
                                    </div>
                                </div>
                                <div v-for="s in reviewSubmissions" :key="s.id" class="review-card">
                                    <div class="review-card-header" @click="openSubmissionPreview(s)" style="cursor:pointer">
                                        <span class="review-card-title">{{ s.title }}</span>
                                        <el-tag type="warning" size="small">待审核</el-tag>
                                    </div>
                                    <div class="review-card-meta">
                                        <span>✍️ {{ s.user?.name || '匿名' }}</span>
                                        <span>📄 {{ s.content?.length || 0 }} 字</span>
                                        <span>🕐 {{ formatTime(s.created_at) }}</span>
                                    </div>
                                    <div class="review-card-preview">{{ s.content?.substring(0, 200) }}{{ s.content?.length > 200 ? '...' : '' }}</div>
                                    <div class="review-card-actions">
                                        <el-button size="small" @click="openSubmissionPreview(s)">👁️ 预览</el-button>
                                        <el-button size="small" type="success" @click="doReview(s, 'approve')">
                                            <el-icon><Select /></el-icon> 通过并发布
                                        </el-button>
                                        <el-button size="small" type="danger" @click="doReview(s, 'reject')">
                                            <el-icon><Close /></el-icon> 拒绝
                                        </el-button>
                                    </div>
                                </div>
                                <div v-if="!reviewSubmissions.length && !loadingReviews" class="empty-chat" style="padding:60px 0">
                                    <el-empty description="暂无待审核投稿" :image-size="60" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- ═══════ 普通会话/群聊视图 ═══════ -->
                    <template v-else>
                        <div class="chat-header">
                            <div class="chat-header-left">
                            <el-button v-if="isMobile" text size="small" @click="activeConv = null" class="back-btn"><el-icon><ArrowLeft /></el-icon></el-button>
                            <span v-if="onlineStatusLabel" class="online-status-label" :class="onlineStatusLabel">●</span>
                            <span class="chat-title">{{ activeConv.name }}</span>
                        </div>
                        <div class="chat-actions">
                            <el-button text size="small" @click="showTagPanel = !showTagPanel" :title="'标签'" :type="showTagPanel ? 'primary' : 'default'"><el-icon><CollectionTag /></el-icon></el-button>
                            <el-button v-if="activeConv?.type === 'group'" text size="small" @click="showAnnouncements = true" title="群公告"><el-icon><Message /></el-icon></el-button>
                            <el-button v-if="activeConv?.type === 'group'" text size="small" @click="openSlowModeDialog" :title="'慢速模式'" :type="activeConv.slow_mode_interval > 0 ? 'primary' : 'default'"><el-icon><Timer /></el-icon></el-button>
                            <el-button v-if="activeConv?.type === 'group'" text size="small" @click="openInviteDialog" title="邀请"><el-icon><Share /></el-icon></el-button>
                            <el-button v-if="activeConv?.type === 'group'" text size="small" @click="showGroupManage = true" title="群管理"><el-icon><Setting /></el-icon></el-button>
                            <el-button text size="small" @click="togglePin" :title="activeConv.is_pinned ? '取消置顶' : '置顶'"><el-icon><StarFilled v-if="activeConv.is_pinned" /><Star v-else /></el-icon></el-button>
                            <el-button text size="small" @click="toggleMute" :title="activeConv.is_muted ? '取消静音' : '静音'"><el-icon><Bell v-if="!activeConv.is_muted" /><Mute v-else /></el-icon></el-button>
                            <el-button text size="small" @click="handleHandoff" title="转接客服"><el-icon><Service /></el-icon></el-button>
                            <el-button text size="small" @click="showA11yPanel = !showA11yPanel" title="无障碍" :type="showA11yPanel ? 'primary' : 'default'"><el-icon><Reading /></el-icon></el-button>
                            <el-button text size="small" @click="startCall('audio')" title="语音通话"><el-icon><Phone /></el-icon></el-button>
                            <el-button text size="small" @click="startCall('video')" title="视频通话"><el-icon><VideoCamera /></el-icon></el-button>
                            <el-button text size="small" @click="handleExportConv" title="导出聊天"><el-icon><Download /></el-icon></el-button>
                            <el-button text size="small" @click="handleSummarize" title="总结"><el-icon><MagicStick /></el-icon></el-button>
                            <el-button v-if="activeConv?.type === 'group'" text size="small" @click="showModerator = true" title="AI 主持人"><el-icon><Cpu /></el-icon></el-button>
                            <el-button text size="small" @click="handleDeleteConv"><el-icon><Delete /></el-icon></el-button>
                        </div>
                    </div>
                    <div v-if="showTagPanel" class="tag-assign-panel">
                        <div class="tag-assign-header"><span>分配标签</span><el-button text size="small" @click="showTagPanel = false"><el-icon><Close /></el-icon></el-button></div>
                        <div class="tag-list">
                            <el-checkbox-group v-model="selectedTagIds">
                                <el-checkbox v-for="tag in allTags" :key="tag.id" :label="tag.id" :value="tag.id">
                                    <span class="tag-label-dot" :style="{ background: tag.color || '#409eff' }"></span>{{ tag.name }}
                                </el-checkbox>
                            </el-checkbox-group>
                        </div>
                        <div class="tag-assign-actions"><el-button size="small" type="primary" :loading="savingTags" @click="saveConvTags">保存</el-button></div>
                    </div>
                    <div v-if="convTags.length" class="conv-tags-bar">
                        <span v-for="tag in convTags" :key="tag.id" class="conv-tag" :style="{ background: tag.color || '#409eff' }">{{ tag.name }}</span>
                    </div>
                    <!-- 无障碍面板 -->
                    <div v-if="showA11yPanel" class="a11y-panel">
                        <div class="a11y-panel-header">
                            <span>♿ 无障碍</span>
                            <el-button text size="small" @click="showA11yPanel = false"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div class="a11y-panel-body">
                            <div class="a11y-row">
                                <span class="a11y-label">字体大小</span>
                                <el-radio-group v-model="a11yFontSize" size="small" @change="setA11yFontSize">
                                    <el-radio-button value="small">小</el-radio-button>
                                    <el-radio-button value="normal">中</el-radio-button>
                                    <el-radio-button value="large">大</el-radio-button>
                                    <el-radio-button value="extra_large">超大</el-radio-button>
                                </el-radio-group>
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">减少动画</span>
                                <el-switch v-model="a11yReducedMotion" @change="setA11yReducedMotion" />
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">高对比度</span>
                                <el-switch v-model="a11yHighContrast" @change="setA11yHighContrast" />
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">自动生成图片描述</span>
                                <el-switch v-model="a11yAutoAlt" />
                                <span class="a11y-hint">为聊天中的图片自动生成 ALT 文本</span>
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">消息朗读</span>
                                <el-switch v-model="a11yMsgAnnounce" @change="setA11yMsgAnnounce" />
                                <span class="a11y-hint">新消息自动朗读内容</span>
                            </div>
                            <el-divider style="margin:8px 0" />
                            <div class="a11y-actions">
                                <el-button text size="small" @click="readConversationSummary">🔊 朗读会话摘要</el-button>
                                <router-link to="/a11y" class="a11y-more-link">更多无障碍设置 →</router-link>
                            </div>
                        </div>
                    </div>
                    <!-- SRCH-004: 会话内搜索 -->
                    <div class="conv-search-bar">
                        <el-popover placement="bottom" :width="300" trigger="click" v-model:visible="showConvSearch">
                            <template #reference>
                                <el-input v-model="convSearchKeyword" placeholder="搜索当前会话..." size="small" clearable @keydown.enter="doConvSearch" @clear="clearConvSearch" style="width:160px">
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                            </template>
                            <div class="conv-search-popover">
                                <div class="conv-search-filters">
                                    <el-input v-model="convSearchKeyword" size="small" placeholder="关键词" clearable @keydown.enter="doConvSearch" />
                                    <el-select v-model="convSearchType" size="small" placeholder="消息类型" clearable style="width:100%">
                                        <el-option label="全部类型" value="" />
                                        <el-option label="文本" value="text" />
                                        <el-option label="图片" value="image" />
                                        <el-option label="文件" value="file" />
                                        <el-option label="语音" value="voice" />
                                        <el-option label="位置" value="location" />
                                        <el-option label="卡片" value="card" />
                                        <el-option label="贴纸" value="sticker" />
                                    </el-select>
                                    <el-date-picker v-model="convSearchDateRange" type="daterange" size="small" range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD" style="width:100%" />
                                </div>
                                <div class="conv-search-actions">
                                    <el-button size="small" type="primary" @click="doConvSearch">搜索</el-button>
                                    <el-button size="small" @click="clearConvSearch">清除</el-button>
                                </div>
                                <div v-if="convSearchResults.length" class="conv-search-results">
                                    <div v-for="r in convSearchResults" :key="r.id" class="conv-search-item" @click="scrollToMessage(r.id); showConvSearch = false">
                                        <div class="conv-search-sender">{{ r.sender?.name || '用户' }}</div>
                                        <div class="conv-search-text" v-html="convHighlightKeyword(r.content, convSearchKeyword)"></div>
                                        <div class="conv-search-time">{{ formatTime(r.created_at) }}</div>
                                    </div>
                                </div>
                                <div v-else-if="convSearchSearched" class="conv-search-empty">未找到匹配消息</div>
                            </div>
                        </el-popover>
                        <el-button v-if="convSearchResults.length" size="small" text @click="clearConvSearch" title="清除搜索"><el-icon><Close /></el-icon></el-button>
                    </div>
                    <div class="chat-messages-wrap">
                        <div class="messages-area" ref="msgAreaRef" @scroll="onScrollTop">
                        <div v-if="pinnedMessages.length" class="pinned-banner">
                            <div class="pinned-header">📌 置顶消息 <span class="pinned-count">{{ pinnedMessages.length }}</span></div>
                            <div class="pinned-list">
                                <div v-for="pm in pinnedMessages.slice(0, 3)" :key="pm.id" class="pinned-item" @click="scrollToMessage(pm.id)">
                                    <span class="pinned-sender">{{ pm.sender?.name || '用户' }}:</span>
                                    <span class="pinned-content">{{ pm.content?.substring(0, 40) }}</span>
                                </div>
                                <div v-if="pinnedMessages.length > 3" class="pinned-more">还有 {{ pinnedMessages.length - 3 }} 条置顶消息</div>
                            </div>
                        </div>
                        <div v-if="hasMore" class="load-more"><el-button text size="small" @click="loadMore">加载更多</el-button></div>
                        <div v-for="msg in messages" :key="msg.id" :data-msg-id="msg.id" class="msg-item" :class="{ 'msg-self': msg.sender_id === myId, 'msg-selected': selectingForward && forwardMsgs.some(m => m.id === msg.id) }" @click="selectingForward && toggleSelectForward(msg)">
                            <div class="msg-avatar">
                                <img v-if="msg.sender?.avatar" :src="msg.sender.avatar" class="msg-avatar-img" @error="$event.target.style.display='none'" />
                                <span v-else>{{ msg.sender?.name?.charAt(0) || '?' }}</span>
                            </div>
                            <div class="msg-bubble" :class="{ 'msg-recalled': msg.is_recalled, 'msg-high-priority': msg.metadata?.priority === 'high' }">
                                <div v-if="msg.metadata?.priority === 'high'" class="msg-priority-badge">🔴 紧急</div>
                                <div v-else-if="msg.metadata?.priority === 'medium'" class="msg-priority-badge msg-priority-medium">🟡 重要</div>
                                <div v-if="msg.sender_id !== myId" class="msg-sender">{{ msg.sender?.name || '用户' }}</div>
                                <div v-if="msg.reply_to" class="msg-reply" @click="scrollToMessage(msg.reply_to_id)">
                                    <div class="reply-sender">{{ msg.reply_to.sender?.name || '用户' }}</div>
                                    <div class="reply-text">{{ replyPreviewText(msg.reply_to) }}</div>
                                </div>
                                <div v-if="msg.is_recalled" class="msg-recalled-text">{{ msg.sender_id === myId ? '你' : msg.sender?.name || '对方' }} 撤回了一条消息</div>
                                <div v-else-if="msg.message_type === 'image' && msg.content" class="msg-image">
                                    <img :src="msg.content" :alt="msg.metadata?.alt_text || '图片'" class="chat-image" @click="previewImage(msg.content)" @load="onImageLoaded(msg)" />
                                    <button v-if="msg.metadata?.alt_text" class="alt-text-btn" @click.stop="readAltText(msg)" title="朗读图片描述">🔊</button>
                                </div>
                                <div v-else-if="msg.message_type === 'voice' && msg.content" class="msg-voice-wrap">
                                    <div class="msg-voice" :class="{ 'msg-voice-self': msg.sender_id === myId }" @click="playVoice(msg)">
                                        <el-icon :size="20" style="margin-right:6px"><CaretRight v-if="!voicePlayingId || voicePlayingId !== msg.id" /><Loading v-else /></el-icon>
                                        <div class="voice-wave"><span v-for="i in 30" :key="i" class="voice-bar" :style="{ height: (10 + Math.sin(i * 0.5) * 8 + Math.random() * 4) + 'px' }"></span></div>
                                        <span class="voice-duration">{{ voiceDuration(msg) }}″</span>
                                    </div>
                                    <div class="msg-voice-actions" v-if="msg.sender_id === myId || msg.metadata?.transcript">
                                        <el-button v-if="!msg._transcribing && !msg.metadata?.transcript" text size="small" @click.stop="transcribeVoice(msg)">📝 转文字</el-button>
                                        <el-button v-else-if="msg._transcribing" text size="small" disabled>⏳ 识别中...</el-button>
                                        <div v-else-if="msg.metadata?.transcript" class="voice-transcript" @click.stop>
                                            <el-icon style="margin-right:4px;color:#67c23a"><Reading /></el-icon>
                                            <span>{{ msg.metadata.transcript }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="msg.message_type === 'contact' && msg.content" class="msg-contact-card" @click="addContactByCard(msg)">
                                    <div class="contact-card-avatar">{{ contactName(msg)?.charAt(0) || '?' }}</div>
                                    <div class="contact-card-info"><div class="contact-card-name">{{ contactName(msg) }}</div><div class="contact-card-hint">点击添加好友</div></div>
                                </div>
                                <div v-else-if="msg.message_type === 'location' && msg.content" class="msg-location" @click="openLocation(msg)">
                                    <div class="location-icon">📍</div>
                                    <div class="location-info"><div class="location-name">{{ locationName(msg) }}</div><div class="location-coords">{{ locationCoords(msg) }}</div></div>
                                </div>
                                <!-- 贴纸消息 -->
                                <div v-else-if="msg.message_type === 'sticker' && msg.metadata?.image_url" class="msg-sticker">
                                    <img :src="msg.metadata.image_url" class="sticker-img" :title="msg.metadata.emoji || ''" />
                                </div>
                                <!-- 文件消息 -->
                                <div v-else-if="msg.message_type === 'file'" class="msg-file" @click.stop="previewFile(msg)">
                                    <div class="file-icon">{{ fileIcon(msg) }}</div>
                                    <div class="file-info">
                                        <div class="file-name">{{ fileName(msg) }}</div>
                                        <div class="file-meta">
                                            <span class="file-size">{{ fileSizeStr(msg) }}</span>
                                            <span class="file-action">预览</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- 转发消息 -->
                                <div v-else-if="msg.message_type === 'forward'" class="msg-forward">
                                    <div class="forward-header">📨 {{ msg.metadata?.merge_forward ? '合并转发' : '转发消息' }}</div>
                                    <div v-if="msg.metadata?.merge_forward" class="forward-preview">
                                        <div v-for="(item, fi) in (msg.attachments || msg.metadata?.items || [])" :key="fi" class="forward-item">
                                            <span class="forward-item-sender">{{ item.sender }}:</span>
                                            <span class="forward-item-content">{{ item.content }}</span>
                                        </div>
                                        <div class="forward-count">共 {{ msg.metadata?.message_count || (msg.attachments?.length || 0) }} 条消息</div>
                                    </div>
                                    <div v-else class="forward-single">
                                        <span class="forward-origin">来自 {{ msg.metadata?.original_sender || '用户' }}</span>
                                        <span class="forward-text">{{ msg.content }}</span>
                                    </div>
                                </div>
                                <!-- 卡片消息：产品/订单/审批/文章/优惠券/待办，支持回调 -->
                                <div v-else-if="msg.message_type === 'card' && msg.metadata" class="msg-card" :class="'card-'+ (msg.metadata.type || 'info')">
                                    <!-- 产品卡片 -->
                                    <template v-if="msg.metadata.type === 'product_card' && msg.metadata.product">
                                        <a v-if="msg.metadata.product.image_url" :href="msg.metadata.product.action_url" target="_blank" class="card-img-link">
                                            <img :src="msg.metadata.product.image_url" class="card-img" @error="$event.target.style.display='none'" />
                                        </a>
                                        <div class="card-body">
                                            <a :href="msg.metadata.product.action_url" target="_blank" class="card-title-link">
                                                <div class="card-title">{{ msg.metadata.product.name }}</div>
                                            </a>
                                            <div class="card-desc">{{ msg.metadata.product.description }}</div>
                                            <div class="card-price">¥{{ msg.metadata.product.price }}</div>
                                            <div class="card-actions">
                                                <a :href="msg.metadata.product.action_url" target="_blank" class="card-btn primary">{{ msg.metadata.product.action_label || '立即购买' }}</a>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 订单卡片 -->
                                    <template v-else-if="msg.metadata.type === 'order_card' && msg.metadata.order">
                                        <div class="card-body">
                                            <div class="card-title">📦 订单 {{ msg.metadata.order.order_number }}</div>
                                            <div class="card-field"><span class="card-label">金额</span><span class="card-value">¥{{ msg.metadata.order.amount }}</span></div>
                                            <div class="card-field"><span class="card-label">状态</span><el-tag :type="msg.metadata.order.status === 'paid' ? 'success' : 'warning'" size="small">{{ msg.metadata.order.status }}</el-tag></div>
                                            <div class="card-actions">
                                                <a :href="msg.metadata.order.action_url" target="_blank" class="card-btn primary">{{ msg.metadata.order.action_label || '查看订单' }}</a>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 文章卡片 -->
                                    <template v-else-if="msg.metadata.type === 'article_card' && msg.metadata.article">
                                        <a v-if="msg.metadata.article.cover_url" :href="msg.metadata.article.action_url" target="_blank" class="card-img-link">
                                            <img :src="msg.metadata.article.cover_url" class="card-img" @error="$event.target.style.display='none'" />
                                        </a>
                                        <div class="card-body">
                                            <a :href="msg.metadata.article.action_url" target="_blank" class="card-title-link">
                                                <div class="card-title">{{ msg.metadata.article.title }}</div>
                                            </a>
                                            <div class="card-desc">{{ msg.metadata.article.summary }}</div>
                                            <div v-if="msg.metadata.article.author" class="card-meta">✍️ {{ msg.metadata.article.author }}</div>
                                            <div class="card-actions">
                                                <a :href="msg.metadata.article.action_url" target="_blank" class="card-btn primary">{{ msg.metadata.article.action_label || '阅读全文' }}</a>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 审批卡片 -->
                                    <template v-else-if="msg.metadata.type === 'approval_card' && msg.metadata.approval">
                                        <div class="card-body">
                                            <div class="card-title">📋 {{ msg.metadata.approval.title }}</div>
                                            <div v-if="msg.metadata.approval.applicant" class="card-field"><span class="card-label">申请人</span><span class="card-value">{{ msg.metadata.approval.applicant }}</span></div>
                                            <div v-if="msg.metadata.approval.amount" class="card-field"><span class="card-label">金额</span><span class="card-value">¥{{ msg.metadata.approval.amount }}</span></div>
                                            <div v-if="msg.metadata.approval.reason" class="card-desc">{{ msg.metadata.approval.reason }}</div>
                                            <div v-for="(f, fi) in (msg.metadata.approval.fields || [])" :key="fi" class="card-field">
                                                <span class="card-label">{{ f.label }}</span><span class="card-value">{{ f.value }}</span>
                                            </div>
                                            <div v-if="msg.metadata.actions?.length" class="card-actions">
                                                <span v-for="(a, ai) in msg.metadata.actions" :key="ai" class="card-btn" :class="a.type || 'default'"
                                                    @click="handleCardAction(msg, a)">{{ a.label }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 优惠券卡片 -->
                                    <template v-else-if="msg.metadata.type === 'coupon_card' && msg.metadata.coupon">
                                        <div class="card-body coupon-body">
                                            <div class="coupon-discount">{{ msg.metadata.coupon.discount }}</div>
                                            <div class="card-title">{{ msg.metadata.coupon.title }}</div>
                                            <div v-if="msg.metadata.coupon.condition" class="card-desc">{{ msg.metadata.coupon.condition }}</div>
                                            <div v-if="msg.metadata.coupon.expire_at" class="card-meta">⏳ {{ msg.metadata.coupon.expire_at }} 前有效</div>
                                            <div v-if="msg.metadata.actions?.length" class="card-actions">
                                                <span v-for="(a, ai) in msg.metadata.actions" :key="ai" class="card-btn" :class="a.type || 'primary'"
                                                    @click="handleCardAction(msg, a)">{{ a.label }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 待办卡片 -->
                                    <template v-else-if="msg.metadata.type === 'todo_card' && msg.metadata.todo">
                                        <div class="card-body">
                                            <div class="card-title">
                                                <el-tag v-if="msg.metadata.todo.priority" :type="msg.metadata.todo.priority === 'urgent' ? 'danger' : msg.metadata.todo.priority === 'high' ? 'warning' : 'info'" size="small" style="margin-right:6px">{{ msg.metadata.todo.priority }}</el-tag>
                                                {{ msg.metadata.todo.title }}
                                            </div>
                                            <div v-if="msg.metadata.todo.assignee" class="card-field"><span class="card-label">负责人</span><span class="card-value">{{ msg.metadata.todo.assignee }}</span></div>
                                            <div v-if="msg.metadata.todo.deadline" class="card-field"><span class="card-label">截止</span><span class="card-value">{{ msg.metadata.todo.deadline }}</span></div>
                                            <div v-if="msg.metadata.actions?.length" class="card-actions">
                                                <span v-for="(a, ai) in msg.metadata.actions" :key="ai" class="card-btn" :class="a.type || 'default'"
                                                    @click="handleCardAction(msg, a)">{{ a.label }}</span>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 通用自定义卡片 -->
                                    <template v-else-if="msg.metadata.card">
                                        <div class="card-body">
                                            <div class="card-title">{{ msg.metadata.card.title }}</div>
                                            <div v-for="(f, fi) in (msg.metadata.card.fields || [])" :key="fi" class="card-field">
                                                <span class="card-label">{{ f.label }}</span><span class="card-value">{{ f.value }}</span>
                                            </div>
                                            <div v-if="msg.metadata.card.actions?.length" class="card-actions">
                                                <span v-for="(a, ai) in msg.metadata.card.actions" :key="ai" class="card-btn" :class="a.type || 'default'"
                                                    @click="handleCardAction(msg, a)">{{ a.label }}</span>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                                <div v-else class="msg-text-wrap" :class="{ 'msg-collapsed': isLongText(msg.content) && !msg._expanded }">
                                    <div class="msg-text" v-html="renderMarkdown(renderCustomEmojis(renderSpoiler(msg.content)))"></div>
                                    <button v-if="isLongText(msg.content) && !msg._expanded" class="expand-btn" @click.stop="msg._expanded = true">展开全文 <el-icon><ArrowDown /></el-icon></button>
                                    <button v-else-if="isLongText(msg.content) && msg._expanded" class="expand-btn expanded" @click.stop="msg._expanded = false">收起 <el-icon><ArrowUp /></el-icon></button>
                                </div>
                                <span v-if="msg.is_edited" class="edited-badge"> 已编辑</span>
                                <div v-if="msg.attachments && msg.attachments.length" class="msg-attachments">
                                    <div v-for="(att, i) in msg.attachments" :key="i" class="msg-attachment-item">
                                        <template v-if="typeof att === 'string'"><a :href="att" target="_blank">📎 附件 {{ i + 1 }}</a></template>
                                        <template v-else>
                                            <a v-if="att.url && (att.mime||'').startsWith('image/')" :href="att.url" target="_blank" @click.stop.prevent="previewImage(att.url)"><img :src="att.url" alt="" class="attach-thumb" /><span class="attach-preview-label">预览</span></a>
                                            <a v-else :href="att.url" target="_blank" @click.stop.prevent="previewAttachment(att)">📎 {{ att.name || '附件' }}</a>
                                        </template>
                                    </div>
                                </div>
                                <div class="msg-encrypted" title="端到端加密">🔒</div>
                                <div class="msg-time">
                                    <span v-if="msg.sender_id === myId" class="msg-status-icon" :style="{color: messageStatusColor(msg)}">{{ messageStatusIcon(msg) }}</span>
                                    {{ formatTime(msg.created_at) }}
                                </div>
                                <div v-if="msg.linkPreview" class="link-card" @click.stop="openLink(msg.linkPreview.url)">
                                    <img v-if="msg.linkPreview.image" :src="msg.linkPreview.image" class="link-card-img" @error="msg.linkPreview.image = null" />
                                    <div class="link-card-body">
                                        <div class="link-card-title">{{ msg.linkPreview.title || msg.linkPreview.url }}</div>
                                        <div v-if="msg.linkPreview.description" class="link-card-desc">{{ msg.linkPreview.description }}</div>
                                        <div class="link-card-site">{{ msg.linkPreview.site_name || new URL(msg.linkPreview.url).hostname }}</div>
                                    </div>
                                </div>
                                <div v-if="msg.id" class="msg-reactions">
                                    <span v-for="r in (msg.reactions || [])" :key="r.emoji" class="reaction-badge" :class="{ 'reaction-me': r.me }" @click.stop="toggleReaction(msg, r.emoji)" :title="(r.users||[]).join(', ')">{{ r.emoji }} {{ r.count }}</span>
                                    <el-popover trigger="click" :width="240">
                                        <template #reference><span class="reaction-add" @click.stop>😊</span></template>
                                        <div class="emoji-picker"><span v-for="emoji in emojiList" :key="emoji" class="emoji-option" @click="toggleReaction(msg, emoji)">{{ emoji }}</span></div>
                                    </el-popover>
                                </div>
                                <div v-if="msg.sender_id === myId && msg.read_count > 0" class="msg-read-status">
                                    <el-icon style="margin-right:2px;font-size:12px"><Select /></el-icon> {{ msg.read_count }} 人已读
                                </div>
                            </div>
                            <div class="msg-actions">
                                <el-button text size="small" @click.stop="replyToMsg = msg" title="回复"><el-icon><ChatDotRound /></el-icon></el-button>
                                <el-button v-if="msg.thread_reply_count > 0 || msg.id === activeThreadId" text size="small" @click.stop="openThread(msg)" title="查看回复串">
                                    <el-icon><ChatLineSquare /></el-icon> {{ msg.thread_reply_count || '' }}
                                </el-button>
                                <el-button text size="small" @click.stop="openForward(msg)" title="转发"><el-icon><Share /></el-icon></el-button>
                                <el-button v-if="!msg.is_pinned" text size="small" @click.stop="pinMsg(msg)" title="置顶"><el-icon><StarFilled /></el-icon></el-button>
                                <el-button v-else text size="small" type="warning" @click.stop="unpinMsg(msg)" title="取消置顶"><el-icon><StarFilled /></el-icon></el-button>
                                <el-button v-if="msg.sender_id === myId && !msg.is_recalled" text size="small" @click.stop="editMessage(msg)" title="编辑"><el-icon><EditPen /></el-icon></el-button>
                                <el-button v-if="(msg.sender_id === myId || (activeConv?.type === 'group' && userRoleInGroup !== 'member')) && !msg.is_recalled" text size="small" @click.stop="recallMessage(msg)" title="撤回"><el-icon><RefreshLeft /></el-icon></el-button>
                                <el-button v-if="msg.sender_id === myId" text size="small" @click.stop="deleteMessage(msg)" title="删除"><el-icon><Delete /></el-icon></el-button>
                                <el-button text size="small" @click.stop="toggleFavorite(msg)" :title="msg.is_favorited ? '取消收藏' : '收藏'">
                                    <el-icon :color="msg.is_favorited ? '#e6a23c' : ''"><StarFilled v-if="msg.is_favorited" /><Star v-else /></el-icon>
                                </el-button>
                                <el-button text size="small" @click.stop="togglePendingMsg(msg)" :title="msg.is_pending ? '取消待处理' : '标记待处理'">
                                    <el-icon :color="msg.is_pending ? '#e6a23c' : ''"><Clock v-if="msg.is_pending" /><Watch v-else /></el-icon>
                                </el-button>
                                <el-button text size="small" @click.stop="openCreateTicket(msg)" title="创建工单"><el-icon><Tickets /></el-icon></el-button>
                                <el-button v-if="msg.sender_id !== myId" text size="small" @click.stop="openReportDialog(msg)" title="举报"><el-icon><Warning /></el-icon></el-button>
                                <el-button v-if="msg.sender_id !== myId && msg.content" text size="small" @click.stop="translateMessage(msg)" title="翻译"><el-icon><ChatLineRound /></el-icon></el-button>
                                <el-button text size="small" @click.stop="aiOptimizeMessage(msg)" title="AI 优化"><el-icon><MagicStick /></el-icon></el-button>
                                <el-button v-if="msg.sender_id !== myId" text size="small" @click.stop="shareContact(msg)" title="分享名片"><el-icon><User /></el-icon></el-button>
                            </div>
                        </div>
                        <div v-if="!messages.length" class="empty-chat" style="padding:60px 0"><el-empty description="暂无消息，发送第一条消息吧" :image-size="60" /></div>
                    </div>
                    <!-- THREAD: 话题面板 -->
                    <div v-if="activeThreadId" class="thread-panel">
                        <div class="thread-header">
                            <span>💬 回复串</span>
                            <el-button text size="small" @click="closeThread"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div class="thread-parent-msg" v-if="threadParentMsg">
                            <div class="thread-parent-sender">{{ threadParentMsg.sender?.name || '用户' }}</div>
                            <div class="thread-parent-content">{{ threadParentMsg.content }}</div>
                        </div>
                        <div class="thread-replies" ref="threadReplyRef">
                            <div v-for="r in threadReplies" :key="r.id" class="thread-reply-item">
                                <div class="thread-reply-avatar">
                                    <img v-if="r.sender?.avatar" :src="r.sender.avatar" class="msg-avatar-img" />
                                    <span v-else>{{ r.sender?.name?.charAt(0) || '?' }}</span>
                                </div>
                                <div class="thread-reply-body">
                                    <div class="thread-reply-sender">{{ r.sender?.name || '用户' }} <span class="thread-reply-time">{{ formatTime(r.created_at) }}</span></div>
                                    <div class="thread-reply-text">{{ r.content }}</div>
                                </div>
                            </div>
                            <div v-if="!threadReplies.length && !loadingThread" class="thread-empty">暂无回复</div>
                            <div v-if="loadingThread" class="thread-loading"><el-icon class="is-loading"><Loading /></el-icon></div>
                        </div>
                        <div class="thread-input-row">
                            <el-input v-model="threadInput" size="small" placeholder="回复此话题..." @keydown.enter.prevent="sendThreadReply" :disabled="sendingThread" />
                            <el-button size="small" type="primary" :loading="sendingThread" @click="sendThreadReply" :disabled="!threadInput.trim()">回复</el-button>
                        </div>
                    </div><!-- /thread-panel -->
                    </div><!-- /chat-messages-wrap -->
                    <div class="chat-input-area">
                        <div v-if="replyToMsg" class="reply-preview">
                            <div class="reply-preview-content">
                                <span class="reply-preview-label">回复 {{ replyToMsg.sender?.name || '用户' }}:</span>
                                <span class="reply-preview-text">{{ replyPreviewText(replyToMsg) }}</span>
                            </div>
                            <el-button text size="small" @click="replyToMsg = null"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div v-if="pendingAttachments.length" class="pending-attachments">
                            <div v-for="(att, i) in pendingAttachments" :key="i" class="pending-att-item">
                                <span class="att-name">📎 {{ att.name }}</span>
                                <el-button text size="small" @click="removePendingAttachment(i)"><el-icon><Close /></el-icon></el-button>
                            </div>
                        </div>
                        <!-- 斜杠命令建议 -->
                        <div v-if="slashSuggestions.length" class="slash-suggestions">
                            <div v-for="(cmd, i) in slashSuggestions" :key="cmd.command"
                                class="slash-item" :class="{ 'slash-active': i === slashSelectedIndex }"
                                @click="selectSlashCommand(cmd)" @mouseenter="slashSelectedIndex = i">
                                <span class="slash-cmd">{{ cmd.command }}</span>
                                <span class="slash-desc">{{ cmd.description }}</span>
                            </div>
                        </div>
                        <el-input v-model="inputMessage" type="textarea" :rows="3" placeholder="输入消息... 输入 / 查看命令" @keydown.enter.exact.prevent="handleInputEnter" @keydown.up.prevent="slashSelectUp" @keydown.down.prevent="slashSelectDown" @input="onSlashInput" />
                        <div class="input-actions">
                            <div class="input-action-left">
                                <el-button text size="small" @click="showFileUpload = true" title="上传文件/图片"><el-icon><Picture /></el-icon></el-button>
                                <el-button text size="small" @click="toggleVoiceRecord" :title="isRecording ? '停止录音' : '语音消息'" :type="isRecording ? 'danger' : 'default'">
                                    <template v-if="isRecording">{{ recordingDuration }}s</template>
                                    <el-icon v-else><Microphone /></el-icon>
                                </el-button>
                                <el-button text size="small" @click="showCannedPanel = !showCannedPanel" title="快捷回复" :type="showCannedPanel ? 'primary' : 'default'"><el-icon><ChatDotRound /></el-icon></el-button>
                                <el-button text size="small" @click="showStickerPanel = !showStickerPanel" title="贴纸/GIF" :type="showStickerPanel ? 'primary' : 'default'"><el-icon><Mug /></el-icon></el-button>
                                <el-button text size="small" @click="showAIPanel = !showAIPanel" title="AI 助手" :type="showAIPanel ? 'primary' : 'default'"><el-icon><MagicStick /></el-icon></el-button>
                                <el-dropdown trigger="click" v-if="inputMessage.trim()">
                                    <el-button text size="small" title="AI 写作"><el-icon><EditPen /></el-icon></el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item @click="aiWrite('polish')"><el-icon><Edit /></el-icon> 润色</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('expand')"><el-icon><FullScreen /></el-icon> 扩写</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('translate')"><el-icon><ChatLineRound /></el-icon> 翻译为中文</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('formal')"><el-icon><Document /></el-icon> 改正式语气</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('friendly')"><el-icon><Mug /></el-icon> 改友好语气</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-button text size="small" @click="markdownEnabled = !markdownEnabled" :title="markdownEnabled ? 'Markdown 已开启' : 'Markdown 已关闭'" :type="markdownEnabled ? 'primary' : 'default'">
                                    <code style="font-weight:700">MD</code>
                                </el-button>
                                <el-button text size="small" @click="showLocationDialog = true" title="发送位置"><el-icon><Location /></el-icon></el-button>
                                <el-button v-if="activeConv?.type === 'group'" text size="small" @click="openPollDialog" title="发起投票"><el-icon><Select /></el-icon></el-button>
                                <span class="text-muted">Enter 发送</span>
                                <el-button text size="small" @click="translateConversation" title="翻译整个会话"><el-icon><ChatLineRound /></el-icon></el-button>
                            </div>
                            <el-button type="primary" size="small" :loading="sending" @click="sendMessage">发送</el-button>
                        </div>
                        <div v-if="smartReplies.length && activeConv" class="smart-replies-bar">
                            <span class="smart-replies-label">💡 快捷回复</span>
                            <el-button v-for="(r, i) in smartReplies" :key="i" size="small" text @click="inputMessage = r; sendMessage()">{{ r }}</el-button>
                            <el-button size="small" text @click="smartReplies = []"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div v-if="showCannedPanel" class="canned-panel">
                            <div class="canned-header">
                                <span>📋 快捷回复</span>
                                <div>
                                    <el-button text size="small" @click="showAgentReplyManager = true; loadAgentQuickReplies()">管理</el-button>
                                    <el-button text size="small" @click="showCannedPanel = false"><el-icon><Close /></el-icon></el-button>
                                </div>
                            </div>
                            <div class="canned-categories">
                                <el-radio-group v-model="cannedCategory" size="small">
                                    <el-radio-button label="">全部</el-radio-button>
                                    <el-radio-button v-for="cat in cannedCategories" :key="cat" :label="cat">{{ cat }}</el-radio-button>
                                </el-radio-group>
                            </div>
                            <div class="canned-list">
                                <div v-for="r in filteredCanned" :key="r.id" class="canned-item" @click="selectCanned(r)">
                                    <div class="canned-title">{{ r.title }}</div>
                                    <div class="canned-preview">{{ r.content }}</div>
                                </div>
                                <div v-if="!filteredCanned.length" style="padding:20px;text-align:center;color:#999">暂无快捷回复</div>
                            </div>
                        </div>
                        <div v-if="showAIPanel" class="ai-panel">
                            <div class="ai-header"><span>🤖 AI 助手</span><el-button text size="small" @click="showAIPanel = false"><el-icon><Close /></el-icon></el-button></div>
                            <div class="ai-messages" ref="aiMsgRef">
                                <div v-for="(m, i) in aiMessages" :key="i" class="ai-message" :class="m.role">
                                    <span class="ai-role-tag">{{ m.role === 'assistant' ? 'AI' : '我' }}</span>
                                    <span class="ai-msg-content">{{ m.content }}</span>
                                </div>
                                <div v-if="aiStreaming" class="ai-streaming-indicator">AI 正在输入<el-icon class="is-loading" style="margin-left:4px"><Loading /></el-icon></div>
                            </div>
                            <div class="ai-input-row">
                                <el-input v-model="aiInput" size="small" placeholder="输入问题..." @keydown.enter.prevent="sendAiMessage" :disabled="aiStreaming" />
                                <el-button size="small" type="primary" :loading="aiLoading" @click="sendAiMessage" :disabled="aiStreaming">{{ aiStreaming ? '输入中...' : '发送' }}</el-button>
                            </div>
                        </div>
                        <div v-if="showStickerPanel" class="sticker-panel">
                            <div class="sticker-header">
                                <span>😊 贴纸 / GIF</span>
                                <el-radio-group v-model="stickerTab" size="small">
                                    <el-radio-button value="emoji">😀 表情</el-radio-button>
                                    <el-radio-button value="stickers">🖼️ 贴纸</el-radio-button>
                                    <el-radio-button value="custom">🏢 企业</el-radio-button>
                                    <el-radio-button value="gif">🎬 GIF</el-radio-button>
                                </el-radio-group>
                                <el-button text size="small" @click="showStickerPanel = false"><el-icon><Close /></el-icon></el-button>
                            </div>
                            <div v-if="stickerTab === 'emoji'" class="sticker-grid emoji-grid">
                                <span v-for="e in commonEmojis" :key="e" class="emoji-item" @click="sendStickerDirectly({ emoji: e })">{{ e }}</span>
                            </div>
                            <div v-if="stickerTab === 'stickers'" class="sticker-grid">
                                <div v-if="stickerPacks.length === 0" style="padding:20px;text-align:center;color:#999">暂无贴纸</div>
                                <div v-for="pack in stickerPacks" :key="pack.id" class="sticker-pack">
                                    <div class="sticker-pack-name">{{ pack.name }}</div>
                                    <div class="sticker-pack-items">
                                        <img v-for="s in pack.stickers" :key="s.id" :src="s.image_url" class="sticker-item" @click="sendStickerDirectly({ sticker: s })" :title="s.name" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="stickerTab === 'custom'" class="sticker-grid">
                                <div v-if="customEmojis.length === 0" style="padding:20px;text-align:center;color:#999">暂无企业自定义表情</div>
                                <div v-for="cat in customEmojiGroups" :key="cat.name" class="sticker-pack" v-if="cat.items.length">
                                    <div class="sticker-pack-name">{{ customEmojiCategoryLabel(cat.name) }}</div>
                                    <div class="sticker-pack-items">
                                        <div v-for="e in cat.items" :key="e.id" class="custom-emoji-item" @click="insertCustomEmoji(e)" :title="':' + e.shortcode + ':'">
                                            <img :src="e.image_url" class="sticker-item" />
                                            <span class="custom-emoji-label">:{{ e.shortcode }}:</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-if="stickerTab === 'gif'" class="gif-panel">
                                <div class="gif-search-row">
                                    <el-input v-model="gifQuery" size="small" placeholder="搜索 GIF..." @keydown.enter.prevent="searchGif" />
                                    <el-button size="small" type="primary" @click="searchGif">搜索</el-button>
                                </div>
                                <div v-if="gifResults.length" class="gif-grid">
                                    <img v-for="(gif, i) in gifResults" :key="i" :src="gif.preview || gif.url" class="gif-item" @click="sendStickerDirectly({ gif })" :title="gif.title" />
                                </div>
                                <div v-else style="padding:20px;text-align:center;color:#999">{{ gifQuery ? '无结果' : '输入关键词搜索 GIF' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="typing-indicator" v-if="typingUsers.length"><span>{{ typingUsers.join(', ') }} 正在输入...</span></div>
                </template>
                </template>
                <template v-else>
                    <div class="empty-state"><el-empty description="选择一个会话开始聊天" :image-size="80" /></div>
                </template>
            </div>
        </div>

        <!-- ====== Copilot 浮动按钮 ====== -->
        <el-button class="copilot-fab" circle :type="showCopilot ? 'primary' : 'default'" @click="showCopilot = !showCopilot" title="Copilot">
            <el-icon style="font-size:20px"><MagicStick /></el-icon>
        </el-button>
        <transition name="slide-right">
            <div v-if="showCopilot" class="copilot-sidebar">
                <div class="copilot-header">
                    <span>🤖 Copilot</span>
                    <el-button text size="small" @click="showCopilot = false"><el-icon><Close /></el-icon></el-button>
                </div>
                <div class="copilot-actions">
                    <el-button size="small" @click="copilotAction('summarize')" :disabled="!activeConv"><el-icon><MagicStick /></el-icon> 总结对话</el-button>
                    <el-button size="small" @click="copilotAction('extract')" :disabled="!activeConv"><el-icon><List /></el-icon> 提取待办</el-button>
                    <el-button size="small" @click="copilotAction('tag')" :disabled="!activeConv"><el-icon><CollectionTag /></el-icon> 打标签</el-button>
                    <el-button size="small" @click="copilotAction('translate')" :disabled="!inputMessage.trim()"><el-icon><ChatLineRound /></el-icon> 翻译输入</el-button>
                </div>
                <div class="copilot-content" ref="copilotRef">
                    <div v-if="copilotLoading" class="copilot-loading"><el-icon class="is-loading"><Loading /></el-icon> AI 思考中...</div>
                    <div v-for="(item, i) in copilotResults" :key="i" class="copilot-result-item">
                        <div class="copilot-result-title">{{ item.title }}</div>
                        <div class="copilot-result-body">{{ item.content }}</div>
                    </div>
                    <div v-if="!copilotResults.length && !copilotLoading" class="copilot-empty">点击上方按钮使用 AI 能力</div>
                </div>
            </div>
        </transition>

        <!-- ====== 对话框 ====== -->
        <el-dialog v-model="showNewChat" title="新建会话" width="400px">
            <el-select v-model="newChatUserIds" multiple filterable remote :remote-method="searchUsers" :loading="searching" placeholder="搜索用户..." style="width:100%">
                <el-option v-for="u in searchedUsers" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
            <template #footer><el-button @click="showNewChat = false">取消</el-button><el-button type="primary" @click="createNewChat">开始聊天</el-button></template>
        </el-dialog>
        <el-dialog v-model="showAddFriend" title="添加好友" width="400px">
            <el-select v-model="addFriendUserId" filterable remote :remote-method="searchUsers" :loading="searching" placeholder="搜索用户名、邮箱或手机号..." style="width:100%">
                <el-option v-for="u in searchedUsers" :key="u.id" :label="u.name" :value="u.id">
                    <div class="user-option"><span class="user-opt-name">{{ u.name }}</span><span class="user-opt-email">{{ u.email }}</span></div>
                </el-option>
            </el-select>
            <div class="dialog-hint">支持通过用户名、邮箱或手机号搜索用户</div>
            <template #footer><el-button @click="showAddFriend = false">取消</el-button><el-button type="primary" :loading="addFriendLoading" @click="submitAddFriend">发送请求</el-button></template>
        </el-dialog>
        <el-dialog v-model="showPendingRequests" title="好友请求" width="420px">
            <div v-for="req in pendingRequests" :key="req.id" class="pending-req-item">
                <div class="req-user-avatar">{{ (req.sender?.name || req.user?.name || '?').charAt(0) }}</div>
                <div class="add-friend-info">
                    <div class="req-user-name"><strong>{{ req.sender?.name || req.user?.name || '用户' }}</strong></div>
                    <div class="req-user-email">{{ req.sender?.email || req.user?.email || '' }}</div>
                    <div style="font-size:12px;color:#909399">请求添加好友</div>
                </div>
                <div style="display:flex;gap:4px">
                    <el-button size="small" type="primary" @click="handleFriendRequest(req.id, 'accepted')">接受</el-button>
                    <el-button size="small" @click="handleFriendRequest(req.id, 'rejected')">拒绝</el-button>
                </div>
            </div>
            <div v-if="!pendingRequests.length"><el-empty description="暂无请求" :image-size="50" /></div>
        </el-dialog>
        <el-dialog v-model="showFriendGroups" title="好友分组" width="400px">
            <div v-for="g in friendGroups" :key="g.id" class="friend-group-item">
                <el-input v-model="g.name" size="small" style="flex:1;margin-right:8px" @change="updateFriendGroup(g)" />
                <el-button text size="small" type="danger" @click="deleteFriendGroup(g.id)">删除</el-button>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px">
                <el-input v-model="newFriendGroupName" placeholder="新分组" size="small" />
                <el-button size="small" type="primary" @click="createFriendGroup">添加</el-button>
            </div>
        </el-dialog>
        <el-dialog v-model="showRemarkDialog" title="设置备注" width="360px">
            <el-input v-model="remarkText" placeholder="输入备注名" />
            <template #footer><el-button @click="showRemarkDialog = false">取消</el-button><el-button type="primary" @click="submitRemark">保存</el-button></template>
        </el-dialog>
        <el-dialog v-model="showMoveGroupDialog" title="移动分组" width="360px">
            <el-select v-model="moveGroupId" placeholder="选择分组" style="width:100%">
                <el-option v-for="g in friendGroups" :key="g.id" :label="g.name" :value="g.id" />
            </el-select>
            <template #footer><el-button @click="showMoveGroupDialog = false">取消</el-button><el-button type="primary" @click="submitMoveGroup">移动</el-button></template>
        </el-dialog>
        <el-dialog v-model="showForward" :title="forwardMsgs.length > 1 ? '合并转发 ('+forwardMsgs.length+' 条)' : '转发消息'" width="420px">
            <el-select v-model="forwardConvId" filterable remote :remote-method="searchForwardConvs" :loading="forwardSearching" placeholder="搜索或选择会话..." style="width:100%">
                <el-option v-for="c in forwardableConvs" :key="c.id" :label="c.name" :value="c.id">
                    <span>{{ c.type === 'group' ? '👥' : '👤' }} {{ c.name }}</span>
                </el-option>
            </el-select>
            <div v-if="forwardMsgs.length > 1" class="text-sm text-gray-400 mt-2">将合并转发 {{ forwardMsgs.length }} 条消息</div>
            <template #footer>
                <el-button @click="showForward = false">取消</el-button>
                <el-button type="primary" :loading="forwarding" @click="submitForward">转发 ({{ forwardMsgs.length }})</el-button>
            </template>
        </el-dialog>
        <el-dialog :model-value="!!editingMsg" title="编辑消息" width="480px" @update:model-value="val => { if(!val) editingMsg = null }" @close="editingMsg = null">
            <el-input v-model="editContent" type="textarea" :rows="4" placeholder="输入新内容..." />
            <template #footer><el-button @click="editingMsg = null">取消</el-button><el-button type="primary" @click="submitEdit">保存</el-button></template>
        </el-dialog>
        <el-dialog v-model="showAnnouncements" title="群公告" width="520px" @open="loadAnnouncements">
            <div v-if="!announcements.length && !loadingAnnouncements"><el-empty description="暂无公告" :image-size="50" /></div>
            <div v-loading="loadingAnnouncements" style="min-height:100px">
                <div v-for="a in announcements" :key="a.id" class="announcement-item">
                    <div class="announcement-header"><span class="announcement-title">{{ a.title }}</span><span class="announcement-time">{{ formatTime(a.created_at) }}</span></div>
                    <div class="announcement-content">{{ a.content }}</div>
                    <div class="announcement-footer">
                        <span>{{ a.sender?.name }} 发布</span>
                        <span>📖 {{ a.reads_count || a.read_count || 0 }} 人已读
                            <el-button v-if="!a.is_read" text size="small" type="primary" @click="markAnnouncementRead(a)">标记已读</el-button>
                            <el-button v-else text size="small" @click="showAnnouncementDetail(a)">查看详情</el-button>
                        </span>
                    </div>
                </div>
            </div>
            <template #footer><el-button @click="showCreateAnnouncement = true" type="primary">发布公告</el-button></template>
        </el-dialog>
        <el-dialog v-model="showCreateAnnouncement" title="发布公告" width="480px">
            <el-form label-position="top">
                <el-form-item label="标题"><el-input v-model="newAnnouncement.title" placeholder="公告标题" maxlength="200" /></el-form-item>
                <el-form-item label="内容"><el-input v-model="newAnnouncement.content" type="textarea" :rows="5" placeholder="公告内容" maxlength="10000" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showCreateAnnouncement = false">取消</el-button><el-button type="primary" :loading="creatingAnnouncement" @click="submitAnnouncement">发布</el-button></template>
        </el-dialog>
        <el-dialog v-model="showAnnouncementDetailDialog" title="已读详情" width="420px">
            <div v-if="announcementDetail">
                <div class="announcement-detail-header">{{ announcementDetail.title }}</div>
                <div style="margin:8px 0;font-size:13px;color:#666">共 {{ announcementDetail.total_members }} 人 · 已读 {{ announcementDetail.read_count }} 人 · 未读 {{ announcementDetail.unread_count }} 人</div>
                <el-progress :percentage="announcementDetail.total_members > 0 ? Math.round(announcementDetail.read_count / announcementDetail.total_members * 100) : 0" :stroke-width="14" :text-inside="true" :status="announcementDetail.read_count === announcementDetail.total_members ? 'success' : 'warning'" />
                <div style="margin-top:12px"><div class="read-list-title">已读成员：</div>
                    <div v-if="announcementDetail.read_users?.length"><el-tag v-for="u in announcementDetail.read_users" :key="u.id" size="small" style="margin:3px">{{ u.name }}</el-tag></div>
                    <div v-else style="color:#999;font-size:13px">暂无已读</div>
                </div>
            </div>
        </el-dialog>
        <el-dialog v-model="showSlowModeDialog" title="群慢速模式" width="400px">
            <p style="font-size:13px;color:#666;margin-bottom:12px">开启后，群成员每次发消息后需等待指定时间才能发送下一条消息。</p>
            <el-form label-position="top">
                <el-form-item label="慢速间隔"><el-select v-model="slowModeInterval" style="width:100%">
                    <el-option :value="0" label="关闭" /><el-option :value="5" label="5 秒" /><el-option :value="10" label="10 秒" /><el-option :value="30" label="30 秒" />
                    <el-option :value="60" label="1 分钟" /><el-option :value="300" label="5 分钟" /><el-option :value="600" label="10 分钟" /><el-option :value="3600" label="1 小时" />
                </el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="showSlowModeDialog = false">取消</el-button><el-button type="primary" :loading="savingSlowMode" @click="saveSlowMode">保存</el-button></template>
        </el-dialog>
        <el-dialog v-model="showInviteDialog" title="群邀请" width="480px" @open="loadInvites">
            <el-tabs v-model="inviteTab">
                <el-tab-pane label="生成邀请" name="create">
                    <el-form label-position="top">
                        <el-form-item label="过期时间"><el-select v-model="inviteExpires" style="width:100%">
                            <el-option :value="1" label="1 小时后" /><el-option :value="24" label="24 小时后" /><el-option :value="72" label="3 天后" />
                            <el-option :value="168" label="7 天后" /><el-option :value="720" label="30 天后" /><el-option :value="0" label="永不过期" />
                        </el-select></el-form-item>
                        <el-form-item label="使用次数限制"><el-select v-model="inviteMaxUses" style="width:100%">
                            <el-option :value="0" label="无限制" /><el-option :value="1" label="仅 1 次" /><el-option :value="5" label="5 次" />
                            <el-option :value="10" label="10 次" /><el-option :value="50" label="50 次" /><el-option :value="100" label="100 次" />
                        </el-select></el-form-item>
                        <el-button type="primary" :loading="creatingInvite" @click="createInvite" style="width:100%">生成邀请链接</el-button>
                        <div v-if="newInviteUrl" style="margin-top:12px">
                            <div style="font-size:13px;color:#666;margin-bottom:6px">邀请链接：</div>
                            <el-input v-model="newInviteUrl" readonly><template #append><el-button @click="copyInviteUrl">复制</el-button></template></el-input>
                        </div>
                    </el-form>
                </el-tab-pane>
                <el-tab-pane label="已生成链接" name="list">
                    <div v-if="invites.length === 0" style="text-align:center;padding:20px;color:#999">暂无邀请链接</div>
                    <div v-for="inv in invites" :key="inv.id" class="invite-item">
                        <div class="invite-info">
                            <div class="invite-url">{{ inv.url }}</div>
                            <div class="invite-meta">{{ inv.is_valid ? '✅ 有效' : '❌ 已失效' }} · 已用 {{ inv.use_count }}/{{ inv.max_uses || '∞' }} · {{ inv.expires_at ? '截止 ' + formatTime(inv.expires_at) : '永不过期' }}</div>
                        </div>
                        <el-button v-if="inv.is_valid" text size="small" type="danger" @click="revokeInvite(inv)">撤销</el-button>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-dialog>
        <el-dialog v-model="showFolderDialog" title="管理会话分组" width="420px">
            <div v-if="folders.length === 0" style="text-align:center;padding:16px;color:#999">暂无分组</div>
            <div v-for="f in folders" :key="f.id" class="folder-item">
                <el-input v-model="f.name" size="small" style="flex:1;margin-right:8px" @change="saveFolders" />
                <el-button text size="small" type="danger" @click="deleteFolder(f)">删除</el-button>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px">
                <el-input v-model="newFolderName" placeholder="新分组名称" size="small" />
                <el-button size="small" type="primary" @click="createFolder">添加</el-button>
            </div>
        </el-dialog>
        <el-dialog v-model="showLocationDialog" title="发送位置" width="420px">
            <el-form label-position="top">
                <el-form-item label="地点名称"><el-input v-model="locationNameInput" placeholder="如：公司大楼" /></el-form-item>
                <el-form-item label="经度"><el-input-number v-model="locationLng" :precision="6" :step="0.01" style="width:100%" /></el-form-item>
                <el-form-item label="纬度"><el-input-number v-model="locationLat" :precision="6" :step="0.01" style="width:100%" /></el-form-item>
                <el-form-item><el-button @click="getCurrentLocation" :loading="gettingLocation">获取当前位置</el-button></el-form-item>
            </el-form>
            <template #footer><el-button @click="showLocationDialog = false">取消</el-button><el-button type="primary" :loading="sendingLocation" @click="sendLocation">发送</el-button></template>
        </el-dialog>
        <el-dialog v-model="showDndSettings" title="消息免打扰" width="400px">
            <el-form label-position="top">
                <el-form-item><el-switch v-model="dndEnabled" active-text="启用免打扰" /></el-form-item>
                <el-form-item label="开始时间" v-if="dndEnabled"><el-time-picker v-model="dndStart" format="HH:mm" value-format="HH:mm" style="width:100%" placeholder="选择开始时间" /></el-form-item>
                <el-form-item label="结束时间" v-if="dndEnabled"><el-time-picker v-model="dndEnd" format="HH:mm" value-format="HH:mm" style="width:100%" placeholder="选择结束时间" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showDndSettings = false">取消</el-button><el-button type="primary" @click="saveDndSettings">保存</el-button></template>
        </el-dialog>
        <el-dialog v-model="showBlockedList" title="黑名单" width="400px">
            <div v-if="blockedUsers.length === 0"><el-empty description="暂无黑名单" :image-size="50" /></div>
            <div v-for="u in blockedUsers" :key="u.id" class="pending-req-item">
                <div class="add-friend-info"><strong>{{ u.name || '用户' }}</strong></div>
                <el-button size="small" @click="unblockUser(u.id)">取消拉黑</el-button>
            </div>
        </el-dialog>
        <el-dialog v-model="showCreateTicket" title="创建工单" width="480px">
            <el-form label-position="top">
                <el-form-item label="主题"><el-input v-model="ticketSubject" placeholder="工单主题" /></el-form-item>
                <el-form-item label="描述"><el-input v-model="ticketDescription" type="textarea" :rows="4" placeholder="问题描述..." /></el-form-item>
                <el-form-item label="优先级"><el-select v-model="ticketPriority" style="width:100%">
                    <el-option label="低" value="low" /><el-option label="中" value="medium" /><el-option label="高" value="high" /><el-option label="紧急" value="urgent" />
                </el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="showCreateTicket = false">取消</el-button><el-button type="primary" :loading="creatingTicket" @click="submitTicket">提交</el-button></template>
        </el-dialog>
        <!-- 举报 -->
        <el-dialog v-model="showReportDialog" title="举报" width="400px">
            <el-form label-position="top">
                <el-form-item label="举报对象">
                    <el-tag>{{ reportTarget?.name || (reportTarget?.sender?.name || '消息') }}</el-tag>
                </el-form-item>
                <el-form-item label="举报原因">
                    <el-select v-model="reportReason" style="width:100%">
                        <el-option label="垃圾广告" value="spam" />
                        <el-option label="骚扰谩骂" value="harassment" />
                        <el-option label="色情低俗" value="pornographic" />
                        <el-option label="违法违规" value="illegal" />
                        <el-option label="冒充他人" value="impersonation" />
                        <el-option label="侵权" value="copyright" />
                        <el-option label="其他" value="other" />
                    </el-select>
                </el-form-item>
                <el-form-item label="补充说明（可选）">
                    <el-input v-model="reportDescription" type="textarea" :rows="3" maxlength="1000" placeholder="请详细描述问题..." />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showReportDialog = false">取消</el-button>
                <el-button type="danger" :loading="submittingReport" @click="submitReport">提交举报</el-button>
            </template>
        </el-dialog>
        <!-- AI-015: 提取待办 -->
        <el-dialog v-model="showTaskDialog" title="📋 提取的待办事项" width="500px">
            <div v-if="taskLoading" style="text-align:center;padding:30px"><el-icon class="is-loading" style="font-size:24px"><Loading /></el-icon><p>AI 分析中...</p></div>
            <div v-else-if="extractedTasks.length === 0" style="text-align:center;padding:30px;color:#999">未提取到待办事项</div>
            <div v-else>
                <div v-for="(t, i) in extractedTasks" :key="i" class="task-item">
                    <el-tag :type="t.type === 'event' ? 'warning' : 'primary'" size="small">{{ t.type === 'event' ? '📅 日程' : '✅ 待办' }}</el-tag>
                    <span class="task-title">{{ t.title }}</span>
                    <span v-if="t.deadline" class="task-deadline">⏰ {{ t.deadline }}</span>
                    <span v-if="t.assignee" class="task-assignee">👤 {{ t.assignee }}</span>
                </div>
            </div>
            <template #footer><el-button @click="showTaskDialog = false">关闭</el-button></template>
        </el-dialog>
        <!-- AI 好友创建 -->
        <el-dialog v-model="showCreateAiFriend" title="🤖 创建 AI 好友" width="520px">
            <el-form label-position="top" size="small">
                <el-form-item label="名称">
                    <el-input v-model="newAiFriend.name" placeholder="如：翻译官、写作助手" />
                </el-form-item>
                <el-form-item label="头像">
                    <div class="avatar-selector">
                        <div class="avatar-preview">
                            <img :src="newAiFriend.avatar_url || getDefaultAvatar(newAiFriend.name)" class="avatar-img" />
                        </div>
                        <div class="avatar-options">
                            <div v-for="a in presetAvatars" :key="a.url" class="avatar-option" :class="{ selected: newAiFriend.avatar_url === a.url }" @click="newAiFriend.avatar_url = a.url">
                                <img :src="a.url" :title="a.label" />
                            </div>
                            <el-tooltip content="自定义头像 URL">
                                <el-button size="small" circle :type="newAiFriend.avatar_url && !presetAvatars.find(p=>p.url===newAiFriend.avatar_url) ? 'primary' : 'default'" @click="openAvatarInput = !openAvatarInput">URL</el-button>
                            </el-tooltip>
                            <el-upload :show-file-list="false" :http-request="uploadPersonalAvatar" accept="image/*">
                                <el-tooltip content="上传头像图片">
                                    <el-button size="small" circle type="success">+</el-button>
                                </el-tooltip>
                            </el-upload>
                        </div>
                        <el-input v-if="openAvatarInput" v-model="newAiFriend.avatar_url" placeholder="输入头像图片 URL..." size="small" style="margin-top:6px" />
                    </div>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="newAiFriend.category" style="width:100%">
                        <el-option label="通用助手" value="assistant" />
                        <el-option label="翻译官" value="translator" />
                        <el-option label="写作助手" value="writer" />
                        <el-option label="客服" value="custom_service" />
                        <el-option label="自定义" value="custom" />
                    </el-select>
                </el-form-item>
                <el-form-item label="模型提供商">
                    <el-select v-model="newAiFriend.provider" style="width:100%">
                        <el-option label="DeepSeek" value="deepseek" />
                        <el-option label="OpenAI" value="openai" />
                        <el-option label="Claude" value="claude" />
                        <el-option label="自定义 API" value="custom" />
                    </el-select>
                </el-form-item>
                <el-form-item label="模型名称">
                    <el-input v-model="newAiFriend.model_name" placeholder="如：deepseek-chat, gpt-4" />
                </el-form-item>
                <el-form-item label="API Key">
                    <el-input v-model="newAiFriend.api_key" type="password" placeholder="输入 API Key" show-password />
                </el-form-item>
                <el-form-item label="自定义 API 地址" v-if="newAiFriend.provider === 'custom'">
                    <el-input v-model="newAiFriend.api_base_url" placeholder="https://api.example.com/v1" />
                </el-form-item>
                <el-form-item label="系统提示词">
                    <el-input v-model="newAiFriend.system_prompt" type="textarea" :rows="3" placeholder="设定 AI 好友的角色和行为..." />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateAiFriend = false">取消</el-button>
                <el-button type="primary" :loading="creatingAiFriend" @click="submitCreateAiFriend">创建</el-button>
            </template>
        </el-dialog>
        <!-- AI 好友管理（管理员） -->
        <el-dialog v-model="showAiFriendAdmin" title="🤖 AI 好友管理" width="700px" @open="loadAdminAiFriends">
            <template #header><span>🤖 AI 好友管理 <span style="font-size:12px;color:#999;font-weight:400">— 管理员可创建全局可见的 AI 好友</span></span></template>
            <div class="admin-ai-header">
                <el-button size="small" type="primary" @click="showCreatePlatformAi = true"><el-icon><Plus /></el-icon> 创建平台 AI 好友</el-button>
                <el-tag v-if="adminAiFriends.length">共 {{ adminAiFriends.length }} 个</el-tag>
            </div>
            <el-table :data="adminAiFriends" v-loading="loadingAdminAi" empty-text="暂无平台 AI 好友" size="small" style="width:100%">
                <el-table-column label="头像" width="60">
                    <template #default="{ row }">
                        <img :src="row.user?.avatar || getDefaultAvatar(row.user?.name)" style="width:32px;height:32px;border-radius:50%" />
                    </template>
                </el-table-column>
                <el-table-column label="名称" prop="user.name" />
                <el-table-column label="分类" width="100">
                    <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
                </el-table-column>
                <el-table-column label="模型" width="140">
                    <template #default="{ row }">{{ row.llm_config?.provider || '-' }} / {{ row.llm_config?.model_name || '-' }}</template>
                </el-table-column>
                <el-table-column label="状态" width="80">
                    <template #default="{ row }"><el-tag :type="row.published_at ? 'success' : 'info'" size="small">{{ row.published_at ? '已发布' : '草稿' }}</el-tag></template>
                </el-table-column>
                <el-table-column label="操作" width="240">
                    <template #default="{ row }">
                        <el-button text size="small" @click="testAiFriend(row.id)" :loading="testingId === row.id">测试</el-button>
                        <el-button v-if="!row.published_at" text size="small" type="primary" @click="publishAiFriend(row.id)">发布</el-button>
                        <el-tag v-else size="small" type="success">已发布</el-tag>
                        <el-button text size="small" @click="viewAiFriendConvs(row)">💬 对话</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>
        <!-- AI 好友对话记录 -->
        <el-dialog v-model="showAiFriendConvs" :title="'💬 ' + (aiFriendConvsName || '') + ' 对话记录'" width="560px" @open="loadAiFriendConvs">
            <div v-loading="loadingAiFriendConvs">
                <div v-if="aiFriendConvs.length === 0" style="text-align:center;padding:32px 0;color:#999">暂无对话记录</div>
                <div v-for="conv in aiFriendConvs" :key="conv.id" style="border:1px solid #e4e7ed;border-radius:6px;padding:10px;margin-bottom:8px">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                        <img v-if="conv.user?.avatar" :src="conv.user.avatar" style="width:24px;height:24px;border-radius:50%" />
                        <span v-else style="width:24px;height:24px;border-radius:50%;background:#409eff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px">{{ conv.user?.name?.charAt(0) || '?' }}</span>
                        <span style="font-size:13px;font-weight:500">{{ conv.user?.name || '用户' }}</span>
                        <span style="font-size:11px;color:#999;margin-left:auto">{{ conv.messages_count }} 条消息</span>
                    </div>
                    <div style="font-size:12px;color:#666;background:#f5f7fa;padding:6px 8px;border-radius:4px;margin-bottom:4px">{{ conv.last_message?.substring(0, 100) || '无消息' }}</div>
                    <div style="font-size:11px;color:#bbb;text-align:right">{{ formatTime(conv.updated_at) }}</div>
                </div>
            </div>
        </el-dialog>
        <!-- 创建平台 AI 好友弹窗 -->
        <el-dialog v-model="showCreatePlatformAi" title="创建平台 AI 好友" width="520px">
            <el-form label-position="top" size="small">
                <el-form-item label="名称"><el-input v-model="platformAiForm.name" placeholder="如：智能助手" /></el-form-item>
                <el-form-item label="头像">
                    <div class="avatar-selector">
                        <div class="avatar-preview"><img :src="platformAiForm.avatar_url || getDefaultAvatar(platformAiForm.name)" class="avatar-img" /></div>
                        <div class="avatar-options">
                            <div v-for="a in presetAvatars" :key="a.url" class="avatar-option" :class="{selected:platformAiForm.avatar_url===a.url}" @click="platformAiForm.avatar_url=a.url"><img :src="a.url" /></div>
                            <el-upload :show-file-list="false" :http-request="uploadPlatformAvatar" accept="image/*">
                                <el-button size="small" circle>+</el-button>
                            </el-upload>
                        </div>
                        <el-input v-if="platformAiShowUrl" v-model="platformAiForm.avatar_url" placeholder="输入头像图片 URL..." size="small" style="margin-top:6px" />
                    </div>
                </el-form-item>
                <el-form-item label="分类"><el-select v-model="platformAiForm.category" style="width:100%">
                    <el-option label="通用助手" value="assistant" /><el-option label="翻译官" value="translator" />
                    <el-option label="写作助手" value="writer" /><el-option label="客服" value="custom_service" /><el-option label="自定义" value="custom" />
                </el-select></el-form-item>
                <el-form-item label="欢迎语"><el-input v-model="platformAiForm.welcome_message" placeholder="用户首次对话时显示" /></el-form-item>
                <el-form-item label="模型提供商"><el-select v-model="platformAiForm.provider" style="width:100%">
                    <el-option label="DeepSeek（平台 Key）" value="deepseek" /><el-option label="OpenAI" value="openai" />
                    <el-option label="Claude" value="claude" /><el-option label="自定义 API" value="custom" />
                </el-select></el-form-item>
                <el-form-item label="模型名称"><el-input v-model="platformAiForm.model_name" placeholder="deepseek-chat" /></el-form-item>
                <el-form-item label="API Key" v-if="platformAiForm.provider !== 'deepseek'"><el-input v-model="platformAiForm.api_key" type="password" show-password /></el-form-item>
                <el-form-item label="系统提示词"><el-input v-model="platformAiForm.system_prompt" type="textarea" :rows="3" placeholder="设定 AI 好友的角色和行为..." /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreatePlatformAi = false">取消</el-button>
                <el-button type="primary" :loading="creatingPlatformAi" @click="submitPlatformAi">创建并发布</el-button>
            </template>
        </el-dialog>
        <!-- SEC-006: 隐私设置 -->
        <el-dialog v-model="showPrivacySettings" title="隐私设置" width="420px" @open="loadPrivacySettings">
            <el-form label-position="top">
                <el-form-item label="加好友方式">
                    <el-radio-group v-model="privacySettings.friend_add_policy">
                        <el-radio value="everyone">允许所有人添加</el-radio>
                        <el-radio value="need_question">需回答问题</el-radio>
                        <el-radio value="nobody">不允许添加</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="在线状态">
                    <el-switch v-model="privacySettings.show_online_status" active-text="展示在线状态" inactive-text="隐藏" />
                </el-form-item>
                <el-form-item label="已读回执">
                    <el-switch v-model="privacySettings.show_read_receipt" active-text="发送已读回执" inactive-text="不发送" />
                </el-form-item>
                <el-form-item label="陌生人消息">
                    <el-switch v-model="privacySettings.allow_stranger_message" active-text="允许陌生人发消息" inactive-text="禁止" />
                </el-form-item>
                <el-divider />
                <el-form-item label="🔒 私密空间 PIN">
                    <div style="width:100%">
                        <div v-if="privacyPinStatus.has_pin" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                            <el-tag type="success">已设置</el-tag>
                            <el-button text size="small" @click="showSetPin = true; setPinMode = 'change'">修改 PIN</el-button>
                            <el-button text size="small" type="danger" @click="removePrivacyPin">清除 PIN</el-button>
                        </div>
                        <el-button v-else type="primary" size="small" @click="showSetPin = true; setPinMode = 'set'">设置 PIN</el-button>
                        <div style="font-size:12px;color:#909399;margin-top:4px">设置后查看隐藏会话需输入 PIN 码</div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPrivacySettings = false">取消</el-button>
                <el-button type="primary" :loading="savingPrivacy" @click="savePrivacySettings">保存</el-button>
            </template>
        </el-dialog>
        <!-- 私密空间 PIN 设置 -->
        <el-dialog v-model="showSetPin" :title="setPinMode === 'set' ? '设置私密空间 PIN' : '修改 PIN'" width="400px">
            <el-form label-position="top">
                <el-form-item v-if="setPinMode === 'change'" label="当前 PIN">
                    <el-input v-model="pinForm.currentPin" type="password" show-password maxlength="20" placeholder="输入当前 PIN" />
                </el-form-item>
                <el-form-item label="新 PIN">
                    <el-input v-model="pinForm.newPin" type="password" show-password maxlength="20" placeholder="4-20 位数字" />
                </el-form-item>
                <el-form-item label="确认 PIN">
                    <el-input v-model="pinForm.confirmPin" type="password" show-password maxlength="20" placeholder="再次输入新 PIN" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSetPin = false">取消</el-button>
                <el-button type="primary" :loading="savingPin" @click="submitPrivacyPin">确认</el-button>
            </template>
        </el-dialog>
        <!-- 私密空间解锁 -->
        <el-dialog v-model="showUnlockDialog" title="🔒 私密空间" width="380px" :close-on-click-modal="false" @close="onUnlockCancel">
            <div style="text-align:center;padding:10px 0">
                <el-icon :size="48" color="#409eff"><Lock /></el-icon>
                <p style="margin:12px 0 8px;font-weight:600">请输入 PIN 码查看隐藏会话</p>
                <el-input v-model="unlockPin" type="password" show-password maxlength="20" placeholder="输入 PIN 码"
                    size="large" style="width:200px" @keyup.enter="verifyUnlockPin" />
                <div v-if="unlockError" style="color:#f56c6c;font-size:13px;margin-top:6px">{{ unlockError }}</div>
            </div>
            <template #footer>
                <el-button @click="showUnlockDialog = false">取消</el-button>
                <el-button type="primary" :loading="unlocking" @click="verifyUnlockPin">解锁</el-button>
            </template>
        </el-dialog>
        <!-- 隐藏会话列表 -->
        <el-dialog v-model="showHiddenConvs" title="🔒 私密空间" width="480px">
            <div v-if="!hiddenConvs.length" style="text-align:center;padding:40px;color:#909399">暂无隐藏会话</div>
            <div v-else class="hidden-conv-list">
                <div v-for="conv in hiddenConvs" :key="conv.id" class="hidden-conv-item" @click="selectHiddenConv(conv)">
                    <div class="conv-avatar-wrap">
                        <div class="conv-avatar">{{ conv.name?.charAt(0) || '?' }}</div>
                    </div>
                    <div class="conv-info">
                        <div class="conv-top">
                            <span class="conv-name">{{ conv.name }}</span>
                            <span class="conv-time">{{ formatTime(conv.updated_at) }}</span>
                        </div>
                        <div class="conv-bottom">
                            <span class="conv-last">{{ conv.last_message?.content || '暂无消息' }}</span>
                        </div>
                    </div>
                    <el-button text size="small" type="info" @click.stop="unhideConv(conv)">取消隐藏</el-button>
                </div>
            </div>
        </el-dialog>
        <!-- OPR-011: 投票 -->
        <el-dialog v-model="showPollDialog" title="发起投票" width="480px">
            <el-form label-position="top">
                <el-form-item label="投票问题"><el-input v-model="pollQuestion" placeholder="输入投票问题..." /></el-form-item>
                <el-form-item label="投票类型">
                    <el-radio-group v-model="pollType">
                        <el-radio value="single">单选</el-radio>
                        <el-radio value="multiple">多选</el-radio>
                        <el-radio value="ranked">排序投票</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="选项">
                    <div v-for="(opt, i) in pollOptions" :key="i" class="poll-option-row">
                        <span v-if="pollType === 'ranked'" class="poll-rank-badge">{{ i + 1 }}</span>
                        <el-input v-model="pollOptions[i]" :placeholder="'选项 '+(i+1)" size="small" style="flex:1" />
                        <el-button v-if="pollOptions.length > 2" text size="small" type="danger" @click="pollOptions.splice(i,1)">×</el-button>
                    </div>
                    <div v-if="pollType === 'ranked'" class="poll-rank-hint">💡 投票者将按优先级排序选项</div>
                    <el-button v-if="pollOptions.length < 20" size="small" text @click="pollOptions.push('')">+ 添加选项</el-button>
                </el-form-item>
                <el-form-item label="设置">
                    <el-checkbox v-model="pollIsAnonymous">匿名投票</el-checkbox>
                    <el-checkbox v-model="pollHideResults" style="margin-left:12px">截止后显示结果</el-checkbox>
                </el-form-item>
                <el-form-item label="截止时间">
                    <el-select v-model="pollExpireHours" placeholder="不限制" clearable style="width:160px">
                        <el-option label="1 小时后" :value="1" />
                        <el-option label="6 小时后" :value="6" />
                        <el-option label="24 小时后" :value="24" />
                        <el-option label="3 天后" :value="72" />
                        <el-option label="7 天后" :value="168" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPollDialog = false">取消</el-button>
                <el-button type="primary" :loading="creatingPoll" @click="submitPoll">发布投票</el-button>
            </template>
        </el-dialog>
        <!-- 投票详情 -->
        <el-dialog v-model="showPollResult" :title="activePoll?.question || '投票详情'" width="400px">
            <div v-if="activePoll">
                <div v-for="opt in pollResults" :key="opt.key" class="poll-result-row">
                    <div class="poll-result-label">{{ opt.label }}</div>
                    <div class="poll-result-bar-wrap"><div class="poll-result-bar" :style="{width: opt.percentage+'%'}"></div></div>
                    <div class="poll-result-num">{{ opt.count }}票 ({{ opt.percentage }}%)</div>
                </div>
                <div style="margin-top:12px;font-size:13px;color:#999">
                    共 {{ pollTotalVotes }} 人投票
                    <span v-if="activePoll.is_anonymous"> · 匿名</span>
                    <span v-if="activePoll.is_closed"> · 已结束</span>
                </div>
            </div>
        </el-dialog>
        <!-- ═══════ 频道编辑对话框 ═══════ -->
        <el-dialog v-model="showChannelEdit" title="编辑圈子" width="420px" :close-on-click-modal="false">
            <el-form :model="channelEditForm" label-width="70px" size="small">
                <el-form-item label="名称" required>
                    <el-input v-model="channelEditForm.name" placeholder="圈子名称" maxlength="100" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="channelEditForm.description" type="textarea" :rows="2" placeholder="圈子描述" maxlength="500" />
                </el-form-item>
                <el-form-item label="头像">
                    <div class="channel-edit-avatar-row">
                        <div class="channel-edit-avatar-preview">
                            <img v-if="channelEditForm.avatar" :src="channelEditForm.avatar" class="channel-edit-avatar-img" />
                            <span v-else class="channel-edit-avatar-placeholder">{{ channelEditForm.icon || '#' }}</span>
                        </div>
                        <div class="channel-edit-avatar-actions">
                            <el-upload :show-file-list="false" :http-request="uploadChannelEditAvatar" accept="image/*">
                                <el-button size="small" type="success">上传头像</el-button>
                            </el-upload>
                            <el-button v-if="channelEditForm.avatar" size="small" text type="danger" @click="channelEditForm.avatar = ''">清除</el-button>
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="图标">
                    <el-input v-model="channelEditForm.icon" placeholder="表情符号如 # 📢 💬" maxlength="10" />
                </el-form-item>
                <el-form-item label="分类" v-if="channelCategories.length">
                    <el-select v-model="channelEditForm.category_id" placeholder="选择分类" clearable style="width:100%">
                        <el-option v-for="cat in channelCategories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showChannelEdit = false">取消</el-button>
                <el-button size="small" type="primary" :loading="channelEditing" @click="submitChannelEdit">保存</el-button>
            </template>
        </el-dialog>
        <!-- ═══════ 频道成员管理对话框 ═══════ -->
        <el-dialog v-model="showChannelMembers" title="圈子成员管理" width="480px" @open="loadChannelMembers">
            <div v-loading="loadingChannelMembersData">
                <el-alert :title="'我的角色：' + (myRoleInChannel === 'owner' ? '创建者' : myRoleInChannel === 'admin' ? '管理员' : '成员')" :type="myRoleInChannel === 'owner' ? 'warning' : myRoleInChannel === 'admin' ? 'success' : 'info'" :closable="false" show-icon style="margin-bottom:12px" />
                <div style="font-weight:bold;margin-bottom:8px">成员 ({{ channelMembersData.length }})</div>
                <div v-for="m in channelMembersData" :key="m.id" class="group-member-row">
                    <img v-if="m.user?.avatar_url" :src="m.user.avatar_url" style="width:28px;height:28px;border-radius:50%;margin-right:6px" />
                    <span v-else style="width:28px;height:28px;border-radius:50%;background:#409eff;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:6px">{{ m.user?.name?.charAt(0) || '?' }}</span>
                    <span>{{ m.user?.name || '用户#'+m.user_id }}</span>
                    <span v-if="m.role === 'owner'" class="role-tag creator-tag" style="margin-left:6px">创建者</span>
                    <span v-else-if="m.role === 'admin'" class="role-tag admin-tag" style="margin-left:6px">管理员</span>
                    <span v-else class="role-tag member-tag" style="margin-left:6px">成员</span>
                    <span style="flex:1" />
                    <template v-if="myRoleInChannel === 'owner' && m.user_id !== currentUserId && m.role !== 'owner'">
                        <el-button v-if="m.role === 'member'" text size="small" type="primary" @click="setChannelAdmin(m)">设为管理</el-button>
                        <el-button v-else text size="small" @click="removeChannelAdmin(m)">取消管理</el-button>
                        <el-button text size="small" type="danger" @click="kickChannelMember(m)">踢出</el-button>
                    </template>
                    <template v-else-if="myRoleInChannel === 'admin' && m.role === 'member'">
                        <el-button text size="small" type="danger" @click="kickChannelMember(m)">踢出</el-button>
                    </template>
                </div>
                <el-divider v-if="myRoleInChannel === 'owner'" />
                <div v-if="myRoleInChannel === 'owner'">
                    <div style="font-weight:bold;margin-bottom:8px">转让圈子</div>
                    <div style="display:flex;gap:8px">
                        <el-select v-model="channelTransferUserId" placeholder="选择新创建者" style="flex:1" size="small">
                            <el-option v-for="m in channelMembersData.filter(m => m.user_id !== currentUserId)" :key="m.user_id" :label="m.user?.name || '用户#'+m.user_id" :value="m.user_id" />
                        </el-select>
                        <el-button size="small" type="warning" :loading="channelTransferring" @click="confirmChannelTransfer">转让</el-button>
                    </div>
                </div>
            </div>
        </el-dialog>
        <!-- ═══════ 频道置顶消息对话框 ═══════ -->
        <el-dialog v-model="showPinnedMessages" title="📌 置顶消息" width="520px" @open="loadChannelPinnedMessages">
            <div v-if="!channelPinnedMessages.length" style="text-align:center;padding:40px;color:#999">暂无置顶消息</div>
            <div v-else class="pinned-messages-list">
                <div v-for="pm in channelPinnedMessages" :key="pm.id" class="pinned-msg-item">
                    <div class="pinned-msg-header">
                        <img v-if="pm.user?.avatar_url" :src="pm.user.avatar_url" style="width:24px;height:24px;border-radius:50%;margin-right:6px" />
                        <span v-else style="width:24px;height:24px;border-radius:50%;background:#409eff;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;margin-right:6px">{{ pm.user?.name?.charAt(0) || '?' }}</span>
                        <span class="pinned-msg-sender">{{ pm.user?.name || '用户' }}</span>
                        <span class="pinned-msg-time">{{ formatTime(pm.updated_at) }}</span>
                    </div>
                    <div class="pinned-msg-content">{{ pm.content }}</div>
                    <div v-if="myRoleInChannel !== 'member'" class="pinned-msg-actions">
                        <el-button text size="small" type="danger" @click="unpinChannelMessage(pm)">取消置顶</el-button>
                    </div>
                </div>
            </div>
        </el-dialog>
        <el-dialog v-model="showDashboard" title="IM 数据看板" width="640px" @open="loadDashboard">
            <div v-loading="loadingDashboard">
                <div class="dashboard-grid">
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.total_conversations || 0 }}</div><div class="dashboard-label">总会话</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.today_messages || 0 }}</div><div class="dashboard-label">今日消息</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.active_users || 0 }}</div><div class="dashboard-label">活跃用户</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.total_canned || 0 }}</div><div class="dashboard-label">快捷回复</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.avg_response_time || 0 }}s</div><div class="dashboard-label">平均响应</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.avg_satisfaction || '-' }}</div><div class="dashboard-label">满意度</div></div>
                </div>
            </div>
        </el-dialog>
        <!-- 群管理 -->
        <el-dialog v-model="showGroupManage" title="群管理" width="480px">
            <div v-loading="loadingGroupMembers">
                <el-alert v-if="userRoleInGroup === 'member'" title="您的角色：成员" type="info" :closable="false" show-icon style="margin-bottom:12px" />
                <el-alert v-else :title="'您的角色：'+(userRoleInGroup === 'creator' ? '创建者' : '管理员')" :type="userRoleInGroup === 'creator' ? 'warning' : 'success'" :closable="false" show-icon style="margin-bottom:12px" />
                <div style="margin-bottom:12px;font-weight:bold">成员列表 ({{ groupMembers.length }})</div>
                <div v-for="m in groupMembers" :key="m.id" class="group-member-row">
                    <span>{{ m.name || m.nickname || '用户#'+m.id }}</span>
                    <span v-if="m.pivot?.role === 'creator'" class="role-tag creator-tag">创建者</span>
                    <span v-else-if="m.pivot?.role === 'admin'" class="role-tag admin-tag">管理员</span>
                    <span v-else class="role-tag member-tag">成员</span>
                    <span style="flex:1" />
                    <template v-if="userRoleInGroup !== 'member' && m.id !== myId && m.pivot?.role !== 'creator'">
                        <el-button v-if="userRoleInGroup === 'creator' && m.pivot?.role !== 'admin'" text size="small" type="primary" @click="setAsAdmin(m)">设为管理</el-button>
                        <el-button v-if="userRoleInGroup === 'creator' && m.pivot?.role === 'admin'" text size="small" @click="removeAdmin(m)">取消管理</el-button>
                        <el-button text size="small" type="danger" @click="kickMember(m)">移除</el-button>
                    </template>
                </div>
                <el-divider />
                <!-- 入群审批 -->
                <div v-if="userRoleInGroup !== 'member'" style="margin-bottom:12px">
                    <div style="font-weight:bold;margin-bottom:6px">🔒 入群审批</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <span style="font-size:13px">开启入群审批：</span>
                        <el-switch v-model="groupJoinApproval" @change="toggleJoinApproval" />
                        <span style="font-size:12px;color:#909399">开启后新成员需管理员审批才能加入</span>
                    </div>
                    <div v-if="groupJoinApproval && pendingJoinReqs.length" class="join-requests-section">
                        <div style="font-weight:600;margin-bottom:6px;font-size:13px">待审批请求 ({{ pendingJoinReqs.length }})</div>
                        <div v-for="req in pendingJoinReqs" :key="req.id" class="join-req-row">
                            <span>{{ req.user?.name || '用户#'+req.user_id }}</span>
                            <span v-if="req.reason" style="color:#909399;font-size:12px;margin-left:4px">: {{ req.reason }}</span>
                            <span style="flex:1" />
                            <el-button size="small" type="success" text @click="handleJoinRequest(req, 'approve')">通过</el-button>
                            <el-button size="small" type="danger" text @click="handleJoinRequest(req, 'reject')">拒绝</el-button>
                        </div>
                    </div>
                </div>
                <!-- 群权限配置 -->
                <div v-if="userRoleInGroup !== 'member'" style="margin-bottom:12px">
                    <div style="font-weight:bold;margin-bottom:6px">⚙️ 群权限配置</div>
                    <div v-for="perm in groupPermissionDefs" :key="perm.key" class="perm-row">
                        <span style="font-size:13px;flex:1">{{ perm.label }}</span>
                        <el-switch :model-value="groupPermissions[perm.key]" @change="v => updateGroupPerm(perm.key, v)" size="small" />
                    </div>
                </div>
                <el-divider />
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <el-button v-if="userRoleInGroup === 'creator'" size="small" @click="showTransferOwner = true">转让群主</el-button>
                    <el-button size="small" @click="leaveCurrentGroup" type="warning">退出群聊</el-button>
                    <el-button v-if="userRoleInGroup === 'creator'" size="small" type="danger" @click="dismissCurrentGroup">解散群聊</el-button>
                </div>
            </div>
        </el-dialog>
        <!-- 转让群主 -->
        <el-dialog v-model="showTransferOwner" title="转让群主" width="400px">
            <el-select v-model="newOwnerId" placeholder="选择新群主" style="width:100%">
                <el-option v-for="m in groupMembers.filter(m=>m.id !== myId)" :key="m.id" :label="m.name || m.nickname || '用户#'+m.id" :value="m.id" />
            </el-select>
            <template #footer>
                <el-button @click="showTransferOwner = false">取消</el-button>
                <el-button type="primary" :loading="transferring" @click="confirmTransferOwner">确认转让</el-button>
            </template>
        </el-dialog>
        <!-- 敏感词管理 -->
        <el-dialog v-model="showSensitiveWords" title="敏感词管理" width="600px" @open="loadSensitiveWords">
            <div>
                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <el-input v-model="newSensitiveWord.word" placeholder="敏感词" size="small" style="width:140px" />
                    <el-input v-model="newSensitiveWord.replacement" placeholder="替换为(默认***)" size="small" style="width:100px" />
                    <el-select v-model="newSensitiveWord.category" placeholder="分类" size="small" style="width:100px" clearable>
                        <el-option label="通用" value="general" />
                        <el-option label="政治" value="political" />
                        <el-option label="广告" value="advertising" />
                        <el-option label="辱骂" value="abuse" />
                        <el-option label="色情" value="pornographic" />
                    </el-select>
                    <el-select v-model="newSensitiveWord.severity" placeholder="严重级别" size="small" style="width:100px">
                        <el-option label="低" value="low" />
                        <el-option label="中" value="medium" />
                        <el-option label="高" value="high" />
                        <el-option label="严重" value="critical" />
                    </el-select>
                    <el-button size="small" type="primary" :loading="addingSensitiveWord" @click="addSensitiveWord">添加</el-button>
                </div>
                <el-table :data="sensitiveWords" max-height="360" size="small" style="width:100%">
                    <el-table-column prop="word" label="敏感词" />
                    <el-table-column prop="replacement" label="替换为" width="80" />
                    <el-table-column prop="category" label="分类" width="80" />
                    <el-table-column prop="severity" label="级别" width="70">
                        <template #default="{row}">
                            <el-tag :type="row.severity === 'critical' ? 'danger' : row.severity === 'high' ? 'warning' : row.severity === 'medium' ? 'info' : 'success'" size="small">{{ {low:'低',medium:'中',high:'高',critical:'严重'}[row.severity] || row.severity }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="is_active" label="启用" width="60">
                        <template #default="{row}">
                            <el-switch :model-value="!!row.is_active" size="small" @change="toggleSensitiveWord(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="100">
                        <template #default="{row}">
                            <el-button text size="small" type="danger" @click="deleteSensitiveWord(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-divider />
                <div style="display:flex;gap:8px;align-items:center">
                    <el-input v-model="sensitiveTestText" placeholder="输入文本测试敏感词" size="small" style="flex:1" />
                    <el-button size="small" @click="testSensitiveWord" :loading="testingSensitive">测试</el-button>
                </div>
                <div v-if="sensitiveTestResult" style="margin-top:8px;padding:8px;background:#f5f7fa;border-radius:4px;font-size:13px">
                    <div>原文本: {{ sensitiveTestResult.original }}</div>
                    <div>替换后: <span v-html="sensitiveTestResult.filtered" /></div>
                    <div v-if="sensitiveTestResult.found?.length">
                        <el-tag v-for="w in sensitiveTestResult.found" :key="w" size="small" type="danger" style="margin:2px">{{ w }}</el-tag>
                    </div>
                    <div v-else style="color:#67c23a">✅ 无敏感词</div>
                </div>
            </div>
        </el-dialog>
        <!-- MSG-015: 文件上传对话框 -->
        <FileUploadDialog v-model="showFileUpload" @uploaded="onFilesUploaded" />
        <FilePreviewDialog v-model="showFilePreview" :file-url="previewFileData.url" :file-name="previewFileData.name"
            :file-size="previewFileData.size" :file-mime="previewFileData.mime" :ext="previewFileData.ext" />

        <!-- AI 主持人对话框 -->
        <el-dialog v-model="showModerator" title="🤖 AI 群主持人" width="580px" top="8vh" @open="initModerator">
            <div v-loading="moderatorLoading">
                <!-- 功能选择 -->
                <el-radio-group v-model="moderatorMode" size="small" class="moderator-mode-bar">
                    <el-radio-button value="summary">📝 讨论总结</el-radio-button>
                    <el-radio-button value="agenda">📋 生成议程</el-radio-button>
                    <el-radio-button value="mediate">🤝 争论调解</el-radio-button>
                    <el-radio-button value="focus">🎯 专注度检查</el-radio-button>
                </el-radio-group>

                <!-- 议程额外参数 -->
                <div v-if="moderatorMode === 'agenda'" class="moderator-extra">
                    <el-input v-model="moderatorTopic" placeholder="输入讨论主题（选填）" size="small" clearable />
                </div>
                <div v-if="moderatorMode === 'focus'" class="moderator-extra">
                    <el-input v-model="moderatorTopic" placeholder="请输入讨论主题" size="small" />
                </div>

                <div class="moderator-action-row">
                    <el-button type="primary" :loading="moderatorRunning" @click="runModerator">
                        {{ moderatorRunning ? '分析中...' : '开始分析' }}
                    </el-button>
                </div>

                <!-- 结果 -->
                <div v-if="moderatorResult" class="moderator-result">
                    <div v-if="moderatorMode === 'summary'" class="result-card">
                        <div class="result-title">📝 讨论总结</div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.summary)"></div>
                        <div v-if="moderatorResult.message_count" class="result-meta">共分析 {{ moderatorResult.message_count }} 条消息</div>
                    </div>
                    <div v-if="moderatorMode === 'agenda'" class="result-card">
                        <div class="result-title">📋 建议议程</div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.agenda)"></div>
                        <div v-if="moderatorResult.estimated_minutes" class="result-meta">预计总时长：约 {{ moderatorResult.estimated_minutes }} 分钟</div>
                    </div>
                    <div v-if="moderatorMode === 'mediate'" class="result-card">
                        <div class="result-title">🤝 调解分析</div>
                        <div :class="['debate-badge', moderatorResult.has_debate ? 'debate-yes' : 'debate-no']">
                            {{ moderatorResult.has_debate ? '⚠️ 检测到争论' : '✅ 讨论氛围良好' }}
                        </div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.analysis)"></div>
                    </div>
                    <div v-if="moderatorMode === 'focus'" class="result-card">
                        <div class="result-title">🎯 专注度分析</div>
                        <div :class="['focus-badge', 'focus-' + moderatorResult.focus_level]">
                            {{ {高:'🔥 专注度高',中:'👌 专注度中等',低:'⚠️ 专注度低'}[moderatorResult.focus_level] || '' }}
                        </div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.analysis)"></div>
                    </div>
                </div>
                <div v-else-if="!moderatorLoading" class="moderator-hint">选择一种模式，AI 将分析群聊消息并给出建议</div>
            </div>
        </el-dialog>
        <CallPanel ref="callPanelRef" v-model="callState" :call-type="callType" :call-partner="callPartner"
            :conversation-id="activeConv?.id" :my-name="myName" @call-ended="onCallEnded" />

        <!-- 投稿预览对话框 -->
        <el-dialog v-model="showSubmissionPreview" :title="'📝 ' + (previewSubmission?.title || '投稿预览')" width="680px" top="5vh" :close-on-click-modal="false">
            <template v-if="previewSubmission">
                <div class="oa-detail-header">
                    <div class="oa-detail-account">
                        <span class="oa-detail-acc-name">{{ activeConv?.oa_name }}</span>
                        <el-tag type="warning" size="small">待审核</el-tag>
                    </div>
                    <h2 class="oa-detail-title">{{ previewSubmission.title }}</h2>
                    <div class="oa-detail-author-row">
                        <span class="oa-detail-author">✍️ {{ previewSubmission.user?.name || '匿名' }}</span>
                        <span class="oa-detail-time">{{ formatTime(previewSubmission.created_at) }}</span>
                        <span>📄 {{ previewSubmission.content?.length || 0 }} 字</span>
                    </div>
                    <div v-if="previewSubmission.summary" class="preview-summary" style="margin-top:8px;color:#666;font-size:13px;padding:8px;background:#f5f7fa;border-radius:4px">
                        📌 {{ previewSubmission.summary }}
                    </div>
                </div>
                <div class="oa-detail-cover" v-if="previewSubmission.cover_image">
                    <img :src="previewSubmission.cover_image" class="oa-detail-cover-img" @error="$event.target.style.display='none'" />
                </div>
                <div class="oa-detail-content" v-html="previewSubmission.content" style="max-height:400px;overflow-y:auto"></div>
            </template>
            <template #footer>
                <el-button size="small" type="success" @click="doReview(previewSubmission, 'approve')">
                    <el-icon><Select /></el-icon> 通过并发布
                </el-button>
                <el-button size="small" type="danger" @click="doReview(previewSubmission, 'reject')">
                    <el-icon><Close /></el-icon> 拒绝
                </el-button>
                <el-button size="small" @click="showSubmissionPreview = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 评论管理对话框 -->
        <el-dialog v-model="showOaCommentManager" title="💬 评论管理" width="680px" top="5vh" :close-on-click-modal="false">
            <div style="margin-bottom:10px;display:flex;gap:8px;align-items:center">
                <el-input v-model="oaCommentSearch" placeholder="搜索评论..." size="small" clearable prefix-icon="Search" style="width:260px" />
                <el-select v-model="oaCommentMgrFilter" size="small" style="width:130px" @change="loadOaComments">
                    <el-option label="全部" value="all" />
                    <el-option label="待审核" value="pending" />
                    <el-option label="已通过" value="approved" />
                    <el-option label="已拒绝" value="rejected" />
                    <el-option label="已置顶" value="pinned" />
                </el-select>
                <span style="font-size:12px;color:#999">共 {{ oaComments.length }} 条评论</span>
                <el-badge v-if="pendingCommentCount > 0" :value="pendingCommentCount" style="margin-left:4px">
                    <el-tag size="small" type="danger">待审核</el-tag>
                </el-badge>
            </div>
            <div style="max-height:480px;overflow-y:auto">
                <div v-for="c in mgrFilteredComments" :key="c.id" class="oa-comment-item-mgr">
                    <div class="oa-comment-header">
                        <img v-if="c.user?.avatar" :src="c.user.avatar" class="oa-comment-avatar" />
                        <span v-else class="oa-comment-avatar-placeholder">{{ c.user?.name?.charAt(0) || '?' }}</span>
                        <span class="oa-comment-author">{{ c.user?.name || '匿名' }}</span>
                        <span v-if="c.user?.region" class="oa-comment-mgr-region" style="font-size:11px;color:#999">{{ c.user.region }}</span>
                        <span class="oa-comment-article">→ {{ c.article?.title?.substring(0, 28) }}</span>
                        <el-tag v-if="c.status === 'pending'" size="small" type="warning" style="margin-left:auto">⏳ 待审核</el-tag>
                        <el-tag v-else-if="c.status === 'rejected'" size="small" type="danger" style="margin-left:auto">❌ 已拒绝</el-tag>
                        <el-tag v-else-if="c.is_pinned" size="small" type="warning" style="margin-left:auto">📌 置顶</el-tag>
                        <span v-else class="oa-comment-time">{{ formatTime(c.created_at) }}</span>
                    </div>
                    <div class="oa-comment-content">{{ c.content }}</div>
                    <div v-if="c.image" class="oa-comment-mgr-image">
                        <img :src="c.image" style="max-width:160px;max-height:100px;border-radius:4px;margin-top:4px" />
                    </div>
                    <div class="oa-comment-mgr-footer" style="display:flex;gap:6px;margin-top:6px">
                        <span style="font-size:11px;color:#999">❤️ {{ c.likes_count || 0 }}</span>
                        <template v-if="c.status === 'pending'">
                            <el-button size="small" type="success" text @click="approveOaComment(c)">✅ 通过</el-button>
                            <el-button size="small" type="danger" text @click="rejectOaComment(c)">❌ 拒绝</el-button>
                        </template>
                        <template v-else>
                            <el-button size="small" text @click="startReplyComment(c)">💬 回复</el-button>
                            <el-button size="small" text :type="c.is_pinned ? 'warning' : 'default'" @click="togglePinComment(c)">
                                {{ c.is_pinned ? '📌 取消置顶' : '📌 置顶' }}
                            </el-button>
                            <el-button size="small" text type="danger" @click="deleteOaComment(c)">🗑️ 删除</el-button>
                        </template>
                    </div>
                    <!-- 子回复 -->
                    <div v-if="c.replies?.length" class="oa-comment-replies" style="margin:6px 0 0 30px">
                        <div v-for="r in c.replies" :key="r.id" class="oa-comment-reply" style="padding:3px 0">
                            <span class="oa-comment-author" style="font-size:12px">{{ r.user?.name || '匿名' }}：</span>
                            <span class="oa-comment-reply-text">{{ r.content }}</span>
                            <span class="oa-comment-time" style="font-size:10px">{{ formatTime(r.created_at) }}</span>
                        </div>
                    </div>
                    <!-- 回复输入框 -->
                    <div v-if="replyingTo === c.id" class="oa-reply-input" style="margin-top:8px;display:flex;gap:6px">
                        <el-input v-model="replyText" placeholder="输入回复..." size="small" style="flex:1" maxlength="1000" />
                        <el-button size="small" type="primary" :loading="replying" @click="submitReply(c)">发送</el-button>
                        <el-button size="small" @click="replyingTo = null">取消</el-button>
                    </div>
                </div>
                <el-empty v-if="!oaComments.length" description="暂无评论" :image-size="50" />
            </div>
        </el-dialog>

        <!-- 文章管理对话框 -->
        <el-dialog v-model="showOaArticleManager" title="📄 文章管理" width="700px" top="5vh" :close-on-click-modal="false">
            <div style="margin-bottom:10px;display:flex;gap:8px;align-items:center">
                <el-input v-model="oaArticleMgrSearch" placeholder="搜索文章..." size="small" clearable prefix-icon="Search" style="width:260px" />
                <el-select v-model="oaArticleMgrFilter" size="small" style="width:120px" @change="loadOaManagedArticles">
                    <el-option label="全部" value="all" />
                    <el-option label="已发布" value="published" />
                    <el-option label="草稿" value="draft" />
                    <el-option label="定时" value="scheduled" />
                </el-select>
                <el-button size="small" text @click="openOaEditor">✏️ 新建</el-button>
            </div>
            <div style="max-height:450px;overflow-y:auto">
                <div v-for="art in filteredOaManagedArticles" :key="art.id" class="oa-mgr-article">
                    <div class="oa-mgr-info">
                        <div class="oa-mgr-title">
                            <el-tag v-if="art.is_pinned" size="small" type="warning" style="margin-right:4px">📌 置顶</el-tag>
                            {{ art.title }}
                        </div>
                        <div class="oa-mgr-meta">
                            <el-tag :type="art.status === 'published' ? 'success' : art.status === 'scheduled' ? 'warning' : 'info'" size="small">
                                {{ art.status === 'published' ? '已发布' : art.status === 'scheduled' ? '⏰定时' : '草稿' }}
                            </el-tag>
                            <span v-if="art.scheduled_at" style="font-size:11px;color:#e6a23c">⏰ {{ formatTime(art.scheduled_at) }}</span>
                            <span>❤️ {{ art.likes_count || 0 }}</span>
                            <span>👁️ {{ art.reads_count || 0 }}</span>
                            <span>🔗 {{ art.shares_count || 0 }}</span>
                            <span>{{ formatTime(art.published_at || art.created_at) }}</span>
                        </div>
                    </div>
                    <div class="oa-mgr-actions">
                        <el-button v-if="!art.edited_at" size="small" text @click="editOaManagedArticle(art)" title="编辑">
                            <el-icon><Edit /></el-icon>
                        </el-button>
                        <el-tag v-else size="small" type="info" style="font-size:10px">已编辑</el-tag>
                        <el-button size="small" text @click="togglePinArticle(art)" :title="art.is_pinned ? '取消置顶' : '置顶'">
                            {{ art.is_pinned ? '📌' : '📍' }}
                        </el-button>
                        <el-button size="small" text @click="toggleArticleStatus(art)">
                            {{ art.status === 'published' ? '📥 下架' : '📤 发布' }}
                        </el-button>
                        <el-button size="small" text @click="openArticleStats(art)">📊</el-button>
                        <el-button size="small" text type="danger" @click="deleteOaManagedArticle(art)">🗑️</el-button>
                    </div>
                </div>
                <el-empty v-if="!oaManagedArticles.length" description="暂无文章" :image-size="50" />
            </div>
        </el-dialog>

        <!-- 文章统计对话框 -->
        <el-dialog v-model="showArticleStats" title="📊 文章统计" width="560px" top="5vh" :close-on-click-modal="false" @open="loadArticleStats">
            <div v-if="articleStatsData" v-loading="loadingArticleStats">
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px">
                    <div class="oa-stat-item">
                        <span class="oa-stat-num">{{ articleStatsData.reads_count }}</span>
                        <span class="oa-stat-label">总阅读</span>
                    </div>
                    <div class="oa-stat-item">
                        <span class="oa-stat-num">{{ articleStatsData.likes_count }}</span>
                        <span class="oa-stat-label">点赞</span>
                    </div>
                    <div class="oa-stat-item">
                        <span class="oa-stat-num">{{ articleStatsData.comments_count }}</span>
                        <span class="oa-stat-label">评论</span>
                    </div>
                    <div class="oa-stat-item">
                        <span class="oa-stat-num">{{ articleStatsData.shares_count }}</span>
                        <span class="oa-stat-label">分享</span>
                    </div>
                    <div class="oa-stat-item">
                        <span class="oa-stat-num">{{ articleStatsData.favorites_count }}</span>
                        <span class="oa-stat-label">收藏</span>
                    </div>
                    <div class="oa-stat-item">
                        <span class="oa-stat-num">{{ articleStatsData.today_reads }}</span>
                        <span class="oa-stat-label">今日阅读</span>
                    </div>
                </div>

                <div style="margin-bottom:12px">
                    <div style="font-size:13px;font-weight:500;margin-bottom:8px">📈 近7天阅读趋势</div>
                    <div style="display:flex;align-items:flex-end;gap:4px;height:80px;padding:0 4px">
                        <div v-for="day in articleStatsData.read_trend" :key="day.date" style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%">
                            <div style="font-size:10px;color:#909399;margin-bottom:2px">{{ day.count }}</div>
                            <div :style="{height: Math.max(4, (day.count / maxReadCount) * 55) + 'px', width:'100%', background:'#409eff', borderRadius:'3px 3px 0 0', transition:'height .3s'}" :title="day.date + ': ' + day.count + ' 次阅读'"></div>
                            <div style="font-size:9px;color:#bbb;margin-top:2px;writing-mode:vertical-lr;font-size:8px">{{ day.date.slice(5) }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="articleStatsData.recent_likers?.length" style="margin-top:12px">
                    <div style="font-size:13px;font-weight:500;margin-bottom:6px">❤️ 最近点赞</div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <div v-for="liker in articleStatsData.recent_likers" :key="liker.id" style="display:flex;align-items:center;gap:4px;padding:4px 8px;background:#f5f7fa;border-radius:16px;font-size:12px">
                            <img v-if="liker.avatar" :src="liker.avatar" style="width:20px;height:20px;border-radius:50%;object-fit:cover" />
                            <span v-else style="width:20px;height:20px;border-radius:50%;background:#409eff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px">{{ liker.name?.charAt(0) || '?' }}</span>
                            <span>{{ liker.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <el-empty v-else-if="!loadingArticleStats" description="暂无数据" :image-size="50" />
        </el-dialog>

        <!-- 粉丝管理对话框 -->
        <el-dialog v-model="showOaFollowers" title="👥 粉丝管理" width="560px" top="5vh" :close-on-click-modal="false" @open="loadOaFollowers">
            <div style="margin-bottom:10px">
                <el-input v-model="oaFollowerSearch" placeholder="搜索粉丝..." size="small" clearable prefix-icon="Search" style="width:260px" @keydown.enter="loadOaFollowers" />
            </div>
            <div style="max-height:400px;overflow-y:auto" v-loading="loadingOaFollowers">
                <div v-for="f in oaFollowers" :key="f.id" class="oa-follower-item" style="display:flex;align-items:center;gap:10px;padding:8px 4px;border-bottom:1px solid #f0f0f0">
                    <img v-if="f.user?.avatar" :src="f.user.avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover" />
                    <span v-else style="width:36px;height:36px;border-radius:50%;background:#409eff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0">{{ f.user?.name?.charAt(0) || '?' }}</span>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:14px;font-weight:500">{{ f.user?.name || '用户' }}</div>
                        <div style="font-size:12px;color:#999">{{ f.user?.email || '' }}</div>
                    </div>
                    <span style="font-size:11px;color:#999;white-space:nowrap">{{ formatTime(f.created_at) }} 关注</span>
                </div>
                <el-empty v-if="!oaFollowers.length && !loadingOaFollowers" description="暂无粉丝" :image-size="50" />
            </div>
        </el-dialog>

        <!-- 自动回复管理对话框 -->
        <el-dialog v-model="showOaAutoReply" title="🤖 自动回复管理" width="620px" top="5vh" :close-on-click-modal="false" @open="loadOaAutoReplies">
            <template #header>
                <div style="display:flex;align-items:center;gap:10px">
                    <span>🤖 自动回复管理</span>
                    <el-tag size="small" type="info" effect="plain">公众号: {{ activeConv?.oaAccount?.name || selectedOaAccount?.name || '' }}</el-tag>
                </div>
            </template>
            <el-tabs v-model="oaAutoReplyTab" @tab-change="loadOaAutoReplies">
                <el-tab-pane label="💬 关注回复" name="welcome">
                    <div style="margin-bottom:10px;color:#909399;font-size:12px">当用户关注公众号时自动发送的回复消息</div>
                    <div v-if="oaWelcomeReply" class="oa-auto-reply-card">
                        <div class="oa-auto-reply-header">
                            <span class="oa-auto-reply-label">回复内容</span>
                            <div style="display:flex;gap:4px">
                                <el-button size="small" text @click="editAutoReply(oaWelcomeReply)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteAutoReply(oaWelcomeReply)">删除</el-button>
                            </div>
                        </div>
                        <div v-if="oaWelcomeReply.content_type === 'text'" class="oa-auto-reply-content">{{ oaWelcomeReply.content }}</div>
                        <div v-else-if="oaWelcomeReply.content_type === 'image'" class="oa-auto-reply-content">
                            <img :src="oaWelcomeReply.media_url" style="max-width:100%;max-height:120px;border-radius:4px" />
                            <div style="margin-top:4px;font-size:12px;color:#666">{{ oaWelcomeReply.content }}</div>
                        </div>
                        <div v-else-if="oaWelcomeReply.content_type === 'article'" class="oa-auto-reply-content">
                            <span style="color:#409eff">📄 {{ oaWelcomeReply.content }}</span>
                        </div>
                        <div class="oa-auto-reply-meta">
                            <span :class="oaWelcomeReply.is_active ? 'status-active' : 'status-inactive'">{{ oaWelcomeReply.is_active ? '已启用' : '已禁用' }}</span>
                            <el-switch size="small" :model-value="oaWelcomeReply.is_active" @change="toggleAutoReplyActive(oaWelcomeReply)" style="margin-left:8px" />
                        </div>
                    </div>
                    <div v-else class="oa-auto-reply-empty">
                        <el-button size="small" type="primary" @click="openNewAutoReply('welcome')">创建关注回复</el-button>
                    </div>
                </el-tab-pane>
                <el-tab-pane label="🔑 关键词回复" name="keyword">
                    <div style="margin-bottom:10px;display:flex;gap:6px;align-items:center">
                        <el-input v-model="oaKeywordSearch" placeholder="搜索关键词..." size="small" clearable prefix-icon="Search" style="flex:1" />
                        <el-button size="small" type="primary" @click="openNewAutoReply('keyword')">新建</el-button>
                    </div>
                    <div v-if="oaKeywordReplies.length" class="oa-auto-reply-list">
                        <div v-for="r in oaFilteredKeywords" :key="r.id" class="oa-auto-reply-card">
                            <div class="oa-auto-reply-header">
                                <el-tag size="small" :type="r.match_type === 0 ? '' : 'warning'" effect="plain" style="margin-right:6px">
                                    {{ r.match_type === 0 ? '精确' : '模糊' }}
                                </el-tag>
                                <strong style="font-size:13px">{{ r.keyword }}</strong>
                                <div style="flex:1"></div>
                                <el-button size="small" text @click="editAutoReply(r)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteAutoReply(r)">删除</el-button>
                            </div>
                            <div v-if="r.content_type === 'text'" class="oa-auto-reply-content">{{ r.content }}</div>
                            <div v-else-if="r.content_type === 'image'" class="oa-auto-reply-content">
                                <img :src="r.media_url" style="max-width:100%;max-height:100px;border-radius:4px" />
                                <div style="margin-top:4px;font-size:12px;color:#666">{{ r.content }}</div>
                            </div>
                            <div v-else class="oa-auto-reply-content" style="color:#409eff">📄 {{ r.content }}</div>
                            <div class="oa-auto-reply-meta">
                                <span :class="r.is_active ? 'status-active' : 'status-inactive'">{{ r.is_active ? '已启用' : '已禁用' }}</span>
                                <el-switch size="small" :model-value="r.is_active" @change="toggleAutoReplyActive(r)" style="margin-left:8px" />
                                <span style="margin-left:12px;color:#999;font-size:11px">排序: {{ r.sort_order }}</span>
                            </div>
                        </div>
                    </div>
                    <el-empty v-else-if="!oaKeywordReplies.length" description="暂无关键词回复" :image-size="50" />
                </el-tab-pane>
                <el-tab-pane label="🔉 默认回复" name="default">
                    <div style="margin-bottom:10px;color:#909399;font-size:12px">当消息不匹配任何关键词时自动发送的回复</div>
                    <div v-if="oaDefaultReply" class="oa-auto-reply-card">
                        <div class="oa-auto-reply-header">
                            <span class="oa-auto-reply-label">回复内容</span>
                            <div style="display:flex;gap:4px">
                                <el-button size="small" text @click="editAutoReply(oaDefaultReply)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteAutoReply(oaDefaultReply)">删除</el-button>
                            </div>
                        </div>
                        <div v-if="oaDefaultReply.content_type === 'text'" class="oa-auto-reply-content">{{ oaDefaultReply.content }}</div>
                        <div v-else-if="oaDefaultReply.content_type === 'image'" class="oa-auto-reply-content">
                            <img :src="oaDefaultReply.media_url" style="max-width:100%;max-height:120px;border-radius:4px" />
                            <div style="margin-top:4px;font-size:12px;color:#666">{{ oaDefaultReply.content }}</div>
                        </div>
                        <div v-else class="oa-auto-reply-content" style="color:#409eff">📄 {{ oaDefaultReply.content }}</div>
                        <div class="oa-auto-reply-meta">
                            <span :class="oaDefaultReply.is_active ? 'status-active' : 'status-inactive'">{{ oaDefaultReply.is_active ? '已启用' : '已禁用' }}</span>
                            <el-switch size="small" :model-value="oaDefaultReply.is_active" @change="toggleAutoReplyActive(oaDefaultReply)" style="margin-left:8px" />
                        </div>
                    </div>
                    <div v-else class="oa-auto-reply-empty">
                        <el-button size="small" type="primary" @click="openNewAutoReply('default')">创建默认回复</el-button>
                    </div>
                </el-tab-pane>
            </el-tabs>

            <!-- 自动回复编辑对话框 -->
            <el-dialog v-model="showOaAutoReplyEditor" :title="editingAutoReply ? '编辑回复' : '新建回复'" width="520px" :close-on-click-modal="false" append-to-body>
                <el-form :model="oaAutoReplyForm" label-width="80px" size="small">
                    <el-form-item label="回复类型">
                        <el-tag>{{ oaAutoReplyForm.type === 'welcome' ? '关注回复' : oaAutoReplyForm.type === 'keyword' ? '关键词回复' : '默认回复' }}</el-tag>
                    </el-form-item>
                    <el-form-item v-if="oaAutoReplyForm.type === 'keyword'" label="关键词" required>
                        <el-input v-model="oaAutoReplyForm.keyword" placeholder="输入关键词" maxlength="100" />
                    </el-form-item>
                    <el-form-item v-if="oaAutoReplyForm.type === 'keyword'" label="匹配方式">
                        <el-radio-group v-model="oaAutoReplyForm.match_type">
                            <el-radio :value="0">精确匹配</el-radio>
                            <el-radio :value="1">模糊匹配</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="内容类型">
                        <el-radio-group v-model="oaAutoReplyForm.content_type">
                            <el-radio value="text">文本</el-radio>
                            <el-radio value="image">图片</el-radio>
                            <el-radio value="article">文章链接</el-radio>
                        </el-radio-group>
                    </el-form-item>
                    <el-form-item label="回复内容" required>
                        <el-input v-if="oaAutoReplyForm.content_type === 'text'" v-model="oaAutoReplyForm.content" type="textarea" :rows="4" placeholder="输入回复文本内容" maxlength="1000" show-word-limit />
                        <div v-else-if="oaAutoReplyForm.content_type === 'image'">
                            <el-input v-model="oaAutoReplyForm.content" placeholder="图片描述文本（选填）" style="margin-bottom:8px" />
                            <el-input v-model="oaAutoReplyForm.media_url" placeholder="图片URL" />
                        </div>
                        <div v-else>
                            <el-input v-model="oaAutoReplyForm.content" placeholder="文章标题" style="margin-bottom:8px" />
                            <el-input v-model="oaAutoReplyForm.media_url" placeholder="文章链接URL" />
                        </div>
                    </el-form-item>
                    <el-form-item label="排序">
                        <el-input-number v-model="oaAutoReplyForm.sort_order" :min="0" :max="999" size="small" />
                    </el-form-item>
                    <el-form-item label="启用">
                        <el-switch v-model="oaAutoReplyForm.is_active" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <el-button size="small" @click="showOaAutoReplyEditor = false">取消</el-button>
                    <el-button size="small" type="primary" :loading="savingAutoReply" @click="saveAutoReply">保存</el-button>
                </template>
            </el-dialog>
        </el-dialog>

        <!-- 自定义菜单管理对话框 -->
        <el-dialog v-model="showOaMenuManager" title="📋 自定义菜单" width="640px" top="5vh" :close-on-click-modal="false">
            <template #header>
                <div style="display:flex;align-items:center;gap:10px">
                    <span>📋 自定义菜单</span>
                    <el-tag size="small" type="info" effect="plain">{{ activeConv?.oaAccount?.name || '' }}</el-tag>
                    <span style="font-size:11px;color:#999">最多3个一级菜单，每个最多5个二级菜单</span>
                </div>
            </template>
            <div style="display:flex;gap:12px;min-height:300px">
                <!-- 左侧：菜单列表（手机预览风格） -->
                <div style="flex:1">
                    <div style="display:flex;gap:4px;background:#f5f5f5;border-radius:8px 8px 0 0;padding:8px 6px;border-bottom:1px solid #e4e7ed">
                        <span style="font-size:11px;color:#999;flex:1">菜单项</span>
                        <el-button size="small" type="primary" text @click="openNewMenu(null)">＋ 添加菜单</el-button>
                    </div>
                    <div style="background:#fafafa;min-height:250px;border-radius:0 0 8px 8px;padding:4px">
                        <div v-if="oaMenus.length === 0" style="text-align:center;padding:40px 0;color:#999;font-size:13px">
                            暂无菜单，点击「添加菜单」创建
                        </div>
                        <div v-for="menu in oaMenus" :key="menu.id" class="oa-menu-item" style="margin-bottom:2px">
                            <div class="oa-menu-row" style="display:flex;align-items:center;padding:6px 8px;background:#fff;border-radius:6px;border:1px solid #e4e7ed;margin:2px 0">
                                <el-tag size="small" :type="menu.type === 'view' ? 'success' : menu.type === 'miniprogram' ? 'warning' : ''" style="margin-right:6px">
                                    {{ menu.type === 'click' ? '点击' : menu.type === 'view' ? '跳转' : '小程序' }}
                                </el-tag>
                                <span style="font-size:13px;font-weight:500;flex:1">{{ menu.name }}</span>
                                <div style="display:flex;gap:2px">
                                    <el-button size="small" text @click="openNewMenu(menu.id)">＋子</el-button>
                                    <el-button size="small" text @click="editOaMenu(menu)">编辑</el-button>
                                    <el-button size="small" text type="danger" @click="deleteOaMenu(menu)">🗑️</el-button>
                                </div>
                            </div>
                            <!-- 子菜单 -->
                            <div v-if="menu.children?.length" style="margin-left:24px">
                                <div v-for="child in menu.children" :key="child.id" class="oa-menu-row oa-menu-child" style="display:flex;align-items:center;padding:5px 8px;background:#fff;border-radius:6px;border:1px solid #e8e8e8;margin:2px 0">
                                    <el-tag size="small" type="info" style="margin-right:6px;font-size:10px">子</el-tag>
                                    <el-tag size="small" :type="child.type === 'view' ? 'success' : child.type === 'miniprogram' ? 'warning' : ''" style="margin-right:6px">
                                        {{ child.type === 'click' ? '点击' : child.type === 'view' ? '跳转' : '小程序' }}
                                    </el-tag>
                                    <span style="font-size:12px;flex:1">{{ child.name }}</span>
                                    <div style="display:flex;gap:2px">
                                        <el-button size="small" text @click="editOaMenu(child)">编辑</el-button>
                                        <el-button size="small" text type="danger" @click="deleteOaMenu(child)">🗑️</el-button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>

        <!-- 菜单编辑对话框 -->
        <el-dialog v-model="showOaMenuEditor" :title="editingMenu ? '编辑菜单' : '新建菜单'" width="480px" :close-on-click-modal="false" append-to-body>
            <el-form :model="oaMenuForm" label-width="80px" size="small">
                <el-form-item label="菜单名称" required>
                    <el-input v-model="oaMenuForm.name" placeholder="菜单名称（最多4个字）" maxlength="40" />
                </el-form-item>
                <el-form-item label="菜单类型">
                    <el-radio-group v-model="oaMenuForm.type">
                        <el-radio value="click">点击发送消息</el-radio>
                        <el-radio value="view">跳转URL</el-radio>
                        <el-radio value="miniprogram">跳转小程序</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="oaMenuForm.type === 'click'" label="消息内容" required>
                    <el-input v-model="oaMenuForm.key" placeholder="输入点击后自动发送的消息内容" type="textarea" :rows="3" maxlength="300" />
                </el-form-item>
                <el-form-item v-if="oaMenuForm.type === 'view'" label="链接URL" required>
                    <el-input v-model="oaMenuForm.key" placeholder="https://..." maxlength="500" />
                </el-form-item>
                <template v-if="oaMenuForm.type === 'miniprogram'">
                    <el-form-item label="AppID" required>
                        <el-input v-model="oaMenuForm.app_id" placeholder="小程序AppID" maxlength="50" />
                    </el-form-item>
                    <el-form-item label="页面路径" required>
                        <el-input v-model="oaMenuForm.page_path" placeholder="pages/index/index" maxlength="255" />
                    </el-form-item>
                    <el-form-item label="备用链接">
                        <el-input v-model="oaMenuForm.key" placeholder="不支持小程序时跳转的URL" maxlength="500" />
                    </el-form-item>
                </template>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showOaMenuEditor = false">取消</el-button>
                <el-button size="small" type="primary" :loading="savingMenu" @click="saveOaMenu">保存</el-button>
            </template>
        </el-dialog>

        <!-- 素材管理对话框 -->
        <el-dialog v-model="showOaMaterialManager" title="📁 素材管理" width="700px" top="5vh" :close-on-click-modal="false">
            <div style="margin-bottom:10px;display:flex;gap:8px;align-items:center">
                <el-input v-model="oaMaterialSearch" placeholder="搜索素材..." size="small" clearable prefix-icon="Search" style="flex:1" />
                <el-select v-model="oaMaterialType" size="small" style="width:100px" @change="loadOaMaterials">
                    <el-option label="全部" value="" />
                    <el-option label="图片" value="image" />
                    <el-option label="文本" value="text" />
                </el-select>
                <el-upload :action="uploadMaterialUrl" :headers="uploadHeaders" :data="{ group: oaMaterialGroup }" :on-success="onMaterialUploaded" :show-file-list="false" accept="image/*" style="display:inline-block">
                    <el-button size="small" type="primary">📤 上传图片</el-button>
                </el-upload>
                <el-button size="small" text @click="openNewMaterial">✏️ 新建文本</el-button>
            </div>
            <div style="max-height:420px;overflow-y:auto" v-loading="loadingOaMaterials">
                <div v-if="oaMaterials.length === 0 && !loadingOaMaterials" style="text-align:center;padding:40px 0;color:#999">暂无素材</div>
                <!-- 图片素材网格 -->
                <div v-if="oaMaterialType !== 'text'" class="oa-material-grid">
                    <div v-for="m in filteredOaMaterials" :key="m.id" class="oa-material-card" v-if="m.type === 'image'">
                        <div class="oa-material-img-wrap">
                            <img :src="m.file_url" class="oa-material-img" @click="previewMaterialImg = m.file_url; showMaterialPreview = true" />
                            <div class="oa-material-img-actions">
                                <el-button size="small" text style="color:#fff" @click="copyMaterialUrl(m)">📋 复制</el-button>
                                <el-button size="small" text style="color:#fff" @click="deleteOaMaterial(m)">🗑️</el-button>
                            </div>
                        </div>
                        <div class="oa-material-info">
                            <div class="oa-material-name">{{ m.file_name || '未命名' }}</div>
                            <div class="oa-material-meta">{{ formatFileSize(m.file_size) }}</div>
                        </div>
                    </div>
                </div>
                <!-- 文本素材列表 -->
                <div v-if="oaMaterialType !== 'image'" class="oa-material-text-list">
                    <div v-for="m in filteredOaMaterials" :key="m.id" class="oa-material-text-item" v-if="m.type === 'text'">
                        <div class="oa-material-text-content">{{ m.content?.substring(0, 200) }}{{ m.content?.length > 200 ? '...' : '' }}</div>
                        <div class="oa-material-text-footer">
                            <el-tag size="small" type="success">文本</el-tag>
                            <span style="font-size:11px;color:#999;margin-left:8px">{{ formatTime(m.created_at) }}</span>
                            <div style="flex:1"></div>
                            <el-button size="small" text @click="editOaMaterial(m)">编辑</el-button>
                            <el-button size="small" text @click="copyMaterialContent(m)">📋 复制</el-button>
                            <el-button size="small" text type="danger" @click="deleteOaMaterial(m)">🗑️</el-button>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>

        <!-- 素材图片预览对话框 -->
        <el-dialog v-model="showMaterialPreview" title="图片预览" width="auto" :close-on-click-modal="true" align-center @close="previewMaterialImg = ''">
            <img v-if="previewMaterialImg" :src="previewMaterialImg" style="max-width:80vw;max-height:80vh;border-radius:4px" />
        </el-dialog>

        <!-- 文本素材编辑对话框 -->
        <el-dialog v-model="showOaMaterialEditor" :title="editingMaterial ? '编辑文本素材' : '新建文本素材'" width="520px" :close-on-click-modal="false" append-to-body>
            <el-form :model="oaMaterialForm" label-width="60px" size="small">
                <el-form-item label="内容" required>
                    <el-input v-model="oaMaterialForm.content" type="textarea" :rows="6" placeholder="输入文本内容，可用于自动回复等场景" maxlength="5000" show-word-limit />
                </el-form-item>
                <el-form-item label="分组">
                    <el-input v-model="oaMaterialForm.group" placeholder="分组名称（选填）" maxlength="50" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showOaMaterialEditor = false">取消</el-button>
                <el-button size="small" type="primary" :loading="savingMaterial" @click="saveOaMaterial">保存</el-button>
            </template>
        </el-dialog>

        <!-- 关注者消息对话框 -->
        <el-dialog v-model="showOaMessages" title="💬 关注者消息" width="720px" top="5vh" :close-on-click-modal="false" @open="loadOaConversations">
            <div style="display:flex;gap:0;min-height:400px;max-height:500px">
                <!-- 左侧：会话列表 -->
                <div style="width:240px;border-right:1px solid #e4e7ed;overflow-y:auto;flex-shrink:0">
                    <div style="padding:8px;font-size:12px;color:#999;border-bottom:1px solid #f0f0f0">共 {{ oaConversations.length }} 个会话</div>
                    <div v-for="conv in oaConversations" :key="conv.user_id" class="oa-msg-conv-item" :class="{ active: oaActiveConversation === conv.user_id }" @click="selectOaConversation(conv)">
                        <div style="display:flex;align-items:center;gap:6px">
                            <img v-if="conv.user?.avatar" :src="conv.user.avatar" style="width:28px;height:28px;border-radius:50%;object-fit:cover" />
                            <span v-else style="width:28px;height:28px;border-radius:50%;background:#409eff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0">{{ conv.user?.name?.charAt(0) || '?' }}</span>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ conv.user?.name || '用户' }}</div>
                                <div style="font-size:11px;color:#999;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ conv.last_message?.substring(0, 30) || '' }}</div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px">
                                <span style="font-size:10px;color:#bbb;white-space:nowrap">{{ formatTime(conv.last_time) }}</span>
                                <el-badge v-if="conv.unread_count > 0" :value="conv.unread_count" :max="99" class="oa-msg-badge" />
                            </div>
                        </div>
                    </div>
                    <div v-if="!oaConversations.length" style="text-align:center;padding:32px 0;color:#999;font-size:12px">暂无消息</div>
                </div>
                <!-- 右侧：消息详情 -->
                <div style="flex:1;display:flex;flex-direction:column;overflow:hidden">
                    <div v-if="oaActiveConversation" style="flex:1;overflow-y:auto;padding:10px">
                        <div v-for="msg in oaActiveMessages" :key="msg.id" class="oa-msg-bubble" :class="'oa-msg-' + msg.direction">
                            <div class="oa-msg-bubble-content">{{ msg.content }}</div>
                            <div class="oa-msg-bubble-time">{{ formatTime(msg.created_at) }}</div>
                        </div>
                        <div v-if="!oaActiveMessages.length" style="text-align:center;padding:32px 0;color:#999">暂无消息记录</div>
                    </div>
                    <div v-else style="flex:1;display:flex;align-items:center;justify-content:center;color:#999;font-size:13px">选择一个会话查看消息</div>
                    <!-- 回复输入 -->
                    <div v-if="oaActiveConversation" style="border-top:1px solid #e4e7ed;padding:8px;display:flex;gap:6px">
                        <el-input v-model="oaReplyText" placeholder="输入回复内容..." size="small" style="flex:1" maxlength="2000" @keydown.enter="sendOaReply" />
                        <el-button size="small" type="primary" :loading="sendingOaReply" @click="sendOaReply">发送</el-button>
                    </div>
                </div>
            </div>
        </el-dialog>

        <!-- 关注二维码对话框 -->
        <el-dialog v-model="showOaQrCode" title="📱 关注二维码" width="420px" top="10vh" :close-on-click-modal="true">
            <div v-if="oaQrData" style="text-align:center">
                <div style="margin-bottom:12px">
                    <div style="display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:8px">
                        <img v-if="oaQrData.avatar" :src="oaQrData.avatar" style="width:40px;height:40px;border-radius:50%;object-fit:cover" />
                        <span style="font-size:16px;font-weight:600">{{ oaQrData.name }}</span>
                    </div>
                    <span style="font-size:12px;color:#999">👥 {{ oaQrData.followers_count }} 人已关注</span>
                </div>
                <div style="background:#fff;display:inline-block;padding:12px;border-radius:8px;border:1px solid #e4e7ed;margin-bottom:10px">
                    <img :src="oaQrData.qr_url" style="width:240px;height:240px;display:block" alt="关注二维码" />
                </div>
                <div style="font-size:12px;color:#909399;margin-bottom:12px">扫描二维码关注公众号</div>
                <div style="display:flex;gap:8px;justify-content:center">
                    <el-button size="small" @click="downloadOaQrCode">⬇️ 下载二维码</el-button>
                    <el-button size="small" text @click="copyOaFollowUrl">📋 复制链接</el-button>
                </div>
            </div>
            <div v-else style="text-align:center;padding:32px 0" v-loading="loadingOaQr">
                <span style="color:#999">加载中...</span>
            </div>
        </el-dialog>

        <!-- 投稿对话框 -->
        <el-dialog v-model="showSubmitDialog" title="📝 投稿" width="600px" :close-on-click-modal="false">
            <el-form :model="submitForm" label-width="70px" size="small">
                <el-form-item label="标题" required>
                    <el-input v-model="submitForm.title" placeholder="文章标题" maxlength="200" />
                </el-form-item>
                <el-form-item label="摘要">
                    <el-input v-model="submitForm.summary" placeholder="文章摘要（选填）" maxlength="300" />
                </el-form-item>
                <el-form-item label="内容" required>
                    <el-input v-model="submitForm.content" type="textarea" :rows="10" placeholder="支持 HTML 格式的内容..." />
                </el-form-item>
                <el-form-item label="封面图">
                    <el-input v-model="submitForm.cover_image" placeholder="封面图片URL（选填）" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showSubmitDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="submitting" @click="doSubmitArticle">提交审核</el-button>
            </template>
        </el-dialog>

        <!-- 快捷回复管理对话框 -->
        <el-dialog v-model="showAgentReplyManager" title="💬 快捷回复管理" width="480px" :close-on-click-modal="false">
            <div style="margin-bottom:12px;display:flex;gap:6px;align-items:center">
                <el-input v-model="chatReplyMgrSearch" placeholder="搜索..." size="small" clearable prefix-icon="Search" style="flex:1" />
                <el-button size="small" type="primary" @click="openChatNewReply">新建</el-button>
                <el-button size="small" @click="loadAgentQuickReplies">刷新</el-button>
            </div>
            <div style="max-height:400px;overflow-y:auto">
                <div v-for="r in chatFilteredReplies" :key="r.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0">
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;margin-bottom:2px">
                            <el-tag size="small">{{ r.category || '通用' }}</el-tag>
                            <span>{{ r.title }}</span>
                        </div>
                        <div style="font-size:12px;color:#909399;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-left:50px">
                            {{ r.content?.substring(0, 80) }}{{ r.content?.length > 80 ? '...' : '' }}
                        </div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;margin-left:8px">
                        <el-button size="small" text @click="openChatEditReply(r)">编辑</el-button>
                        <el-button size="small" text type="danger" @click="deleteChatReply(r)">删除</el-button>
                    </div>
                </div>
                <div v-if="!chatFilteredReplies.length" style="padding:40px 0;text-align:center">
                    <el-empty description="暂无快捷回复" :image-size="40" />
                </div>
            </div>
        </el-dialog>

        <!-- 快捷回复编辑对话框 -->
        <el-dialog v-model="showChatReplyEditor" :title="chatEditingReply ? '编辑快捷回复' : '新建快捷回复'" width="420px">
            <el-form label-width="70px">
                <el-form-item label="分类">
                    <el-select v-model="chatReplyForm.category" filterable allow-create placeholder="选择或输入分类..." style="width:100%">
                        <el-option label="问候" value="问候" />
                        <el-option label="产品" value="产品" />
                        <el-option label="售后" value="售后" />
                        <el-option label="技术" value="技术" />
                        <el-option label="结束语" value="结束语" />
                        <el-option label="通用" value="通用" />
                    </el-select>
                </el-form-item>
                <el-form-item label="标题">
                    <el-input v-model="chatReplyForm.title" placeholder="快捷回复标题..." maxlength="100" />
                </el-form-item>
                <el-form-item label="内容">
                    <el-input v-model="chatReplyForm.content" type="textarea" :rows="4" placeholder="回复内容..." maxlength="1000" />
                </el-form-item>
                <el-form-item label="快捷词">
                    <el-select v-model="chatReplyForm.shortcuts" multiple filterable allow-create default-first-option
                        placeholder="输入触发词按回车..." style="width:100%">
                        <el-option v-for="s in chatReplyForm.shortcuts" :key="s" :label="s" :value="s" />
                    </el-select>
                    <div style="font-size:11px;color:#909399;margin-top:4px">输入关键词后按回车添加</div>
                </el-form-item>
                <el-form-item label="共享">
                    <el-switch v-model="chatReplyForm.is_shared" active-text="全员可见" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showChatReplyEditor = false">取消</el-button>
                <el-button size="small" type="primary" :loading="chatSavingReply" @click="saveChatReply">保存</el-button>
            </template>
        </el-dialog>
    </div>
    <!-- 积分交易记录 -->
    <PointsHistory v-model="pointsHistoryVisible" />
</template>
<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    Plus, Star, StarFilled, Bell, Mute, Delete, ArrowLeft, ArrowRight, ArrowDown,
    Message, Setting, MoreFilled, Edit, User, Search, Picture,
    ChatDotRound, Download, Close, Loading, CollectionTag, MagicStick,
    Tickets, DataBoard, Service, Share, Phone, VideoCamera, MuteNotification,
    RefreshLeft, RemoveFilled, EditPen, Timer, Microphone, CaretRight,
    Sunny, MoonNight, Monitor, Location, Select, Warning, Lock,
    ChatLineSquare, View, Upload, Document, Link, Reading, CopyDocument
} from '@element-plus/icons-vue'
import apiClient from '@/api/client'
import PointsHistory from '@/components/PointsHistory.vue'
import FileUploadDialog from './FileUploadDialog.vue'
import FilePreviewDialog from './FilePreviewDialog.vue'
import CallPanel from './CallPanel.vue'
import ChannelPanel from './ChannelPanel.vue'
import PlazaPanel from './PlazaPanel.vue'
import OaPanel from './OaPanel.vue'
import AgentWorkspace from '../im/AgentWorkspace.vue'

function stripHtml(html) {
  var d = document.createElement('div')
  d.innerHTML = html || ''
  return d.textContent || ''
}

function extractFirstImage(html) {
  if (!html) return ''
  var m = html.match(/<img[^>]+src=["']([^"']+)["']/)
  return m ? m[1] : ''
}

function getCoverImage(item) {
  return item.cover_image || (item.content ? extractFirstImage(item.content) : '')
}

function typeIcon(type) {
    const icons = { 'blog_post': '📝', 'oa_article': '📄', 'forum_post': '🌐', 'product': '🛒', 'default': '📌' }
    return icons[type] || icons.default
}
function typeName(type) {
    const names = { 'blog_post': '博客', 'oa_article': '公众号', 'forum_post': '广场', 'product': '商品', 'default': '内容' }
    return names[type] || names.default
}
const tplLabels = { discuss: '💬 讨论', poll: '📊 投票', qa: '❓ 问答', checkin: '✅ 打卡', announce: '📢 公告' }
const tplTagTypes = { discuss: '', poll: 'warning', qa: 'danger', checkin: 'success', announce: '' }
function tplLabel(t) { return tplLabels[t] || '💬 讨论' }
function tplTagType(t) { return tplTagTypes[t] || '' }
function plazaRenderContent(content) {
  if (!content) return ''
  if (content.indexOf('<') === -1) return content.replace(/\n/g, '<br>')
  return content
}

const router = useRouter()
const route = useRoute()
const myId = ref(0)
const myName = ref('')
const myAvatar = ref('')
const isMobile = ref(window.innerWidth < 768)
window.addEventListener('resize', () => { isMobile.value = window.innerWidth < 768 })

// ── 深色模式 ──
const themeMode = ref(localStorage.getItem('chat-theme') || 'light')
watch(themeMode, (val) => { localStorage.setItem('chat-theme', val); applyTheme(val) })
function applyTheme(mode) {
    const isDark = mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)
    document.documentElement.classList.toggle('chat-dark-mode', isDark)
}
function cycleTheme() {
    const modes = ['light', 'dark', 'auto']
    const idx = modes.indexOf(themeMode.value)
    themeMode.value = modes[(idx + 1) % modes.length]
}
applyTheme(themeMode.value)
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (themeMode.value === 'auto') applyTheme('auto') })

// ── 核心状态 ──
const loading = ref(false)
const conversations = ref([])
const activeConv = ref(null)
const messages = ref([])
const inputMessage = ref('')
// 斜杠命令
const slashCommands = ref([])
const slashSuggestions = ref([])
const slashSelectedIndex = ref(0)
const slashLoaded = ref(false)
const sending = ref(false)
const hasMore = ref(false)
const searchKeyword = ref('')
const sidebarTab = ref('messages')
const onlineStatusLabel = ref('')
const showSearchPanel = ref(false)
const globalSearchKeyword = ref('')
const globalSearchResults = ref([])

// ── 我的面板状态 ──
const userInfo = ref({})
const pointsBalance = ref(0)
const pointsHistoryVisible = ref(false)
const myProfileSection = ref('daily')
const readingListCount = ref(0)
const showMyInteractions = ref(false)
const myInteractionTab = ref('following')
const followingFeed = ref([])
const loadingFollowing = ref(false)
const readingList = ref([])
const loadingReadingList = ref(false)
const loadingFollows = ref(false)
const loadingFavs = ref(false)
const loadingLikes = ref(false)
const myFollowedAccounts = ref([])
const myFavorites = ref([])
const myLikes = ref([])

const dailyTasks = ref([
    { key: 'read', label: '浏览3篇文章', reward: 5, total: 3, progress: 0, done: false },
    { key: 'comment', label: '发表1条评论', reward: 3, total: 1, progress: 0, done: false },
    { key: 'share', label: '分享1次内容', reward: 2, total: 1, progress: 0, done: false },
    { key: 'tip', label: '打赏1次', reward: 5, total: 1, progress: 0, done: false },
])
const dailyEarned = computed(() => {
    return dailyTasks.value.filter(t => t.done).reduce((sum, t) => sum + t.reward, 0)
})
const dailyMax = 15

// ── 频道状态 ──
const myChannels = ref([])
const loadingChannels = ref(false)
const browseChannels = ref([])
const channelCategories = ref([])

// ── 公众号状态 ──
const oaPanelRef = ref(null)
const oaArticles = ref([])
const loadingOaArticles = ref(false)
const loadingMoreOaArticles = ref(false)
const oaArticleTotal = ref(0)
const oaArticlePage = ref(1)
const oaArticleSearch = ref('')
const oaArticleSort = ref('latest')
const selectedOaTag = ref('')
const selectedOaCollection = ref('')
const oaRecommendations = ref([])
const oaCollections = ref([])
const selectedOaAccount = ref(null)
const oaDashboard = ref(null)
const trendCharts = computed(() => {
    const t = oaDashboard.value?.trends
    if (!t) return []
    return [
        { key: 'followers', label: '👥 粉丝增长', total: oaDashboard.value?.today_new_followers + ' 今日新增', color: '#409eff' },
        { key: 'reads', label: '👁️ 阅读趋势', total: oaDashboard.value?.total_reads + ' 总计', color: '#67c23a' },
        { key: 'shares', label: '🔗 分享趋势', total: oaDashboard.value?.total_shares + ' 总计', color: '#e6a23c' },
        { key: 'likes', label: '❤️ 点赞趋势', total: oaDashboard.value?.total_likes + ' 总计', color: '#f56c6c' },
    ]
})
function trendMax(key) {
    const data = oaDashboard.value?.trends?.[key]
    if (!data?.length) return 1
    return Math.max(1, ...data.map(d => d.count))
}
const showOaArticleManager = ref(false)
const showOaFollowers = ref(false)
const oaFollowers = ref([])
const oaFollowerSearch = ref('')
const loadingOaFollowers = ref(false)
const oaCommentCount = ref(0)
const loadingOaDashboard = ref(false)
const showOaAutoReply = ref(false)
const oaAutoReplyTab = ref('welcome')
const oaAutoReplies = ref([])
const oaKeywordSearch = ref('')
const showOaAutoReplyEditor = ref(false)
const editingAutoReply = ref(null)
const savingAutoReply = ref(false)
const showArticleStats = ref(false)
const articleStatsData = ref(null)
const loadingArticleStats = ref(false)
const articleStatsTarget = ref(null)
const maxReadCount = computed(() => {
    if (!articleStatsData.value?.read_trend?.length) return 1
    return Math.max(1, ...articleStatsData.value.read_trend.map(d => d.count))
})
const oaAutoReplyForm = ref({
    type: 'welcome',
    keyword: '',
    match_type: 0,
    content: '',
    content_type: 'text',
    media_url: '',
    is_active: true,
    sort_order: 0,
})
const oaWelcomeReply = computed(() => oaAutoReplies.value.find(r => r.type === 'welcome') || null)
const oaDefaultReply = computed(() => oaAutoReplies.value.find(r => r.type === 'default') || null)
const oaKeywordReplies = computed(() => oaAutoReplies.value.filter(r => r.type === 'keyword'))
const oaFilteredKeywords = computed(() => {
    if (!oaKeywordSearch.value) return oaKeywordReplies.value
    const q = oaKeywordSearch.value.toLowerCase()
    return oaKeywordReplies.value.filter(r => r.keyword?.toLowerCase().includes(q))
})
const showOaMenuManager = ref(false)
const showOaMenuEditor = ref(false)
const editingMenu = ref(null)
const savingMenu = ref(false)
const oaMenus = ref([])
const oaMenuForm = ref({
    name: '',
    type: 'click',
    key: '',
    parent_id: null,
    app_id: '',
    page_path: '',
})
const showOaMaterialManager = ref(false)
const showOaMaterialEditor = ref(false)
const editingMaterial = ref(null)
const savingMaterial = ref(false)
const loadingOaMaterials = ref(false)
const oaMaterials = ref([])
const oaMaterialSearch = ref('')
const oaMaterialType = ref('')
const oaMaterialGroup = ref('')
const previewMaterialImg = ref('')
const showMaterialPreview = ref(false)
const showOaMessages = ref(false)
const oaConversations = ref([])
const oaActiveConversation = ref(null)
const oaActiveMessages = ref([])
const oaReplyText = ref('')
const sendingOaReply = ref(false)
const oaUnreadCount = ref(0)
const showOaQrCode = ref(false)
const oaQrData = ref(null)
const loadingOaQr = ref(false)
const oaMaterialForm = ref({ content: '', group: '' })
const uploadMaterialUrl = computed(() => {
    const id = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    return id ? '/api/official-accounts/' + id + '/materials/upload' : ''
})
const uploadHeaders = computed(() => ({ 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', 'Authorization': 'Bearer ' + (localStorage.getItem('auth_token') || '') }))
const filteredOaMaterials = computed(() => {
    let list = oaMaterials.value
    // 前端侧类型过滤（补充API已过滤的情况）
    if (oaMaterialType.value) {
        list = list.filter(m => m.type === oaMaterialType.value)
    }
    if (!oaMaterialSearch.value) return list
    const q = oaMaterialSearch.value.toLowerCase()
    return list.filter(m =>
        m.file_name?.toLowerCase().includes(q) ||
        m.content?.toLowerCase().includes(q)
    )
})
const showOaCommentManager = ref(false)
const oaComments = ref([])
const oaCommentSearch = ref('')
const oaCommentMgrFilter = ref('all')
const replyingTo = ref(null)
const replyText = ref('')
const replying = ref(false)
const oaManagedArticles = ref([])
const oaArticleMgrSearch = ref('')
const oaArticleMgrFilter = ref('all')
const loadingOaManagedArticles = ref(false)
const newCommentText = ref('')
const submittingComment = ref(false)
const detailReplyingTo = ref(null)
const detailReplyText = ref('')
const detailReplying = ref(false)
const searchingGlobal = ref(false)
const showNewChat = ref(false)
const searchedUsers = ref([])
const searching = ref(false)
const newChatUserIds = ref([])
const replyToMsg = ref(null)
const pendingAttachments = ref([])
const showFileUpload = ref(false)
function onFilesUploaded(files) {
    // 上传完成后，将文件 URL 作为附件发送到当前会话
    if (!activeConv.value || !files.length) return
    files.forEach(f => {
        pendingAttachments.value.push({ name: f.name, size: f.size, url: f.url })
    })
    ElMessage.info(`${files.length} 个文件已添加到待发送列表`)
}

const showCannedPanel = ref(false)
const showAIPanel = ref(false)
// AI 主持人
const showModerator = ref(false)
const moderatorMode = ref('summary')
const moderatorTopic = ref('')
const moderatorLoading = ref(false)
const moderatorRunning = ref(false)
const moderatorResult = ref(null)
const showStickerPanel = ref(false)
const stickerTab = ref('emoji')
const stickerPacks = ref([])
const customEmojis = ref([])
const customEmojiMap = ref({})
const customEmojiGroups = computed(() => {
    const groups = {}
    customEmojis.value.forEach(e => {
        const cat = e.category || 'general'
        if (!groups[cat]) groups[cat] = { name: cat, items: [] }
        groups[cat].items.push(e)
    })
    return Object.values(groups)
})
const gifQuery = ref('')
const gifResults = ref([])
const commonEmojis = ['😀','😃','😄','😁','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🫣','🤫','🤔','🫡','🤐','🤨','😐','😑','😶','🫥','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥴','😵','🤯','🥺','😢','😭','😤','😠','😡','🤬','💀','☠️','💩','🤡','👹','👺','👻','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾','💋','👋','🤚','🖐️','✋','🖖','🫰','🤌','🤏','👌','✌️','🤞','🫵','🤟','🤘','🤙','👈','👉','👆','🖕','👇','👍','👎','✊','👊','🤛','🤜','👏','🙌','🫶','👐','🤲','🤝','🙏','✍️','💅','🤳','💪','🦵','🦶','👂','🦻','👃','🧠','🫀','🫁','🦷','👅','👁️','👀','🧑','👨','👩','🧔','👱','👴','👵','🧓','🙍','🙎','🙅','🙆','💁','🙋','🧏','🤦','🤷','👳','👸','🤴','👲','🧕','🤵','👰','🤰','🫃','🫄','🤱','👼','🧑‍🎄','🦸','🦹','🧙','🧝','🧛','🧟','🧞','🧜','🧚','🧑‍⚕️','🧑‍🎓','🧑‍🏫','🧑‍⚖️','🧑‍🌾','🧑‍🍳','🧑‍🔧','🧑‍🏭','🧑‍💼','🧑‍🔬','🧑‍💻','🧑‍🎤','🧑‍🎨','🧑‍✈️','🧑‍🚀','🧑‍🚒','👮','🕵️','💂','🥷','👷','🫅','🤴','👸','👳','👲','🧕','🤵','👰','🤰','🫃','🫄','🤱','👼','🎅','🤶','🧑‍🎄','🦸','🦹','🧙','🧝','🧛','🧟','🧞','🧜','🧚','🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🙈','🙉','🙊','🐒','🐔','🐧','🐦','🐤','🐣','🐥','🦆','🦅','🦉','🦇','🐺','🐗','🐴','🦄','🐝','🐛','🦋','🐌','🐞','🐜','🦟','🦗','🪳','🪰','🪲','🪴','🐢','🐍','🦎','🦖','🦕','🐙','🦑','🦐','🦞','🦀','🐡','🐠','🐟','🐬','🐳','🐋','🦈','🪸','🐊','🐅','🐆','🦓','🦍','🦧','🐘','🦛','🦏','🐪','🐫','🦒','🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙','🐐','🦌','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🪶','🐓','🦃','🦤','🦚','🦜','🦢','🦩','🕊️','🐇','🦝','🦨','🦡','🦫','🦦','🦥','🐁','🐀','🐿️','🦔','🐾','🐉','🐲','🌵','🎄','🌲','🌳','🌴','🪵','🌱','🌿','☘️','🍀','🎍','🪴','🎋','🍃','🍂','🍁','🪺','🪹','🍄','🐚','🪨','🌾','💐','🌷','🌹','🥀','🌺','🌸','🌼','🌻','🌞','🌝','🌛','🌜','🌚','🌕','🌖','🌗','🌘','🌑','🌒','🌓','🌔','🌙','🌎','🌍','🌏','🪐','💫','⭐','🌟','✨','⚡','☄️','💥','🔥','🌪️','🌈','☀️','🌤️','⛅','🌥️','☁️','🌦️','🌧️','⛈️','🌩️','🌨️','❄️','☃️','⛄','🌬️','💨','💧','💦','🫧','☔','☂️','🌊','🪐','🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐','🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑','🥦','🥬','🥒','🌶️','🫑','🌽','🥕','🫒','🧄','🧅','🥔','🍠','🫘','🥐','🍞','🥖','🥨','🧀','🥚','🍳','🧈','🥞','🧇','🥓','🥩','🍗','🍖','🦴','🌭','🍔','🍟','🍕','🫓','🥪','🥙','🧆','🌮','🌯','🫔','🥗','🥘','🫕','🥫','🍝','🍜','🍲','🍛','🍣','🍱','🥟','🦪','🍤','🍙','🍚','🍘','🍥','🥠','🥮','🍢','🍡','🍧','🍨','🍦','🥧','🧁','🍰','🎂','🍮','🍭','🍬','🍫','🍿','🍩','🍪','🌰','🥜','🍯','🥛','🍼','🫖','☕','🍵','🧃','🥤','🧋','🍶','🍺','🍻','🥂','🍷','🫗','🥃','🍸','🍹','🧉','🍾','🧊','🥄','🍴','🍽️','🥣','🥡','🥢','🧂','🎃','🎄','🎆','🎇','🧨','✨','🎈','🎉','🎊','🎋','🎍','🎎','🎏','🎐','🎑','🧧','🎀','🎁','🎗️','🎞️','🎟️','🎫','🎖️','🏆','🏅','🥇','🥈','🥉','⚽','⚾','🥎','🏀','🏐','🏈','🏉','🎾','🥏','🎳','🏏','🏑','🏒','🥍','🏓','🏸','🥊','🥋','🥅','⛳','⛸️','🎣','🤿','🎽','🎿','🛷','🥌','🎯','🪀','🪁','🔫','🎱','🔮','🪄','🎮','🕹️','🎰','🎲','🧩','♟️','🎭','🎨','🧵','🪡','🧶','🪢','🎼','🎤','🎧','🪘','🥁','🪗','🎷','🎺','🎸','🪕','🎻','🎹','🎵','🎶','🎙️','📻','📱','📲','☎️','📞','📟','📠','🔋','🪫','🔌','💻','🖥️','🖨️','⌨️','🖱️','🖲️','💽','💾','💿','📀','🧮','🎥','🎬','📺','📷','📸','📹','🎥','📽️','🎞️','📞','🔍','🔎','🕯️','💡','🔦','🏮','🪔','📔','📕','📖','📗','📘','📙','📚','📓','📒','📃','📜','📄','📰','🗞️','📑','🔖','🏷️','💰','🪙','💴','💵','💶','💷','💸','💳','🧾','💹','✉️','📧','📨','📩','📤','📥','📦','📫','📪','📬','📭','📮','🗳️','✏️','✒️','🖋️','🖊️','🖌️','🖍️','📝','📁','📂','🗂️','📅','📆','🗒️','🗓️','📇','📈','📉','📊','📋','📌','📍','📎','🖇️','📏','📐','✂️','🗃️','🗄️','🗑️','🔒','🔓','🔏','🔐','🔑','🗝️','🔨','🪓','⛏️','⚒️','🛠️','🗡️','⚔️','💣','🪃','🏹','🛡️','🔧','🔩','⚙️','🗜️','⚖️','🦯','🔗','⛓️','🪝','🧰','🧲','🪜','⚗️','🧪','🧫','🧬','🔬','🔭','📡','💉','🩸','💊','🩹','🩻','🩺','🚪','🪞','🪟','🛏️','🛋️','🪑','🚽','🪠','🚿','🛁','🪤','🪒','🧴','🧷','🧹','🧺','🧻','🪣','🧼','🫧','🧽','🧯','🛒','🚬','⚰️','🪦','⚱️','🗿','🪧','🪪','🚰','🛟','🧿','🪬','🗾','🏁','🚩','🎌','🏴','🏳️','🏳️‍🌈','🏳️‍⚧️','🏴‍☠️','🇺🇳','🇦🇫','🇦🇱','🇩🇿','🇦🇸','🇦🇩','🇦🇴','🇦🇮','🇦🇶','🇦🇬','🇦🇷','🇦🇲','🇦🇼','🇦🇺','🇦🇹','🇦🇿','🇧🇸','🇧🇭','🇧🇩','🇧🇧','🇧🇾','🇧🇪','🇧🇿','🇧🇯','🇧🇲','🇧🇹','🇧🇴','🇧🇦','🇧🇼','🇧🇷','🇧🇳','🇧🇬','🇧🇫','🇧🇮','🇰🇭','🇨🇲','🇨🇦','🇨🇻','🇰🇾','🇨🇫','🇹🇩','🇨🇱','🇨🇳','🇨🇽','🇨🇨','🇨🇴','🇰🇲','🇨🇬','🇨🇩','🇨🇰','🇨🇷','🇨🇮','🇭🇷','🇨🇺','🇨🇼','🇨🇾','🇨🇿','🇩🇰','🇩🇯','🇩🇲','🇩🇴','🇪🇨','🇪🇬','🇸🇻','🇬🇶','🇪🇷','🇪🇪','🇸🇿','🇪🇹','🇫🇰','🇫🇴','🇫🇯','🇫🇮','🇫🇷','🇬🇫','🇵🇫','🇹🇫','🇬🇦','🇬🇲','🇬🇪','🇩🇪','🇬🇭','🇬🇮','🇬🇷','🇬🇱','🇬🇩','🇬🇵','🇬🇺','🇬🇹','🇬🇬','🇬🇳','🇬🇼','🇬🇾','🇭🇹','🇭🇳','🇭🇰','🇭🇺','🇮🇸','🇮🇳','🇮🇩','🇮🇷','🇮🇶','🇮🇪','🇮🇲','🇮🇱','🇮🇹','🇯🇲','🇯🇵','🇯🇪','🇯🇴','🇰🇿','🇰🇪','🇰🇮','🇰🇵','🇰🇷','🇽🇰','🇰🇼','🇰🇬','🇱🇦','🇱🇻','🇱🇧','🇱🇸','🇱🇷','🇱🇾','🇱🇮','🇱🇹','🇱🇺','🇲🇴','🇲🇬','🇲🇼','🇲🇾','🇲🇻','🇲🇱','🇲🇹','🇲🇭','🇲🇶','🇲🇷','🇲🇺','🇾🇹','🇲🇽','🇫🇲','🇲🇩','🇲🇨','🇲🇳','🇲🇪','🇲🇸','🇲🇦','🇲🇿','🇲🇲','🇳🇦','🇳🇷','🇳🇵','🇳🇱','🇳🇨','🇳🇿','🇳🇮','🇳🇪','🇳🇬','🇳🇺','🇳🇫','🇲🇰','🇲🇵','🇳🇴','🇴🇲','🇵🇰','🇵🇼','🇵🇸','🇵🇦','🇵🇬','🇵🇾','🇵🇪','🇵🇭','🇵🇳','🇵🇱','🇵🇹','🇵🇷','🇶🇦','🇷🇪','🇷🇴','🇷🇺','🇷🇼','🇼🇸','🇸🇲','🇸🇹','🇸🇦','🇸🇳','🇷🇸','🇸🇨','🇸🇱','🇸🇬','🇸🇽','🇸🇰','🇸🇮','🇸🇧','🇸🇴','🇿🇦','🇬🇸','🇸🇸','🇪🇸','🇱🇰','🇧🇱','🇸🇭','🇰🇳','🇱🇨','🇲🇫','🇵🇲','🇻🇨','🇸🇩','🇸🇷','🇸🇪','🇨🇭','🇸🇾','🇹🇼','🇹🇯','🇹🇿','🇹🇭','🇹🇱','🇹🇬','🇹🇰','🇹🇴','🇹🇹','🇹🇳','🇹🇷','🇹🇲','🇹🇨','🇹🇻','🇺🇬','🇺🇦','🇦🇪','🇬🇧','🇺🇸','🇺🇾','🇺🇿','🇻🇺','🇻🇦','🇻🇪','🇻🇳','🇻🇬','🇻🇮','🇼🇫','🇪🇭','🇾🇪','🇿🇲','🇿🇼']
const aiInput = ref('')
const aiMessages = ref([])
const aiLoading = ref(false)
const showDndSettings = ref(false)
const dndEnabled = ref(false)
const dndStart = ref('')
const dndEnd = ref('')
const showBlockedList = ref(false)
const blockedUsers = ref([])
const showDashboard = ref(false)
const loadingDashboard = ref(false)
const dashboardData = ref({})
const showGroupManage = ref(false)
const groupMembers = ref([])
const loadingGroupMembers = ref(false)
const userRoleInGroup = ref('member')
const showTransferOwner = ref(false)
const newOwnerId = ref(null)
const transferring = ref(false)
const showSensitiveWords = ref(false)
const sensitiveWords = ref([])
const newSensitiveWord = ref({ word:'', replacement:'***', category:'general', severity:'medium' })
const addingSensitiveWord = ref(false)
const sensitiveTestText = ref('')
const sensitiveTestResult = ref(null)
const testingSensitive = ref(false)
// 入群审批
const groupJoinApproval = ref(false)
const pendingJoinReqs = ref([])
// 群权限
const groupPermissions = ref({})
const groupPermissionDefs = [
    { key: 'invite', label: '允许成员邀请新成员' },
    { key: 'mention_all', label: '允许成员 @所有人' },
    { key: 'send_file', label: '允许成员发送文件' },
    { key: 'send_card', label: '允许成员发送卡片' },
    { key: 'edit_group', label: '允许成员编辑群信息' },
    { key: 'pin_message', label: '允许成员置顶消息' },
]
// PRES-003: 在线状态
const myStatus = ref('online')
const showStatusMenu = ref(false)
// SEC-006: 隐私设置
const showPrivacySettings = ref(false)
const privacySettings = ref({ friend_add_policy: 'everyone', show_online_status: true, show_read_receipt: true, allow_stranger_message: false })
// 私密空间
const showSetPin = ref(false)
const setPinMode = ref('set')
const pinForm = ref({ currentPin: '', newPin: '', confirmPin: '' })
const savingPin = ref(false)
const showUnlockDialog = ref(false)
const unlockPin = ref('')
const unlocking = ref(false)
const unlockError = ref('')
const privacyPinStatus = ref({ has_pin: false, verified: false })
const showHiddenConvs = ref(false)
const hiddenConvs = ref([])
const savingPrivacy = ref(false)
// MSG-005: 消息状态
function messageStatusIcon(msg) { if (msg.sender_id !== myId.value) return ''; const map = { sent: '✓', delivered: '✓✓', read: '✓✓' }; return map[msg.deliver_status] || '✓' }
function messageStatusColor(msg) { if (msg.sender_id !== myId.value) return ''; return msg.deliver_status === 'read' ? '#409eff' : msg.deliver_status === 'delivered' ? '#67c23a' : '#999' }
// 举报
const showReportDialog = ref(false)
const reportTarget = ref(null)
const reportReason = ref('spam')
const reportDescription = ref('')
const submittingReport = ref(false)
const showTagPanel = ref(false)
const allTags = ref([])
const selectedTagIds = ref([])
const savingTags = ref(false)
const convTags = ref([])
// 无障碍
const showA11yPanel = ref(false)
const a11yFontSize = ref('normal')
const a11yReducedMotion = ref(false)
const a11yHighContrast = ref(false)
const a11yAutoAlt = ref(true)
const a11yMsgAnnounce = ref(false)
const a11yGeneratingAlt = ref(new Set())
const msgAreaRef = ref(null)
const friendSearch = ref('')
const loadingFriends = ref(false)
const friendsList = ref([])
const friendGroups = ref([])
const friendGroupFilter = ref(null)
const showAddFriend = ref(false)
const addFriendUserId = ref(null)
const addFriendLoading = ref(false)
const showPendingRequests = ref(false)
const pendingRequests = ref([])
const showFriendGroups = ref(false)
const newFriendGroupName = ref('')
const showRemarkDialog = ref(false)
const remarkText = ref('')
const remarkTarget = ref(null)
const showMoveGroupDialog = ref(false)
const moveGroupId = ref(null)
// OPR-011: 投票
const showPollDialog = ref(false)
const showPollResult = ref(false)
const pollQuestion = ref('')
const pollOptions = ref(['', ''])
const pollType = ref('single')
const pollIsAnonymous = ref(false)
const pollHideResults = ref(false)
const pollExpireHours = ref(null)
const creatingPoll = ref(false)
const activePoll = ref(null)
const pollResults = ref([])
const pollTotalVotes = ref(0)
// AI-001: 智能回复
const smartReplies = ref([])
// AI-005: 语义搜索
const searchMode = ref('fulltext')
const semanticResults = ref([])
const searchKeywords = ref('')
const searchingSemantic = ref(false)
// AI-012: AI 助手
const aiConvId = ref(null)
const aiSending = ref(false)
// AI-014: Copilot 侧边栏
const showCopilot = ref(false)
const copilotResults = ref([])
const copilotLoading = ref(false)
const copilotRef = ref(null)
// AI-015: 提取待办
const showTaskDialog = ref(false)
const extractedTasks = ref([])
const taskLoading = ref(false)
// AI-017: 自动标签
const aiConvTags = ref({})
// AI-018: 智能分类
const convCategory = ref('')
const batchMode = ref(false)
const selectedConvIds = ref([])
const convClassifications = ref({})
const moveGroupTarget = ref(null)
const showForward = ref(false)
const forwardConvId = ref(null)
const forwardMsgs = ref([])
const forwardableConvs = ref([])
const forwardSearching = ref(false)
const forwarding = ref(false)
const selectingForward = ref(false)
const typingUsers = ref([])
const typingTimer = ref(null)
const showCreateTicket = ref(false)
// AI 好友
const showCreateAiFriend = ref(false)
const creatingAiFriend = ref(false)
const aiFriends = ref([])
const newAiFriend = ref({ name:'', category:'assistant', provider:'deepseek', model_name:'deepseek-chat', api_key:'', api_base_url:'', system_prompt:'', avatar_url:'' })
const openAvatarInput = ref(false)
const presetAvatars = [
  { url:'https://api.dicebear.com/7.x/bottts/svg?seed=assistant&backgroundColor=409eff', label:'机器人' },
  { url:'https://api.dicebear.com/7.x/bottts/svg?seed=translator&backgroundColor=67c23a', label:'翻译' },
  { url:'https://api.dicebear.com/7.x/bottts/svg?seed=writer&backgroundColor=e6a23c', label:'写作' },
  { url:'https://api.dicebear.com/7.x/bottts/svg?seed=custom&backgroundColor=909399', label:'自定义' },
  { url:'https://api.dicebear.com/7.x/thumbs/svg?seed=AI&backgroundColor=409eff', label:'默认' },
]
// AI 好友管理（管理员）
const showAiFriendAdmin = ref(false)
const adminAiFriends = ref([])
const loadingAdminAi = ref(false)
const testingId = ref(null)
const showCreatePlatformAi = ref(false)
const creatingPlatformAi = ref(false)
const platformAiShowUrl = ref(false)
const platformAiForm = ref({ name:'', category:'assistant', provider:'deepseek', model_name:'deepseek-chat', api_key:'', avatar_url:'', welcome_message:'', system_prompt:'' })
const showAiFriendConvs = ref(false)
const aiFriendConvs = ref([])
const aiFriendConvsTarget = ref(null)
const aiFriendConvsName = ref('')
const loadingAiFriendConvs = ref(false)
const ticketSubject = ref('')
const ticketDescription = ref('')
const ticketPriority = ref('medium')
const creatingTicket = ref(false)
const aiMsgRef = ref(null)
const cannedCategory = ref('')
const cannedReplies = ref([])

const cannedCategories = computed(() => {
    const cats = new Set()
    for (const r of cannedReplies.value) {
        if (r.category) cats.add(r.category)
    }
    return Array.from(cats).sort()
})

// ── 计算属性 ──
const filteredConversations = computed(() => {
    let list = conversations.value
    // 排除文件传输助手和 AI 助手
    list = list.filter(c => c.type !== 'self' && c.type !== 'ai')
    // 排除归档会话（除非显示归档标签）
    if (convCategory.value !== 'archived') {
        list = list.filter(c => !c.is_archived)
    } else {
        list = list.filter(c => c.is_archived)
    }
    // 排除隐藏会话
    list = list.filter(c => !c.is_hidden)
    if (activeFolder.value) {
        const convIds = JSON.parse(localStorage.getItem('chat-folder-convs-' + activeFolder.value) || '[]')
        list = list.filter(c => convIds.includes(c.id))
    }
    if (searchKeyword.value) {
        const kw = searchKeyword.value.toLowerCase()
        list = list.filter(c => (c.name || '').toLowerCase().includes(kw) || c.last_message?.content?.toLowerCase().includes(kw))
    }
    return list
})
const filteredFriends = computed(() => {
    let list = friendsList.value
    if (friendGroupFilter.value) list = list.filter(f => f.friend_group_id == friendGroupFilter.value)
    if (friendSearch.value) {
        const kw = friendSearch.value.toLowerCase()
        list = list.filter(f => (f.name || '').toLowerCase().includes(kw) || (f.remark || '').toLowerCase().includes(kw))
    }
    return list
})
const filteredCanned = computed(() => {
    if (!cannedCategory.value) return cannedReplies.value
    return cannedReplies.value.filter(r => r.category === cannedCategory.value)
})

// ── 消息操作 ──
function formatTime(t) { if (!t) return ''; const d = new Date(t); const now = new Date(); const pad = n => String(n).padStart(2,'0'); if (d.toDateString() === now.toDateString()) return pad(d.getHours())+':'+pad(d.getMinutes()); const y = d.getFullYear(); return y===now.getFullYear()?pad(d.getMonth()+1)+'/'+pad(d.getDate()):y+'/'+pad(d.getMonth()+1)+'/'+pad(d.getDate()) }
async function selectConversation(conv) { activeConv.value = conv; messages.value = []; hasMore.value = false; showTagPanel.value = false; if (conv.type === 'group') userRoleInGroup.value = conv.my_role || 'member'; else userRoleInGroup.value = 'member'; await loadMessages(); try { await apiClient.post('/user-chat/conversations/'+conv.id+'/read') } catch { /* ignore */ }; loadConvTags(conv.id); loadPinnedMessages() }
async function loadConversations() { try { const res = await apiClient.get('/user-chat/conversations'); conversations.value = res.data?.data || [] } catch { /* ignore */ } }
async function loadMessages() { try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/messages'); const data = res.data?.data || {}; const items = data.items || data.data || data || []; for (const msg of items) { msg._expanded = false; if (msg.id) { try { const rr = await apiClient.get('/user-chat/messages/'+msg.id+'/reactions'); msg.reactions = rr.data?.data || [] } catch { msg.reactions = [] } } }; messages.value = items.reverse(); hasMore.value = data.total > messages.value.length; await nextTick(); scrollToBottom(); messages.value.forEach(msg => fetchLinkPreview(msg)) } catch { /* ignore */ } }
async function loadMore() { if (!messages.value.length) return; const firstId = messages.value[0].id; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/messages', { params: { before_id: firstId } }); const data = res.data?.data || {}; const items = data.items || data.data || data || []; items.forEach(msg => msg._expanded = false); messages.value = [...items.reverse(), ...messages.value]; hasMore.value = data.total > messages.value.length; items.forEach(msg => fetchLinkPreview(msg)) } catch { /* ignore */ } }

// ── 斜杠命令 ──
async function loadSlashCommands() {
    if (slashLoaded.value) return
    try {
        const res = await apiClient.get('/user-chat/slash-commands')
        slashCommands.value = res.data?.data?.commands || []
        slashLoaded.value = true
    } catch { slashCommands.value = [] }
}
function onSlashInput() {
    const text = inputMessage.value
    if (text.startsWith('/') && slashCommands.value.length) {
        const query = text.slice(1).toLowerCase()
        slashSuggestions.value = slashCommands.value.filter(c =>
            c.command.slice(1).toLowerCase().includes(query)
        )
        slashSelectedIndex.value = 0
    } else {
        slashSuggestions.value = []
    }
}
function slashSelectUp() { if (slashSuggestions.value.length) slashSelectedIndex.value = Math.max(0, slashSelectedIndex.value - 1) }
function slashSelectDown() { if (slashSuggestions.value.length) slashSelectedIndex.value = Math.min(slashSuggestions.value.length - 1, slashSelectedIndex.value + 1) }
function selectSlashCommand(cmd) {
    inputMessage.value = cmd.command + ' '
    slashSuggestions.value = []
    slashSelectedIndex.value = 0
    // 聚焦到输入框
    nextTick(() => { document.querySelector('.chat-input-area textarea')?.focus() })
}
async function handleInputEnter() {
    const text = inputMessage.value.trim()
    // 如果有斜杠建议且选中了某个命令，插入命令
    if (slashSuggestions.value.length > 0 && slashSuggestions.value[slashSelectedIndex.value]) {
        selectSlashCommand(slashSuggestions.value[slashSelectedIndex.value])
        return
    }
    // 如果是斜杠命令，执行它而不是发送普通消息
    if (text.startsWith('/')) {
        await executeSlashCommand(text)
        return
    }
    await sendMessage()
}
async function executeSlashCommand(text) {
    if (!activeConv.value) return
    try {
        const res = await apiClient.post('/user-chat/slash-command', {
            text: text,
            conversation_id: activeConv.value.id,
        })
        const result = res.data?.data
        if (result?.handled) {
            inputMessage.value = ''
            slashSuggestions.value = []
            if (result.response) {
                // 显示命令结果
                ElMessageBox.alert(result.response, '💻 ' + text.split(' ')[0], {
                    dangerouslyUseHTMLString: true,
                    customClass: 'slash-result-dialog',
                    confirmButtonText: '好的',
                })
            }
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '命令执行失败')
    }
}
async function sendMessage() {
    if (!activeConv.value) return
    const content = inputMessage.value.trim()
    if (!content && !pendingAttachments.value.length) return

    // PRAC-006: 发送前二次确认
    const confirmMsg = getSendConfirmationMsg(content, activeConv.value)
    if (confirmMsg) {
        try {
            await ElMessageBox.confirm(confirmMsg, '发送确认', {
                confirmButtonText: '确认发送',
                cancelButtonText: '取消',
                type: 'warning',
                dangerouslyUseHTMLString: true,
            })
        } catch { return } // 用户取消
    }

    // AI-044: 发送前 AI 预审（仅文本消息）
    let reviewOverride = false
    if (content && !pendingAttachments.value.length) {
        try {
            const reviewRes = await apiClient.post('/user-chat/pre-review', { content })
            const review = reviewRes.data?.data
            if (review?.has_issue || review?.warnings?.length) {
                const warningHtml = (review.warnings || []).map(w => `⚠️ ${w}`).join('<br>')
                try {
                    await ElMessageBox.confirm(
                        `🔍 <strong>AI 预审提示</strong><br><br>${warningHtml}<br><br>您可以选择修改内容后重试，或忽略提示继续发送。`,
                        '消息预审',
                        {
                            confirmButtonText: '忽略提示，继续发送',
                            cancelButtonText: '修改内容',
                            type: 'warning',
                            dangerouslyUseHTMLString: true,
                            distinguishCancelAndClose: true,
                        }
                    )
                    reviewOverride = true
                } catch { return } // 用户选择修改内容→取消发送
            }
        } catch { /* 预审接口失败静默降级，不影响发送 */ }
    }

    sending.value = true
    try {
        const payload = { content: content || '(文件)', message_type: 'text', reply_to_id: replyToMsg.value?.id || undefined, client_msg_id: crypto.randomUUID?.() || Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,10), attachments: pendingAttachments.value.length ? pendingAttachments.value : undefined }
        // PRAC-006: 如果经过了二次确认，标记已确认
        if (confirmMsg) payload.confirmed = true
        // AI-044: 如果用户强制发送，标记覆盖
        if (reviewOverride) payload.review_override = true
        await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', payload)
        inputMessage.value = ''
        pendingAttachments.value = []
        replyToMsg.value = null
        await loadMessages()
        await loadConversations()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败')
    } finally {
        sending.value = false
    }
}
function getSendConfirmationMsg(content, conv) {
    const warnings = []
    // 检测 @所有人
    if (/@(all|everyone|所有人)/i.test(content)) {
        if (conv.type === 'group') {
            warnings.push('⚠️ 消息中包含 <strong>@所有人</strong>，将通知全部群成员')
        }
    }
    // 检测外部链接
    const urlRegex = /https?:\/\/([^\s\/]+)/gi
    let match
    while ((match = urlRegex.exec(content)) !== null) {
        const domain = match[1].toLowerCase()
        // 排除自身域名
        if (!domain.includes(window.location.hostname) && !domain.includes('huwutong.com')) {
            warnings.push(`🔗 消息包含外部链接 <strong>${match[0].substring(0, 50)}</strong>，请确认链接安全`)
            break // 只提示一次
        }
    }
    // 检测批量文件发送
    if (pendingAttachments.value.length >= 5) {
        warnings.push(`📎 即将发送 <strong>${pendingAttachments.value.length}</strong> 个文件，请确认`)
    }
    return warnings.length ? warnings.join('<br>') : null
}
function onScrollTop(e) { if (e.target.scrollTop === 0 && hasMore.value) loadMore() }
function scrollToBottom() { nextTick(() => { const el = msgAreaRef.value; if (el) el.scrollTop = el.scrollHeight }) }

// ── 会话操作 ──
async function togglePin() { if (!activeConv.value) return; try { await apiClient.put('/user-chat/conversations/'+activeConv.value.id+'/pin'); activeConv.value.is_pinned = !activeConv.value.is_pinned; await loadConversations() } catch { /* ignore */ } }
async function toggleMute() { if (!activeConv.value) return; try { await apiClient.put('/user-chat/conversations/'+activeConv.value.id+'/mute'); activeConv.value.is_muted = !activeConv.value.is_muted } catch { /* ignore */ } }
async function handleDeleteConv() { if (!activeConv.value) return; if (activeConv.value.is_channel || activeConv.value.is_oa) { activeConv.value = null; return } try { await ElMessageBox.confirm('确定删除此会话？'); await apiClient.delete('/user-chat/conversations/'+activeConv.value.id); activeConv.value = null; await loadConversations() } catch { /* ignore */ } }
function onSearch() { /* filteredConversations handles it */ }
async function handleExportConv() { if (!activeConv.value) return; try { const res = await apiClient.get('/im/conversations/'+activeConv.value.id+'/export', { responseType: 'blob' }); const url = URL.createObjectURL(new Blob([res.data])); const a = document.createElement('a'); a.href = url; a.download = 'chat-export.html'; a.click(); URL.revokeObjectURL(url) } catch { ElMessage.error('导出失败') } }
async function handleHandoff() { if (!activeConv.value) return; try { await apiClient.post('/api/handoff', { conversation_id: activeConv.value.id }); ElMessage.success('已提交转接请求，客服将尽快接入') } catch (e) { ElMessage.error(e.response?.data?.message || '转接失败') } }

// ── 无障碍 AI ──
const a11yAnnounceMsg = ref(false)
function setA11yFontSize(v) {
    document.documentElement.style.fontSize = { small:'13px', normal:'14px', large:'16px', extra_large:'18px' }[v] || '14px'
}
function setA11yReducedMotion(v) { document.documentElement.classList.toggle('a11y-reduced-motion', v) }
function setA11yHighContrast(v) { document.documentElement.classList.toggle('a11y-high-contrast', v) }
function setA11yMsgAnnounce(v) { if (v && 'speechSynthesis' in window) a11yAnnounceMsg.value = true; else a11yAnnounceMsg.value = false }
async function onImageLoaded(msg) {
    if (!a11yAutoAlt.value || msg.metadata?.alt_text || a11yGeneratingAlt.value.has(msg.id)) return
    a11yGeneratingAlt.value.add(msg.id)
    try {
        const res = await apiClient.post('/a11y/image-alt', { image_url: msg.content })
        const alt = res.data?.data?.alt_text
        if (alt && alt !== '图片') { msg.metadata = msg.metadata || {}; msg.metadata.alt_text = alt }
    } catch {} finally { a11yGeneratingAlt.value.delete(msg.id) }
}
async function readAltText(msg) {
    if (!msg.metadata?.alt_text || !('speechSynthesis' in window)) return
    window.speechSynthesis.cancel()
    const u = new SpeechSynthesisUtterance(msg.metadata.alt_text)
    u.lang = 'zh-CN'; u.rate = 0.9
    window.speechSynthesis.speak(u)
}
async function readConversationSummary() {
    if (!activeConv.value || !('speechSynthesis' in window)) return
    try {
        const res = await apiClient.get('/a11y/conversation-summary/'+activeConv.value.id, { params: { limit: 10 } })
        const text = res.data?.data?.full_text
        if (text) { window.speechSynthesis.cancel(); const u = new SpeechSynthesisUtterance(text); u.lang='zh-CN'; u.rate=0.85; window.speechSynthesis.speak(u) }
    } catch {}
}

// ── 全局搜索（支持语义搜索 AI-005）──
async function onGlobalSearch() { if (!globalSearchKeyword.value.trim()) return; searchingGlobal.value = true; try { if (searchMode.value === 'semantic') { const res = await apiClient.get('/user-chat/messages/semantic-search', { params: { q: globalSearchKeyword.value } }); const data = res.data?.data || {}; globalSearchResults.value = data.items || []; searchKeywords.value = data.keywords || '' } else { const res = await apiClient.get('/user-chat/messages/search-fulltext', { params: { q: globalSearchKeyword.value } }); const data = res.data?.data || {}; globalSearchResults.value = data.data || data || [] } } catch { try { const res = await apiClient.get('/im/messages/search', { params: { q: globalSearchKeyword.value } }); globalSearchResults.value = res.data?.data || [] } catch { globalSearchResults.value = [] } } finally { searchingGlobal.value = false } }
function jumpToMessage(r) { const convId = r.conversation_id || r.conversation?.id; if (!convId) return; const conv = conversations.value.find(c => c.id === convId); if (conv) { sidebarTab.value = 'conversations'; selectConversation(conv) } }
function highlightKeyword(text) { if (!text || !globalSearchKeyword.value) return text; const kw = globalSearchKeyword.value; const re = new RegExp('('+kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')', 'gi'); return text.replace(re, '<span style="background:#fff3cd">$1</span>') }

// ── 新建会话 ──
async function searchUsers(q) { if (!q) { searchedUsers.value = []; return }; searching.value = true; try { const res = await apiClient.get('/user-chat/users/search', { params: { q } }); searchedUsers.value = res.data?.data || [] } catch { searchedUsers.value = [] } finally { searching.value = false } }
async function createNewChat() { if (!newChatUserIds.value.length) return; try { const res = await apiClient.post('/user-chat/conversations', { participant_ids: newChatUserIds.value }); const conv = res.data?.data; if (conv) { selectConversation(conv); await loadConversations(); showNewChat.value = false; newChatUserIds.value = [] } } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败') } }

// ── 文件上传 ──
function onFileSelect(e) { const file = e.raw; if (!file) return; const reader = new FileReader(); reader.onload = async (ev) => { const base64 = ev.target.result; let msgType = 'file'; if (file.type.startsWith('image/')) { msgType = 'image'; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { content: base64, message_type: msgType }); await loadMessages() } catch { pendingAttachments.value.push({ name: file.name, size: file.size, data: base64 }) } } else { pendingAttachments.value.push({ name: file.name, size: file.size }) } }; if (file.type.startsWith('image/')) { reader.readAsDataURL(file) } else { pendingAttachments.value.push({ name: file.name, size: file.size }) } }
function removePendingAttachment(i) { pendingAttachments.value.splice(i, 1) }

// ── 收藏 ──
const favorites = ref([])
const loadingFavorites = ref(false)
const favTab = ref('messages')
const oaFavorites = ref([])
const loadingOaFavorites = ref(false)
async function toggleFavorite(msg) { try { const res = await apiClient.post('/user-chat/messages/'+msg.id+'/favorite'); msg.is_favorited = res.data?.data?.favorited } catch { /* ignore */ } }
async function loadFavorites() { loadingFavorites.value = true; try { const res = await apiClient.get('/user-chat/favorites'); favorites.value = (res.data?.data || []).map(f => ({...f, content: f.message?.content || f.content || '(消息已删除)', sender_name: f.message?.sender?.name || f.sender_name || '用户', created_at: f.created_at || f.message?.created_at})) } catch { favorites.value = [] } finally { loadingFavorites.value = false } }
async function loadOaFavorites() { loadingOaFavorites.value = true; try { const res = await apiClient.get('/official-accounts/my-favorite-articles'); oaFavorites.value = res.data?.data?.data || res.data?.data || [] } catch { oaFavorites.value = [] } finally { loadingOaFavorites.value = false } }
async function toggleOaFavorite(article) { try { const res = await apiClient.post('/official-accounts/articles/' + article.id + '/favorite'); article.is_favorited = res.data?.data?.favorited; if (!article.is_favorited && sidebarTab.value === 'favorites' && favTab.value === 'articles') { oaFavorites.value = oaFavorites.value.filter(f => f.article_id !== article.id) } } catch (e) { ElMessage.error('操作失败') } }
function openOaArticleDetailFromFav(fav) { showOaArticleDetail.value = true; oaArticleDetail.value = null; const detail = { id: fav.article_id, title: fav.article?.title, content: fav.article?.content, summary: fav.article?.summary, cover_image: fav.article?.cover_image, author: fav.article?.author, account: fav.article?.account, tags: fav.article?.tags, published_at: fav.article?.published_at, likes_count: fav.article?.likes_count || 0, reads_count: fav.article?.reads_count || 0, shares_count: fav.article?.shares_count || 0, is_favorited: true, }; loadOaArticleDetail(fav.article_id).then(d => { if (d) oaArticleDetail.value = { ...detail, ...d } }).catch(() => oaArticleDetail.value = detail) }
function jumpToFavorite(fav) { const convId = fav.message?.conversation_id || fav.conversation_id; if (convId) { const conv = conversations.value.find(c => c.id === convId); if (conv) { sidebarTab.value = 'conversations'; selectConversation(conv) } else { ElMessage.info('请先打开相关会话查看') } } }

// ── OPR-008: 消息稍后处理/标记 ──
const pendingMessages = ref([])
const loadingPending = ref(false)
async function togglePendingMsg(msg) {
    try {
        const res = await apiClient.post('/user-chat/messages/'+msg.id+'/pending')
        msg.is_pending = res.data?.data?.pending
    } catch { /* ignore */ }
}
async function loadPending() {
    loadingPending.value = true
    try {
        const res = await apiClient.get('/user-chat/messages/pending')
        pendingMessages.value = res.data?.data || []
    } catch { pendingMessages.value = [] }
    finally { loadingPending.value = false }
}
async function removePending(p) {
    try {
        await apiClient.post('/user-chat/messages/'+p.message_id+'/pending')
        pendingMessages.value = pendingMessages.value.filter(x => x.id !== p.id)
        ElMessage.success('已取消待处理')
    } catch { /* ignore */ }
}
function jumpToPending(p) {
    if (p.conversation_id) {
        const conv = conversations.value.find(c => c.id === p.conversation_id)
        if (conv) { sidebarTab.value = 'conversations'; selectConversation(conv) }
        else { ElMessage.info('请先打开相关会话查看') }
    }
}

// ── 消息置顶 ──
const pinnedMessages = ref([])
async function loadPinnedMessages() {
    if (!activeConv.value) return
    try {
        const res = await apiClient.get('/user-chat/conversations/' + activeConv.value.id + '/pinned-messages')
        pinnedMessages.value = res.data?.data || []
    } catch { pinnedMessages.value = [] }
}
async function pinMsg(msg) {
    try {
        await apiClient.post('/user-chat/messages/' + msg.id + '/pin')
        ElMessage.success('已置顶')
        msg.is_pinned = true
        loadPinnedMessages()
        loadMessages()
    } catch (e) { ElMessage.error(e.response?.data?.message || '置顶失败') }
}
async function unpinMsg(msg) {
    try {
        await apiClient.post('/user-chat/messages/' + msg.id + '/unpin')
        ElMessage.success('已取消置顶')
        msg.is_pinned = false
        loadPinnedMessages()
    } catch (e) { ElMessage.error(e.response?.data?.message || '取消置顶失败') }
}

// ── SRCH-004: 会话内搜索 ──
const showConvSearch = ref(false)
const convSearchKeyword = ref('')
const convSearchType = ref('')
const convSearchDateRange = ref(null)
const convSearchResults = ref([])
const convSearchSearched = ref(false)
async function doConvSearch() {
    if (!convSearchKeyword.value.trim() || !activeConv.value) return
    convSearchSearched.value = true
    try {
        const params = { q: convSearchKeyword.value, conversation_id: activeConv.value.id }
        if (convSearchType.value) params.message_type = convSearchType.value
        if (convSearchDateRange.value && convSearchDateRange.value[0]) params.date_from = convSearchDateRange.value[0]
        if (convSearchDateRange.value && convSearchDateRange.value[1]) params.date_to = convSearchDateRange.value[1]
        const res = await apiClient.get('/user-chat/messages/search-fulltext', { params })
        convSearchResults.value = res.data?.data || []
    } catch { convSearchResults.value = [] }
}
function clearConvSearch() {
    convSearchKeyword.value = ''
    convSearchType.value = ''
    convSearchDateRange.value = null
    convSearchResults.value = []
    convSearchSearched.value = false
}
function convHighlightKeyword(text, keyword) {
    if (!text || !keyword) return text || ''
    const re = new RegExp('(' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi')
    return text.replace(re, '<mark style="background:#e6a23c;color:#fff;padding:0 2px;border-radius:2px">$1</mark>')
}

// ── THREAD-001~004: Thread 话题 ──
const activeThreadId = ref(null)
const threadParentMsg = ref(null)
const threadReplies = ref([])
const threadInput = ref('')
const loadingThread = ref(false)
const sendingThread = ref(false)
const threadReplyRef = ref(null)

async function openThread(msg) {
    activeThreadId.value = msg.thread_parent_id || msg.id
    threadParentMsg.value = msg
    loadingThread.value = true
    try {
        const res = await apiClient.get('/threads/messages/' + (msg.thread_parent_id || msg.id) + '/replies')
        threadReplies.value = res.data?.data?.data || res.data?.data || []
    } catch { threadReplies.value = [] }
    finally { loadingThread.value = false }
    // 滚动到底部
    await nextTick()
    if (threadReplyRef.value) threadReplyRef.value.scrollTop = threadReplyRef.value.scrollHeight
}
function closeThread() {
    activeThreadId.value = null
    threadParentMsg.value = null
    threadReplies.value = []
    threadInput.value = ''
}
async function sendThreadReply() {
    if (!threadInput.value.trim() || !activeThreadId.value) return
    sendingThread.value = true
    try {
        const res = await apiClient.post('/threads/messages/' + activeThreadId.value + '/reply', {
            content: threadInput.value.trim(),
        })
        const reply = res.data?.data
        if (reply) {
            threadReplies.value.push(reply)
            threadInput.value = ''
            // 更新原消息的回复计数
            if (threadParentMsg.value) {
                threadParentMsg.value.thread_reply_count = (threadParentMsg.value.thread_reply_count || 0) + 1
            }
            await nextTick()
            if (threadReplyRef.value) threadReplyRef.value.scrollTop = threadReplyRef.value.scrollHeight
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '回复失败')
    } finally { sendingThread.value = false }
}

// ── 卡片动作回调 ──
async function handleCardAction(msg, action) {
    if (!action || !msg.id) return

    // open_url: 直接跳转
    if (action.action === 'open_url' && action.url) {
        window.open(action.url, '_blank')
        return
    }

    // callback: 调用后端 API
    if (action.action === 'callback' && action.callback_id) {
        try {
            const trace_id = msg.trace_id || msg.metadata?.trace_id || crypto.randomUUID?.() || Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10)
            const res = await apiClient.post('/user-chat/card-callback', {
                message_id: msg.id,
                callback_id: action.callback_id,
                payload: action.payload || {},
                trace_id,
            })
            const data = res.data?.data || {}
            if (data.message) {
                ElMessage.success(data.message)
            }
            // 如果状态变更，更新消息元数据标记为已处理
            if (data.status) {
                msg.metadata._processed = true
                msg.metadata._status = data.status
            }
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '操作失败')
        }
        return
    }

    // 兼容旧格式：直接跳转 action URL
    if (action.action && action.action.startsWith('http')) {
        window.open(action.action, '_blank')
    }
}

// ── 表情回复 ──
const emojiList = ['👍','❤️','😂','😮','😢','😡','🎉','🔥','💯','❓']
async function toggleReaction(msg, emoji) { if (!msg.id) return; try { await apiClient.post('/user-chat/messages/'+msg.id+'/reactions', { reaction: emoji }); const rr = await apiClient.get('/user-chat/messages/'+msg.id+'/reactions'); msg.reactions = rr.data?.data || [] } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') } }

// ── RTC-001~004: 音视频通话 ──
const callState = ref('idle')
const callType = ref('audio')
const callPartner = ref(null)
const callPanelRef = ref(null)

function startCall(type) {
    if (!activeConv.value) { ElMessage.warning('请先选择一个会话'); return }
    // 查找通话对象（私聊的对方）
    const others = activeConv.value.participants?.filter(p => p.id !== myId.value) || []
    if (!others.length && activeConv.value.type === 'private') {
        // 自聊天不允许通话
        ElMessage.info('文件传输助手不支持通话')
        return
    }
    const target = others[0] || { id: 0, name: '群组通话' }
    callType.value = type
    callPartner.value = target

    // 调用 CallPanel 的 startCall
    if (callPanelRef.value) {
        callPanelRef.value.startCall(target.id, type, activeConv.value.id)
    }
}

function onCallEnded() {
    callPartner.value = null
    callState.value = 'idle'
}
async function deleteMessage(msg) { try { await ElMessageBox.confirm('确定删除此消息？'); await apiClient.delete('/user-chat/messages/'+msg.id); messages.value = messages.value.filter(m => m.id !== msg.id) } catch { /* ignore */ } }
async function translateMessage(msg) {
    if (msg.translated_text) { msg.translated_text = ''; return }
    try {
        const target = 'zh-CN'
        const res = await apiClient.post('/user-chat/messages/' + msg.id + '/translate', { target })
        if (res.data?.data?.translated) {
            msg.translated_text = res.data.data.translated
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '翻译失败') }
}
async function translateConversation() {
    if (!activeConv.value?.id) return
    try {
        ElMessage.info('正在翻译全部消息...')
        const res = await apiClient.post('/user-chat/conversations/' + activeConv.value.id + '/translate-all', { target: 'zh-CN' })
        if (res.data?.data?.translated) {
            ElMessageBox.alert('<div style="white-space:pre-wrap;font-size:13px;line-height:1.6;max-height:400px;overflow-y:auto">' + res.data.data.translated + '</div>', '🌐 会话翻译（共' + res.data.data.message_count + '条消息）', { dangerouslyUseHTMLString: true, confirmButtonText: '关闭' })
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '翻译失败') }
}

// ── 黑名单 ──
async function loadBlocked() { try { const res = await apiClient.get('/user-chat/blocked'); blockedUsers.value = res.data?.data || [] } catch { blockedUsers.value = [] } }
async function unblockUser(id) { try { await apiClient.post('/user-chat/block/'+id+'/unblock'); blockedUsers.value = blockedUsers.value.filter(u => u.id !== id); ElMessage.success('已取消拉黑') } catch { /* ignore */ } }

// ── 编辑消息 ──
const editingMsg = ref(null)
const editContent = ref('')
function editMessage(msg) { editingMsg.value = msg; editContent.value = msg.content || '' }
async function submitEdit() { if (!editingMsg.value || !editContent.value.trim()) return; try { await apiClient.put('/user-chat/messages/'+editingMsg.value.id+'/edit', { content: editContent.value.trim() }); editingMsg.value.content = editContent.value.trim(); editingMsg.value.is_edited = true; editingMsg.value = null; editContent.value = ''; ElMessage.success('消息已编辑') } catch (e) { ElMessage.error(e.response?.data?.message || '编辑失败') } }
async function recallMessage(msg) {
    try {
        await apiClient.post('/user-chat/messages/'+msg.id+'/recall')
        msg.is_recalled = true
        msg.content = null
        ElMessage.success('消息已撤回')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '撤回失败')
    }
}

// ── 操作按钮 ──
function openForward(msg) {
    forwardMsgs.value = [msg]
    forwardConvId.value = null
    showForward.value = true
    loadForwardConvs()
}
function toggleSelectForward(msg) {
    const idx = forwardMsgs.value.findIndex(m => m.id === msg.id)
    if (idx >= 0) { forwardMsgs.value.splice(idx, 1) }
    else { forwardMsgs.value.push(msg) }
}
function startBatchForward() {
    forwardMsgs.value = []
    selectingForward.value = true
    ElMessage.info('请点击要转发的消息，点击"完成选择"转发')
}
function cancelBatchForward() {
    selectingForward.value = false
    forwardMsgs.value = []
}
async function loadForwardConvs() {
    try { const res = await apiClient.get('/user-chat/conversations/forwardable')
    if (res.data?.success) forwardableConvs.value = res.data.data || [] } catch {}
}
async function searchForwardConvs(query) {
    forwardSearching.value = true
    try { const res = await apiClient.get('/user-chat/conversations/forwardable', { params: { search: query } })
    if (res.data?.success) forwardableConvs.value = res.data.data || [] } catch {} finally { forwardSearching.value = false }
}
async function submitForward() {
    if (!forwardConvId.value || !forwardMsgs.value.length) return
    forwarding.value = true
    try {
        const res = await apiClient.post('/user-chat/messages/forward', {
            message_ids: forwardMsgs.value.map(m => m.id),
            target_conversation_id: forwardConvId.value,
        })
        if (res.data?.success) {
            showForward.value = false
            selectingForward.value = false
            ElMessage.success(res.data.message || '已转发')
            forwardMsgs.value = []
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '转发失败') }
    finally { forwarding.value = false }
}
async function aiOptimizeMessage(msg) { try { const res = await apiClient.post('/api/chat/send', { message: '优化以下客服回复文案，使其更专业友好：'+msg.content }); if (res.data?.reply) { inputMessage.value = res.data.reply } } catch { /* ignore */ } }

// ── 工单 ──
function openCreateTicket(msg) { ticketSubject.value = ''; ticketDescription.value = '消息内容：\n'+msg.content+'\n\n发送者：'+ (msg.sender?.name||'未知') + '\n时间：'+formatTime(msg.created_at); ticketPriority.value = 'medium'; showCreateTicket.value = true }
async function submitTicket() { if (!ticketSubject.value.trim()) return ElMessage.warning('请输入工单主题'); if (!ticketDescription.value.trim()) return ElMessage.warning('请输入工单描述'); creatingTicket.value = true; try { await apiClient.post('/api/tickets', { subject: ticketSubject.value, description: ticketDescription.value, priority: ticketPriority.value }); showCreateTicket.value = false; ElMessage.success('工单已提交') } catch (e) { ElMessage.error(e.response?.data?.message || '提交失败') } finally { creatingTicket.value = false } }

// ── AI 助手（支持 SSE 流式输出）──
const aiStreaming = ref(false)
async function sendAiMessage() {
  if (!aiInput.value.trim() || !activeConv.value) return
  aiMessages.value.push({ role: 'user', content: aiInput.value })
  const q = aiInput.value
  aiInput.value = ''
  aiLoading.value = true
  aiStreaming.value = true
  try {
    const token = localStorage.getItem('auth_token') || ''
    const res = await fetch('/api/user-chat/conversations/' + activeConv.value.id + '/chat-stream', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'Authorization': 'Bearer ' + token },
      body: JSON.stringify({ message: q, mode: 'chat' })
    })
    const reader = res.body?.getReader()
    if (!reader) throw new Error('No reader')
    const decoder = new TextDecoder()
    let fullContent = ''
    aiMessages.value.push({ role: 'assistant', content: '' })
    const msgIndex = aiMessages.value.length - 1
    while (true) {
      const { done, value } = await reader.read()
      if (done) break
      const text = decoder.decode(value, { stream: true })
      const lines = text.split('\n')
      for (const line of lines) {
        if (line.startsWith('data: ')) {
          try {
            const data = JSON.parse(line.slice(6))
            if (data.type === 'chunk') {
              fullContent += data.content
              aiMessages.value[msgIndex].content = fullContent
              nextTick(() => { if (aiMsgRef.value) aiMsgRef.value.scrollTop = aiMsgRef.value.scrollHeight })
            }
          } catch { /* ignore parse errors */ }
        }
      }
    }
  } catch {
    aiMessages.value.push({ role: 'assistant', content: '请求失败，请重试' })
  } finally {
    aiLoading.value = false
    aiStreaming.value = false
  }
}

// ── AI-002: 未读消息摘要 ──
async function checkUnreadSummary() { try { const res = await apiClient.get('/user-chat/unread-summary'); const d = res.data?.data; if (d?.has_unread && d.summary) { ElNotification({ title: '📬 未读消息摘要', message: d.summary, type: 'info', duration: 6000, onClick: () => { if (d.conversations?.[0]) { const conv = conversations.value.find(c => c.id === d.conversations[0].id); if (conv) activeConv.value = conv } } }) } } catch {} }

// ── AI-014: Copilot 侧边栏 ──
async function copilotAction(action) {
    if (!activeConv.value && action !== 'translate') return;
    copilotLoading.value = true;
    try {
        if (action === 'summarize') {
            const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/summarize');
            const d = res.data?.data;
            copilotResults.value.unshift({ title: '📝 对话总结 ('+formatTime(Date.now())+')', content: d?.summary || '总结失败' });
        } else if (action === 'extract') {
            const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/extract-tasks');
            const d = res.data?.data;
            const tasks = d?.tasks || [];
            if (tasks.length) {
                copilotResults.value.unshift({ title: '📋 提取的待办 ('+tasks.length+' 项)', content: tasks.map((t,i)=>(i+1)+'. '+t.title+(t.deadline?' ⏰'+t.deadline:'')).join('\n') });
                extractedTasks.value = tasks;
                showTaskDialog.value = true;
            } else {
                copilotResults.value.unshift({ title: '📋 提取待办', content: '未提取到待办事项' });
            }
        } else if (action === 'tag') {
            const res = await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/auto-tag');
            const d = res.data?.data;
            const tags = d?.tags || [];
            copilotResults.value.unshift({ title: '🏷️ 对话标签', content: tags.length ? '标签: '+tags.join(', ') : '未能识别标签' });
            if (tags.length) aiConvTags.value[activeConv.value.id] = tags;
        } else if (action === 'translate' && inputMessage.value.trim()) {
            const token = localStorage.getItem('auth_token') || '';
            const res = await fetch('/api/user-chat/conversations/'+activeConv.value.id+'/chat-stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', Authorization: 'Bearer '+token }, body: JSON.stringify({ message: inputMessage.value, mode: 'translate' }) });
            const reader = res.body?.getReader(); let full = ''; const decoder = new TextDecoder();
            if (reader) { while (true) { const { done, value } = await reader.read(); if (done) break; const text = decoder.decode(value, { stream:true }); const lines = text.split('\n'); for (const line of lines) { if (line.startsWith('data: ')) { try { const data = JSON.parse(line.slice(6)); if (data.type === 'chunk') full += data.content; else if (data.type === 'done') full = data.content } catch {} } } } }
            copilotResults.value.unshift({ title: '🌐 翻译结果', content: full || '翻译失败' });
        }
    } catch (e) { copilotResults.value.unshift({ title: '❌ 操作失败', content: e.response?.data?.message || e.message }) }
    finally { copilotLoading.value = false }
}

// ── AI-018: 会话分类 ──
async function loadClassifications() { try { const res = await apiClient.get('/user-chat/classify'); const d = res.data?.data; if (d?.categories) { const map = {}; d.categories.forEach(c => { c.conversations?.forEach(conv => { map[conv.id] = c.category }) }); convClassifications.value = map } } catch {} }
function onCategoryChange() { /* filter handled by computed */ }
// ── 批量归档 ──
function toggleBatchMode() {
    batchMode.value = !batchMode.value
    if (!batchMode.value) selectedConvIds.value = []
}
function cancelBatch() { batchMode.value = false; selectedConvIds.value = [] }
function onConvClick(conv) {
    if (batchMode.value) {
        const idx = selectedConvIds.value.indexOf(conv.id)
        if (idx >= 0) selectedConvIds.value.splice(idx, 1)
        else selectedConvIds.value.push(conv.id)
    } else {
        selectConversation(conv)
    }
}
function onConvContextMenu(e, conv) {
    if (batchMode.value) return
    // 显示右键菜单（归档、分组）
    const items = [
        { label: conv.is_archived ? '📂 取消归档' : '📁 归档会话', action: () => toggleArchiveConv(conv) },
        { label: '📁 移动到分组', action: () => showConvFolderMenu(e, conv) },
    ]
    ElMessageBox.close()
    // 简化为直接归档
    toggleArchiveConv(conv)
}
async function toggleArchiveConv(conv) {
    try {
        if (conv.is_archived) {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/unarchive')
            conv.is_archived = false
            ElMessage.success('已取消归档')
        } else {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/archive')
            conv.is_archived = true
            ElMessage.success('已归档')
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}
async function batchArchive() {
    if (!selectedConvIds.value.length) { ElMessage.warning('请选择会话'); return }
    try {
        const res = await apiClient.post('/user-chat/conversations/batch-archive', {
            conversation_ids: selectedConvIds.value
        })
        const count = res.data?.data?.archived_count || 0
        ElMessage.success(`已归档 ${count} 个会话`)
        selectedConvIds.value.forEach(id => {
            const conv = conversations.value.find(c => c.id === id)
            if (conv) conv.is_archived = true
        })
        selectedConvIds.value = []
        batchMode.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || '归档失败') }
}
async function batchUnarchive() {
    if (!selectedConvIds.value.length) { ElMessage.warning('请选择会话'); return }
    try {
        for (const id of selectedConvIds.value) {
            await apiClient.post('/user-chat/conversations/' + id + '/unarchive')
            const conv = conversations.value.find(c => c.id === id)
            if (conv) conv.is_archived = false
        }
        ElMessage.success(`已取消 ${selectedConvIds.value.length} 个会话的归档`)
        selectedConvIds.value = []
        batchMode.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}
async function batchArchiveInactive() {
    try {
        const res = await apiClient.post('/user-chat/conversations/batch-archive', { days: 30 })
        const count = res.data?.data?.archived_count || 0
        ElMessage.success(`已归档 ${count} 个 30 天未更新的会话`)
        loadConversations()
    } catch (e) { ElMessage.error(e.response?.data?.message || '归档失败') }
}
const classifiedConversations = computed(() => {
    if (!convCategory.value) return conversations.value;
    return conversations.value.filter(c => convClassifications.value[c.id] === convCategory.value);
})

// ── 快捷回复 ──
async function loadCannedReplies() { try { const res = await apiClient.get('/im/canned-replies'); cannedReplies.value = res.data?.data || [] } catch { cannedReplies.value = [] } }
function selectCanned(r) { inputMessage.value = r.content; showCannedPanel.value = false }

// ── 贴纸 / Emoji / GIF ──
function sendStickerDirectly(item) {
    if (!activeConv.value) return
    if (item.emoji) {
        // 直接发送 emoji 文本
        inputMessage.value += item.emoji
        sendMessage()
    } else if (item.sticker) {
        // 发送贴纸消息
        apiClient.post('/stickers/send/' + activeConv.value.id, {
            sticker_id: item.sticker.id
        }).then(() => {
            loadMessages()
            showStickerPanel.value = false
        }).catch(e => ElMessage.error(e.response?.data?.message || '发送失败'))
    } else if (item.gif) {
        // 发送 GIF 消息
        const gif = item.gif
        apiClient.post('/stickers/send/' + activeConv.value.id, {
            image_url: gif.url || gif.preview,
            emoji: ''
        }).then(() => {
            loadMessages()
            showStickerPanel.value = false
        }).catch(e => ElMessage.error(e.response?.data?.message || '发送失败'))
    }
}
async function loadStickerPacks() {
    try {
        const res = await apiClient.get('/stickers/packs')
        stickerPacks.value = res.data?.data || []
    } catch { stickerPacks.value = [] }
}
async function loadCustomEmojis() {
    try {
        const res = await apiClient.get('/emoji')
        const data = res.data?.data || {}
        customEmojis.value = data.list || []
        customEmojiMap.value = data.map || {}
    } catch { customEmojis.value = []; customEmojiMap.value = {} }
}
function insertCustomEmoji(e) {
    const text = `:${e.shortcode}:`
    inputMessage.value += text
    showStickerPanel.value = false
    // 聚焦输入框
    nextTick(() => {
        document.querySelector('.chat-input-area textarea')?.focus()
    })
}
function customEmojiCategoryLabel(cat) {
    const map = { general: '通用', funny: '搞笑', reaction: '反应', logo: '品牌', other: '其他' }
    return map[cat] || cat
}
/** 渲染自定义 emoji 短代码为 HTML 图片 */
function renderCustomEmojis(text) {
    if (!text || !customEmojiMap.value) return text
    const codes = Object.keys(customEmojiMap.value)
    if (!codes.length) return text
    const pattern = new RegExp(':(' + codes.map(c => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|') + '):', 'g')
    return text.replace(pattern, (match, code) => {
        const url = customEmojiMap.value[code]
        if (!url) return match
        return `<img src="${url}" alt="${match}" class="custom-emoji-inline" title="${match}" />`
    })
}
async function searchGif() {
    if (!gifQuery.value.trim()) return
    try {
        const res = await apiClient.get('/stickers/search-gif', { params: { q: gifQuery.value } })
        gifResults.value = res.data?.data?.gifs || []
    } catch { gifResults.value = [] }
}

// ── 标签 ──
async function loadAllTags() { try { const res = await apiClient.get('/im/tags'); allTags.value = res.data?.data || [] } catch { allTags.value = [] } }
async function loadConvTags(convId) { try { const res = await apiClient.get('/im/tags/assign', { params: { conversation_id: convId } }); convTags.value = res.data?.data?.tags || []; selectedTagIds.value = convTags.value.map(t => t.id) } catch { convTags.value = []; selectedTagIds.value = [] } }
async function saveConvTags() { if (!activeConv.value) return; savingTags.value = true; try { await apiClient.post('/im/tags/assign', { conversation_id: activeConv.value.id, tag_ids: selectedTagIds.value }); ElMessage.success('标签已保存'); await loadConvTags(activeConv.value.id) } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') } finally { savingTags.value = false } }

// ── 通知 ──
const notifications = ref([])
async function loadNotifications() { try { const res = await apiClient.get('/notifications'); notifications.value = res.data?.data || [] } catch { notifications.value = [] } }
function handleNotifClick(n) { /* handle notification click */ }
async function markAllNotifRead() { try { await apiClient.put('/notifications/read-all'); notifications.value.forEach(n => n.is_read = true) } catch { /* ignore */ } }
function initNotifEcho() { /* echo listener placeholder */ }

// ── 在线状态与心跳 ──
let heartbeatTimer = null
let oaUnreadTimer = null
function startHeartbeat() { heartbeatTimer = setInterval(async () => { try { await apiClient.post('/user-chat/heartbeat', { status: myStatus.value }) } catch { /* ignore */ } }, 60000) }
function stopHeartbeat() { if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null } }

// ── 好友系统 ──
async function loadFriendsEnhanced() {
    try {
        const res = await apiClient.get('/user-chat/friends/enhanced')
        const raw = res.data?.data
        friendsList.value = Array.isArray(raw) ? raw : (raw?.data || [])
    } catch { friendsList.value = [] }
}
async function loadFriendGroups() { try { const res = await apiClient.get('/user-chat/groups'); friendGroups.value = res.data?.data || [] } catch { friendGroups.value = [] } }
async function loadPendingRequests() { try { const res = await apiClient.get('/user-chat/friends/requests'); pendingRequests.value = res.data?.data || [] } catch { pendingRequests.value = [] } }
async function submitAddFriend() { if (!addFriendUserId.value) return; addFriendLoading.value = true; try { await apiClient.post('/user-chat/friends/add', { user_id: addFriendUserId.value }); showAddFriend.value = false; ElMessage.success('好友请求已发送') } catch (e) { ElMessage.error(e.response?.data?.message || '添加失败') } finally { addFriendLoading.value = false } }
async function handleFriendRequest(id, status) { try { await apiClient.put('/user-chat/friends/'+id+'/handle', { status }); await loadPendingRequests(); await loadFriendsEnhanced() } catch { /* ignore */ } }
async function startFriendChat(f) { try { const res = await apiClient.post('/user-chat/conversations', { participant_ids: [f.id] }); const conv = res.data?.data; if (conv) { sidebarTab.value = 'conversations'; const exists = conversations.value.find(c => c.id === conv.id); if (!exists) conversations.value.unshift(conv); selectConversation(conv) } } catch (e) { ElMessage.error(e.response?.data?.message || '创建会话失败') } }
function handleFriendAction(cmd, f) { if (cmd === 'remark') { remarkTarget.value = f; remarkText.value = f.remark || ''; showRemarkDialog.value = true } else if (cmd === 'group') { moveGroupTarget.value = f; moveGroupId.value = f.friend_group_id || null; showMoveGroupDialog.value = true } else if (cmd === 'remove') { removeFriend(f) } }
async function submitRemark() { if (!remarkTarget.value || !remarkText.value) return; try { await apiClient.put('/user-chat/friends/'+remarkTarget.value.id+'/remark', { remark: remarkText.value }); remarkTarget.value.remark = remarkText.value; showRemarkDialog.value = false; ElMessage.success('备注已更新') } catch (e) { ElMessage.error(e.response?.data?.message || '设置失败') } }
async function submitMoveGroup() { if (!moveGroupTarget.value) return; try { await apiClient.put('/user-chat/friends/'+moveGroupTarget.value.id+'/group', { group_id: moveGroupId.value }); moveGroupTarget.value.friend_group_id = moveGroupId.value; showMoveGroupDialog.value = false; ElMessage.success('已移动') } catch (e) { ElMessage.error(e.response?.data?.message || '移动失败') } }
async function removeFriend(f) { try { await ElMessageBox.confirm('确定删除好友 '+ (f.name||'用户') +'？'); await apiClient.delete('/user-chat/friends/'+f.id); await loadFriendsEnhanced() } catch { /* ignore */ } }
async function handleAiFriendAction(cmd, f) {
  if (cmd === 'delete') {
    try {
      await ElMessageBox.confirm('确定删除 AI 好友「' + f.name + '」？');
      await apiClient.delete('/ai-friends/personal/' + f.id);
      ElMessage.success('AI 好友已删除');
      await loadAiFriends();
    } catch (e) {
      if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '删除失败');
    }
  }
}
async function createFriendGroup() { if (!newFriendGroupName.value.trim()) return; try { const res = await apiClient.post('/user-chat/groups', { name: newFriendGroupName.value.trim() }); friendGroups.value.push(res.data?.data); newFriendGroupName.value = ''; ElMessage.success('分组已创建') } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败') } }
async function updateFriendGroup(g) { try { await apiClient.put('/user-chat/groups/'+g.id, { name: g.name }) } catch { /* ignore */ } }
async function deleteFriendGroup(id) { try { await apiClient.delete('/user-chat/groups/'+id); friendGroups.value = friendGroups.value.filter(g => g.id !== id) } catch { /* ignore */ } }

// ── Rendered ──
function renderContent(msg) { if (!msg.content) return ''; let text = escapeHtml(msg.content); text = text.replace(/@(\S+)/g, '<span class="mention-at">@$1</span>'); text = text.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" class="msg-link">$1</a>'); return text }
const markdownEnabled = ref(true)
function renderMarkdown(content) { if (!content) return ''; let text = escapeHtml(content); text = text.replace(/@(\S+)/g, '<span class="mention-at">@$1</span>'); text = text.replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener" class="msg-link">$1</a>'); if (!markdownEnabled.value) return text.replace(/\n/g, '<br>'); return renderMarkdownSyntax(text) }
function renderMarkdownSyntax(text) { const codeBlocks = []; text = text.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => { const idx = codeBlocks.length; codeBlocks.push('<pre><code class="hljs'+(lang?' language-'+lang:'')+'">'+code.trim()+'</code></pre>'); return '%%CODEBLOCK_'+idx+'%%' }); text = text.replace(/`([^`]+)`/g, '<code>$1</code>'); text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>'); text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>'); text = text.replace(/~~([^~]+)~~/g, '<del>$1</del>'); text = text.replace(/^---$/gm, '<hr>'); text = text.replace(/^### (.+)$/gm, '<h4>$1</h4>'); text = text.replace(/^## (.+)$/gm, '<h3>$1</h3>'); text = text.replace(/^# (.+)$/gm, '<h2>$1</h2>'); text = text.replace(/^&gt; (.+)$/gm, '<blockquote>$1</blockquote>'); text = text.replace(/^[-*] (.+)$/gm, '<li>$1</li>'); text = text.replace(/(<li>.*<\/li>\n?)+/g, '<ul>$&</ul>'); text = text.replace(/^\d+\. (.+)$/gm, '<li>$1</li>'); text = text.replace(/(?:<li>.*<\/li>\n?)+/g, (m) => m.includes('<ul>') ? m : '<ol>'+m+'</ol>'); text = text.replace(/\n/g, '<br>'); text = text.replace(/%%CODEBLOCK_(\d+)%%/g, (_, idx) => codeBlocks[parseInt(idx)]||''); return text }
function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML }

// ── 长文折叠 / Spoiler ──
const LONG_TEXT_THRESHOLD = 300 // 超过此字符数折叠
function isLongText(content) { return (content || '').length > LONG_TEXT_THRESHOLD }
function renderSpoiler(content) {
    if (!content) return content
    // 支持 ||spoiler|| 和 >!spoiler!< 格式
    let text = content
    // 先处理 >!spoiler!<
    text = text.replace(/>!([^!]+)!</g, (_, match) => {
        const inner = escapeHtml(match.trim())
        return `<span class="spoiler-text" onclick="this.classList.toggle('spoiler-revealed')" title="点击查看">${inner}</span>`
    })
    // 处理 ||spoiler||
    text = text.replace(/\|\|([^|]+)\|\|/g, (_, match) => {
        const inner = escapeHtml(match.trim())
        return `<span class="spoiler-text" onclick="this.classList.toggle('spoiler-revealed')" title="点击查看">${inner}</span>`
    })
    return text
}

// ── 链接预览 ──
const urlRegex = /https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)/g
async function fetchLinkPreview(msg) { if (msg.linkPreview || msg.linkPreviewLoading || !msg.content) return; const urls = msg.content.match(urlRegex); if (!urls) return; msg.linkPreviewLoading = true; try { const res = await apiClient.post('/user-chat/link-preview', { url: urls[0] }); msg.linkPreview = res.data?.data || null } catch { /* ignore */ } finally { msg.linkPreviewLoading = false } }
function openLink(url) { window.open(url, '_blank') }

// ── 群公告 ──
const showAnnouncements = ref(false); const showCreateAnnouncement = ref(false); const showAnnouncementDetailDialog = ref(false); const announcements = ref([]); const loadingAnnouncements = ref(false); const creatingAnnouncement = ref(false); const announcementDetail = ref(null); const newAnnouncement = ref({ title: '', content: '' })
async function loadAnnouncements() { if (!activeConv.value) return; loadingAnnouncements.value = true; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/announcements'); announcements.value = res.data?.data || [] } catch { announcements.value = [] } finally { loadingAnnouncements.value = false } }
async function submitAnnouncement() { if (!newAnnouncement.value.title.trim() || !newAnnouncement.value.content.trim()) { ElMessage.warning('请填写标题和内容'); return }; creatingAnnouncement.value = true; try { await apiClient.post('/user-chat/announcements', { conversation_id: activeConv.value.id, title: newAnnouncement.value.title.trim(), content: newAnnouncement.value.content.trim() }); ElMessage.success('公告已发布'); showCreateAnnouncement.value = false; newAnnouncement.value = { title: '', content: '' }; await loadAnnouncements() } catch (e) { ElMessage.error(e.response?.data?.message || '发布失败') } finally { creatingAnnouncement.value = false } }
async function markAnnouncementRead(a) { if (a.is_read) return; try { await apiClient.post('/user-chat/announcements/'+a.id+'/read'); a.is_read = true; ElMessage.success('已标记已读'); await loadAnnouncements() } catch { /* ignore */ } }
async function showAnnouncementDetail(a) {
    try {
        const res = await apiClient.get('/user-chat/announcements/'+a.id)
        const detail = res.data?.data
        // 同时加载阅读进度
        try {
            const progressRes = await apiClient.get('/user-chat/announcements/'+a.id+'/read-progress')
            const progress = progressRes.data?.data
            if (progress) {
                detail.total_members = progress.total_members
                detail.read_count = progress.read_count
                detail.unread_count = progress.unread_count
                detail.read_users = progress.read_by?.map(u => ({ id: u.user_id, name: u.name, avatar: u.avatar, read_at: u.read_at })) || []
                detail.progress_percent = progress.progress_percent
            }
        } catch {}
        announcementDetail.value = detail
        showAnnouncementDetailDialog.value = true
    } catch { /* ignore */ }
}

// ── 慢速模式 ──
const showSlowModeDialog = ref(false); const slowModeInterval = ref(0); const savingSlowMode = ref(false)
async function loadSlowMode() { if (!activeConv.value) return; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/slow-mode'); slowModeInterval.value = res.data?.data?.interval || 0 } catch { slowModeInterval.value = 0 } }
async function saveSlowMode() { if (!activeConv.value) return; savingSlowMode.value = true; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/slow-mode', { interval: slowModeInterval.value }); activeConv.value.slow_mode_interval = slowModeInterval.value; ElMessage.success(slowModeInterval.value > 0 ? '慢速模式已开启' : '慢速模式已关闭'); showSlowModeDialog.value = false } catch (e) { ElMessage.error(e.response?.data?.message || '设置失败') } finally { savingSlowMode.value = false } }
function openSlowModeDialog() { loadSlowMode(); showSlowModeDialog.value = true }

// ── 群邀请 ──
const showInviteDialog = ref(false); const inviteTab = ref('create'); const invites = ref([]); const creatingInvite = ref(false); const newInviteUrl = ref(''); const inviteExpires = ref(24); const inviteMaxUses = ref(0); const loadingInvites = ref(false)
function openInviteDialog() { showInviteDialog.value = true; inviteTab.value = 'create'; newInviteUrl.value = '' }
async function createInvite() { if (!activeConv.value) return; creatingInvite.value = true; try { const params = {}; if (inviteExpires.value > 0) params.expires_in_hours = inviteExpires.value; if (inviteMaxUses.value > 0) params.max_uses = inviteMaxUses.value; const res = await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/invites', params); newInviteUrl.value = res.data?.data?.url || ''; ElMessage.success('邀请链接已生成'); await loadInvites() } catch (e) { ElMessage.error(e.response?.data?.message || '生成失败') } finally { creatingInvite.value = false } }
async function loadInvites() { if (!activeConv.value) return; loadingInvites.value = true; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/invites'); invites.value = res.data?.data || [] } catch { invites.value = [] } finally { loadingInvites.value = false } }
async function revokeInvite(inv) { try { await apiClient.delete('/user-chat/invites/'+inv.id); inv.is_active = false; inv.is_valid = false; ElMessage.success('邀请已撤销'); await loadInvites() } catch { /* ignore */ } }
function copyInviteUrl() { navigator.clipboard.writeText(newInviteUrl.value); ElMessage.success('链接已复制') }

// ── 文件传输助手 ──
async function openSelfChat() { try { const res = await apiClient.post('/user-chat/self-conversation'); const conv = res.data?.data; if (conv) { sidebarTab.value = 'conversations'; const exists = conversations.value.find(c => c.id === conv.id); if (!exists) conversations.value.unshift(conv); selectConversation(conv) } } catch (e) { ElMessage.error(e.response?.data?.message || '打开失败') } }

// ── AI-012: AI 助手单聊 ──
async function openAIChat() { try { const res = await apiClient.post('/user-chat/ai-conversation'); const conv = res.data?.data; if (conv) { aiConvId.value = conv.id; sidebarTab.value = 'conversations'; const exists = conversations.value.find(c => c.id === conv.id); if (!exists) conversations.value.unshift(conv); selectConversation(conv); showAIPanel.value = true; aiMessages.value.push({ role: 'assistant', content: '你好！我是 AI 助手，有什么可以帮你的？' }) } } catch (e) { ElMessage.error(e.response?.data?.message || '打开失败') } }

// ── AI-004: AI 写作助手（润色/扩写/翻译/改语气）──
async function aiWrite(mode) { if (!inputMessage.value.trim() || !activeConv.value) return; const original = inputMessage.value; inputMessage.value = ''; aiLoading.value = true; try { const token = localStorage.getItem('auth_token') || ''; const res = await fetch('/api/user-chat/conversations/'+activeConv.value.id+'/chat-stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'Authorization': 'Bearer ' + token }, body: JSON.stringify({ message: original, mode: mode === 'polish' ? 'polish' : mode === 'translate' ? 'translate' : 'chat' }) }); const reader = res.body?.getReader(); if (!reader) throw new Error('No reader'); const decoder = new TextDecoder(); let fullContent = ''; while (true) { const { done, value } = await reader.read(); if (done) break; const text = decoder.decode(value, { stream: true }); const lines = text.split('\n'); for (const line of lines) { if (line.startsWith('data: ')) { try { const data = JSON.parse(line.slice(6)); if (data.type === 'chunk') fullContent += data.content; else if (data.type === 'done') fullContent = data.content } catch {} } } }; if (fullContent) { inputMessage.value = fullContent; ElMessage.success('已优化') } else { inputMessage.value = original } } catch { inputMessage.value = original; ElMessage.error('优化失败') } finally { aiLoading.value = false } }

// ── AIF: AI 好友系统 ──
async function loadAiFriends() { try { const res = await apiClient.get('/ai-friends/my'); aiFriends.value = res.data?.data || [] } catch { aiFriends.value = [] } }
function categoryLabel(cat) { return { assistant:'助手', translator:'翻译', writer:'写作', custom_service:'客服', custom:'自定义' }[cat] || cat }
function getDefaultAvatar(name) { return 'https://ui-avatars.com/api/?name='+encodeURIComponent(name||'AI')+'&background=409eff&color=fff&size=80' }
async function submitCreateAiFriend() {
  if (!newAiFriend.value.name.trim()) return ElMessage.warning('请输入名称');
  creatingAiFriend.value = true;
  try {
    const payload = { ...newAiFriend.value };
    const res = await apiClient.post('/ai-friends/personal', payload);
    if (res.data?.success) {
      ElMessage.success('AI 好友已创建');
      showCreateAiFriend.value = false;
      newAiFriend.value = { name:'', category:'assistant', provider:'deepseek', model_name:'deepseek-chat', api_key:'', api_base_url:'', system_prompt:'', avatar_url:'' };
      openAvatarInput.value = false;
      await loadAiFriends();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败') }
  finally { creatingAiFriend.value = false }
}
async function startAiFriendChat(f) {
  // 打开与 AI 好友的会话
  try {
    const res = await apiClient.post('/ai-friends/' + f.id + '/chat', { message: f.welcome_message || '你好' });
    const d = res.data?.data;
    if (d) {
      sidebarTab.value = 'conversations';
      if (d.conversation_id) {
        const conv = { id: d.conversation_id, name: f.name, type: 'ai_friend', is_ai_friend: true };
        const exists = conversations.value.find(c => c.id === d.conversation_id);
        if (!exists) conversations.value.unshift(conv);
        selectConversation(conv);
      }
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '打开失败') }
}

// ── AI 好友管理（管理员）──
async function loadAdminAiFriends() { loadingAdminAi.value = true; try { const res = await apiClient.get('/ai-friends/admin'); adminAiFriends.value = res.data?.data?.data || res.data?.data || [] } catch { adminAiFriends.value = [] } finally { loadingAdminAi.value = false } }
async function testAiFriend(id) { testingId.value = id; try { const res = await apiClient.post('/ai-friends/admin/'+id+'/test'); ElMessage.success(res.data?.data?.message || '连接成功') } catch (e) { ElMessage.error(e.response?.data?.message || '测试失败') } finally { testingId.value = null } }
async function publishAiFriend(id) { try { await apiClient.post('/ai-friends/admin/'+id+'/publish'); ElMessage.success('已发布，全员可见'); await loadAdminAiFriends() } catch (e) { ElMessage.error(e.response?.data?.message || '发布失败') } }
async function viewAiFriendConvs(row) {
    aiFriendConvsTarget.value = row
    aiFriendConvsName.value = row.user?.name || 'AI 好友'
    showAiFriendConvs.value = true
}
async function loadAiFriendConvs() {
    if (!aiFriendConvsTarget.value?.id) return
    loadingAiFriendConvs.value = true
    try {
        const r = await apiClient.get('/ai-friends/admin/' + aiFriendConvsTarget.value.id + '/conversations')
        aiFriendConvs.value = r.data?.data || []
    } catch { aiFriendConvs.value = [] }
    finally { loadingAiFriendConvs.value = false }
}
// ── AI 好友头像上传 ──
async function uploadPersonalAvatar(options) {
  const formData = new FormData();
  formData.append('avatar', options.file);
  try {
    const res = await apiClient.post('/ai-friends/upload-avatar', formData);
    newAiFriend.value.avatar_url = res.data?.data?.url || '';
    ElMessage.success('头像已上传');
  } catch (e) {
    ElMessage.error('头像上传失败');
  }
}
async function uploadPlatformAvatar(options) {
  const formData = new FormData();
  formData.append('avatar', options.file);
  try {
    const res = await apiClient.post('/ai-friends/upload-avatar', formData);
    platformAiForm.value.avatar_url = res.data?.data?.url || '';
    ElMessage.success('头像已上传');
  } catch (e) {
    ElMessage.error('头像上传失败');
  }
}

async function submitPlatformAi() {
  if (!platformAiForm.value.name.trim()) return ElMessage.warning('请输入名称');
  creatingPlatformAi.value = true;
  try {
    const payload = { ...platformAiForm.value };
    if (payload.provider === 'deepseek') delete payload.api_key;
    const res = await apiClient.post('/ai-friends/admin', payload);
    if (res.data?.success) {
      ElMessage.success('平台 AI 好友已创建并发布');
      showCreatePlatformAi.value = false;
      platformAiForm.value = { name:'', category:'assistant', provider:'deepseek', model_name:'deepseek-chat', api_key:'', avatar_url:'', welcome_message:'', system_prompt:'' };
      platformAiShowUrl.value = false;
      // 自动发布
      const newId = res.data?.data?.id;
      if (newId) await publishAiFriend(newId);
      await loadAdminAiFriends();
      await loadAiFriends();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败') }
  finally { creatingPlatformAi.value = false }
}

// ── 语音消息 ──
const isRecording = ref(false); const recordingDuration = ref(0); let mediaRecorder = null; let audioChunks = []; let recordingTimer = null; const voicePlayingId = ref(null); let audioPlayer = null
async function toggleVoiceRecord() { if (isRecording.value) { stopRecording(); return }; if (!navigator.mediaDevices?.getUserMedia) { ElMessage.warning('您的浏览器不支持录音'); return }; try { const stream = await navigator.mediaDevices.getUserMedia({ audio: true }); const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')?'audio/webm;codecs=opus':'audio/webm'; mediaRecorder = new MediaRecorder(stream, { mimeType }); audioChunks = []; recordingDuration.value = 0; isRecording.value = true; mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data) }; mediaRecorder.onstop = async () => { isRecording.value = false; clearInterval(recordingTimer); stream.getTracks().forEach(t => t.stop()); if (audioChunks.length === 0) return; const blob = new Blob(audioChunks, { type: 'audio/webm' }); if (blob.size < 100) return; await sendVoiceMessage(blob) }; mediaRecorder.start(); recordingTimer = setInterval(() => { recordingDuration.value++ }, 1000) } catch { ElMessage.error('无法访问麦克风'); isRecording.value = false } }
function stopRecording() { if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop() }
async function sendVoiceMessage(blob) { if (!activeConv.value) return; const formData = new FormData(); formData.append('file', blob, 'voice.webm'); try { const uploadRes = await apiClient.post('/im/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }); const url = uploadRes.data?.data?.url; if (!url) { ElMessage.error('上传失败'); return }; await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { message_type: 'voice', content: url, attachments: [{ duration: recordingDuration.value }] }); await loadMessages() } catch (e) { ElMessage.error(e.response?.data?.message || '发送失败') } }
function voiceDuration(msg) { return msg.attachments?.[0]?.duration || msg.duration || 0 }
function playVoice(msg) { if (!msg.content) return; if (voicePlayingId.value === msg.id && audioPlayer) { audioPlayer.pause(); audioPlayer = null; voicePlayingId.value = null; return }; if (audioPlayer) { audioPlayer.pause(); audioPlayer = null }; voicePlayingId.value = msg.id; audioPlayer = new Audio(msg.content); audioPlayer.onended = () => { voicePlayingId.value = null; audioPlayer = null }; audioPlayer.onerror = () => { voicePlayingId.value = null; audioPlayer = null; ElMessage.warning('播放失败') }; audioPlayer.play().catch(() => { voicePlayingId.value = null }) }

// MEDIA-012: 语音转文字
async function transcribeVoice(msg) {
    if (msg._transcribing) return;
    msg._transcribing = true;
    try {
        const res = await apiClient.post('/user-chat/messages/' + msg.id + '/transcribe');
        const data = res.data?.data || {};
        if (data.transcript) {
            if (!msg.metadata) msg.metadata = {};
            msg.metadata.transcript = data.transcript;
            ElMessage.success('语音转文字完成');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '语音识别失败');
    } finally {
        msg._transcribing = false;
    }
}

// ── 会话文件夹 ──
const showFolderDialog = ref(false); const folders = ref(loadFolders()); const activeFolder = ref(''); const newFolderName = ref('')
function loadFolders() { try { return JSON.parse(localStorage.getItem('chat-folders') || '[]') } catch { return [] } }
function saveFolders() { localStorage.setItem('chat-folders', JSON.stringify(folders.value)) }
function createFolder() { if (!newFolderName.value.trim()) { ElMessage.warning('请输入名称'); return }; folders.value.push({ id: Date.now(), name: newFolderName.value.trim() }); saveFolders(); newFolderName.value = ''; ElMessage.success('分组已创建') }
function deleteFolder(f) { folders.value = folders.value.filter(x => x.id !== f.id); saveFolders(); if (activeFolder.value === f.id || String(activeFolder.value) === String(f.id)) activeFolder.value = ''; ElMessage.success('分组已删除') }
function onFolderChange() { /* filter handled by computed */ }
function showConvFolderMenu(e, conv) { const menu = document.createElement('div'); menu.className = 'conv-folder-menu'; menu.style.cssText = 'position:fixed;left:'+e.clientX+'px;top:'+e.clientY+'px;z-index:9999;background:#fff;border:1px solid #e4e7ed;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);padding:4px 0;min-width:160px'; menu.innerHTML = '<div style="padding:4px 12px;font-size:12px;color:#999;border-bottom:1px solid #f0f0f0">分配到分组</div>'; const noneItem = document.createElement('div'); noneItem.className = 'folder-menu-item'; noneItem.textContent = '无分组'; noneItem.onclick = () => { assignConvFolder(conv, null); menu.remove() }; menu.appendChild(noneItem); folders.value.forEach(f => { const item = document.createElement('div'); item.className = 'folder-menu-item'; item.textContent = '📁 '+f.name; item.onclick = () => { assignConvFolder(conv, f.id); menu.remove() }; menu.appendChild(item) }); document.body.appendChild(menu); setTimeout(() => document.addEventListener('click', (e2) => { if (!menu.contains(e2.target)) { menu.remove() } }), 10) }
function assignConvFolder(conv, folderId) { const key = 'chat-folder-convs-'+(folderId||'null'); let ids = JSON.parse(localStorage.getItem(key)||'[]'); if (!folderId) { folders.value.forEach(f => { const k = 'chat-folder-convs-'+f.id; let ids2 = JSON.parse(localStorage.getItem(k)||'[]'); ids2 = ids2.filter(id => id !== conv.id); localStorage.setItem(k, JSON.stringify(ids2)) }) } else { if (!ids.includes(conv.id)) ids.push(conv.id); localStorage.setItem(key, JSON.stringify(ids)) }; ElMessage.success(folderId ? '已分配到分组' : '已取消分组') }

// ── 名片消息 ──
async function shareContact(msg) { if (!activeConv.value) return; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { message_type: 'contact', content: String(msg.sender_id) }); await loadMessages(); ElMessage.success('名片已分享') } catch (e) { ElMessage.error(e.response?.data?.message || '发送失败') } }
function contactName(msg) { return msg.contact_name || '用户' }
function replyPreviewText(msg) {
    const typeMap = { text: '', image: '[图片]', voice: '[语音]', file: '[文件]', card: '[卡片]', sticker: '[贴纸]', forward: '[转发]', contact: '[名片]', location: '[位置]' }
    const prefix = typeMap[msg.message_type] || '[' + msg.message_type + ']'
    return (prefix ? prefix + ' ' : '') + (msg.content?.substring(0, 60) || '')
}
async function addContactByCard(msg) { const userId = parseInt(msg.content); if (!userId) return; try { await apiClient.post('/user-chat/friends/add', { user_id: userId }); ElMessage.success('好友请求已发送') } catch (e) { ElMessage.error(e.response?.data?.message || '添加失败') } }

// ── 举报 ──
function openReportDialog(msg) { reportTarget.value = msg; reportReason.value = 'spam'; reportDescription.value = ''; showReportDialog.value = true }
async function submitReport() { if (!reportTarget.value) return; submittingReport.value = true; try { await apiClient.post('/user-chat/reports', { reportable_type: 'message', reportable_id: reportTarget.value.id, reason: reportReason.value, description: reportDescription.value }); ElMessage.success('举报已提交，感谢您的反馈'); showReportDialog.value = false } catch (e) { ElMessage.error(e.response?.data?.message || '提交失败') } finally { submittingReport.value = false } }
// PRES-003: 设置在线状态
const autoReplyEnabled = ref(false)
async function setMyStatus(status) {
    if (status === 'goto_auto_reply') { router.push('/auto-reply'); return }
    myStatus.value = status
    try { await apiClient.put('/user-chat/status', { status }) } catch { /* ignore */ }
}
async function toggleAutoReply(v) {
    autoReplyEnabled.value = v
    // 如果启用但没有规则，引导创建
    if (v) {
        try {
            const res = await apiClient.get('/user-chat/auto-reply')
            const rules = res.data?.data || []
            if (!rules.length) {
                // 自动创建一条默认的离开自动回复
                await apiClient.post('/user-chat/auto-reply', {
                    type: 'away',
                    reply_content: '我现在不在，稍后回复您。',
                    is_active: true,
                })
                ElMessage.success('已启用自动回复（默认：我现在不在，稍后回复您）')
            }
            // 激活所有规则或创建默认规则后刷新
            loadAutoReplyStatus()
        } catch { /* ignore */ }
    } else {
        // 禁用所有规则
        try {
            const res = await apiClient.get('/user-chat/auto-reply')
            const rules = res.data?.data || []
            for (const r of rules) {
                if (r.is_active) {
                    await apiClient.put('/user-chat/auto-reply/' + r.id, { is_active: false })
                }
            }
        } catch { /* ignore */ }
    }
}
async function loadAutoReplyStatus() {
    try {
        const res = await apiClient.get('/user-chat/auto-reply/status')
        const data = res.data?.data || {}
        autoReplyEnabled.value = data.has_auto_reply || false
    } catch { autoReplyEnabled.value = false }
}
// SEC-006: 隐私设置
async function loadPrivacySettings() { try { const res = await apiClient.get('/user-chat/privacy-settings'); privacySettings.value = res.data?.data || privacySettings.value } catch { /* ignore */ } }
async function savePrivacySettings() { savingPrivacy.value = true; try { await apiClient.put('/user-chat/privacy-settings', privacySettings.value); ElMessage.success('隐私设置已保存'); showPrivacySettings.value = false } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') } finally { savingPrivacy.value = false } }
// ── 私密空间 ──
async function loadPrivacyPinStatus() {
    try { const res = await apiClient.get('/user-chat/privacy-pin/status'); privacyPinStatus.value = res.data?.data || { has_pin: false, verified: false } } catch { privacyPinStatus.value = { has_pin: false, verified: false } }
}
async function submitPrivacyPin() {
    if (pinForm.value.newPin !== pinForm.value.confirmPin) { ElMessage.warning('两次输入的 PIN 不一致'); return }
    if (!/^\d{4,20}$/.test(pinForm.value.newPin)) { ElMessage.warning('PIN 必须为 4-20 位数字'); return }
    savingPin.value = true
    try {
        await apiClient.post('/user-chat/privacy-pin/set', {
            pin: pinForm.value.newPin,
            current_pin: pinForm.value.currentPin || undefined,
        })
        ElMessage.success(setPinMode.value === 'set' ? 'PIN 已设置' : 'PIN 已修改')
        showSetPin.value = false
        pinForm.value = { currentPin: '', newPin: '', confirmPin: '' }
        loadPrivacyPinStatus()
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') } finally { savingPin.value = false }
}
async function removePrivacyPin() {
    try { await ElMessageBox.confirm('确定清除私密空间 PIN？隐藏会话将可直接查看', '确认')
        await apiClient.post('/user-chat/privacy-pin/set', { pin: '000000' }) // dummy to fail - actually need a proper remove endpoint
        // For now just use the status check
        ElMessage.success('功能开发中')
    } catch {}
}
function openHiddenConversations() {
    if (privacyPinStatus.value.has_pin && !privacyPinStatus.value.verified) {
        showUnlockDialog.value = true; unlockPin.value = ''; unlockError.value = ''
    } else {
        loadHiddenConvs()
    }
}
async function verifyUnlockPin() {
    if (!unlockPin.value.trim()) { unlockError.value = '请输入 PIN 码'; return }
    unlocking.value = true; unlockError.value = ''
    try {
        await apiClient.post('/user-chat/privacy-pin/verify', { pin: unlockPin.value })
        privacyPinStatus.value.verified = true
        showUnlockDialog.value = false
        ElMessage.success('验证成功')
        loadHiddenConvs()
    } catch (e) { unlockError.value = e.response?.data?.message || 'PIN 不正确' } finally { unlocking.value = false }
}
function onUnlockCancel() { unlockPin.value = ''; unlockError.value = '' }
async function loadHiddenConvs() {
    try {
        const res = await apiClient.get('/user-chat/conversations/hidden')
        hiddenConvs.value = res.data?.data || []
        showHiddenConvs.value = true
    } catch { ElMessage.error('加载失败') }
}
async function toggleHideConv(conv) {
    try {
        if (conv.is_hidden) {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/unhide')
            conv.is_hidden = false
            ElMessage.success('已取消隐藏')
        } else {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/hide')
            conv.is_hidden = true
            ElMessage.success('已隐藏')
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}
function selectHiddenConv(conv) {
    showHiddenConvs.value = false
    selectConversation(conv)
}
async function unhideConv(conv) {
    try {
        await apiClient.post('/user-chat/conversations/' + conv.id + '/unhide')
        conv.is_hidden = false
        hiddenConvs.value = hiddenConvs.value.filter(c => c.id !== conv.id)
        ElMessage.success('已取消隐藏')
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

// ── OPR-011: 投票 ──
function openPollDialog() { showPollDialog.value = true; pollQuestion.value = ''; pollOptions.value = ['', '']; pollType.value = 'single'; pollIsAnonymous.value = false; pollHideResults.value = false; pollExpireHours.value = null }
async function submitPoll() { if (!pollQuestion.value.trim() || !activeConv.value) return; const opts = pollOptions.value.filter(o => o.trim()); if (opts.length < 2) { ElMessage.warning('至少需要2个选项'); return }; if (pollType.value === 'ranked' && opts.length > 10) { ElMessage.warning('排序投票最多10个选项'); return }; creatingPoll.value = true; try { await apiClient.post('/user-chat/polls', { conversation_id: activeConv.value.id, question: pollQuestion.value, options: opts, type: pollType.value, is_anonymous: pollIsAnonymous.value, is_hide_results: pollHideResults.value, expires_in_hours: pollExpireHours.value || undefined }); ElMessage.success('投票已发布'); showPollDialog.value = false } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败') } finally { creatingPoll.value = false } }
async function showPollDetail(pollId) { try { const res = await apiClient.get('/user-chat/polls/'+pollId); const d = res.data?.data || {}; activePoll.value = d.poll || d; pollResults.value = d.results || []; pollTotalVotes.value = d.total_voters || 0; showPollResult.value = true } catch { ElMessage.error('获取投票详情失败') } }
async function votePoll(pollId, optionId) {
    try {
        const votes = [{ option_id: optionId, rank: 1 }]
        await apiClient.post('/user-chat/polls/'+pollId+'/vote', { votes })
        ElMessage.success('投票成功')
        showPollResult.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || '投票失败') }
}

// ── AI-001: 智能回复 ──
async function loadSmartReplies() { if (!activeConv.value) return; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/smart-replies'); smartReplies.value = res.data?.data?.replies || [] } catch { smartReplies.value = [] } }
watch(() => messages.value?.length, () => { if (messages.value?.length) loadSmartReplies() })

// ── AI-003: 总结（接入真实 LLM）──
// ── AI 群主持人 ──
function initModerator() {
    moderatorResult.value = null
    moderatorTopic.value = ''
    moderatorRunning.value = false
}
async function runModerator() {
    if (!activeConv.value) return
    if (moderatorMode.value === 'focus' && !moderatorTopic.value.trim()) {
        ElMessage.warning('请输入讨论主题'); return
    }
    moderatorRunning.value = true
    moderatorResult.value = null
    const modeMap = { summary: 'summary', agenda: 'agenda', mediate: 'mediate', focus: 'focus' }
    const mode = modeMap[moderatorMode.value]
    try {
        const params = {}
        if (moderatorTopic.value.trim()) params.topic = moderatorTopic.value.trim()
        const res = await apiClient.post(`/enterprise-ai/moderator/${mode}/${activeConv.value.id}`, params)
        moderatorResult.value = res.data?.data
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '分析失败')
    } finally {
        moderatorRunning.value = false
    }
}

async function handleSummarize() { if (!activeConv.value) return; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/summarize'); const d = res.data?.data; if (!d) { ElMessage.warning('暂无消息可总结'); return }; if (d.from_llm) { ElMessageBox.alert(`<div class="summary-content">${d.summary.replace(/\n/g, '<br>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')}</div><div class="summary-footer" style="margin-top:12px;color:#999;font-size:12px">共 ${d.total} 条消息 · AI 生成</div>`, '📝 对话总结', { dangerouslyUseHTMLString: true, customClass: 'summary-dialog', width: '520px', confirmButtonText: '关闭' }) } else { ElMessage.success(d.summary, 5000) } } catch (e) { ElMessage.error(e.response?.data?.message || '总结失败') } }

// ── 位置消息 ──
const showLocationDialog = ref(false); const locationNameInput = ref(''); const locationLng = ref(116.397428); const locationLat = ref(39.90923); const gettingLocation = ref(false); const sendingLocation = ref(false)
function getCurrentLocation() { if (!navigator.geolocation) { ElMessage.warning('浏览器不支持定位'); return }; gettingLocation.value = true; navigator.geolocation.getCurrentPosition((pos) => { locationLat.value = pos.coords.latitude; locationLng.value = pos.coords.longitude; gettingLocation.value = false }, () => { ElMessage.warning('获取位置失败'); gettingLocation.value = false }, { enableHighAccuracy: true, timeout: 10000 }) }
async function sendLocation() { if (!activeConv.value) return; sendingLocation.value = true; try { const content = (locationNameInput.value||'位置')+'@'+locationLat.value+','+locationLng.value; await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { message_type: 'location', content }); showLocationDialog.value = false; await loadMessages() } catch (e) { ElMessage.error(e.response?.data?.message || '发送失败') } finally { sendingLocation.value = false } }
function locationName(msg) { return msg.content?.split('@')[0] || '位置' }
function locationCoords(msg) { return msg.content?.split('@')[1] || '' }
function openLocation(msg) { const coords = locationCoords(msg); if (coords) window.open('https://www.openstreetmap.org/?mlat='+coords.split(',')[0]+'&mlon='+coords.split(',')[1]+'&zoom=15', '_blank') }

// ── 免打扰 ──
async function saveDndSettings() { try { await apiClient.post('/user/notification-preferences', { dnd_enabled: dndEnabled.value, dnd_start: dndStart.value, dnd_end: dndEnd.value }); ElMessage.success('免打扰设置已保存'); showDndSettings.value = false } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') } }

// ── IM 看板 ──
async function loadDashboard() { loadingDashboard.value = true; try { const res = await apiClient.get('/api/im/dashboard'); dashboardData.value = res.data?.data || {} } catch { dashboardData.value = {} } finally { loadingDashboard.value = false } }

// ── 群管理 ──
async function loadGroupMembers() { if (!activeConv.value) return; loadingGroupMembers.value = true; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/participants'); groupMembers.value = res.data?.data || []; const me = groupMembers.value.find(m => m.id === myId.value); userRoleInGroup.value = me?.pivot?.role || 'member' } catch { groupMembers.value = [] } finally { loadingGroupMembers.value = false } }
watch(showOaArticleManager, v => { if (v) { loadOaManagedArticles(); oaArticleMgrSearch.value = ''; oaArticleMgrFilter.value = 'all' } })
async function kickMember(m) { try { await ElMessageBox.confirm('确定将 '+ (m.name || m.nickname || '用户#'+m.id) +' 移出群聊？'); await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/kick/'+m.id); ElMessage.success('已移除'); await loadGroupMembers() } catch { /* ignore */ } }
async function leaveCurrentGroup() { try { await ElMessageBox.confirm('确定退出群聊？'); await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/leave'); ElMessage.success('已退出群聊'); showGroupManage.value = false; activeConv.value = null; await loadConversations() } catch { /* ignore */ } }
async function setAsAdmin(m) { try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/set-admin', { user_id: m.id, role: 'admin' }); ElMessage.success('已设为管理员'); await loadGroupMembers() } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') } }
async function removeAdmin(m) { try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/set-admin', { user_id: m.id, role: 'member' }); ElMessage.success('已取消管理员'); await loadGroupMembers() } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') } }
async function confirmTransferOwner() { if (!newOwnerId.value) return; transferring.value = true; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/transfer-owner', { user_id: newOwnerId.value }); ElMessage.success('群主已转让'); showTransferOwner.value = false; newOwnerId.value = null; await loadGroupMembers() } catch (e) { ElMessage.error(e.response?.data?.message || '转让失败') } finally { transferring.value = false } }
async function dismissCurrentGroup() { try { await ElMessageBox.confirm('确定解散群聊？此操作不可撤销！', '警告', { confirmButtonText: '确认解散', cancelButtonText: '取消', type: 'warning' }); await apiClient.delete('/user-chat/conversations/'+activeConv.value.id+'/dismiss'); ElMessage.success('群聊已解散'); showGroupManage.value = false; activeConv.value = null; await loadConversations() } catch { /* ignore */ } }

// ── 入群审批 ──
watch(showGroupManage, v => { if (v) { loadGroupMembers(); loadJoinApprovalStatus(); loadGroupPerms(); } })
async function loadJoinApprovalStatus() {
    if (!activeConv.value) return
    try {
        const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id)
        groupJoinApproval.value = res.data?.data?.join_approval || false
    } catch { groupJoinApproval.value = false }
    // 同时加载待审批请求
    if (userRoleInGroup.value !== 'member') {
        try {
            const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/join-requests')
            pendingJoinReqs.value = res.data?.data || []
        } catch { pendingJoinReqs.value = [] }
    }
}
async function toggleJoinApproval() {
    try {
        const res = await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/toggle-approval')
        groupJoinApproval.value = res.data?.data?.join_approval ?? !groupJoinApproval.value
        ElMessage.success(groupJoinApproval.value ? '已开启入群审批' : '已关闭入群审批')
    } catch (e) {
        groupJoinApproval.value = !groupJoinApproval.value // revert
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}
async function handleJoinRequest(req, action) {
    try {
        await apiClient.post('/user-chat/join-requests/'+req.id+'/handle', { action })
        ElMessage.success(action === 'approve' ? '已通过' : '已拒绝')
        pendingJoinReqs.value = pendingJoinReqs.value.filter(r => r.id !== req.id)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ── 群权限配置 ──
async function loadGroupPerms() {
    if (!activeConv.value) return
    try {
        const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/permissions')
        groupPermissions.value = res.data?.data || {}
    } catch { groupPermissions.value = {} }
}
async function updateGroupPerm(key, value) {
    const newPerms = { ...groupPermissions.value, [key]: value }
    try {
        await apiClient.put('/user-chat/conversations/'+activeConv.value.id+'/permissions', { permissions: newPerms })
        groupPermissions.value = newPerms
        ElMessage.success('权限已更新')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '更新失败')
    }
}

// ── 敏感词管理 ──
async function loadSensitiveWords() { try { const res = await apiClient.get('/im/sensitive-words'); sensitiveWords.value = res.data?.data || [] } catch { sensitiveWords.value = [] } }
async function addSensitiveWord() { if (!newSensitiveWord.value.word.trim()) return; addingSensitiveWord.value = true; try { await apiClient.post('/im/sensitive-words', { ...newSensitiveWord.value }); ElMessage.success('已添加'); newSensitiveWord.value = { word:'', replacement:'***', category:'general', severity:'medium' }; await loadSensitiveWords() } catch (e) { ElMessage.error(e.response?.data?.message || '添加失败') } finally { addingSensitiveWord.value = false } }
async function toggleSensitiveWord(row) { try { await apiClient.put('/im/sensitive-words/'+row.id, { is_active: !row.is_active }); row.is_active = !row.is_active } catch { ElMessage.error('操作失败') } }
async function deleteSensitiveWord(row) { try { await ElMessageBox.confirm('确定删除敏感词「'+row.word+'」？'); await apiClient.delete('/im/sensitive-words/'+row.id); ElMessage.success('已删除'); await loadSensitiveWords() } catch { /* ignore */ } }
async function testSensitiveWord() { if (!sensitiveTestText.value.trim()) return; testingSensitive.value = true; try { const res = await apiClient.post('/im/sensitive-words/test', { text: sensitiveTestText.value }); sensitiveTestResult.value = res.data?.data || null } catch { sensitiveTestResult.value = null } finally { testingSensitive.value = false } }

// ── 实时消息监听 ──
function initEcho() { /* echo placeholder */ }
function startTypingEcho() { /* typing echo placeholder */ }

// ── Tab切换 ──
function onSidebarTabChange() {
    if (sidebarTab.value === 'friends') { loadFriendsEnhanced(); loadFriendGroups(); loadPendingRequests() }
    else if (sidebarTab.value === 'channels') { loadMyChannels(); loadBrowseChannels(); loadChannelCategories() }
    else if (sidebarTab.value === 'plaza') { onPlazaCategory('all') }
    else if (sidebarTab.value === 'notifications') { loadNotifications() }
    else if (sidebarTab.value === 'favorites') { loadFavorites() }
    else if (sidebarTab.value === 'pending') { loadPending() }
    else if (sidebarTab.value === 'myProfile') { loadMyProfileData() }
}

// ── 我的面板方法 ──
async function loadMyProfileData() {
    try {
        const [userRes, pointsRes] = await Promise.all([
            apiClient.get('/user'),
            apiClient.get('/points/balance').catch(() => ({ data: { data: { balance: 0 } } })),
        ]);
        userInfo.value = userRes.data?.data || {};
        pointsBalance.value = pointsRes.data?.data?.balance || 0;
        // 加载阅读清单数量
        apiClient.get('/official-accounts/reading-list', { params: { per_page: 1 } })
            .then(r => { readingListCount.value = r.data?.data?.total || 0 })
            .catch(() => {});
    } catch { /* ignore */ }
}

function openAccountProfile() {
    window.open('/build/account/profile', '_blank');
}
function openMyInteractions() {
    showMyInteractions.value = true;
    myInteractionTab.value = 'following';
    loadFollowingFeed();
    loadMyReadingList();
    loadMyFollowedAccounts();
    loadMyFavorites();
    loadMyLikes();
}
async function loadFollowingFeed() {
    loadingFollowing.value = true;
    try {
        const r = await apiClient.get('/user/interactions/following-feed', { params: { limit: 20 } });
        followingFeed.value = r.data?.data?.items || r.data?.data || [];
    } catch { followingFeed.value = [] }
    finally { loadingFollowing.value = false }
}
async function loadMyReadingList() {
    loadingReadingList.value = true;
    try {
        const r = await apiClient.get('/user/interactions/reading-queue', { params: { per_page: 20 } });
        readingList.value = r.data?.data?.data || r.data?.data || [];
        readingListCount.value = r.data?.data?.total || readingList.value.length || 0;
    } catch { readingList.value = [] }
    finally { loadingReadingList.value = false }
}
async function loadMyFollowedAccounts() {
    loadingFollows.value = true;
    try {
        const r = await apiClient.get('/user/interactions', { params: { types: 'follows', per_page: 20 } });
        myFollowedAccounts.value = r.data?.data?.follows || [];
    } catch { myFollowedAccounts.value = [] }
    finally { loadingFollows.value = false }
}
async function loadMyFavorites() {
    loadingFavs.value = true;
    try {
        const r = await apiClient.get('/user/interactions', { params: { types: 'favorites', per_page: 20 } });
        myFavorites.value = r.data?.data?.favorites || [];
    } catch { myFavorites.value = [] }
    finally { loadingFavs.value = false }
}
async function loadMyLikes() {
    loadingLikes.value = true;
    try {
        const r = await apiClient.get('/user/interactions', { params: { types: 'likes', per_page: 20 } });
        myLikes.value = r.data?.data?.likes || [];
    } catch { myLikes.value = [] }
    finally { loadingLikes.value = false }
}
function selectFollowedAccount(account) {
    if (account.latest_article) {
        openArticleFromFeed(account.latest_article);
    }
}
// ── 从朋友圈打开文章（关闭朋友圈，跳转到OA详情） ──
function openArticleFromFeed(article) {
    if (!article?.id) return;
    showMyInteractions.value = false;
    // 根据内容类型打开对应详情
    const type = article._type || article.type || '';
    if (type === 'forum_post' || type === 'plaza') {
        window.open('/build/plaza/' + article.id, '_blank');
    } else if (type === 'blog_post') {
        window.open('/blog/' + (article.slug || article.id), '_blank');
    } else {
        // OA article 或其他
        if (sidebarTab.value !== 'officialAccounts') {
            sidebarTab.value = 'officialAccounts';
        }
        nextTick(() => {
            // 如果是完整文章对象直接用，否则按ID加载
            if (article.content || article.title) {
                openOaArticleDetail(article);
            } else {
                openOaArticleDetailById(article.id);
            }
        });
    }
}
function openSocialTab(tab) {
    if (tab === 'reading') {
        window.open('/build/account/profile?tab=reading', '_blank');
    } else if (tab === 'favorites') {
        sidebarTab.value = 'favorites';
    } else {
        window.open('/build/account/profile?tab=' + tab, '_blank');
    }
}
function openPointsHistory() {
    // 触发积分交易记录弹窗
    pointsHistoryVisible.value = true;
}

function handleMoreTab(tab) {
    sidebarTab.value = tab;
    onSidebarTabChange();
}

// ── 频道方法 ──
async function loadMyChannels() {
    loadingChannels.value = true;
    try { const r = await apiClient.get('/channels'); myChannels.value = r.data?.data || [] } catch { myChannels.value = [] }
    finally { loadingChannels.value = false }
}
async function loadBrowseChannels() {
    try { const r = await apiClient.get('/channels/browse'); browseChannels.value = r.data?.data || [] } catch { browseChannels.value = [] }
}
async function loadChannelCategories() {
    try { const r = await apiClient.get('/channels/categories'); channelCategories.value = r.data?.data || [] } catch { channelCategories.value = [] }
}
// ── 广场方法 ──
const plazaCategory = ref('all')
const plazaPosts = ref([])
const plazaLoading = ref(false)
const plazaKeyword = ref('')
const plazaCategoryId = ref(null)
const plazaTag = ref(null)
const plazaCollectionId = ref(null)
const plazaPage = ref(1)
const plazaHasMore = ref(true)
const plazaLoadingMore = ref(false)
const plazaFeedRef = ref(null)

// ── 自适应列数 ──
let masonryObserver = null
function updateMasonryColumns(container) {
    if (!container) return
    const w = container.clientWidth
    let cols = 2
    if (w >= 900) cols = 3
    if (w >= 1300) cols = 4
    container.style.columnCount = cols
}

function setupMasonryObserver(el) {
    if (masonryObserver) masonryObserver.disconnect()
    if (!el) return
    updateMasonryColumns(el)
    masonryObserver = new ResizeObserver(entries => {
        for (const entry of entries) {
            updateMasonryColumns(entry.target)
        }
    })
    masonryObserver.observe(el)
}

// ── 下拉刷新 ──
const pullRefreshHint = ref('')
let touchStartY = 0
let touchMoveY = 0
let isPulling = false

function onTouchStart(e) {
    const el = plazaFeedRef.value
    if (!el || el.scrollTop > 0) return
    touchStartY = e.touches[0].clientY
    isPulling = true
}

function onTouchMove(e) {
    if (!isPulling) return
    touchMoveY = e.touches[0].clientY
    const diff = touchMoveY - touchStartY
    if (diff > 0) {
        pullRefreshHint.value = diff > 60 ? '释放刷新' : '下拉刷新...'
    }
}

async function onTouchEnd() {
    if (!isPulling) return
    isPulling = false
    const diff = touchMoveY - touchStartY
    if (diff > 60) {
        pullRefreshHint.value = '刷新中...'
        plazaPage.value = 1
        plazaHasMore.value = true
        await loadPlazaFeed(false)
        pullRefreshHint.value = ''
    } else {
        pullRefreshHint.value = ''
    }
    touchStartY = 0
    touchMoveY = 0
}

// ── 视频自动播放 ──
function playVideo(el) {
    if (el) el.play().catch(() => {})
}

function pauseVideo(el) {
    if (el) { el.pause(); el.currentTime = 0 }
}

// ── 快速发帖 ──
const showQuickPost = ref(false)
const quickPostText = ref('')
const quickPosting = ref(false)

async function submitQuickPost() {
    const text = quickPostText.value.trim()
    if (!text) return
    quickPosting.value = true
    try {
        await apiClient.post('/moments', { content: text })
        ElMessage.success('已发布')
        showQuickPost.value = false
        quickPostText.value = ''
        // 刷新 feed
        plazaPage.value = 1
        plazaHasMore.value = true
        await loadPlazaFeed(false)
    } catch (e) { ElMessage.error(e.response?.data?.message || '发布失败') }
    finally { quickPosting.value = false }
}

function openFullEditor() {
    showQuickPost.value = false
    quickPostText.value = ''
    // 触发 sidebar 中的"发表"按钮打开完整编辑器
    const btn = document.querySelector('.plaza-panel .sidebar-header .el-button--primary')
    if (btn) {
        btn.click()
    } else {
        // 备用：创建自定义事件通知 PlazaPanel
        window.dispatchEvent(new CustomEvent('plaza-open-create'))
    }
}

function onPlazaCategory(cat, categoryId, keyword, tag, collectionId) {
    plazaCategory.value = cat
    plazaKeyword.value = keyword || ''
    plazaCategoryId.value = categoryId || null
    plazaTag.value = tag || null
    plazaCollectionId.value = collectionId || null
    // 设置 activeConv 触发右侧 Feed 显示
    activeConv.value = { id: '__plaza__', is_plaza: true, plaza_category: cat, plaza_keyword: keyword || '', plaza_category_id: categoryId || null, plaza_tag: tag || null }
    plazaPage.value = 1
    plazaHasMore.value = true
    loadPlazaFeed(false)
}

function viewMyPlazaPost(post) {
    openPlazaDetail(post)
    activeConv.value = { id: '__plaza__', is_plaza: true, plaza_category: 'all' }
}

async function loadPlazaFeed(append = false) {
    if (!append) plazaLoading.value = true
    else plazaLoadingMore.value = true
    try {
        // 收藏夹模式
        if (plazaCollectionId.value) {
            const params = { per_page: 20, page: plazaPage.value }
            if (plazaCollectionId.value !== 'uncategorized') params.collection_id = plazaCollectionId.value
            const res = await apiClient.get('/moments/favorites', { params })
            const data = res.data?.data || []
            const meta = res.data?.meta || {}
            if (append) {
                plazaPosts.value = [...plazaPosts.value, ...data.map(f => ({ ...f.post, _fav_meta: { id: f.id, collection_id: f.collection_id, collection: f.collection, favorited_at: f.created_at } }))]
            } else {
                plazaPosts.value = data.map(f => ({ ...f.post, _fav_meta: { id: f.id, collection_id: f.collection_id, collection: f.collection, favorited_at: f.created_at } }))
            }
            plazaHasMore.value = plazaPage.value < (meta.last_page || 1)
            return
        }

        const params = { tab: plazaCategory.value === 'following' ? 'all' : plazaCategory.value, per_page: 20, page: plazaPage.value }
        if (plazaKeyword.value) params.q = plazaKeyword.value
        if (plazaCategoryId.value) params.category_id = plazaCategoryId.value
        if (plazaTag.value) params.tag = plazaTag.value
        let res
        if (plazaCategory.value === 'following') {
          res = await apiClient.get('/moments/following', { params })
        } else {
          res = await apiClient.get('/moments', { params })
        }
        const data = res.data?.data || []
        const meta = res.data?.meta || {}
        if (append) {
            plazaPosts.value = [...plazaPosts.value, ...data]
        } else {
            plazaPosts.value = data
        }
        plazaHasMore.value = plazaPage.value < (meta.last_page || 1)
    } catch { if (!append) plazaPosts.value = [] }
    finally {
        plazaLoading.value = false
        plazaLoadingMore.value = false
    }
    // 设置自适应列数
    setTimeout(() => {
        const container = document.querySelector('.plaza-feed-masonry')
        if (container) setupMasonryObserver(container)
    }, 50)
}

function onPlazaScroll(e) {
    const el = e.target
    if (!el || plazaLoadingMore.value || !plazaHasMore.value) return
    if (el.scrollHeight - el.scrollTop - el.clientHeight < 100) {
        plazaPage.value++
        loadPlazaFeed(true)
    }
}

// ── 广场互动方法 ──
const plazaShowDetail = ref(null)
const plazaShowDetailView = ref(false)
const plazaComments = ref([])
const plazaCommentsLoading = ref(false)
const plazaCommentText = ref('')
const plazaCommentSubmitting = ref(false)
const plazaReplyTo = ref(null)
const plazaReplyText = ref('')
const plazaReplySubmitting = ref(false)

// ── 广场转发 ──
const plazaForwardShow = ref(false)
const plazaForwardConvId = ref(null)
const plazaForwardPost = ref(null)
const plazaForwardConvs = ref([])
const plazaForwardSearching = ref(false)
const plazaForwarding = ref(false)

function openPlazaForward(post) {
    plazaForwardPost.value = post
    plazaForwardConvId.value = null
    plazaForwardShow.value = true
    loadPlazaForwardConvs()
}

async function loadPlazaForwardConvs() {
    try {
        const res = await apiClient.get('/user-chat/conversations/forwardable')
        plazaForwardConvs.value = res.data?.data || []
    } catch { plazaForwardConvs.value = [] }
}

async function searchPlazaForwardConvs(query) {
    plazaForwardSearching.value = true
    try {
        const res = await apiClient.get('/user-chat/conversations/forwardable', { params: { search: query } })
        plazaForwardConvs.value = res.data?.data || []
    } catch { plazaForwardConvs.value = [] }
    finally { plazaForwardSearching.value = false }
}

async function submitPlazaForward() {
    if (!plazaForwardConvId.value || !plazaForwardPost.value) return
    plazaForwarding.value = true
    try {
        const res = await apiClient.post('/moments/' + plazaForwardPost.value.id + '/forward', {
            target_conversation_id: plazaForwardConvId.value,
        })
        if (res.data?.success) {
            ElMessage.success('已转发')
            plazaForwardShow.value = false
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '转发失败') }
    finally { plazaForwarding.value = false }
}

// ── 广场编辑 ──
const plazaEditShow = ref(false)
const plazaEditContent = ref('')
const plazaEditImages = ref([])
const plazaEditVideo = ref('')
const plazaEditSaving = ref(false)

function openPlazaEdit(post) {
    plazaEditContent.value = post.content || ''
    plazaEditImages.value = post.images ? [...post.images] : []
    plazaEditVideo.value = post.video || ''
    plazaEditShow.value = true
}

function plazaEditUploadImage(options) {
    const formData = new FormData()
    formData.append('file', options.file)
    apiClient.post('/moments/upload', formData)
        .then(res => { const url = res.data?.data?.url; if (url) plazaEditImages.value.push(url) })
        .catch(() => ElMessage.error('图片上传失败'))
}

function plazaEditUploadVideo(options) {
    const formData = new FormData()
    formData.append('file', options.file)
    apiClient.post('/moments/upload-video', formData)
        .then(res => { const url = res.data?.data?.url; if (url) plazaEditVideo.value = url })
        .catch(() => ElMessage.error('视频上传失败'))
}

async function submitPlazaEdit() {
    if (!plazaShowDetail.value?.id) return
    plazaEditSaving.value = true
    try {
        const res = await apiClient.put('/moments/' + plazaShowDetail.value.id, {
            content: plazaEditContent.value.trim(),
            images: plazaEditImages.value,
            video: plazaEditVideo.value || null,
        })
        if (res.data?.success) {
            ElMessage.success('已更新')
            plazaEditShow.value = false
            // 刷新数据
            const updated = res.data?.data
            if (updated) {
                Object.assign(plazaShowDetail.value, updated)
                // 也刷新卡片列表
                const idx = plazaPosts.value.findIndex(p => p.id === plazaShowDetail.value.id)
                if (idx >= 0) Object.assign(plazaPosts.value[idx], updated)
            }
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '更新失败') }
    finally { plazaEditSaving.value = false }
}

// ── 广场举报 ──
const plazaReportShow = ref(false)
const plazaReportPost = ref(null)
const plazaReportReason = ref('')
const plazaReportDesc = ref('')
const plazaReportSubmitting = ref(false)

// ── 收藏夹 ──
const showCollectionPicker = ref(false)
const collectionPickerPost = ref(null)
const collections = ref([])
const newCollectionName = ref('')

async function loadCollections() {
    try {
        const res = await apiClient.get('/moments/favorites/collections')
        const d = res.data?.data || {}
        collections.value = d.collections || []
    } catch { collections.value = [] }
}

async function createAndPick() {
    const name = newCollectionName.value.trim()
    if (!name) { ElMessage.warning('请输入收藏夹名称'); return }
    try {
        const res = await apiClient.post('/moments/favorites/collections', { name })
        const col = res.data?.data
        if (col) {
            collections.value.push({ ...col, favorites_count: 0 })
            newCollectionName.value = ''
            await doFavoriteWithCollection(col.id)
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败') }
}

function openPlazaReport(post) {
    plazaReportPost.value = post
    plazaReportReason.value = ''
    plazaReportDesc.value = ''
    plazaReportShow.value = true
}

async function submitPlazaReport() {
    if (!plazaReportPost.value?.id || !plazaReportReason.value) {
        ElMessage.warning('请选择举报原因')
        return
    }
    plazaReportSubmitting.value = true
    try {
        await apiClient.post('/user-chat/reports', {
            reportable_type: 'forum_post',
            reportable_id: plazaReportPost.value.id,
            reason: plazaReportReason.value,
            description: plazaReportDesc.value || undefined,
        })
        ElMessage.success('举报已提交，感谢您的反馈')
        plazaReportShow.value = false
    } catch (e) {
        if (e.response?.status === 409) {
            ElMessage.warning('您已举报过此内容')
            plazaReportShow.value = false
        } else {
            ElMessage.error(e.response?.data?.message || '举报失败')
        }
    }
    finally { plazaReportSubmitting.value = false }
}

function openPlazaDetail(p) {
    if (!p?.id) return
    window.open('/build/plaza/' + p.id, '_blank')
}

function closePlazaDetail() {
    plazaShowDetailView.value = false
    plazaShowDetail.value = null
}

function filterByTag(slug) {
    closePlazaDetail()
    // 通知 PlazaPanel 更新标签高亮
    // 直接触发按标签筛选
    plazaTag.value = slug
    plazaCategory.value = 'all'
    plazaKeyword.value = ''
    plazaCategoryId.value = null
    activeConv.value = { id: '__plaza__', is_plaza: true, plaza_category: 'all', plaza_tag: slug }
    plazaPage.value = 1
    plazaHasMore.value = true
    loadPlazaFeed(false)
}

function openPlazaStandalone(p) {
    if (!p?.id) return
    window.open('/build/plaza/' + p.id, '_blank')
}

function shareToChat(p) {
    if (!p?.id) return
    const url = window.location.origin + '/build/plaza/' + p.id
    // 尝试使用原生分享API
    if (navigator.share) {
        navigator.share({
            title: '广场帖子',
            url: url,
        }).catch(() => {})
    } else {
        navigator.clipboard.writeText(url).then(() => {
            ElMessage.success('✅ 链接已复制，去聊天窗口粘贴发送')
        }).catch(() => {
            window.open(url, '_blank')
        })
    }
}

async function plazaToggleLike(p) {
    if (!p || !p.id) return
    try {
        const res = await apiClient.post('/moments/' + p.id + '/like')
        p.is_liked = res.data?.data?.liked
        p.likes_count = res.data?.data?.likes_count
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

const followingUsers = ref({}) // { userId: true/false }

async function toggleFollowUser(user) {
    if (!user?.id) return
    const isFollowing = followingUsers.value[user.id]
    try {
        if (isFollowing) {
            await apiClient.post('/moments/users/' + user.id + '/unfollow')
            followingUsers.value[user.id] = false
        } else {
            await apiClient.post('/moments/users/' + user.id + '/follow')
            followingUsers.value[user.id] = true
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

async function plazaToggleFavorite(p) {
    if (!p || !p.id) return
    try {
        // 如果是取消收藏，直接操作
        if (p.is_favorited) {
            const res = await apiClient.post('/moments/' + p.id + '/favorite')
            p.is_favorited = res.data?.data?.favorited
            p.favorites_count = res.data?.data?.favorites_count || 0
            return
        }
        // 收藏 - 弹出收藏夹选择
        showCollectionPicker.value = true
        collectionPickerPost.value = p
        await loadCollections()
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

async function doFavoriteWithCollection(collectionId) {
    const p = collectionPickerPost.value
    if (!p) return
    try {
        const res = await apiClient.post('/moments/' + p.id + '/favorite', { collection_id: collectionId || null })
        p.is_favorited = res.data?.data?.favorited
        p.favorites_count = res.data?.data?.favorites_count || 0
        p.favorite_collection_id = res.data?.data?.collection_id
        ElMessage.success('已收藏')
        showCollectionPicker.value = false
        collectionPickerPost.value = null
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

async function plazaVote(p, optionId) {
    if (!p || !p.id) return
    if (p.poll?.voted) { ElMessage.warning('你已经投过票了'); return }
    try {
        const res = await apiClient.post('/moments/' + p.id + '/vote', { option_id: optionId })
        const data = res.data?.data
        if (data) {
            p.poll.voted = data.voted
            p.poll.total_votes = data.total_votes
            data.options.forEach((opt, i) => {
                if (p.poll.options[i]) {
                    p.poll.options[i].votes = opt.votes
                    p.poll.options[i].percent = opt.percent
                    p.poll.options[i].voted = opt.voted
                }
            })
        }
        ElMessage.success('投票成功')
    } catch (e) {
        if (e.response?.data?.code === 'ALREADY_VOTED') {
            ElMessage.warning('你已经投过票了')
        } else {
            ElMessage.error(e.response?.data?.message || '投票失败')
        }
    }
}

async function plazaDeletePost(p) {
    if (!p || !p.id) return
    try {
        await ElMessageBox.confirm('确定删除此帖子？', '确认', { type: 'warning' })
        await apiClient.delete('/moments/' + p.id)
        ElMessage.success('已删除')
        plazaPosts.value = plazaPosts.value.filter(x => x.id !== p.id)
        plazaShowDetail.value = null
    } catch { /* ignore */ }
}

async function plazaLoadComments(p) {
    if (!p || !p.id) return
    plazaCommentsLoading.value = true
    try {
        const res = await apiClient.get('/moments/' + p.id + '/comments')
        plazaComments.value = res.data?.data || []
    } catch { plazaComments.value = [] }
    finally { plazaCommentsLoading.value = false }
}

async function plazaSubmitComment(p) {
    if (!p || !p.id || !plazaCommentText.value.trim()) return
    plazaCommentSubmitting.value = true
    try {
        const res = await apiClient.post('/moments/' + p.id + '/comment', { content: plazaCommentText.value.trim() })
        const comment = res.data?.data
        if (comment) {
            plazaComments.value.push(comment)
            p.replies_count = (p.replies_count || 0) + 1
            plazaCommentText.value = ''
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '评论失败') }
    finally { plazaCommentSubmitting.value = false }
}

async function plazaSubmitReply(parentComment) {
    if (!parentComment?.id || !plazaReplyText.value.trim()) return
    plazaReplySubmitting.value = true
    try {
        const res = await apiClient.post('/moments/comments/' + parentComment.id + '/reply', { content: plazaReplyText.value.trim() })
        const reply = res.data?.data
        if (reply) {
            if (!parentComment.replies) parentComment.replies = []
            parentComment.replies.push(reply)
            if (plazaShowDetail.value) plazaShowDetail.value.replies_count = (plazaShowDetail.value.replies_count || 0) + 1
            plazaReplyText.value = ''
            plazaReplyTo.value = null
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '回复失败') }
    finally { plazaReplySubmitting.value = false }
}

async function plazaDeleteComment(comment) {
    try {
        await apiClient.delete('/moments/comments/' + comment.id)
        // 从父评论列表中移除
        if (plazaShowDetailComments.value) {
            plazaShowDetailComments.value = plazaShowDetailComments.value.filter(c => c.id !== comment.id)
        }
        if (plazaShowDetail.value) {
            plazaShowDetail.value.replies_count = Math.max(0, (plazaShowDetail.value.replies_count || 0) - 1)
        }
        ElMessage.success('已删除')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '删除失败')
    }
}

// 监听详情弹窗打开时加载评论
watch(plazaShowDetail, (val) => {
    if (val) plazaLoadComments(val)
})

// ── 图片 Lightbox ──
const plazaLightboxImages = ref([])
const plazaLightboxIndex = ref(0)

function openPlazaLightbox(img, index, images) {
    plazaLightboxImages.value = images || [img]
    plazaLightboxIndex.value = index || 0
}

function closePlazaLightbox() {
    plazaLightboxImages.value = []
}

function nextPlazaLightbox() {
    if (plazaLightboxIndex.value < plazaLightboxImages.value.length - 1) {
        plazaLightboxIndex.value++
    }
}

function prevPlazaLightbox() {
    if (plazaLightboxIndex.value > 0) {
        plazaLightboxIndex.value--
    }
}

// 键盘左右键切换
function onPlazaLightboxKeydown(e) {
    if (!plazaLightboxImages.value.length) return
    if (e.key === 'Escape') closePlazaLightbox()
    else if (e.key === 'ArrowRight') nextPlazaLightbox()
    else if (e.key === 'ArrowLeft') prevPlazaLightbox()
}

onMounted(() => {
    document.addEventListener('keydown', onPlazaLightboxKeydown)
})
onUnmounted(() => {
    document.removeEventListener('keydown', onPlazaLightboxKeydown)
})

async function selectChannel(channel) {
    try {
        const res = await apiClient.get('/channels/' + channel.id);
        const data = res.data?.data;
        if (data) {
            currentUserId.value = myId.value;
            myRoleInChannel.value = data.my_role || 'member';
            // 创建一个虚拟会话来显示频道消息
            activeConv.value = {
                id: 'channel_' + channel.id,
                is_channel: true,
                channel_id: channel.id,
                channel_name: channel.name,
                channel_icon: channel.icon,
                is_member: data.is_member,
                my_role: data.my_role,
                member_count: data.member_count,
                is_muted: data.is_muted ?? false,
            };
            loadChannelMessages(channel.id);
            loadChannelPinnedMessages();
        }
    } catch (e) { ElMessage.error('加载频道失败') }
}
const channelMessages = ref([]);
const loadingChannelMessages = ref(false);
const channelMsgAreaRef = ref(null);

// ── 频道设置/编辑/成员/置顶 ──
const showChannelEdit = ref(false);
const channelEditForm = ref({ name: '', description: '', icon: '', avatar: '', category_id: null });
const channelEditing = ref(false);
const showChannelMembers = ref(false);
const channelMembersData = ref([]);
const loadingChannelMembersData = ref(false);
const myRoleInChannel = ref('member');
const showPinnedMessages = ref(false);
const channelPinnedMessages = ref([]);
const channelTransferUserId = ref(null);
const channelTransferring = ref(false);
const currentUserId = ref(null);

function handleChannelSetting(cmd) {
    if (cmd === 'edit') { openChannelEdit(); }
    else if (cmd === 'members') { showChannelMembers.value = true; }
    else if (cmd === 'pinned') { showPinnedMessages.value = true; loadChannelPinnedMessages(); }
    else if (cmd === 'mute') { toggleChannelMute(); }
    else if (cmd === 'unmute') { toggleChannelMute(); }
    else if (cmd === 'leave') { leaveChannel(activeConv.value?.channel_id); }
}
function openChannelEdit() {
    const conv = activeConv.value;
    if (!conv) return;
    channelEditForm.value = { name: conv.channel_name || '', description: '', icon: '', avatar: '', category_id: null };
    // Load current channel data
    apiClient.get('/channels/' + conv.channel_id).then(res => {
        const ch = res.data?.data?.channel;
        if (ch) {
            channelEditForm.value = { name: ch.name, description: ch.description || '', icon: ch.icon || '#', avatar: ch.avatar || '', category_id: ch.category_id || null };
        }
    }).catch(() => {});
    showChannelEdit.value = true;
}
async function submitChannelEdit() {
    const conv = activeConv.value;
    if (!conv || !channelEditForm.value.name.trim()) { ElMessage.warning('请输入频道名称'); return; }
    channelEditing.value = true;
    try {
        // 如果有新上传的头像，先上传获取 URL
        if (channelEditPendingFile.value) {
            try {
                const fd = new FormData();
                fd.append('avatar', channelEditPendingFile.value);
                fd.append('channel_id', conv.channel_id);
                const uploadRes = await apiClient.post('/channels/upload-avatar', fd);
                channelEditForm.value.avatar = uploadRes.data?.data?.avatar || '';
            } catch (e) {
                ElMessage.warning('头像上传失败，其他信息已保存');
            }
            channelEditPendingFile.value = null;
        }
        await apiClient.put('/channels/' + conv.channel_id, { ...channelEditForm.value });
        ElMessage.success('频道已更新');
        showChannelEdit.value = false;
        conv.channel_name = channelEditForm.value.name;
        // Refresh channel list
        loadMyChannels();
    } catch (e) { ElMessage.error(e.response?.data?.message || '更新失败') }
    finally { channelEditing.value = false }
}

// ── 频道编辑头像上传 ──
const channelEditPendingFile = ref(null);
function uploadChannelEditAvatar(options) {
    channelEditPendingFile.value = options.file;
    // 本地预览
    const reader = new FileReader();
    reader.onload = (e) => { channelEditForm.value.avatar = e.target.result; };
    reader.readAsDataURL(options.file);
    ElMessage.success('已选择头像，保存时自动上传');
}
async function loadChannelMembers() {
    const conv = activeConv.value;
    if (!conv) return;
    loadingChannelMembersData.value = true;
    try {
        const res = await apiClient.get('/channels/' + conv.channel_id + '/members');
        channelMembersData.value = res.data?.data || [];
    } catch { channelMembersData.value = [] }
    finally { loadingChannelMembersData.value = false }
}
async function setChannelAdmin(member) {
    try {
        await apiClient.put('/channels/' + activeConv.value.channel_id + '/members/' + member.id + '/role', { role: 'admin' });
        ElMessage.success('已设为管理员');
        await loadChannelMembers();
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}
async function removeChannelAdmin(member) {
    try {
        await apiClient.put('/channels/' + activeConv.value.channel_id + '/members/' + member.id + '/role', { role: 'member' });
        ElMessage.success('已取消管理员');
        await loadChannelMembers();
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}
async function kickChannelMember(member) {
    try {
        await ElMessageBox.confirm('确定将 ' + (member.user?.name || '该成员') + ' 移出圈子？', '确认', { type: 'warning', confirmButtonText: '移出', cancelButtonText: '取消' })
        await apiClient.delete('/channels/' + activeConv.value.channel_id + '/members/' + member.id);
        ElMessage.success('已移出');
        await loadChannelMembers();
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败');
    }
}
async function confirmChannelTransfer() {
    if (!channelTransferUserId.value) { ElMessage.warning('请选择新创建者'); return; }
    channelTransferring.value = true;
    try {
        await apiClient.post('/channels/' + activeConv.value.channel_id + '/transfer', { user_id: channelTransferUserId.value });
        ElMessage.success('频道已转让');
        showChannelMembers.value = false;
        activeConv.value = null;
        loadMyChannels();
    } catch (e) { ElMessage.error(e.response?.data?.message || '转让失败') }
    finally { channelTransferring.value = false }
}
async function pinChannelMessage(msg) {
    const conv = activeConv.value;
    if (!conv) return;
    try {
        await apiClient.post('/channels/' + conv.channel_id + '/messages/' + msg.id + '/pin');
        ElMessage.success('消息已置顶');
        msg.is_pinned = true;
        await loadChannelPinnedMessages();
    } catch (e) { ElMessage.error(e.response?.data?.message || '置顶失败') }
}
async function unpinChannelMessage(msg) {
    const conv = activeConv.value;
    if (!conv) return;
    try {
        await apiClient.post('/channels/' + conv.channel_id + '/messages/' + msg.id + '/unpin');
        ElMessage.success('已取消置顶');
        msg.is_pinned = false;
        await loadChannelPinnedMessages();
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}
async function loadChannelPinnedMessages() {
    const conv = activeConv.value;
    if (!conv) return;
    try {
        const res = await apiClient.get('/channels/' + conv.channel_id + '/pinned-messages');
        channelPinnedMessages.value = res.data?.data || [];
    } catch { channelPinnedMessages.value = [] }
}
async function toggleChannelMute() {
    const conv = activeConv.value;
    if (!conv) return;
    try {
        const res = await apiClient.post('/channels/' + conv.channel_id + '/toggle-mute');
        conv.is_muted = res.data?.data?.is_muted;
        ElMessage.success(conv.is_muted ? '已开启免打扰' : '已关闭免打扰');
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

// ── 频道消息时间分组 ──
const groupedChannelMessages = computed(() => {
    const msgs = channelMessages.value;
    if (!msgs.length) return [];
    const groups = [];
    const today = new Date();
    const todayStr = today.toDateString();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    const yesterdayStr = yesterday.toDateString();

    let currentLabel = '';
    let currentGroup = null;

    for (const msg of msgs) {
        const d = new Date(msg.created_at);
        const dateStr = d.toDateString();
        let label;
        if (dateStr === todayStr) label = '今天';
        else if (dateStr === yesterdayStr) label = '昨天';
        else label = d.getFullYear() + '年' + (d.getMonth() + 1) + '月' + d.getDate() + '日';

        if (label !== currentLabel) {
            currentLabel = label;
            currentGroup = { label, messages: [] };
            groups.push(currentGroup);
        }
        currentGroup.messages.push(msg);
    }
    return groups;
});

// ── 频道消息操作 ──
const channelPendingFiles = ref([]);
async function uploadChannelFile(options) {
    const conv = activeConv.value;
    if (!conv) return;
    channelSending.value = true;
    try {
        const formData = new FormData();
        formData.append('file', options.file);
        formData.append('message_type', options.file.type.startsWith('image/') ? 'image' : options.file.type.startsWith('video/') ? 'video' : 'file');
        await apiClient.post('/channels/' + conv.channel_id + '/messages', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        ElMessage.success('文件已发送');
        await loadChannelMessages(conv.channel_id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '上传失败');
    } finally {
        channelSending.value = false;
    }
}

async function deleteChannelMessage(msg) {
    const conv = activeConv.value;
    if (!conv) return;
    try {
        await ElMessageBox.confirm('确定删除此消息？', '确认删除', { type: 'warning' });
        await apiClient.delete('/channels/' + conv.channel_id + '/messages/' + msg.id);
        ElMessage.success('消息已删除');
        channelMessages.value = channelMessages.value.filter(m => m.id !== msg.id);
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '删除失败');
    }
}

async function recallChannelMessage(msg) {
    const conv = activeConv.value;
    if (!conv) return;
    try {
        await apiClient.post('/channels/' + conv.channel_id + '/messages/' + msg.id + '/recall');
        ElMessage.success('消息已撤回');
        msg.is_recalled = true;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '撤回失败');
    }
}

// ── 频道消息：引用 ──
const channelReplyToMsg = ref(null);
const channelSearchActive = ref(false);
const channelSearchQuery = ref('');
const channelSearchResults = ref([]);
const channelSearchLoading = ref(false);

async function searchChannelMessages() {
    const q = channelSearchQuery.value.trim();
    if (!q) return;
    const channelId = activeConv.value?.channel_id;
    if (!channelId) return;
    channelSearchLoading.value = true;
    try {
        const r = await apiClient.get('/channels/' + channelId + '/messages/search', { params: { q } });
        channelSearchResults.value = r.data?.data || [];
    } catch { ElMessage.error('搜索失败') }
    finally { channelSearchLoading.value = false }
}
function scrollToChannelMsg(msg) {
    // 关闭搜索结果，滚动到消息所在位置（简化：重新加载频道消息）
    channelSearchActive.value = false;
    channelSearchResults.value = [];
    channelSearchQuery.value = '';
    ElMessage.info('搜索结果已关闭，消息ID: ' + msg.id);
}

// ── 频道消息：收藏 ──
function channelToggleFavorite(msg) {
    if (msg.is_favorited) {
        msg.is_favorited = false;
        const favs = JSON.parse(localStorage.getItem('ch_fav') || '{}');
        delete favs[msg.id];
        localStorage.setItem('ch_fav', JSON.stringify(favs));
        ElMessage.success('已取消收藏');
    } else {
        msg.is_favorited = true;
        const favs = JSON.parse(localStorage.getItem('ch_fav') || '{}');
        favs[msg.id] = { content: msg.content?.substring(0, 100), user: msg.user?.name, time: msg.created_at };
        localStorage.setItem('ch_fav', JSON.stringify(favs));
        ElMessage.success('已收藏');
    }
}

// ── 频道消息：复制 ──
function copyChannelMsg(msg) {
    const text = msg.content || '';
    if (!text) { ElMessage.warning('无可复制的内容'); return; }
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制');
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        ElMessage.success('已复制');
    });
}

// ── 频道消息：转发 ──
function channelForwardMsg(msg) {
    forwardMsgs.value = [{
        id: 'ch_' + msg.id,
        content: msg.content,
        message_type: msg.message_type,
        channel_id: activeConv.value?.channel_id,
    }];
    forwardConvId.value = null;
    showForward.value = true;
    loadForwardConvs();
}

// ── 频道消息：举报 ──
function channelReportMsg(msg) {
    reportTarget.value = msg;
    reportReason.value = 'spam';
    reportDescription.value = '';
    showReportDialog.value = true;
}

function openChannelMsgContextMenu(e, msg) {
    const conv = activeConv.value;
    if (!conv) return;
    const items = [];
    items.push({ label: '📋 复制', action: () => copyChannelMsg(msg) });
    items.push({ label: '💬 回复', action: () => { channelReplyToMsg.value = msg; } });
    items.push({ label: msg.is_favorited ? '⭐ 取消收藏' : '☆ 收藏', action: () => channelToggleFavorite(msg) });
    items.push({ label: '📤 转发', action: () => channelForwardMsg(msg) });
    if (msg.user_id === myId.value) {
        items.push({ label: '✏️ 撤回', action: () => recallChannelMessage(msg) });
    } else {
        items.push({ label: '🚫 举报', action: () => channelReportMsg(msg) });
    }
    items.push({ label: '🗑️ 删除', action: () => deleteChannelMessage(msg) });
    if (myRoleInChannel.value !== 'member') {
        if (msg.is_pinned) {
            items.push({ label: '📌 取消置顶', action: () => unpinChannelMessage(msg) });
        } else {
            items.push({ label: '📌 置顶', action: () => pinChannelMessage(msg) });
        }
    }
    if (!items.length) return;

    // 显示简易右键菜单
    const menu = document.createElement('div');
    menu.className = 'channel-msg-context-menu';
    menu.style.cssText = 'position:fixed;left:' + e.clientX + 'px;top:' + e.clientY + 'px;z-index:9999;background:#fff;border:1px solid #e4e7ed;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);padding:4px 0;min-width:120px';
    items.forEach(item => {
        const el = document.createElement('div');
        el.textContent = item.label;
        el.style.cssText = 'padding:6px 14px;cursor:pointer;font-size:13px;transition:background 0.15s';
        el.addEventListener('mouseenter', () => el.style.background = '#f0f0f0');
        el.addEventListener('mouseleave', () => el.style.background = '');
        el.addEventListener('click', () => { item.action(); menu.remove(); });
        menu.appendChild(el);
    });
    document.body.appendChild(menu);
    setTimeout(() => {
        document.addEventListener('click', () => menu.remove(), { once: true });
    }, 10);
}

// ── 频道语音消息 ──
const channelIsRecording = ref(false);
const channelRecordingDuration = ref(0);
const channelVoicePlayingId = ref(null);
let channelMediaRecorder = null;
let channelAudioChunks = [];
let channelRecordingTimer = null;
let channelAudioPlayer = null;

async function toggleChannelVoiceRecord() {
    if (channelIsRecording.value) {
        // 停止录音
        if (channelMediaRecorder && channelMediaRecorder.state !== 'inactive') {
            channelMediaRecorder.stop();
        }
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        ElMessage.warning('您的浏览器不支持录音');
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') ? 'audio/webm;codecs=opus' : 'audio/webm';
        channelMediaRecorder = new MediaRecorder(stream, { mimeType });
        channelAudioChunks = [];
        channelRecordingDuration.value = 0;
        channelIsRecording.value = true;

        channelMediaRecorder.ondataavailable = e => {
            if (e.data.size > 0) channelAudioChunks.push(e.data);
        };
        channelMediaRecorder.onstop = async () => {
            channelIsRecording.value = false;
            clearInterval(channelRecordingTimer);
            stream.getTracks().forEach(t => t.stop());
            if (channelAudioChunks.length === 0) return;
            const blob = new Blob(channelAudioChunks, { type: 'audio/webm' });
            if (blob.size < 100) return;
            await sendChannelVoiceMessage(blob);
        };
        channelMediaRecorder.start();
        channelRecordingTimer = setInterval(() => {
            channelRecordingDuration.value++;
        }, 1000);
    } catch {
        ElMessage.error('无法访问麦克风');
        channelIsRecording.value = false;
    }
}

// ── 频道视频录制 ──
const channelIsVideoRecording = ref(false);
const channelVideoRecordingDuration = ref(0);
const channelVideoPreviewUrl = ref('');
let channelVideoStream = null;
let channelVideoRecorder = null;
let channelVideoChunks = [];
let channelVideoRecordingTimer = null;

async function toggleChannelVideoRecord() {
    if (channelIsVideoRecording.value) {
        // 停止录像
        if (channelVideoRecorder && channelVideoRecorder.state !== 'inactive') {
            channelVideoRecorder.stop();
        }
        return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
        ElMessage.warning('您的浏览器不支持录像');
        return;
    }
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        channelVideoStream = stream;
        const mimeType = MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus') ? 'video/webm;codecs=vp9,opus'
            : MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus') ? 'video/webm;codecs=vp8,opus'
            : 'video/webm';
        channelVideoRecorder = new MediaRecorder(stream, { mimeType });
        channelVideoChunks = [];
        channelVideoRecordingDuration.value = 0;
        channelIsVideoRecording.value = true;

        channelVideoRecorder.ondataavailable = e => {
            if (e.data.size > 0) channelVideoChunks.push(e.data);
        };
        channelVideoRecorder.onstop = async () => {
            channelIsVideoRecording.value = false;
            clearInterval(channelVideoRecordingTimer);
            if (channelVideoStream) {
                channelVideoStream.getTracks().forEach(t => t.stop());
                channelVideoStream = null;
            }
            if (channelVideoChunks.length === 0) return;
            const blob = new Blob(channelVideoChunks, { type: 'video/webm' });
            if (blob.size < 1000) return;
            await sendChannelVideoMessage(blob);
        };
        channelVideoRecorder.start();
        channelVideoRecordingTimer = setInterval(() => {
            channelVideoRecordingDuration.value++;
        }, 1000);
    } catch {
        ElMessage.error('无法访问摄像头');
        channelIsVideoRecording.value = false;
    }
}

async function sendChannelVideoMessage(blob) {
    const conv = activeConv.value;
    if (!conv) return;
    channelSending.value = true;
    try {
        const formData = new FormData();
        formData.append('file', blob, 'video.webm');
        formData.append('message_type', 'video');
        // Upload via generic upload
        const uploadRes = await apiClient.post('/im/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const url = uploadRes.data?.data?.url;
        if (!url) { ElMessage.error('上传失败'); return; }

        await apiClient.post('/channels/' + conv.channel_id + '/messages', {
            content: url,
            message_type: 'video',
            attachments: [{ duration: channelVideoRecordingDuration.value, url }],
        });
        ElMessage.success('视频已发送');
        await loadChannelMessages(conv.channel_id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败');
    } finally {
        channelSending.value = false;
    }
}

async function sendChannelVoiceMessage(blob) {
    const conv = activeConv.value;
    if (!conv) return;
    channelSending.value = true;
    try {
        const formData = new FormData();
        formData.append('file', blob, 'voice.webm');
        formData.append('message_type', 'voice');
        // Upload via a generic file upload then create message
        const uploadRes = await apiClient.post('/im/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        const url = uploadRes.data?.data?.url;
        if (!url) { ElMessage.error('上传失败'); return; }

        await apiClient.post('/channels/' + conv.channel_id + '/messages', {
            content: url,
            message_type: 'voice',
            attachments: [{ duration: channelRecordingDuration.value, url }],
        });
        ElMessage.success('语音已发送');
        await loadChannelMessages(conv.channel_id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败');
    } finally {
        channelSending.value = false;
    }
}

function playChannelVoice(msg) {
    const url = msg.content || msg.attachments?.[0]?.url;
    if (!url) return;
    if (channelVoicePlayingId.value === msg.id && channelAudioPlayer) {
        channelAudioPlayer.pause();
        channelAudioPlayer = null;
        channelVoicePlayingId.value = null;
        return;
    }
    if (channelAudioPlayer) {
        channelAudioPlayer.pause();
        channelAudioPlayer = null;
    }
    channelVoicePlayingId.value = msg.id;
    channelAudioPlayer = new Audio(url);
    channelAudioPlayer.onended = () => {
        channelVoicePlayingId.value = null;
        channelAudioPlayer = null;
    };
    channelAudioPlayer.onerror = () => {
        channelVoicePlayingId.value = null;
        channelAudioPlayer = null;
        ElMessage.warning('播放失败');
    };
    channelAudioPlayer.play().catch(() => {
        channelVoicePlayingId.value = null;
    });
}

// ── 辅助方法 ──
function formatFileSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + 'B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + 'KB';
    return (bytes / 1048576).toFixed(1) + 'MB';
}
function showUserProfile(user) {
    // 预留：显示用户资料
}

// ── OA 二维码 ──
async function loadOaQrCode() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    loadingOaQr.value = true
    try {
        const r = await apiClient.get('/official-accounts/' + accountId + '/qr-code')
        oaQrData.value = r.data?.data || null
    } catch { oaQrData.value = null }
    finally { loadingOaQr.value = false }
}
function downloadOaQrCode() {
    if (oaQrData.value?.download_url) {
        const a = document.createElement('a')
        a.href = oaQrData.value.download_url
        a.download = oaQrData.value.name + '_qrcode.png'
        a.target = '_blank'
        a.click()
    }
}
function copyOaFollowUrl() {
    if (oaQrData.value?.follow_url) {
        navigator.clipboard.writeText(oaQrData.value.follow_url).then(() => ElMessage.success('链接已复制'))
    }
}

// ── OA 关注者消息 ──
async function loadOaConversations() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    try {
        const r = await apiClient.get('/official-accounts/' + accountId + '/messages/conversations')
        oaConversations.value = r.data?.data || []
        oaUnreadCount.value = oaConversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0)
    } catch { oaConversations.value = [] }
}
async function selectOaConversation(conv) {
    oaActiveConversation.value = conv.user_id
    oaActiveMessages.value = []
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    try {
        const r = await apiClient.get('/official-accounts/' + accountId + '/messages/' + conv.user_id)
        oaActiveMessages.value = r.data?.data || []
        conv.unread_count = 0
        oaUnreadCount.value = Math.max(0, oaUnreadCount.value - (conv.unread_count || 0))
    } catch { oaActiveMessages.value = [] }
}
async function sendOaReply() {
    if (!oaReplyText.value.trim()) return
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId || !oaActiveConversation.value) return
    sendingOaReply.value = true
    try {
        const r = await apiClient.post('/official-accounts/' + accountId + '/messages/' + oaActiveConversation.value + '/reply', { content: oaReplyText.value })
        const msg = r.data?.data
        if (msg) {
            oaActiveMessages.value.push(msg)
            oaReplyText.value = ''
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败')
    } finally { sendingOaReply.value = false }
}
async function loadOaUnreadCount() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    try {
        const r = await apiClient.get('/official-accounts/' + accountId + '/messages/unread-count')
        oaUnreadCount.value = r.data?.data?.count || 0
    } catch { /* ignore */ }
}

// ── 频道输入表情 ──
function insertChannelEmoji(emoji) {
    channelInput.value += emoji;
}

// ── 客服工作台 ──
const showAgentCustomerInfo = ref(true);
const agentMessages = ref([]);
const agentInput = ref('');
const agentSending = ref(false);
const agentMsgAreaRef = ref(null);
const agentConvTags = ref([]);
const agentQuickReplies = ref([]);
const showAgentReplyManager = ref(false);

async function loadAgentQuickReplies() {
    try {
        const res = await apiClient.get('/im/canned-replies');
        const items = res.data?.data || [];
        // Add dividers between categories
        let lastCat = '';
        agentQuickReplies.value = items.map(r => {
            const divider = r.category && r.category !== lastCat;
            lastCat = r.category || '';
            return { ...r, _divider: divider };
        });
    } catch { agentQuickReplies.value = []; }
}

// ── 用户聊天快捷回复管理 ──
const chatReplyMgrSearch = ref('');
const showChatReplyEditor = ref(false);
const chatEditingReply = ref(null);
const chatSavingReply = ref(false);
const chatReplyForm = reactive({
    category: '', title: '', content: '', shortcuts: [], is_shared: true,
});

const chatFilteredReplies = computed(() => {
    const q = chatReplyMgrSearch.value.toLowerCase().trim();
    if (!q) return agentQuickReplies.value;
    return agentQuickReplies.value.filter(r =>
        (r.title || '').toLowerCase().includes(q) ||
        (r.content || '').toLowerCase().includes(q) ||
        (r.category || '').toLowerCase().includes(q)
    );
});

function openChatNewReply() {
    chatEditingReply.value = null;
    chatReplyForm.category = '';
    chatReplyForm.title = '';
    chatReplyForm.content = '';
    chatReplyForm.shortcuts = [];
    chatReplyForm.is_shared = true;
    showChatReplyEditor.value = true;
}

function openChatEditReply(reply) {
    chatEditingReply.value = reply;
    chatReplyForm.category = reply.category || '';
    chatReplyForm.title = reply.title || '';
    chatReplyForm.content = reply.content || '';
    chatReplyForm.shortcuts = Array.isArray(reply.shortcuts) ? [...reply.shortcuts] : [];
    chatReplyForm.is_shared = reply.is_shared !== false;
    showChatReplyEditor.value = true;
}

async function saveChatReply() {
    if (!chatReplyForm.title.trim() || !chatReplyForm.content.trim()) {
        ElMessage.warning('标题和内容不能为空');
        return;
    }
    chatSavingReply.value = true;
    try {
        const payload = {
            category: chatReplyForm.category || undefined,
            title: chatReplyForm.title.trim(),
            content: chatReplyForm.content.trim(),
            shortcuts: chatReplyForm.shortcuts.length ? chatReplyForm.shortcuts : undefined,
            is_shared: chatReplyForm.is_shared,
        };
        if (chatEditingReply.value) {
            await apiClient.put('/im/canned-replies/' + chatEditingReply.value.id, payload);
            ElMessage.success('已更新');
        } else {
            await apiClient.post('/im/canned-replies', payload);
            ElMessage.success('已创建');
        }
        showChatReplyEditor.value = false;
        await loadAgentQuickReplies();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        chatSavingReply.value = false;
    }
}

async function deleteChatReply(reply) {
    try {
        await ElMessageBox.confirm('确定删除「' + reply.title + '」？', '确认删除');
        await apiClient.delete('/im/canned-replies/' + reply.id);
        ElMessage.success('已删除');
        await loadAgentQuickReplies();
    } catch { /* cancelled or error */ }
}

function selectAgentConversation(conv) {
    activeConv.value = conv;
    showAgentCustomerInfo.value = true;
    agentConvTags.value = conv.customer?.tags || [];
    loadAgentMessages(conv.handoff_id);
    loadAgentQuickReplies();
}

async function loadAgentMessages(handoffId) {
    if (!handoffId) { agentMessages.value = []; return; }
    try {
        const res = await apiClient.get('/handoff/' + handoffId + '/messages');
        agentMessages.value = res.data?.data || [];
        scrollAgentMessages();
    } catch { agentMessages.value = []; }
}

async function sendAgentMessage() {
    const conv = activeConv.value;
    if (!conv?.handoff_id || !agentInput.value.trim()) return;
    agentSending.value = true;
    try {
        await apiClient.post('/handoff/' + conv.handoff_id + '/messages', { content: agentInput.value.trim() });
        agentInput.value = '';
        await loadAgentMessages(conv.handoff_id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败');
    } finally {
        agentSending.value = false;
    }
}

function insertQuickReply(text) {
    if (text === '__manage__') {
        showAgentReplyManager.value = true;
        loadAgentQuickReplies();
        return;
    }
    agentInput.value = text;
}

function quickTag(tag) {
    if (!agentConvTags.value.includes(tag)) {
        agentConvTags.value.push(tag);
    }
    ElMessage.success('已标记: ' + tag);
}

function scrollAgentMessages() {
    nextTick(() => {
        const el = agentMsgAreaRef.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
}

async function loadChannelMessages(channelId) {
    loadingChannelMessages.value = true;
    try {
        const r = await apiClient.get('/channels/' + channelId + '/messages');
        const data = r.data?.data || [];
        // API 返回降序(最新在前)，反转后升序显示
        channelMessages.value = Array.isArray(data) ? data.reverse() : [];
    } catch { channelMessages.value = [] }
    finally { loadingChannelMessages.value = false }
}
async function sendChannelMessage() {
    const conv = activeConv.value;
    if (!conv || !conv.is_channel || (!channelInput.value.trim() && !channelPendingFiles.value.length)) return;
    channelSending.value = true;
    try {
        const payload = { content: channelInput.value || '' };
        if (channelReplyToMsg.value) {
            payload.reply_to_id = channelReplyToMsg.value.id;
        }
        if (channelPendingFiles.value.length) {
            // 有挂起的文件，通过 FormData 发送
            const formData = new FormData();
            formData.append('content', channelInput.value || '');
            if (channelReplyToMsg.value) formData.append('reply_to_id', channelReplyToMsg.value.id);
            channelPendingFiles.value.forEach((f, i) => {
                formData.append('attachments[' + i + '][url]', f.url);
                formData.append('attachments[' + i + '][name]', f.name);
                formData.append('attachments[' + i + '][size]', f.size);
            });
            await apiClient.post('/channels/' + conv.channel_id + '/messages', formData);
            channelPendingFiles.value = [];
        } else {
            await apiClient.post('/channels/' + conv.channel_id + '/messages', payload);
        }
        channelInput.value = '';
        channelReplyToMsg.value = null;
        await loadChannelMessages(conv.channel_id);
    } catch (e) { ElMessage.error('发送失败') }
    finally { channelSending.value = false }
}
const channelInput = ref('');
const channelSending = ref(false);

async function joinChannel(channelId) {
    try {
        await apiClient.post('/channels/' + channelId + '/join');
        ElMessage.success('已加入频道');
        // 刷新频道状态
        const res = await apiClient.get('/channels/' + channelId);
        const data = res.data?.data;
        if (data && activeConv.value?.channel_id === channelId) {
            activeConv.value.is_member = true;
            activeConv.value.my_role = data.my_role;
            activeConv.value.member_count = data.member_count;
        }
        loadMyChannels();
    } catch (e) { ElMessage.error(e.response?.data?.message || '加入失败') }
}
async function leaveChannel(channelId) {
    try {
        await apiClient.post('/channels/' + channelId + '/leave');
        ElMessage.success('已离开频道');
        activeConv.value = null;
        loadMyChannels();
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

// ── 公众号方法 ──
function openOaSettings() {
    if (selectedOaAccount.value && oaPanelRef.value?.openEditDialog) {
        oaPanelRef.value.openEditDialog(selectedOaAccount.value);
    }
}
async function selectOaAccount(acc) {
    selectedOaAccount.value = acc;
    selectedOaTag.value = '';
    activeConv.value = {
        id: 'oa_' + acc.id,
        is_oa: true,
        oa_account_id: acc.id,
        oa_name: acc.name,
        oa_avatar: acc.avatar,
        oa_description: acc.description,
    };
    loadOaArticles(acc.id);
    loadOaDashboard(acc.id);
    loadOaUnreadCount();
    loadRecommendations();
    loadOaCollections();
}
async function loadOaArticles(accountId, reset = true) {
    if (reset) { oaArticles.value = []; oaArticlePage.value = 1; oaArticleTotal.value = 0; }
    loadingOaArticles.value = true;
    try {
        const params = { per_page: 20, page: oaArticlePage.value };
        if (oaArticleSort.value === 'hot') params.sort = 'hot';
        const r = await apiClient.get('/official-accounts/' + accountId + '/articles', { params });
        const data = r.data?.data || [];
        const meta = r.data?.meta || {};
        const items = Array.isArray(data) ? data : (data.data || []);
        if (reset) oaArticles.value = items;
        else oaArticles.value = [...oaArticles.value, ...items];
        oaArticleTotal.value = meta.total || items.length;
    } catch { if (reset) oaArticles.value = [] }
    finally {
        loadingOaArticles.value = false;
        loadingMoreOaArticles.value = false;
        // 设置自适应列数
        if (reset) {
            setTimeout(() => {
                const container = document.querySelector('.oa-article-masonry')
                if (container) setupMasonryObserver(container)
            }, 50)
        }
    }
}
async function loadMoreOaArticles() {
    if (!activeConv.value?.oa_account_id || loadingMoreOaArticles.value) return;
    oaArticlePage.value++;
    loadingMoreOaArticles.value = true;
    await loadOaArticles(activeConv.value.oa_account_id, false);
}

async function loadOaCollections() {
    const accountId = activeConv.value?.oa_account_id
    if (!accountId) return
    try {
        const res = await apiClient.get('/official-accounts/' + accountId + '/collections')
        oaCollections.value = res.data?.data || []
    } catch { oaCollections.value = [] }
}

function onOaScroll(e) {
    const el = e.target
    if (!el || loadingMoreOaArticles.value || oaArticles.value.length >= oaArticleTotal.value) return
    if (el.scrollHeight - el.scrollTop - el.clientHeight < 100) {
        loadMoreOaArticles()
    }
}

async function loadRecommendations() {
    try {
        const res = await apiClient.get('/official-accounts/recommendations')
        oaRecommendations.value = res.data?.data || []
    } catch { oaRecommendations.value = [] }
}

function openOaArticleRecommend(rec) {
    openOaArticleDetail(rec)
}

// ── 阅读清单 ──
async function toggleReadingList(art) {
    if (!art) return
    try {
        if (art.in_reading_list) {
            await apiClient.delete('/official-accounts/reading-list/' + art.id)
            art.in_reading_list = false
            ElMessage.success('已从阅读清单移除')
        } else {
            await apiClient.post('/official-accounts/reading-list', { article_id: art.id })
            art.in_reading_list = true
            ElMessage.success('已添加到阅读清单')
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

const filteredOaArticles = computed(() => {
    let items = oaArticles.value
    // 合集筛选
    if (selectedOaCollection.value) {
        items = items.filter(a => a.collection_id === selectedOaCollection.value)
    }
    // 标签筛选
    if (selectedOaTag.value) {
        items = items.filter(a => a.tags?.includes(selectedOaTag.value))
    }
    // 全文搜索
    if (oaArticleSearch.value) {
        const q = oaArticleSearch.value.toLowerCase()
        items = items.filter(a => {
            if (a.title?.toLowerCase().includes(q)) return true
            if (a.summary?.toLowerCase().includes(q)) return true
            if (a.content?.toLowerCase().includes(q)) return true
            if (a.author?.name?.toLowerCase().includes(q)) return true
            if (a.tags?.some(t => t.toLowerCase().includes(q))) return true
            return false
        })
    }
    return items
})

const oaTags = computed(() => {
    const tagSet = new Set()
    oaArticles.value.forEach(a => {
        if (a.tags?.length) a.tags.forEach(t => tagSet.add(t))
    })
    return [...tagSet].sort()
})

async function loadOaArticleDetail(articleId) {
    try {
        const r = await apiClient.get('/official-accounts/articles/' + articleId);
        return r.data?.data;
    } catch { return null }
}
async function toggleOaArticleLike(articleId) {
    try {
        const r = await apiClient.post('/official-accounts/articles/' + articleId + '/like');
        return r.data?.data;
    } catch (e) { ElMessage.error('操作失败'); return null }
}
async function shareOaArticle(articleId) {
    try {
        await apiClient.post('/official-accounts/articles/' + articleId + '/share');
        ElMessage.success('分享已记录');
    } catch { /* ignore */ }
}

// ── OA 运营数据 ──
async function loadOaDashboard(accountId) {
    loadingOaDashboard.value = true;
    try {
        const r = await apiClient.get('/official-accounts/' + accountId + '/dashboard');
        oaDashboard.value = r.data?.data || null;
    } catch { oaDashboard.value = null }
    finally { loadingOaDashboard.value = false }
}
async function loadOaComments() {
    if (!activeConv.value?.oa_account_id) return;
    try {
        const r = await apiClient.get('/official-accounts/' + activeConv.value.oa_account_id + '/comments');
        oaComments.value = r.data?.data || [];
        oaCommentCount.value = oaComments.value.length;
        showOaCommentManager.value = true;
        oaCommentSearch.value = '';
    } catch { ElMessage.error('加载评论失败') }
}

// ── OA 粉丝管理 ──
async function loadOaFollowers() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    loadingOaFollowers.value = true
    try {
        const params = { per_page: 50 }
        if (oaFollowerSearch.value) params.q = oaFollowerSearch.value
        const r = await apiClient.get('/official-accounts/' + accountId + '/followers', { params })
        oaFollowers.value = r.data?.data || []
    } catch { oaFollowers.value = [] }
    finally { loadingOaFollowers.value = false }
}

// ── OA 自动回复 ──
async function loadOaAutoReplies() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    try {
        const params = {}
        if (oaAutoReplyTab.value && oaAutoReplyTab.value !== 'all') params.type = oaAutoReplyTab.value
        const r = await apiClient.get('/official-accounts/' + accountId + '/auto-replies', { params })
        oaAutoReplies.value = r.data?.data || []
    } catch { oaAutoReplies.value = [] }
}
async function openNewAutoReply(type) {
    editingAutoReply.value = null
    oaAutoReplyForm.value = {
        type: type,
        keyword: '',
        match_type: 0,
        content: '',
        content_type: 'text',
        media_url: '',
        is_active: true,
        sort_order: 0,
    }
    showOaAutoReplyEditor.value = true
}
async function editAutoReply(reply) {
    editingAutoReply.value = reply
    oaAutoReplyForm.value = {
        type: reply.type,
        keyword: reply.keyword || '',
        match_type: reply.match_type ?? 0,
        content: reply.content || '',
        content_type: reply.content_type || 'text',
        media_url: reply.media_url || '',
        is_active: reply.is_active ?? true,
        sort_order: reply.sort_order ?? 0,
    }
    showOaAutoReplyEditor.value = true
}
async function saveAutoReply() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    savingAutoReply.value = true
    try {
        const form = oaAutoReplyForm.value
        if (editingAutoReply.value) {
            await apiClient.put('/official-accounts/auto-replies/' + editingAutoReply.value.id, form)
            ElMessage.success('已更新')
        } else {
            await apiClient.post('/official-accounts/' + accountId + '/auto-replies', form)
            ElMessage.success('已创建')
        }
        showOaAutoReplyEditor.value = false
        loadOaAutoReplies()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally { savingAutoReply.value = false }
}
async function deleteAutoReply(reply) {
    try {
        await ElMessageBox.confirm('确定删除此自动回复？', '确认', { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' })
        await apiClient.delete('/official-accounts/auto-replies/' + reply.id)
        ElMessage.success('已删除')
        loadOaAutoReplies()
    } catch { /* cancelled */ }
}
async function toggleAutoReplyActive(reply) {
    try {
        await apiClient.put('/official-accounts/auto-replies/' + reply.id, { is_active: !reply.is_active })
        reply.is_active = !reply.is_active
    } catch { ElMessage.error('操作失败') }
}

// ── OA 自定义菜单 ──
async function loadOaMenus() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    try {
        const r = await apiClient.get('/official-accounts/' + accountId + '/menus')
        oaMenus.value = r.data?.data || []
    } catch { oaMenus.value = [] }
}
function openNewMenu(parentId) {
    editingMenu.value = null
    oaMenuForm.value = { name: '', type: 'click', key: '', parent_id: parentId, app_id: '', page_path: '' }
    showOaMenuEditor.value = true
}
function editOaMenu(menu) {
    editingMenu.value = menu
    oaMenuForm.value = {
        name: menu.name,
        type: menu.type || 'click',
        key: menu.key || '',
        parent_id: menu.parent_id || null,
        app_id: menu.app_id || '',
        page_path: menu.page_path || '',
    }
    showOaMenuEditor.value = true
}
async function saveOaMenu() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    savingMenu.value = true
    try {
        const form = { ...oaMenuForm.value }
        if (form.parent_id === null) delete form.parent_id
        if (editingMenu.value) {
            await apiClient.put('/official-accounts/menus/' + editingMenu.value.id, form)
            ElMessage.success('已更新')
        } else {
            await apiClient.post('/official-accounts/' + accountId + '/menus', form)
            ElMessage.success('已创建')
        }
        showOaMenuEditor.value = false
        loadOaMenus()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally { savingMenu.value = false }
}
async function deleteOaMenu(menu) {
    try {
        await ElMessageBox.confirm('确定删除菜单「' + menu.name + '」？' + (menu.children?.length ? '其子菜单也将被删除。' : ''), '确认', { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' })
        await apiClient.delete('/official-accounts/menus/' + menu.id)
        ElMessage.success('已删除')
        loadOaMenus()
    } catch { /* cancelled */ }
}

// ── OA 素材管理 ──
async function loadOaMaterials() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    loadingOaMaterials.value = true
    try {
        const params = { per_page: 50 }
        if (oaMaterialType.value) params.type = oaMaterialType.value
        if (oaMaterialSearch.value) params.q = oaMaterialSearch.value
        const r = await apiClient.get('/official-accounts/' + accountId + '/materials', { params })
        oaMaterials.value = r.data?.data || []
    } catch { oaMaterials.value = [] }
    finally { loadingOaMaterials.value = false }
}
function openNewMaterial() {
    editingMaterial.value = null
    oaMaterialForm.value = { content: '', group: '' }
    showOaMaterialEditor.value = true
}
function editOaMaterial(material) {
    editingMaterial.value = material
    oaMaterialForm.value = { content: material.content || '', group: material.group || '' }
    showOaMaterialEditor.value = true
}
async function saveOaMaterial() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    if (!accountId) return
    savingMaterial.value = true
    try {
        if (editingMaterial.value) {
            await apiClient.put('/official-accounts/materials/' + editingMaterial.value.id, oaMaterialForm.value)
            ElMessage.success('已更新')
        } else {
            await apiClient.post('/official-accounts/' + accountId + '/materials', {
                type: 'text',
                content: oaMaterialForm.value.content,
                group: oaMaterialForm.value.group || undefined,
            })
            ElMessage.success('素材已创建')
        }
        showOaMaterialEditor.value = false
        loadOaMaterials()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally { savingMaterial.value = false }
}
async function deleteOaMaterial(material) {
    try {
        await ElMessageBox.confirm('确定删除此素材？', '确认', { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' })
        await apiClient.delete('/official-accounts/materials/' + material.id)
        ElMessage.success('已删除')
        loadOaMaterials()
    } catch { /* cancelled */ }
}
function onMaterialUploaded(res) {
    if (res?.data) {
        ElMessage.success('上传成功')
        loadOaMaterials()
    } else {
        ElMessage.error(res?.message || '上传失败')
    }
}
function copyMaterialUrl(material) {
    if (material.file_url) {
        navigator.clipboard.writeText(material.file_url).then(() => ElMessage.success('已复制链接'))
    }
}
function copyMaterialContent(material) {
    if (material.content) {
        navigator.clipboard.writeText(material.content).then(() => ElMessage.success('已复制内容'))
    }
}

const filteredOaComments = computed(() => {
    if (!oaCommentSearch.value) return oaComments.value;
    const q = oaCommentSearch.value.toLowerCase();
    return oaComments.value.filter(c =>
        c.content?.toLowerCase().includes(q) ||
        c.user?.name?.toLowerCase().includes(q)
    );
});
const mgrFilteredComments = computed(() => {
    let list = filteredOaComments.value;
    if (oaCommentMgrFilter.value === 'pinned') {
        list = list.filter(c => c.is_pinned);
    } else if (oaCommentMgrFilter.value === 'pending') {
        list = list.filter(c => c.status === 'pending');
    } else if (oaCommentMgrFilter.value === 'approved') {
        list = list.filter(c => c.status === 'approved');
    } else if (oaCommentMgrFilter.value === 'rejected') {
        list = list.filter(c => c.status === 'rejected');
    }
    return list;
});
const pendingCommentCount = computed(() => oaComments.value.filter(c => c.status === 'pending').length);
async function startReplyComment(comment) {
    replyingTo.value = comment.id;
    replyText.value = '';
}
async function submitReply(comment) {
    if (!replyText.value.trim()) { ElMessage.warning('请输入回复内容'); return; }
    replying.value = true;
    try {
        const r = await apiClient.post('/official-accounts/comments/' + comment.id + '/reply', { content: replyText.value });
        const reply = r.data?.data;
        if (reply) {
            if (!comment.replies) comment.replies = [];
            comment.replies.push(reply);
            replyText.value = '';
            replyingTo.value = null;
            ElMessage.success('回复成功');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '回复失败');
    } finally { replying.value = false; }
}
async function deleteOaComment(comment) {
    try {
        await apiClient.delete('/official-accounts/comments/' + comment.id);
        oaComments.value = oaComments.value.filter(c => c.id !== comment.id);
        ElMessage.success('已删除');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '删除失败');
    }
}
async function togglePinComment(comment) {
    try {
        const r = await apiClient.post('/official-accounts/comments/' + comment.id + '/pin');
        comment.is_pinned = r.data?.data?.is_pinned;
        ElMessage.success(comment.is_pinned ? '已置顶' : '已取消置顶');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    }
}
async function approveOaComment(comment) {
    try {
        await apiClient.post('/official-accounts/comments/' + comment.id + '/approve');
        comment.status = 'approved';
        ElMessage.success('评论已通过');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    }
}
async function rejectOaComment(comment) {
    try {
        await apiClient.post('/official-accounts/comments/' + comment.id + '/reject');
        comment.status = 'rejected';
        ElMessage.success('评论已拒绝');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败');
    }
}
// ── OA 文章管理 ──
async function loadOaManagedArticles() {
    if (!activeConv.value?.oa_account_id) return;
    loadingOaManagedArticles.value = true;
    try {
        const params = {};
        if (oaArticleMgrFilter.value !== 'all') params.status = oaArticleMgrFilter.value;
        const r = await apiClient.get('/official-accounts/' + activeConv.value.oa_account_id + '/articles', { params });
        oaManagedArticles.value = r.data?.data || [];
    } catch { oaManagedArticles.value = [] }
    finally { loadingOaManagedArticles.value = false; }
}
const filteredOaManagedArticles = computed(() => {
    if (!oaArticleMgrSearch.value) return oaManagedArticles.value;
    const q = oaArticleMgrSearch.value.toLowerCase();
    return oaManagedArticles.value.filter(a => a.title?.toLowerCase().includes(q));
});
async function togglePinArticle(art) {
    try {
        const r = await apiClient.post('/official-accounts/articles/' + art.id + '/pin');
        art.is_pinned = r.data?.data?.is_pinned;
        ElMessage.success(art.is_pinned ? '已置顶' : '已取消置顶');
    } catch (e) { ElMessage.error('操作失败'); }
}
async function toggleArticleStatus(art) {
    try {
        const newStatus = art.status === 'published' ? 'draft' : 'published';
        await apiClient.put('/official-accounts/articles/' + art.id, { status: newStatus });
        art.status = newStatus;
        ElMessage.success(newStatus === 'published' ? '已发布' : '已下架');
    } catch (e) { ElMessage.error('操作失败'); }
}
async function deleteOaManagedArticle(art) {
    try {
        await apiClient.delete('/official-accounts/articles/' + art.id);
        oaManagedArticles.value = oaManagedArticles.value.filter(a => a.id !== art.id);
        ElMessage.success('已删除');
    } catch (e) { ElMessage.error('删除失败'); }
}
function editOaManagedArticle(art) {
    if (!art?.id) return;
    const url = router.resolve({ name: 'OaEditor', query: { id: art.id, account_id: activeConv.value?.oa_account_id } }).href;
    window.open(url, '_blank');
}
async function openArticleStats(art) {
    articleStatsTarget.value = art
    articleStatsData.value = null
    showArticleStats.value = true
}
async function loadArticleStats() {
    if (!articleStatsTarget.value?.id) return
    loadingArticleStats.value = true
    try {
        const r = await apiClient.get('/official-accounts/articles/' + articleStatsTarget.value.id + '/stats')
        articleStatsData.value = r.data?.data || null
    } catch { articleStatsData.value = null; ElMessage.error('加载统计数据失败') }
    finally { loadingArticleStats.value = false }
}

// ── OA 文章详情 ──
const showOaArticleDetail = ref(false)
const oaArticleDetail = ref(null)
async function openOaArticleDetail(art) {
    oaArticleDetail.value = null;
    showOaArticleDetail.value = true;
    // 标记为已读
    art.is_read = true;
    const detail = await loadOaArticleDetail(art.id);
    if (detail) oaArticleDetail.value = detail;
}
async function handleOaLike() {
    if (!oaArticleDetail.value) return;
    const result = await toggleOaArticleLike(oaArticleDetail.value.id);
    if (result) {
        oaArticleDetail.value.is_liked = result.liked;
        oaArticleDetail.value.likes_count += result.liked ? 1 : -1;
    }
}
async function openOaArticleDetailById(articleId) {
    if (!articleId) return;
    oaArticleDetail.value = null;
    showOaArticleDetail.value = true;
    const detail = await loadOaArticleDetail(articleId);
    if (detail) oaArticleDetail.value = detail;
}
function openOaArticleStandalone(article) {
    if (!article?.id) return;
    const url = router.resolve({ name: 'OaArticleDetail', params: { id: article.id } }).href;
    window.open(url, '_blank');
}

// ── 离线保存 ──
function toggleOfflineSave(article) {
    if (!article?.id || !('serviceWorker' in navigator)) return
    if (article._offlineSaved) {
        navigator.serviceWorker.controller?.postMessage({
            type: 'UNCACHE_ARTICLE',
            articleId: article.id,
        })
        article._offlineSaved = false
        ElMessage.success('已移除离线缓存')
    } else {
        const data = {
            id: article.id,
            title: article.title,
            content: article.content,
            summary: article.summary,
            cover_image: article.cover_image,
            author: article.author,
            account: article.account,
            tags: article.tags,
            published_at: article.published_at,
            reads_count: article.reads_count,
            likes_count: article.likes_count,
        }
        navigator.serviceWorker.controller?.postMessage({
            type: 'CACHE_ARTICLE',
            articleId: article.id,
            data,
        })
        article._offlineSaved = true
        ElMessage.success('✅ 已保存到离线缓存')
    }
}

function copyOaArticleLink(article) {
    if (!article?.id) return;
    const url = window.location.origin + router.resolve({ name: 'OaArticleDetail', params: { id: article.id } }).href;
    navigator.clipboard.writeText(url).then(() => {
        ElMessage.success('链接已复制');
    }).catch(() => {
        ElMessage.success('链接: ' + url);
    });
}

// ── OA 文章分享 ──
async function shareOaArticleTo(target) {
    const article = oaArticleDetail.value
    if (!article?.id) return

    if (target === 'plaza') {
        try {
            await apiClient.post('/official-accounts/articles/' + article.id + '/share', { target: 'plaza' })
            ElMessage.success('已分享到广场')
            if (article.id) loadOaArticleDetail(article.id)
        } catch (e) { ElMessage.error(e.response?.data?.message || '分享失败') }
        return
    }

    if (target === 'wechat' || target === 'weibo' || target === 'copy') {
        const res = await apiClient.post('/official-accounts/articles/' + article.id + '/share', { target: target })
        const data = res.data?.data
        const text = (data?.share_text || article.title) + ' ' + (data?.share_url || window.location.href)

        if (target === 'copy') {
            navigator.clipboard.writeText(text).then(() => ElMessage.success('链接已复制'))
            return
        }

        if (target === 'weibo') {
            window.open('https://service.weibo.com/share/share.php?title=' + encodeURIComponent(text), '_blank')
            return
        }

        // wechat - copy text
        navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制，请粘贴到微信发送'))
        return
    }

    if (target === 'chat') {
        try {
            const { value: convId } = await ElMessageBox.prompt('选择目标会话ID（请输入会话ID）：', '分享到聊天', {
                inputPlaceholder: '请输入会话ID...',
            })
            if (convId) {
                await apiClient.post('/official-accounts/articles/' + article.id + '/share', {
                    target: 'chat',
                    conversation_id: parseInt(convId),
                })
                ElMessage.success('已分享到聊天')
                if (article.id) loadOaArticleDetail(article.id)
            }
        } catch { /* cancelled */ }
        return
    }

    if (target === 'channel') {
        try {
            const { value: chId } = await ElMessageBox.prompt('选择目标圈子ID（请输入圈子ID）：', '分享到圈子', {
                inputPlaceholder: '请输入圈子ID...',
            })
            if (chId) {
                await apiClient.post('/official-accounts/articles/' + article.id + '/share', {
                    target: 'channel',
                    channel_id: parseInt(chId),
                })
                ElMessage.success('已分享到圈子')
                if (article.id) loadOaArticleDetail(article.id)
            }
        } catch { /* cancelled */ }
    }
}
async function handleOaFollow(article) {
    const accountId = article.account?.id || activeConv.value?.oa_account_id;
    if (!accountId) return;
    try {
        await apiClient.post('/official-accounts/' + accountId + '/follow');
        article.is_following = true;
        ElMessage.success('已关注');
    } catch (e) { ElMessage.error(e.response?.data?.message || '关注失败'); }
}
async function handleOaUnfollow(article) {
    const accountId = article.account?.id || activeConv.value?.oa_account_id;
    if (!accountId) return;
    try {
        await apiClient.post('/official-accounts/' + accountId + '/unfollow');
        article.is_following = false;
        ElMessage.success('已取消关注');
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
}

// ── OA 文章举报 ──
async function reportOaArticle(article) {
    try {
        const { value: reason } = await ElMessageBox.prompt('请选择举报原因（spam/harassment/pornographic/illegal/other）：', '⚠️ 举报文章', {
            inputPlaceholder: '输入举报原因代码...',
        })
        if (reason) {
            await apiClient.post('/user-chat/reports', {
                reportable_type: 'article',
                reportable_id: article.id,
                reason: reason.trim(),
            })
            ElMessage.success('举报已提交')
        }
    } catch { /* cancelled */ }
}

function formatFullTime(date) {
    if (!date) return '';
    const d = new Date(date);
    const y = d.getFullYear();
    const mo = String(d.getMonth() + 1).padStart(2, '0');
    const da = String(d.getDate()).padStart(2, '0');
    const h = String(d.getHours()).padStart(2, '0');
    const mi = String(d.getMinutes()).padStart(2, '0');
    return `${y}年${mo}月${da}日 ${h}:${mi}`;
}
async function submitArticleComment(articleId) {
    if (!newCommentText.value.trim()) { ElMessage.warning('请输入评论内容'); return; }
    submittingComment.value = true;
    try {
        const r = await apiClient.post('/official-accounts/articles/' + articleId + '/comment', { content: newCommentText.value });
        const comment = r.data?.data;
        if (comment && oaArticleDetail.value) {
            if (!oaArticleDetail.value.comments) oaArticleDetail.value.comments = [];
            oaArticleDetail.value.comments.unshift(comment);
            newCommentText.value = '';
            ElMessage.success('评论成功');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '评论失败');
    } finally { submittingComment.value = false; }
}
function startDetailReply(comment) {
    detailReplyingTo.value = detailReplyingTo.value === comment.id ? null : comment.id;
    detailReplyText.value = '';
}
async function submitDetailReply(comment) {
    if (!detailReplyText.value.trim()) { ElMessage.warning('请输入回复内容'); return; }
    detailReplying.value = true;
    try {
        const r = await apiClient.post('/official-accounts/comments/' + comment.id + '/reply', { content: detailReplyText.value });
        const reply = r.data?.data;
        if (reply) {
            if (!comment.replies) comment.replies = [];
            comment.replies.push(reply);
            detailReplyText.value = '';
            detailReplyingTo.value = null;
            ElMessage.success('回复成功');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '回复失败');
    } finally { detailReplying.value = false; }
}

// ── OA 投稿 ──
const showSubmitDialog = ref(false)
const submitForm = ref({ title: '', content: '', summary: '', cover_image: '' })
const submitting = ref(false)

// ── 发现 ──
const discoverKeyword = ref('')
const discoverTab = ref('all')
const discoverAccounts = ref([])
const discoverArticles = ref([])
const discoverProducts = ref([])
const discoverMerchants = ref([])
const discoverLoading = ref(false)

function openDiscover() {
    activeConv.value = { id: '__discover__', is_discover: true }
    discoverKeyword.value = ''
    discoverTab.value = 'all'
    discoverAccounts.value = []
    discoverArticles.value = []
    doDiscoverSearch()
}
async function doDiscoverSearch() {
    discoverLoading.value = true
    try {
        const r = await apiClient.get('/official-accounts/search', {
            params: { q: discoverKeyword.value, type: discoverTab.value }
        })
        discoverAccounts.value = r.data?.data?.accounts || []
        discoverArticles.value = r.data?.data?.articles || []
        discoverProducts.value = r.data?.data?.products || []
        discoverMerchants.value = r.data?.data?.merchants || []
    } catch { discoverAccounts.value = []; discoverArticles.value = []; discoverProducts.value = []; discoverMerchants.value = [] }
    finally { discoverLoading.value = false }
}
function typeLabel(t) {
    const labels = { text: '📝 图文', image: '🖼️ 图片', multi_image: '📸 多图', video: '🎬 视频', audio: '🎵 音频' }
    return labels[t] || '📝 图文'
}
async function followDiscoverAccount(acc) {
    try {
        await apiClient.post('/official-accounts/' + acc.id + '/follow')
        ElMessage.success('关注成功')
        acc.is_following = true
        oaPanelRef.value?.loadData()
    } catch (e) { ElMessage.error(e.response?.data?.message || '关注失败') }
}
async function openDiscoverArticle(art) {
    showOaArticleDetail.value = true
    oaArticleDetail.value = null
    const detail = await loadOaArticleDetail(art.id)
    if (detail) oaArticleDetail.value = detail
}
async function doSubmitArticle() {
    if (!submitForm.value.title.trim() || !submitForm.value.content.trim()) {
        ElMessage.warning('请填写标题和内容'); return
    }
    submitting.value = true
    try {
        await apiClient.post('/official-accounts/submit', {
            account_id: activeConv.value?.oa_account_id,
            ...submitForm.value,
        })
        ElMessage.success('投稿已提交，等待审核')
        showSubmitDialog.value = false
        submitForm.value = { title: '', content: '', summary: '', cover_image: '' }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '提交失败')
    } finally {
        submitting.value = false
    }
}

// ── 打开全屏编辑器 ──
function openOaEditor() {
    const accountId = activeConv.value?.oa_account_id || selectedOaAccount.value?.id
    const route = router.resolve({ name: 'OaEditor', query: accountId ? { account_id: accountId } : {} })
    window.open(route.href, '_blank')
}

// ── OA 审核 ──
const showReviewPanel = ref(false)
const reviewSubmissions = ref([])
const reviewStats = ref(null)
const loadingReviews = ref(false)
const showSubmissionPreview = ref(false)
const previewSubmission = ref(null)
async function viewPendingReviews(acc) {
    selectedOaAccount.value = acc
    showReviewPanel.value = true
    activeConv.value = {
        id: 'oa_review_' + acc.id,
        is_oa_review: true,
        oa_account_id: acc.id,
        oa_name: acc.name,
    }
    await loadPendingReviews(acc.id)
}
async function loadPendingReviews(accountId) {
    loadingReviews.value = true
    try {
        const r = await apiClient.get(`/official-accounts/${accountId}/submissions/pending`)
        const data = r.data?.data || {}
        reviewSubmissions.value = data.submissions || data.data || []
        reviewStats.value = data.stats || null
    } catch { reviewSubmissions.value = []; reviewStats.value = null }
    finally { loadingReviews.value = false }
}
function openSubmissionPreview(sub) {
    previewSubmission.value = sub
    showSubmissionPreview.value = true
}
async function doReview(submission, action) {
    try {
        const payload = { action }
        if (action === 'reject') {
            const { value } = await ElMessageBox.prompt('请输入拒绝原因', '拒绝投稿', { confirmButtonText: '确认', cancelButtonText: '取消' })
            if (!value) return
            payload.reject_reason = value
        }
        await apiClient.post(`/official-accounts/submissions/${submission.id}/review`, payload)
        ElMessage.success(action === 'approve' ? '已通过并发布' : '已拒绝')
        showSubmissionPreview.value = false
        await loadPendingReviews(activeConv.value?.oa_account_id)
    } catch (e) {
        if (e !== 'cancel' && e !== undefined) ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ── OA 查看投稿 ──
async function viewOaSubmission(sub) {
    showOaArticleDetail.value = true
    oaArticleDetail.value = {
        id: sub.id,
        title: sub.title,
        content: sub.content,
        summary: sub.summary,
        cover_image: sub.cover_image,
        author: sub.user,
        account: sub.account,
        status: sub.status,
        published_at: sub.created_at,
        likes_count: 0, reads_count: 0, shares_count: 0,
        is_liked: false,
        related_articles: [],
    }
}

// ── 预览图片 ──
function previewImage(url) { window.open(url, '_blank') }
function openUrl(url) { window.open(url, '_blank') }

// ── 文件预览 ──
const showFilePreview = ref(false)
const previewFileData = ref({ url: '', name: '', size: 0, mime: '', ext: '' })
function previewFile(msg) {
    const name = msg.metadata?.file_name || msg.content || '文件'
    const size = msg.metadata?.file_size || 0
    const mime = msg.metadata?.file_mime || ''
    const url = msg.content || ''
    const ext = name.split('.').pop() || ''
    previewFileData.value = { url, name, size, mime, ext }
    showFilePreview.value = true
}
function previewAttachment(att) {
    const name = att.name || '附件'
    const ext = name.split('.').pop() || ''
    previewFileData.value = { url: att.url, name, size: att.size || 0, mime: att.mime || '', ext }
    showFilePreview.value = true
}
function fileIcon(msg) {
    const name = msg.metadata?.file_name || msg.content || ''
    const ext = name.split('.').pop()?.toLowerCase() || ''
    const iconMap = { pdf: '📕', doc: '📘', docx: '📘', xls: '📗', xlsx: '📗', ppt: '📙', pptx: '📙', zip: '📦', rar: '📦', '7z': '📦', mp4: '🎬', mov: '🎬', mp3: '🎵', jpg: '🖼️', jpeg: '🖼️', png: '🖼️', gif: '🖼️' }
    return iconMap[ext] || '📄'
}
function fileName(msg) {
    return msg.metadata?.file_name || msg.content?.split('/').pop() || '文件'
}
function fileSizeStr(msg) {
    const size = msg.metadata?.file_size || 0
    if (!size) return ''
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0
    let s = size
    while (s >= 1024 && i < units.length - 1) { s /= 1024; i++ }
    return s.toFixed(i > 0 ? 1 : 0) + ' ' + units[i]
}

function scrollToMessage(id) {
    if (!id) return;
    // 尝试找到目标消息 DOM 元素
    const el = document.querySelector(`[data-msg-id="${id}"]`);
    if (el) {
        el.classList.add('msg-highlight');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.classList.remove('msg-highlight'), 2000);
    } else if (activeConv.value) {
        // 消息不在当前视图，尝试加载更早消息
        ElMessage.info('正在定位消息...');
        loadAndScrollTo(id);
    }
}

async function loadAndScrollTo(targetId) {
    // 向更早的历史翻页查找
    let found = false;
    let page = 1;
    while (!found && page < 20) {
        try {
            const res = await apiClient.get('/user-chat/conversations/' + activeConv.value.id + '/messages', {
                params: { page: page, per_page: 50 }
            });
            const msgs = res.data?.data || [];
            if (msgs.length === 0) break;
            const msg = msgs.find(m => m.id === targetId);
            if (msg) {
                messages.value = msgs;
                found = true;
                await nextTick();
                const el = document.querySelector(`[data-msg-id="${targetId}"]`);
                if (el) {
                    el.classList.add('msg-highlight');
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    setTimeout(() => el.classList.remove('msg-highlight'), 2000);
                }
                break;
            }
            page++;
        } catch { break; }
    }
    if (!found) ElMessage.warning('消息未找到或已被删除');
}

// ── 生命周期 ──
onMounted(async () => {
    try { const userRes = await apiClient.get('/user'); const user = userRes.data?.data || {}; myId.value = user.id || 0; myName.value = user.name || ''; myAvatar.value = user.avatar_url || '' } catch { /* ignore */ }
    await loadConversations()
    // SYNC-004: 消息漫游
    try { await apiClient.get('/user-chat/messages/sync', { params: { per_page: 50 } }) } catch { /* ignore */ }
    initEcho()
    initNotifEcho()
    startHeartbeat()
    loadCannedReplies()
    loadAllTags()
    // AI-002: 未读消息摘要
    setTimeout(checkUnreadSummary, 3000)
    // AI-018: 加载分类
    loadClassifications()
    loadAiFriends()
    // MSG-010: 加载贴纸
    loadStickerPacks()
    // 加载自定义 emoji
    loadCustomEmojis()
    // 加载斜杠命令
    loadSlashCommands()
    // 加载自动回复状态
    loadAutoReplyStatus()
    // 加载公众号未读消息数（每30秒）
    loadOaUnreadCount()
    oaUnreadTimer = setInterval(loadOaUnreadCount, 30000)
    // 每30秒刷新公众号未读数
    // 加载私密空间 PIN 状态
    loadPrivacyPinStatus()
    // 从编辑器返回时自动选中公众号
    if (route.query.account_id) {
        const aid = Number(route.query.account_id)
        if (aid) {
            sidebarTab.value = 'officialAccounts'
            // 等待 OA 面板加载完成后选中
            setTimeout(async () => {
                try {
                    const r = await apiClient.get('/official-accounts/my-owned')
                    const accounts = r.data?.data || []
                    const acc = accounts.find(a => a.id === aid)
                    if (acc) selectOaAccount(acc)
                } catch { /* ignore */ }
            }, 500)
        }
    }
})

// 监听路由参数，从编辑器返回时自动选中公众号
watch(() => route.query.account_id, (aid) => {
    if (aid) {
        const id = Number(aid)
        if (id) {
            sidebarTab.value = 'officialAccounts'
            setTimeout(async () => {
                try {
                    const r = await apiClient.get('/official-accounts/my-owned')
                    const accounts = r.data?.data || []
                    const acc = accounts.find(a => a.id === id)
                    if (acc) selectOaAccount(acc)
                } catch { /* ignore */ }
            }, 500)
        }
    }
})
onUnmounted(() => { stopHeartbeat(); if (oaUnreadTimer) clearInterval(oaUnreadTimer) })
</script>
<style scoped>
.user-chat-page { height: calc(100vh - 140px); position: relative; }
.chat-layout { display: flex; height: 100%; border: 1px solid #e4e7ed; border-radius: 6px; overflow: hidden; background: #fff; }
.chat-sidebar { width: 320px; border-right: 1px solid #e4e7ed; display: flex; flex-direction: column; background: #fafafa; transition: transform 0.3s ease; }
.chat-main { flex: 1; display: flex; flex-direction: column; min-width: 0; background: #fff; }
.chat-messages-wrap { flex: 1; display: flex; flex-direction: row; min-height: 0; }
.chat-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #e4e7ed; }
.channel-badge { font-size: 11px; background: #409eff; color: #fff; padding: 1px 6px; border-radius: 4px; margin-left: 6px; }
/* ── 频道消息置顶标记 ── */
.msg-pinned { border-left: 3px solid #e6a23c; }
.pinned-indicator { font-size: 11px; color: #e6a23c; margin-bottom: 4px; font-weight: 500; }
.channel-msg-actions { display: none !important; }
.msg-item:hover .channel-msg-actions { display: none !important; }
/* ── 频道置顶消息列表 ── */
.pinned-msg-item { padding: 12px; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .pinned-msg-item { border-bottom-color: #2a2a3e; }
.pinned-msg-header { display: flex; align-items: center; font-size: 13px; margin-bottom: 6px; }
.pinned-msg-sender { font-weight: 600; color: #409eff; }
.pinned-msg-time { margin-left: auto; font-size: 11px; color: #999; }
.pinned-msg-content { font-size: 14px; color: #333; margin-bottom: 6px; word-break: break-all; }
.chat-dark-mode .pinned-msg-content { color: #ccc; }
.pinned-msg-actions { text-align: right; }
/* ── 频道编辑头像 ── */
.channel-edit-avatar-row { display: flex; gap: 12px; align-items: center; }
.channel-edit-avatar-preview { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.channel-edit-avatar-img { width: 100%; height: 100%; object-fit: cover; }
.channel-edit-avatar-placeholder { font-size: 24px; }
.channel-edit-avatar-actions { display: flex; gap: 6px; align-items: center; }
/* ── 时间分隔线 ── */
.time-separator { text-align: center; padding: 16px 0 8px; position: relative; }
.time-separator::before { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 1px; background: #e4e7ed; }
.time-separator-text { display: inline-block; background: #fff; padding: 0 12px; font-size: 12px; color: #999; position: relative; z-index: 1; }
.chat-dark-mode .time-separator-text { background: #1a1a2e; }
.chat-dark-mode .time-separator::before { background: #2a2a3e; }
/* ── 频道消息输入行 ── */
/* ── 频道消息输入区 ── */
.channel-input-area { padding: 0 12px 8px; }
.channel-input-field { flex: 1; }
.channel-input-field .el-textarea__inner { min-height: 40px !important; }
.channel-input-toolbar { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }
.channel-toolbar-left { display: flex; align-items: center; gap: 2px; }
.channel-toolbar-right { display: flex; align-items: center; gap: 8px; }
/* ── 频道表情选择器 ── */
.channel-emoji-picker { max-height: 200px; overflow-y: auto; }
.channel-emoji-picker .emoji-option { font-size: 22px; cursor: pointer; padding: 2px; border-radius: 4px; transition: background 0.15s; display: inline-block; }
.channel-emoji-picker .emoji-option:hover { background: #f0f0f0; }
.channel-emoji-picker .emoji-custom-row .emoji-option img { width: 24px; height: 24px; }
.channel-emoji-picker .emoji-section-label { width: 100%; font-size: 11px; color: #999; margin-bottom: 2px; padding: 4px 0; }
/* ── 频道消息搜索 ── */
.channel-search-bar { display: flex; gap: 6px; padding: 6px 0; border-bottom: 1px solid #e4e7ed; margin-bottom: 4px; }
.channel-search-results { max-height: 200px; overflow-y: auto; border-bottom: 1px solid #e4e7ed; margin-bottom: 4px; }
.channel-search-result-item { display: flex; align-items: center; padding: 4px 0; cursor: pointer; font-size: 12px; }
.channel-search-result-item:hover { background: #f0f5ff; }
/* ── 消息底部 ── */
.msg-footer { display: flex; align-items: center; gap: 4px; margin-top: 4px; font-size: 11px; color: #bbb; }
.msg-item:not(.msg-self) .msg-footer { justify-content: flex-end; }
.msg-self .msg-footer { justify-content: flex-start; }
.msg-status { display: inline-flex; align-items: center; }
/* ── 已撤回消息 ── */
.msg-recalled { background: #f5f5f5 !important; color: #999 !important; cursor: default; }
.msg-recalled-text { font-size: 12px; color: #999; font-style: italic; padding: 4px 0; }
.chat-dark-mode .msg-recalled { background: #2a2a3e !important; }
/* ── 图片/视频消息 ── */
.msg-image { margin: 4px 0; }
.chat-image { max-width: 240px; max-height: 200px; border-radius: 6px; cursor: pointer; }
.chat-video { max-width: 240px; max-height: 200px; border-radius: 6px; }
/* ── 文件消息 ── */
.msg-file { display: flex; align-items: center; gap: 8px; padding: 6px 10px; background: #f5f7fa; border-radius: 6px; cursor: pointer; margin: 4px 0; }
.chat-dark-mode .msg-file { background: #2a2a3e; }
.file-icon { font-size: 24px; }
.file-info { flex: 1; min-width: 0; }
.file-name { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.file-meta { font-size: 11px; color: #999; }
/* ── 语音消息 ── */
.msg-voice-wrap { margin: 4px 0; }
.msg-voice { display: flex; align-items: center; cursor: pointer; padding: 6px 10px; background: #e8f4fd; border-radius: 20px; max-width: 180px; }
.msg-voice-self { background: #d9f0ff; }
.chat-dark-mode .msg-voice { background: #1a3a5c; }
.voice-wave { display: flex; align-items: center; gap: 1px; flex: 1; height: 24px; }
.voice-bar { width: 3px; background: #409eff; border-radius: 2px; min-height: 4px; }
.voice-duration { font-size: 11px; color: #999; margin-left: 6px; white-space: nowrap; }
/* ── 频道右键菜单 ── */
.channel-msg-context-menu { background: #fff; border: 1px solid #e4e7ed; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 4px 0; min-width: 120px; z-index: 9999; }
.channel-msg-context-menu div:hover { background: #f0f0f0; }
.chat-dark-mode .channel-msg-context-menu { background: #2a2a3e; border-color: #3a3a4e; }

/* ── 公众号 OA 样式 ── */
/* OA 管理面板 */
.oa-manage-bar {
    border-bottom: 1px solid #e4e7ed; padding: 10px 16px; background: #fafafa;
}
.chat-dark-mode .oa-manage-bar { border-color: #2a2a3e; background: #1a1a2e; }
.oa-stats-row { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.oa-stat-item {
    flex: 1 0 calc(33.33% - 8px); min-width: 70px; display: flex; flex-direction: column; align-items: center;
    padding: 8px 4px; border-radius: 8px; background: #fff;
    border: 1px solid #e4e7ed; cursor: default;
}
.chat-dark-mode .oa-stat-item { background: #16213e; border-color: #2a2a3e; }
.oa-stat-num { font-size: 20px; font-weight: 700; color: #409eff; }
.oa-stat-label { font-size: 11px; color: #999; margin-top: 2px; }
.oa-manage-actions { display: flex; gap: 6px; flex-wrap: wrap; }
/* 文章列表工具栏 */
.oa-article-toolbar { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid #eee; align-items: center; }
.chat-dark-mode .oa-article-toolbar { border-color: #2a2a3e; }

/* ── OA 标签筛选 ── */
.oa-tag-filter { display: flex; flex-wrap: wrap; gap: 4px; padding: 6px 12px; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .oa-tag-filter { border-color: #2a2a3e; }
.oa-tag-filter-item { font-size: 11px; padding: 2px 10px; border-radius: 12px; cursor: pointer; color: #909399; background: #f5f5f5; transition: all .15s; white-space: nowrap; }
.chat-dark-mode .oa-tag-filter-item { background: #1f1f3a; color: #999; }
.oa-tag-filter-item:hover { color: #409eff; background: #ecf5ff; }
.chat-dark-mode .oa-tag-filter-item:hover { background: #1a2744; color: #66b1ff; }
.oa-tag-filter-item.active { color: #fff; background: #409eff; font-weight: 600; }
.chat-dark-mode .oa-tag-filter-item.active { background: #409eff; color: #fff; }

/* ── OA 合集筛选 ── */
.oa-collection-filter { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px 12px; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .oa-collection-filter { border-color: #2a2a3e; }
.oa-collection-item { background: #fef5e7 !important; color: #e6a23c !important; }
.chat-dark-mode .oa-collection-item { background: #2a2010 !important; color: #e6a23c !important; }
.oa-collection-item:hover { background: #e6a23c !important; color: #fff !important; }
.oa-collection-item.active { background: #e6a23c !important; color: #fff !important; }
.oa-col-count { font-size: 10px; margin-left: 2px; opacity: .7; }

.oa-load-more { text-align: center; padding: 16px; }

/* ── 为你推荐 ── */
.oa-recommend-section { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .oa-recommend-section { border-color: #2a2a3e; }
.oa-recommend-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #555; }
.chat-dark-mode .oa-recommend-header { color: #aaa; }
.oa-recommend-scroll { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px; -webkit-overflow-scrolling: touch; }
.oa-recommend-scroll::-webkit-scrollbar { height: 3px; }
.oa-recommend-scroll::-webkit-scrollbar-thumb { background: #ddd; border-radius: 3px; }
.oa-recommend-card { flex: 0 0 180px; border-radius: 8px; border: 1px solid #eee; overflow: hidden; cursor: pointer; transition: all .15s; background: #fff; }
.chat-dark-mode .oa-recommend-card { border-color: #2a2a3e; background: #1a1a2e; }
.oa-recommend-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); border-color: #409eff; }
.oa-rec-cover { width: 100%; height: 90px; overflow: hidden; background: #f5f5f5; }
.oa-rec-cover img { width: 100%; height: 100%; object-fit: cover; }
.oa-rec-body { padding: 8px; }
.oa-rec-title { font-size: 12px; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; margin-bottom: 4px; }
.oa-rec-meta { display: flex; align-items: center; justify-content: space-between; font-size: 10px; color: #999; }
.oa-rec-tags { margin-top: 4px; display: flex; gap: 2px; flex-wrap: wrap; }
.oa-rec-tags .el-tag { font-size: 9px; padding: 0 4px; height: 18px; line-height: 18px; }
/* 文章卡片新布局 */
/* ── OA 瀑布流 ── */
.oa-masonry-area { flex: 1; overflow-y: auto; padding: 8px; -webkit-overflow-scrolling: touch; }
.oa-article-masonry { column-count: 2; column-gap: 8px; }
.oa-article-card { break-inside: avoid; margin-bottom: 8px; border-radius: 10px; border: 1px solid #eee; cursor: pointer; transition: all 0.2s; background: #fff; overflow: hidden; display: inline-block; width: 100%; animation: plazaCardIn .3s ease both; }
.oa-article-card.oa-article-read { opacity: .75; }
.oa-article-card.oa-article-read .oa-article-title { font-weight: 400; }
.oa-article-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.08); border-color: #409eff; }
.oa-article-card.oa-article-read:hover { opacity: 1; }
.chat-dark-mode .oa-article-card { border-color: #2a2a3e; background: #1a1a2e; }
.chat-dark-mode .oa-article-card:hover { border-color: #409eff; box-shadow: 0 4px 16px rgba(0,0,0,.3); }
.oa-article-cover { width: 100%; aspect-ratio: 16/10; overflow: hidden; background: #f5f5f5; position: relative; }
.oa-cover-noimg { display: flex; align-items: center; justify-content: center; min-height: 80px; }
.oa-noimg-icon { font-size: 28px; color: #ddd; }
.oa-read-badge { position: absolute; top: 6px; right: 6px; font-size: 10px; background: rgba(0,0,0,.5); color: #fff; padding: 1px 7px; border-radius: 8px; backdrop-filter: blur(2px); }
.oa-reading-list-badge { position: absolute; top: 6px; left: 6px; font-size: 16px; cursor: pointer; filter: drop-shadow(0 1px 2px rgba(0,0,0,.4)); transition: transform .15s; }
.oa-reading-list-badge:hover { transform: scale(1.2); }
.oa-title-unread { font-weight: 700; }
.oa-cover-img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.oa-article-card:hover .oa-cover-img { transform: scale(1.05); }
.oa-article-body { padding: 10px 12px; }
.oa-article-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.oa-article-summary { font-size: 12px; color: #666; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; }
.chat-dark-mode .oa-article-summary { color: #999; }
.oa-article-meta-row { display: flex; align-items: center; gap: 4px 8px; font-size: 11px; color: #999; flex-wrap: wrap; }
.oa-article-author { display: flex; align-items: center; gap: 3px; color: #409eff; font-weight: 500; }
.oa-author-avatar { width: 16px; height: 16px; border-radius: 50%; object-fit: cover; }
.oa-article-stats-list { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.oa-article-stats-list span { white-space: nowrap; }
.oa-article-time { margin-left: auto; font-size: 10px; color: #ccc; white-space: nowrap; }
.oa-article-tags { margin-top: 6px; display: flex; gap: 3px; flex-wrap: wrap; }
.oa-load-more { text-align: center; padding: 16px; color: #999; font-size: 13px; }
.oa-load-end { color: #ddd; font-size: 12px; }
/* OA 文章详情评论区 */
.oa-detail-comments { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee; }
.chat-dark-mode .oa-detail-comments { border-top-color: #2a2a3e; }
.oa-detail-comments h4 { font-size: 15px; margin-bottom: 12px; }
.oa-detail-comment-input { margin-bottom: 16px; }
.oa-detail-comment-list { display: flex; flex-direction: column; gap: 12px; }
.oa-detail-comment-item { display: flex; gap: 8px; }
.oa-detail-comment-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.oa-detail-comment-avatar-text { width: 32px; height: 32px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; }
.oa-detail-comment-body { flex: 1; min-width: 0; }
.oa-detail-comment-author { font-size: 13px; font-weight: 600; color: #409eff; }
.oa-detail-comment-time { font-size: 11px; color: #999; margin-left: 6px; font-weight: 400; }
.oa-detail-comment-text { font-size: 13px; margin: 4px 0; }
.oa-detail-comment-replies { margin-top: 8px; padding: 8px; background: #f5f7fa; border-radius: 6px; }
.chat-dark-mode .oa-detail-comment-replies { background: #1a1a2e; }
.oa-detail-comment-reply { padding: 4px 0; font-size: 12px; display: flex; align-items: flex-start; gap: 4px; }
.oa-reply-author { font-weight: 600; color: #67c23a; white-space: nowrap; }
.oa-reply-text { flex: 1; color: #333; }
.chat-dark-mode .oa-reply-text { color: #ccc; }
.oa-header-avatar { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #409eff; color: #fff; font-size: 16px; margin-right: 8px; }
.oa-detail-header { margin-bottom: 16px; }
.oa-detail-account { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.oa-detail-acc-avatar { width: 28px; height: 28px; border-radius: 50%; }
.oa-detail-acc-name { font-size: 14px; font-weight: 500; color: #409eff; }
.oa-detail-title { font-size: 22px; font-weight: 700; line-height: 1.4; margin-bottom: 12px; }
.oa-detail-author-row { display: flex; align-items: center; gap: 12px; font-size: 13px; color: #999; }
.oa-detail-author { display: flex; align-items: center; gap: 4px; }
.oa-detail-author-avatar { width: 20px; height: 20px; border-radius: 50%; }
.oa-detail-time { font-size: 12px; }
.oa-detail-cover { margin-bottom: 16px; border-radius: 8px; overflow: hidden; max-height: 360px; }
.oa-detail-cover-img { width: 100%; object-fit: cover; }
.oa-detail-content { font-size: 15px; line-height: 1.8; color: #333; }
.chat-dark-mode .oa-detail-content { color: #ccc; }
.oa-detail-content p { margin-bottom: 12px; }
.oa-detail-tags { margin-top: 16px; }
.oa-detail-actions-bar { display: flex; gap: 16px; margin-top: 16px; padding-top: 12px; border-top: 1px solid #eee; justify-content: center; }
.chat-dark-mode .oa-detail-actions-bar { border-top-color: #2a2a3e; }
.oa-detail-related { margin-top: 20px; padding-top: 16px; border-top: 1px solid #eee; }
.chat-dark-mode .oa-detail-related { border-top-color: #2a2a3e; }
.oa-detail-related h4 { font-size: 15px; margin-bottom: 8px; }
.oa-related-item { display: flex; align-items: center; padding: 8px 0; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.oa-related-item:hover { color: #409eff; }
.chat-dark-mode .oa-related-item { border-bottom-color: #2a2a3e; }
.oa-related-title { flex: 1; font-size: 13px; }
.oa-related-time { font-size: 11px; color: #999; }
/* OA 文章详情视图（左侧面板） */
.oa-article-detail-view { flex: 1; overflow-y: auto; background: #fff; display: flex; flex-direction: column; }
.chat-dark-mode .oa-article-detail-view { background: #12121e; }

/* ── 我的互动视图（匹配账户中心样式） ── */
.my-interactions-view { flex: 1; overflow-y: auto; background: #fff; display: flex; flex-direction: column; }
.chat-dark-mode .my-interactions-view { background: #12121e; }
.my-interactions-body { flex: 1; overflow-y: auto; padding: 12px 16px; }
.my-feed-card {
    display: flex;
    gap: 12px;
    border: 1px solid #eee;
    border-radius: 8px;
    padding: 14px;
    background: #fff;
    cursor: pointer;
    transition: all .2s;
    margin-bottom: 8px;
}
.my-feed-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.08); }
.chat-dark-mode .my-feed-card { background: #1a1a2e; border-color: #2a2a3e; }
.chat-dark-mode .my-feed-card:hover { border-color: #409eff; }
.my-feed-cover {
    width: 100px; height: 70px; border-radius: 4px; overflow: hidden;
    flex-shrink: 0; background: #f0f0f0;
}
.my-feed-cover img { width: 100%; height: 100%; object-fit: cover; }
.my-feed-cover-avatar { width: 60px; display: flex; align-items: center; justify-content: center; height: auto; min-height: 60px; }
.my-feed-body { flex: 1; min-width: 0; }
.my-feed-account { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.my-feed-acc-name { font-size: 13px; font-weight: 600; color: #303133; }
.chat-dark-mode .my-feed-acc-name { color: #e0e0e0; }
.my-feed-time { font-size: 11px; color: #c0c4cc; margin-left: auto; white-space: nowrap; }
.my-feed-title { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.chat-dark-mode .my-feed-title { color: #e0e0e0; }
.my-feed-summary { font-size: 12px; color: #909399; margin-bottom: 6px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.my-feed-meta { display: flex; gap: 12px; font-size: 11px; color: #c0c4cc; }

/* ── 朋友圈过滤条 ── */
.my-feed-type-filter { display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap; }
.my-feed-type-filter span {
    padding: 4px 12px; border-radius: 14px; font-size: 12px; cursor: pointer;
    border: 1px solid #e4e7ed; transition: all 0.15s; background: #fff;
}
.my-feed-type-filter span:hover { border-color: #409eff; color: #409eff; }
.my-feed-type-filter span.active { background: #409eff; color: #fff; border-color: #409eff; }
.chat-dark-mode .my-feed-type-filter span { background: #1a1a2e; border-color: #2a2a3e; color: #c0c4cc; }
.chat-dark-mode .my-feed-type-filter span.active { background: #409eff; color: #fff; border-color: #409eff; }
.my-feed-meta { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #c0c4cc; }
.my-feed-tags { margin-left: auto; display: flex; gap: 4px; }
.my-feed-meta .el-tag { font-size: 10px; padding: 0 4px; height: 18px; line-height: 18px; }
.my-interact-item { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .my-interact-item { border-color: #2a2a3e; }
.my-interact-item:last-child { border-bottom: none; }
.my-int-head { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
.my-int-name { font-size: 13px; font-weight: 500; }
.my-int-time { font-size: 11px; color: #c0c4cc; margin-left: auto; }
.my-int-title { font-size: 14px; color: #303133; cursor: pointer; padding: 4px 0; }
.my-int-title:hover { color: #409eff; }
.chat-dark-mode .my-int-title { color: #e0e0e0; }
.chat-dark-mode .my-int-title:hover { color: #409eff; }
.oa-article-detail-header { text-align: center; padding: 24px 32px 16px; border-bottom: 1px solid #eee; flex-shrink: 0; }
.chat-dark-mode .oa-article-detail-header { border-color: #2a2a3e; }
.oa-detail-top-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.oa-detail-top-actions { display: flex; gap: 4px; }
.oa-detail-back { background: none; border: none; color: #409eff; cursor: pointer; font-size: 13px; display: block; }
.oa-detail-back:hover { color: #66b1ff; }
.oa-article-detail-header .oa-detail-title { font-size: 22px; font-weight: 700; line-height: 1.4; margin: 0 0 12px; }
.oa-detail-meta-row { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 13px; color: #999; flex-wrap: wrap; }
.oa-detail-author { display: flex; align-items: center; gap: 4px; color: #409eff; font-weight: 500; }
.oa-detail-author-avatar { width: 22px; height: 22px; border-radius: 50%; object-fit: cover; }
.oa-detail-sep { color: #ddd; }
.oa-detail-acc-name { color: #666; }
.oa-article-detail-body { flex: 1; overflow-y: auto; padding: 20px 32px 40px; max-width: 720px; margin: 0 auto; width: 100%; }
.oa-detail-cover { margin-bottom: 20px; border-radius: 8px; overflow: hidden; }
.oa-detail-cover-img { width: 100%; max-height: 400px; object-fit: cover; }
.oa-detail-content { font-size: 16px; line-height: 1.8; color: #333; }
.chat-dark-mode .oa-detail-content { color: #ccc; }
.oa-detail-content img { max-width: 100%; border-radius: 6px; margin: 12px 0; }
.oa-detail-prev-next { display: flex; gap: 16px; margin-top: 24px; }
.oa-pn-item { flex: 1; padding: 12px; border-radius: 8px; border: 1px solid #eee; cursor: pointer; transition: all 0.2s; }
.oa-pn-item:hover { border-color: #409eff; background: #f0f7ff; }
.chat-dark-mode .oa-pn-item { border-color: #2a2a3e; }
.chat-dark-mode .oa-pn-item:hover { border-color: #409eff; background: #16213e; }
.oa-pn-label { display: block; font-size: 11px; color: #999; margin-bottom: 4px; }
.oa-pn-title { font-size: 14px; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.oa-pn-next { text-align: right; }
.oa-related-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.oa-related-card { border-radius: 8px; border: 1px solid #eee; overflow: hidden; cursor: pointer; transition: all 0.2s; }
.oa-related-card:hover { border-color: #409eff; box-shadow: 0 2px 8px rgba(64,158,255,0.1); }
.chat-dark-mode .oa-related-card { border-color: #2a2a3e; }
.oa-related-cover { width: 100%; height: 120px; overflow: hidden; background: #f5f7fa; display: flex; align-items: center; justify-content: center; font-size: 32px; }
.chat-dark-mode .oa-related-cover { background: #1a1a2e; }
.oa-related-cover img { width: 100%; height: 100%; object-fit: cover; }
.oa-related-cover-text { color: #ccc; }
.oa-related-info { padding: 10px 12px; }
.oa-related-info .oa-related-title { font-size: 13px; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.oa-related-desc { font-size: 12px; color: #999; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.oa-related-info .oa-related-time { font-size: 11px; color: #ccc; margin-top: 4px; }

/* OA 评论管理 */
.oa-comment-item { padding: 12px; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .oa-comment-item { border-color: #2a2a3e; }
.oa-comment-header { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 12px; }
.oa-comment-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
.oa-comment-avatar-placeholder { width: 24px; height: 24px; border-radius: 50%; background: #409eff; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
.oa-comment-author { font-weight: 600; color: #409eff; }
.oa-comment-article { color: #999; font-size: 11px; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oa-comment-time { font-size: 11px; color: #999; margin-left: auto; }
.oa-comment-content { font-size: 13px; padding: 4px 0 4px 30px; }
.oa-comment-actions { padding-left: 30px; display: flex; gap: 4px; }
.oa-comment-replies { margin: 8px 0 4px 30px; padding: 8px; background: #f5f7fa; border-radius: 6px; }
.chat-dark-mode .oa-comment-replies { background: #1a1a2e; }
.oa-comment-reply { display: flex; align-items: flex-start; gap: 4px; padding: 4px 0; font-size: 12px; }
.oa-comment-reply-text { flex: 1; color: #333; }
.chat-dark-mode .oa-comment-reply-text { color: #ccc; }
.oa-reply-input { border-top: 1px solid #eee; padding-top: 8px; }
.chat-dark-mode .oa-reply-input { border-color: #2a2a3e; }
/* OA 文章管理 */
.oa-mgr-article { display: flex; align-items: center; padding: 10px 12px; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .oa-mgr-article { border-color: #2a2a3e; }
.oa-mgr-info { flex: 1; min-width: 0; }
.oa-mgr-title { font-size: 14px; font-weight: 500; margin-bottom: 4px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.oa-mgr-meta { display: flex; align-items: center; gap: 8px; font-size: 11px; color: #999; }
.oa-mgr-actions { display: flex; gap: 2px; flex-shrink: 0; }

/* ── 发现视图 ── */
.discover-search-bar { padding: 12px 20px; border-bottom: 1px solid #e4e7ed; }
.chat-dark-mode .discover-search-bar { border-color: #2a2a4a; }
.discover-tabs { margin-top: 8px; }
.discover-results { padding: 8px 12px; overflow-y: auto; max-height: calc(100vh - 220px); }
.discover-section { margin-bottom: 20px; }
.discover-section-title { font-size: 14px; font-weight: 600; margin-bottom: 8px; padding-left: 4px; }
.discover-account-card { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border-radius: 8px; cursor: default; margin-bottom: 4px; transition: background 0.2s; }
.discover-account-card:hover { background: #f5f5f5; }
.chat-dark-mode .discover-account-card:hover { background: #2a2a3e; }
.discover-acc-avatar { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: #409eff; color: #fff; font-size: 18px; }
.discover-acc-img { width: 100%; height: 100%; object-fit: cover; }
.discover-acc-info { flex: 1; min-width: 0; }
.discover-acc-name { font-size: 14px; font-weight: 500; }
.discover-acc-desc { font-size: 12px; color: #999; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.discover-acc-meta { font-size: 11px; color: #999; margin-top: 2px; }
.discover-article-card { display: flex; gap: 12px; padding: 12px; border-radius: 8px; cursor: pointer; margin-bottom: 4px; transition: all 0.2s; border: 1px solid #eee; }
.discover-article-card:hover { border-color: #409eff; background: #f0f7ff; }
.chat-dark-mode .discover-article-card { border-color: #2a2a3e; }
.chat-dark-mode .discover-article-card:hover { border-color: #409eff; background: #16213e; }
.discover-art-cover { width: 100px; height: 70px; border-radius: 6px; overflow: hidden; flex-shrink: 0; position: relative; }
.discover-art-img { width: 100%; height: 100%; object-fit: cover; }
.discover-art-type-badge { position: absolute; bottom: 2px; left: 2px; font-size: 10px; background: rgba(0,0,0,0.6); color: #fff; padding: 1px 4px; border-radius: 2px; }
.discover-art-body { flex: 1; min-width: 0; }
.discover-art-title { font-size: 14px; font-weight: 500; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.discover-art-summary { font-size: 12px; color: #666; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.chat-dark-mode .discover-art-summary { color: #999; }
.discover-art-meta { display: flex; align-items: center; gap: 10px; font-size: 11px; color: #999; margin-top: 6px; flex-wrap: wrap; }
.discover-art-source { color: #409eff; font-weight: 500; }
.discover-art-time { margin-left: auto; }

/* ── 商品卡片（产品商城风格）── */
.discover-prod-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 24px; padding: 4px 0; }
.prod-card { border-radius: 12px; overflow: hidden; border: 1px solid #f3f4f6; background: #fff; transition: all 0.3s; position: relative; display: flex; flex-direction: column; }
.chat-dark-mode .prod-card { border-color: #2a2a3e; background: #16213e; }
.prod-card:hover { border-color: #409eff; box-shadow: 0 4px 20px rgba(64,158,255,0.12); transform: translateY(-3px); }
.prod-card-link { text-decoration: none; color: inherit; display: flex; flex-direction: column; flex: 1; }
.prod-card-img-wrap { aspect-ratio: 16/9; background: linear-gradient(135deg, #f5f7fa, #e8ecf1); display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
.chat-dark-mode .prod-card-img-wrap { background: linear-gradient(135deg, #1a1a2e, #2a2a3e); }
.prod-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.prod-card:hover .prod-card-img { transform: scale(1.05); }
.prod-card-placeholder { font-size: 40px; opacity: 0.25; }
.prod-card-badges { position: absolute; top: 6px; left: 6px; display: flex; flex-direction: column; gap: 4px; }
.prod-card-badge { padding: 2px 8px; font-size: 11px; font-weight: 700; border-radius: 20px; color: #fff; line-height: 1.4; }
.badge-新品 { background: #67c23a; }
.badge-热卖 { background: #e6a23c; }
.badge-优惠 { background: #f56c6c; }
.badge-推荐 { background: #409eff; }
.prod-card-body { padding: 16px 20px 20px; display: flex; flex-direction: column; flex: 1; min-height: 150px; }
.prod-card-name { font-size: 18px; font-weight: 600; color: #1a1a2e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; transition: color 0.2s; }
.prod-card:hover .prod-card-name { color: #409eff; }
.chat-dark-mode .prod-card-name { color: #e0e0e0; }
.prod-card-desc { font-size: 14px; color: #999; margin-top: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.5; flex: 1; }
.prod-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 12px; }
.prod-card-price { font-size: 18px; color: #e6a23c; font-weight: 700; }
.prod-card-sales { font-size: 12px; color: #999; }
.prod-card-cat { font-size: 12px; color: #409eff; background: #eef4ff; padding: 2px 10px; border-radius: 20px; display: inline-block; margin-top: 10px; align-self: flex-start; }
.chat-dark-mode .prod-card-cat { background: #1a2a3e; }

/* ── 商家卡片 ── */
.discover-merchant-card { border: 1px solid #e8e8e8; border-radius: 12px; padding: 16px; margin-bottom: 16px; background: #fff; transition: all 0.2s; }
.chat-dark-mode .discover-merchant-card { border-color: #2a2a3e; background: #1a1a2e; }
.discover-merchant-card:hover { border-color: #409eff; box-shadow: 0 2px 12px rgba(64,158,255,0.1); }
.discover-mch-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.discover-mch-avatar { width: 48px; height: 48px; border-radius: 50%; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #409eff, #337ecc); color: #fff; font-size: 20px; box-shadow: 0 2px 8px rgba(64,158,255,0.3); }
.discover-mch-img { width: 100%; height: 100%; object-fit: cover; }
.discover-mch-info { flex: 1; min-width: 0; }
.discover-mch-name { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.chat-dark-mode .discover-mch-name { color: #e0e0e0; }
.discover-mch-desc { font-size: 12px; color: #999; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.discover-mch-meta { display: flex; align-items: center; gap: 12px; font-size: 12px; color: #999; margin-top: 4px; }
.discover-mch-prod-count { color: #409eff; font-weight: 500; }
.discover-mch-products { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; padding-top: 14px; border-top: 1px solid #f0f0f0; }
.chat-dark-mode .discover-mch-products { border-top-color: #2a2a3e; }
.discover-mch-prod-card { border-radius: 10px; overflow: hidden; border: 1px solid #eee; transition: all 0.25s; background: #fff; }
.chat-dark-mode .discover-mch-prod-card { border-color: #2a2a3e; background: #16213e; }
.discover-mch-prod-card:hover { border-color: #409eff; box-shadow: 0 4px 16px rgba(64,158,255,0.15); transform: translateY(-2px); }
.discover-mch-prod-link { display: flex; flex-direction: column; text-decoration: none; color: inherit; height: 100%; }
.discover-mch-prod-img-wrap { aspect-ratio: 16/9; background: linear-gradient(135deg, #f5f7fa, #e8ecf1); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.chat-dark-mode .discover-mch-prod-img-wrap { background: linear-gradient(135deg, #1a1a2e, #2a2a3e); }
.discover-mch-prod-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
.discover-mch-prod-card:hover .discover-mch-prod-img { transform: scale(1.05); }
.discover-mch-prod-placeholder { font-size: 32px; opacity: 0.3; }
.discover-mch-prod-info { padding: 10px 12px; display: flex; flex-direction: column; flex: 1; }
.discover-mch-prod-name { font-size: 13px; font-weight: 600; color: #1a1a2e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chat-dark-mode .discover-mch-prod-name { color: #e0e0e0; }
.discover-mch-prod-desc { font-size: 11px; color: #999; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; }
.discover-mch-prod-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 8px; }
.discover-mch-prod-price { font-size: 16px; color: #e6a23c; font-weight: 700; }
.discover-mch-prod-link-text { font-size: 11px; color: #409eff; }

/* ── 审核卡片 ── */
.review-card { padding: 16px; margin: 8px 12px; border-radius: 8px; border: 1px solid #e6a23c; background: #fffbe6; }
.chat-dark-mode .review-card { border-color: #e6a23c; background: #2a2a1e; }
.review-card-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.review-card-title { font-size: 15px; font-weight: 600; flex: 1; }
.review-card-meta { display: flex; gap: 16px; font-size: 12px; color: #999; margin-bottom: 8px; }
.review-card-preview { font-size: 13px; color: #666; margin-bottom: 12px; line-height: 1.6; }
.chat-dark-mode .review-card-preview { color: #999; }
.review-card-actions { display: flex; gap: 8px; }
/* 审核统计条 */
.review-stats-bar { display: flex; gap: 8px; padding: 10px 12px; margin: 4px 8px; border-radius: 8px; background: #f5f7fa; }
.chat-dark-mode .review-stats-bar { background: #1a1a2e; }
.review-stat-item { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 6px; border-radius: 6px; background: #fff; }
.chat-dark-mode .review-stat-item { background: #16213e; }
.review-stat-num { font-size: 18px; font-weight: 700; }
.review-stat-label { font-size: 11px; color: #999; margin-top: 2px; }
.review-stat-pending { color: #e6a23c; }
.review-stat-approved { color: #67c23a; }
.review-stat-rejected { color: #f56c6c; }
.chat-header-left { display: flex; align-items: center; gap: 4px; }
.chat-title { font-size: 16px; font-weight: 600; }
.chat-actions { display: flex; gap: 4px; }
.back-btn { margin-right: 4px; }
.sidebar-tabs :deep(.el-tabs__header) { margin: 0; }
.sidebar-tabs :deep(.el-tabs__nav-wrap) { padding: 0 12px; }
.sidebar-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #e4e7ed; }
.sidebar-header h3 { margin: 0; font-size: 15px; }
.sidebar-search { padding: 8px 12px; border-bottom: 1px solid #e4e7ed; }
.sidebar-search :deep(.el-input__wrapper) { background: #fff; }
.conversation-list { flex: 1; overflow-y: auto; }

/* ── 我的面板 ── */
.my-profile-actions {
    display: flex;
    gap: 8px;
    padding: 10px 16px;
    border-bottom: 1px solid #f0f0f0;
}
.my-profile-content {
    padding: 8px 16px;
}
.my-section-header {
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid #f0f0f0;
}
.daily-task-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    font-size: 13px;
}
.daily-task-info {
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 0;
    flex: 1;
}
.daily-task-icon { font-size: 14px; flex-shrink: 0; }
.daily-task-label { flex: 1; }
.task-done { text-decoration: line-through; color: #909399; }
.daily-task-reward { font-size: 11px; color: #e6a23c; font-weight: 600; flex-shrink: 0; }
.daily-task-bar-wrap {
    width: 60px;
    height: 6px;
    background: #f0f0f0;
    border-radius: 3px;
    overflow: hidden;
    flex-shrink: 0;
}
.daily-task-bar {
    height: 100%;
    background: linear-gradient(90deg, #e6a23c, #f56c6c);
    border-radius: 3px;
    transition: width 0.3s;
}
.daily-task-progress { font-size: 11px; color: #909399; min-width: 28px; text-align: right; }
.daily-summary {
    text-align: center;
    font-size: 12px;
    color: #909399;
    margin-top: 10px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
}
.daily-summary strong { color: #e6a23c; }
.social-menu-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s;
}
.social-menu-item:hover { background: #f5f7fa; }
.social-menu-icon { font-size: 16px; }
.social-menu-label { flex: 1; font-size: 13px; color: #303133; }
.social-menu-badge {
    font-size: 11px;
    background: #f56c6c;
    color: #fff;
    padding: 1px 6px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}
.chat-dark-mode .social-menu-item:hover { background: #2a2a3e; }
.chat-dark-mode .my-section-header { color: #e0e0e0; border-color: #2a2a3e; }
.chat-dark-mode .daily-task-bar-wrap { background: #2a2a3e; }
.chat-dark-mode .daily-summary { border-color: #2a2a3e; }
.conv-item { display: flex; align-items: center; padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; gap: 10px; }
.conv-checkbox { margin-right: 0; flex-shrink: 0; }

/* 批量归档 */
.batch-archive-bar { display: flex; align-items: center; justify-content: space-between; padding: 6px 12px; background: #fff7e6; border-bottom: 1px solid #ffe58f; font-size: 13px; }
.batch-count { color: #d46b08; font-weight: 500; }
.batch-actions { display: flex; gap: 6px; }
.batch-archive-trigger { text-align: center; padding: 4px; }
.chat-dark-mode .batch-archive-bar { background: #2a1a00; border-color: #5a3a00; }
.chat-dark-mode .batch-count { color: #ffa940; }

/* 隐藏会话/私密空间 */
.hidden-conv-list { max-height: 400px; overflow-y: auto; }
.hidden-conv-item { display: flex; align-items: center; padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; gap: 10px; }
.hidden-conv-item:hover { background: #f5f7fa; }
.chat-dark-mode .hidden-conv-item { border-color: #2a2a4a; }
.chat-dark-mode .hidden-conv-item:hover { background: #1a1a3a; }
.conv-item:hover { background: #f0f5ff; }
.conv-item.active { background: #e6f0ff; }
.conv-avatar-wrap { position: relative; flex-shrink: 0; }
.conv-avatar { width: 40px; height: 40px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 600; }
.conv-avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.online-dot { position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; border-radius: 50%; background: #52c41a; border: 2px solid #fff; }
.conv-info { flex: 1; min-width: 0; }
.conv-top { display: flex; justify-content: space-between; align-items: center; }
.conv-name { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conv-time { font-size: 11px; color: #999; flex-shrink: 0; }
.conv-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 2px; }
.conv-last { font-size: 12px; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.unread-badge { background: #f56c6c; color: #fff; font-size: 11px; padding: 0 6px; border-radius: 10px; line-height: 18px; flex-shrink: 0; margin-left: 4px; }
.empty-chat { display: flex; align-items: center; justify-content: center; padding: 32px; }
.messages-area { flex: 1; overflow-y: auto; padding: 16px 20px; }
.msg-item { display: flex; margin-bottom: 12px; position: relative; justify-content: flex-start; flex-direction: row; }
.msg-self { flex-direction: row-reverse; }
.msg-avatar { width: 36px; height: 36px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; margin-right: 8px; flex-shrink: 0; overflow: hidden; }
.msg-avatar-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.msg-self .msg-avatar { margin-right: 0; margin-left: 8px; background: #67c23a; }
.msg-bubble { max-width: 60%; padding: 8px 12px; border-radius: 8px; background: #fff; position: relative; border: 1px solid #e4e7ed; }
.msg-bubble::before { content: ''; position: absolute; top: 12px; width: 0; height: 0; border: 7px solid transparent; }
.msg-item:not(.msg-self) .msg-bubble { background: #fff; border-color: #e4e7ed; }
.msg-item:not(.msg-self) .msg-bubble::before { left: -7px; border-right-color: #e4e7ed; border-left: 0; }
.msg-item:not(.msg-self) .msg-bubble::after { content: ''; position: absolute; top: 13px; left: -5px; width: 0; height: 0; border: 6px solid transparent; border-right-color: #fff; border-left: 0; }
.msg-self .msg-bubble { background: #95ec69; border-color: #95ec69; }
.msg-self .msg-bubble::before { right: -7px; border-left-color: #95ec69; border-right: 0; }
.msg-self .msg-bubble::after { content: ''; position: absolute; top: 13px; right: -5px; width: 0; height: 0; border: 6px solid transparent; border-left-color: #95ec69; border-right: 0; }
.chat-dark-mode .msg-item:not(.msg-self) .msg-bubble { background: #2a2a3e; border-color: #3a3a4e; }
.chat-dark-mode .msg-item:not(.msg-self) .msg-bubble::after { border-right-color: #2a2a3e; }
.chat-dark-mode .msg-self .msg-bubble { background: #1a7a2e; border-color: #1a7a2e; }
.chat-dark-mode .msg-self .msg-bubble::before { border-left-color: #1a7a2e; }
.chat-dark-mode .msg-self .msg-bubble::after { border-left-color: #1a7a2e; }
.msg-sender { font-size: 12px; color: #888; margin-bottom: 2px; padding: 0 4px; text-align: left; display: block; }
.msg-self .msg-sender { text-align: right; display: block; }
.msg-text { font-size: 14px; line-height: 1.6; word-break: break-word; }
/* 撤回消息去掉特殊样式 */
.msg-recalled { background: #f5f5f5 !important; border-color: #f5f5f5 !important; color: #999 !important; cursor: default; }
.msg-recalled::before, .msg-recalled::after { display: none !important; }

/* 长文折叠 */
.msg-text-wrap { position: relative; }
.msg-text-wrap.msg-collapsed .msg-text { max-height: 120px; overflow: hidden; }
.msg-text-wrap.msg-collapsed::after { content: ''; position: absolute; bottom: 32px; left: 0; right: 0; height: 40px; background: linear-gradient(transparent, var(--msg-bg, #fff)); pointer-events: none; }
.chat-dark-mode .msg-text-wrap.msg-collapsed::after { --msg-bg: #16213e; }
.expand-btn { display: flex; align-items: center; gap: 4px; margin-top: 4px; padding: 2px 10px; font-size: 12px; color: #409eff; background: none; border: 1px solid #e4e7ed; border-radius: 12px; cursor: pointer; transition: all .2s; }
.expand-btn:hover { background: #ecf5ff; border-color: #409eff; }
.expand-btn.expanded { color: #909399; }
.chat-dark-mode .expand-btn { border-color: #2a2a4a; color: #66b1ff; }
.chat-dark-mode .expand-btn:hover { background: #1a2a4a; }

/* Spoiler */
.spoiler-text { background: #303133; color: #303133; border-radius: 4px; padding: 0 4px; cursor: pointer; transition: all .3s; user-select: none; }
.spoiler-text:hover { background: #505153; }
.spoiler-text.spoiler-revealed { background: transparent; color: inherit; cursor: default; user-select: text; }
.chat-dark-mode .spoiler-text { background: #555; color: #555; }
.chat-dark-mode .spoiler-text:hover { background: #777; }
.chat-dark-mode .spoiler-text.spoiler-revealed { background: transparent; color: inherit; }
.msg-text :deep(code) { background: #f0f0f0; padding: 1px 5px; border-radius: 3px; font-size: 13px; font-family: Consolas, monospace; }
.msg-text :deep(pre) { background: #f5f5f5; padding: 10px; border-radius: 6px; overflow-x: auto; margin: 6px 0; }
.msg-text :deep(pre code) { background: none; padding: 0; }
.msg-text :deep(blockquote) { border-left: 3px solid #409eff; padding-left: 10px; margin: 4px 0; color: #666; }
.msg-text :deep(table) { border-collapse: collapse; margin: 4px 0; font-size: 13px; width: 100%; }
.msg-text :deep(td) { border: 1px solid #e4e7ed; padding: 4px 8px; }
.msg-text :deep(hr) { border: none; border-top: 1px solid #e4e7ed; margin: 8px 0; }
.msg-text :deep(h2), .msg-text :deep(h3), .msg-text :deep(h4) { margin: 8px 0 4px; }
.msg-text :deep(ul), .msg-text :deep(ol) { padding-left: 20px; margin: 4px 0; }
.msg-text :deep(a) { color: #409eff; word-break: break-all; }
.msg-translated { font-size: 13px; color: #555; margin-top: 2px; padding: 4px 6px; background: #f0f7ff; border-radius: 4px; }
.chat-dark-mode .msg-translated { background: #1a2a4a; color: #ccc; }
.msg-status-icon { font-size: 11px; margin-right: 3px; font-weight: 600; }
.msg-time { font-size: 11px; color: #999; margin-top: 2px; text-align: right; }
.msg-encrypted { font-size: 10px; display: inline; margin-right: 4px; opacity: 0.6; }
.msg-actions { position: absolute; top: 0; right: -36px; display: none; flex-direction: column; gap: 2px; }
.msg-item:hover .msg-actions { display: flex; }
.msg-self .msg-actions { right: auto; left: -36px; }
.msg-actions .el-button { padding: 2px; min-height: auto; }
.msg-reply { font-size: 12px; color: #666; padding: 4px 8px; background: rgba(0,0,0,0.03); border-radius: 4px; margin-bottom: 4px; cursor: pointer; border-left: 2px solid #409eff; }
.pinned-banner { background: #fdf6ec; border: 1px solid #faecd8; border-radius: 6px; padding: 8px 12px; margin-bottom: 8px; font-size: 13px; }
.pinned-header { font-weight: 600; color: #e6a23c; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
.pinned-count { font-size: 11px; background: #e6a23c; color: #fff; border-radius: 10px; padding: 0 6px; }
.pinned-item { padding: 2px 0; cursor: pointer; display: flex; gap: 4px; }
.pinned-item:hover { color: #409eff; }
.pinned-sender { color: #409eff; font-weight: 500; white-space: nowrap; }
.pinned-content { color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pinned-more { color: #909399; font-size: 12px; margin-top: 2px; }
.reply-sender { font-weight: 600; color: #409eff; font-size: 11px; margin-bottom: 2px; }
.reply-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.msg-highlight { animation: msgHighlight 2s ease-out; }
@keyframes msgHighlight { 0% { background-color: rgba(64,158,255,0.2); } 100% { background-color: transparent; } }
.reply-preview { display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: #f0f7ff; border-radius: 6px; margin-bottom: 6px; font-size: 13px; }
.reply-preview-content { flex: 1; overflow: hidden; }
.reply-preview-label { font-weight: 600; color: #409eff; margin-right: 4px; }
.reply-preview-text { color: #606266; }

/* 斜杠命令建议 */
.slash-suggestions { background: #fff; border: 1px solid #e4e7ed; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.1); margin-bottom: 4px; max-height: 240px; overflow-y: auto; }
.slash-item { display: flex; align-items: center; gap: 8px; padding: 8px 14px; cursor: pointer; font-size: 13px; transition: background .15s; }
.slash-item:not(:last-child) { border-bottom: 1px solid #f5f5f5; }
.slash-item:hover, .slash-active { background: #ecf5ff; }
.slash-cmd { font-family: monospace; font-weight: 600; color: #409eff; min-width: 100px; }
.slash-desc { color: #909399; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.chat-dark-mode .slash-suggestions { background: #1e1e3a; border-color: #2a2a4a; }
.chat-dark-mode .slash-item { border-bottom-color: #2a2a4a; }
.chat-dark-mode .slash-item:hover, .chat-dark-mode .slash-active { background: #1a2a4a; }
.chat-dark-mode .slash-cmd { color: #66b1ff; }
.chat-dark-mode .slash-desc { color: #888; }
.msg-recalled { opacity: 0.6; }
.msg-recalled-text { font-style: italic; color: #999; font-size: 13px; padding: 4px 0; text-align: center; }
.msg-priority-badge { font-size: 11px; color: #f56c6c; font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
.msg-priority-medium { color: #e6a23c; }
.msg-high-priority { border-left: 3px solid #f56c6c; }
.msg-attachments { margin-top: 4px; }
.msg-attachment-item { margin: 2px 0; }
.attach-thumb { max-width: 120px; max-height: 80px; border-radius: 4px; cursor: pointer; margin-top: 4px; border: 1px solid #e4e7ed; }
.msg-image { margin: 4px 0; }
.chat-image { max-width: 240px; max-height: 200px; border-radius: 8px; cursor: pointer; border: 1px solid #e4e7ed; display: block; }
.chat-input-area { border-top: 1px solid #e4e7ed; padding: 12px 20px; }
/* THREAD: 话题面板 */
.thread-panel { width: 320px; border-left: 1px solid #e4e7ed; display: flex; flex-direction: column; background: #fafafa; flex-shrink: 0; }
.thread-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #e4e7ed; font-weight: 600; font-size: 14px; }
.thread-parent-msg { padding: 12px 16px; border-bottom: 1px solid #e4e7ed; background: #fff; }
.thread-parent-sender { font-size: 12px; color: #409eff; font-weight: 600; margin-bottom: 4px; }
.thread-parent-content { font-size: 13px; color: #333; word-break: break-word; }
.thread-replies { flex: 1; overflow-y: auto; padding: 8px 16px; }
.thread-reply-item { display: flex; gap: 8px; margin-bottom: 16px; }
.thread-reply-avatar { width: 30px; height: 30px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; flex-shrink: 0; }
.thread-reply-body { flex: 1; min-width: 0; }
.thread-reply-sender { font-size: 12px; font-weight: 600; color: #333; }
.thread-reply-time { font-size: 11px; color: #909399; font-weight: 400; margin-left: 6px; }
.thread-reply-text { font-size: 13px; color: #555; margin-top: 2px; word-break: break-word; }
.thread-empty { text-align: center; padding: 40px 0; color: #909399; font-size: 13px; }
.thread-loading { text-align: center; padding: 20px; }
.thread-input-row { display: flex; gap: 6px; padding: 8px 12px; border-top: 1px solid #e4e7ed; background: #fff; }
.input-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.input-action-left { display: flex; align-items: center; gap: 4px; }
.text-muted { font-size: 12px; color: #999; margin-left: 8px; }
.reply-preview { padding: 6px 10px; background: #f5f7fa; border-radius: 4px; margin-bottom: 6px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666; }
.pending-attachments { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
.pending-att-item { display: flex; align-items: center; gap: 4px; padding: 4px 8px; background: #f5f7fa; border-radius: 4px; font-size: 12px; }
.tags-bar { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px 16px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
.tag { display: inline-block; font-size: 11px; color: #fff; padding: 1px 8px; border-radius: 10px; line-height: 20px; }
.load-more { text-align: center; padding: 8px; }
.msg-reactions { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 4px; }
.reaction-badge { display: inline-flex; align-items: center; gap: 2px; font-size: 12px; padding: 1px 6px; border-radius: 10px; background: #f0f0f0; cursor: pointer; border: 1px solid transparent; }
.reaction-badge:hover { border-color: #409eff; }
.reaction-badge.reaction-me { background: #e6f0ff; border-color: #409eff; }
.reaction-add { font-size: 14px; cursor: pointer; opacity: 0.5; padding: 0 4px; }
.reaction-add:hover { opacity: 1; }
.emoji-picker { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px; }
.emoji-option { font-size: 22px; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s; }
.emoji-option:hover { background: #f0f5ff; }
.edited-badge { font-size: 11px; color: #999; font-style: italic; margin-left: 4px; }
.msg-read-status { font-size: 11px; color: #999; text-align: right; margin-top: 2px; display: flex; align-items: center; justify-content: flex-end; gap: 2px; }
.msg-self .msg-read-status { color: #409eff; }
.mention-at { color: #409eff; font-weight: 500; cursor: pointer; }
.mention-at:hover { text-decoration: underline; }
.link-card { display: flex; gap: 8px; margin-top: 6px; padding: 8px; border: 1px solid #e8e8e8; border-radius: 6px; cursor: pointer; transition: background 0.2s; max-width: 320px; background: rgba(255,255,255,0.5); }
.link-card:hover { background: rgba(64,158,255,0.05); border-color: #c0d9ff; }
.link-card-img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.link-card-body { min-width: 0; flex: 1; }
.link-card-title { font-size: 13px; font-weight: 600; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; color: #1a1a1a; }
.link-card-desc { font-size: 12px; color: #666; line-height: 1.4; margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.link-card-site { font-size: 11px; color: #999; margin-top: 2px; }
/* 卡片消息 */
.msg-card { max-width: 300px; border-radius: 8px; overflow: hidden; border: 1px solid #e8e8e8; background: #fff; margin-top: 4px; }
.card-img-link { display: block; text-decoration: none; }
.card-img-link:hover { opacity: 0.9; }
.card-img { width: 100%; height: 140px; object-fit: cover; background: #f5f5f5; }
.card-title-link { text-decoration: none; color: inherit; display: block; }
.card-title-link:hover { color: #409eff; }
.card-body { padding: 10px; }
.card-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; color: #1a1a1a; }
.card-desc { font-size: 12px; color: #666; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 4px; }
.card-price { font-size: 16px; font-weight: 700; color: #f56c6c; margin-bottom: 6px; }
.card-field { display: flex; justify-content: space-between; align-items: center; padding: 3px 0; font-size: 13px; }
.card-label { color: #999; }
.card-value { color: #333; font-weight: 500; }
.card-actions { display: flex; gap: 6px; margin-top: 8px; }
.card-btn { display: inline-block; padding: 5px 12px; border-radius: 4px; font-size: 12px; text-decoration: none; text-align: center; flex: 1; cursor: pointer; border: none; }
.card-btn.primary { background: #409eff; color: #fff; }
.card-btn.primary:hover { background: #66b1ff; }
.card-btn.default { background: #f0f0f0; color: #333; border: 1px solid #ddd; }
.card-btn.default:hover { background: #e4e7ed; }
.card-btn.danger { background: #f56c6c; color: #fff; }
.card-btn.danger:hover { background: #f78989; }
.card-btn.success { background: #67c23a; color: #fff; }
.card-btn.success:hover { background: #85ce61; }
.card-btn.text { background: transparent; color: #409eff; border: none; }
.card-btn.text:hover { background: #ecf5ff; }
.card-product_card { border-top: 3px solid #409eff; }
.card-order_card { border-top: 3px solid #67c23a; }
.card-approval_card { border-top: 3px solid #e6a23c; }
.card-article_card { border-top: 3px solid #909399; }
.card-coupon_card { border-top: 3px solid #e6a23c; }
.card-todo_card { border-left: 3px solid #409eff; }
.card-meta { font-size: 11px; color: #909399; margin-top: 4px; }
.coupon-body { text-align: center; padding: 16px; }
.coupon-discount { font-size: 32px; font-weight: 800; color: #e6a23c; line-height: 1.2; }
.self-chat-entry { display: flex; align-items: center; padding: 8px 16px; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #409eff; transition: background 0.2s; }
.self-chat-entry:hover { background: #f0f7ff; }
.msg-voice { display: flex; align-items: center; cursor: pointer; padding: 6px 10px; border-radius: 8px; background: #f5f7fa; min-width: 160px; max-width: 240px; }
.msg-voice-self { background: #e6f0ff; }
.voice-wave { display: flex; align-items: center; gap: 1px; flex: 1; height: 30px; }
.voice-bar { width: 3px; background: #409eff; border-radius: 2px; min-height: 4px; }
.msg-voice-wrap { display: flex; flex-direction: column; gap: 4px; }
.msg-voice-actions { display: flex; align-items: center; gap: 4px; padding-left: 4px; }
.voice-transcript { display: flex; align-items: flex-start; gap: 4px; font-size: 12px; color: #606266; background: #f5f7fa; padding: 6px 10px; border-radius: 8px; max-width: 280px; line-height: 1.5; word-break: break-all; }
.voice-duration { font-size: 12px; color: #999; margin-left: 6px; white-space: nowrap; }
.msg-contact-card { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; border: 1px solid #e4e7ed; cursor: pointer; background: #fff; min-width: 140px; }
.msg-contact-card:hover { border-color: #409eff; background: #f0f7ff; }
.contact-card-avatar { width: 36px; height: 36px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 600; flex-shrink: 0; }
.contact-card-name { font-size: 14px; font-weight: 600; color: #1a1a1a; }
.contact-card-hint { font-size: 11px; color: #999; }
.msg-location { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 8px; border: 1px solid #e4e7ed; cursor: pointer; background: #fff; min-width: 160px; }
.msg-location:hover { border-color: #409eff; background: #f0f7ff; }
.location-icon { font-size: 24px; }
.location-name { font-size: 14px; font-weight: 600; color: #1a1a1a; }
.location-coords { font-size: 11px; color: #999; }
.folder-bar { display: flex; align-items: center; gap: 4px; padding: 4px 12px; border-bottom: 1px solid #f0f0f0; }
.folder-item { display: flex; align-items: center; margin-bottom: 8px; }
.folder-menu-item { padding: 6px 12px; cursor: pointer; font-size: 13px; }
.folder-menu-item:hover { background: #f0f5ff; }
.friend-filter-bar { display: flex; align-items: center; gap: 4px; padding: 4px 12px; border-bottom: 1px solid #f0f0f0; }
.friend-group-item { display: flex; align-items: center; margin-bottom: 8px; }
.friend-group-tag { background: #e6f0ff; color: #409eff; font-size: 11px; padding: 0 6px; border-radius: 4px; }
.notif-item .notif-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.notif-item.notif-unread { background: #f0f7ff; }
.unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #409eff; flex-shrink: 0; }
.global-search-panel { padding: 8px 12px; border-bottom: 1px solid #e4e7ed; }
.search-results { max-height: 240px; overflow-y: auto; margin-top: 4px; }
.search-result-item { padding: 8px; cursor: pointer; border-radius: 4px; border-bottom: 1px solid #f0f0f0; }
.search-result-item:hover { background: #f0f5ff; }
.search-result-conv { font-size: 11px; color: #999; }
.search-result-content { font-size: 13px; margin: 2px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.search-result-time { font-size: 11px; color: #999; }
.search-loading { text-align: center; padding: 12px; font-size: 13px; color: #999; }
.canned-panel { position: absolute; bottom: 100%; left: 0; right: 0; height: 200px; background: #fff; border: 1px solid #e4e7ed; border-radius: 6px; display: flex; flex-direction: column; z-index: 10; }
.canned-header { display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 13px; }
.canned-categories { padding: 4px 8px; border-bottom: 1px solid #f0f0f0; overflow-x: auto; }
.canned-list { flex: 1; overflow-y: auto; }
.canned-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.canned-item:hover { background: #f0f5ff; }
.canned-title { font-size: 13px; font-weight: 500; }
.canned-preview { font-size: 12px; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.conv-tags-bar { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px 16px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
.conv-search-bar { display: flex; align-items: center; gap: 4px; padding: 4px 16px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
.conv-search-popover { display: flex; flex-direction: column; gap: 8px; }
.conv-search-filters { display: flex; flex-direction: column; gap: 6px; }
.conv-search-actions { display: flex; gap: 6px; justify-content: flex-end; }
.conv-search-results { max-height: 300px; overflow-y: auto; border-top: 1px solid #f0f0f0; padding-top: 6px; }
.conv-search-item { padding: 6px 8px; cursor: pointer; border-radius: 4px; }
.conv-search-item:hover { background: #f0f5ff; }
.conv-search-sender { font-size: 12px; color: #409eff; font-weight: 600; }
.conv-search-text { font-size: 13px; word-break: break-word; }
.conv-search-time { font-size: 11px; color: #999; margin-top: 2px; }
.conv-search-empty { padding: 20px; text-align: center; color: #999; font-size: 13px; }
.conv-tag { display: inline-block; font-size: 11px; color: #fff; padding: 1px 8px; border-radius: 10px; line-height: 20px; }
.tag-assign-panel { border-bottom: 1px solid #e4e7ed; background: #fafafa; }

/* 无障碍面板 */
.a11y-panel { border-bottom: 1px solid #e4e7ed; background: #f0f9eb; padding: 10px 14px; }
.a11y-panel-header { display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; margin-bottom: 8px; }
.a11y-panel-body { display: flex; flex-direction: column; gap: 6px; }
.a11y-row { display: flex; align-items: center; gap: 8px; font-size: 13px; flex-wrap: wrap; }
.a11y-label { min-width: 90px; color: #606266; }
.a11y-hint { font-size: 11px; color: #909399; width: 100%; }
.a11y-actions { display: flex; justify-content: space-between; align-items: center; }
.a11y-more-link { font-size: 12px; color: #409eff; text-decoration: none; }
.alt-text-btn { position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,0.5); color: #fff; border: none; border-radius: 4px; padding: 2px 6px; font-size: 12px; cursor: pointer; opacity: 0; transition: opacity .2s; }
.msg-image:hover .alt-text-btn { opacity: 1; }
.tag-assign-header { display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; font-size: 13px; font-weight: 600; }
.tag-list { padding: 4px 12px 8px; max-height: 120px; overflow-y: auto; }
.tag-label-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
.tag-assign-actions { padding: 4px 12px 8px; text-align: right; }
.ai-panel { position: absolute; bottom: 100%; left: 0; right: 0; height: 280px; background: #fff; border: 1px solid #e4e7ed; border-radius: 6px; display: flex; flex-direction: column; z-index: 10; }
.sticker-panel { position: absolute; bottom: 100%; left: 0; right: 0; height: 260px; background: #fff; border: 1px solid #e4e7ed; border-radius: 6px; display: flex; flex-direction: column; z-index: 10; }
.sticker-header { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 6px 12px; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 13px; }
.sticker-grid { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-wrap: wrap; gap: 4px; align-content: flex-start; }
.emoji-grid .emoji-item { font-size: 24px; cursor: pointer; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
.emoji-grid .emoji-item:hover { background: #f0f5ff; transform: scale(1.2); }
.sticker-pack { width: 100%; margin-bottom: 8px; }
.sticker-pack-name { font-size: 12px; color: #666; margin-bottom: 4px; }
.sticker-pack-items { display: flex; flex-wrap: wrap; gap: 4px; }
.sticker-item { width: 72px; height: 72px; object-fit: contain; cursor: pointer; border-radius: 4px; border: 1px solid #f0f0f0; }
.sticker-item:hover { border-color: #409eff; transform: scale(1.05); }
.custom-emoji-item { display: flex; flex-direction: column; align-items: center; cursor: pointer; padding: 4px; border-radius: 6px; transition: background .2s; }
.custom-emoji-item:hover { background: #ecf5ff; }
.custom-emoji-label { font-size: 11px; color: #909399; margin-top: 2px; max-width: 72px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.custom-emoji-inline { display: inline-block; width: 22px; height: 22px; vertical-align: middle; margin: 0 1px; object-fit: contain; }
.gif-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.gif-search-row { display: flex; gap: 6px; padding: 8px; }
.gif-grid { flex: 1; overflow-y: auto; padding: 8px; display: flex; flex-wrap: wrap; gap: 4px; align-content: flex-start; }
.gif-item { width: 120px; height: 120px; object-fit: cover; cursor: pointer; border-radius: 4px; }
.gif-item:hover { transform: scale(1.05); box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
.msg-sticker { text-align: center; padding: 4px; }
.msg-sticker .sticker-img { max-width: 150px; max-height: 150px; object-fit: contain; }

/* 文件消息 */
.msg-file { display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: #f5f7fa; border-radius: 8px; cursor: pointer; min-width: 200px; max-width: 280px; transition: background .2s; }
.msg-file:hover { background: #eef0f4; }
.msg-file .file-icon { font-size: 28px; line-height: 1; flex-shrink: 0; }
.msg-file .file-info { flex: 1; min-width: 0; }
.msg-file .file-name { font-size: 13px; font-weight: 500; color: #303133; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.msg-file .file-meta { display: flex; align-items: center; gap: 8px; margin-top: 2px; }
.msg-file .file-size { font-size: 12px; color: #909399; }
.msg-file .file-action { font-size: 12px; color: #409eff; }

.msg-high-priority .msg-file { border-left: 3px solid #f56c6c; }

/* 附件预览标签 */
.attach-thumb { max-width: 80px; max-height: 60px; border-radius: 4px; object-fit: cover; display: block; }
.attach-preview-label { display: block; text-align: center; font-size: 11px; color: #409eff; margin-top: 2px; }
.msg-forward { background: #f5f7fa; border-radius: 8px; padding: 10px; font-size: 13px; max-width: 280px; }
.forward-header { font-weight: 600; font-size: 12px; color: #606266; margin-bottom: 6px; }
.forward-item { padding: 2px 0; display: flex; gap: 4px; }
.forward-item-sender { color: #409eff; font-weight: 500; white-space: nowrap; }
.forward-item-content { color: #606266; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.forward-count { color: #909399; font-size: 11px; margin-top: 4px; }
.forward-single { display: flex; flex-direction: column; gap: 4px; }
.forward-origin { font-size: 11px; color: #909399; }
.forward-text { color: #303133; }
.ai-header { display: flex; justify-content: space-between; align-items: center; padding: 6px 12px; border-bottom: 1px solid #f0f0f0; font-weight: 600; font-size: 13px; }
.ai-messages { flex: 1; overflow-y: auto; padding: 8px 12px; }
.ai-message { margin-bottom: 8px; font-size: 13px; display: flex; gap: 6px; }
.ai-message .ai-role-tag { display: inline-block; padding: 0 6px; background: #ecf5ff; color: #409eff; border-radius: 4px; font-size: 12px; white-space: nowrap; font-weight: 600; align-self: flex-start; }
.ai-message.user .ai-role-tag { background: #f0f9eb; color: #67c23a; }
.ai-message .ai-msg-content { flex: 1; word-break: break-word; }
.ai-streaming-indicator { display: flex; align-items: center; padding: 8px; font-size: 13px; color: #909399; font-style: italic; }
.ai-input-row { display: flex; gap: 6px; padding: 6px 12px; border-top: 1px solid #f0f0f0; }
.pending-req-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.req-user-avatar { width: 40px; height: 40px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 700; flex-shrink: 0; }
.req-user-name { font-size: 14px; font-weight: 600; }
.req-user-email { font-size: 11px; color: #909399; }
.add-friend-info { flex: 1; font-size: 14px; }
.online-status-label { font-size: 12px; margin-right: 4px; }
.typing-indicator { padding: 4px 20px; font-size: 12px; color: #999; font-style: italic; }
.smart-replies-bar { display: flex; align-items: center; gap: 4px; padding: 4px 16px; border-top: 1px solid #f0f0f0; background: #fafafa; flex-wrap: wrap; }
.smart-replies-label { font-size: 12px; color: #999; margin-right: 4px; white-space: nowrap; }
.chat-dark-mode .smart-replies-bar { border-color: #2a2a4a; background: #16213e; }
.poll-option-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.poll-rank-badge { width: 22px; height: 22px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; flex-shrink: 0; }
.poll-rank-hint { font-size: 12px; color: #909399; margin: 4px 0; }
.user-option { display: flex; flex-direction: column; }
.user-opt-name { font-weight: 600; font-size: 14px; }
.user-opt-email { font-size: 11px; color: #909399; }
.dialog-hint { font-size: 12px; color: #909399; margin-top: 6px; }
.poll-result-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
.poll-result-label { width: 80px; font-size: 13px; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.poll-result-bar-wrap { flex: 1; height: 20px; background: #f0f0f0; border-radius: 10px; overflow: hidden; }
.poll-result-bar { height: 100%; background: #409eff; border-radius: 10px; transition: width 0.3s; min-width: 2px; }
.poll-result-num { width: 60px; font-size: 12px; color: #666; text-align: left; }
.empty-state { display: flex; align-items: center; justify-content: center; height: 100%; }
/* AI-014: Copilot 侧边栏 */
.copilot-fab { position: fixed; bottom: 100px; right: 30px; z-index: 1000; width: 48px; height: 48px; font-size: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.copilot-sidebar { position: fixed; top: 60px; right: 0; width: 340px; height: calc(100vh - 60px); background: #fff; border-left: 1px solid #e4e7ed; z-index: 999; display: flex; flex-direction: column; box-shadow: -4px 0 16px rgba(0,0,0,0.08); }
.copilot-header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: 15px; font-weight: 600; }
.copilot-actions { display: flex; flex-wrap: wrap; gap: 6px; padding: 12px 16px; border-bottom: 1px solid #f0f0f0; }
.copilot-content { flex: 1; overflow-y: auto; padding: 12px 16px; }
.copilot-loading { text-align: center; padding: 30px; color: #909399; }
.copilot-empty { text-align: center; padding: 40px 20px; color: #c0c4cc; font-size: 14px; }
.copilot-result-item { margin-bottom: 16px; padding: 12px; background: #f5f7fa; border-radius: 8px; }
.copilot-result-title { font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #303133; }
.copilot-result-body { font-size: 13px; color: #606266; white-space: pre-wrap; word-break: break-word; }
.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.3s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
/* AI-018: 分类栏 */
.category-bar { padding: 4px 8px; border-bottom: 1px solid #f0f0f0; }
.category-bar .el-radio-group { display: flex; flex-wrap: wrap; gap: 2px; }
.category-bar .el-radio-button { margin: 0; }
.category-bar .el-radio-button__inner { padding: 4px 8px; font-size: 12px; }
/* AI-015: 待办事项 */
.task-item { display: flex; align-items: center; gap: 8px; padding: 10px 0; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap; }
.task-title { flex: 1; font-size: 14px; }
.task-deadline { font-size: 12px; color: #e6a23c; white-space: nowrap; }
.task-assignee { font-size: 12px; color: #909399; white-space: nowrap; }
/* AI 好友 */
.ai-friend-section { margin-bottom: 4px; }
.ai-friend-header { display: flex; align-items: center; gap: 6px; padding: 8px 12px; font-size: 13px; font-weight: 600; color: #409eff; border-bottom: 1px solid #f0f0f0; }
.ai-friend-count { font-size: 11px; color: #999; font-weight: 400; }
.admin-ai-header { display: flex; justify-content: space-between; align-items: center; padding: 0 0 12px 0; }
.avatar-selector { width: 100%; }
.avatar-preview { margin-bottom: 8px; }
.avatar-img { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 2px solid #409eff; }
.avatar-options { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.avatar-option { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; cursor: pointer; border: 2px solid transparent; transition: all 0.2s; }
.avatar-option img { width: 100%; height: 100%; object-fit: cover; }
.avatar-option.selected { border-color: #409eff; box-shadow: 0 0 0 2px rgba(64,158,255,0.3); }
.avatar-option:hover { border-color: #a0cfff; }
.ai-friend-item { cursor: pointer; }
.ai-friend-item .conv-avatar-wrap { position: relative; }
.ai-badge { position: absolute; bottom: -4px; right: -4px; font-size: 12px; line-height: 1; }
.ai-category-tag { font-size: 11px; padding: 0 6px; background: #ecf5ff; color: #409eff; border-radius: 4px; }
.sidebar-divider { height: 1px; background: #f0f0f0; margin: 4px 0; }
.dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 8px 0; }
.dashboard-card { text-align: center; padding: 16px; background: #f5f7fa; border-radius: 8px; }
.dashboard-num { font-size: 28px; font-weight: 700; color: #409eff; line-height: 1.2; }
.dashboard-label { font-size: 13px; color: #666; margin-top: 4px; }
.dashboard-loading { text-align: center; padding: 40px; color: #999; }
.announcement-item { padding: 12px; border-bottom: 1px solid #f0f0f0; }
.announcement-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.announcement-title { font-weight: 600; font-size: 14px; color: #1a1a1a; }
.announcement-time { font-size: 12px; color: #999; }
.announcement-content { font-size: 13px; color: #333; line-height: 1.5; margin-bottom: 8px; white-space: pre-wrap; }
.announcement-footer { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #999; }
.announcement-detail-header { font-weight: 600; font-size: 15px; margin-bottom: 8px; }
.read-list-title { font-size: 13px; color: #666; margin-bottom: 6px; }
.invite-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
.invite-url { font-size: 13px; color: #409eff; word-break: break-all; }
.invite-meta { font-size: 12px; color: #999; margin-top: 2px; }
.msg-link { color: #409eff; word-break: break-all; }
.msg-link:hover { text-decoration: underline; }
.conv-folder-menu { position: fixed; z-index: 9999; background: #fff; border: 1px solid #e4e7ed; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); padding: 4px 0; min-width: 160px; }

/* 深色模式 */
.chat-dark-mode .user-chat-page { background: #1a1a2e; color: #e0e0e0; }
.chat-dark-mode .chat-sidebar { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .chat-main { background: #1a1a2e; }
.chat-dark-mode .chat-header { border-color: #2a2a4a; background: #16213e; }
.chat-dark-mode .chat-title { color: #e0e0e0; }
.chat-dark-mode .sidebar-tabs :deep(.el-tabs__item) { color: #999; }
.chat-dark-mode .sidebar-tabs :deep(.el-tabs__item.is-active) { color: #409eff; }
.chat-dark-mode .sidebar-tabs :deep(.el-tabs__header) { border-color: #2a2a4a; }
.chat-dark-mode .sidebar-header { border-color: #2a2a4a; }
.chat-dark-mode .sidebar-header h3 { color: #e0e0e0; }
.chat-dark-mode .sidebar-search :deep(.el-input__wrapper) { background: #0f3460; box-shadow: 0 0 0 1px #2a2a4a inset; }
.chat-dark-mode .conv-item { border-color: #2a2a4a; }
.chat-dark-mode .conv-item:hover { background: #0f3460; }
.chat-dark-mode .conv-item.active { background: #1a3a6a; }
.chat-dark-mode .conv-name { color: #e0e0e0; }
.chat-dark-mode .conv-time { color: #666; }
.chat-dark-mode .conv-last { color: #999; }
.chat-dark-mode .msg-bubble { background: #0f3460; color: #e0e0e0; }
.chat-dark-mode .msg-self .msg-bubble { background: #1a5276; }
.chat-dark-mode .msg-sender { color: #888; }
.chat-dark-mode .msg-time { color: #666; }
.chat-dark-mode .msg-encrypted { color: #666; }
.chat-dark-mode .chat-input-area { border-color: #2a2a4a; background: #16213e; }
.chat-dark-mode .thread-panel { background: #1a1a2e; border-color: #2a2a4a; }
.chat-dark-mode .thread-header { border-color: #2a2a4a; }
.chat-dark-mode .thread-parent-msg { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .thread-parent-content { color: #e0e0e0; }
.chat-dark-mode .thread-reply-sender { color: #e0e0e0; }
.chat-dark-mode .thread-reply-text { color: #b0b0b0; }
.chat-dark-mode .thread-input-row { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .chat-input-area :deep(.el-textarea__inner) { background: #0f3460; color: #e0e0e0; border-color: #2a2a4a; }
.chat-dark-mode .reply-preview { background: #0f3460; border-color: #2a2a4a; color: #999; }
.chat-dark-mode .conv-avatar { background: #0f3460; color: #e0e0e0; }
.chat-dark-mode .messages-area { background: #1a1a2e; }
.chat-dark-mode .notif-item.notif-unread { background: #0f3460; }
.chat-dark-mode .self-chat-entry { border-color: #2a2a4a; }
.chat-dark-mode .self-chat-entry:hover { background: #0f3460; }
.chat-dark-mode .link-card { border-color: #2a2a4a; background: #0f3460; }
.chat-dark-mode .link-card-title { color: #e0e0e0; }
.chat-dark-mode .link-card-desc { color: #999; }
.chat-dark-mode .link-card-site { color: #666; }
.chat-dark-mode .msg-card { border-color: #2a2a4a; background: #16213e; }
.chat-dark-mode .card-title { color: #e0e0e0; }
.chat-dark-mode .card-desc { color: #999; }
.chat-dark-mode .card-value { color: #ccc; }
.chat-dark-mode .card-btn.default { background: #2a2a4a; color: #ccc; border-color: #3a3a5a; }
.chat-dark-mode .msg-voice { background: #0f3460; }
.chat-dark-mode .msg-voice-self { background: #1a5276; }
.chat-dark-mode .voice-duration { color: #999; }
.chat-dark-mode .global-search-panel { border-color: #2a2a4a; }
.chat-dark-mode .search-result-item { border-color: #2a2a4a; }
.chat-dark-mode .search-result-item:hover { background: #0f3460; }
.chat-dark-mode .canned-panel { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .canned-item { border-color: #2a2a4a; }
.chat-dark-mode .canned-item:hover { background: #0f3460; }
.chat-dark-mode .ai-panel { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .sticker-panel { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .custom-emoji-item:hover { background: #1a2a4a; }
.chat-dark-mode .custom-emoji-label { color: #a0a0b0; }
.chat-dark-mode .emoji-grid .emoji-item:hover { background: #0f3460; }
.chat-dark-mode .sticker-item { border-color: #2a2a4a; }
.chat-dark-mode .sticker-item:hover { border-color: #66b1ff; }
.chat-dark-mode .ai-message .ai-role-tag { background: #1a3a5c; color: #66b1ff; }
.chat-dark-mode .ai-message.user .ai-role-tag { background: #1a3a1a; color: #85ce61; }
.chat-dark-mode .ai-streaming-indicator { color: #a6a9ad; }
.chat-dark-mode .copilot-sidebar { background: #1a1a2e; border-color: #2a2a4a; }
.chat-dark-mode .copilot-result-item { background: #16213e; }
.chat-dark-mode .copilot-result-title { color: #e0e0e0; }
.chat-dark-mode .copilot-result-body { color: #b0b0b0; }
.chat-dark-mode .category-bar { border-color: #2a2a4a; }
.chat-dark-mode .conv-search-bar { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .conv-search-item:hover { background: #0f3460; }
.chat-dark-mode .conv-search-results { border-color: #2a2a4a; }
.chat-dark-mode .task-item { border-color: #2a2a4a; }
.chat-dark-mode .ai-friend-header { border-color: #2a2a4a; color: #66b1ff; }
.chat-dark-mode .ai-category-tag { background: #1a3a5c; color: #66b1ff; }
.chat-dark-mode .sidebar-divider { background: #2a2a4a; }
.chat-dark-mode .ai-message { background: #0f3460; }
.chat-dark-mode .conv-tags-bar { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .tag-assign-panel { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .a11y-panel { background: #1a2a1a; border-color: #2a4a2a; }
.chat-dark-mode .a11y-label { color: #a0a0b0; }
.chat-dark-mode .a11y-more-link { color: #66b1ff; }
.chat-dark-mode .reaction-badge { background: #2a2a4a; }
.chat-dark-mode .reaction-badge.reaction-me { background: #1a3a6a; border-color: #409eff; }
.chat-dark-mode .emoji-option:hover { background: #2a2a4a; }
.chat-dark-mode .edited-badge { color: #666; }
.chat-dark-mode .msg-read-status { color: #666; }
.chat-dark-mode .msg-recalled-text { color: #666; }

/* AI 主持人 */
.moderator-mode-bar { display: flex; justify-content: center; margin-bottom: 12px; }
.moderator-extra { margin-bottom: 12px; }
.moderator-action-row { text-align: center; margin-bottom: 16px; }
.moderator-hint { text-align: center; color: #909399; padding: 40px 0; font-size: 14px; }
.moderator-result { margin-top: 12px; }
.result-card { background: #f5f7fa; border-radius: 8px; padding: 16px; }
.result-title { font-size: 15px; font-weight: 600; margin-bottom: 10px; color: #303133; }
.result-content { font-size: 14px; line-height: 1.7; color: #303133; }
.result-content :deep(p) { margin: 6px 0; }
.result-content :deep(strong) { font-weight: 600; }
.result-content :deep(ul), .result-content :deep(ol) { padding-left: 20px; }
.result-content :deep(li) { margin: 4px 0; }
.result-meta { font-size: 12px; color: #909399; margin-top: 8px; padding-top: 8px; border-top: 1px solid #e4e7ed; }
.debate-badge, .focus-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 13px; font-weight: 500; margin-bottom: 10px; }
.debate-yes, .focus-low { background: #fef0f0; color: #f56c6c; }
.debate-no, .focus-high { background: #f0f9eb; color: #67c23a; }
.focus-medium { background: #fdf6ec; color: #e6a23c; }

.chat-dark-mode .result-card { background: #1a1a3a; }
.chat-dark-mode .result-title { color: #e0e0e0; }
.chat-dark-mode .result-content { color: #ccc; }
.chat-dark-mode .result-meta { border-top-color: #2a2a4a; color: #888; }
.chat-dark-mode .debate-yes, .chat-dark-mode .focus-low { background: #3a1a1a; }
.chat-dark-mode .debate-no, .chat-dark-mode .focus-high { background: #1a3a1a; }
.chat-dark-mode .focus-medium { background: #3a3a1a; }
.chat-dark-mode .msg-high-priority { border-left-color: #e74c3c; }
.chat-dark-mode .mention-at { color: #64b5f6; }
.chat-dark-mode .dashboard-card { background: #0f3460; }
.chat-dark-mode .dashboard-num { color: #64b5f6; }
.chat-dark-mode .dashboard-label { color: #999; }
.chat-dark-mode .folder-bar { border-color: #2a2a4a; }
.chat-dark-mode .folder-menu-item:hover { background: #0f3460; }
.chat-dark-mode .conv-folder-menu { background: #16213e; border-color: #2a2a4a; }
.chat-dark-mode .msg-contact-card { background: #0f3460; border-color: #2a2a4a; }
.chat-dark-mode .contact-card-name { color: #e0e0e0; }
.chat-dark-mode .msg-location { background: #0f3460; border-color: #2a2a4a; }
.chat-dark-mode .location-name { color: #e0e0e0; }
.chat-dark-mode .msg-text :deep(code) { background: #2a2a4a; color: #e0e0e0; }
.chat-dark-mode .msg-text :deep(pre) { background: #0f3460; }
.chat-dark-mode .msg-text :deep(blockquote) { color: #999; }
.chat-dark-mode .msg-text :deep(td) { border-color: #2a2a4a; }
.chat-dark-mode .msg-text :deep(hr) { border-color: #2a2a4a; }
.chat-dark-mode .friend-filter-bar { border-color: #2a2a4a; }
.chat-dark-mode .pending-att-item { background: #0f3460; }
.chat-dark-mode .att-name { color: #ccc; }

/* 群管理 */
.group-member-row { display: flex; align-items: center; gap: 6px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.chat-dark-mode .group-member-row { border-color: #2a2a4a; }
.role-tag { font-size: 11px; padding: 0 6px; border-radius: 3px; line-height: 20px; font-weight: 500; }
.join-requests-section { background: #f5f7fa; border-radius: 6px; padding: 8px; margin-top: 6px; }
.join-req-row { display: flex; align-items: center; gap: 6px; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #eee; }
.join-req-row:last-child { border-bottom: none; }
.perm-row { display: flex; align-items: center; padding: 5px 0; border-bottom: 1px solid #f5f5f5; }
.perm-row:last-child { border-bottom: none; }
.creator-tag { background: #fdf6ec; color: #e6a23c; }
.admin-tag { background: #e6f0ff; color: #409eff; }
.member-tag { background: #f5f7fa; color: #909399; }
.chat-dark-mode .creator-tag { background: #3a2a10; color: #e6a23c; }
.chat-dark-mode .admin-tag { background: #1a2a4a; color: #64b5f6; }
.chat-dark-mode .member-tag { background: #2a2a3a; color: #aaa; }

/* 移动端 */
@media (max-width: 767px) {
    .chat-sidebar { width: 100%; flex-shrink: 0; }
    .sidebar-hidden { display: none; }
    .chat-main { width: 100%; }
    .msg-bubble { max-width: 85%; }
    .mobile-overlay { display: none; }
}
@media (min-width: 768px) { .mobile-overlay { display: none; } .back-btn { display: none; } }

/* ── 客服工作台会话视图 ── */
.agent-chat-header .agent-customer-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: #409eff; color: #fff; display: flex;
    align-items: center; justify-content: center;
    font-weight: 700; font-size: 16px; margin-right: 8px; flex-shrink: 0;
}
.chat-main-split { display: flex; flex: 1; min-height: 0; }
.chat-main-split .chat-messages-wrap { flex: 1; flex-direction: column; }
.chat-main-split.with-info .chat-messages-wrap { width: 65%; }
.agent-customer-info-panel {
    width: 35%; border-left: 1px solid #e4e7ed;
    display: flex; flex-direction: column; overflow-y: auto;
    background: #fafafa;
}
.chat-dark-mode .agent-customer-info-panel {
    border-color: #2a2a4a; background: #1e1e2e;
}
.info-panel-header { padding: 12px 16px; font-weight: 600; font-size: 14px; border-bottom: 1px solid #e4e7ed; }
.chat-dark-mode .info-panel-header { border-color: #2a2a4a; }
.info-panel-body { padding: 12px 16px; }
.info-row { display: flex; align-items: center; margin-bottom: 8px; font-size: 13px; }
.info-label { color: #909399; width: 50px; flex-shrink: 0; }
.info-value { color: #303133; }
.chat-dark-mode .info-value { color: #e0e0e0; }
.info-tags { display: flex; flex-wrap: wrap; gap: 2px; }
.info-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.agent-input-area .input-action-left .el-button { font-size: 12px; }

/* ── 更多 Tab 下拉 ── */
.more-tab-label { display: inline-flex; align-items: center; gap: 2px; cursor: pointer; font-size: 12px; }
.more-tab-label:hover { color: #409eff; }
.chat-dark-mode .more-tab-label:hover { color: #66b1ff; }

/* ════════════════════════════════════════════
   广场 - 小红书信息流样式
   ════════════════════════════════════════════ */
.plaza-feed-container { flex: 1; overflow-y: auto; padding: 12px; -webkit-overflow-scrolling: touch; }

/* ── 瀑布流布局 ── */
.plaza-feed-masonry { column-count: 2; column-gap: 10px; }
.plaza-feed-card { break-inside: avoid; margin-bottom: 10px; background: #fff; border-radius: 10px; overflow: hidden; cursor: pointer; transition: transform .15s, box-shadow .15s; border: 1px solid #f0f0f0; display: inline-block; width: 100%; animation: plazaCardIn .3s ease both; }
@keyframes plazaCardIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
.chat-dark-mode .plaza-feed-card { background: #1a1a2e; border-color: #2a2a3e; }
.plaza-feed-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.chat-dark-mode .plaza-feed-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.3); }
.plaza-quick-post { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #fff; border-bottom: 1px solid #f0f0f0; flex-shrink: 0; }
.chat-dark-mode .plaza-quick-post { background: #1a1a2e; border-color: #2a2a3e; }
.plaza-quick-post-avatar-img { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.plaza-quick-post-avatar { width: 34px; height: 34px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; flex-shrink: 0; }
.plaza-quick-post-input { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; }
.plaza-quick-post-placeholder { flex: 1; padding: 7px 14px; border: 1px solid #e8e8e8; border-radius: 20px; font-size: 13px; color: #bbb; cursor: pointer; transition: all .15s; }
.chat-dark-mode .plaza-quick-post-placeholder { border-color: #333; color: #666; }
.plaza-quick-post-placeholder:hover { border-color: #409eff; background: #f0f7ff; }
.chat-dark-mode .plaza-quick-post-placeholder:hover { background: #1a2744; }
.plaza-quick-post-btn { flex-shrink: 0; }
.plaza-quick-expanded-wrap { width: 100%; }
.plaza-quick-post-input .el-textarea__inner { min-height: 60px !important; }
.plaza-quick-expanded-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.plaza-quick-expand-title { font-size: 14px; font-weight: 600; color: #333; }
.chat-dark-mode .plaza-quick-expand-title { color: #ccc; }
.plaza-quick-actions { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }
.plaza-quick-left { display: flex; align-items: center; gap: 8px; }
.plaza-quick-right { display: flex; align-items: center; gap: 6px; }
.plaza-quick-count { font-size: 11px; color: #999; }

/* 双栏网格 */
.plaza-feed-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

/* 卡片 */
.plaza-feed-card {
    background: #fff; border-radius: 10px; overflow: hidden;
    cursor: pointer; transition: transform .15s, box-shadow .15s;
    border: 1px solid #f0f0f0; display: flex; flex-direction: column;
}
.chat-dark-mode .plaza-feed-card { background: #1a1a2e; border-color: #2a2a3e; }
.plaza-feed-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.chat-dark-mode .plaza-feed-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.3); }

/* 图片区域 - 正方形裁剪 */
.plaza-card-img-wrap {
    position: relative; width: 100%; aspect-ratio: 1;
    overflow: hidden; background: #f5f5f5; flex-shrink: 0;
}
.chat-dark-mode .plaza-card-img-wrap { background: #1f1f3a; }
.plaza-card-img-wrap.no-img { aspect-ratio: auto; min-height: 80px; }
.plaza-card-img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.plaza-feed-card:hover .plaza-card-img { transform: scale(1.05); }
.plaza-card-noimg { width: 100%; height: 80px; display: flex; align-items: center; justify-content: center; font-size: 28px; color: #ccc; }
.plaza-card-img-count {
    position: absolute; bottom: 6px; right: 6px;
    background: rgba(0,0,0,.55); color: #fff; font-size: 11px;
    padding: 1px 7px; border-radius: 10px;
}

/* 卡片内容 */
.plaza-card-body { padding: 8px 10px; flex: 1; display: flex; flex-direction: column; }
.plaza-card-text { font-size: 13px; line-height: 1.5; color: #333; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 6px; word-break: break-all; }
.chat-dark-mode .plaza-card-text { color: #ccc; }

/* 卡片投票 */
.plaza-card-poll { margin: 6px 0; padding: 6px; background: #f8f9fa; border-radius: 6px; font-size: 12px; }
.chat-dark-mode .plaza-card-poll { background: #252540; }
.plaza-poll-question { font-weight: 600; margin-bottom: 4px; font-size: 12px; color: #333; }
.chat-dark-mode .plaza-poll-question { color: #ccc; }
.plaza-poll-option { display: flex; align-items: center; gap: 4px; padding: 4px 6px; margin-bottom: 2px; border-radius: 4px; cursor: pointer; position: relative; overflow: hidden; background: #fff; border: 1px solid #eee; transition: all .15s; }
.chat-dark-mode .plaza-poll-option { background: #1a1a2e; border-color: #333; }
.plaza-poll-option:hover { border-color: #409eff; }
.plaza-poll-option.voted { border-color: #409eff; background: #ecf5ff; }
.chat-dark-mode .plaza-poll-option.voted { background: #1a2a4e; }
.plaza-poll-label { flex: 1; z-index: 1; font-weight: 500; color: #333; }
.chat-dark-mode .plaza-poll-label { color: #ccc; }
.plaza-poll-pct { z-index: 1; font-weight: 600; color: #409eff; min-width: 32px; text-align: right; }
.plaza-poll-bar-wrap { position: absolute; inset: 0; pointer-events: none; }
.plaza-poll-bar { height: 100%; background: rgba(64,158,255,.08); border-radius: 4px; transition: width .3s; }
.plaza-poll-footer { font-size: 11px; color: #999; margin-top: 2px; }

/* 卡片标签 */
.plaza-card-tags { display: flex; flex-wrap: wrap; gap: 3px; margin: 4px 0; }
.plaza-card-tag { font-size: 10px; color: #409eff; background: #ecf5ff; padding: 1px 6px; border-radius: 4px; cursor: pointer; transition: all .15s; }
.chat-dark-mode .plaza-card-tag { background: #1a2a4e; }
.plaza-card-tag:hover { background: #409eff; color: #fff; }

/* 卡片底部 */
.plaza-card-footer { margin-top: auto; }

/* ── 视频相关 ── */
.plaza-card-video-badge { position: absolute; bottom: 6px; left: 6px; background: rgba(0,0,0,.6); color: #fff; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; z-index: 2; }
.plaza-card-video-preview { margin: 6px 0; border-radius: 6px; overflow: hidden; }
.plaza-auto-video { width: 100%; max-height: 200px; border-radius: 6px; object-fit: cover; }

/* ── 下拉刷新 ── */
.plaza-pull-hint { text-align: center; padding: 8px; font-size: 12px; color: #909399; transition: opacity .2s; }
.plaza-card-user { display: flex; align-items: center; gap: 4px; margin-bottom: 4px; }
.plaza-card-avatar { width: 20px; height: 20px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.plaza-card-avatar-text { width: 20px; height: 20px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 10px; flex-shrink: 0; }
.plaza-card-name { font-size: 11px; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* 操作栏 */
.plaza-card-actions-bar { display: flex; gap: 12px; }
.plaza-card-action { font-size: 12px; color: #999; cursor: pointer; user-select: none; display: flex; align-items: center; gap: 2px; }
.plaza-card-action.liked { color: #e74c3c; }
.plaza-card-action.favorited { color: #e6a23c; }
.plaza-card-action:hover { color: #666; }

/* ═══════ 帖子详情弹窗 ═══════ */
.plaza-detail-dialog .el-dialog__body { padding: 16px 20px; max-height: 70vh; overflow-y: auto; }

/* ═══════ 广场内联详情页 ═══════ */
.plaza-detail-page { height: 100%; display: flex; flex-direction: column; }
.plaza-detail-topbar { display: flex; align-items: center; justify-content: space-between; padding: 10px 16px; border-bottom: 1px solid #eee; flex-shrink: 0; background: #fafafa; }
.chat-dark-mode .plaza-detail-topbar { border-color: #2a2a3e; background: #1a1a2e; }
.plaza-detail-back { background: none; border: none; cursor: pointer; font-size: 14px; color: #409eff; padding: 4px 8px; border-radius: 4px; }
.plaza-detail-back:hover { background: #f0f7ff; }
.plaza-detail-top-actions { display: flex; gap: 4px; }
.plaza-detail-body { flex: 1; overflow-y: auto; padding: 20px; }
.plaza-detail-user-row { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
.plaza-detail-user-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; }
.plaza-detail-user-avatar-text { width: 44px; height: 44px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.plaza-detail-user-meta { display: flex; flex-direction: column; }
.plaza-detail-user-name { font-size: 16px; font-weight: 600; color: #333; }
.chat-dark-mode .plaza-detail-user-name { color: #e0e0e0; }
.plaza-detail-post-time { font-size: 12px; color: #999; }
.plaza-detail-text { font-size: 15px; line-height: 1.8; color: #333; white-space: pre-wrap; margin-bottom: 16px; }
.chat-dark-mode .plaza-detail-text { color: #ccc; }

/* 详情投票 */
.plaza-detail-poll { margin: 12px 0; padding: 12px; background: #f8f9fa; border-radius: 8px; }
.chat-dark-mode .plaza-detail-poll { background: #252540; }
.plaza-detail-poll-title { font-weight: 600; margin-bottom: 8px; font-size: 15px; color: #333; }
.chat-dark-mode .plaza-detail-poll-title { color: #ccc; }
.plaza-detail-poll-options { display: flex; flex-direction: column; gap: 6px; }
.plaza-detail-poll-option { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; cursor: pointer; position: relative; overflow: hidden; background: #fff; border: 1px solid #eee; transition: all .15s; }
.chat-dark-mode .plaza-detail-poll-option { background: #1a1a2e; border-color: #333; }
.plaza-detail-poll-option:hover { border-color: #409eff; }
.plaza-detail-poll-option.voted { border-color: #409eff; background: #ecf5ff; }
.chat-dark-mode .plaza-detail-poll-option.voted { background: #1a2a4e; }
.plaza-detail-poll-label { flex: 1; z-index: 1; font-weight: 500; color: #333; }
.chat-dark-mode .plaza-detail-poll-label { color: #ccc; }
.plaza-detail-poll-pct { z-index: 1; font-weight: 600; color: #409eff; min-width: 36px; text-align: right; font-size: 14px; }
.plaza-detail-poll-bar-wrap { position: absolute; inset: 0; pointer-events: none; }
.plaza-detail-poll-bar { height: 100%; background: rgba(64,158,255,.08); border-radius: 6px; transition: width .3s; }
.plaza-detail-poll-footer { font-size: 12px; color: #999; margin-top: 6px; }

/* 详情标签 */
.plaza-detail-tags { display: flex; flex-wrap: wrap; gap: 6px; margin: 12px 0; }
.plaza-detail-tag { font-size: 12px; color: #409eff; background: #ecf5ff; padding: 2px 10px; border-radius: 12px; cursor: pointer; transition: all .15s; }
.chat-dark-mode .plaza-detail-tag { background: #1a2a4e; }
.plaza-detail-tag:hover { background: #409eff; color: #fff; }
.plaza-detail-images-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 6px; margin-bottom: 16px; }
.plaza-detail-image-item { border-radius: 8px; overflow: hidden; cursor: pointer; }
.plaza-detail-image { width: 100%; display: block; }
.plaza-detail-video-section { margin-bottom: 16px; }
.plaza-detail-video-player { width: 100%; max-height: 450px; border-radius: 8px; background: #000; }
.plaza-detail-action-bar { display: flex; gap: 8px; flex-wrap: wrap; padding: 8px 0; }
.plaza-detail-comments-section { }
.plaza-detail-header { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.plaza-detail-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.plaza-detail-avatar-text { width: 40px; height: 40px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.plaza-detail-user-info { display: flex; flex-direction: column; }
.plaza-detail-username { font-size: 15px; font-weight: 600; }
.plaza-detail-time { font-size: 12px; color: #999; }
.plaza-detail-content { font-size: 15px; line-height: 1.8; color: #333; white-space: pre-wrap; margin-bottom: 12px; }
.chat-dark-mode .plaza-detail-content { color: #ccc; }

/* 详情图片 */
.plaza-detail-images { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.plaza-detail-img-wrap { width: calc(50% - 3px); border-radius: 8px; overflow: hidden; cursor: pointer; }
.plaza-detail-img-wrap:only-child { width: 100%; }
.plaza-detail-img { width: 100%; display: block; }

/* 详情统计+操作 */
.plaza-detail-stats { font-size: 13px; color: #999; margin-bottom: 8px; }
.plaza-detail-actions { display: flex; gap: 8px; margin-bottom: 4px; }

/* 详情评论 */
.plaza-detail-comments { max-height: 200px; overflow-y: auto; }
.plaza-comment-list { margin-bottom: 8px; }
.plaza-comment-item { display: flex; gap: 8px; padding: 6px 0; align-items: flex-start; }
.plaza-comment-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.plaza-comment-avatar-text { width: 28px; height: 28px; border-radius: 50%; background: #67c23a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
.plaza-comment-body { flex: 1; font-size: 13px; line-height: 1.5; }
.plaza-comment-author { font-weight: 600; color: #409eff; margin-right: 6px; font-size: 13px; }
.plaza-comment-text { color: #333; }
.chat-dark-mode .plaza-comment-text { color: #ccc; }
.plaza-comment-footer { display: flex; align-items: center; gap: 8px; margin-top: 1px; }
.plaza-comment-time { display: block; font-size: 11px; color: #999; }
.plaza-comment-reply-btn { font-size: 11px; color: #409eff; cursor: pointer; display: none; }
.plaza-comment-del-btn { font-size: 11px; color: #f56c6c; cursor: pointer; display: none; margin-left: 4px; }
.plaza-comment-item:hover .plaza-comment-reply-btn, .plaza-comment-item:hover .plaza-comment-del-btn { display: inline; }
.plaza-sub-replies { margin-top: 4px; padding-left: 8px; border-left: 2px solid #eee; }
.chat-dark-mode .plaza-sub-replies { border-left-color: #2a2a3e; }
.plaza-sub-item { padding: 3px 0; }
.plaza-reply-input { display: flex; gap: 4px; align-items: center; margin-top: 4px; }
.plaza-reply-input .el-input { flex: 1; }

/* 转发预览 */
.plaza-forward-preview { margin-top: 12px; }
.plaza-forward-label { font-size: 12px; color: #999; margin-bottom: 4px; }
.plaza-forward-card { background: #f5f5f5; border-radius: 8px; padding: 10px; border: 1px solid #eee; }
.chat-dark-mode .plaza-forward-card { background: #1f1f3a; border-color: #2a2a3e; }
.plaza-forward-user { font-size: 12px; color: #409eff; font-weight: 600; margin-bottom: 4px; }
.plaza-forward-text { font-size: 13px; color: #333; line-height: 1.5; }
.chat-dark-mode .plaza-forward-text { color: #ccc; }

/* 详情视频 */
.plaza-detail-video { margin-bottom: 12px; }
.plaza-video-player { width: 100%; max-height: 400px; border-radius: 8px; background: #000; }

/* 编辑 */
.plaza-edit-images { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.plaza-edit-img-wrap { position: relative; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; }
.plaza-edit-img { width: 100%; height: 100%; object-fit: cover; }
.plaza-edit-remove { position: absolute; top: 0; right: 0; min-width: auto !important; padding: 0 4px !important; height: 20px !important; font-size: 14px; }
.plaza-comment-input { display: flex; gap: 6px; align-items: center; margin-top: 8px; }
.plaza-comment-input .el-input { flex: 1; }

/* 加载更多 */
.plaza-load-more { grid-column: 1 / -1; text-align: center; padding: 16px 0; font-size: 13px; color: #999; }
.plaza-load-end { color: #ccc; font-size: 12px; }

/* ═══════ 广场 Lightbox ═══════ */
.plaza-lightbox-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,.9); z-index: 9999;
    display: flex; align-items: center; justify-content: center;
}
.plaza-lightbox-content { display: flex; flex-direction: column; align-items: center; max-width: 90vw; max-height: 90vh; }
.plaza-lightbox-img { max-width: 90vw; max-height: 85vh; object-fit: contain; border-radius: 4px; }
.plaza-lightbox-counter { color: #fff; font-size: 13px; margin-top: 8px; opacity: .7; }
.plaza-lightbox-close {
    position: absolute; top: 16px; right: 16px; width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 24px; cursor: pointer; border-radius: 50%;
    background: rgba(255,255,255,.15); transition: background .2s; z-index: 1;
}
.plaza-lightbox-close:hover { background: rgba(255,255,255,.3); }
.plaza-lightbox-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 36px; cursor: pointer; border-radius: 50%;
    background: rgba(255,255,255,.12); transition: background .2s; user-select: none; z-index: 1;
}
.plaza-lightbox-nav:hover { background: rgba(255,255,255,.3); }
.plaza-lightbox-prev { left: 16px; }
.plaza-lightbox-next { right: 16px; }

/* ── 收藏夹选择 ── */
.collection-picker-list { display: flex; flex-direction: column; gap: 4px; max-height: 300px; overflow-y: auto; }
.collection-picker-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 6px; cursor: pointer; transition: background .15s; }
.collection-picker-item:hover { background: #f5f5f5; }
.chat-dark-mode .collection-picker-item:hover { background: #1f1f3a; }
.collection-picker-icon { font-size: 18px; }
.collection-picker-name { flex: 1; font-size: 14px; color: #333; }
.chat-dark-mode .collection-picker-name { color: #ccc; }
.collection-picker-count { font-size: 12px; color: #999; }

/* ── OA 文章分享 ── */
.oa-share-options { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; padding: 8px 0; }
.oa-share-option { display: flex; flex-direction: column; align-items: center; gap: 6px; cursor: pointer; padding: 12px 4px; border-radius: 10px; transition: background .15s; }
.oa-share-option:hover { background: #f5f5f5; }
.chat-dark-mode .oa-share-option:hover { background: #1f1f3a; }
.oa-share-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; color: #fff; }
.oa-share-label { font-size: 12px; color: #666; }
.chat-dark-mode .oa-share-label { color: #aaa; }
.oa-share-conv-selector { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
.chat-dark-mode .oa-share-conv-selector { border-top-color: #2a2a3e; }

/* ── OA 自动回复 ── */
.oa-auto-reply-card {
    border: 1px solid #e4e7ed; border-radius: 6px; padding: 12px; margin-bottom: 8px; background: #fff;
}
.chat-dark-mode .oa-auto-reply-card { border-color: #2a2a3e; background: #1a1a2e; }
.oa-auto-reply-header { display: flex; align-items: center; gap: 6px; margin-bottom: 8px; }
.oa-auto-reply-label { font-size: 12px; color: #909399; font-weight: 500; }
.oa-auto-reply-content { font-size: 13px; color: #333; padding: 8px 10px; background: #f5f7fa; border-radius: 4px; margin-bottom: 6px; word-break: break-all; }
.chat-dark-mode .oa-auto-reply-content { background: #2a2a3e; color: #ccc; }
.oa-auto-reply-meta { display: flex; align-items: center; font-size: 12px; }
.oa-auto-reply-empty { text-align: center; padding: 32px 0; }
.oa-auto-reply-list { max-height: 380px; overflow-y: auto; }
.status-active { color: #67c23a; }
.status-inactive { color: #f56c6c; }

/* ── OA 自定义菜单 ── */
.oa-menu-item { transition: all .15s; }
.oa-menu-row:hover { border-color: #409eff !important; }
.oa-menu-child { margin-left: 12px; }

/* ── OA 素材管理 ── */
.oa-material-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.oa-material-card { border: 1px solid #e4e7ed; border-radius: 6px; overflow: hidden; background: #fff; transition: box-shadow .2s; }
.oa-material-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.1); }
.oa-material-img-wrap { position: relative; width: 100%; aspect-ratio: 1; background: #f5f5f5; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.oa-material-img { max-width: 100%; max-height: 100%; object-fit: cover; cursor: pointer; }
.oa-material-img-actions { position: absolute; bottom: 0; left: 0; right: 0; display: none; gap: 4px; padding: 4px; background: rgba(0,0,0,.4); justify-content: center; }
.oa-material-img-wrap:hover .oa-material-img-actions { display: flex; }
.oa-material-info { padding: 6px 8px; }
.oa-material-name { font-size: 11px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oa-material-meta { font-size: 10px; color: #999; }
.oa-material-text-list { display: flex; flex-direction: column; gap: 6px; }
.oa-material-text-item { border: 1px solid #e4e7ed; border-radius: 6px; padding: 10px 12px; background: #fff; }
.oa-material-text-content { font-size: 13px; color: #333; line-height: 1.5; margin-bottom: 6px; white-space: pre-wrap; }
.oa-material-text-footer { display: flex; align-items: center; }
.chat-dark-mode .oa-material-card { border-color: #2a2a3e; background: #1a1a2e; }
.chat-dark-mode .oa-material-text-item { border-color: #2a2a3e; background: #1a1a2e; }

/* ── OA 增长趋势 ── */
.oa-trends-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 10px 0; }
.oa-trend-chart { background: #fff; border: 1px solid #e4e7ed; border-radius: 8px; padding: 10px; }
.chat-dark-mode .oa-trend-chart { background: #16213e; border-color: #2a2a3e; }
.oa-trend-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.oa-trend-title { font-size: 11px; color: #666; }
.oa-trend-num { font-size: 11px; color: #999; }
.oa-trend-bars { display: flex; align-items: flex-end; gap: 2px; height: 40px; }
.oa-trend-bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; }
.oa-trend-bar { width: 100%; border-radius: 2px 2px 0 0; transition: height .3s; min-height: 2px; }

/* ── OA 关注者消息 ── */
.oa-msg-conv-item { padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background .15s; }
.oa-msg-conv-item:hover { background: #f0f5ff; }
.oa-msg-conv-item.active { background: #e6f0ff; }
.chat-dark-mode .oa-msg-conv-item { border-color: #2a2a3e; }
.chat-dark-mode .oa-msg-conv-item:hover { background: #1a1a3e; }
.chat-dark-mode .oa-msg-conv-item.active { background: #1a2a4e; }
.oa-msg-badge { line-height: 1; }
.oa-msg-badge :deep(.el-badge__content) { font-size: 10px; height: 16px; line-height: 16px; padding: 0 4px; }
.oa-msg-bubble { margin-bottom: 8px; max-width: 80%; }
.oa-msg-in { margin-right: auto; }
.oa-msg-out { margin-left: auto; text-align: right; }
.oa-msg-bubble-content { display: inline-block; padding: 6px 10px; border-radius: 8px; font-size: 13px; line-height: 1.4; word-break: break-all; }
.oa-msg-in .oa-msg-bubble-content { background: #f0f0f0; color: #333; }
.oa-msg-out .oa-msg-bubble-content { background: #95ec69; color: #333; }
.chat-dark-mode .oa-msg-in .oa-msg-bubble-content { background: #2a2a3e; color: #ccc; }
.chat-dark-mode .oa-msg-out .oa-msg-bubble-content { background: #1a5a2e; color: #fff; }
.oa-msg-bubble-time { font-size: 10px; color: #bbb; margin-top: 2px; }
</style>