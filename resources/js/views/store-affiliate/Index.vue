<template>
  <div class="store-affiliate-page">
    <div class="page-header">
      <h2>{{ t(`${ns}.title`) }}</h2>
      <div class="header-actions">
        <el-button @click="loadAll" :loading="loading">
          <el-icon><Refresh /></el-icon> {{ t(`${ns}.buttons.refresh`) }}
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t(`${ns}.stats.total_orders`) }}</div>
          <div class="stat-value">{{ dashboard.total_orders }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t(`${ns}.stats.total_commission`) }}</div>
          <div class="stat-value">¥{{ formatNum(dashboard.total_commission) }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t(`${ns}.stats.pending_commission`) }}</div>
          <div class="stat-value text-warning">¥{{ formatNum(dashboard.pending_commission) }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="6">
        <el-card shadow="hover">
          <div class="stat-label">{{ t(`${ns}.stats.month_commission`) }}</div>
          <div class="stat-value">¥{{ formatNum(dashboard.month_commission) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16" class="mb-6">
      <!-- 推广商品列表（阿里妈妈联盟样式） -->
      <el-col :span="24" class="mb-4">
        <el-card shadow="hover">
          <template #header>
            <div class="card-header-flex">
              <span>{{ t(`${ns}.sections.promote_products`) }}</span>
              <div class="header-actions">
                <el-button size="small" :disabled="!selectedSkus.length" type="primary" @click="generateLinks">
                  {{ t(`${ns}.buttons.generate_links`, { count: selectedSkus.length }) }}
                </el-button>
                <el-button size="small" @click="selectAllSkus" :type="allSelected ? 'warning' : 'default'">
                  {{ allSelected ? t(`${ns}.buttons.deselect_all`) : t(`${ns}.buttons.select_all`) }}
                </el-button>
              </div>
            </div>
          </template>

          <!-- 分类筛选 -->
          <div class="category-tabs">
            <button
              :class="['cat-pill', { active: activeCategory === '' }]"
              @click="activeCategory = ''"
            >{{ t(`${ns}.filters.all_categories`, { count: skus.length }) }}</button>
            <button
              v-for="cat in categories"
              :key="cat.id"
              :class="['cat-pill', { active: activeCategory === cat.id }]"
              @click="activeCategory = cat.id"
            >{{ cat.name }} ({{ cat.count }})</button>
          </div>

          <!-- 分类分组商品卡片 -->
          <div v-if="groupedSkus.length" class="affiliate-sections">
            <div
              v-for="group in groupedSkus"
              :key="group.category_id || 'uncategorized'"
              class="affiliate-section"
            >
              <div v-if="group.category_name" class="section-header">
                <span class="section-badge">{{ group.category_name }}</span>
                <span class="section-count">{{ t(`${ns}.product.item_count`, { count: group.items.length }) }}</span>
              </div>
              <div class="affiliate-product-grid">
                <div
                  v-for="sku in group.items"
                  :key="sku.id"
                  class="affiliate-product-card"
                  :class="{ selected: selectedSkus.includes(sku.id) }"
                >
                  <!-- 选中标记 -->
                  <div class="apc-select-mask" @click="toggleSku(sku.id)">
                    <el-checkbox :model-value="selectedSkus.includes(sku.id)" @change="toggleSku(sku.id)" />
                  </div>
                  <!-- 商品图片 -->
                  <div class="apc-image" @click="toggleSku(sku.id)">
                    <img :src="sku.image_url || '/images/product-placeholder.svg'" :alt="sku.name" loading="lazy" @error="$event.target.src='/images/product-placeholder.svg'" />
                    <span v-if="sku.compare_at_price && sku.compare_at_price > sku.price" class="apc-discount-badge">
                      -{{ discountPercent(sku.price, sku.compare_at_price) }}%
                    </span>
                  </div>
                  <!-- 信息区 -->
                  <div class="apc-body" @click="toggleSku(sku.id)">
                    <div class="apc-name" :title="(sku.product_name || sku.name) + ' - ' + sku.sku_code">{{ sku.product_name || sku.name }}</div>
                    <div class="apc-meta">
                      <span v-if="sku.category_name" class="apc-category">{{ sku.category_name }}</span>
                      <span class="apc-sold">{{ t(`${ns}.product.sold`, { count: sku.sold_count || 0 }) }}</span>
                    </div>
                    <div class="apc-price-row">
                      <div class="apc-price">¥{{ formatNum(sku.price) }}</div>
                      <div v-if="sku.compare_at_price && sku.compare_at_price > sku.price" class="apc-original-price">¥{{ formatNum(sku.compare_at_price) }}</div>
                    </div>
                  </div>
                  <!-- 佣金 + 操作 -->
                  <div class="apc-footer">
                    <div class="apc-commission-tag">
                      <span class="apc-rate">{{ sku.commission_rate }}%</span>
                      <span class="apc-amount">{{ t(`${ns}.product.commission_prefix`) }} ¥{{ formatNum(sku.commission_amount) }}</span>
                    </div>
                    <div class="apc-actions" @click.stop>
                      <el-dropdown trigger="click" @command="(cmd) => handlePromote(sku, cmd)">
                        <el-button size="small" class="apc-copy-btn">
                          <el-icon><Promotion /></el-icon> {{ t(`${ns}.buttons.promote`) }}
                          <el-icon class="el-icon--right"><ArrowDown /></el-icon>
                        </el-button>
                        <template #dropdown>
                          <el-dropdown-menu>
                            <el-dropdown-item command="link">
                              <el-icon><Link /></el-icon> {{ t(`${ns}.promote.copy_link`) }}
                            </el-dropdown-item>
                            <el-dropdown-item command="text">
                              <el-icon><DocumentCopy /></el-icon> {{ t(`${ns}.promote.copy_text`) }}
                            </el-dropdown-item>
                            <el-dropdown-item command="html">
                              <el-icon><Document /></el-icon> {{ t(`${ns}.promote.copy_html`) }}
                            </el-dropdown-item>
                            <el-dropdown-item command="qrcode">
                              <el-icon><FullScreen /></el-icon> {{ t(`${ns}.promote.qrcode`) }}
                            </el-dropdown-item>
                            <el-dropdown-item command="image" divided>
                              <el-icon><Picture /></el-icon> {{ t(`${ns}.promote.save_image`) }}
                            </el-dropdown-item>
                          </el-dropdown-menu>
                        </template>
                      </el-dropdown>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <el-empty v-else :description="t(`${ns}.empty.no_products`)" :image-size="60" />

          <!-- 生成的推广链接 -->
          <div v-if="generatedLinks.length" class="generated-links-section">
            <el-divider />
            <h4 class="section-title">{{ t(`${ns}.sections.generated_links`) }}</h4>
            <div v-for="link in generatedLinks" :key="link.click_id" class="generated-link-item">
              <div class="gl-product">
                <strong>{{ link.product_name || link.sku_name }}</strong>
                <span class="gl-rate">{{ t(`${ns}.link.commission`, { rate: link.commission_rate, amount: formatNum(link.commission_amount) }) }}</span>
              </div>
              <div class="gl-link-row">
                <el-input :model-value="link.link" readonly size="small" class="gl-input">
                  <template #append>
                    <el-button @click="copyLink(link.link)" size="small">{{ t('affiliate_enhanced_page.buttons.copy_link') }}</el-button>
                  </template>
                </el-input>
              </div>
            </div>
          </div>

          <!-- 推广二维码弹窗 -->
          <el-dialog v-model="qrDialog.visible" :title="t(`${ns}.qr.title`, { name: qrDialog.title })" width="360px" align-center>
            <div class="qr-container">
              <div class="qr-canvas-wrap">
                <img :src="'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qrDialog.link)" :alt="t(`${ns}.qr.alt`)" class="qr-canvas" />
              </div>
              <p class="qr-link-text">{{ qrDialog.link }}</p>
              <div class="qr-actions">
                <el-button size="small" class="apc-copy-btn" @click="copyToClipboard(qrDialog.link, t(`${ns}.messages.promo_link_copied`))">
                  {{ t(`${ns}.promote.copy_link`) }}
                </el-button>
                <el-button size="small" @click="downloadQr(qrDialog.link, qrDialog.title)">
                  {{ t(`${ns}.buttons.save_qrcode`) }}
                </el-button>
              </div>
            </div>
          </el-dialog>
        </el-card>
      </el-col>

      <!-- 近7天趋势 -->
      <el-col :xs="24" :lg="12" class="mb-4">
        <el-card shadow="hover">
          <template #header><span>{{ t(`${ns}.sections.trend_7d`) }}</span></template>
          <div v-if="dashboard.trend?.length" class="trend-chart">
            <div v-for="t in dashboard.trend" :key="t.date" class="trend-bar-item">
              <div class="trend-label">{{ t.label }}</div>
              <div class="trend-bar-wrapper">
                <div class="trend-bar" :style="{ height: trendBarHeight(t.commission) + 'px' }"></div>
              </div>
              <div class="trend-value">¥{{ formatNum(t.commission) }}</div>
            </div>
          </div>
          <el-empty v-else :description="t(`${ns}.empty.no_trend`)" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 商品推广排行 -->
    <el-card shadow="hover" class="mb-6">
      <template #header><span>{{ t(`${ns}.sections.product_ranking`) }}</span></template>
      <el-table v-if="dashboard.product_ranking?.length" :data="dashboard.product_ranking" size="small" stripe>
        <el-table-column label="#" type="index" width="50" />
        <el-table-column prop="name" :label="t(`${ns}.cols.product_name`)" min-width="160" />
        <el-table-column prop="qty" :label="t(`${ns}.cols.qty`)" width="80" align="center" />
        <el-table-column prop="revenue" :label="t(`${ns}.cols.revenue`)" width="120" align="right">
          <template #default="{ row }">¥{{ formatNum(row.revenue) }}</template>
        </el-table-column>
      </el-table>
      <el-empty v-else :description="t(`${ns}.empty.no_ranking`)" :image-size="60" />
    </el-card>

    <!-- Tab: 推广订单 / 推广链接 -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${ns}.tabs.orders`)" name="orders">
          <el-table :data="affiliateOrders" v-loading="loadingOrders" size="small" stripe>
            <el-table-column prop="order_no" :label="t(`${ns}.cols.order_no`)" width="160" />
            <el-table-column prop="customer_name" :label="t('affiliate_enhanced_page.cols.customer')" min-width="120" />
            <el-table-column prop="final_amount" :label="t(`${ns}.cols.amount`)" width="100" align="right">
              <template #default="{ row }">¥{{ formatNum(row.final_amount) }}</template>
            </el-table-column>
            <el-table-column prop="commission_amount" :label="t('affiliate_enhanced_page.cols.commission')" width="100" align="right">
              <template #default="{ row }">¥{{ formatNum(row.commission_amount) }}</template>
            </el-table-column>
            <el-table-column prop="commission_rate" :label="t(`${ns}.cols.rate`)" width="60" align="center">
              <template #default="{ row }">{{ row.commission_rate }}%</template>
            </el-table-column>
            <el-table-column prop="status" :label="t(`${ns}.cols.status`)" width="80">
              <template #default="{ row }">
                <el-tag :type="row.status === 'settled' ? 'success' : row.status === 'cancelled' ? 'danger' : 'warning'" size="small">
                  {{ orderStatusLabel(row.status) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" :label="t(`${ns}.cols.time`)" width="160" />
          </el-table>
          <el-pagination
            v-if="orderPagination.total > orderPagination.per_page"
            background layout="prev,pager,next,total"
            :total="orderPagination.total" :page-size="orderPagination.per_page"
            :current-page="orderPagination.current_page"
            @current-change="loadOrders"
            style="margin-top:12px;justify-content:center"
          />
        </el-tab-pane>
        <el-tab-pane :label="t(`${ns}.tabs.links`)" name="links">
          <el-table :data="affiliateLinks" v-loading="loadingLinks" size="small" stripe>
            <el-table-column :label="t('affiliate_enhanced_page.cols.landing_page')" min-width="300" show-overflow-tooltip>
              <template #default="{ row }">{{ row.landing_url }}</template>
            </el-table-column>
            <el-table-column prop="referral_code" :label="t('affiliate_enhanced_page.cols.referral_code')" width="100" />
            <el-table-column prop="converted" :label="t(`${ns}.cols.converted`)" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.converted ? 'success' : 'info'" size="small">{{ convertedLabel(row.converted) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="commission_amount" :label="t('affiliate_enhanced_page.cols.commission')" width="100" align="right">
              <template #default="{ row }">¥{{ formatNum(row.commission_amount) }}</template>
            </el-table-column>
            <el-table-column prop="created_at" :label="t(`${ns}.cols.created_at`)" width="160" />
          </el-table>
          <el-pagination
            v-if="linkPagination.total > linkPagination.per_page"
            background layout="prev,pager,next,total"
            :total="linkPagination.total" :page-size="linkPagination.per_page"
            :current-page="linkPagination.current_page"
            @current-change="loadLinks"
            style="margin-top:12px;justify-content:center"
          />
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Refresh, Promotion, ArrowDown, Link, DocumentCopy, Document, FullScreen, Picture } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getPromotableSkus, generateAffiliateLinks,
  getStoreAffiliateDashboard, getStoreAffiliateOrders, getStoreAffiliateLinks,
} from '@/api/storeAffiliate'

const { t, locale } = useI18n()
const ns = 'store_affiliate_page'
const ae = 'affiliate_enhanced_page'

const loading = ref(false)
const loadingOrders = ref(false)
const loadingLinks = ref(false)
const activeTab = ref('orders')

const dashboard = ref({})
const skus = ref([])
const selectedSkus = ref([])
const generatedLinks = ref([])
const activeCategory = ref('')
const affiliateOrders = ref([])
const qrDialog = ref({ visible: false, title: '', link: '' })
const affiliateLinks = ref([])

const orderPagination = reactive({ current_page: 1, per_page: 20, total: 0 })
const linkPagination = reactive({ current_page: 1, per_page: 20, total: 0 })

const orderStatusMap = computed(() => ({
  settled: t(`${ns}.order_status.settled`),
  cancelled: t(`${ns}.order_status.cancelled`),
  pending: t(`${ns}.order_status.pending`),
}))

function orderStatusLabel(status) {
  return orderStatusMap.value[status] || orderStatusMap.value.pending
}

function convertedLabel(value) {
  return value ? t(`${ae}.bool.yes`) : t(`${ae}.bool.no`)
}

function formatNum(val) {
  if (val === null || val === undefined) return '0.00'
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
  return Number(val).toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function trendBarHeight(val) {
  const max = Math.max(...(dashboard.value.trend?.map(t => t.commission) || [0]), 1)
  return Math.max((val / max) * 100, 4)
}

const discountPercent = (price, compareAt) => {
  if (!compareAt || compareAt <= price) return 0
  return Math.round((1 - price / compareAt) * 100)
}

async function loadDashboard() {
  try {
    const res = await getStoreAffiliateDashboard()
    dashboard.value = res.data?.data || res.data || {}
  } catch { dashboard.value = {} }
}

async function loadSkus() {
  try {
    const res = await getPromotableSkus()
    skus.value = res.data?.data || res.data || []
  } catch { skus.value = [] }
}

async function generateLinks() {
  if (!selectedSkus.value.length) return
  try {
    const res = await generateAffiliateLinks(selectedSkus.value)
    generatedLinks.value = res.data?.data || res.data || []
    if (generatedLinks.value.length) {
      ElMessage.success(t(`${ns}.messages.links_generated`, { count: generatedLinks.value.length }))
    }
  } catch (e) {
    const msg = e?.response?.data?.message || e?.response?.data?.error?.message || t(`${ns}.messages.generate_links_failed`)
    ElMessage.warning(msg)
  }
}

function copyLink(link) {
  navigator.clipboard?.writeText(link)
  ElMessage.success(t(`${ns}.messages.link_copied`))
}

async function loadOrders(page = 1) {
  loadingOrders.value = true
  orderPagination.current_page = page
  try {
    const res = await getStoreAffiliateOrders({ page, per_page: orderPagination.per_page })
    const data = res.data?.data || res.data || []
    affiliateOrders.value = Array.isArray(data) ? data : []
    Object.assign(orderPagination, res.data?.meta || { current_page: page, per_page: orderPagination.per_page, total: 0 })
  } catch { affiliateOrders.value = [] }
  finally { loadingOrders.value = false }
}

async function loadLinks(page = 1) {
  loadingLinks.value = true
  linkPagination.current_page = page
  try {
    const res = await getStoreAffiliateLinks({ page, per_page: linkPagination.per_page })
    const data = res.data?.data || res.data || []
    affiliateLinks.value = Array.isArray(data) ? data : []
    Object.assign(linkPagination, res.data?.meta || { current_page: page, per_page: linkPagination.per_page, total: 0 })
  } catch { affiliateLinks.value = [] }
  finally { loadingLinks.value = false }
}

const allSelected = computed(() => {
  const filtered = filteredSkus.value
  return filtered.length > 0 && selectedSkus.value.length === filtered.length && skus.value.every(s => selectedSkus.value.includes(s.id))
})

const categories = computed(() => {
  const map = {}
  skus.value.forEach(s => {
    const id = s.category_id || '0'
    if (!map[id]) map[id] = { id: s.category_id, name: s.category_name || t(`${ns}.labels.uncategorized`), count: 0 }
    map[id].count++
  })
  return Object.values(map).sort((a, b) => b.count - a.count)
})

const filteredSkus = computed(() => {
  if (!activeCategory.value) return skus.value
  return skus.value.filter(s => s.category_id === activeCategory.value)
})

const groupedSkus = computed(() => {
  const map = {}
  filteredSkus.value.forEach(s => {
    const key = s.category_id || '0'
    if (!map[key]) map[key] = { category_id: s.category_id, category_name: s.category_name || t(`${ns}.labels.uncategorized`), items: [] }
    map[key].items.push(s)
  })
  return Object.values(map)
})

function toggleSku(id) {
  const idx = selectedSkus.value.indexOf(id)
  if (idx >= 0) selectedSkus.value.splice(idx, 1)
  else selectedSkus.value.push(id)
}

function selectAllSkus() {
  const filtered = filteredSkus.value
  if (allSelected.value) {
    selectedSkus.value = []
  } else {
    const existing = new Set(selectedSkus.value)
    filtered.forEach(s => existing.add(s.id))
    selectedSkus.value = [...existing]
  }
}

async function generateSingleLink(sku) {
  selectedSkus.value = [sku.id]
  await generateLinks()
}

// ─── 推广功能（参考电商联盟） ───

function buildPromoText(sku) {
  const name = sku.product_name || sku.name
  const price = `¥${formatNum(sku.price)}`
  const commission = `¥${formatNum(sku.commission_amount)}`
  const rate = `${sku.commission_rate}%`
  const original = sku.compare_at_price && sku.compare_at_price > sku.price
    ? t(`${ns}.promo.original_price`, { price: formatNum(sku.compare_at_price) })
    : ''
  return [
    `【${name}】`,
    t(`${ns}.promo.price`, { price }) + original,
    t(`${ns}.promo.commission`, { amount: commission, rate }),
    t(`${ns}.promo.sold`, { count: sku.sold_count || 0 }),
    t(`${ns}.promo.link`, { link: generateTempLink(sku) }),
    '',
    t(`${ns}.promo.footer`),
  ].join('\n')
}

function buildPromoHtml(sku) {
  const name = sku.product_name || sku.name
  const price = `¥${formatNum(sku.price)}`
  const commission = `¥${formatNum(sku.commission_amount)}`
  const imgUrl = sku.image_url || ''
  const link = generateTempLink(sku)
  return `<a href="${link}" target="_blank" style="text-decoration:none;color:#333;">` +
    `<table style="border:1px solid #e8e8e8;border-radius:8px;padding:12px;max-width:400px;font-family:Arial,sans-serif;">` +
    `<tr><td style="width:100px;vertical-align:top;">` +
    (imgUrl ? `<img src="${imgUrl}" width="100" height="100" style="border-radius:6px;object-fit:cover;" />` : '') +
    `</td><td style="padding-left:12px;vertical-align:top;">` +
    `<div style="font-size:14px;font-weight:bold;margin-bottom:6px;">${name}</div>` +
    `<div style="font-size:18px;color:#f56c6c;font-weight:bold;">${price}</div>` +
    `<div style="font-size:12px;color:#e6a23c;margin-top:4px;">${t(`${ns}.promo.html_commission`, { amount: commission, rate: `${sku.commission_rate}%` })}</div>` +
    `</td></tr></table></a>`
}

function generateTempLink(sku) {
  const base = window.location.origin
  const code = sku.sku_code || sku.id
  return `${base}/products/${sku.product_name ? sku.product_name.replace(/\s+/g, '-').toLowerCase() : 'product'}?ref=AFFILIATE&sku=${sku.id}`
}

async function handlePromote(sku, cmd) {
  if (cmd === 'link') {
    selectedSkus.value = [sku.id]
    await generateLinks()
    if (generatedLinks.value.length) {
      copyToClipboard(generatedLinks.value[0].link, t(`${ns}.messages.promo_link_copied`))
    }
    return
  }
  if (cmd === 'text') {
    copyToClipboard(buildPromoText(sku), t(`${ns}.messages.promo_text_copied`))
    return
  }
  if (cmd === 'html') {
    copyToClipboard(buildPromoHtml(sku), t(`${ns}.messages.promo_html_copied`))
    return
  }
  if (cmd === 'qrcode') {
    const link = generateTempLink(sku)
    qrDialog.value = { visible: true, title: sku.product_name || sku.name, link }
    return
  }
  if (cmd === 'image') {
    const imgUrl = sku.image_url
    if (!imgUrl) { ElMessage.warning(t(`${ns}.messages.no_product_image`)); return }
    // 在新标签页打开图片，用户可右键保存
    window.open(imgUrl, '_blank')
    ElMessage.success(t(`${ns}.messages.image_opened`))
  }
}

function copyToClipboard(text, msg) {
  navigator.clipboard?.writeText(text).then(() => {
    ElMessage.success(msg || t(`${ae}.messages.copied`))
  }).catch(() => {
    // Fallback
    const ta = document.createElement('textarea')
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'
    document.body.appendChild(ta); ta.select(); document.execCommand('copy')
    document.body.removeChild(ta)
    ElMessage.success(msg || t(`${ae}.messages.copied`))
  })
}

function downloadQr(link, title) {
  const url = `https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=${encodeURIComponent(link)}`
  const a = document.createElement('a')
  a.href = url; a.download = `promo-qr_${title || 'product'}.png`
  a.target = '_blank'
  a.click()
  ElMessage.success(t(`${ns}.messages.qrcode_opened`))
}

function loadAll() {
  loadDashboard()
  loadOrders()
  loadLinks()
}

onMounted(() => {
  loadAll()
  loadSkus()
})
</script>

<style scoped>
.store-affiliate-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.mb-6 { margin-bottom: 24px; }
.mb-4 { margin-bottom: 16px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; color: #303133; }
.text-warning { color: #e6a23c; }

/* ===== 分类筛选 ===== */
.category-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid #f0f0f0; }
.cat-pill { padding: 6px 16px; border-radius: 20px; border: 1px solid #e4e7ed; background: #fff; font-size: 13px; color: #606266; cursor: pointer; transition: all .2s; }
.cat-pill:hover { border-color: #0f172a; color: #0f172a; }
.cat-pill.active { background: #0f172a; color: #fff; border-color: #0f172a; }

/* ===== 分类分组标题 ===== */
.affiliate-sections { display: flex; flex-direction: column; gap: 28px; }
.section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #e8f4ff; }
.section-badge { font-size: 16px; font-weight: 700; color: #303133; position: relative; padding-left: 14px; }
.section-badge::before { content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%); width: 4px; height: 18px; background: #0f172a; border-radius: 2px; }
.section-count { font-size: 12px; color: #909399; }

/* ===== 商品卡片网格 ===== */
.affiliate-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; }

/* ===== 单张商品卡片（电商联盟主流样式） ===== */
.affiliate-product-card {
  position: relative;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  transition: all .25s;
  display: flex;
  flex-direction: column;
}
.affiliate-product-card:hover { border-color: #0f172a; box-shadow: 0 6px 20px rgba(15,23,42,.12); transform: translateY(-3px); }
.affiliate-product-card.selected { border-color: #0f172a; box-shadow: 0 0 0 2px rgba(15,23,42,.2); }

/* 选中遮罩 */
.apc-select-mask { position: absolute; top: 8px; left: 8px; z-index: 2; background: rgba(255,255,255,.85); border-radius: 4px; padding: 2px; line-height: 1; }

/* 商品图片 */
.apc-image { position: relative; width: 100%; aspect-ratio: 1/1; background: #f5f7fa; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.apc-image img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s; }
.affiliate-product-card:hover .apc-image img { transform: scale(1.05); }
.apc-discount-badge { position: absolute; top: 8px; right: 8px; background: #f56c6c; color: #fff; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; z-index: 1; }

/* 商品信息 */
.apc-body { padding: 12px 14px 8px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
.apc-name { font-size: 14px; font-weight: 600; color: #303133; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 40px; }
.apc-name:hover { color: #0f172a; }
.apc-meta { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.apc-category { font-size: 11px; color: #0f172a; background: #f1f5f9; padding: 1px 8px; border-radius: 3px; }
.apc-sold { font-size: 11px; color: #c0c4cc; }
.apc-price-row { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
.apc-price { font-size: 20px; font-weight: 700; color: #f56c6c; }
.apc-original-price { font-size: 12px; color: #c0c4cc; text-decoration: line-through; }

/* 底部：佣金 + 操作 */
.apc-footer { padding: 10px 14px 14px; border-top: 1px solid #f5f5f5; display: flex; flex-direction: column; gap: 10px; }
.apc-commission-tag { display: flex; align-items: center; gap: 6px; justify-content: center; background: #fff7e6; border: 1px solid #ffe58f; border-radius: 6px; padding: 6px 10px; width: 100%; box-sizing: border-box; }
.apc-rate { font-size: 16px; font-weight: 700; color: #e6a23c; }
.apc-amount { font-size: 13px; color: #e6a23c; font-weight: 500; }
.apc-actions { display: flex; }
.apc-actions .el-dropdown { display: flex; width: 100%; }
.apc-copy-btn { width: 100%; border-radius: 8px; font-size: 13px; padding: 9px 0; background: #f56c6c; border-color: #f56c6c; color: #fff; font-weight: 500; letter-spacing: 0.5px; display: flex; justify-content: center; }
.apc-copy-btn:hover { background: #e04040; border-color: #e04040; color: #fff; }
/* 已生成链接 */
.generated-links-section { margin-top: 12px; }
.section-title { font-size: 14px; font-weight: 600; margin-bottom: 12px; }
.generated-link-item { display: flex; flex-direction: column; gap: 6px; padding: 10px 12px; background: #fafafa; border-radius: 6px; margin-bottom: 8px; }
.gl-product { display: flex; justify-content: space-between; font-size: 13px; }
.gl-rate { color: #e6a23c; font-size: 12px; }
.gl-link-row { display: flex; }
.gl-input { flex: 1; }
.gl-input :deep(.el-input__inner) { font-size: 12px; font-family: monospace; }
.card-header-flex { display: flex; justify-content: space-between; align-items: center; }

/* ===== 推广二维码弹窗 ===== */
.qr-container { display: flex; flex-direction: column; align-items: center; padding: 10px 0; }
.qr-canvas-wrap { width: 210px; height: 210px; background: #fff; border: 1px solid #e8e8e8; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 4px; }
.qr-canvas { width: 200px; height: 200px; border-radius: 8px; }
.qr-link-text { font-size: 12px; color: #909399; margin-top: 12px; word-break: break-all; text-align: center; max-width: 300px; font-family: monospace; }
.qr-actions { display: flex; gap: 8px; margin-top: 12px; }

/* 趋势图 */
.trend-chart { display: flex; align-items: flex-end; justify-content: space-between; height: 140px; padding-top: 20px; gap: 8px; }
.trend-bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; }
.trend-label { font-size: 11px; color: #909399; }
.trend-bar-wrapper { flex: 1; display: flex; align-items: flex-end; width: 100%; }
.trend-bar { width: 100%; max-width: 32px; background: linear-gradient(to top, #0f172a, #94a3b8); border-radius: 4px 4px 0 0; min-height: 4px; margin: 0 auto; transition: height .3s; }
.trend-value { font-size: 10px; color: #606266; }
</style>
