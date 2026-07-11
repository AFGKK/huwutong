<template>
    <div class="agent-workspace">
        <!-- ═══════ 顶部: 坐席状态与统计 ═══════ -->
        <el-row :gutter="8" class="agent-stats-row">
            <el-col :span="8">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini">
                        <span class="stat-label">状态</span>
                        <el-select v-model="agentStatus" size="small" style="width:80px" @change="updateStatus">
                            <el-option label="在线" value="online" />
                            <el-option label="忙碌" value="busy" />
                            <el-option label="离开" value="away" />
                        </el-select>
                    </div>
                    <div class="stat-value-mini" :class="'status-' + agentStatus">
                        {{ agentStatus === 'online' ? '在线' : agentStatus === 'busy' ? '忙碌' : '离开' }}
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini"><span class="stat-label">排队中</span></div>
                    <div class="stat-value-mini" style="color:#e6a23c">{{ stats.queueTotal }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini"><span class="stat-label">进行中</span></div>
                    <div class="stat-value-mini" style="color:#409eff">{{ stats.activeChats }}</div>
                </el-card>
            </el-col>
            <el-col :span="6" style="margin-top:8px">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini"><span class="stat-label">紧急队列</span></div>
                    <div class="stat-value-mini" style="color:#f56c6c">{{ stats.urgentTotal }}</div>
                </el-card>
            </el-col>
            <el-col :span="6" style="margin-top:8px">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini"><span class="stat-label">平均等待</span></div>
                    <div class="stat-value-mini" style="color:#909399">{{ stats.avgWait }}</div>
                </el-card>
            </el-col>
            <el-col :span="6" style="margin-top:8px">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini"><span class="stat-label">在线客服</span></div>
                    <div class="stat-value-mini" style="color:#67c23a">{{ stats.onlineAgents }}</div>
                </el-card>
            </el-col>
            <el-col :span="6" style="margin-top:8px">
                <el-card shadow="never" class="stat-card" body-style="padding:8px">
                    <div class="stat-mini"><span class="stat-label">今日处理</span></div>
                    <div class="stat-value-mini">{{ stats.todayTotal }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- ═══════ Tab 切换: 排队 | 商品 | 快捷回复 ═══════ -->
        <el-tabs v-model="activeAgentTab" class="agent-tabs" @tab-change="onTabChange">
            <!-- ── Tab 1: 排队队列 + 聊天面板 ── -->
            <el-tab-pane label="📋 排队" name="queue" lazy>
                <div class="queue-layout">
                    <div class="queue-left" :class="{ 'with-chat': activeChatConv }">
                        <div class="queue-section">
                            <div class="queue-section-title">⏳ 等待中 <el-tag size="small" type="danger">{{ queue.length }}</el-tag></div>
                            <div v-if="loadingQueue" v-loading="loadingQueue" class="queue-loading" />
                            <div v-for="item in queue" :key="item.id" class="queue-item" :class="'priority-' + (item.priority || 'normal')" @click="selectQueueItem(item)">
                                <div class="queue-item-left">
                                    <div class="queue-avatar">{{ (item.customer?.user?.name || '?').charAt(0) }}</div>
                                </div>
                                <div class="queue-item-body">
                                    <div class="queue-item-name">
                                        {{ queueItemName(item) }}
                                        <el-tag v-if="isLiveChatHandoff(item)" size="small" type="warning" style="margin-left:4px">在线客服</el-tag>
                                    </div>
                                    <div class="queue-item-meta">
                                        <span class="queue-priority" :class="'badge-' + (item.priority || 'normal')">
                                            {{ item.priority === 'urgent' ? '紧急' : item.priority === 'high' ? '高' : item.priority === 'medium' ? '中' : '低' }}
                                        </span>
                                        <span class="queue-time">⏳ {{ formatWaitTime(item.created_at) }}</span>
                                    </div>
                                </div>
                                <el-button size="small" type="primary" @click.stop="acceptQueueItem(item)">接入</el-button>
                            </div>
                            <div v-if="!queue.length && !loadingQueue" class="empty-section"><el-empty description="暂无排队" :image-size="40" /></div>
                        </div>
                        <div class="queue-section" style="margin-top:8px">
                            <div class="queue-section-title">💬 进行中 <el-tag size="small" type="success">{{ activeConversations.length }}</el-tag></div>
                            <div v-for="conv in activeConversations" :key="conv.id" class="queue-item active-conv" :class="{ 'is-selected': activeChatConv?.id === conv.id }" @click="selectActiveConv(conv)">
                                <div class="queue-item-left">
                                    <div class="queue-avatar" style="background:#409eff">{{ (conv.customer?.user?.name || '?').charAt(0) }}</div>
                                </div>
                                <div class="queue-item-body">
                                    <div class="queue-item-name">{{ conv.customer?.user?.name || '会话' }}</div>
                                    <div class="queue-item-meta">
                                        <span class="queue-time">{{ formatTime(conv.created_at) }}</span>
                                        <span v-if="conv.status === 'in_progress'" class="status-dot active" />
                                        <span v-else class="status-dot" />
                                    </div>
                                </div>
                                <el-button size="small" circle text @click.stop="showTransferDialog(conv)" title="转接">
                                    <el-icon><Share /></el-icon>
                                </el-button>
                            </div>
                            <div v-if="!activeConversations.length && !loadingQueue" class="empty-section"><el-empty description="暂无进行中" :image-size="40" /></div>
                        </div>
                        <!-- 待转人工已合并到「等待中」队列 -->
                    </div>

                    <!-- ── 聊天面板 ── -->
                    <div v-if="activeChatConv" class="chat-panel">
                        <div class="chat-panel-header">
                            <div class="chat-panel-user">
                                <strong>{{ activeChatConvName }}</strong>
                                <el-tag size="small" type="success" v-if="activeChatConv.status === 'in_progress'">进行中</el-tag>
                                <el-tag size="small" type="warning" v-else>待接受</el-tag>
                            </div>
                            <div class="chat-panel-actions">
                                <el-button size="small" @click="showTransferDialog(activeChatConv)" :disabled="!activeChatConv.id">转交</el-button>
                                <el-button size="small" type="success" @click="handleCloseConv" v-if="activeChatConv.status === 'in_progress'">关闭</el-button>
                                <el-button size="small" type="primary" @click="handleAcceptConv" v-if="activeChatConv.status === 'assigned' || activeChatConv.status === 'queued'">接受</el-button>
                            </div>
                        </div>

                        <!-- 🌐 访客信息 -->
                        <div class="chat-visitor" v-if="visitorInfo">
                            <el-collapse>
                                <el-collapse-item title="🌐 访客信息">
                                    <div class="visitor-grid">
                                        <div class="visitor-item" v-if="visitorInfo.ip">
                                            <span class="v-label">IP 地址</span>
                                            <span class="v-value">{{ visitorInfo.ip }}</span>
                                            <el-tag size="small" v-if="visitorInfo.country" type="info">
                                                {{ [visitorInfo.country, visitorInfo.region, visitorInfo.city].filter(Boolean).join(' · ') }}
                                            </el-tag>
                                        </div>
                                        <div class="visitor-item" v-if="visitorInfo.browser">
                                            <span class="v-label">设备信息</span>
                                            <span class="v-value">{{ visitorInfo.os }} · {{ visitorInfo.browser }} · {{ visitorInfo.device }}</span>
                                        </div>
                                        <div class="visitor-item" v-if="visitorInfo.isp">
                                            <span class="v-label">运营商</span>
                                            <span class="v-value">{{ visitorInfo.isp }}</span>
                                        </div>
                                        <div class="visitor-item" v-if="visitorInfo.page_url">
                                            <span class="v-label">来源页面</span>
                                            <span class="v-value v-url" :title="visitorInfo.page_url">{{ visitorInfo.page_url }}</span>
                                        </div>
                                        <div class="visitor-item" v-if="visitorInfo.source">
                                            <span class="v-label">来源渠道</span>
                                            <span class="v-value">{{ sourceLabel(visitorInfo.source) }}</span>
                                        </div>
                                    </div>
                                </el-collapse-item>
                            </el-collapse>
                        </div>

                        <!-- 私信转接提示 -->
                        <div v-if="isUserChatHandoffActive" class="dm-handoff-notice">
                            <el-alert type="success" :closable="false" show-icon>
                                <template #title>
                                    此转接来自用户私信，回复将发送至私信会话
                                    <router-link v-if="dmConversationLink" :to="dmConversationLink" class="dm-link">在私信中查看 →</router-link>
                                </template>
                            </el-alert>
                        </div>

                        <!-- AI 上下文 -->
                        <div class="chat-context" v-if="activeChatConv.conversation_context">
                            <el-collapse>
                                <el-collapse-item title="💬 AI 对话上下文">
                                    <pre class="context-json">{{ JSON.stringify(activeChatConv.conversation_context, null, 2) }}</pre>
                                </el-collapse-item>
                            </el-collapse>
                        </div>

                        <!-- 消息列表 -->
                        <div class="chat-messages" ref="msgContainer">
                            <div v-for="msg in chatMessages" :key="msg.id" class="chat-msg" :class="'msg-' + (msg.sender_type || 'customer')">
                                <div class="msg-sender">
                                    {{ msg.sender_type === 'agent' ? '客服' : msg.sender_type === 'customer' ? '客户' : '系统' }}
                                    <span class="msg-time">{{ formatTime(msg.created_at) }}</span>
                                </div>
                                <div class="msg-bubble">{{ msg.content }}</div>
                            </div>
                            <div v-if="loadingMessages" class="chat-loading">加载中...</div>
                            <div v-if="!chatMessages.length && !loadingMessages" class="chat-empty">暂无消息</div>
                        </div>

                        <!-- 输入框 -->
                        <div class="chat-input-area" v-if="activeChatConv.status === 'in_progress'">
                            <el-input v-model="newMessage" type="textarea" :rows="2" placeholder="输入回复内容... (Ctrl+Enter 发送)" @keyup.enter.ctrl="sendChatMessage" />
                            <el-button type="primary" @click="sendChatMessage" :disabled="!newMessage.trim()" class="send-btn">发送</el-button>
                        </div>
                    </div>
                </div>
            </el-tab-pane>

            <!-- ── Tab 2: 商品浏览 ── -->
            <el-tab-pane label="📦 商品" name="products" lazy>
                <div class="product-search-bar">
                    <el-input v-model="productSearch" placeholder="搜索商品..." size="small" clearable
                        @keydown.enter="searchProducts" @clear="searchProducts">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-button size="small" type="primary" @click="searchProducts">搜索</el-button>
                </div>
                <div v-loading="productLoading" class="product-compact-list">
                    <div v-for="p in products" :key="p.id" class="product-compact-item">
                        <div class="product-compact-img">
                            <img v-if="p.image_url" :src="p.image_url" @error="$el.style.display='none'" />
                            <span v-else>📦</span>
                        </div>
                        <div class="product-compact-info">
                            <div class="product-compact-name">{{ p.name }}</div>
                            <div class="product-compact-price">
                                <template v-if="p.sku_price_min !== null && p.sku_price_min !== undefined">
                                    ¥{{ p.sku_price_min }}<template v-if="p.sku_price_max && p.sku_price_max !== p.sku_price_min"> ~ ¥{{ p.sku_price_max }}</template>
                                </template>
                                <template v-else>¥{{ p.base_price || '面议' }}</template>
                            </div>
                        </div>
                        <el-dropdown trigger="click" @command="(cmd) => handleCardAction(cmd, p)">
                            <el-button size="small" type="primary" circle>
                                <el-icon><Plus /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="product">📇 商品卡片</el-dropdown-item>
                                    <el-dropdown-item command="order">🧾 订单卡片</el-dropdown-item>
                                    <el-dropdown-item command="coupon">🎫 优惠券卡片</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </div>
                    <div v-if="!products.length && !productLoading" class="empty-section"><el-empty description="暂无商品" :image-size="40" /></div>
                </div>
                <div v-if="productTotal > products.length" class="product-pagination" style="text-align:center;margin-top:8px">
                    <el-button size="small" text @click="loadMoreProducts(page + 1)">加载更多</el-button>
                </div>
            </el-tab-pane>

            <!-- ── Tab 3: 快捷回复 ── -->
            <el-tab-pane label="💬 回复" name="replies" lazy>
                <div class="reply-toolbar">
                    <el-input v-model="replySearch" placeholder="搜索常用语..." size="small" clearable prefix-icon="Search" @input="filterReplies" />
                    <el-tooltip content="管理快捷回复" placement="top">
                        <el-button size="small" text @click="openReplyManager">管理</el-button>
                    </el-tooltip>
                </div>
                <div class="reply-group-list">
                    <div v-for="group in filteredReplyGroups" :key="group.category" class="reply-group">
                        <div class="reply-group-title" @click="toggleReplyGroup(group.category)">
                            {{ group.category || '通用' }}
                            <el-tag size="small" style="margin-left:4px">{{ group.items.length }}</el-tag>
                        </div>
                        <div v-show="expandedReplyGroups[group.category]" class="reply-items">
                            <div v-for="r in group.items" :key="r.id" class="reply-item" @click="sendQuickReply(r)">
                                <div class="reply-item-text">{{ r.content?.substring(0, 60) }}{{ r.content?.length > 60 ? '...' : '' }}</div>
                                <div class="reply-item-shortcut" v-if="r.shortcuts?.length">[{{ r.shortcuts[0] }}]</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!filteredReplyGroups.length" class="empty-section"><el-empty description="暂无快捷回复" :image-size="40" /></div>
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- ═══════ 底部快捷操作栏 ═══════ -->
        <div class="agent-bottom-actions" v-if="selectedCustomer">
            <div class="bottom-actions-title">当前: {{ selectedCustomerName }}</div>
            <div class="bottom-actions-btns">
                <el-button size="small" @click="showTagDialog = true">🏷️ 标签</el-button>
                <el-button size="small" @click="showTransferDialogForCurrent()">🔄 转接</el-button>
                <el-button size="small" type="primary" @click="showMultiCardDialog = true">📇 发卡片</el-button>
            </div>
        </div>

        <!-- ═══════ 对话框: 发送多类型卡片 ═══════ -->
        <el-dialog v-model="showMultiCardDialog" title="发送卡片" width="420px" :close-on-click-modal="false">
            <el-form label-width="80px">
                <el-form-item label="卡片类型">
                    <el-radio-group v-model="cardType">
                        <el-radio value="product">📇 商品</el-radio>
                        <el-radio value="order">🧾 订单</el-radio>
                        <el-radio value="coupon">🎫 优惠券</el-radio>
                        <el-radio value="custom">📝 自定义</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item v-if="cardType === 'product'" label="商品">
                    <el-select v-model="selectedCardProductId" filterable placeholder="选择商品..." style="width:100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="cardType === 'order'" label="订单号">
                    <el-input v-model="cardOrderNo" placeholder="输入订单号..." />
                </el-form-item>
                <el-form-item v-if="cardType === 'coupon'" label="优惠券">
                    <el-input v-model="cardCouponCode" placeholder="优惠券代码..." />
                </el-form-item>
                <el-form-item v-if="cardType === 'custom'" label="标题">
                    <el-input v-model="customCardTitle" placeholder="卡片标题..." />
                </el-form-item>
                <el-form-item v-if="cardType === 'custom'" label="内容">
                    <el-input v-model="customCardContent" type="textarea" :rows="3" placeholder="卡片内容..." />
                </el-form-item>
                <el-form-item label="目标会话">
                    <el-select v-model="cardTargetConvId" filterable placeholder="选择会话..." style="width:100%">
                        <el-option v-for="c in conversations" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="cardNote" placeholder="可选备注..." maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showMultiCardDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="sendingCard" @click="doSendMultiCard">发送</el-button>
            </template>
        </el-dialog>

        <!-- ═══════ 对话框: 转接 ═══════ -->
        <el-dialog v-model="showTransferDlg" title="转接会话" width="380px">
            <el-form label-width="70px">
                <el-form-item label="转给">
                    <el-select v-model="transferTargetId" filterable placeholder="选择客服..." style="width:100%">
                        <el-option v-for="a in onlineAgents" :key="a.id" :label="a.name + ' (' + a.status + ')'" :value="a.id">
                            <span>{{ a.name }}</span>
                            <span :class="'agent-status-dot ' + (a.status || 'online')" />
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="备注">
                    <el-input v-model="transferNote" type="textarea" :rows="3" placeholder="转接原因..." />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showTransferDlg = false">取消</el-button>
                <el-button size="small" type="primary" :loading="transferring" @click="doTransfer">确认转接</el-button>
            </template>
        </el-dialog>

        <!-- ═══════ 对话框: 打标签 ═══════ -->
        <el-dialog v-model="showTagDialog" title="会话标签" width="360px">
            <el-checkbox-group v-model="selectedTags">
                <el-checkbox v-for="tag in availableTags" :key="tag.id" :label="tag.id" :value="tag.id">
                    <el-tag :color="tag.color || '#909399'" style="color:#fff">{{ tag.name }}</el-tag>
                </el-checkbox>
            </el-checkbox-group>
            <div style="margin-top:12px">
                <el-input v-model="newTagName" placeholder="新标签名..." size="small" style="width:200px" />
                <el-button size="small" @click="createTag" style="margin-left:8px">创建</el-button>
            </div>
            <template #footer>
                <el-button size="small" @click="showTagDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="savingTags" @click="saveTags">保存</el-button>
            </template>
        </el-dialog>

        <!-- ═══════ 对话框: 快捷回复管理 ═══════ -->
        <el-dialog v-model="showReplyManager" title="💬 快捷回复管理" width="480px" :close-on-click-modal="false">
            <div style="margin-bottom:12px;display:flex;gap:6px;align-items:center">
                <el-input v-model="replyMgrSearch" placeholder="搜索..." size="small" clearable prefix-icon="Search" style="flex:1" />
                <el-button size="small" type="primary" @click="openNewReply">新建</el-button>
            </div>
            <div style="max-height:400px;overflow-y:auto">
                <div v-for="r in filteredManagerReplies" :key="r.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f0f0f0">
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
                        <el-button size="small" text @click="editReply(r)">编辑</el-button>
                        <el-button size="small" text type="danger" @click="deleteReply(r)">删除</el-button>
                    </div>
                </div>
                <div v-if="!filteredManagerReplies.length" style="padding:40px 0;text-align:center">
                    <el-empty description="暂无快捷回复" :image-size="40" />
                </div>
            </div>
        </el-dialog>

        <!-- ═══════ 对话框: 编辑快捷回复 ═══════ -->
        <el-dialog v-model="showReplyEditor" :title="editingReply ? '编辑快捷回复' : '新建快捷回复'" width="420px">
            <el-form label-width="70px">
                <el-form-item label="分类">
                    <el-select v-model="replyForm.category" filterable allow-create placeholder="选择或输入分类..." style="width:100%">
                        <el-option label="问候" value="问候" />
                        <el-option label="产品" value="产品" />
                        <el-option label="售后" value="售后" />
                        <el-option label="技术" value="技术" />
                        <el-option label="结束语" value="结束语" />
                        <el-option label="通用" value="通用" />
                    </el-select>
                </el-form-item>
                <el-form-item label="标题">
                    <el-input v-model="replyForm.title" placeholder="快捷回复标题..." maxlength="100" />
                </el-form-item>
                <el-form-item label="内容">
                    <el-input v-model="replyForm.content" type="textarea" :rows="4" placeholder="回复内容..." maxlength="1000" />
                </el-form-item>
                <el-form-item label="快捷词">
                    <el-select v-model="replyForm.shortcuts" multiple filterable allow-create default-first-option
                        placeholder="输入触发词按回车..." style="width:100%" />
                    <div style="font-size:11px;color:#909399;margin-top:4px">输入关键词后按回车添加，用于快速搜索定位</div>
                </el-form-item>
                <el-form-item label="共享">
                    <el-switch v-model="replyForm.is_shared" active-text="全员可见" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showReplyEditor = false">取消</el-button>
                <el-button size="small" type="primary" :loading="savingReply" @click="saveReply">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { Search, Plus, Share } from '@element-plus/icons-vue';
import apiClient from '@/api/client';
import handoffApi from '@/api/handoff';

const emit = defineEmits(['select-conversation']);

// ── 坐席状态 ──
const agentStatus = ref('online');
const stats = reactive({ activeChats: 0, todayTotal: 0, queueTotal: 0, urgentTotal: 0, avgWait: '—', onlineAgents: 0 });

async function updateStatus() {
    try { await handoffApi.updateStatus(agentStatus.value); } catch { /* ignore */ }
}

// ── Tab ──
const activeAgentTab = ref('queue');

// ── 排队队列 ──
const queue = ref([]);
const activeConversations = ref([]);
const loadingQueue = ref(false);

async function loadQueue() {
    loadingQueue.value = true;
    try {
        const [queueRes, convRes, statsRes] = await Promise.allSettled([
            handoffApi.getQueue(),
            handoffApi.myConversations(),
            handoffApi.getQueueStats(),
        ]);
        if (queueRes.status === 'fulfilled') queue.value = queueRes.value.data?.data || [];
        if (convRes.status === 'fulfilled') activeConversations.value = convRes.value.data?.data || [];
        if (statsRes.status === 'fulfilled') {
            const d = statsRes.value.data?.data || {};
            stats.queueTotal = d.total_queued ?? queue.value.length;
            stats.urgentTotal = d.urgent_queued ?? 0;
            stats.avgWait = d.avg_wait_formatted || '—';
            stats.onlineAgents = d.online_agents ?? 0;
            stats.activeChats = d.active ?? activeConversations.value.length;
            stats.todayTotal = d.today_count ?? '--';
        } else {
            stats.queueTotal = queue.value.length;
            stats.activeChats = activeConversations.value.length;
        }
    } catch {
        queue.value = [];
        activeConversations.value = [];
    } finally {
        loadingQueue.value = false;
    }
}

function formatWaitTime(dateStr) {
    if (!dateStr) return '';
    const diff = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return '刚刚';
    if (mins < 60) return `${mins}m`;
    const hours = Math.floor(mins / 60);
    return `${hours}h${mins % 60}m`;
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

function sourceLabel(src) {
    const labels = { portal: '客户门户', widget: '网站挂件', api: 'API 接入', chat: 'IM 聊天', 'live-chat': '在线客服', user_chat: '用户私信' };
    return labels[src] || src || '未知';
}

function queueItemName(item) {
    if (item.live_chat_conversation_id || item.metadata?.source === 'live_chat') {
        return item.user?.name
            || item.live_chat_conversation?.session_id
            || item.conversation_context?.session_id
            || '在线客服访客';
    }
    return item.user?.name || item.customer?.user?.name || item.metadata?.conversation_name || '未知用户';
}

function isLiveChatHandoff(item) {
    return !!(item.live_chat_conversation_id || item.metadata?.source === 'live_chat');
}

function isUserChatHandoff(conv) {
    return !!(conv?.user_conversation_id || conv?.metadata?.source === 'user_chat');
}

const selectedCustomer = ref(null);
const selectedCustomerName = computed(() => selectedCustomer.value?.customer?.user?.name || selectedCustomer.value?.user?.name || selectedCustomer.value?.name || '');

// ── 聊天面板 ──
const activeChatConv = ref(null);
const activeChatConvName = computed(() => activeChatConv.value?.user?.name || activeChatConv.value?.customer?.user?.name || activeChatConv.value?.name || '客户');
const isUserChatHandoffActive = computed(() => isUserChatHandoff(activeChatConv.value));
const dmConversationLink = computed(() => {
    const dmId = activeChatConv.value?.dm_conversation_id || activeChatConv.value?.metadata?.dm_conversation_id;
    return dmId ? { path: '/user-chat', query: { conv: dmId } } : null;
});
const visitorInfo = ref(null);

async function loadVisitorInfo() {
    const conv = activeChatConv.value;
    if (!conv?.id) { visitorInfo.value = null; return; }
    try {
        const res = await handoffApi.visitorInfo(conv.id);
        if (res.data?.success) visitorInfo.value = res.data.data;
    } catch { /* ignore */ }
}

// 监听活跃对话变化，自动加载访客信息
watch(activeChatConv, () => {
    visitorInfo.value = null;
    if (activeChatConv.value?.id) loadVisitorInfo();
});
const chatMessages = ref([]);
const newMessage = ref('');
const loadingMessages = ref(false);
const msgContainer = ref(null);

async function loadChatMessages(convId) {
    if (!convId) return;
    loadingMessages.value = true;
    try {
        const res = await handoffApi.show(convId);
        const data = res.data?.data || {};
        activeChatConv.value = { ...activeChatConv.value, ...data };
        if (isUserChatHandoff(data)) {
            const dmId = data.dm_conversation_id || data.metadata?.dm_conversation_id;
            if (dmId) {
                const dmRes = await apiClient.get(`/user-chat/conversations/${dmId}/messages`, { params: { per_page: 50 } });
                const dmMsgs = dmRes.data?.data || [];
                chatMessages.value = dmMsgs.map(m => ({
                    id: m.id,
                    content: m.content,
                    sender_type: m.sender_id === data.assigned_to ? 'agent' : (m.message_type === 'system' ? 'system' : 'customer'),
                    created_at: m.created_at,
                })).reverse();
            } else {
                chatMessages.value = [];
            }
        } else {
            chatMessages.value = data.messages || [];
        }
        await nextTick();
        if (msgContainer.value) msgContainer.value.scrollTop = msgContainer.value.scrollHeight;
    } catch {
        chatMessages.value = [];
    } finally {
        loadingMessages.value = false;
    }
}

async function sendChatMessage() {
    const content = newMessage.value.trim();
    if (!content || !activeChatConv.value?.id) return;
    try {
        await handoffApi.agentSend(activeChatConv.value.id, content);
        newMessage.value = '';
        if (isUserChatHandoffActive.value) {
            ElMessage.success('已发送至用户私信');
        }
        await loadChatMessages(activeChatConv.value.id);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败');
    }
}

async function handleCloseConv() {
    if (!activeChatConv.value?.id) return;
    try {
        await handoffApi.close(activeChatConv.value.id);
        ElMessage.success('会话已关闭');
        activeChatConv.value = null;
        chatMessages.value = [];
        await loadQueue();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '关闭失败');
    }
}

async function handleAcceptConv() {
    if (!activeChatConv.value?.id) return;
    try {
        await handoffApi.accept(activeChatConv.value.id);
        ElMessage.success('已接受');
        await loadChatMessages(activeChatConv.value.id);
        activeChatConv.value.status = 'in_progress';
        await loadQueue();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '接受失败');
    }
}


function selectQueueItem(item) {
    selectedCustomer.value = item;
    activeChatConv.value = item;
    loadChatMessages(item.id);
    emit('select-conversation', {
        id: 'handoff_' + item.id,
        is_handoff: true,
        handoff_id: item.id,
        name: item.customer?.user?.name || '用户',
        customer: item.customer,
        messages: [],
    });
}

async function acceptQueueItem(item) {
    try {
        await handoffApi.accept(item.id);
        ElMessage.success('已接入');
        await loadQueue();
        selectQueueItem(item);
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '接入失败');
    }
}

