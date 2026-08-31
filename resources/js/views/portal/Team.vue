<template>
    <div class="portal-team-page">
        <div class="page-header">
            <div>
                <h2>{{ $t('portal.team_title') }}</h2>
                <p class="text-muted">{{ $t('portal.team_subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ $t('portal.refresh') }}</el-button>
                <el-button type="primary" @click="showInviteDialog = true" :icon="Plus">{{ $t('portal.invite_member') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">{{ $t('portal.team_members') }}</div>
                    <div class="metric-value">{{ overview.total_members ?? 0 }}<small class="text-muted"> / {{ overview.max_members ?? '∞' }}</small></div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">{{ $t('portal.pending_invites') }}</div>
                    <div class="metric-value warning">{{ overview.pending_invitations ?? 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover" class="metric-card">
                    <div class="metric-label">{{ $t('portal.my_role') }}</div>
                    <div class="metric-value"><el-tag size="small">{{ roleLabels[overview.my_role] || overview.my_role }}</el-tag></div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="$t('portal.members_tab')" name="members">
                <el-table :data="members" stripe v-loading="membersLoading" size="small">
                    <el-table-column :label="$t('portal.user')" min-width="180">
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
                    <el-table-column :label="$t('portal.role')" width="140">
                        <template #default="{row}">
                            <el-select v-if="canManage" :model-value="row.role" size="small" @change="v => updateRole(row, v)" style="width:110px">
                                <el-option v-for="(l, k) in roleLabels" :key="k" :label="l" :value="k" />
                            </el-select>
                            <el-tag v-else size="small">{{ roleLabels[row.role] || row.role }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" :label="$t('portal.status')" width="80">
                        <template #default="{row}"><el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column :label="$t('portal.joined_at')" width="150"><template #default="{row}">{{ fmtTime(row.joined_at) }}</template></el-table-column>
                    <el-table-column :label="$t('portal.actions')" width="100" fixed="right">
                        <template #default="{row}">
                            <el-popconfirm v-if="canManage && row.role !== 'admin'" :title="$t('portal.confirm_remove')" @confirm="remove(row)">
                                <template #reference><el-button size="small" type="danger">{{ $t('portal.remove') }}</el-button></template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane :label="$t('portal.invites_tab')" name="invitations">
                <el-table :data="pendingInvites" stripe v-loading="invLoading" size="small">
                    <el-table-column prop="email" :label="$t('portal.email')" min-width="200" />
                    <el-table-column :label="$t('portal.role')" width="120"><template #default="{row}"><el-tag size="small">{{ roleLabels[row.role] || row.role }}</el-tag></template></el-table-column>
                    <el-table-column prop="status" :label="$t('portal.status')" width="80"><template #default="{row}"><el-tag size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column :label="$t('portal.expires')" width="150"><template #default="{row}">{{ fmtTime(row.expires_at) }}</template></el-table-column>
                    <el-table-column :label="$t('portal.actions')" width="160" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="resend(row)">{{ $t('portal.resend') }}</el-button>
                            <el-button size="small" type="danger" @click="cancelInvite(row)">{{ $t('actions.cancel') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!pendingInvites.length && !invLoading" :description="$t('portal.no_pending_invites')" />
            </el-tab-pane>
        </el-tabs>

        <el-dialog v-model="showInviteDialog" :title="$t('portal.invite_member')" width="500px">
            <el-form :model="inviteForm" label-width="100px">
                <el-form-item :label="$t('portal.email')" prop="email" :rules="[{required:true, type:'email'}]">
                    <el-input v-model="inviteForm.email" placeholder="member@example.com" />
                </el-form-item>
                <el-form-item :label="$t('portal.role')" prop="role" :rules="[{required:true}]">
                    <el-select v-model="inviteForm.role" style="width:100%">
                        <el-option v-for="(l, k) in roleLabels" :key="k" :label="l" :value="k" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('portal.invite_note')"><el-input v-model="inviteForm.message" type="textarea" :rows="2" :placeholder="$t('portal.invite_note_ph')" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showInviteDialog = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitInvite" :loading="inviting">{{ $t('portal.send_invite') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh, Plus } from '@element-plus/icons-vue';
import tenantTeamApi from '@/api/tenantTeam';

const { t, locale } = useI18n();

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

const roleLabels = computed(() => ({
    admin: t('portal.role_admin'),
    finance: t('portal.role_finance'),
    developer: t('portal.role_developer'),
    readonly: t('portal.role_readonly'),
}));
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
        if (e.response?.status === 403) ElMessage.warning(t('portal.team_forbidden'));
    } finally { loading.value = false; }
}

async function submitInvite() {
    inviting.value = true;
    try {
        await tenantTeamApi.invite(inviteForm);
        ElMessage.success(t('portal.invite_sent')); showInviteDialog.value = false; loadAll();
    } catch { ElMessage.error(t('portal.invite_failed')); } finally { inviting.value = false; }
}

async function updateRole(row, newRole) {
    try {
        await tenantTeamApi.updateMemberRole(row.id, { role: newRole });
        ElMessage.success(t('portal.role_updated')); await loadAll();
    } catch { ElMessage.error(t('portal.update_failed')); }
}

async function remove(row) {
    await tenantTeamApi.removeMember(row.id);
    ElMessage.success(t('portal.removed_ok')); await loadAll();
}

async function resend(row) {
    await tenantTeamApi.resendInvitation(row.id);
    ElMessage.success(t('portal.resent_ok'));
}

async function cancelInvite(row) {
    await tenantTeamApi.cancelInvitation(row.id);
    ElMessage.success(t('portal.cancelled_ok')); await loadAll();
}

function fmtTime(ts) {
    if (!ts) return '—';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(ts).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
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
