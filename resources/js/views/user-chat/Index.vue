<template>
    <div class="user-chat-page">
        <div class="chat-layout">
            <div v-if="isMobile && activeConv" class="mobile-overlay" @click="activeConv = null"></div>

            <!-- 左侧面板 -->
            <div class="chat-sidebar" :class="{ 'sidebar-hidden': isMobile && activeConv }">
                <div class="sidebar-tabs">
                    <el-tabs v-model="sidebarTab" @tab-change="onSidebarTabChange">
                        <el-tab-pane name="messages">
                            <template #label>
                                <el-icon style="vertical-align:middle;margin-right:2px"><ChatRound /></el-icon>
                                <span style="vertical-align:middle">消息</span>
                            </template>
                        </el-tab-pane>
                        <el-tab-pane name="friends">
                            <template #label>
                                <el-icon style="vertical-align:middle;margin-right:2px"><User /></el-icon>
                                <span style="vertical-align:middle">好友</span>
                            </template>
                        </el-tab-pane>
                        <el-tab-pane name="more">
                            <template #label>
                                <el-dropdown trigger="click" @command="handleMoreTab" @click.stop>
                                    <span class="more-tab-label">🔔 更多 <el-icon><ArrowDown /></el-icon></span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="notifications">🔔 通知</el-dropdown-item>
                                            <el-dropdown-item command="favorites">⭐ 收藏</el-dropdown-item>
                                            <el-dropdown-item command="message-requests">
                                                📩 消息请求
                                                <el-badge v-if="messageRequestCount" :value="messageRequestCount" style="margin-left:6px" />
                                            </el-dropdown-item>
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
                        <el-tag v-if="echoStatus === 'disabled'" size="small" type="warning" class="echo-status-tag" title="未配置 Reverb，消息与来电将使用轮询">实时未启用</el-tag>
                        <el-tag v-else-if="echoStatus === 'offline'" size="small" type="info" class="echo-status-tag" title="WebSocket 已断开，将自动重连">实时已断开</el-tag>
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
                    <div class="conversation-list" v-loading="loading">
                        <!-- 固定：文件传输助手 -->
                        <div v-if="!convCategory && !batchMode && !searchKeyword" class="assistant-pinned-section">
                            <div class="conv-item assistant-pinned-item" :class="{ active: activeConv?.id === fileTransferConv?.id }" @click="openFileTransferAssistant">
                                <div class="conv-avatar-wrap">
                                    <div class="conv-avatar assistant-avatar-file">📁</div>
                                </div>
                                <div class="conv-info">
                                    <div class="conv-top">
                                        <span class="conv-name">文件传输助手</span>
                                        <span v-if="fileTransferConv" class="conv-time">{{ formatTime(fileTransferConv.updated_at) }}</span>
                                    </div>
                                    <div class="conv-bottom">
                                        <span class="conv-last">{{ fileTransferConv?.last_message?.content || '传文件到手机或电脑' }}</span>
                                        <span v-if="fileTransferConv?.unread_count > 0" class="unread-badge">{{ fileTransferConv.unread_count > 99 ? '99+' : fileTransferConv.unread_count }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- AI 助手独立分区（仅展示已创建会话；入口在好友 Tab） -->
                        <div v-if="!convCategory && !batchMode && !searchKeyword && aiAssistantConvs.length" class="assistant-section">
                            <div class="assistant-section-title">🤖 AI 助手</div>
                            <div v-for="conv in aiAssistantConvs" :key="'ai-'+conv.id"
                                class="conv-item ai-assistant-item" :class="{ active: activeConv?.id === conv.id }"
                                @click="onConvClick(conv)">
                                <div class="conv-avatar-wrap">
                                    <div class="conv-avatar assistant-avatar-ai">🤖</div>
                                </div>
                                <div class="conv-info">
                                    <div class="conv-top">
                                        <span class="conv-name">{{ conv.name || 'AI 助手' }}</span>
                                        <span class="conv-time">{{ formatTime(conv.updated_at) }}</span>
                                    </div>
                                    <div class="conv-bottom">
                                        <span class="conv-last">{{ conv.last_message?.content || '智能问答与写作助手' }}</span>
                                        <span v-if="conv.unread_count > 0" class="unread-badge">{{ conv.unread_count > 99 ? '99+' : conv.unread_count }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!convCategory && !batchMode && !searchKeyword && displayConversations.length" class="assistant-section-divider"></div>
                        <div v-for="conv in displayConversations" :key="conv.id"
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
                        <div v-if="displayConversations.length === 0 && !loading" class="empty-chat">
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
                    <!-- AI 助手入口（方案 B：仅从好友 Tab 进入） -->
                    <div class="ai-assistant-entry conv-item" @click="openAIChat">
                        <div class="conv-avatar-wrap">
                            <div class="conv-avatar assistant-avatar-ai">🤖</div>
                        </div>
                        <div class="conv-info">
                            <div class="conv-top"><span class="conv-name">AI 助手</span></div>
                            <div class="conv-bottom"><span class="conv-last">独立会话，不与真人私聊混排</span></div>
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
                    <!-- 收藏的消息 -->
                    <div class="conversation-list" v-loading="loadingFavorites">
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
                </template>

                <!-- ====== 消息请求 ====== -->
                <template v-if="sidebarTab === 'message-requests'">
                    <div class="sidebar-header"><h3>📩 消息请求</h3></div>
                    <div class="conversation-list" v-loading="loadingMessageRequests">
                        <div v-for="conv in messageRequests" :key="conv.id" class="conv-item msg-request-item">
                            <div class="conv-avatar-wrap">
                                <img v-if="conv.avatar" :src="conv.avatar" class="conv-avatar" />
                                <div v-else class="conv-avatar">{{ (conv.name || '?').charAt(0) }}</div>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ conv.name }}</span>
                                    <span class="conv-time">{{ formatTime(conv.last_message?.created_at || conv.updated_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ conv.last_message?.content || '请求与你聊天' }}</span>
                                </div>
                            </div>
                            <div class="msg-request-actions" @click.stop>
                                <el-button size="small" type="primary" @click="acceptMessageRequest(conv)">接受</el-button>
                                <el-button size="small" @click="rejectMessageRequest(conv)">拒绝</el-button>
                                <el-button size="small" text type="danger" @click="rejectMessageRequest(conv, { block: true })">拉黑</el-button>
                            </div>
                        </div>
                        <div v-if="!messageRequests.length && !loadingMessageRequests" class="empty-chat">
                            <el-empty description="暂无消息请求" :image-size="60" />
                        </div>
                    </div>
                </template>

                <!-- ====== 待处理消息 ====== -->
                <template v-if="sidebarTab === 'pending'">
                    <div class="sidebar-header"><h3>⏳ 待处理</h3></div>
                    <div class="conversation-list" v-loading="loadingPending">
                        <div v-for="p in pendingMessages" :key="p.id" class="conv-item" @click="jumpToPending(p)">
                            <div class="conv-avatar-wrap"><div class="conv-avatar" style="background:#e6a23c">⏳</div></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ p.sender_name || p.conversation_name || '用户' }}</span>
                                    <span class="conv-time">{{ formatTime(p.created_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ p.content || '(消息)' }}</span>
                                    <el-button size="small" text type="danger" @click.stop="removePending(p)">移除</el-button>
                                </div>
                            </div>
                        </div>
                        <div v-if="!pendingMessages.length && !loadingPending" class="empty-chat">
                            <el-empty description="暂无待处理消息" :image-size="60" />
                        </div>
                    </div>
                </template>

            </div>

            <!-- ====== 右侧聊天窗口 ====== -->
            <div class="chat-main">
                <template v-if="activeConv">
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
                        <div class="messages-area" ref="msgAreaRef" @scroll="onMessagesAreaScroll">
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
                        <div v-for="msg in messages" :key="msg.id" :data-msg-id="msg.id" class="msg-item" :class="{ 'msg-self': msg.sender_id === myId, 'msg-selected': selectingForward && forwardMsgs.some(m => m.id === msg.id), 'msg-menu-active': msgContextMenu.visible && msgContextMenu.msg?.id === msg.id }" @click="selectingForward && toggleSelectForward(msg)" @contextmenu.prevent="onMsgContextMenu($event, msg)" @touchstart.passive="onMsgTouchStart($event, msg)" @touchmove.passive="onMsgTouchMove" @touchend.passive="onMsgTouchEnd" @touchcancel.passive="onMsgTouchEnd">
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
                                <div v-if="msg.sender_id === myId && msg.read_count > 0" class="msg-read-status">
                                    <el-icon style="margin-right:2px;font-size:12px"><Select /></el-icon> {{ msg.read_count }} 人已读
                                </div>
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
                                <el-popover v-model:visible="showEmojiPopover" trigger="click" :width="300" placement="top-start">
                                    <template #reference>
                                        <el-button text size="small" title="表情" :type="showEmojiPopover ? 'primary' : 'default'"><span style="font-size:18px;line-height:1">😀</span></el-button>
                                    </template>
                                    <div class="channel-emoji-picker">
                                        <div class="emoji-quick-row">
                                            <span v-for="e in commonEmojis.slice(0, 40)" :key="'qe-' + e" class="emoji-option" @click="insertInputEmoji(e)">{{ e }}</span>
                                        </div>
                                        <div v-if="customEmojis.length" class="emoji-custom-row">
                                            <div class="emoji-section-label">企业自定义</div>
                                            <span v-for="e in customEmojis" :key="'ce-' + e.id" class="emoji-option" :title="':' + e.shortcode + ':'" @click="insertCustomEmoji(e)">
                                                <img :src="e.image_url" class="custom-emoji-inline" />
                                            </span>
                                        </div>
                                        <div v-else class="emoji-empty-hint">
                                            暂无企业自定义表情
                                            <el-button text size="small" type="primary" @click="$router.push('/custom-emoji')">去管理</el-button>
                                        </div>
                                    </div>
                                </el-popover>
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
                                    <el-button text size="small" @click="showCannedReplyManager = true; loadCannedReplies()">管理</el-button>
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
                                <template v-if="customEmojis.length">
                                    <div v-for="cat in customEmojiGroups" :key="'sticker-' + cat.name" class="sticker-pack custom-emoji-pack" v-if="cat.items.length">
                                        <div class="sticker-pack-name">{{ customEmojiCategoryLabel(cat.name) }}</div>
                                        <div class="sticker-pack-items custom-emoji-pack-items">
                                            <div v-for="e in cat.items" :key="e.id" class="custom-emoji-item" @click="insertCustomEmoji(e)" :title="':' + e.shortcode + ':'">
                                                <img :src="e.image_url" class="sticker-item custom-emoji-sticker" />
                                                <span class="custom-emoji-label">:{{ e.shortcode }}:</span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
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
                <template v-else>
                    <div class="empty-state"><el-empty description="选择一个会话开始聊天" :image-size="80" /></div>
                </template>
            </div>
        </div>

        <!-- 消息右键/长按菜单 -->
        <teleport to="body">
            <div v-if="msgContextMenu.visible" class="msg-context-overlay" @click="closeMsgContextMenu" @contextmenu.prevent="closeMsgContextMenu"></div>
            <div v-if="msgContextMenu.visible" class="msg-context-menu" :style="{ left: msgContextMenu.x + 'px', top: msgContextMenu.y + 'px' }" @click.stop @contextmenu.prevent>
                <button v-for="(item, i) in msgContextMenu.items" :key="i" type="button" class="msg-context-item" :class="{ danger: item.danger, divided: item.divided }" @click="runMsgContextAction(item)">{{ item.label }}</button>
            </div>
        </teleport>

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
        <el-dialog v-model="showDndSettings" title="消息免打扰" width="400px" @open="loadDndSettings">
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
            :conversation-id="activeConv?.id" :my-name="myName" :my-id="myId" @call-ended="onCallEnded" />

        <!-- 快捷回复管理对话框 -->
        <el-dialog v-model="showCannedReplyManager" title="💬 快捷回复管理" width="480px" :close-on-click-modal="false">
            <div style="margin-bottom:12px;display:flex;gap:6px;align-items:center">
                <el-input v-model="chatReplyMgrSearch" placeholder="搜索..." size="small" clearable prefix-icon="Search" style="flex:1" />
                <el-button size="small" type="primary" @click="openChatNewReply">新建</el-button>
                <el-button size="small" @click="loadCannedReplies">刷新</el-button>
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
</template>
<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useRouter } from 'vue-router'
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
import { useUserChatMessages } from '@/composables/useUserChatMessages'
import { useUserChatEcho } from '@/composables/useUserChatEcho'
import FileUploadDialog from './FileUploadDialog.vue'
import FilePreviewDialog from './FilePreviewDialog.vue'
import CallPanel from './CallPanel.vue'
const router = useRouter()
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
const replyToMsg = ref(null)
const pendingAttachments = ref([])
const searchKeyword = ref('')
const sidebarTab = ref('messages')
const onlineStatusLabel = ref('')
const showSearchPanel = ref(false)
const globalSearchKeyword = ref('')
const globalSearchResults = ref([])
const notifications = ref([])

