<template>
  <div class="team-management">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>团队管理</h2>
        <span class="header-subtitle" v-if="tenant">{{ tenant.name }}</span>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="showInviteDialog = true" :disabled="!canInvite">
          <el-icon><Plus /></el-icon> 邀请成员
        </el-button>
        <el-button @click="showSettings = !showSettings">
          <el-icon><Setting /></el-icon> 团队设置
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ memberCount }}</div>
            <div class="stat-label">团队成员</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value role-admin">{{ adminCount }}</div>
            <div class="stat-label">管理员</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value role-developer">{{ developerCount }}</div>
            <div class="stat-label">开发者</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value warning" :class="{ 'has-pending': pendingCount > 0 }">{{ pendingCount }}</div>
            <div class="stat-label">待处理邀请</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主内容区 -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- 成员列表 -->
        <el-tab-pane label="所有成员" name="members">
          <div class="member-list">
            <div v-for="member in members" :key="member.id" class="member-card">
              <el-avatar :size="40">{{ member.user?.name?.charAt(0) || '?' }}</el-avatar>
              <div class="member-info">
                <div class="member-name">
                  {{ member.user?.name || '未知用户' }}
                  <el-tag v-if="member.user_id === currentUserId" size="small" type="info">我</el-tag>
                </div>
                <div class="member-email">{{ member.user?.email }}</div>
                <div class="member-meta">
                  加入于 {{ formatDate(member.joined_at) }}
                  <span class="meta-sep">·</span>
                  通过 {{ member.invited_via_label || '直接添加' }}
                  <span class="meta-sep">·</span>
                  最后活跃 {{ member.user?.last_login_at ? formatDate(member.user.last_login_at) : '未知' }}
                </div>
                <div class="member-inviter" v-if="member.inviter">
                  邀请人: {{ member.inviter.name }}
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
                <el-button text type="primary" size="small" @click="handleTransferAdmin(member)">转让</el-button>
                <el-button text type="danger" size="small" @click="handleRemove(member)">移除</el-button>
              </div>
            </div>

            <div v-if="members.length === 0" class="empty-state">
              <el-empty description="暂无团队成员" />
            </div>
          </div>
        </el-tab-pane>

        <!-- 待处理邀请 -->
        <el-tab-pane :label="`待处理邀请 (${pendingInvitations.length})`" name="invitations">
          <div v-if="pendingInvitations.length === 0" class="empty-state">
            <el-empty description="暂无待处理邀请" />
          </div>
          <div v-else class="invitation-list">
            <div v-for="inv in pendingInvitations" :key="inv.id" class="invitation-card">
              <el-icon class="inv-icon" :size="28"><Message /></el-icon>
              <div class="inv-info">
                <div class="inv-email">{{ inv.email }}</div>
                <div class="inv-role">角色: {{ roleLabels[inv.role] || inv.role }}</div>
                <div class="inv-meta">
                  邀请于 {{ formatDate(inv.created_at) }}
                  <span v-if="inv.expires_at"> · 过期于 {{ formatDate(inv.expires_at) }}</span>
                </div>
                <div class="inv-inviter" v-if="inv.inviter">
                  邀请人: {{ inv.inviter.name }}
                </div>
              </div>
              <div class="inv-status">
                <el-tag size="small" type="warning">等待接受</el-tag>
              </div>
              <div class="inv-actions">
                <el-button text size="small" type="primary" @click="handleResend(inv)">重发</el-button>
                <el-button text size="small" type="danger" @click="handleCancelInvite(inv)">取消</el-button>
              </div>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 邀请成员对话框 -->
    <el-dialog v-model="showInviteDialog" title="邀请成员" width="520px">
      <el-form ref="inviteFormRef" :model="inviteForm" :rules="inviteRules" label-width="80px">
        <!-- 单条邀请模式 -->
        <template v-if="!batchMode">
          <el-form-item label="邮箱" prop="email">
            <el-input v-model="inviteForm.email" placeholder="输入成员邮箱" />
          </el-form-item>
          <el-form-item label="角色" prop="role">
            <el-select v-model="inviteForm.role" style="width: 100%">
              <el-option v-for="(label, key) in roleLabels" :key="key" :label="label" :value="key" />
            </el-select>
          </el-form-item>
          <el-form-item label="附言">
            <el-input v-model="inviteForm.message" type="textarea" :rows="2" placeholder="可选：给被邀请人的留言" maxlength="500" show-word-limit />
          </el-form-item>
        </template>
        <!-- 批量邀请模式 -->
        <template v-else>
          <el-alert title="批量邀请" description="每行一个邮箱，格式: email,角色(可选)。例如: user@example.com,developer" type="info" show-icon :closable="false" class="mb-3" />
          <el-form-item label="邀请列表">
            <el-input
              v-model="inviteForm.batchText"
              type="textarea"
              :rows="6"
              placeholder="user1@example.com,developer&#10;user2@example.com,finance&#10;user3@example.com"
            />
          </el-form-item>
        </template>
      </el-form>
      <template #footer>
        <div class="dialog-footer">
          <el-button @click="batchMode = !batchMode" text>
            {{ batchMode ? '切换为单个邀请' : '批量邀请' }}
          </el-button>
          <el-button @click="showInviteDialog = false">取消</el-button>
          <el-button type="primary" @click="handleInvite" :loading="inviting">
            {{ batchMode ? '批量发送' : '发送邀请' }}
          </el-button>
        </div>
      </template>
    </el-dialog>

    <!-- 团队设置面板 -->
    <el-drawer v-model="showSettings" title="团队设置" size="400px">
      <div class="settings-panel">
        <el-divider content-position="left">角色说明</el-divider>
        <div v-for="(desc, role) in roleDescriptions" :key="role" class="role-desc-item">
          <el-tag :type="roleTagType(role)" size="small" class="role-badge">{{ roleLabels[role] }}</el-tag>
          <span class="role-desc-text">{{ desc }}</span>
        </div>

        <el-divider content-position="left">危险操作</el-divider>
        <div class="danger-zone">
          <div class="danger-item">
            <div class="danger-info">
              <div class="danger-title">退出团队</div>
              <div class="danger-desc">退出后将失去对租户资源的访问权限</div>
            </div>
            <el-button type="danger" plain @click="handleLeave">退出团队</el-button>
          </div>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Setting, ArrowDown, Message } from '@element-plus/icons-vue';