function selectActiveConv(conv) {
    selectedCustomer.value = conv;
    activeChatConv.value = conv;
    loadChatMessages(conv.id);
    emit('select-conversation', {
        id: 'handoff_' + conv.id,
        is_handoff: true,
        handoff_id: conv.id,
        name: conv.customer?.user?.name || '会话',
        customer: conv.customer,
        messages: conv.messages || [],
    });
}

// ── 商品浏览 ──
const productSearch = ref('');
const products = ref([]);
const productLoading = ref(false);
const productTotal = ref(0);
const page = ref(1);
const perPage = ref(20);

async function searchProducts() {
    page.value = 1;
    await loadProducts();
}

async function loadProducts() {
    productLoading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (productSearch.value.trim()) params.search = productSearch.value.trim();
        const res = await apiClient.get('/products', { params });
        const data = res.data?.data || {};
        const items = Array.isArray(data) ? data : (data.data || []);
        products.value = page.value === 1 ? items : [...products.value, ...items];
        productTotal.value = res.data?.meta?.total || data.total || 0;
    } catch {
        products.value = [];
    } finally {
        productLoading.value = false;
    }
}

function loadMoreProducts(newPage) {
    page.value = newPage;
    loadProducts();
}

// ── 多卡片发送 ──
const showMultiCardDialog = ref(false);
const cardType = ref('product');
const selectedCardProductId = ref(null);
const cardOrderNo = ref('');
const cardCouponCode = ref('');
const customCardTitle = ref('');
const customCardContent = ref('');
const cardTargetConvId = ref(null);
const cardNote = ref('');
const conversations = ref([]);
const sendingCard = ref(false);

