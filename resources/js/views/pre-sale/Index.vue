<template>
  <div class="pre-sale-page">
    <el-tabs v-model="saleMainTab" type="border-card">
      <!-- Tab 1: 预售/众筹 -->
      <el-tab-pane :label="$t('admin.menu.pre_sale')" name="presale">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="mb-4">
          <el-col :span="4" v-for="s in statItems" :key="s.label">
            <el-card shadow="hover" :body-style="{ padding: '16px' }">
              <div class="stat-value text-2xl font-bold" :class="s.color">{{ s.value }}</div>
              <div class="stat-label text-gray-500 text-sm">{{ s.label }}</div>
            </el-card>
          </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card shadow="never">
          <div class="flex justify-between items-center mb-4">
            <div class="flex gap-2">
              <el-button type="primary" @click="openCreate">{{ t('pre_sale_page.buttons.create_campaign') }}</el-button>
              <el-button @click="refresh">{{ t('pre_sale_page.buttons.refresh') }}</el-button>
            </div>
            <div class="flex gap-2">
              <el-select v-model="filters.type" clearable :placeholder="t('pre_sale_page.filters.type_ph')" style="width:120px" @change="search">
                <el-option v-for="opt in typeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
              <el-select v-model="filters.status" clearable :placeholder="t('pre_sale_page.filters.status_ph')" style="width:120px" @change="search">
                <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
              <el-input v-model="filters.search" :placeholder="t('pre_sale_page.filters.search_ph')" clearable style="width:200px" @clear="search" @keyup.enter="search" />
              <el-select v-model="filters.sort" style="width:140px" @change="search">
                <el-option v-for="opt in sortOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </div>
          </div>

          <!-- 活动列表 -->
          <el-table :data="campaigns" v-loading="loading" stripe>
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column :label="t('pre_sale_page.cols.type')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.type === 'crowdfunding' ? 'warning' : 'primary'" size="small">
                  {{ typeLabel(row.type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="name" :label="t('pre_sale_page.cols.name')" min-width="160" show-overflow-tooltip />
            <el-table-column prop="product.name" :label="t('pre_sale_page.cols.product')" width="140" show-overflow-tooltip />
            <el-table-column :label="t('pre_sale_page.cols.status')" width="100">
              <template #default="{ row }">
                <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('pre_sale_page.cols.progress')" width="160">
              <template #default="{ row }">
                <div class="flex items-center gap-2">
                  <el-progress :percentage="row.progress_percent" :stroke-width="12" :status="row.progress_percent >= 100 ? 'success' : ''" style="width:100px" />
                  <span class="text-xs text-gray-500">{{ row.progress_percent }}%</span>
                </div>
              </template>
            </el-table-column>
            <el-table-column :label="t('pre_sale_page.cols.raised')" width="140">
              <template #default="{ row }">
                <span class="text-sm">¥{{ formatMoney(row.raised_amount) }}</span>
                <span class="text-xs text-gray-400 ml-1">/ ¥{{ formatMoney(row.target_amount) }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('pre_sale_page.cols.backers')" width="80">
              <template #default="{ row }">{{ row.current_backers }} / {{ row.target_backers || '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('pre_sale_page.cols.time')" width="280">
              <template #default="{ row }">
                <div class="text-xs">
                  <div>{{ formatDate(row.start_at) }} → {{ formatDate(row.end_at) }}</div>
                  <div v-if="row.is_active" class="text-green-500">{{ t('pre_sale_page.remaining_days', { n: row.remaining_days }) }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column :label="t('pre_sale_page.cols.actions')" width="260" fixed="right">
              <template #default="{ row }">
                <el-button size="small" text @click="viewCampaign(row)">{{ t('pre_sale_page.buttons.detail') }}</el-button>
                <el-button size="small" text v-if="row.status === 'draft'" @click="editCampaign(row)">{{ t('actions.edit') }}</el-button>
                <el-button size="small" text type="primary" v-if="row.status === 'draft'" @click="handlePublish(row)">{{ t('pre_sale_page.buttons.publish') }}</el-button>
                <el-button size="small" text type="primary" v-if="row.status === 'active'" @click="handleCheckStatus(row)">{{ t('pre_sale_page.buttons.check_status') }}</el-button>
                <el-button size="small" text type="success" v-if="row.status === 'success'" @click="handleComplete(row)">{{ t('pre_sale_page.buttons.complete') }}</el-button>
                <el-button size="small" text type="danger" v-if="['draft','active'].includes(row.status)" @click="handleCancel(row)">{{ t('pre_sale_page.buttons.cancel_campaign') }}</el-button>
                <el-button size="small" text type="danger" v-if="['draft','failed','cancelled'].includes(row.status)" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="flex justify-center mt-4" v-if="pagination">
            <el-pagination
              v-model:current-page="pagination.current_page"
              :page-size="pagination.per_page"
              :total="pagination.total"
              layout="prev, pager, next, total"
              @current-change="loadData"
            />
          </div>
        </el-card>

        <!-- 创建/编辑活动对话框 -->
        <el-dialog v-model="dialogVisible" :title="isEditing ? t('pre_sale_page.dialogs.edit') : t('pre_sale_page.dialogs.create')" width="720px" :close-on-click-modal="false">
          <el-form :model="form" label-width="120px" v-loading="saving">
            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item :label="t('pre_sale_page.form.campaign_type')" required>
                  <el-radio-group v-model="form.type" :disabled="isEditing">
                    <el-radio value="pre_sale">{{ t('pre_sale_page.types.pre_sale') }}</el-radio>
                    <el-radio value="crowdfunding">{{ t('pre_sale_page.types.crowdfunding') }}</el-radio>
                  </el-radio-group>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item :label="t('pre_sale_page.form.product')" required>
                  <el-select v-model="form.product_id" filterable remote :disabled="isEditing" :remote-method="searchProducts" :loading="productLoading" :placeholder="t('pre_sale_page.form.search_product_ph')" style="width:100%">
                    <el-option v-for="p in productOptions" :key="p.id" :label="p.name" :value="p.id" />
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>

            <el-form-item :label="t('pre_sale_page.form.name')" required>
              <el-input v-model="form.name" maxlength="200" show-word-limit />
            </el-form-item>
            <el-form-item :label="t('pre_sale_page.form.description')">
              <el-input v-model="form.description" type="textarea" :rows="3" />
            </el-form-item>

            <el-row :gutter="20">
              <el-col :span="8">
                <el-form-item :label="t('pre_sale_page.form.start_at')" required>
                  <el-date-picker v-model="form.start_at" type="datetime" :placeholder="t('pre_sale_page.form.select_time_ph')" style="width:100%" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item :label="t('pre_sale_page.form.end_at')" required>
                  <el-date-picker v-model="form.end_at" type="datetime" :placeholder="t('pre_sale_page.form.select_time_ph')" style="width:100%" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item :label="t('pre_sale_page.form.estimated_delivery')">
                  <el-date-picker v-model="form.estimated_delivery_at" type="datetime" :placeholder="t('pre_sale_page.form.optional_ph')" style="width:100%" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="8">
                <el-form-item :label="t('pre_sale_page.form.target_amount')">
                  <el-input v-model="form.target_amount" type="number" min="0" :placeholder="t('pre_sale_page.form.target_amount_ph')" />
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item :label="t('pre_sale_page.form.deposit_rate')">
                  <el-input v-model="form.deposit_rate" type="number" min="0" max="100">
                    <template #suffix>%</template>
                  </el-input>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item :label="t('pre_sale_page.form.deposit_amount')">
                  <el-input v-model="form.deposit_amount" type="number" min="0" :placeholder="t('pre_sale_page.form.deposit_amount_ph')" />
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="20">
              <el-col :span="12">
                <el-form-item :label="t('pre_sale_page.form.target_backers')">
                  <el-input v-model="form.target_backers" type="number" min="1" :placeholder="t('pre_sale_page.form.target_backers_ph')" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item :label="t('pre_sale_page.form.currency')">
                  <el-select v-model="form.currency" style="width:100%">
                    <el-option label="CNY" value="CNY" />
                    <el-option label="USD" value="USD" />
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>

          <template #footer>
            <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" @click="save" :loading="saving">{{ t('actions.save') }}</el-button>
          </template>
        </el-dialog>

        <!-- 活动详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t('pre_sale_page.dialogs.detail')" width="800px">
          <div v-if="detail" v-loading="detailLoading">
            <el-descriptions :column="2" border>
              <el-descriptions-item :label="t('pre_sale_page.detail.name')">{{ detail.name }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.type')">{{ typeLabel(detail.type) }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.status')">
                <el-tag :type="statusTag(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
              </el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.product')">{{ detail.product?.name }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.target_amount')">¥{{ formatMoney(detail.target_amount) }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.raised')">¥{{ formatMoney(detail.raised_amount) }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.progress')">
                <el-progress :percentage="detail.progress_percent" :status="detail.progress_percent >= 100 ? 'success' : ''" />
              </el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.backers')">{{ detail.current_backers }} / {{ detail.target_backers || '-' }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.time')">{{ formatDate(detail.start_at) }} → {{ formatDate(detail.end_at) }}</el-descriptions-item>
              <el-descriptions-item :label="t('pre_sale_page.detail.remaining')">{{ t('pre_sale_page.days', { n: detail.remaining_days }) }}</el-descriptions-item>
            </el-descriptions>

            <!-- 活动更新 -->
            <div class="mt-4">
              <div class="flex justify-between items-center mb-2">
                <h3 class="text-lg font-medium">{{ t('pre_sale_page.detail.updates') }}</h3>
                <el-button size="small" @click="showPostUpdate = true">{{ t('pre_sale_page.buttons.post_update') }}</el-button>
              </div>
              <div v-if="detail.updates && detail.updates.length > 0">
                <el-timeline>
                  <el-timeline-item
                    v-for="u in detail.updates"
                    :key="u.id"
                    :timestamp="formatDate(u.created_at)"
                    :type="u.is_pinned ? 'primary' : ''"
                  >
                    <div class="flex justify-between">
                      <span class="font-medium">{{ u.title }}</span>
                      <el-button size="small" text type="danger" @click="handleDeleteUpdate(u.id)">{{ t('actions.delete') }}</el-button>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">{{ u.content }}</p>
                    <el-tag size="mini" v-if="u.type !== 'update'">{{ updateTypeLabel(u.type) }}</el-tag>
                  </el-timeline-item>
                </el-timeline>
              </div>
              <el-empty v-else :description="t('pre_sale_page.detail.no_updates')" />
            </div>

            <!-- 订单标签页 -->
            <el-tabs v-model="orderTab" class="mt-4">
              <el-tab-pane :label="t('pre_sale_page.tabs.orders')" name="orders">
                <el-table :data="detailOrders" v-loading="ordersLoading" size="small" stripe>
                  <el-table-column prop="order_no" :label="t('pre_sale_page.cols.order_no')" width="160" />
                  <el-table-column :label="t('pre_sale_page.cols.user')" width="120">
                    <template #default="{ row }">{{ row.user?.name || row.user?.email }}</template>
                  </el-table-column>
                  <el-table-column :label="t('pre_sale_page.cols.amount')" width="100">
                    <template #default="{ row }">¥{{ formatMoney(row.total_amount) }}</template>
                  </el-table-column>
                  <el-table-column :label="t('pre_sale_page.cols.deposit')" width="80">
                    <template #default="{ row }">¥{{ formatMoney(row.deposit_paid) }}</template>
                  </el-table-column>
                  <el-table-column :label="t('pre_sale_page.cols.final')" width="80">
                    <template #default="{ row }">¥{{ formatMoney(row.final_paid) }}</template>
                  </el-table-column>
                  <el-table-column :label="t('pre_sale_page.cols.payment_status')" width="120">
                    <template #default="{ row }">
                      <el-tag :type="paymentStatusTag(row.payment_status)" size="small">{{ paymentStatusLabel(row.payment_status) }}</el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column :label="t('pre_sale_page.cols.fulfillment_status')" width="100">
                    <template #default="{ row }">
                      <el-tag size="small">{{ fulfillmentStatusLabel(row.fulfillment_status) }}</el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column :label="t('pre_sale_page.cols.actions')" width="160">
                    <template #default="{ row }">
                      <el-button size="small" text v-if="row.payment_status === 'deposit_pending'" @click="handlePayDeposit(row)">{{ t('pre_sale_page.buttons.pay_deposit') }}</el-button>
                      <el-button size="small" text v-if="row.payment_status === 'deposit_paid'" type="warning" @click="handlePayFinal(row)">{{ t('pre_sale_page.buttons.pay_final') }}</el-button>
                      <el-select
                        v-if="row.payment_status === 'final_paid'"
                        size="small"
                        :model-value="row.fulfillment_status"
                        @change="(v) => handleUpdateFulfillment(row, v)"
                        style="width:100px"
                      >
                        <el-option v-for="opt in fulfillmentOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                      </el-select>
                    </template>
                  </el-table-column>
                </el-table>
              </el-tab-pane>
            </el-tabs>
          </div>
        </el-dialog>

        <!-- 发布更新对话框 -->
        <el-dialog v-model="showPostUpdate" :title="t('pre_sale_page.dialogs.post_update')" width="500px">
          <el-form :model="updateForm" label-width="80px">
            <el-form-item :label="t('pre_sale_page.form.title')" required>
              <el-input v-model="updateForm.title" maxlength="200" />
            </el-form-item>
            <el-form-item :label="t('pre_sale_page.form.content')" required>
              <el-input v-model="updateForm.content" type="textarea" :rows="4" />
            </el-form-item>
            <el-form-item :label="t('pre_sale_page.form.update_type')">
              <el-select v-model="updateForm.type">
                <el-option v-for="opt in updateTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
            <el-form-item :label="t('pre_sale_page.form.pin')">
              <el-switch v-model="updateForm.is_pinned" />
            </el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="showPostUpdate = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" @click="handlePostUpdate" :loading="updating">{{ t('pre_sale_page.buttons.publish') }}</el-button>
          </template>
        </el-dialog>

        <!-- 支付确认 -->
        <el-dialog v-model="showPayDialog" :title="t('pre_sale_page.dialogs.confirm_pay')" width="400px">
          <p class="mb-3">{{ t('pre_sale_page.pay.order_label') }}：{{ payTarget?.order_no }}</p>
          <el-form label-width="80px" size="small">
            <el-form-item :label="t('pre_sale_page.pay.pay_type')"><span>{{ payPhase === 'deposit' ? t('pre_sale_page.pay.deposit') : t('pre_sale_page.pay.final') }}</span></el-form-item>
            <el-form-item :label="t('pre_sale_page.pay.method')">
              <el-select v-model="payMethod" style="width:100%">
                <el-option v-for="opt in payMethodOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
              </el-select>
            </el-form-item>
          </el-form>
          <template #footer>
            <el-button @click="showPayDialog = false">{{ t('actions.cancel') }}</el-button>
            <el-button type="primary" :loading="payLoading" @click="submitPay">{{ t('pre_sale_page.buttons.confirm_pay') }}</el-button>
          </template>
        </el-dialog>
      </el-tab-pane>

      <!-- Tab 2: 秒杀/抢购 -->
      <el-tab-pane :label="$t('admin.menu.flash_sale')" name="flashsale">
        <template v-if="fs_tabVisited">
          <div class="flash-sale-page">
            <div class="page-header">
              <h2>
                <el-icon style="vertical-align:middle;margin-right:8px"><Lightning /></el-icon>
                {{ t('flash_sale_page.title') }}
              </h2>
              <div class="header-actions">
                <el-button type="primary" @click="fs_refreshAll" :loading="fs_loading">
                  <el-icon><Refresh /></el-icon> {{ t('flash_sale_page.refresh') }}
                </el-button>
              </div>
            </div>

            <el-row :gutter="16" class="mb-4">
              <el-col :span="4">
                <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ fs_stats.total }}</div><div class="stat-label">{{ t('flash_sale_page.stats.total') }}</div></el-card>
              </el-col>
              <el-col :span="5">
                <el-card shadow="hover" class="stat-card"><div class="stat-value stat-success">{{ fs_stats.active }}</div><div class="stat-label">{{ t('flash_sale_page.stats.active') }}</div></el-card>
              </el-col>
              <el-col :span="5">
                <el-card shadow="hover" class="stat-card"><div class="stat-value stat-primary">{{ fs_stats.scheduled }}</div><div class="stat-label">{{ t('flash_sale_page.stats.scheduled') }}</div></el-card>
              </el-col>
              <el-col :span="5">
                <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ fs_stats.totalOrders }}</div><div class="stat-label">{{ t('flash_sale_page.stats.total_orders') }}</div></el-card>
              </el-col>
              <el-col :span="5">
                <el-card shadow="hover" class="stat-card"><div class="stat-value">{{ fs_stats.paidOrders }}</div><div class="stat-label">{{ t('flash_sale_page.stats.paid_orders') }}</div></el-card>
              </el-col>
            </el-row>

            <el-card shadow="hover">
              <template #header>
                <div class="flex justify-between items-center">
                  <span>{{ t('flash_sale_page.card_title') }}</span>
                  <el-button type="danger" size="small" @click="fs_showCreate">
                    <el-icon><Plus /></el-icon> {{ t('flash_sale_page.btn_new') }}
                  </el-button>
                </div>
              </template>
              <el-table :data="fs_sales" stripe v-loading="fs_salesLoading">
                <el-table-column :label="t('flash_sale_page.col_name')" prop="name" min-width="160" />
                <el-table-column :label="t('flash_sale_page.col_sku')" width="100">{{ row => row.sku?.id }}</el-table-column>
                <el-table-column :label="t('flash_sale_page.col_original_price')" width="90" align="center">
                  <template #default="{ row }">¥{{ ((row.original_price || 0) / 100).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column :label="t('flash_sale_page.col_flash_price')" width="90" align="center">
                  <template #default="{ row }"><span class="text-danger">¥{{ ((row.flash_price || 0) / 100).toFixed(2) }}</span></template>
                </el-table-column>
                <el-table-column :label="t('flash_sale_page.col_stock')" prop="stock" width="60" align="center" />
                <el-table-column :label="t('flash_sale_page.col_max_per_user')" prop="max_per_user" width="80" align="center" />
                <el-table-column :label="t('flash_sale_page.col_start_time')" width="150">{{ fs_formatTime(row.start_time) }}</el-table-column>
                <el-table-column :label="t('flash_sale_page.col_end_time')" width="150">{{ fs_formatTime(row.end_time) }}</el-table-column>
                <el-table-column :label="t('flash_sale_page.col_status')" width="80">
                  <template #default="{ row }">
                    <el-tag :type="row.status === 'active' ? 'danger' : row.status === 'scheduled' ? 'primary' : row.status === 'paused' ? 'warning' : 'info'" size="small">
                      {{ fs_statusLabel(row.status) }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column :label="t('flash_sale_page.col_actions')" width="200" fixed="right">
                  <template #default="{ row }">
                    <el-button v-if="row.status === 'scheduled'" size="small" type="danger" @click="fs_handleStatus(row, 'active')">{{ t('flash_sale_page.btn_start') }}</el-button>
                    <el-button v-if="row.status === 'active'" size="small" type="warning" @click="fs_handleStatus(row, 'paused')">{{ t('flash_sale_page.btn_pause') }}</el-button>
                    <el-button v-if="row.status === 'active'" size="small" type="info" @click="fs_handleReleaseExpired(row)">{{ t('flash_sale_page.btn_release') }}</el-button>
                    <el-button v-if="row.status !== 'ended'" size="small" @click="fs_handleStatus(row, 'ended')">{{ t('flash_sale_page.btn_end') }}</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <div class="pagination-wrap" v-if="fs_pagination.total > fs_pagination.per_page">
                <el-pagination v-model:current-page="fs_pagination.current_page" :page-size="fs_pagination.per_page" :total="fs_pagination.total" layout="prev, pager, next" @current-change="fs_loadSales" />
              </div>
            </el-card>

            <el-dialog v-model="fs_createVisible" :title="t('flash_sale_page.dialog_create_title')" width="520px">
              <el-form :model="fs_form" :rules="fs_rules" ref="fs_formRef" label-width="100px">
                <el-form-item :label="t('flash_sale_page.form_name')" prop="name"><el-input v-model="fs_form.name" /></el-form-item>
                <el-form-item :label="t('flash_sale_page.form_sku')" prop="sku_id">
                  <el-select v-model="fs_form.sku_id" filterable style="width:100%"><el-option v-for="s in fs_skus" :key="s.id" :label="`#${s.id} - ${s.product_name || ''}`" :value="s.id" /></el-select>
                </el-form-item>
                <el-row :gutter="12">
                  <el-col :span="12"><el-form-item :label="t('flash_sale_page.form_original_price')"><el-input-number v-model="fs_form.original_price" :min="1" style="width:100%" /><div class="text-[10px] text-gray-400 mt-1">{{ t('flash_sale_page.hint_price_cents') }}</div></el-form-item></el-col>
                  <el-col :span="12"><el-form-item :label="t('flash_sale_page.form_flash_price')"><el-input-number v-model="fs_form.flash_price" :min="1" style="width:100%" /><div class="text-[10px] text-gray-400 mt-1">{{ t('flash_sale_page.hint_cents') }}</div></el-form-item></el-col>
                </el-row>
                <el-row :gutter="12">
                  <el-col :span="12"><el-form-item :label="t('flash_sale_page.form_stock')"><el-input-number v-model="fs_form.stock" :min="1" style="width:100%" /></el-form-item></el-col>
                  <el-col :span="12"><el-form-item :label="t('flash_sale_page.form_max_per_user')"><el-input-number v-model="fs_form.max_per_user" :min="1" :max="100" style="width:100%" /></el-form-item></el-col>
                </el-row>
                <el-form-item :label="t('flash_sale_page.form_start_time')"><el-date-picker v-model="fs_form.start_time" type="datetime" style="width:100%" /></el-form-item>
                <el-form-item :label="t('flash_sale_page.form_end_time')"><el-date-picker v-model="fs_form.end_time" type="datetime" style="width:100%" /></el-form-item>
              </el-form>
              <template #footer>
                <el-button @click="fs_createVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="danger" @click="fs_handleCreate" :loading="fs_submitting">{{ t('actions.create') }}</el-button>
              </template>
            </el-dialog>
          </div>
        </template>
        <div v-else class="p-8 text-center text-gray-400">{{ $t('common.loading') }}</div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Lightning, Refresh, Plus } from '@element-plus/icons-vue';
