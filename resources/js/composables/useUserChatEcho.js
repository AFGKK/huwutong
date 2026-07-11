import apiClient from '@/api/client'



/**

 * 私信 WebSocket：消息、正在输入、通知推送、来电

 */

export function useUserChatEcho({

    myId,

    activeConv,

    messages,

    conversations,

    scrollToBottom,

    typingUsers,

    notifications,

    onIncomingCall,

}) {

    let chatChannel = null

    let notifChannel = null

    const typingClearTimers = {}

    let lastTypingSentAt = 0

    let incomingPollInterval = null

    const handledCallIds = new Set()



    async function markDeliveredBatch(messageIds) {

        if (!messageIds?.length) return

        try {

            await apiClient.post('/user-chat/messages/delivered', { message_ids: messageIds })

        } catch { /* ignore */ }

    }



    function bumpConversation(payload) {

        const conv = conversations.value.find(c => c.id === payload.conversation_id)

        if (!conv) return



        conv.last_message = {

            content: payload.content,

            sender_name: payload.sender_name,

            created_at: payload.created_at,

        }

        conv.updated_at = payload.created_at



        if (payload.sender_id !== myId.value && activeConv.value?.id !== payload.conversation_id) {

            conv.unread_count = (conv.unread_count || 0) + 1

        }

    }



    function appendIncomingMessage(payload) {

        if (activeConv.value?.id !== payload.conversation_id) return



        if (messages.value.some(m => m.id === payload.id)) return



        messages.value.push({

            ...payload,

            deliver_status: 'delivered',

            _expanded: false,

        })

        scrollToBottom?.()



        if (payload.sender_id !== myId.value && payload.id) {

            markDeliveredBatch([payload.id])

        }

    }



    function handleTypingEvent(payload) {

        if (!typingUsers?.value || !payload?.user_name) return

        if (activeConv.value?.id !== payload.conversation_id) return

        if (payload.user_id === myId.value) return



        const key = String(payload.user_id)

        if (payload.is_typing) {

            if (!typingUsers.value.includes(payload.user_name)) {

                typingUsers.value = [...typingUsers.value, payload.user_name]

            }

            clearTimeout(typingClearTimers[key])

            typingClearTimers[key] = setTimeout(() => {

                typingUsers.value = typingUsers.value.filter(name => name !== payload.user_name)

                delete typingClearTimers[key]

            }, 4000)

        } else {

            typingUsers.value = typingUsers.value.filter(name => name !== payload.user_name)

            clearTimeout(typingClearTimers[key])

            delete typingClearTimers[key]

        }

    }



    function appendNotification(payload) {

        if (!notifications?.value || !payload?.id) return

        if (notifications.value.some(n => n.id === payload.id)) return

        notifications.value.unshift({

            id: payload.id,

            type: payload.type,

            title: payload.title,

            content: payload.content,

            payload: payload.payload,

            is_read: false,

            created_at: payload.created_at,

        })

    }



    function handleIncomingCallEvent(payload) {

        if (!onIncomingCall || !payload?.call_id) return

        if (handledCallIds.has(payload.call_id)) return

        handledCallIds.add(payload.call_id)

        onIncomingCall(payload)

    }



    function startIncomingPoll() {

        if (!onIncomingCall || incomingPollInterval) return

        incomingPollInterval = setInterval(async () => {

            try {

                const res = await apiClient.get('/calls/incoming')

                const data = res.data?.data

                if (data?.call_id) handleIncomingCallEvent(data)

            } catch { /* ignore */ }

        }, 3000)

    }



    function stopIncomingPoll() {

        if (incomingPollInterval) {

            clearInterval(incomingPollInterval)

            incomingPollInterval = null

        }

    }



    async function pulseTyping() {

        const convId = activeConv.value?.id

        if (!convId) return

        const now = Date.now()

        if (now - lastTypingSentAt < 2000) return

        lastTypingSentAt = now

        try {

            await apiClient.post(`/user-chat/conversations/${convId}/typing`)

        } catch { /* ignore */ }

    }



    function initEcho() {

        const userId = myId.value

        if (!userId) return



        teardownEcho()



        if (onIncomingCall) {

            startIncomingPoll()

        }



        if (typeof window.Echo === 'undefined' || !window.Echo) return



        chatChannel = window.Echo.private(`chat.${userId}`)

        chatChannel.listen('.chat.message', (payload) => {

            bumpConversation(payload)

            appendIncomingMessage(payload)

        })

        if (typingUsers) {

            chatChannel.listen('.chat.typing', handleTypingEvent)

        }

        if (onIncomingCall) {

            chatChannel.listen('.call.incoming', handleIncomingCallEvent)

        }



        if (notifications) {

            notifChannel = window.Echo.private(`App.Models.User.${userId}`)

            notifChannel.listen('.notification.created', appendNotification)

        }

    }



    function clearTypingTimers() {

        Object.values(typingClearTimers).forEach(timer => clearTimeout(timer))

        Object.keys(typingClearTimers).forEach(key => delete typingClearTimers[key])

    }



    function teardownEcho() {

        const userId = myId.value

        clearTypingTimers()

        stopIncomingPoll()

        handledCallIds.clear()

        if (userId && typeof window.Echo !== 'undefined' && window.Echo) {

            window.Echo.leave(`chat.${userId}`)

            if (notifChannel) {

                window.Echo.leave(`App.Models.User.${userId}`)

            }

        }

        chatChannel = null

        notifChannel = null

    }



    return { initEcho, teardownEcho, markDeliveredBatch, pulseTyping }

}