function handleCardAction(cmd, product) {
    selectedCardProductId.value = product.id;
    cardType.value = cmd;
    showMultiCardDialog.value = true;
    loadConversations();
}

async function loadConversations() {
    try {
        const res = await apiClient.get('/user-chat/conversations');
        conversations.value = res.data?.data || [];
    } catch { conversations.value = []; }
}

async function doSendMultiCard() {
    if (!cardTargetConvId.value) {
        ElMessage.warning('请选择目标会话');
        return;
    }
    sendingCard.value = true;
    const trace_id = crypto.randomUUID?.() || Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    try {
        let endpoint = '';
        let payload = { trace_id, content: cardNote.value || undefined };
        if (cardType.value === 'product') {
            if (!selectedCardProductId.value) { ElMessage.warning('请选择商品'); return; }
            endpoint = '/user-chat/conversations/' + cardTargetConvId.value + '/send-product-card';
            payload.product_id = selectedCardProductId.value;
        } else if (cardType.value === 'order') {
            if (!cardOrderNo.value) { ElMessage.warning('请输入订单号'); return; }
            endpoint = '/user-chat/conversations/' + cardTargetConvId.value + '/send-order-card';
            payload.order_no = cardOrderNo.value;
        } else if (cardType.value === 'coupon') {
            endpoint = '/user-chat/conversations/' + cardTargetConvId.value + '/send-coupon-card';
            payload.coupon_code = cardCouponCode.value || undefined;
        } else {
            if (!customCardTitle.value) { ElMessage.warning('请输入卡片标题'); return; }
            endpoint = '/user-chat/conversations/' + cardTargetConvId.value + '/send-custom-card';
            payload.title = customCardTitle.value;
            payload.content = customCardContent.value || undefined;
        }
        await apiClient.post(endpoint, payload);
        ElMessage.success('卡片已发送');
        showMultiCardDialog.value = false;
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '发送失败');
    } finally {
        sendingCard.value = false;
    }
}

