<template>
  <div class="team-management">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>{{ t('team_page.title') }}</h2>
        <span class="header-subtitle" v-if="tenant">{{ tenant.name }}</span>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="showInviteDialog = true" :disabled="!canInvite">
          <el-icon><Plus /></el-icon> {{ t('team_page.invite_member') }}
        </el-button>
        <el-button @click="showSettings = !showSettings">
          <el-icon><Setting /></el-icon> {{ t('team_page.team_settings') }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ memberCount }}</div>
            <div class="stat-label">{{ t('team_page.stats.members') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value role-admin">{{ adminCount }}</div>
            <div class="stat-label">{{ t('team_page.stats.admins') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value role-developer">{{ developerCount }}</div>
            <div class="stat-label">{{ t('team_page.stats.developers') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value warning" :class="{ 'has-pending': pendingCount > 0 }">{{ pendingCount }}</div>
            <div class="stat-label">{{ t('team_page.stats.pending_invitations') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容区 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 成员列表 -->
        <el-tab-pane :label="t('team_page.tabs.all_members')" name="members">
          <div class="member-list">
            <div v-for="member in members" :key="member.id" class="member-card">
              <el-avatar :size="40">{{ member.user?.name?.charAt(0) || '?' }}</el-avatar>
              <div class="member-info">
                <div class="member-name">
                  {{ member.user?.name || t('team_page.member.unknown_user') }}
                  <el-tag v-if="member.user_id === currentUserId" size="small" type="info">{{ t('team_page.member.me') }}</el-tag>
                </div>
                <div class="member-email">{{ member.user?.email }}</div>
                <div class="member-meta">
                  {{ t('team_page.member.joined_at', { date: formatDate(member.joined_at) }) }}
                  <span class="meta-sep">·</span>
                  {{ t('team_page.member.via', { via: member.invited_via_label || t('team_page.member.direct_add') }) }}
                  <span class="meta-sep">·</span>
                  {{ member.user?.last_login_at
                    ? t('team_page.member.last_active', { date: formatDate(member.user.last_login_at) })
                    : t('team_page.member.last_active_unknown') }}
                </div>
                <div class="member-inviter" v-if="member.inviter">
                  {{ t('team_page.member.inviter', { name: member.inviter.name }) }}
                </div>
              </div>
              <div class="member-role" :class="'role-' + member.role">
                <el-tag v-if="isCurrentUser(member)" :type="roleTagType(member.role)" effect="dark" size="small">
                  {{ member.role_label }}
                </el-tag>
                <el-dropdown v-else-if="canManage" @command="(val) => handleRoleChange(member, val)">
                  <el-tag :type="roleTagType(member.role)" style="cursor: pointer" size="small">
                    {{ member.role_label }} <el-icon><ArrowDown /></el-icon>
                  </el-tag>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item v-for="r in allRoles" :key="r" :command="r"
                        :disabled="r === member.role"
                        :class="{ 'role-admin': r === 'admin' }"
                      >
                        {{ roleLabels[r] }}
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
                <el-tag v-else :type="roleTagType(member.role)" size="small">
                  {{ member.role_label }}
                </el-tag>
              </div>
              <div class="member-actions" v-if="canManage && !isCurrentUser(member)">
                <el-button text type="primary" size="small" @click="handleTransferAdmin(member)">{{ t('team_page.actions.transfer') }}</el-button>
                <el-button text type="danger" size="small" @click="handleRemove(member)">{{ t('team_page.actions.remove') }}</el-button>
              </div>
            </div>

            <div v-if="members.length === 0" class="empty-state">
              <el-empty :description="t('team_page.empty.no_members')" />
            </div>
          </div>
        </el-tab-pane>

        <!-- 待处理邀请 -->
        <el-tab-pane :label="t('team_page.tabs.pending_invitations', { n: pendingInvitations.length })" name="invitations">
          <div v-if="pendingInvitations.length === 0" class="empty-state">
            <el-empty :description="t('team_page.empty.no_pending')" />
          </div>
          <div v-else class="invitation-list">
            <div v-for="inv in pendingInvitations" :key="inv.id" class="invitation-card">
              <el-icon class="inv-icon" :size="28"><Message /></el-icon>
              <div class="inv-info">
                <div class="inv-email">{{ inv.email }}</div>
                <div class="inv-role">{{ t('team_page.invitation.role', { role: roleLabels[inv.role] || inv.role }) }}</div>
                <div class="inv-meta">
                  {{ t('team_page.invitation.invited_at', { date: formatDate(inv.created_at) }) }}
                  <span v-if="inv.expires_at">{{ t('team_page.invitation.expires_at', { date: formatDate(inv.expires_at) }) }}</span>
                </div>
                <div class="inv-inviter" v-if="inv.inviter">
                  {{ t('team_page.invitation.inviter', { name: inv.inviter.name }) }}
                </div>
              </div>
              <div class="inv-status">
                <el-tag size="small" type="warning">{{ t('team_page.invitation.status_pending') }}</el-tag>
              </div>
              <div class="inv-actions">
                <el-button text size="small" type="primary" @click="handleResend(inv)">{{ t('team_page.actions.resend') }}</el-button>
                <el-button text size="small" type="danger" @click="handleCancelInvite(inv)">{{ t('actions.cancel') }}</el-button>
              </div>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 邀请成员对话框 -->
    <el-dialog v-model="showInviteDialog" :title="t('team_page.invite_dialog.title')" width="520px">
      <el-form ref="inviteFormRef" :model="inviteForm" :rules="inviteRules" label-width="80px">
        <!-- 单条邀请模式 -->
        <template v-if="!batchMode">
          <el-form-item :label="t('team_page.invite_dialog.email')" prop="email">
            <el-input v-model="inviteForm.email" :placeholder="t('team_page.invite_dialog.email_ph')" />
          </el-form-item>
          <el-form-item :label="t('team_page.invite_dialog.role')" prop="role">
            <el-select v-model="inviteForm.role" style="width: 100%">
              <el-option v-for="(label, key) in roleLabels" :key="key" :label="label" :value="key" />
            </el-select>
          </el-form-item>
          <el-form-item :label="t('team_page.invite_dialog.message')">
            <el-input v-model="inviteForm.message" type="textarea" :rows="2" :placeholder="t('team_page.invite_dialog.message_ph')" maxlength="500" show-word-limit />
          </el-form-item>
        </template>
        <!-- 批量邀请模式 -->
        <template v-else>
          <el-alert :title="t('team_page.invite_dialog.batch_title')" :description="t('team_page.invite_dialog.batch_desc')" type="info" show-icon :closable="false" class="mb-3" />
          <el-form-item :label="t('team_page.invite_dialog.batch_list')">
            <el-input
              v-model="inviteForm.batchText"
              type="textarea"
              :rows="6"
              :placeholder="t('team_page.invite_dialog.batch_ph')"
            />
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="batchMode = !batchMode" text>
            {{ batchMode ? t('team_page.invite_dialog.switch_single') : t('team_page.invite_dialog.batch_mode') }}
          </el-button>
          <el-button @click="showInviteDialog = false">{{ t('actions.cancel') }}</el-button>
          <el-button type="primary" @click="handleInvite" :loading="inviting">
            {{ batchMode ? t('team_page.invite_dialog.batch_send') : t('team_page.invite_dialog.send') }}
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 团队设置面板 -->
    <el-drawer v-model="showSettings" :title="t('team_page.settings.title')" size="400px">
      <div class="settings-panel">
        <el-divider content-position="left">{{ t('team_page.settings.role_descriptions') }}</el-divider>
        <div v-for="(desc, role) in roleDescriptions" :key="role" class="role-desc-item">
          <el-tag :type="roleTagType(role)" size="small" class="role-badge">{{ roleLabels[role] }}</el-tag>
          <span class="role-desc-text">{{ desc }}</span>
        </div>

        <el-divider content-position="left">{{ t('team_page.settings.danger_zone') }}</el-divider>
        <div class="danger-zone">
          <div class="danger-item">
            <div class="danger-info">
              <div class="danger-title">{{ t('team_page.settings.leave_team') }}</div>
              <div class="danger-desc">{{ t('team_page.settings.leave_desc') }}</div>
            </div>
            <el-button type="danger" plain @click="handleLeave">{{ t('team_page.settings.leave_team') }}</el-button>
          </div>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Setting, ArrowDown, Message } from '@element-plus/icons-vue';
import teamApi from '@/api/team';

const { t, locale } = useI18n();

const activeTab = ref('members');
const showInviteDialog = ref(false);
const showSettings = ref(false);
const batchMode = ref(false);
const inviting = ref(false);
const loading = ref(false);

// 数据
const members = ref([]);
const pendingInvitations = ref([]);
const tenant = ref(null);
const userRole = ref(null);
const currentUserId = ref(null);

// 角色配置
const allRoles = ['admin', 'finance', 'developer', 'readonly'];
const roleLabels = computed(() => ({
  admin: t('team_page.roles.admin'),
  finance: t('team_page.roles.finance'),
  developer: t('team_page.roles.developer'),
  readonly: t('team_page.roles.readonly'),
}));
const roleDescriptions = computed(() => ({
  admin: t('team_page.role_desc.admin'),
  finance: t('team_page.role_desc.finance'),
  developer: t('team_page.role_desc.developer'),
  readonly: t('team_page.role_desc.readonly'),
}));
function roleTagType(role) {
  const map = { admin: 'danger', finance: 'warning', developer: 'primary', readonly: 'info' };
  return map[role] || 'info';
}

// 计算属性
const memberCount = computed(() => members.value.length);
const adminCount = computed(() => members.value.filter(m => m.role === 'admin').length);
const developerCount = computed(() => members.value.filter(m => m.role === 'developer').length);
const pendingCount = computed(() => pendingInvitations.value.length);
const canInvite = computed(() => ['admin', 'finance'].includes(userRole.value));
const canManage = computed(() => userRole.value === 'admin');

// 邀请表单
const inviteFormRef = ref();
const inviteForm = reactive({
  email: '',
  role: 'developer',
  message: '',
  batchText: '',
});
const inviteRules = computed(() => ({
  email: [
    { required: true, message: t('team_page.validation.email_required'), trigger: 'blur' },
    { type: 'email', message: t('team_page.validation.email_invalid'), trigger: 'blur' },
  ],
  role: [{ required: true, message: t('team_page.validation.role_required'), trigger: 'change' }],
}));

function isCurrentUser(member) {
  return member.user_id === currentUserId.value;
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(dateStr).toLocaleString(loc, {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  });
}

// 加载数据
async function loadTeam() {
  loading.value = true;
  try {
    const { data: res } = await teamApi.overview();
    const d = res.data;
    tenant.value = d.tenant;
    userRole.value = d.user_role;
    currentUserId.value = res.meta?.user_id || null;
    members.value = d.members || [];
    pendingInvitations.value = d.pending_invitations || [];
  } catch {
    ElMessage.error(t('team_page.messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

// 邀请
async function handleInvite() {
  inviting.value = true;
  try {
    if (batchMode.value) {
      const lines = inviteForm.batchText.trim().split('\n').filter(Boolean);
      const invites = lines.map(line => {
        const parts = line.split(',').map(s => s.trim());
        return {
          email: parts[0],
          role: parts[1] || 'developer',
          message: parts[2] || null,
        };
      });
      await teamApi.invite({ invites });
      ElMessage.success(t('team_page.messages.batch_sent', { n: invites.length }));
    } else {
      await inviteFormRef.value.validate();
      await teamApi.invite({
        email: inviteForm.email,
        role: inviteForm.role,
        message: inviteForm.message || null,
      });
      ElMessage.success(t('team_page.messages.invite_sent', { email: inviteForm.email }));
    }
    showInviteDialog.value = false;
    inviteForm.email = '';
    inviteForm.message = '';
    inviteForm.batchText = '';
    loadTeam();
  } catch (e) {
    const msg = e.response?.data?.message || t('team_page.messages.invite_failed');
    ElMessage.error(msg);
  } finally {
    inviting.value = false;
  }
}

// 角色变更
async function handleRoleChange(member, newRole) {
  try {
    await ElMessageBox.confirm(
      t('team_page.confirm.role_change', {
        name: member.user?.name,
        from: roleLabels.value[member.role],
        to: roleLabels.value[newRole],
      }),
      t('team_page.confirm.role_change_title'),
      { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    await teamApi.updateMemberRole(member.id, newRole);
    ElMessage.success(t('team_page.messages.role_updated'));
    loadTeam();
  } catch { /* cancelled */ }
}

// 移除成员
async function handleRemove(member) {
  try {
    await ElMessageBox.confirm(
      t('team_page.confirm.remove_member', { name: member.user?.name, email: member.user?.email }),
      t('team_page.confirm.remove_title'),
      { confirmButtonText: t('team_page.confirm.remove_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    await teamApi.removeMember(member.id);
    ElMessage.success(t('team_page.messages.member_removed'));
    loadTeam();
  } catch { /* cancelled */ }
}

// 转让管理员
async function handleTransferAdmin(member) {
  try {
    await ElMessageBox.confirm(
      t('team_page.confirm.transfer_admin', { name: member.user?.name, email: member.user?.email }),
      t('team_page.confirm.transfer_title'),
      { confirmButtonText: t('team_page.confirm.transfer_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    await teamApi.transferAdmin(member.id);
    ElMessage.success(t('team_page.messages.admin_transferred'));
    loadTeam();
  } catch { /* cancelled */ }
}

// 邀请操作
async function handleResend(inv) {
  try {
    await teamApi.resendInvitation(inv.id);
    ElMessage.success(t('team_page.messages.invite_resent'));
  } catch { /* ignore */ }
}

async function handleCancelInvite(inv) {
  try {
    await ElMessageBox.confirm(
      t('team_page.confirm.cancel_invite', { email: inv.email }),
      t('team_page.confirm.cancel_invite_title'),
      { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
    );
    await teamApi.cancelInvitation(inv.id);
    ElMessage.success(t('team_page.messages.invite_cancelled'));
    loadTeam();
  } catch { /* cancelled */ }
}

// 退出团队
async function handleLeave() {
  try {
    await ElMessageBox.confirm(
      t('team_page.confirm.leave_team'),
      t('team_page.confirm.leave_title'),
      { confirmButtonText: t('team_page.confirm.leave_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' }
    );
    await teamApi.leave();
    ElMessage.success(t('team_page.messages.left_team'));
    window.location.href = '/tenants';
  } catch { /* cancelled */ }
}

onMounted(() => {
  loadTeam();
});
</script>

<style scoped>
.team-management { padding: 20px; }

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
  font-size: 13px;
  color: var(--el-text-color-secondary);
  margin-left: 12px;
}
.header-right { display: flex; gap: 8px; }

.mb-4 { margin-bottom: 16px; }

.stat-box { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-color-primary); }
.stat-value.role-admin { color: var(--el-color-danger); }
.stat-value.role-developer { color: var(--el-color-primary); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.has-pending { animation: pulse 2s infinite; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.6; }
}

.member-list { display: flex; flex-direction: column; gap: 8px; }

.member-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid var(--el-border-color-lighter);
  transition: all 0.2s;
}
.member-card:hover { background: var(--el-fill-color-light); }

.member-info { flex: 1; min-width: 0; }
.member-name {
  font-size: 14px;
  font-weight: 500;
  color: var(--el-text-color-primary);
  display: flex;
  align-items: center;
  gap: 4px;
}
.member-email { font-size: 12px; color: var(--el-text-color-secondary); }
.member-meta {
  font-size: 11px;
  color: var(--el-text-color-placeholder);
  margin-top: 2px;
}
.meta-sep { margin: 0 4px; }
.member-inviter {
  font-size: 11px;
  color: var(--el-text-color-placeholder);
}

.member-role { flex-shrink: 0; min-width: 80px; text-align: center; }
.member-actions {
  flex-shrink: 0;
  display: flex;
  gap: 4px;
}

.empty-state { padding: 40px 0; }

.invitation-list { display: flex; flex-direction: column; gap: 8px; }
.invitation-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 8px;
  border: 1px solid var(--el-border-color-lighter);
  background: #fffbe6;
}
.inv-icon { color: var(--el-color-warning); }
.inv-info { flex: 1; }
.inv-email { font-weight: 500; }
.inv-role, .inv-meta, .inv-inviter {
  font-size: 12px;
  color: var(--el-text-color-secondary);
}
.inv-status { flex-shrink: 0; }
.inv-actions { flex-shrink: 0; display: flex; gap: 4px; }

.dialog-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.mb-3 { margin-bottom: 12px; }

.settings-panel { padding: 0 16px; }

.role-desc-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 8px 0;
}
.role-badge { flex-shrink: 0; margin-top: 2px; }
.role-desc-text { font-size: 13px; color: var(--el-text-color-regular); line-height: 1.5; }

.danger-zone { border: 1px solid var(--el-color-danger-light-5); border-radius: 8px; padding: 16px; }
.danger-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.danger-title { font-weight: 500; color: var(--el-color-danger); }
.danger-desc { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 2px; }
</style>