import preSaleApi from '@/api/preSale';
import flashApi from '@/api/flashSale';
import productApi from '@/api/product';

const { t, locale } = useI18n();

// ─── 标签页切换 ───
const saleMainTab = ref('presale');

// ─── 统计 ───
const statsData = ref(null);

const statItems = computed(() => {
  if (!statsData.value) return [];
  const d = statsData.value;
  return [
    { label: t('pre_sale_page.stats.total'), value: d.total, color: 'text-blue-500' },
    { label: t('pre_sale_page.stats.active'), value: d.active, color: 'text-green-500' },
    { label: t('pre_sale_page.stats.success'), value: d.success, color: 'text-teal-500' },
    { label: t('pre_sale_page.stats.failed'), value: d.failed, color: 'text-red-500' },
    { label: t('pre_sale_page.stats.total_raised'), value: '¥' + formatMoney(d.totalRaised), color: 'text-yellow-600' },
    { label: t('pre_sale_page.stats.total_backers'), value: d.totalBackers, color: 'text-purple-500' },
  ];
});

const typeLabels = computed(() => ({
  pre_sale: t('pre_sale_page.types.pre_sale'),
  crowdfunding: t('pre_sale_page.types.crowdfunding'),
}));

const statusLabels = computed(() => ({
  draft: t('pre_sale_page.status.draft'),
  pending: t('pre_sale_page.status.pending'),
  active: t('pre_sale_page.status.active'),
  success: t('pre_sale_page.status.success'),
  failed: t('pre_sale_page.status.failed'),
  cancelled: t('pre_sale_page.status.cancelled'),
  completed: t('pre_sale_page.status.completed'),
}));

