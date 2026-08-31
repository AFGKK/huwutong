<template>
    <div class="renewal-center-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('renewal_center.title') }}</h2>
                <span class="header-subtitle">{{ t('renewal_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="mainTab" type="border-card">
            <!-- ==================== Tab 1: 续期管理 ==================== -->
            <el-tab-pane :label="t('renewal_center.tab_renewal')" name="renewal">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6" v-for="item in statCardsRow1" :key="item.key">
                        <el-card shadow="never" :body-style="{ padding: '16px' }">
                            <div class="stat-card" @click="item.onClick?.()">
                                <div class="stat-value" :class="item.valueClass">{{ item.value }}</div>
                                <div class="stat-label">{{ item.label }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6" v-for="item in statCardsRow2" :key="item.key">
                        <el-card shadow="never" :body-style="{ padding: '16px' }">
                            <div class="stat-card">
                                <div class="stat-value" :class="item.valueClass">{{ item.value }}</div>
                                <div class="stat-label">{{ item.label }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-card shadow="never">
                    <el-tabs v-model="renewalActiveTab" @tab-change="handleRenewalTabChange">
                        <el-tab-pane :label="t('renewal_page.tabs.expiring')" name="expiring">
                            <div class="toolbar">
                                <el-form :inline="true" size="small">
                                    <el-form-item>
                                        <el-radio-group v-model="filterDays" @change="fetchExpiring">
                                            <el-radio-button v-for="opt in filterDayOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio-button>
                                        </el-radio-group>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-input v-model="expiringSearch" :placeholder="t('renewal_page.filter.search_expiring_ph')" clearable @clear="fetchExpiring" @keyup.enter="fetchExpiring" style="width: 260px">
                                            <template #prefix><el-icon><Search /></el-icon></template>
                                        </el-input>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-button type="primary" @click="fetchExpiring"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button>
                                    </el-form-item>
                                </el-form>
                                <div>
                                    <el-button type="warning" :disabled="!selectedExpiring.length" @click="showBatchRenew = true">
                                        <el-icon><Refresh /></el-icon> {{ t('renewal_page.batch_renew_n', { n: selectedExpiring.length }) }}
                                    </el-button>
                                </div>
                            </div>
                            <el-table :data="expiringLicenses" v-loading="loading" stripe @selection-change="(val) => selectedExpiring = val.map(v => v.id)">
                                <el-table-column type="selection" width="40" />
                                <el-table-column prop="license_key" :label="t('licenses_page.license_key')" min-width="180" />
                                <el-table-column :label="t('licenses_page.customer')" min-width="150"><template #default="{ row }">{{ row.customer?.name || '-' }}</template></el-table-column>
                                <el-table-column :label="t('licenses_page.product')" width="120"><template #default="{ row }">{{ row.product?.name || '-' }}</template></el-table-column>
                                <el-table-column prop="seats" :label="t('licenses_page.seats')" width="60" align="center" />
                                <el-table-column :label="t('licenses_page.col_expires_at')" width="170" sortable="custom"><template #default="{ row }">{{ row.expires_at || '-' }}</template></el-table-column>
                                <el-table-column :label="t('renewal_page.cols.days_remaining')" width="100"><template #default="{ row }"><el-tag :type="getExpiryTagType(row)" size="small">{{ t('renewal_page.days_count', { n: row.days_until_expiry ?? '-' }) }}</el-tag></template></el-table-column>
                                <el-table-column :label="t('licenses_page.col_actions')" width="150" fixed="right">
                                    <template #default="{ row }">
                                        <el-button text size="small" type="primary" @click="openRenewDialog(row)">{{ t('renewal_page.renew') }}</el-button>
                                        <el-button text size="small" type="primary" @click="viewLicense(row)">{{ t('actions.view') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div class="pagination-wrap">
                                <el-pagination v-model:current-page="expiringPage" v-model:page-size="expiringPerPage" :total="expiringTotal" :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next" @change="fetchExpiring" />
                            </div>
                        </el-tab-pane>
                        <el-tab-pane :label="t('licenses_page.stat_expired')" name="expired">
                            <div class="toolbar">
                                <el-form :inline="true" size="small">
                                    <el-form-item>
                                        <el-select v-model="expiredDaysAgo" :placeholder="t('renewal_page.filter.time_range_ph')" @change="fetchExpired" style="width: 140px">
                                            <el-option v-for="opt in expiredRangeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item>
                                        <el-input v-model="expiredSearch" :placeholder="t('renewal_page.filter.search_expired_ph')" clearable @clear="fetchExpired" @keyup.enter="fetchExpired" style="width: 260px">
                                            <template #prefix><el-icon><Search /></el-icon></template>
                                        </el-input>
                                    </el-form-item>
                                    <el-form-item><el-button type="primary" @click="fetchExpired"><el-icon><Search /></el-icon> {{ t('actions.search') }}</el-button></el-form-item>
                                </el-form>
                            </div>
                            <el-table :data="expiredLicenses" v-loading="loading" stripe>
                                <el-table-column prop="license_key" :label="t('licenses_page.license_key')" min-width="180" />
                                <el-table-column :label="t('licenses_page.customer')" min-width="150"><template #default="{ row }">{{ row.customer?.name || '-' }}</template></el-table-column>
                                <el-table-column :label="t('licenses_page.product')" width="120"><template #default="{ row }">{{ row.product?.name || '-' }}</template></el-table-column>
                                <el-table-column prop="expires_at" :label="t('licenses_page.col_expires_at')" width="170" />
                                <el-table-column :label="t('licenses_page.status')" width="90"><template #default><el-tag type="danger" size="small">{{ t('licenses_page.st_expired') }}</el-tag></template></el-table-column>
                                <el-table-column :label="t('licenses_page.col_actions')" width="120">
                                    <template #default="{ row }"><el-button text size="small" type="primary" @click="openRenewDialog(row)">{{ t('renewal_page.renew') }}</el-button></template>
                                </el-table-column>
                            </el-table>
                            <div class="pagination-wrap">
                                <el-pagination v-model:current-page="expiredPage" v-model:page-size="expiredPerPage" :total="expiredTotal" :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next" @change="fetchExpired" />
                            </div>
                        </el-tab-pane>
                        <el-tab-pane :label="t('renewal_page.tabs.log')" name="log">
                            <el-table :data="activityLogs" v-loading="activityLoading" stripe>
                                <el-table-column prop="created_at" :label="t('renewal_page.cols.time')" width="170" />
                                <el-table-column prop="action" :label="t('renewal_page.cols.action_type')" width="100" />
                                <el-table-column prop="description" :label="t('renewal_page.cols.description')" min-width="300" />
                                <el-table-column :label="t('renewal_page.cols.details')" width="200">
                                    <template #default="{ row }">
                                        <template v-if="row.properties">
                                            <el-tag size="small" type="info">{{ t('renewal_page.days_added', { n: row.properties.days_added }) }}</el-tag>
                                            <span class="ml-1" v-if="row.properties.old_expires_at">{{ row.properties.old_expires_at }} → {{ row.properties.new_expires_at }}</span>
                                        </template>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </el-tab-pane>

            <!-- ==================== Tab 2: 自动续费 ==================== -->
            <el-tab-pane :label="t('renewal_center.tab_auto')" name="auto">
                <div class="tab-header">
                    <el-button @click="autoRefreshAll" :loading="autoLoading"><el-icon><Refresh /></el-icon> {{ t('auto_renewal_page.refresh') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4">
                    <el-col :xs="12" :sm="6" v-for="item in autoStatCards" :key="item.key">
                        <el-card shadow="hover">
                            <div class="auto-stat-value">{{ autoDash[item.key] ?? 0 }}</div>
                            <div class="auto-stat-label">{{ item.label }}</div>
                        </el-card>
                    </el-col>
                </el-row>
                <el-card shadow="never">
                    <el-tabs v-model="autoTab">
                        <el-tab-pane :label="t('auto_renewal_page.tabs.plans')" name="plans">
                            <el-table :data="autoPlans" v-loading="autoLoading" stripe>
                                <el-table-column prop="name" :label="t('auto_renewal_page.cols.plan_name')" min-width="160" />
                                <el-table-column prop="product?.name" :label="t('auto_renewal_page.cols.product')" min-width="120" />
                                <el-table-column prop="billing_period" :label="t('auto_renewal_page.cols.billing_period')" width="100" />
                                <el-table-column prop="price" :label="t('auto_renewal_page.cols.price')" width="100" />
                                <el-table-column :label="t('auto_renewal_page.cols.status')" width="90">
                                    <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag></template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                        <el-tab-pane :label="t('auto_renewal_page.tabs.subscriptions')" name="subscriptions">
                            <el-table :data="autoSubs" v-loading="autoLoading" stripe>
                                <el-table-column prop="customer?.name" :label="t('auto_renewal_page.cols.customer')" min-width="120" />
                                <el-table-column prop="plan?.name" :label="t('auto_renewal_page.cols.plan')" min-width="140" />
                                <el-table-column prop="status" :label="t('auto_renewal_page.cols.status')" width="100" />
                                <el-table-column prop="next_renew_at" :label="t('auto_renewal_page.cols.next_renew_at')" width="170" />
                                <el-table-column :label="t('auto_renewal_page.cols.actions')" width="220" fixed="right">
                                    <template #default="{ row }">
                                        <el-button size="small" type="primary" link @click="autoHandleAction('renew', row)">{{ t('auto_renewal_page.renew') }}</el-button>
                                        <el-button size="small" link @click="autoHandleAction(row.status === 'paused' ? 'resume' : 'pause', row)">{{ row.status === 'paused' ? t('auto_renewal_page.resume') : t('auto_renewal_page.pause') }}</el-button>
                                        <el-button size="small" type="danger" link @click="autoHandleAction('cancel', row)">{{ t('actions.cancel') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </el-tab-pane>

            <!-- ==================== Tab 3: 续费提醒 ==================== -->
            <el-tab-pane :label="t('renewal_center.tab_reminder')" name="reminder">
                <div class="tab-header">
                    <el-button type="primary" @click="reminderRefreshAll" :loading="reminderLoading"><el-icon><Refresh /></el-icon> {{ t('renewal_reminder_page.refresh') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4" v-if="reminderSuggestions.length > 0">
                    <el-col :span="24">
                        <el-card shadow="hover" class="sug-card">
                            <template #header><span><el-icon style="vertical-align:middle;margin-right:4px"><DataAnalysis /></el-icon> {{ t('renewal_reminder_page.sections.suggestions') }}</span></template>
                            <el-row :gutter="12">
                                <el-col v-for="s in reminderSuggestions" :key="s.type" :span="8" style="margin-bottom:8px">
                                    <el-alert :title="s.title" :description="s.message" :type="s.severity === 'critical' ? 'error' : s.severity === 'high' ? 'warning' : 'info'" show-icon :closable="false" />
                                </el-col>
                            </el-row>
                        </el-card>
                    </el-col>
                </el-row>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="rmd-stat-card"><div class="rmd-stat-value">{{ reminderAnalytics.auto_renew_rate || 0 }}%</div><div class="rmd-stat-label">{{ t('renewal_reminder_page.stats.auto_renew_rate') }}</div><div class="rmd-stat-sub">{{ t('renewal_reminder_page.stats.auto_renew_sub', { enabled: reminderAnalytics.auto_renew_count || 0, total: reminderAnalytics.total_active || 0 }) }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="rmd-stat-card"><div class="rmd-stat-value" :class="reminderAnalytics.conversion_rate_30d >= 60 ? 'rmd-success' : 'rmd-danger'">{{ reminderAnalytics.conversion_rate_30d || 0 }}%</div><div class="rmd-stat-label">{{ t('renewal_reminder_page.stats.conversion_rate_30d') }}</div><div class="rmd-stat-sub">{{ t('renewal_reminder_page.stats.conversion_sub', { renewed: reminderAnalytics.renewed_30d || 0, expired: reminderAnalytics.expired_30d || 0 }) }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="rmd-stat-card"><div class="rmd-stat-value">{{ reminderAnalytics.renewed_30d || 0 }}</div><div class="rmd-stat-label">{{ t('renewal_reminder_page.stats.renewed_30d') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="rmd-stat-card"><div class="rmd-stat-value rmd-danger">{{ reminderAnalytics.expired_30d || 0 }}</div><div class="rmd-stat-label">{{ t('renewal_reminder_page.stats.expired_30d') }}</div></el-card></el-col>
                </el-row>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="24"><el-card shadow="hover"><template #header><span>{{ t('renewal_reminder_page.sections.channel_distribution') }}</span></template><div ref="channelChartRef" style="height:180px"></div></el-card></el-col>
                </el-row>
                <el-card shadow="hover">
                    <el-tabs v-model="reminderTab">
                        <el-tab-pane :label="t('renewal_reminder_page.tabs.templates')" name="templates">
                            <div class="tab-bar">
                                <el-button size="small" type="primary" @click="reminderShowTemplateDialog(null)"><el-icon><Plus /></el-icon> {{ t('renewal_reminder_page.btn.new_template') }}</el-button>
                                <el-button size="small" @click="reminderHandleProcessDue" :loading="reminderProDue"><el-icon><Promotion /></el-icon> {{ t('renewal_reminder_page.btn.process_due') }}</el-button>
                                <el-select v-model="reminderTmplFlt.channel" :placeholder="t('renewal_reminder_page.filters.channel')" clearable style="width:120px;margin-left:8px">
                                    <el-option :label="t('renewal_reminder_page.filters.all_channels')" value="" />
                                    <el-option v-for="key in reminderChKeys" :key="key" :label="reminderChLabel(key)" :value="key" />
                                </el-select>
                            </div>
                            <el-table :data="reminderTmpls" stripe v-loading="reminderTmplLoading">
                                <el-table-column prop="name" :label="t('renewal_reminder_page.cols.name')" min-width="140" />
                                <el-table-column :label="t('renewal_reminder_page.cols.channel')" width="80"><template #default="{ row }">{{ reminderChLabel(row.channel) }}</template></el-table-column>
                                <el-table-column :label="t('renewal_reminder_page.cols.days_before')" width="100" align="center"><template #default="{ row }">{{ t('renewal_reminder_page.days_count', { n: row.days_before }) }}</template></el-table-column>
                                <el-table-column :label="t('renewal_reminder_page.cols.enabled')" width="70" align="center"><template #default="{ row }"><el-switch v-model="row.is_active" size="small" @change="reminderToggleTmpl(row)" /></template></el-table-column>
                                <el-table-column prop="subject" :label="t('renewal_reminder_page.cols.subject')" min-width="180" show-overflow-tooltip />
                                <el-table-column :label="t('renewal_reminder_page.cols.actions')" width="160">
                                    <template #default="{ row }"><el-button size="small" text type="primary" @click="reminderShowTemplateDialog(row)">{{ t('actions.edit') }}</el-button><el-button size="small" text type="danger" @click="reminderDeleteTmpl(row)">{{ t('actions.delete') }}</el-button></template>
                                </el-table-column>
                            </el-table>
                        </el-tab-pane>
                        <el-tab-pane :label="t('renewal_reminder_page.tabs.logs')" name="logs">
                            <div class="tab-bar">
                                <el-select v-model="reminderLogFlt.status" :placeholder="t('renewal_reminder_page.filters.status')" clearable style="width:120px">
                                    <el-option :label="t('renewal_reminder_page.filters.all')" value="" />
                                    <el-option v-for="key in reminderStKeys" :key="key" :label="reminderStLabel(key)" :value="key" />
                                </el-select>
                                <el-select v-model="reminderLogFlt.channel" :placeholder="t('renewal_reminder_page.filters.channel')" clearable style="width:120px;margin-left:8px">
                                    <el-option :label="t('renewal_reminder_page.filters.all_channels')" value="" />
                                    <el-option v-for="key in reminderChKeys" :key="key" :label="reminderChLabel(key)" :value="key" />
                                </el-select>
                            </div>
                            <el-table :data="reminderLogEntries" stripe v-loading="reminderLogLoading">
                                <el-table-column prop="subscription_id" :label="t('renewal_reminder_page.cols.subscription_id')" width="80" />
                                <el-table-column :label="t('renewal_reminder_page.cols.channel')" width="80"><template #default="{ row }">{{ reminderChLabel(row.channel) }}</template></el-table-column>
                                <el-table-column prop="template_name" :label="t('renewal_reminder_page.cols.template')" width="120" />
                                <el-table-column prop="subject" :label="t('renewal_reminder_page.cols.subject')" min-width="180" show-overflow-tooltip />
                                <el-table-column :label="t('renewal_reminder_page.filters.status')" width="90"><template #default="{ row }"><el-tag :type="row.status === 'sent' ? 'success' : row.status === 'failed' ? 'danger' : 'warning'" size="small">{{ reminderStLabel(row.status) }}</el-tag></template></el-table-column>
                                <el-table-column :label="t('renewal_reminder_page.cols.sent_at')" width="150"><template #default="{ row }">{{ fmtTs(row.sent_at) }}</template></el-table-column>
                                <el-table-column :label="t('renewal_reminder_page.cols.error')" min-width="120" show-overflow-tooltip><template #default="{ row }"><span class="error-text">{{ row.error }}</span></template></el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </el-tab-pane>

            <!-- ==================== Tab 4: 智能催缴 ==================== -->
            <el-tab-pane :label="t('renewal_center.tab_dunning')" name="dunning">
                <div class="tab-header">
                    <el-button @click="dnScan" :loading="dnScanning" type="warning"><el-icon><Refresh /></el-icon> {{ t('dunning_page.scan_overdue') }}</el-button>
                    <el-button @click="dnRun" :loading="dnRunning"><el-icon><Refresh /></el-icon> {{ t('dunning_page.run_dunning') }}</el-button>
                    <el-button @click="dnRefresh" :loading="dnLoading" type="primary"><el-icon><Refresh /></el-icon> {{ t('dunning_page.refresh') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="dn-stat"><div class="dn-stat-v">{{ dnDash.total_active }}</div><div class="dn-stat-l">{{ t('dunning_page.stat_active') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="dn-stat dn-ok"><div class="dn-stat-v">{{ dnDash.total_resolved }}</div><div class="dn-stat-l">{{ t('dunning_page.stat_resolved') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="dn-stat dn-err"><div class="dn-stat-v">{{ dnDash.total_failed }}</div><div class="dn-stat-l">{{ t('dunning_page.stat_failed') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="dn-stat dn-warn"><div class="dn-stat-v">{{ fmtMoney(dnDash.total_due_amount) }}</div><div class="dn-stat-l">{{ t('dunning_page.stat_total_due') }}</div></el-card></el-col>
                </el-row>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="24"><el-card><template #header><span>{{ t('dunning_page.stage_distribution') }}</span></template>
                        <el-table v-if="dnDash.by_stage?.length" :data="dnDash.by_stage" stripe size="small">
                            <el-table-column :label="t('dunning_page.col_stage')" width="200"><template #default="{ row }"><el-tag :type="dnStageTag(row.current_stage)">{{ dnStageLbl(row.current_stage) }}</el-tag></template></el-table-column>
                            <el-table-column prop="count" :label="t('dunning_page.col_count')" width="120" sortable />
                            <el-table-column prop="total" :label="t('dunning_page.col_total_amount')" width="150" sortable><template #default="{ row }">{{ fmtMoney(row.total) }}</template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_share')" min-width="200"><template #default="{ row }"><el-progress :percentage="dnDash.total_active > 0 ? Math.round(row.count / dnDash.total_active * 100) : 0" :stroke-width="16" :text-inside="true" /></template></el-table-column>
                        </el-table>
                        <el-empty v-else :description="t('dunning_page.empty_active')" />
                    </el-card></el-col>
                </el-row>
                <el-tabs v-model="dnTab" type="border-card">
                    <el-tab-pane :label="t('dunning_page.tab_queue')" name="queue">
                        <el-card class="mb-4">
                            <el-form :model="dnQF" inline @keyup.enter="dnFetchQ(1)">
                                <el-form-item :label="t('dunning_page.status')"><el-select v-model="dnQF.status" :placeholder="t('dunning_page.all')" clearable style="width:150px"><el-option v-for="opt in dnQOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                                <el-form-item :label="t('actions.search')"><el-input v-model="dnQF.search" :placeholder="t('dunning_page.search_ph')" clearable style="width:200px" /></el-form-item>
                                <el-form-item><el-button type="primary" @click="dnFetchQ(1)">{{ t('actions.search') }}</el-button><el-button @click="dnResetQF">{{ t('actions.reset') }}</el-button></el-form-item>
                            </el-form>
                        </el-card>
                        <el-table :data="dnQL" stripe style="width:100%" @row-click="dnShowQDetail">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column :label="t('dunning_page.col_customer')" min-width="150"><template #default="{ row }">{{ row.customer?.name || row.customer?.user?.name || t('dunning_page.na') }}</template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_invoice_no')" width="180"><template #default="{ row }">{{ row.invoice?.invoice_no || t('dunning_page.na') }}</template></el-table-column>
                            <el-table-column prop="amount_due" :label="t('dunning_page.col_amount_due')" width="130" sortable><template #default="{ row }">{{ fmtMoney(row.amount_due) }} {{ row.currency }}</template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_dunning_stage')" width="150"><template #default="{ row }"><el-tag :type="dnStageTag(row.current_stage)" size="small">{{ dnStageLbl(row.current_stage) }}</el-tag></template></el-table-column>
                            <el-table-column prop="attempt_count" :label="t('dunning_page.col_attempt_count')" width="100" sortable />
                            <el-table-column :label="t('dunning_page.col_status')" width="110"><template #default="{ row }"><el-tag :type="dnStTag(row.status)" size="small">{{ dnStLbl(row.status) }}</el-tag></template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_next_action')" width="170" sortable><template #default="{ row }"><span v-if="row.next_action_at">{{ fmtDate(row.next_action_at) }}</span><span v-else class="text-gray-400">-</span></template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_actions')" width="120"><template #default="{ row }"><el-button size="small" @click.stop="dnShowQDetail(row)">{{ t('dunning_page.detail') }}</el-button><el-button v-if="['pending', 'in_progress'].includes(row.status)" size="small" type="success" @click.stop="dnResolve(row)">{{ t('dunning_page.resolve') }}</el-button></template></el-table-column>
                        </el-table>
                        <div class="pagination-wrapper"><el-pagination :current-page="dnQPg" :total="dnQTot" :page-size="20" layout="total, prev, pager, next" @current-change="dnFetchQ" /></div>
                    </el-tab-pane>
                    <el-tab-pane :label="t('dunning_page.tab_strategies')" name="strategies">
                        <div class="mb-4"><el-button type="primary" @click="dnOpenStrat()"><el-icon><Plus /></el-icon> {{ t('dunning_page.new_strategy') }}</el-button></div>
                        <el-table :data="dnStrats" stripe style="width:100%">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column prop="name" :label="t('dunning_page.col_name')" min-width="180" />
                            <el-table-column prop="slug" :label="t('dunning_page.col_slug')" width="120"><template #default="{ row }"><code>{{ row.slug }}</code></template></el-table-column>
                            <el-table-column prop="stageCount" :label="t('dunning_page.col_stage_count')" width="100"><template #default="{ row }">{{ row.stages?.length ?? 0 }}</template></el-table-column>
                            <el-table-column prop="max_attempts" :label="t('dunning_page.col_max_attempts')" width="100" />
                            <el-table-column :label="t('dunning_page.col_status')" width="90"><template #default="{ row }"><el-switch v-model="row.is_active" @change="dnToggleStrat(row)" /></template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_sort_order')" width="80"><template #default="{ row }">{{ row.sort_order }}</template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_actions')" width="150"><template #default="{ row }"><el-button size="small" @click="dnOpenStrat(row)">{{ t('actions.edit') }}</el-button><el-popconfirm :title="t('dunning_page.confirm_delete_strategy')" @confirm="dnDelStrat(row)"><template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template></el-popconfirm></template></el-table-column>
                        </el-table>
                    </el-tab-pane>
                    <el-tab-pane :label="t('dunning_page.tab_logs')" name="dn_logs">
                        <el-card class="mb-4">
                            <el-form :model="dnLogF" inline>
                                <el-form-item :label="t('dunning_page.col_action_type')"><el-select v-model="dnLogF.action_taken" :placeholder="t('dunning_page.all')" clearable style="width:180px"><el-option v-for="opt in dnLogAOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                                <el-form-item><el-button type="primary" @click="dnFetchLogs(1)">{{ t('actions.search') }}</el-button></el-form-item>
                            </el-form>
                        </el-card>
                        <el-table :data="dnLogL" stripe size="small" style="width:100%">
                            <el-table-column type="index" label="#" width="50" />
                            <el-table-column :label="t('dunning_page.col_actions')" width="130"><template #default="{ row }"><el-tag :type="dnActTag(row.action_taken)" size="small">{{ dnActLbl(row.action_taken) }}</el-tag></template></el-table-column>
                            <el-table-column prop="attempt_number" :label="t('dunning_page.col_attempt_no')" width="80" />
                            <el-table-column :label="t('dunning_page.col_channel')" width="100"><template #default="{ row }">{{ row.channel || '-' }}</template></el-table-column>
                            <el-table-column :label="t('dunning_page.col_success')" width="80"><template #default="{ row }"><el-tag :type="row.success ? 'success' : 'danger'" size="small">{{ row.success ? t('dunning_page.yes') : t('dunning_page.no') }}</el-tag></template></el-table-column>
                            <el-table-column prop="error_message" :label="t('dunning_page.col_error_message')" min-width="250" show-overflow-tooltip />
                            <el-table-column :label="t('dunning_page.col_time')" width="170" sortable><template #default="{ row }">{{ fmtDate(row.actioned_at) }}</template></el-table-column>
                        </el-table>
                        <div class="pagination-wrapper"><el-pagination :current-page="dnLogPg" :total="dnLogTot" :page-size="50" layout="total, prev, pager, next" @current-change="dnFetchLogs" /></div>
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>
        </el-tabs>

        <!-- ==================== Dialogs ==================== -->

        <!-- 单个续期对话框 -->
        <el-dialog v-model="showRenewDialog" :title="t('renewal_page.renew_dialog_title')" width="400px">
            <div v-if="renewTarget" class="mb-4">
                <p><strong>{{ t('renewal_page.label_license') }}:</strong> {{ renewTarget.license_key }}</p>
                <p><strong>{{ t('licenses_page.customer') }}:</strong> {{ renewTarget.customer?.name || '-' }}</p>
                <p><strong>{{ t('renewal_page.label_current_expiry') }}:</strong> {{ renewTarget.expires_at || t('licenses_page.permanent') }}</p>
            </div>
            <el-form label-width="100px">
                <el-form-item :label="t('licenses_page.renew_days')"><el-input-number v-model="renewDays" :min="1" :max="3650" :step="30" style="width:200px" /></el-form-item>
                <el-form-item :label="t('licenses_page.send_notify')"><el-switch v-model="renewNotify" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showRenewDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="renewing" @click="confirmRenew">{{ t('renewal_page.confirm_renew') }}</el-button></template>
        </el-dialog>

        <!-- 批量续期对话框 -->
        <el-dialog v-model="showBatchRenew" :title="t('licenses_page.batch_renew')" width="400px">
            <p class="mb-4">{{ t('licenses_page.batch_selected', { n: selectedExpiring.length }) }}</p>
            <el-form label-width="100px">
                <el-form-item :label="t('licenses_page.renew_days')"><el-input-number v-model="batchRenewDays" :min="1" :max="3650" :step="30" style="width:200px" /></el-form-item>
                <el-form-item :label="t('licenses_page.send_notify')"><el-switch v-model="batchRenewNotify" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showBatchRenew = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="renewing" @click="confirmBatchRenew">{{ t('renewal_page.confirm_batch_renew') }}</el-button></template>
        </el-dialog>

        <!-- 续费提醒模板对话框 -->
        <el-dialog v-model="reminderDlg.visible" :title="reminderDlgTitle" width="550px">
            <el-form :model="reminderFm" label-width="110px">
                <el-form-item :label="t('renewal_reminder_page.form.name')" required><el-input v-model="reminderFm.name" /></el-form-item>
                <el-form-item :label="t('renewal_reminder_page.form.channel')" required><el-select v-model="reminderFm.channel" style="width:100%"><el-option v-for="key in reminderChKeys" :key="key" :label="reminderChLabel(key)" :value="key" /></el-select></el-form-item>
                <el-form-item :label="t('renewal_reminder_page.form.days_before')" required><el-input-number v-model="reminderFm.days_before" :min="0" :max="365" style="width:100%" /></el-form-item>
                <el-form-item :label="t('renewal_reminder_page.form.subject')" v-if="reminderFm.channel !== 'sms'"><el-input v-model="reminderFm.subject" :placeholder="t('renewal_reminder_page.form.subject_ph')" /></el-form-item>
                <el-form-item :label="t('renewal_reminder_page.form.content')" v-if="reminderFm.channel !== 'sms'"><el-input v-model="reminderFm.content" type="textarea" :rows="4" :placeholder="t('renewal_reminder_page.form.content_ph')" /></el-form-item>
                <el-form-item :label="t('renewal_reminder_page.form.sms_content')" v-if="reminderFm.channel === 'sms'"><el-input v-model="reminderFm.sms_content" type="textarea" :rows="3" maxlength="500" show-word-limit :placeholder="t('renewal_reminder_page.form.sms_content_ph')" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="reminderDlg.visible = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="reminderSave" :loading="reminderSaving">{{ reminderDlg.editing ? t('actions.save') : t('actions.create') }}</el-button></template>
        </el-dialog>

        <!-- 催缴项详情对话框 -->
        <el-dialog v-model="dnDetailVis" :title="t('dunning_page.detail_title')" width="65%" top="5vh">
            <div v-if="dnDetailData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item :label="t('dunning_page.col_customer')">{{ dnDetailData.customer?.name || t('dunning_page.na') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_invoice_no')">{{ dnDetailData.invoice?.invoice_no || t('dunning_page.na') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_amount')">{{ fmtMoney(dnDetailData.amount_due) }} {{ dnDetailData.currency }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_status')"><el-tag :type="dnStTag(dnDetailData.status)" size="small">{{ dnStLbl(dnDetailData.status) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_current_stage')"><el-tag :type="dnStageTag(dnDetailData.current_stage)">{{ dnStageLbl(dnDetailData.current_stage) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_attempt_count')">{{ dnDetailData.attempt_count }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_strategy')">{{ dnDetailData.strategy?.name || t('dunning_page.default_strategy') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_enqueued_at')">{{ fmtDate(dnDetailData.enqueued_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_next_action')">{{ fmtDate(dnDetailData.next_action_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('dunning_page.col_notes')">{{ dnDetailData.notes || '-' }}</el-descriptions-item>
                </el-descriptions>
                <h4 class="mb-2">{{ t('dunning_page.log_records') }}</h4>
                <el-table :data="dnDetailData.logs ?? []" size="small" stripe max-height="400">
                    <el-table-column prop="attempt_number" :label="t('dunning_page.col_attempt_no')" width="70" />
                    <el-table-column :label="t('dunning_page.col_actions')" width="130"><template #default="{ row }"><el-tag :type="dnActTag(row.action_taken)" size="small">{{ dnActLbl(row.action_taken) }}</el-tag></template></el-table-column>
                    <el-table-column :label="t('dunning_page.col_channel')" width="100"><template #default="{ row }">{{ row.channel || '-' }}</template></el-table-column>
                    <el-table-column :label="t('dunning_page.col_success')" width="70"><template #default="{ row }"><el-tag :type="row.success ? 'success' : 'danger'" size="small">{{ row.success ? t('dunning_page.yes') : t('dunning_page.no') }}</el-tag></template></el-table-column>
                    <el-table-column prop="error_message" :label="t('dunning_page.col_error')" min-width="200" show-overflow-tooltip />
                    <el-table-column :label="t('dunning_page.col_time')" width="170"><template #default="{ row }">{{ fmtDate(row.actioned_at) }}</template></el-table-column>
                </el-table>
            </div>
        </el-dialog>

        <!-- 催缴策略编辑对话框 -->
        <el-dialog v-model="dnStratVis" :title="dnEditingStrat ? t('dunning_page.edit_strategy_title') : t('dunning_page.new_strategy_title')" width="75%" top="5vh">
            <el-form :model="dnStratFm" label-width="120px" v-loading="dnStratSaving">
                <el-form-item :label="t('dunning_page.col_name')" required><el-input v-model="dnStratFm.name" :placeholder="t('dunning_page.ph_strategy_name')" /></el-form-item>
                <el-form-item :label="t('dunning_page.col_slug')" required><el-input v-model="dnStratFm.slug" :placeholder="t('dunning_page.ph_strategy_slug')" :disabled="!!dnEditingStrat" /></el-form-item>
                <el-form-item :label="t('dunning_page.col_description')"><el-input v-model="dnStratFm.description" type="textarea" :rows="2" /></el-form-item>
                <el-form-item :label="t('dunning_page.label_max_attempts')"><el-input-number v-model="dnStratFm.max_attempts" :min="1" :max="20" /></el-form-item>
                <el-form-item :label="t('dunning_page.label_applicable_plans')"><el-select v-model="dnStratFm.applicable_plans" multiple clearable :placeholder="t('dunning_page.ph_all_plans')" style="width:100%"><el-option v-for="p in dnAllPlans" :key="p" :label="p" :value="p" /></el-select></el-form-item>
                <el-form-item :label="t('dunning_page.col_sort_order')"><el-input-number v-model="dnStratFm.sort_order" :min="0" /></el-form-item>
                <el-divider>{{ t('dunning_page.stage_config_divider') }}</el-divider>
                <div v-for="(stage, idx) in dnStratFm.stages" :key="idx" class="dn-stage-item">
                    <el-row :gutter="8" class="mb-2">
                        <el-col :span="4"><el-form-item :label="t('dunning_page.stage_n', { n: idx + 1 })" label-width="60"><el-input-number v-model="stage.day" :min="0" :max="365" :placeholder="t('dunning_page.ph_days')" /></el-form-item></el-col>
                        <el-col :span="5"><el-select v-model="stage.action" :placeholder="t('dunning_page.ph_action')" style="width:100%"><el-option v-for="opt in dnStageActOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-col>
                        <el-col :span="4"><el-select v-model="stage.channel" :placeholder="t('dunning_page.ph_channel')" style="width:100%"><el-option v-for="opt in dnChOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-col>
                        <el-col :span="9"><el-input v-model="stage.subject" :placeholder="t('dunning_page.ph_subject')" /></el-col>
                        <el-col :span="2"><el-button type="danger" :icon="Delete" circle size="small" @click="dnRemStage(idx)" /></el-col>
                    </el-row>
                </div>
                <el-button type="primary" @click="dnAddStage">+ {{ t('dunning_page.add_stage') }}</el-button>
            </el-form>
            <template #footer><el-button @click="dnStratVis = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="dnSaveStrat" :loading="dnStratSaving">{{ t('actions.save') }}</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh, Plus, Delete, Promotion, DataAnalysis } from '@element-plus/icons-vue';
import * as echarts from 'echarts';
import renewalApi from '@/api/renewal';
import autoRenewalApi from '@/api/autoRenewal';
import reminderApi from '@/api/renewalReminder';
import dunningApi from '@/api/dunning';

const { t, locale } = useI18n();
const mainTab = ref('renewal');

// ===== Tab 1: 续期管理 =====
const renewalActiveTab = ref('expiring');
const stats = reactive({ licenses: {}, subscriptions: {}, estimated_renewal_amount: 0 });
const statCardsRow1 = computed(() => [
    { key: 'expiring_7d', label: t('renewal_page.stats.expiring_7d'), value: stats.licenses?.expiring_7d ?? '-', valueClass: 'warning', onClick: () => { renewalActiveTab.value = 'expiring'; filterDays.value = 7; fetchExpiring(); } },
    { key: 'expiring_14d', label: t('renewal_page.stats.expiring_14d'), value: stats.licenses?.expiring_14d ?? '-', valueClass: (stats.licenses?.expiring_14d ?? 0) > 0 ? 'warning' : '', onClick: () => { renewalActiveTab.value = 'expiring'; filterDays.value = 14; fetchExpiring(); } },
    { key: 'expiring_30d', label: t('renewal_page.stats.expiring_30d'), value: stats.licenses?.expiring_30d ?? '-', valueClass: '', onClick: () => { renewalActiveTab.value = 'expiring'; filterDays.value = 30; fetchExpiring(); } },
    { key: 'expired', label: t('licenses_page.stat_expired'), value: stats.licenses?.expired ?? '-', valueClass: 'danger', onClick: () => { renewalActiveTab.value = 'expired'; fetchExpired(); } },
]);
const statCardsRow2 = computed(() => [
    { key: 'expired_30d', label: t('renewal_page.stats.expired_30d'), value: stats.licenses?.expired_30d ?? '-', valueClass: 'info' },
    { key: 'sub_expiring_7d', label: t('renewal_page.stats.sub_expiring_7d'), value: stats.subscriptions?.expiring_7d ?? '-', valueClass: '' },
    { key: 'sub_expiring_30d', label: t('renewal_page.stats.sub_expiring_30d'), value: stats.subscriptions?.expiring_30d ?? '-', valueClass: '' },
    { key: 'estimated_amount', label: t('renewal_page.stats.estimated_amount'), value: `¥${fmtMoney(stats.estimated_renewal_amount)}`, valueClass: 'money' },
]);
const filterDayOptions = computed(() => [7, 14, 30, 60].map(v => ({ value: v, label: t('renewal_page.filter.days', { n: v }) })));
const expiredRangeOptions = computed(() => [{ label: t('renewal_page.filter.range_7d'), value: 7 }, { label: t('renewal_page.filter.range_30d'), value: 30 }, { label: t('renewal_page.filter.range_90d'), value: 90 }, { label: t('licenses_page.all'), value: 0 }]);
const expiringLicenses = ref([]); const loading = ref(false); const expiringPage = ref(1); const expiringPerPage = ref(20); const expiringTotal = ref(0);
const expiringSearch = ref(''); const filterDays = ref(30); const selectedExpiring = ref([]);
const expiredLicenses = ref([]); const expiredPage = ref(1); const expiredPerPage = ref(20); const expiredTotal = ref(0); const expiredSearch = ref(''); const expiredDaysAgo = ref(30);
const activityLogs = ref([]); const activityLoading = ref(false);
const showRenewDialog = ref(false); const renewTarget = ref(null); const renewDays = ref(365); const renewNotify = ref(true); const renewing = ref(false);
const showBatchRenew = ref(false); const batchRenewDays = ref(365); const batchRenewNotify = ref(true);

async function fetchExpiring() { loading.value = true; try { const res = await renewalApi.expiringLicenses({ days: filterDays.value, search: expiringSearch.value || undefined, page: expiringPage.value, per_page: expiringPerPage.value }); expiringLicenses.value = res.data?.data || []; expiringTotal.value = res.data?.meta?.total || 0; } catch { ElMessage.error(t('renewal_page.messages.fetch_expiring_failed')); } finally { loading.value = false; } }
async function fetchExpired() { loading.value = true; try { const res = await renewalApi.expiredLicenses({ days_ago: expiredDaysAgo.value, search: expiredSearch.value || undefined, page: expiredPage.value, per_page: expiredPerPage.value }); expiredLicenses.value = res.data?.data || []; expiredTotal.value = res.data?.meta?.total || 0; } catch { ElMessage.error(t('renewal_page.messages.fetch_expired_failed')); } finally { loading.value = false; } }
async function fetchActivityLog() { activityLoading.value = true; try { const res = await renewalApi.activityLog(); activityLogs.value = res.data?.data || []; } catch { /* silent */ } finally { activityLoading.value = false; } }
function handleRenewalTabChange() { if (renewalActiveTab.value === 'expiring') fetchExpiring(); else if (renewalActiveTab.value === 'expired') fetchExpired(); else if (renewalActiveTab.value === 'log') fetchActivityLog(); }
function openRenewDialog(license) { renewTarget.value = license; renewDays.value = 365; renewNotify.value = true; showRenewDialog.value = true; }
async function confirmRenew() { renewing.value = true; try { await renewalApi.renew(renewTarget.value.id, { days: renewDays.value, notify: renewNotify.value }); ElMessage.success(t('renewal_page.messages.renew_ok', { n: renewDays.value })); showRenewDialog.value = false; await fetchExpiring(); await fetchStats(); } catch (err) { ElMessage.error(err.response?.data?.message || t('renewal_page.messages.renew_fail')); } finally { renewing.value = false; } }
async function confirmBatchRenew() { renewing.value = true; try { const res = await renewalApi.batchRenew({ license_ids: selectedExpiring.value, days: batchRenewDays.value, notify: batchRenewNotify.value }); ElMessage.success(res.data?.message || t('renewal_page.messages.batch_renew_ok')); showBatchRenew.value = false; selectedExpiring.value = []; await fetchExpiring(); await fetchStats(); } catch (err) { ElMessage.error(err.response?.data?.message || t('renewal_page.messages.batch_renew_fail')); } finally { renewing.value = false; } }
function getExpiryTagType(row) { if (!row.days_until_expiry) return 'info'; if (row.days_until_expiry <= 7) return 'danger'; if (row.days_until_expiry <= 14) return 'warning'; if (row.days_until_expiry <= 30) return ''; return 'info'; }
function viewLicense(row) { window.open(`/admin/licenses/${row.id}`, '_blank'); }
async function fetchStats() { try { const res = await renewalApi.stats(); Object.assign(stats, res.data?.data || {}); } catch { /* silent */ } }

// ===== Tab 2: 自动续费 =====
const autoTab = ref('plans'); const autoLoading = ref(false); const autoPlans = ref([]); const autoSubs = ref([]); const autoDash = reactive({});
const autoStatCards = computed(() => [
    { key: 'active_plans', label: t('auto_renewal_page.stats.active_plans') }, { key: 'active_subscriptions', label: t('auto_renewal_page.stats.active_subscriptions') },
    { key: 'renewals_30d', label: t('auto_renewal_page.stats.renewals_30d') }, { key: 'failed_30d', label: t('auto_renewal_page.stats.failed_30d') },
]);
async function autoFetchDash() { const { data: res } = await autoRenewalApi.dashboard(); Object.assign(autoDash, res.data || {}); }
async function autoFetchPlans() { const { data: res } = await autoRenewalApi.plans(); autoPlans.value = res.data?.data || res.data || []; }
async function autoFetchSubs() { const { data: res } = await autoRenewalApi.subscriptions(); autoSubs.value = res.data?.data || res.data || []; }
async function autoRefreshAll() { autoLoading.value = true; try { await Promise.all([autoFetchDash(), autoFetchPlans(), autoFetchSubs()]); } catch { ElMessage.error(t('messages.load_failed')); } finally { autoLoading.value = false; } }
async function autoHandleAction(action, row) { try { await autoRenewalApi[action](row.id); ElMessage.success(t('messages.success')); await autoRefreshAll(); } catch (e) { ElMessage.error(e?.response?.data?.error?.message || t('messages.failed')); } }

// ===== Tab 3: 续费提醒 =====
const reminderTab = ref('templates'); const reminderLoading = ref(false);
const reminderChKeys = ['mail', 'sms', 'in_app']; const reminderStKeys = ['pending', 'sent', 'failed'];
const reminderChOpts = computed(() => Object.fromEntries(reminderChKeys.map(k => [k, t('renewal_reminder_page.channels.' + k)])));
const reminderStOpts = computed(() => Object.fromEntries(reminderStKeys.map(k => [k, t('renewal_reminder_page.status.' + k)])));
const reminderDlg = ref({ visible: false, editing: null }); const reminderDlgTitle = computed(() => reminderDlg.value.editing ? t('renewal_reminder_page.dialog.edit_title') : t('renewal_reminder_page.dialog.create_title'));
function reminderChLabel(c) { return reminderChOpts.value[c] || c; } function reminderStLabel(s) { return reminderStOpts.value[s] || s; }
const reminderAnalytics = ref({}); const reminderSuggestions = ref([]);
const reminderTmpls = ref([]); const reminderTmplLoading = ref(false); const reminderTmplFlt = ref({ channel: '' });
const reminderLogEntries = ref([]); const reminderLogLoading = ref(false); const reminderLogFlt = ref({ status: '', channel: '' });
const reminderProDue = ref(false);
const reminderFm = ref({ name: '', channel: 'mail', days_before: 30, subject: '', content: '', sms_content: '' });
const reminderSaving = ref(false);
const channelChartRef = ref(null); let channelChart = null;

function fmtTs(ts) { if (!ts) return '-'; const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'; return new Date(ts).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
async function reminderLoadAnalytics() { try { const res = await reminderApi.getConversionAnalytics(); reminderAnalytics.value = res.data || {}; } catch (e) { console.error('Failed to load analytics', e); } }
async function reminderLoadSuggestions() { try { const res = await reminderApi.getOptimizationSuggestions(); reminderSuggestions.value = res.data || []; } catch (e) { console.error('Failed to load suggestions', e); } }
async function reminderLoadTmpls() { reminderTmplLoading.value = true; try { const params = {}; if (reminderTmplFlt.value.channel) params.channel = reminderTmplFlt.value.channel; const res = await reminderApi.getTemplates(params); reminderTmpls.value = res.data?.data || res.data || []; } catch (e) { console.error('Failed to load templates', e); } finally { reminderTmplLoading.value = false; } }
async function reminderLoadLogs() { reminderLogLoading.value = true; try { const params = {}; if (reminderLogFlt.value.status) params.status = reminderLogFlt.value.status; if (reminderLogFlt.value.channel) params.channel = reminderLogFlt.value.channel; const res = await reminderApi.getReminderLogs(params); reminderLogEntries.value = res.data?.data || res.data || []; } catch (e) { console.error('Failed to load logs', e); } finally { reminderLogLoading.value = false; } }
async function reminderRefreshAll() { reminderLoading.value = true; await Promise.all([reminderLoadAnalytics(), reminderLoadSuggestions(), reminderLoadTmpls(), reminderLoadLogs()]); await nextTick(); reminderRenderChart(); reminderLoading.value = false; }
function reminderRenderChart() { if (!channelChartRef.value || !reminderAnalytics.value.channel_stats) return; if (channelChart) channelChart.dispose(); channelChart = echarts.init(channelChartRef.value); const stats2 = reminderAnalytics.value.channel_stats || {}; const data = Object.entries(stats2).map(([k, v]) => ({ name: reminderChLabel(k), value: v.total || 0 })); if (data.length === 0) return; channelChart.setOption({ tooltip: { trigger: 'item', formatter: '{b}: {c}' }, series: [{ type: 'pie', radius: ['40%', '70%'], data, label: { show: true, formatter: '{b}: {c}' } }] }); }
function reminderShowTemplateDialog(row) { reminderDlg.value.editing = row; if (row) { reminderFm.value = { name: row.name, channel: row.channel, days_before: row.days_before, subject: row.subject || '', content: row.content || '', sms_content: row.sms_content || '' }; } else { reminderFm.value = { name: '', channel: 'mail', days_before: 30, subject: '', content: '', sms_content: '' }; } reminderDlg.value.visible = true; }
function reminderSave() { reminderSaving.value = true; const data = { ...reminderFm.value }; const call = reminderDlg.value.editing ? reminderApi.updateTemplate(reminderDlg.value.editing.id, data) : reminderApi.createTemplate(data); call.then(() => { ElMessage.success(reminderDlg.value.editing ? t('renewal_reminder_page.messages.template_updated') : t('renewal_reminder_page.messages.template_created')); reminderDlg.value.visible = false; reminderLoadTmpls(); }).catch(e => ElMessage.error(t('messages.failed') + ': ' + (e.response?.data?.message || e.message))).finally(() => reminderSaving.value = false); }
async function reminderToggleTmpl(row) { await reminderApi.updateTemplate(row.id, { is_active: row.is_active }); }
function reminderDeleteTmpl(row) { ElMessageBox.confirm(t('renewal_reminder_page.confirm_delete', { name: row.name }), t('actions.confirm'), { type: 'warning' }).then(() => { reminderApi.deleteTemplate(row.id).then(() => { ElMessage.success(t('renewal_reminder_page.messages.deleted')); reminderLoadTmpls(); }); }).catch(() => {}); }
async function reminderHandleProcessDue() { reminderProDue.value = true; try { const res = await reminderApi.processDue(); const d = res.data || {}; ElMessage.success(t('renewal_reminder_page.messages.process_done', { sent: d.sent || 0, failed: d.failed || 0 })); reminderLoadLogs(); reminderLoadAnalytics(); } catch (e) { ElMessage.error(t('renewal_reminder_page.messages.process_failed') + ': ' + (e.response?.data?.message || e.message)); } finally { reminderProDue.value = false; } }
watch(() => reminderTmplFlt.value.channel, () => reminderLoadTmpls());
watch(() => reminderLogFlt.value.status, () => reminderLoadLogs());
watch(() => reminderLogFlt.value.channel, () => reminderLoadLogs());
watch(locale, () => { nextTick(() => reminderRenderChart()); });

// ===== Tab 4: 智能催缴 =====
const dnTab = ref('queue'); const dnLoading = ref(false); const dnScanning = ref(false); const dnRunning = ref(false);
const dnDash = reactive({ total_active: 0, total_resolved: 0, total_failed: 0, total_due_amount: 0, by_stage: [], overdue_trend: [], action_distribution: {} });
const dnQL = ref([]); const dnQPg = ref(1); const dnQTot = ref(0); const dnQF = reactive({ status: '', search: '' });
const dnDetailVis = ref(false); const dnDetailData = ref(null);
const dnStrats = ref([]); const dnAllPlans = ref([]);
const dnStratVis = ref(false); const dnEditingStrat = ref(null); const dnStratSaving = ref(false);
const dnDefStage = () => ({ day: 0, action: 'send_reminder', channel: 'email', subject: '' });
const dnStratFm = reactive({ name: '', slug: '', description: '', max_attempts: 5, applicable_plans: [], sort_order: 0, stages: [dnDefStage()] });
const dnLogL = ref([]); const dnLogPg = ref(1); const dnLogTot = ref(0); const dnLogF = reactive({ action_taken: '' });
const dnDateLoc = computed(() => locale.value === 'zh_CN' ? 'zh-CN' : 'en-US');

const dnStageLbls = computed(() => [t('dunning_page.stage_0'), t('dunning_page.stage_1'), t('dunning_page.stage_2'), t('dunning_page.stage_3'), t('dunning_page.stage_4'), t('dunning_page.stage_5'), t('dunning_page.stage_6')]);
const dnStLbls = computed(() => ({ pending: t('dunning_page.st_pending'), in_progress: t('dunning_page.st_in_progress'), paid: t('dunning_page.st_paid'), resolved: t('dunning_page.st_resolved'), failed: t('dunning_page.st_failed'), expired: t('dunning_page.st_expired') }));
const dnActLbls = computed(() => ({ send_reminder: t('dunning_page.act_send_reminder'), send_warning: t('dunning_page.act_send_warning'), retry_payment: t('dunning_page.act_retry_payment'), downgrade: t('dunning_page.act_downgrade'), suspend: t('dunning_page.act_suspend'), escalate: t('dunning_page.act_escalate'), resolve: t('dunning_page.act_resolve') }));
const dnLogActLbls = computed(() => ({ ...dnActLbls.value, escalate: t('dunning_page.act_escalate_log') }));
const dnQOpts = computed(() => [{ label: t('dunning_page.st_pending'), value: 'pending' }, { label: t('dunning_page.st_in_progress'), value: 'in_progress' }, { label: t('dunning_page.st_resolved'), value: 'resolved' }, { label: t('dunning_page.st_failed'), value: 'failed' }]);
const dnLogAOpts = computed(() => [{ label: t('dunning_page.act_send_reminder'), value: 'send_reminder' }, { label: t('dunning_page.act_send_warning'), value: 'send_warning' }, { label: t('dunning_page.act_retry_payment'), value: 'retry_payment' }, { label: t('dunning_page.act_downgrade'), value: 'downgrade' }, { label: t('dunning_page.act_suspend'), value: 'suspend' }, { label: t('dunning_page.act_escalate_log'), value: 'escalate' }, { label: t('dunning_page.act_resolve'), value: 'resolve' }]);
const dnStageActOpts = computed(() => [{ label: t('dunning_page.act_send_reminder'), value: 'send_reminder' }, { label: t('dunning_page.act_send_warning'), value: 'send_warning' }, { label: t('dunning_page.act_retry_payment'), value: 'retry_payment' }, { label: t('dunning_page.act_downgrade'), value: 'downgrade' }, { label: t('dunning_page.act_suspend'), value: 'suspend' }, { label: t('dunning_page.act_escalate'), value: 'escalate' }]);
const dnChOpts = computed(() => [{ label: t('dunning_page.ch_email'), value: 'email' }, { label: t('dunning_page.ch_sms'), value: 'sms' }, { label: t('dunning_page.ch_email_sms'), value: 'email_and_sms' }, { label: t('dunning_page.ch_payment_gateway'), value: 'payment_gateway' }, { label: t('dunning_page.ch_none'), value: 'none' }]);

function dnStageLbl(s) { return dnStageLbls.value[s] || t('dunning_page.stage_fallback', { n: s }); }
function dnStageTag(s) { const m = ['', 'warning', 'warning', 'danger', 'danger', 'danger', 'info']; return m[s] || 'info'; }
function dnStLbl(s) { return dnStLbls.value[s] || s; }
function dnStTag(s) { const m = { pending: 'info', in_progress: 'warning', paid: 'success', resolved: 'success', failed: 'danger', expired: 'info' }; return m[s] || 'info'; }
function dnActLbl(a) { return dnLogActLbls.value[a] || dnActLbls.value[a] || a; }
function dnActTag(a) { const m = { send_reminder: '', send_warning: 'warning', retry_payment: 'danger', downgrade: 'warning', suspend: 'danger', escalate: 'info', resolve: 'success' }; return m[a] || ''; }

async function dnRefresh() { dnLoading.value = true; try { await Promise.all([dnFetchDash(), dnFetchQ(), dnFetchStrats()]); ElMessage.success(t('dunning_page.msg_refreshed')); } catch (e) { ElMessage.error(t('dunning_page.msg_load_failed')); } finally { dnLoading.value = false; } }
async function dnFetchDash() { try { const { data } = await dunningApi.dashboard(); Object.assign(dnDash, data); } catch (e) { console.error('Failed to fetch dunning dashboard', e); } }
async function dnFetchQ(page = 1) { try { const params = { page, per_page: 20 }; if (dnQF.status) params.status = dnQF.status; if (dnQF.search) params.search = dnQF.search; const { data } = await dunningApi.queue(params); dnQL.value = data.data || []; dnQPg.value = data.current_page || page; dnQTot.value = data.total || 0; } catch (e) { console.error('Failed to fetch dunning queue', e); } }
function dnResetQF() { dnQF.status = ''; dnQF.search = ''; dnFetchQ(1); }
async function dnShowQDetail(row) { dnDetailVis.value = true; dnDetailData.value = null; try { const { data } = await dunningApi.showQueue(row.id); dnDetailData.value = data; } catch (e) { ElMessage.error(t('dunning_page.msg_detail_failed')); } }
async function dnResolve(row) { try { await ElMessageBox.confirm(t('dunning_page.confirm_resolve', { id: row.id }), t('actions.confirm'), { type: 'info' }); } catch { return; } try { await dunningApi.resolve(row.id); ElMessage.success(t('dunning_page.msg_resolved')); await dnFetchQ(dnQPg.value); await dnFetchDash(); } catch (e) { ElMessage.error(t('messages.failed')); } }
async function dnFetchStrats() { try { const { data } = await dunningApi.strategies(); dnStrats.value = data || []; } catch { console.error('Failed to fetch strategies'); } }
function dnOpenStrat(strat = null) { dnEditingStrat.value = strat; dnStratVis.value = true; if (strat) { Object.assign(dnStratFm, { name: strat.name, slug: strat.slug, description: strat.description || '', max_attempts: strat.max_attempts ?? 5, applicable_plans: strat.applicable_plans || [], sort_order: strat.sort_order ?? 0, stages: (strat.stages || []).length ? strat.stages : [dnDefStage()] }); } else { Object.assign(dnStratFm, { name: '', slug: '', description: '', max_attempts: 5, applicable_plans: [], sort_order: 0, stages: [dnDefStage()] }); } }
function dnAddStage() { dnStratFm.stages.push(dnDefStage()); }
function dnRemStage(idx) { if (dnStratFm.stages.length <= 1) return; dnStratFm.stages.splice(idx, 1); }
async function dnSaveStrat() { dnStratSaving.value = true; try { const p = { ...dnStratFm }; if (dnEditingStrat.value) { await dunningApi.updateStrategy(dnEditingStrat.value.id, p); ElMessage.success(t('dunning_page.msg_strategy_updated')); } else { await dunningApi.storeStrategy(p); ElMessage.success(t('dunning_page.msg_strategy_created')); } dnStratVis.value = false; await dnFetchStrats(); } catch (e) { ElMessage.error(t('dunning_page.msg_save_failed')); } finally { dnStratSaving.value = false; } }
async function dnDelStrat(strat) { try { await dunningApi.destroyStrategy(strat.id); ElMessage.success(t('dunning_page.msg_strategy_deleted')); await dnFetchStrats(); } catch (e) { ElMessage.error(t('dunning_page.msg_delete_failed')); } }
async function dnToggleStrat(strat) { try { await dunningApi.updateStrategy(strat.id, { is_active: strat.is_active }); } catch (e) { strat.is_active = !strat.is_active; ElMessage.error(t('dunning_page.msg_update_failed')); } }
async function dnScan() { dnScanning.value = true; try { const { data } = await dunningApi.scanOverdue(); ElMessage.success(t('dunning_page.msg_scan_done', { count: data.enqueued })); await dnFetchDash(); await dnFetchQ(1); } catch (e) { ElMessage.error(t('dunning_page.msg_scan_failed')); } finally { dnScanning.value = false; } }
async function dnRun() { dnRunning.value = true; try { const { data } = await dunningApi.run(); ElMessage.success(t('dunning_page.msg_run_done', { count: data.processed })); await dnRefresh(); } catch (e) { ElMessage.error(t('dunning_page.msg_run_failed')); } finally { dnRunning.value = false; } }
async function dnFetchLogs(page = 1) { try { const params = { page, per_page: 50 }; if (dnLogF.action_taken) params.action_taken = dnLogF.action_taken; const { data } = await dunningApi.logs(params); dnLogL.value = data.data || []; dnLogPg.value = data.current_page || page; dnLogTot.value = data.total || 0; } catch (e) { console.error('Failed to fetch logs', e); } }

// ===== Shared helpers =====
function fmtMoney(val) { if (val === null || val === undefined) return '-'; return Number(val).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function fmtDate(date) { if (!date) return '-'; return new Date(date).toLocaleString(dnDateLoc.value, { hour12: false }); }

// Lazy-load on tab switch
watch(mainTab, (tab) => {
    if (tab === 'auto' && autoPlans.value.length === 0) autoRefreshAll();
    if (tab === 'reminder' && reminderTmpls.value.length === 0) reminderRefreshAll();
    if (tab === 'dunning' && dnQL.value.length === 0) dnRefresh();
});

onMounted(async () => { await fetchStats(); await fetchExpiring(); });
</script>

<style scoped>
.renewal-center-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.mb-4 { margin-bottom: 16px; } .mb-2 { margin-bottom: 8px; } .mt-2 { margin-top: 8px; } .ml-1 { margin-left: 4px; }
.tab-header { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px; }
.tab-bar { display: flex; align-items: center; margin-bottom: 12px; }
.pagination-wrap { display: flex; justify-content: center; margin-top: 16px; }
.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }
.text-gray-400 { color: #c0c4cc; }
.error-text { color: #f56c6c; font-size: 12px; }
.sug-card :deep(.el-card__body) { padding-top: 0; }

.stat-card { text-align: center; cursor: pointer; transition: transform 0.1s; }
.stat-card:hover { transform: translateY(-2px); }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.danger { color: var(--el-color-danger); }
.stat-value.info { color: var(--el-color-info); }
.stat-value.money { color: var(--el-color-success); }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }

.auto-stat-value { font-size: 28px; font-weight: 700; }
.auto-stat-label { color: #909399; margin-top: 4px; }

.rmd-stat-card { text-align: center; }
.rmd-stat-card .rmd-stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.rmd-stat-card .rmd-stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.rmd-stat-card .rmd-stat-sub { font-size: 11px; color: #c0c4cc; margin-top: 2px; }
.rmd-stat-value.rmd-danger { color: #f56c6c; }
.rmd-stat-value.rmd-success { color: #67c23a; }

.dn-stat { text-align: center; cursor: default; }
.dn-stat .dn-stat-v { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
.dn-stat .dn-stat-l { font-size: 0.8rem; color: #909399; margin-top: 4px; }
.dn-stat.dn-ok .dn-stat-v { color: #67c23a; }
.dn-stat.dn-err .dn-stat-v { color: #f56c6c; }
.dn-stat.dn-warn .dn-stat-v { color: #e6a23c; }
.dn-stage-item { padding: 8px; border: 1px solid #ebeef5; border-radius: 4px; margin-bottom: 8px; background: #fafafa; }
</style>