// ── 快捷回复 ──
const replySearch = ref('');
const replies = ref([]);
const expandedReplyGroups = reactive({});

const replyGroups = computed(() => {
    const groups = {};
    for (const r of replies.value) {
        const cat = r.category || '通用';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(r);
    }
    return Object.entries(groups).map(([category, items]) => ({ category, items }));
});

const filteredReplyGroups = computed(() => {
    const q = replySearch.value.toLowerCase().trim();
    if (!q) return replyGroups.value;
    return replyGroups.value.map(g => ({
        ...g,
        items: g.items.filter(r =>
            (r.content || '').toLowerCase().includes(q) ||
            (r.shortcut || '').toLowerCase().includes(q)
        ),
    })).filter(g => g.items.length > 0);
});

function toggleReplyGroup(category) {
    expandedReplyGroups[category] = !expandedReplyGroups[category];
}

function filterReplies() {
    // computed handles it
}

async function loadReplies() {
    try {
        const res = await apiClient.get('/im/canned-replies');
        replies.value = res.data?.data || [];
        if (replyGroups.value.length > 0) {
            expandedReplyGroups[replyGroups.value[0].category] = true;
        }
    } catch { replies.value = []; }
}

async function sendQuickReply(reply) {
    const convId = selectedCustomer.value?.handoff_id || activeConversations.value[0]?.id;
    if (!convId) {
        ElMessage.warning('请先选择一个会话');
        return;
    }
    try {
        await handoffApi.agentSend(convId, reply.content);
        ElMessage.success('快捷回复已发送');
    } catch {
        ElMessage.error('发送失败');
    }
}