const paymentStatusLabels = computed(() => ({
  deposit_pending: t('pre_sale_page.payment_status.deposit_pending'),
  deposit_paid: t('pre_sale_page.payment_status.deposit_paid'),
  final_pending: t('pre_sale_page.payment_status.final_pending'),
  final_paid: t('pre_sale_page.payment_status.final_paid'),
  refunding: t('pre_sale_page.payment_status.refunding'),
  refunded: t('pre_sale_page.payment_status.refunded'),
}));

const fulfillmentStatusLabels = computed(() => ({
  pending: t('pre_sale_page.fulfillment_status.pending'),
  processing: t('pre_sale_page.fulfillment_status.processing'),
  shipped: t('pre_sale_page.fulfillment_status.shipped'),
  delivered: t('pre_sale_page.fulfillment_status.delivered'),
}));

const updateTypeLabels = computed(() => ({
  update: t('pre_sale_page.update_types.update'),
  milestone: t('pre_sale_page.update_types.milestone'),
  announcement: t('pre_sale_page.update_types.announcement'),
}));

const typeFilterOptions = computed(() => [
  { value: 'pre_sale', label: typeLabels.value.pre_sale },
  { value: 'crowdfunding', label: typeLabels.value.crowdfunding },
]);

const statusFilterOptions = computed(() => [
  { value: 'draft', label: statusLabels.value.draft },
  { value: 'active', label: statusLabels.value.active },
  { value: 'success', label: statusLabels.value.success },
  { value: 'failed', label: statusLabels.value.failed },
  { value: 'cancelled', label: statusLabels.value.cancelled },
  { value: 'completed', label: statusLabels.value.completed },
]);

