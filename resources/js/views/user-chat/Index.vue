<template>
    <div class="user-chat-page">
        <div class="chat-layout">
            <!-- 左侧面板 -->
            <div class="chat-sidebar" :class="{ 'sidebar-hidden': isMobile && activeConv }">
                <div class="sidebar-tabs">
                    <el-tabs v-model="sidebarTab" @tab-change="onSidebarTabChange">
                        <el-tab-pane name="messages">
                            <template #label>
                                <el-icon style="vertical-align:middle;margin-right:2px"><ChatRound /></el-icon>
                                <span style="vertical-align:middle">{{ t('user_chat_page.tabs.messages') }}</span>
                            </template>
                        </el-tab-pane>
                        <el-tab-pane name="friends">
                            <template #label>
                                <el-icon style="vertical-align:middle;margin-right:2px"><User /></el-icon>
                                <span style="vertical-align:middle">{{ t('user_chat_page.tabs.friends') }}</span>
                            </template>
                        </el-tab-pane>
                        <el-tab-pane name="more">
                            <template #label>
                                <el-dropdown trigger="click" @command="handleMoreTab" @click.stop>
                                    <span class="more-tab-label">{{ t('user_chat_page.tabs.more') }} <el-icon><ArrowDown /></el-icon></span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item command="notifications">{{ t('user_chat_page.tabs.notifications') }}</el-dropdown-item>
                                            <el-dropdown-item command="favorites">{{ t('user_chat_page.tabs.favorites') }}</el-dropdown-item>
                                            <el-dropdown-item command="pending">{{ t('user_chat_page.tabs.pending') }}</el-dropdown-item>
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
                        <h3>{{ t('user_chat_page.tabs.messages') }}</h3>
                        <el-tag v-if="echoStatus === 'disabled'" size="small" type="warning" class="echo-status-tag" :title="t('user_chat_page.echo.disabled_title')">{{ t('user_chat_page.echo.disabled') }}</el-tag>
                        <el-tag v-else-if="echoStatus === 'offline'" size="small" type="info" class="echo-status-tag" :title="t('user_chat_page.echo.offline_title')">{{ t('user_chat_page.echo.offline') }}</el-tag>
                        <div class="sidebar-header-actions">
                            <el-button size="small" text @click="showSearchPanel = !showSearchPanel" :type="showSearchPanel ? 'primary' : 'default'" :title="t('user_chat_page.toolbar.global_search')"><el-icon><Search /></el-icon></el-button>
                            <el-dropdown trigger="click" @command="openNewChat">
                                <el-button size="small" type="primary" circle :title="t('user_chat_page.dialogs.new_chat')"><el-icon><Plus /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="dm">
                                            <el-icon style="margin-right:4px"><User /></el-icon>{{ t('user_chat_page.dialogs.start_dm') }}
                                        </el-dropdown-item>
                                        <el-dropdown-item command="group">
                                            <el-icon style="margin-right:4px"><ChatLineSquare /></el-icon>{{ t('user_chat_page.dialogs.create_group_chat') }}
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-button size="small" text @click="cycleTheme" :title="t('user_chat_page.toolbar.theme', { mode: themeModeLabelMap[themeMode] || themeModeLabelMap.light })">
                                <el-icon><Sunny v-if="themeMode === 'light'" /><MoonNight v-else-if="themeMode === 'dark'" /><Monitor v-else /></el-icon>
                            </el-button>
                            <el-dropdown trigger="click" @command="setMyStatus">
                                <el-button size="small" text :title="t('user_chat_page.toolbar.status', { status: myStatusLabelMap[myStatus] || myStatusLabelMap.online })">
                                    <el-icon :color="myStatus === 'online' ? '#67c23a' : myStatus === 'busy' ? '#e6a23c' : '#999'">
                                        <User />
                                    </el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="online"><el-icon style="color:#67c23a"><Select /></el-icon> {{ t('user_chat_page.status.online') }}</el-dropdown-item>
                                        <el-dropdown-item command="busy"><el-icon style="color:#e6a23c"><RemoveFilled /></el-icon> {{ t('user_chat_page.status.busy') }}</el-dropdown-item>
                                        <el-dropdown-item command="invisible"><el-icon style="color:#999"><Mute /></el-icon> {{ t('user_chat_page.status.invisible') }}</el-dropdown-item>
                                        <el-dropdown-item divided>
                                            <div style="display:flex;align-items:center;gap:6px;font-size:12px" @click.stop>
                                                <span>{{ t('user_chat_page.toolbar.auto_reply') }}</span>
                                                <el-switch :model-value="autoReplyEnabled" size="small" @change="toggleAutoReply" />
                                            </div>
                                        </el-dropdown-item>
                                        <el-dropdown-item command="goto_auto_reply" style="font-size:12px;color:#909399">{{ t('user_chat_page.toolbar.manage_auto_reply') }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                            <el-dropdown trigger="click" @command="onSidebarMoreCommand">
                                <el-button size="small" text :title="t('user_chat_page.toolbar.more')"><el-icon><MoreFilled /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="blocked"><el-icon><RemoveFilled /></el-icon> {{ t('user_chat_page.toolbar.blocked') }}</el-dropdown-item>
                                        <el-dropdown-item command="sensitive_words"><el-icon><EditPen /></el-icon> {{ t('user_chat_page.toolbar.sensitive_words') }}</el-dropdown-item>
                                        <el-dropdown-item command="dnd"><el-icon><MuteNotification /></el-icon> {{ t('user_chat_page.toolbar.dnd') }}</el-dropdown-item>
                                        <el-dropdown-item command="privacy"><el-icon><Lock /></el-icon> {{ t('user_chat_page.toolbar.privacy') }}</el-dropdown-item>
                                        <el-dropdown-item divided command="dashboard"><el-icon><DataBoard /></el-icon> {{ t('user_chat_page.toolbar.dashboard') }}</el-dropdown-item>
                                        <el-dropdown-item command="ai_friend_admin"><el-icon><MagicStick /></el-icon> {{ t('user_chat_page.toolbar.ai_friend_admin') }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    <div class="sidebar-search">
                        <el-input v-model="searchKeyword" :placeholder="t('user_chat_page.search.conv_ph')" size="small" clearable @input="onSearch" />
                    </div>
                    <!-- 全局搜索面板 -->
                    <div v-if="showSearchPanel" class="global-search-panel">
                        <el-input v-model="globalSearchKeyword" :placeholder="t('user_chat_page.search.global_ph')" size="small" clearable @keydown.enter="onGlobalSearch">
                            <template #prefix><el-icon><Search /></el-icon></template>
                        </el-input>
                        <div class="search-options">
                            <el-radio-group v-model="searchMode" size="small">
                                <el-radio-button value="fulltext">{{ t('user_chat_page.search.keyword') }}</el-radio-button>
                                <el-radio-button value="semantic">{{ t('user_chat_page.search.semantic') }}</el-radio-button>
                            </el-radio-group>
                            <el-button v-if="globalSearchKeyword.trim()" text size="small" type="primary" @click="onGlobalSearch"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button>
                        </div>
                        <div v-if="globalSearchResults.length > 0" class="search-results">
                            <div v-for="r in globalSearchResults" :key="r.id" class="search-result-item" @click="jumpToMessage(r)">
                                <div class="search-result-conv">{{ r.conversation_name || r.conversation?.name || t('user_chat_page.msgs.conv_fallback') }}</div>
                                <div class="search-result-content" v-html="highlightKeyword(r.content)"></div>
                                <div class="search-result-time">{{ formatTime(r.created_at) }}</div>
                            </div>
                        </div>
                        <div v-else-if="globalSearchKeyword && !searchingGlobal" class="empty-chat">
                            <el-empty :description="t('user_chat_page.search.no_results')" :image-size="40" />
                        </div>
                        <div v-if="searchingGlobal" class="search-loading"><el-icon class="is-loading"><Loading /></el-icon> {{ t('user_chat_page.search.searching') }}</div>
                        <div v-if="searchKeywords && searchMode === 'semantic'" class="search-keywords">{{ t('user_chat_page.search.keywords') }} <strong>{{ searchKeywords }}</strong></div>
                    </div>
                    <!-- 会话文件夹 -->
                    <div class="folder-bar">
                        <el-select v-model="activeFolder" size="small" :placeholder="t('user_chat_page.folders.all_groups')" clearable style="width:120px" @change="onFolderChange">
                            <el-option :label="t('user_chat_page.folders.all_groups')" value="" />
                            <el-option v-for="f in folders" :key="f.id" :label="f.name" :value="f.id" />
                        </el-select>
                        <el-button text size="small" @click="showFolderDialog = true" :title="t('user_chat_page.folders.manage')"><el-icon><Setting /></el-icon></el-button>
                        <el-button text size="small" @click="toggleBatchMode" :type="batchMode ? 'primary' : 'default'" :title="t('user_chat_page.folders.batch')">
                            <el-icon><Select /></el-icon>
                        </el-button>
                        <el-button text size="small" @click="openHiddenConversations" :title="t('user_chat_page.folders.private_space')" style="color:#909399">
                            <el-icon><Lock /></el-icon>
                        </el-button>
                    </div>
                    <!-- AI-018: 智能分类 -->
                    <div class="category-bar">
                        <el-radio-group v-model="convCategory" size="small" @change="onCategoryChange">
                            <el-radio-button value="">{{ t('user_chat_page.category.all') }}</el-radio-button>
                            <el-radio-button value="urgent"><el-icon style="color:#f56c6c"><Warning /></el-icon> {{ t('user_chat_page.category.urgent') }}</el-radio-button>
                            <el-radio-button value="work"><el-icon style="color:#409eff"><Briefcase /></el-icon> {{ t('user_chat_page.category.work') }}</el-radio-button>
                            <el-radio-button value="normal"><el-icon style="color:#67c23a"><ChatDotRound /></el-icon> {{ t('user_chat_page.category.normal') }}</el-radio-button>
                            <el-radio-button value="promotion"><el-icon style="color:#e6a23c"><PriceTag /></el-icon> {{ t('user_chat_page.category.promotion') }}</el-radio-button>
                            <el-radio-button value="spam"><el-icon style="color:#999"><Delete /></el-icon> {{ t('user_chat_page.category.spam') }}</el-radio-button>
                            <el-radio-button value="archived"><el-icon style="color:#909399"><FolderDelete /></el-icon> {{ t('user_chat_page.category.archived') }}</el-radio-button>
                        </el-radio-group>
                    </div>
                    <!-- 批量归档 -->
                    <div class="batch-archive-bar" v-if="batchMode">
                        <span class="batch-count">{{ t('user_chat_page.batch.selected', { n: selectedConvIds.length }) }}</span>
                        <div class="batch-actions">
                            <el-button size="small" text @click="cancelBatch">{{ t('actions.cancel') }}</el-button>
                            <el-button v-if="convCategory === 'archived'" size="small" @click="batchUnarchive">{{ t('user_chat_page.batch.unarchive') }}</el-button>
                            <el-button v-else size="small" type="warning" @click="batchArchive">{{ t('user_chat_page.batch.archive_selected') }}</el-button>
                            <el-button size="small" type="primary" @click="batchArchiveInactive">{{ t('user_chat_page.batch.archive_inactive') }}</el-button>
                        </div>
                    </div>
                    <div v-if="dmMuteActive" class="dm-mute-banner">{{ privacySettings.dm_mute?.message || t('user_chat_page.msg_request.muted_hint') }}</div>
                    <div class="conversation-list" v-loading="loading">
                        <!-- 互动通知 / 系统官方：抖音式置顶入口 -->
                        <div v-if="!convCategory && !batchMode && !searchKeyword" class="inbox-hub-section">
                            <div class="conv-item inbox-hub-item" @click="openNotifHub('interactions')">
                                <div class="conv-avatar-wrap">
                                    <div class="conv-avatar inbox-hub-avatar inbox-hub-avatar-ix"><el-icon :size="18"><StarFilled /></el-icon></div>
                                    <span v-if="inboxHub.interaction_count > 0" class="msg-request-dot"></span>
                                </div>
                                <div class="conv-info">
                                    <div class="conv-top">
                                        <span class="conv-name">{{ t('user_chat_page.inbox_hub.interactions') }}</span>
                                    </div>
                                    <div class="conv-bottom">
                                        <span class="conv-last">{{ inboxHub.interaction_preview || t('user_chat_page.inbox_hub.interactions_hint') }}</span>
                                        <span v-if="inboxHub.interaction_count > 0" class="unread-badge">{{ inboxHub.interaction_count > 99 ? '99+' : inboxHub.interaction_count }}</span>
                                    </div>
                                </div>
                            </div>
                            <div v-if="inboxHub.system_total > 0 || inboxHub.system_count > 0 || inboxHub.system_preview" class="conv-item inbox-hub-item" @click="openNotifHub('system')">
                                <div class="conv-avatar-wrap">
                                    <div class="conv-avatar inbox-hub-avatar inbox-hub-avatar-sys"><el-icon :size="18"><Bell /></el-icon></div>
                                    <span v-if="inboxHub.system_count > 0" class="msg-request-dot"></span>
                                </div>
                                <div class="conv-info">
                                    <div class="conv-top">
                                        <span class="conv-name">{{ t('user_chat_page.inbox_hub.system') }}</span>
                                    </div>
                                    <div class="conv-bottom">
                                        <span class="conv-last">{{ inboxHub.system_preview || t('user_chat_page.inbox_hub.system_hint') }}</span>
                                        <span v-if="inboxHub.system_count > 0" class="unread-badge">{{ inboxHub.system_count > 99 ? '99+' : inboxHub.system_count }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- 消息请求独立入口 -->
                        <div v-if="!convCategory && !batchMode && !searchKeyword" class="msg-request-entry conv-item" @click="openMessageRequests">
                            <div class="conv-avatar-wrap">
                                <div class="conv-avatar msg-request-entry-avatar"><el-icon :size="18"><Message /></el-icon></div>
                                <span v-if="messageRequestCount > 0" class="msg-request-dot"></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ t('user_chat_page.tabs.message_requests') }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ messageRequestCount ? t('user_chat_page.msg_request.entry_pending', { n: messageRequestCount }) : t('user_chat_page.msg_request.entry_idle') }}</span>
                                    <span v-if="messageRequestBadge > 0" class="unread-badge">{{ messageRequestBadge > 99 ? '99+' : messageRequestBadge }}</span>
                                </div>
                            </div>
                        </div>
                        <!-- 固定：文件传输助手 -->
                        <div v-if="!convCategory && !batchMode && !searchKeyword" class="assistant-pinned-section">
                            <div class="conv-item assistant-pinned-item" :class="{ active: activeConv?.id === fileTransferConv?.id }" @click="openFileTransferAssistant">
                                <div class="conv-avatar-wrap">
                                    <div class="conv-avatar assistant-avatar-file"><el-icon :size="18"><Folder /></el-icon></div>
                                </div>
                                <div class="conv-info">
                                    <div class="conv-top">
                                        <span class="conv-name">{{ t('user_chat_page.file_transfer.name') }}</span>
                                        <span v-if="fileTransferConv" class="conv-time">{{ formatTime(fileTransferConv.updated_at) }}</span>
                                    </div>
                                    <div class="conv-bottom">
                                        <span class="conv-last">{{ fileTransferConv?.last_message?.content || t('user_chat_page.file_transfer.hint') }}</span>
                                        <span v-if="fileTransferConv?.unread_count > 0" class="unread-badge">{{ fileTransferConv.unread_count > 99 ? '99+' : fileTransferConv.unread_count }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- AI 助手独立分区（仅展示已创建会话；入口在好友 Tab） -->
                        <div v-if="!convCategory && !batchMode && !searchKeyword && aiAssistantConvs.length" class="assistant-section">
                            <div class="assistant-section-title">{{ t('user_chat_page.ai.assistant') }}</div>
                            <div v-for="conv in aiAssistantConvs" :key="'ai-'+conv.id"
                                class="conv-item ai-assistant-item" :class="{ active: activeConv?.id === conv.id }"
                                @click="onConvClick(conv)">
                                <div class="conv-avatar-wrap">
                                    <div class="conv-avatar assistant-avatar-ai"><el-icon :size="18"><Cpu /></el-icon></div>
                                </div>
                                <div class="conv-info">
                                    <div class="conv-top">
                                        <span class="conv-name">{{ conv.name || t('user_chat_page.ai.assistant') }}</span>
                                        <span class="conv-time">{{ formatTime(conv.updated_at) }}</span>
                                    </div>
                                    <div class="conv-bottom">
                                        <span class="conv-last">{{ conv.last_message?.content || t('user_chat_page.ai.hint') }}</span>
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
                                    <span class="conv-last">{{ conv.last_message?.content || t('user_chat_page.empty.no_messages') }}</span>
                                    <span v-if="conv.unread_count > 0" class="unread-badge">{{ conv.unread_count > 99 ? '99+' : conv.unread_count }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-if="displayConversations.length === 0 && !loading" class="empty-chat">
                            <el-empty :description="convCategory ? t('user_chat_page.empty.no_conversations_cat') : t('user_chat_page.empty.no_conversations')" :image-size="60" />
                        </div>
                    </div>
                </template>

                <!-- ====== 好友列表 ====== -->
                <template v-if="sidebarTab === 'friends'">
                    <div class="sidebar-header">
                        <h3>{{ t('user_chat_page.tabs.friends') }}</h3>
                        <div style="display:flex;gap:4px">
                            <el-button size="small" circle @click="showCreateAiFriend = true" :title="t('user_chat_page.ai.create_friend')"><el-icon><MagicStick /></el-icon></el-button>
                            <el-button size="small" type="primary" circle @click="showAddFriend = true"><el-icon><User /></el-icon></el-button>
                        </div>
                    </div>
                    <!-- AI 助手入口（方案 B：仅从好友 Tab 进入） -->
                    <div class="ai-assistant-entry conv-item" @click="openAIChat">
                        <div class="conv-avatar-wrap">
                            <div class="conv-avatar assistant-avatar-ai"><el-icon :size="18"><Cpu /></el-icon></div>
                        </div>
                        <div class="conv-info">
                            <div class="conv-top"><span class="conv-name">{{ t('user_chat_page.ai.assistant') }}</span></div>
                            <div class="conv-bottom"><span class="conv-last">{{ t('user_chat_page.ai.standalone') }}</span></div>
                        </div>
                    </div>
                    <!-- AI 好友分组 -->
                    <div v-if="aiFriends.length" class="ai-friend-section">
                        <div class="ai-friend-header"><el-icon style="color:#409eff"><MagicStick /></el-icon> {{ t('user_chat_page.ai.assistant') }} <span class="ai-friend-count">{{ aiFriends.length }}</span></div>
                        <div v-for="f in aiFriends" :key="'ai-'+f.id" class="conv-item ai-friend-item" @click="startAiFriendChat(f)">
                            <div class="conv-avatar-wrap">
                                <img v-if="f.avatar" :src="f.avatar" class="conv-avatar-img" />
                                <div v-else class="conv-avatar" style="background:#409eff">{{ f.name?.charAt(0) || 'A' }}</div>
                                <span class="ai-badge"><el-icon :size="10"><Cpu /></el-icon></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ f.name }}</span>
                                    <span class="ai-category-tag">{{ categoryLabel(f.category) }}</span>
                                </div>
                                <div class="conv-bottom"><span class="conv-last">{{ f.description || f.welcome_message || t('user_chat_page.ai.assistant') }}</span></div>
                            </div>
                            <el-dropdown trigger="click" @command="(cmd) => handleAiFriendAction(cmd, f)" @click.stop>
                                <el-button text size="small" @click.stop><el-icon><MoreFilled /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="delete" divided>{{ t('user_chat_page.friends.delete') }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    <div class="sidebar-divider"></div>
                    <!-- 搜索好友 -->
                    <div class="sidebar-search">
                        <el-input v-model="friendSearch" :placeholder="t('user_chat_page.friends.search_ph')" size="small" clearable />
                    </div>
                    <div class="friend-filter-bar">
                        <el-select v-model="friendGroupFilter" :placeholder="t('user_chat_page.folders.all_groups')" size="small" clearable style="width:130px">
                            <el-option :label="t('user_chat_page.folders.all_groups')" :value="null" />
                            <el-option v-for="g in friendGroups" :key="g.id" :label="g.name" :value="g.id" />
                        </el-select>
                        <el-button size="small" text @click="showFriendGroups = true">{{ t('user_chat_page.friends.manage_groups') }}</el-button>
                        <el-button v-if="pendingRequests.length" size="small" text @click="showPendingRequests = true">{{ t('user_chat_page.friends.requests', { n: pendingRequests.length }) }}</el-button>
                    </div>
                    <div class="conversation-list" v-loading="loadingFriends">
                        <div v-for="f in filteredFriends" :key="f.id" class="conv-item" @click="startFriendChat(f)">
                            <div class="conv-avatar-wrap">
                                <div class="conv-avatar" :style="{ background: f.online === 'online' ? '#52c41a' : '#999' }">{{ f.name?.charAt(0) || '?' }}</div>
                                <span v-if="f.online === 'online'" class="online-dot"></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ f.remark || f.name || t('user_chat_page.msg.user') }}</span>
                                    <span class="conv-time">{{ f.online === 'online' ? t('user_chat_page.status.online') : t('user_chat_page.status.offline') }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span v-if="f.friend_group_name" class="friend-group-tag">{{ f.friend_group_name }}</span>
                                </div>
                            </div>
                            <el-dropdown trigger="click" @command="(cmd) => handleFriendAction(cmd, f)">
                                <el-button text size="small" @click.stop><el-icon><MoreFilled /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="remark">{{ t('user_chat_page.friends.set_remark') }}</el-dropdown-item>
                                        <el-dropdown-item command="group">{{ t('user_chat_page.friends.move_group') }}</el-dropdown-item>
                                        <el-dropdown-item command="remove" divided>{{ t('user_chat_page.friends.remove') }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                        <div v-if="!filteredFriends.length && !loadingFriends" class="empty-chat">
                            <el-empty :description="t('user_chat_page.empty.no_friends')" :image-size="60" />
                        </div>
                    </div>
                </template>

                <!-- ====== 通知列表（含互动聚合） ====== -->
                <template v-if="sidebarTab === 'notifications'">
                    <div class="sidebar-header">
                        <h3>{{ t('user_chat_page.tabs.notifications') }}</h3>
                        <el-button v-if="notifications.some(n => !n.is_read)" size="small" text @click="markAllNotifRead">{{ t('user_chat_page.friends.mark_all_read') }}</el-button>
                    </div>
                    <div class="notif-filter-row">
                        <button
                            v-for="g in notifGroups"
                            :key="g.key"
                            type="button"
                            class="notif-filter-chip"
                            :class="{ active: notifGroup === g.key }"
                            @click="notifGroup = g.key; loadNotifications()"
                        >
                            {{ t('user_chat_page.notifications.groups.' + g.key) }}
                            <span v-if="g.unread" class="notif-chip-badge">{{ g.unread > 99 ? '99+' : g.unread }}</span>
                        </button>
                    </div>
                    <div class="conversation-list">
                        <div v-for="n in notifications" :key="n.id" class="conv-item notif-item" :class="{ 'notif-unread': !n.is_read }" @click="handleNotifClick(n)">
                            <div class="notif-icon">
                                <el-avatar v-if="notifActorAvatar(n)" :size="36" :src="notifActorAvatar(n)">{{ notifActorName(n).charAt(0) }}</el-avatar>
                                <el-icon v-else :size="18" :color="n.is_read ? '#999' : '#409eff'"><Bell /></el-icon>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name" :style="{ fontWeight: n.is_read ? 'normal' : 'bold' }">{{ n.title }}</span>
                                    <span class="conv-time">{{ formatTime(n.updated_at || n.created_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ n.content }}</span>
                                    <span v-if="!n.is_read" class="unread-dot"></span>
                                </div>
                            </div>
                        </div>
                        <div v-if="!notifications.length" class="empty-chat"><el-empty :description="t('user_chat_page.empty.no_notifications')" :image-size="60" /></div>
                    </div>
                </template>

                <!-- ====== 收藏列表 ====== -->
                <template v-if="sidebarTab === 'favorites'">
                    <div class="sidebar-header"><h3>{{ t('user_chat_page.tabs.favorites') }}</h3></div>
                    <!-- 收藏的消息 -->
                    <div class="conversation-list" v-loading="loadingFavorites">
                        <div v-for="fav in favorites" :key="fav.id" class="conv-item" @click="jumpToFavorite(fav)">
                            <div class="conv-avatar-wrap"><div class="conv-avatar" style="background:#e6a23c">★</div></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ fav.sender_name || t('user_chat_page.msg.user') }}</span>
                                    <span class="conv-time">{{ formatTime(fav.created_at) }}</span>
                                </div>
                                <div class="conv-bottom"><span class="conv-last">{{ fav.content }}</span></div>
                            </div>
                        </div>
                        <div v-if="!favorites.length && !loadingFavorites" class="empty-chat"><el-empty :description="t('user_chat_page.empty.no_favorites')" :image-size="60" /></div>
                    </div>
                </template>

                <!-- ====== 消息请求 ====== -->
                <template v-if="sidebarTab === 'message-requests'">
                    <div class="sidebar-header">
                        <el-button text size="small" @click="sidebarTab = 'messages'"><el-icon><ArrowLeft /></el-icon></el-button>
                        <h3>{{ t('user_chat_page.tabs.message_requests') }}</h3>
                        <el-badge v-if="messageRequestCount" :value="messageRequestCount" />
                    </div>
                    <div class="msg-request-intro">{{ t('user_chat_page.msg_request.intro') }}</div>
                    <div class="conversation-list" v-loading="loadingMessageRequests">
                        <div v-for="conv in messageRequests" :key="conv.id" class="conv-item msg-request-item" :class="{ active: activeConv?.id === conv.id }" @click="previewMessageRequest(conv)">
                            <div class="conv-avatar-wrap">
                                <img v-if="conv.avatar" :src="conv.avatar" class="conv-avatar" />
                                <div v-else class="conv-avatar">{{ (conv.name || '?').charAt(0) }}</div>
                                <span v-if="conv.unread_count > 0" class="msg-request-dot"></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ conv.name }}</span>
                                    <span class="conv-time">{{ formatTime(conv.last_message?.created_at || conv.updated_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ conv.last_message?.content || t('user_chat_page.msg_request.hint') }}</span>
                                    <span v-if="conv.unread_count > 0" class="unread-badge">{{ conv.unread_count > 99 ? '99+' : conv.unread_count }}</span>
                                </div>
                                <div class="msg-request-why">{{ t('user_chat_page.msg_request.why') }}</div>
                            </div>
                            <div class="msg-request-actions" @click.stop>
                                <el-button size="small" type="primary" @click="acceptMessageRequest(conv)">{{ t('user_chat_page.msg_request.accept') }}</el-button>
                                <el-button size="small" @click="rejectMessageRequest(conv)">{{ t('user_chat_page.msg_request.reject') }}</el-button>
                                <el-button size="small" type="danger" plain @click="rejectMessageRequest(conv, { block: true })">{{ t('user_chat_page.msg_request.block') }}</el-button>
                            </div>
                        </div>
                        <div v-if="!messageRequests.length && !loadingMessageRequests" class="empty-chat msg-request-empty">
                            <el-empty :description="t('user_chat_page.empty.no_msg_requests')" :image-size="72">
                                <p class="msg-request-empty-hint">{{ t('user_chat_page.msg_request.empty_hint') }}</p>
                                <el-button size="small" @click="showPrivacySettings = true">{{ t('user_chat_page.toolbar.privacy') }}</el-button>
                            </el-empty>
                        </div>
                    </div>
                </template>

                <!-- ====== 待处理消息 ====== -->
                <template v-if="sidebarTab === 'pending'">
                    <div class="sidebar-header"><h3>{{ t('user_chat_page.tabs.pending') }}</h3></div>
                    <div class="conversation-list" v-loading="loadingPending">
                        <div v-for="p in pendingMessages" :key="p.id" class="conv-item" @click="jumpToPending(p)">
                            <div class="conv-avatar-wrap"><div class="conv-avatar" style="background:#e6a23c">⏳</div></div>
                            <div class="conv-info">
                                <div class="conv-top">
                                    <span class="conv-name">{{ p.sender_name || p.conversation_name || t('user_chat_page.msg.user') }}</span>
                                    <span class="conv-time">{{ formatTime(p.created_at) }}</span>
                                </div>
                                <div class="conv-bottom">
                                    <span class="conv-last">{{ p.content || t('user_chat_page.pending.message_fallback') }}</span>
                                    <el-button size="small" text type="danger" @click.stop="removePending(p)">{{ t('user_chat_page.pending.remove') }}</el-button>
                                </div>
                            </div>
                        </div>
                        <div v-if="!pendingMessages.length && !loadingPending" class="empty-chat">
                            <el-empty :description="t('user_chat_page.empty.no_pending')" :image-size="60" />
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
                            <el-button text size="small" @click="startCall('audio')" :title="t('user_chat_page.header.audio_call')"><el-icon><Phone /></el-icon></el-button>
                            <el-button text size="small" @click="startCall('video')" :title="t('user_chat_page.header.video_call')"><el-icon><VideoCamera /></el-icon></el-button>
                            <el-button text size="small" @click="togglePin" :title="activeConv.is_pinned ? t('user_chat_page.header.unpin') : t('user_chat_page.header.pin')"><el-icon><StarFilled v-if="activeConv.is_pinned" /><Star v-else /></el-icon></el-button>
                            <el-button text size="small" @click="toggleMute" :title="activeConv.is_muted ? t('user_chat_page.header.unmute') : t('user_chat_page.header.mute')"><el-icon><Bell v-if="!activeConv.is_muted" /><Mute v-else /></el-icon></el-button>
                            <el-button v-if="activeConv?.type === 'group'" text size="small" @click="showGroupManage = true" :title="t('user_chat_page.header.group_manage')"><el-icon><Setting /></el-icon></el-button>
                            <el-button text size="small" @click="handleDeleteConv" :title="t('actions.delete')"><el-icon><Delete /></el-icon></el-button>
                            <el-dropdown trigger="click" @command="onChatMoreCommand">
                                <el-button text size="small" :title="t('user_chat_page.header.more')"><el-icon><MoreFilled /></el-icon></el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="tags"><el-icon><CollectionTag /></el-icon> {{ t('user_chat_page.header.tags') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="activeConv?.type === 'group'" command="announcements"><el-icon><Message /></el-icon> {{ t('user_chat_page.header.announcements') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="activeConv?.type === 'group'" command="slow_mode"><el-icon><Timer /></el-icon> {{ t('user_chat_page.header.slow_mode') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="activeConv?.type === 'group'" command="invite"><el-icon><Share /></el-icon> {{ t('user_chat_page.header.invite') }}</el-dropdown-item>
                                        <el-dropdown-item divided command="a11y"><el-icon><Reading /></el-icon> {{ t('user_chat_page.header.a11y') }}</el-dropdown-item>
                                        <el-dropdown-item command="export"><el-icon><Download /></el-icon> {{ t('user_chat_page.header.export') }}</el-dropdown-item>
                                        <el-dropdown-item command="summarize"><el-icon><MagicStick /></el-icon> {{ t('user_chat_page.header.summarize') }}</el-dropdown-item>
                                        <el-dropdown-item v-if="activeConv?.type === 'group'" command="moderator"><el-icon><Cpu /></el-icon> {{ t('user_chat_page.header.moderator') }}</el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </div>
                    <div v-if="showTagPanel" class="tag-assign-panel">
                        <div class="tag-assign-header"><span>{{ t('user_chat_page.tags.assign') }}</span><el-button text size="small" @click="showTagPanel = false"><el-icon><Close /></el-icon></el-button></div>
                        <div class="tag-list">
                            <el-checkbox-group v-model="selectedTagIds">
                                <el-checkbox v-for="tag in allTags" :key="tag.id" :label="tag.id" :value="tag.id">
                                    <span class="tag-label-dot" :style="{ background: tag.color || '#409eff' }"></span>{{ tag.name }}
                                </el-checkbox>
                            </el-checkbox-group>
                        </div>
                        <div class="tag-assign-actions"><el-button size="small" type="primary" :loading="savingTags" @click="saveConvTags">{{ t('actions.save') }}</el-button></div>
                    </div>
                    <div v-if="convTags.length" class="conv-tags-bar">
                        <span v-for="tag in convTags" :key="tag.id" class="conv-tag" :style="{ background: tag.color || '#409eff' }">{{ tag.name }}</span>
                    </div>
                    <!-- 无障碍面板 -->
                    <div v-if="showA11yPanel" class="a11y-panel">
                        <div class="a11y-panel-header">
                            <span>{{ t('user_chat_page.a11y.title') }}</span>
                            <el-button text size="small" @click="showA11yPanel = false"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div class="a11y-panel-body">
                            <div class="a11y-row">
                                <span class="a11y-label">{{ t('user_chat_page.a11y.font_size') }}</span>
                                <el-radio-group v-model="a11yFontSize" size="small" @change="setA11yFontSize">
                                    <el-radio-button value="small">{{ t('user_chat_page.a11y.size_small') }}</el-radio-button>
                                    <el-radio-button value="normal">{{ t('user_chat_page.a11y.size_normal') }}</el-radio-button>
                                    <el-radio-button value="large">{{ t('user_chat_page.a11y.size_large') }}</el-radio-button>
                                    <el-radio-button value="extra_large">{{ t('user_chat_page.a11y.size_xl') }}</el-radio-button>
                                </el-radio-group>
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">{{ t('user_chat_page.a11y.reduce_motion') }}</span>
                                <el-switch v-model="a11yReducedMotion" @change="setA11yReducedMotion" />
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">{{ t('user_chat_page.a11y.high_contrast') }}</span>
                                <el-switch v-model="a11yHighContrast" @change="setA11yHighContrast" />
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">{{ t('user_chat_page.a11y.auto_alt') }}</span>
                                <el-switch v-model="a11yAutoAlt" />
                                <span class="a11y-hint">{{ t('user_chat_page.a11y.auto_alt_hint') }}</span>
                            </div>
                            <div class="a11y-row">
                                <span class="a11y-label">{{ t('user_chat_page.a11y.msg_announce') }}</span>
                                <el-switch v-model="a11yMsgAnnounce" @change="setA11yMsgAnnounce" />
                                <span class="a11y-hint">{{ t('user_chat_page.a11y.msg_announce_hint') }}</span>
                            </div>
                            <el-divider style="margin:8px 0" />
                            <div class="a11y-actions">
                                <el-button text size="small" @click="readConversationSummary">{{ t('user_chat_page.a11y.read_summary') }}</el-button>
                                <router-link to="/a11y" class="a11y-more-link">{{ t('user_chat_page.a11y.more_settings') }} →</router-link>
                            </div>
                        </div>
                    </div>
                    <!-- SRCH-004: 会话内搜索 -->
                    <div class="conv-search-bar">
                        <el-popover placement="bottom" :width="300" trigger="click" v-model:visible="showConvSearch">
                            <template #reference>
                                <el-input v-model="convSearchKeyword" :placeholder="t('user_chat_page.conv_search.ph')" size="small" clearable @keydown.enter="doConvSearch" @clear="clearConvSearch" style="width:160px">
                                    <template #prefix><el-icon><Search /></el-icon></template>
                                </el-input>
                            </template>
                            <div class="conv-search-popover">
                                <div class="conv-search-filters">
                                    <el-input v-model="convSearchKeyword" size="small" :placeholder="t('user_chat_page.conv_search.keyword')" clearable @keydown.enter="doConvSearch" />
                                    <el-select v-model="convSearchType" size="small" :placeholder="t('user_chat_page.conv_search.msg_type')" clearable style="width:100%">
                                        <el-option :label="t('user_chat_page.conv_search.all_types')" value="" />
                                        <el-option :label="t('user_chat_page.conv_search.type_text')" value="text" />
                                        <el-option :label="t('user_chat_page.conv_search.type_image')" value="image" />
                                        <el-option :label="t('user_chat_page.conv_search.type_file')" value="file" />
                                        <el-option :label="t('user_chat_page.conv_search.type_voice')" value="voice" />
                                        <el-option :label="t('user_chat_page.conv_search.type_location')" value="location" />
                                        <el-option :label="t('user_chat_page.conv_search.type_card')" value="card" />
                                        <el-option :label="t('user_chat_page.conv_search.type_sticker')" value="sticker" />
                                    </el-select>
                                    <el-date-picker v-model="convSearchDateRange" type="daterange" size="small" :range-separator="t('user_chat_page.conv_search.date_sep')" :start-placeholder="t('user_chat_page.conv_search.start_date')" :end-placeholder="t('user_chat_page.conv_search.end_date')" value-format="YYYY-MM-DD" style="width:100%" />
                                </div>
                                <div class="conv-search-actions">
                                    <el-button size="small" type="primary" @click="doConvSearch">{{ t('actions.search') }}</el-button>
                                    <el-button size="small" @click="clearConvSearch">{{ t('user_chat_page.conv_search.clear') }}</el-button>
                                </div>
                                <div v-if="convSearchResults.length" class="conv-search-results">
                                    <div v-for="r in convSearchResults" :key="r.id" class="conv-search-item" @click="scrollToMessage(r.id); showConvSearch = false">
                                        <div class="conv-search-sender">{{ r.sender?.name || t('user_chat_page.msg.user') }}</div>
                                        <div class="conv-search-text" v-html="convHighlightKeyword(r.content, convSearchKeyword)"></div>
                                        <div class="conv-search-time">{{ formatTime(r.created_at) }}</div>
                                    </div>
                                </div>
                                <div v-else-if="convSearchSearched" class="conv-search-empty">{{ t('user_chat_page.conv_search.no_match') }}</div>
                            </div>
                        </el-popover>
                        <el-button v-if="convSearchResults.length" size="small" text @click="clearConvSearch" :title="t('user_chat_page.conv_search.clear')"><el-icon><Close /></el-icon></el-button>
                    </div>
                    <div class="chat-messages-wrap">
                        <div class="messages-area" ref="msgAreaRef" @scroll="onMessagesAreaScroll">
                        <div v-if="pinnedMessages.length" class="pinned-banner">
                            <div class="pinned-header">{{ t('user_chat_page.pinned.title') }} <span class="pinned-count">{{ pinnedMessages.length }}</span></div>
                            <div class="pinned-list">
                                <div v-for="pm in pinnedMessages.slice(0, 3)" :key="pm.id" class="pinned-item" @click="scrollToMessage(pm.id)">
                                    <span class="pinned-sender">{{ pm.sender?.name || t('user_chat_page.msg.user') }}:</span>
                                    <span class="pinned-content">{{ pm.content?.substring(0, 40) }}</span>
                                </div>
                                <div v-if="pinnedMessages.length > 3" class="pinned-more">{{ t('user_chat_page.pinned.more', { n: pinnedMessages.length - 3 }) }}</div>
                            </div>
                        </div>
                        <div v-if="hasMore" class="load-more"><el-button text size="small" @click="loadMore">{{ t('user_chat_page.load_more') }}</el-button></div>
                        <div v-for="msg in messages" :key="msg.id" :data-msg-id="msg.id" class="msg-item" :class="{ 'msg-self': msg.sender_id === myId, 'msg-selected': selectingForward && forwardMsgs.some(m => m.id === msg.id), 'msg-menu-active': msgContextMenu.visible && msgContextMenu.msg?.id === msg.id }" @click="selectingForward && toggleSelectForward(msg)" @contextmenu.prevent="onMsgContextMenu($event, msg)" @touchstart.passive="onMsgTouchStart($event, msg)" @touchmove.passive="onMsgTouchMove" @touchend.passive="onMsgTouchEnd" @touchcancel.passive="onMsgTouchEnd">
                            <div class="msg-avatar">
                                <img v-if="msg.sender?.avatar" :src="msg.sender.avatar" class="msg-avatar-img" @error="$event.target.style.display='none'" />
                                <span v-else>{{ msg.sender?.name?.charAt(0) || '?' }}</span>
                            </div>
                            <div class="msg-bubble" :class="{ 'msg-recalled': msg.is_recalled, 'msg-high-priority': msg.metadata?.priority === 'high' }">
                                <div v-if="msg.metadata?.priority === 'high'" class="msg-priority-badge">{{ t('user_chat_page.msg.urgent') }}</div>
                                <div v-else-if="msg.metadata?.priority === 'medium'" class="msg-priority-badge msg-priority-medium">{{ t('user_chat_page.msg.important') }}</div>
                                <div v-if="msg.sender_id !== myId" class="msg-sender">{{ msg.sender?.name || t('user_chat_page.msg.user') }}</div>
                                <div v-if="msg.reply_to" class="msg-reply" @click="scrollToMessage(msg.reply_to_id)">
                                    <div class="reply-sender">{{ msg.reply_to.sender?.name || t('user_chat_page.msg.user') }}</div>
                                    <div class="reply-text">{{ replyPreviewText(msg.reply_to) }}</div>
                                </div>
                                <div v-if="msg.is_recalled" class="msg-recalled-text">{{ t('user_chat_page.msg.recalled', { name: msg.sender_id === myId ? t('user_chat_page.msg.you') : msg.sender?.name || t('user_chat_page.msg.other') }) }}</div>
                                <div v-else-if="msg.message_type === 'image' && msg.content" class="msg-image">
                                    <img :src="msg.content" :alt="msg.metadata?.alt_text || t('user_chat_page.msg.image')" class="chat-image" @click="previewImage(msg.content)" @load="onImageLoaded(msg)" />
                                    <button v-if="msg.metadata?.alt_text" class="alt-text-btn" @click.stop="readAltText(msg)" :title="t('user_chat_page.a11y.read_summary')">🔊</button>
                                </div>
                                <div v-else-if="msg.message_type === 'voice' && msg.content" class="msg-voice-wrap">
                                    <div class="msg-voice" :class="{ 'msg-voice-self': msg.sender_id === myId }" @click="playVoice(msg)">
                                        <el-icon :size="20" style="margin-right:6px"><CaretRight v-if="!voicePlayingId || voicePlayingId !== msg.id" /><Loading v-else /></el-icon>
                                        <div class="voice-wave"><span v-for="i in 30" :key="i" class="voice-bar" :style="{ height: (10 + Math.sin(i * 0.5) * 8 + Math.random() * 4) + 'px' }"></span></div>
                                        <span class="voice-duration">{{ voiceDuration(msg) }}″</span>
                                    </div>
                                    <div class="msg-voice-actions" v-if="msg.sender_id === myId || msg.metadata?.transcript">
                                        <el-button v-if="!msg._transcribing && !msg.metadata?.transcript" text size="small" @click.stop="transcribeVoice(msg)">{{ t('user_chat_page.msg.transcribe') }}</el-button>
                                        <el-button v-else-if="msg._transcribing" text size="small" disabled>{{ t('user_chat_page.msg.transcribing') }}</el-button>
                                        <div v-else-if="msg.metadata?.transcript" class="voice-transcript" @click.stop>
                                            <el-icon style="margin-right:4px;color:#67c23a"><Reading /></el-icon>
                                            <span>{{ msg.metadata.transcript }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="msg.message_type === 'contact' && msg.content" class="msg-contact-card" @click="addContactByCard(msg)">
                                    <div class="contact-card-avatar">{{ contactName(msg)?.charAt(0) || '?' }}</div>
                                    <div class="contact-card-info"><div class="contact-card-name">{{ contactName(msg) }}</div><div class="contact-card-hint">{{ t('user_chat_page.msg.contact_hint') }}</div></div>
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
                                            <span class="file-action">{{ t('user_chat_page.msg.preview') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- 转发消息 -->
                                <div v-else-if="msg.message_type === 'forward'" class="msg-forward">
                                    <div class="forward-header">📨 {{ msg.metadata?.merge_forward ? t('user_chat_page.msg.forward_merge') : t('user_chat_page.msg.forward') }}</div>
                                    <div v-if="msg.metadata?.merge_forward" class="forward-preview">
                                        <div v-for="(item, fi) in (msg.attachments || msg.metadata?.items || [])" :key="fi" class="forward-item">
                                            <span class="forward-item-sender">{{ item.sender }}:</span>
                                            <span class="forward-item-content">{{ item.content }}</span>
                                        </div>
                                        <div class="forward-count">{{ t('user_chat_page.msg.msg_count', { n: msg.metadata?.message_count || (msg.attachments?.length || 0) }) }}</div>
                                    </div>
                                    <div v-else class="forward-single">
                                        <span class="forward-origin">{{ t('user_chat_page.msg.from', { name: msg.metadata?.original_sender || t('user_chat_page.msg.user') }) }}</span>
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
                                                <a :href="msg.metadata.product.action_url" target="_blank" class="card-btn primary">{{ msg.metadata.product.action_label || t('user_chat_page.msg.buy_now') }}</a>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 订单卡片 -->
                                    <template v-else-if="msg.metadata.type === 'order_card' && msg.metadata.order">
                                        <div class="card-body">
                                            <div class="card-kicker">{{ t('user_chat_page.msg.order') }}</div>
                                            <div class="card-title">{{ msg.metadata.order.order_number || msg.metadata.order.order_no || ('#' + (msg.metadata.order.order_id || msg.metadata.order.id || '')) }}</div>
                                            <div v-if="msg.metadata.order.item_name" class="card-desc">{{ msg.metadata.order.item_name }}</div>
                                            <div class="card-field"><span class="card-label">{{ t('user_chat_page.msg.amount') }}</span><span class="card-value">¥{{ msg.metadata.order.amount }}</span></div>
                                            <div class="card-field"><span class="card-label">{{ t('user_chat_page.table.status') }}</span><el-tag :type="orderCardStatusType(msg.metadata.order.status)" size="small">{{ orderCardStatusLabel(msg.metadata.order.status) }}</el-tag></div>
                                            <div class="card-actions">
                                                <a :href="msg.metadata.order.action_url || '/portal/orders'" target="_blank" class="card-btn primary">{{ msg.metadata.order.action_label || t('user_chat_page.msg.view_order') }}</a>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 售后/工单卡片 -->
                                    <template v-else-if="(msg.metadata.type === 'aftersale_card' || msg.metadata.type === 'ticket_card') && (msg.metadata.aftersale || msg.metadata.ticket)">
                                        <div class="card-body">
                                            <div class="card-kicker">{{ t('user_chat_page.msg.aftersale') }}</div>
                                            <div class="card-title">{{ (msg.metadata.aftersale || msg.metadata.ticket).subject || t('user_chat_page.msg.ticket_fallback') }}</div>
                                            <div class="card-field"><span class="card-label">{{ t('user_chat_page.table.status') }}</span><el-tag :type="aftersaleCardStatusType((msg.metadata.aftersale || msg.metadata.ticket).status)" size="small">{{ aftersaleCardStatusLabel((msg.metadata.aftersale || msg.metadata.ticket).status) }}</el-tag></div>
                                            <div v-if="(msg.metadata.aftersale || msg.metadata.ticket).priority" class="card-field"><span class="card-label">{{ t('user_chat_page.msg.priority') }}</span><span class="card-value">{{ (msg.metadata.aftersale || msg.metadata.ticket).priority }}</span></div>
                                            <div v-if="(msg.metadata.aftersale || msg.metadata.ticket).order_id" class="card-field"><span class="card-label">{{ t('user_chat_page.msg.order') }}</span><span class="card-value">#{{ (msg.metadata.aftersale || msg.metadata.ticket).order_id }}</span></div>
                                            <div class="card-actions">
                                                <a :href="(msg.metadata.aftersale || msg.metadata.ticket).action_url || ('/portal/tickets/' + ((msg.metadata.aftersale || msg.metadata.ticket).id || ''))" target="_blank" class="card-btn primary">{{ (msg.metadata.aftersale || msg.metadata.ticket).action_label || t('user_chat_page.msg.view_ticket') }}</a>
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
                                                <a :href="msg.metadata.article.action_url" target="_blank" class="card-btn primary">{{ msg.metadata.article.action_label || t('user_chat_page.msg.read_full') }}</a>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- 审批卡片 -->
                                    <template v-else-if="msg.metadata.type === 'approval_card' && msg.metadata.approval">
                                        <div class="card-body">
                                            <div class="card-title">📋 {{ msg.metadata.approval.title }}</div>
                                            <div v-if="msg.metadata.approval.applicant" class="card-field"><span class="card-label">{{ t('user_chat_page.msg.applicant') }}</span><span class="card-value">{{ msg.metadata.approval.applicant }}</span></div>
                                            <div v-if="msg.metadata.approval.amount" class="card-field"><span class="card-label">{{ t('user_chat_page.msg.amount') }}</span><span class="card-value">¥{{ msg.metadata.approval.amount }}</span></div>
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
                                            <div v-if="msg.metadata.coupon.expire_at" class="card-meta">⏳ {{ msg.metadata.coupon.expire_at }} {{ t('user_chat_page.msg.coupon_valid').replace('{date}', '') }}</div>
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
                                            <div v-if="msg.metadata.todo.assignee" class="card-field"><span class="card-label">{{ t('user_chat_page.msg.assignee') }}</span><span class="card-value">{{ msg.metadata.todo.assignee }}</span></div>
                                            <div v-if="msg.metadata.todo.deadline" class="card-field"><span class="card-label">{{ t('user_chat_page.msg.deadline') }}</span><span class="card-value">{{ msg.metadata.todo.deadline }}</span></div>
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
                                    <button v-if="isLongText(msg.content) && !msg._expanded" class="expand-btn" @click.stop="msg._expanded = true">{{ t('user_chat_page.msg.expand') }} <el-icon><ArrowDown /></el-icon></button>
                                    <button v-else-if="isLongText(msg.content) && msg._expanded" class="expand-btn expanded" @click.stop="msg._expanded = false">{{ t('user_chat_page.msg.collapse') }} <el-icon><ArrowUp /></el-icon></button>
                                </div>
                                <span v-if="msg.is_edited" class="edited-badge"> {{ t('user_chat_page.msg.edited') }}</span>
                                <div v-if="msg.attachments && msg.attachments.length" class="msg-attachments">
                                    <div v-for="(att, i) in msg.attachments" :key="i" class="msg-attachment-item">
                                        <template v-if="typeof att === 'string'"><a :href="att" target="_blank">📎 {{ t('user_chat_page.msg.attachment') }} {{ i + 1 }}</a></template>
                                        <template v-else>
                                            <a v-if="att.url && (att.mime||'').startsWith('image/')" :href="att.url" target="_blank" @click.stop.prevent="previewImage(att.url)"><img :src="att.url" alt="" class="attach-thumb" /><span class="attach-preview-label">{{ t('user_chat_page.msg.preview') }}</span></a>
                                            <a v-else :href="att.url" target="_blank" @click.stop.prevent="previewAttachment(att)">📎 {{ att.name || t('user_chat_page.msg.attachment') }}</a>
                                        </template>
                                    </div>
                                </div>
                                <div class="msg-encrypted" :title="t('user_chat_page.msg.encrypted')">🔒</div>
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
                                    <el-icon style="margin-right:2px;font-size:12px"><Select /></el-icon> {{ t('user_chat_page.msg.read_count', { n: msg.read_count }) }}
                                </div>
                            </div>
                        </div>
                        <div v-if="!messages.length" class="empty-chat" style="padding:60px 0"><el-empty :description="t('user_chat_page.empty.no_chat_messages')" :image-size="60" /></div>
                    </div>
                    <!-- THREAD: 话题面板 -->
                    <div v-if="activeThreadId" class="thread-panel">
                        <div class="thread-header">
                            <span>{{ t('user_chat_page.thread.title') }}</span>
                            <el-button text size="small" @click="closeThread"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div class="thread-parent-msg" v-if="threadParentMsg">
                            <div class="thread-parent-sender">{{ threadParentMsg.sender?.name || t('user_chat_page.msg.user') }}</div>
                            <div class="thread-parent-content">{{ threadParentMsg.content }}</div>
                        </div>
                        <div class="thread-replies" ref="threadReplyRef">
                            <div v-for="r in threadReplies" :key="r.id" class="thread-reply-item">
                                <div class="thread-reply-avatar">
                                    <img v-if="r.sender?.avatar" :src="r.sender.avatar" class="msg-avatar-img" />
                                    <span v-else>{{ r.sender?.name?.charAt(0) || '?' }}</span>
                                </div>
                                <div class="thread-reply-body">
                                    <div class="thread-reply-sender">{{ r.sender?.name || t('user_chat_page.msg.user') }} <span class="thread-reply-time">{{ formatTime(r.created_at) }}</span></div>
                                    <div class="thread-reply-text">{{ r.content }}</div>
                                </div>
                            </div>
                            <div v-if="!threadReplies.length && !loadingThread" class="thread-empty">{{ t('user_chat_page.thread.empty') }}</div>
                            <div v-if="loadingThread" class="thread-loading"><el-icon class="is-loading"><Loading /></el-icon></div>
                        </div>
                        <div class="thread-input-row">
                            <el-input v-model="threadInput" size="small" :placeholder="t('user_chat_page.thread.input_ph')" @keydown.enter.prevent="sendThreadReply" :disabled="sendingThread" />
                            <el-button size="small" type="primary" :loading="sendingThread" @click="sendThreadReply" :disabled="!threadInput.trim()">{{ t('user_chat_page.thread.reply') }}</el-button>
                        </div>
                    </div><!-- /thread-panel -->
                    </div><!-- /chat-messages-wrap -->
                    <div v-if="activeConv?.is_message_request" class="msg-request-chat-banner">
                        <div class="msg-request-chat-text">{{ t('user_chat_page.msg_request.preview_hint') }}</div>
                        <div class="msg-request-actions" @click.stop>
                            <el-button size="small" type="primary" @click="acceptMessageRequest(activeConv)">{{ t('user_chat_page.msg_request.accept') }}</el-button>
                            <el-button size="small" @click="rejectMessageRequest(activeConv)">{{ t('user_chat_page.msg_request.reject') }}</el-button>
                            <el-button size="small" type="danger" plain @click="rejectMessageRequest(activeConv, { block: true })">{{ t('user_chat_page.msg_request.block') }}</el-button>
                        </div>
                    </div>
                    <div v-else-if="activeConv?.is_outgoing_request && !activeConv?.stranger_limit?.recipient_replied" class="msg-request-chat-banner outgoing">
                        <div class="msg-request-chat-text">{{ t('user_chat_page.msg_request.outgoing_hint', { n: activeConv.stranger_limit?.remaining ?? 5, max: activeConv.stranger_limit?.max ?? 5 }) }}</div>
                    </div>
                    <div class="chat-input-area">
                        <div v-if="replyToMsg" class="reply-preview">
                            <div class="reply-preview-content">
                                <span class="reply-preview-label">{{ t('user_chat_page.composer.reply_to', { name: replyToMsg.sender?.name || t('user_chat_page.msg.user') }) }}</span>
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
                        <el-input v-model="inputMessage" type="textarea" :rows="3" :placeholder="t('user_chat_page.composer.input_ph')" @keydown.enter.exact.prevent="handleInputEnter" @keydown.up.prevent="slashSelectUp" @keydown.down.prevent="slashSelectDown" @input="onSlashInput" />
                        <div class="input-actions">
                            <div class="input-action-left">
                                <el-button text size="small" @click="showFileUpload = true" :title="t('user_chat_page.composer.upload')"><el-icon><Picture /></el-icon></el-button>
                                <el-button text size="small" @click="toggleVoiceRecord" :title="isRecording ? t('user_chat_page.composer.stop_record') : t('user_chat_page.composer.voice')" :type="isRecording ? 'danger' : 'default'">
                                    <template v-if="isRecording">{{ recordingDuration }}s</template>
                                    <el-icon v-else><Microphone /></el-icon>
                                </el-button>
                                <el-button text size="small" @click="showCannedPanel = !showCannedPanel" :title="t('user_chat_page.composer.canned')" :type="showCannedPanel ? 'primary' : 'default'"><el-icon><ChatDotRound /></el-icon></el-button>
                                <el-popover v-model:visible="showEmojiPopover" trigger="click" :width="300" placement="top-start">
                                    <template #reference>
                                        <el-button text size="small" :title="t('user_chat_page.composer.emoji')" :type="showEmojiPopover ? 'primary' : 'default'"><span style="font-size:18px;line-height:1">😀</span></el-button>
                                    </template>
                                    <div class="channel-emoji-picker">
                                        <div class="emoji-quick-row">
                                            <span v-for="e in commonEmojis.slice(0, 40)" :key="'qe-' + e" class="emoji-option" @click="insertInputEmoji(e)">{{ e }}</span>
                                        </div>
                                        <div v-if="customEmojis.length" class="emoji-custom-row">
                                            <div class="emoji-section-label">{{ t('user_chat_page.composer.enterprise_custom') }}</div>
                                            <span v-for="e in customEmojis" :key="'ce-' + e.id" class="emoji-option" :title="':' + e.shortcode + ':'" @click="insertCustomEmoji(e)">
                                                <img :src="e.image_url" class="custom-emoji-inline" />
                                            </span>
                                        </div>
                                        <div v-else class="emoji-empty-hint">
                                            {{ t('user_chat_page.empty.no_custom_emoji') }}
                                            <el-button text size="small" type="primary" @click="$router.push('/custom-emoji')">{{ t('user_chat_page.composer.go_manage') }}</el-button>
                                        </div>
                                    </div>
                                </el-popover>
                                <el-button text size="small" @click="showStickerPanel = !showStickerPanel" :title="t('user_chat_page.composer.sticker_gif')" :type="showStickerPanel ? 'primary' : 'default'"><el-icon><Mug /></el-icon></el-button>
                                <el-button text size="small" @click="showAIPanel = !showAIPanel" :title="t('user_chat_page.ai.panel')" :type="showAIPanel ? 'primary' : 'default'"><el-icon><MagicStick /></el-icon></el-button>
                                <el-dropdown trigger="click" v-if="inputMessage.trim()">
                                    <el-button text size="small" :title="t('user_chat_page.composer.ai_write')"><el-icon><EditPen /></el-icon></el-button>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item @click="aiWrite('polish')"><el-icon><Edit /></el-icon> {{ t('user_chat_page.composer.polish') }}</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('expand')"><el-icon><FullScreen /></el-icon> {{ t('user_chat_page.composer.expand') }}</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('translate')"><el-icon><ChatLineRound /></el-icon> {{ t('user_chat_page.composer.translate_zh') }}</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('formal')"><el-icon><Document /></el-icon> {{ t('user_chat_page.composer.formal') }}</el-dropdown-item>
                                            <el-dropdown-item @click="aiWrite('friendly')"><el-icon><Mug /></el-icon> {{ t('user_chat_page.composer.friendly') }}</el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <el-button text size="small" @click="markdownEnabled = !markdownEnabled" :title="markdownEnabled ? t('user_chat_page.composer.md_on') : t('user_chat_page.composer.md_off')" :type="markdownEnabled ? 'primary' : 'default'">
                                    <code style="font-weight:700">MD</code>
                                </el-button>
                                <el-button text size="small" @click="showLocationDialog = true" :title="t('user_chat_page.composer.location')"><el-icon><Location /></el-icon></el-button>
                                <el-button v-if="activeConv?.type === 'group'" text size="small" @click="openPollDialog" :title="t('user_chat_page.composer.poll')"><el-icon><Select /></el-icon></el-button>
                                <span class="text-muted">{{ t('user_chat_page.composer.enter_send') }}</span>
                                <el-button text size="small" @click="translateConversation" :title="t('user_chat_page.composer.translate_conv')"><el-icon><ChatLineRound /></el-icon></el-button>
                            </div>
                            <el-button type="primary" size="small" :loading="sending" @click="sendMessage">{{ t('user_chat_page.composer.send') }}</el-button>
                        </div>
                        <div v-if="smartReplies.length && activeConv" class="smart-replies-bar">
                            <span class="smart-replies-label">{{ t('user_chat_page.composer.smart_replies') }}</span>
                            <el-button v-for="(r, i) in smartReplies" :key="i" size="small" text @click="inputMessage = r; sendMessage()">{{ r }}</el-button>
                            <el-button size="small" text @click="smartReplies = []"><el-icon><Close /></el-icon></el-button>
                        </div>
                        <div v-if="showCannedPanel" class="canned-panel">
                            <div class="canned-header">
                                <span>{{ t('user_chat_page.composer.canned') }}</span>
                                <div>
                                    <el-button text size="small" @click="showCannedReplyManager = true; loadCannedReplies()">{{ t('user_chat_page.composer.manage') }}</el-button>
                                    <el-button text size="small" @click="showCannedPanel = false"><el-icon><Close /></el-icon></el-button>
                                </div>
                            </div>
                            <div class="canned-categories">
                                <el-radio-group v-model="cannedCategory" size="small">
                                    <el-radio-button label="">{{ t('user_chat_page.category.all') }}</el-radio-button>
                                    <el-radio-button v-for="cat in cannedCategories" :key="cat" :label="cat">{{ cannedCatLabel(cat) }}</el-radio-button>
                                </el-radio-group>
                            </div>
                            <div class="canned-list">
                                <div v-for="r in filteredCanned" :key="r.id" class="canned-item" @click="selectCanned(r)">
                                    <div class="canned-title">{{ r.title }}</div>
                                    <div class="canned-preview">{{ r.content }}</div>
                                </div>
                                <div v-if="!filteredCanned.length" style="padding:20px;text-align:center;color:#999">{{ t('user_chat_page.empty.no_canned') }}</div>
                            </div>
                        </div>
                        <div v-if="showAIPanel" class="ai-panel">
                            <div class="ai-header"><span>{{ t('user_chat_page.ai.panel') }}</span><el-button text size="small" @click="showAIPanel = false"><el-icon><Close /></el-icon></el-button></div>
                            <div class="ai-messages" ref="aiMsgRef">
                                <div v-for="(m, i) in aiMessages" :key="i" class="ai-message" :class="m.role">
                                    <span class="ai-role-tag">{{ m.role === 'assistant' ? 'AI' : t('user_chat_page.ai.role_me') }}</span>
                                    <span class="ai-msg-content">{{ m.content }}</span>
                                </div>
                                <div v-if="aiStreaming" class="ai-streaming-indicator">{{ t('user_chat_page.ai.streaming') }} <el-icon class="is-loading" style="margin-left:4px"><Loading /></el-icon></div>
                            </div>
                            <div class="ai-input-row">
                                <el-input v-model="aiInput" size="small" :placeholder="t('user_chat_page.ai.input_ph')" @keydown.enter.prevent="sendAiMessage" :disabled="aiStreaming" />
                                <el-button size="small" type="primary" :loading="aiLoading" @click="sendAiMessage" :disabled="aiStreaming">{{ aiStreaming ? t('user_chat_page.ai.typing') : t('user_chat_page.composer.send') }}</el-button>
                            </div>
                        </div>
                        <div v-if="showStickerPanel" class="sticker-panel">
                            <div class="sticker-header">
                                <span>{{ t('user_chat_page.composer.sticker_gif') }}</span>
                                <el-radio-group v-model="stickerTab" size="small">
                                    <el-radio-button value="emoji">{{ t('user_chat_page.composer.sticker_tab_emoji') }}</el-radio-button>
                                    <el-radio-button value="stickers">{{ t('user_chat_page.composer.sticker_tab_stickers') }}</el-radio-button>
                                    <el-radio-button value="custom">{{ t('user_chat_page.composer.sticker_tab_custom') }}</el-radio-button>
                                    <el-radio-button value="gif">{{ t('user_chat_page.composer.sticker_tab_gif') }}</el-radio-button>
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
                                <div v-if="stickerPacks.length === 0" style="padding:20px;text-align:center;color:#999">{{ t('user_chat_page.empty.no_stickers') }}</div>
                                <div v-for="pack in stickerPacks" :key="pack.id" class="sticker-pack">
                                    <div class="sticker-pack-name">{{ pack.name }}</div>
                                    <div class="sticker-pack-items">
                                        <img v-for="s in pack.stickers" :key="s.id" :src="s.image_url" class="sticker-item" @click="sendStickerDirectly({ sticker: s })" :title="s.name" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="stickerTab === 'custom'" class="sticker-grid">
                                <div v-if="customEmojis.length === 0" style="padding:20px;text-align:center;color:#999">{{ t('user_chat_page.empty.no_custom_emoji') }}</div>
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
                                    <el-input v-model="gifQuery" size="small" :placeholder="t('user_chat_page.composer.gif_search_ph')" @keydown.enter.prevent="searchGif" />
                                    <el-button size="small" type="primary" @click="searchGif">{{ t('actions.search') }}</el-button>
                                </div>
                                <div v-if="gifResults.length" class="gif-grid">
                                    <img v-for="(gif, i) in gifResults" :key="i" :src="gif.preview || gif.url" class="gif-item" @click="sendStickerDirectly({ gif })" :title="gif.title" />
                                </div>
                                <div v-else style="padding:20px;text-align:center;color:#999">{{ gifQuery ? t('user_chat_page.empty.no_gif_results') : t('user_chat_page.empty.gif_hint') }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="typing-indicator" v-if="typingUsers.length"><span>{{ t('user_chat_page.composer.typing', { names: typingUsers.join(', ') }) }}</span></div>
                </template>
                <template v-else>
                    <div class="empty-state"><el-empty :description="t('user_chat_page.empty.select_conv')" :image-size="80" /></div>
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

        <!-- 会话列表右键菜单 -->
        <teleport to="body">
            <div v-if="convContextMenu.visible" class="msg-context-overlay" @click="closeConvContextMenu" @contextmenu.prevent="closeConvContextMenu"></div>
            <div v-if="convContextMenu.visible" class="msg-context-menu" :style="{ left: convContextMenu.x + 'px', top: convContextMenu.y + 'px' }" @click.stop @contextmenu.prevent>
                <button v-for="(item, i) in convContextMenu.items" :key="i" type="button" class="msg-context-item" :class="{ danger: item.danger, divided: item.divided }" @click="runConvContextAction(item)">{{ item.label }}</button>
            </div>
        </teleport>

        <!-- ====== Copilot 浮动按钮 ====== -->
        <el-button class="copilot-fab" circle :type="showCopilot ? 'primary' : 'default'" @click="showCopilot = !showCopilot" title="Copilot">
            <el-icon style="font-size:20px"><MagicStick /></el-icon>
        </el-button>
        <transition name="slide-right">
            <div v-if="showCopilot" class="copilot-sidebar">
                <div class="copilot-header">
                    <span>{{ t('user_chat_page.copilot.title') }}</span>
                    <el-button text size="small" @click="showCopilot = false"><el-icon><Close /></el-icon></el-button>
                </div>
                <div class="copilot-actions">
                    <el-button size="small" @click="copilotAction('summarize')" :disabled="!activeConv"><el-icon><MagicStick /></el-icon> {{ t('user_chat_page.copilot.summarize') }}</el-button>
                    <el-button size="small" @click="copilotAction('extract')" :disabled="!activeConv"><el-icon><List /></el-icon> {{ t('user_chat_page.copilot.extract') }}</el-button>
                    <el-button size="small" @click="copilotAction('tag')" :disabled="!activeConv"><el-icon><CollectionTag /></el-icon> {{ t('user_chat_page.copilot.tag') }}</el-button>
                    <el-button size="small" @click="copilotAction('translate')" :disabled="!inputMessage.trim()"><el-icon><ChatLineRound /></el-icon> {{ t('user_chat_page.copilot.translate_input') }}</el-button>
                </div>
                <div class="copilot-content" ref="copilotRef">
                    <div v-if="copilotLoading" class="copilot-loading"><el-icon class="is-loading"><Loading /></el-icon> {{ t('user_chat_page.copilot.thinking') }}</div>
                    <div v-for="(item, i) in copilotResults" :key="i" class="copilot-result-item">
                        <div class="copilot-result-title">{{ item.title }}</div>
                        <div class="copilot-result-body">{{ item.content }}</div>
                    </div>
                    <div v-if="!copilotResults.length && !copilotLoading" class="copilot-empty">{{ t('user_chat_page.copilot.empty') }}</div>
                </div>
            </div>
        </transition>

        <!-- ====== 对话框 ====== -->
        <el-dialog
            v-model="showNewChat"
            :title="newChatMode === 'group' ? t('user_chat_page.dialogs.create_group_chat') : t('user_chat_page.dialogs.start_dm')"
            width="400px"
            @closed="resetNewChat"
        >
            <p v-if="newChatMode === 'group'" class="dialog-hint" style="margin-bottom:12px">{{ t('user_chat_page.dialogs.create_group_hint') }}</p>
            <p v-else class="dialog-hint" style="margin-bottom:12px">{{ t('user_chat_page.dialogs.start_dm_hint') }}</p>
            <el-input
                v-if="newChatMode === 'group'"
                v-model="newGroupName"
                :placeholder="t('user_chat_page.dialogs.group_name_ph')"
                maxlength="100"
                clearable
                style="margin-bottom:12px"
            />
            <el-select
                v-model="newChatUserIds"
                multiple
                filterable
                remote
                :remote-method="searchUsers"
                :loading="searching"
                :multiple-limit="newChatMode === 'dm' ? 1 : 0"
                :placeholder="t('user_chat_page.dialogs.search_users')"
                style="width:100%"
            >
                <el-option v-for="u in searchedUsers" :key="u.id" :label="u.name" :value="u.id" />
            </el-select>
            <template #footer>
                <el-button @click="showNewChat = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="creatingChat" @click="createNewChat">
                    {{ newChatMode === 'group' ? t('user_chat_page.dialogs.create_group_chat') : t('user_chat_page.dialogs.start_chat') }}
                </el-button>
            </template>
        </el-dialog>
        <el-dialog v-model="showAddFriend" :title="t('user_chat_page.dialogs.add_friend')" width="400px">
            <el-select v-model="addFriendUserId" filterable remote :remote-method="searchUsers" :loading="searching" :placeholder="t('user_chat_page.dialogs.search_users_full')" style="width:100%">
                <el-option v-for="u in searchedUsers" :key="u.id" :label="u.name" :value="u.id">
                    <div class="user-option"><span class="user-opt-name">{{ u.name }}</span><span class="user-opt-email">{{ u.email }}</span></div>
                </el-option>
            </el-select>
            <div class="dialog-hint">{{ t('user_chat_page.dialogs.add_friend_hint') }}</div>
            <template #footer><el-button @click="showAddFriend = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="addFriendLoading" @click="submitAddFriend">{{ t('user_chat_page.dialogs.send_request') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showPendingRequests" :title="t('user_chat_page.dialogs.friend_requests')" width="420px">
            <div v-for="req in pendingRequests" :key="req.id" class="pending-req-item">
                <div class="req-user-avatar">{{ (req.sender?.name || req.user?.name || '?').charAt(0) }}</div>
                <div class="add-friend-info">
                    <div class="req-user-name"><strong>{{ req.sender?.name || req.user?.name || t('user_chat_page.msg.user') }}</strong></div>
                    <div class="req-user-email">{{ req.sender?.email || req.user?.email || '' }}</div>
                    <div style="font-size:12px;color:#909399">{{ t('user_chat_page.friends.request_add') }}</div>
                </div>
                <div style="display:flex;gap:4px">
                    <el-button size="small" type="primary" @click="handleFriendRequest(req.id, 'accepted')">{{ t('user_chat_page.msg_request.accept') }}</el-button>
                    <el-button size="small" @click="handleFriendRequest(req.id, 'rejected')">{{ t('user_chat_page.msg_request.reject') }}</el-button>
                </div>
            </div>
            <div v-if="!pendingRequests.length"><el-empty :description="t('user_chat_page.friends.no_requests')" :image-size="50" /></div>
        </el-dialog>
        <el-dialog v-model="showFriendGroups" :title="t('user_chat_page.dialogs.friend_groups')" width="400px">
            <div v-for="g in friendGroups" :key="g.id" class="friend-group-item">
                <el-input v-model="g.name" size="small" style="flex:1;margin-right:8px" @change="updateFriendGroup(g)" />
                <el-button text size="small" type="danger" @click="deleteFriendGroup(g.id)">{{ t('actions.delete') }}</el-button>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px">
                <el-input v-model="newFriendGroupName" :placeholder="t('user_chat_page.dialogs.new_group')" size="small" />
                <el-button size="small" type="primary" @click="createFriendGroup">{{ t('user_chat_page.dialogs.add') }}</el-button>
            </div>
        </el-dialog>
        <el-dialog v-model="showRemarkDialog" :title="t('user_chat_page.dialogs.set_remark')" width="360px">
            <el-input v-model="remarkText" :placeholder="t('user_chat_page.dialogs.remark_ph')" />
            <template #footer><el-button @click="showRemarkDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitRemark">{{ t('actions.save') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showMoveGroupDialog" :title="t('user_chat_page.dialogs.move_group')" width="360px">
            <el-select v-model="moveGroupId" :placeholder="t('user_chat_page.dialogs.select_group')" style="width:100%">
                <el-option v-for="g in friendGroups" :key="g.id" :label="g.name" :value="g.id" />
            </el-select>
            <template #footer><el-button @click="showMoveGroupDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitMoveGroup">{{ t('user_chat_page.dialogs.move') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showForward" :title="forwardMsgs.length > 1 ? t('user_chat_page.dialogs.forward_merge_title', { n: forwardMsgs.length }) : t('user_chat_page.dialogs.forward_title')" width="420px">
            <el-select v-model="forwardConvId" filterable remote :remote-method="searchForwardConvs" :loading="forwardSearching" :placeholder="t('user_chat_page.dialogs.forward_ph')" style="width:100%">
                <el-option v-for="c in forwardableConvs" :key="c.id" :label="c.name" :value="c.id">
                    <span>{{ c.type === 'group' ? '👥' : '👤' }} {{ c.name }}</span>
                </el-option>
            </el-select>
            <div v-if="forwardMsgs.length > 1" class="text-sm text-gray-400 mt-2">{{ t('user_chat_page.dialogs.forward_merge_hint', { n: forwardMsgs.length }) }}</div>
            <template #footer>
                <el-button @click="showForward = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="forwarding" @click="submitForward">{{ t('user_chat_page.dialogs.forward_btn', { n: forwardMsgs.length }) }}</el-button>
            </template>
        </el-dialog>
        <el-dialog :model-value="!!editingMsg" :title="t('user_chat_page.dialogs.edit_msg')" width="480px" @update:model-value="val => { if(!val) editingMsg = null }" @close="editingMsg = null">
            <el-input v-model="editContent" type="textarea" :rows="4" :placeholder="t('user_chat_page.dialogs.edit_ph')" />
            <template #footer><el-button @click="editingMsg = null">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitEdit">{{ t('actions.save') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showAnnouncements" :title="t('user_chat_page.header.announcements')" width="520px" @open="loadAnnouncements">
            <div v-if="!announcements.length && !loadingAnnouncements"><el-empty :description="t('user_chat_page.dialogs.no_announcements')" :image-size="50" /></div>
            <div v-loading="loadingAnnouncements" style="min-height:100px">
                <div v-for="a in announcements" :key="a.id" class="announcement-item">
                    <div class="announcement-header"><span class="announcement-title">{{ a.title }}</span><span class="announcement-time">{{ formatTime(a.created_at) }}</span></div>
                    <div class="announcement-content">{{ a.content }}</div>
                    <div class="announcement-footer">
                        <span>{{ t('user_chat_page.dialogs.published_by', { name: a.sender?.name }) }}</span>
                        <span>📖 {{ t('user_chat_page.msg.read_count', { n: a.reads_count || a.read_count || 0 }) }}
                            <el-button v-if="!a.is_read" text size="small" type="primary" @click="markAnnouncementRead(a)">{{ t('user_chat_page.dialogs.mark_read') }}</el-button>
                            <el-button v-else text size="small" @click="showAnnouncementDetail(a)">{{ t('user_chat_page.dialogs.view_detail') }}</el-button>
                        </span>
                    </div>
                </div>
            </div>
            <template #footer><el-button @click="showCreateAnnouncement = true" type="primary">{{ t('user_chat_page.dialogs.publish_announcement') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showCreateAnnouncement" :title="t('user_chat_page.dialogs.create_announcement')" width="480px">
            <el-form label-position="top">
                <el-form-item :label="t('user_chat_page.dialogs.ann_title')"><el-input v-model="newAnnouncement.title" :placeholder="t('user_chat_page.dialogs.ann_title_ph')" maxlength="200" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.ann_content')"><el-input v-model="newAnnouncement.content" type="textarea" :rows="5" :placeholder="t('user_chat_page.dialogs.ann_content_ph')" maxlength="10000" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showCreateAnnouncement = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="creatingAnnouncement" @click="submitAnnouncement">{{ t('user_chat_page.dialogs.publish') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showAnnouncementDetailDialog" :title="t('user_chat_page.dialogs.read_detail')" width="420px">
            <div v-if="announcementDetail">
                <div class="announcement-detail-header">{{ announcementDetail.title }}</div>
                <div style="margin:8px 0;font-size:13px;color:#666">{{ t('user_chat_page.dialogs.read_stats', { total: announcementDetail.total_members, read: announcementDetail.read_count, unread: announcementDetail.unread_count }) }}</div>
                <el-progress :percentage="announcementDetail.total_members > 0 ? Math.round(announcementDetail.read_count / announcementDetail.total_members * 100) : 0" :stroke-width="14" :text-inside="true" :status="announcementDetail.read_count === announcementDetail.total_members ? 'success' : 'warning'" />
                <div style="margin-top:12px"><div class="read-list-title">{{ t('user_chat_page.dialogs.read_members') }}</div>
                    <div v-if="announcementDetail.read_users?.length"><el-tag v-for="u in announcementDetail.read_users" :key="u.id" size="small" style="margin:3px">{{ u.name }}</el-tag></div>
                    <div v-else style="color:#999;font-size:13px">{{ t('user_chat_page.dialogs.no_read') }}</div>
                </div>
            </div>
        </el-dialog>
        <el-dialog v-model="showSlowModeDialog" :title="t('user_chat_page.dialogs.slow_mode')" width="400px">
            <p style="font-size:13px;color:#666;margin-bottom:12px">{{ t('user_chat_page.dialogs.slow_mode_desc') }}</p>
            <el-form label-position="top">
                <el-form-item :label="t('user_chat_page.dialogs.slow_interval')"><el-select v-model="slowModeInterval" style="width:100%">
                    <el-option v-for="o in slowModeOptions" :key="o.value" :value="o.value" :label="o.label" />
                </el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="showSlowModeDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="savingSlowMode" @click="saveSlowMode">{{ t('actions.save') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showInviteDialog" :title="t('user_chat_page.dialogs.invite')" width="480px" @open="loadInvites">
            <el-tabs v-model="inviteTab">
                <el-tab-pane :label="t('user_chat_page.dialogs.gen_invite')" name="create">
                    <el-form label-position="top">
                        <el-form-item :label="t('user_chat_page.dialogs.expire_time')"><el-select v-model="inviteExpires" style="width:100%">
                            <el-option v-for="o in inviteExpireOptions" :key="o.value" :value="o.value" :label="o.label" />
                        </el-select></el-form-item>
                        <el-form-item :label="t('user_chat_page.dialogs.uses_limit')"><el-select v-model="inviteMaxUses" style="width:100%">
                            <el-option v-for="o in inviteUsesOptions" :key="o.value" :value="o.value" :label="o.label" />
                        </el-select></el-form-item>
                        <el-button type="primary" :loading="creatingInvite" @click="createInvite" style="width:100%">{{ t('user_chat_page.dialogs.gen_link') }}</el-button>
                        <div v-if="newInviteUrl" style="margin-top:12px">
                            <div style="font-size:13px;color:#666;margin-bottom:6px">{{ t('user_chat_page.dialogs.invite_link') }}</div>
                            <el-input v-model="newInviteUrl" readonly><template #append><el-button @click="copyInviteUrl">{{ t('actions.copy') }}</el-button></template></el-input>
                        </div>
                    </el-form>
                </el-tab-pane>
                <el-tab-pane :label="t('user_chat_page.dialogs.generated_links')" name="list">
                    <div v-if="invites.length === 0" style="text-align:center;padding:20px;color:#999">{{ t('user_chat_page.dialogs.no_invites') }}</div>
                    <div v-for="inv in invites" :key="inv.id" class="invite-item">
                        <div class="invite-info">
                            <div class="invite-url">{{ inv.url }}</div>
                            <div class="invite-meta">{{ formatInviteMeta(inv) }}</div>
                        </div>
                        <el-button v-if="inv.is_valid" text size="small" type="danger" @click="revokeInvite(inv)">{{ t('user_chat_page.dialogs.revoke') }}</el-button>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-dialog>
        <el-dialog v-model="showFolderDialog" :title="t('user_chat_page.dialogs.manage_folders')" width="420px">
            <div v-if="folders.length === 0" style="text-align:center;padding:16px;color:#999">{{ t('user_chat_page.dialogs.no_folders') }}</div>
            <div v-for="f in folders" :key="f.id" class="folder-item">
                <el-input v-model="f.name" size="small" style="flex:1;margin-right:8px" @change="saveFolders" />
                <el-button text size="small" type="danger" @click="deleteFolder(f)">{{ t('user_chat_page.friends.delete') }}</el-button>
            </div>
            <div style="margin-top:12px;display:flex;gap:8px">
                <el-input v-model="newFolderName" :placeholder="t('user_chat_page.dialogs.new_folder')" size="small" />
                <el-button size="small" type="primary" @click="createFolder">{{ t('user_chat_page.dialogs.add') }}</el-button>
            </div>
        </el-dialog>
        <el-dialog v-model="showLocationDialog" :title="t('user_chat_page.composer.location')" width="420px">
            <el-form label-position="top">
                <el-form-item :label="t('user_chat_page.dialogs.place_name')"><el-input v-model="locationNameInput" :placeholder="t('user_chat_page.dialogs.place_ph')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.lng')"><el-input-number v-model="locationLng" :precision="6" :step="0.01" style="width:100%" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.lat')"><el-input-number v-model="locationLat" :precision="6" :step="0.01" style="width:100%" /></el-form-item>
                <el-form-item><el-button @click="getCurrentLocation" :loading="gettingLocation">{{ t('user_chat_page.dialogs.get_location') }}</el-button></el-form-item>
            </el-form>
            <template #footer><el-button @click="showLocationDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="sendingLocation" @click="sendLocation">{{ t('user_chat_page.composer.send') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showDndSettings" :title="t('user_chat_page.dialogs.dnd')" width="400px" @open="loadDndSettings">
            <el-form label-position="top">
                <el-form-item><el-switch v-model="dndEnabled" :active-text="t('user_chat_page.dialogs.dnd_enable')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.start_time')" v-if="dndEnabled"><el-time-picker v-model="dndStart" format="HH:mm" value-format="HH:mm" style="width:100%" :placeholder="t('user_chat_page.dialogs.select_start')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.end_time')" v-if="dndEnabled"><el-time-picker v-model="dndEnd" format="HH:mm" value-format="HH:mm" style="width:100%" :placeholder="t('user_chat_page.dialogs.select_end')" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showDndSettings = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="saveDndSettings">{{ t('actions.save') }}</el-button></template>
        </el-dialog>
        <el-dialog v-model="showBlockedList" :title="t('user_chat_page.toolbar.blocked')" width="400px">
            <div v-if="blockedUsers.length === 0"><el-empty :description="t('user_chat_page.dialogs.no_blocked')" :image-size="50" /></div>
            <div v-for="u in blockedUsers" :key="u.id" class="pending-req-item">
                <div class="add-friend-info"><strong>{{ u.name || t('user_chat_page.msg.user') }}</strong></div>
                <el-button size="small" @click="unblockUser(u.id)">{{ t('user_chat_page.dialogs.unblock') }}</el-button>
            </div>
        </el-dialog>
        <el-dialog v-model="showCreateTicket" :title="t('user_chat_page.dialogs.create_ticket')" width="480px">
            <el-form label-position="top">
                <el-form-item :label="t('user_chat_page.dialogs.subject')"><el-input v-model="ticketSubject" :placeholder="t('user_chat_page.dialogs.subject_ph')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.description')"><el-input v-model="ticketDescription" type="textarea" :rows="4" :placeholder="t('user_chat_page.dialogs.desc_ph')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.priority')"><el-select v-model="ticketPriority" style="width:100%">
                    <el-option :label="t('user_chat_page.priority.low')" value="low" /><el-option :label="t('user_chat_page.priority.medium')" value="medium" /><el-option :label="t('user_chat_page.priority.high')" value="high" /><el-option :label="t('user_chat_page.priority.urgent')" value="urgent" />
                </el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="showCreateTicket = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="creatingTicket" @click="submitTicket">{{ t('actions.submit') }}</el-button></template>
        </el-dialog>
        <!-- 举报 -->
        <el-dialog v-model="showReportDialog" :title="t('user_chat_page.dialogs.report')" width="400px">
            <el-form label-position="top">
                <el-form-item :label="t('user_chat_page.dialogs.report_target')">
                    <el-tag>{{ reportTarget?.name || (reportTarget?.sender?.name || t('user_chat_page.msgs.message_fallback')) }}</el-tag>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.report_reason')">
                    <el-select v-model="reportReason" style="width:100%">
                        <el-option v-for="o in reportReasonOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.report_extra')">
                    <el-input v-model="reportDescription" type="textarea" :rows="3" maxlength="1000" :placeholder="t('user_chat_page.dialogs.report_extra_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showReportDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" :loading="submittingReport" @click="submitReport">{{ t('user_chat_page.dialogs.submit_report') }}</el-button>
            </template>
        </el-dialog>
        <!-- AI-015: 提取待办 -->
        <el-dialog v-model="showTaskDialog" :title="t('user_chat_page.dialogs.tasks')" width="500px">
            <div v-if="taskLoading" style="text-align:center;padding:30px"><el-icon class="is-loading" style="font-size:24px"><Loading /></el-icon><p>{{ t('user_chat_page.dialogs.analyzing') }}</p></div>
            <div v-else-if="extractedTasks.length === 0" style="text-align:center;padding:30px;color:#999">{{ t('user_chat_page.dialogs.no_tasks') }}</div>
            <div v-else>
                <div v-for="(t, i) in extractedTasks" :key="i" class="task-item">
                    <el-tag :type="t.type === 'event' ? 'warning' : 'primary'" size="small">{{ t.type === 'event' ? t('user_chat_page.dialogs.task_event') : t('user_chat_page.dialogs.task_todo') }}</el-tag>
                    <span class="task-title">{{ t.title }}</span>
                    <span v-if="t.deadline" class="task-deadline">⏰ {{ t.deadline }}</span>
                    <span v-if="t.assignee" class="task-assignee">👤 {{ t.assignee }}</span>
                </div>
            </div>
            <template #footer><el-button @click="showTaskDialog = false">{{ t('actions.close') }}</el-button></template>
        </el-dialog>
        <!-- AI 好友创建 -->
        <el-dialog v-model="showCreateAiFriend" :title="t('user_chat_page.dialogs.create_ai_friend')" width="520px">
            <el-form label-position="top" size="small">
                <el-form-item :label="t('user_chat_page.form.name')">
                    <el-input v-model="newAiFriend.name" :placeholder="t('user_chat_page.form.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.avatar')">
                    <div class="avatar-selector">
                        <div class="avatar-preview">
                            <img :src="newAiFriend.avatar_url || getDefaultAvatar(newAiFriend.name)" class="avatar-img" />
                        </div>
                        <div class="avatar-options">
                            <div v-for="a in presetAvatars" :key="a.url" class="avatar-option" :class="{ selected: newAiFriend.avatar_url === a.url }" @click="newAiFriend.avatar_url = a.url">
                                <img :src="a.url" :title="a.label" />
                            </div>
                            <el-tooltip :content="t('user_chat_page.form.custom_avatar_url')">
                                <el-button size="small" circle :type="newAiFriend.avatar_url && !presetAvatars.find(p=>p.url===newAiFriend.avatar_url) ? 'primary' : 'default'" @click="openAvatarInput = !openAvatarInput">URL</el-button>
                            </el-tooltip>
                            <el-upload :show-file-list="false" :http-request="uploadPersonalAvatar" accept="image/*">
                                <el-tooltip :content="t('user_chat_page.form.upload_avatar')">
                                    <el-button size="small" circle type="success">+</el-button>
                                </el-tooltip>
                            </el-upload>
                        </div>
                        <el-input v-if="openAvatarInput" v-model="newAiFriend.avatar_url" :placeholder="t('user_chat_page.form.custom_avatar_url') + '...'" size="small" style="margin-top:6px" />
                    </div>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.type')">
                    <el-select v-model="newAiFriend.category" style="width:100%">
                        <el-option v-for="o in aiCategoryOptions" :key="o.value" :value="o.value" :label="o.label" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.provider')">
                    <el-select v-model="newAiFriend.provider" style="width:100%">
                        <el-option label="DeepSeek" value="deepseek" />
                        <el-option label="OpenAI" value="openai" />
                        <el-option label="Claude" value="claude" />
                        <el-option :label="t('user_chat_page.form.cat_custom') + ' API'" value="custom" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.model')">
                    <el-input v-model="newAiFriend.model_name" :placeholder="t('user_chat_page.form.model_ph')" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.api_key')">
                    <el-input v-model="newAiFriend.api_key" type="password" :placeholder="t('user_chat_page.form.api_key_ph')" show-password />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.api_base')" v-if="newAiFriend.provider === 'custom'">
                    <el-input v-model="newAiFriend.api_base_url" placeholder="https://api.example.com/v1" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.system_prompt')">
                    <el-input v-model="newAiFriend.system_prompt" type="textarea" :rows="3" :placeholder="t('user_chat_page.form.system_prompt_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateAiFriend = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="creatingAiFriend" @click="submitCreateAiFriend">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>
        <!-- AI 好友管理（管理员） -->
        <el-dialog v-model="showAiFriendAdmin" :title="t('user_chat_page.dialogs.ai_friend_admin')" width="700px" @open="loadAdminAiFriends">
            <template #header><span>{{ t('user_chat_page.dialogs.ai_friend_admin') }} <span style="font-size:12px;color:#999;font-weight:400">— {{ t('user_chat_page.dialogs.admin_hint') }}</span></span></template>
            <div class="admin-ai-header">
                <el-button size="small" type="primary" @click="showCreatePlatformAi = true"><el-icon><Plus /></el-icon> {{ t('user_chat_page.dialogs.create_platform') }}</el-button>
                <el-tag v-if="adminAiFriends.length">{{ t('user_chat_page.table.total', { n: adminAiFriends.length }) }}</el-tag>
            </div>
            <el-table :data="adminAiFriends" v-loading="loadingAdminAi" :empty-text="t('user_chat_page.table.empty_platform')" size="small" style="width:100%">
                <el-table-column :label="t('user_chat_page.table.avatar')" width="60">
                    <template #default="{ row }">
                        <img :src="row.user?.avatar || getDefaultAvatar(row.user?.name)" style="width:32px;height:32px;border-radius:50%" />
                    </template>
                </el-table-column>
                <el-table-column :label="t('user_chat_page.table.name')" prop="user.name" />
                <el-table-column :label="t('user_chat_page.table.category')" width="100">
                    <template #default="{ row }">{{ categoryLabel(row.category) }}</template>
                </el-table-column>
                <el-table-column :label="t('user_chat_page.table.model')" width="140">
                    <template #default="{ row }">{{ row.llm_config?.provider || '-' }} / {{ row.llm_config?.model_name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('user_chat_page.table.status')" width="80">
                    <template #default="{ row }"><el-tag :type="row.published_at ? 'success' : 'info'" size="small">{{ row.published_at ? t('user_chat_page.table.published') : t('user_chat_page.table.draft') }}</el-tag></template>
                </el-table-column>
                <el-table-column :label="t('user_chat_page.table.actions')" width="240">
                    <template #default="{ row }">
                        <el-button text size="small" @click="testAiFriend(row.id)" :loading="testingId === row.id">{{ t('user_chat_page.table.test') }}</el-button>
                        <el-button v-if="!row.published_at" text size="small" type="primary" @click="publishAiFriend(row.id)">{{ t('user_chat_page.dialogs.publish') }}</el-button>
                        <el-tag v-else size="small" type="success">{{ t('user_chat_page.table.published') }}</el-tag>
                        <el-button text size="small" @click="viewAiFriendConvs(row)">{{ t('user_chat_page.table.chats') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>
        <!-- AI 好友对话记录 -->
        <el-dialog v-model="showAiFriendConvs" :title="t('user_chat_page.dialogs.ai_convs') + ': ' + (aiFriendConvsName || '')" width="560px" @open="loadAiFriendConvs">
            <div v-loading="loadingAiFriendConvs">
                <div v-if="aiFriendConvs.length === 0" style="text-align:center;padding:32px 0;color:#999">{{ t('user_chat_page.dialogs.no_ai_convs') }}</div>
                <div v-for="conv in aiFriendConvs" :key="conv.id" style="border:1px solid #e4e7ed;border-radius:6px;padding:10px;margin-bottom:8px">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                        <img v-if="conv.user?.avatar" :src="conv.user.avatar" style="width:24px;height:24px;border-radius:50%" />
                        <span v-else style="width:24px;height:24px;border-radius:50%;background:#409eff;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px">{{ conv.user?.name?.charAt(0) || '?' }}</span>
                        <span style="font-size:13px;font-weight:500">{{ conv.user?.name || t('user_chat_page.msg.user') }}</span>
                        <span style="font-size:11px;color:#999;margin-left:auto">{{ t('user_chat_page.dialogs.msg_count_short', { n: conv.messages_count }) }}</span>
                    </div>
                    <div style="font-size:12px;color:#666;background:#f5f7fa;padding:6px 8px;border-radius:4px;margin-bottom:4px">{{ conv.last_message?.substring(0, 100) || t('user_chat_page.dialogs.no_msg') }}</div>
                    <div style="font-size:11px;color:#bbb;text-align:right">{{ formatTime(conv.updated_at) }}</div>
                </div>
            </div>
        </el-dialog>
        <!-- 创建平台 AI 好友弹窗 -->
        <el-dialog v-model="showCreatePlatformAi" :title="t('user_chat_page.dialogs.create_platform')" width="520px">
            <el-form label-position="top" size="small">
                <el-form-item :label="t('user_chat_page.form.name')"><el-input v-model="platformAiForm.name" :placeholder="t('user_chat_page.form.name_ph')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.form.avatar')">
                    <div class="avatar-selector">
                        <div class="avatar-preview"><img :src="platformAiForm.avatar_url || getDefaultAvatar(platformAiForm.name)" class="avatar-img" /></div>
                        <div class="avatar-options">
                            <div v-for="a in presetAvatars" :key="a.url" class="avatar-option" :class="{selected:platformAiForm.avatar_url===a.url}" @click="platformAiForm.avatar_url=a.url"><img :src="a.url" /></div>
                            <el-upload :show-file-list="false" :http-request="uploadPlatformAvatar" accept="image/*">
                                <el-button size="small" circle>+</el-button>
                            </el-upload>
                        </div>
                        <el-input v-if="platformAiShowUrl" v-model="platformAiForm.avatar_url" :placeholder="t('user_chat_page.form.custom_avatar_url') + '...'" size="small" style="margin-top:6px" />
                    </div>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.form.type')"><el-select v-model="platformAiForm.category" style="width:100%">
                    <el-option v-for="o in aiCategoryOptions" :key="o.value" :value="o.value" :label="o.label" />
                </el-select></el-form-item>
                <el-form-item :label="t('user_chat_page.form.welcome')"><el-input v-model="platformAiForm.welcome_message" :placeholder="t('user_chat_page.form.welcome_ph')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.form.provider')"><el-select v-model="platformAiForm.provider" style="width:100%">
                    <el-option :label="t('user_chat_page.form.platform_key')" value="deepseek" /><el-option label="OpenAI" value="openai" />
                    <el-option label="Claude" value="claude" /><el-option :label="t('user_chat_page.form.cat_custom') + ' API'" value="custom" />
                </el-select></el-form-item>
                <el-form-item :label="t('user_chat_page.form.model')"><el-input v-model="platformAiForm.model_name" placeholder="deepseek-chat" /></el-form-item>
                <el-form-item :label="t('user_chat_page.form.api_key')" v-if="platformAiForm.provider !== 'deepseek'"><el-input v-model="platformAiForm.api_key" type="password" show-password /></el-form-item>
                <el-form-item :label="t('user_chat_page.form.system_prompt')"><el-input v-model="platformAiForm.system_prompt" type="textarea" :rows="3" :placeholder="t('user_chat_page.form.system_prompt_ph')" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreatePlatformAi = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="creatingPlatformAi" @click="submitPlatformAi">{{ t('user_chat_page.dialogs.create_publish') }}</el-button>
            </template>
        </el-dialog>
        <!-- SEC-006: 隐私设置 -->
        <el-dialog v-model="showPrivacySettings" :title="t('user_chat_page.toolbar.privacy')" width="480px" @open="loadPrivacySettings">
            <el-form label-position="top">
                <el-alert v-if="dmMuteActive" type="warning" :closable="false" show-icon class="dm-mute-alert" :title="privacySettings.dm_mute?.message || t('user_chat_page.msg_request.muted_hint')" />
                <el-form-item :label="t('user_chat_page.dialogs.friend_policy')">
                    <el-radio-group v-model="privacySettings.friend_add_policy">
                        <el-radio value="everyone">{{ t('user_chat_page.dialogs.allow_all') }}</el-radio>
                        <el-radio value="need_question">{{ t('user_chat_page.dialogs.need_question') }}</el-radio>
                        <el-radio value="nobody">{{ t('user_chat_page.dialogs.nobody') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.online_status')">
                    <el-switch v-model="privacySettings.show_online_status" :active-text="t('user_chat_page.dialogs.show_online')" :inactive-text="t('user_chat_page.dialogs.hide_online')" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.read_receipt')">
                    <el-switch v-model="privacySettings.show_read_receipt" :active-text="t('user_chat_page.dialogs.send_receipt')" :inactive-text="t('user_chat_page.dialogs.no_receipt')" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.dm_policy')">
                    <el-radio-group v-model="privacySettings.dm_policy" class="dm-policy-group">
                        <el-radio value="everyone" class="dm-policy-radio">
                            <span class="dm-policy-title">{{ t('user_chat_page.dialogs.dm_everyone') }}</span>
                            <span class="dm-policy-desc">{{ t('user_chat_page.dialogs.dm_everyone_hint') }}</span>
                        </el-radio>
                        <el-radio value="followers_only" class="dm-policy-radio">
                            <span class="dm-policy-title">{{ t('user_chat_page.dialogs.dm_followers') }}</span>
                            <span class="dm-policy-desc">{{ t('user_chat_page.dialogs.dm_followers_hint') }}</span>
                        </el-radio>
                        <el-radio value="mutual_follow" class="dm-policy-radio">
                            <span class="dm-policy-title">{{ t('user_chat_page.dialogs.dm_mutual') }}</span>
                            <span class="dm-policy-desc">{{ t('user_chat_page.dialogs.dm_mutual_hint') }}</span>
                        </el-radio>
                        <el-radio value="closed" class="dm-policy-radio">
                            <span class="dm-policy-title">{{ t('user_chat_page.dialogs.dm_closed') }}</span>
                            <span class="dm-policy-desc">{{ t('user_chat_page.dialogs.dm_closed_hint') }}</span>
                        </el-radio>
                    </el-radio-group>
                    <div class="dm-policy-note">{{ t('user_chat_page.dialogs.dm_seller_note') }}</div>
                </el-form-item>
                <el-divider />
                <el-form-item :label="'🔒 ' + t('user_chat_page.dialogs.private_pin')">
                    <div style="width:100%">
                        <div v-if="privacyPinStatus.has_pin" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                            <el-tag type="success">{{ t('user_chat_page.dialogs.pin_set') }}</el-tag>
                            <el-button text size="small" @click="showSetPin = true; setPinMode = 'change'">{{ t('user_chat_page.dialogs.change_pin') }}</el-button>
                            <el-button text size="small" type="danger" @click="removePrivacyPin">{{ t('user_chat_page.dialogs.clear_pin') }}</el-button>
                        </div>
                        <el-button v-else type="primary" size="small" @click="showSetPin = true; setPinMode = 'set'">{{ t('user_chat_page.dialogs.set_pin') }}</el-button>
                        <div style="font-size:12px;color:#909399;margin-top:4px">{{ t('user_chat_page.dialogs.pin_hint') }}</div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPrivacySettings = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingPrivacy" @click="savePrivacySettings">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
        <!-- 私密空间 PIN 设置 -->
        <el-dialog v-model="showSetPin" :title="setPinMode === 'set' ? t('user_chat_page.dialogs.set_pin_title') : t('user_chat_page.dialogs.change_pin_title')" width="400px">
            <el-form label-position="top">
                <el-form-item v-if="setPinMode === 'change'" :label="t('user_chat_page.dialogs.current_pin')">
                    <el-input v-model="pinForm.currentPin" type="password" show-password maxlength="20" :placeholder="t('user_chat_page.dialogs.current_pin_ph')" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.new_pin')">
                    <el-input v-model="pinForm.newPin" type="password" show-password maxlength="20" :placeholder="t('user_chat_page.dialogs.new_pin_ph')" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.confirm_pin')">
                    <el-input v-model="pinForm.confirmPin" type="password" show-password maxlength="20" :placeholder="t('user_chat_page.dialogs.confirm_pin_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showSetPin = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="savingPin" @click="submitPrivacyPin">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>
        <!-- 私密空间解锁 -->
        <el-dialog v-model="showUnlockDialog" :title="'🔒 ' + t('user_chat_page.dialogs.unlock_title')" width="380px" :close-on-click-modal="false" @close="onUnlockCancel">
            <div style="text-align:center;padding:10px 0">
                <el-icon :size="48" color="#409eff"><Lock /></el-icon>
                <p style="margin:12px 0 8px;font-weight:600">{{ t('user_chat_page.dialogs.unlock_hint') }}</p>
                <el-input v-model="unlockPin" type="password" show-password maxlength="20" :placeholder="t('user_chat_page.dialogs.unlock_ph')"
                    size="large" style="width:200px" @keyup.enter="verifyUnlockPin" />
                <div v-if="unlockError" style="color:#f56c6c;font-size:13px;margin-top:6px">{{ unlockError }}</div>
            </div>
            <template #footer>
                <el-button @click="showUnlockDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="unlocking" @click="verifyUnlockPin">{{ t('user_chat_page.dialogs.unlock') }}</el-button>
            </template>
        </el-dialog>
        <!-- 隐藏会话列表 -->
        <el-dialog v-model="showHiddenConvs" :title="'🔒 ' + t('user_chat_page.dialogs.unlock_title')" width="480px">
            <div v-if="!hiddenConvs.length" style="text-align:center;padding:40px;color:#909399">{{ t('user_chat_page.dialogs.no_hidden') }}</div>
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
                            <span class="conv-last">{{ conv.last_message?.content || t('user_chat_page.empty.no_messages') }}</span>
                        </div>
                    </div>
                    <el-button text size="small" type="info" @click.stop="unhideConv(conv)">{{ t('user_chat_page.dialogs.unhide') }}</el-button>
                </div>
            </div>
        </el-dialog>
        <!-- OPR-011: 投票 -->
        <el-dialog v-model="showPollDialog" :title="t('user_chat_page.composer.poll')" width="480px">
            <el-form label-position="top">
                <el-form-item :label="t('user_chat_page.dialogs.poll_question')"><el-input v-model="pollQuestion" :placeholder="t('user_chat_page.dialogs.poll_question_ph')" /></el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.poll_type')">
                    <el-radio-group v-model="pollType">
                        <el-radio value="single">{{ t('user_chat_page.dialogs.poll_single') }}</el-radio>
                        <el-radio value="multiple">{{ t('user_chat_page.dialogs.poll_multiple') }}</el-radio>
                        <el-radio value="ranked">{{ t('user_chat_page.dialogs.poll_ranked') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.poll_options')">
                    <div v-for="(opt, i) in pollOptions" :key="i" class="poll-option-row">
                        <span v-if="pollType === 'ranked'" class="poll-rank-badge">{{ i + 1 }}</span>
                        <el-input v-model="pollOptions[i]" :placeholder="t('user_chat_page.dialogs.poll_option_ph', { n: i + 1 })" size="small" style="flex:1" />
                        <el-button v-if="pollOptions.length > 2" text size="small" type="danger" @click="pollOptions.splice(i,1)">×</el-button>
                    </div>
                    <div v-if="pollType === 'ranked'" class="poll-rank-hint">{{ t('user_chat_page.dialogs.poll_rank_hint') }}</div>
                    <el-button v-if="pollOptions.length < 20" size="small" text @click="pollOptions.push('')">+ {{ t('user_chat_page.dialogs.add_option') }}</el-button>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.poll_settings')">
                    <el-checkbox v-model="pollIsAnonymous">{{ t('user_chat_page.dialogs.poll_anonymous') }}</el-checkbox>
                    <el-checkbox v-model="pollHideResults" style="margin-left:12px">{{ t('user_chat_page.dialogs.poll_hide_results') }}</el-checkbox>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.poll_deadline')">
                    <el-select v-model="pollExpireHours" :placeholder="t('user_chat_page.dialogs.no_limit')" clearable style="width:160px">
                        <el-option v-for="o in pollExpireOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showPollDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="creatingPoll" @click="submitPoll">{{ t('user_chat_page.dialogs.publish_poll') }}</el-button>
            </template>
        </el-dialog>
        <!-- 投票详情 -->
        <el-dialog v-model="showPollResult" :title="activePoll?.question || t('user_chat_page.dialogs.poll_detail')" width="400px">
            <div v-if="activePoll">
                <div v-for="opt in pollResults" :key="opt.key" class="poll-result-row">
                    <div class="poll-result-label">{{ opt.label }}</div>
                    <div class="poll-result-bar-wrap"><div class="poll-result-bar" :style="{width: opt.percentage+'%'}"></div></div>
                    <div class="poll-result-num">{{ t('user_chat_page.dialogs.poll_votes', { count: opt.count, pct: opt.percentage }) }}</div>
                </div>
                <div style="margin-top:12px;font-size:13px;color:#999">
                    {{ t('user_chat_page.dialogs.poll_total', { n: pollTotalVotes }) }}
                    <span v-if="activePoll.is_anonymous"> · {{ t('user_chat_page.dialogs.poll_anonymous_tag') }}</span>
                    <span v-if="activePoll.is_closed"> · {{ t('user_chat_page.dialogs.poll_closed') }}</span>
                </div>
            </div>
        </el-dialog>
        <el-dialog v-model="showDashboard" :title="t('user_chat_page.toolbar.dashboard')" width="640px" @open="loadDashboard">
            <div v-loading="loadingDashboard">
                <div class="dashboard-grid">
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.total_conversations || 0 }}</div><div class="dashboard-label">{{ t('user_chat_page.dialogs.stat_conversations') }}</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.today_messages || 0 }}</div><div class="dashboard-label">{{ t('user_chat_page.dialogs.stat_today_msgs') }}</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.active_users || 0 }}</div><div class="dashboard-label">{{ t('user_chat_page.dialogs.stat_active_users') }}</div></div>
                    <div class="dashboard-card"><div class="dashboard-num">{{ dashboardData.total_canned || 0 }}</div><div class="dashboard-label">{{ t('user_chat_page.dialogs.stat_canned') }}</div></div>
                </div>
            </div>
        </el-dialog>
        <!-- 群管理 -->
        <el-dialog v-model="showGroupManage" :title="t('user_chat_page.header.group_manage')" width="480px">
            <div v-loading="loadingGroupMembers">
                <el-alert v-if="userRoleInGroup === 'member'" :title="userRoleAlertTitle('member')" type="info" :closable="false" show-icon style="margin-bottom:12px" />
                <el-alert v-else :title="userRoleAlertTitle(userRoleInGroup)" :type="userRoleInGroup === 'creator' ? 'warning' : 'success'" :closable="false" show-icon style="margin-bottom:12px" />
                <div style="margin-bottom:12px;font-weight:bold">{{ t('user_chat_page.dialogs.members', { n: groupMembers.length }) }}</div>
                <div v-for="m in groupMembers" :key="m.id" class="group-member-row">
                    <span>{{ memberDisplayName(m) }}</span>
                    <span v-if="m.pivot?.role === 'creator'" class="role-tag creator-tag">{{ groupRoleLabel('creator') }}</span>
                    <span v-else-if="m.pivot?.role === 'admin'" class="role-tag admin-tag">{{ groupRoleLabel('admin') }}</span>
                    <span v-else class="role-tag member-tag">{{ groupRoleLabel('member') }}</span>
                    <span style="flex:1" />
                    <template v-if="userRoleInGroup !== 'member' && m.id !== myId && m.pivot?.role !== 'creator'">
                        <el-button v-if="userRoleInGroup === 'creator' && m.pivot?.role !== 'admin'" text size="small" type="primary" @click="setAsAdmin(m)">{{ t('user_chat_page.dialogs.set_admin') }}</el-button>
                        <el-button v-if="userRoleInGroup === 'creator' && m.pivot?.role === 'admin'" text size="small" @click="removeAdmin(m)">{{ t('user_chat_page.dialogs.remove_admin') }}</el-button>
                        <el-button text size="small" type="danger" @click="kickMember(m)">{{ t('user_chat_page.dialogs.kick') }}</el-button>
                    </template>
                </div>
                <el-divider />
                <!-- 入群审批 -->
                <div v-if="userRoleInGroup !== 'member'" style="margin-bottom:12px">
                    <div style="font-weight:bold;margin-bottom:6px">{{ t('user_chat_page.dialogs.join_approval') }}</div>
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <span style="font-size:13px">{{ t('user_chat_page.dialogs.enable_join_approval') }}</span>
                        <el-switch v-model="groupJoinApproval" @change="toggleJoinApproval" />
                        <span style="font-size:12px;color:#909399">{{ t('user_chat_page.dialogs.join_approval_hint') }}</span>
                    </div>
                    <div v-if="groupJoinApproval && pendingJoinReqs.length" class="join-requests-section">
                        <div style="font-weight:600;margin-bottom:6px;font-size:13px">{{ t('user_chat_page.dialogs.pending_join', { n: pendingJoinReqs.length }) }}</div>
                        <div v-for="req in pendingJoinReqs" :key="req.id" class="join-req-row">
                            <span>{{ memberDisplayName(req.user || { id: req.user_id }) }}</span>
                            <span v-if="req.reason" style="color:#909399;font-size:12px;margin-left:4px">: {{ req.reason }}</span>
                            <span style="flex:1" />
                            <el-button size="small" type="success" text @click="handleJoinRequest(req, 'approve')">{{ t('user_chat_page.dialogs.approve') }}</el-button>
                            <el-button size="small" type="danger" text @click="handleJoinRequest(req, 'reject')">{{ t('user_chat_page.msgs.rejected') }}</el-button>
                        </div>
                    </div>
                </div>
                <!-- 群权限配置 -->
                <div v-if="userRoleInGroup !== 'member'" style="margin-bottom:12px">
                    <div style="font-weight:bold;margin-bottom:6px">{{ t('user_chat_page.dialogs.group_perms') }}</div>
                    <div v-for="perm in groupPermissionDefs" :key="perm.key" class="perm-row">
                        <span style="font-size:13px;flex:1">{{ perm.label }}</span>
                        <el-switch :model-value="groupPermissions[perm.key]" @change="v => updateGroupPerm(perm.key, v)" size="small" />
                    </div>
                </div>
                <el-divider />
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <el-button v-if="userRoleInGroup === 'creator'" size="small" @click="showTransferOwner = true">{{ t('user_chat_page.dialogs.transfer_owner') }}</el-button>
                    <el-button size="small" @click="leaveCurrentGroup" type="warning">{{ t('user_chat_page.dialogs.leave_group') }}</el-button>
                    <el-button v-if="userRoleInGroup === 'creator'" size="small" type="danger" @click="dismissCurrentGroup">{{ t('user_chat_page.dialogs.dismiss_group') }}</el-button>
                </div>
            </div>
        </el-dialog>
        <!-- 转让群主 -->
        <el-dialog v-model="showTransferOwner" :title="t('user_chat_page.dialogs.transfer_owner')" width="400px">
            <el-select v-model="newOwnerId" :placeholder="t('user_chat_page.dialogs.select_owner')" style="width:100%">
                <el-option v-for="m in groupMembers.filter(m=>m.id !== myId)" :key="m.id" :label="memberDisplayName(m)" :value="m.id" />
            </el-select>
            <template #footer>
                <el-button @click="showTransferOwner = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="transferring" @click="confirmTransferOwner">{{ t('user_chat_page.dialogs.confirm_transfer') }}</el-button>
            </template>
        </el-dialog>
        <!-- 敏感词管理 -->
        <el-dialog v-model="showSensitiveWords" :title="t('user_chat_page.toolbar.sensitive_words')" width="600px" @open="loadSensitiveWords">
            <div>
                <div style="display:flex;gap:8px;margin-bottom:12px">
                    <el-input v-model="newSensitiveWord.word" :placeholder="t('user_chat_page.dialogs.word_ph')" size="small" style="width:140px" />
                    <el-input v-model="newSensitiveWord.replacement" :placeholder="t('user_chat_page.dialogs.replace_ph')" size="small" style="width:100px" />
                    <el-select v-model="newSensitiveWord.category" :placeholder="t('user_chat_page.dialogs.category')" size="small" style="width:100px" clearable>
                        <el-option v-for="o in swCategoryOptions" :key="o.value" :value="o.value" :label="o.label" />
                    </el-select>
                    <el-select v-model="newSensitiveWord.severity" :placeholder="t('user_chat_page.table.severity')" size="small" style="width:100px">
                        <el-option v-for="o in severityOptions" :key="o.value" :value="o.value" :label="o.label" />
                    </el-select>
                    <el-button size="small" type="primary" :loading="addingSensitiveWord" @click="addSensitiveWord">{{ t('user_chat_page.dialogs.add') }}</el-button>
                </div>
                <el-table :data="sensitiveWords" max-height="360" size="small" style="width:100%">
                    <el-table-column prop="word" :label="t('user_chat_page.dialogs.word_ph')" />
                    <el-table-column prop="replacement" :label="t('user_chat_page.table.replacement')" width="80" />
                    <el-table-column prop="category" :label="t('user_chat_page.dialogs.category')" width="80">
                        <template #default="{ row }">{{ swCategoryLabel(row.category) }}</template>
                    </el-table-column>
                    <el-table-column prop="severity" :label="t('user_chat_page.table.severity')" width="70">
                        <template #default="{row}">
                            <el-tag :type="row.severity === 'critical' ? 'danger' : row.severity === 'high' ? 'warning' : row.severity === 'medium' ? 'info' : 'success'" size="small">{{ severityLabel(row.severity) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="is_active" :label="t('user_chat_page.table.status')" width="60">
                        <template #default="{row}">
                            <el-switch :model-value="!!row.is_active" size="small" @change="toggleSensitiveWord(row)" />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('user_chat_page.table.actions')" width="100">
                        <template #default="{row}">
                            <el-button text size="small" type="danger" @click="deleteSensitiveWord(row)">{{ t('user_chat_page.friends.delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-divider />
                <div style="display:flex;gap:8px;align-items:center">
                    <el-input v-model="sensitiveTestText" :placeholder="t('user_chat_page.dialogs.test_ph')" size="small" style="flex:1" />
                    <el-button size="small" @click="testSensitiveWord" :loading="testingSensitive">{{ t('user_chat_page.dialogs.test') }}</el-button>
                </div>
                <div v-if="sensitiveTestResult" style="margin-top:8px;padding:8px;background:#f5f7fa;border-radius:4px;font-size:13px">
                    <div>{{ t('user_chat_page.dialogs.original') }} {{ sensitiveTestResult.original }}</div>
                    <div>{{ t('user_chat_page.dialogs.filtered') }} <span v-html="sensitiveTestResult.filtered" /></div>
                    <div v-if="sensitiveTestResult.found?.length">
                        <el-tag v-for="w in sensitiveTestResult.found" :key="w" size="small" type="danger" style="margin:2px">{{ w }}</el-tag>
                    </div>
                    <div v-else style="color:#67c23a">{{ t('user_chat_page.dialogs.no_sensitive') }}</div>
                </div>
            </div>
        </el-dialog>
        <!-- MSG-015: 文件上传对话框 -->
        <FileUploadDialog v-model="showFileUpload" @uploaded="onFilesUploaded" />
        <FilePreviewDialog v-model="showFilePreview" :file-url="previewFileData.url" :file-name="previewFileData.name"
            :file-size="previewFileData.size" :file-mime="previewFileData.mime" :ext="previewFileData.ext" />

        <!-- AI 主持人对话框 -->
        <el-dialog v-model="showModerator" :title="t('user_chat_page.dialogs.moderator')" width="580px" top="8vh" @open="initModerator">
            <div v-loading="moderatorLoading">
                <!-- 功能选择 -->
                <el-radio-group v-model="moderatorMode" size="small" class="moderator-mode-bar">
                    <el-radio-button value="summary">{{ t('user_chat_page.dialogs.mod_summary') }}</el-radio-button>
                    <el-radio-button value="agenda">{{ t('user_chat_page.dialogs.mod_agenda') }}</el-radio-button>
                    <el-radio-button value="mediate">{{ t('user_chat_page.dialogs.mod_mediate') }}</el-radio-button>
                    <el-radio-button value="focus">{{ t('user_chat_page.dialogs.mod_focus') }}</el-radio-button>
                </el-radio-group>

                <!-- 议程额外参数 -->
                <div v-if="moderatorMode === 'agenda'" class="moderator-extra">
                    <el-input v-model="moderatorTopic" :placeholder="t('user_chat_page.dialogs.mod_topic_ph')" size="small" clearable />
                </div>
                <div v-if="moderatorMode === 'focus'" class="moderator-extra">
                    <el-input v-model="moderatorTopic" :placeholder="t('user_chat_page.dialogs.mod_focus_ph')" size="small" />
                </div>

                <div class="moderator-action-row">
                    <el-button type="primary" :loading="moderatorRunning" @click="runModerator">
                        {{ moderatorRunning ? t('user_chat_page.dialogs.analyzing_mod') : t('user_chat_page.dialogs.start_analysis') }}
                    </el-button>
                </div>

                <!-- 结果 -->
                <div v-if="moderatorResult" class="moderator-result">
                    <div v-if="moderatorMode === 'summary'" class="result-card">
                        <div class="result-title">{{ t('user_chat_page.dialogs.mod_summary') }}</div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.summary)"></div>
                        <div v-if="moderatorResult.message_count" class="result-meta">{{ t('user_chat_page.moderator.analyzed_count', { n: moderatorResult.message_count }) }}</div>
                    </div>
                    <div v-if="moderatorMode === 'agenda'" class="result-card">
                        <div class="result-title">{{ t('user_chat_page.moderator.agenda_title') }}</div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.agenda)"></div>
                        <div v-if="moderatorResult.estimated_minutes" class="result-meta">{{ t('user_chat_page.moderator.estimated_minutes', { n: moderatorResult.estimated_minutes }) }}</div>
                    </div>
                    <div v-if="moderatorMode === 'mediate'" class="result-card">
                        <div class="result-title">{{ t('user_chat_page.moderator.mediate_title') }}</div>
                        <div :class="['debate-badge', moderatorResult.has_debate ? 'debate-yes' : 'debate-no']">
                            {{ moderatorResult.has_debate ? t('user_chat_page.moderator.debate_yes') : t('user_chat_page.moderator.debate_no') }}
                        </div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.analysis)"></div>
                    </div>
                    <div v-if="moderatorMode === 'focus'" class="result-card">
                        <div class="result-title">{{ t('user_chat_page.moderator.focus_title') }}</div>
                        <div :class="['focus-badge', 'focus-' + moderatorResult.focus_level]">
                            {{ moderatorFocusLabel(moderatorResult.focus_level) }}
                        </div>
                        <div class="result-content" v-html="renderMarkdown(moderatorResult.analysis)"></div>
                    </div>
                </div>
                <div v-else-if="!moderatorLoading" class="moderator-hint">{{ t('user_chat_page.dialogs.mod_hint') }}</div>
            </div>
        </el-dialog>
        <CallPanel ref="callPanelRef" v-model="callState" :call-type="callType" :call-partner="callPartner"
            :conversation-id="activeConv?.id" :my-name="myName" :my-id="myId" @call-ended="onCallEnded" />

        <!-- 快捷回复管理对话框 -->
        <el-dialog v-model="showCannedReplyManager" :title="t('user_chat_page.dialogs.canned_mgr')" width="480px" :close-on-click-modal="false">
            <div style="margin-bottom:12px;display:flex;gap:6px;align-items:center">
                <el-input v-model="chatReplyMgrSearch" :placeholder="t('user_chat_page.dialogs.canned_search')" size="small" clearable prefix-icon="Search" style="flex:1" />
                <el-button size="small" type="primary" @click="openChatNewReply">{{ t('user_chat_page.dialogs.new') }}</el-button>
                <el-button size="small" @click="loadCannedReplies">{{ t('user_chat_page.dialogs.refresh') }}</el-button>
            </div>
            <div style="max-height:400px;overflow-y:auto">
                <div v-for="r in chatFilteredReplies" :key="r.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0">
                    <div style="flex:1;min-width:0">
                        <div style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:500;margin-bottom:2px">
                            <el-tag size="small">{{ cannedCatLabel(r.category) }}</el-tag>
                            <span>{{ r.title }}</span>
                        </div>
                        <div style="font-size:12px;color:#909399;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-left:50px">
                            {{ r.content?.substring(0, 80) }}{{ r.content?.length > 80 ? '...' : '' }}
                        </div>
                    </div>
                    <div style="display:flex;gap:4px;flex-shrink:0;margin-left:8px">
                        <el-button size="small" text @click="openChatEditReply(r)">{{ t('actions.edit') }}</el-button>
                        <el-button size="small" text type="danger" @click="deleteChatReply(r)">{{ t('actions.delete') }}</el-button>
                    </div>
                </div>
                <div v-if="!chatFilteredReplies.length" style="padding:40px 0;text-align:center">
                    <el-empty :description="t('user_chat_page.empty.no_canned')" :image-size="40" />
                </div>
            </div>
        </el-dialog>

        <!-- 快捷回复编辑对话框 -->
        <el-dialog v-model="showChatReplyEditor" :title="chatEditingReply ? t('user_chat_page.dialogs.edit_canned') : t('user_chat_page.dialogs.new_canned')" width="420px">
            <el-form label-width="70px">
                <el-form-item :label="t('user_chat_page.dialogs.category')">
                    <el-select v-model="chatReplyForm.category" filterable allow-create :placeholder="t('user_chat_page.dialogs.category_ph')" style="width:100%">
                        <el-option v-for="o in chatCannedCategoryOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.title')">
                    <el-input v-model="chatReplyForm.title" :placeholder="t('user_chat_page.dialogs.title_ph')" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.content')">
                    <el-input v-model="chatReplyForm.content" type="textarea" :rows="4" :placeholder="t('user_chat_page.dialogs.content_ph')" maxlength="1000" />
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.shortcuts')">
                    <el-select v-model="chatReplyForm.shortcuts" multiple filterable allow-create default-first-option
                        :placeholder="t('user_chat_page.dialogs.shortcuts_ph')" style="width:100%">
                        <el-option v-for="s in chatReplyForm.shortcuts" :key="s" :label="s" :value="s" />
                    </el-select>
                    <div style="font-size:11px;color:#909399;margin-top:4px">{{ t('user_chat_page.dialogs.shortcuts_hint') }}</div>
                </el-form-item>
                <el-form-item :label="t('user_chat_page.dialogs.shared')">
                    <el-switch v-model="chatReplyForm.is_shared" :active-text="t('user_chat_page.dialogs.shared_all')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showChatReplyEditor = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" :loading="chatSavingReply" @click="saveChatReply">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>
<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    Plus, Star, StarFilled, Bell, Mute, Delete, ArrowLeft, ArrowRight, ArrowDown,
    Message, Setting, MoreFilled, Edit, User, Search, Picture,
    ChatDotRound, Download, Close, Loading, CollectionTag, MagicStick,
    Tickets, DataBoard, Share, Phone, VideoCamera, MuteNotification,
    RefreshLeft, RemoveFilled, EditPen, Timer, Microphone, CaretRight,
    Sunny, MoonNight, Monitor, Location, Select, Warning, Lock,
    ChatLineSquare, View, Upload, Document, Link, Reading, CopyDocument,
    Folder, Cpu, ChatRound, Briefcase, PriceTag, FolderDelete
} from '@element-plus/icons-vue'
import apiClient from '@/api/client'
import { useUserChatMessages } from '@/composables/useUserChatMessages'
import { useUserChatEcho } from '@/composables/useUserChatEcho'
import { useUserChatDeepLink } from '@/composables/useUserChatDeepLink'
import FileUploadDialog from './FileUploadDialog.vue'
import FilePreviewDialog from './FilePreviewDialog.vue'
import CallPanel from './CallPanel.vue'
const { t } = useI18n()
const router = useRouter()
const myStatusLabelMap = computed(() => ({
    online: t('user_chat_page.status.online'),
    busy: t('user_chat_page.status.busy'),
    invisible: t('user_chat_page.status.invisible'),
}))
const themeModeLabelMap = computed(() => ({
    light: t('user_chat_page.toolbar.theme_light'),
    dark: t('user_chat_page.toolbar.theme_dark'),
    auto: t('user_chat_page.toolbar.theme_auto'),
}))
const convCategoryLabels = computed(() => ({
    '': t('user_chat_page.category.all'),
    urgent: t('user_chat_page.category.urgent'),
    work: t('user_chat_page.category.work'),
    normal: t('user_chat_page.category.normal'),
    promotion: t('user_chat_page.category.promotion'),
    spam: t('user_chat_page.category.spam'),
    archived: t('user_chat_page.category.archived'),
}))
const ticketPriorityOptions = computed(() => [
    { label: t('user_chat_page.priority.low'), value: 'low' },
    { label: t('user_chat_page.priority.medium'), value: 'medium' },
    { label: t('user_chat_page.priority.high'), value: 'high' },
    { label: t('user_chat_page.priority.urgent'), value: 'urgent' },
])
const reportReasonOptions = computed(() => [
    { label: t('user_chat_page.report_reasons.spam'), value: 'spam' },
    { label: t('user_chat_page.report_reasons.harassment'), value: 'harassment' },
    { label: t('user_chat_page.report_reasons.pornographic'), value: 'pornographic' },
    { label: t('user_chat_page.report_reasons.illegal'), value: 'illegal' },
    { label: t('user_chat_page.report_reasons.impersonation'), value: 'impersonation' },
    { label: t('user_chat_page.report_reasons.copyright'), value: 'copyright' },
    { label: t('user_chat_page.report_reasons.other'), value: 'other' },
])
const chatCannedCategoryOptions = computed(() => [
    { value: '问候', label: t('user_chat_page.canned_cats.greeting') },
    { value: '产品', label: t('user_chat_page.canned_cats.product') },
    { value: '售后', label: t('user_chat_page.canned_cats.support') },
    { value: '技术', label: t('user_chat_page.canned_cats.tech') },
    { value: '结束语', label: t('user_chat_page.canned_cats.closing') },
    { value: '通用', label: t('user_chat_page.canned_cats.general') },
])
const groupPermissionDefs = computed(() => [
    { key: 'invite', label: t('user_chat_page.group_perm.invite') },
    { key: 'mention_all', label: t('user_chat_page.group_perm.mention_all') },
    { key: 'send_file', label: t('user_chat_page.group_perm.send_file') },
    { key: 'send_card', label: t('user_chat_page.group_perm.send_card') },
    { key: 'edit_group', label: t('user_chat_page.group_perm.edit_group') },
    { key: 'pin_message', label: t('user_chat_page.group_perm.pin_message') },
])
const slowModeOptions = computed(() => [
    { value: 0, label: t('user_chat_page.slow_mode.off') },
    { value: 5, label: t('user_chat_page.slow_mode.s5') },
    { value: 10, label: t('user_chat_page.slow_mode.s10') },
    { value: 30, label: t('user_chat_page.slow_mode.s30') },
    { value: 60, label: t('user_chat_page.slow_mode.m1') },
    { value: 300, label: t('user_chat_page.slow_mode.m5') },
    { value: 600, label: t('user_chat_page.slow_mode.m10') },
    { value: 3600, label: t('user_chat_page.slow_mode.h1') },
])
const inviteExpireOptions = computed(() => [
    { value: 1, label: t('user_chat_page.invite_expire.h1') },
    { value: 24, label: t('user_chat_page.invite_expire.h24') },
    { value: 72, label: t('user_chat_page.invite_expire.d3') },
    { value: 168, label: t('user_chat_page.invite_expire.d7') },
    { value: 720, label: t('user_chat_page.invite_expire.d30') },
    { value: 0, label: t('user_chat_page.invite_expire.never') },
])
const inviteUsesOptions = computed(() => [
    { value: 0, label: t('user_chat_page.invite_uses.unlimited') },
    { value: 1, label: t('user_chat_page.invite_uses.once') },
    { value: 5, label: t('user_chat_page.invite_uses.n5') },
    { value: 10, label: t('user_chat_page.invite_uses.n10') },
    { value: 50, label: t('user_chat_page.invite_uses.n50') },
    { value: 100, label: t('user_chat_page.invite_uses.n100') },
])
const pollExpireOptions = computed(() => [
    { value: 1, label: t('user_chat_page.poll_expire.h1') },
    { value: 6, label: t('user_chat_page.poll_expire.h6') },
    { value: 24, label: t('user_chat_page.poll_expire.h24') },
    { value: 72, label: t('user_chat_page.poll_expire.d3') },
    { value: 168, label: t('user_chat_page.poll_expire.d7') },
])
const aiCategoryOptions = computed(() => [
    { value: 'assistant', label: t('user_chat_page.form.cat_assistant') },
    { value: 'translator', label: t('user_chat_page.form.cat_translator') },
    { value: 'writer', label: t('user_chat_page.form.cat_writer') },
    { value: 'custom_service', label: t('user_chat_page.form.cat_service') },
    { value: 'custom', label: t('user_chat_page.form.cat_custom') },
])
const swCategoryOptions = computed(() => [
    { value: 'general', label: t('user_chat_page.sw_category.general') },
    { value: 'political', label: t('user_chat_page.sw_category.political') },
    { value: 'advertising', label: t('user_chat_page.sw_category.advertising') },
    { value: 'abuse', label: t('user_chat_page.sw_category.abuse') },
    { value: 'pornographic', label: t('user_chat_page.sw_category.pornographic') },
])
const severityOptions = computed(() => [
    { value: 'low', label: t('user_chat_page.severity.low') },
    { value: 'medium', label: t('user_chat_page.severity.medium') },
    { value: 'high', label: t('user_chat_page.severity.high') },
    { value: 'critical', label: t('user_chat_page.severity.critical') },
])
const presetAvatars = computed(() => [
    { url: 'https://api.dicebear.com/7.x/bottts/svg?seed=assistant&backgroundColor=409eff', label: t('user_chat_page.form.avatar_bot') },
    { url: 'https://api.dicebear.com/7.x/bottts/svg?seed=translator&backgroundColor=67c23a', label: t('user_chat_page.form.avatar_translator') },
    { url: 'https://api.dicebear.com/7.x/bottts/svg?seed=writer&backgroundColor=e6a23c', label: t('user_chat_page.form.avatar_writer') },
    { url: 'https://api.dicebear.com/7.x/bottts/svg?seed=custom&backgroundColor=909399', label: t('user_chat_page.form.cat_custom') },
    { url: 'https://api.dicebear.com/7.x/thumbs/svg?seed=AI&backgroundColor=409eff', label: t('user_chat_page.form.avatar_default') },
])
function cannedCatLabel(cat) {
    const map = {
        '问候': t('user_chat_page.canned_cats.greeting'),
        '产品': t('user_chat_page.canned_cats.product'),
        '售后': t('user_chat_page.canned_cats.support'),
        '技术': t('user_chat_page.canned_cats.tech'),
        '结束语': t('user_chat_page.canned_cats.closing'),
        '通用': t('user_chat_page.canned_cats.general'),
    }
    return map[cat] || cat || t('user_chat_page.canned_cats.general')
}
function categoryLabel(cat) {
    const map = {
        assistant: t('user_chat_page.ai_cat.assistant'),
        translator: t('user_chat_page.ai_cat.translator'),
        writer: t('user_chat_page.ai_cat.writer'),
        custom_service: t('user_chat_page.ai_cat.custom_service'),
        custom: t('user_chat_page.ai_cat.custom'),
    }
    return map[cat] || cat
}
function customEmojiCategoryLabel(cat) {
    const map = {
        general: t('user_chat_page.emoji_cat.general'),
        funny: t('user_chat_page.emoji_cat.funny'),
        reaction: t('user_chat_page.emoji_cat.reaction'),
        logo: t('user_chat_page.emoji_cat.logo'),
        other: t('user_chat_page.emoji_cat.other'),
    }
    return map[cat] || cat
}
function replyPreviewText(msg) {
    const typeMap = {
        text: '',
        image: t('user_chat_page.reply_type.image'),
        voice: t('user_chat_page.reply_type.voice'),
        file: t('user_chat_page.reply_type.file'),
        card: t('user_chat_page.reply_type.card'),
        sticker: t('user_chat_page.reply_type.sticker'),
        forward: t('user_chat_page.reply_type.forward'),
        contact: t('user_chat_page.reply_type.contact'),
        location: t('user_chat_page.reply_type.location'),
    }
    const prefix = typeMap[msg.message_type] || '[' + msg.message_type + ']'
    return (prefix ? prefix + ' ' : '') + (msg.content?.substring(0, 60) || '')
}
function severityLabel(sev) {
    const map = {
        low: t('user_chat_page.severity.low'),
        medium: t('user_chat_page.severity.medium'),
        high: t('user_chat_page.severity.high'),
        critical: t('user_chat_page.severity.critical'),
    }
    return map[sev] || sev
}
function swCategoryLabel(cat) {
    const map = {
        general: t('user_chat_page.sw_category.general'),
        political: t('user_chat_page.sw_category.political'),
        advertising: t('user_chat_page.sw_category.advertising'),
        abuse: t('user_chat_page.sw_category.abuse'),
        pornographic: t('user_chat_page.sw_category.pornographic'),
    }
    return map[cat] || cat
}
function groupRoleLabel(role) {
    const map = {
        creator: t('user_chat_page.dialogs.role_creator'),
        admin: t('user_chat_page.dialogs.role_admin'),
        member: t('user_chat_page.dialogs.role_member'),
    }
    return map[role] || role
}
function userRoleAlertTitle(role) {
    const map = {
        member: t('user_chat_page.role_alert.member'),
        creator: t('user_chat_page.role_alert.creator'),
        admin: t('user_chat_page.role_alert.admin'),
    }
    return map[role] || ''
}
function memberDisplayName(m) {
    return m?.name || m?.nickname || t('user_chat_page.msg.user') + '#' + (m?.id ?? m?.user_id ?? '')
}
function formatInviteMeta(inv) {
    const status = inv.is_valid ? t('user_chat_page.dialogs.valid') : t('user_chat_page.dialogs.invalid')
    const maxUses = inv.max_uses || t('user_chat_page.dialogs.unlimited')
    const expire = inv.expires_at
        ? t('user_chat_page.dialogs.expires_at', { time: formatTime(inv.expires_at) })
        : t('user_chat_page.dialogs.never_expire')
    const usedText = t('user_chat_page.dialogs.used') + ' ' + inv.use_count + '/' + maxUses
    return t('user_chat_page.dialogs.invite_meta', { status, used: usedText, max: maxUses, expire })
}
function moderatorFocusLabel(level) {
    const map = {
        '高': t('user_chat_page.moderator.focus_high'),
        '中': t('user_chat_page.moderator.focus_medium'),
        '低': t('user_chat_page.moderator.focus_low'),
        high: t('user_chat_page.moderator.focus_high'),
        medium: t('user_chat_page.moderator.focus_medium'),
        low: t('user_chat_page.moderator.focus_low'),
    }
    return map[level] || ''
}

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
const notifGroup = ref('all')
const notifGroupMeta = ref([
    { key: 'all', unread: 0 },
    { key: 'interactions', unread: 0 },
    { key: 'likes', unread: 0 },
    { key: 'comments', unread: 0 },
    { key: 'follows', unread: 0 },
    { key: 'system', unread: 0 },
])
const notifGroups = computed(() => notifGroupMeta.value)
const inboxHub = ref({
    interaction_count: 0,
    interaction_preview: '',
    system_count: 0,
    system_total: 0,
    system_preview: '',
})

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
// PRES-003: 在线状态
const myStatus = ref('online')
const showStatusMenu = ref(false)
// SEC-006: 隐私设置
const showPrivacySettings = ref(false)
const privacySettings = ref({
    friend_add_policy: 'everyone',
    show_online_status: true,
    show_read_receipt: true,
    allow_stranger_message: false,
    dm_policy: 'followers_only',
    dm_mute: null,
    stranger_message_limit: 5,
})
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
const showNewChat = ref(false)
const newChatMode = ref('dm') // 'dm' | 'group'
const newChatUserIds = ref([])
const newGroupName = ref('')
const searchedUsers = ref([])
const searching = ref(false)
const creatingChat = ref(false)
const showAddFriend = ref(false)
const addFriendUserId = ref(null)
const addFriendLoading = ref(false)
const showPendingRequests = ref(false)
const pendingRequests = ref([])
const messageRequests = ref([])
const loadingMessageRequests = ref(false)
const messageRequestCount = computed(() => messageRequests.value.length)
const messageRequestUnread = computed(() => messageRequests.value.reduce((n, c) => n + Number(c.unread_count || 0), 0))
const messageRequestBadge = computed(() => messageRequestUnread.value || messageRequestCount.value)
const dmMuteActive = computed(() => !!privacySettings.value?.dm_mute?.active)
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
watch(conversations, (list) => {
    const current = activeConv.value
    if (!current?.id) return
    const next = list.find(c => c.id === current.id)
    if (!next) return
    if (next.stranger_limit || next.is_outgoing_request !== current.is_outgoing_request) {
        activeConv.value = {
            ...current,
            stranger_limit: next.stranger_limit,
            is_outgoing_request: next.is_outgoing_request,
            is_message_request: next.is_message_request,
        }
    }
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
    callPartner.value = { id: payload.caller_id, name: payload.caller_name || t('user_chat_page.msgs.incoming_user') }
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
                ElMessageBox.alert(result.response, '💻 ' + t('user_chat_page.msgs.slash_result'), {
                    dangerouslyUseHTMLString: true,
                    customClass: 'slash-result-dialog',
                    confirmButtonText: t('user_chat_page.msgs.ok'),
                })
            }
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.cmd_fail'))
    }
}
// ── 会话操作 ──
async function togglePinConv(conv) {
    if (!conv?.id) return
    try {
        const res = await apiClient.post('/user-chat/conversations/' + conv.id + '/pin')
        const pinned = res.data?.data?.is_pinned
        conv.is_pinned = typeof pinned === 'boolean' ? pinned : !conv.is_pinned
        if (activeConv.value?.id === conv.id) activeConv.value.is_pinned = conv.is_pinned
        await loadConversations()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
    }
}
async function toggleMuteConv(conv) {
    if (!conv?.id) return
    try {
        const res = await apiClient.post('/user-chat/conversations/' + conv.id + '/mute')
        const muted = res.data?.data?.is_muted
        conv.is_muted = typeof muted === 'boolean' ? muted : !conv.is_muted
        if (activeConv.value?.id === conv.id) activeConv.value.is_muted = conv.is_muted
        await loadConversations()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
    }
}
async function togglePin() { await togglePinConv(activeConv.value) }
async function toggleMute() { await toggleMuteConv(activeConv.value) }
async function handleDeleteConv() { if (!activeConv.value) return; if (activeConv.value.is_channel || activeConv.value.is_oa || activeConv.value.is_plaza) { activeConv.value = null; return } try { await ElMessageBox.confirm(t('user_chat_page.msgs.delete_conv_confirm')); await apiClient.delete('/user-chat/conversations/'+activeConv.value.id); activeConv.value = null; await loadConversations() } catch { /* ignore */ } }
function onSearch() { /* filteredConversations handles it */ }
async function handleExportConv() { if (!activeConv.value) return; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/export', { responseType: 'blob' }); const url = URL.createObjectURL(new Blob([res.data])); const a = document.createElement('a'); a.href = url; a.download = 'chat-export-'+activeConv.value.id+'.html'; a.click(); URL.revokeObjectURL(url) } catch { ElMessage.error(t('user_chat_page.msgs.export_fail')) } }

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
        if (alt && alt !== t('user_chat_page.msg.image')) { msg.metadata = msg.metadata || {}; msg.metadata.alt_text = alt }
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
function jumpToMessage(r) { const convId = r.conversation_id || r.conversation?.id; if (!convId) return; const conv = conversations.value.find(c => c.id === convId); if (conv) { sidebarTab.value = 'messages'; selectConversation(conv) } }
function highlightKeyword(text) { if (!text || !globalSearchKeyword.value) return text; const kw = globalSearchKeyword.value; const re = new RegExp('('+kw.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')', 'gi'); return text.replace(re, '<span style="background:#fff3cd">$1</span>') }

// ── 新建会话 ──
async function searchUsers(q) { if (!q) { searchedUsers.value = []; return }; searching.value = true; try { const res = await apiClient.get('/user-chat/users/search', { params: { q } }); searchedUsers.value = res.data?.data || [] } catch { searchedUsers.value = [] } finally { searching.value = false } }
function openNewChat(mode) {
    newChatMode.value = mode === 'group' ? 'group' : 'dm'
    newChatUserIds.value = []
    newGroupName.value = ''
    searchedUsers.value = []
    showNewChat.value = true
}
function onSidebarMoreCommand(cmd) {
    if (cmd === 'blocked') showBlockedList.value = true
    else if (cmd === 'sensitive_words') showSensitiveWords.value = true
    else if (cmd === 'dnd') showDndSettings.value = true
    else if (cmd === 'privacy') showPrivacySettings.value = true
    else if (cmd === 'dashboard') showDashboard.value = true
    else if (cmd === 'ai_friend_admin') showAiFriendAdmin.value = true
}
function onChatMoreCommand(cmd) {
    if (cmd === 'tags') showTagPanel.value = !showTagPanel.value
    else if (cmd === 'announcements') showAnnouncements.value = true
    else if (cmd === 'slow_mode') openSlowModeDialog()
    else if (cmd === 'invite') openInviteDialog()
    else if (cmd === 'a11y') showA11yPanel.value = !showA11yPanel.value
    else if (cmd === 'export') handleExportConv()
    else if (cmd === 'summarize') handleSummarize()
    else if (cmd === 'moderator') showModerator.value = true
}
function resetNewChat() {
    newChatUserIds.value = []
    newGroupName.value = ''
    searchedUsers.value = []
    creatingChat.value = false
}
async function createNewChat() {
    const ids = Array.isArray(newChatUserIds.value) ? newChatUserIds.value : (newChatUserIds.value ? [newChatUserIds.value] : [])
    if (newChatMode.value === 'dm') {
        if (ids.length !== 1) {
            ElMessage.warning(t('user_chat_page.dialogs.dm_pick_one'))
            return
        }
    } else if (ids.length < 2) {
        ElMessage.warning(t('user_chat_page.dialogs.group_pick_min'))
        return
    }
    creatingChat.value = true
    try {
        const res = await apiClient.post('/user-chat/conversations', { participant_ids: ids })
        const conv = res.data?.data
        if (conv) {
            if (newChatMode.value === 'group' && newGroupName.value.trim()) {
                try {
                    await apiClient.put('/user-chat/conversations/' + conv.id + '/profile', { name: newGroupName.value.trim() })
                    conv.name = newGroupName.value.trim()
                } catch { /* optional group title */ }
            }
            selectConversation(conv)
            await loadConversations()
            showNewChat.value = false
            resetNewChat()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.create_fail'))
    } finally {
        creatingChat.value = false
    }
}

// ── 文件上传 ──
function onFilesUploaded(files) {
    if (!Array.isArray(files) || !files.length) return
    for (const f of files) {
        if (!f?.url) continue
        const ext = f.ext || (f.name?.includes('.') ? f.name.split('.').pop().toLowerCase() : '')
        pendingAttachments.value.push({
            name: f.name,
            size: f.size,
            url: f.url,
            ext,
            mime: f.mime || '',
        })
    }
}
function onFileSelect(e) {
    const file = e.raw
    if (!file) return
    const reader = new FileReader()
    reader.onload = async (ev) => {
        const base64 = ev.target.result
        if (file.type.startsWith('image/') && activeConv.value?.id) {
            try {
                await apiClient.post('/user-chat/conversations/' + activeConv.value.id + '/messages', {
                    content: base64,
                    message_type: 'image',
                })
                await loadMessages()
                return
            } catch { /* queue as pending attachment */ }
        }
        pendingAttachments.value.push({
            name: file.name,
            size: file.size,
            data: base64,
            url: base64,
            mime: file.type || '',
            ext: (file.name.split('.').pop() || '').toLowerCase(),
        })
    }
    reader.readAsDataURL(file)
}
function removePendingAttachment(i) { pendingAttachments.value.splice(i, 1) }

// ── 收藏 ──
const favorites = ref([])
const loadingFavorites = ref(false)
async function toggleFavorite(msg) { try { const res = await apiClient.post('/user-chat/messages/'+msg.id+'/favorite'); msg.is_favorited = res.data?.data?.favorited } catch { /* ignore */ } }
async function loadFavorites() { loadingFavorites.value = true; try { const res = await apiClient.get('/user-chat/favorites'); favorites.value = (res.data?.data || []).map(f => ({...f, content: f.message?.content || f.content || t('user_chat_page.msgs.deleted_msg'), sender_name: f.message?.sender?.name || f.sender_name || t('user_chat_page.msg.user'), created_at: f.created_at || f.message?.created_at})) } catch { favorites.value = [] } finally { loadingFavorites.value = false } }
function jumpToFavorite(fav) { const convId = fav.message?.conversation_id || fav.conversation_id; if (convId) { const conv = conversations.value.find(c => c.id === convId); if (conv) { sidebarTab.value = 'messages'; selectConversation(conv) } else { ElMessage.info(t('user_chat_page.msgs.open_conv_first')) } } }

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
        ElMessage.success(t('user_chat_page.msgs.pending_removed'))
    } catch { /* ignore */ }
}
function jumpToPending(p) {
    if (p.conversation_id) {
        const conv = conversations.value.find(c => c.id === p.conversation_id)
        if (conv) { sidebarTab.value = 'messages'; selectConversation(conv) }
        else { ElMessage.info(t('user_chat_page.msgs.open_conv_first')) }
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
        ElMessage.success(t('user_chat_page.msgs.pinned'))
        msg.is_pinned = true
        loadPinnedMessages()
        loadMessages()
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.pin_fail')) }
}
async function unpinMsg(msg) {
    try {
        await apiClient.post('/user-chat/messages/' + msg.id + '/unpin')
        ElMessage.success(t('user_chat_page.msgs.unpinned'))
        msg.is_pinned = false
        loadPinnedMessages()
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.unpin_fail')) }
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
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.reply_fail'))
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
            ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
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

    add(t('user_chat_page.context.reply'), () => { replyToMsg.value = msg })
    if (msg.thread_reply_count > 0 || msg.id === activeThreadId.value) {
        add(msg.thread_reply_count ? t('user_chat_page.context.thread_count', { n: msg.thread_reply_count }) : t('user_chat_page.context.thread'), () => openThread(msg))
    }
    add(t('user_chat_page.context.forward'), () => openForward(msg))
    if (!msg.is_pinned) add(t('user_chat_page.context.pin'), () => pinMsg(msg))
    else add(t('user_chat_page.context.unpin'), () => unpinMsg(msg))
    if (msg.sender_id === myId.value && !msg.is_recalled) add(t('user_chat_page.context.edit'), () => editMessage(msg))
    if ((msg.sender_id === myId.value || (activeConv.value?.type === 'group' && userRoleInGroup.value !== 'member')) && !msg.is_recalled) {
        add(t('user_chat_page.context.recall'), () => recallMessage(msg))
    }
    if (msg.sender_id === myId.value) add(t('user_chat_page.context.delete'), () => deleteMessage(msg), { danger: true, divided: true })
    add(msg.is_favorited ? t('user_chat_page.context.unfavorite') : t('user_chat_page.context.favorite'), () => toggleFavorite(msg))
    add(msg.is_pending ? t('user_chat_page.context.unmark_pending') : t('user_chat_page.context.mark_pending'), () => togglePendingMsg(msg))
    add(t('user_chat_page.context.create_ticket'), () => openCreateTicket(msg))
    if (msg.sender_id !== myId.value) add(t('user_chat_page.context.report'), () => openReportDialog(msg), { danger: true, divided: true })
    if (msg.sender_id !== myId.value && msg.content) add(t('user_chat_page.context.translate'), () => translateMessage(msg))
    add(t('user_chat_page.context.ai_optimize'), () => aiOptimizeMessage(msg))
    if (msg.sender_id !== myId.value) add(t('user_chat_page.context.share_contact'), () => shareContact(msg))

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
    if (e.key === 'Escape') {
        closeMsgContextMenu()
        closeConvContextMenu()
    }
}

// ── RTC-001~004: 音视频通话 ──
const callState = ref('idle')
const callType = ref('audio')
const callPartner = ref(null)
const callPanelRef = ref(null)

function startCall(type) {
    if (!activeConv.value) { ElMessage.warning(t('user_chat_page.msgs.select_conv')); return }
    // 查找通话对象（私聊的对方）
    const others = activeConv.value.participants?.filter(p => p.id !== myId.value) || []
    if (!others.length && activeConv.value.type === 'private') {
        // 自聊天不允许通话
        ElMessage.info(t('user_chat_page.msgs.no_call_file_transfer'))
        return
    }
    const target = others[0] || { id: 0, name: t('user_chat_page.msgs.group_call') }
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
async function deleteMessage(msg) { try { await ElMessageBox.confirm(t('user_chat_page.msgs.delete_msg_confirm')); await apiClient.delete('/user-chat/messages/'+msg.id); messages.value = messages.value.filter(m => m.id !== msg.id) } catch { /* ignore */ } }
async function translateMessage(msg) {
    if (msg.translated_text) { msg.translated_text = ''; return }
    try {
        const target = 'zh-CN'
        const res = await apiClient.post('/user-chat/messages/' + msg.id + '/translate', { target })
        if (res.data?.data?.translated) {
            msg.translated_text = res.data.data.translated
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.translate_fail')) }
}
async function translateConversation() {
    if (!activeConv.value?.id) return
    try {
        ElMessage.info(t('user_chat_page.msgs.translating'))
        const res = await apiClient.post('/user-chat/conversations/' + activeConv.value.id + '/translate-all', { target: 'zh-CN' })
        if (res.data?.data?.translated) {
            ElMessageBox.alert('<div style="white-space:pre-wrap;font-size:13px;line-height:1.6;max-height:400px;overflow-y:auto">' + res.data.data.translated + '</div>', '🌐 ' + t('user_chat_page.msgs.translate_title', { n: res.data.data.message_count }), { dangerouslyUseHTMLString: true, confirmButtonText: t('actions.close') })
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.translate_fail')) }
}

// ── 黑名单 ──
async function loadBlocked() { try { const res = await apiClient.get('/user-chat/blocked'); blockedUsers.value = res.data?.data || [] } catch { blockedUsers.value = [] } }
async function unblockUser(id) { try { await apiClient.post('/user-chat/unblock/'+id); blockedUsers.value = blockedUsers.value.filter(u => u.id !== id); ElMessage.success(t('user_chat_page.msgs.unblocked')) } catch { ElMessage.error(t('user_chat_page.msgs.op_fail')) } }

// ── 编辑消息 ──
const editingMsg = ref(null)
const editContent = ref('')
function editMessage(msg) { editingMsg.value = msg; editContent.value = msg.content || '' }
async function submitEdit() { if (!editingMsg.value || !editContent.value.trim()) return; try { await apiClient.put('/user-chat/messages/'+editingMsg.value.id+'/edit', { content: editContent.value.trim() }); editingMsg.value.content = editContent.value.trim(); editingMsg.value.is_edited = true; editingMsg.value = null; editContent.value = ''; ElMessage.success(t('user_chat_page.msgs.edited')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.edit_fail')) } }
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
    ElMessage.info(t('user_chat_page.msgs.forward_hint'))
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
            ElMessage.success(res.data.message || t('user_chat_page.msgs.forwarded'))
            forwardMsgs.value = []
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.forward_fail')) }
    finally { forwarding.value = false }
}
async function aiOptimizeMessage(msg) { if (!msg?.content || !activeConv.value) return; inputMessage.value = msg.content; await aiWrite('polish') }

// ── 工单 ──
function openCreateTicket(msg) { ticketSubject.value = ''; ticketDescription.value = t('user_chat_page.msgs.ticket_from_msg', { content: msg.content, sender: msg.sender?.name || t('user_chat_page.msgs.unknown'), time: formatTime(msg.created_at) }); ticketPriority.value = 'medium'; showCreateTicket.value = true }
async function submitTicket() { if (!ticketSubject.value.trim()) return ElMessage.warning(t('user_chat_page.msgs.ticket_subject_required')); if (!ticketDescription.value.trim()) return ElMessage.warning(t('user_chat_page.msgs.ticket_desc_required')); creatingTicket.value = true; try { await apiClient.post('/tickets', { subject: ticketSubject.value, description: ticketDescription.value, priority: ticketPriority.value }); showCreateTicket.value = false; ElMessage.success(t('user_chat_page.msgs.ticket_ok')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.submit_fail')) } finally { creatingTicket.value = false } }

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
    aiMessages.value.push({ role: 'assistant', content: t('user_chat_page.ai.request_failed') })
  } finally {
    aiLoading.value = false
    aiStreaming.value = false
  }
}

// ── AI-002: 未读消息摘要 ──
async function checkUnreadSummary() { try { const res = await apiClient.get('/user-chat/unread-summary'); const d = res.data?.data; if (d?.has_unread && d.summary) { ElNotification({ title: '📬 ' + t('user_chat_page.msgs.unread_summary'), message: d.summary, type: 'info', duration: 6000, onClick: () => { if (d.conversations?.[0]) { const conv = conversations.value.find(c => c.id === d.conversations[0].id); if (conv) activeConv.value = conv } } }) } } catch {} }

// ── AI-014: Copilot 侧边栏 ──
async function copilotAction(action) {
    if (!activeConv.value && action !== 'translate') return;
    copilotLoading.value = true;
    try {
        if (action === 'summarize') {
            const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/summarize');
            const d = res.data?.data;
            copilotResults.value.unshift({ title: t('user_chat_page.copilot.summary_title') + ' ('+formatTime(Date.now())+')', content: d?.summary || t('user_chat_page.msgs.summary_fail') });
        } else if (action === 'extract') {
            const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/extract-tasks');
            const d = res.data?.data;
            const tasks = d?.tasks || [];
            if (tasks.length) {
                copilotResults.value.unshift({ title: t('user_chat_page.copilot.extract_title', { n: tasks.length }), content: tasks.map((task,i)=>(i+1)+'. '+task.title+(task.deadline?' ⏰'+task.deadline:'')).join('\n') });
                extractedTasks.value = tasks;
                showTaskDialog.value = true;
            } else {
                copilotResults.value.unshift({ title: t('user_chat_page.copilot.extract_empty_title'), content: t('user_chat_page.dialogs.no_tasks') });
            }
        } else if (action === 'tag') {
            const res = await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/auto-tag');
            const d = res.data?.data;
            const tags = d?.tags || [];
            copilotResults.value.unshift({ title: t('user_chat_page.copilot.tag_title'), content: tags.length ? t('user_chat_page.copilot.tags_list', { tags: tags.join(', ') }) : t('user_chat_page.copilot.no_tags') });
            if (tags.length) aiConvTags.value[activeConv.value.id] = tags;
        } else if (action === 'translate' && inputMessage.value.trim()) {
            const token = localStorage.getItem('auth_token') || '';
            const res = await fetch('/api/user-chat/conversations/'+activeConv.value.id+'/chat-stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', Authorization: 'Bearer '+token }, body: JSON.stringify({ message: inputMessage.value, mode: 'translate' }) });
            const reader = res.body?.getReader(); let full = ''; const decoder = new TextDecoder();
            if (reader) { while (true) { const { done, value } = await reader.read(); if (done) break; const text = decoder.decode(value, { stream:true }); const lines = text.split('\n'); for (const line of lines) { if (line.startsWith('data: ')) { try { const data = JSON.parse(line.slice(6)); if (data.type === 'chunk') full += data.content; else if (data.type === 'done') full = data.content } catch {} } } } }
            copilotResults.value.unshift({ title: t('user_chat_page.copilot.translate_title'), content: full || t('user_chat_page.msgs.translate_fail') });
        }
    } catch (e) { copilotResults.value.unshift({ title: t('user_chat_page.copilot.op_fail_title'), content: e.response?.data?.message || e.message }) }
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
const convContextMenu = ref({ visible: false, x: 0, y: 0, conv: null, items: [] })

function closeConvContextMenu() {
    convContextMenu.value = { visible: false, x: 0, y: 0, conv: null, items: [] }
}

function runConvContextAction(item) {
    closeConvContextMenu()
    item.action?.()
}

function onConvContextMenu(e, conv) {
    if (batchMode.value || !conv) return
    const items = [
        { label: conv.is_pinned ? t('user_chat_page.header.unpin') : t('user_chat_page.header.pin'), action: () => togglePinConv(conv) },
        { label: conv.is_muted ? t('user_chat_page.header.unmute') : t('user_chat_page.header.mute'), action: () => toggleMuteConv(conv) },
        { label: conv.is_archived ? t('user_chat_page.context.unarchive') : t('user_chat_page.context.archive'), action: () => toggleArchiveConv(conv) },
        { label: t('user_chat_page.context.move_folder'), action: () => showConvFolderMenu({ clientX: e.clientX, clientY: e.clientY }, conv), divided: true },
    ]
    const menuWidth = 176
    const menuHeight = items.length * 36 + 8
    let x = e.clientX
    let y = e.clientY
    if (x + menuWidth > window.innerWidth) x = window.innerWidth - menuWidth - 8
    if (y + menuHeight > window.innerHeight) y = window.innerHeight - menuHeight - 8
    convContextMenu.value = { visible: true, x: Math.max(8, x), y: Math.max(8, y), conv, items }
}
async function toggleArchiveConv(conv) {
    try {
        if (conv.is_archived) {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/unarchive')
            conv.is_archived = false
            ElMessage.success(t('user_chat_page.msgs.unarchived'))
        } else {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/archive')
            conv.is_archived = true
            ElMessage.success(t('user_chat_page.msgs.archived'))
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) }
}
async function batchArchive() {
    if (!selectedConvIds.value.length) { ElMessage.warning(t('user_chat_page.msgs.select_convs')); return }
    try {
        const res = await apiClient.post('/user-chat/conversations/batch-archive', {
            conversation_ids: selectedConvIds.value
        })
        const count = res.data?.data?.archived_count || 0
        ElMessage.success(t('user_chat_page.msgs.archived_count', { n: count }))
        selectedConvIds.value.forEach(id => {
            const conv = conversations.value.find(c => c.id === id)
            if (conv) conv.is_archived = true
        })
        selectedConvIds.value = []
        batchMode.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.archive_fail')) }
}
async function batchUnarchive() {
    if (!selectedConvIds.value.length) { ElMessage.warning(t('user_chat_page.msgs.select_convs')); return }
    try {
        for (const id of selectedConvIds.value) {
            await apiClient.post('/user-chat/conversations/' + id + '/unarchive')
            const conv = conversations.value.find(c => c.id === id)
            if (conv) conv.is_archived = false
        }
        ElMessage.success(t('user_chat_page.msgs.unarchived_count', { n: selectedConvIds.value.length }))
        selectedConvIds.value = []
        batchMode.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) }
}
async function batchArchiveInactive() {
    try {
        const res = await apiClient.post('/user-chat/conversations/batch-archive', { days: 30 })
        const count = res.data?.data?.archived_count || 0
        ElMessage.success(t('user_chat_page.msgs.archived_inactive', { n: count }))
        loadConversations()
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.archive_fail')) }
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
        }).catch(e => ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.send_fail')))
    } else if (item.gif) {
        // 发送 GIF 消息
        const gif = item.gif
        apiClient.post('/stickers/send/' + activeConv.value.id, {
            image_url: gif.url || gif.preview,
            emoji: ''
        }).then(() => {
            loadMessages()
            showStickerPanel.value = false
        }).catch(e => ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.send_fail')))
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
        ElMessage.success(t('user_chat_page.msgs.tags_saved'))
        await loadConvTags(activeConv.value.id)
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.save_fail')) }
    finally { savingTags.value = false }
}

async function loadInboxHub() {
    try {
        const res = await apiClient.get('/notifications/unread-count')
        const d = res.data?.data || {}
        inboxHub.value = {
            interaction_count: d.interaction_count || 0,
            interaction_preview: d.interaction_preview || '',
            system_count: d.system_count || 0,
            system_total: d.system_total || 0,
            system_preview: d.system_preview || '',
        }
        const sysUnread = d.system_count || 0
        const ixUnread = d.interaction_count || 0
        notifGroupMeta.value = notifGroupMeta.value.map(g => {
            if (g.key === 'interactions') return { ...g, unread: ixUnread }
            if (g.key === 'system') return { ...g, unread: sysUnread }
            return g
        })
    } catch { /* ignore */ }
}
function openNotifHub(group) {
    sidebarTab.value = 'notifications'
    notifGroup.value = group === 'system' ? 'system' : 'interactions'
    loadNotifications()
}
function mergeNotifGroups(groups, systemUnread) {
    const byKey = Object.fromEntries((groups || []).map(g => [g.key, g.unread || 0]))
    return [
        { key: 'all', unread: 0 },
        { key: 'interactions', unread: byKey.all || inboxHub.value.interaction_count || 0 },
        { key: 'likes', unread: byKey.likes || 0 },
        { key: 'comments', unread: byKey.comments || 0 },
        { key: 'follows', unread: byKey.follows || 0 },
        { key: 'system', unread: systemUnread ?? inboxHub.value.system_count ?? 0 },
    ]
}
function orderCardStatusType(status) {
    return { paid: 'success', pending: 'warning', cancelled: 'info', refunding: 'danger', refunded: 'info', partial_refund: 'warning' }[status] || 'info'
}
function orderCardStatusLabel(status) {
    const key = 'user_chat_page.card.status_' + (status || '')
    const label = t(key)
    return label === key ? (status || '') : label
}
function aftersaleCardStatusType(status) {
    return { open: 'warning', pending: 'warning', in_progress: 'primary', replied: 'success', resolved: 'success', closed: 'info' }[status] || 'info'
}
function aftersaleCardStatusLabel(status) {
    const key = 'user_chat_page.card.ticket_' + (status || '')
    const label = t(key)
    return label === key ? (status || '') : label
}

// ── 通知（含互动聚合） ──
function parseNotifPayload(n) {
    let payload = n?.payload
    if (typeof payload === 'string') {
        try { payload = JSON.parse(payload) } catch { payload = {} }
    }
    return payload || {}
}
function notifActorAvatar(n) {
    const p = parseNotifPayload(n)
    return p.actor?.avatar || p.actors?.[0]?.avatar || ''
}
function notifActorName(n) {
    const p = parseNotifPayload(n)
    return p.actor?.name || p.actors?.[0]?.name || t('user_chat_page.msg.user_fallback')
}
async function loadNotifications() {
    try {
        if (notifGroup.value === 'likes' || notifGroup.value === 'comments' || notifGroup.value === 'follows' || notifGroup.value === 'interactions') {
            const group = notifGroup.value === 'interactions' ? 'all' : notifGroup.value
            const res = await apiClient.get('/notifications/interactions', {
                params: { group, per_page: 50 },
            })
            notifications.value = res.data?.data || []
            const groups = res.data?.meta?.groups
            if (Array.isArray(groups)) {
                notifGroupMeta.value = mergeNotifGroups(groups)
            }
            return
        }
        if (notifGroup.value === 'system') {
            const res = await apiClient.get('/notifications', { params: { per_page: 50 } })
            const rows = res.data?.data || []
            notifications.value = rows.filter(n => !['interaction_like', 'interaction_comment', 'interaction_mention', 'interaction_follow', 'im_message'].includes(n.type))
            const unreadSys = notifications.value.filter(n => !n.is_read).length
            notifGroupMeta.value = notifGroupMeta.value.map(g => g.key === 'system' ? { ...g, unread: unreadSys } : g)
            return
        }
        // all: 全部通知（含互动 + 私信 + 系统）
        const [allRes, ixRes] = await Promise.all([
            apiClient.get('/notifications', { params: { per_page: 50 } }),
            apiClient.get('/notifications/interactions', { params: { group: 'all', per_page: 1 } }).catch(() => null),
        ])
        notifications.value = (allRes.data?.data || []).filter(n => n.type !== 'im_message')
        const groups = ixRes?.data?.meta?.groups
        if (Array.isArray(groups)) {
            notifGroupMeta.value = mergeNotifGroups(groups)
        }
    } catch {
        notifications.value = []
    }
}
async function handleNotifClick(n) {
    if (!n) return
    if (!n.is_read) {
        try {
            await apiClient.post('/notifications/' + n.id + '/read')
            n.is_read = true
        } catch { /* ignore */ }
    }
    const payload = parseNotifPayload(n)
    if (payload.conversation_id) {
        sidebarTab.value = 'messages'
        let conv = conversations.value.find(c => c.id === payload.conversation_id)
        if (!conv) {
            await loadConversations()
            conv = conversations.value.find(c => c.id === payload.conversation_id)
        }
        if (conv) {
            await selectConversation(conv)
        } else {
            ElMessage.info(t('user_chat_page.msgs.conv_not_found'))
        }
        return
    }
    if (payload.moment_id || (payload.target_type === 'moment' && payload.target_id)) {
        const mid = payload.moment_id || payload.target_id
        window.open(`/build/plaza/${mid}`, '_blank')
        return
    }
    if (payload.user_id || (payload.target_type === 'user' && payload.target_id)) {
        const uid = payload.user_id || payload.target_id
        window.open(`/build/plaza/user/${uid}`, '_blank')
        return
    }
    if (payload.url) {
        window.open(payload.url.startsWith('http') ? payload.url : payload.url, '_blank')
    }
}
async function markAllNotifRead() {
    try {
        const params = {}
        if (notifGroup.value === 'likes') params.type = 'interaction_like'
        else if (notifGroup.value === 'comments') params.type = 'interaction_comment,interaction_mention'
        else if (notifGroup.value === 'follows') params.type = 'interaction_follow'
        else if (notifGroup.value === 'interactions') params.type = 'interaction_like,interaction_comment,interaction_mention,interaction_follow'
        else if (notifGroup.value === 'system') {
            /* mark all non-interaction; backend has no exclude filter — mark all */
        }
        await apiClient.post('/notifications/read-all', null, { params })
        notifications.value.forEach(n => { n.is_read = true })
        notifGroupMeta.value = notifGroupMeta.value.map(g => ({ ...g, unread: 0 }))
        await loadInboxHub()
    } catch { ElMessage.error(t('user_chat_page.msgs.op_fail')) }
}

async function startChatWithUser(userId) {
    if (!userId || userId === myId.value) return
    try {
        const res = await apiClient.post('/user-chat/conversations', { participant_ids: [userId] })
        const conv = res.data?.data
        if (!conv) return
        sidebarTab.value = 'messages'
        const exists = conversations.value.find(c => c.id === conv.id)
        if (!exists) conversations.value.unshift(conv)
        await selectConversation(conv)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msg.create_conv_failed'))
    }
}
async function startSellerInquiry(sellerId, productId, options = {}) {
    if (!sellerId || sellerId === myId.value) return null
    try {
        if (productId && !options.skipCard) {
            const res = await apiClient.post('/user-chat/seller-inquiry', {
                seller_id: sellerId,
                product_id: productId,
            })
            const conv = res.data?.data?.conversation
            if (!conv) return null
            sidebarTab.value = 'messages'
            const exists = conversations.value.find(c => c.id === conv.id)
            if (!exists) conversations.value.unshift(conv)
            await selectConversation(conv)
            ElMessage.success(t('user_chat_page.msg.inquiry_sent'))
            return conv
        }
        const res = await apiClient.post('/user-chat/conversations', { participant_ids: [sellerId] })
        const conv = res.data?.data
        if (!conv) return null
        sidebarTab.value = 'messages'
        const exists = conversations.value.find(c => c.id === conv.id)
        if (!exists) conversations.value.unshift(conv)
        await selectConversation(conv)
        return conv
    } catch (e) {
        ElMessage.error(e.response?.data?.message || e.response?.data?.error?.message || t('user_chat_page.msg.open_seller_failed'))
        return null
    }
}

const { handleDeepLink } = useUserChatDeepLink({
    startSellerInquiry,
    startChatWithUser,
    selectConversation,
    conversations,
    loadConversations,
})


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
function handleMoreTab(cmd) {
    sidebarTab.value = cmd
    onSidebarTabChange()
}
function openMessageRequests() {
    sidebarTab.value = 'message-requests'
    loadMessageRequests()
}
async function previewMessageRequest(conv) {
    await selectConversation(conv)
}
async function acceptMessageRequest(conv) {
    try {
        const res = await apiClient.post('/user-chat/message-requests/' + conv.id + '/accept')
        const accepted = res.data?.data
        ElMessage.success(t('user_chat_page.msgs.msg_request_accepted'))
        messageRequests.value = messageRequests.value.filter(c => c.id !== conv.id)
        await loadConversations()
        sidebarTab.value = 'messages'
        if (accepted) await selectConversation(accepted)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
    }
}
async function rejectMessageRequest(conv, { block = false } = {}) {
    try {
        if (block) {
            await ElMessageBox.confirm(t('user_chat_page.msgs.block_confirm'), t('user_chat_page.msgs.block_title'), { type: 'warning' })
        } else {
            await ElMessageBox.confirm(t('user_chat_page.msg_request.reject_confirm'), t('user_chat_page.msg_request.reject'), { type: 'warning' })
        }
        await apiClient.post('/user-chat/message-requests/' + conv.id + '/reject', { block })
        messageRequests.value = messageRequests.value.filter(c => c.id !== conv.id)
        if (activeConv.value?.id === conv.id) activeConv.value = null
        ElMessage.success(block ? t('user_chat_page.msgs.rejected_blocked') : t('user_chat_page.msgs.rejected'))
        await loadConversations()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
    }
}
async function submitAddFriend() { if (!addFriendUserId.value) return; addFriendLoading.value = true; try { await apiClient.post('/user-chat/friends/add', { user_id: addFriendUserId.value }); showAddFriend.value = false; ElMessage.success(t('user_chat_page.msgs.friend_request_sent')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.add_fail')) } finally { addFriendLoading.value = false } }
async function handleFriendRequest(id, status) { try { await apiClient.put('/user-chat/friends/'+id+'/handle', { status }); await loadPendingRequests(); await loadFriendsEnhanced() } catch { /* ignore */ } }
async function startFriendChat(f) { try { const res = await apiClient.post('/user-chat/conversations', { participant_ids: [f.id] }); const conv = res.data?.data; if (conv) { sidebarTab.value = 'messages'; const exists = conversations.value.find(c => c.id === conv.id); if (!exists) conversations.value.unshift(conv); selectConversation(conv) } } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.conv_create_fail')) } }
function handleFriendAction(cmd, f) { if (cmd === 'remark') { remarkTarget.value = f; remarkText.value = f.remark || ''; showRemarkDialog.value = true } else if (cmd === 'group') { moveGroupTarget.value = f; moveGroupId.value = f.friend_group_id || null; showMoveGroupDialog.value = true } else if (cmd === 'remove') { removeFriend(f) } }
async function submitRemark() { if (!remarkTarget.value || !remarkText.value) return; try { await apiClient.put('/user-chat/friends/'+remarkTarget.value.id+'/remark', { remark: remarkText.value }); remarkTarget.value.remark = remarkText.value; showRemarkDialog.value = false; ElMessage.success(t('user_chat_page.msgs.remark_updated')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.setting_fail')) } }
async function submitMoveGroup() { if (!moveGroupTarget.value) return; try { await apiClient.put('/user-chat/friends/'+moveGroupTarget.value.id+'/group', { group_id: moveGroupId.value }); moveGroupTarget.value.friend_group_id = moveGroupId.value; showMoveGroupDialog.value = false; ElMessage.success(t('user_chat_page.msgs.moved')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.move_fail')) } }
async function removeFriend(f) { try { await ElMessageBox.confirm(t('user_chat_page.msgs.delete_friend_confirm', { name: f.name || t('user_chat_page.msg.user') })); await apiClient.delete('/user-chat/friends/'+f.id); await loadFriendsEnhanced() } catch { /* ignore */ } }
async function handleAiFriendAction(cmd, f) {
  if (cmd === 'delete') {
    try {
      await ElMessageBox.confirm(t('user_chat_page.msgs.delete_ai_confirm', { name: f.name }));
      await apiClient.delete('/ai-friends/personal/' + f.id);
      ElMessage.success(t('user_chat_page.msgs.ai_deleted'));
      await loadAiFriends();
    } catch (e) {
      if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.delete_fail'));
    }
  }
}
async function createFriendGroup() { if (!newFriendGroupName.value.trim()) return; try { const res = await apiClient.post('/user-chat/groups', { name: newFriendGroupName.value.trim() }); friendGroups.value.push(res.data?.data); newFriendGroupName.value = ''; ElMessage.success(t('user_chat_page.msgs.group_created')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.create_fail')) } }
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
        return `<span class="spoiler-text" onclick="this.classList.toggle('spoiler-revealed')" title="${escapeHtml(t('user_chat_page.msgs.click_to_view'))}">${inner}</span>`
    })
    // 处理 ||spoiler||
    text = text.replace(/\|\|([^|]+)\|\|/g, (_, match) => {
        const inner = escapeHtml(match.trim())
        return `<span class="spoiler-text" onclick="this.classList.toggle('spoiler-revealed')" title="${escapeHtml(t('user_chat_page.msgs.click_to_view'))}">${inner}</span>`
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
async function submitAnnouncement() { if (!newAnnouncement.value.title.trim() || !newAnnouncement.value.content.trim()) { ElMessage.warning(t('user_chat_page.msgs.fill_title_content')); return }; creatingAnnouncement.value = true; try { await apiClient.post('/user-chat/announcements', { conversation_id: activeConv.value.id, title: newAnnouncement.value.title.trim(), content: newAnnouncement.value.content.trim() }); ElMessage.success(t('user_chat_page.msgs.ann_published')); showCreateAnnouncement.value = false; newAnnouncement.value = { title: '', content: '' }; await loadAnnouncements() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.publish_fail2')) } finally { creatingAnnouncement.value = false } }
async function markAnnouncementRead(a) { if (a.is_read) return; try { await apiClient.post('/user-chat/announcements/'+a.id+'/read'); a.is_read = true; ElMessage.success(t('user_chat_page.msgs.marked_read')); await loadAnnouncements() } catch { /* ignore */ } }
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
async function saveSlowMode() { if (!activeConv.value) return; savingSlowMode.value = true; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/slow-mode', { interval: slowModeInterval.value }); activeConv.value.slow_mode_interval = slowModeInterval.value; ElMessage.success(slowModeInterval.value > 0 ? t('user_chat_page.msgs.slow_on') : t('user_chat_page.msgs.slow_off')); showSlowModeDialog.value = false } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.setting_fail')) } finally { savingSlowMode.value = false } }
function openSlowModeDialog() { loadSlowMode(); showSlowModeDialog.value = true }

// ── 群邀请 ──
const showInviteDialog = ref(false); const inviteTab = ref('create'); const invites = ref([]); const creatingInvite = ref(false); const newInviteUrl = ref(''); const inviteExpires = ref(24); const inviteMaxUses = ref(0); const loadingInvites = ref(false)
function openInviteDialog() { showInviteDialog.value = true; inviteTab.value = 'create'; newInviteUrl.value = '' }
async function createInvite() { if (!activeConv.value) return; creatingInvite.value = true; try { const params = {}; if (inviteExpires.value > 0) params.expires_in_hours = inviteExpires.value; if (inviteMaxUses.value > 0) params.max_uses = inviteMaxUses.value; const res = await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/invites', params); newInviteUrl.value = res.data?.data?.url || ''; ElMessage.success(t('user_chat_page.msgs.invite_generated')); await loadInvites() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.gen_fail')) } finally { creatingInvite.value = false } }
async function loadInvites() { if (!activeConv.value) return; loadingInvites.value = true; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/invites'); invites.value = res.data?.data || [] } catch { invites.value = [] } finally { loadingInvites.value = false } }
async function revokeInvite(inv) { try { await apiClient.delete('/user-chat/invites/'+inv.id); inv.is_active = false; inv.is_valid = false; ElMessage.success(t('user_chat_page.msgs.invite_revoked')); await loadInvites() } catch { /* ignore */ } }
function copyInviteUrl() { navigator.clipboard.writeText(newInviteUrl.value); ElMessage.success(t('user_chat_page.msgs.link_copied')) }

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

async function openSelfChat() { try { const res = await apiClient.post('/user-chat/self-conversation'); const conv = res.data?.data; if (conv) { sidebarTab.value = 'messages'; const item = upsertConversation(conv, { is_self: true, type: 'private', name: conv.name || ('📁 ' + t('user_chat_page.file_transfer.name')) }); await selectConversation(item) } } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.open_fail')) } }

// ── AI-012: AI 助手单聊 ──
async function openAIChat() { try { const res = await apiClient.post('/user-chat/ai-conversation'); const conv = res.data?.data; if (!conv) return; aiConvId.value = conv.id; sidebarTab.value = 'messages'; const item = upsertConversation(conv, { type: 'ai', is_ai_assistant: true, name: conv.name || t('user_chat_page.ai.assistant') }); await selectConversation(item); showAIPanel.value = true; if (!aiMessages.value.length) aiMessages.value = [{ role: 'assistant', content: t('user_chat_page.ai.welcome') }] } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.open_fail')) } }

// ── AI-004: AI 写作助手（润色/扩写/翻译/改语气）──
async function aiWrite(mode) { if (!inputMessage.value.trim() || !activeConv.value) return; const original = inputMessage.value; inputMessage.value = ''; aiLoading.value = true; try { const token = localStorage.getItem('auth_token') || ''; const res = await fetch('/api/user-chat/conversations/'+activeConv.value.id+'/chat-stream', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'text/event-stream', 'Authorization': 'Bearer ' + token }, body: JSON.stringify({ message: original, mode: mode === 'polish' ? 'polish' : mode === 'translate' ? 'translate' : 'chat' }) }); const reader = res.body?.getReader(); if (!reader) throw new Error('No reader'); const decoder = new TextDecoder(); let fullContent = ''; while (true) { const { done, value } = await reader.read(); if (done) break; const text = decoder.decode(value, { stream: true }); const lines = text.split('\n'); for (const line of lines) { if (line.startsWith('data: ')) { try { const data = JSON.parse(line.slice(6)); if (data.type === 'chunk') fullContent += data.content; else if (data.type === 'done') fullContent = data.content } catch {} } } }; if (fullContent) { inputMessage.value = fullContent; ElMessage.success(t('user_chat_page.msgs.optimized')) } else { inputMessage.value = original } } catch { inputMessage.value = original; ElMessage.error(t('user_chat_page.msgs.optimize_fail')) } finally { aiLoading.value = false } }

// ── AIF: AI 好友系统 ──
async function loadAiFriends() { try { const res = await apiClient.get('/ai-friends/my'); aiFriends.value = res.data?.data || [] } catch { aiFriends.value = [] } }
function getDefaultAvatar(name) { return 'https://ui-avatars.com/api/?name='+encodeURIComponent(name||'AI')+'&background=409eff&color=fff&size=80' }
async function submitCreateAiFriend() {
  if (!newAiFriend.value.name.trim()) return ElMessage.warning(t('user_chat_page.msgs.name_required'));
  creatingAiFriend.value = true;
  try {
    const payload = { ...newAiFriend.value };
    const res = await apiClient.post('/ai-friends/personal', payload);
    if (res.data?.success) {
      ElMessage.success(t('user_chat_page.msgs.ai_created'));
      showCreateAiFriend.value = false;
      newAiFriend.value = { name:'', category:'assistant', provider:'deepseek', model_name:'deepseek-chat', api_key:'', api_base_url:'', system_prompt:'', avatar_url:'' };
      openAvatarInput.value = false;
      await loadAiFriends();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.create_fail')) }
  finally { creatingAiFriend.value = false }
}
async function startAiFriendChat(f) {
  // 打开与 AI 好友的会话
  try {
    const res = await apiClient.post('/ai-friends/' + f.id + '/chat', { message: f.welcome_message || t('user_chat_page.msgs.greeting_hi') });
    const d = res.data?.data;
    if (d) {
      sidebarTab.value = 'messages';
      if (d.conversation_id) {
        const conv = { id: d.conversation_id, name: f.name, type: 'ai_friend', is_ai_friend: true };
        const exists = conversations.value.find(c => c.id === d.conversation_id);
        if (!exists) conversations.value.unshift(conv);
        selectConversation(conv);
      }
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.open_fail')) }
}

// ── AI 好友管理（管理员）──
async function loadAdminAiFriends() { loadingAdminAi.value = true; try { const res = await apiClient.get('/ai-friends/admin'); adminAiFriends.value = res.data?.data?.data || res.data?.data || [] } catch { adminAiFriends.value = [] } finally { loadingAdminAi.value = false } }
async function testAiFriend(id) { testingId.value = id; try { const res = await apiClient.post('/ai-friends/admin/'+id+'/test'); ElMessage.success(res.data?.data?.message || t('user_chat_page.msgs.test_ok')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.test_fail')) } finally { testingId.value = null } }
async function publishAiFriend(id) { try { await apiClient.post('/ai-friends/admin/'+id+'/publish'); ElMessage.success(t('user_chat_page.msgs.published_all')); await loadAdminAiFriends() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.publish_fail2')) } }
async function viewAiFriendConvs(row) {
    aiFriendConvsTarget.value = row
    aiFriendConvsName.value = row.user?.name || t('user_chat_page.ai.friend')
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
    ElMessage.success(t('user_chat_page.msgs.avatar_uploaded'));
  } catch (e) {
    ElMessage.error(t('user_chat_page.msgs.avatar_fail'));
  }
}
async function uploadPlatformAvatar(options) {
  const formData = new FormData();
  formData.append('avatar', options.file);
  try {
    const res = await apiClient.post('/ai-friends/upload-avatar', formData);
    platformAiForm.value.avatar_url = res.data?.data?.url || '';
    ElMessage.success(t('user_chat_page.msgs.avatar_uploaded'));
  } catch (e) {
    ElMessage.error(t('user_chat_page.msgs.avatar_fail'));
  }
}

async function submitPlatformAi() {
  if (!platformAiForm.value.name.trim()) return ElMessage.warning(t('user_chat_page.msgs.name_required'));
  creatingPlatformAi.value = true;
  try {
    const payload = { ...platformAiForm.value };
    if (payload.provider === 'deepseek') delete payload.api_key;
    const res = await apiClient.post('/ai-friends/admin', payload);
    if (res.data?.success) {
      ElMessage.success(t('user_chat_page.msgs.platform_created'));
      showCreatePlatformAi.value = false;
      platformAiForm.value = { name:'', category:'assistant', provider:'deepseek', model_name:'deepseek-chat', api_key:'', avatar_url:'', welcome_message:'', system_prompt:'' };
      platformAiShowUrl.value = false;
      // 自动发布
      const newId = res.data?.data?.id;
      if (newId) await publishAiFriend(newId);
      await loadAdminAiFriends();
      await loadAiFriends();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.create_fail')) }
  finally { creatingPlatformAi.value = false }
}

// ── 语音消息 ──
const isRecording = ref(false); const recordingDuration = ref(0); let mediaRecorder = null; let audioChunks = []; let recordingTimer = null; const voicePlayingId = ref(null); let audioPlayer = null
async function toggleVoiceRecord() { if (isRecording.value) { stopRecording(); return }; if (!navigator.mediaDevices?.getUserMedia) { ElMessage.warning(t('user_chat_page.msgs.no_recording')); return }; try { const stream = await navigator.mediaDevices.getUserMedia({ audio: true }); const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')?'audio/webm;codecs=opus':'audio/webm'; mediaRecorder = new MediaRecorder(stream, { mimeType }); audioChunks = []; recordingDuration.value = 0; isRecording.value = true; mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data) }; mediaRecorder.onstop = async () => { isRecording.value = false; clearInterval(recordingTimer); stream.getTracks().forEach(t => t.stop()); if (audioChunks.length === 0) return; const blob = new Blob(audioChunks, { type: 'audio/webm' }); if (blob.size < 100) return; await sendVoiceMessage(blob) }; mediaRecorder.start(); recordingTimer = setInterval(() => { recordingDuration.value++ }, 1000) } catch { ElMessage.error(t('user_chat_page.msgs.mic_fail')); isRecording.value = false } }
function stopRecording() { if (mediaRecorder && mediaRecorder.state !== 'inactive') mediaRecorder.stop() }
async function sendVoiceMessage(blob) { if (!activeConv.value) return; const formData = new FormData(); formData.append('file', blob, 'voice.webm'); try { const uploadRes = await apiClient.post('/im/upload', formData, { headers: { 'Content-Type': 'multipart/form-data' } }); const url = uploadRes.data?.data?.url; if (!url) { ElMessage.error(t('user_chat_page.msgs.upload_fail')); return }; await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { message_type: 'voice', content: url, attachments: [{ duration: recordingDuration.value }] }); await loadMessages() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.send_fail')) } }
function voiceDuration(msg) { return msg.attachments?.[0]?.duration || msg.duration || 0 }
function playVoice(msg) { if (!msg.content) return; if (voicePlayingId.value === msg.id && audioPlayer) { audioPlayer.pause(); audioPlayer = null; voicePlayingId.value = null; return }; if (audioPlayer) { audioPlayer.pause(); audioPlayer = null }; voicePlayingId.value = msg.id; audioPlayer = new Audio(msg.content); audioPlayer.onended = () => { voicePlayingId.value = null; audioPlayer = null }; audioPlayer.onerror = () => { voicePlayingId.value = null; audioPlayer = null; ElMessage.warning(t('user_chat_page.msgs.play_fail')) }; audioPlayer.play().catch(() => { voicePlayingId.value = null }) }

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
            ElMessage.success(t('user_chat_page.msgs.transcribe_ok'));
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.transcribe_fail'));
    } finally {
        msg._transcribing = false;
    }
}

// ── 会话文件夹 ──
const showFolderDialog = ref(false); const folders = ref(loadFolders()); const activeFolder = ref(''); const newFolderName = ref('')
function loadFolders() { try { return JSON.parse(localStorage.getItem('chat-folders') || '[]') } catch { return [] } }
function saveFolders() { localStorage.setItem('chat-folders', JSON.stringify(folders.value)) }
function createFolder() { if (!newFolderName.value.trim()) { ElMessage.warning(t('user_chat_page.msgs.name_required')); return }; folders.value.push({ id: Date.now(), name: newFolderName.value.trim() }); saveFolders(); newFolderName.value = ''; ElMessage.success(t('user_chat_page.msgs.group_created')) }
function deleteFolder(f) { folders.value = folders.value.filter(x => x.id !== f.id); saveFolders(); if (activeFolder.value === f.id || String(activeFolder.value) === String(f.id)) activeFolder.value = ''; ElMessage.success(t('user_chat_page.msgs.folder_deleted')) }
function onFolderChange() { /* filter handled by computed */ }
function showConvFolderMenu(e, conv) { const menu = document.createElement('div'); menu.className = 'conv-folder-menu'; menu.style.cssText = 'position:fixed;left:'+e.clientX+'px;top:'+e.clientY+'px;z-index:9999;background:#fff;border:1px solid #e4e7ed;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);padding:4px 0;min-width:160px'; menu.innerHTML = '<div style="padding:4px 12px;font-size:12px;color:#999;border-bottom:1px solid #f0f0f0">'+t('user_chat_page.msgs.assign_to_folder')+'</div>'; const noneItem = document.createElement('div'); noneItem.className = 'folder-menu-item'; noneItem.textContent = t('user_chat_page.msgs.no_folder'); noneItem.onclick = () => { assignConvFolder(conv, null); menu.remove() }; menu.appendChild(noneItem); folders.value.forEach(f => { const item = document.createElement('div'); item.className = 'folder-menu-item'; item.textContent = f.name; item.onclick = () => { assignConvFolder(conv, f.id); menu.remove() }; menu.appendChild(item) }); document.body.appendChild(menu); setTimeout(() => document.addEventListener('click', (e2) => { if (!menu.contains(e2.target)) { menu.remove() } }), 10) }
function assignConvFolder(conv, folderId) { const key = 'chat-folder-convs-'+(folderId||'null'); let ids = JSON.parse(localStorage.getItem(key)||'[]'); if (!folderId) { folders.value.forEach(f => { const k = 'chat-folder-convs-'+f.id; let ids2 = JSON.parse(localStorage.getItem(k)||'[]'); ids2 = ids2.filter(id => id !== conv.id); localStorage.setItem(k, JSON.stringify(ids2)) }) } else { if (!ids.includes(conv.id)) ids.push(conv.id); localStorage.setItem(key, JSON.stringify(ids)) }; ElMessage.success(folderId ? t('user_chat_page.msgs.assigned_folder') : t('user_chat_page.msgs.unassigned_folder')) }

// ── 名片消息 ──
async function shareContact(msg) { if (!activeConv.value) return; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { message_type: 'contact', content: String(msg.sender_id) }); await loadMessages(); ElMessage.success(t('user_chat_page.msgs.contact_shared')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.send_fail')) } }
function contactName(msg) { return msg.contact_name || t('user_chat_page.msg.user') }
async function addContactByCard(msg) { const userId = parseInt(msg.content); if (!userId) return; try { await apiClient.post('/user-chat/friends/add', { user_id: userId }); ElMessage.success(t('user_chat_page.msgs.friend_request_sent')) } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.add_fail')) } }

// ── 举报 ──
function openReportDialog(msg) { reportTarget.value = msg; reportReason.value = 'spam'; reportDescription.value = ''; showReportDialog.value = true }
async function submitReport() { if (!reportTarget.value) return; submittingReport.value = true; try { await apiClient.post('/user-chat/reports', { reportable_type: 'message', reportable_id: reportTarget.value.id, reason: reportReason.value, description: reportDescription.value }); ElMessage.success(t('user_chat_page.msgs.report_ok')); showReportDialog.value = false } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.submit_fail')) } finally { submittingReport.value = false } }
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
                    reply_content: t('user_chat_page.msgs.auto_reply_default'),
                    is_active: true,
                })
                ElMessage.success(t('user_chat_page.msgs.auto_reply_on'))
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
async function loadPrivacySettings() {
    try {
        const res = await apiClient.get('/user-chat/privacy-settings')
        const data = res.data?.data || {}
        if (!data.dm_policy) {
            data.dm_policy = data.allow_stranger_message ? 'everyone' : 'followers_only'
        }
        privacySettings.value = { ...privacySettings.value, ...data }
    } catch { /* ignore */ }
}
async function savePrivacySettings() {
    savingPrivacy.value = true
    try {
        const payload = {
            friend_add_policy: privacySettings.value.friend_add_policy,
            show_online_status: privacySettings.value.show_online_status,
            show_read_receipt: privacySettings.value.show_read_receipt,
            dm_policy: privacySettings.value.dm_policy || 'followers_only',
        }
        const res = await apiClient.put('/user-chat/privacy-settings', payload)
        const data = res.data?.data || payload
        privacySettings.value = { ...privacySettings.value, ...data }
        ElMessage.success(t('user_chat_page.msgs.privacy_saved'))
        showPrivacySettings.value = false
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.save_fail'))
    } finally {
        savingPrivacy.value = false
    }
}
// ── 私密空间 ──
async function loadPrivacyPinStatus() {
    try { const res = await apiClient.get('/user-chat/privacy-pin/status'); privacyPinStatus.value = res.data?.data || { has_pin: false, verified: false } } catch { privacyPinStatus.value = { has_pin: false, verified: false } }
}
async function submitPrivacyPin() {
    if (pinForm.value.newPin !== pinForm.value.confirmPin) { ElMessage.warning(t('user_chat_page.msgs.pin_mismatch')); return }
    if (!/^\d{4,20}$/.test(pinForm.value.newPin)) { ElMessage.warning(t('user_chat_page.msgs.pin_format')); return }
    savingPin.value = true
    try {
        await apiClient.post('/user-chat/privacy-pin/set', {
            pin: pinForm.value.newPin,
            current_pin: pinForm.value.currentPin || undefined,
        })
        ElMessage.success(setPinMode.value === 'set' ? t('user_chat_page.msgs.pin_set') : t('user_chat_page.msgs.pin_changed'))
        showSetPin.value = false
        pinForm.value = { currentPin: '', newPin: '', confirmPin: '' }
        loadPrivacyPinStatus()
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) } finally { savingPin.value = false }
}
async function removePrivacyPin() {
    try { await ElMessageBox.confirm(t('user_chat_page.msgs.clear_pin_remove'), t('actions.confirm'))
        await apiClient.post('/user-chat/privacy-pin/set', { pin: '000000' }) // dummy to fail - actually need a proper remove endpoint
        // For now just use the status check
        ElMessage.success(t('user_chat_page.msgs.feature_dev'))
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
    if (!unlockPin.value.trim()) { unlockError.value = t('user_chat_page.msgs.enter_pin'); return }
    unlocking.value = true; unlockError.value = ''
    try {
        await apiClient.post('/user-chat/privacy-pin/verify', { pin: unlockPin.value })
        privacyPinStatus.value.verified = true
        showUnlockDialog.value = false
        ElMessage.success(t('user_chat_page.msgs.verify_ok'))
        loadHiddenConvs()
    } catch (e) { unlockError.value = e.response?.data?.message || t('user_chat_page.msgs.pin_wrong') } finally { unlocking.value = false }
}
function onUnlockCancel() { unlockPin.value = ''; unlockError.value = '' }
async function loadHiddenConvs() {
    try {
        const res = await apiClient.get('/user-chat/conversations/hidden')
        hiddenConvs.value = res.data?.data || []
        showHiddenConvs.value = true
    } catch { ElMessage.error(t('user_chat_page.msgs.load_fail')) }
}
async function toggleHideConv(conv) {
    try {
        if (conv.is_hidden) {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/unhide')
            conv.is_hidden = false
            ElMessage.success(t('user_chat_page.msgs.unhidden'))
        } else {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/hide')
            conv.is_hidden = true
            ElMessage.success(t('user_chat_page.msgs.hidden'))
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) }
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
        ElMessage.success(t('user_chat_page.msgs.unhidden'))
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) }
}

// ── OPR-011: 投票 ──
function openPollDialog() { showPollDialog.value = true; pollQuestion.value = ''; pollOptions.value = ['', '']; pollType.value = 'single'; pollIsAnonymous.value = false; pollHideResults.value = false; pollExpireHours.value = null }
async function submitPoll() { if (!pollQuestion.value.trim() || !activeConv.value) return; const opts = pollOptions.value.filter(o => o.trim()); if (opts.length < 2) { ElMessage.warning(t('user_chat_page.msgs.poll_min_options')); return }; if (pollType.value === 'ranked' && opts.length > 10) { ElMessage.warning(t('user_chat_page.msgs.poll_max_ranked')); return }; creatingPoll.value = true; try { await apiClient.post('/user-chat/polls', { conversation_id: activeConv.value.id, question: pollQuestion.value, options: opts, type: pollType.value, is_anonymous: pollIsAnonymous.value, is_hide_results: pollHideResults.value, expires_in_hours: pollExpireHours.value || undefined }); ElMessage.success(t('user_chat_page.msgs.poll_published')); showPollDialog.value = false } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.create_fail')) } finally { creatingPoll.value = false } }
async function showPollDetail(pollId) { try { const res = await apiClient.get('/user-chat/polls/'+pollId); const d = res.data?.data || {}; activePoll.value = d.poll || d; pollResults.value = d.results || []; pollTotalVotes.value = d.total_voters || 0; showPollResult.value = true } catch { ElMessage.error(t('user_chat_page.msgs.poll_detail_fail')) } }
async function votePoll(pollId, optionId) {
    try {
        const votes = [{ option_id: optionId, rank: 1 }]
        await apiClient.post('/user-chat/polls/'+pollId+'/vote', { votes })
        ElMessage.success(t('user_chat_page.msgs.vote_ok'))
        showPollResult.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.vote_fail')) }
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
        ElMessage.warning(t('user_chat_page.msgs.topic_required')); return
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
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.analyze_fail'))
    } finally {
        moderatorRunning.value = false
    }
}

async function handleSummarize() { if (!activeConv.value) return; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/summarize'); const d = res.data?.data; if (!d) { ElMessage.warning(t('user_chat_page.msgs.no_summary')); return }; if (d.from_llm) { ElMessageBox.alert(`<div class="summary-content">${d.summary.replace(/\n/g, '<br>').replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')}</div><div class="summary-footer" style=\"margin-top:12px;color:#999;font-size:12px\">${t('user_chat_page.msgs.summary_footer', { n: d.total })}</div>`, t('user_chat_page.msgs.summary_title'), { dangerouslyUseHTMLString: true, customClass: 'summary-dialog', width: '520px', confirmButtonText: t('actions.close') }) } else { ElMessage.success(d.summary, 5000) } } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.summary_fail')) } }

// ── 位置消息 ──
const showLocationDialog = ref(false); const locationNameInput = ref(''); const locationLng = ref(116.397428); const locationLat = ref(39.90923); const gettingLocation = ref(false); const sendingLocation = ref(false)
function getCurrentLocation() { if (!navigator.geolocation) { ElMessage.warning(t('user_chat_page.msgs.geo_unsupported')); return }; gettingLocation.value = true; navigator.geolocation.getCurrentPosition((pos) => { locationLat.value = pos.coords.latitude; locationLng.value = pos.coords.longitude; gettingLocation.value = false }, () => { ElMessage.warning(t('user_chat_page.msgs.geo_fail')); gettingLocation.value = false }, { enableHighAccuracy: true, timeout: 10000 }) }
async function sendLocation() { if (!activeConv.value) return; sendingLocation.value = true; try { const content = (locationNameInput.value||t('user_chat_page.msgs.location_fallback'))+'@'+locationLat.value+','+locationLng.value; await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/messages', { message_type: 'location', content }); showLocationDialog.value = false; await loadMessages() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.send_fail')) } finally { sendingLocation.value = false } }
function locationName(msg) { return msg.content?.split('@')[0] || t('user_chat_page.msgs.location_fallback') }
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
        ElMessage.warning(t('user_chat_page.msgs.dnd_hours_required'))
        return
    }
    try {
        await apiClient.put('/notifications/preferences', {
            quiet_hours_start: dndEnabled.value ? dndStart.value : null,
            quiet_hours_end: dndEnabled.value ? dndEnd.value : null,
        })
        ElMessage.success(t('user_chat_page.msgs.dnd_saved'))
        showDndSettings.value = false
    } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.save_fail')) }
}

// ── IM 看板 ──
async function loadDashboard() { loadingDashboard.value = true; try { const res = await apiClient.get('/im/dashboard'); dashboardData.value = res.data?.data || {} } catch { dashboardData.value = {} } finally { loadingDashboard.value = false } }

// ── 群管理 ──
async function loadGroupMembers() { if (!activeConv.value) return; loadingGroupMembers.value = true; try { const res = await apiClient.get('/user-chat/conversations/'+activeConv.value.id+'/participants'); groupMembers.value = res.data?.data || []; const me = groupMembers.value.find(m => m.id === myId.value); userRoleInGroup.value = me?.pivot?.role || 'member' } catch { groupMembers.value = [] } finally { loadingGroupMembers.value = false } }

async function kickMember(m) { try { await ElMessageBox.confirm(t('user_chat_page.msgs.kick_confirm', { name: memberDisplayName(m) })); await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/kick/'+m.id); ElMessage.success(t('user_chat_page.msgs.kicked')); await loadGroupMembers() } catch { /* ignore */ } }
async function leaveCurrentGroup() { try { await ElMessageBox.confirm(t('user_chat_page.msgs.leave_confirm')); await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/leave'); ElMessage.success(t('user_chat_page.msgs.left_group')); showGroupManage.value = false; activeConv.value = null; await loadConversations() } catch { /* ignore */ } }
async function setAsAdmin(m) { try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/set-admin', { user_id: m.id, role: 'admin' }); ElMessage.success(t('user_chat_page.msgs.set_admin_ok')); await loadGroupMembers() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) } }
async function removeAdmin(m) { try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/set-admin', { user_id: m.id, role: 'member' }); ElMessage.success(t('user_chat_page.msgs.remove_admin_ok')); await loadGroupMembers() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail')) } }
async function confirmTransferOwner() { if (!newOwnerId.value) return; transferring.value = true; try { await apiClient.post('/user-chat/conversations/'+activeConv.value.id+'/transfer-owner', { user_id: newOwnerId.value }); ElMessage.success(t('user_chat_page.msgs.transfer_ok')); showTransferOwner.value = false; newOwnerId.value = null; await loadGroupMembers() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.transfer_fail')) } finally { transferring.value = false } }
async function dismissCurrentGroup() { try { await ElMessageBox.confirm(t('user_chat_page.msgs.dismiss_confirm'), t('user_chat_page.msgs.dismiss_title'), { confirmButtonText: t('user_chat_page.msgs.dismiss_confirm_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' }); await apiClient.delete('/user-chat/conversations/'+activeConv.value.id+'/dismiss'); ElMessage.success(t('user_chat_page.msgs.dismissed')); showGroupManage.value = false; activeConv.value = null; await loadConversations() } catch { /* ignore */ } }

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
        ElMessage.success(groupJoinApproval.value ? t('user_chat_page.msgs.join_approval_on') : t('user_chat_page.msgs.join_approval_off'))
    } catch (e) {
        groupJoinApproval.value = !groupJoinApproval.value // revert
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
    }
}
async function handleJoinRequest(req, action) {
    try {
        await apiClient.post('/user-chat/join-requests/'+req.id+'/handle', { action })
        ElMessage.success(action === 'approve' ? t('user_chat_page.msgs.approved') : t('user_chat_page.msgs.rejected'))
        pendingJoinReqs.value = pendingJoinReqs.value.filter(r => r.id !== req.id)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.op_fail'))
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
        ElMessage.success(t('user_chat_page.msgs.perm_updated'))
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.update_fail'))
    }
}

// ── 敏感词管理 ──
async function loadSensitiveWords() { try { const res = await apiClient.get('/im/sensitive-words'); sensitiveWords.value = res.data?.data || [] } catch { sensitiveWords.value = [] } }
async function addSensitiveWord() { if (!newSensitiveWord.value.word.trim()) return; addingSensitiveWord.value = true; try { await apiClient.post('/im/sensitive-words', { ...newSensitiveWord.value }); ElMessage.success(t('user_chat_page.msgs.sw_added')); newSensitiveWord.value = { word:'', replacement:'***', category:'general', severity:'medium' }; await loadSensitiveWords() } catch (e) { ElMessage.error(e.response?.data?.message || t('user_chat_page.msgs.add_fail')) } finally { addingSensitiveWord.value = false } }
async function toggleSensitiveWord(row) { try { await apiClient.put('/im/sensitive-words/'+row.id, { is_active: !row.is_active }); row.is_active = !row.is_active } catch { ElMessage.error(t('user_chat_page.msgs.op_fail')) } }
async function deleteSensitiveWord(row) { try { await ElMessageBox.confirm(t('user_chat_page.msgs.sw_delete_confirm', { word: row.word })); await apiClient.delete('/im/sensitive-words/'+row.id); ElMessage.success(t('user_chat_page.msgs.sw_deleted')); await loadSensitiveWords() } catch { /* ignore */ } }
async function testSensitiveWord() { if (!sensitiveTestText.value.trim()) return; testingSensitive.value = true; try { const res = await apiClient.post('/im/sensitive-words/test', { text: sensitiveTestText.value }); sensitiveTestResult.value = res.data?.data || null } catch { sensitiveTestResult.value = null } finally { testingSensitive.value = false } }

// ── Tab切换 ──
function onSidebarTabChange() {
    if (sidebarTab.value === 'friends') { loadFriendsEnhanced(); loadFriendGroups(); loadPendingRequests() }
    else if (sidebarTab.value === 'notifications') { loadNotifications() }
    else if (sidebarTab.value === 'favorites') { loadFavorites() }
    else if (sidebarTab.value === 'message-requests') { loadMessageRequests() }
    else if (sidebarTab.value === 'pending') { loadPending() }
    else if (sidebarTab.value === 'messages') { loadInboxHub() }
}

// ── 预览图片 ──
function previewImage(url) { window.open(url, '_blank') }
function openUrl(url) { window.open(url, '_blank') }

// ── 文件预览 ──
const showFilePreview = ref(false)
const previewFileData = ref({ url: '', name: '', size: 0, mime: '', ext: '' })
function previewFile(msg) {
    const name = msg.metadata?.file_name || msg.content || t('user_chat_page.msgs.file_fallback')
    const size = msg.metadata?.file_size || 0
    const mime = msg.metadata?.file_mime || ''
    const url = msg.content || ''
    const ext = name.split('.').pop() || ''
    previewFileData.value = { url, name, size, mime, ext }
    showFilePreview.value = true
}
function previewAttachment(att) {
    const name = att.name || t('user_chat_page.msg.attachment')
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
    return msg.metadata?.file_name || msg.content?.split('/').pop() || t('user_chat_page.msgs.file_fallback')
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
        ElMessage.info(t('user_chat_page.msgs.locating_msg'));
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
    if (!found) ElMessage.warning(t('user_chat_page.msgs.msg_not_found'));
}

// ── 生命周期 ──
onMounted(async () => {
    try { const userRes = await apiClient.get('/user'); const user = userRes.data?.data || {}; myId.value = user.id || 0; myName.value = user.name || ''; myAvatar.value = user.avatar_url || '' } catch { /* ignore */ }
    await loadConversations()
    loadInboxHub()
    try { await handleDeepLink() } catch { /* ignore */ }
    loadMessageRequests()
    loadPrivacySettings()
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

.user-chat-page { height: calc(100vh - 100px); position: relative; overflow: hidden; }
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
.msg-request-actions { display: flex; gap: 6px; flex-wrap: wrap; width: 100%; margin-top: 8px; padding-left: 48px; }
.msg-request-entry { background: #fff; }
.inbox-hub-section { background: #fff; }
.inbox-hub-item { background: #fff; }
.inbox-hub-avatar { display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
.inbox-hub-avatar-ix { background: #ff4d6d !important; }
.inbox-hub-avatar-sys { background: #409eff !important; }
.card-kicker { font-size: 11px; color: #909399; margin-bottom: 2px; letter-spacing: 0.02em; }
.msg-request-entry-avatar { background: #f56c6c !important; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
.msg-request-dot { position: absolute; top: 0; right: 0; width: 10px; height: 10px; border-radius: 50%; background: #f56c6c; border: 2px solid #fff; }
.msg-request-intro { padding: 8px 12px; font-size: 12px; color: #909399; line-height: 1.5; background: #fafafa; border-bottom: 1px solid #f0f0f0; }
.msg-request-why { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.msg-request-empty-hint { font-size: 12px; color: #909399; margin: 0 0 8px; line-height: 1.5; max-width: 240px; }
.msg-request-chat-banner { padding: 8px 16px; background: #fdf6ec; border-top: 1px solid #faecd8; font-size: 12px; color: #b88230; }
.msg-request-chat-banner.outgoing { background: #ecf5ff; border-color: #d9ecff; color: #409eff; }
.msg-request-chat-text { margin-bottom: 6px; line-height: 1.5; }
.dm-mute-banner { padding: 8px 12px; font-size: 12px; color: #e6a23c; background: #fdf6ec; border-bottom: 1px solid #faecd8; line-height: 1.5; }
.dm-mute-alert { margin-bottom: 12px; }
.dm-policy-group { display: flex; flex-direction: column; align-items: stretch; gap: 8px; width: 100%; }
.dm-policy-radio { display: flex; align-items: flex-start; height: auto; margin-right: 0; white-space: normal; line-height: 1.4; padding: 6px 0; }
.dm-policy-title { display: block; font-weight: 600; }
.dm-policy-desc { display: block; font-size: 12px; color: #909399; font-weight: 400; margin-top: 2px; }
.dm-policy-note { font-size: 12px; color: #909399; margin-top: 8px; line-height: 1.5; }
.more-tab-badge :deep(.el-badge__content.is-dot) { right: 2px; top: 4px; }
.chat-dark-mode .msg-request-intro { background: #1a1a2e; border-color: #2a2a4a; color: #aaa; }
.chat-dark-mode .msg-request-chat-banner { background: #3a2a10; border-color: #5a3a00; color: #e6a23c; }
.chat-dark-mode .msg-request-chat-banner.outgoing { background: #10243a; border-color: #1a3a5a; color: #66b1ff; }
.chat-dark-mode .dm-mute-banner { background: #3a2a10; border-color: #5a3a00; }
.chat-dark-mode .dm-policy-desc, .chat-dark-mode .dm-policy-note { color: #aaa; }
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
.card-aftersale_card, .card-ticket_card { border-top: 3px solid #e6a23c; }
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
.chat-dark-mode .msg-voice { background: #0f3460; }
.chat-dark-mode .msg-voice-self { background: #1a5276; }
.voice-wave { display: flex; align-items: center; gap: 1px; flex: 1; height: 30px; }
.voice-bar { width: 3px; background: #409eff; border-radius: 2px; min-height: 4px; }
.msg-voice-wrap { display: flex; flex-direction: column; gap: 4px; margin: 4px 0; }
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
.notif-filter-row { display: flex; flex-wrap: wrap; gap: 6px; padding: 8px 12px; border-bottom: 1px solid #eee; background: #fff; }
.notif-filter-chip { border: 1px solid #e4e7ed; background: #f5f7fa; color: #606266; font-size: 12px; padding: 4px 10px; border-radius: 999px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.notif-filter-chip.active { background: #ecf5ff; border-color: #b3d8ff; color: #409eff; }
.notif-chip-badge { background: #f56c6c; color: #fff; border-radius: 999px; font-size: 10px; min-width: 16px; height: 16px; line-height: 16px; text-align: center; padding: 0 4px; }
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
.msg-file { display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: #f5f7fa; border-radius: 8px; cursor: pointer; min-width: 200px; max-width: 280px; margin: 4px 0; transition: background .2s; }
.msg-file:hover { background: #eef0f4; }
.chat-dark-mode .msg-file { background: #2a2a3e; }
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
.ai-badge { position: absolute; bottom: -4px; right: -4px; font-size: 12px; line-height: 1; display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background: #67c23a; color: #fff; }
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
}
@media (min-width: 768px) { .back-btn { display: none; } }

/* ── 更多 Tab 下拉 ── */
.more-tab-label { display: inline-flex; align-items: center; gap: 2px; cursor: pointer; font-size: 12px; }
.more-tab-label:hover { color: #409eff; }
.chat-dark-mode .more-tab-label:hover { color: #66b1ff; }

.assistant-pinned-section { padding: 4px 0 0; }
.assistant-section { padding: 4px 0 0; }
.assistant-section-title { padding: 6px 12px 4px; font-size: 11px; color: #909399; font-weight: 600; }
.assistant-section-divider { height: 1px; background: #ebeef5; margin: 6px 8px; }
.assistant-pinned-item, .ai-assistant-item, .ai-assistant-entry { cursor: pointer; }
.assistant-avatar-file { background: #e8f4ff !important; color: #409eff; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.assistant-avatar-ai { background: #f0f9eb !important; color: #67c23a; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.ai-assistant-entry { margin: 8px; border-radius: 8px; border: 1px solid #e4e7ed; background: #fafcff; }
</style>
