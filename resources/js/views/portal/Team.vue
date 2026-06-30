<template>
    <div class="portal-team-page">
        <div class="page-header">
            <div>
                <h2>团队协作</h2>
                <p class="text-muted">邀请成员 · 角色管理 · 权限控制</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button type="primary" @click="showInviteDialog = true" :icon="Plus">邀请成员</el-button>
            </div>
        </div>

        <!-- 团队概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">团队成员</div>
                    <div class="metric-value">{{ overview.total_members ?? 0 }}<small class="text-muted"> / {{ overview.max_members ?? '∞' }}</small></div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">待处理邀请</div>
                    <div class="metric-value warning">{{ overview.pending_invitations ?? 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">我的角色</div>
                    <div class="metric-value"><el-tag size="small">{{ roleLabels[overview.my_role] || overview.my_role }}</el-tag></div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 成员列表 -->
            <el-tab-pane label="👥 成员列表" name="members">
                <el-table :data="members" stripe v-loading="membersLoading" size="small">
                    <el-table-column label="用户" min-width="180">
                        <template #default="{row}">
                            <div class="user-info">
                                <el-avatar :size="32">{{ row.user?.name?.charAt(0) || '?' }}</el-avatar>
                                <div>
                                    <div class="user-name">{{ row.user?.name || row.user?.email || '—' }}</div>
                                    <div class="user-email">{{ row.user?.email || '—' }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="角色" width="140">
                        <template #default="{row}">
                            <el-select v-if="canManage" :model-value="row.role" size="small" @change="v => updateRole(row, v)" style="width:110px">
                                <el-option v-for="(l, k) in roleLabels" :key="k" :label="l" :value="k" />
                            </el-select>
                            <el-tag v-else size="small">{{ roleLabels[row.role] || row.role }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="80">
                        <template #default="{row}"><el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="加入时间" width="150"><template #default="{row}">{{ fmtTime(row.joined_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="100" fixed="right">
                        <template #default="{row}">
                            <el-popconfirm v-if="canManage && row.role !== 'admin'" title="确认移除?" @confirm="remove(row)">
                                <template #reference><el-button size="small" type="danger">移除</el-button></template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 待处理邀请 -->
            <el-tab-pane label="📨 待处理邀请" name="invitations">
                <el-table :data="pendingInvites" stripe v-loading="invLoading" size="small">
                    <el-table-column prop="email" label="邮箱" min-width="200" />
                    <el-table-column label="角色" width="120"><template #default="{row}"><el-tag size="small">{{ roleLabels[row.role] || row.role }}</el-tag></template></el-table-column>
                    <el-table-column prop="status" label="状态" width="80"><template #default="{row}"><el-tag size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column label="过期" width="150"><template #default="{row}">{{ fmtTime(row.expires_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="resend(row)">重发</el-button>
                            <el-button size="small" type="danger" @click="cancelInvite(row)">取消</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!pendingInvites.length && !invLoading" description="暂无待处理邀请" />
            </el-tab-pane>
        </el-tabs>

        <!-- 邀请对话框 -->
        <el-dialog v-model="showInviteDialog" title="邀请成员" width="500px">
            <el-form :model="inviteForm" label-width="100px">
                <el-form-item label="邮箱" prop="email" :rules="[{required:true, type:'email'}]">
                    <el-input v-model="inviteForm.email" placeholder="member@example.com" />
                </el-form-item>
                <el-form-item label="角色" prop="role" :rules="[{required:true}]">
                    <el-select v-model="inviteForm.role" style="width:100%">
                        <el-option v-for="(l, k) in roleLabels" :key="k" :label="l" :value="k" />
                    </el-select>
                </el-form-item>
                <el-form-item label="附言"><el-input v-model="inviteForm.message" type="textarea" :rows="2" placeholder="可选邀请附言" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showInviteDialog = false">取消</el-button>
                <el-button type="primary" @click="submitInvite" :loading="inviting">发送邀请</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus } from '@element-plus/icons-vue';
import tenantTeamApi from '@/api/tenantTeam';

const loading = ref(false);
const membersLoading = ref(false);
const invLoading = ref(false);
const inviting = ref(false);
const activeTab = ref('members');
const showInviteDialog = ref(false);
const overview = reactive({ total_members: 0, max_members: 100, pending_invitations: 0, my_role: '' });
const members = ref([]);
const pendingInvites = ref([]);
const inviteForm = reactive({ email: '', role: 'developer', message: '' });

const roleLabels = { admin: '管理员', finance: '财务', developer: '开发者', readonly: '只读' };
const canManage = computed(() => ['admin'].includes(overview.my_role));

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try {
        const [o, m, p] = await Promise.all([
            tenantTeamApi.overview(), tenantTeamApi.members(), tenantTeamApi.pendingInvitations()
        ]);
        Object.assign(overview, o.data?.data || {});
        members.value = m.data?.data || [];
        pendingInvites.value = p.data?.data || [];
    } catch (e) {
        if (e.response?.status === 403) ElMessage.warning('无权限查看团队信息');
    } finally { loading.value = false; }
}

async function submitInvite() {
    inviting.value = true;
    try {
        await tenantTeamApi.invite(inviteForm);
        ElMessage.success('邀请已发送'); showInviteDialog.value = false; loadAll();
    } catch { ElMessage.error('邀请失败'); } finally { inviting.value = false; }
}

async function updateRole(row, newRole) {
    try {
        await tenantTeamApi.updateMemberRole(row.id, { role: newRole });
        ElMessage.success('角色已更新'); await loadAll();
    } catch { ElMessage.error('更新失败'); }
}

async function remove(row) {
    await tenantTeamApi.removeMember(row.id);
    ElMessage.success('已移除'); await loadAll();
}

async function resend(row) {
    await tenantTeamApi.resendInvitation(row.id);
    ElMessage.success('已重发');
}

async function cancelInvite(row) {
    await tenantTeamApi.cancelInvitation(row.id);
    ElMessage.success('已取消'); await loadAll();
}

function fmtTime(t) { if (!t) return '—'; return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
</script>

<style scoped>
.portal-team-page { padding: 16px; max-width: 1000px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 12px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 22px; font-weight: 700; }
.metric-card .metric-value small { font-size: 13px; font-weight: 400; }
.warning { color: #e6a23c; }
.text-muted { color: #c0c4cc; }
.user-info { display: flex; align-items: center; gap: 10px; }
.user-name { font-weight: 600; font-size: 14px; }
.user-email { font-size: 12px; color: #909399; }
</style>
