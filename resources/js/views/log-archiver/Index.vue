<template>
  <el-tabs v-model="logMainTab" type="border-card">
    <!-- Tab1: 日志归档 -->
    <el-tab-pane label="日志归档" name="archiver">
      <div>
        <el-card shadow="never" class="mb-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-medium">{{ t('log_archiver_page.title') }}</h2>
            <el-button size="small" @click="processExpired" :loading="processing">{{ t('log_archiver_page.process_expired') }}</el-button>
          </div>
          <el-row :gutter="16">
            <el-col :span="6">
              <el-card shadow="hover" class="mb-2 text-center">
                <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.archived_records') }}</div>
                <div class="text-2xl font-bold mt-1">{{ dashboard.total_archived_records || 0 }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover" class="mb-2 text-center">
                <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.total_size') }}</div>
                <div class="text-2xl font-bold mt-1">{{ dashboard.total_size_human || '0 B' }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover" class="mb-2 text-center">
                <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.pending_restores') }}</div>
                <div class="text-2xl font-bold mt-1 text-yellow-500">{{ dashboard.pending_restores || 0 }}</div>
              </el-card>
            </el-col>
            <el-col :span="6">
              <el-card shadow="hover" class="mb-2 text-center">
                <div class="text-xs text-gray-500">{{ t('audit_archive_page.stats.available_restores') }}</div>
                <div class="text-2xl font-bold mt-1 text-green-500">{{ dashboard.available_restores || 0 }}</div>
              </el-card>
            </el-col>
          </el-row>
        </el-card>

        <el-card shadow="never">
          <el-tabs v-model="activeTab">
            <!-- 归档策略 -->
            <el-tab-pane :label="t('audit_archive_page.tabs.policies')" name="policies">
              <div class="mb-3 flex justify-end">
                <el-button size="small" type="primary" @click="showPolicyDialog = true; isEditing = false; policyForm = defaultPolicy()">{{ t('audit_archive_page.btn_new_policy') }}</el-button>
              </div>
              <el-table :data="policies" v-loading="loadingP" stripe style="width:100%">
                <el-table-column prop="name" :label="t('log_archiver_page.cols.name')" width="140" />
                <el-table-column prop="type" :label="t('audit_archive_page.cols.type')" width="100">
                  <template #default="{ row }">{{ logTypeLabel(row.type) }}</template>
                </el-table-column>
                <el-table-column prop="storage_tier" :label="t('audit_archive_page.cols.storage_tier')" width="100">
                  <template #default="{ row }">
                    <el-tag :type="tierType(row.storage_tier)" size="small">{{ tierLabel(row.storage_tier) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="archive_after_days" :label="t('log_archiver_page.cols.archive_after_days')" width="90" align="center" />
                <el-table-column prop="delete_after_days" :label="t('log_archiver_page.cols.delete_after_days')" width="90" align="center" />
                <el-table-column prop="archive_records_count" :label="t('log_archiver_page.cols.archive_count')" width="80" align="center" />
                <el-table-column :label="t('log_archiver_page.cols.active')" width="70" align="center">
                  <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('audit_retention_page.status_enabled') : t('audit_retention_page.status_disabled') }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="last_executed_at" :label="t('log_archiver_page.cols.last_executed_at')" width="180" />
                <el-table-column :label="t('audit_archive_page.cols.actions')" width="180" fixed="right">
                  <template #default="{ row }">
                    <el-button size="small" @click="editPolicy(row)">{{ t('actions.edit') }}</el-button>
                    <el-button size="small" type="primary" @click="handleArchive(row)" :loading="archivingPolicy === row.id">{{ t('audit_archive_page.btn_archive') }}</el-button>
                  </template>
                </el-table-column>
              </el-table>
            </el-tab-pane>

            <!-- 归档记录 -->
            <el-tab-pane :label="t('audit_archive_page.tabs.records')" name="records">
              <div class="mb-3 flex gap-2">
                <el-select v-model="rFilters.type" :placeholder="t('audit_archive_page.cols.type')" clearable size="small" style="width:120px">
                  <el-option v-for="opt in logTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-select v-model="rFilters.status" :placeholder="t('audit_archive_page.cols.status')" clearable size="small" style="width:120px">
                  <el-option v-for="opt in recordStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-button size="small" @click="fetchRecords">{{ t('actions.search') }}</el-button>
              </div>
              <el-table :data="records" v-loading="loadingR" stripe style="width:100%">
                <el-table-column prop="type" :label="t('audit_archive_page.cols.type')" width="80">
                  <template #default="{ row }">{{ logTypeLabel(row.type) }}</template>
                </el-table-column>
                <el-table-column :label="t('audit_archive_page.cols.status')" width="80">
                  <template #default="{ row }"><el-tag :type="recStatusType(row.status)" size="small">{{ recordStatusLabel(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="storage_class" :label="t('log_archiver_page.cols.storage_class')" width="120" />
                <el-table-column prop="archived_logs" :label="t('log_archiver_page.cols.archived_logs')" width="70" align="center" />
                <el-table-column prop="file_size_bytes" :label="t('audit_archive_page.cols.size')" width="80">
                  <template #default="{ row }">{{ formatBytes(row.file_size_bytes) }}</template>
                </el-table-column>
                <el-table-column prop="archive_date_to" :label="t('log_archiver_page.cols.archive_date_to')" width="120" />
                <el-table-column prop="executed_at" :label="t('log_archiver_page.cols.executed_at')" width="180" />
                <el-table-column :label="t('audit_archive_page.cols.actions')" width="120" fixed="right">
                  <template #default="{ row }">
                    <el-button v-if="row.status === 'completed'" size="small" @click="handleRestore(row)">{{ t('log_archiver_page.btn_restore') }}</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <div class="mt-3 flex justify-between items-center">
                <span class="text-sm text-gray-500">{{ t('audit_archive_page.list_total', { total: rPagination.total }) }}</span>
                <el-pagination v-model:current-page="rPagination.page" :page-size="rPagination.per_page" :total="rPagination.total" layout="prev, pager, next" small @current-change="fetchRecords" />
              </div>
            </el-tab-pane>

            <!-- 取回请求 -->
            <el-tab-pane :label="t('audit_archive_page.tabs.restores')" name="restores">
              <div class="mb-3 flex gap-2">
                <el-select v-model="sFilters.status" :placeholder="t('audit_archive_page.cols.status')" clearable size="small" style="width:140px">
                  <el-option v-for="opt in restoreStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-button size="small" @click="fetchRestores">{{ t('actions.search') }}</el-button>
              </div>
              <el-table :data="restoreRequests" v-loading="loadingS" stripe style="width:100%">
                <el-table-column :label="t('audit_archive_page.cols.status')" width="100">
                  <template #default="{ row }"><el-tag :type="restoreStatusType(row.status)" size="small">{{ restoreStatusLabel(row.status) }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="reason" :label="t('log_archiver_page.cols.reason')" min-width="200" show-overflow-tooltip />
                <el-table-column prop="requester_type" :label="t('log_archiver_page.cols.requester_type')" width="100" />
                <el-table-column prop="requested_at" :label="t('audit_archive_page.cols.requested_at')" width="180" />
                <el-table-column prop="available_until" :label="t('audit_archive_page.cols.available_until')" width="180" />
                <el-table-column prop="error_message" :label="t('log_archiver_page.cols.error_message')" width="180" show-overflow-tooltip />
                <el-table-column :label="t('audit_archive_page.cols.actions')" width="180" fixed="right">
                  <template #default="{ row }">
                    <el-button v-if="row.status === 'pending'" size="small" type="primary" @click="handleExecuteRestore(row)">{{ t('audit_archive_page.btn_execute_restore') }}</el-button>
                    <el-button v-if="['pending','restoring'].includes(row.status)" size="small" type="warning" @click="handleCancelRestore(row)">{{ t('actions.cancel') }}</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <div class="mt-3 flex justify-between items-center">
                <span class="text-sm text-gray-500">{{ t('audit_archive_page.list_total', { total: sPagination.total }) }}</span>
                <el-pagination v-model:current-page="sPagination.page" :page-size="sPagination.per_page" :total="sPagination.total" layout="prev, pager, next" small @current-change="fetchRestores" />
              </div>
            </el-tab-pane>
          </el-tabs>
        </el-card>

        <!-- 策略对话框 -->
        <el-dialog v-model="showPolicyDialog" :title="isEditing ? t('audit_archive_page.dialog.edit_policy') : t('audit_archive_page.dialog.new_policy')" width="500px">
          <el-form :model="policyForm" label-width="130px">
            <el-form-item :label="t('log_archiver_page.form.name')"><el-input v-model="policyForm.name" /></el-form-item>
            <el-form-item :label="t('audit_archive_page.form.type')">
              <el-select v-model="policyForm.type" :disabled="isEditing" style="width:100%">
                <el-option v-for="opt in logTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item :label="t('audit_archive_page.form.storage_tier')">
              <el-select v-model="policyForm.storage_tier" style="width:100%">
                <el-option v-for="opt in tierOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item :label="t('log_archiver_page.form.archive_after_days')"><el-input-number v-model="policyForm.archive_after_days" :min="1" :max="3650" style="width:100%" /></el-form-item>
            <el-form-item :label="t('log_archiver_page.form.delete_after_days')"><el-input-number v-model="policyForm.delete_after_days" :min="1" :max="7300" style="width:100%" /></el-form-item>
            <el-form-item :label="t('log_archiver_page.form.compress_archive')"><el-switch v-model="policyForm.compress_archive" /></el-form-item>
            <el-form-item :label="t('audit_archive_page.form.enabled')"><el-switch v-model="policyForm.is_active" /></el-form-item>
            <el-form-item :label="t('log_archiver_page.form.description')"><el-input v-model="policyForm.description" type="textarea" :rows="2" /></el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="showPolicyDialog = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" @click="handleSavePolicy" :loading="savingPolicy">{{ t('actions.save') }}</el-button>
          </template>
        </el-dialog>

        <!-- 取回归档对话框 -->
        <el-dialog v-model="showRestoreDialog" :title="t('log_archiver_page.dialog.request_restore')" width="400px">
          <p class="mb-3">{{ t('log_archiver_page.dialog.restore_confirm', { id: restoreRecordId }) }}</p>
          <el-input v-model="restoreReason" type="textarea" :rows="2" :placeholder="t('log_archiver_page.form.restore_reason_ph')" />
          <template #footer>
            <el-button @click="showRestoreDialog = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" @click="handleConfirmRestore" :loading="restoring">{{ t('audit_archive_page.btn_submit_restore') }}</el-button>
          </template>
        </el-dialog>
      </div>
    </el-tab-pane>

    <!-- Tab2: 日志聚合 -->
    <el-tab-pane label="日志聚合" name="aggregation">
      <div v-if="la_tabVisited" class="log-aggregation-page">
        <div class="page-header">
          <h2>{{ t('log_aggregation_page.title') }}</h2>
          <p class="text-muted">{{ t('log_aggregation_page.subtitle') }}</p>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
          <el-col :span="6">
            <el-card shadow="never">
              <div class="stat-card"><div class="stat-value">{{ la_stats.total_entries }}</div><div class="stat-label">{{ t('log_aggregation_page.stats.total_entries') }}</div></div>
            </el-card>
          </el-col>
          <el-col :span="6">
            <el-card shadow="never" style="border-top:3px solid #f56c6c">
              <div class="stat-card"><div class="stat-value" style="color:#f56c6c">{{ la_stats.error_count }}</div><div class="stat-label">{{ t('log_aggregation_page.stats.error_count') }}</div></div>
            </el-card>
          </el-col>
          <el-col :span="6">
            <el-card shadow="never">
              <div class="stat-card"><div class="stat-value">{{ la_stats.total_indices }}</div><div class="stat-label">{{ t('log_aggregation_page.stats.total_indices') }}</div></div>
            </el-card>
          </el-col>
          <el-col :span="6">
            <el-card shadow="never">
              <div class="stat-card"><div class="stat-value">{{ la_stats.avg_duration_ms }}ms</div><div class="stat-label">{{ t('log_aggregation_page.stats.avg_response') }}</div></div>
            </el-card>
          </el-col>
        </el-row>

        <el-tabs v-model="la_activeTab">
          <!-- 搜索 -->
          <el-tab-pane :label="t('log_aggregation_page.tabs.search')" name="search">
            <el-card shadow="never" class="mb-4">
              <el-form :inline="true" :model="la_filters" size="small" @keyup.enter="la_doSearch">
                <el-form-item :label="t('audit_logs_page.filter_keyword')">
                  <el-input v-model="la_filters.q" :placeholder="t('log_aggregation_page.filters.keyword_ph')" clearable style="width:220px" />
                </el-form-item>
                <el-form-item :label="t('log_aggregation_page.filters.level')">
                  <el-select v-model="la_filters.level" clearable :placeholder="t('audit_logs_page.placeholder_all')" style="width:110px">
                    <el-option label="DEBUG" value="debug" />
                    <el-option label="INFO" value="info" />
                    <el-option label="WARNING" value="warning" />
                    <el-option label="ERROR" value="error" />
                    <el-option label="CRITICAL" value="critical" />
                  </el-select>
                </el-form-item>
                <el-form-item :label="t('apm_page.columns.method')">
                  <el-select v-model="la_filters.method" clearable :placeholder="t('audit_logs_page.placeholder_all')" style="width:100px">
                    <el-option label="GET" value="GET" />
                    <el-option label="POST" value="POST" />
                    <el-option label="PUT" value="PUT" />
                    <el-option label="DELETE" value="DELETE" />
                  </el-select>
                </el-form-item>
                <el-form-item :label="t('apm_page.columns.path')">
                  <el-input v-model="la_filters.path" :placeholder="t('log_aggregation_page.filters.path_ph')" clearable style="width:150px" />
                </el-form-item>
                <el-form-item :label="t('log_aggregation_page.filters.status_code')">
                  <el-input v-model="la_filters.status_code" :placeholder="t('log_aggregation_page.filters.status_code_ph')" clearable style="width:100px" type="number" />
                </el-form-item>
                <el-form-item :label="t('log_aggregation_page.filters.duration_min')">
                  <el-input v-model="la_filters.duration_min" :placeholder="t('log_aggregation_page.filters.duration_ph')" clearable style="width:100px" type="number" />
                </el-form-item>
                <el-form-item>
                  <el-button type="primary" @click="la_doSearch">{{ t('actions.search') }}</el-button>
                  <el-button @click="la_resetFilters">{{ t('actions.reset') }}</el-button>
                  <el-button text @click="la_showSaveDialog = true">{{ t('log_aggregation_page.filters.save_search') }}</el-button>
                </el-form-item>
              </el-form>
            </el-card>

            <el-card shadow="never">
              <el-table :data="la_entries" v-loading="la_searchLoading" stripe size="small" @row-click="la_showDetail">
                <el-table-column prop="logged_at" :label="t('audit_logs_page.col_time')" width="160">
                  <template #default="{ row }">{{ row.logged_at }}</template>
                </el-table-column>
                <el-table-column prop="level" :label="t('log_aggregation_page.cols.level')" width="90">
                  <template #default="{ row }">
                    <el-tag :type="la_levelType(row.level)" size="small" effect="dark">{{ row.level?.toUpperCase() }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="message" :label="t('log_aggregation_page.cols.message')" min-width="300">
                  <template #default="{ row }">
                    <div class="log-message">{{ row.message }}</div>
                  </template>
                </el-table-column>
                <el-table-column prop="request_method" :label="t('apm_page.columns.method')" width="70">
                  <template #default="{ row }">
                    <el-tag size="small" effect="plain">{{ row.request_method }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="request_path" :label="t('apm_page.columns.path')" width="200">
                  <template #default="{ row }">
                    <span class="text-muted">{{ row.request_path }}</span>
                  </template>
                </el-table-column>
                <el-table-column prop="duration_ms" :label="t('apm_page.columns.duration')" width="80">
                  <template #default="{ row }">{{ row.duration_ms ? `${row.duration_ms}ms` : '-' }}</template>
                </el-table-column>
                <el-table-column prop="response_status" :label="t('log_aggregation_page.cols.status')" width="70">
                  <template #default="{ row }">
                    <el-tag :type="row.response_status >= 400 ? 'danger' : 'success'" size="small">{{ row.response_status }}</el-tag>
                  </template>
                </el-table-column>
              </el-table>

              <div class="mt-4 flex-center" v-if="la_total > la_perPage">
                <el-pagination
                  v-model:current-page="la_page"
                  :page-size="la_perPage"
                  :total="la_total"
                  layout="total, prev, pager, next"
                  small
                  background
                  @current-change="la_loadEntries"
                />
              </div>
            </el-card>
          </el-tab-pane>

          <!-- 分析 -->
          <el-tab-pane :label="t('log_aggregation_page.tabs.analytics')" name="analytics">
            <el-row :gutter="16">
              <el-col :span="12">
                <el-card shadow="never">
                  <template #header>{{ t('log_aggregation_page.analytics.level_distribution') }}</template>
                  <div v-if="la_levelStats.length" class="analytics-list">
                    <div v-for="s in la_levelStats" :key="s.level" class="analytics-item">
                      <el-tag :type="la_levelType(s.level)" size="small" effect="dark">{{ s.level?.toUpperCase() }}</el-tag>
                      <span class="analytics-count">{{ t('log_aggregation_page.analytics.count_items', { n: s.count }) }}</span>
                      <span class="analytics-avg">{{ t('log_aggregation_page.analytics.avg_ms', { n: Math.round(s.avg_duration) }) }}</span>
                    </div>
                  </div>
                  <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
                </el-card>
              </el-col>
              <el-col :span="12">
                <el-card shadow="never">
                  <template #header>{{ t('log_aggregation_page.analytics.slow_queries_top') }}</template>
                  <el-table :data="la_slowQueries" size="small" stripe v-if="la_slowQueries.length">
                    <el-table-column prop="request_path" :label="t('apm_page.columns.path')" min-width="200" />
                    <el-table-column prop="duration_ms" :label="t('apm_page.columns.duration')" width="90">
                      <template #default="{ row }">
                        <span class="text-danger">{{ row.duration_ms }}ms</span>
                      </template>
                    </el-table-column>
                    <el-table-column prop="logged_at" :label="t('audit_logs_page.col_time')" width="160" />
                  </el-table>
                  <el-empty v-else :description="t('apm_page.empty.no_slow_requests')" :image-size="60" />
                </el-card>
              </el-col>
            </el-row>
            <el-card shadow="never" class="mt-4">
              <template #header>{{ t('log_aggregation_page.analytics.path_stats') }}</template>
              <el-table :data="la_pathStats" size="small" stripe v-if="la_pathStats.length">
                <el-table-column prop="request_path" :label="t('apm_page.columns.path')" min-width="250" />
                <el-table-column prop="hits" :label="t('apm_page.columns.request_count')" width="100" />
                <el-table-column prop="avg_duration" :label="t('apm_page.columns.avg_duration')" width="100">
                  <template #default="{ row }">{{ Math.round(row.avg_duration) }}ms</template>
                </el-table-column>
                <el-table-column prop="errors" :label="t('log_aggregation_page.cols.errors')" width="80">
                  <template #default="{ row }">
                    <span :class="row.errors > 0 ? 'text-danger' : ''">{{ row.errors }}</span>
                  </template>
                </el-table-column>
              </el-table>
              <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
            </el-card>
          </el-tab-pane>

          <!-- 保存的搜索 -->
          <el-tab-pane :label="t('saved_search.label')" name="saved">
            <el-card shadow="never">
              <el-table :data="la_savedSearches" stripe v-loading="la_savedLoading">
                <el-table-column prop="name" :label="t('log_archiver_page.cols.name')" min-width="200" />
                <el-table-column prop="creator.name" :label="t('log_aggregation_page.cols.creator')" width="120" />
                <el-table-column :label="t('log_aggregation_page.cols.shared')" width="80">
                  <template #default="{ row }">
                    <el-tag :type="row.is_shared ? 'success' : 'info'" size="small">{{ la_sharedLabel(row.is_shared) }}</el-tag>
                  </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('search_center_page.columns.created_at')" width="170" />
                <el-table-column :label="t('audit_archive_page.cols.actions')" width="120">
                  <template #default="{ row }">
                    <el-button size="small" text @click="la_applySavedSearch(row)">{{ t('search_center_page.saved.apply') }}</el-button>
                    <el-popconfirm :title="t('search_center_page.saved.confirm_delete')" @confirm="la_deleteSavedSearch(row.id)">
                      <template #reference><el-button size="small" text type="danger">{{ t('actions.delete') }}</el-button></template>
                    </el-popconfirm>
                  </template>
                </el-table-column>
              </el-table>
              <el-empty v-if="!la_savedLoading && !la_savedSearches.length" :description="t('saved_search.empty')" :image-size="60" />
            </el-card>
          </el-tab-pane>
        </el-tabs>

        <!-- 日志详情抽屉 -->
        <el-drawer v-model="la_detailVisible" :title="t('log_aggregation_page.detail.title')" size="550px">
          <template v-if="la_detail">
            <el-descriptions :column="1" border size="small">
              <el-descriptions-item :label="t('audit_logs_page.col_time')">{{ la_detail.logged_at }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.cols.level')">
                <el-tag :type="la_levelType(la_detail.level)" size="small" effect="dark">{{ la_detail.level?.toUpperCase() }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.cols.message')">{{ la_detail.message }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.detail.trace_id')">{{ la_detail.trace_id || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.detail.source')">{{ la_detail.index?.source || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.detail.channel')">{{ la_detail.channel || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.detail.file')">{{ la_detail.file ? `${la_detail.file}:${la_detail.line}` : '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('audit_logs_page.col_ip')">{{ la_detail.ip || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.detail.request')">{{ la_detail.request_method }} {{ la_detail.request_path }}</el-descriptions-item>
              <el-descriptions-item :label="t('log_aggregation_page.detail.response_status')">{{ la_detail.response_status }}</el-descriptions-item>
              <el-descriptions-item :label="t('apm_page.columns.duration')">{{ la_detail.duration_ms ? `${la_detail.duration_ms}ms` : '-' }}</el-descriptions-item>
            </el-descriptions>
            <h4 class="mt-4">{{ t('log_aggregation_page.detail.context_data') }}</h4>
            <pre class="context-json">{{ JSON.stringify(la_detail.context, null, 2) || t('log_aggregation_page.detail.none') }}</pre>
          </template>
        </el-drawer>

        <!-- 保存搜索对话框 -->
        <el-dialog v-model="la_showSaveDialog" :title="t('log_aggregation_page.save_dialog.title')" width="400px">
          <el-form>
            <el-form-item :label="t('saved_search.name')">
              <el-input v-model="la_saveName" :placeholder="t('saved_search.name_ph')" />
            </el-form-item>
            <el-form-item>
              <el-checkbox v-model="la_saveShared">{{ t('saved_search.share') }}</el-checkbox>
            </el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="la_showSaveDialog = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" :loading="la_saving" @click="la_handleSaveSearch">{{ t('actions.save') }}</el-button>
          </template>
        </el-dialog>
      </div>
    </el-tab-pane>
  </el-tabs>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
  getArchiverDashboard, getArchivePolicies, getArchiveRecords, getRestoreRequests,
  upsertArchivePolicy, executeArchive, requestRestore, executeRestore,
  cancelRestore, processExpiredRestores,
} from '../../api/logArchiver';
import logApi from '@/api/logAggregation';

const { t } = useI18n();

// ── 主Tab ──
const logMainTab = ref('archiver');

// ── 日志聚合懒加载 ──
const la_tabVisited = ref(false);
watch(logMainTab, (val) => { if (val === 'aggregation' && !la_tabVisited.value) la_tabVisited.value = true; });

// ══════════════════════════════════════════════
//  日志归档
// ══════════════════════════════════════════════

const logTypeKeys = ['audit', 'security', 'error', 'system'];
const recordStatusKeys = ['completed', 'processing', 'failed', 'skipped'];
const restoreStatusKeys = ['pending', 'restoring', 'available', 'expired', 'failed', 'cancelled'];
const tierKeys = ['warm', 'cold', 'frozen'];

const loadingP = ref(false);
const loadingR = ref(false);
const loadingS = ref(false);
const processing = ref(false);
const savingPolicy = ref(false);
const archivingPolicy = ref(null);
const restoring = ref(false);
const activeTab = ref('policies');

const dashboard = ref({});
const policies = ref([]);
const records = ref([]);
const restoreRequests = ref([]);

const rPagination = ref({ page: 1, per_page: 20, total: 0 });
const sPagination = ref({ page: 1, per_page: 20, total: 0 });

const rFilters = ref({ type: '', status: '' });
const sFilters = ref({ status: '' });

const showPolicyDialog = ref(false);
const isEditing = ref(false);
const policyForm = ref(defaultPolicy());

const showRestoreDialog = ref(false);
const restoreRecordId = ref(null);
const restoreReason = ref('');

const logTypeLabels = computed(() =>
  Object.fromEntries(logTypeKeys.map((key) => [key, t(`log_archiver_page.log_type.${key}`)]))
);

const logTypeOptions = computed(() =>
  logTypeKeys.map((value) => ({
    value,
    label: t(`log_archiver_page.log_type.${value}`),
  }))
);

const recordStatusLabels = computed(() =>
  Object.fromEntries(recordStatusKeys.map((key) => [key, t(`log_archiver_page.record_status.${key}`)]))
);

const recordStatusOptions = computed(() =>
  recordStatusKeys.map((value) => ({
    value,
    label: t(`log_archiver_page.record_status.${value}`),
  }))
);

const restoreStatusLabelsMap = computed(() =>
  Object.fromEntries(restoreStatusKeys.map((key) => [
    key,
    t(`audit_archive_page.restore_status.${key}`, t(`log_archiver_page.restore_status.${key}`, key)),
  ]))
);

const restoreStatusOptions = computed(() =>
  ['pending', 'restoring', 'available', 'expired'].map((value) => ({
    value,
    label: restoreStatusLabelsMap.value[value],
  }))
);

const tierLabels = computed(() =>
  Object.fromEntries(tierKeys.map((key) => [key, t(`audit_archive_page.tier.${key}`)]))
);

const tierOptions = computed(() =>
  tierKeys.map((value) => ({
    value,
    label: t(`audit_archive_page.tier.${value}`),
  }))
);

function logTypeLabel(type) {
  return logTypeLabels.value[type] || type;
}

function recordStatusLabel(status) {
  return recordStatusLabels.value[status] || status;
}

function restoreStatusLabel(status) {
  return restoreStatusLabelsMap.value[status] || status;
}

function tierLabel(tier) {
  return tierLabels.value[tier] || tier;
}

function defaultPolicy() {
  return { name: '', type: 'audit', storage_tier: 'cold', archive_after_days: 90, delete_after_days: 365, compress_archive: true, is_active: true, description: '' };
}

onMounted(async () => {
  await fetchDashboard();
  await fetchPolicies();
});

async function fetchDashboard() {
  try { const res = await getArchiverDashboard(); dashboard.value = res.data.data || {}; } catch (e) {}
}
async function fetchPolicies() {
  loadingP.value = true;
  try { const res = await getArchivePolicies(); policies.value = res.data.data || []; } catch (e) {}
  loadingP.value = false;
}
async function fetchRecords() {
  loadingR.value = true;
  try {
    const params = { ...rFilters.value, page: rPagination.value.page, per_page: rPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getArchiveRecords(params);
    const d = res.data.data; records.value = d.data || [];
    rPagination.value = { page: d.current_page || 1, per_page: d.per_page || 20, total: d.total || 0 };
  } catch (e) {}
  loadingR.value = false;
}
async function fetchRestores() {
  loadingS.value = true;
  try {
    const params = { ...sFilters.value, page: sPagination.value.page, per_page: sPagination.value.per_page };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await getRestoreRequests(params);
    const d = res.data.data; restoreRequests.value = d.data || [];
    sPagination.value = { page: d.current_page || 1, per_page: d.per_page || 20, total: d.total || 0 };
  } catch (e) {}
  loadingS.value = false;
}

function editPolicy(row) {
  isEditing.value = true;
  policyForm.value = { ...row };
  showPolicyDialog.value = true;
}
async function handleSavePolicy() {
  savingPolicy.value = true;
  try {
    await upsertArchivePolicy(policyForm.value);
    ElMessage.success(t('audit_archive_page.messages.policy_saved'));
    showPolicyDialog.value = false;
    await fetchPolicies();
  } catch (e) {}
  savingPolicy.value = false;
}
async function handleArchive(row) {
  archivingPolicy.value = row.id;
  try {
    const res = await executeArchive(row.id);
    ElMessage.success(res.data.message || t('log_archiver_page.messages.archive_done'));
    await fetchPolicies();
    await fetchRecords();
    await fetchDashboard();
  } catch (e) {}
  archivingPolicy.value = null;
}
function handleRestore(row) {
  restoreRecordId.value = row.id;
  restoreReason.value = '';
  showRestoreDialog.value = true;
}
async function handleConfirmRestore() {
  if (!restoreReason.value.trim()) { ElMessage.warning(t('log_archiver_page.messages.restore_reason_required')); return; }
  restoring.value = true;
  try {
    await requestRestore(restoreRecordId.value, { reason: restoreReason.value });
    ElMessage.success(t('audit_archive_page.messages.restore_requested'));
    showRestoreDialog.value = false;
    await fetchRestores();
  } catch (e) {}
  restoring.value = false;
}
async function handleExecuteRestore(row) {
  try {
    await executeRestore(row.id);
    ElMessage.success(t('log_archiver_page.messages.restore_done'));
    await fetchRestores();
  } catch (e) {}
}
async function handleCancelRestore(row) {
  try {
    await ElMessageBox.confirm(t('log_archiver_page.messages.confirm_cancel_restore'), t('actions.confirm'));
    await cancelRestore(row.id);
    ElMessage.success(t('audit_archive_page.messages.cancelled'));
    await fetchRestores();
  } catch (e) {}
}
async function processExpired() {
  processing.value = true;
  try {
    const res = await processExpiredRestores();
    ElMessage.success(res.data.message || t('audit_archive_page.messages.process_done'));
    await fetchRestores();
  } catch (e) {}
  processing.value = false;
}

function tierType(tier) { return { warm: 'warning', cold: 'primary', frozen: 'info' }[tier] || 'info'; }
function recStatusType(s) { return { completed: 'success', processing: 'warning', failed: 'danger', skipped: 'info' }[s] || 'info'; }
function restoreStatusType(s) { return { pending: 'warning', restoring: 'primary', available: 'success', expired: 'info', failed: 'danger', cancelled: 'default' }[s] || 'info'; }
function formatBytes(b) {
  if (!b) return '0 B';
  const u = ['B','KB','MB','GB','TB']; let i = 0;
  while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
  return b.toFixed(1) + ' ' + u[i];
}

// ══════════════════════════════════════════════
//  日志聚合
// ══════════════════════════════════════════════

const la_activeTab = ref('search');
const la_stats = ref({ total_entries: 0, error_count: 0, total_indices: 0, avg_duration_ms: 0 });
const la_entries = ref([]);
const la_total = ref(0);
const la_page = ref(1);
const la_perPage = ref(50);
const la_searchLoading = ref(false);
const la_detailVisible = ref(false);
const la_detail = ref(null);
const la_levelStats = ref([]);
const la_slowQueries = ref([]);
const la_pathStats = ref([]);
const la_savedSearches = ref([]);
const la_savedLoading = ref(false);
const la_showSaveDialog = ref(false);
const la_saveName = ref('');
const la_saveShared = ref(false);
const la_saving = ref(false);

const la_filters = reactive({
  q: '', level: '', method: '', path: '', status_code: '', duration_min: '',
});

const la_sharedLabels = computed(() => ({
  true: t('search_center_page.saved.shared_tag'),
  false: t('log_aggregation_page.shared.private'),
}));

function la_sharedLabel(isShared) {
  return la_sharedLabels.value[isShared ? 'true' : 'false'];
}

function la_levelType(level) {
  return { debug: 'info', info: '', warning: 'warning', error: 'danger', critical: 'danger' }[level] || 'info';
}

async function la_loadStats() {
  try { const res = await logApi.getDashboard(); la_stats.value = res.data?.data || {} } catch {}
}

async function la_loadEntries() {
  la_searchLoading.value = true;
  try {
    const params = { page: la_page.value, per_page: la_perPage.value, ...la_filters };
    Object.keys(params).forEach(k => { if (!params[k] && params[k] !== 0) delete params[k] });
    const res = await logApi.search(params);
    const d = res.data?.data || {};
    la_entries.value = d.data || [];
    la_total.value = d.total || 0;
  } catch { ElMessage.error(t('log_aggregation_page.messages.search_failed')); }
  finally { la_searchLoading.value = false; }
}

function la_doSearch() { la_page.value = 1; la_loadEntries(); }

function la_resetFilters() {
  Object.keys(la_filters).forEach(k => la_filters[k] = '');
  la_doSearch();
}

async function la_showDetail(row) {
  try {
    const res = await logApi.show(row.id);
    la_detail.value = res.data?.data || row;
  } catch { la_detail.value = row; }
  la_detailVisible.value = true;
}

async function la_loadAnalytics() {
  try {
    const [levelRes, slowRes, pathRes] = await Promise.all([
      logApi.getLevelStats(),
      logApi.getSlowQueries(),
      logApi.getPathStats(),
    ]);
    la_levelStats.value = levelRes.data?.data || [];
    la_slowQueries.value = slowRes.data?.data || [];
    la_pathStats.value = pathRes.data?.data || [];
  } catch {}
}

async function la_loadSavedSearches() {
  la_savedLoading.value = true;
  try { const res = await logApi.listSavedSearches(); la_savedSearches.value = res.data?.data || []; }
  catch {} finally { la_savedLoading.value = false; }
}

function la_applySavedSearch(row) {
  if (row.filters) Object.assign(la_filters, row.filters);
  la_activeTab.value = 'search';
  la_doSearch();
  ElMessage.success(t('log_aggregation_page.messages.applied', { name: row.name }));
}

async function la_handleSaveSearch() {
  if (!la_saveName.value.trim()) return;
  la_saving.value = true;
  try {
    await logApi.saveSearch({ name: la_saveName.value, filters: { ...la_filters }, is_shared: la_saveShared.value });
    ElMessage.success(t('saved_search.saved'));
    la_showSaveDialog.value = false;
    la_saveName.value = '';
    la_saveShared.value = false;
    la_loadSavedSearches();
  } catch { ElMessage.error(t('saved_search.save_fail')); }
  finally { la_saving.value = false; }
}

async function la_deleteSavedSearch(id) {
  try { await logApi.deleteSavedSearch(id); ElMessage.success(t('saved_search.deleted')); la_loadSavedSearches(); }
  catch { ElMessage.error(t('saved_search.delete_fail')); }
}

// 日志聚合初始化 — 仅在 Tab 首次访问时触发
watch(la_tabVisited, (visited) => {
  if (visited) {
    la_loadStats();
    la_loadEntries();
    la_loadAnalytics();
    la_loadSavedSearches();
  }
});
</script>

<style scoped>
.log-aggregation-page { padding: 20px; }
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 22px; font-weight: bold; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-muted { color: #909399; font-size: 12px; }
.text-danger { color: #f56c6c; font-weight: 600; }
.flex-center { display: flex; justify-content: center; }
.log-message { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 400px; }
.analytics-list { display: flex; flex-direction: column; gap: 8px; }
.analytics-item { display: flex; align-items: center; gap: 12px; padding: 8px; background: #f8f9fa; border-radius: 6px; }
.analytics-count { flex: 1; font-weight: 600; }
.analytics-avg { color: #909399; font-size: 12px; }
.context-json { background: #f5f7fa; padding: 12px; border-radius: 6px; font-size: 12px; max-height: 300px; overflow: auto; }
</style>