const sortOptions = computed(() => [
  { value: 'latest', label: t('pre_sale_page.sort.latest') },
  { value: 'oldest', label: t('pre_sale_page.sort.oldest') },
  { value: 'ending_soon', label: t('pre_sale_page.sort.ending_soon') },
  { value: 'most_raised', label: t('pre_sale_page.sort.most_raised') },
]);

const updateTypeOptions = computed(() => [
  { value: 'update', label: updateTypeLabels.value.update },
  { value: 'milestone', label: updateTypeLabels.value.milestone },
  { value: 'announcement', label: updateTypeLabels.value.announcement },
]);

const fulfillmentOptions = computed(() => [
  { value: 'pending', label: fulfillmentStatusLabels.value.pending },
  { value: 'processing', label: fulfillmentStatusLabels.value.processing },
  { value: 'shipped', label: fulfillmentStatusLabels.value.shipped },
  { value: 'delivered', label: fulfillmentStatusLabels.value.delivered },
]);

const payMethodOptions = computed(() => [
  { value: 'gateway', label: t('pre_sale_page.pay.gateway') },
  { value: 'prepaid', label: t('pre_sale_page.pay.prepaid') },
]);

async function loadStats() {
  try {
    const { data } = await preSaleApi.stats();
    if (data?.data) {
      statsData.value = data.data;
    }
  } catch (e) {
    console.error('Failed to load stats:', e);
  }
}