// ── 转接 ──
const showTransferDlg = ref(false);
const transferTargetId = ref(null);
const transferNote = ref('');
const transferConvId = ref(null);
const onlineAgents = ref([]);
const transferring = ref(false);

function showTransferDialog(conv) {
    transferConvId.value = conv.id;
    transferTargetId.value = null;
    transferNote.value = '';
    showTransferDlg.value = true;
    loadOnlineAgents();
}

function showTransferDialogForCurrent() {
    if (!selectedCustomer.value) { ElMessage.warning('请先选择一个会话'); return; }
    showTransferDialog(selectedCustomer.value);
}

async function loadOnlineAgents() {
    try {
        const res = await handoffApi.onlineAgents();
        onlineAgents.value = res.data?.data || [];
    } catch { onlineAgents.value = []; }
}

async function doTransfer() {
    if (!transferTargetId.value) { ElMessage.warning('请选择目标客服'); return; }
    transferring.value = true;
    try {
        await handoffApi.transfer(transferConvId.value, transferTargetId.value, transferNote.value || undefined);
        ElMessage.success('已转接');
        showTransferDlg.value = false;
        await loadQueue();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '转接失败');
    } finally {
        transferring.value = false;
    }
}

// ── 标签 ──
const showTagDialog = ref(false);
const availableTags = ref([]);
const selectedTags = ref([]);
const newTagName = ref('');
const savingTags = ref(false);

