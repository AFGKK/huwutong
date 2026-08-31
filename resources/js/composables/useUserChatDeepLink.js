import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const SELLER_INQUIRY_KEY = 'im_seller_inquiry_sent'

/**
 * IM 深链接：account_id 跳转互物号管理，seller_id+product_id 打开卖家私信，
 * user_id 打开社区私信，conv 打开指定会话
 */
export function useUserChatDeepLink({
    startSellerInquiry,
    startChatWithUser,
    selectConversation,
    conversations,
    loadConversations,
}) {
    const route = useRoute()
    const router = useRouter()

    function clearQueryKeys(...keys) {
        const nextQuery = { ...route.query }
        keys.forEach((k) => { delete nextQuery[k] })
        router.replace({ path: route.path, query: nextQuery })
    }

    function clearSellerQuery() {
        clearQueryKeys('seller_id', 'product_id')
    }

    async function handleDeepLink() {
        if (route.query.account_id) {
            const id = Number(route.query.account_id)
            if (id) {
                window.location.replace(`/build/channels?account_id=${id}&tab=manage`)
                return true
            }
        }

        const convId = Number(route.query.conv || 0)
        if (convId && selectConversation) {
            if (loadConversations) await loadConversations()
            const conv = conversations?.value?.find(c => c.id === convId)
            if (conv) {
                await selectConversation(conv)
                clearQueryKeys('conv')
                return false
            }
        }

        const userId = Number(route.query.user_id || 0)
        if (userId && typeof startChatWithUser === 'function') {
            await startChatWithUser(userId)
            clearQueryKeys('user_id')
            return false
        }

        const sellerId = Number(route.query.seller_id || 0)
        const productId = Number(route.query.product_id || 0)
        if (sellerId && productId && typeof startSellerInquiry === 'function') {
            const dedupeKey = `${SELLER_INQUIRY_KEY}:${sellerId}:${productId}`
            const alreadySent = sessionStorage.getItem(dedupeKey)
            await startSellerInquiry(sellerId, productId, { skipCard: !!alreadySent })
            if (!alreadySent) {
                sessionStorage.setItem(dedupeKey, String(Date.now()))
            }
            clearSellerQuery()
        } else if (sellerId && typeof startSellerInquiry === 'function') {
            await startSellerInquiry(sellerId, null)
            clearSellerQuery()
        }

        return false
    }

    watch(() => route.query.account_id, (aid) => {
        if (aid) {
            const id = Number(aid)
            if (id) window.location.replace(`/build/channels?account_id=${id}&tab=manage`)
        }
    })

    watch(() => route.query.user_id, async (uid) => {
        const userId = Number(uid || 0)
        if (userId && typeof startChatWithUser === 'function') {
            await startChatWithUser(userId)
            clearQueryKeys('user_id')
        }
    })

    return { handleDeepLink }
}
