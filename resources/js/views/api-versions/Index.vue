<template>
  <div class="api-version-management">
    <el-tabs v-model="apiMainTab">
      <el-tab-pane :label="t('api_versions_page.tabs.version_management')" name="versionsMain">

    <el-tabs v-model="activeTab">
      <el-tab-pane :label="t('api_versions_page.tabs.versions')" name="versions">
        <div class="section-header">
          <h3>{{ t('api_versions_page.titles.versions') }}</h3>
          <el-button type="primary" @click="showCreateDialog = true">
            <el-icon><Plus /></el-icon> {{ t('api_versions_page.create_version_btn') }}
          </el-button>
        </div>

        <el-table :data="versions" stripe style="width: 100%">
          <el-table-column prop="version" :label="t('api_versions_page.cols.version')" width="100" />
          <el-table-column prop="base_path" :label="t('api_versions_page.cols.base_path')" width="160" />
          <el-table-column prop="name" :label="t('api_versions_page.cols.name')" min-width="140" />
          <el-table-column prop="status" :label="t('api_versions_page.cols.status')" width="100">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">
                {{ statusLabel(row.status) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('api_versions_page.cols.default')" width="70" align="center">
            <template #default="{ row }">
              <el-tag v-if="row.is_default" type="success" size="small">{{ t('api_versions_page.cols.default') }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="deprecated_at" :label="t('api_versions_page.cols.deprecated_at')" width="120">
            <template #default="{ row }">{{ row.deprecated_at ? formatDate(row.deprecated_at) : '-' }}</template>
          </el-table-column>
          <el-table-column prop="sunset_at" :label="t('api_versions_page.cols.planned_sunset')" width="120">
            <template #default="{ row }">{{ row.sunset_at ? formatDate(row.sunset_at) : '-' }}</template>
          </el-table-column>
          <el-table-column prop="created_at" :label="t('api_versions_page.cols.created_at')" width="120">
            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('api_versions_page.cols.actions')" width="280" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click="viewVersion(row)">{{ t('actions.view_details') }}</el-button>
              <el-dropdown v-if="row.status !== 'retired'" trigger="click" @command="(cmd) => handleLifecycle(cmd, row)">
                <el-button size="small">
                  {{ t('api_versions_page.lifecycle.menu') }} <el-icon><ArrowDown /></el-icon>
                </el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item v-if="row.status === 'active'" command="deprecate" :disabled="row.is_default">
                      {{ t('api_versions_page.lifecycle.mark_deprecated') }}
                    </el-dropdown-item>
                    <el-dropdown-item v-if="row.status === 'deprecated'" command="sunset">
                      {{ t('api_versions_page.lifecycle.sunset') }}
                    </el-dropdown-item>
                    <el-dropdown-item v-if="row.status === 'sunset' || row.status === 'deprecated'" command="retire">
                      {{ t('api_versions_page.lifecycle.retire') }}
                    </el-dropdown-item>
                    <el-dropdown-item divided command="delete">
                      <span class="text-danger">{{ t('actions.delete') }}</span>
                    </el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
              <el-button v-if="!row.is_default && row.status === 'active'" size="small" type="primary" plain @click="setDefault(row)">
                {{ t('api_versions_page.lifecycle.set_default') }}
              </el-button>
            </template>
          </el-table-column>
        </el-table>

        <el-empty v-if="!loading && versions.length === 0" :description="t('api_versions_page.empty.versions')" />
      </el-tab-pane>

      <el-tab-pane :label="t('api_versions_page.tabs.detail')" name="detail" :disabled="!selectedVersion">
        <template #label>
          <span v-if="selectedVersion">{{ t('api_versions_page.tabs.detail_with_version', { version: selectedVersion.version }) }}</span>
          <span v-else>{{ t('api_versions_page.tabs.detail') }}</span>
        </template>

        <div v-if="selectedVersion" class="version-detail">
          <el-descriptions :column="2" border>
            <el-descriptions-item :label="t('api_versions_page.cols.version')">{{ selectedVersion.version }}</el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.cols.base_path')">
              <code>{{ selectedVersion.base_path }}</code>
            </el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.cols.name')">{{ selectedVersion.name || '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.cols.status')">
              <el-tag :type="statusType(selectedVersion.status)" size="small">
                {{ statusLabel(selectedVersion.status) }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.detail.default_version')">
              <el-tag v-if="selectedVersion.is_default" type="success" size="small">{{ t('api_versions_page.yes') }}</el-tag>
              <span v-else>{{ t('api_versions_page.no') }}</span>
            </el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.cols.deprecated_at')">
              {{ selectedVersion.deprecated_at ? formatDate(selectedVersion.deprecated_at) : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.detail.planned_sunset_at')">
              {{ selectedVersion.sunset_at ? formatDate(selectedVersion.sunset_at) : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :label="t('api_versions_page.detail.retired_at')">
              {{ selectedVersion.retired_at ? formatDate(selectedVersion.retired_at) : '-' }}
            </el-descriptions-item>
            <el-descriptions-item :span="2" :label="t('api_versions_page.detail.deprecation_notice')">
              {{ selectedVersion.deprecation_notice || '-' }}
            </el-descriptions-item>
            <el-descriptions-item :span="2" :label="t('api_versions_page.detail.migration_guide')">
              <template #default>
                <div v-if="selectedVersion.migration_guide" class="migration-guide-content">
                  <pre>{{ selectedVersion.migration_guide }}</pre>
                </div>
                <span v-else>-</span>
              </template>
            </el-descriptions-item>
            <el-descriptions-item :span="2" :label="t('api_versions_page.detail.changelog')">
              <template #default>
                <div v-if="selectedVersion.changelog" class="changelog-content">
                  <pre>{{ selectedVersion.changelog }}</pre>
                </div>
                <span v-else>-</span>
              </template>
            </el-descriptions-item>
          </el-descriptions>

          <el-divider />

          <h4>{{ t('api_versions_page.titles.registered_routes') }}</h4>
          <el-table :data="versionRoutes" stripe style="width: 100%">
            <el-table-column prop="method" :label="t('api_versions_page.cols.http_method')" width="80">
              <template #default="{ row }">
                <el-tag :type="methodType(row.method)" size="small">{{ row.method }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="path" :label="t('api_versions_page.cols.path')" min-width="250">
              <template #default="{ row }">
                <code>{{ selectedVersion.base_path }}{{ row.path }}</code>
              </template>
            </el-table-column>
            <el-table-column prop="route_name" :label="t('api_versions_page.cols.route_name')" width="150" />
            <el-table-column prop="controller" :label="t('api_versions_page.cols.controller')" width="200" />
            <el-table-column prop="action" :label="t('api_versions_page.cols.controller_action')" width="120" />
            <el-table-column prop="is_deprecated" :label="t('api_versions_page.cols.is_deprecated')" width="80" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.is_deprecated" type="warning" size="small">{{ t('api_versions_page.yes') }}</el-tag>
                <span v-else>{{ t('api_versions_page.no') }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('api_versions_page.cols.actions')" width="80" fixed="right">
              <template #default="{ row }">
                <el-popconfirm :title="t('api_versions_page.confirm.delete_route')" @confirm="deleteRoute(row)">
                  <template #reference>
                    <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-tab-pane>

      <el-tab-pane :label="t('api_versions_page.tabs.stats')" name="stats">
        <div class="section-header">
          <h3>{{ t('api_versions_page.titles.stats') }}</h3>
          <div class="filter-bar">
            <el-select v-model="statsVersion" :placeholder="t('api_versions_page.filters.select_version')" clearable style="width: 150px">
              <el-option v-for="v in versions" :key="v.id" :label="v.version" :value="v.version" />
            </el-select>
            <el-date-picker
              v-model="statsDateRange"
              type="daterange"
              :range-separator="t('api_versions_page.filters.date_range_sep')"
              :start-placeholder="t('api_versions_page.filters.start_date')"
              :end-placeholder="t('api_versions_page.filters.end_date')"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
            />
            <el-button type="primary" @click="loadStats">{{ t('api_versions_page.filters.query') }}</el-button>
          </div>
        </div>

        <el-table v-if="statsData.length > 0" :data="statsData" stripe style="width: 100%">
          <el-table-column prop="call_date" :label="t('api_versions_page.cols.date')" width="120" />
          <el-table-column prop="method" :label="t('api_versions_page.cols.http_method')" width="80">
            <template #default="{ row }">
              <el-tag :type="methodType(row.method)" size="small">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="path" :label="t('api_versions_page.cols.path')" min-width="250" />
          <el-table-column prop="total_calls" :label="t('api_versions_page.cols.call_count')" width="120" align="right">
            <template #default="{ row }">
              <strong>{{ formatNumber(row.total_calls) }}</strong>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="statsLoaded" :description="t('api_versions_page.empty.stats')" />
      </el-tab-pane>

      <el-tab-pane :label="t('api_versions_page.tabs.impact')" name="impact">
        <div class="section-header">
          <h3>{{ t('api_versions_page.titles.impact') }}</h3>
          <div class="filter-bar">
            <el-select v-model="impactVersion" :placeholder="t('api_versions_page.filters.select_version')" style="width: 200px">
              <el-option v-for="v in versions" :key="v.id" :label="v.version" :value="v.version" />
            </el-select>
            <el-button type="primary" @click="loadImpactAnalysis">{{ t('api_versions_page.filters.analyze') }}</el-button>
          </div>
        </div>

        <template v-if="impactData">
          <el-alert
            :title="t('api_versions_page.impact_alert', { version: impactVersion, count: impactData.affected_tenants_count })"
            :type="impactData.affected_tenants_count > 0 ? 'warning' : 'success'"
            show-icon
            style="margin-bottom: 16px"
          />
          <el-table v-if="impactData.tenants.length > 0" :data="impactData.tenants" stripe style="width: 100%">
            <el-table-column prop="tenant_name" :label="t('api_versions_page.cols.tenant_name')" min-width="200" />
            <el-table-column prop="total_calls" :label="t('api_versions_page.cols.total_calls')" width="120" align="right">
              <template #default="{ row }">
                <strong>{{ formatNumber(row.total_calls) }}</strong>
              </template>
            </el-table-column>
            <el-table-column prop="last_call_date" :label="t('api_versions_page.cols.last_call_date')" width="140">
              <template #default="{ row }">{{ row.last_call_date }}</template>
            </el-table-column>
          </el-table>
        </template>
      </el-tab-pane>

      <el-tab-pane :label="t('api_versions_page.tabs.trend')" name="trend">
        <div class="section-header">
          <h3>{{ t('api_versions_page.titles.trend') }}</h3>
          <div class="filter-bar">
            <el-date-picker
              v-model="trendDateRange"
              type="daterange"
              :range-separator="t('api_versions_page.filters.date_range_sep')"
              :start-placeholder="t('api_versions_page.filters.start_date')"
              :end-placeholder="t('api_versions_page.filters.end_date')"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
            />
            <el-button type="primary" @click="loadTrend">{{ t('api_versions_page.filters.query') }}</el-button>
          </div>
        </div>

        <el-table v-if="trendData.length > 0" :data="trendData" stripe style="width: 100%">
          <el-table-column prop="date" :label="t('api_versions_page.cols.date')" width="120" />
          <el-table-column prop="version" :label="t('api_versions_page.cols.version')" width="100" />
          <el-table-column prop="calls" :label="t('api_versions_page.cols.call_count')" width="120" align="right">
            <template #default="{ row }">
              <strong>{{ formatNumber(row.calls) }}</strong>
            </template>
          </el-table-column>
          <el-table-column prop="status" :label="t('api_versions_page.cols.status')" width="100">
            <template #default="{ row }">
              <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-else-if="trendLoaded && trendData.length === 0" :description="t('api_versions_page.empty.trend')" />
      </el-tab-pane>
    </el-tabs>

    <!-- 新建版本对话框 -->
    <el-dialog v-model="showCreateDialog" :title="t('api_versions_page.dialogs.create_title')" width="600px">
      <el-form ref="createFormRef" :model="createForm" :rules="createRules" label-width="100px">
        <el-form-item :label="t('api_versions_page.cols.version')" prop="version">
          <el-input v-model="createForm.version" :placeholder="t('api_versions_page.dialogs.version_ph')" />
        </el-form-item>
        <el-form-item :label="t('api_versions_page.cols.name')" prop="name">
          <el-input v-model="createForm.name" :placeholder="t('api_versions_page.dialogs.name_ph')" />
        </el-form-item>
        <el-form-item :label="t('api_versions_page.cols.status')">
          <el-select v-model="createForm.status" default-first-option>
            <el-option v-for="opt in createStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('api_versions_page.dialogs.set_default')">
          <el-switch v-model="createForm.is_default" />
        </el-form-item>
        <el-form-item :label="t('api_versions_page.detail.changelog')" prop="changelog">
          <el-input v-model="createForm.changelog" type="textarea" :rows="3" />
        </el-form-item>
        <el-form-item :label="t('api_versions_page.detail.migration_guide')" prop="migration_guide">
          <el-input v-model="createForm.migration_guide" type="textarea" :rows="3" :placeholder="t('api_versions_page.dialogs.migration_guide_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="creating" @click="createVersion">{{ t('actions.confirm') }}</el-button>
      </template>
    </el-dialog>

    <!-- 标记废弃对话框 -->
    <el-dialog v-model="showDeprecateDialog" :title="t('api_versions_page.dialogs.deprecate_title')" width="500px">
      <el-alert
        :title="t('api_versions_page.dialogs.deprecate_alert')"
        type="warning"
        show-icon
        :closable="false"
        style="margin-bottom: 16px"
      />
      <el-form ref="deprecateFormRef" :model="deprecateForm" label-width="120px">
        <el-form-item :label="t('api_versions_page.detail.migration_guide')">
          <el-input v-model="deprecateForm.migration_guide" type="textarea" :rows="3" :placeholder="t('api_versions_page.dialogs.migration_guide_ph')" />
        </el-form-item>
        <el-form-item :label="t('api_versions_page.detail.deprecation_notice')">
          <el-input v-model="deprecateForm.deprecation_notice" type="textarea" :rows="3" :placeholder="t('api_versions_page.dialogs.deprecation_notice_ph')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showDeprecateDialog = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="warning" :loading="deprecating" @click="confirmDeprecate">{{ t('api_versions_page.dialogs.confirm_deprecate') }}</el-button>
      </template>
    </el-dialog>

      </el-tab-pane>

      <el-tab-pane :label="t('api_versions_page.tabs.change_impact')" name="impactMain">

    <div v-if="ai_tabVisited" class="api-impact-page">
        <div class="page-header">
            <div>
                <h2>{{ t(`${ai_P}.title`) }}</h2>
                <p class="text-muted">{{ t(`${ai_P}.subtitle`) }}</p>
            </div>
            <div class="header-actions">
                <el-radio-group v-model="ai_days" size="small" @change="ai_loadAll">
                    <el-radio-button :value="7">{{ t(`${ai_P}.days_n`, { n: 7 }) }}</el-radio-button>
                    <el-radio-button :value="30">{{ t(`${ai_P}.days_n`, { n: 30 }) }}</el-radio-button>
                    <el-radio-button :value="90">{{ t(`${ai_P}.days_n`, { n: 90 }) }}</el-radio-button>
                </el-radio-group>
                <el-button @click="ai_loadAll" :loading="ai_loading" :icon="Refresh">{{ t(`${ai_P}.refresh`) }}</el-button>
            </div>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.metrics.deprecated`) }}</div><div class="metric-value warning">{{ ai_dash.deprecated_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.metrics.retired`) }}</div><div class="metric-value text-muted">{{ ai_dash.retired_versions }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.metrics.calls_today`) }}</div><div class="metric-value">{{ ai_dash.total_calls_today }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.metrics.pending`) }}</div><div class="metric-value danger">{{ ai_dash.pending_notifications }}</div></el-card></el-col>
        </el-row>

        <el-row :gutter="16" class="mb-4" v-if="ai_dash.impact_summary?.length">
            <el-col v-for="v in ai_dash.impact_summary" :key="v.version_id" :xs="24" :sm="8">
                <el-card :shadow="'hover'" :class="['impact-card', v.days_until_sunset !== null && v.days_until_sunset < 30 ? 'border-danger' : 'border-warning']">
                    <div class="impact-header">
                        <span class="impact-version">{{ v.version }}</span>
                        <el-tag :type="v.status === 'deprecated' ? 'warning' : 'danger'" size="small">{{ v.status === 'deprecated' ? t(`${ai_P}.status.deprecated`) : t(`${ai_P}.status.retiring`) }}</el-tag>
                    </div>
                    <div class="impact-stats">
                        <div><span class="stat-l">{{ t(`${ai_P}.impact.calls_30d`) }}</span><span class="stat-v">{{ v.call_count_30d?.toLocaleString() }}</span></div>
                        <div><span class="stat-l">{{ t(`${ai_P}.impact.tenants`) }}</span><span class="stat-v">{{ v.affected_tenants }}</span></div>
                        <div v-if="v.deprecated_at"><span class="stat-l">{{ t(`${ai_P}.impact.deprecated_at`) }}</span><span class="stat-v">{{ v.deprecated_at }}</span></div>
                        <div v-if="v.days_until_sunset !== null"><span class="stat-l">{{ t(`${ai_P}.impact.until_sunset`) }}</span><span :class="v.days_until_sunset < 30 ? 'danger' : 'warning'">{{ t(`${ai_P}.days_n`, { n: v.days_until_sunset }) }}</span></div>
                    </div>
                    <el-button size="small" type="primary" style="margin-top:8px;width:100%" @click="ai_analyzeVersion(v.version_id)">{{ t(`${ai_P}.analyze`) }}</el-button>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="hover" class="mb-4">
            <template #header>
                <div class="card-header"><span><el-icon><DataBoard /></el-icon> {{ t(`${ai_P}.report_title`) }}</span></div>
            </template>
            <el-table :data="ai_report.versions" stripe v-loading="ai_reportLoading" size="small">
                <el-table-column prop="version" :label="t(`${ai_P}.cols.version`)" width="80" />
                <el-table-column :label="t(`${ai_P}.cols.status`)" width="90"><template #default="{row}"><el-tag :type="row.status === 'active' ? 'success' : (row.status === 'deprecated' ? 'warning' : 'danger')" size="small">{{ ai_versionStatus(row.status) }}</el-tag></template></el-table-column>
                <el-table-column :label="t(`${ai_P}.cols.default`)" width="60"><template #default="{row}"><el-icon v-if="row.is_default" color="#67c23a"><CircleCheck /></el-icon></template></el-table-column>
                <el-table-column :label="t(`${ai_P}.cols.deprecated_at`)" width="110"><template #default="{row}">{{ row.deprecated_at || '—' }}</template></el-table-column>
                <el-table-column :label="t(`${ai_P}.cols.sunset_at`)" width="110"><template #default="{row}">{{ row.sunset_at || '—' }}</template></el-table-column>
                <el-table-column :label="t(`${ai_P}.cols.calls_30d`)" width="120" prop="call_count" />
                <el-table-column :label="t(`${ai_P}.cols.tenants`)" width="80" prop="tenant_count" />
                <el-table-column :label="t(`${ai_P}.cols.impact`)" width="100"><template #default="{row}">
                    <el-tag :type="row.impact_level === 'critical' ? 'danger' : (row.impact_level === 'high' ? 'warning' : (row.impact_level === 'medium' ? 'info' : 'success'))" size="small">{{ ai_impactLabel(row.impact_level) }}</el-tag>
                </template></el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="ai_showAnalysis" :title="t(`${ai_P}.analysis_title`, { version: ai_analysis?.version?.version || '' })" width="850px" top="5vh">
            <template v-if="ai_analysis">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.analysis.total_calls`) }}</div><div class="metric-value">{{ ai_analysis.total_calls?.toLocaleString() }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.analysis.tenants`) }}</div><div class="metric-value warning">{{ ai_analysis.total_tenants }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.cols.status`) }}</div><div class="metric-value"><el-tag :type="ai_analysis.version?.status === 'deprecated' ? 'warning' : 'danger'" size="small">{{ ai_analysis.version?.status }}</el-tag></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t(`${ai_P}.analysis.period`) }}</div><div class="metric-value">{{ t(`${ai_P}.days_n`, { n: ai_analysis.analysis_period_days }) }}</div></el-card></el-col>
                </el-row>
                <el-tabs type="border-card">
                    <el-tab-pane :label="t(`${ai_P}.tabs.tenants`)">
                        <el-table :data="ai_analysis.affected_tenants" stripe size="small" max-height="300">
                            <el-table-column prop="name" :label="t(`${ai_P}.cols.customer`)" min-width="160" />
                            <el-table-column prop="email" :label="t(`${ai_P}.cols.email`)" min-width="180" />
                            <el-table-column :label="t(`${ai_P}.cols.calls_nd`, { n: ai_days })" width="120" prop="total_calls" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t(`${ai_P}.tabs.paths`)">
                        <el-table :data="ai_analysis.by_path" stripe size="small" max-height="300">
                            <el-table-column prop="path" :label="t(`${ai_P}.cols.path`)" min-width="300" />
                            <el-table-column :label="t(`${ai_P}.cols.calls`)" width="120" prop="total_calls" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t(`${ai_P}.tabs.trend`)">
                        <el-table :data="ai_analysis.monthly_trend" stripe size="small" max-height="300">
                            <el-table-column prop="month" :label="t(`${ai_P}.cols.month`)" width="100" />
                            <el-table-column :label="t(`${ai_P}.cols.calls`)" width="120" prop="total_calls" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t(`${ai_P}.tabs.guide`)">
                        <pre v-if="ai_analysis.version?.migration_guide" class="guide-block">{{ ai_analysis.version.migration_guide }}</pre>
                        <el-empty v-else :description="t(`${ai_P}.no_guide`)" />
                    </el-tab-pane>
                </el-tabs>
                <div style="margin-top:12px;display:flex;gap:8px">
                    <el-button type="primary" @click="ai_notifyCustomers(ai_analysis.version?.id)" :loading="ai_notifyLoading">{{ t(`${ai_P}.notify`) }}</el-button>
                    <el-button @click="ai_exportCsv(ai_analysis.version?.id)">{{ t(`${ai_P}.export`) }}</el-button>
                </div>
            </template>
        </el-dialog>
    </div>

      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, ArrowDown, Refresh, DataBoard, CircleCheck } from '@element-plus/icons-vue'
import api from '../../api/apiVersion'
import apiImpactApi from '@/api/apiImpact'

const { t, locale } = useI18n()

// ─── 外层主 Tab ───
const apiMainTab = ref('versionsMain')

// ─── 版本管理 Tab 内部 ───
const activeTab = ref('versions')
const loading = ref(false)
const versions = ref([])
const selectedVersion = ref(null)
const versionRoutes = ref([])

// 统计
const statsVersion = ref('')
const statsDateRange = ref([])
const statsData = ref([])
const statsLoaded = ref(false)

// 影响分析
const impactVersion = ref('')
const impactData = ref(null)

// 趋势
const trendDateRange = ref([])
const trendData = ref([])
const trendLoaded = ref(false)

// 创建
const showCreateDialog = ref(false)
const creating = ref(false)
const createFormRef = ref(null)
const createForm = reactive({
    version: '',
    name: '',
    status: 'active',
    is_default: false,
    changelog: '',
    migration_guide: '',
})

const createRules = computed(() => ({
    version: [{ required: true, message: t('api_versions_page.rules.version_required'), trigger: 'blur' }],
}))

const createStatusOptions = computed(() => [
    { label: t('api_versions_page.status.active_option'), value: 'active' },
    { label: t('api_versions_page.status.deprecated_option'), value: 'deprecated' },
])

// 废弃对话框
const showDeprecateDialog = ref(false)
const deprecatingVersion = ref(null)
const deprecateFormRef = ref(null)
const deprecating = ref(false)
const deprecateForm = reactive({
    migration_guide: '',
    deprecation_notice: '',
})

const statusLabels = computed(() => ({
    active: t('api_versions_page.status.active'),
    deprecated: t('api_versions_page.status.deprecated'),
    sunset: t('api_versions_page.status.sunset'),
    retired: t('api_versions_page.status.retired'),
}))

onMounted(() => {
    loadVersions()
})

async function loadVersions() {
    loading.value = true
    try {
        const { data } = await api.index()
        versions.value = data.data || []
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.load_versions_failed'))
    } finally {
        loading.value = false
    }
}

function statusType(status) {
    const map = { active: 'success', deprecated: 'warning', sunset: 'danger', retired: 'info' }
    return map[status] || 'info'
}

function statusLabel(status) {
    return statusLabels.value[status] || status
}

function methodType(method) {
    const map = { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: 'warning', DELETE: 'danger' }
    return map[method] || 'info'
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const d = new Date(dateStr)
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return d.toLocaleDateString(loc) + ' ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' })
}

function formatNumber(n) {
    return Number(n || 0).toLocaleString()
}

async function viewVersion(version) {
    selectedVersion.value = version
    activeTab.value = 'detail'
    try {
        const { data } = await api.show(version.version)
        selectedVersion.value = data.data?.version || version
        versionRoutes.value = data.data?.routes || []
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.load_detail_failed'))
    }
}

async function createVersion() {
    if (!createFormRef.value) return
    const valid = await createFormRef.value.validate().catch(() => false)
    if (!valid) return

    creating.value = true
    try {
        await api.store(createForm)
        ElMessage.success(t('api_versions_page.messages.create_success'))
        showCreateDialog.value = false
        createForm.version = ''
        createForm.name = ''
        createForm.status = 'active'
        createForm.is_default = false
        createForm.changelog = ''
        createForm.migration_guide = ''
        await loadVersions()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('api_versions_page.messages.create_failed'))
    } finally {
        creating.value = false
    }
}

function handleLifecycle(cmd, row) {
    if (cmd === 'delete') {
        ElMessageBox.confirm(
            t('api_versions_page.confirm.delete_version', { version: row.version }),
            t('api_versions_page.confirm.delete_version_title'),
            {
                confirmButtonText: t('actions.delete'),
                cancelButtonText: t('actions.cancel'),
                type: 'error',
            },
        ).then(async () => {
            try {
                await api.destroy(row.version)
                ElMessage.success(t('api_versions_page.messages.delete_success'))
                await loadVersions()
                if (selectedVersion.value?.id === row.id) {
                    selectedVersion.value = null
                    versionRoutes.value = []
                }
            } catch (e) {
                ElMessage.error(t('api_versions_page.messages.delete_failed'))
            }
        }).catch(() => {})
        return
    }

    if (cmd === 'deprecate') {
        deprecatingVersion.value = row
        deprecateForm.migration_guide = row.migration_guide || ''
        deprecateForm.deprecation_notice = row.deprecation_notice || ''
        showDeprecateDialog.value = true
        return
    }

    const actionMap = {
        sunset: { method: 'sunset', msgKey: 'api_versions_page.messages.sunset_success' },
        retire: { method: 'retire', msgKey: 'api_versions_page.messages.retire_success' },
    }

    const action = actionMap[cmd]
    if (!action) return

    const confirmKey = cmd === 'sunset' ? 'api_versions_page.confirm.sunset' : 'api_versions_page.confirm.retire'
    ElMessageBox.confirm(
        t(confirmKey, { version: row.version }),
        t('api_versions_page.confirm.title'),
        {
            confirmButtonText: t('actions.confirm'),
            cancelButtonText: t('actions.cancel'),
            type: 'warning',
        },
    ).then(async () => {
        try {
            await api[action.method](row.version)
            ElMessage.success(t(action.msgKey))
            await loadVersions()
            if (selectedVersion.value?.id === row.id) {
                selectedVersion.value = null
                versionRoutes.value = []
            }
        } catch (e) {
            ElMessage.error(e.response?.data?.message || t('messages.failed'))
        }
    }).catch(() => {})
}

async function confirmDeprecate() {
    deprecating.value = true
    try {
        const { data } = await api.deprecate(deprecatingVersion.value.version, deprecateForm)
        ElMessage.success(t('api_versions_page.messages.deprecate_success', { notice: data.data?.notice || '' }))
        showDeprecateDialog.value = false
        deprecatingVersion.value = null
        await loadVersions()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        deprecating.value = false
    }
}

async function setDefault(row) {
    try {
        await api.update(row.version, { is_default: true })
        ElMessage.success(t('api_versions_page.messages.set_default_success'))
        await loadVersions()
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.set_default_failed'))
    }
}

async function deleteRoute(route) {
    if (!selectedVersion.value) return
    try {
        await api.deleteRoute(selectedVersion.value.version, route.id)
        ElMessage.success(t('api_versions_page.messages.route_delete_success'))
        await viewVersion(selectedVersion.value)
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.delete_failed'))
    }
}

async function loadStats() {
    if (!statsVersion.value) {
        ElMessage.warning(t('api_versions_page.messages.select_version_required'))
        return
    }
    statsLoaded.value = false
    try {
        const params = {}
        if (statsDateRange.value) {
            params.start_date = statsDateRange.value[0]
            params.end_date = statsDateRange.value[1]
        }
        const { data } = await api.callStats(statsVersion.value, params)
        statsData.value = data.data?.stats || []
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.load_stats_failed'))
    } finally {
        statsLoaded.value = true
    }
}

async function loadImpactAnalysis() {
    if (!impactVersion.value) {
        ElMessage.warning(t('api_versions_page.messages.select_version_required'))
        return
    }
    try {
        const { data } = await api.impactAnalysis(impactVersion.value)
        impactData.value = data.data
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.load_impact_failed'))
    }
}

async function loadTrend() {
    trendLoaded.value = false
    try {
        const params = {}
        if (trendDateRange.value) {
            params.start_date = trendDateRange.value[0]
            params.end_date = trendDateRange.value[1]
        }
        const { data } = await api.usageTrend(params)
        trendData.value = data.data || []
    } catch (e) {
        ElMessage.error(t('api_versions_page.messages.load_trend_failed'))
    } finally {
        trendLoaded.value = true
    }
}

// ─── 变更影响 Tab (api-impact, 懒加载) ───
const ai_P = 'api_impact_page'
const ai_tabVisited = ref(false)
const ai_loading = ref(false)
const ai_reportLoading = ref(false)
const ai_notifyLoading = ref(false)
const ai_days = ref(30)
const ai_dash = reactive({ deprecated_versions: 0, retired_versions: 0, total_calls_today: 0, impact_summary: [], pending_notifications: 0 })
const ai_report = reactive({ versions: [], generated_at: '', period_days: 30, total_deprecated_calls: 0 })
const ai_showAnalysis = ref(false)
const ai_analysis = ref(null)

function ai_versionStatus(s) {
    const key = `${ai_P}.vstatus.${s}`
    const translated = t(key)
    return translated === key ? s : translated
}
function ai_impactLabel(level) {
    const key = `${ai_P}.impact_level.${level}`
    const translated = t(key)
    return translated === key ? level : translated
}

// 懒加载：切换到「变更影响」Tab 时首次加载数据
watch(apiMainTab, (val) => {
    if (val === 'impactMain' && !ai_tabVisited.value) {
        ai_tabVisited.value = true
        ai_loadAll()
    }
})

async function ai_loadAll() {
    ai_loading.value = true
    try { await Promise.all([ai_loadDashboard(), ai_loadReport()]); } finally { ai_loading.value = false; }
}
async function ai_loadDashboard() {
    try { const r = await apiImpactApi.dashboard(); Object.assign(ai_dash, r.data?.data || {}); } catch {}
}
async function ai_loadReport() {
    ai_reportLoading.value = true
    try { const r = await apiImpactApi.overallReport({ days: ai_days.value }); Object.assign(ai_report, r.data?.data || {}); } finally { ai_reportLoading.value = false; }
}
async function ai_analyzeVersion(versionId) {
    try {
        const r = await apiImpactApi.analyzeVersion(versionId, { days: ai_days.value });
        ai_analysis.value = r.data?.data; ai_showAnalysis.value = true;
    } catch { ElMessage.error(t(`${ai_P}.messages.load_failed`)); }
}
async function ai_notifyCustomers(versionId) {
    if (!versionId) return;
    try {
        await ElMessageBox.confirm(t(`${ai_P}.confirm_notify`), t('actions.confirm'));
        ai_notifyLoading.value = true;
        const r = await apiImpactApi.sendNotifications(versionId);
        ElMessage.success(t(`${ai_P}.messages.notified`, { n: r.data?.data?.sent }));
        ai_loadDashboard();
    } catch (e) { if (e !== 'cancel') ElMessage.error(t(`${ai_P}.messages.notify_failed`)); } finally { ai_notifyLoading.value = false; }
}
async function ai_exportCsv(versionId) {
    if (!versionId) return;
    try {
        const r = await apiImpactApi.exportReport(versionId, { days: ai_days.value });
        const data = r.data?.data || [];
        if (!data.length) { ElMessage.warning(t('messages.no_data')); return; }
        const csv = data.map(row => row.map(c => typeof c === 'string' && c.includes(',') ? `"${c}"` : c).join(',')).join('\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'api-impact-report.csv'; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t(`${ai_P}.messages.exported`));
    } catch { ElMessage.error(t(`${ai_P}.messages.export_failed`)); }
}
</script>

<style scoped>
.api-version-management {
    padding: 20px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.section-header h3 {
    margin: 0;
}

.filter-bar {
    display: flex;
    gap: 12px;
    align-items: center;
}

.version-detail {
    max-width: 1000px;
}

.migration-guide-content pre,
.changelog-content pre {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 4px;
    white-space: pre-wrap;
    font-size: 13px;
    max-height: 200px;
    overflow-y: auto;
}

.text-danger {
    color: #f56c6c;
}

code {
    background: #f5f7fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
}

/* ─── api-impact 合并样式 ─── */
.api-impact-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.impact-card { margin-bottom: 8px; }
.impact-card.border-danger { border-left: 3px solid #f56c6c; }
.impact-card.border-warning { border-left: 3px solid #e6a23c; }
.impact-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.impact-version { font-size: 18px; font-weight: 700; font-family: monospace; }
.impact-stats { font-size: 13px; }
.impact-stats > div { display: flex; justify-content: space-between; padding: 2px 0; }
.stat-l { color: #909399; }
.stat-v { font-weight: 600; }
.guide-block { background: #f5f7fa; padding: 12px; border-radius: 4px; white-space: pre-wrap; font-size: 13px; max-height: 300px; overflow-y: auto; }
</style>