import teamApi from '@/api/team';

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
const roleLabels = {
  admin: '管理员',
  finance: '财务',
  developer: '开发者',
  readonly: '只读',
};
const roleDescriptions = {
  admin: '完全控制：管理团队、设置、账单和所有资源',
  finance: '财务管理：查看发票、账单和支付信息',
  developer: '开发权限：管理 License、API Key、SDK 配置',
  readonly: '仅查看：访问仪表盘和报表，不能做任何变更',
};
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
const inviteRules = {
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '请输入有效的邮箱', trigger: 'blur' },
  ],
  role: [{ required: true, message: '请选择角色', trigger: 'change' }],
};

function isCurrentUser(member) {
  return member.user_id === currentUserId.value;
}

function formatDate(dateStr) {
  if (!dateStr) return '-';
  return new Date(dateStr).toLocaleString('zh-CN', {
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
    ElMessage.error('加载团队信息失败');
  } finally {
    loading.value = false;
  }
}

// 邀请
async function handleInvite() {
  inviting.value = true;
  try {
    if (batchMode.value) {
      // 批量邀请
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
      ElMessage.success(`已发送 ${invites.length} 份邀请`);
    } else {
      // 单条邀请
      await inviteFormRef.value.validate();
      await teamApi.invite({
        email: inviteForm.email,
        role: inviteForm.role,
        message: inviteForm.message || null,
      });
      ElMessage.success(`邀请已发送至 ${inviteForm.email}`);
    }
    showInviteDialog.value = false;
    inviteForm.email = '';
    inviteForm.message = '';
    inviteForm.batchText = '';
    loadTeam();
  } catch (e) {
    const msg = e.response?.data?.message || '邀请发送失败';
    ElMessage.error(msg);
  } finally {
    inviting.value = false;
  }
}

// 角色变更
async function handleRoleChange(member, newRole) {
  try {
    await ElMessageBox.confirm(
      `确定将 ${member.user?.name} 的角色从「${roleLabels[member.role]}」变更为「${roleLabels[newRole]}」？`,
      '变更角色',
      { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    );
    await teamApi.updateMemberRole(member.id, newRole);
    ElMessage.success('角色已更新');
    loadTeam();
  } catch { /* cancelled */ }
}

// 移除成员
async function handleRemove(member) {
  try {
    await ElMessageBox.confirm(
      `确定移除 ${member.user?.name}（${member.user?.email}）？该成员将失去对本租户资源的访问权限。`,
      '移除成员',
      { confirmButtonText: '确定移除', cancelButtonText: '取消', type: 'warning' }
    );
    await teamApi.removeMember(member.id);
    ElMessage.success('成员已移除');
    loadTeam();
  } catch { /* cancelled */ }
}

// 转让管理员
async function handleTransferAdmin(member) {
  try {
    await ElMessageBox.confirm(
      `确定将管理员权限转让给 ${member.user?.name}（${member.user?.email}）？转让后您仍保留管理员角色。`,
      '转让管理员',
      { confirmButtonText: '确定转让', cancelButtonText: '取消', type: 'warning' }
    );
    await teamApi.transferAdmin(member.id);
    ElMessage.success('管理员权限已转让');
    loadTeam();
  } catch { /* cancelled */ }
}

// 邀请操作
async function handleResend(inv) {
  try {
    await teamApi.resendInvitation(inv.id);
    ElMessage.success('邀请已重新发送');
  } catch { /* ignore */ }
}

async function handleCancelInvite(inv) {
  try {
    await ElMessageBox.confirm(`确定取消对 ${inv.email} 的邀请？`, '取消邀请', {
      confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
    });
    await teamApi.cancelInvitation(inv.id);
    ElMessage.success('邀请已取消');
    loadTeam();
  } catch { /* cancelled */ }
}

// 退出团队
async function handleLeave() {
  try {
    await ElMessageBox.confirm(
      '确定要退出当前团队？退出后将失去对租户内所有资源的访问权限。',
      '退出团队',
      { confirmButtonText: '确定退出', cancelButtonText: '取消', type: 'warning' }
    );
    await teamApi.leave();
    ElMessage.success('已退出团队');
    // 重定向到租户选择页
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