// ─── 列表 ───
const campaigns = ref([]);
const loading = ref(false);
const pagination = ref(null);
const filters = reactive({
  type: '',
  status: '',
  search: '',
  sort: 'latest',
});

async function loadData(page = 1) {
  loading.value = true;
  try {
    const params = { ...filters, page };
    const { data } = await preSaleApi.list(params);
    campaigns.value = data?.data?.data || [];
    pagination.value = data?.data || null;
  } catch (e) {
    ElMessage.error(t('pre_sale_page.messages.load_list_failed'));
  } finally {
    loading.value = false;
  }
}

function search() {
  loadData(1);
}

function refresh() {
  loadStats();
  loadData(pagination.value?.current_page || 1);
}

// ─── 创建/编辑 ───
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const saving = ref(false);
const form = reactive({
  tenant_id: 1,
  type: 'pre_sale',
  name: '',
  description: '',
  product_id: null,
  target_amount: null,
  deposit_rate: 0,
  deposit_amount: null,
  currency: 'CNY',
  start_at: null,
  end_at: null,
  estimated_delivery_at: null,
  target_backers: null,
});

function resetForm() {
  form.tenant_id = 1;
  form.type = 'pre_sale';
  form.name = '';
  form.description = '';
  form.product_id = null;
  form.target_amount = null;
  form.deposit_rate = 0;
  form.deposit_amount = null;
  form.currency = 'CNY';
  form.start_at = null;
  form.end_at = null;
  form.estimated_delivery_at = null;
  form.target_backers = null;
  isEditing.value = false;
  editingId.value = null;
}

