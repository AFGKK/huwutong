<template>
    <div class="llm-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('llm_page.title') }}</h2>
                <span class="header-subtitle">{{ t('llm_page.subtitle') }}</span>
            </div>
        </div>

        <el-alert :title="t('llm_page.alert')" type="success" show-icon :closable="false" class="alert-bar" />

        <el-tabs v-model="activeTab" class="tabs-container">
            <!-- Provider 管理 -->
            <el-tab-pane :label="t('llm_page.tabs.providers')" name="providers">
                <div style="margin-bottom:12px">
                    <el-button type="primary" size="small" @click="openCreateDialog">{{ t('llm_page.add_provider') }}</el-button>
                </div>
                <el-table :data="providers" v-loading="loadingProviders" stripe>
                    <el-table-column :label="t('llm_page.cols.sort')" width="60" prop="sort_order" align="center" />
                    <el-table-column :label="t('llm_page.cols.name')" min-width="120" prop="name" />
                    <el-table-column :label="t('llm_page.cols.driver')" width="110" prop="driver" />
                    <el-table-column :label="t('llm_page.cols.default_model')" width="150" prop="default_model" />
                    <el-table-column :label="t('llm_page.cols.api_base')" min-width="200" prop="api_base" />
                    <el-table-column :label="t('llm_page.cols.api_key')" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.api_key_set ? 'success' : 'danger'" size="small" effect="plain">
                                {{ row.api_key_set ? t('llm_page.status.configured') : t('llm_page.status.not_configured') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.status')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? t('llm_page.status.enabled') : t('llm_page.status.disabled') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.fallback')" width="60" align="center">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_fallback" type="warning" size="small">{{ t('llm_page.status.fallback_tag') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.actions')" width="180" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openEditDialog(row)">{{ t('actions.edit') }}</el-button>
                            <el-button text size="small" type="success" :loading="testingId === row.id" @click="handleTest(row)">{{ t('llm_page.test') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 编辑/新增 Provider 对话框 -->
                <el-dialog v-model="editDialogVisible" :title="editDialogTitle" width="560px">
                    <el-form :model="editForm" label-position="top" size="small">
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item :label="t('llm_page.providers.name')">
                                    <el-input v-model="editForm.name" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="12" v-if="isCreating">
                                <el-form-item :label="t('llm_page.providers.driver')">
                                    <el-select v-model="editForm.driver" style="width:100%">
                                        <el-option v-for="opt in driverOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-row :gutter="16">
                            <el-col :span="12">
                                <el-form-item :label="t('llm_page.providers.default_model')">
                                    <el-input v-model="editForm.default_model" :placeholder="t('llm_page.providers.default_model_ph')" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                        <el-form-item :label="t('llm_page.providers.api_base')">
                            <el-input v-model="editForm.api_base" :placeholder="t('llm_page.providers.api_base_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('llm_page.providers.api_key')">
                            <el-input v-model="editForm.api_key" type="password" show-password :placeholder="t('llm_page.providers.api_key_ph')" />
                        </el-form-item>
                        <el-row :gutter="16">
                            <el-col :span="8">
                                <el-form-item :label="t('llm_page.providers.temperature')">
                                    <el-input-number v-model="editForm.config.temperature" :min="0" :max="2" :step="0.1" :precision="1" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="8">
                                <el-form-item :label="t('llm_page.providers.max_tokens')">
                                    <el-input-number v-model="editForm.config.max_tokens" :min="100" :max="128000" :step="1000" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="4">
                                <el-form-item :label="t('llm_page.providers.enable')">
                                    <el-switch v-model="editForm.is_active" />
                                </el-form-item>
                            </el-col>
                            <el-col :span="4">
                                <el-form-item :label="t('llm_page.providers.fallback')">
                                    <el-switch v-model="editForm.is_fallback" />
                                </el-form-item>
                            </el-col>
                        </el-row>
                    </el-form>
                    <template #footer>
                        <el-button @click="editDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="savingProvider" @click="handleSaveProvider">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- AI 聊天测试 -->
            <el-tab-pane :label="t('llm_page.tabs.chat')" name="chat">
                <div class="chat-container">
                    <div class="chat-messages" ref="chatRef">
                        <div v-if="chatMessages.length === 0" class="chat-empty">
                            <el-icon :size="48" color="#c0c4cc"><ChatLineSquare /></el-icon>
                            <p>{{ t('llm_page.chat.empty') }}</p>
                        </div>
                        <div v-for="(msg, i) in chatMessages" :key="i" :class="['chat-msg', msg.role]">
                            <div class="msg-role">{{ msg.role === 'user' ? t('llm_page.chat.you') : t('llm_page.chat.ai') }}</div>
                            <div class="msg-content" v-html="renderMarkdown(msg.content)" />
                            <div v-if="msg.usage" class="msg-usage">
                                {{ t('llm_page.chat.token_usage', { tokens: msg.usage.total_tokens, ms: msg.duration_ms }) }}
                            </div>
                        </div>
                        <div v-if="streaming" class="chat-msg assistant">
                            <div class="msg-role">{{ t('llm_page.chat.ai') }}</div>
                            <div class="msg-content">{{ streamContent }}<span class="cursor-blink">▍</span></div>
                        </div>
                    </div>
                    <div class="chat-input-bar">
                        <el-select v-model="chatProvider" size="small" style="width: 130px; margin-right: 8px;">
                            <el-option v-for="p in activeProviders" :key="p.slug" :label="p.name" :value="p.slug" />
                        </el-select>
                        <el-select v-model="chatModel" size="small" style="width: 150px; margin-right: 8px;">
                            <el-option v-for="m in availableModels" :key="m.id" :label="m.name" :value="m.id" />
                        </el-select>
                        <el-input v-model="chatInput" size="small" :placeholder="t('llm_page.chat.input_ph')" @keyup.enter="sendChat" :disabled="streaming" clearable />
                        <el-button type="primary" size="small" :loading="streaming" @click="sendChat" style="margin-left: 8px;">
                            {{ t('llm_page.send') }}
                        </el-button>
                        <el-button size="small" @click="clearChat" style="margin-left: 4px;">{{ t('llm_page.clear') }}</el-button>
                    </div>
                </div>
            </el-tab-pane>

            <!-- 自动降级管理 -->
            <el-tab-pane :label="t('llm_page.tabs.fallback')" name="fallback">
                <div class="fallback-panel">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never" class="section-card">
                                <template #header>
                                    <div class="card-header">
                                        <span>{{ t('llm_page.fallback.status_title') }}</span>
                                        <el-tag v-if="fallbackStatus?.fallback_active" type="warning">{{ t('llm_page.status.in_fallback') }}</el-tag>
                                        <el-tag v-else type="success">{{ t('llm_page.status.normal') }}</el-tag>
                                    </div>
                                </template>
                                <el-descriptions :column="1" border v-if="fallbackStatus">
                                    <el-descriptions-item :label="t('llm_page.fallback.strategy')">
                                        <el-tag size="small">{{ fallbackStatus.fallback_strategy || t('llm_page.none') }}</el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('llm_page.fallback.active_provider')">
                                        {{ fallbackStatus.fallback_provider || t('llm_page.dash') }}
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('llm_page.fallback.consecutive_failures')">
                                        <el-tag :type="(fallbackStatus.consecutive_failures || 0) > 3 ? 'danger' : 'info'" size="small">
                                            {{ fallbackStatus.consecutive_failures || 0 }}
                                        </el-tag>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('llm_page.fallback.triggered_at')">
                                        {{ fallbackStatus.fallback_triggered_at || t('llm_page.dash') }}
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('llm_page.fallback.total_fallbacks')">
                                        {{ fallbackStatus.total_fallbacks || 0 }}
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('llm_page.fallback.last_reason')">
                                        {{ fallbackStatus.last_fallback_reason || t('llm_page.dash') }}
                                    </el-descriptions-item>
                                </el-descriptions>
                                <div v-else class="empty-data">
                                    <el-skeleton :rows="4" animated />
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never" class="section-card">
                                <template #header><span>{{ t('llm_page.fallback.provider_health') }}</span></template>
                                <el-table :data="fallbackStatus?.provider_health || []" size="small" v-if="(fallbackStatus?.provider_health?.length || 0) > 0">
                                    <el-table-column :label="t('llm_page.cols.provider')" prop="provider_name" />
                                    <el-table-column :label="t('llm_page.cols.health')" width="100">
                                        <template #default="{ row }">
                                            <el-tag :type="row.healthy ? 'success' : 'danger'" size="small">
                                                {{ row.healthy ? t('llm_page.status.healthy') : t('llm_page.status.unhealthy') }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.last_success')" width="160" prop="last_success_at">
                                        <template #default="{ row }">{{ row.last_success_at || t('llm_page.dash') }}</template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.failure_rate')" width="100">
                                        <template #default="{ row }">
                                            {{ row.failure_rate ? (row.failure_rate * 100).toFixed(1) + '%' : '0%' }}
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-empty v-else-if="!loadingFallback" :description="t('llm_page.fallback.no_health_data')" />
                                <el-skeleton v-else :rows="3" animated />
                            </el-card>
                        </el-col>
                    </el-row>
                    <el-card shadow="never" class="section-card">
                        <template #header><span>{{ t('llm_page.fallback.actions_title') }}</span></template>
                        <div class="action-bar">
                            <el-button type="warning" @click="handleResetFallback" :loading="resettingFallback">
                                <el-icon style="margin-right:4px"><Refresh /></el-icon>{{ t('llm_page.reset_fallback') }}
                            </el-button>
                            <el-button @click="loadFallbackStatus" :loading="loadingFallback" style="margin-left: 8px;">
                                {{ t('llm_page.refresh_status') }}
                            </el-button>
                            <span class="tip-text">{{ t('llm_page.fallback.reset_tip') }}</span>
                        </div>
                    </el-card>
                </div>
            </el-tab-pane>

            <!-- LLM 健康监控 -->
            <el-tab-pane :label="t('llm_page.tabs.health')" name="health">
                <div class="health-panel">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never" class="section-card">
                                <template #header>
                                    <div class="card-header">
                                        <span>{{ t('llm_page.health.provider_status') }}</span>
                                        <el-button size="small" @click="runHealthCheck" :loading="checkingHealth">{{ t('llm_page.run_check') }}</el-button>
                                    </div>
                                </template>
                                <el-table :data="healthData" size="small" v-loading="loadingHealth">
                                    <el-table-column :label="t('llm_page.cols.provider')" prop="name" width="120" />
                                    <el-table-column :label="t('llm_page.cols.status')" width="80">
                                        <template #default="{ row }">
                                            <el-tag v-if="row.healthy" type="success" size="small">{{ t('llm_page.status.healthy') }}</el-tag>
                                            <el-tag v-else type="danger" size="small">{{ t('llm_page.status.unhealthy') }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.latency')" width="80" align="right">
                                        <template #default="{ row }">
                                            <span :style="{ color: row.latency_ms > 5000 ? '#e6a23c' : '#67c23a' }">
                                                {{ row.latency_ms ? row.latency_ms + 'ms' : t('llm_page.dash') }}
                                            </span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.health_rate_24h')" width="100" align="right">
                                        <template #default="{ row }">
                                            <el-tag :type="(row.health_rate_24h || 100) >= 99 ? 'success' : (row.health_rate_24h || 100) >= 95 ? 'warning' : 'danger'" size="small">
                                                {{ row.health_rate_24h || 100 }}%
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.avg_latency_24h')" width="110" align="right">
                                        <template #default="{ row }">
                                            {{ row.avg_latency_24h ? row.avg_latency_24h + 'ms' : t('llm_page.dash') }}
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.circuit')" width="60">
                                        <template #default="{ row }">
                                            <el-tag v-if="row.circuit_status === 'open'" type="danger" size="small">{{ t('llm_page.status.circuit_open') }}</el-tag>
                                            <el-tag v-else type="success" size="small">{{ t('llm_page.status.circuit_closed') }}</el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.error')" min-width="140">
                                        <template #default="{ row }">
                                            <span class="error-text">{{ row.last_error || t('llm_page.dash') }}</span>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('llm_page.cols.last_check')" width="160" prop="last_check_at" />
                                </el-table>
                            </el-card>
                        </el-col>
                        <el-col :span="12">
                            <el-card shadow="never" class="section-card">
                                <template #header><span>{{ t('llm_page.health.event_timeline') }}</span></template>
                                <div class="event-timeline" v-if="events.length > 0">
                                    <el-timeline>
                                        <el-timeline-item v-for="evt in events" :key="evt.id"
                                            :type="evt.event_type === 'circuit_closed' || evt.event_type === 'health_recover' ? 'success' : 'danger'"
                                            :timestamp="formatEventTime(evt.created_at)">
                                            <div class="event-item">
                                                <el-tag :type="eventTagType(evt.event_type)" size="small" class="event-tag">
                                                    {{ eventLabel(evt.event_type) }}
                                                </el-tag>
                                                <p class="event-reason">{{ evt.reason || evt.context?.reason || '' }}</p>
                                                <p class="event-meta" v-if="evt.provider?.name">
                                                    {{ evt.provider.name }}
                                                </p>
                                            </div>
                                        </el-timeline-item>
                                    </el-timeline>
                                </div>
                                <el-empty v-else :description="t('llm_page.health.no_events')" />
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <!-- Token 统计 -->
            <el-tab-pane :label="t('llm_page.tabs.stats')" name="stats">
                <el-row :gutter="16" class="stats-row">
                    <el-col :span="6" v-for="s in tokenStatCards" :key="s.label">
                        <el-card shadow="never" class="stat-card">
                            <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                            <div class="stat-label">{{ s.label }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-card shadow="never" class="section-card">
                    <template #header><span>{{ t('llm_page.stats.by_model') }}</span></template>
                    <el-table :data="byModel" v-loading="loadingStats" stripe size="small">
                        <el-table-column :label="t('llm_page.cols.model')" prop="model" />
                        <el-table-column :label="t('llm_page.cols.tokens')" prop="tokens" align="right">
                            <template #default="{ row }">{{ formatNumber(row.tokens) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('llm_page.cols.cost_usd')" prop="cost" align="right" width="140">
                            <template #default="{ row }">${{ row.cost.toFixed(6) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('llm_page.cols.requests')" prop="requests" align="right" width="100" />
                    </el-table>
                </el-card>

                <el-card shadow="never" class="section-card">
                    <template #header><span>{{ t('llm_page.stats.daily_trend') }}</span></template>
                    <div class="daily-chart" v-if="byDay.length > 0">
                        <div class="bar-item" v-for="d in dailyChartData" :key="d.date">
                            <div class="bar-label">{{ d.label }}</div>
                            <div class="bar-track">
                                <div class="bar-fill" :style="{ width: d.pct + '%' }" />
                            </div>
                            <div class="bar-value">{{ formatNumber(d.tokens) }}</div>
                        </div>
                    </div>
                    <el-empty v-else :description="t('messages.no_data')" />
                </el-card>
            </el-tab-pane>

            <!-- 日志 -->
            <el-tab-pane :label="t('llm_page.tabs.logs')" name="logs">
                <div class="filter-bar">
                    <el-select v-model="logFilter.errorsOnly" size="small" style="width: 130px;" @change="loadLogs">
                        <el-option v-for="opt in logFilterOptions" :key="String(opt.value)" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-button size="small" @click="loadLogs" style="margin-left: 8px;">{{ t('llm_page.refresh') }}</el-button>
                </div>
                <el-table :data="logList" v-loading="loadingLogs" stripe size="small" max-height="500">
                    <el-table-column :label="t('llm_page.cols.provider')" width="100" prop="provider_name" />
                    <el-table-column :label="t('llm_page.cols.model')" width="120" prop="model" />
                    <el-table-column :label="t('llm_page.cols.function')" width="80" prop="function" />
                    <el-table-column label="Token" width="100" align="right">
                        <template #default="{ row }">{{ row.total_tokens }}</template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.cost')" width="100" align="right" prop="cost_usd" />
                    <el-table-column :label="t('llm_page.cols.duration')" width="80" align="right" prop="duration_ms">
                        <template #default="{ row }">{{ row.duration_ms }}ms</template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.status')" width="70">
                        <template #default="{ row }">
                            <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                                {{ row.success ? t('llm_page.status.success') : t('llm_page.status.failed') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.error')" min-width="160" prop="error_message" />
                    <el-table-column :label="t('llm_page.cols.time')" width="170" prop="created_at" />
                </el-table>
            </el-tab-pane>

            <!-- ─── 本地大模型部署 (M3-49) ─── -->
            <el-tab-pane :label="t('llm_page.tabs.local')" name="local">
                <el-alert
                  :title="t('llm_page.local.alert')"
                  type="info" show-icon :closable="false" class="mb-3"
                />

                <!-- 实例状态 -->
                <h4 class="mb-2">{{ t('llm_page.local.running_instances') }}</h4>
                <el-row :gutter="16" class="mb-3">
                  <el-col :span="8" v-for="inst in localInstances" :key="inst.id || inst.driver">
                    <el-card shadow="hover" :class="['instance-card', inst.healthy ? 'healthy' : 'unhealthy']">
                      <template #header>
                        <span>{{ inst.name }}</span>
                        <el-tag :type="inst.healthy ? 'success' : 'danger'" size="small" style="float:right">
                          {{ inst.healthy ? t('llm_page.status.healthy') : t('llm_page.status.unhealthy') }}
                        </el-tag>
                      </template>
                      <div class="instance-meta">
                        <div>{{ t('llm_page.local.driver_label') }}: {{ inst.driver }}</div>
                        <div>{{ t('llm_page.local.api_label') }}: <code>{{ inst.api_base }}</code></div>
                        <div>{{ t('llm_page.local.latency_label') }}: {{ inst.latency_ms }}ms</div>
                        <div>{{ t('llm_page.local.model_count') }}: {{ inst.model_count }}</div>
                        <div v-if="inst.default_model">{{ t('llm_page.local.default_model') }}: {{ inst.default_model }}</div>
                      </div>
                    </el-card>
                  </el-col>
                </el-row>

                <!-- GPU 信息 -->
                <el-card shadow="never" class="mb-3" v-if="gpuInfo.available">
                  <template #header><span>{{ t('llm_page.local.gpu_status') }}</span></template>
                  <el-table :data="gpuInfo.gpus || []" stripe size="small">
                    <el-table-column prop="index" :label="t('llm_page.cols.gpu_index')" width="60" />
                    <el-table-column prop="name" :label="t('llm_page.cols.gpu_model')" min-width="200" />
                    <el-table-column :label="t('llm_page.cols.vram')" width="200">
                      <template #default="{ row }">
                        {{ formatMB(row.memory_used_mb) }} / {{ formatMB(row.memory_total_mb) }}
                      </template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.temperature')" width="100">
                      <template #default="{ row }">{{ row.temperature_c }}°C</template>
                    </el-table-column>
                    <el-table-column :label="t('llm_page.cols.utilization')" width="120">
                      <template #default="{ row }">
                        <el-progress :percentage="row.utilization_percent" :stroke-width="12" />
                      </template>
                    </el-table-column>
                  </el-table>
                </el-card>
                <el-alert v-else :title="t('llm_page.local.no_gpu')" type="warning" show-icon :closable="false" class="mb-3" />

                <!-- 模型管理 -->
                <h4 class="mb-2">{{ t('llm_page.local.model_mgmt') }}</h4>
                <div class="section-toolbar mb-2">
                  <el-select v-model="modelToPull" :placeholder="t('llm_page.local.select_model_ph')" style="width:220px">
                    <el-option v-for="opt in modelPullOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                  </el-select>
                  <el-button type="primary" @click="handlePullModel" :loading="pulling" size="small">{{ t('llm_page.pull_model') }}</el-button>
                </div>
                <el-table :data="localModels" stripe size="small" v-if="localModels.length">
                  <el-table-column prop="id" :label="t('llm_page.cols.model_name')" min-width="250" />
                  <el-table-column :label="t('llm_page.cols.size')" width="120">
                    <template #default="{ row }">{{ formatBytes(row.size) }}</template>
                  </el-table-column>
                  <el-table-column :label="t('llm_page.cols.actions')" width="100">
                    <template #default="{ row }">
                      <el-popconfirm :title="t('llm_page.local.delete_confirm')" @confirm="deleteModel(row.id)">
                        <template #reference>
                          <el-button text type="danger" size="small">{{ t('actions.delete') }}</el-button>
                        </template>
                      </el-popconfirm>
                    </template>
                  </el-table-column>
                </el-table>

                <!-- 硬件信息 -->
                <el-card shadow="never" class="mt-3">
                  <template #header><span>{{ t('llm_page.local.hardware_info') }}</span></template>
                  <el-descriptions :column="3" border size="small">
                    <el-descriptions-item :label="t('llm_page.local.cpu')">{{ hardware.cpu || 'N/A' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('llm_page.local.cores')">{{ hardware.cpu_cores || 'N/A' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('llm_page.local.memory')">
                        {{ t('llm_page.local.memory_fmt', { total: hardware.ram_total_gb, available: hardware.ram_available_gb }) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('llm_page.local.disk')">
                        {{ t('llm_page.local.disk_fmt', { total: hardware.disk_total_gb, free: hardware.disk_free_gb }) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('llm_page.local.min_requirements')">
                      <el-tag :type="hardware.meets_minimum ? 'success' : 'danger'" size="small">
                        {{ hardware.meets_minimum ? t('llm_page.status.meets_minimum') : t('llm_page.status.not_meets_minimum') }}
                      </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('llm_page.local.recommended')">
                      {{ t('llm_page.local.recommended_fmt', { ram: hardware.recommended_hardware?.recommended_ram_gb, vram: hardware.recommended_hardware?.recommended_vram_gb }) }}
                    </el-descriptions-item>
                  </el-descriptions>
                </el-card>

                <!-- 部署指引 -->
                <el-card shadow="never" class="mt-3">
                  <template #header><span>{{ t('llm_page.local.deploy_guide') }}</span></template>
                  <el-steps direction="vertical" :active="-1" v-if="deployGuide.ollama">
                    <el-step v-for="(s, i) in deployGuide.ollama.steps" :key="i" :title="s.action" :description="s.command" />
                  </el-steps>
                  <el-divider />
                  <el-button size="small" @click="refreshLocalData" :loading="localLoading">
                    <el-icon><Refresh /></el-icon> {{ t('llm_page.refresh_status') }}
                  </el-button>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { ChatLineSquare } from '@element-plus/icons-vue';
import llmApi from '@/api/llm';
import llmFallbackApi from '@/api/llmFallback';
import localLlmApi from '@/api/localLLM';
import { Refresh } from '@element-plus/icons-vue';

const { t, locale } = useI18n();

const activeTab = ref('providers');
const loadingProviders = ref(false);
const savingProvider = ref(false);
const testingId = ref(null);

const providers = ref([]);
const editDialogVisible = ref(false);
const isCreating = ref(false);
const editForm = reactive({
    id: null, name: '', driver: '', api_base: '', api_key: '',
    default_model: '', is_active: true, is_fallback: false,
    config: { temperature: 0.7, max_tokens: 4096 },
});

const editDialogTitle = computed(() =>
    isCreating.value
        ? t('llm_page.providers.create_title')
        : t('llm_page.providers.edit_title', { name: editForm.name }),
);

const driverOptions = computed(() => [
    { label: t('llm_page.drivers.openai'), value: 'openai' },
    { label: t('llm_page.drivers.deepseek'), value: 'deepseek' },
    { label: t('llm_page.drivers.ollama'), value: 'ollama' },
    { label: t('llm_page.drivers.vllm'), value: 'vllm' },
    { label: t('llm_page.drivers.claude'), value: 'claude' },
]);

const logFilterOptions = computed(() => [
    { label: t('llm_page.logs.all'), value: false },
    { label: t('llm_page.logs.errors_only'), value: true },
]);

const modelPullOptions = computed(() => [
    { label: t('llm_page.models.qwen2_7b'), value: 'qwen2:7b' },
    { label: t('llm_page.models.qwen2_72b'), value: 'qwen2:72b' },
    { label: t('llm_page.models.deepseek_r1_7b'), value: 'deepseek-r1:7b' },
    { label: t('llm_page.models.deepseek_r1_14b'), value: 'deepseek-r1:14b' },
    { label: t('llm_page.models.llama3'), value: 'llama3' },
    { label: t('llm_page.models.mistral'), value: 'mistral' },
    { label: t('llm_page.models.nomic_embed'), value: 'nomic-embed-text' },
]);

// Chat
const chatInput = ref('');
const chatMessages = ref([]);
const chatProvider = ref('deepseek');
const chatModel = ref('deepseek-chat');
const streaming = ref(false);
const streamContent = ref('');
const chatRef = ref(null);

const activeProviders = computed(() => providers.value.filter(p => p.is_active));
const availableModels = computed(() => {
    const p = providers.value.find(p => p.slug === chatProvider.value);
    return p?.models || [];
});

    // Stats
const loadingStats = ref(false);
const tokenStats = reactive({
    total_tokens: 0, total_cost_usd: 0, total_requests: 0,
    total_prompt_tokens: 0, total_completion_tokens: 0,
});
const byModel = ref([]);
const byDay = ref([]);

const tokenStatCards = computed(() => [
    { label: t('llm_page.stats.total_tokens'), value: formatNumber(tokenStats.total_tokens), color: '#0f172a' },
    { label: t('llm_page.stats.total_cost'), value: '$' + (tokenStats.total_cost_usd || 0).toFixed(6), color: '#F56C6C' },
    { label: t('llm_page.stats.total_requests'), value: formatNumber(tokenStats.total_requests), color: '#67C23A' },
    { label: t('llm_page.stats.prompt_completion'), value: formatNumber(tokenStats.total_prompt_tokens) + ' / ' + formatNumber(tokenStats.total_completion_tokens), color: '#E6A23C' },
]);

const dailyChartData = computed(() => {
    if (byDay.value.length === 0) return [];
    const maxTokens = Math.max(...byDay.value.map(d => d.tokens || 0));
    return byDay.value.map(d => ({
        date: d.date,
        label: d.date?.slice(5) || '',
        tokens: d.tokens || 0,
        pct: maxTokens > 0 ? ((d.tokens || 0) / maxTokens) * 100 : 0,
    }));
});

// Logs
const logList = ref([]);
const loadingLogs = ref(false);
const logFilter = reactive({ errorsOnly: false });

function formatNumber(n) {
    if (!n) return '0';
    return n.toLocaleString();
}

function renderMarkdown(text) {
    if (!text) return '';
    const escaped = text.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return escaped
        .replace(/```(\w*)\n?([\s\S]*?)```/g, '<pre><code>$2</code></pre>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}

async function loadProviders() {
    loadingProviders.value = true;
    try {
        const { data: res } = await llmApi.providers();
        if (res.success) providers.value = res.data || [];
    } finally {
        loadingProviders.value = false;
    }
}

function openEditDialog(provider) {
    isCreating.value = false;
    Object.assign(editForm, {
        id: provider.id,
        name: provider.name,
        driver: provider.driver || '',
        api_base: provider.api_base || '',
        api_key: '',
        default_model: provider.default_model || '',
        is_active: provider.is_active,
        is_fallback: provider.is_fallback,
        config: {
            temperature: provider.config?.temperature ?? 0.7,
            max_tokens: provider.config?.max_tokens ?? 4096,
        },
    });
    editDialogVisible.value = true;
}

function openCreateDialog() {
    isCreating.value = true;
    Object.assign(editForm, {
        id: null, name: '', driver: 'openai', api_base: '', api_key: '',
        default_model: '', is_active: true, is_fallback: false,
        config: { temperature: 0.7, max_tokens: 4096 },
    });
    editDialogVisible.value = true;
}

async function handleSaveProvider() {
    savingProvider.value = true;
    try {
        const data = {
            name: editForm.name,
            api_base: editForm.api_base,
            default_model: editForm.default_model,
            is_active: editForm.is_active,
            is_fallback: editForm.is_fallback,
            config: {
                temperature: editForm.config.temperature,
                max_tokens: editForm.config.max_tokens,
            },
        };
        if (editForm.api_key) data.api_key = editForm.api_key;

        if (isCreating.value) {
            data.driver = editForm.driver;
            const { data: res } = await llmApi.createProvider(data);
            if (res.success) {
                ElMessage.success(t('llm_page.messages.provider_created'));
            }
        } else {
            const { data: res } = await llmApi.updateProvider(editForm.id, data);
            if (res.success) {
                ElMessage.success(t('llm_page.messages.config_saved'));
            }
        }
        editDialogVisible.value = false;
        await loadProviders();
    } catch {
        ElMessage.error(isCreating.value ? t('llm_page.messages.create_failed') : t('llm_page.messages.save_failed'));
    } finally {
        savingProvider.value = false;
    }
}

async function handleTest(provider) {
    testingId.value = provider.id;
    try {
        const { data: res } = await llmApi.testConnection(provider.id);
        if (res.success) {
            ElMessage.success(res.data?.message || t('llm_page.messages.connection_ok'));
        } else {
            ElMessage.warning(res.message || t('llm_page.messages.connection_abnormal'));
        }
    } catch {
        ElMessage.error(t('llm_page.messages.test_failed'));
    } finally {
        testingId.value = null;
    }
}

async function sendChat() {
    const text = chatInput.value.trim();
    if (!text || streaming.value) return;

    chatMessages.value.push({ role: 'user', content: text });
    chatInput.value = '';

    streaming.value = true;
    streamContent.value = '';
    const msgIndex = chatMessages.value.length;

    chatMessages.value.push({ role: 'assistant', content: '', usage: null });

    try {
        const { data: res } = await llmApi.chat([
            { role: 'system', content: t('llm_page.chat.system_prompt') },
            ...chatMessages.value.slice(0, msgIndex).map(m => ({ role: m.role, content: m.content })),
        ], {
            provider: chatProvider.value,
            model: chatModel.value,
        });

        if (res.success) {
            chatMessages.value[msgIndex].content = res.data?.content || '';
            chatMessages.value[msgIndex].usage = res.data?.usage || null;
        } else {
            chatMessages.value[msgIndex].content = t('llm_page.chat.error_prefix') + (res.message || t('llm_page.chat.request_failed'));
        }
    } catch (e) {
        chatMessages.value[msgIndex].content = t('llm_page.chat.exception_prefix') + (e.message || t('llm_page.chat.unknown_error'));
    } finally {
        streaming.value = false;
        scrollToBottom();
    }
}

function clearChat() {
    chatMessages.value = [];
    streamContent.value = '';
}

function scrollToBottom() {
    nextTick(() => {
        if (chatRef.value) {
            chatRef.value.scrollTop = chatRef.value.scrollHeight;
        }
    });
}

async function loadStats() {
    loadingStats.value = true;
    try {
        const { data: res } = await llmApi.tokenStats(30);
        if (res.success) {
            Object.assign(tokenStats, res.data);
            byModel.value = res.data?.by_model || [];
            byDay.value = res.data?.by_day || [];
        }
    } finally {
        loadingStats.value = false;
    }
}

async function loadLogs() {
    loadingLogs.value = true;
    try {
        const params = { per_page: 50 };
        if (logFilter.errorsOnly) params.errors_only = true;
        const { data: res } = await llmApi.logs(params);
        if (res.success) logList.value = res.data?.data || [];
    } finally {
        loadingLogs.value = false;
    }
}

// ── Fallback 管理 ──
const fallbackStatus = ref(null);
const loadingFallback = ref(false);
const resettingFallback = ref(false);

// ── 健康监控 ──
const healthData = ref([]);
const loadingHealth = ref(false);
const checkingHealth = ref(false);
const events = ref([]);

const eventLabels = computed(() => ({
    circuit_opened: t('llm_page.events.circuit_opened'),
    circuit_closed: t('llm_page.events.circuit_closed'),
    provider_switch: t('llm_page.events.provider_switch'),
    health_fail: t('llm_page.events.health_fail'),
    health_recover: t('llm_page.events.health_recover'),
    all_down: t('llm_page.events.all_down'),
}));

async function loadHealthStatus() {
    loadingHealth.value = true;
    try {
        const { data: res } = await llmApi.healthStatus();
        if (res.success) healthData.value = res.data || [];
    } catch { /* ignore */ }
    finally { loadingHealth.value = false; }
}

async function loadFallbackEvents() {
    try {
        const { data: res } = await llmApi.fallbackEvents();
        if (res.success) events.value = res.data || [];
    } catch { /* ignore */ }
}

async function runHealthCheck() {
    checkingHealth.value = true;
    try {
        const { data: res } = await llmApi.runHealthCheck();
        if (res.success) {
            ElMessage.success(t('llm_page.messages.health_check_done', {
                healthy: res.data.healthy_count,
                total: res.data.total_count,
            }));
            await loadHealthStatus();
            await loadFallbackEvents();
        }
    } catch { ElMessage.error(t('llm_page.messages.health_check_failed')); }
    finally { checkingHealth.value = false; }
}

function eventTagType(type) {
    return ['circuit_closed', 'health_recover'].includes(type) ? 'success' : 'warning';
}

function eventLabel(type) {
    return eventLabels.value[type] || type;
}

function formatEventTime(time) {
    if (!time) return '';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(time).toLocaleString(loc);
}

async function loadFallbackStatus() {
    loadingFallback.value = true;
    try {
        const { data: res } = await llmFallbackApi.status();
        if (res.success) fallbackStatus.value = res.data;
    } catch {
        // ignore
    } finally {
        loadingFallback.value = false;
    }
}

async function handleResetFallback() {
    resettingFallback.value = true;
    try {
        const { data: res } = await llmFallbackApi.reset();
        if (res.success) {
            ElMessage.success(t('llm_page.messages.fallback_reset'));
            await loadFallbackStatus();
        } else {
            ElMessage.warning(res.message || t('llm_page.messages.reset_failed'));
        }
    } catch {
        ElMessage.error(t('llm_page.messages.reset_request_failed'));
    } finally {
        resettingFallback.value = false;
    }
}

onMounted(() => {
    loadProviders();
    loadStats();
    loadLogs();
    loadFallbackStatus();
    loadHealthStatus();
    loadFallbackEvents();
    loadLocalData();
});

// ─── 本地大模型部署 (M3-49) ───
const localLoading = ref(false);
const localInstances = ref([]);
const gpuInfo = ref({ available: false });
const hardware = ref({});
const deployGuide = ref({});
const localModels = ref([]);
const modelToPull = ref('qwen2:7b');
const pulling = ref(false);

function formatMB(mb) {
  if (!mb) return '0 MB';
  if (mb > 1024) return (mb / 1024).toFixed(1) + ' GB';
  return mb + ' MB';
}

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

async function loadLocalData() {
  localLoading.value = true;
  try {
    const [statusRes, hwRes, guideRes] = await Promise.all([
      localLlmApi.getStatus(),
      localLlmApi.getHardwareInfo(),
      localLlmApi.getDeploymentGuide(),
    ]);

    if (statusRes.success) {
      localInstances.value = statusRes.data.instances || [];
      gpuInfo.value = statusRes.data.gpu_info || { available: false };
      const allModels = [];
      statusRes.data.instances.forEach(inst => {
        if (inst.models) allModels.push(...inst.models);
      });
      localModels.value = allModels;
    }
    if (hwRes.success) hardware.value = hwRes.data;
    if (guideRes.success) deployGuide.value = guideRes.data;
  } catch {
    // silent
  } finally {
    localLoading.value = false;
  }
}

async function refreshLocalData() {
  await loadLocalData();
  ElMessage.success(t('llm_page.messages.local_refreshed'));
}

async function handlePullModel() {
  if (!modelToPull.value) { ElMessage.warning(t('llm_page.messages.select_model')); return; }
  pulling.value = true;
  try {
    const { data } = await localLlmApi.pullModel(modelToPull.value);
    if (data.success) {
      ElMessage.success(data.message);
      await loadLocalData();
    } else {
      ElMessage.error(data.message);
    }
  } catch {
    ElMessage.error(t('llm_page.messages.pull_failed'));
  } finally {
    pulling.value = false;
  }
}

async function deleteModel(modelName) {
  try {
    const { data } = await localLlmApi.deleteModel(modelName);
    if (data.success) {
      ElMessage.success(data.message);
      await loadLocalData();
    }
  } catch {
    ElMessage.error(t('llm_page.messages.delete_failed'));
  }
}
</script>

<style scoped>
.llm-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}
.alert-bar { margin-bottom: 16px; }
.tabs-container { }

/* Chat */
.chat-container {
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 8px;
    overflow: hidden;
}
.chat-messages {
    height: 420px;
    overflow-y: auto;
    padding: 16px;
    background: var(--el-bg-color-page);
}
.chat-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: var(--el-text-color-placeholder);
}
.chat-empty p { margin-top: 12px; }
.chat-msg {
    margin-bottom: 16px;
    max-width: 80%;
}
.chat-msg.user {
    margin-left: auto;
    text-align: right;
}
.chat-msg.assistant {
    margin-right: auto;
}
.msg-role {
    font-size: 12px;
    font-weight: 600;
    color: var(--el-text-color-secondary);
    margin-bottom: 4px;
}
.msg-content {
    background: var(--el-bg-color);
    border-radius: 8px;
    padding: 10px 14px;
    line-height: 1.6;
    font-size: 14px;
    text-align: left;
    white-space: pre-wrap;
    word-break: break-word;
}
.chat-msg.user .msg-content {
    background: var(--el-color-primary-light-9);
}
.msg-usage {
    font-size: 11px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}
.cursor-blink {
    animation: blink 1s step-end infinite;
}
@keyframes blink {
    50% { opacity: 0; }
}
.chat-input-bar {
    display: flex;
    align-items: center;
    padding: 10px 12px;
    border-top: 1px solid var(--el-border-color-lighter);
    background: var(--el-bg-color);
}
.chat-input-bar .el-input { flex: 1; }

/* Stats */
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 20px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.section-card { margin-bottom: 16px; }

/* Logs */
.filter-bar {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
/* 每日趋势图 */
.daily-chart {
    max-height: 300px;
    overflow-y: auto;
}
.bar-item {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
    font-size: 12px;
}
.bar-label {
    width: 50px;
    color: var(--el-text-color-secondary);
    text-align: right;
    margin-right: 8px;
    flex-shrink: 0;
}
.bar-track {
    flex: 1;
    height: 16px;
    background: var(--el-fill-color-light);
    border-radius: 8px;
    overflow: hidden;
}
.bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #0f172a, #94a3b8);
    border-radius: 8px;
    transition: width 0.3s ease;
}
.bar-value {
    width: 80px;
    text-align: right;
    margin-left: 8px;
    color: var(--el-text-color-regular);
    flex-shrink: 0;
}
:deep(.el-card__body) { padding: 16px; }

/* Fallback panel */
.fallback-panel { }
.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.action-bar {
    display: flex;
    align-items: center;
    gap: 8px;
}
.tip-text {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-left: 8px;
}
.empty-data {
    padding: 20px 0;
}

/* Health monitoring */
.health-panel {}
.event-timeline { max-height: 480px; overflow-y: auto; }
.event-item { font-size: 13px; }
.event-tag { margin-bottom: 4px; }
.event-reason { margin: 4px 0; color: #606266; }
.event-meta { margin: 0; font-size: 12px; color: #909399; }
.error-text { font-size: 12px; color: #909399; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
</style>
