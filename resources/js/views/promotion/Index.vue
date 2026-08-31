<template>
    <div class="promo-center-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('promo_center.title') }}</h2>
                <span class="header-subtitle">{{ t('promo_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="mainTab" type="border-card" @tab-change="onMainTabChange">
            <!-- ============ Tab 1: 促销活动 + 合同 + 优惠券 ============ -->
            <el-tab-pane :label="t('promo_center.tab_promo')" name="promo">
                <el-row :gutter="12" class="mb-4" v-if="promoStats">
                    <el-col :span="6"><el-card shadow="never"><div class="s-label">{{ t('promotion_page.stats.active_promotions') }}</div><div class="s-val">{{ promoStats.active || 0 }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="s-label">{{ t('promotion_page.stats.total_discount') }}</div><div class="s-val">{{ (promoStats.total_discount_given || 0).toLocaleString() }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="s-label">{{ t('promotion_page.stats.active_contracts') }}</div><div class="s-val">{{ promoStats.active_contracts ?? 0 }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="never"><div class="s-label">{{ t('promotion_page.stats.active_coupons') }}</div><div class="s-val">{{ promoStats.active_coupons || 0 }}</div></el-card></el-col>
                </el-row>

                <el-card shadow="never">
                    <el-tabs v-model="promoSubTab">
                        <!-- 促销活动列表 -->
                        <el-tab-pane :label="t('promotion_page.tabs.promotions')" name="promotions">
                            <div class="flex justify-between mb-3">
                                <span class="text-sm text-gray-400">{{ t('promotion_page.count_promotions', { n: promos.length }) }}</span>
                                <el-button type="primary" @click="openPromoForm()">{{ t('promotion_page.create_promotion') }}</el-button>
                            </div>
                            <el-table :data="promos" v-loading="promoLoading && promoSubTab === 'promotions'" stripe>
                                <el-table-column prop="name" :label="t('promotion_page.cols.name')" min-width="160" />
                                <el-table-column :label="t('promotion_page.cols.type')" width="100"><template #default="{ row }">{{ promoTypeOpts.find(opt => opt.value === row.type)?.label || row.type }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.discount')" width="100"><template #default="{ row }">{{ row.discount_type === 'percentage' ? (row.discount_value||0)+'%' : row.discount_type === 'fixed_amount' ? '¥'+(row.discount_value||0) : row.discount_type }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.status')" width="80"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'paused' ? 'warning' : row.status === 'expired' ? 'danger' : 'info'" size="small">{{ promoStLbl(row.status) }}</el-tag></template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.start')" width="120"><template #default="{ row }">{{ fmtDateShort(row.starts_at) }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.end')" width="120"><template #default="{ row }">{{ row.ends_at ? fmtDateShort(row.ends_at) : t('promotion_page.unlimited') }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.usage_limit')" width="100"><template #default="{ row }">{{ row.usage_count }}{{ row.usage_limit ? '/'+row.usage_limit : '' }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.actions')" width="180" fixed="right">
                                    <template #default="{ row }">
                                        <el-button size="small" @click="openPromoForm()">{{ t('actions.edit') }}</el-button>
                                        <el-button v-if="row.status === 'draft' || row.status === 'paused'" size="small" type="success" @click="publishPromo(row)">{{ t('promotion_page.row_actions.publish') }}</el-button>
                                        <el-button v-if="row.status === 'active'" size="small" type="warning" @click="pausePromo(row)">{{ t('promotion_page.row_actions.pause') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="promoPg.current_page" :page-size="15" :total="promoPg.total" layout="prev,pager,next,total" @current-change="loadPromos" /></div>
                        </el-tab-pane>

                        <!-- 企业年框合同 -->
                        <el-tab-pane :label="t('promotion_page.tabs.contracts')" name="contracts">
                            <div class="flex justify-between mb-3">
                                <span class="text-sm text-gray-400">{{ t('promotion_page.count_contracts', { n: contracts.length }) }}</span>
                                <el-button type="primary" @click="openContractForm()">{{ t('promotion_page.create_contract') }}</el-button>
                            </div>
                            <el-table :data="contracts" v-loading="promoLoading && promoSubTab === 'contracts'" stripe>
                                <el-table-column prop="contract_number" :label="t('promotion_page.cols.contract_number')" width="140" />
                                <el-table-column prop="name" :label="t('promotion_page.cols.name')" min-width="150" />
                                <el-table-column :label="t('promotion_page.cols.customer')" width="120"><template #default="{ row }">{{ row.customer?.name || '-' }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.amount')" width="110"><template #default="{ row }">¥{{ row.total_value?.toLocaleString() }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.discount')" width="60"><template #default="{ row }">{{ row.discount_rate }}%</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.status')" width="90"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'expired' ? 'danger' : row.status === 'pending_approval' ? 'warning' : 'info'" size="small">{{ contractStLbl(row.status) }}</el-tag></template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.period')" width="180"><template #default="{ row }">{{ fmtDateShort(row.start_date) }} ~ {{ fmtDateShort(row.end_date) }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.actions')" width="180" fixed="right">
                                    <template #default="{ row }">
                                        <el-button size="small" @click="showContractDetail(row)">{{ t('promotion_page.row_actions.detail') }}</el-button>
                                        <el-button v-if="row.status === 'draft'" size="small" @click="openContractForm()">{{ t('actions.edit') }}</el-button>
                                        <el-button v-if="row.status === 'pending_approval'" size="small" type="success" @click="approveContract(row, 'approved')">{{ t('actions.approve') }}</el-button>
                                        <el-button v-if="row.status === 'pending_approval'" size="small" type="danger" @click="approveContract(row, 'rejected')">{{ t('actions.reject') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="contractPg.current_page" :page-size="15" :total="contractPg.total" layout="prev,pager,next,total" @current-change="loadContracts" /></div>
                        </el-tab-pane>

                        <!-- 优惠券管理 -->
                        <el-tab-pane :label="t('promotion_page.tabs.coupons')" name="coupons">
                            <div class="flex justify-between mb-3">
                                <span class="text-sm text-gray-400">{{ t('promotion_page.count_coupons', { n: coupons.length }) }}</span>
                                <el-button type="primary" @click="openCouponForm()">{{ t('promotion_page.create_coupon') }}</el-button>
                            </div>
                            <el-table :data="coupons" v-loading="promoLoading && promoSubTab === 'coupons'" stripe>
                                <el-table-column prop="code" :label="t('promotion_page.cols.code')" width="120" />
                                <el-table-column prop="name" :label="t('promotion_page.cols.name')" min-width="140" />
                                <el-table-column :label="t('promotion_page.cols.type')" width="90"><template #default="{ row }">{{ couponTypeLbl(row.type) }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.value')" width="80"><template #default="{ row }">{{ row.type === 'percentage' ? row.value+'%' : '¥'+row.value }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.status')" width="70"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ promoStLbl(row.status) }}</el-tag></template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.usage_cap')" width="90"><template #default="{ row }">{{ row.usage_count || 0 }}{{ row.usage_limit ? '/'+row.usage_limit : '' }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.validity')" width="180"><template #default="{ row }">{{ fmtDateShort(row.starts_at) }} ~ {{ row.expires_at ? fmtDateShort(row.expires_at) : t('promotion_page.unlimited') }}</template></el-table-column>
                                <el-table-column :label="t('promotion_page.cols.priority')" width="60"><template #default="{ row }">{{ row.priority || 0 }}</template></el-table-column>
                            </el-table>
                            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="couponPg.current_page" :page-size="15" :total="couponPg.total" layout="prev,pager,next,total" @current-change="loadCoupons" /></div>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </el-tab-pane>

            <!-- ============ Tab 2: 促销引擎 (规则引擎) ============ -->
            <el-tab-pane :label="t('promo_center.tab_engine')" name="engine">
                <div class="tab-header">
                    <el-button @click="engLoadStats" :loading="engLoading">{{ t('promotion_engine_page.refresh_stats') }}</el-button>
                    <el-button type="primary" @click="engShowDialog(null)"><el-icon><Plus /></el-icon> {{ t('promotion_engine_page.btn_create') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover"><div class="eng-s-label">{{ t('promotion_engine_page.stats.total_rules') }}</div><div class="eng-s-val">{{ engStats.total_rules }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="eng-s-active"><div class="eng-s-label">{{ t('promotion_engine_page.stats.active_rules') }}</div><div class="eng-s-val">{{ engStats.active_rules }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="eng-s-info"><div class="eng-s-label">{{ t('promotion_engine_page.stats.total_redemptions') }}</div><div class="eng-s-val">{{ engStats.total_redemptions }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="eng-s-warn"><div class="eng-s-label">{{ t('promotion_engine_page.stats.total_discount') }}</div><div class="eng-s-val">¥{{ engStats.total_discount_amount }}</div></el-card></el-col>
                </el-row>
                <el-card>
                    <template #header>
                        <div class="card-header-flex">
                            <span>{{ t('promotion_engine_page.card_title') }}</span>
                            <div>
                                <el-select v-model="engFilterStatus" :placeholder="t('promotion_engine_page.filters.status')" clearable class="mr-2" style="width:120px" @change="engLoadRules"><el-option v-for="opt in engStatusFlt" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select>
                                <el-select v-model="engFilterType" :placeholder="t('promotion_engine_page.filters.type')" clearable style="width:140px" @change="engLoadRules"><el-option v-for="opt in engTypeFlt" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select>
                            </div>
                        </div>
                    </template>
                    <el-table :data="engRules" stripe v-loading="engLoadingRules" @row-click="engShowDialog">
                        <el-table-column prop="name" :label="t('promotion_engine_page.cols.name')" min-width="160" />
                        <el-table-column :label="t('promotion_engine_page.cols.type')" width="100"><template #default="{ row }"><el-tag :type="row.type === 'amount_off' ? 'danger' : row.type === 'percent_off' ? 'warning' : 'info'" size="small">{{ engTypeLbl(row.type) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('promotion_engine_page.cols.threshold')" width="120" align="center"><template #default="{ row }"><span v-if="row.condition_type === 'subtotal'">{{ t('promotion_engine_page.threshold.subtotal', { value: row.condition_value }) }}</span><span v-else-if="row.condition_type === 'quantity'">{{ t('promotion_engine_page.threshold.quantity', { value: row.condition_value }) }}</span><span v-else>{{ t('promotion_engine_page.em_dash') }}</span></template></el-table-column>
                        <el-table-column :label="t('promotion_engine_page.cols.discount')" width="120" align="center"><template #default="{ row }"><span v-if="row.type === 'amount_off'">{{ t('promotion_engine_page.discount_fmt.amount_off', { value: row.discount_value }) }}</span><span v-else-if="row.type === 'percent_off'">{{ row.discount_value }}{{ t('promotion_engine_page.units.percent') }}</span><span v-else-if="row.type === 'buy_x_get_y'">{{ t('promotion_engine_page.discount_fmt.buy_x_get_y', { buy: row.buy_quantity, free: row.free_quantity }) }}</span><span v-else>¥{{ row.discount_value }}</span></template></el-table-column>
                        <el-table-column :label="t('promotion_engine_page.cols.stackable')" width="100" align="center"><template #default="{ row }"><el-tag v-if="row.stackable_with_coupon || row.stackable_with_other_rules" size="small" type="success">{{ t('promotion_engine_page.stackable_tag') }}</el-tag><span v-else class="text-muted">{{ t('promotion_engine_page.em_dash') }}</span></template></el-table-column>
                        <el-table-column prop="usage_count" :label="t('promotion_engine_page.cols.usage')" width="110" align="center"><template #default="{ row }">{{ row.usage_count }}{{ row.usage_limit ? '/'+row.usage_limit : '' }}</template></el-table-column>
                        <el-table-column :label="t('promotion_engine_page.cols.status')" width="90"><template #default="{ row }"><el-tag :type="engStTag(row.status)" size="small">{{ engStLbl(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('promotion_engine_page.cols.time')" width="170"><template #default="{ row }"><div class="time-col"><span v-if="row.starts_at" class="time-text">{{ row.starts_at.slice(0, 10) }}</span><span v-if="row.ends_at" class="time-text"> ~ {{ row.ends_at.slice(0, 10) }}</span></div></template></el-table-column>
                        <el-table-column :label="t('promotion_engine_page.cols.actions')" width="180" fixed="right">
                            <template #default="{ row }">
                                <el-button-group>
                                    <el-button v-if="row.status === 'draft'" size="small" type="primary" @click.stop="engToggleStatus(row, 'active')">{{ t('promotion_engine_page.row_actions.publish') }}</el-button>
                                    <el-button v-if="row.status === 'active'" size="small" type="warning" @click.stop="engToggleStatus(row, 'paused')">{{ t('promotion_engine_page.row_actions.pause') }}</el-button>
                                    <el-button v-if="row.status === 'paused'" size="small" type="primary" @click.stop="engToggleStatus(row, 'active')">{{ t('promotion_engine_page.row_actions.resume') }}</el-button>
                                    <el-button size="small" type="danger" @click.stop="engDeleteRule(row)">{{ t('actions.delete') }}</el-button>
                                </el-button-group>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="pagination-wrap" v-if="engPg.total > engPg.per_page"><el-pagination v-model:current-page="engPg.current_page" :page-size="engPg.per_page" :total="engPg.total" layout="prev, pager, next" @current-change="engLoadRules" /></div>
                </el-card>
            </el-tab-pane>

            <!-- ============ Tab 3: 定时促销 (日历 + 列表) ============ -->
            <el-tab-pane :label="t('promo_center.tab_scheduled')" name="scheduled">
                <div class="tab-header">
                    <el-button type="primary" @click="schShowFormDialog(null)">{{ t('scheduled_promotion_page.btn_create') }}</el-button>
                    <el-button @click="schLoadAll" :loading="schLoading"><el-icon><Refresh /></el-icon> {{ t('scheduled_promotion_page.refresh') }}</el-button>
                </div>
                <el-row :gutter="16" class="mb-4">
                    <el-col :xs="12" :sm="4"><el-card shadow="hover" size="small"><div class="sch-s-label">{{ t('scheduled_promotion_page.stats.total') }}</div><div class="sch-s-val">{{ schStats.total }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="4"><el-card shadow="hover" size="small"><div class="sch-s-label">{{ t('scheduled_promotion_page.stats.active') }}</div><div class="sch-s-val text-success">{{ schStats.active }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="4"><el-card shadow="hover" size="small"><div class="sch-s-label">{{ t('scheduled_promotion_page.stats.scheduled') }}</div><div class="sch-s-val text-warning">{{ schStats.scheduled }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="4"><el-card shadow="hover" size="small"><div class="sch-s-label">{{ t('scheduled_promotion_page.stats.expired') }}</div><div class="sch-s-val text-info">{{ schStats.expired }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="4"><el-card shadow="hover" size="small"><div class="sch-s-label">{{ t('scheduled_promotion_page.stats.draft') }}</div><div class="sch-s-val">{{ schStats.draft }}</div></el-card></el-col>
                    <el-col :xs="12" :sm="4"><el-card shadow="hover" size="small"><div class="sch-s-label">{{ t('scheduled_promotion_page.stats.total_budget') }}</div><div class="sch-s-val">¥{{ schFmtNum(schStats.total_budget) }}</div></el-card></el-col>
                </el-row>
                <el-card shadow="hover" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('scheduled_promotion_page.calendar.title') }}</span>
                            <div class="calendar-nav">
                                <el-button size="small" @click="schChangeMonth(-1)">{{ t('scheduled_promotion_page.calendar.prev_month') }}</el-button>
                                <span class="current-month">{{ schCurrentMonth }}</span>
                                <el-button size="small" @click="schChangeMonth(1)">{{ t('scheduled_promotion_page.calendar.next_month') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <div v-if="schCalendarEvents.length" class="calendar-list">
                        <div v-for="evt in schCalendarEvents" :key="evt.id" class="calendar-event" :style="{ borderLeftColor: evt.color || '#0f172a' }">
                            <div class="event-date">{{ schFormatDate(evt.start_at) }}</div>
                            <div class="event-info"><div class="event-title">{{ evt.title }}</div><div class="event-meta"><el-tag :type="evt.status === 'active' ? 'success' : evt.status === 'scheduled' ? 'warning' : 'info'" size="small">{{ schStLbl(evt.status) }}</el-tag><span v-if="evt.end_at" class="event-end">{{ t('scheduled_promotion_page.calendar.event_end', { date: schFormatDate(evt.end_at) }) }}</span></div></div>
                        </div>
                    </div>
                    <el-empty v-else :description="t('scheduled_promotion_page.calendar.empty')" :image-size="60" />
                </el-card>
                <el-card shadow="hover">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('scheduled_promotion_page.list_title') }}</span>
                            <el-form :inline="true" size="small">
                                <el-form-item><el-select v-model="schFilters.status" :placeholder="t('scheduled_promotion_page.filters.all_status')" clearable style="width:120px" @change="schLoadList"><el-option v-for="opt in schStatusFlt" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                                <el-form-item><el-select v-model="schFilters.type" :placeholder="t('scheduled_promotion_page.filters.all_types')" clearable style="width:130px" @change="schLoadList"><el-option v-for="opt in promoTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                                <el-form-item><el-input v-model="schFilters.search" :placeholder="t('scheduled_promotion_page.filters.search')" clearable style="width:160px" @input="schOnSearch" /></el-form-item>
                            </el-form>
                        </div>
                    </template>
                    <el-table :data="schPromos" v-loading="schLoadingList" stripe style="width:100%">
                        <el-table-column prop="name" :label="t('scheduled_promotion_page.cols.name')" min-width="160" />
                        <el-table-column :label="t('promotion_page.cols.type')" width="100"><template #default="{ row }">{{ schTypeMap[row.type] || row.type }}</template></el-table-column>
                        <el-table-column :label="t('promotion_page.cols.discount')" width="100"><template #default="{ row }">{{ row.discount_type === 'percentage' ? row.discount_value + '%' : '¥' + row.discount_value }}</template></el-table-column>
                        <el-table-column :label="t('promotion_page.cols.status')" width="80"><template #default="{ row }"><el-tag :type="schStType(row.status)" size="small">{{ schStLbl(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('scheduled_promotion_page.cols.starts_at')" width="150"><template #default="{ row }">{{ row.starts_at }}</template></el-table-column>
                        <el-table-column :label="t('scheduled_promotion_page.cols.ends_at')" width="150"><template #default="{ row }">{{ row.ends_at || t('promotion_engine_page.em_dash') }}</template></el-table-column>
                        <el-table-column :label="t('scheduled_promotion_page.cols.usage')" width="80" align="center"><template #default="{ row }">{{ row.usage_count }}/{{ row.usage_limit || '∞' }}</template></el-table-column>
                        <el-table-column :label="t('promotion_page.cols.actions')" width="200" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="schShowFormDialog(row)">{{ t('actions.edit') }}</el-button>
                                <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" size="small" type="primary" @click="schPublish(row)">{{ t('promotion_page.row_actions.publish') }}</el-button>
                                <el-button v-if="row.status === 'active'" size="small" type="warning" @click="schPause(row)">{{ t('promotion_page.row_actions.pause') }}</el-button>
                                <el-button v-if="row.status === 'draft'" size="small" type="danger" @click="schRemove(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination v-if="schPg.total > schPg.per_page" background layout="prev,pager,next,total" :total="schPg.total" :page-size="schPg.per_page" :current-page="schPg.current_page" @current-change="schOnPageChange" style="margin-top:16px;justify-content:center" />
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- ===== Dialogs ===== -->

        <!-- 促销活动创建/编辑 -->
        <el-dialog v-model="promoDlg" :title="t('promotion_page.dialogs.create_promotion')" width="600px">
            <el-form :model="promoFm" label-width="120px">
                <el-form-item :label="t('promotion_page.form.name')"><el-input v-model="promoFm.name" /></el-form-item>
                <el-form-item :label="t('promotion_page.form.promo_type')"><el-select v-model="promoFm.type" class="w-full"><el-option v-for="opt in promoTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                <el-form-item :label="t('promotion_page.form.description')"><el-input v-model="promoFm.description" type="textarea" :rows="2" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.discount_type')"><el-select v-model="promoFm.discount_type"><el-option v-for="opt in discTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.discount_value')"><el-input v-model.number="promoFm.discount_value" type="number" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.max_discount')"><el-input v-model.number="promoFm.max_discount" type="number" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.min_order_amount')"><el-input v-model.number="promoFm.min_order_amount" type="number" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.budget')"><el-input v-model.number="promoFm.budget" type="number" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.usage_limit')"><el-input v-model.number="promoFm.usage_limit" type="number" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.usage_limit_per_customer')"><el-input v-model.number="promoFm.usage_limit_per_customer" type="number" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.starts_at')"><el-date-picker v-model="promoFm.starts_at" type="datetime" class="w-full" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.ends_at')"><el-date-picker v-model="promoFm.ends_at" type="datetime" class="w-full" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                </el-row>
            </el-form>
            <template #footer><el-button @click="promoDlg = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="savePromo">{{ t('actions.create') }}</el-button></template>
        </el-dialog>

        <!-- 合同详情 -->
        <el-dialog v-model="contractDetailVis" :title="t('promotion_page.dialogs.contract_detail')" width="700px">
            <div v-if="contractDetail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('promotion_page.detail.contract_number')">{{ contractDetail.contract_number }}</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.name')">{{ contractDetail.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.customer')">{{ contractDetail.customer?.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.total_value')">¥{{ contractDetail.total_value?.toLocaleString() }}</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.discount_rate')">{{ contractDetail.discount_rate }}%</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.status')">{{ contractStLbl(contractDetail.status) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.validity')">{{ fmtDateShort(contractDetail.start_date) }} ~ {{ fmtDateShort(contractDetail.end_date) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('promotion_page.detail.auto_renew')">{{ contractDetail.auto_renew ? t('promotion_page.yes') : t('promotion_page.no') }}</el-descriptions-item>
                </el-descriptions>
                <div v-if="contractDetail.licensed_items?.length" class="mt-3"><el-divider>{{ t('promotion_page.detail.licensed_items') }}</el-divider><div v-for="(item, i) in contractDetail.licensed_items" :key="i" class="mb-1 text-sm">{{ t('promotion_page.detail.licensed_item_fmt', { name: item.name, quantity: item.quantity, price: item.unit_price }) }}</div></div>
            </div>
        </el-dialog>

        <!-- 合同创建 -->
        <el-dialog v-model="contractDlg" :title="t('promotion_page.dialogs.create_contract')" width="600px">
            <el-form :model="contractFm" label-width="120px">
                <el-form-item :label="t('promotion_page.form.name')"><el-input v-model="contractFm.name" /></el-form-item>
                <el-form-item :label="t('promotion_page.form.customer_id')"><el-input v-model.number="contractFm.customer_id" type="number" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.total_value')"><el-input v-model.number="contractFm.total_value" type="number" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.discount_rate')"><el-input v-model.number="contractFm.discount_rate" type="number" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.start_date')"><el-date-picker v-model="contractFm.start_date" type="date" class="w-full" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.end_date')"><el-date-picker v-model="contractFm.end_date" type="date" class="w-full" /></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t('promotion_page.form.notes')"><el-input v-model="contractFm.notes" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="contractDlg = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="saveContract">{{ t('actions.create') }}</el-button></template>
        </el-dialog>

        <!-- 优惠券创建 -->
        <el-dialog v-model="couponDlg" :title="t('promotion_page.dialogs.create_coupon')" width="500px">
            <el-form :model="couponFm" label-width="110px">
                <el-form-item :label="t('promotion_page.form.code')"><el-input v-model="couponFm.code" :placeholder="t('promotion_page.form.code_auto')" /></el-form-item>
                <el-form-item :label="t('promotion_page.form.name')"><el-input v-model="couponFm.name" /></el-form-item>
                <el-form-item :label="t('promotion_page.form.coupon_type')"><el-select v-model="couponFm.type"><el-option v-for="opt in couponTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                <el-form-item :label="t('promotion_page.form.value')"><el-input v-model.number="couponFm.value" type="number" /></el-form-item>
                <el-form-item :label="t('promotion_page.form.usage_limit')"><el-input v-model.number="couponFm.usage_limit" type="number" :placeholder="t('promotion_page.optional')" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.starts_at')"><el-date-picker v-model="couponFm.starts_at" type="datetime" class="w-full" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.expires_at')"><el-date-picker v-model="couponFm.expires_at" type="datetime" class="w-full" :placeholder="t('promotion_page.optional')" /></el-form-item></el-col>
                </el-row>
            </el-form>
            <template #footer><el-button @click="couponDlg = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="saveCoupon">{{ t('actions.create') }}</el-button></template>
        </el-dialog>

        <!-- 促销引擎编辑对话框 -->
        <el-dialog v-model="engDlg" :title="engEditing ? t('promotion_engine_page.dialogs.edit') : t('promotion_engine_page.dialogs.create')" width="750px" :close-on-click-modal="false" @close="engResetForm">
            <el-form :model="engFm" label-width="120px" :rules="engFmRules" ref="engFmRef" v-loading="engSaving">
                <el-tabs v-model="engFmTab">
                    <el-tab-pane :label="t('promotion_engine_page.tabs.basic')" name="basic">
                        <el-form-item :label="t('promotion_engine_page.form.name')" prop="name"><el-input v-model="engFm.name" maxlength="200" /></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.type')" prop="type"><el-radio-group v-model="engFm.type"><el-radio v-for="opt in engTypeFmOpts" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio></el-radio-group></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.description')"><el-input v-model="engFm.description" type="textarea" :rows="2" maxlength="1000" /></el-form-item>
                    </el-tab-pane>
                    <el-tab-pane :label="t('promotion_engine_page.tabs.conditions')" name="conditions">
                        <el-form-item :label="t('promotion_engine_page.form.condition_type')" prop="condition_type"><el-radio-group v-model="engFm.condition_type"><el-radio v-for="opt in engCondOpts" :key="opt.value" :value="opt.value">{{ opt.label }}</el-radio></el-radio-group></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.condition_value')" prop="condition_value"><el-input-number v-model="engFm.condition_value" :min="0" :precision="2" style="width:200px" /></el-form-item>
                        <template v-if="engFm.type !== 'buy_x_get_y'">
                            <el-form-item :label="t('promotion_engine_page.form.discount_value')" prop="discount_value"><el-input-number v-model="engFm.discount_value" :min="0" :precision="2" style="width:200px" /><span class="ml-2 text-muted">{{ engFm.type === 'percent_off' ? t('promotion_engine_page.units.percent') : t('promotion_engine_page.units.currency') }}</span></el-form-item>
                            <el-form-item :label="t('promotion_engine_page.form.max_discount')"><el-input-number v-model="engFm.max_discount" :min="0" :precision="2" :placeholder="t('promotion_engine_page.placeholders.no_limit')" style="width:200px" /><span class="ml-2 text-muted">{{ t('promotion_engine_page.hints.max_discount') }}</span></el-form-item>
                        </template>
                        <template v-if="engFm.type === 'buy_x_get_y'">
                            <el-form-item :label="t('promotion_engine_page.form.buy_quantity')" prop="buy_quantity"><el-input-number v-model="engFm.buy_quantity" :min="1" style="width:200px" /></el-form-item>
                            <el-form-item :label="t('promotion_engine_page.form.free_quantity')" prop="free_quantity"><el-input-number v-model="engFm.free_quantity" :min="1" style="width:200px" /></el-form-item>
                        </template>
                        <el-form-item :label="t('promotion_engine_page.form.min_order_amount')"><el-input-number v-model="engFm.min_order_amount" :min="0" :precision="2" style="width:200px" /></el-form-item>
                    </el-tab-pane>
                    <el-tab-pane :label="t('promotion_engine_page.tabs.tiers')">
                        <p class="text-muted">{{ t('promotion_engine_page.tier.hint') }}</p>
                        <div v-for="(tier, i) in engFm.tiers" :key="i" class="tier-row">
                            <el-row :gutter="8" align="middle">
                                <el-col :span="6"><el-input-number v-model="tier.from" :min="0" :precision="2" :placeholder="t('promotion_engine_page.tier.from_ph')" size="small" style="width:100%" /></el-col>
                                <el-col :span="1" class="text-center">~</el-col>
                                <el-col :span="6"><el-input-number v-model="tier.to" :min="0" :precision="2" :placeholder="t('promotion_engine_page.tier.to_ph')" size="small" style="width:100%" /></el-col>
                                <el-col :span="5"><el-select v-model="tier.type" size="small" style="width:100%"><el-option v-for="opt in engTierTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-col>
                                <el-col :span="4"><el-input-number v-model="tier.value" :min="0" :precision="2" size="small" style="width:100%" /></el-col>
                                <el-col :span="2"><el-button type="danger" size="small" @click="engRemoveTier(i)">×</el-button></el-col>
                            </el-row>
                        </div>
                        <el-button size="small" @click="engAddTier" class="mt-2">{{ t('promotion_engine_page.tier.add') }}</el-button>
                    </el-tab-pane>
                    <el-tab-pane :label="t('promotion_engine_page.tabs.scope')">
                        <el-form-item :label="t('promotion_engine_page.form.applicable_products')"><el-select v-model="engFm.applicable_products" multiple filterable remote :remote-method="engSearchProducts" :placeholder="t('promotion_engine_page.placeholders.all_products')" style="width:100%"><el-option v-for="p in engProductOpts" :key="p.id" :label="p.name" :value="p.id" /></el-select></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.excluded_products')"><el-select v-model="engFm.excluded_products" multiple filterable :placeholder="t('promotion_engine_page.placeholders.exclude_products')" style="width:100%"><el-option v-for="p in engProductOpts" :key="p.id" :label="p.name" :value="p.id" /></el-select></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.stackable_coupon')"><el-switch v-model="engFm.stackable_with_coupon" /><span class="ml-2 text-muted">{{ t('promotion_engine_page.hints.stackable_coupon') }}</span></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.stackable_rules')"><el-switch v-model="engFm.stackable_with_other_rules" /><span class="ml-2 text-muted">{{ t('promotion_engine_page.hints.stackable_rules') }}</span></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.priority')"><el-input-number v-model="engFm.priority" :min="0" :max="999" style="width:120px" /><span class="ml-2 text-muted">{{ t('promotion_engine_page.hints.priority') }}</span></el-form-item>
                    </el-tab-pane>
                    <el-tab-pane :label="t('promotion_engine_page.tabs.limits')">
                        <el-form-item :label="t('promotion_engine_page.form.usage_limit')"><el-input-number v-model="engFm.usage_limit" :min="1" :placeholder="t('promotion_engine_page.placeholders.no_limit')" style="width:200px" /></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.usage_limit_per_customer')"><el-input-number v-model="engFm.usage_limit_per_customer" :min="1" :placeholder="t('promotion_engine_page.placeholders.no_limit')" style="width:200px" /></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.budget')"><el-input-number v-model="engFm.budget" :min="0" :precision="2" :placeholder="t('promotion_engine_page.placeholders.no_limit')" style="width:200px" /></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.starts_at')"><el-date-picker v-model="engFm.starts_at" type="datetime" :placeholder="t('promotion_engine_page.placeholders.starts_at')" style="width:200px" /></el-form-item>
                        <el-form-item :label="t('promotion_engine_page.form.ends_at')"><el-date-picker v-model="engFm.ends_at" type="datetime" :placeholder="t('promotion_engine_page.placeholders.ends_at')" style="width:200px" /></el-form-item>
                    </el-tab-pane>
                </el-tabs>
            </el-form>
            <template #footer><el-button @click="engDlg = false" :disabled="engSaving">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="engSaveRule" :loading="engSaving">{{ engEditing ? t('actions.save') : t('actions.create') }}</el-button></template>
        </el-dialog>

        <!-- 定时促销创建/编辑对话框 -->
        <el-dialog v-model="schDlg" :title="schEditing ? t('scheduled_promotion_page.dialogs.edit') : t('promotion_page.dialogs.create_promotion')" width="700px">
            <el-form :model="schFm" label-width="120px" size="small">
                <el-row :gutter="16">
                    <el-col :span="12"><el-form-item :label="t('scheduled_promotion_page.cols.name')" required><el-input v-model="schFm.name" :placeholder="t('scheduled_promotion_page.form.name_ph')" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.promo_type')" required><el-select v-model="schFm.type" style="width:100%"><el-option v-for="opt in promoTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t('promotion_page.form.description')"><el-input v-model="schFm.description" type="textarea" :rows="2" /></el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.discount_type')"><el-select v-model="schFm.discount_type" style="width:100%"><el-option v-for="opt in discTypeOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.discount_value')"><el-input v-model="schFm.discount_value" type="number" min="0" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.max_discount')"><el-input v-model="schFm.max_discount" type="number" min="0" :placeholder="t('promotion_page.unlimited')" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.starts_at')" required><el-date-picker v-model="schFm.starts_at" type="datetime" :placeholder="t('promotion_page.form.starts_at')" style="width:100%" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item :label="t('promotion_page.form.ends_at')"><el-date-picker v-model="schFm.ends_at" type="datetime" :placeholder="t('promotion_page.optional')" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.usage_limit')"><el-input v-model="schFm.usage_limit" type="number" min="0" :placeholder="t('promotion_page.unlimited')" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('scheduled_promotion_page.form.per_customer_limit')"><el-input v-model="schFm.usage_limit_per_customer" type="number" min="0" :placeholder="t('promotion_page.unlimited')" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('promotion_page.form.budget')"><el-input v-model="schFm.budget" type="number" min="0" :placeholder="t('promotion_page.unlimited')" /></el-form-item></el-col>
                </el-row>
                <el-divider />
                <el-row :gutter="16">
                    <el-col :span="8"><el-form-item :label="t('scheduled_promotion_page.form.first_order_only')"><el-switch v-model="schFm.is_first_order_only" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item :label="t('scheduled_promotion_page.form.member_only')"><el-switch v-model="schFm.is_member_only" /></el-form-item></el-col>
                    <el-col :span="8"><el-form-item v-if="schFm.is_member_only" :label="t('scheduled_promotion_page.form.tier_required')"><el-select v-model="schFm.member_tier" style="width:100%"><el-option v-for="opt in schMemberTierOpts" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t('scheduled_promotion_page.form.auto_recover')"><el-switch v-model="schFm.auto_recover" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="schDlg = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" :loading="schSaving" @click="schSaveForm">{{ schEditing ? t('actions.save') : t('actions.create') }}</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh } from '@element-plus/icons-vue';
import promoApi from '@/api/promotions';
import promotionEngineApi from '@/api/promotionEngine';
import {
    getScheduledPromotions, createScheduledPromotion, updateScheduledPromotion,
    publishScheduledPromotion, pauseScheduledPromotion, deleteScheduledPromotion,
    getPromotionStats, getPromotionCalendar,
} from '@/api/scheduledPromotion';

const { t, locale } = useI18n();
const mainTab = ref('promo');
const dateLoc = computed(() => (locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'));

// ===== Shared i18n =====
const PROMO_TYPE_KEYS = ['flash_sale', 'bulk_discount', 'bundle', 'x_for_y', 'free_gift', 'tiered'];
const DISCOUNT_TYPE_KEYS = ['percentage', 'fixed_amount', 'free'];
const COUPON_TYPE_KEYS = ['percentage', 'fixed_amount', 'free_trial'];
const PROMO_STATUS_KEYS = ['draft', 'active', 'paused', 'expired', 'cancelled'];
const CONTRACT_STATUS_KEYS = ['draft', 'pending_approval', 'active', 'expired', 'terminated'];

const promoTypeOpts = computed(() => PROMO_TYPE_KEYS.map(v => ({ value: v, label: t('promotion_page.promo_types.' + v) })));
const discTypeOpts = computed(() => DISCOUNT_TYPE_KEYS.map(v => ({ value: v, label: t('promotion_page.discount_types.' + v) })));
const couponTypeOpts = computed(() => COUPON_TYPE_KEYS.map(v => ({ value: v, label: t('promotion_page.coupon_types.' + v) })));
const promoStLbls = computed(() => Object.fromEntries(PROMO_STATUS_KEYS.map(k => [k, t('promotion_page.promo_status.' + k)])));
const contractStLbls = computed(() => Object.fromEntries(CONTRACT_STATUS_KEYS.map(k => [k, t('promotion_page.contract_status.' + k)])));
const couponTypeLbls = computed(() => Object.fromEntries(COUPON_TYPE_KEYS.map(k => [k, t('promotion_page.coupon_types.' + k)])));

function promoStLbl(s) { return promoStLbls.value[s] || s; }
function contractStLbl(s) { return contractStLbls.value[s] || s; }
function couponTypeLbl(tp) { return couponTypeLbls.value[tp] || tp; }
function fmtDateShort(d) { return d ? new Date(d).toLocaleDateString(dateLoc.value) : '-'; }

// ===== Tab 1: 促销活动 + 合同 + 优惠券 =====
const promoSubTab = ref('promotions');
const promoLoading = ref(false);
const promoStats = ref(null);
const promos = ref([]); const promoPg = reactive({ current_page: 1, total: 0 });
const contracts = ref([]); const contractPg = reactive({ current_page: 1, total: 0 });
const coupons = ref([]); const couponPg = reactive({ current_page: 1, total: 0 });
const promoDlg = ref(false); const promoFm = reactive({ name: '', type: 'flash_sale', description: '', discount_type: 'percentage', discount_value: null, max_discount: null, min_order_amount: null, usage_limit: null, usage_limit_per_customer: null, budget: null, starts_at: '', ends_at: null });
const contractDlg = ref(false); const contractFm = reactive({ name: '', customer_id: null, total_value: null, currency: 'CNY', discount_rate: 0, start_date: '', end_date: '', licensed_items: [], notes: '' });
const contractDetailVis = ref(false); const contractDetail = ref(null);
const couponDlg = ref(false); const couponFm = reactive({ code: '', name: '', type: 'percentage', value: null, usage_limit: null, starts_at: '', expires_at: null, applicable_plans: [] });

async function loadPromoStats() { try { const res = await promoApi.stats(); promoStats.value = res.data.data; } catch {} }
async function loadPromos(page = 1) { promoLoading.value = true; try { const res = await promoApi.list({ page, per_page: 15 }); const d = res.data.data; promos.value = d?.data || d || []; promoPg.total = d?.total || 0; promoPg.current_page = d?.current_page || page; } catch {} finally { promoLoading.value = false; } }
async function loadContracts(page = 1) { promoLoading.value = true; try { const res = await promoApi.listContracts({ page, per_page: 15 }); const d = res.data.data; contracts.value = d?.data || d || []; contractPg.total = d?.total || 0; contractPg.current_page = d?.current_page || page; } catch {} finally { promoLoading.value = false; } }
async function loadCoupons(page = 1) { promoLoading.value = true; try { const res = await promoApi.listCoupons({ page, per_page: 15 }); const d = res.data.data; coupons.value = d?.data || d || []; couponPg.total = d?.total || 0; couponPg.current_page = d?.current_page || page; } catch {} finally { promoLoading.value = false; } }

function openPromoForm() { Object.assign(promoFm, { name: '', type: 'flash_sale', description: '', discount_type: 'percentage', discount_value: null, max_discount: null, min_order_amount: null, usage_limit: null, usage_limit_per_customer: null, budget: null, starts_at: '', ends_at: null }); promoDlg.value = true; }
async function savePromo() { try { await promoApi.create(promoFm); ElMessage.success(t('promotion_page.messages.promo_created')); promoDlg.value = false; loadPromos(promoPg.current_page); loadPromoStats(); } catch { ElMessage.error(t('promotion_page.messages.save_failed')); } }
async function publishPromo(p) { try { await promoApi.publish(p.id); ElMessage.success(t('promotion_page.messages.published')); loadPromos(promoPg.current_page); } catch { ElMessage.error(t('promotion_page.messages.publish_failed')); } }
async function pausePromo(p) { try { await promoApi.pause(p.id); ElMessage.success(t('promotion_page.messages.paused')); loadPromos(promoPg.current_page); } catch { ElMessage.error(t('messages.failed')); } }

function openContractForm() { Object.assign(contractFm, { name: '', customer_id: null, total_value: null, currency: 'CNY', discount_rate: 0, start_date: '', end_date: '', licensed_items: [], notes: '' }); contractDlg.value = true; }
async function saveContract() { try { await promoApi.createContract(contractFm); ElMessage.success(t('promotion_page.messages.contract_created')); contractDlg.value = false; loadContracts(contractPg.current_page); } catch { ElMessage.error(t('promotion_page.messages.save_failed')); } }
async function showContractDetail(c) { try { const res = await promoApi.showContract(c.id); contractDetail.value = res.data.data; contractDetailVis.value = true; } catch {} }
async function approveContract(c, status) { try { await promoApi.approveContract(c.id, { status }); ElMessage.success(status === 'approved' ? t('promotion_page.messages.approved') : t('promotion_page.messages.rejected')); loadContracts(contractPg.current_page); } catch { ElMessage.error(t('messages.failed')); } }

function openCouponForm() { Object.assign(couponFm, { code: '', name: '', type: 'percentage', value: null, usage_limit: null, starts_at: '', expires_at: null, applicable_plans: [] }); couponDlg.value = true; }
async function saveCoupon() { try { await promoApi.createCoupon(couponFm); ElMessage.success(t('promotion_page.messages.coupon_created')); couponDlg.value = false; loadCoupons(couponPg.current_page); } catch { ElMessage.error(t('promotion_page.messages.save_failed')); } }

// ===== Tab 2: 促销引擎 =====
const engLoading = ref(false); const engLoadingRules = ref(false); const engFilterStatus = ref(''); const engFilterType = ref('');
const engRules = ref([]); const engPg = reactive({ current_page: 1, per_page: 20, total: 0 });
const engStats = reactive({ total_rules: 0, active_rules: 0, total_redemptions: 0, total_discount_amount: 0 });
const engDlg = ref(false); const engEditing = ref(null); const engSaving = ref(false); const engFmRef = ref(null); const engFmTab = ref('basic');
const engProductOpts = ref([]);

const engFm = reactive({ name: '', type: 'amount_off', description: '', condition_type: 'subtotal', condition_value: 0, discount_value: 0, max_discount: null, min_order_amount: 0, applicable_products: [], applicable_categories: [], excluded_products: [], stackable_with_coupon: false, stackable_with_other_rules: false, priority: 0, usage_limit: null, usage_limit_per_customer: null, budget: null, starts_at: null, ends_at: null, tiers: [], buy_quantity: null, free_quantity: null, free_products: [], status: 'draft' });

const engTypeLbls = computed(() => ({ amount_off: t('promotion_engine_page.types.amount_off'), percent_off: t('promotion_engine_page.types.percent_off'), buy_x_get_y: t('promotion_engine_page.types.buy_x_get_y'), fixed_price: t('promotion_engine_page.types.fixed_price') }));
const engStLbls = computed(() => ({ draft: t('promotion_engine_page.status.draft'), active: t('promotion_engine_page.status.active'), paused: t('promotion_engine_page.status.paused'), expired: t('promotion_engine_page.status.expired') }));
const engStatusFlt = computed(() => [{ value: 'draft', label: engStLbls.value.draft }, { value: 'active', label: engStLbls.value.active }, { value: 'paused', label: engStLbls.value.paused }, { value: 'expired', label: engStLbls.value.expired }]);
const engTypeFlt = computed(() => [{ value: 'amount_off', label: engTypeLbls.value.amount_off }, { value: 'percent_off', label: engTypeLbls.value.percent_off }, { value: 'buy_x_get_y', label: engTypeLbls.value.buy_x_get_y }, { value: 'fixed_price', label: engTypeLbls.value.fixed_price }]);
const engTypeFmOpts = computed(() => [{ value: 'amount_off', label: t('promotion_engine_page.types.amount_off_full') }, { value: 'percent_off', label: t('promotion_engine_page.types.percent_off_full') }, { value: 'buy_x_get_y', label: t('promotion_engine_page.types.buy_x_get_y_full') }, { value: 'fixed_price', label: t('promotion_engine_page.types.fixed_price_full') }]);
const engCondOpts = computed(() => [{ value: 'subtotal', label: t('promotion_engine_page.condition_types.subtotal') }, { value: 'quantity', label: t('promotion_engine_page.condition_types.quantity') }, { value: 'items_count', label: t('promotion_engine_page.condition_types.items_count') }]);
const engTierTypeOpts = computed(() => [{ value: 'amount_off', label: t('promotion_engine_page.tier.type_amount_off') }, { value: 'percent_off', label: t('promotion_engine_page.tier.type_percent_off') }]);
const engFmRules = computed(() => ({ name: [{ required: true, message: t('promotion_engine_page.validation.name_required'), trigger: 'blur' }], condition_value: [{ required: true, message: t('promotion_engine_page.validation.condition_value_required'), trigger: 'blur' }], discount_value: [{ required: true, message: t('promotion_engine_page.validation.discount_value_required'), trigger: 'blur' }] }));

function engTypeLbl(t) { return engTypeLbls.value[t] || t; }
function engStLbl(s) { return engStLbls.value[s] || s; }
function engStTag(s) { return { draft: 'info', active: 'success', paused: 'warning', expired: 'danger' }[s] || 'info'; }

async function engLoadStats() { try { const res = await promotionEngineApi.getStats(); Object.assign(engStats, res.data?.data || {}); } catch {} }
async function engLoadRules(page) { engLoadingRules.value = true; try { const params = { page: page || engPg.current_page }; if (engFilterStatus.value) params.status = engFilterStatus.value; if (engFilterType.value) params.type = engFilterType.value; const res = await promotionEngineApi.getRules(params); const data = res.data?.data || {}; engRules.value = data.data || []; engPg.current_page = data.current_page || 1; engPg.per_page = data.per_page || 20; engPg.total = data.total || 0; } catch { ElMessage.error(t('promotion_engine_page.messages.load_failed')); } finally { engLoadingRules.value = false; } }

function engShowDialog(row) {
    if (!row) { engEditing.value = null; engResetForm(); engDlg.value = true; return; }
    engEditing.value = row;
    Object.keys(engFm).forEach(k => { if (k === 'starts_at' || k === 'ends_at') { engFm[k] = row[k] || null; } else { engFm[k] = row[k] !== undefined ? row[k] : engFm[k]; } });
    engFmTab.value = 'basic'; engDlg.value = true;
}
function engResetForm() { Object.assign(engFm, { name: '', type: 'amount_off', description: '', condition_type: 'subtotal', condition_value: 0, discount_value: 0, max_discount: null, min_order_amount: 0, applicable_products: [], applicable_categories: [], excluded_products: [], stackable_with_coupon: false, stackable_with_other_rules: false, priority: 0, usage_limit: null, usage_limit_per_customer: null, budget: null, starts_at: null, ends_at: null, tiers: [], buy_quantity: null, free_quantity: null, free_products: [], status: 'draft' }); engEditing.value = null; }
async function engSaveRule() { const valid = await engFmRef.value?.validate().catch(() => false); if (!valid) return; engSaving.value = true; try { const data = { ...engFm }; if (data.starts_at) data.starts_at = data.starts_at.toISOString(); if (data.ends_at) data.ends_at = data.ends_at.toISOString(); if (data.max_discount === null) delete data.max_discount; if (data.usage_limit === null) delete data.usage_limit; if (data.usage_limit_per_customer === null) delete data.usage_limit_per_customer; if (data.budget === null) delete data.budget; if (engEditing.value) { await promotionEngineApi.updateRule(engEditing.value.id, data); ElMessage.success(t('promotion_engine_page.messages.updated')); } else { await promotionEngineApi.createRule(data); ElMessage.success(t('promotion_engine_page.messages.created')); } engDlg.value = false; await engLoadRules(1); await engLoadStats(); } catch (err) { ElMessage.error(err.response?.data?.message || t('promotion_engine_page.messages.save_failed')); } finally { engSaving.value = false; } }
async function engToggleStatus(row, ns) { try { await promotionEngineApi.toggleStatus(row.id, ns); ElMessage.success(t('promotion_engine_page.messages.status_changed', { status: engStLbl(ns) })); await engLoadRules(); await engLoadStats(); } catch (err) { ElMessage.error(err.response?.data?.message || t('promotion_engine_page.messages.toggle_failed')); } }
async function engDeleteRule(row) { try { const msgKey = row.redemptions_count > 0 ? 'promotion_engine_page.messages.confirm_expire' : 'promotion_engine_page.messages.confirm_delete'; await ElMessageBox.confirm(t(msgKey, { name: row.name }), t('actions.confirm'), { type: 'warning' }); await promotionEngineApi.deleteRule(row.id); ElMessage.success(t('messages.success')); await engLoadRules(); await engLoadStats(); } catch (err) { if (err !== 'cancel') ElMessage.error(err.response?.data?.message || t('messages.failed')); } }
function engAddTier() { engFm.tiers.push({ from: 0, to: null, type: 'amount_off', value: 0 }); }
function engRemoveTier(i) { engFm.tiers.splice(i, 1); }
async function engSearchProducts(query) { try { const { default: productApi } = await import('@/api/product'); const res = await productApi.list({ search: query, per_page: 20 }); engProductOpts.value = res.data?.data?.data || []; } catch { engProductOpts.value = []; } }

// ===== Tab 3: 定时促销 =====
const schLoading = ref(false); const schLoadingList = ref(false); const schSaving = ref(false);
const schDlg = ref(false); const schEditing = ref(null);
const schPromos = ref([]); const schCalendarEvents = ref([]);
const schCurrentMonth = ref(new Date().toISOString().slice(0, 7));
const schStats = reactive({ total: 0, active: 0, scheduled: 0, expired: 0, draft: 0, total_budget: 0 });
const schPg = reactive({ current_page: 1, per_page: 20, total: 0 });
const schFilters = reactive({ status: '', type: '', search: '' });
let schSearchTimer = null;

const schFm = reactive({ name: '', type: 'flash_sale', description: '', discount_type: 'percentage', discount_value: 0, max_discount: null, starts_at: '', ends_at: null, usage_limit: null, usage_limit_per_customer: null, budget: null, is_first_order_only: false, is_member_only: false, member_tier: null, auto_recover: true });
const schTypeMap = computed(() => Object.fromEntries(PROMO_TYPE_KEYS.map(k => [k, t('promotion_page.promo_types.' + k)])));
const schStLbls = computed(() => ({ ...Object.fromEntries(['draft', 'active', 'paused', 'expired', 'cancelled'].map(k => [k, t('promotion_page.promo_status.' + k)])), scheduled: t('scheduled_promotion_page.status.scheduled') }));
const schStatusFlt = computed(() => ['active', 'scheduled', 'draft', 'paused', 'expired'].map(v => ({ value: v, label: schStLbls.value[v] || v })));
const schMemberTierOpts = computed(() => ['silver', 'gold', 'platinum'].map(v => ({ value: v, label: t('scheduled_promotion_page.member_tiers.' + v) })));

function schFmtNum(v) { return v ? Number(v).toLocaleString() : '0.00'; }
function schFormatDate(d) { return d ? new Date(d).toLocaleDateString(dateLoc.value) : t('promotion_engine_page.em_dash'); }
function schStType(s) { return { active: 'success', scheduled: 'warning', draft: 'info', paused: 'warning', expired: 'info', cancelled: 'danger' }[s] || 'info'; }
function schStLbl(s) { return schStLbls.value[s] || s; }

async function schLoadStats() { try { const r = await getPromotionStats(); Object.assign(schStats, r.data || {}); } catch {} }
async function schLoadCalendar() { try { const r = await getPromotionCalendar(schCurrentMonth.value); schCalendarEvents.value = r.data || []; } catch {} }
async function schLoadList(page = 1) { schLoadingList.value = true; schPg.current_page = page; try { const params = { ...schFilters, page, per_page: schPg.per_page }; Object.keys(params).forEach(k => { if (!params[k]) delete params[k] }); const r = await getScheduledPromotions(params); const data = r.data?.data || r.data || []; schPromos.value = Array.isArray(data) ? data : []; Object.assign(schPg, r.data || r.meta || {}); } catch { schPromos.value = []; } finally { schLoadingList.value = false; } }
function schLoadAll() { schLoadStats(); schLoadCalendar(); schLoadList(); }
function schOnSearch() { clearTimeout(schSearchTimer); schSearchTimer = setTimeout(() => schLoadList(), 300); }
function schOnPageChange(p) { schLoadList(p); }
function schChangeMonth(dir) { const [y, m] = schCurrentMonth.value.split('-').map(Number); const d = new Date(y, m - 1 + dir, 1); schCurrentMonth.value = d.toISOString().slice(0, 7); schLoadCalendar(); }

function schShowFormDialog(row) {
    if (row) { schEditing.value = row.id; Object.assign(schFm, { name: row.name, type: row.type, description: row.description || '', discount_type: row.discount_type, discount_value: row.discount_value, max_discount: row.max_discount, starts_at: row.starts_at, ends_at: row.ends_at, usage_limit: row.usage_limit, usage_limit_per_customer: row.usage_limit_per_customer, budget: row.budget, is_first_order_only: row.is_first_order_only, is_member_only: row.is_member_only, member_tier: row.member_tier, auto_recover: row.auto_recover !== false }); }
    else { schEditing.value = null; Object.assign(schFm, { name: '', type: 'flash_sale', description: '', discount_type: 'percentage', discount_value: 0, max_discount: null, starts_at: '', ends_at: null, usage_limit: null, usage_limit_per_customer: null, budget: null, is_first_order_only: false, is_member_only: false, member_tier: null, auto_recover: true }); }
    schDlg.value = true;
}
async function schSaveForm() { schSaving.value = true; try { if (schEditing.value) { await updateScheduledPromotion(schEditing.value, schFm); ElMessage.success(t('scheduled_promotion_page.messages.updated')); } else { await createScheduledPromotion(schFm); ElMessage.success(t('scheduled_promotion_page.messages.created')); } schDlg.value = false; schLoadAll(); } catch {} finally { schSaving.value = false; } }
async function schPublish(row) { try { await publishScheduledPromotion(row.id); ElMessage.success(t('promotion_page.messages.published')); schLoadAll(); } catch {} }
async function schPause(row) { try { await pauseScheduledPromotion(row.id); ElMessage.success(t('promotion_page.messages.paused')); schLoadAll(); } catch {} }
async function schRemove(row) { try { await ElMessageBox.confirm(t('scheduled_promotion_page.messages.confirm_delete', { name: row.name })); await deleteScheduledPromotion(row.id); ElMessage.success(t('scheduled_promotion_page.messages.deleted')); schLoadAll(); } catch {} }

// ===== Lazy loading =====
function onMainTabChange(tab) {
    if (tab === 'engine' && engRules.value.length === 0) { engLoadStats(); engLoadRules(); }
    if (tab === 'scheduled' && schPromos.value.length === 0) schLoadAll();
}

onMounted(() => { loadPromoStats(); loadPromos(); loadContracts(); loadCoupons(); });
</script>

<style scoped>
.promo-center-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.mb-4 { margin-bottom: 16px; } .mb-3 { margin-bottom: 12px; } .mt-2 { margin-top: 8px; } .mt-3 { margin-top: 12px; } .ml-2 { margin-left: 8px; } .mr-2 { margin-right: 8px; }
.tab-header { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }
.flex { display: flex; } .justify-between { justify-content: space-between; } .justify-center { justify-content: center; } .items-center { align-items: center; }
.text-sm { font-size: 12px; } .text-gray-400 { color: #909399; } .text-muted { color: #909399; font-size: 12px; } .text-center { text-align: center; }
.text-success { color: #67C23A; } .text-warning { color: #E6A23C; } .text-info { color: #909399; }
.w-full { width: 100%; }

.s-label { font-size: 12px; color: #909399; } .s-val { font-size: 20px; font-weight: 700; }

.eng-s-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.eng-s-val { font-size: 22px; font-weight: 700; }
.eng-s-active .eng-s-val { color: #67c23a; }
.eng-s-info .eng-s-val { color: #0f172a; }
.eng-s-warn .eng-s-val { color: #e6a23c; }

.card-header-flex { display: flex; justify-content: space-between; align-items: center; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: center; }
.time-col { font-size: 12px; } .time-text { white-space: nowrap; }
.tier-row { margin-bottom: 8px; padding: 8px; background: #f5f7fa; border-radius: 4px; }

.sch-s-label { font-size: 12px; color: #909399; margin-bottom: 2px; }
.sch-s-val { font-size: 20px; font-weight: 700; color: #303133; }

.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.calendar-nav { display: flex; align-items: center; gap: 8px; }
.current-month { font-size: 14px; font-weight: 600; min-width: 80px; text-align: center; }
.calendar-list { display: flex; flex-direction: column; gap: 8px; }
.calendar-event { display: flex; gap: 12px; padding: 10px 12px; border-left: 4px solid; background: #fafafa; border-radius: 4px; }
.event-date { font-size: 13px; color: #606266; min-width: 80px; padding-top: 2px; }
.event-title { font-weight: 600; font-size: 14px; }
.event-meta { display: flex; gap: 8px; align-items: center; margin-top: 4px; }
.event-end { font-size: 12px; color: #909399; }
</style>
