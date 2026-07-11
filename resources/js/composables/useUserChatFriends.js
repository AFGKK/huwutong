import { ref, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/api/client'

/**
 * IM 好友列表、分组、好友请求
 */
export function useUserChatFriends() {
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
    const moveGroupTarget = ref(null)

    const filteredFriends = computed(() => {
        let list = friendsList.value
        if (friendGroupFilter.value) list = list.filter(f => f.friend_group_id == friendGroupFilter.value)
        if (friendSearch.value) {
            const kw = friendSearch.value.toLowerCase()
            list = list.filter(f => (f.name || '').toLowerCase().includes(kw) || (f.remark || '').toLowerCase().includes(kw))
        }
        return list
    })

    async function loadFriendsEnhanced() {
        try {
            const res = await apiClient.get('/user-chat/friends/enhanced')
            const raw = res.data?.data
            friendsList.value = Array.isArray(raw) ? raw : (raw?.data || [])
        } catch {
            friendsList.value = []
        }
    }

    async function loadFriendGroups() {
        try {
            const res = await apiClient.get('/user-chat/groups')
            friendGroups.value = res.data?.data || []
        } catch {
            friendGroups.value = []
        }
    }

    async function loadPendingRequests() {
        try {
            const res = await apiClient.get('/user-chat/friends/requests')
            pendingRequests.value = res.data?.data || []
        } catch {
            pendingRequests.value = []
        }
    }

    function loadFriendsTab() {
        loadFriendsEnhanced()
        loadFriendGroups()
        loadPendingRequests()
    }

    async function submitAddFriend() {
        if (!addFriendUserId.value) return
        addFriendLoading.value = true
        try {
            await apiClient.post('/user-chat/friends/add', { user_id: addFriendUserId.value })
            showAddFriend.value = false
            ElMessage.success('好友请求已发送')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '添加失败')
        } finally {
            addFriendLoading.value = false
        }
    }

    async function handleFriendRequest(id, status) {
        try {
            await apiClient.put('/user-chat/friends/' + id + '/handle', { status })
            await loadPendingRequests()
            await loadFriendsEnhanced()
        } catch { /* ignore */ }
    }

    function handleFriendAction(cmd, f) {
        if (cmd === 'remark') {
            remarkTarget.value = f
            remarkText.value = f.remark || ''
            showRemarkDialog.value = true
        } else if (cmd === 'group') {
            moveGroupTarget.value = f
            moveGroupId.value = f.friend_group_id || null
            showMoveGroupDialog.value = true
        } else if (cmd === 'remove') {
            removeFriend(f)
        }
    }

    async function submitRemark() {
        if (!remarkTarget.value || !remarkText.value) return
        try {
            await apiClient.put('/user-chat/friends/' + remarkTarget.value.id + '/remark', { remark: remarkText.value })
            remarkTarget.value.remark = remarkText.value
            showRemarkDialog.value = false
            ElMessage.success('备注已更新')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '设置失败')
        }
    }

    async function submitMoveGroup() {
        if (!moveGroupTarget.value) return
        try {
            await apiClient.put('/user-chat/friends/' + moveGroupTarget.value.id + '/group', { group_id: moveGroupId.value })
            moveGroupTarget.value.friend_group_id = moveGroupId.value
            showMoveGroupDialog.value = false
            ElMessage.success('已移动')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '移动失败')
        }
    }

    async function removeFriend(f) {
        try {
            await ElMessageBox.confirm('确定删除好友 ' + (f.name || '用户') + '？')
            await apiClient.delete('/user-chat/friends/' + f.id)
            await loadFriendsEnhanced()
        } catch { /* ignore */ }
    }

    async function createFriendGroup() {
        if (!newFriendGroupName.value.trim()) return
        try {
            const res = await apiClient.post('/user-chat/groups', { name: newFriendGroupName.value.trim() })
            friendGroups.value.push(res.data?.data)
            newFriendGroupName.value = ''
            ElMessage.success('分组已创建')
        } catch (e) {
            ElMessage.error(e.response?.data?.message || '创建失败')
        }
    }

    async function updateFriendGroup(g) {
        try {
            await apiClient.put('/user-chat/groups/' + g.id, { name: g.name })
        } catch { /* ignore */ }
    }

    async function deleteFriendGroup(id) {
        try {
            await apiClient.delete('/user-chat/groups/' + id)
            friendGroups.value = friendGroups.value.filter(g => g.id !== id)
        } catch { /* ignore */ }
    }

    return {
        friendSearch,
        loadingFriends,
        friendsList,
        friendGroups,
        friendGroupFilter,
        showAddFriend,
        addFriendUserId,
        addFriendLoading,
        showPendingRequests,
        pendingRequests,
        showFriendGroups,
        newFriendGroupName,
        showRemarkDialog,
        remarkText,
        remarkTarget,
        showMoveGroupDialog,
        moveGroupId,
        moveGroupTarget,
        filteredFriends,
        loadFriendsEnhanced,
        loadFriendGroups,
        loadPendingRequests,
        loadFriendsTab,
        submitAddFriend,
        handleFriendRequest,
        handleFriendAction,
        submitRemark,
        submitMoveGroup,
        removeFriend,
        createFriendGroup,
        updateFriendGroup,
        deleteFriendGroup,
    }
}