function openCreate() {
  resetForm();
  dialogVisible.value = true;
}

function editCampaign(row) {
  resetForm();
  isEditing.value = true;
  editingId.value = row.id;
  Object.assign(form, {
    tenant_id: row.tenant_id,
    type: row.type,
    name: row.name,
    description: row.description,
    product_id: row.product_id,
    target_amount: row.target_amount,
    deposit_rate: row.deposit_rate,
    deposit_amount: row.deposit_amount,
    currency: row.currency,
    start_at: row.start_at,
    end_at: row.end_at,
    estimated_delivery_at: row.estimated_delivery_at,
    target_backers: row.target_backers,
  });
  dialogVisible.value = true;
}

async function save() {
  if (!form.name || !form.product_id || !form.start_at || !form.end_at) {
    ElMessage.warning(t('pre_sale_page.messages.fill_required'));
    return;
  }
  saving.value = true;
  try {
    const payload = {
      ...form,
      start_at: form.start_at instanceof Date ? form.start_at.toISOString() : form.start_at,
      end_at: form.end_at instanceof Date ? form.end_at.toISOString() : form.end_at,
      estimated_delivery_at: form.estimated_delivery_at instanceof Date ? form.estimated_delivery_at.toISOString() : form.estimated_delivery_at,
    };
    if (isEditing.value) {
      await preSaleApi.update(editingId.value, payload);
      ElMessage.success(t('pre_sale_page.messages.updated'));
    } else {
      await preSaleApi.create(payload);
      ElMessage.success(t('pre_sale_page.messages.created'));
    }
    dialogVisible.value = false;
    refresh();
  } catch (e) {
    ElMessage.error(t('pre_sale_page.messages.save_failed'));
  } finally {
    saving.value = false;
  }
}

// ─── 商品搜索 ───
const productOptions = ref([]);
const productLoading = ref(false);

async function searchProducts(query) {
  if (!query) return;
  productLoading.value = true;
  try {
    const { data } = await productApi.list({ search: query, per_page: 10 });
    productOptions.value = data?.data?.data || [];
  } catch (e) {
    productOptions.value = [];
  } finally {
    productLoading.value = false;
  }
}

