<template>
    <div class="im-admin-page">
        <div class="page-header">
            <h2>📊 IM 管理后台</h2>
        </div>

        <el-tabs v-model="activeTab" type="border-card" class="im-admin-tabs">
            <!-- ADMIN-005: 数据看板 -->
            <el-tab-pane label="📈 数据看板" name="dashboard">
                <div class="tab-content" v-loading="loadingDash">
                    <el-row :gutter="16" class="stat-cards">
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.total_users }}</div><div class="stat-label">使用用户</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.total_groups }}</div><div class="stat-label">群组数</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.total_messages }}</div><div class="stat-label">总消息数</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.today_messages }}</div><div class="stat-label">今日消息</div></div></el-card></el-col>
                    </el-row>
                    <el-row :gutter="16" style="margin-top:16px">
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.week_messages }}</div><div class="stat-label">本周消息</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.active_users_7d }}</div><div class="stat-label">7日活跃用户</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ dash.total_conversations }}</div><div class="stat-label">总会话数</div></div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value" style="color:#e6a23c">{{ dash.pending_reports }}</div><div class="stat-label">待处理举报</div></div></el-card></el-col>
                    </el-row>
                    <el-card shadow="never" style="margin-top:16px">
                        <template #header><span>近7日消息趋势</span></template>
                        <div class="trend-chart">
                            <div v-for="t in dash.message_trend" :key="t.date" class="trend-bar-wrap">
                                <div class="trend-bar" :style="{ height: trendHeight(t.count) + 'px' }"></div>
                                <div class="trend-label">{{ t.date }}</div>
                                <div class="trend-value">{{ t.count }}</div>
                            </div>
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- ADMIN-002: IM 用户管理 -->
            <el-tab-pane label="👥 用户管理" name="users">
                <div class="tab-content">
                    <div class="toolbar">
                        <el-input v-model="userQuery" placeholder="搜索用户名/邮箱..." size="small" clearable style="width:260px" @keydown.enter="loadUsers" />
                        <el-button size="small" type="primary" @click="loadUsers">搜索</el-button>
                    </div>
                    <el-table :data="users" v-loading="loadingUsers" stripe size="small" style="width:100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column prop="name" label="用户名" min-width="120" />
                        <el-table-column prop="email" label="邮箱" min-width="180" />
                        <el-table-column label="消息数" width="80"><template #default="{row}">{{ row.total_msgs || 0 }}</template></el-table-column>
                        <el-table-column label="会话数" width="80"><template #default="{row}">{{ row.total_convs || 0 }}</template></el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{row}"><el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="注册时间" width="160"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                        <el-table-column label="操作" width="140">
                            <template #default="{row}">
                                <el-button size="small" text @click="showUserDetail(row.id)">详情</el-button>
                                <el-button v-if="row.status === 'active'" size="small" text type="danger" @click="banUser(row)">封禁</el-button>
                                <el-button v-else size="small" text type="success" @click="unbanUser(row)">解封</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="userTotal > 20" background layout="prev,pager,next" :total="userTotal" :page-size="20" @current-change="page => { userPage = page; loadUsers() }" style="margin-top:12px;justify-content:center" />
                </div>
            </el-tab-pane>

            <!-- ADMIN-003: 群组管理 -->
            <el-tab-pane label="👥 群组管理" name="groups">
                <div class="tab-content">
                    <div class="toolbar">
                        <el-input v-model="groupQuery" placeholder="搜索群组名..." size="small" clearable style="width:260px" @keydown.enter="loadGroups" />
                        <el-button size="small" type="primary" @click="loadGroups">搜索</el-button>
                    </div>
                    <el-table :data="groups" v-loading="loadingGroups" stripe size="small" style="width:100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column prop="name" label="群组名" min-width="160" />
                        <el-table-column label="创建者" width="120"><template #default="{row}">{{ row.creator?.name || '-' }}</template></el-table-column>
                        <el-table-column label="成员数" width="80"><template #default="{row}">{{ row.member_count || 0 }}</template></el-table-column>
                        <el-table-column label="创建时间" width="160"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{row}">
                                <el-button size="small" text @click="showGroupDetail(row.id)">详情</el-button>
                                <el-popconfirm title="确定解散该群组？" @confirm="dismissGroup(row.id)">
                                    <template #reference><el-button size="small" text type="danger">解散</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="groupTotal > 20" background layout="prev,pager,next" :total="groupTotal" :page-size="20" @current-change="page => { groupPage = page; loadGroups() }" style="margin-top:12px;justify-content:center" />
                </div>
            </el-tab-pane>

            <!-- ADMIN-004: 消息审计 -->
            <el-tab-pane label="🔍 消息审计" name="audit">
                <div class="tab-content">
                    <div class="toolbar" style="flex-wrap:wrap;gap:6px">
                        <el-input v-model="auditQuery" placeholder="关键词..." size="small" clearable style="width:160px" />
                        <el-select v-model="auditType" placeholder="消息类型" size="small" clearable style="width:130px">
                            <el-option label="全部" value="" />
                            <el-option label="文本" value="text" />
                            <el-option label="图片" value="image" />
                            <el-option label="文件" value="file" />
                            <el-option label="语音" value="voice" />
                            <el-option label="贴纸" value="sticker" />
                            <el-option label="卡片" value="card" />
                        </el-select>
                        <el-date-picker v-model="auditDateRange" type="daterange" size="small" range-separator="至" start-placeholder="开始" end-placeholder="结束" value-format="YYYY-MM-DD" style="width:220px" />
                        <el-button size="small" type="primary" @click="loadAudit">搜索</el-button>
                    </div>
                    <el-table :data="auditMsgs" v-loading="loadingAudit" stripe size="small" style="width:100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="发送者" width="100"><template #default="{row}">{{ row.sender?.name || '-' }}</template></el-table-column>
                        <el-table-column label="会话" width="140"><template #default="{row}">{{ row.conversation?.name || '-' }}</template></el-table-column>
                        <el-table-column label="类型" width="70"><template #default="{row}"><el-tag size="small">{{ row.message_type }}</el-tag></template></el-table-column>
                        <el-table-column label="内容" min-width="200">
                            <template #default="{row}">
                                <div class="audit-content">{{ row.content?.substring(0, 100) }}{{ row.content?.length > 100 ? '...' : '' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="时间" width="150"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                        <el-table-column label="操作" width="80">
                            <template #default="{row}">
                                <el-popconfirm title="确定删除此消息？" @confirm="deleteAuditMsg(row.id)">
                                    <template #reference><el-button size="small" text type="danger">删除</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="auditTotal > 20" background layout="prev,pager,next" :total="auditTotal" :page-size="20" @current-change="page => { auditPage = page; loadAudit() }" style="margin-top:12px;justify-content:center" />
                </div>
            </el-tab-pane>

            <!-- ADMIN-007: 举报管理 -->
            <el-tab-pane label="🚨 举报管理" name="reports">
                <div class="tab-content">
                    <div class="toolbar">
                        <el-select v-model="reportStatus" size="small" clearable style="width:150px">
                            <el-option label="全部" value="" />
                            <el-option label="待处理" value="pending" />
                            <el-option label="已处理" value="resolved" />
                        </el-select>
                        <el-button size="small" type="primary" @click="loadReports">刷新</el-button>
                    </div>
                    <el-table :data="reports" v-loading="loadingReports" stripe size="small" style="width:100%">
                        <el-table-column prop="id" label="ID" width="60" />
                        <el-table-column label="举报人" width="100"><template #default="{row}">{{ row.reporter?.name || '-' }}</template></el-table-column>
                        <el-table-column label="被举报人" width="140"><template #default="{row}">{{ row.reportable?.user?.name || row.reportable?.name || row.reportable?.sender?.name || '-' }}</template></el-table-column>
                        <el-table-column label="原因" min-width="180"><template #default="{row}">{{ row.reason }}</template></el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{row}"><el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="时间" width="150"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{row}">
                                <el-button v-if="row.status !== 'resolved'" size="small" type="primary" text @click="resolveReport(row.id)">处理</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="reportTotal > 20" background layout="prev,pager,next" :total="reportTotal" :page-size="20" @current-change="page => { reportPage = page; loadReports() }" style="margin-top:12px;justify-content:center" />
                </div>
            </el-tab-pane>

            <!-- OA 互物号分类管理 -->
            <el-tab-pane label="📢 互物号分类" name="oaCats">
                <div class="tab-content">
                    <div class="toolbar">
                        <el-button size="small" type="primary" @click="openNewOaCat">新建分类</el-button>
                        <el-button size="small" @click="loadOaCats">刷新</el-button>
                    </div>
                    <el-table :data="oaCats" v-loading="loadingOaCats" stripe size="small" style="width:100%">
                        <el-table-column label="图标" width="60"><template #default="{row}">{{ row.icon || '📌' }}</template></el-table-column>
                        <el-table-column prop="name" label="分类名称" width="120" />
                        <el-table-column prop="sort_order" label="排序" width="70" />
                        <el-table-column label="状态" width="70">
                            <template #default="{row}"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '禁用' }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="互物号数" width="80"><template #default="{row}">{{ row.accounts_count || 0 }}</template></el-table-column>
                        <el-table-column label="操作" width="140">
                            <template #default="{row}">
                                <el-button size="small" text @click="editOaCat(row)">编辑</el-button>
                                <el-button size="small" text type="danger" @click="deleteOaCat(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </el-tab-pane>
            <el-tab-pane label="💬 会话管理" name="conversations">
                <div style="margin-bottom:10px;display:flex;gap:6px;align-items:center">
                    <el-input v-model="convSearch" placeholder="搜索会话/用户..." size="small" clearable prefix-icon="Search" style="width:260px" @keydown.enter="loadConversations(1)" />
                    <el-select v-model="convType" size="small" style="width:100px" @change="loadConversations(1)">
                        <el-option label="全部" value="" />
                        <el-option label="私聊" value="private" />
                        <el-option label="群组" value="group" />
                    </el-select>
                </div>
                <el-table :data="convs" v-loading="loadingConvs" stripe size="small" @row-click="showConvDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column label="类型" width="70">
                        <template #default="{row}"><el-tag :type="row.type === 'group' ? 'success' : 'info'" size="small">{{ row.type === 'group' ? '群组' : '私聊' }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="名称" min-width="160">
                        <template #default="{row}">{{ row.name || row.participants?.map(p => p.user?.name).filter(Boolean).join(', ') || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="成员数" width="70" prop="participants_count" />
                    <el-table-column label="消息数" width="70" prop="messages_count" />
                    <el-table-column label="最后活跃" width="140">
                        <template #default="{row}">{{ formatTime(row.updated_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="80" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" text type="danger" @click.stop="deleteConv(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div v-if="convTotal > 20" style="margin-top:10px;text-align:center">
                    <el-pagination background layout="prev,pager,next" :total="convTotal" :page-size="20" :current-page="convPage" @current-change="loadConversations" />
                </div>
            </el-tab-pane>
        </el-tabs>

        <!-- 用户详情对话框 -->
        <el-dialog v-model="showUserDetailDialog" title="用户详情" width="500px">
            <div v-if="userDetail" class="user-detail">
                <div class="detail-row"><span class="detail-label">ID:</span><span>{{ userDetail.user?.id }}</span></div>
                <div class="detail-row"><span class="detail-label">用户名:</span><span>{{ userDetail.user?.name }}</span></div>
                <div class="detail-row"><span class="detail-label">邮箱:</span><span>{{ userDetail.user?.email }}</span></div>
                <div class="detail-row"><span class="detail-label">状态:</span><el-tag :type="userDetail.user?.status === 'active' ? 'success' : 'danger'" size="small">{{ userDetail.user?.status }}</el-tag></div>
                <div class="detail-row"><span class="detail-label">在线状态:</span><el-tag :type="userDetail.online === 'online' ? 'success' : 'info'" size="small">{{ userDetail.online }}</el-tag></div>
                <div class="detail-row"><span class="detail-label">好友数:</span><span>{{ userDetail.friend_count || 0 }}</span></div>
                <div class="detail-row"><span class="detail-label">消息数:</span><span>{{ userDetail.total_msgs || 0 }}</span></div>
                <div class="detail-row"><span class="detail-label">会话数:</span><span>{{ userDetail.total_convs || 0 }}</span></div>
                <div class="detail-row"><span class="detail-label">最后活跃:</span><span>{{ userDetail.last_active || '-' }}</span></div>
            </div>
        </el-dialog>

        <!-- OA 分类编辑对话框 -->
        <el-dialog v-model="showOaCatDialog" :title="oaCatEditId ? '编辑分类' : '新建分类'" width="380px">
            <el-form label-width="70px">
                <el-form-item label="名称">
                    <el-input v-model="oaCatForm.name" placeholder="分类名称..." maxlength="50" />
                </el-form-item>
                <el-form-item label="图标">
                    <el-input v-model="oaCatForm.icon" placeholder="emoji 图标..." maxlength="10" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="oaCatForm.sort_order" :min="0" size="small" />
                </el-form-item>
                <el-form-item label="启用">
                    <el-switch v-model="oaCatForm.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showOaCatDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="savingOaCat" @click="saveOaCat">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import apiClient from '@/utils/request'

const activeTab = ref('dashboard')

// ── ADMIN-005: 数据看板 ──
const loadingDash = ref(false)
const dash = reactive({
    total_users: 0, total_groups: 0, total_conversations: 0,
    total_messages: 0, today_messages: 0, week_messages: 0,
    active_users_7d: 0, pending_reports: 0, message_trend: [],
})
function trendHeight(count) { return Math.min(Math.max(count, 4), 120) }

async function loadDash() {
    loadingDash.value = true
    try {
        const res = await apiClient.get('/im-admin/dashboard')
        const d = res.data?.data || {}
        Object.assign(dash, d)
    } catch { /* ignore */ }
    finally { loadingDash.value = false }
}

// ── ADMIN-002: 用户管理 ──
const users = ref([])
const loadingUsers = ref(false)
const userQuery = ref('')
const userPage = ref(1)
const userTotal = ref(0)
const showUserDetailDialog = ref(false)
const userDetail = ref(null)

async function loadUsers() {
    loadingUsers.value = true
    try {
        const res = await apiClient.get('/im-admin/users', { params: { q: userQuery.value, per_page: 20, page: userPage.value } })
        users.value = res.data?.data || []
        userTotal.value = res.data?.meta?.total || 0
    } catch { users.value = [] }
    finally { loadingUsers.value = false }
}
async function showUserDetail(id) {
    try {
        const res = await apiClient.get('/im-admin/users/' + id)
        userDetail.value = res.data?.data || null
        showUserDetailDialog.value = true
    } catch { ElMessage.error('获取用户详情失败') }
}

// ── ADMIN-003: 群组管理 ──
const groups = ref([])
const loadingGroups = ref(false)
const groupQuery = ref('')
const groupPage = ref(1)
const groupTotal = ref(0)

async function loadGroups() {
    loadingGroups.value = true
    try {
        const res = await apiClient.get('/im-admin/groups', { params: { q: groupQuery.value, per_page: 20, page: groupPage.value } })
        groups.value = res.data?.data || []
        groupTotal.value = res.data?.meta?.total || 0
    } catch { groups.value = [] }
    finally { loadingGroups.value = false }
}
async function showGroupDetail(id) {
    try {
        const res = await apiClient.get('/im-admin/groups/' + id)
        const d = res.data?.data
        const msg = `群组: ${d.group.name}\n创建者: ${d.group.creator?.name || '-'}\n成员数: ${d.member_count}\n总消息数: ${d.total_messages}`
        ElMessageBox.alert(msg, '群组详情')
    } catch { /* ignore */ }
}
async function dismissGroup(id) {
    try {
        await apiClient.delete('/im-admin/groups/' + id)
        ElMessage.success('群组已解散')
        loadGroups()
    } catch { ElMessage.error('操作失败') }
}

// ── ADMIN-004: 消息审计 ──
const auditMsgs = ref([])
const loadingAudit = ref(false)
const auditQuery = ref('')
const auditType = ref('')
const auditDateRange = ref(null)
const auditPage = ref(1)
const auditTotal = ref(0)

async function loadAudit() {
    loadingAudit.value = true
    try {
        const params = { per_page: 20, page: auditPage.value }
        if (auditQuery.value) params.q = auditQuery.value
        if (auditType.value) params.message_type = auditType.value
        if (auditDateRange.value && auditDateRange.value[0]) params.date_from = auditDateRange.value[0]
        if (auditDateRange.value && auditDateRange.value[1]) params.date_to = auditDateRange.value[1]
        const res = await apiClient.get('/im-admin/messages', { params })
        auditMsgs.value = res.data?.data || []
        auditTotal.value = res.data?.meta?.total || 0
    } catch { auditMsgs.value = [] }
    finally { loadingAudit.value = false }
}
async function deleteAuditMsg(id) {
    try {
        await apiClient.delete('/im-admin/messages/' + id)
        ElMessage.success('消息已删除')
        loadAudit()
    } catch { ElMessage.error('删除失败') }
}

// ── ADMIN-007: 举报管理 ──
const reports = ref([])
const loadingReports = ref(false)
const reportStatus = ref('')
const reportPage = ref(1)
const reportTotal = ref(0)

async function loadReports() {
    loadingReports.value = true
    try {
        const params = { per_page: 20, page: reportPage.value }
        if (reportStatus.value) params.status = reportStatus.value
        const res = await apiClient.get('/im-admin/reports', { params })
        reports.value = res.data?.data || []
        reportTotal.value = res.data?.meta?.total || 0
    } catch { reports.value = [] }
    finally { loadingReports.value = false }
}
async function resolveReport(id) {
    try {
        await ElMessageBox.prompt('处理备注（选填）', '处理举报', { inputType: 'textarea', inputPlaceholder: '输入处理说明...', confirmButtonText: '处理', cancelButtonText: '取消' })
            .then(async ({ value }) => {
                await apiClient.post('/im-admin/reports/' + id + '/resolve', { note: value || '' })
                ElMessage.success('举报已处理')
                loadReports()
            })
    } catch { /* cancelled */ }
}

// ── OA 互物号分类管理 ──
const oaCats = ref([])
const loadingOaCats = ref(false)
const showOaCatDialog = ref(false)
const oaCatEditId = ref(null)
const savingOaCat = ref(false)
const oaCatForm = reactive({ name: '', icon: '', sort_order: 0, is_active: true })

async function loadOaCats() {
    loadingOaCats.value = true
    try { const r = await apiClient.get('/official-accounts/admin/categories'); oaCats.value = r.data?.data || [] }
    catch { oaCats.value = [] }
    finally { loadingOaCats.value = false }
}

// ── 会话管理 ──
const convs = ref([])
const loadingConvs = ref(false)
const convSearch = ref('')
const convType = ref('')
const convPage = ref(1)
const convTotal = ref(0)

async function loadConversations(page) {
    if (page) convPage.value = page
    loadingConvs.value = true
    try {
        const params = { per_page: 20, page: convPage.value }
        if (convSearch.value) params.q = convSearch.value
        if (convType.value) params.type = convType.value
        const r = await apiClient.get('/im-admin/conversations', { params })
        convs.value = r.data?.data || []
        convTotal.value = r.data?.meta?.total || 0
    } catch { convs.value = [] }
    finally { loadingConvs.value = false }
}
async function showConvDetail(conv) {
    try {
        const r = await apiClient.get('/im-admin/conversations/' + conv.id)
        const data = r.data?.data
        if (!data) return
        const parts = data.conversation?.participants?.map(p => p.user?.name).filter(Boolean).join(', ') || '-'
        const msgCount = data.recent_messages?.length || 0
        const recentMsgs = data.recent_messages?.slice(0, 10).map(m => `[${m.sender?.name || '?'}] ${m.content?.substring(0, 50)}`).join('\n') || '无'
        ElMessageBox.alert(`<div style="font-size:14px"><b>会话 #${conv.id}</b><br>类型: ${conv.type === 'group' ? '群组' : '私聊'}<br>参与者: ${parts}<br>总消息: ${conv.messages_count}<br><br><b>最近消息:</b><br>${recentMsgs}</div>`, '会话详情', { dangerouslyUseHTMLString: true, confirmButtonText: '关闭' })
    } catch { ElMessage.error('加载失败') }
}
async function deleteConv(conv) {
    try {
        await ElMessageBox.confirm('确定删除此会话？所有消息将被清除。', '确认', { type: 'warning' })
        await apiClient.delete('/im-admin/conversations/' + conv.id)
        ElMessage.success('已删除')
        loadConversations()
    } catch { /* cancelled */ }
}

function openNewOaCat() {
    oaCatEditId.value = null; oaCatForm.name = ''; oaCatForm.icon = ''; oaCatForm.sort_order = 0; oaCatForm.is_active = true
    showOaCatDialog.value = true
}

function editOaCat(row) {
    oaCatEditId.value = row.id; oaCatForm.name = row.name; oaCatForm.icon = row.icon || ''; oaCatForm.sort_order = row.sort_order ?? 0; oaCatForm.is_active = row.is_active !== false
    showOaCatDialog.value = true
}

async function saveOaCat() {
    if (!oaCatForm.name.trim()) { ElMessage.warning('请输入名称'); return }
    savingOaCat.value = true
    try {
        const payload = { name: oaCatForm.name.trim(), icon: oaCatForm.icon || undefined, sort_order: oaCatForm.sort_order, is_active: oaCatForm.is_active }
        if (oaCatEditId.value) { await apiClient.put('/official-accounts/admin/categories/' + oaCatEditId.value, payload); ElMessage.success('已更新') }
        else { await apiClient.post('/official-accounts/admin/categories', payload); ElMessage.success('已创建') }
        showOaCatDialog.value = false; await loadOaCats()
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') }
    finally { savingOaCat.value = false }
}

async function deleteOaCat(row) {
    try {
        await ElMessageBox.confirm('确定删除分类「' + row.name + '」？')
        await apiClient.delete('/official-accounts/admin/categories/' + row.id)
        ElMessage.success('已删除'); await loadOaCats()
    } catch { /* cancelled */ }
}

// ── 封禁/解封 ──
async function banUser(row) {
    try {
        await ElMessageBox.confirm('确定封禁用户「' + row.name + '」？该用户将无法登录。', '确认', { type: 'warning' })
        await apiClient.post('/im-admin/users/' + row.id + '/ban')
        ElMessage.success('已封禁')
        loadUsers()
    } catch { /* cancelled */ }
}
async function unbanUser(row) {
    try {
        await apiClient.post('/im-admin/users/' + row.id + '/unban')
        ElMessage.success('已解封')
        loadUsers()
    } catch { ElMessage.error('操作失败') }
}

onMounted(() => {
    loadDash()
    loadUsers()
    loadGroups()
    loadAudit()
    loadReports()
    loadOaCats()
    loadConversations()
})
</script>

<style scoped>
.im-admin-page { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }
.im-admin-tabs { min-height: 400px; }
.tab-content { padding: 16px 0; }
.stat-cards { margin-bottom: 0; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.trend-chart { display: flex; align-items: flex-end; gap: 12px; height: 140px; padding: 10px 0; justify-content: center; }
.trend-bar-wrap { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.trend-bar { width: 36px; background: linear-gradient(180deg, #409eff, #66b1ff); border-radius: 4px 4px 0 0; min-height: 4px; transition: height 0.3s; }
.trend-label { font-size: 11px; color: #909399; }
.trend-value { font-size: 12px; color: #606266; font-weight: 600; }
.audit-content { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-detail .detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.user-detail .detail-label { width: 100px; color: #909399; flex-shrink: 0; }
</style>
