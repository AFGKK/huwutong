<template>
    <div class="rbac-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('rbac_page.title') }}</h2>
                <span class="header-subtitle">{{ t('rbac_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="activeTab = 'roles'; openRoleDialog()">
                    <el-icon><Plus /></el-icon>
                    {{ t('rbac_page.buttons.new_role') }}
                </el-button>
            </div>
        </div>

        <el-card shadow="never">
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <el-tab-pane :label="t('rbac_page.tabs.roles')" name="roles">
                    <template #label>
                        <span><el-icon><UserFilled /></el-icon> {{ t('rbac_page.tabs.roles') }}</span>
                    </template>

                    <!-- 搜索框 -->
                    <div class="toolbar">
                        <el-input
                            v-model="roleSearch"
                            :placeholder="t('rbac_page.toolbar.search_role_ph')"
                            clearable
                            style="width: 240px"
                            @keyup.enter="loadRoles"
                        />
                        <el-button type="primary" @click="loadRoles">
                            <el-icon><Search /></el-icon> {{ t('actions.search') }}
                        </el-button>
                        <el-button type="success" @click="openDuplicateDialog">
                            <el-icon><CopyDocument /></el-icon> {{ t('rbac_page.toolbar.duplicate_role') }}
                        </el-button>
                    </div>

                    <el-table :data="roles" v-loading="rolesLoading" stripe>
                        <el-table-column :label="t('rbac_page.cols.role_name')" min-width="180" prop="name">
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
                                        {{ t('rbac_page.badges.system') }}
                                    </el-tag>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.perm_count')" width="80">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions?.length || 0 }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.parent_role')" width="140">
                            <template #default="{ row }">
                                <span v-if="roleParentMap[row.id]" class="text-muted">
                                    ← {{ roleParentMap[row.id] }}
                                </span>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.created_at')" width="160" prop="created_at">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.actions')" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRoleDialog(row)">
                                    {{ t('rbac_page.buttons.edit_permissions') }}
                                </el-button>
                                <el-button text size="small" type="primary" @click="openDuplicateDialog(row)">
                                    {{ t('actions.copy') }}
                                </el-button>
                                <el-button
                                    v-if="!['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'].includes(row.name)"
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDeleteRole(row)"
                                >
                                    {{ t('actions.delete') }}
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

                <el-tab-pane :label="t('rbac_page.tabs.users')" name="users">
                    <template #label>
                        <span><el-icon><User /></el-icon> {{ t('rbac_page.tabs.users') }}</span>
                    </template>

                    <el-table :data="tenantUsers" v-loading="usersLoading" stripe>
                        <el-table-column :label="t('rbac_page.cols.user')" min-width="200">
                            <template #default="{ row }">
                                <div>
                                    <span class="user-name">{{ row.name }}</span>
                                    <div class="user-email">{{ row.email }}</div>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.current_roles')" min-width="250">
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
                                <span v-if="!row.roles?.length" class="text-muted">{{ t('rbac_page.empty.no_roles') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
                                    {{ row.status === 'active' ? t('rbac_page.status.active') : t('rbac_page.status.inactive') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.actions')" width="140" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openUserRoleDialog(row)">
                                    {{ t('rbac_page.buttons.assign_roles') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- ═══ 角色层级 ═══ -->
                <el-tab-pane :label="t('rbac_page.tabs.hierarchy')" name="hierarchy">
                    <template #label>
                        <span><el-icon><Share /></el-icon> {{ t('rbac_page.tabs.hierarchy') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="loadRoleHierarchy" :loading="hierarchyLoading">
                            <el-icon><Refresh /></el-icon> {{ t('rbac_page.toolbar.refresh_hierarchy') }}
                        </el-button>
                        <span class="text-muted" style="margin-left: 8px; font-size: 13px;">
                            {{ t('rbac_page.toolbar.hierarchy_hint') }}
                        </span>
                    </div>
                    <el-table :data="hierarchyData" v-loading="hierarchyLoading" stripe>
                        <el-table-column :label="t('rbac_page.cols.role')" min-width="180">
                            <template #default="{ row }">
                                <el-tag :type="roleTagType(row.name)" size="small" effect="dark">
                                    {{ row.name }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.perm_count')" width="80">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions_count }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.parent_role')" min-width="180">
                            <template #default="{ row }">
                                <span v-if="row.parent">
                                    <el-tag type="warning" size="small" effect="plain">
                                        ← {{ row.parent.name }}
                                    </el-tag>
                                </span>
                                <span v-else class="text-muted">{{ t('rbac_page.empty.no_parent_top') }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.actions')" width="200">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openRoleDialog(roles.find(r => r.id === row.id))">
                                    {{ t('rbac_page.buttons.set_parent_role') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- ═══ 角色模板 ═══ -->
                <el-tab-pane :label="t('rbac_page.tabs.templates')" name="templates">
                    <template #label>
                        <span><el-icon><Collection /></el-icon> {{ t('rbac_page.tabs.templates') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-button type="primary" @click="openCreateTemplateDialog">
                            <el-icon><Plus /></el-icon> {{ t('rbac_page.toolbar.new_template') }}
                        </el-button>
                        <el-button @click="handleSeedTemplates">
                            <el-icon><MagicStick /></el-icon> {{ t('rbac_page.toolbar.seed_templates') }}
                        </el-button>
                    </div>
                    <el-table :data="templates" v-loading="templatesLoading" stripe>
                        <el-table-column :label="t('rbac_page.cols.template_name')" min-width="180" prop="name" />
                        <el-table-column :label="t('rbac_page.cols.description')" min-width="250" prop="description">
                            <template #default="{ row }">
                                <span class="text-muted">{{ row.description || '-' }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.category')" width="120">
                            <template #default="{ row }">
                                <el-tag size="small" :type="row.category === 'system' ? 'danger' : row.category === 'industry' ? 'warning' : ''">
                                    {{ categoryLabel(row.category) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.perm_count')" width="80">
                            <template #default="{ row }">
                                <el-tag type="primary" effect="plain" size="small">
                                    {{ row.permissions?.length || 0 }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.actions')" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openCreateFromTemplateDialog(row)">
                                    {{ t('rbac_page.buttons.create_role') }}
                                </el-button>
                                <el-button
                                    v-if="!row.is_system"
                                    text
                                    size="small"
                                    type="danger"
                                    @click="handleDeleteTemplate(row)"
                                >
                                    {{ t('actions.delete') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- ═══ 权限审计 ═══ -->
                <el-tab-pane :label="t('rbac_page.tabs.audit')" name="audit">
                    <template #label>
                        <span><el-icon><List /></el-icon> {{ t('rbac_page.tabs.audit') }}</span>
                    </template>
                    <div class="toolbar">
                        <el-select v-model="auditFilter.action" clearable :placeholder="t('rbac_page.toolbar.audit_action_ph')" style="width: 160px">
                            <el-option
                                v-for="opt in auditActionOptions"
                                :key="opt.value"
                                :label="opt.label"
                                :value="opt.value"
                            />
                        </el-select>
                        <el-date-picker
                            v-model="auditFilter.dateRange"
                            type="daterange"
                            :range-separator="t('rbac_page.audit.date_sep')"
                            :start-placeholder="t('rbac_page.audit.date_start')"
                            :end-placeholder="t('rbac_page.audit.date_end')"
                            value-format="YYYY-MM-DD"
                            style="width: 280px"
                        />
                        <el-button type="primary" @click="loadAuditLogs">
                            <el-icon><Search /></el-icon> {{ t('rbac_page.toolbar.query') }}
                        </el-button>
                        <el-button @click="loadAuditStats">
                            <el-icon><Refresh /></el-icon> {{ t('rbac_page.toolbar.refresh_stats') }}
                        </el-button>
                    </div>

                    <!-- 审计统计卡片 -->
                    <el-row :gutter="16" class="stats-row" v-if="auditStatsData">
                        <el-col :span="6">
                            <el-statistic :title="t('rbac_page.audit.stat_total')" :value="auditStatsData.total_changes" />
                        </el-col>
                        <el-col :span="6">
                            <el-statistic :title="t('rbac_page.audit.stat_recent')" :value="auditStatsData.recent_days" />
                        </el-col>
                    </el-row>

                    <el-table :data="auditLogs" v-loading="auditLoading" stripe class="audit-table">
                        <el-table-column :label="t('rbac_page.cols.time')" width="160">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.operator')" width="160">
                            <template #default="{ row }">
                                {{ row.user?.name || t('rbac_page.badges.system') }}
                                <div class="user-email">{{ row.user?.email || '-' }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.actions')" width="130">
                            <template #default="{ row }">
                                <el-tag :type="auditActionTag(row.action)" size="small">
                                    {{ auditActionLabel(row.action) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.target')" min-width="180" prop="target_name">
                            <template #default="{ row }">
                                <span>{{ row.target_name || '-' }}</span>
                                <span class="text-muted" style="margin-left: 4px;">({{ row.target_type }})</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('rbac_page.cols.detail')" width="120">
                            <template #default="{ row }">
                                <el-button v-if="row.old_values || row.new_values" text size="small" type="primary" @click="viewAuditDetail(row)">
                                    {{ t('rbac_page.buttons.view_changes') }}
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
            :title="editingRole ? t('rbac_page.dialogs.edit_role') : t('rbac_page.dialogs.new_role')"
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
                <el-form-item :label="t('rbac_page.form.role_name')" prop="name">
                    <el-input v-model="roleForm.name" :disabled="!!editingRole && isSystemRole" :placeholder="t('rbac_page.placeholders.role_name')" />
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.inherit_parent')">
                    <el-select v-model="roleForm.parent_role_id" clearable :placeholder="t('rbac_page.placeholders.parent_role')" style="width: 100%">
                        <el-option
                            v-for="r in roles"
                            :key="r.id"
                            :label="r.name"
                            :value="r.id"
                            :disabled="r.id === editingRole?.id"
                        >
                            <el-tag :type="roleTagType(r.name)" size="small">{{ r.name }}</el-tag>
                            <span class="text-muted" style="margin-left: 4px;">{{ t('rbac_page.perm_count', { n: r.permissions?.length || 0 }) }}</span>
                        </el-option>
                    </el-select>
                    <div class="form-help" v-if="roleForm.parent_role_id">
                        {{ t('rbac_page.hints.inherit_parent') }}
                    </div>
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.permissions')">
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
                    <div v-else class="text-muted">{{ t('rbac_page.loading_permissions') }}</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="roleDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="roleSubmitting" @click="submitRole">
                    {{ editingRole ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 用户角色分配 Dialog -->
        <el-dialog
            v-model="userRoleDialogVisible"
            :title="t('rbac_page.dialogs.assign_role')"
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
                        <span class="role-perm-count">{{ t('rbac_page.perm_count_unit', { n: role.permissions?.length || 0 }) }}</span>
                    </el-checkbox>
                </el-checkbox-group>
            </div>
            <template #footer>
                <el-button @click="userRoleDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="userRoleSubmitting" @click="submitUserRoles">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <!-- 审计详情 Dialog -->
        <el-dialog v-model="auditDetailVisible" :title="t('rbac_page.dialogs.audit_detail')" width="600px">
            <div v-if="auditDetailRow">
                <div class="audit-meta">
                    <el-descriptions :column="2" size="small" border>
                        <el-descriptions-item :label="t('rbac_page.form.operated_at')">{{ formatDate(auditDetailRow.created_at) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('rbac_page.form.operator')">{{ auditDetailRow.user?.name || t('rbac_page.badges.system') }}</el-descriptions-item>
                        <el-descriptions-item :label="t('rbac_page.form.action_type')">
                            <el-tag :type="auditActionTag(auditDetailRow.action)" size="small">
                                {{ auditActionLabel(auditDetailRow.action) }}
                            </el-tag>
                        </el-descriptions-item>
                        <el-descriptions-item :label="t('rbac_page.form.target')">{{ auditDetailRow.target_name }} ({{ auditDetailRow.target_type }})</el-descriptions-item>
                    </el-descriptions>
                </div>
                <el-divider />
                <div v-if="auditDetailRow.old_values" class="diff-section">
                    <h4 class="diff-title">{{ t('rbac_page.diff.old_values') }}</h4>
                    <pre class="diff-pre">{{ JSON.stringify(auditDetailRow.old_values, null, 2) }}</pre>
                </div>
                <div v-if="auditDetailRow.new_values" class="diff-section">
                    <h4 class="diff-title">{{ t('rbac_page.diff.new_values') }}</h4>
                    <pre class="diff-pre">{{ JSON.stringify(auditDetailRow.new_values, null, 2) }}</pre>
                </div>
            </div>
            <template #footer>
                <el-button @click="auditDetailVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 复制角色 Dialog -->
        <el-dialog v-model="duplicateDialogVisible" :title="t('rbac_page.dialogs.duplicate_role')" width="400px">
            <el-form ref="duplicateFormRef" :model="duplicateForm" :rules="duplicateFormRules" label-width="100px">
                <el-form-item :label="t('rbac_page.form.source_role')">
                    <el-select v-model="duplicateForm.source_role_id" :placeholder="t('rbac_page.placeholders.source_role')" style="width: 100%">
                        <el-option
                            v-for="r in roles"
                            :key="r.id"
                            :label="r.name"
                            :value="r.id"
                        >
                            <el-tag :type="roleTagType(r.name)" size="small">{{ r.name }}</el-tag>
                            <span class="text-muted" style="margin-left: 4px;">{{ t('rbac_page.perm_count', { n: r.permissions?.length || 0 }) }}</span>
                        </el-option>
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.new_name')" prop="name">
                    <el-input v-model="duplicateForm.name" :placeholder="t('rbac_page.placeholders.new_role_name')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="duplicateDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="duplicateLoading" @click="submitDuplicate">{{ t('actions.copy') }}</el-button>
            </template>
        </el-dialog>

        <!-- 从模板创建角色 Dialog -->
        <el-dialog v-model="createFromTemplateVisible" :title="t('rbac_page.dialogs.create_from_template')" width="400px">
            <el-form ref="templateRoleFormRef" :model="templateRoleForm" :rules="templateRoleFormRules" label-width="100px">
                <el-form-item :label="t('rbac_page.form.template')">
                    <div class="dialog-user-info" v-if="selectedTemplate">
                        <el-tag size="small">{{ selectedTemplate.name }}</el-tag>
                        <span class="text-muted">{{ selectedTemplate.description }}</span>
                    </div>
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.role_name')" prop="name">
                    <el-input v-model="templateRoleForm.name" :placeholder="t('rbac_page.placeholders.role_from_template', { name: selectedTemplate?.name || '' })" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="createFromTemplateVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="templateRoleLoading" @click="submitCreateFromTemplate">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- 创建模板 Dialog -->
        <el-dialog v-model="createTemplateVisible" :title="t('rbac_page.dialogs.create_template')" width="550px">
            <el-form ref="templateFormRef" :model="templateForm" :rules="templateFormRules" label-width="100px">
                <el-form-item :label="t('rbac_page.cols.template_name')" prop="name">
                    <el-input v-model="templateForm.name" :placeholder="t('rbac_page.placeholders.template_name')" />
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.description')" prop="description">
                    <el-input v-model="templateForm.description" type="textarea" :rows="2" :placeholder="t('rbac_page.placeholders.template_desc')" />
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.category')">
                    <el-select v-model="templateForm.category" style="width: 100%">
                        <el-option v-for="opt in templateCategoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('rbac_page.form.permissions')">
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
                <el-button @click="createTemplateVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="templateSubmitting" @click="submitTemplate">{{ t('rbac_page.buttons.save_template') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Plus, UserFilled, User, CopyDocument, Share, Collection, Refresh, List, MagicStick } from '@element-plus/icons-vue';
import permissionApi from '@/api/permission';

const { t, locale } = useI18n();

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
const roleFormRules = computed(() => ({
    name: [{ required: true, message: t('rbac_page.rules.role_name_required'), trigger: 'blur' }],
}));

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
const duplicateFormRules = computed(() => ({
    source_role_id: [{ required: true, message: t('rbac_page.rules.source_role_required'), trigger: 'change' }],
    name: [{ required: true, message: t('rbac_page.rules.new_role_name_required'), trigger: 'blur' }],
}));

// ─── Create from Template Dialog ───
const createFromTemplateVisible = ref(false);
const templateRoleLoading = ref(false);
const selectedTemplate = ref(null);
const templateRoleFormRef = ref(null);
const templateRoleForm = reactive({ name: '' });
const templateRoleFormRules = computed(() => ({
    name: [{ required: true, message: t('rbac_page.rules.role_name_required'), trigger: 'blur' }],
}));

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
const templateFormRules = computed(() => ({
    name: [{ required: true, message: t('rbac_page.rules.template_name_required'), trigger: 'blur' }],
}));

const auditActionOptions = computed(() => [
    { label: t('rbac_page.audit.all'), value: '' },
    { label: t('rbac_page.audit_actions.role_created'), value: 'role_created' },
    { label: t('rbac_page.audit_actions.role_updated'), value: 'role_updated' },
    { label: t('rbac_page.audit_actions.role_deleted'), value: 'role_deleted' },
    { label: t('rbac_page.audit_actions.user_role_assigned'), value: 'user_role_assigned' },
    { label: t('rbac_page.audit_actions.permission_assigned'), value: 'permission_assigned' },
]);

const templateCategoryOptions = computed(() => [
    { label: t('rbac_page.categories.custom'), value: 'custom' },
    { label: t('rbac_page.categories.industry'), value: 'industry' },
]);

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

function categoryLabel(category) {
    const key = `rbac_page.categories.${category}`;
    const translated = t(key);
    return translated !== key ? translated : category;
}

function groupLabel(group) {
    const key = `rbac_page.perm_groups.${group}`;
    const translated = t(key);
    return translated !== key ? translated : group;
}

function permLabel(name) {
    const actionMap = {
        view: 'actions.view',
        create: 'actions.create',
        edit: 'actions.edit',
        delete: 'actions.delete',
        cancel: 'actions.cancel',
        export: 'actions.export',
    };
    const parts = name.split('.');
    const action = parts[parts.length - 1];
    if (actionMap[action]) return t(actionMap[action]);
    const key = `rbac_page.perm_actions.${action}`;
    const translated = t(key);
    return translated !== key ? translated : action;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
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
    const key = `rbac_page.audit_actions.${action}`;
    const translated = t(key);
    return translated !== key ? translated : action;
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
            ElMessage.success(t('rbac_page.messages.role_updated'));
        } else {
            await permissionApi.roleCreate(payload);
            ElMessage.success(t('rbac_page.messages.role_created'));
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
            t('rbac_page.confirm.delete_role', { name: row.name }),
            t('rbac_page.confirm.delete_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await permissionApi.roleDelete(row.id);
        ElMessage.success(t('rbac_page.messages.role_deleted'));
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
        ElMessage.success(t('rbac_page.messages.roles_assigned'));
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
    duplicateForm.name = role ? role.name + t('rbac_page.copy_suffix') : '';
    duplicateDialogVisible.value = true;
}

async function submitDuplicate() {
    const valid = await duplicateFormRef.value.validate().catch(() => false);
    if (!valid) return;
    duplicateLoading.value = true;
    try {
        await permissionApi.roleDuplicate(duplicateForm.source_role_id, { name: duplicateForm.name });
        ElMessage.success(t('rbac_page.messages.role_duplicated'));
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
        ElMessage.success(t('rbac_page.messages.role_created'));
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
        ElMessage.success(t('rbac_page.messages.template_created'));
        createTemplateVisible.value = false;
        loadTemplates();
    } catch {} finally {
        templateSubmitting.value = false;
    }
}

async function handleSeedTemplates() {
    try {
        await ElMessageBox.confirm(
            t('rbac_page.confirm.seed_templates'),
            t('rbac_page.confirm.seed_templates_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'info' }
        );
        const { data: res } = await permissionApi.seedTemplates();
        if (res.success) {
            ElMessage.success(t('rbac_page.messages.templates_seeded'));
            loadTemplates();
        }
    } catch {}
}

async function handleDeleteTemplate(row) {
    try {
        await ElMessageBox.confirm(
            t('rbac_page.confirm.delete_template', { name: row.name }),
            t('rbac_page.confirm.delete_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await permissionApi.templateDelete(row.id);
        ElMessage.success(t('rbac_page.messages.template_deleted'));
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