// ─── 操作 ───
async function handlePublish(row) {
  try {
    await ElMessageBox.confirm(
      t('pre_sale_page.messages.publish_confirm', { name: row.name }),
      t('actions.confirm'),
    );
    await preSaleApi.publish(row.id);
    ElMessage.success(t('pre_sale_page.messages.published'));
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('pre_sale_page.messages.publish_failed'));
  }
}

async function handleCancel(row) {
  try {
    const { value: reason } = await ElMessageBox.prompt(
      t('pre_sale_page.messages.cancel_reason_ph'),
      t('pre_sale_page.messages.cancel_title'),
      { inputType: 'textarea' },
    );
    await preSaleApi.cancel(row.id, reason || '');
    ElMessage.success(t('pre_sale_page.messages.cancelled'));
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('pre_sale_page.messages.cancel_failed'));
  }
}

async function handleComplete(row) {
  try {
    await ElMessageBox.confirm(
      t('pre_sale_page.messages.complete_confirm', { name: row.name }),
      t('actions.confirm'),
    );
    await preSaleApi.complete(row.id);
    ElMessage.success(t('pre_sale_page.messages.completed'));
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('pre_sale_page.messages.operation_failed'));
  }
}

async function handleCheckStatus(row) {
  try {
    const { data } = await preSaleApi.checkStatus(row.id);
    ElMessage.success(t('pre_sale_page.messages.status_updated', { status: statusLabel(data.data.status) }));
    refresh();
  } catch (e) {
    ElMessage.error(t('pre_sale_page.messages.check_failed'));
  }
}

async function handleDelete(row) {
  try {
    await ElMessageBox.confirm(
      t('pre_sale_page.messages.delete_confirm', { name: row.name }),
      t('pre_sale_page.messages.warning'),
      { type: 'warning' },
    );
    await preSaleApi.destroy(row.id);
    ElMessage.success(t('pre_sale_page.messages.deleted'));
    refresh();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('pre_sale_page.messages.delete_failed'));
  }
}

// ─── 详情 ───
const detailVisible = ref(false);
const detail = ref(null);
const detailLoading = ref(false);
const detailOrders = ref([]);
const ordersLoading = ref(false);
const orderTab = ref('orders');

async function viewCampaign(row) {
  detailLoading.value = true;
  detailVisible.value = true;
  try {
    const { data: detailData } = await preSaleApi.show(row.id);
    detail.value = detailData?.data || null;

    const { data: ordersData } = await preSaleApi.listOrders({ campaign_id: row.id });
    detailOrders.value = ordersData?.data?.data || [];
  } catch (e) {
    ElMessage.error(t('pre_sale_page.messages.load_detail_failed'));
  } finally {
    detailLoading.value = false;
  }
}

const payMethod = ref('gateway');
const showPayDialog = ref(false);
const payLoading = ref(false);
const payTarget = ref(null);
const payPhase = ref('deposit');

function openPayDialog(order, phase) {
  payTarget.value = order;
  payPhase.value = phase;
  payMethod.value = 'gateway';
  showPayDialog.value = true;
}

async function submitPay() {
  if (!payTarget.value) return;
  payLoading.value = true;
  try {
    const fn = payPhase.value === 'deposit' ? preSaleApi.payDeposit : preSaleApi.payFinal;
    await fn(payTarget.value.id, payMethod.value);
    ElMessage.success(
      payPhase.value === 'deposit'
        ? t('pre_sale_page.messages.deposit_collected')
        : t('pre_sale_page.messages.final_collected'),
    );
    showPayDialog.value = false;
    await viewCampaign(detail.value);
  } catch (e) {
    ElMessage.error(e.response?.data?.message || t('pre_sale_page.messages.pay_failed'));
  } finally {
    payLoading.value = false;
  }
}

// ─── 订单操作 ───
async function handlePayDeposit(order) {
  openPayDialog(order, 'deposit');
}

async function handlePayFinal(order) {
  openPayDialog(order, 'final');
}

async function handleUpdateFulfillment(order, status) {
  try {
    await preSaleApi.updateFulfillment(order.id, status);
    ElMessage.success(t('pre_sale_page.messages.fulfillment_updated'));
    order.fulfillment_status = status;
  } catch (e) {
    ElMessage.error(t('pre_sale_page.messages.update_failed'));
  }
}

// ─── 活动更新 ───
const showPostUpdate = ref(false);
const updating = ref(false);
const updateForm = reactive({
  title: '',
  content: '',
  type: 'update',
  is_pinned: false,
});

async function handlePostUpdate() {
  if (!updateForm.title || !updateForm.content) {
    ElMessage.warning(t('pre_sale_page.messages.fill_title_content'));
    return;
  }
  updating.value = true;
  try {
    await preSaleApi.postUpdate(detail.value.id, { ...updateForm });
    ElMessage.success(t('pre_sale_page.messages.update_posted'));
    showPostUpdate.value = false;
    updateForm.title = '';
    updateForm.content = '';
    updateForm.type = 'update';
    updateForm.is_pinned = false;
    await viewCampaign(detail.value);
  } catch (e) {
    ElMessage.error(t('pre_sale_page.messages.post_failed'));
  } finally {
    updating.value = false;
  }
}

