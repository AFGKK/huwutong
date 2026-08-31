import { nextTick } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/api/client'
import i18n from '@/i18n'

const DM_TEXT_MAX_LENGTH = 2000
const t = (key, params) => i18n.global.t('user_chat_page.' + key, params)
const apiZh = (key) => i18n.global.t('user_chat_page.' + key, {}, { locale: 'zh_CN' })

function parseMessagePage(res) {
    const body = res.data || {}
    const items = Array.isArray(body.data) ? body.data : (body.data?.items || body.data?.data || [])
    const total = body.meta?.total ?? items.length
    return { items, total }
}

/**
 * 私信消息：加载、发送、撤回、滚动
 */
export function useUserChatMessages({
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
}) {
    async function loadMessages() {
        if (!activeConv.value?.id) return
        try {
            const res = await apiClient.get('/user-chat/conversations/' + activeConv.value.id + '/messages')
            const { items, total } = parseMessagePage(res)
            for (const msg of items) {
                msg._expanded = false
            }
            messages.value = items.reverse()
            hasMore.value = total > messages.value.length

            const deliverFn = typeof markDeliveredBatch === 'function' ? markDeliveredBatch : null
            if (deliverFn) {
                const toDeliver = messages.value
                    .filter(m => m.sender_id !== myId.value && m.deliver_status === 'sent' && m.id)
                    .map(m => m.id)
                if (toDeliver.length) {
                    await deliverFn(toDeliver)
                    messages.value.forEach(m => {
                        if (toDeliver.includes(m.id)) m.deliver_status = 'delivered'
                    })
                }
            }

            await nextTick()
            scrollToBottom()
            if (typeof fetchLinkPreview === 'function') {
                messages.value.forEach(msg => fetchLinkPreview(msg))
            }
        } catch (e) {
            console.error('loadMessages failed', e)
            ElMessage.error(t('msg.load_failed'))
        }
    }

    async function loadMore() {
        if (!messages.value.length || !activeConv.value?.id) return
        const firstId = messages.value[0].id
        try {
            const res = await apiClient.get('/user-chat/conversations/' + activeConv.value.id + '/messages', { params: { before_id: firstId } })
            const { items, total } = parseMessagePage(res)
            items.forEach(msg => {
                msg._expanded = false
            })
            messages.value = [...items.reverse(), ...messages.value]
            hasMore.value = total > messages.value.length
            if (typeof fetchLinkPreview === 'function') {
                items.forEach(msg => fetchLinkPreview(msg))
            }
        } catch (e) {
            console.error('loadMore failed', e)
        }
    }

    function getSendConfirmationMsg(content, conv) {
        const warnings = []
        const atAll = apiZh('api.at_all')
        if (new RegExp(`@(all|everyone|${atAll})`, 'i').test(content)) {
            if (conv.type === 'group') {
                warnings.push(t('msg.warn_at_all'))
            }
        }
        const urlRegex = /https?:\/\/([^\s\/]+)/gi
        let match
        while ((match = urlRegex.exec(content)) !== null) {
            const domain = match[1].toLowerCase()
            if (!domain.includes(window.location.hostname) && !domain.includes('huwutong.com')) {
                warnings.push(t('msg.warn_external_link', { url: match[0].substring(0, 50) }))
                break
            }
        }
        if (pendingAttachments.value.length >= 5) {
            warnings.push(t('msg.warn_many_files', { n: pendingAttachments.value.length }))
        }
        return warnings.length ? warnings.join('<br>') : null
    }

    async function sendMessage() {
        if (!activeConv.value) return
        const content = inputMessage.value.trim()
        if (!content && !pendingAttachments.value.length) return

        if (content.length > DM_TEXT_MAX_LENGTH) {
            ElMessage.warning(t('msg.too_long', { n: DM_TEXT_MAX_LENGTH }))
            return
        }

        const confirmMsg = getSendConfirmationMsg(content, activeConv.value)
        if (confirmMsg) {
            try {
                await ElMessageBox.confirm(confirmMsg, t('msg.send_confirm_title'), {
                    confirmButtonText: t('msg.send_confirm_ok'),
                    cancelButtonText: t('msg.send_confirm_cancel'),
                    type: 'warning',
                    dangerouslyUseHTMLString: true,
                })
            } catch { return }
        }

        let reviewOverride = false
        if (content && !pendingAttachments.value.length) {
            try {
                const reviewRes = await apiClient.post('/user-chat/pre-review', { content })
                const review = reviewRes.data?.data
                if (review?.has_issue || review?.warnings?.length) {
                    const warningHtml = (review.warnings || []).map(w => `⚠️ ${w}`).join('<br>')
                    try {
                        await ElMessageBox.confirm(
                            t('msg.pre_review_body', { warnings: warningHtml }),
                            t('msg.pre_review_title'),
                            {
                                confirmButtonText: t('msg.pre_review_ok'),
                                cancelButtonText: t('msg.pre_review_cancel'),
                                type: 'warning',
                                dangerouslyUseHTMLString: true,
                                distinguishCancelAndClose: true,
                            }
                        )
                        reviewOverride = true
                    } catch { return }
                }
            } catch { /* 预审失败静默降级 */ }
        }

        sending.value = true
        try {
            const payload = {
                content: content || t('msg.file_placeholder'),
                message_type: 'text',
                reply_to_id: replyToMsg.value?.id || undefined,
                client_msg_id: crypto.randomUUID?.() || Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10),
                attachments: pendingAttachments.value.length ? pendingAttachments.value : undefined,
            }
            if (sellerInquiryProductId.value) payload.product_id = sellerInquiryProductId.value
            if (confirmMsg) payload.confirmed = true
            if (reviewOverride) payload.review_override = true
            await apiClient.post('/user-chat/conversations/' + activeConv.value.id + '/messages', payload)
            inputMessage.value = ''
            pendingAttachments.value = []
            replyToMsg.value = null
            await loadMessages()
            if (typeof loadConversations === 'function') await loadConversations()
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t('msg.send_failed'))
        } finally {
            sending.value = false
        }
    }

    async function recallMessage(msg) {
        try {
            await apiClient.post('/user-chat/messages/' + msg.id + '/recall')
            msg.is_recalled = true
            msg.content = null
            ElMessage.success(t('msg.recalled'))
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t('msg.recall_failed'))
        }
    }

    function scrollToBottom() {
        nextTick(() => {
            const el = msgAreaRef.value
            if (el) el.scrollTop = el.scrollHeight
        })
    }

    function onScrollTop(e) {
        if (e.target.scrollTop === 0 && hasMore.value) loadMore()
    }

    return {
        loadMessages,
        loadMore,
        sendMessage,
        recallMessage,
        scrollToBottom,
        onScrollTop,
    }
}
