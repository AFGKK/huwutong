<template>
  <div class="api-docs-page">
    <!-- 多语言切换条 -->
    <el-card shadow="never" class="mb-3 i18n-bar">
      <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px">
        <span style="font-size:13px;color:#909399">{{ t('api_docs_page.doc_language') }}:</span>
        <el-radio-group v-model="currentLocale" size="small" @change="onLocaleChange">
          <el-radio-button value="en">{{ t('language.en') }}</el-radio-button>
          <el-radio-button value="zh_CN">{{ t('language.zh_CN') }}</el-radio-button>
          <el-radio-button value="ja">{{ t('language.ja') }}</el-radio-button>
        </el-radio-group>
        <el-button size="small" type="warning" :loading="exportLocalizedLoading" @click="exportLocalizedOpenApi">
          <el-icon><Download /></el-icon> {{ t('api_docs_page.export_localized_openapi', { locale: localeLabel }) }}
        </el-button>
      </div>
    </el-card>

    <!-- 统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_endpoints }}</div><div class="stat-label">{{ t('api_docs_page.stats.total_endpoints') }}</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-success">{{ stats.active_endpoints }}</div><div class="stat-label">{{ t('api_docs_page.stats.active') }}</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_schemas }}</div><div class="stat-label">{{ t('api_docs_page.stats.schemas') }}</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.active_sdks }}</div><div class="stat-label">{{ t('api_docs_page.stats.sdks') }}</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_changelogs }}</div><div class="stat-label">{{ t('api_docs_page.stats.changelogs') }}</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_tests }}</div><div class="stat-label">{{ t('api_docs_page.stats.test_requests') }}</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ──────── 标签1: API 文档 ──────── -->
      <el-tab-pane :label="t('api_docs_page.tabs.docs')" name="docs">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="docFilter.group" :placeholder="t('api_docs_page.filters.group')" clearable @change="fetchEndpoints" style="width:130px">
                <el-option v-for="(label, key) in groups" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="docFilter.method" :placeholder="t('api_docs_page.filters.method')" clearable @change="fetchEndpoints" style="width:100px">
                <el-option label="GET" value="GET" />
                <el-option label="POST" value="POST" />
                <el-option label="PUT" value="PUT" />
                <el-option label="PATCH" value="PATCH" />
                <el-option label="DELETE" value="DELETE" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="docFilter.status" :placeholder="t('api_docs_page.filters.status')" clearable @change="fetchEndpoints" style="width:110px">
                <el-option v-for="opt in endpointStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-input v-model="docFilter.search" :placeholder="t('api_docs_page.filters.search_ph')" clearable @input="onSearchDebounce" style="width:200px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchEndpoints"><el-icon><Refresh /></el-icon> {{ t('api_docs_page.refresh') }}</el-button>
              <el-button type="success" :loading="scanning" @click="scanRoutes">
                <el-icon><Search /></el-icon> {{ t('api_docs_page.scan_routes') }}
              </el-button>
              <el-button type="warning" @click="showExportDlg = true">
                <el-icon><Download /></el-icon> {{ t('api_docs_page.export_openapi') }}
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <!-- 批量操作工具栏 -->
        <div v-if="selectedEndpointIds.length > 0" class="batch-toolbar mb-2">
          <span class="mr-2">{{ t('api_docs_page.batch.selected', { n: selectedEndpointIds.length }) }}</span>
          <el-select v-model="batchStatus" :placeholder="t('api_docs_page.batch.status_ph')" size="small" style="width:130px" class="mr-2">
            <el-option v-for="opt in endpointStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
          <el-button :loading="batchUpdating" size="small" type="primary" :disabled="!batchStatus" @click="batchUpdateStatus">{{ t('api_docs_page.batch.apply') }}</el-button>
          <el-button size="small" @click="selectedEndpointIds = []">{{ t('api_docs_page.batch.clear_selection') }}</el-button>
        </div>

        <el-table :data="endpoints" v-loading="loading" stripe @row-click="showEndpointDetail" style="cursor:pointer"
          @selection-change="onSelectionChange">
          <el-table-column type="selection" width="40" />
          <el-table-column :label="t('api_docs_page.cols.method')" width="90">
            <template #default="{ row }">
              <el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:60px;text-align:center">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="path" :label="t('api_docs_page.cols.path')" min-width="300" show-overflow-tooltip />
          <el-table-column prop="summary" :label="t('api_docs_page.cols.summary')" min-width="200" show-overflow-tooltip />
          <el-table-column prop="group" :label="t('api_docs_page.cols.group')" width="100">
            <template #default="{ row }">{{ groups[row.group] || row.group }}</template>
          </el-table-column>
          <el-table-column :label="t('api_docs_page.cols.status')" width="90">
            <template #default="{ row }">
              <el-tag v-if="row.status === 'beta'" type="warning" size="small">Beta</el-tag>
              <el-tag v-else-if="row.status === 'deprecated'" type="danger" size="small">{{ statusTextMap.deprecated }}</el-tag>
              <el-tag v-else-if="row.status === 'experimental'" type="info" size="small">{{ statusTextMap.experimental }}</el-tag>
              <span v-else class="text-success">{{ statusTextMap.active }}</span>
            </template>
          </el-table-column>
          <el-table-column :label="t('api_docs_page.cols.favorite')" width="60">
            <template #default="{ row }">
              <el-tooltip :content="favoriteIds.has(row.id) ? t('api_docs_page.favorite.remove') : t('api_docs_page.favorite.add')">
                <el-icon :class="{ 'favorite-active': favoriteIds.has(row.id) }" class="favorite-btn" @click.stop="toggleFavorite(row.id)">
                  <StarFilled v-if="favoriteIds.has(row.id)" />
                  <Star v-else />
                </el-icon>
              </el-tooltip>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- ──────── 标签2: 测试控制台 ──────── -->
      <el-tab-pane :label="t('api_docs_page.tabs.console')" name="console">
        <el-row :gutter="20">
          <el-col :span="14">
            <el-card shadow="never">
              <template #header>{{ t('api_docs_page.console.request') }}</template>
              <el-form label-width="0" size="small">
                <el-row :gutter="8">
                  <el-col :span="4">
                    <el-select v-model="testForm.method" style="width:100%">
                      <el-option label="GET" value="GET" />
                      <el-option label="POST" value="POST" />
                      <el-option label="PUT" value="PUT" />
                      <el-option label="PATCH" value="PATCH" />
                      <el-option label="DELETE" value="DELETE" />
                    </el-select>
                  </el-col>
                  <el-col :span="20">
                    <el-input v-model="testForm.url" placeholder="https://api.example.com/api/..." />
                  </el-col>
                </el-row>
                <div class="mt-2">
                  <div class="label-text">{{ t('api_docs_page.console.headers_json') }}</div>
                  <el-input v-model="testHeadersText" type="textarea" :rows="3" placeholder='{"Authorization": "Bearer xxx"}' />
                </div>
                <div class="mt-2">
                  <div class="label-text">{{ t('api_docs_page.console.body_json') }}</div>
                  <el-input v-model="testBodyText" type="textarea" :rows="6" placeholder='{"key": "value"}' />
                </div>
                <div class="mt-2">
                  <el-button type="primary" :loading="testLoading" @click="sendTest">
                    <el-icon><CaretRight /></el-icon> {{ t('api_docs_page.console.send') }}
                  </el-button>
                  <el-button @click="clearTest">{{ t('api_docs_page.console.clear') }}</el-button>
                </div>
              </el-form>
            </el-card>
          </el-col>
          <el-col :span="10">
            <el-card shadow="never">
              <template #header>
                <span>{{ t('api_docs_page.console.response') }}</span>
                <el-tag v-if="testResult.status === 'success'" type="success" size="small" class="ml-2">{{ testResult.response_status }}</el-tag>
                <el-tag v-else-if="testResult.status === 'failed'" type="danger" size="small" class="ml-2">{{ t('api_docs_page.console.failed') }}</el-tag>
                <span v-if="testResult.response_time_ms" class="ml-2 text-muted">{{ testResult.response_time_ms }}ms</span>
              </template>
              <pre v-if="testResult.response" class="response-pre">{{ formatJson(testResult.response) }}</pre>
              <div v-else-if="testResult.error_message" class="text-danger">{{ testResult.error_message }}</div>
              <div v-else class="text-muted">{{ t('api_docs_page.console.waiting') }}</div>
            </el-card>
            <el-card shadow="never" class="mt-3">
              <template #header>{{ t('api_docs_page.console.history') }}</template>
              <div v-if="testHistory.length">
                <div v-for="h in testHistory" :key="h.id" class="history-item" @click="restoreTest(h)">
                  <el-tag :type="h.status === 'success' ? 'success' : 'danger'" size="small">{{ h.method }}</el-tag>
                  <span class="text-ellipsis">{{ h.url }}</span>
                  <small class="text-muted">{{ formatTime(h.created_at) }}</small>
                </div>
              </div>
              <div v-else class="text-muted">{{ t('api_docs_page.console.no_history') }}</div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ──────── 标签3: SDK ──────── -->
      <el-tab-pane :label="t('api_docs_page.tabs.sdk')" name="sdk">
        <el-row :gutter="16" class="mb-3">
          <el-col v-for="sdk in sdks" :key="sdk.id" :span="8">
            <el-card shadow="hover" class="sdk-card">
              <div class="sdk-header">
                <span class="sdk-icon">{{ sdk.language === 'javascript' ? 'JS' : sdk.language.toUpperCase() }}</span>
                <div class="sdk-info">
                  <strong>{{ sdk.name }}</strong>
                  <small>v{{ sdk.version }}</small>
                </div>
              </div>
              <p class="text-muted mt-1">{{ sdk.description }}</p>
              <div v-if="sdk.install_command" class="install-cmd">$ {{ sdk.install_command }}</div>
              <div class="mt-2">
                <el-button size="small" @click="previewSdk(sdk.language)"><el-icon><View /></el-icon> {{ t('api_docs_page.sdk.preview') }}</el-button>
                <el-button size="small" type="primary" @click="copySetupCode(sdk)"><el-icon><CopyDocument /></el-icon> {{ t('api_docs_page.sdk.copy_setup') }}</el-button>
              </div>
            </el-card>
          </el-col>
        </el-row>
        <el-card v-if="sdkPreview" shadow="never">
          <template #header>
            <span>{{ t('api_docs_page.sdk.preview_title', { language: sdkPreview.language }) }}</span>
            <el-button size="small" class="ml-2" @click="sdkPreview = null">{{ t('actions.close') }}</el-button>
            <el-button size="small" type="primary" class="ml-2" @click="copySdkCode"><el-icon><CopyDocument /></el-icon> {{ t('api_docs_page.sdk.copy_code') }}</el-button>
          </template>
          <pre class="code-pre">{{ sdkPreview.code }}</pre>
        </el-card>
      </el-tab-pane>

      <!-- ──────── 标签4: 变更日志 ──────── -->
      <el-tab-pane :label="t('api_docs_page.tabs.changelog')" name="changelog">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showChangelogDlg = true"><el-icon><Plus /></el-icon> {{ t('api_docs_page.changelog.add') }}</el-button>
          <el-button type="success" :loading="autoDetecting" @click="autoDetectChanges">
            <el-icon><MagicStick /></el-icon> {{ t('api_docs_page.changelog.auto_detect') }}
          </el-button>
          <el-select v-model="changelogFilter.type" :placeholder="t('api_docs_page.filters.type')" clearable @change="fetchChangelogs" style="width:120px" class="ml-2">
            <el-option v-for="opt in changelogTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
          <el-tag v-if="autoDetectLogs.length" type="info" effect="plain" size="small" class="ml-2">
            {{ t('api_docs_page.changelog.auto_generated_count', { n: autoDetectLogs.length }) }}
          </el-tag>
        </div>
        <el-timeline>
          <el-timeline-item v-for="log in changelogs" :key="log.id" :timestamp="log.release_date" placement="top">
            <el-card shadow="hover">
              <h4>
                <el-tag :type="changelogTag(log.type)" size="small" effect="dark">{{ log.version }}</el-tag>
                <el-tag v-if="log.source === 'auto_detect'" size="small" type="success" effect="plain" class="ml-1">{{ t('api_docs_page.changelog.auto_badge') }}</el-tag>
                <span class="ml-2">{{ log.title }}</span>
              </h4>
              <p v-if="log.description" class="text-muted mt-1" style="white-space:pre-wrap">{{ log.description }}</p>
              <p v-if="log.migration_guide" class="mt-1"><el-tag type="warning" size="small">{{ t('api_docs_page.changelog.migration_guide') }}</el-tag> {{ log.migration_guide }}</p>
              <p v-if="log.affected_endpoints?.length" class="mt-1">
                <el-tag v-for="ep in log.affected_endpoints" :key="ep" size="small" class="mr-1">{{ ep }}</el-tag>
              </p>
            </el-card>
          </el-timeline-item>
        </el-timeline>

        <!-- 自动检测结果对话框 -->
        <el-dialog v-model="showAutoDetectResult" :title="t('api_docs_page.auto_detect.title')" width="600px">
          <el-alert v-if="autoDetectResult.status === 'snapshot_created'" type="info" :description="autoDetectResult.message" show-icon :closable="false" />
          <div v-else>
            <el-alert :type="autoDetectResult.changelogs_created > 0 ? 'success' : 'info'" show-icon :closable="false">
              <template #title>
                <div>{{ autoDetectResult.message }}</div>
              </template>
            </el-alert>
            <div v-if="autoDetectResult.changes" class="mt-3">
              <el-descriptions :column="4" border size="small">
                <el-descriptions-item v-if="autoDetectResult.changes.added" :label="t('api_docs_page.auto_detect.added')" label-class-name="text-success">{{ autoDetectResult.changes.added }}</el-descriptions-item>
                <el-descriptions-item v-if="autoDetectResult.changes.changed" :label="t('api_docs_page.auto_detect.changed')" label-class-name="text-primary">{{ autoDetectResult.changes.changed }}</el-descriptions-item>
                <el-descriptions-item v-if="autoDetectResult.changes.deprecated" :label="t('api_docs_page.auto_detect.deprecated')" label-class-name="text-warning">{{ autoDetectResult.changes.deprecated }}</el-descriptions-item>
                <el-descriptions-item v-if="autoDetectResult.changes.removed" :label="t('api_docs_page.auto_detect.removed')" label-class-name="text-danger">{{ autoDetectResult.changes.removed }}</el-descriptions-item>
              </el-descriptions>
            </div>
            <div v-if="autoDetectResult.added?.length" class="mt-3">
              <h5>{{ t('api_docs_page.auto_detect.added_endpoints') }}</h5>
              <div v-for="item in autoDetectResult.added" :key="item.key" class="change-item">
                <el-tag size="small" type="success" effect="plain">{{ item.method }}</el-tag>
                <code>{{ item.path }}</code>
                <span v-if="item.summary" class="text-muted">— {{ item.summary }}</span>
              </div>
            </div>
            <div v-if="autoDetectResult.removed?.length" class="mt-3">
              <h5>{{ t('api_docs_page.auto_detect.removed_endpoints') }}</h5>
              <div v-for="item in autoDetectResult.removed" :key="item.key" class="change-item">
                <el-tag size="small" type="danger" effect="plain">{{ item.method }}</el-tag>
                <code>{{ item.path }}</code>
                <span v-if="item.summary" class="text-muted">— {{ item.summary }}</span>
              </div>
            </div>
          </div>
        </el-dialog>
      </el-tab-pane>

      <!-- ──────── 标签5: 版本差异 ──────── -->
      <el-tab-pane :label="t('api_docs_page.tabs.diff')" name="diff">
        <VersionDiff />
      </el-tab-pane>

      <!-- ──────── 标签6: 我的收藏 ──────── -->
      <el-tab-pane :label="t('api_docs_page.tabs.favorites')" name="favorites">
        <div class="tab-toolbar">
          <el-button type="primary" @click="fetchFavorites"><el-icon><Refresh /></el-icon> {{ t('api_docs_page.refresh') }}</el-button>
        </div>
        <el-table :data="favorites" v-loading="favoritesLoading" stripe @row-click="showFavoriteEndpoint" style="cursor:pointer">
          <el-table-column :label="t('api_docs_page.cols.method')" width="90">
            <template #default="{ row }">
              <el-tag :type="methodTag(row.endpoint.method)" size="small" effect="dark" style="width:60px;text-align:center">{{ row.endpoint.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="endpoint.path" :label="t('api_docs_page.cols.path')" min-width="300" show-overflow-tooltip />
          <el-table-column prop="endpoint.summary" :label="t('api_docs_page.cols.summary')" min-width="200" show-overflow-tooltip />
          <el-table-column prop="note" :label="t('api_docs_page.cols.note')" min-width="150" show-overflow-tooltip />
          <el-table-column :label="t('api_docs_page.cols.favorited_at')" width="170">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('api_docs_page.cols.actions')" width="80">
            <template #default="{ row }">
              <el-button size="small" type="danger" link @click.stop="removeFavorite(row.endpoint_id)">
                {{ t('api_docs_page.favorite.remove') }}
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!favorites.length && !favoritesLoading" :description="t('api_docs_page.favorites.empty')" />
      </el-tab-pane>
    </el-tabs>

    <!-- ───── 端点详情抽屉 ───── -->
    <el-drawer v-model="showDetail" :title="detailEndpoint?.method + ' ' + detailEndpoint?.path" size="700px">
      <template v-if="detailEndpoint">
        <!-- 端点信息 -->
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('api_docs_page.cols.method')">
            <el-tag :type="methodTag(detailEndpoint.method)" size="small">{{ detailEndpoint.method }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('api_docs_page.cols.path')">{{ detailEndpoint.path }}</el-descriptions-item>
          <el-descriptions-item :label="t('api_docs_page.cols.group')">{{ groups[detailEndpoint.group] || detailEndpoint.group }}</el-descriptions-item>
          <el-descriptions-item :label="t('api_docs_page.cols.status')">
            <el-tag v-if="detailEndpoint.status === 'beta'" type="warning" size="small">Beta</el-tag>
            <el-tag v-else-if="detailEndpoint.status === 'deprecated'" type="danger" size="small">{{ statusTextMap.deprecated }}</el-tag>
            <el-tag v-else-if="detailEndpoint.status === 'experimental'" type="info" size="small">{{ statusTextMap.experimental }}</el-tag>
            <span v-else class="text-success">{{ statusTextMap.active }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <!-- 操作按钮 -->
        <div class="detail-actions mt-2">
          <el-button size="small" :type="favoriteIds.has(detailEndpoint.id) ? 'danger' : 'default'"
            @click="toggleFavorite(detailEndpoint.id)">
            <el-icon><StarFilled v-if="favoriteIds.has(detailEndpoint.id)" /><Star v-else /></el-icon>
            {{ favoriteIds.has(detailEndpoint.id) ? t('api_docs_page.favorite.remove') : t('api_docs_page.favorite.add') }}
          </el-button>
          <el-button size="small" :loading="genSnippetLoading" @click="autoGenerateSnippets(detailEndpoint.id)">
            <el-icon><MagicStick /></el-icon> {{ t('api_docs_page.detail.auto_generate_snippets') }}
          </el-button>
          <el-button size="small" @click="fillTestFromEndpoint">
            <el-icon><CaretRight /></el-icon> {{ t('api_docs_page.detail.test_in_console') }}
          </el-button>
        </div>

        <!-- 端点统计 -->
        <el-collapse class="mt-2">
          <el-collapse-item :title="t('api_docs_page.detail.stats_title')" name="stats">
            <el-descriptions v-if="endpointStats" :column="3" border size="small">
              <el-descriptions-item :label="t('api_docs_page.detail.test_count')">{{ endpointStats.total_tests }}</el-descriptions-item>
              <el-descriptions-item :label="t('api_docs_page.detail.success_rate')">
                <span :class="successRateClass">{{ successRate }}%</span>
              </el-descriptions-item>
              <el-descriptions-item :label="t('api_docs_page.detail.avg_response')">{{ endpointStats.avg_response_time_ms }}ms</el-descriptions-item>
              <el-descriptions-item :label="t('api_docs_page.detail.favorite_count')">{{ endpointStats.favorite_count }}</el-descriptions-item>
              <el-descriptions-item :label="t('api_docs_page.detail.last_tested')">{{ endpointStats.last_tested_at ? formatTime(endpointStats.last_tested_at) : t('api_docs_page.detail.never') }}</el-descriptions-item>
            </el-descriptions>
            <div v-else class="text-muted">{{ t('api_docs_page.detail.no_stats') }}</div>
          </el-collapse-item>
        </el-collapse>

        <!-- 说明 -->
        <div class="mt-3">
          <h4>{{ t('api_docs_page.detail.description') }}</h4>
          <p>{{ detailEndpoint.summary || t('api_docs_page.detail.none') }}</p>
          <p v-if="detailEndpoint.description">{{ detailEndpoint.description }}</p>
        </div>

        <!-- 参数 -->
        <div v-if="detailEndpoint.parameters?.length" class="mt-3">
          <h4>{{ t('api_docs_page.detail.parameters') }}</h4>
          <el-table :data="detailEndpoint.parameters" size="small" border>
            <el-table-column prop="name" :label="t('api_docs_page.cols.name')" width="120" />
            <el-table-column prop="type" :label="t('api_docs_page.detail.param_type')" width="80" />
            <el-table-column :label="t('api_docs_page.detail.param_required')" width="60">
              <template #default="{ row }">
                <el-tag v-if="row.required" type="danger" size="small">{{ t('api_docs_page.detail.param_required_yes') }}</el-tag>
                <span v-else>{{ t('api_docs_page.detail.param_required_no') }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="description" :label="t('api_docs_page.cols.summary')" />
          </el-table>
        </div>

        <!-- 请求体 Schema -->
        <div v-if="detailEndpoint.request_body" class="mt-3">
          <h4>{{ t('api_docs_page.detail.request_body_schema') }}</h4>
          <pre class="code-pre">{{ formatJson(detailEndpoint.request_body) }}</pre>
        </div>

        <!-- 请求示例 -->
        <div v-if="detailEndpoint.example_request" class="mt-3">
          <h4>{{ t('api_docs_page.detail.request_example') }}</h4>
          <pre class="code-pre">{{ formatJson(detailEndpoint.example_request) }}</pre>
        </div>

        <!-- 响应示例 -->
        <div v-if="detailEndpoint.example_response" class="mt-3">
          <h4>{{ t('api_docs_page.detail.response_example') }}</h4>
          <pre class="code-pre">{{ formatJson(detailEndpoint.example_response) }}</pre>
        </div>

        <!-- 代码片段 -->
        <div v-if="detailEndpoint.snippets?.length" class="mt-3">
          <h4>{{ t('api_docs_page.detail.code_examples') }}</h4>
          <el-tabs>
            <el-tab-pane v-for="snippet in detailEndpoint.snippets" :key="snippet.id" :label="snippet.title || snippet.language">
              <pre class="code-pre">{{ snippet.code }}</pre>
              <el-button size="small" class="mt-1" @click="copyText(snippet.code)">
                <el-icon><CopyDocument /></el-icon> {{ t('actions.copy') }}
              </el-button>
            </el-tab-pane>
          </el-tabs>
        </div>

        <!-- 原 code_examples 回退 -->
        <div v-else-if="detailEndpoint.code_examples" class="mt-3">
          <h4>{{ t('api_docs_page.detail.code_examples') }}</h4>
          <el-tabs>
            <el-tab-pane v-for="(code, lang) in detailEndpoint.code_examples" :key="lang" :label="lang">
              <pre class="code-pre">{{ code }}</pre>
            </el-tab-pane>
          </el-tabs>
        </div>
      </template>
    </el-drawer>

    <!-- ───── 新增变更日志对话框 ───── -->
    <el-dialog v-model="showChangelogDlg" :title="t('api_docs_page.changelog.dialog_title')" width="600px">
      <el-form :model="changelogForm" :rules="changelogRules" ref="clFormRef" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item :label="t('api_docs_page.changelog.form.version')" prop="version"><el-input v-model="changelogForm.version" placeholder="v2.1.0" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item :label="t('api_docs_page.changelog.form.release_date')" prop="release_date"><el-date-picker v-model="changelogForm.release_date" type="date" style="width:100%" /></el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('api_docs_page.changelog.form.type')" prop="type">
          <el-select v-model="changelogForm.type" style="width:100%">
            <el-option v-for="opt in changelogFormTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('api_docs_page.changelog.form.title')" prop="title"><el-input v-model="changelogForm.title" maxlength="300" /></el-form-item>
        <el-form-item :label="t('api_docs_page.changelog.form.description')"><el-input v-model="changelogForm.description" type="textarea" :rows="3" /></el-form-item>
        <el-form-item :label="t('api_docs_page.changelog.form.migration_guide')"><el-input v-model="changelogForm.migration_guide" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showChangelogDlg = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="clLoading" @click="submitChangelog">{{ t('actions.submit') }}</el-button>
      </template>
    </el-dialog>

    <!-- ───── 导出 OpenAPI 对话框 ───── -->
    <el-dialog v-model="showExportDlg" :title="t('api_docs_page.export.dialog_title')" width="700px">
      <el-form label-width="0" size="small">
        <el-form-item>
          <el-button type="primary" :loading="exportLoading" @click="doExport">
            <el-icon><Download /></el-icon> {{ t('api_docs_page.export.generate') }}
          </el-button>
          <el-button v-if="exportResult.spec" type="success" class="ml-2" @click="downloadOpenApi">
            <el-icon><Download /></el-icon> {{ t('api_docs_page.export.download_json') }}
          </el-button>
        </el-form-item>
      </el-form>
      <div v-if="exportResult.spec" class="mt-2">
        <div class="mb-2">
          <el-tag>{{ t('api_docs_page.export.version') }}: {{ exportResult.version }}</el-tag>
          <el-tag type="success" class="ml-2">{{ t('api_docs_page.export.endpoints') }}: {{ exportResult.endpoint_count }}</el-tag>
        </div>
        <pre class="code-pre" style="max-height:400px">{{ exportResult.spec }}</pre>
      </div>
      <template #footer>
        <el-button @click="showExportDlg = false">{{ t('actions.close') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
  Refresh, Search, CaretRight, View, CopyDocument, Plus,
  Download, Star, StarFilled, MagicStick
} from '@element-plus/icons-vue';
import apiDocsApi from '../../api/apiDocs';
import { exportLocalizedOpenApi as doExportLocalized } from '@/api/multilingualApiDocs';
import VersionDiff from './components/VersionDiff.vue';

export default {
  name: 'ApiDocs',
  components: { VersionDiff, Refresh, Search, CaretRight, View, CopyDocument, Plus, Download, Star, StarFilled, MagicStick },
  setup() {
    const { t, locale } = useI18n();

    const activeTab = ref('docs');
    const loading = ref(false);
    const scanning = ref(false);
    const testLoading = ref(false);
    const clLoading = ref(false);
    const showDetail = ref(false);
    const showChangelogDlg = ref(false);
    const showExportDlg = ref(false);
    const exportLoading = ref(false);
    const genSnippetLoading = ref(false);
    const batchUpdating = ref(false);
    const favoritesLoading = ref(false);
    const clFormRef = ref(null);
    const detailEndpoint = ref(null);
    const selectedEndpointIds = ref([]);
    const batchStatus = ref('');

    // 文档语言（OpenAPI 导出）
    const currentLocale = ref('zh_CN');
    const exportLocalizedLoading = ref(false);
    const docLocaleLabels = computed(() => ({
      en: t('language.en'),
      zh_CN: t('language.zh_CN'),
      ja: t('language.ja'),
    }));
    const localeLabel = computed(() => docLocaleLabels.value[currentLocale.value] || currentLocale.value);

    const endpointStatusOptions = computed(() => [
      { label: t('api_docs_page.status.active'), value: 'active' },
      { label: 'Beta', value: 'beta' },
      { label: t('api_docs_page.status.deprecated'), value: 'deprecated' },
      { label: t('api_docs_page.status.experimental'), value: 'experimental' },
    ]);

    const statusTextMap = computed(() => ({
      active: t('api_docs_page.status.active'),
      deprecated: t('api_docs_page.status.deprecated_short'),
      experimental: t('api_docs_page.status.experimental_short'),
    }));

    const changelogTypeOptions = computed(() => [
      { label: t('api_docs_page.changelog.types.new'), value: 'new' },
      { label: t('api_docs_page.changelog.types.update'), value: 'update' },
      { label: t('api_docs_page.changelog.types.breaking'), value: 'breaking' },
      { label: t('api_docs_page.changelog.types.deprecation'), value: 'deprecation' },
      { label: t('api_docs_page.changelog.types.removal'), value: 'removal' },
    ]);

    const changelogFormTypeOptions = computed(() => [
      { label: t('api_docs_page.changelog.types.new'), value: 'new' },
      { label: t('api_docs_page.changelog.types.update'), value: 'update' },
      { label: t('api_docs_page.changelog.types.breaking_full'), value: 'breaking' },
      { label: t('api_docs_page.changelog.types.deprecation'), value: 'deprecation' },
      { label: t('api_docs_page.changelog.types.removal'), value: 'removal' },
    ]);

    // 统计
    const stats = reactive({
      total_endpoints: 0, active_endpoints: 0, total_schemas: 0,
      active_sdks: 0, total_changelogs: 0, total_tests: 0,
    });

    // 分组
    const groups = ref({});

    // 端点列表
    const endpoints = ref([]);
    const docFilter = reactive({ group: '', method: '', status: '', search: '' });

    // 测试控制台
    const testForm = reactive({ method: 'GET', url: '' });
    const testHeadersText = ref('');
    const testBodyText = ref('');
    const testResult = reactive({ status: '', response_status: null, response_time_ms: null, response: null, error_message: '' });
    const testHistory = ref([]);

    // SDK
    const sdks = ref([]);
    const sdkPreview = ref(null);

    // 变更日志
    const changelogs = ref([]);
    const changelogFilter = reactive({ type: '' });
    const changelogForm = reactive({
      version: '', release_date: '', type: 'update', title: '', description: '', migration_guide: '',
    });
    const changelogRules = computed(() => ({
      version: [{ required: true, message: t('api_docs_page.validation.version_required') }],
      release_date: [{ required: true, message: t('api_docs_page.validation.release_date_required') }],
      type: [{ required: true, message: t('api_docs_page.validation.type_required') }],
      title: [{ required: true, message: t('api_docs_page.validation.title_required') }],
    }));

    // 自动检测变更 (M3-32)
    const autoDetecting = ref(false);
    const showAutoDetectResult = ref(false);
    const autoDetectResult = ref({});
    const autoDetectLogs = ref([]);

    // 收藏
    const favorites = ref([]);
    const favoriteIds = ref(new Set());

    // 导出 OpenAPI
    const exportResult = reactive({ spec: '', version: '', endpoint_count: 0 });

    // 端点统计
    const endpointStats = ref(null);

    // 计算成功率
    const successRate = computed(() => {
      if (!endpointStats.value || endpointStats.value.total_tests === 0) return 0;
      return Math.round((endpointStats.value.successful_tests / endpointStats.value.total_tests) * 100);
    });
    const successRateClass = computed(() => {
      const r = successRate.value;
      if (r >= 80) return 'text-success';
      if (r >= 50) return 'text-warning';
      return 'text-danger';
    });

    function onLocaleChange() {
      // 文档语言切换仅影响本地化 OpenAPI 导出
    }

    async function exportLocalizedOpenApi() {
      exportLocalizedLoading.value = true;
      try {
        const { data } = await doExportLocalized({ locale: currentLocale.value });
        if (data.success) {
          const spec = typeof data.data === 'string' ? data.data : JSON.stringify(data.data, null, 2);
          const blob = new Blob([spec], { type: 'application/json' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `openapi-${currentLocale.value}.json`;
          a.click();
          URL.revokeObjectURL(url);
          ElMessage.success(t('api_docs_page.messages.openapi_generated'));
        }
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.export_localized_failed', { msg: e.response?.data?.message || e.message }));
      } finally {
        exportLocalizedLoading.value = false;
      }
    }

    // ─── 数据加载 ───
    async function fetchDashboard() {
      try {
        const { data } = await apiDocsApi.getDashboard();
        if (data.success) {
          Object.assign(stats, data.data.stats);
        }
      } catch (e) { /* ignore */ }
    }

    async function fetchGroups() {
      try {
        const { data } = await apiDocsApi.getGroups();
        if (data.success) groups.value = data.data;
      } catch (e) { /* ignore */ }
    }

    async function fetchEndpoints() {
      loading.value = true;
      try {
        const params = { per_page: 200 };
        if (docFilter.group) params.group = docFilter.group;
        if (docFilter.method) params.method = docFilter.method;
        if (docFilter.status) params.status = docFilter.status;
        if (docFilter.search) params.search = docFilter.search;
        const { data } = await apiDocsApi.getEndpoints(params);
        if (data.success) endpoints.value = data.data.data || [];
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.fetch_endpoints_failed'));
      } finally {
        loading.value = false;
      }
    }

    async function fetchSdks() {
      try {
        const { data } = await apiDocsApi.getSdks();
        if (data.success) sdks.value = data.data;
      } catch (e) { /* ignore */ }
    }

    async function fetchChangelogs() {
      try {
        const params = {};
        if (changelogFilter.type) params.type = changelogFilter.type;
        const { data } = await apiDocsApi.getChangelogs(params);
        if (data.success) changelogs.value = data.data;
      } catch (e) { /* ignore */ }
    }

    // 自动检测变更 (M3-32)
    async function autoDetectChanges() {
      let versionId = null;
      try {
        const { data: versions } = await import('../../api/apiVersion').then(m => m.default ? m.default.getApiVersions() : Promise.resolve({ data: { data: [] } }));
        const activeVersions = versions?.data?.filter?.(v => v.status === 'active') || [];
        if (activeVersions.length === 1) {
          versionId = activeVersions[0].id;
        } else if (activeVersions.length > 1) {
          // 简化为使用第一个活跃版本
          versionId = activeVersions[0].id;
        }
      } catch (e) { /* ignore */ }

      if (!versionId) {
        ElMessage.warning(t('api_docs_page.messages.create_version_first'));
        return;
      }

      autoDetecting.value = true;
      try {
        const { data } = await apiDocsApi.autoDetectChanges(versionId);
        if (data.success) {
          autoDetectResult.value = data.data;
          showAutoDetectResult.value = true;
          if (data.data.changelogs_created > 0) {
            ElMessage.success(data.message || t('api_docs_page.messages.detect_complete'));
            fetchChangelogs();
            fetchAutoDetectLogs();
          } else {
            ElMessage.info(data.message || t('api_docs_page.messages.no_changes'));
          }
        }
      } catch (e) {
        ElMessage.error(e.response?.data?.message || t('api_docs_page.messages.auto_detect_failed'));
      } finally {
        autoDetecting.value = false;
      }
    }

    async function fetchAutoDetectLogs() {
      try {
        const { data } = await apiDocsApi.getAutoDetectHistory();
        if (data.success) autoDetectLogs.value = data.data || [];
      } catch (e) { /* ignore */ }
    }

    async function fetchTestHistory() {
      try {
        const { data } = await apiDocsApi.getTestHistory();
        if (data.success) testHistory.value = data.data;
      } catch (e) { /* ignore */ }
    }

    // ─── 收藏 ───
    async function fetchFavorites() {
      favoritesLoading.value = true;
      try {
        const { data } = await apiDocsApi.getFavorites();
        if (data.success) {
          favorites.value = data.data;
          favoriteIds.value = new Set(data.data.map(f => f.endpoint_id));
        }
      } catch (e) { /* ignore */ }
      finally { favoritesLoading.value = false; }
    }

    async function toggleFavorite(endpointId) {
      try {
        const { data } = await apiDocsApi.toggleFavorite(endpointId);
        if (data.success) {
          if (data.data.favorited) {
            ElMessage.success(t('api_docs_page.messages.favorited'));
            favoriteIds.value.add(endpointId);
          } else {
            ElMessage.info(t('api_docs_page.messages.unfavorited'));
            favoriteIds.value.delete(endpointId);
            if (activeTab.value === 'favorites') fetchFavorites();
          }
        }
      } catch (e) {
        ElMessage.error(t('messages.failed'));
      }
    }

    async function removeFavorite(endpointId) {
      try {
        await apiDocsApi.toggleFavorite(endpointId);
        favoriteIds.value.delete(endpointId);
        ElMessage.info(t('api_docs_page.messages.unfavorited'));
        fetchFavorites();
      } catch (e) {
        ElMessage.error(t('messages.failed'));
      }
    }

    // ─── 端点详情 ───
    async function showEndpointDetail(row) {
      try {
        const { data } = await apiDocsApi.getEndpoint(row.id);
        if (data.success) {
          detailEndpoint.value = data.data;
          showDetail.value = true;
          fetchEndpointStats(row.id);
        }
      } catch (e) { /* ignore */ }
    }

    async function showFavoriteEndpoint(row) {
      if (row.endpoint) {
        showEndpointDetail(row.endpoint);
      }
    }

    async function fetchEndpointStats(id) {
      try {
        const { data } = await apiDocsApi.getEndpointStats(id);
        if (data.success) endpointStats.value = data.data;
      } catch (e) { /* ignore */ }
    }

    // ─── 自动生成代码片段 ───
    async function autoGenerateSnippets(endpointId) {
      genSnippetLoading.value = true;
      try {
        const { data } = await apiDocsApi.autoGenerateSnippets(endpointId);
        if (data.success) {
          ElMessage.success(t('api_docs_page.messages.snippets_generated'));
          const detail = await apiDocsApi.getEndpoint(endpointId);
          if (detail.data.success) detailEndpoint.value = detail.data.data;
        }
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.generate_failed', { msg: e.response?.data?.message || e.message }));
      } finally {
        genSnippetLoading.value = false;
      }
    }

    // ─── 在测试控制台中填充 ───
    function fillTestFromEndpoint() {
      if (!detailEndpoint.value) return;
      const ep = detailEndpoint.value;
      testForm.method = ep.method;
      testForm.url = window.location.origin + ep.path;
      if (ep.example_request) {
        testBodyText.value = JSON.stringify(ep.example_request, null, 2);
      }
      activeTab.value = 'console';
    }

    // ─── 扫描路由 ───
    async function scanRoutes() {
      scanning.value = true;
      try {
        const { data } = await apiDocsApi.scanRoutes();
        if (data.success) {
          ElMessage.success(data.data.message);
          fetchEndpoints();
          fetchDashboard();
        }
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.scan_failed', { msg: e.response?.data?.message || e.message }));
      } finally {
        scanning.value = false;
      }
    }

    // ─── 请求搜索防抖 ───
    let searchTimer = null;
    function onSearchDebounce() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(fetchEndpoints, 300);
    }

    // ─── 批量操作 ───
    function onSelectionChange(selection) {
      selectedEndpointIds.value = selection.map(s => s.id);
    }

    async function batchUpdateStatus() {
      if (!batchStatus.value || !selectedEndpointIds.value.length) return;
      batchUpdating.value = true;
      try {
        const { data } = await apiDocsApi.batchUpdateEndpoints(selectedEndpointIds.value, batchStatus.value);
        if (data.success) {
          ElMessage.success(data.message || t('api_docs_page.messages.batch_update_success'));
          selectedEndpointIds.value = [];
          batchStatus.value = '';
          fetchEndpoints();
          fetchDashboard();
        }
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.batch_update_failed'));
      } finally {
        batchUpdating.value = false;
      }
    }

    // ─── 测试控制台 ───
    async function sendTest() {
      testLoading.value = true;
      try {
        const payload = {
          method: testForm.method,
          url: testForm.url,
          headers: testHeadersText.value ? JSON.parse(testHeadersText.value) : {},
          body: testBodyText.value ? JSON.parse(testBodyText.value) : null,
        };
        const { data } = await apiDocsApi.sendTestRequest(payload);
        if (data.success) {
          Object.assign(testResult, data.data);
        }
      } catch (e) {
        testResult.status = 'failed';
        testResult.error_message = e.response?.data?.message || e.message;
      } finally {
        testLoading.value = false;
        fetchTestHistory();
      }
    }

    function clearTest() {
      testForm.method = 'GET';
      testForm.url = '';
      testHeadersText.value = '';
      testBodyText.value = '';
      testResult.status = '';
      testResult.response = null;
      testResult.error_message = '';
    }

    function restoreTest(h) {
      testForm.method = h.method;
      testForm.url = h.url;
      testHeadersText.value = JSON.stringify(h.headers || {}, null, 2);
      testBodyText.value = h.body ? JSON.stringify(h.body, null, 2) : '';
    }

    // ─── SDK ───
    async function previewSdk(language) {
      try {
        const { data } = await apiDocsApi.generateSdk(language);
        if (data.success) sdkPreview.value = data.data;
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.sdk_generate_failed'));
      }
    }

    function copySetupCode(sdk) {
      navigator.clipboard.writeText(sdk.setup_code || '').then(() => {
        ElMessage.success(t('api_docs_page.messages.setup_code_copied'));
      });
    }

    function copySdkCode() {
      if (sdkPreview.value) {
        navigator.clipboard.writeText(sdkPreview.value.code).then(() => {
          ElMessage.success(t('api_docs_page.messages.sdk_code_copied'));
        });
      }
    }

    // ─── 变更日志 ───
    async function submitChangelog() {
      const valid = await clFormRef.value?.validate().catch(() => false);
      if (!valid) return;
      clLoading.value = true;
      try {
        await apiDocsApi.createChangelog({ ...changelogForm });
        ElMessage.success(t('api_docs_page.messages.changelog_created'));
        showChangelogDlg.value = false;
        changelogForm.version = '';
        changelogForm.release_date = '';
        changelogForm.type = 'update';
        changelogForm.title = '';
        changelogForm.description = '';
        changelogForm.migration_guide = '';
        fetchChangelogs();
        fetchDashboard();
      } catch (e) {
        ElMessage.error(e.response?.data?.message || t('api_docs_page.messages.create_failed'));
      } finally {
        clLoading.value = false;
      }
    }

    // ─── OpenAPI 导出 ───
    async function doExport() {
      exportLoading.value = true;
      try {
        const { data } = await apiDocsApi.exportOpenApi();
        if (data.success) {
          Object.assign(exportResult, data.data);
          ElMessage.success(t('api_docs_page.messages.openapi_generated'));
        }
      } catch (e) {
        ElMessage.error(t('api_docs_page.messages.export_failed', { msg: e.response?.data?.message || e.message }));
      } finally {
        exportLoading.value = false;
      }
    }

    function downloadOpenApi() {
      if (!exportResult.spec) return;
      const blob = new Blob([exportResult.spec], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `openapi-${exportResult.version || 'latest'}.json`;
      a.click();
      URL.revokeObjectURL(url);
    }

    // ─── 通用 ───
    function copyText(text) {
      navigator.clipboard.writeText(text).then(() => ElMessage.success(t('api_docs_page.messages.copied')));
    }

    // ─── 工具函数 ───
    function methodTag(m) {
      const map = { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: 'info', DELETE: 'danger' };
      return map[m] || '';
    }

    function changelogTag(type) {
      const map = { new: 'success', update: 'primary', breaking: 'danger', deprecation: 'warning', removal: 'info' };
      return map[type] || '';
    }

    function formatJson(obj) {
      try {
        return JSON.stringify(obj, null, 2);
      } catch (e) {
        return String(obj);
      }
    }

    function formatTime(timeStr) {
      if (!timeStr) return '';
      const loc = locale.value === 'zh_CN' ? 'zh-CN' : locale.value === 'en' ? 'en-US' : locale.value;
      return new Date(timeStr).toLocaleString(loc, { hour12: false });
    }

    // ─── 初始化 ───
    onMounted(async () => {
      fetchDashboard();
      fetchGroups();
      fetchEndpoints();
      fetchSdks();
      fetchChangelogs();
      fetchTestHistory();
      fetchFavorites();
      fetchAutoDetectLogs();
    });

    return {
      t,
      activeTab, loading, scanning, testLoading, clLoading,
      showDetail, showChangelogDlg, showExportDlg, exportLoading,
      genSnippetLoading, batchUpdating, favoritesLoading,
      clFormRef, detailEndpoint, stats, groups, endpoints, docFilter,
      testForm, testHeadersText, testBodyText, testResult, testHistory,
      sdks, sdkPreview,
      changelogs, changelogFilter, changelogForm, changelogRules,
      endpointStatusOptions, statusTextMap, changelogTypeOptions, changelogFormTypeOptions,
      currentLocale, localeLabel, exportLocalizedLoading,
      onLocaleChange, exportLocalizedOpenApi,
      autoDetecting, showAutoDetectResult, autoDetectResult, autoDetectLogs,
      autoDetectChanges, fetchAutoDetectLogs,
      favorites, favoriteIds, endpointStats, successRate, successRateClass,
      exportResult, selectedEndpointIds, batchStatus,
      fetchEndpoints, scanRoutes, onSearchDebounce,
      showEndpointDetail, showFavoriteEndpoint,
      sendTest, clearTest, restoreTest,
      previewSdk, copySetupCode, copySdkCode,
      submitChangelog,
      fetchFavorites, toggleFavorite, removeFavorite,
      autoGenerateSnippets, fillTestFromEndpoint,
      doExport, downloadOpenApi, onSelectionChange, batchUpdateStatus,
      copyText,
      methodTag, changelogTag, formatJson, formatTime,
    };
  },
};
</script>

<style scoped>
.api-docs-page { padding: 16px; }
.stat-item { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.text-muted { color: #909399; }
.text-warning { color: #e6a23c; }
.text-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; }
.tab-toolbar { margin-bottom: 12px; }
.batch-toolbar { display: flex; align-items: center; padding: 8px 12px; background: #fdf6ec; border-radius: 4px; border: 1px solid #faecd8; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-3 { margin-top: 12px; }
.mt-2 { margin-top: 8px; }
.mt-1 { margin-top: 4px; }
.mr-2 { margin-right: 8px; }
.mr-1 { margin-right: 4px; }
.ml-2 { margin-left: 8px; }
.label-text { font-size: 12px; color: #606266; margin-bottom: 4px; }
.response-pre, .code-pre {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 12px;
  border-radius: 4px;
  overflow: auto;
  max-height: 400px;
  font-size: 12px;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-all;
}
.sdk-card { cursor: default; }
.sdk-header { display: flex; align-items: center; gap: 12px; }
.sdk-icon {
  width: 40px; height: 40px; border-radius: 8px;
  background: #0f172a; color: #fff;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 14px;
}
.sdk-info { display: flex; flex-direction: column; }
.install-cmd {
  background: #f5f7fa; padding: 6px 10px; border-radius: 4px;
  font-family: monospace; font-size: 12px; margin-top: 6px;
}
.history-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 0; cursor: pointer; border-bottom: 1px solid #f0f0f0;
}
.history-item:hover { background: #f5f7fa; }
.favorite-btn { font-size: 18px; cursor: pointer; color: #c0c4cc; }
.favorite-btn:hover { color: #e6a23c; }
.favorite-active { color: #e6a23c; }
.detail-actions { display: flex; gap: 8px; flex-wrap: wrap; }
</style>
