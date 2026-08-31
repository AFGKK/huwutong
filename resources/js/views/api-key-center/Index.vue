<template>
  <div class="api-key-center-page">
    <div class="page-header">
      <h2>API Key 管理中心</h2>
    </div>

    <el-tabs v-model="activeTab">
      <!-- ════════════════ Tab 1: 管理密钥 (Admin Keys) ════════════════ -->
      <el-tab-pane label="管理密钥" name="admin">
        <div v-show="activeTab === 'admin'">
          <div class="page-header-tab">
            <div class="header-left">
              <span class="header-subtitle">{{ t(`${P1}.subtitle`) }}</span>
            </div>
            <div class="header-right">
              <el-button @click="a1_fetchAll"><el-icon><Refresh /></el-icon>{{ t(`${P1}.refresh`) }}</el-button>
              <el-button type="primary" @click="a1_showCreate = true"><el-icon><Plus /></el-icon>{{ t(`${P1}.create_key`) }}</el-button>
            </div>
          </div>

          <el-row :gutter="16" class="mb-4">
            <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">{{ t(`${P1}.stats.total`) }}</div><div class="stat-value">{{ a1_stats.total }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">{{ t(`${P1}.stats.active`) }}</div><div class="stat-value text-success">{{ a1_stats.active }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">{{ t(`${P1}.stats.expiring_soon`) }}</div><div class="stat-value text-warning">{{ a1_overview.keys_expiring_soon }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">{{ t(`${P1}.stats.near_quota`) }}</div><div class="stat-value text-danger">{{ a1_overview.keys_near_quota }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">{{ t(`${P1}.stats.total_requests`) }}</div><div class="stat-value">{{ a1_overview.total_usage_count || 0 }}</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-item"><div class="stat-label">{{ t(`${P1}.stats.max_keys`) }}</div><div class="stat-value">{{ a1_maxKeys }}</div></div></el-card></el-col>
          </el-row>

          <el-card shadow="never">
            <el-table :data="a1_keys" v-loading="a1_loading" stripe @row-click="a1_showDetail">
              <el-table-column :label="t(`${P1}.columns.name`)" min-width="140">
                <template #default="{ row }">
                  <div class="key-name">
                    <span class="name-text">{{ row.name }}</span>
                    <el-tag v-if="!row.is_active" size="small" type="danger" effect="dark">{{ t(`${P1}.badges.disabled`) }}</el-tag>
                    <el-tag v-if="a1_isExpiring(row)" size="small" type="warning" effect="light">{{ t(`${P1}.badges.expiring`) }}</el-tag>
                  </div>
                </template>
              </el-table-column>
              <el-table-column :label="t(`${P1}.columns.key_id`)" min-width="160"><template #default="{ row }"><code class="key-id-text">{{ row.key_id }}</code></template></el-table-column>
              <el-table-column :label="t(`${P1}.columns.tier`)" width="100"><template #default="{ row }"><el-tag :type="a1_tierType(row.tier)" size="small">{{ a1_tierLabel(row.tier) }}</el-tag></template></el-table-column>
              <el-table-column :label="t(`${P1}.columns.permissions`)" width="90"><template #default="{ row }"><el-tag :type="a1_permType(row.permissions)" size="small">{{ a1_permLabel(row.permissions) }}</el-tag></template></el-table-column>
              <el-table-column :label="t(`${P1}.columns.rate_limit`)" width="70">{{ row.rate_limit || a1_emDash }}</el-table-column>
              <el-table-column :label="t(`${P1}.columns.quota`)" width="100">
                <template #default="{ row }">
                  <span v-if="row.usage_quota">{{ row.usage_count }}/{{ row.usage_quota }}</span>
                  <span v-else class="text-muted">{{ t(`${P1}.unlimited`) }}</span>
                </template>
              </el-table-column>
              <el-table-column :label="t(`${P1}.columns.daily`)" width="90">
                <template #default="{ row }">
                  <span v-if="row.daily_quota">{{ row.daily_usage }}/{{ row.daily_quota }}</span>
                  <span v-else class="text-muted">{{ a1_emDash }}</span>
                </template>
              </el-table-column>
              <el-table-column :label="t(`${P1}.columns.allowed_ip`)" width="110">
                <template #default="{ row }">
                  <code v-if="row.allowed_ip" class="ip-text">{{ row.allowed_ip }}</code>
                  <span v-else-if="row.allowed_ips" class="ip-text">{{ t(`${P1}.ip_count`, { n: row.allowed_ips.length }) }}</span>
                  <span v-else class="text-muted">{{ t(`${P1}.unlimited`) }}</span>
                </template>
              </el-table-column>
              <el-table-column :label="t(`${P1}.columns.last_used`)" width="140">{{ row.last_used_at ? a1_formatTime(row.last_used_at) : t(`${P1}.never_used`) }}</el-table-column>
              <el-table-column :label="t(`${P1}.columns.expires`)" width="130">
                <template #default="{ row }">
                  <span v-if="row.expires_at" :class="a1_isExpired(row.expires_at) ? 'text-danger' : ''">{{ a1_formatTime(row.expires_at) }}</span>
                  <span v-else class="text-muted">{{ t(`${P1}.never_expires`) }}</span>
                </template>
              </el-table-column>
              <el-table-column :label="t(`${P1}.columns.status`)" width="70"><template #default="{ row }"><el-switch :model-value="row.is_active" :loading="a1_togglingId === row.id" size="small" @change="a1_toggleActive(row)" /></template></el-table-column>
              <el-table-column :label="t(`${P1}.columns.actions`)" width="300" fixed="right">
                <template #default="{ row }">
                  <el-button text size="small" @click.stop="a1_showEdit(row)">{{ t('actions.edit') }}</el-button>
                  <el-button text size="small" type="primary" @click.stop="a1_showPermissionConfig(row)">{{ t(`${P1}.endpoint_permissions`) }}</el-button>
                  <el-button text size="small" type="warning" @click.stop="a1_handleRegenerate(row)">{{ t(`${P1}.regenerate`) }}</el-button>
                  <el-popconfirm :title="t(`${P1}.confirm_delete`)" :confirm-button-text="t('actions.delete')" @confirm.stop="a1_handleDelete(row)">
                    <template #reference><el-button text size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button></template>
                  </el-popconfirm>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!a1_loading && a1_keys.length === 0" :image-size="80" :description="t(`${P1}.empty`)" />
          </el-card>

          <!-- 端点权限配置对话框 (M2-138) -->
          <el-dialog v-model="a1_showPermissionDialog" :title="t(`${P1}.perm_dialog.title`)" width="700px" :close-on-click-modal="false">
            <el-form v-if="a1_permissionConfigKey" label-position="top">
              <el-alert :title="t(`${P1}.perm_dialog.alert`)" type="info" :closable="false" show-icon class="mb-4" />
              <h4 class="section-title">{{ t(`${P1}.perm_dialog.matrix_title`) }}</h4>
              <div class="perm-matrix">
                <div class="perm-row perm-header">
                  <div class="perm-cell endpoint-name">{{ t(`${P1}.perm_dialog.endpoint`) }}</div>
                  <div class="perm-cell" v-for="m in httpMethods" :key="m">{{ m }}</div>
                  <div class="perm-cell endpoint-desc">{{ t(`${P1}.perm_dialog.description`) }}</div>
                </div>
                <div class="perm-row" v-for="ep in a1_sdkEndpoints" :key="ep.endpoint">
                  <div class="perm-cell endpoint-name"><code>{{ ep.endpoint }}</code></div>
                  <div class="perm-cell" v-for="m in httpMethods" :key="m">
                    <el-checkbox
                      v-model="a1_permForm[ep.endpoint]"
                      :val="m"
                      :checked="a1_permForm[ep.endpoint]?.includes(m)"
                      @change="a1_toggleEndpointMethod(ep.endpoint, m)"
                    />
                  </div>
                  <div class="perm-cell endpoint-desc">{{ ep.description }}</div>
                </div>
              </div>
              <el-divider />
              <el-form-item :label="t(`${P1}.perm_dialog.global_methods`)">
                <el-select v-model="a1_permFormGlobalMethods" multiple :placeholder="t(`${P1}.perm_dialog.no_limit_ph`)" clearable class="w-full">
                  <el-option v-for="m in httpMethods" :key="m" :label="m" :value="m" />
                </el-select>
                <div class="form-tip">{{ t(`${P1}.perm_dialog.global_methods_tip`) }}</div>
              </el-form-item>
              <el-row :gutter="16">
                <el-col :span="12">
                  <el-form-item :label="t(`${P1}.perm_dialog.bound_ips`)">
                    <el-input v-model="a1_permFormIps" :placeholder="t(`${P1}.perm_dialog.bound_ips_ph`)" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item :label="t(`${P1}.perm_dialog.expires_at`)">
                    <el-date-picker
                      v-model="a1_permFormExpiresAt"
                      type="datetime"
                      :placeholder="t(`${P1}.perm_dialog.never_expires_ph`)"
                      value-format="YYYY-MM-DD HH:mm:ss"
                      :disabled-date="d => d <= new Date()"
                      class="w-full"
                    />
                  </el-form-item>
                </el-col>
              </el-row>
              <el-row :gutter="16">
                <el-col :span="12">
                  <el-form-item :label="t(`${P1}.perm_dialog.usage_quota`)">
                    <el-input-number v-model="a1_permFormUsageQuota" :min="1" :step="1000" :placeholder="t(`${P1}.perm_dialog.unlimited_ph`)" class="w-full" />
                  </el-form-item>
                </el-col>
                <el-col :span="12">
                  <el-form-item :label="t(`${P1}.perm_dialog.daily_quota`)">
                    <el-input-number v-model="a1_permFormDailyQuota" :min="1" :step="100" :placeholder="t(`${P1}.perm_dialog.unlimited_ph`)" class="w-full" />
                  </el-form-item>
                </el-col>
              </el-row>
            </el-form>
            <template #footer>
              <el-button @click="a1_showPermissionDialog = false">{{ t('actions.cancel') }}</el-button>
              <el-button type="primary" :loading="a1_savingPermission" @click="a1_savePermissionConfig">{{ t(`${P1}.perm_dialog.save_config`) }}</el-button>
            </template>
          </el-dialog>
        </div>
      </el-tab-pane>

      <!-- ════════════════ Tab 2: 客户密钥 (Customer Keys) ════════════════ -->
      <el-tab-pane label="客户密钥" name="customer">
        <div v-show="activeTab === 'customer'">
          <div class="page-header-tab">
            <div class="header-right">
              <el-button type="primary" @click="ck_showCreate = true">
                <el-icon><Plus /></el-icon> {{ t(`${P2}.create`) }}
              </el-button>
            </div>
          </div>

          <el-tabs v-model="ck_activeTab">
            <el-tab-pane :label="t(`${P2}.tabs.dashboard`)" name="dashboard">
              <el-row :gutter="16" class="mb-4">
                <el-col :span="6">
                  <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value text-primary">{{ ck_dashboard.total || 0 }}</div><div class="stat-label">{{ t(`${P2}.stats.total`) }}</div></div>
                  </el-card>
                </el-col>
                <el-col :span="6">
                  <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value text-success">{{ ck_dashboard.active || 0 }}</div><div class="stat-label">{{ t(`${P2}.stats.active`) }}</div></div>
                  </el-card>
                </el-col>
                <el-col :span="6">
                  <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value text-warning">{{ ck_dashboard.expired || 0 }}</div><div class="stat-label">{{ t(`${P2}.stats.expired`) }}</div></div>
                  </el-card>
                </el-col>
                <el-col :span="6">
                  <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value text-info">{{ ck_dashboard.recent || 0 }}</div><div class="stat-label">{{ t(`${P2}.stats.recent`) }}</div></div>
                  </el-card>
                </el-col>
              </el-row>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P2}.tabs.list`)" name="list">
              <el-card shadow="never">
                <el-table v-loading="ck_loading" :data="ck_keys" stripe border style="width:100%">
                  <el-table-column prop="name" :label="t(`${P2}.cols.name`)" width="160" />
                  <el-table-column :label="t(`${P2}.cols.key`)" width="200">
                    <template #default="{ row }">
                      <span class="font-mono">{{ row.prefix }}****{{ row.key.slice(-6) }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column :label="t(`${P2}.cols.abilities`)" min-width="180">
                    <template #default="{ row }">
                      <el-tag v-if="!row.abilities || row.abilities.includes('*')" size="small" type="success">{{ t(`${P2}.all_abilities`) }}</el-tag>
                      <template v-else>
                        <el-tag v-for="a in row.abilities" :key="a" size="small" class="mr-1">{{ a }}</el-tag>
                      </template>
                    </template>
                  </el-table-column>
                  <el-table-column :label="t(`${P2}.cols.ip`)" width="150" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.ip_whitelist || '-' }}</template>
                  </el-table-column>
                  <el-table-column :label="t(`${P2}.cols.expires`)" width="160">
                    <template #default="{ row }">{{ row.expires_at ? ck_formatDate(row.expires_at) : t(`${P2}.never_expires`) }}</template>
                  </el-table-column>
                  <el-table-column :label="t(`${P2}.cols.status`)" width="80">
                    <template #default="{ row }">
                      <el-tag :type="row.is_active && !row.is_expired ? 'success' : 'danger'" size="small">
                        {{ row.is_active && !row.is_expired ? t(`${P2}.enabled`) : t(`${P2}.disabled`) }}
                      </el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column :label="t(`${P2}.cols.last_used`)" width="160">
                    <template #default="{ row }">{{ row.last_used_at ? ck_formatDate(row.last_used_at) : '-' }}</template>
                  </el-table-column>
                  <el-table-column :label="t(`${P2}.cols.actions`)" width="160" fixed="right">
                    <template #default="{ row }">
                      <el-button size="small" @click="ck_handleEdit(row)">{{ t('actions.edit') }}</el-button>
                      <el-button size="small" :type="row.is_active ? 'warning' : 'success'" @click="ck_handleToggle(row)">
                        {{ row.is_active ? t('actions.disable') : t('actions.enable') }}
                      </el-button>
                      <el-popconfirm :title="t(`${P2}.confirm_delete`)" @confirm="ck_handleDelete(row)">
                        <template #reference>
                          <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                        </template>
                      </el-popconfirm>
                    </template>
                  </el-table-column>
                </el-table>
              </el-card>
            </el-tab-pane>
          </el-tabs>

          <!-- Create Dialog -->
          <el-dialog v-model="ck_showCreate" :title="t(`${P2}.create`)" width="520px" :close-on-click-modal="false">
            <el-form ref="ck_createFormRef" :model="ck_form" :rules="ck_rules" label-position="top">
              <el-form-item :label="t(`${P2}.cols.name`)" prop="name">
                <el-input v-model="ck_form.name" :placeholder="t(`${P2}.name_ph`)" maxlength="100" />
              </el-form-item>
              <el-form-item :label="t(`${P2}.cols.abilities`)">
                <el-checkbox-group v-model="ck_form.abilities">
                  <el-checkbox v-for="(label, key) in ck_abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
                </el-checkbox-group>
                <div class="text-muted" style="font-size:12px;margin-top:4px;">{{ t(`${P2}.abilities_hint`) }}</div>
              </el-form-item>
              <el-form-item :label="t(`${P2}.ip_optional`)">
                <el-input v-model="ck_form.ip_whitelist" :placeholder="t(`${P2}.ip_ph`)" />
              </el-form-item>
              <el-form-item :label="t(`${P2}.expires_optional`)">
                <el-date-picker v-model="ck_form.expires_at" type="datetime" :placeholder="t(`${P2}.pick_expires`)" style="width:100%" />
              </el-form-item>
            </el-form>
            <template #footer>
              <el-button @click="ck_showCreate = false">{{ t('actions.cancel') }}</el-button>
              <el-button type="primary" @click="ck_handleCreate">{{ t('actions.create') }}</el-button>
            </template>
          </el-dialog>

          <!-- Key Result Dialog -->
          <el-dialog v-model="ck_showKeyResult" :title="t(`${P2}.created_title`)" width="480px">
            <el-alert :title="t(`${P2}.created_alert`)" type="warning" :closable="false" show-icon class="mb-4" />
            <div class="key-result-box">
              <code class="font-mono">{{ ck_newKeyPlainText }}</code>
              <el-button size="small" @click="ck_copyKey">{{ t('actions.copy') }}</el-button>
            </div>
          </el-dialog>

          <!-- Edit Dialog -->
          <el-dialog v-model="ck_showEdit" :title="t(`${P2}.edit_title`)" width="480px">
            <el-form label-position="top">
              <el-form-item :label="t(`${P2}.cols.name`)">
                <el-input v-model="ck_editForm.name" maxlength="100" />
              </el-form-item>
              <el-form-item :label="t(`${P2}.cols.abilities`)">
                <el-checkbox-group v-model="ck_editForm.abilities">
                  <el-checkbox v-for="(label, key) in ck_abilityOptions" :key="key" :label="key">{{ label }}</el-checkbox>
                </el-checkbox-group>
              </el-form-item>
              <el-form-item :label="t(`${P2}.cols.ip`)">
                <el-input v-model="ck_editForm.ip_whitelist" :placeholder="t(`${P2}.ip_comma`)" />
              </el-form-item>
              <el-form-item :label="t(`${P2}.cols.expires`)">
                <el-date-picker v-model="ck_editForm.expires_at" type="datetime" :placeholder="t(`${P2}.never_expires`)" style="width:100%" />
              </el-form-item>
            </el-form>
            <template #footer>
              <el-button @click="ck_showEdit = false">{{ t('actions.cancel') }}</el-button>
              <el-button type="primary" @click="ck_handleUpdate">{{ t('actions.save') }}</el-button>
            </template>
          </el-dialog>
        </div>
      </el-tab-pane>

      <!-- ════════════════ Tab 3: 审计日志 (Audit Logs) ════════════════ -->
      <el-tab-pane label="审计日志" name="audit">
        <div v-show="activeTab === 'audit'">
          <div class="page-header-tab">
            <div>
              <p class="text-muted">{{ t('api_key_audit_page.subtitle') }}</p>
            </div>
            <el-button @click="au_loadLogs" :loading="au_loading" :icon="Refresh">{{ t('actions.refresh') }}</el-button>
          </div>

          <el-card shadow="never" class="mb-4">
            <el-form :inline="true" @submit.prevent="au_doSearch">
              <el-form-item :label="t('api_key_audit_page.key')">
                <el-select v-model="au_filters.api_key_id" :placeholder="t('api_key_audit_page.all_keys')" clearable style="width:180px" @change="au_doSearch">
                  <el-option v-for="k in au_apiKeysList" :key="k.id" :label="k.name" :value="k.id" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('api_key_audit_page.action')">
                <el-select v-model="au_filters.action" :placeholder="t('api_key_audit_page.all_actions')" clearable style="width:140px" @change="au_doSearch">
                  <el-option :label="t('api_key_audit_page.actions.created')" value="created" />
                  <el-option :label="t('api_key_audit_page.actions.updated')" value="updated" />
                  <el-option :label="t('api_key_audit_page.actions.deleted')" value="deleted" />
                  <el-option :label="t('api_key_audit_page.actions.activated')" value="activated" />
                  <el-option :label="t('api_key_audit_page.actions.deactivated')" value="deactivated" />
                  <el-option :label="t('api_key_audit_page.actions.regenerated')" value="regenerated" />
                  <el-option :label="t('api_key_audit_page.actions.other')" value="other" />
                </el-select>
              </el-form-item>
              <el-form-item :label="t('api_key_audit_page.range')">
                <el-date-picker
                  v-model="au_dateRange"
                  type="datetimerange"
                  :range-separator="t('api_key_audit_page.to')"
                  :start-placeholder="t('api_key_audit_page.start')"
                  :end-placeholder="t('api_key_audit_page.end')"
                  style="width:300px"
                  value-format="YYYY-MM-DD HH:mm:ss"
                  @change="au_doSearch"
                />
              </el-form-item>
              <el-form-item>
                <el-button type="primary" native-type="submit" :icon="Search">{{ t('actions.search') }}</el-button>
              </el-form-item>
            </el-form>
          </el-card>

          <el-card shadow="never">
            <el-table :data="au_logs" v-loading="au_loading" stripe>
              <el-table-column :label="t('api_key_audit_page.cols.time')" width="170">
                <template #default="{ row }">{{ au_fmtTime(row.created_at) }}</template>
              </el-table-column>
              <el-table-column :label="t('api_key_audit_page.cols.key_name')" min-width="140">
                <template #default="{ row }">{{ row.api_key?.name || '-' }}</template>
              </el-table-column>
              <el-table-column :label="t('api_key_audit_page.cols.key_id')" width="130">
                <template #default="{ row }">
                  <code style="font-size:12px">{{ row.api_key?.key_id || '-' }}</code>
                </template>
              </el-table-column>
              <el-table-column :label="t('api_key_audit_page.action')" width="100">
                <template #default="{ row }">
                  <el-tag :type="au_actionType(row.action)" size="small">{{ au_actionLabel(row.action) }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column :label="t('api_key_audit_page.cols.actor')" width="120">
                <template #default="{ row }">{{ row.actor_type === 'user' ? t('api_key_audit_page.admin') : t('api_key_audit_page.system') }}</template>
              </el-table-column>
              <el-table-column :label="t('api_key_audit_page.cols.ip')" width="130" prop="ip_address" />
              <el-table-column :label="t('api_key_audit_page.cols.remark')" min-width="160" prop="remark" show-overflow-tooltip />
              <el-table-column :label="t('api_key_audit_page.cols.detail')" width="80" fixed="right">
                <template #default="{ row }">
                  <el-button size="small" link type="primary" @click="au_showDetail(row)">{{ t('actions.view') }}</el-button>
                </template>
              </el-table-column>
            </el-table>
            <el-empty v-if="!au_logs.length && !au_loading" :description="t('api_key_audit_page.empty')" :image-size="60" />

            <div class="pagination-wrap" v-if="au_total > 0">
              <el-pagination
                v-model:current-page="au_page"
                v-model:page-size="au_perPage"
                :total="au_total"
                :page-sizes="[20, 50, 100]"
                layout="total, sizes, prev, pager, next"
                @current-change="au_loadLogs"
                @size-change="au_loadLogs"
              />
            </div>
          </el-card>

          <!-- Audit Detail Drawer -->
          <el-drawer v-model="au_detailVisible" :title="t('api_key_audit_page.detail_title')" size="480px">
            <template v-if="au_currentLog">
              <el-descriptions :column="1" border size="small">
                <el-descriptions-item :label="t('api_key_audit_page.cols.time')">{{ au_fmtTime(au_currentLog.created_at) }}</el-descriptions-item>
                <el-descriptions-item :label="t('api_key_audit_page.cols.key_name')">{{ au_currentLog.api_key?.name }}</el-descriptions-item>
                <el-descriptions-item :label="t('api_key_audit_page.cols.key_id')">
                  <code>{{ au_currentLog.api_key?.key_id }}</code>
                </el-descriptions-item>
                <el-descriptions-item :label="t('api_key_audit_page.action')">
                  <el-tag :type="au_actionType(au_currentLog.action)" size="small">{{ au_actionLabel(au_currentLog.action) }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item :label="t('api_key_audit_page.actor_ip')">{{ au_currentLog.ip_address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="User-Agent">
                  <div style="word-break:break-all;font-size:12px">{{ au_currentLog.user_agent || '-' }}</div>
                </el-descriptions-item>
                <el-descriptions-item :label="t('api_key_audit_page.cols.remark')">{{ au_currentLog.remark || '-' }}</el-descriptions-item>
              </el-descriptions>

              <h4 style="margin:20px 0 8px">{{ t('api_key_audit_page.changes') }}</h4>
              <el-table v-if="au_changeEntries.length" :data="au_changeEntries" size="small" stripe>
                <el-table-column :label="t('api_key_audit_page.cols.field')" width="100" prop="field" />
                <el-table-column :label="t('api_key_audit_page.cols.old')" min-width="120">
                  <template #default="{ row }">{{ au_formatValue(row.old) }}</template>
                </el-table-column>
                <el-table-column :label="t('api_key_audit_page.cols.new')" min-width="120">
                  <template #default="{ row }">{{ au_formatValue(row.new) }}</template>
                </el-table-column>
              </el-table>
              <el-empty v-else :description="t('api_key_audit_page.no_changes')" :image-size="40" />
            </template>
          </el-drawer>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh, CopyDocument, View, Hide, Search } from '@element-plus/icons-vue'
import apiKeyApi from '@/api/apiKey'
import fineGrainedApiKeyApi from '@/api/fineGrainedApiKey'
import { getDashboard, getMyKeys, createKey, updateKey, deleteKey, toggleKey, getAbilities } from '@/api/customerApiKey'

const P1 = 'api_keys_page'
const P2 = 'customer_api_key_page'
const { t, locale } = useI18n()

const activeTab = ref('admin')

// ═══════════════════════════════════════════════════════
// SECTION 1: 管理密钥 (Admin Keys) — prefix a1_
// ═══════════════════════════════════════════════════════

const a1_loading = ref(false)
const a1_creating = ref(false)
const a1_updating = ref(false)
const a1_togglingId = ref(null)
const a1_showCreate = ref(false)
const a1_showSecret = ref(false)
const a1_showSecretText = ref(false)
const a1_showEditDialog = ref(false)
const a1_maxKeys = 20
const a1_keys = ref([])
const a1_newKeyData = ref({})
const a1_currentEditKey = ref(null)
const a1_createFormRef = ref(null)
const a1_editFormRef = ref(null)
const a1_tierConfig = ref({ tiers: [], permissions: [] })
const a1_overview = ref({})
const a1_stats = reactive({ total: 0, active: 0 })

// 细粒度权限状态 (M2-138)
const a1_showPermissionDialog = ref(false)
const a1_savingPermission = ref(false)
const a1_permissionConfigKey = ref(null)
const a1_sdkEndpoints = ref([])
const httpMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']
const a1_permForm = ref({})
const a1_permFormGlobalMethods = ref([])
const a1_permFormIps = ref('')
const a1_permFormExpiresAt = ref(null)
const a1_permFormUsageQuota = ref(null)
const a1_permFormDailyQuota = ref(null)

const a1_createForm = ref({
  name: '', description: '', permissions: 'read-write', tier: 'standard',
  allowed_endpoints: null, allowed_methods: null,
  rate_limit: null, usage_quota: null, daily_quota: null,
  allowed_ip: '', allowed_ips: null, expires_at: null,
})

const a1_createRules = computed(() => ({
  name: [{ required: true, message: t(`${P1}.rules.name_required`), trigger: 'blur' }],
}))

const a1_editForm = ref({})

const a1_permTypes = {
  'read-only': 'info', 'read-write': 'warning', 'admin': 'danger',
}
const a1_tierTypes = {
  free: 'info', standard: 'primary', enterprise: 'success', custom: 'warning',
}

const a1_emDash = computed(() => t(`${P1}.em_dash`))

const a1_permLabelMap = computed(() => ({
  'read-only': t(`${P1}.perm.read_only`),
  'read-write': t(`${P1}.perm.read_write`),
  admin: t(`${P1}.perm.admin`),
}))
const a1_tierLabelMap = computed(() => ({
  free: t(`${P1}.tier.free`),
  standard: t(`${P1}.tier.standard`),
  enterprise: t(`${P1}.tier.enterprise`),
  custom: t(`${P1}.tier.custom`),
}))

function a1_permLabel(v) { return a1_permLabelMap.value[v] || v }
function a1_permType(v) { return a1_permTypes[v] || 'info' }
function a1_tierLabel(v) { return a1_tierLabelMap.value[v] || v }
function a1_tierType(v) { return a1_tierTypes[v] || 'info' }
function a1_formatTime(time) {
  return time ? new Date(time).toLocaleString(locale.value === 'en' ? 'en-US' : 'zh-CN') : a1_emDash.value
}
function a1_isExpired(d) { return d && new Date(d) < new Date() }
function a1_isExpiring(r) {
  return r.expires_at && !a1_isExpired(r.expires_at) && new Date(r.expires_at) < Date.now() + 7 * 86400000
}

function a1_copyText(text) {
  navigator.clipboard.writeText(text).then(() => ElMessage.success(t(`${P1}.messages.copied`))).catch(() => {
    const ta = document.createElement('textarea')
    ta.value = text
    document.body.appendChild(ta)
    ta.select()
    document.execCommand('copy')
    document.body.removeChild(ta)
    ElMessage.success(t(`${P1}.messages.copied`))
  })
}

async function a1_fetchAll() {
  a1_loading.value = true
  try {
    const [keysRes, overviewRes, configRes] = await Promise.all([
      apiKeyApi.list(),
      apiKeyApi.myOverview(),
      apiKeyApi.getTierConfig(),
    ])
    if (keysRes.data?.success) {
      const d = keysRes.data.data
      a1_keys.value = d.keys || []
      Object.assign(a1_stats, d.stats || {})
    }
    if (overviewRes.data?.success) a1_overview.value = overviewRes.data.data
    if (configRes.data?.success) a1_tierConfig.value = configRes.data.data
  } catch { ElMessage.error(t('messages.load_failed')) }
  finally { a1_loading.value = false }
}

function a1_showEdit(row) {
  a1_currentEditKey.value = row
  a1_editForm.value = {
    name: row.name,
    description: row.description || '',
    permissions: row.permissions || 'read-write',
    tier: row.tier || 'standard',
    allowed_endpoints: row.allowed_endpoints || null,
    allowed_methods: row.allowed_methods || null,
    rate_limit: row.rate_limit ?? null,
    usage_quota: row.usage_quota ?? null,
    daily_quota: row.daily_quota ?? null,
    allowed_ip: row.allowed_ip || '',
    allowed_ips: row.allowed_ips || null,
    expires_at: row.expires_at,
    is_active: row.is_active,
  }
  a1_showEditDialog.value = true
}

function a1_resetForm() {
  a1_createForm.value = {
    name: '', description: '', permissions: 'read-write', tier: 'standard',
    allowed_endpoints: null, allowed_methods: null,
    rate_limit: null, usage_quota: null, daily_quota: null,
    allowed_ip: '', allowed_ips: null, expires_at: null,
  }
}

function a1_resetAndRefresh() { a1_resetForm(); a1_fetchAll() }
function a1_showDetail() {}

async function a1_handleCreate() {
  const valid = await a1_createFormRef.value?.validate().catch(() => false)
  if (!valid) return
  a1_creating.value = true
  try {
    const p = { ...a1_createForm.value }
    p.allowed_ip = p.allowed_ip || null
    if (!p.allowed_endpoints?.length) p.allowed_endpoints = null
    if (p.allowed_methods?.length) p.allowed_methods = p.allowed_methods.join(',')
    else p.allowed_methods = null
    if (!p.allowed_ips?.length) p.allowed_ips = null
    const res = await apiKeyApi.create(p)
    if (res.data?.success) {
      a1_newKeyData.value = res.data.data
      a1_showSecret.value = true; a1_showSecretText.value = true
      a1_showCreate.value = false
      ElMessage.success(t(`${P1}.messages.created`))
    }
  } catch (e) { ElMessage.error(e.response?.data?.error?.message || t(`${P1}.messages.create_failed`)) }
  finally { a1_creating.value = false }
}

async function a1_handleUpdate() {
  a1_updating.value = true
  try {
    const p = { ...a1_editForm.value }
    p.allowed_ip = p.allowed_ip || null
    if (!p.allowed_endpoints?.length) p.allowed_endpoints = null
    if (p.allowed_methods?.length) p.allowed_methods = p.allowed_methods.join(',')
    else p.allowed_methods = null
    if (!p.allowed_ips?.length) p.allowed_ips = null
    const res = await apiKeyApi.update(a1_currentEditKey.value.id, p)
    if (res.data?.success) {
      ElMessage.success(t(`${P1}.messages.updated`))
      a1_showEditDialog.value = false
      a1_fetchAll()
    }
  } catch (e) { ElMessage.error(e.response?.data?.error?.message || t(`${P1}.messages.update_failed`)) }
  finally { a1_updating.value = false }
}

async function a1_handleDelete(row) {
  try {
    const res = await apiKeyApi.delete(row.id)
    if (res.data?.success) { ElMessage.success(t(`${P1}.messages.deleted`)); a1_fetchAll() }
  } catch { ElMessage.error(t(`${P1}.messages.delete_failed`)) }
}

async function a1_handleRegenerate(row) {
  try {
    await ElMessageBox.confirm(
      t(`${P1}.confirm.regenerate_msg`, { name: row.name }),
      t(`${P1}.confirm.regenerate_title`),
      { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' },
    )
    const res = await apiKeyApi.regenerate(row.id)
    if (res.data?.success) {
      a1_newKeyData.value = {
        key_id: res.data.data.key_id,
        secret: res.data.data.secret,
        name: row.name,
        tier: row.tier,
        permissions: row.permissions,
      }
      a1_showSecret.value = true; a1_showSecretText.value = true
      ElMessage.success(t(`${P1}.messages.regenerated`))
      a1_fetchAll()
    }
  } catch {}
}

async function a1_toggleActive(row) {
  a1_togglingId.value = row.id
  try {
    const res = await apiKeyApi.toggleActive(row.id)
    if (res.data?.success) {
      ElMessage.success(row.is_active ? t(`${P1}.messages.disabled`) : t(`${P1}.messages.enabled`))
      a1_fetchAll()
    }
  } catch { ElMessage.error(t('messages.failed')) }
  finally { a1_togglingId.value = null }
}

// ── 细粒度权限管理方法 (M2-138) ──

async function a1_showPermissionConfig(row) {
  a1_permissionConfigKey.value = row
  a1_showPermissionDialog.value = true
  a1_permForm.value = {}
  a1_permFormGlobalMethods.value = []
  a1_permFormIps.value = ''
  a1_permFormExpiresAt.value = null
  a1_permFormUsageQuota.value = null
  a1_permFormDailyQuota.value = null

  try {
    const [sdkRes, permRes] = await Promise.all([
      fineGrainedApiKeyApi.getSdkEndpoints(),
      fineGrainedApiKeyApi.getKeyPermissions(row.id),
    ])

    if (sdkRes.data?.success) {
      a1_sdkEndpoints.value = sdkRes.data.data.endpoints || []
    }

    if (permRes.data?.success) {
      const d = permRes.data.data
      const ep = d.endpoint_permissions || []
      ep.forEach(item => {
        if (item.allowed && item.allowed_methods?.length) {
          a1_permForm.value[item.endpoint] = [...item.allowed_methods]
        } else {
          a1_permForm.value[item.endpoint] = []
        }
      })

      a1_permFormGlobalMethods.value = d.allowed_methods
        ? d.allowed_methods.split(',').map(m => m.trim())
        : []

      a1_permFormIps.value = (d.allowed_ips || []).join(', ')

      a1_permFormExpiresAt.value = d.expiry_status?.expires_at || null

      const q = d.quota_snapshot || {}
      a1_permFormUsageQuota.value = q.usage_quota ?? null
      a1_permFormDailyQuota.value = q.daily_quota ?? null
    }
  } catch { ElMessage.error(t(`${P1}.messages.perm_load_failed`)) }
}

function a1_toggleEndpointMethod(endpoint, method) {
  if (!a1_permForm.value[endpoint]) {
    a1_permForm.value[endpoint] = []
  }
  const idx = a1_permForm.value[endpoint].indexOf(method)
  if (idx >= 0) {
    a1_permForm.value[endpoint].splice(idx, 1)
  } else {
    a1_permForm.value[endpoint].push(method)
  }
}

async function a1_savePermissionConfig() {
  if (!a1_permissionConfigKey.value) return
  a1_savingPermission.value = true

  try {
    const endpointPermissions = {}
    a1_sdkEndpoints.value.forEach(ep => {
      const methods = a1_permForm.value[ep.endpoint] || []
      if (methods.length > 0) {
        endpointPermissions[ep.endpoint] = methods
      }
    })

    const payload = {
      endpoint_permissions: Object.keys(endpointPermissions).length > 0 ? endpointPermissions : null,
      allowed_methods: a1_permFormGlobalMethods.value.length > 0 ? a1_permFormGlobalMethods.value.join(',') : null,
      allowed_ips: a1_permFormIps.value
        ? a1_permFormIps.value.split(/[,\s]+/).filter(Boolean)
        : null,
      expires_at: a1_permFormExpiresAt.value || null,
      usage_quota: a1_permFormUsageQuota.value || null,
      daily_quota: a1_permFormDailyQuota.value || null,
    }

    const res = await fineGrainedApiKeyApi.updatePermissions(a1_permissionConfigKey.value.id, payload)
    if (res.data?.success) {
      ElMessage.success(t(`${P1}.messages.perm_updated`))
      a1_showPermissionDialog.value = false
      a1_fetchAll()
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.error?.message || t(`${P1}.messages.save_failed`))
  } finally {
    a1_savingPermission.value = false
  }
}

// ═══════════════════════════════════════════════════════
// SECTION 2: 客户密钥 (Customer Keys) — prefix ck_
// ═══════════════════════════════════════════════════════

const ck_dateLocale = computed(() => (locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'))
const ck_activeTab = ref('dashboard')
const ck_loading = ref(false)
const ck_dashboard = ref({})
const ck_keys = ref([])
const ck_abilityOptions = ref({})

const ck_showCreate = ref(false)
const ck_showEdit = ref(false)
const ck_showKeyResult = ref(false)
const ck_newKeyPlainText = ref('')
const ck_createFormRef = ref(null)

const ck_form = reactive({
  name: '',
  abilities: [],
  ip_whitelist: '',
  expires_at: null,
})

const ck_editForm = reactive({
  id: null,
  name: '',
  abilities: [],
  ip_whitelist: '',
  expires_at: null,
})

const ck_rules = computed(() => ({
  name: [{ required: true, message: t(`${P2}.name_required`), trigger: 'blur' }],
}))

async function ck_loadDashboard() {
  try {
    const { data: res } = await getDashboard()
    ck_dashboard.value = res.data || {}
  } catch { ck_dashboard.value = {} }
}

async function ck_loadKeys() {
  ck_loading.value = true
  try {
    const { data: res } = await getMyKeys()
    ck_keys.value = res.data?.data || []
  } catch { ck_keys.value = [] }
  finally { ck_loading.value = false }
}

async function ck_loadAbilities() {
  try {
    const { data: res } = await getAbilities()
    ck_abilityOptions.value = res.data || {}
  } catch { ck_abilityOptions.value = {} }
}

async function ck_handleCreate() {
  if (!ck_form.name) { ElMessage.warning(t(`${P2}.name_required`)); return }
  try {
    const { data: res } = await createKey({ ...ck_form })
    ck_newKeyPlainText.value = res.data?.plain_text_key || ''
    ck_showKeyResult.value = true
    ck_showCreate.value = false
    ck_form.name = ''
    ck_form.abilities = []
    ck_form.ip_whitelist = ''
    ck_form.expires_at = null
    ck_loadKeys()
    ck_loadDashboard()
  } catch { /* */ }
}

function ck_copyKey() {
  navigator.clipboard.writeText(ck_newKeyPlainText.value)
  ElMessage.success(t(`${P2}.messages.copied`))
}

function ck_handleEdit(row) {
  ck_editForm.id = row.id
  ck_editForm.name = row.name
  ck_editForm.abilities = row.abilities || []
  ck_editForm.ip_whitelist = row.ip_whitelist || ''
  ck_editForm.expires_at = row.expires_at
  ck_showEdit.value = true
}

async function ck_handleUpdate() {
  try {
    await updateKey(ck_editForm.id, {
      name: ck_editForm.name,
      abilities: ck_editForm.abilities,
      ip_whitelist: ck_editForm.ip_whitelist,
      expires_at: ck_editForm.expires_at,
    })
    ElMessage.success(t(`${P2}.messages.updated`))
    ck_showEdit.value = false
    ck_loadKeys()
  } catch { /* */ }
}

async function ck_handleToggle(row) {
  try {
    const { data: res } = await toggleKey(row.id)
    ElMessage.success(res.message || (row.is_active ? t(`${P2}.messages.disabled`) : t(`${P2}.messages.enabled`)))
    ck_loadKeys()
  } catch { /* */ }
}

async function ck_handleDelete(row) {
  try {
    await deleteKey(row.id)
    ElMessage.success(t(`${P2}.messages.deleted`))
    ck_loadKeys()
    ck_loadDashboard()
  } catch { /* */ }
}

function ck_formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString(ck_dateLocale.value, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

// ═══════════════════════════════════════════════════════
// SECTION 3: 审计日志 (Audit Logs) — prefix au_
// ═══════════════════════════════════════════════════════

const au_loading = ref(false)
const au_logs = ref([])
const au_apiKeysList = ref([])
const au_total = ref(0)
const au_page = ref(1)
const au_perPage = ref(20)
const au_detailVisible = ref(false)
const au_currentLog = ref(null)
const au_dateRange = ref(null)

const au_filters = reactive({
  api_key_id: '',
  action: '',
  date_from: '',
  date_to: '',
})

const AU_ACTION_TYPES = {
  created: 'success',
  updated: 'primary',
  deleted: 'danger',
  activated: 'success',
  deactivated: 'warning',
  regenerated: 'warning',
}

function au_actionType(action) { return AU_ACTION_TYPES[action] || 'info' }
function au_actionLabel(action) {
  const key = { created: 'created', updated: 'updated', deleted: 'deleted', activated: 'activated', deactivated: 'deactivated', regenerated: 'regenerated' }[action]
  return key ? t(`api_key_audit_page.actions.${key}`) : (action || '-')
}

const au_changeEntries = computed(() => {
  const log = au_currentLog.value
  if (!log) return []
  const entries = []
  if (log.old_values && typeof log.old_values === 'object') {
    for (const [key, val] of Object.entries(log.old_values)) {
      entries.push({
        field: key,
        old: val,
        new: log.new_values?.[key] ?? '-',
      })
    }
  }
  return entries
})

function au_formatValue(val) {
  if (val === null || val === undefined) return '-'
  if (typeof val === 'boolean') return val ? t('api_key_audit_page.yes') : t('api_key_audit_page.no')
  if (Array.isArray(val)) return val.join(', ') || t('api_key_audit_page.empty_arr')
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
}

function au_fmtTime(tVal) {
  if (!tVal) return '-'
  const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
  return new Date(tVal).toLocaleString(loc, {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}

async function au_loadKeys() {
  try {
    const { data: res } = await apiKeyApi.list({ per_page: 200 })
    au_apiKeysList.value = res.data?.data || res.data || []
  } catch { /* ignore */ }
}

async function au_loadLogs() {
  au_loading.value = true
  try {
    const params = { page: au_page.value, per_page: au_perPage.value }
    if (au_filters.api_key_id) params.api_key_id = au_filters.api_key_id
    if (au_filters.action) params.action = au_filters.action
    if (au_dateRange.value) {
      params.date_from = au_dateRange.value[0]
      params.date_to = au_dateRange.value[1]
    }
    const { data: res } = await apiKeyApi.allAuditLogs(params)
    au_logs.value = res.data?.data || res.data || []
    au_total.value = res.meta?.total || res.data?.total || au_logs.value.length
  } catch {
    au_logs.value = []
  } finally {
    au_loading.value = false
  }
}

function au_doSearch() {
  au_page.value = 1
  au_loadLogs()
}

function au_showDetail(log) {
  au_currentLog.value = log
  au_detailVisible.value = true
}

// ═══════════════════════════════════════════════════════
// Lifecycle
// ═══════════════════════════════════════════════════════

onMounted(() => {
  a1_fetchAll()
  ck_loadDashboard()
  ck_loadKeys()
  ck_loadAbilities()
  au_loadKeys()
  au_loadLogs()
})
</script>

<style scoped>
.api-key-center-page { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 22px; }

.page-header-tab { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left { display: flex; align-items: center; }
.header-right { display: flex; align-items: center; gap: 8px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }

.mb-4 { margin-bottom: 16px; }
.mr-1 { margin-right: 4px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-label { font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 6px; }
.stat-value { font-size: 24px; font-weight: 700; color: var(--el-text-color-primary); }

.text-primary { color: var(--el-color-primary); }
.text-success { color: var(--el-color-success); }
.text-warning { color: var(--el-color-warning); }
.text-danger { color: var(--el-color-danger); }
.text-info { color: var(--el-color-info); }
.text-muted { color: var(--el-text-color-placeholder); }

.key-name { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.name-text { font-weight: 500; }
.key-id-text { font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace; font-size: 12px; cursor: pointer; user-select: all; }
.ip-text { font-family: monospace; font-size: 12px; }
.font-mono { font-family: 'Courier New', Courier, monospace; }

.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0 0; }
:deep(.el-card__body) { padding: 16px; }
.w-full { width: 100%; }
.form-tip { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 4px; }
.section-title { font-size: 15px; font-weight: 600; margin: 0 0 12px; }

.perm-matrix { border: 1px solid var(--el-border-color-light); border-radius: 6px; overflow: hidden; margin-bottom: 16px; }
.perm-row { display: flex; align-items: center; border-bottom: 1px solid var(--el-border-color-light); }
.perm-row:last-child { border-bottom: none; }
.perm-header { background: var(--el-fill-color-light); font-weight: 600; font-size: 12px; }
.perm-cell { flex: 1; padding: 10px 8px; text-align: center; min-width: 60px; }
.perm-cell.endpoint-name { flex: 0 0 120px; text-align: left; font-weight: 500; }
.perm-cell.endpoint-desc { flex: 0 0 180px; text-align: left; font-size: 12px; color: var(--el-text-color-secondary); }

.key-result-box {
  display: flex; align-items: center; gap: 12px;
  background: var(--el-bg-color-page); padding: 12px 16px; border-radius: 6px;
}
.key-result-box code { flex: 1; word-break: break-all; font-size: 14px; }
</style>
