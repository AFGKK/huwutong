<template>
    <div class="alert-center-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('alert_center.title') }}</h2>
                <span class="header-subtitle">{{ t('alert_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="mainTab" type="border-card" @tab-change="onMainTabChange">
            <!-- ===== Tab 1: 智能告警 ===== -->
            <el-tab-pane :label="t('alert_center.tab_alerts')" name="alerts">
                <div class="tab-header">
                    <el-button @click="a1EvaluateRules" :loading="a1Evaluating" type="warning"><el-icon><Refresh /></el-icon> {{ t('alert_page.evaluate_rules') }}</el-button>
                    <el-button @click="a1RefreshAll" :loading="a1Loading" type="primary"><el-icon><Refresh /></el-icon> {{ t('alert_page.refresh') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="4"><el-card shadow="hover" class="stat-card stat-danger"><div class="stat-value">{{ a1Dash.firing_count }}</div><div class="stat-label">{{ t('alert_page.stats.firing') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card stat-warning"><div class="stat-value">{{ a1Dash.acknowledged_count }}</div><div class="stat-label">{{ t('alert_page.stats.acknowledged') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card stat-success"><div class="stat-value">{{ a1Dash.resolved_count }}</div><div class="stat-label">{{ t('alert_page.stats.resolved') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card"><div class="stat-value">{{ a1Dash.total_today }}</div><div class="stat-label">{{ t('alert_page.stats.today') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card"><div class="stat-value">{{ a1Dash.active_rules }}/{{ a1Dash.total_rules }}</div><div class="stat-label">{{ t('alert_page.stats.active_rules') }}</div></el-card></el-col>
                </el-row>
                <el-card shadow="never"><el-tabs v-model="a1SubTab" type="card">
                    <el-tab-pane :label="t('alert_page.tabs.events')" name="events">
                        <el-card class="mb-3"><el-form :inline="true" :model="a1EventFilters">
                            <el-form-item :label="t('alert_page.cols.status')"><el-select v-model="a1EventFilters.status" :placeholder="t('alert_page.filters.all')" clearable style="width:140px"><el-option v-for="opt in a1EventStOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                            <el-form-item :label="t('alert_page.cols.severity')"><el-select v-model="a1EventFilters.severity" :placeholder="t('alert_page.filters.all')" clearable style="width:140px"><el-option v-for="opt in a1SevOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                            <el-form-item><el-button type="primary" @click="a1FetchEvents(1)">{{ t('actions.search') }}</el-button></el-form-item>
                        </el-form></el-card>
                        <el-table :data="a1Events" stripe @row-click="a1ShowDetail">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column :label="t('alert_page.cols.severity')" width="90"><template #default="{ row }"><el-tag :type="a1SevTag(row.severity)" size="small" effect="dark">{{ a1SevLbl(row.severity) }}</el-tag></template></el-table-column>
                            <el-table-column prop="title" :label="t('alert_page.cols.title')" min-width="200" show-overflow-tooltip />
                            <el-table-column :label="t('alert_page.cols.rule')" width="160"><template #default="{ row }">{{ row.rule?.name || t('alert_page.manual_trigger') }}</template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.status')" width="100"><template #default="{ row }"><el-tag :type="a1StTag(row.status)" size="small">{{ a1StLbl(row.status) }}</el-tag></template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.fired_at')" width="170" sortable><template #default="{ row }">{{ a1FmtDate(row.fired_at) }}</template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.actions')" width="180"><template #default="{ row }"><el-button v-if="row.status === 'firing'" size="small" @click.stop="a1Ack(row)">{{ t('actions.confirm') }}</el-button><el-button v-if="row.status !== 'resolved'" size="small" type="success" @click.stop="a1Resolve(row)">{{ t('alert_page.resolve') }}</el-button></template></el-table-column>
                        </el-table>
                        <div class="pagination-wrap"><el-pagination :current-page="a1EventPg" :total="a1EventTotal" :page-size="20" layout="total,prev,pager,next" @current-change="a1FetchEvents" /></div>
                    </el-tab-pane>
                    <el-tab-pane :label="t('alert_page.tabs.rules')" name="rules">
                        <div class="mb-3"><el-button type="primary" @click="a1OpenRule()"><el-icon><Plus /></el-icon> {{ t('alert_page.create_rule') }}</el-button></div>
                        <el-table :data="a1Rules" stripe>
                            <el-table-column type="index" label="#" width="50" /><el-table-column prop="name" :label="t('alert_page.cols.name')" min-width="180" />
                            <el-table-column prop="slug" :label="t('alert_page.cols.slug')" width="120"><template #default="{ row }"><code>{{ row.slug }}</code></template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.metric_type')" width="140"><template #default="{ row }">{{ a1Meta.metric_types?.[row.metric_type] || row.metric_type }}</template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.condition')" width="150"><template #default="{ row }">{{ row.condition_operator }} {{ row.threshold }}{{ row.metric_type === 'apm_slow' ? 'ms' : '' }}</template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.severity')" width="90"><template #default="{ row }"><el-tag :type="a1SevTag(row.severity)" size="small">{{ a1SevLbl(row.severity) }}</el-tag></template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.cooldown')" width="80"><template #default="{ row }">{{ row.cooldown_minutes }}{{ t('alert_page.minutes_abbr') }}</template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.daily_limit')" width="80"><template #default="{ row }">{{ row.max_alert_per_day }}</template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.status')" width="80"><template #default="{ row }"><el-switch v-model="row.is_active" @change="a1ToggleRule(row)" /></template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.actions')" width="140"><template #default="{ row }"><el-button size="small" @click="a1OpenRule(row)">{{ t('actions.edit') }}</el-button><el-popconfirm :title="t('alert_page.delete_confirm')" @confirm="a1DeleteRule(row)"><template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template></el-popconfirm></template></el-table-column>
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t('alert_page.tabs.integrations')" name="integrations">
                        <div class="mb-3"><el-button type="primary" @click="a1OpenIntegration()"><el-icon><Plus /></el-icon> {{ t('alert_page.create_integration') }}</el-button></div>
                        <el-table :data="a1Ints" stripe>
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="name" :label="t('alert_page.cols.name')" min-width="180" />
                            <el-table-column :label="t('alert_page.cols.type')" width="120"><template #default="{ row }"><el-tag>{{ a1IntTypeLbl(row.type) }}</el-tag></template></el-table-column>
                            <el-table-column prop="webhook_url" :label="t('alert_page.cols.webhook_url')" min-width="240" show-overflow-tooltip />
                            <el-table-column :label="t('alert_page.cols.status')" width="80"><template #default="{ row }"><el-switch v-model="row.is_active" @change="a1ToggleInt(row)" /></template></el-table-column>
                            <el-table-column :label="t('alert_page.cols.actions')" width="200"><template #default="{ row }"><el-button size="small" @click="a1TestInt(row)">{{ t('alert_page.test') }}</el-button><el-button size="small" @click="a1OpenIntegration(row)">{{ t('actions.edit') }}</el-button><el-popconfirm :title="t('alert_page.delete_confirm')" @confirm="a1DeleteInt(row)"><template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template></el-popconfirm></template></el-table-column>
                        </el-table>
                    </el-tab-pane>
                </el-tabs></el-card>
            </el-tab-pane>

            <!-- ===== Tab 2: 告警中心 ===== -->
            <el-tab-pane :label="t('alert_center.tab_alerting')" name="alerting">
                <el-row :gutter="20" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover"><div class="s-card"><div class="s-val">{{ a2Stats.active_rules }}</div><div class="s-lbl">{{ t('alerting_page.stats.active_rules') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="s-card"><div class="s-val text-danger">{{ a2Stats.firing_events }}</div><div class="s-lbl">{{ t('alerting_page.stats.firing_events') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="s-card"><div class="s-val">{{ a2Stats.today_events }}</div><div class="s-lbl">{{ t('alerting_page.stats.today_events') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="s-card"><div class="s-val text-success">{{ a2Stats.active_channels }}</div><div class="s-lbl">{{ t('alerting_page.stats.active_channels') }}</div></div></el-card></el-col>
                </el-row>
                <el-row :gutter="20" class="mb-4">
                    <el-col :span="8"><el-card shadow="hover"><template #header>{{ t('alerting_page.severity_distribution') }}</template><div v-if="Object.keys(a2Stats.by_severity || {}).length" class="flex flex-col gap-2"><div v-for="(cnt, sev) in a2Stats.by_severity" :key="sev" class="flex items-center justify-between"><el-tag :type="a2SevTag(sev)" size="small">{{ a2SevLbl(sev) }}</el-tag><span class="font-bold">{{ cnt }}</span></div></div><el-empty v-else :description="t('messages.no_data')" :image-size="60" /></el-card></el-col>
                    <el-col :span="16"><el-card shadow="hover"><template #header>{{ t('alerting_page.recent_events') }}</template><el-table :data="a2Stats.recent_events || []" stripe size="small" v-if="a2Stats.recent_events?.length" style="cursor:pointer" @row-click="a2ShowEvent"><el-table-column :label="t('alerting_page.cols.title')" prop="title" min-width="200" show-overflow-tooltip /><el-table-column :label="t('alerting_page.cols.rule')" prop="rule?.name" width="140" /><el-table-column :label="t('alerting_page.cols.severity')" prop="severity" width="90"><template #default="{ row }"><el-tag :type="a2SevTag(row.severity)" size="small">{{ a2SevLbl(row.severity) }}</el-tag></template></el-table-column><el-table-column :label="t('alerting_page.cols.status')" prop="status" width="80"><template #default="{ row }"><el-tag :type="a2StTag(row.status)" size="small">{{ a2StLbl(row.status) }}</el-tag></template></el-table-column><el-table-column :label="t('alerting_page.cols.fired_at')" prop="fired_at" width="160" /></el-table><el-empty v-else :description="t('alerting_page.no_events')" :image-size="60" /></el-card></el-col>
                </el-row>
                <el-card shadow="never"><el-tabs v-model="a2SubTab" type="card">
                    <el-tab-pane :label="t('alerting_page.tabs.rules')" name="rules"><RulePanel @edit="a2EditRule" /></el-tab-pane>
                    <el-tab-pane :label="t('alerting_page.tabs.channels')" name="channels"><ChannelPanel /></el-tab-pane>
                    <el-tab-pane :label="t('alerting_page.tabs.escalations')" name="escalations"><EscalationPanel /></el-tab-pane>
                    <el-tab-pane :label="t('alerting_page.tabs.events')" name="events"><EventPanel @detail="a2ShowEvent" /></el-tab-pane>
                </el-tabs></el-card>
            </el-tab-pane>

            <!-- ===== Tab 3: 告警管理 ===== -->
            <el-tab-pane :label="t('alert_center.tab_manager')" name="manager">
                <div class="tab-header">
                    <el-button @click="a3LoadAll" :loading="a3Loading" :icon="Refresh">{{ t('alert_manager_page.refresh') }}</el-button>
                    <el-button type="primary" @click="a3RunAggregate" :loading="a3AggLoading" :icon="DataBoard">{{ t('alert_manager_page.run_aggregate') }}</el-button>
                    <el-button type="warning" @click="a3RunDowngrade" :loading="a3DowngradeLoading">{{ t('alert_manager_page.auto_downgrade') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4">
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alert_manager_page.stats.total_rules') }}</div><div class="m-val">{{ a3Dash.total_rules }}<small class="text-muted">/{{ a3Dash.active_rules }}</small></div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alerting_page.stats.firing_events') }}</div><div class="m-val danger">{{ a3Dash.firing_events }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alerting_page.status.acknowledged') }}</div><div class="m-val warning">{{ a3Dash.acknowledged }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alert_manager_page.stats.resolved_today') }}</div><div class="m-val success">{{ a3Dash.resolved_today }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alert_manager_page.stats.active_silences') }}</div><div class="m-val">{{ a3Dash.active_silences }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alert_manager_page.stats.aggregated_events') }}</div><div class="m-val">{{ a3Dash.aggregated_events }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alert_manager_page.stats.fatigue_settings') }}</div><div class="m-val">{{ a3Dash.fatigue_settings }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alerting_page.severity_distribution') }}</div><div class="m-val" style="font-size:14px">{{ a3SevText }}</div></el-card></el-col>
                </el-row>
                <el-card shadow="never"><el-tabs v-model="a3SubTab" type="card">
                    <el-tab-pane :label="t('alert_manager_page.tabs.aggregation')" name="aggregation">
                        <el-table :data="a3AggGroups" stripe v-loading="a3AggLoading" size="small">
                            <el-table-column prop="group_key" :label="t('alert_manager_page.cols.group_key')" min-width="180" /><el-table-column :label="t('alert_manager_page.cols.parent_events')" width="80" prop="parent_count" /><el-table-column :label="t('alert_manager_page.cols.child_events')" width="80" prop="total_children" /><el-table-column :label="t('alerting_page.cols.rule')" width="120"><template #default="{row}">{{ row.sample_parent?.rule_name || '—' }}</template></el-table-column><el-table-column :label="t('alert_manager_page.cols.metric_type')" width="100"><template #default="{row}">{{ row.sample_parent?.metric_type || '—' }}</template></el-table-column><el-table-column :label="t('alerting_page.cols.severity')" width="70"><template #default="{row}"><el-tag :type="a3SevTag(row.sample_parent?.severity)" size="small">{{ a3SevLbl(row.sample_parent?.severity) }}</el-tag></template></el-table-column><el-table-column :label="t('alerting_page.cols.status')" width="70"><template #default="{row}">{{ row.sample_parent?.status }}</template></el-table-column><el-table-column :label="t('alert_manager_page.cols.actions')" width="100"><template #default="{row}"><el-button size="small" @click="a3ViewAggGroup(row.group_key)">{{ t('alert_manager_page.view_children') }}</el-button></template></el-table-column>
                        </el-table>
                        <el-empty v-if="!a3AggGroups.length && !a3AggLoading" :description="t('alert_manager_page.empty.aggregation')" />
                    </el-tab-pane>
                    <el-tab-pane :label="t('alert_manager_page.tabs.silence')" name="silence">
                        <div class="flex justify-between mb-3"><span>{{ t('alert_manager_page.section_silence_rules') }}</span><el-button size="small" type="primary" @click="a3ShowSilence=true">+ {{ t('alert_manager_page.new_silence') }}</el-button></div>
                        <el-table :data="a3SilenceRules" stripe v-loading="a3SilenceLoading" size="small">
                            <el-table-column prop="name" :label="t('alert_manager_page.cols.name')" min-width="140" /><el-table-column prop="match_type" :label="t('alert_manager_page.cols.match_type')" width="100"><template #default="{ row }">{{ a3MatchTypeLbl(row.match_type) }}</template></el-table-column><el-table-column :label="t('alert_manager_page.cols.conditions')" min-width="160"><template #default="{row}"><code style="font-size:11px">{{ JSON.stringify(row.match_rules) }}</code></template></el-table-column><el-table-column :label="t('alert_manager_page.cols.starts_at')" width="140"><template #default="{row}">{{ a3FmtTime(row.starts_at) }}</template></el-table-column><el-table-column :label="t('alert_manager_page.cols.ends_at')" width="140"><template #default="{row}">{{ a3FmtTime(row.ends_at) }}</template></el-table-column><el-table-column :label="t('alert_manager_page.cols.active')" width="60"><template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template></el-table-column><el-table-column :label="t('alert_manager_page.cols.reason')" min-width="120" show-overflow-tooltip prop="reason" /><el-table-column :label="t('alert_manager_page.cols.actions')" width="140" fixed="right"><template #default="{row}"><el-button size="small" @click="a3ToggleSilence(row)">{{ row.is_active ? t('actions.disable') : t('actions.enable') }}</el-button><el-button size="small" type="danger" @click="a3DeleteSilence(row)">{{ t('actions.delete') }}</el-button></template></el-table-column>
                        </el-table>
                        <el-empty v-if="!a3SilenceRules.length && !a3SilenceLoading" :description="t('alert_manager_page.empty.silence')" />
                    </el-tab-pane>
                    <el-tab-pane :label="t('alert_manager_page.tabs.fatigue')" name="fatigue">
                        <el-button @click="a3LoadFatigue" :loading="a3FatigueLoading">{{ t('alert_manager_page.refresh_settings') }}</el-button><el-button type="warning" style="margin-left:8px" @click="a3RunDowngrade" :loading="a3DowngradeLoading">{{ t('alert_manager_page.run_auto_downgrade') }}</el-button>
                        <el-table :data="a3FatigueSettings" stripe size="small" class="mt-3" v-if="a3FatigueSettings.length">
                            <el-table-column prop="source_type" :label="t('alert_manager_page.cols.source_type')" width="150" /><el-table-column prop="repetition_threshold" :label="t('alert_manager_page.cols.repetition_threshold')" width="100" /><el-table-column prop="decay_factor" :label="t('alert_manager_page.cols.decay_factor')" width="100" /><el-table-column prop="auto_downgrade" :label="t('alert_manager_page.cols.auto_downgrade')" width="80"><template #default="{row}"><el-tag :type="row.auto_downgrade ? 'success' : 'info'" size="small">{{ row.auto_downgrade ? t('alert_manager_page.yes') : t('alert_manager_page.no') }}</el-tag></template></el-table-column><el-table-column prop="target_severity" :label="t('alert_manager_page.cols.target_severity')" width="100" /><el-table-column :label="t('alert_manager_page.cols.actions')" width="120" fixed="right"><template #default="{row}"><el-button size="small" type="danger" @click="a3DeleteFatigue(row)">{{ t('actions.delete') }}</el-button></template></el-table-column>
                        </el-table>
                        <el-empty v-if="!a3FatigueSettings.length && !a3FatigueLoading" :description="t('alert_manager_page.empty.fatigue')" />
                    </el-tab-pane>
                    <el-tab-pane :label="t('alert_manager_page.tabs.noise')" name="noise">
                        <div class="mb-3"><el-radio-group v-model="a3NoiseDays" size="small" @change="a3LoadNoise"><el-radio-button :value="1">{{ t('alert_manager_page.noise_days.d1') }}</el-radio-button><el-radio-button :value="7">{{ t('alert_manager_page.noise_days.d7') }}</el-radio-button><el-radio-button :value="30">{{ t('alert_manager_page.noise_days.d30') }}</el-radio-button></el-radio-group><span class="ml-3 text-muted">{{ t('alert_manager_page.noisy_rules_count', { n: a3NoiseData.total_noisy_rules }) }}</span></div>
                        <el-table :data="a3NoiseData.rules" stripe v-loading="a3NoiseLoading" size="small">
                            <el-table-column prop="rule_name" :label="t('alerting_page.cols.rule')" min-width="160" /><el-table-column prop="metric_type" :label="t('alert_manager_page.cols.metric_type')" width="100" /><el-table-column prop="total_events" :label="t('alert_manager_page.cols.event_count')" width="80" /><el-table-column :label="t('alert_manager_page.cols.noise_score')" width="110"><template #default="{row}"><el-progress :percentage="Math.min(row.noise_score, 100)" :status="row.is_noisy ? 'exception' : 'success'" :stroke-width="12" /></template></el-table-column><el-table-column :label="t('alert_manager_page.cols.is_noisy')" width="80"><template #default="{row}"><el-tag :type="row.is_noisy ? 'danger' : 'success'" size="small">{{ row.is_noisy ? t('alert_manager_page.yes') : t('alert_manager_page.no') }}</el-tag></template></el-table-column><el-table-column :label="t('alert_manager_page.cols.suggestion')" min-width="200" prop="suggested_action" />
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t('alert_manager_page.tabs.digest')" name="digest">
                        <el-button @click="a3LoadDigest" :loading="a3DigestLoading" class="mb-3">{{ t('alert_manager_page.generate_digest') }}</el-button>
                        <div v-if="a3DigestData.total !== undefined">
                            <el-row :gutter="16" class="mb-3">
                                <el-col :span="6"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alert_manager_page.total_events') }}</div><div class="m-val">{{ a3DigestData.total }}</div></el-card></el-col>
                                <el-col :span="6"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alerting_page.severity.critical') }}</div><div class="m-val danger">{{ a3DigestData.critical }}</div></el-card></el-col>
                                <el-col :span="6"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alerting_page.severity.warning') }}</div><div class="m-val warning">{{ a3DigestData.warning }}</div></el-card></el-col>
                                <el-col :span="6"><el-card shadow="hover" class="m-card"><div class="m-lbl">{{ t('alerting_page.severity.info') }}</div><div class="m-val">{{ a3DigestData.info }}</div></el-card></el-col>
                            </el-row>
                            <el-table :data="a3DigestData.events" stripe size="small" max-height="400"><el-table-column :label="t('alerting_page.cols.rule')" width="140" prop="rule" /><el-table-column :label="t('alerting_page.cols.severity')" width="70"><template #default="{row}"><el-tag :type="a3SevTag(row.severity)" size="small">{{ a3SevLbl(row.severity) }}</el-tag></template></el-table-column><el-table-column :label="t('alert_manager_page.cols.message')" min-width="240" prop="message" show-overflow-tooltip /><el-table-column :label="t('alerting_page.cols.status')" width="70" prop="status" /><el-table-column :label="t('alert_manager_page.cols.time')" width="150"><template #default="{row}">{{ row.time }}</template></el-table-column></el-table>
                        </div>
                        <el-empty v-else :description="t('alert_manager_page.empty.digest')" />
                    </el-tab-pane>
                </el-tabs></el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- ===== Dialogs for Tab 1 (alert) ===== -->
        <el-dialog v-model="a1DetailVis" :title="t('alert_page.detail.title')" width="60%" top="5vh"><div v-if="a1Detail"><el-descriptions :column="2" border class="mb-3"><el-descriptions-item :label="t('alert_page.cols.title')">{{ a1Detail.title }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.cols.severity')"><el-tag :type="a1SevTag(a1Detail.severity)">{{ a1SevLbl(a1Detail.severity) }}</el-tag></el-descriptions-item><el-descriptions-item :label="t('alert_page.cols.rule')">{{ a1Detail.rule?.name || t('alert_page.manual_trigger') }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.cols.status')"><el-tag :type="a1StTag(a1Detail.status)">{{ a1StLbl(a1Detail.status) }}</el-tag></el-descriptions-item><el-descriptions-item :label="t('alert_page.cols.fired_at')">{{ a1FmtDate(a1Detail.fired_at) }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.detail.event_type')">{{ a1Detail.event_type }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.detail.acknowledged_by')">{{ a1Detail.acknowledged_by?.name || '-' }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.detail.acknowledged_at')">{{ a1FmtDate(a1Detail.acknowledged_at) || '-' }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.detail.resolved_by')">{{ a1Detail.resolved_by?.name || '-' }}</el-descriptions-item><el-descriptions-item :label="t('alert_page.detail.resolved_at')">{{ a1FmtDate(a1Detail.resolved_at) || '-' }}</el-descriptions-item></el-descriptions><el-card><pre class="msg-pre">{{ a1Detail.message }}</pre></el-card><div v-if="a1Detail.context" class="mt-2"><el-collapse><el-collapse-item :title="t('alert_page.detail.context_data')"><pre>{{ JSON.stringify(a1Detail.context, null, 2) }}</pre></el-collapse-item></el-collapse></div></div><template #footer><el-button v-if="a1Detail?.status === 'firing'" @click="a1Ack(a1Detail)">{{ t('alert_page.detail.acknowledge_alert') }}</el-button><el-button v-if="a1Detail?.status !== 'resolved'" type="success" @click="a1Resolve(a1Detail)">{{ t('alert_page.detail.resolve_alert') }}</el-button></template></el-dialog>

        <el-dialog v-model="a1RuleDlg" :title="a1EditingRule ? t('alert_page.rule_dialog.edit_title') : t('alert_page.rule_dialog.create_title')" width="70%" top="5vh"><el-form :model="a1RuleFm" label-width="140px">
            <el-row :gutter="16"><el-col :span="12"><el-form-item :label="t('alert_page.cols.name')" required><el-input v-model="a1RuleFm.name" /></el-form-item></el-col><el-col :span="12"><el-form-item :label="t('alert_page.cols.slug')" required><el-input v-model="a1RuleFm.slug" :disabled="!!a1EditingRule" /></el-form-item></el-col></el-row>
            <el-form-item :label="t('alert_page.rule_dialog.description')"><el-input v-model="a1RuleFm.description" type="textarea" :rows="2" /></el-form-item>
            <el-row :gutter="16"><el-col :span="8"><el-form-item :label="t('alert_page.cols.metric_type')" required><el-select v-model="a1RuleFm.metric_type" style="width:100%"><el-option v-for="(label, key) in a1Meta.metric_types" :key="key" :label="label" :value="key" /></el-select></el-form-item></el-col><el-col :span="8"><el-form-item :label="t('alert_page.rule_dialog.operator')" required><el-select v-model="a1RuleFm.condition_operator" style="width:100%"><el-option v-for="(label, key) in a1Meta.operator_options" :key="key" :label="label" :value="key" /></el-select></el-form-item></el-col><el-col :span="8"><el-form-item :label="t('alert_page.rule_dialog.threshold')" required><el-input-number v-model="a1RuleFm.threshold" :min="0" style="width:100%" /></el-form-item></el-col></el-row>
            <el-row :gutter="16"><el-col :span="8"><el-form-item :label="t('alert_page.rule_dialog.duration_minutes')"><el-input-number v-model="a1RuleFm.duration_minutes" :min="0" style="width:100%" /></el-form-item></el-col><el-col :span="8"><el-form-item :label="t('alert_page.cols.severity')"><el-select v-model="a1RuleFm.severity" style="width:100%"><el-option v-for="(label, key) in a1Meta.severity_options" :key="key" :label="label" :value="key" /></el-select></el-form-item></el-col><el-col :span="8"><el-form-item :label="t('alert_page.rule_dialog.cooldown_minutes')"><el-input-number v-model="a1RuleFm.cooldown_minutes" :min="1" style="width:100%" /></el-form-item></el-col></el-row>
            <el-row :gutter="16"><el-col :span="12"><el-form-item :label="t('alert_page.rule_dialog.max_alert_per_day')"><el-input-number v-model="a1RuleFm.max_alert_per_day" :min="1" :max="100" style="width:100%" /></el-form-item></el-col><el-col :span="12"><el-form-item :label="t('alert_page.rule_dialog.channels')"><el-checkbox-group v-model="a1RuleFm.channels"><el-checkbox v-for="ch in a1ChOpts" :key="ch.value" :label="ch.value">{{ ch.label }}</el-checkbox></el-checkbox-group></el-form-item></el-col></el-row>
            <el-form-item :label="t('alert_page.rule_dialog.slack_webhook')"><el-input v-model="a1RuleFm.slack_webhook" /></el-form-item>
            <el-form-item :label="t('alert_page.rule_dialog.dingtalk_webhook')"><el-input v-model="a1RuleFm.dingtalk_webhook" /></el-form-item>
            <el-form-item :label="t('alert_page.rule_dialog.custom_webhook')"><div v-for="(url, idx) in a1RuleFm.webhook_urls" :key="idx" class="flex mb-1"><el-input v-model="a1RuleFm.webhook_urls[idx]" style="margin-right:8px" /><el-button type="danger" :icon="Delete" circle size="small" @click="a1RuleFm.webhook_urls.splice(idx,1)" /></div><el-button size="small" @click="a1RuleFm.webhook_urls.push('')">+ {{ t('alert_page.rule_dialog.add_url') }}</el-button></el-form-item>
        </el-form><template #footer><el-button @click="a1RuleDlg=false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="a1SaveRule" :loading="a1SavingRule">{{ t('actions.save') }}</el-button></template></el-dialog>

        <el-dialog v-model="a1IntDlg" :title="a1EditingInt ? t('alert_page.integration_dialog.edit_title') : t('alert_page.integration_dialog.create_title')" width="55%" top="5vh"><el-form :model="a1IntFm" label-width="120px">
            <el-form-item :label="t('alert_page.cols.name')" required><el-input v-model="a1IntFm.name" /></el-form-item>
            <el-form-item :label="t('alert_page.cols.type')" required><el-select v-model="a1IntFm.type" style="width:100%"><el-option v-for="opt in a1IntTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
            <el-form-item :label="t('alert_page.cols.webhook_url')" required><el-input v-model="a1IntFm.webhook_url" /></el-form-item>
            <el-form-item :label="t('alert_page.cols.severity_filter')"><el-select v-model="a1IntFm.severity_filter" style="width:100%"><el-option v-for="opt in a1SevFltOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
            <el-form-item :label="t('alert_page.rule_dialog.description')"><el-input v-model="a1IntFm.description" type="textarea" :rows="2" /></el-form-item>
        </el-form><template #footer><el-button @click="a1IntDlg=false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="a1SaveInt" :loading="a1SavingInt">{{ t('actions.save') }}</el-button></template></el-dialog>

        <!-- ===== Dialogs for Tab 2 (alerting) ===== -->
        <RuleDialog v-model:visible="a2RuleDlg.visible" :rule="a2RuleDlg.rule" @saved="a2OnRuleSaved" />
        <EventDetailDialog v-model:visible="a2EventDlg.visible" :event-id="a2EventDlg.id" />

        <!-- ===== Dialogs for Tab 3 (alert-manager) ===== -->
        <el-dialog v-model="a3ShowSilence" :title="t('alert_manager_page.create_silence_title')" width="550px"><el-form :model="a3SilenceFm" label-width="120px">
            <el-form-item :label="t('alert_manager_page.cols.name')" prop="name" :rules="[{required:true}]"><el-input v-model="a3SilenceFm.name" /></el-form-item>
            <el-row :gutter="12"><el-col :span="12"><el-form-item :label="t('alert_manager_page.cols.match_type')" prop="match_type"><el-select v-model="a3SilenceFm.match_type" style="width:100%"><el-option v-for="opt in a3MatchTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col><el-col :span="12"><el-form-item :label="t('alert_manager_page.timezone')"><el-select v-model="a3SilenceFm.timezone" style="width:100%"><el-option label="UTC" value="UTC" /><el-option label="Asia/Shanghai" value="Asia/Shanghai" /></el-select></el-form-item></el-col></el-row>
            <el-row :gutter="12"><el-col :span="12"><el-form-item :label="t('alert_manager_page.start_time')" prop="starts_at"><el-date-picker v-model="a3SilenceFm.starts_at" type="datetime" format="YYYY-MM-DD HH:mm" style="width:100%" /></el-form-item></el-col><el-col :span="12"><el-form-item :label="t('alert_manager_page.end_time')" prop="ends_at"><el-date-picker v-model="a3SilenceFm.ends_at" type="datetime" format="YYYY-MM-DD HH:mm" style="width:100%" /></el-form-item></el-col></el-row>
            <el-form-item :label="t('alert_manager_page.match_conditions')"><el-input v-model="a3MatchRulesText" type="textarea" :rows="2" /></el-form-item>
            <el-form-item :label="t('alert_manager_page.cols.reason')"><el-input v-model="a3SilenceFm.reason" type="textarea" :rows="2" /></el-form-item>
        </el-form><template #footer><el-button @click="a3ShowSilence=false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="a3SaveSilence" :loading="a3Saving">{{ t('actions.save') }}</el-button></template></el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Delete, DataBoard, CircleCheck } from '@element-plus/icons-vue';
import alertApi from '@/api/alert';
import { getAlertDashboard } from '@/api/alerting';
import alertManagerApi from '@/api/alertManager';
import RulePanel from './components/RulePanel.vue';
import ChannelPanel from './components/ChannelPanel.vue';
import EscalationPanel from './components/EscalationPanel.vue';
import EventPanel from './components/EventPanel.vue';
import RuleDialog from './components/RuleDialog.vue';
import EventDetailDialog from './components/EventDetailDialog.vue';

const { t, locale } = useI18n();
const mainTab = ref('alerts');
const dateLoc = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

// ===== Shared severity/status helpers =====
function sevTag(s) { return { critical: 'danger', warning: 'warning', info: 'info' }[s] || 'info'; }
const SEV_KEYS = ['critical', 'warning', 'info'];
const sevLbls = computed(() => Object.fromEntries(SEV_KEYS.map(k => [k, t('alerting_page.severity.' + k)])));
function sevLbl(s) { return sevLbls.value[s] || s; }
const stLbls = computed(() => ({ firing: t('alerting_page.status.firing'), acknowledged: t('alerting_page.status.acknowledged'), resolved: t('alerting_page.status.resolved') }));
function stLbl(s) { return stLbls.value[s] || s; }
function stTag(s) { return { firing: 'danger', acknowledged: 'warning', resolved: 'success' }[s] || 'info'; }
function fmtDate(d) { if (!d) return '-'; return new Date(d).toLocaleString(dateLoc.value, { hour12: false }); }
function fmtTime(tv) { if (!tv) return '—'; return new Date(tv).toLocaleString(dateLoc.value, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }

// ================================================================
// Tab 1: 智能告警 (alert/Index.vue merged)
// ================================================================
const a1SubTab = ref('events');
const a1Loading = ref(false); const a1Evaluating = ref(false);
const a1Dash = reactive({ firing_count: 0, acknowledged_count: 0, resolved_count: 0, total_today: 0, active_rules: 0, total_rules: 0 });
const a1Meta = reactive({ metric_types: {}, severity_options: {}, operator_options: {} });
const a1SevOpts = computed(() => SEV_KEYS.map(v => ({ value: v, label: sevLbl(v) })));
const a1EventStOpts = computed(() => [{ value: 'firing', label: stLbl('firing') }, { value: 'acknowledged', label: stLbl('acknowledged') }, { value: 'resolved', label: stLbl('resolved') }]);
const a1EventFilters = reactive({ status: '', severity: '' });
const a1Events = ref([]); const a1EventPg = ref(1); const a1EventTotal = ref(0);
const a1DetailVis = ref(false); const a1Detail = ref(null);
const a1Rules = ref([]); const a1RuleDlg = ref(false); const a1EditingRule = ref(null); const a1SavingRule = ref(false);
const a1RuleFm = reactive({ name: '', slug: '', description: '', metric_type: 'license_expiry', condition_operator: 'gt', threshold: 0, duration_minutes: 0, severity: 'warning', channels: ['database'], cooldown_minutes: 60, max_alert_per_day: 10, slack_webhook: '', dingtalk_webhook: '', webhook_urls: [] });
const a1Ints = ref([]); const a1IntDlg = ref(false); const a1EditingInt = ref(null); const a1SavingInt = ref(false);
const a1IntFm = reactive({ name: '', type: 'slack', webhook_url: '', severity_filter: 'all', description: '' });
const a1IntTypeLbls = computed(() => ({ slack: t('alert_page.integration_types.slack'), dingtalk: t('alert_page.integration_types.dingtalk'), webhook: t('alert_page.integration_types.webhook'), email_group: t('alert_page.integration_types.email_group') }));
const a1IntTypeOpts = computed(() => [{ value: 'slack', label: a1IntTypeLbls.value.slack }, { value: 'dingtalk', label: a1IntTypeLbls.value.dingtalk }, { value: 'webhook', label: a1IntTypeLbls.value.webhook }, { value: 'email_group', label: a1IntTypeLbls.value.email_group }]);
const a1SevFltLbls = computed(() => ({ all: t('alert_page.severity_filter.all'), critical: t('alert_page.severity_filter.critical_up'), warning: t('alert_page.severity_filter.warning_up'), info: t('alert_page.severity_filter.info_only') }));
const a1SevFltOpts = computed(() => [{ value: 'all', label: a1SevFltLbls.value.all }, { value: 'critical', label: a1SevFltLbls.value.critical }, { value: 'warning', label: a1SevFltLbls.value.warning }, { value: 'info', label: a1SevFltLbls.value.info }]);
const a1ChOpts = computed(() => [{ value: 'database', label: t('alert_page.channels.database') }, { value: 'slack', label: t('alert_page.channels.slack') }, { value: 'dingtalk', label: t('alert_page.channels.dingtalk') }, { value: 'webhook', label: t('alert_page.channels.webhook') }]);

function a1SevTag(s) { return sevTag(s); } function a1SevLbl(s) { return sevLbl(s); }
function a1StTag(s) { return stTag(s); } function a1StLbl(s) { return stLbl(s); }
function a1FmtDate(d) { return fmtDate(d); }
function a1IntTypeLbl(t) { return a1IntTypeLbls.value[t] || t; }

async function a1RefreshAll() { a1Loading.value = true; try { await Promise.all([a1FetchDash(), a1FetchMeta(), a1FetchRules(), a1FetchEvents(), a1FetchInts()]); ElMessage.success(t('alert_page.messages.data_refreshed')); } catch { ElMessage.error(t('messages.load_failed')); } finally { a1Loading.value = false; } }
async function a1FetchDash() { try { const { data } = await alertApi.dashboard(); Object.assign(a1Dash, data); } catch {} }
async function a1FetchMeta() { try { const { data } = await alertApi.meta(); Object.assign(a1Meta, data); } catch {} }
async function a1FetchRules() { try { const { data } = await alertApi.rules(); a1Rules.value = data || []; } catch {} }
async function a1FetchEvents(page = 1) { try { const params = { page, per_page: 20 }; if (a1EventFilters.status) params.status = a1EventFilters.status; if (a1EventFilters.severity) params.severity = a1EventFilters.severity; const { data } = await alertApi.events(params); a1Events.value = data.data || []; a1EventPg.value = data.current_page || page; a1EventTotal.value = data.total || 0; } catch {} }
async function a1FetchInts() { try { const { data } = await alertApi.integrations(); a1Ints.value = data || []; } catch {} }

async function a1ShowDetail(row) { a1DetailVis.value = true; a1Detail.value = null; try { const { data } = await alertApi.showEvent(row.id); a1Detail.value = data; } catch { ElMessage.error(t('alert_page.messages.detail_failed')); } }
async function a1Ack(row) { try { await alertApi.acknowledgeEvent(row.id); ElMessage.success(t('alert_page.messages.acknowledged')); a1FetchEvents(a1EventPg.value); a1FetchDash(); } catch { ElMessage.error(t('messages.failed')); } }
async function a1Resolve(row) { try { await alertApi.resolveEvent(row.id); ElMessage.success(t('alert_page.messages.resolved')); a1FetchEvents(a1EventPg.value); a1FetchDash(); if (a1Detail.value) a1Detail.value.status = 'resolved'; } catch { ElMessage.error(t('messages.failed')); } }
async function a1EvaluateRules() { a1Evaluating.value = true; try { const { data } = await alertApi.evaluate(); ElMessage.success(t('alert_page.messages.evaluate_done', { n: data.fired })); await a1RefreshAll(); } catch { ElMessage.error(t('alert_page.messages.evaluate_failed')); } finally { a1Evaluating.value = false; } }

function a1OpenRule(rule = null) { a1EditingRule.value = rule; a1RuleDlg.value = true; if (rule) Object.assign(a1RuleFm, { name: rule.name, slug: rule.slug, description: rule.description || '', metric_type: rule.metric_type, condition_operator: rule.condition_operator, threshold: rule.threshold, duration_minutes: rule.duration_minutes ?? 0, severity: rule.severity || 'warning', channels: rule.channels || ['database'], cooldown_minutes: rule.cooldown_minutes ?? 60, max_alert_per_day: rule.max_alert_per_day ?? 10, slack_webhook: rule.slack_webhook || '', dingtalk_webhook: rule.dingtalk_webhook || '', webhook_urls: rule.webhook_urls || [] }); else Object.assign(a1RuleFm, { name: '', slug: '', description: '', metric_type: 'license_expiry', condition_operator: 'gt', threshold: 0, duration_minutes: 0, severity: 'warning', channels: ['database'], cooldown_minutes: 60, max_alert_per_day: 10, slack_webhook: '', dingtalk_webhook: '', webhook_urls: [] }); }
async function a1SaveRule() { a1SavingRule.value = true; try { const payload = { ...a1RuleFm }; if (a1EditingRule.value) { await alertApi.updateRule(a1EditingRule.value.id, payload); ElMessage.success(t('alert_page.messages.rule_updated')); } else { await alertApi.storeRule(payload); ElMessage.success(t('alert_page.messages.rule_created')); } a1RuleDlg.value = false; await a1FetchRules(); } catch { ElMessage.error(t('messages.failed')); } finally { a1SavingRule.value = false; } }
async function a1DeleteRule(rule) { try { await alertApi.destroyRule(rule.id); ElMessage.success(t('alert_page.messages.deleted')); await a1FetchRules(); } catch { ElMessage.error(t('messages.failed')); } }
async function a1ToggleRule(rule) { try { await alertApi.updateRule(rule.id, { is_active: rule.is_active }); } catch { rule.is_active = !rule.is_active; } }

function a1OpenIntegration(intg = null) { a1EditingInt.value = intg; a1IntDlg.value = true; Object.assign(a1IntFm, intg ? { name: intg.name, type: intg.type, webhook_url: intg.webhook_url, severity_filter: intg.severity_filter || 'all', description: intg.description || '' } : { name: '', type: 'slack', webhook_url: '', severity_filter: 'all', description: '' }); }
async function a1SaveInt() { a1SavingInt.value = true; try { if (a1EditingInt.value) { await alertApi.updateIntegration(a1EditingInt.value.id, a1IntFm); ElMessage.success(t('alert_page.messages.integration_updated')); } else { await alertApi.storeIntegration(a1IntFm); ElMessage.success(t('alert_page.messages.integration_created')); } a1IntDlg.value = false; await a1FetchInts(); } catch { ElMessage.error(t('messages.failed')); } finally { a1SavingInt.value = false; } }
async function a1DeleteInt(intg) { try { await alertApi.destroyIntegration(intg.id); ElMessage.success(t('alert_page.messages.deleted')); await a1FetchInts(); } catch { ElMessage.error(t('messages.failed')); } }
async function a1ToggleInt(intg) { try { await alertApi.updateIntegration(intg.id, { is_active: intg.is_active }); } catch { intg.is_active = !intg.is_active; } }
async function a1TestInt(intg) { try { await alertApi.testIntegration(intg.id); ElMessage.success(t('alert_page.messages.test_success')); } catch { ElMessage.error(t('alert_page.messages.test_failed')); } }

// ================================================================
// Tab 2: 告警中心 (alerting/Index.vue merged)
// ================================================================
const a2SubTab = ref('rules');
const a2Stats = ref({ by_severity: {}, recent_events: [] });
const a2RuleDlg = reactive({ visible: false, rule: null });
const a2EventDlg = reactive({ visible: false, id: null });

function a2SevTag(s) { return sevTag(s); } function a2SevLbl(s) { return sevLbl(s); }
function a2StTag(s) { return stTag(s); } function a2StLbl(s) { return stLbl(s); }
function a2EditRule(rule) { a2RuleDlg.rule = rule; a2RuleDlg.visible = true; }
function a2ShowEvent(event) { a2EventDlg.id = event.id || event; a2EventDlg.visible = true; }
function a2OnRuleSaved() { a2RuleDlg.visible = false; a2RuleDlg.rule = null; a2LoadDash(); }
async function a2LoadDash() { try { const { data } = await getAlertDashboard(); a2Stats.value = data; } catch {} }

// ================================================================
// Tab 3: 告警管理 (alert-manager/Index.vue merged)
// ================================================================
const a3SubTab = ref('aggregation');
const a3Loading = ref(false); const a3Saving = ref(false); const a3AggLoading = ref(false); const a3DowngradeLoading = ref(false);
const a3SilenceLoading = ref(false); const a3FatigueLoading = ref(false); const a3NoiseLoading = ref(false); const a3DigestLoading = ref(false);
const a3NoiseDays = ref(7);
const a3Dash = reactive({ total_rules: 0, active_rules: 0, firing_events: 0, acknowledged: 0, resolved_today: 0, active_silences: 0, aggregated_events: 0, fatigue_settings: 0, severity_distribution: {} });
const a3AggGroups = ref([]); const a3SilenceRules = ref([]); const a3FatigueSettings = ref([]);
const a3NoiseData = reactive({ total_noisy_rules: 0, rules: [] }); const a3DigestData = reactive({});
const a3ShowSilence = ref(false); const a3MatchRulesText = ref('');
const a3SilenceFm = reactive({ name: '', match_type: 'exact', timezone: 'UTC', starts_at: '', ends_at: '', reason: '' });
const a3MatchTypeOpts = computed(() => [{ value: 'exact', label: t('alert_manager_page.match_types.exact') }, { value: 'wildcard', label: t('alert_manager_page.match_types.wildcard') }, { value: 'pattern', label: t('alert_manager_page.match_types.pattern') }]);
const a3SevText = computed(() => { const d = a3Dash.severity_distribution || {}; return Object.entries(d).map(([k, v]) => `${sevLbl(k)}:${v}`).join(' | '); });

function a3SevTag(s) { return sevTag(s); } function a3SevLbl(s) { return sevLbl(s); }
function a3FmtTime(tv) { return fmtTime(tv); }
function a3MatchTypeLbl(s) { return a3MatchTypeOpts.value.find(o => o.value === s)?.label || s; }

async function a3LoadAll() { a3Loading.value = true; try { await Promise.all([a3LoadDash(), a3LoadAggGroups(), a3LoadSilence(), a3LoadFatigue(), a3LoadNoise()]); } finally { a3Loading.value = false; } }
async function a3LoadDash() { try { const r = await alertManagerApi.dashboard(); Object.assign(a3Dash, r.data?.data || {}); } catch {} }
async function a3LoadAggGroups() { try { const r = await alertManagerApi.aggregationGroups(); a3AggGroups.value = r.data?.data || []; } catch {} }
async function a3LoadSilence() { a3SilenceLoading.value = true; try { const r = await alertManagerApi.listSilenceRules(); a3SilenceRules.value = r.data?.data?.items || []; } finally { a3SilenceLoading.value = false; } }
async function a3LoadFatigue() { a3FatigueLoading.value = true; try { const r = await alertManagerApi.listFatigueSettings(); a3FatigueSettings.value = r.data?.data || []; } finally { a3FatigueLoading.value = false; } }
async function a3LoadNoise() { a3NoiseLoading.value = true; try { const r = await alertManagerApi.noiseAnalysis({ days: a3NoiseDays.value }); Object.assign(a3NoiseData, r.data?.data || {}); } finally { a3NoiseLoading.value = false; } }
async function a3RunAggregate() { a3AggLoading.value = true; try { const r = await alertManagerApi.aggregate(); ElMessage.success(t('alert_manager_page.messages.aggregated', { n: r.data?.data?.aggregated || 0 })); a3LoadAggGroups(); } catch { ElMessage.error(t('alert_manager_page.messages.aggregate_failed')); } finally { a3AggLoading.value = false; } }
async function a3RunDowngrade() { a3DowngradeLoading.value = true; try { const r = await alertManagerApi.autoDowngrade(); ElMessage.success(t('alert_manager_page.messages.downgraded', { n: r.data?.data?.downgraded || 0 })); } catch { ElMessage.error(t('alert_manager_page.messages.downgrade_failed')); } finally { a3DowngradeLoading.value = false; } }
async function a3ToggleSilence(row) { await alertManagerApi.toggleSilenceRule(row.id); ElMessage.success(row.is_active ? t('alert_manager_page.messages.disabled') : t('alert_manager_page.messages.enabled')); a3LoadSilence(); }
async function a3DeleteSilence(row) { await ElMessageBox.confirm(t('alert_manager_page.messages.delete_silence_confirm', { name: row.name })); await alertManagerApi.deleteSilenceRule(row.id); ElMessage.success(t('alert_manager_page.messages.deleted')); a3LoadSilence(); }
async function a3SaveSilence() { a3Saving.value = true; try { const data = { ...a3SilenceFm }; try { data.match_rules = JSON.parse(a3MatchRulesText.value || '{}'); } catch { data.match_rules = {}; } await alertManagerApi.storeSilenceRule(data); ElMessage.success(t('alert_manager_page.messages.created')); a3ShowSilence.value = false; a3LoadSilence(); } catch { ElMessage.error(t('alert_manager_page.messages.create_failed')); } finally { a3Saving.value = false; } }
async function a3DeleteFatigue(row) { await ElMessageBox.confirm(t('alert_manager_page.messages.delete_fatigue_confirm')); await alertManagerApi.deleteFatigueSetting(row.id); ElMessage.success(t('alert_manager_page.messages.deleted')); a3LoadFatigue(); }
async function a3ViewAggGroup(key) { const r = await alertManagerApi.aggregationDetail(key); const children = r.data?.data || []; let html = children.map(c => `<div>#${c.id} [${sevLbl(c.severity)}] ${c.rule_name}: ${c.message || ''} <small>${c.created_at}</small></div>`).join(''); ElMessageBox.alert(html || t('alert_manager_page.messages.no_children'), t('alert_manager_page.messages.agg_group_title', { key }), { dangerouslyUseHTMLString: true, customClass: 'msg-wide' }); }
async function a3LoadDigest() { a3DigestLoading.value = true; try { const r = await alertManagerApi.generateDigest(); Object.assign(a3DigestData, r.data?.data || {}); } finally { a3DigestLoading.value = false; } }

// ===== Lazy loading =====
function onMainTabChange(tab) {
    if (tab === 'alerting' && !a2Stats.value.active_rules) a2LoadDash();
    if (tab === 'manager' && !a3Dash.total_rules) a3LoadAll();
}

onMounted(() => { a1RefreshAll(); });
</script>

<style scoped>
.alert-center-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.tab-header { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
.mb-3 { margin-bottom: 12px; } .mb-4 { margin-bottom: 16px; } .mt-2 { margin-top: 8px; } .mt-3 { margin-top: 12px; } .ml-3 { margin-left: 12px; }
.flex { display: flex; } .flex-col { flex-direction: column; } .items-center { align-items: center; } .justify-between { justify-content: space-between; } .justify-center { justify-content: center; } .justify-end { justify-content: flex-end; }
.gap-2 { gap: 8px; } .font-bold { font-weight: 700; }
.text-muted { color: #c0c4cc; } .text-sm { font-size: 12px; } .text-gray-400 { color: #909399; }
.text-danger { color: #f56c6c !important; } .text-success { color: #67c23a !important; } .text-warning { color: #e6a23c !important; }
.danger { color: #f56c6c; } .warning { color: #e6a23c; } .success { color: #67c23a; }

.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 0.8rem; color: #909399; margin-top: 4px; }
.stat-success .stat-value { color: #67c23a; }
.stat-danger .stat-value { color: #f56c6c; }
.stat-warning .stat-value { color: #e6a23c; }

.s-card { text-align: center; padding: 8px 0; } .s-val { font-size: 32px; font-weight: 700; color: #303133; } .s-lbl { font-size: 14px; color: #909399; margin-top: 4px; }

.m-card { padding: 8px; } .m-lbl { font-size: 12px; color: #909399; margin-bottom: 4px; } .m-val { font-size: 20px; font-weight: 700; } .m-val small { font-size: 13px; font-weight: 400; }

.pagination-wrap { display: flex; justify-content: flex-end; padding: 16px 0; }
.msg-pre { white-space: pre-wrap; word-break: break-word; font-family: inherit; margin: 0; line-height: 1.6; }
</style>

<style>
.msg-wide .el-message-box__message { max-height: 400px; overflow-y: auto; }
</style>
