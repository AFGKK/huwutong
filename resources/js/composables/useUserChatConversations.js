import { ElMessage } from 'element-plus'
import apiClient from '@/api/client'

/**
 * 私信会话：列表加载、选中、创建私聊
 */
export function useUserChatConversations({
    conversations,
    activeConv,
    messages,
    hasMore,
    sidebarTab,
    myId,
    showTagPanel,
    userRoleInGroup,
    showNewChat,
    newChatUserIds,
    loadMessages,
    loadConvTags,
    loadPinnedMessages,
}) {
    async function selectConversation(conv) {
        activeConv.value = conv
        messages.value = []
        hasMore.value = false
        showTagPanel.value = false
        if (conv.type === 'group') userRoleInGroup.value = conv.my_role || 'member'
        else userRoleInGroup.value = 'member'
        await loadMessages()
        try {
            await apiClient.post('/user-chat/conversations/' + conv.id + '/read')
        } catch { /* ignore */ }
        loadConvTags(conv.id)
        loadPinnedMessages()
    }

    async function loadConversations() {
        try {
            const res = await apiClient.get('/user-chat/conversations')
            conversations.value = res.data?.data || []
        } catch { /* ignore */ }
    }

    async function ensureConversationInList(conv) {
        const exists = conversations.value.find(c => c.id === conv.id)
        if (!exists) conversations.value.unshift(conv)
    }

    async function startChatWithUser(userId, options = {}) {
        if (!userId || userId === myId.value) return
        if (options.productId) {
            return startSellerInquiry(userId, options.productId, options)
        }
        try {
            const res = await apiClient.post('/user-chat/conversations', { participant_ids: [userId] })
            const conv = res.data?.data
            if (!conv) return
            sidebarTab.value = 'messages'
            await ensureConversationInList(conv)
            await selectConversation(conv)
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '创建会话失败')
        }
    }

    async function startFriendChat(f) {
        try {
            const res = await apiClient.post('/user-chat/conversations', { participant_ids: [f.id] })
            const conv = res.data?.data
            if (conv) {
                sidebarTab.value = 'messages'
                await ensureConversationInList(conv)
                await selectConversation(conv)
            }
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '创建会话失败')
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
                await ensureConversationInList(conv)
                await selectConversation(conv)
                if (options.onProductContext) options.onProductContext(productId)
                if (options.prefillInput) {
                    options.prefillInput('你好，我想了解一下这款商品')
                }
                ElMessage.success('已向卖家发送商品咨询')
                return conv
            }

            const res = await apiClient.post('/user-chat/conversations', { participant_ids: [sellerId] })
            const conv = res.data?.data
            if (!conv) return null
            sidebarTab.value = 'messages'
            await ensureConversationInList(conv)
            await selectConversation(conv)
            if (options.prefillInput) {
                options.prefillInput('你好，我想咨询一下')
            }
            return conv
        } catch (e) {
            ElMessage.error(e.response?.data?.message || e.response?.data?.error?.message || '打开卖家私信失败')
            return null
        }
    }

    async function createNewChat() {
        if (!newChatUserIds.value.length) return
        try {
            const res = await apiClient.post('/user-chat/conversations', { participant_ids: newChatUserIds.value })
            const conv = res.data?.data
            if (conv) {
                await selectConversation(conv)
                await loadConversations()
                showNewChat.value = false
                newChatUserIds.value = []
            }
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '创建失败')
        }
    }

    return {
        selectConversation,
        loadConversations,
        startChatWithUser,
        startSellerInquiry,
        startFriendChat,
        createNewChat,
    }
}