const myChannels = ref([])
const loadingChannels = ref(false)
const browseChannels = ref([])
const channelCategories = ref([])

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
const showEmojiPopover = ref(false)
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
const messageRequests = ref([])
const loadingMessageRequests = ref(false)
const messageRequestCount = computed(() => messageRequests.value.length)
const sellerInquiryProductId = ref(null)
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
const fileTransferConv = computed(() =>
    conversations.value.find(c => c.is_self && c.type === 'private' && !c.is_ai_assistant)
)
const aiAssistantConvs = computed(() =>
    conversations.value.filter(c => c.is_ai_assistant || c.type === 'ai')
)
const filteredConversations = computed(() => {
    let list = conversations.value
    // 私信 + 群聊（排除助手/AI 会话）
    list = list.filter(c => (c.type === 'private' || c.type === 'group') && !c.is_oa && !c.is_plaza && !c.is_channel && !c.is_discover)
    list = list.filter(c => !c.is_self && !c.is_ai_assistant && c.type !== 'ai' && !c.is_ai_friend && c.type !== 'ai_friend')
    list = list.filter(c => !c.is_message_request && c.request_status !== 'pending')
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
const displayConversations = computed(() =>
    convCategory.value === 'archived'
        ? filteredConversations.value
        : (convCategory.value ? classifiedConversations.value : filteredConversations.value)
)
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

function echoScrollToBottom() {
    nextTick(() => {
        const el = msgAreaRef.value
        if (el) el.scrollTop = el.scrollHeight
    })
}

// ── 实时连接状态：disabled | offline | online ──
const echoStatus = ref(typeof window !== 'undefined' && window.Echo ? 'offline' : 'disabled')
function onEchoConnected() { echoStatus.value = 'online' }
function onEchoDisconnected() { echoStatus.value = window.Echo ? 'offline' : 'disabled' }

function handleIncomingCall(payload) {
    if (!payload?.call_id || callState.value !== 'idle') return
    callType.value = payload.call_type || 'audio'
    callPartner.value = { id: payload.caller_id, name: payload.caller_name || '来电用户' }
    if (callPanelRef.value) {
        callPanelRef.value.handleIncomingCall(payload.call_id, callPartner.value, callType.value)
    }
}

const { initEcho, teardownEcho, markDeliveredBatch, pulseTyping } = useUserChatEcho({
    myId,
    activeConv,
    messages,
    conversations,
    scrollToBottom: echoScrollToBottom,
    typingUsers,
    notifications,
    onIncomingCall: handleIncomingCall,
})

const {
    loadMessages,
    loadMore,
    sendMessage,
    recallMessage,
    scrollToBottom,
    onScrollTop,
} = useUserChatMessages({
    activeConv,
    messages,
    hasMore,
    myId,
    sending,
    inputMessage,
    pendingAttachments,
    replyToMsg,
    sellerInquiryProductId,
    msgAreaRef,
    markDeliveredBatch,
    loadConversations,
    fetchLinkPreview,
})

// ── 消息操作 ──
function formatTime(t) { if (!t) return ''; const d = new Date(t); const now = new Date(); const pad = n => String(n).padStart(2,'0'); if (d.toDateString() === now.toDateString()) return pad(d.getHours())+':'+pad(d.getMinutes()); const y = d.getFullYear(); return y===now.getFullYear()?pad(d.getMonth()+1)+'/'+pad(d.getDate()):y+'/'+pad(d.getMonth()+1)+'/'+pad(d.getDate()) }
async function selectConversation(conv) { activeConv.value = conv; typingUsers.value = []; messages.value = []; hasMore.value = false; showTagPanel.value = false; if (conv.type === 'group') userRoleInGroup.value = conv.my_role || 'member'; else userRoleInGroup.value = 'member'; await loadMessages(); try { await apiClient.post('/user-chat/conversations/'+conv.id+'/read') } catch { /* ignore */ }; loadConvTags(conv.id); loadPinnedMessages() }
async function loadConversations() { try { const res = await apiClient.get('/user-chat/conversations'); conversations.value = res.data?.data || [] } catch { /* ignore */ } }

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
    pulseTyping()
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
// ── 会话操作 ──
async function togglePin() { if (!activeConv.value) return; try { await apiClient.put('/user-chat/conversations/'+activeConv.value.id+'/pin'); activeConv.value.is_pinned = !activeConv.value.is_pinned; await loadConversations() } catch { /* ignore */ } }
async function toggleMute() { if (!activeConv.value) return; try { await apiClient.put('/user-chat/conversations/'+activeConv.value.id+'/mute'); activeConv.value.is_muted = !activeConv.value.is_muted } catch { /* ignore */ } }
async function handleDeleteConv() { if (!activeConv.value) return; if (activeConv.value.is_channel || activeConv.value.is_oa || activeConv.value.is_plaza) { activeConv.value = null; return } try { await ElMessageBox.confirm('确定删除此会话？'); await apiClient.delete('/user-chat/conversations/'+activeConv.value.id); activeConv.value = null; await loadConversations() } catch { /* ignore */ } }
function onSearch() { /* filteredConversations handles it */ }
async function handleExportConv() { if (!activeConv.value) return; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/export', { responseType: 'blob' }); const url = URL.createObjectURL(new Blob([res.data])); const a = document.createElement('a'); a.href = url; a.download = 'chat-export-'+activeConv.value.id+'.html'; a.click(); URL.revokeObjectURL(url) } catch { ElMessage.error('导出失败') } }
async function handleHandoff() { if (!activeConv.value) return; try { await apiClient.post('/handoff', { conversation_id: activeConv.value.id }); ElMessage.success('已提交转接请求，客服将尽快接入') } catch (e) { ElMessage.error(e.response?.data?.message || '转接失败') } }

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
async function toggleFavorite(msg) { try { const res = await apiClient.post('/user-chat/messages/'+msg.id+'/favorite'); msg.is_favorited = res.data?.data?.favorited } catch { /* ignore */ } }
async function loadFavorites() { loadingFavorites.value = true; try { const res = await apiClient.get('/user-chat/favorites'); favorites.value = (res.data?.data || []).map(f => ({...f, content: f.message?.content || f.content || '(消息已删除)', sender_name: f.message?.sender?.name || f.sender_name || '用户', created_at: f.created_at || f.message?.created_at})) } catch { favorites.value = [] } finally { loadingFavorites.value = false } }
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
        if (conv) { sidebarTab.value = 'messages'; selectConversation(conv) }
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

// ── 消息右键/长按菜单 ──
const msgContextMenu = ref({ visible: false, x: 0, y: 0, msg: null, items: [] })
let msgLongPressTimer = null
let msgLongPressMoved = false

function buildMsgContextMenuItems(msg) {
    const items = []
    const add = (label, action, opts = {}) => items.push({ label, action, ...opts })

    add('回复', () => { replyToMsg.value = msg })
    if (msg.thread_reply_count > 0 || msg.id === activeThreadId.value) {
        add(`回复串${msg.thread_reply_count ? ` (${msg.thread_reply_count})` : ''}`, () => openThread(msg))
    }
    add('转发', () => openForward(msg))
    if (!msg.is_pinned) add('置顶', () => pinMsg(msg))
    else add('取消置顶', () => unpinMsg(msg))
    if (msg.sender_id === myId.value && !msg.is_recalled) add('编辑', () => editMessage(msg))
    if ((msg.sender_id === myId.value || (activeConv.value?.type === 'group' && userRoleInGroup.value !== 'member')) && !msg.is_recalled) {
        add('撤回', () => recallMessage(msg))
    }
    if (msg.sender_id === myId.value) add('删除', () => deleteMessage(msg), { danger: true, divided: true })
    add(msg.is_favorited ? '取消收藏' : '收藏', () => toggleFavorite(msg))
    add(msg.is_pending ? '取消待处理' : '标记待处理', () => togglePendingMsg(msg))
    add('创建工单', () => openCreateTicket(msg))
    if (msg.sender_id !== myId.value) add('举报', () => openReportDialog(msg), { danger: true, divided: true })
    if (msg.sender_id !== myId.value && msg.content) add('翻译', () => translateMessage(msg))
    add('AI 优化', () => aiOptimizeMessage(msg))
    if (msg.sender_id !== myId.value) add('分享名片', () => shareContact(msg))

    return items
}

function openMsgContextMenuAt(clientX, clientY, msg) {
    if (selectingForward.value || !msg) return
    const items = buildMsgContextMenuItems(msg)
    if (!items.length) return

    const menuWidth = 176
    const menuHeight = items.length * 36 + 8
    let x = clientX
    let y = clientY
    if (x + menuWidth > window.innerWidth) x = window.innerWidth - menuWidth - 8
    if (y + menuHeight > window.innerHeight) y = window.innerHeight - menuHeight - 8
    x = Math.max(8, x)
    y = Math.max(8, y)

    msgContextMenu.value = { visible: true, x, y, msg, items }
}

function onMsgContextMenu(e, msg) {
    if (selectingForward.value) return
    openMsgContextMenuAt(e.clientX, e.clientY, msg)
}

function clearMsgLongPressTimer() {
    if (msgLongPressTimer) {
        clearTimeout(msgLongPressTimer)
        msgLongPressTimer = null
    }
}

function onMsgTouchStart(e, msg) {
    if (selectingForward.value) return
    const touch = e.touches?.[0]
    if (!touch) return
    msgLongPressMoved = false
    clearMsgLongPressTimer()
    msgLongPressTimer = setTimeout(() => {
        if (msgLongPressMoved) return
        navigator.vibrate?.(50)
        openMsgContextMenuAt(touch.clientX, touch.clientY, msg)
    }, 500)
}

function onMsgTouchMove() {
    msgLongPressMoved = true
    clearMsgLongPressTimer()
}

function onMsgTouchEnd() {
    clearMsgLongPressTimer()
}

function closeMsgContextMenu() {
    msgContextMenu.value = { visible: false, x: 0, y: 0, msg: null, items: [] }
}

function runMsgContextAction(item) {
    closeMsgContextMenu()
    item.action?.()
}

function onMessagesAreaScroll(e) {
    closeMsgContextMenu()
    onScrollTop(e)
}

function onMsgMenuEscape(e) {
    if (e.key === 'Escape') closeMsgContextMenu()
}

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
async function unblockUser(id) { try { await apiClient.post('/user-chat/unblock/'+id); blockedUsers.value = blockedUsers.value.filter(u => u.id !== id); ElMessage.success('已取消拉黑') } catch { ElMessage.error('操作失败') } }

// ── 编辑消息 ──
const editingMsg = ref(null)
const editContent = ref('')
function editMessage(msg) { editingMsg.value = msg; editContent.value = msg.content || '' }
async function submitEdit() { if (!editingMsg.value || !editContent.value.trim()) return; try { await apiClient.put('/user-chat/messages/'+editingMsg.value.id+'/edit', { content: editContent.value.trim() }); editingMsg.value.content = editContent.value.trim(); editingMsg.value.is_edited = true; editingMsg.value = null; editContent.value = ''; ElMessage.success('消息已编辑') } catch (e) { ElMessage.error(e.response?.data?.message || '编辑失败') } }
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
async function aiOptimizeMessage(msg) { if (!msg?.content || !activeConv.value) return; inputMessage.value = msg.content; await aiWrite('polish') }

// ── 工单 ──
function openCreateTicket(msg) { ticketSubject.value = ''; ticketDescription.value = '消息内容：\n'+msg.content+'\n\n发送者：'+ (msg.sender?.name||'未知') + '\n时间：'+formatTime(msg.created_at); ticketPriority.value = 'medium'; showCreateTicket.value = true }
async function submitTicket() { if (!ticketSubject.value.trim()) return ElMessage.warning('请输入工单主题'); if (!ticketDescription.value.trim()) return ElMessage.warning('请输入工单描述'); creatingTicket.value = true; try { await apiClient.post('/tickets', { subject: ticketSubject.value, description: ticketDescription.value, priority: ticketPriority.value }); showCreateTicket.value = false; ElMessage.success('工单已提交') } catch (e) { ElMessage.error(e.response?.data?.message || '提交失败') } finally { creatingTicket.value = false } }

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
    } else if (conv.is_ai_assistant || conv.type === 'ai') {
        aiConvId.value = conv.id
        selectConversation(conv)
        showAIPanel.value = true
    } else {
        showAIPanel.value = false
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
function insertInputEmoji(value) {
    if (!value) return
    inputMessage.value += value
    showEmojiPopover.value = false
    nextTick(() => {
        document.querySelector('.chat-input-area textarea')?.focus()
    })
}
function insertCustomEmoji(e) {
    insertInputEmoji(`:${e.shortcode}:`)
    showStickerPanel.value = false
    if (e?.id) {
        apiClient.post(`/emoji/${e.id}/track`).catch(() => {})
    }
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
async function loadConvTags(convId) {
    try {
        const res = await apiClient.post('/im/tags/get-assigned', { conversation_type: 'user_chat', conversation_id: convId })
        const tags = res.data?.data || []
        convTags.value = Array.isArray(tags) ? tags : []
        selectedTagIds.value = convTags.value.map(t => t.id)
    } catch { convTags.value = []; selectedTagIds.value = [] }
}
async function saveConvTags() {
    if (!activeConv.value) return
    savingTags.value = true
    try {
        await apiClient.post('/im/tags/assign', {
            conversation_type: 'user_chat',
            conversation_id: activeConv.value.id,
            tag_ids: selectedTagIds.value,
        })
        ElMessage.success('标签已保存')
        await loadConvTags(activeConv.value.id)
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') }
    finally { savingTags.value = false }
}

// ── 通知 ──
async function loadNotifications() { try { const res = await apiClient.get('/notifications'); notifications.value = res.data?.data || [] } catch { notifications.value = [] } }
async function handleNotifClick(n) {
    if (!n) return
    if (!n.is_read) {
        try {
            await apiClient.post('/notifications/' + n.id + '/read')
            n.is_read = true
        } catch { /* ignore */ }
    }
    let payload = n.payload
    if (typeof payload === 'string') {
        try { payload = JSON.parse(payload) } catch { payload = {} }
    }
    payload = payload || {}
    const convId = payload.conversation_id
    if (!convId) return
    sidebarTab.value = 'messages'
    let conv = conversations.value.find(c => c.id === convId)
    if (!conv) {
        await loadConversations()
        conv = conversations.value.find(c => c.id === convId)
    }
    if (conv) {
        await selectConversation(conv)
    } else {
        ElMessage.info('会话未在列表中，请刷新后重试')
    }
}
async function markAllNotifRead() { try { await apiClient.post('/notifications/read-all'); notifications.value.forEach(n => n.is_read = true) } catch { ElMessage.error('操作失败') } }

// ── 在线状态与心跳 ──
let heartbeatTimer = null

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
async function loadMessageRequests() {
    loadingMessageRequests.value = true
    try {
        const res = await apiClient.get('/user-chat/message-requests')
        messageRequests.value = res.data?.data || []
    } catch {
        messageRequests.value = []
    } finally {
        loadingMessageRequests.value = false
    }
}
async function acceptMessageRequest(conv) {
    try {
        const res = await apiClient.post('/user-chat/message-requests/' + conv.id + '/accept')
        const accepted = res.data?.data
        ElMessage.success('已接受消息请求')
        messageRequests.value = messageRequests.value.filter(c => c.id !== conv.id)
        await loadConversations()
        if (accepted) await selectConversation(accepted)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}
async function rejectMessageRequest(conv, { block = false } = {}) {
    try {
        if (block) {
            await ElMessageBox.confirm('拒绝并将对方加入黑名单？', '确认拉黑', { type: 'warning' })
        }
        await apiClient.post('/user-chat/message-requests/' + conv.id + '/reject', { block })
        messageRequests.value = messageRequests.value.filter(c => c.id !== conv.id)
        ElMessage.success(block ? '已拒绝并拉黑' : '已拒绝')
        await loadConversations()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败')
    }
}
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
function upsertConversation(conv, extras = {}) {
    const normalized = { ...conv, ...extras }
    const idx = conversations.value.findIndex(c => c.id === normalized.id)
    if (idx >= 0) {
        conversations.value[idx] = { ...conversations.value[idx], ...normalized }
        return conversations.value[idx]
    }
    conversations.value.unshift(normalized)
    return normalized
}

async function openFileTransferAssistant() {
    showAIPanel.value = false
    if (fileTransferConv.value) {
        sidebarTab.value = 'messages'
        await selectConversation(fileTransferConv.value)
        return
    }
    await openSelfChat()
}

async function openSelfChat() { try { const res = await apiClient.post('/user-chat/self-conversation'); const conv = res.data?.data; if (conv) { sidebarTab.value = 'messages'; const item = upsertConversation(conv, { is_self: true, type: 'private', name: conv.name || '📁 文件传输助手' }); await selectConversation(item) } } catch (e) { ElMessage.error(e.response?.data?.message || '打开失败') } }

// ── AI-012: AI 助手单聊 ──
async function openAIChat() { try { const res = await apiClient.post('/user-chat/ai-conversation'); const conv = res.data?.data; if (!conv) return; aiConvId.value = conv.id; sidebarTab.value = 'messages'; const item = upsertConversation(conv, { type: 'ai', is_ai_assistant: true, name: conv.name || 'AI 助手' }); await selectConversation(item); showAIPanel.value = true; if (!aiMessages.value.length) aiMessages.value = [{ role: 'assistant', content: '你好！我是 AI 助手，有什么可以帮你的？' }] } catch (e) { ElMessage.error(e.response?.data?.message || '打开失败') } }

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
async function loadDndSettings() {
    try {
        const res = await apiClient.get('/notifications/preferences')
        const prefs = res.data?.data || {}
        dndEnabled.value = !!(prefs.quiet_hours_start && prefs.quiet_hours_end)
        dndStart.value = prefs.quiet_hours_start || ''
        dndEnd.value = prefs.quiet_hours_end || ''
    } catch { /* ignore */ }
}
async function saveDndSettings() {
    if (dndEnabled.value && (!dndStart.value || !dndEnd.value)) {
        ElMessage.warning('请设置免打扰时段')
        return
    }
    try {
        await apiClient.put('/notifications/preferences', {
            quiet_hours_start: dndEnabled.value ? dndStart.value : null,
            quiet_hours_end: dndEnabled.value ? dndEnd.value : null,
        })
        ElMessage.success('免打扰设置已保存')
        showDndSettings.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') }
}

// ── IM 看板 ──
async function loadDashboard() { loadingDashboard.value = true; try { const res = await apiClient.get('/im/dashboard'); dashboardData.value = res.data?.data || {} } catch { dashboardData.value = {} } finally { loadingDashboard.value = false } }

// ── 群管理 ──
async function loadGroupMembers() { if (!activeConv.value) return; loadingGroupMembers.value = true; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/participants'); groupMembers.value = res.data?.data || []; const me = groupMembers.value.find(m => m.id === myId.value); userRoleInGroup.value = me?.pivot?.role || 'member' } catch { groupMembers.value = [] } finally { loadingGroupMembers.value = false } }

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

// ── Tab切换 ──
function onSidebarTabChange() {
    if (sidebarTab.value === 'friends') { loadFriendsEnhanced(); loadFriendGroups(); loadPendingRequests() }
    else if (sidebarTab.value === 'notifications') { loadNotifications() }
    else if (sidebarTab.value === 'favorites') { loadFavorites() }
    else if (sidebarTab.value === 'message-requests') { loadMessageRequests() }
    else if (sidebarTab.value === 'pending') { loadPending() }
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
    loadMessageRequests()
    // SYNC-004: 消息漫游
    try { await apiClient.get('/user-chat/messages/sync', { params: { per_page: 50 } }) } catch { /* ignore */ }
    initEcho()
    document.addEventListener('echo:connected', onEchoConnected)
    document.addEventListener('echo:disconnected', onEchoDisconnected)
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
    window.addEventListener('keydown', onMsgMenuEscape)
    loadPrivacyPinStatus()
})
onUnmounted(() => {
    teardownEcho()
    document.removeEventListener('echo:connected', onEchoConnected)
    document.removeEventListener('echo:disconnected', onEchoDisconnected)
    stopHeartbeat()
    clearMsgLongPressTimer()
    window.removeEventListener('keydown', onMsgMenuEscape)
})
</script>
<style scoped>
.im-external-links { display: flex; gap: 8px; padding: 6px 12px; border-bottom: 1px solid #eee; background: #fafafa; }
.im-ext-link { font-size: 12px; color: #409eff; text-decoration: none; padding: 2px 8px; border-radius: 4px; background: #ecf5ff; }
.im-ext-link:hover { background: #d9ecff; }

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
.channel-emoji-picker { max-height: 280px; overflow-y: auto; }
.channel-emoji-picker .emoji-quick-row,
.channel-emoji-picker .emoji-custom-row { display: flex; flex-wrap: wrap; gap: 2px; }
.channel-emoji-picker .emoji-custom-row { margin-top: 8px; padding-top: 8px; border-top: 1px solid #f0f0f0; }
.channel-emoji-picker .emoji-option { font-size: 22px; cursor: pointer; padding: 2px; border-radius: 4px; transition: background 0.15s; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; }
.channel-emoji-picker .emoji-option:hover { background: #f0f0f0; }
.channel-emoji-picker .emoji-custom-row .emoji-option img { width: 24px; height: 24px; }
.channel-emoji-picker .emoji-section-label { width: 100%; font-size: 11px; color: #999; margin-bottom: 2px; padding: 4px 0; }
.emoji-empty-hint { padding: 12px 4px 4px; font-size: 12px; color: #909399; text-align: center; }
.custom-emoji-pack { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e4e7ed; }
.custom-emoji-pack-items { width: 100%; }
.custom-emoji-sticker { width: 48px; height: 48px; }
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

.chat-header-left { display: flex; align-items: center; gap: 4px; }
.chat-title { font-size: 16px; font-weight: 600; }
.chat-actions { display: flex; gap: 4px; }
.back-btn { margin-right: 4px; }
.sidebar-tabs :deep(.el-tabs__header) { margin: 0; }
.sidebar-tabs :deep(.el-tabs__nav-wrap) { padding: 0 12px; }
.sidebar-header { display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #e4e7ed; gap: 6px; flex-wrap: wrap; }
.sidebar-header h3 { margin: 0; font-size: 15px; flex: 1; min-width: 0; }
.echo-status-tag { flex-shrink: 0; }
.sidebar-search { padding: 8px 12px; border-bottom: 1px solid #e4e7ed; }
.sidebar-search :deep(.el-input__wrapper) { background: #fff; }
.conversation-list { flex: 1; overflow-y: auto; }

.conv-item { display: flex; align-items: center; padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f0f0f0; gap: 10px; }
.msg-request-item { flex-wrap: wrap; align-items: flex-start; }
.msg-request-actions { display: flex; gap: 4px; flex-wrap: wrap; width: 100%; margin-top: 6px; padding-left: 48px; }
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
.msg-item.msg-menu-active .msg-bubble { box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.35); }
.msg-context-overlay { position: fixed; inset: 0; z-index: 3000; background: transparent; }
.msg-context-menu { position: fixed; z-index: 3001; min-width: 160px; max-width: 220px; background: #fff; border-radius: 8px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15); padding: 4px 0; border: 1px solid #e4e7ed; }
.msg-context-item { display: block; width: 100%; padding: 8px 16px; font-size: 13px; color: #303133; background: none; border: none; text-align: left; cursor: pointer; white-space: nowrap; }
.msg-context-item:hover { background: #f0f5ff; color: #409eff; }
.msg-context-item.divided { border-top: 1px solid #f0f0f0; margin-top: 4px; padding-top: 10px; }
.msg-context-item.danger { color: #f56c6c; }
.msg-context-item.danger:hover { background: #fef0f0; color: #f56c6c; }
.chat-dark-mode .msg-context-menu { background: #1e1e3a; border-color: #2a2a4a; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.45); }
.chat-dark-mode .msg-context-item { color: #e0e0e0; }
.chat-dark-mode .msg-context-item:hover { background: #1a2a4a; color: #66b1ff; }
.chat-dark-mode .msg-context-item.divided { border-top-color: #2a2a4a; }
.chat-dark-mode .msg-context-item.danger { color: #f78989; }
.chat-dark-mode .msg-context-item.danger:hover { background: #3a1a1a; color: #f78989; }
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
.chat-dark-mode .channel-emoji-picker .emoji-custom-row { border-top-color: #2a2a4a; }
.chat-dark-mode .channel-emoji-picker .emoji-option:hover { background: #0f3460; }
.chat-dark-mode .emoji-empty-hint { color: #a0a0b0; }
.chat-dark-mode .custom-emoji-pack { border-top-color: #2a2a4a; }
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

.assistant-pinned-section { padding: 4px 0 0; }
.assistant-section { padding: 4px 0 0; }
.assistant-section-title { padding: 6px 12px 4px; font-size: 11px; color: #909399; font-weight: 600; }
.assistant-section-divider { height: 1px; background: #ebeef5; margin: 6px 8px; }
.assistant-pinned-item, .ai-assistant-item, .ai-assistant-entry { cursor: pointer; }
.assistant-avatar-file { background: #e8f4ff !important; font-size: 18px; line-height: 36px; text-align: center; }
.assistant-avatar-ai { background: #f0f9eb !important; font-size: 18px; line-height: 36px; text-align: center; }
.ai-assistant-entry { margin: 8px; border-radius: 8px; border: 1px solid #e4e7ed; background: #fafcff; }
</style>
