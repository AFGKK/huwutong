<template>
    <div class="rbac-page">
        <div class="page-header">
            <div class="header-left">
                <h2>权限管理</h2>
                <span class="header-subtitle">角色定义与用户权限分配</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="activeTab = 'roles'; openRoleDialog()">
                    <el-icon><Plus /></el-icon>
                    新建角色
                </el-button>
            </div>
        </div>

        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <el-tab-pane label="角色管理" name="roles">
                    <template #label>
                        <span><el-icon><UserFilled /></el-icon> 角色管理</span>
                    </template>

                    <!-- 搜索框 -->
                    <div class="toolbar">
                        <el-input
                            v-model="roleSearch"
                            placeholder="搜索角色名称"
                            clearable
                            style="width: 240px"
                            @keyup.enter="loadRoles"
                        />
                        <el-button type="primary" @click="loadRoles">
                            <el-icon><Search /></el-icon> 搜索
                        </el-button>
                        <el-button type="success" @click="openDuplicateDialog">
                            <el-icon><CopyDocument /></el-icon> 复制角色
                        </el-button>
                    </div>

                    <el-table :data="roles" v-loading="rolesLoading" stripe>
                        <el-table-column label="角色名称" min-width="180" prop="name">
                            <template #default="{ row }">
                                <div class="role-name-cell">
                                    <el-tag :type="roleTagType(row.name)" size="small" effect="dark">
                                        {{ row.name }}
                                    </el-tag>
                                    <el-tag
                                        v-if="['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'].includes(row.name)"
                                        size="small"
                                        type="info"
                                        effect="plain"
                                        style="margin-left: 6px;"
                                    >
                                        系统
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="权限数" width="80">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions?.length || 0 }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="父角色" width="140">
                            <template #default="{ row }">
                                <span v-if="roleParentMap[row.id]" class="text-muted">
                                    ← {{ roleParentMap[row.id] }}
                                </span>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="创建时间" width="160" prop="created_at">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRoleDialog(row)">
                                    编辑权限
                                </el-button>
                                <el-button text size="small" type="primary" @click="openDuplicateDialog(row)">
                                    复制
                                </el-button>
                                <el-button
                                    v-if="!['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'].includes(row.name)"
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDeleteRole(row)"
                                >
                                    删除
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrapper" v-if="roleTotal > 0">
                        <el-pagination
                            v-model:current-page="rolePage"
                            v-model:page-size="rolePerPage"
                            :total="roleTotal"
                            size="small"
                            layout="total, prev, pager, next"
                            @current-change="loadRoles"
                        />
                    </div>
                </el-tab-pane>

                <el-tab-pane label="用户角色" name="users">
                    <template #label>
                        <span><el-icon><User /></el-icon> 用户角色</span>
                    </template>

                    <el-table :data="tenantUsers" v-loading="usersLoading" stripe>
                        <el-table-column label="用户" min-width="200">
                            <template #default="{ row }">
                                <div>
                                    <span class="user-name">{{ row.name }}</span>
                                    <div class="user-email">{{ row.email }}</div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column label="当前角色" min-width="250">
                            <template #default="{ row }">
                                <el-tag
                                    v-for="role in (row.roles || [])"
                                    :key="role.id"
                                    size="small"
                                    :type="roleTagType(role.name)"
                                    style="margin: 2px 4px 2px 0;"
                                >
                                    {{ role.name }}
                                </el-tag>
                                <span v-if="!row.roles?.length" class="text-muted">无角色</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                    {{ row.status === 'active' ? '启用' : '停用' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="140" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openUserRoleDialog(row)">
                                    分配角色
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- ═══ 角色层级 ═══ -->
                <el-tab-pane label="角色层级" name="hierarchy">
                    <template #label>
                        <span><el-icon><Share /></el-icon> 角色层级</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="loadRoleHierarchy" :loading="hierarchyLoading">
                            <el-icon><Refresh /></el-icon> 刷新层级
                        </el-button>
                        <span class="text-muted" style="margin-left: 8px; font-size: 13px;">
                            角色继承关系：子角色自动继承父角色的所有权限
                        </span>
                    </div>
                    <el-table :data="hierarchyData" v-loading="hierarchyLoading" stripe>
                        <el-table-column label="角色" min-width="180">
                            <template #default="{ row }">
                                <el-tag :type="roleTagType(row.name)" size="small" effect="dark">
                                    {{ row.name }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="权限数" width="80">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions_count }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="父角色" min-width="180">
                            <template #default="{ row }">
                                <span v-if="row.parent">
                                    <el-tag type="warning" size="small" effect="plain">
                                        ← {{ row.parent.name }}
                                    </el-tag>
                                </span>
                                <span v-else class="text-muted">无（顶级角色）</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="200">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRoleDialog(roles.find(r => r.id === row.id))">
                                    设置父角色
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- ═══ 角色模板 ═══ -->
                <el-tab-pane label="角色模板" name="templates">
                    <template #label>
                        <span><el-icon><Collection /></el-icon> 角色模板</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="openCreateTemplateDialog">
                            <el-icon><Plus /></el-icon> 新建模板
                        </el-button>
                        <el-button @click="handleSeedTemplates">
                            <el-icon><MagicStick /></el-icon> 初始化系统模板
                        </el-button>
                    </div>
                    <el-table :data="templates" v-loading="templatesLoading" stripe>
                        <el-table-column label="模板名称" min-width="180" prop="name" />
                        <el-table-column label="描述" min-width="250" prop="description">
                            <template #default="{ row }">
                                <span class="text-muted">{{ row.description || '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="分类" width="120">
                            <template #default="{ row }">
                                <el-tag size="small" :type="row.category === 'system' ? 'danger' : row.category === 'industry' ? 'warning' : ''">
                                    {{ row.category === 'system' ? '系统' : row.category === 'industry' ? '行业' : '自定义' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="权限数" width="80">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions?.length || 0 }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openCreateFromTemplateDialog(row)">
                                    创建角色
                                </el-button>
                                <el-button
                                    v-if="!row.is_system"
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDeleteTemplate(row)"
                                >
                                    删除
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- ═══ 权限审计 ═══ -->
                <el-tab-pane label="权限审计" name="audit">
                    <template #label>
                        <span><el-icon><List /></el-icon> 权限审计</span>
                    </template>
                    <div class="toolbar">
                        <el-select v-model="auditFilter.action" clearable placeholder="操作类型" style="width: 160px">
                            <el-option label="全部" value="" />
                            <el-option label="创建角色" value="role_created" />
                            <el-option label="更新角色" value="role_updated" />
                            <el-option label="删除角色" value="role_deleted" />
                            <el-option label="分配角色" value="user_role_assigned" />
                            <el-option label="分配权限" value="permission_assigned" />
                        </el-select>
                        <el-date-picker
                            v-model="auditFilter.dateRange"
                            type="daterange"
                            range-separator="至"
                            start-placeholder="开始日期"
                            end-placeholder="结束日期"
                            value-format="YYYY-MM-DD"
                            style="width: 280px"
                        />
                        <el-button type="primary" @click="loadAuditLogs">
                            <el-icon><Search /></el-icon> 查询
                        </el-button>
                        <el-button @click="loadAuditStats">
                            <el-icon><Refresh /></el-icon> 刷新统计
                        </el-button>
                    </div>

                    <!-- 审计统计卡片 -->
                    <el-row :gutter="16" class="stats-row" v-if="auditStatsData">
                        <el-col :span="6">
                            <el-statistic title="总变更次数" :value="auditStatsData.total_changes" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic title="近7天变更" :value="auditStatsData.recent_days" />
                        </el-col>
                    </el-row>

                    <el-table :data="auditLogs" v-loading="auditLoading" stripe class="audit-table">
                        <el-table-column label="时间" width="160">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column label="操作人" width="160">
                            <template #default="{ row }">
                                {{ row.user?.name || '系统' }}
                                <div class="user-email">{{ row.user?.email || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="130">
                            <template #default="{ row }">
                                <el-tag :type="auditActionTag(row.action)" size="small">
                                    {{ auditActionLabel(row.action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="目标" min-width="180" prop="target_name">
                            <template #default="{ row }">
                                <span>{{ row.target_name || '-' }}</span>
                                <span class="text-muted" style="margin-left: 4px;">({{ row.target_type }})</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="详情" width="120">
                            <template #default="{ row }">
                                <el-button v-if="row.old_values || row.new_values" text size="small" type="primary" @click="viewAuditDetail(row)">
                                    查看变更
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrapper" v-if="auditTotal > 0">
                        <el-pagination
                            v-model:current-page="auditPage"
                            v-model:page-size="auditPerPage"
                            :total="auditTotal"
                            size="small"
                            layout="total, prev, pager, next"
                            @current-change="loadAuditLogs"
                        />
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 角色编辑 Dialog（选择权限） -->
        <el-dialog
            v-model="roleDialogVisible"
            :title="editingRole ? '编辑角色权限' : '新建角色'"
            width="650px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="roleFormRef"
                :model="roleForm"
                :rules="roleFormRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item label="角色名称" prop="name">
                    <el-input v-model="roleForm.name" :disabled="!!editingRole && isSystemRole" placeholder="如：support-agent" />
                </el-form-item>
                <el-form-item label="继承父角色">
                    <el-select v-model="roleForm.parent_role_id" clearable placeholder="选择父角色（可选）" style="width: 100%">
                        <el-option
                            v-for="r in roles"
                            :key="r.id"
                            :label="r.name"
                            :value="r.id"
                            :disabled="r.id === editingRole?.id"
                        >
                            <el-tag :type="roleTagType(r.name)" size="small">{{ r.name }}</el-tag>
                            <span class="text-muted" style="margin-left: 4px;">({{ r.permissions?.length || 0 }} 权限)</span>
                        </el-option>
                    </el-select>
                    <div class="form-help" v-if="roleForm.parent_role_id">
                        子角色将自动继承父角色的所有权限
                    </div>
                </el-form-item>
                <el-form-item label="权限">
                    <div class="permission-tree" v-if="Object.keys(permissionGroups).length">
                        <div
                            v-for="(perms, group) in permissionGroups"
                            :key="group"
                            class="perm-group"
                        >
                            <div class="perm-group-header">
                                <el-checkbox
                                    :indeterminate="isIndeterminate(group)"
                                    :model-value="isGroupAllChecked(group)"
                                    @change="(val) => toggleGroup(group, val)"
                                >
                                    <span class="perm-group-label">{{ groupLabel(group) }}</span>
                                </el-checkbox>
                            </div>
                            <div class="perm-items">
                                <el-checkbox
                                    v-for="perm in perms"
                                    :key="perm.name"
                                    v-model="roleForm.permissions"
                                    :label="perm.name"
                                    size="small"
                                    class="perm-checkbox"
                                >
                                    {{ permLabel(perm.name) }}
                                </el-checkbox>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-muted">加载权限中...</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="roleDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="roleSubmitting" @click="submitRole">
                    {{ editingRole ? '保存' : '创建' }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 用户角色分配 Dialog -->
        <el-dialog
            v-model="userRoleDialogVisible"
            title="分配角色"
            width="450px"
            :close-on-click-modal="false"
        >
            <div v-if="editingUser">
                <div class="dialog-user-info">
                    <el-avatar :size="40" :src="editingUser.avatar_url">
                        <span class="avatar-initial">{{ (editingUser.name || '?').charAt(0).toUpperCase() }}</span>
                        <template #error>
                            <span class="avatar-initial">{{ (editingUser.name || '?').charAt(0).toUpperCase() }}</span>
                        </template>
                    </el-avatar>
                    <div>
                        <div class="user-name">{{ editingUser.name }}</div>
                        <div class="user-email">{{ editingUser.email }}</div>
                    </div>
                </div>
                <el-divider />
                <el-checkbox-group v-model="selectedRoles" class="role-checkbox-group">
                    <el-checkbox
                        v-for="role in roles"
                        :key="role.id"
                        :label="role.name"
                        class="role-checkbox"
                    >
                        <el-tag :type="roleTagType(role.name)" size="small">
                            {{ role.name }}
                        </el-tag>
                        <span class="role-perm-count">{{ role.permissions?.length || 0 }} 个权限</span>
                    </el-checkbox>
                </el-checkbox-group>
            </div>
            <template #footer>
                <el-button @click="userRoleDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="userRoleSubmitting" @click="submitUserRoles">保存</el-button>
            </template>
        </el-dialog>

        <!-- 审计详情 Dialog -->
        <el-dialog v-model="auditDetailVisible" title="变更详情" width="600px">
            <div v-if="auditDetailRow">
                <div class="audit-meta">
                    <el-descriptions :column="2" size="small" border>
                        <el-descriptions-item label="操作时间">{{ formatDate(auditDetailRow.created_at) }}</el-descriptions-item>
                        <el-descriptions-item label="操作人">{{ auditDetailRow.user?.name || '系统' }}</el-descriptions-item>
                        <el-descriptions-item label="操作类型">
                            <el-tag :type="auditActionTag(auditDetailRow.action)" size="small">
                                {{ auditActionLabel(auditDetailRow.action) }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item label="目标">{{ auditDetailRow.target_name }} ({{ auditDetailRow.target_type }})</el-descriptions-item>
                    </el-descriptions>
                </div>
                <el-divider />
                <div v-if="auditDetailRow.old_values" class="diff-section">
                    <h4 class="diff-title">旧值</h4>
                    <pre class="diff-pre">{{ JSON.stringify(auditDetailRow.old_values, null, 2) }}</pre>
                </div>
                <div v-if="auditDetailRow.new_values" class="diff-section">
                    <h4 class="diff-title">新值</h4>
                    <pre class="diff-pre">{{ JSON.stringify(auditDetailRow.new_values, null, 2) }}</pre>
                </div>
            </div>
            <template #footer>
                <el-button @click="auditDetailVisible = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 复制角色 Dialog -->
        <el-dialog v-model="duplicateDialogVisible" title="复制角色" width="400px">
            <el-form ref="duplicateFormRef" :model="duplicateForm" :rules="duplicateFormRules" label-width="100px">
                <el-form-item label="源角色">
                    <el-select v-model="duplicateForm.source_role_id" placeholder="选择要复制的角色" style="width: 100%">
                        <el-option
                            v-for="r in roles"
                            :key="r.id"
                            :label="r.name"
                            :value="r.id"
                        >
                            <el-tag :type="roleTagType(r.name)" size="small">{{ r.name }}</el-tag>
                            <span class="text-muted" style="margin-left: 4px;">({{ r.permissions?.length || 0 }} 权限)</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item label="新名称" prop="name">
                    <el-input v-model="duplicateForm.name" placeholder="输入新角色名称" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="duplicateDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="duplicateLoading" @click="submitDuplicate">复制</el-button>
            </template>
        </el-dialog>

        <!-- 从模板创建角色 Dialog -->
        <el-dialog v-model="createFromTemplateVisible" title="从模板创建角色" width="400px">
            <el-form ref="templateRoleFormRef" :model="templateRoleForm" :rules="templateRoleFormRules" label-width="100px">
                <el-form-item label="模板">
                    <div class="dialog-user-info" v-if="selectedTemplate">
                        <el-tag size="small">{{ selectedTemplate.name }}</el-tag>
                        <span class="text-muted">{{ selectedTemplate.description }}</span>
                    </div>
                </el-form-item>
                <el-form-item label="角色名称" prop="name">
                    <el-input v-model="templateRoleForm.name" :placeholder="'如：' + (selectedTemplate?.name || '')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createFromTemplateVisible = false">取消</el-button>
                <el-button type="primary" :loading="templateRoleLoading" @click="submitCreateFromTemplate">创建</el-button>
            </template>
        </el-dialog>

        <!-- 创建模板 Dialog -->
        <el-dialog v-model="createTemplateVisible" title="新建角色模板" width="550px">
            <el-form ref="templateFormRef" :model="templateForm" :rules="templateFormRules" label-width="100px">
                <el-form-item label="模板名称" prop="name">
                    <el-input v-model="templateForm.name" placeholder="如：客服模板" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="templateForm.description" type="textarea" :rows="2" placeholder="模板用途说明" />
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="templateForm.category" style="width: 100%">
                        <el-option label="自定义" value="custom" />
                        <el-option label="行业" value="industry" />
                    </el-select>
                </el-form-item>
                <el-form-item label="权限">
                    <div class="permission-tree" v-if="Object.keys(permissionGroups).length">
                        <div v-for="(perms, group) in permissionGroups" :key="group" class="perm-group">
                            <div class="perm-group-header">
                                <el-checkbox
                                    :indeterminate="templateIsIndeterminate(group)"
                                    :model-value="templateIsGroupAllChecked(group)"
                                    @change="(val) => templateToggleGroup(group, val)"
                                >
                                    <span class="perm-group-label">{{ groupLabel(group) }}</span>
                                </el-checkbox>
                            </div>
                            <div class="perm-items">
                                <el-checkbox
                                    v-for="perm in perms"
                                    :key="perm.name"
                                    v-model="templateForm.permissions"
                                    :label="perm.name"
                                    size="small"
                                    class="perm-checkbox"
                                >
                                    {{ permLabel(perm.name) }}
                                </el-checkbox>
                            </div>
                        </div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createTemplateVisible = false">取消</el-button>
                <el-button type="primary" :loading="templateSubmitting" @click="submitTemplate">保存模板</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, UserFilled, User, CopyDocument, Share, Collection, Refresh, List, MagicStick } from '@element-plus/icons-vue';
import permissionApi from '@/api/permission';

const activeTab = ref('roles');

// ─── Roles ───
const roles = ref([]);
const rolesLoading = ref(false);
const roleTotal = ref(0);
const rolePage = ref(1);
const rolePerPage = ref(50);
const roleSearch = ref('');

// ─── Users ───
const tenantUsers = ref([]);
const usersLoading = ref(false);

// ─── Permissions ───
const allPermissions = ref([]);
const permissionGroups = computed(() => {
    const groups = {};
    for (const p of allPermissions.value) {
        const group = p.name.split('.')[0] || 'other';
        if (!groups[group]) groups[group] = [];
        groups[group].push(p);
    }
    return groups;
});

// ─── Role Dialog ───
const roleDialogVisible = ref(false);
const roleSubmitting = ref(false);
const editingRole = ref(null);
const roleFormRef = ref(null);
const roleForm = reactive({
    name: '',
    permissions: [],
    parent_role_id: null,
});
const roleFormRules = {
    name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
};

// ─── User Role Dialog ───
const userRoleDialogVisible = ref(false);
const userRoleSubmitting = ref(false);
const editingUser = ref(null);
const selectedRoles = ref([]);

// ─── Role Hierarchy ───
const hierarchyData = ref([]);
const hierarchyLoading = ref(false);

// 角色父映射
const roleParentMap = computed(() => {
    const map = {};
    for (const item of hierarchyData.value) {
        if (item.parent) {
            map[item.id] = item.parent.name;
        }
    }
    return map;
});

// ─── Templates ───
const templates = ref([]);
const templatesLoading = ref(false);

// ─── Audit ───
const auditLogs = ref([]);
const auditLoading = ref(false);
const auditTotal = ref(0);
const auditPage = ref(1);
const auditPerPage = ref(20);
const auditFilter = reactive({
    action: '',
    dateRange: null,
});
const auditStatsData = ref(null);
const auditDetailVisible = ref(false);
const auditDetailRow = ref(null);

// ─── Duplicate Dialog ───
const duplicateDialogVisible = ref(false);
const duplicateLoading = ref(false);
const duplicateFormRef = ref(null);
const duplicateForm = reactive({
    source_role_id: null,
    name: '',
});
const duplicateFormRules = {
    source_role_id: [{ required: true, message: '请选择源角色', trigger: 'change' }],
    name: [{ required: true, message: '请输入新角色名称', trigger: 'blur' }],
};

// ─── Create from Template Dialog ───
const createFromTemplateVisible = ref(false);
const templateRoleLoading = ref(false);
const selectedTemplate = ref(null);
const templateRoleFormRef = ref(null);
const templateRoleForm = reactive({ name: '' });
const templateRoleFormRules = {
    name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
};

// ─── Create Template Dialog ───
const createTemplateVisible = ref(false);
const templateSubmitting = ref(false);
const templateFormRef = ref(null);
const templateForm = reactive({
    name: '',
    description: '',
    category: 'custom',
    permissions: [],
});
const templateFormRules = {
    name: [{ required: true, message: '请输入模板名称', trigger: 'blur' }],
};

// Helper
const systemRoles = ['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'];

function roleTagType(name) {
    const map = {
        'super-admin': 'danger',
        'tenant-admin': 'warning',
        'finance': 'success',
        'developer': 'primary',
        'readonly': 'info',
    };
    return map[name] || '';
}

function groupLabel(group) {
    const map = {
        tenants: '租户管理',
        users: '用户管理',
        customers: '客户管理',
        products: '产品管理',
        licenses: 'License 管理',
        devices: '设备管理',
        subscriptions: '订阅管理',
        invoices: '发票管理',
        earnings: '收益管理',
        logs: '日志管理',
        rbac: '权限管理',
        settings: '系统设置',
    };
    return map[group] || group;
}

function permLabel(name) {
    const map = {
        view: '查看',
        create: '创建',
        edit: '编辑',
        delete: '删除',
        activate: '激活',
        deactivate: '停用',
        revoke: '撤销',
        blacklist: '黑名单',
        cancel: '取消',
        refund: '退款',
        withdraw: '提现',
        approve_withdrawal: '审批提现',
        export: '导出',
    };
    const parts = name.split('.');
    const action = parts[parts.length - 1];
    return map[action] || action;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

const isSystemRole = computed(() => editingRole.value && systemRoles.includes(editingRole.value.name));

function isIndeterminate(group) {
    const perms = permissionGroups.value[group] || [];
    const checked = perms.filter(p => roleForm.permissions.includes(p.name)).length;
    return checked > 0 && checked < perms.length;
}

function isGroupAllChecked(group) {
    const perms = permissionGroups.value[group] || [];
    return perms.length > 0 && perms.every(p => roleForm.permissions.includes(p.name));
}

function toggleGroup(group, value) {
    const perms = permissionGroups.value[group] || [];
    const permNames = perms.map(p => p.name);
    if (value) {
        // 添加所有
        for (const name of permNames) {
            if (!roleForm.permissions.includes(name)) {
                roleForm.permissions.push(name);
            }
        }
    } else {
        // 移除所有
        roleForm.permissions = roleForm.permissions.filter(p => !permNames.includes(p));
    }
}

// 模板权限辅助
function templateIsIndeterminate(group) {
    const perms = permissionGroups.value[group] || [];
    const checked = perms.filter(p => templateForm.permissions.includes(p.name)).length;
    return checked > 0 && checked < perms.length;
}

function templateIsGroupAllChecked(group) {
    const perms = permissionGroups.value[group] || [];
    return perms.length > 0 && perms.every(p => templateForm.permissions.includes(p.name));
}

function templateToggleGroup(group, value) {
    const perms = permissionGroups.value[group] || [];
    const permNames = perms.map(p => p.name);
    if (value) {
        for (const name of permNames) {
            if (!templateForm.permissions.includes(name)) {
                templateForm.permissions.push(name);
            }
        }
    } else {
        templateForm.permissions = templateForm.permissions.filter(p => !permNames.includes(p));
    }
}

// 审计标签
function auditActionTag(action) {
    const map = { role_created: 'success', role_updated: 'warning', role_deleted: 'danger', user_role_assigned: 'primary', permission_assigned: 'info' };
    return map[action] || '';
}

function auditActionLabel(action) {
    const map = { role_created: '创建角色', role_updated: '更新角色', role_deleted: '删除角色', user_role_assigned: '分配角色', permission_assigned: '分配权限' };
    return map[action] || action;
}

// 加载角色
async function loadRoles() {
    rolesLoading.value = true;
    try {
        const params = { page: rolePage.value, per_page: rolePerPage.value };
        if (roleSearch.value) params.search = roleSearch.value;
        const { data: res } = await permissionApi.roles(params);
        roles.value = res.data || [];
        roleTotal.value = res.meta?.total || 0;
    } catch {
        roles.value = [];
    } finally {
        rolesLoading.value = false;
    }
}

// 加载权限
async function loadPermissions() {
    try {
        const { data: res } = await permissionApi.allPermissions();
        if (res.success) {
            allPermissions.value = res.data.all || [];
        }
    } catch {
        // ignore
    }
}

// 加载用户
async function loadUsers() {
    usersLoading.value = true;
    try {
        const { data: res } = await permissionApi.tenantUsers();
        if (res.success) {
            tenantUsers.value = res.data || [];
        }
    } catch {
        tenantUsers.value = [];
    } finally {
        usersLoading.value = false;
    }
}

async function loadRoleHierarchy() {
    hierarchyLoading.value = true;
    try {
        const { data: res } = await permissionApi.roleHierarchy();
        if (res.success) {
            hierarchyData.value = res.data || [];
        }
    } catch {
        hierarchyData.value = [];
    } finally {
        hierarchyLoading.value = false;
    }
}

async function loadTemplates() {
    templatesLoading.value = true;
    try {
        const { data: res } = await permissionApi.roleTemplates();
        if (res.success) {
            templates.value = res.data || [];
        }
    } catch {
        templates.value = [];
    } finally {
        templatesLoading.value = false;
    }
}

async function loadAuditLogs() {
    auditLoading.value = true;
    try {
        const params = { page: auditPage.value, per_page: auditPerPage.value };
        if (auditFilter.action) params.action = auditFilter.action;
        if (auditFilter.dateRange) {
            params.date_from = auditFilter.dateRange[0];
            params.date_to = auditFilter.dateRange[1];
        }
        const { data: res } = await permissionApi.auditLogs(params);
        if (res.success) {
            auditLogs.value = res.data || [];
            auditTotal.value = res.meta?.total || 0;
        }
    } catch {
        auditLogs.value = [];
    } finally {
        auditLoading.value = false;
    }
}

async function loadAuditStats() {
    try {
        const { data: res } = await permissionApi.auditStats();
        if (res.success) {
            auditStatsData.value = res.data;
        }
    } catch {
        auditStatsData.value = null;
    }
}

// Tab 切换
function handleTabChange(tab) {
    if (tab === 'users' && tenantUsers.value.length === 0) {
        loadUsers();
    }
    if (tab === 'hierarchy') {
        loadRoleHierarchy();
    }
    if (tab === 'templates') {
        loadTemplates();
    }
    if (tab === 'audit') {
        loadAuditLogs();
        loadAuditStats();
    }
}

// 角色 Dialog
async function openRoleDialog(role = null) {
    editingRole.value = role;
    roleForm.name = role ? role.name : '';
    roleForm.permissions = role ? (role.permissions || []).map(p => p.name || p) : [];
    if (role) {
        const parent = hierarchyData.value.find(h => h.id === role.id);
        roleForm.parent_role_id = parent?.parent?.id || null;
    } else {
        roleForm.parent_role_id = null;
    }

    // 确保权限已加载
    if (allPermissions.value.length === 0) {
        await loadPermissions();
    }

    roleDialogVisible.value = true;
}

async function submitRole() {
    const valid = await roleFormRef.value.validate().catch(() => false);
    if (!valid) return;

    roleSubmitting.value = true;
    try {
        const payload = {
            name: roleForm.name,
            permissions: roleForm.permissions,
            parent_role_id: roleForm.parent_role_id || null,
        };
        if (editingRole.value) {
            await permissionApi.roleUpdate(editingRole.value.id, payload);
            ElMessage.success('角色更新成功');
        } else {
            await permissionApi.roleCreate(payload);
            ElMessage.success('角色创建成功');
        }
        roleDialogVisible.value = false;
        loadRoles();
    } catch {
        // handled by interceptor
    } finally {
        roleSubmitting.value = false;
    }
}

async function handleDeleteRole(row) {
    try {
        await ElMessageBox.confirm(
            `确定要删除角色 "${row.name}" 吗？`,
            '确认删除',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await permissionApi.roleDelete(row.id);
        ElMessage.success('角色已删除');
        loadRoles();
    } catch {
        // cancelled
    }
}

// 用户角色分配
async function openUserRoleDialog(user) {
    editingUser.value = user;
    selectedRoles.value = (user.roles || []).map(r => r.name);
    userRoleDialogVisible.value = true;
}

async function submitUserRoles() {
    if (!editingUser.value) return;

    userRoleSubmitting.value = true;
    try {
        await permissionApi.assignRoles(editingUser.value.id, selectedRoles.value);
        ElMessage.success('角色分配成功');
        userRoleDialogVisible.value = false;
        loadUsers();
    } catch {
        // handled by interceptor
    } finally {
        userRoleSubmitting.value = false;
    }
}

// ══════════════════════════════════════════
//  复制角色
// ══════════════════════════════════════════

function openDuplicateDialog(role = null) {
    duplicateForm.source_role_id = role?.id || null;
    duplicateForm.name = role ? role.name + ' (副本)' : '';
    duplicateDialogVisible.value = true;
}

async function submitDuplicate() {
    const valid = await duplicateFormRef.value.validate().catch(() => false);
    if (!valid) return;
    duplicateLoading.value = true;
    try {
        await permissionApi.roleDuplicate(duplicateForm.source_role_id, { name: duplicateForm.name });
        ElMessage.success('角色复制成功');
        duplicateDialogVisible.value = false;
        loadRoles();
    } catch {} finally {
        duplicateLoading.value = false;
    }
}

// ══════════════════════════════════════════
//  角色模板
// ══════════════════════════════════════════

function openCreateFromTemplateDialog(template) {
    selectedTemplate.value = template;
    templateRoleForm.name = '';
    createFromTemplateVisible.value = true;
}

async function submitCreateFromTemplate() {
    const valid = await templateRoleFormRef.value.validate().catch(() => false);
    if (!valid || !selectedTemplate.value) return;
    templateRoleLoading.value = true;
    try {
        await permissionApi.createRoleFromTemplate(selectedTemplate.value.id, { name: templateRoleForm.name });
        ElMessage.success('角色创建成功');
        createFromTemplateVisible.value = false;
        loadRoles();
    } catch {} finally {
        templateRoleLoading.value = false;
    }
}

function openCreateTemplateDialog() {
    templateForm.name = '';
    templateForm.description = '';
    templateForm.category = 'custom';
    templateForm.permissions = [];
    createTemplateVisible.value = true;
}

async function submitTemplate() {
    const valid = await templateFormRef.value.validate().catch(() => false);
    if (!valid) return;
    templateSubmitting.value = true;
    try {
        await permissionApi.templateStore({
            name: templateForm.name,
            description: templateForm.description,
            category: templateForm.category,
            permissions: templateForm.permissions,
        });
        ElMessage.success('模板创建成功');
        createTemplateVisible.value = false;
        loadTemplates();
    } catch {} finally {
        templateSubmitting.value = false;
    }
}

async function handleSeedTemplates() {
    try {
        await ElMessageBox.confirm(
            '将初始化系统预置角色模板（技术支持、运营人员、审计员、销售代表等）。继续吗？',
            '初始化系统模板',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'info' }
        );
        const { data: res } = await permissionApi.seedTemplates();
        if (res.success) {
            ElMessage.success('系统模板已初始化');
            loadTemplates();
        }
    } catch {}
}

async function handleDeleteTemplate(row) {
    try {
        await ElMessageBox.confirm(
            `确定要删除模板 "${row.name}" 吗？`,
            '确认删除',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await permissionApi.templateDelete(row.id);
        ElMessage.success('模板已删除');
        loadTemplates();
    } catch {}
}

// ══════════════════════════════════════════
//  审计日志
// ══════════════════════════════════════════

function viewAuditDetail(row) {
    auditDetailRow.value = row;
    auditDetailVisible.value = true;
}

onMounted(() => {
    loadRoles();
    loadPermissions();
});
</script>

<style scoped>
.rbac-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0;
    font-size: 20px;
}
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.toolbar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.role-name-cell {
    display: flex;
    align-items: center;
}

.permission-tree {
    max-height: 450px;
    overflow-y: auto;
    padding: 4px 0;
}

.perm-group {
    margin-bottom: 12px;
    padding: 8px 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
}
.perm-group-header {
    margin-bottom: 8px;
    font-weight: 500;
}
.perm-group-label {
    font-size: 14px;
}
.perm-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    padding-left: 24px;
}
.perm-checkbox {
    margin-right: 12px !important;
}

.dialog-user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}
.user-name {
    font-weight: 500;
}
.user-email {
    font-size: 13px;
    color: var(--el-text-color-secondary);
}

.role-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.role-checkbox {
    margin-right: 0 !important;
    padding: 8px 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    transition: all 0.2s;
}
.role-checkbox:hover {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}
.role-perm-count {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 8px;
}

.text-muted { color: var(--el-text-color-placeholder); }

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}

.stats-row {
    margin-bottom: 16px;
}

.audit-table {
    margin-top: 8px;
}

.audit-meta {
    margin-bottom: 8px;
}

.diff-section {
    margin-top: 8px;
}

.diff-title {
    font-size: 14px;
    font-weight: 500;
    margin: 0 0 8px 0;
    color: var(--el-text-color-primary);
}

.diff-pre {
    background: var(--el-fill-color-light);
    border: 1px solid var(--el-border-color-light);
    border-radius: 4px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.5;
    overflow-x: auto;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

.form-help {
    font-size: 12px;
    color: var(--el-color-primary);
    margin-top: 4px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