async function handleDeleteUpdate(updateId) {
  try {
    await ElMessageBox.confirm(
      t('pre_sale_page.messages.delete_update_confirm'),
      t('actions.confirm'),
    );
    await preSaleApi.deleteUpdate(updateId);
    ElMessage.success(t('pre_sale_page.messages.deleted'));
    await viewCampaign(detail.value);
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('pre_sale_page.messages.delete_failed'));
  }
}

// ═══════════════════════════════════════════════
// 秒杀/抢购（flash-sale）
// ═══════════════════════════════════════════════
const fs_tabVisited = ref(false);
watch(saleMainTab, (val) => {
  if (val === 'flashsale' && !fs_tabVisited.value) {
    fs_tabVisited.value = true;
    fs_refreshAll();
    fs_loadSkus();
  }
});

const fs_loading = ref(false);
const fs_submitting = ref(false);
const fs_salesLoading = ref(false);

const fs_stats = ref({});
const fs_sales = ref([]);
const fs_skus = ref([]);
const fs_pagination = reactive({ current_page: 1, per_page: 20, total: 0 });
const fs_createVisible = ref(false);
const fs_formRef = ref(null);
const fs_form = reactive({
  name: '', sku_id: '', flash_price: 100, original_price: 200,
  stock: 100, max_per_user: 1, start_time: '', end_time: '',
});
const fs_rules = { name: [{ required: true }], sku_id: [{ required: true }] };

const fs_statusMap = computed(() => ({
  scheduled: t('flash_sale_page.status.scheduled'),
  active: t('flash_sale_page.status.active'),
  paused: t('flash_sale_page.status.paused'),
  ended: t('flash_sale_page.status.ended'),
}));

async function fs_refreshAll() {
  fs_loading.value = true;
  try {
    const res = await flashApi.dashboard();
    fs_stats.value = res.data;
  } finally { fs_loading.value = false; }
  fs_loadSales();
}

async function fs_loadSales() {
  fs_salesLoading.value = true;
  try {
    const res = await flashApi.list({ page: fs_pagination.current_page });
    fs_sales.value = res.data.data || [];
    Object.assign(fs_pagination, res.data);
  } finally { fs_salesLoading.value = false; }
}

async function fs_loadSkus() {
  try {
    const res = await import('@/api/shop').then(m => m.default.getSkus?.({ per_page: 999 }));
    fs_skus.value = res?.data?.data || [];
  } catch {}
}

function fs_showCreate() {
  fs_form.name = ''; fs_form.sku_id = ''; fs_form.flash_price = 100; fs_form.original_price = 200;
  fs_form.stock = 100; fs_form.max_per_user = 1; fs_form.start_time = ''; fs_form.end_time = '';
  fs_createVisible.value = true;
}

async function fs_handleCreate() {
  const valid = await fs_formRef.value.validate().catch(() => false);
  if (!valid) return;
  fs_submitting.value = true;
  try {
    await flashApi.create(fs_form);
    ElMessage.success(t('flash_sale_page.messages.created'));
    fs_createVisible.value = false;
    fs_loadSales(); fs_refreshAll();
  } finally { fs_submitting.value = false; }
}

async function fs_handleStatus(row, status) {
  await flashApi.updateStatus(row.id, status);
  ElMessage.success(t('flash_sale_page.messages.status_updated'));
  fs_loadSales();
}

async function fs_handleReleaseExpired(row) {
  const res = await flashApi.releaseExpired(row.id);
  ElMessage.success(res.message || t('flash_sale_page.messages.released'));
  fs_loadSales();
}

function fs_statusLabel(status) {
  return fs_statusMap.value[status] || status;
}

function fs_formatTime(time) {
  if (!time) return '—';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(time).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// ═══════════════════════════════════════════════
// 共享工具函数
// ═══════════════════════════════════════════════
function formatMoney(v) {
  if (v === null || v === undefined) return '0.00';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return Number(v).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(d) {
  if (!d) return '-';
  const dt = new Date(d);
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return dt.toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function statusTag(s) {
  const map = { draft: 'info', pending: 'warning', active: 'primary', success: 'success', failed: 'danger', cancelled: 'info', completed: 'success' };
  return map[s] || 'info';
}

function typeLabel(s) {
  return typeLabels.value[s] || s;
}

function statusLabel(s) {
  return statusLabels.value[s] || s;
}

function paymentStatusLabel(s) {
  return paymentStatusLabels.value[s] || s;
}

function paymentStatusTag(s) {
  const map = { deposit_pending: 'info', deposit_paid: 'primary', final_pending: 'warning', final_paid: 'success', refunding: 'danger', refunded: 'info' };
  return map[s] || 'info';
}

function fulfillmentStatusLabel(s) {
  return fulfillmentStatusLabels.value[s] || s;
}

function updateTypeLabel(s) {
  return updateTypeLabels.value[s] || s;
}

onMounted(() => {
  loadStats();
  loadData();
});
</script>

<style scoped>
.pre-sale-page { padding: 16px; }

/* flash-sale 样式（在 pre-sale-page 内部时使用） */
.pre-sale-page .flash-sale-page { padding: 0; }
.flash-sale-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 26px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; } .stat-primary { color: #0f172a; }
.text-danger { color: #F56C6C; font-weight: 700; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 12px; }
</style>