async function loadTags() {
    try {
        const res = await apiClient.get('/im/tags');
        availableTags.value = res.data?.data || [];
    } catch { availableTags.value = []; }
}

async function createTag() {
    if (!newTagName.value.trim()) return;
    try {
        const res = await apiClient.post('/im/tags', { name: newTagName.value.trim() });
        availableTags.value.push(res.data?.data || { id: Date.now(), name: newTagName.value.trim() });
        newTagName.value = '';
        ElMessage.success('标签已创建');
    } catch (e) { ElMessage.error(e.response?.data?.message || '创建失败'); }
}

async function saveTags() {
    const convId = selectedCustomer.value?.handoff_id;
    if (!convId) { ElMessage.warning('请先选择会话'); return; }
    savingTags.value = true;
    try {
        await apiClient.post('/im/tags/assign', {
            handoff_id: convId,
            tag_ids: selectedTags.value,
        });
        ElMessage.success('标签已保存');
        showTagDialog.value = false;
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败'); }
    finally { savingTags.value = false; }
}

// ── 快捷回复管理 ──
const showReplyManager = ref(false);
const showReplyEditor = ref(false);
const replyMgrSearch = ref('');
const editingReply = ref(null);
const savingReply = ref(false);
const replyForm = reactive({
    category: '',
    title: '',
    content: '',
    shortcuts: [],
    is_shared: true,
});

const filteredManagerReplies = computed(() => {
    const q = replyMgrSearch.value.toLowerCase().trim();
    if (!q) return replies.value;
    return replies.value.filter(r =>
        (r.title || '').toLowerCase().includes(q) ||
        (r.content || '').toLowerCase().includes(q) ||
        (r.category || '').toLowerCase().includes(q)
    );
});

function openReplyManager() {
    showReplyManager.value = true;
    loadReplies();
}

function openNewReply() {
    editingReply.value = null;
    replyForm.category = '';
    replyForm.title = '';
    replyForm.content = '';
    replyForm.shortcuts = [];
    replyForm.is_shared = true;
    showReplyEditor.value = true;
}

function editReply(reply) {
    editingReply.value = reply;
    replyForm.category = reply.category || '';
    replyForm.title = reply.title || '';
    replyForm.content = reply.content || '';
    replyForm.shortcuts = Array.isArray(reply.shortcuts) ? [...reply.shortcuts] : [];
    replyForm.is_shared = reply.is_shared !== false;
    showReplyEditor.value = true;
}

async function saveReply() {
    if (!replyForm.title.trim() || !replyForm.content.trim()) {
        ElMessage.warning('标题和内容不能为空');
        return;
    }
    savingReply.value = true;
    try {
        const payload = {
            category: replyForm.category || undefined,
            title: replyForm.title.trim(),
            content: replyForm.content.trim(),
            shortcuts: replyForm.shortcuts.length ? replyForm.shortcuts : undefined,
            is_shared: replyForm.is_shared,
        };
        if (editingReply.value) {
            await apiClient.put('/im/canned-replies/' + editingReply.value.id, payload);
            ElMessage.success('已更新');
        } else {
            await apiClient.post('/im/canned-replies', payload);
            ElMessage.success('已创建');
        }
        showReplyEditor.value = false;
        await loadReplies();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        savingReply.value = false;
    }
}

async function deleteReply(reply) {
    try {
        await apiClient.delete('/im/canned-replies/' + reply.id);
        ElMessage.success('已删除');
        await loadReplies();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '删除失败');
    }
}

