<template>
  <div class="api-docs-page">
    <!-- 多语言切换条 -->
    <el-card shadow="never" class="mb-3 i18n-bar">
      <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px">
        <span style="font-size:13px;color:#909399">文档语言:</span>
        <el-radio-group v-model="currentLocale" size="small" @change="onLocaleChange">
          <el-radio-button value="en">English</el-radio-button>
          <el-radio-button value="zh_CN">简体中文</el-radio-button>
          <el-radio-button value="ja">日本語</el-radio-button>
        </el-radio-group>
        <el-button size="small" type="warning" :loading="exportLocalizedLoading" @click="exportLocalizedOpenApi">
          <el-icon><Download /></el-icon> 导出 {{ localeLabel }} OpenAPI
        </el-button>
      </div>
    </el-card>

    <!-- 统计 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_endpoints }}</div><div class="stat-label">总端点</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value text-success">{{ stats.active_endpoints }}</div><div class="stat-label">活跃</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_schemas }}</div><div class="stat-label">Schema</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.active_sdks }}</div><div class="stat-label">SDK</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_changelogs }}</div><div class="stat-label">变更</div></div></el-card></el-col>
      <el-col :span="4"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_tests }}</div><div class="stat-label">测试请求</div></div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <!-- ──────── 标签1: API 文档 ──────── -->
      <el-tab-pane label="API 文档" name="docs">
        <div class="tab-toolbar">
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="docFilter.group" placeholder="分组" clearable @change="fetchEndpoints" style="width:130px">
                <el-option v-for="(label, key) in groups" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="docFilter.method" placeholder="方法" clearable @change="fetchEndpoints" style="width:100px">
                <el-option label="GET" value="GET" />
                <el-option label="POST" value="POST" />
                <el-option label="PUT" value="PUT" />
                <el-option label="PATCH" value="PATCH" />
                <el-option label="DELETE" value="DELETE" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="docFilter.status" placeholder="状态" clearable @change="fetchEndpoints" style="width:110px">
                <el-option label="活跃" value="active" />
                <el-option label="Beta" value="beta" />
                <el-option label="已废弃" value="deprecated" />
                <el-option label="实验性" value="experimental" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-input v-model="docFilter.search" placeholder="搜索路径/说明" clearable @input="onSearchDebounce" style="width:200px" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="fetchEndpoints"><el-icon><Refresh /></el-icon> 刷新</el-button>
              <el-button type="success" :loading="scanning" @click="scanRoutes">
                <el-icon><Search /></el-icon> 扫描路由
              </el-button>
              <el-button type="warning" @click="showExportDlg = true">
                <el-icon><Download /></el-icon> 导出 OpenAPI
              </el-button>
            </el-form-item>
          </el-form>
        </div>

        <!-- 批量操作工具栏 -->
        <div v-if="selectedEndpointIds.length > 0" class="batch-toolbar mb-2">
          <span class="mr-2">已选 {{ selectedEndpointIds.length }} 个端点</span>
          <el-select v-model="batchStatus" placeholder="批量设置状态" size="small" style="width:130px" class="mr-2">
            <el-option label="活跃" value="active" />
            <el-option label="Beta" value="beta" />
            <el-option label="已废弃" value="deprecated" />
            <el-option label="实验性" value="experimental" />
          </el-select>
          <el-button :loading="batchUpdating" size="small" type="primary" :disabled="!batchStatus" @click="batchUpdateStatus">应用</el-button>
          <el-button size="small" @click="selectedEndpointIds = []">取消选择</el-button>
        </div>

        <el-table :data="endpoints" v-loading="loading" stripe @row-click="showEndpointDetail" style="cursor:pointer"
          @selection-change="onSelectionChange">
          <el-table-column type="selection" width="40" />
          <el-table-column label="方法" width="90">
            <template #default="{ row }">
              <el-tag :type="methodTag(row.method)" size="small" effect="dark" style="width:60px;text-align:center">{{ row.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="path" label="路径" min-width="300" show-overflow-tooltip />
          <el-table-column prop="summary" label="说明" min-width="200" show-overflow-tooltip />
          <el-table-column prop="group" label="分组" width="100">
            <template #default="{ row }">{{ groups[row.group] || row.group }}</template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="{ row }">
              <el-tag v-if="row.status === 'beta'" type="warning" size="small">Beta</el-tag>
              <el-tag v-else-if="row.status === 'deprecated'" type="danger" size="small">废弃</el-tag>
              <el-tag v-else-if="row.status === 'experimental'" type="info" size="small">实验</el-tag>
              <span v-else class="text-success">活跃</span>
            </template>
          </el-table-column>
          <el-table-column label="收藏" width="60">
            <template #default="{ row }">
              <el-tooltip :content="favoriteIds.has(row.id) ? '取消收藏' : '收藏'">
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
      <el-tab-pane label="测试控制台" name="console">
        <el-row :gutter="20">
          <el-col :span="14">
            <el-card shadow="never">
              <template #header>请求</template>
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
                  <div class="label-text">请求头 (JSON)</div>
                  <el-input v-model="testHeadersText" type="textarea" :rows="3" placeholder='{"Authorization": "Bearer xxx"}' />
                </div>
                <div class="mt-2">
                  <div class="label-text">请求体 (JSON)</div>
                  <el-input v-model="testBodyText" type="textarea" :rows="6" placeholder='{"key": "value"}' />
                </div>
                <div class="mt-2">
                  <el-button type="primary" :loading="testLoading" @click="sendTest">
                    <el-icon><CaretRight /></el-icon> 发送请求
                  </el-button>
                  <el-button @click="clearTest">清空</el-button>
                </div>
              </el-form>
            </el-card>
          </el-col>
          <el-col :span="10">
            <el-card shadow="never">
              <template #header>
                <span>响应</span>
                <el-tag v-if="testResult.status === 'success'" type="success" size="small" class="ml-2">{{ testResult.response_status }}</el-tag>
                <el-tag v-else-if="testResult.status === 'failed'" type="danger" size="small" class="ml-2">失败</el-tag>
                <span v-if="testResult.response_time_ms" class="ml-2 text-muted">{{ testResult.response_time_ms }}ms</span>
              </template>
              <pre v-if="testResult.response" class="response-pre">{{ formatJson(testResult.response) }}</pre>
              <div v-else-if="testResult.error_message" class="text-danger">{{ testResult.error_message }}</div>
              <div v-else class="text-muted">等待发送请求...</div>
            </el-card>
            <el-card shadow="never" class="mt-3">
              <template #header>历史记录</template>
              <div v-if="testHistory.length">
                <div v-for="h in testHistory" :key="h.id" class="history-item" @click="restoreTest(h)">
                  <el-tag :type="h.status === 'success' ? 'success' : 'danger'" size="small">{{ h.method }}</el-tag>
                  <span class="text-ellipsis">{{ h.url }}</span>
                  <small class="text-muted">{{ formatTime(h.created_at) }}</small>
                </div>
              </div>
              <div v-else class="text-muted">暂无记录</div>
            </el-card>
          </el-col>
        </el-row>
      </el-tab-pane>

      <!-- ──────── 标签3: SDK ──────── -->
      <el-tab-pane label="SDK 客户端" name="sdk">
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
                <el-button size="small" @click="previewSdk(sdk.language)"><el-icon><View /></el-icon> 预览</el-button>
                <el-button size="small" type="primary" @click="copySetupCode(sdk)"><el-icon><CopyDocument /></el-icon> 复制初始化</el-button>
              </div>
            </el-card>
          </el-col>
        </el-row>
        <el-card v-if="sdkPreview" shadow="never">
          <template #header>
            <span>SDK 预览 - {{ sdkPreview.language }}</span>
            <el-button size="small" class="ml-2" @click="sdkPreview = null">关闭</el-button>
            <el-button size="small" type="primary" class="ml-2" @click="copySdkCode"><el-icon><CopyDocument /></el-icon> 复制代码</el-button>
          </template>
          <pre class="code-pre">{{ sdkPreview.code }}</pre>
        </el-card>
      </el-tab-pane>

      <!-- ──────── 标签4: 变更日志 ──────── -->
      <el-tab-pane label="API 变更日志" name="changelog">
        <div class="tab-toolbar">
          <el-button type="primary" @click="showChangelogDlg = true"><el-icon><Plus /></el-icon> 新增变更</el-button>
          <el-button type="success" :loading="autoDetecting" @click="autoDetectChanges">
            <el-icon><MagicStick /></el-icon> 自动检测变更
          </el-button>
          <el-select v-model="changelogFilter.type" placeholder="类型" clearable @change="fetchChangelogs" style="width:120px" class="ml-2">
            <el-option label="新功能" value="new" />
            <el-option label="更新" value="update" />
            <el-option label="破坏性" value="breaking" />
            <el-option label="废弃" value="deprecation" />
            <el-option label="移除" value="removal" />
          </el-select>
          <el-tag v-if="autoDetectLogs.length" type="info" effect="plain" size="small" class="ml-2">
            已自动生成 {{ autoDetectLogs.length }} 条记录
          </el-tag>
        </div>
        <el-timeline>
          <el-timeline-item v-for="log in changelogs" :key="log.id" :timestamp="log.release_date" placement="top">
            <el-card shadow="hover">
              <h4>
                <el-tag :type="changelogTag(log.type)" size="small" effect="dark">{{ log.version }}</el-tag>
                <el-tag v-if="log.source === 'auto_detect'" size="small" type="success" effect="plain" class="ml-1">自动</el-tag>
                <span class="ml-2">{{ log.title }}</span>
              </h4>
              <p v-if="log.description" class="text-muted mt-1" style="white-space:pre-wrap">{{ log.description }}</p>
              <p v-if="log.migration_guide" class="mt-1"><el-tag type="warning" size="small">迁移指南:</el-tag> {{ log.migration_guide }}</p>
              <p v-if="log.affected_endpoints?.length" class="mt-1">
                <el-tag v-for="ep in log.affected_endpoints" :key="ep" size="small" class="mr-1">{{ ep }}</el-tag>
              </p>
            </el-card>
          </el-timeline-item>
        </el-timeline>

        <!-- 自动检测结果对话框 -->
        <el-dialog v-model="showAutoDetectResult" title="自动检测结果" width="600px">
          <el-alert v-if="autoDetectResult.status === 'snapshot_created'" type="info" :description="autoDetectResult.message" show-icon :closable="false" />
          <div v-else>
            <el-alert :type="autoDetectResult.changelogs_created > 0 ? 'success' : 'info'" show-icon :closable="false">
              <template #title>
                <div>{{ autoDetectResult.message }}</div>
              </template>
            </el-alert>
            <div v-if="autoDetectResult.changes" class="mt-3">
              <el-descriptions :column="4" border size="small">
                <el-descriptions-item v-if="autoDetectResult.changes.added" label="新增" label-class-name="text-success">{{ autoDetectResult.changes.added }}</el-descriptions-item>
                <el-descriptions-item v-if="autoDetectResult.changes.changed" label="修改" label-class-name="text-primary">{{ autoDetectResult.changes.changed }}</el-descriptions-item>
                <el-descriptions-item v-if="autoDetectResult.changes.deprecated" label="弃用" label-class-name="text-warning">{{ autoDetectResult.changes.deprecated }}</el-descriptions-item>
                <el-descriptions-item v-if="autoDetectResult.changes.removed" label="移除" label-class-name="text-danger">{{ autoDetectResult.changes.removed }}</el-descriptions-item>
              </el-descriptions>
            </div>
            <div v-if="autoDetectResult.added?.length" class="mt-3">
              <h5>新增端点:</h5>
              <div v-for="item in autoDetectResult.added" :key="item.key" class="change-item">
                <el-tag size="small" type="success" effect="plain">{{ item.method }}</el-tag>
                <code>{{ item.path }}</code>
                <span v-if="item.summary" class="text-muted">— {{ item.summary }}</span>
              </div>
            </div>
            <div v-if="autoDetectResult.removed?.length" class="mt-3">
              <h5>移除端点:</h5>
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
      <el-tab-pane label="版本差异对比" name="diff">
        <VersionDiff />
      </el-tab-pane>

      <!-- ──────── 标签6: 我的收藏 ──────── -->
      <el-tab-pane label="我的收藏" name="favorites">
        <div class="tab-toolbar">
          <el-button type="primary" @click="fetchFavorites"><el-icon><Refresh /></el-icon> 刷新</el-button>
        </div>
        <el-table :data="favorites" v-loading="favoritesLoading" stripe @row-click="showFavoriteEndpoint" style="cursor:pointer">
          <el-table-column label="方法" width="90">
            <template #default="{ row }">
              <el-tag :type="methodTag(row.endpoint.method)" size="small" effect="dark" style="width:60px;text-align:center">{{ row.endpoint.method }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="endpoint.path" label="路径" min-width="300" show-overflow-tooltip />
          <el-table-column prop="endpoint.summary" label="说明" min-width="200" show-overflow-tooltip />
          <el-table-column prop="note" label="备注" min-width="150" show-overflow-tooltip />
          <el-table-column label="收藏时间" width="170">
            <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
          </el-table-column>
          <el-table-column label="操作" width="80">
            <template #default="{ row }">
              <el-button size="small" type="danger" link @click.stop="removeFavorite(row.endpoint_id)">
                取消收藏
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-empty v-if="!favorites.length && !favoritesLoading" description="暂无收藏的端点" />
      </el-tab-pane>
    </el-tabs>

    <!-- ───── 端点详情抽屉 ───── -->
    <el-drawer v-model="showDetail" :title="detailEndpoint?.method + ' ' + detailEndpoint?.path" size="700px">
      <template v-if="detailEndpoint">
        <!-- 端点信息 -->
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="方法">
            <el-tag :type="methodTag(detailEndpoint.method)" size="small">{{ detailEndpoint.method }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="路径">{{ detailEndpoint.path }}</el-descriptions-item>
          <el-descriptions-item label="分组">{{ groups[detailEndpoint.group] || detailEndpoint.group }}</el-descriptions-item>
          <el-descriptions-item label="状态">
            <el-tag v-if="detailEndpoint.status === 'beta'" type="warning" size="small">Beta</el-tag>
            <el-tag v-else-if="detailEndpoint.status === 'deprecated'" type="danger" size="small">废弃</el-tag>
            <el-tag v-else-if="detailEndpoint.status === 'experimental'" type="info" size="small">实验</el-tag>
            <span v-else class="text-success">活跃</span>
          </el-descriptions-item>
        </el-descriptions>

        <!-- 操作按钮 -->
        <div class="detail-actions mt-2">
          <el-button size="small" :type="favoriteIds.has(detailEndpoint.id) ? 'danger' : 'default'"
            @click="toggleFavorite(detailEndpoint.id)">
            <el-icon><StarFilled v-if="favoriteIds.has(detailEndpoint.id)" /><Star v-else /></el-icon>
            {{ favoriteIds.has(detailEndpoint.id) ? '取消收藏' : '收藏' }}
          </el-button>
          <el-button size="small" :loading="genSnippetLoading" @click="autoGenerateSnippets(detailEndpoint.id)">
            <el-icon><MagicStick /></el-icon> 自动生成代码示例
          </el-button>
          <el-button size="small" @click="fillTestFromEndpoint">
            <el-icon><CaretRight /></el-icon> 在控制台测试
          </el-button>
        </div>

        <!-- 端点统计 -->
        <el-collapse class="mt-2">
          <el-collapse-item title="端点统计" name="stats">
            <el-descriptions v-if="endpointStats" :column="3" border size="small">
              <el-descriptions-item label="测试次数">{{ endpointStats.total_tests }}</el-descriptions-item>
              <el-descriptions-item label="成功率">
                <span :class="successRateClass">{{ successRate }}%</span>
              </el-descriptions-item>
              <el-descriptions-item label="平均响应">{{ endpointStats.avg_response_time_ms }}ms</el-descriptions-item>
              <el-descriptions-item label="收藏数">{{ endpointStats.favorite_count }}</el-descriptions-item>
              <el-descriptions-item label="最近测试">{{ endpointStats.last_tested_at ? formatTime(endpointStats.last_tested_at) : '从未' }}</el-descriptions-item>
            </el-descriptions>
            <div v-else class="text-muted">暂无统计数据</div>
          </el-collapse-item>
        </el-collapse>

        <!-- 说明 -->
        <div class="mt-3">
          <h4>说明</h4>
          <p>{{ detailEndpoint.summary || '无' }}</p>
          <p v-if="detailEndpoint.description">{{ detailEndpoint.description }}</p>
        </div>

        <!-- 参数 -->
        <div v-if="detailEndpoint.parameters?.length" class="mt-3">
          <h4>参数</h4>
          <el-table :data="detailEndpoint.parameters" size="small" border>
            <el-table-column prop="name" label="名称" width="120" />
            <el-table-column prop="type" label="类型" width="80" />
            <el-table-column label="必需" width="60">
              <template #default="{ row }">
                <el-tag v-if="row.required" type="danger" size="small">必填</el-tag>
                <span v-else>可选</span>
              </template>
            </el-table-column>
            <el-table-column prop="description" label="说明" />
          </el-table>
        </div>

        <!-- 请求体 Schema -->
        <div v-if="detailEndpoint.request_body" class="mt-3">
          <h4>请求体 Schema</h4>
          <pre class="code-pre">{{ formatJson(detailEndpoint.request_body) }}</pre>
        </div>

        <!-- 请求示例 -->
        <div v-if="detailEndpoint.example_request" class="mt-3">
          <h4>请求示例</h4>
          <pre class="code-pre">{{ formatJson(detailEndpoint.example_request) }}</pre>
        </div>

        <!-- 响应示例 -->
        <div v-if="detailEndpoint.example_response" class="mt-3">
          <h4>响应示例</h4>
          <pre class="code-pre">{{ formatJson(detailEndpoint.example_response) }}</pre>
        </div>

        <!-- 代码片段 -->
        <div v-if="detailEndpoint.snippets?.length" class="mt-3">
          <h4>代码示例</h4>
          <el-tabs>
            <el-tab-pane v-for="snippet in detailEndpoint.snippets" :key="snippet.id" :label="snippet.title || snippet.language">
              <pre class="code-pre">{{ snippet.code }}</pre>
              <el-button size="small" class="mt-1" @click="copyText(snippet.code)">
                <el-icon><CopyDocument /></el-icon> 复制
              </el-button>
            </el-tab-pane>
          </el-tabs>
        </div>

        <!-- 原 code_examples 回退 -->
        <div v-else-if="detailEndpoint.code_examples" class="mt-3">
          <h4>代码示例</h4>
          <el-tabs>
            <el-tab-pane v-for="(code, lang) in detailEndpoint.code_examples" :key="lang" :label="lang">
              <pre class="code-pre">{{ code }}</pre>
            </el-tab-pane>
          </el-tabs>
        </div>
      </template>
    </el-drawer>

    <!-- ───── 新增变更日志对话框 ───── -->
    <el-dialog v-model="showChangelogDlg" title="新增 API 变更日志" width="600px">
      <el-form :model="changelogForm" :rules="changelogRules" ref="clFormRef" label-width="100px">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="版本" prop="version"><el-input v-model="changelogForm.version" placeholder="v2.1.0" /></el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="发布日期" prop="release_date"><el-date-picker v-model="changelogForm.release_date" type="date" style="width:100%" /></el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="类型" prop="type">
          <el-select v-model="changelogForm.type" style="width:100%">
            <el-option label="新功能" value="new" />
            <el-option label="更新" value="update" />
            <el-option label="破坏性变更" value="breaking" />
            <el-option label="废弃" value="deprecation" />
            <el-option label="移除" value="removal" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题" prop="title"><el-input v-model="changelogForm.title" maxlength="300" /></el-form-item>
        <el-form-item label="描述"><el-input v-model="changelogForm.description" type="textarea" :rows="3" /></el-form-item>
        <el-form-item label="迁移指南"><el-input v-model="changelogForm.migration_guide" type="textarea" :rows="2" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showChangelogDlg = false">取消</el-button>
        <el-button type="primary" :loading="clLoading" @click="submitChangelog">提交</el-button>
      </template>
    </el-dialog>

    <!-- ───── 导出 OpenAPI 对话框 ───── -->
    <el-dialog v-model="showExportDlg" title="导出 OpenAPI 3.0 规范" width="700px">
      <el-form label-width="0" size="small">
        <el-form-item>
          <el-button type="primary" :loading="exportLoading" @click="doExport">
            <el-icon><Download /></el-icon> 生成并导出
          </el-button>
          <el-button v-if="exportResult.spec" type="success" class="ml-2" @click="downloadOpenApi">
            <el-icon><Download /></el-icon> 下载 JSON 文件
          </el-button>
        </el-form-item>
      </el-form>
      <div v-if="exportResult.spec" class="mt-2">
        <div class="mb-2">
          <el-tag>版本: {{ exportResult.version }}</el-tag>
          <el-tag type="success" class="ml-2">端点: {{ exportResult.endpoint_count }}</el-tag>
        </div>
        <pre class="code-pre" style="max-height:400px">{{ exportResult.spec }}</pre>
      </div>
      <template #footer>
        <el-button @click="showExportDlg = false">关闭</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage } from 'element-plus';
import {
  Refresh, Search, CaretRight, View, CopyDocument, Plus,
  Download, Star, StarFilled, MagicStick
} from '@element-plus/icons-vue';
import apiDocsApi from '../../api/apiDocs';
import { getSupportedLocales, exportLocalizedOpenApi as doExportLocalized } from '@/api/multilingualApiDocs';
import VersionDiff from './components/VersionDiff.vue';

export default {
  name: 'ApiDocs',
  components: { VersionDiff, Refresh, Search, CaretRight, View, CopyDocument, Plus, Download, Star, StarFilled, MagicStick },
  setup() {
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
    const changelogRules = {
      version: [{ required: true, message: '请输入版本号' }],
      release_date: [{ required: true, message: '请选择日期' }],
      type: [{ required: true, message: '请选择类型' }],
      title: [{ required: true, message: '请输入标题' }],
    };

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
        ElMessage.error('获取端点列表失败');
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
        ElMessage.warning('请先创建 API 版本');
        return;
      }

      autoDetecting.value = true;
      try {
        const { data } = await apiDocsApi.autoDetectChanges(versionId);
        if (data.success) {
          autoDetectResult.value = data.data;
          showAutoDetectResult.value = true;
          if (data.data.changelogs_created > 0) {
            ElMessage.success(data.message || '检测完成');
            fetchChangelogs();
            // Refresh auto-detect logs count
            fetchAutoDetectLogs();
          } else {
            ElMessage.info(data.message || '无变更');
          }
        }
      } catch (e) {
        ElMessage.error(e.response?.data?.message || '自动检测失败');
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
            ElMessage.success('已收藏');
            favoriteIds.value.add(endpointId);
          } else {
            ElMessage.info('已取消收藏');
            favoriteIds.value.delete(endpointId);
            // 如果在收藏标签页，刷新列表
            if (activeTab.value === 'favorites') fetchFavorites();
          }
        }
      } catch (e) {
        ElMessage.error('操作失败');
      }
    }

    async function removeFavorite(endpointId) {
      try {
        await apiDocsApi.toggleFavorite(endpointId);
        favoriteIds.value.delete(endpointId);
        ElMessage.info('已取消收藏');
        fetchFavorites();
      } catch (e) {
        ElMessage.error('操作失败');
      }
    }

    // ─── 端点详情 ───
    async function showEndpointDetail(row) {
      try {
        const { data } = await apiDocsApi.getEndpoint(row.id);
        if (data.success) {
          detailEndpoint.value = data.data;
          showDetail.value = true;
          // 获取统计
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
          ElMessage.success('代码示例已生成');
          // 刷新端点详情
          const detail = await apiDocsApi.getEndpoint(endpointId);
          if (detail.data.success) detailEndpoint.value = detail.data.data;
        }
      } catch (e) {
        ElMessage.error('生成失败: ' + (e.response?.data?.message || e.message));
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
        ElMessage.error('扫描失败: ' + (e.response?.data?.message || e.message));
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
          ElMessage.success(data.message || '批量更新成功');
          selectedEndpointIds.value = [];
          batchStatus.value = '';
          fetchEndpoints();
          fetchDashboard();
        }
      } catch (e) {
        ElMessage.error('批量更新失败');
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
        ElMessage.error('SDK 生成失败');
      }
    }

    function copySetupCode(sdk) {
      navigator.clipboard.writeText(sdk.setup_code || '').then(() => {
        ElMessage.success('初始化代码已复制');
      });
    }

    function copySdkCode() {
      if (sdkPreview.value) {
        navigator.clipboard.writeText(sdkPreview.value.code).then(() => {
          ElMessage.success('SDK 代码已复制');
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
        ElMessage.success('变更日志已创建');
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
        ElMessage.error(e.response?.data?.message || '创建失败');
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
          ElMessage.success('OpenAPI 规范已生成');
        }
      } catch (e) {
        ElMessage.error('导出失败: ' + (e.response?.data?.message || e.message));
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
      navigator.clipboard.writeText(text).then(() => ElMessage.success('已复制'));
    }

    // ─── 工具函数 ───
    function methodTag(m) {
      const map = { GET: 'success', POST: 'primary', PUT: 'warning', PATCH: 'info', DELETE: 'danger' };
      return map[m] || '';
    }

    function changelogTag(t) {
      const map = { new: 'success', update: 'primary', breaking: 'danger', deprecation: 'warning', removal: 'info' };
      return map[t] || '';
    }

    function formatJson(obj) {
      try {
        return JSON.stringify(obj, null, 2);
      } catch (e) {
        return String(obj);
      }
    }

    function formatTime(t) {
      if (!t) return '';
      return new Date(t).toLocaleString('zh-CN', { hour12: false });
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
      activeTab, loading, scanning, testLoading, clLoading,
      showDetail, showChangelogDlg, showExportDlg, exportLoading,
      genSnippetLoading, batchUpdating, favoritesLoading,
      clFormRef, detailEndpoint, stats, groups, endpoints, docFilter,
      testForm, testHeadersText, testBodyText, testResult, testHistory,
      sdks, sdkPreview,
      changelogs, changelogFilter, changelogForm, changelogRules,
      // M3-32
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
  background: #409eff; color: #fff;
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