// ── 后台刷新 ──
let pollTimer = null;
function startPolling() {
    stopPolling();
    pollTimer = setInterval(() => {
        if (activeAgentTab.value === 'queue') loadQueue();
    }, 15000);
}
function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

function onTabChange(tab) {
    if (tab === 'replies' && replies.value.length === 0) loadReplies();
}

// ── 加载统计 ──
async function loadStats() {
    try {
        const res = await apiClient.get('/im/dashboard');
        const d = res.data?.data || {};
        stats.activeChats = d.active_conversations ?? '--';
        stats.todayTotal = d.today_count ?? '--';
        stats.satisfaction = d.satisfaction_rate ?? '--';
    } catch { /* ignore */ }
}

onMounted(() => {
    loadStats();
    loadQueue();
    loadProducts();
    loadTags();
    startPolling();
});
</script>

<style scoped>
.agent-workspace {
    padding: 8px;
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.agent-stats-row { flex-shrink: 0; }
.stat-card { border-radius: 6px; }
.stat-mini { display:flex; align-items:center; justify-content:center; gap:4px; }
.stat-label { font-size: 11px; color: #909399; }
.stat-value-mini { font-size: 20px; font-weight: 700; color: #1a1a1a; text-align:center; }
.stat-value-mini.status-online { color: #67c23a; }
.stat-value-mini.status-busy { color: #e6a23c; }
.stat-value-mini.status-away { color: #909399; }

.agent-tabs { flex: 1; overflow: hidden; display: flex; flex-direction: column; }
.agent-tabs :deep(.el-tabs__content) { flex: 1; overflow: auto; }
.agent-tabs :deep(.el-tab-pane) { height: 100%; overflow-y: auto; }
.agent-tabs :deep(.el-tabs__nav-wrap) { margin-bottom: 0; }
.agent-tabs :deep(.el-tabs__item) { font-size: 12px; padding: 0 8px; }

/* Queue */
.queue-section { margin-bottom: 4px; }
.queue-section-title { font-size: 12px; font-weight: 600; color: #606266; margin-bottom: 6px; display:flex; align-items:center; gap:4px; }
.queue-item {
    display: flex; align-items: center; gap: 8px; padding: 8px 6px;
    border-radius: 6px; cursor: pointer; transition: background .2s;
    border-bottom: 1px solid #f0f0f0;
}
.queue-item:hover { background: #f5f7fa; }
.queue-item:last-child { border-bottom: none; }
.queue-item.priority-urgent { border-left: 3px solid #f56c6c; }
.queue-item.priority-high { border-left: 3px solid #e6a23c; }
.queue-item.priority-medium { border-left: 3px solid #409eff; }
.queue-item.priority-normal { border-left: 3px solid #c0c4cc; }
.queue-item.active-conv { border-left-color: #67c23a; }
.queue-item-left { flex-shrink: 0; }
.queue-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: #e6a23c; color: #fff; display: flex;
    align-items: center; justify-content: center; font-weight: 700; font-size: 14px;
}
.queue-item-body { flex: 1; min-width: 0; }
.queue-item-name { font-size: 13px; font-weight: 500; color: #303133; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.queue-item-meta { display: flex; align-items: center; gap: 6px; margin-top: 2px; }
.queue-priority { font-size: 10px; padding: 1px 4px; border-radius: 3px; }
.badge-urgent { background: #fef0f0; color: #f56c6c; }
.badge-high { background: #fdf6ec; color: #e6a23c; }
.badge-medium { background: #ecf5ff; color: #409eff; }
.badge-normal { background: #f4f4f5; color: #909399; }
.queue-time { font-size: 11px; color: #909399; }
.status-dot { width: 6px; height: 6px; border-radius: 50%; background: #c0c4cc; display: inline-block; }
.status-dot.active { background: #67c23a; }
.queue-loading { min-height: 60px; }
.empty-section { padding: 20px 0; }

/* Chat Panel Layout */
.queue-layout { display: flex; gap: 12px; height: 100%; }
.queue-left { flex: 1; overflow-y: auto; min-width: 280px; }
.queue-left.with-chat { flex: 0 0 320px; }
.chat-panel { flex: 1; display: flex; flex-direction: column; border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden; background: #fff; }
.chat-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid #ebeef5; background: #f5f7fa; }
.chat-panel-user { display: flex; align-items: center; gap: 8px; font-size: 14px; }
.chat-panel-actions { display: flex; gap: 4px; }
.chat-context { padding: 8px 12px; border-bottom: 1px solid #ebeef5; }
.chat-context :deep(.el-collapse-item__header) { font-size: 12px; }
.context-json { font-size: 11px; max-height: 120px; overflow-y: auto; background: #f5f7fa; padding: 8px; border-radius: 4px; }
.chat-messages { flex: 1; overflow-y: auto; padding: 12px; }
.chat-msg { margin-bottom: 12px; }
.chat-msg.msg-customer { text-align: left; }
.chat-msg.msg-agent { text-align: right; }
.chat-msg.msg-system { text-align: center; }
.msg-sender { font-size: 11px; color: #909399; margin-bottom: 4px; }
.msg-time { margin-left: 6px; }
.msg-bubble { display: inline-block; max-width: 80%; padding: 8px 12px; border-radius: 12px; font-size: 13px; line-height: 1.5; word-break: break-word; text-align: left; }
.msg-customer .msg-bubble { background: #ecf5ff; color: #303133; border-bottom-left-radius: 4px; }
.msg-agent .msg-bubble { background: #67c23a; color: #fff; border-bottom-right-radius: 4px; }
.msg-system .msg-bubble { background: #f5f7fa; color: #909399; font-size: 11px; }
.chat-loading, .chat-empty { text-align: center; color: #909399; padding: 40px 0; font-size: 13px; }
.chat-input-area { display: flex; gap: 8px; padding: 10px 12px; border-top: 1px solid #ebeef5; }
.chat-input-area .el-textarea { flex: 1; }
.send-btn { align-self: flex-end; }
.is-selected { background: #ecf5ff; }

/* Products */
.product-search-bar { display: flex; gap: 6px; margin-bottom: 8px; }
.product-compact-list { display: flex; flex-direction: column; gap: 4px; }
.product-compact-item {
    display: flex; align-items: center; gap: 8px; padding: 6px;
    border-radius: 6px; border: 1px solid #ebeef5;
}
.product-compact-img {
    width: 40px; height: 40px; border-radius: 4px; overflow: hidden;
    background: #f5f7fa; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
}
.product-compact-img img { width: 100%; height: 100%; object-fit: cover; }
.product-compact-info { flex: 1; min-width: 0; }
.product-compact-name { font-size: 12px; font-weight: 500; color: #303133; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-compact-price { font-size: 11px; color: #f56c6c; font-weight: 600; }

/* Replies */
.reply-search-bar { margin-bottom: 8px; }
.reply-toolbar { display: flex; gap: 6px; margin-bottom: 8px; }
.reply-toolbar .el-input { flex: 1; }
.reply-group-list { display: flex; flex-direction: column; gap: 4px; }
.reply-group { border: 1px solid #ebeef5; border-radius: 6px; overflow: hidden; }
.reply-group-title {
    padding: 6px 10px; font-size: 12px; font-weight: 600; cursor: pointer;
    background: #fafafa; display: flex; align-items: center;
}

/* 🌐 访客信息 */
.chat-visitor { padding: 4px 12px; border-bottom: 1px solid #ebeef5; }
.chat-visitor :deep(.el-collapse-item__header) { font-size: 12px; }
.chat-visitor :deep(.el-collapse-item__content) { padding-bottom: 8px; }
.visitor-grid { display: flex; flex-direction: column; gap: 6px; }
.visitor-item { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; }
.v-label { color: #909399; flex-shrink: 0; min-width: 56px; }
.v-value { color: #303133; word-break: break-all; }
.v-url { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; }
.reply-group-title:hover { background: #f0f0f0; }
.reply-items { border-top: 1px solid #ebeef5; }
.reply-item {
    padding: 6px 10px; cursor: pointer; font-size: 12px; color: #606266;
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 1px solid #f5f5f5;
}
.reply-item:last-child { border-bottom: none; }
.reply-item:hover { background: #ecf5ff; }
.reply-item-text { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.reply-item-shortcut { font-size: 10px; color: #909399; background: #f5f7fa; padding: 1px 4px; border-radius: 3px; margin-left: 4px; flex-shrink: 0; }

/* Bottom actions */
.agent-bottom-actions {
    flex-shrink: 0; border-top: 1px solid #ebeef5; padding-top: 8px;
}
.bottom-actions-title { font-size: 12px; color: #606266; font-weight: 500; margin-bottom: 6px; }
.bottom-actions-btns { display: flex; gap: 4px; flex-wrap: wrap; }

/* Dialog overrides */
:deep(.el-dialog__body) { padding: 16px; }
:deep(.el-form-item) { margin-bottom: 12px; }
</style>
