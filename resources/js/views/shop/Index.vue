<template>
  <div class="shop-page">
    <div class="shop-header">
      <div class="shop-header-content">
        <h1>{{ t('shop_page.title') }}</h1>
        <p class="text-muted">{{ t('shop_page.subtitle') }}</p>
      </div>
      <div class="header-actions">
        <el-input
          v-model="searchQuery"
          :placeholder="t('shop_page.search_ph')"
          clearable
          class="search-input"
          @input="onSearchInput"
          @clear="onSearchClear"
          @keyup.enter="doSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
        <div class="cart-badge" @click="$router.push('/portal/cart')">
          <el-badge :value="cartItemCount" :hidden="!cartItemCount">
            <el-button circle><el-icon><ShoppingCart /></el-icon></el-button>
          </el-badge>
        </div>
      </div>
    </div>

    <!-- 热门搜索 -->
    <div v-if="!searchQuery && hotTerms.length" class="hot-search-bar">
      <span class="hot-label">{{ t('shop_page.hot_search') }}</span>
      <el-tag
        v-for="term in hotTerms" :key="term"
        size="small"
        class="hot-tag"
        @click="searchQuery = term; doSearch()"
      >{{ term }}</el-tag>
    </div>

    <!-- 搜索建议 -->
    <div v-if="searchQuery && suggestions.length" class="suggestions-dropdown">
      <div v-for="s in suggestions" :key="s" class="suggestion-item" @click="searchQuery = s; doSearch()">
        <el-icon><Search /></el-icon> {{ s }}
      </div>
    </div>

    <!-- 筛选栏 -->
    <el-card shadow="hover" class="filter-bar">
      <el-row :gutter="12" align="middle">
        <el-col :xs="24" :sm="6">
          <el-select v-model="filters.category_id" :placeholder="t('shop_page.all_categories')" clearable style="width:100%" @change="loadProducts">
            <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-col>
        <el-col :xs="12" :sm="4">
          <el-select v-model="filters.billing_cycle" :placeholder="t('shop_page.all_cycles')" clearable style="width:100%" @change="loadProducts">
            <el-option v-for="opt in billingCycleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-col>
        <el-col :xs="12" :sm="5">
          <el-input v-model="filters.price_min" :placeholder="t('shop_page.price_min')" type="number" min="0" style="width:100%" @change="loadProducts" />
        </el-col>
        <el-col :xs="12" :sm="5">
          <el-input v-model="filters.price_max" :placeholder="t('shop_page.price_max')" type="number" min="0" style="width:100%" @change="loadProducts" />
        </el-col>
        <el-col :xs="12" :sm="4">
          <el-select v-model="filters.sort" style="width:100%" @change="loadProducts">
            <el-option v-for="opt in sortOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-col>
      </el-row>
      <!-- 标签筛选 -->
      <div v-if="filterTags.length" class="tag-filters">
        <span class="tag-label">{{ t('shop_page.tags') }}</span>
        <el-tag
          v-for="tag in filterTags" :key="tag.id"
          :type="selectedTags.includes(tag.id) ? 'primary' : 'info'"
          size="small"
          :effect="selectedTags.includes(tag.id) ? 'dark' : 'plain'"
          @click="toggleTag(tag.id)"
        >{{ tag.name }}</el-tag>
      </div>
    </el-card>

    <!-- 搜索状态 -->
    <div v-if="searchQuery" class="search-status">
      {{ t('shop_page.search_result', { q: searchQuery, n: pagination.total }) }}
      <el-button text type="primary" @click="clearSearch">{{ t('shop_page.clear_filters') }}</el-button>
    </div>

    <!-- 搜索历史 -->
    <div v-if="!searchQuery && searchHistory.length && isLoggedIn" class="search-history-bar">
      <span class="history-label">{{ t('shop_page.search_history') }}</span>
      <el-tag
        v-for="term in searchHistory" :key="term"
        size="small"
        closable
        class="history-tag"
        @click="searchQuery = term; doSearch()"
        @close="searchHistory = searchHistory.filter(t => t !== term)"
      >{{ term }}</el-tag>
      <el-button text size="small" @click="clearAllHistory">{{ t('shop_page.clear') }}</el-button>
    </div>

    <!-- 商品列表 -->
    <div v-if="products.length" class="product-grid">
      <div v-for="sku in products" :key="sku.id" class="product-card">
        <el-card shadow="hover" class="product-card-inner">
          <!-- 产品主图 + 收藏按钮 -->
          <div class="product-image-wrap">
            <el-image
              v-if="sku.product?.image_url || sku.image_url"
              :src="sku.product?.image_url || sku.image_url"
              fit="cover"
              class="product-image"
            >
              <template #error>
                <div class="image-placeholder">
                  <el-icon :size="32"><Picture /></el-icon>
                </div>
              </template>
            </el-image>
            <div v-else class="image-placeholder">
              <el-icon :size="32"><Picture /></el-icon>
            </div>
            <div class="wishlist-corner">
              <WishlistButton
                v-if="isLoggedIn"
                :product-id="sku.product_id"
              />
            </div>
          </div>

          <!-- 产品名称 -->
          <div class="product-name" v-html="sku.product_name_highlighted || sku.product?.name || sku.name"></div>

          <!-- 产品描述 -->
          <div class="product-desc" v-if="sku.product?.description">{{ sku.product.description }}</div>

          <div class="sku-code" v-if="sku.sku_code">{{ sku.sku_code_highlighted || sku.sku_code }}</div>

          <div class="sku-price-section">
            <span class="price">¥{{ sku.price }}</span>
            <span v-if="sku.compare_at_price && sku.compare_at_price > sku.price" class="original-price">
              ¥{{ sku.compare_at_price }}
            </span>
            <span class="cycle-badge">{{ cycleLabel(sku.billing_cycle) }}</span>
          </div>

          <div class="sku-meta">
            <span>{{ t('shop_page.sold', { n: sku.sold_count || 0 }) }}</span>
            <span v-if="sku.stock !== null && sku.stock !== undefined">
              / {{ sku.stock === 0 ? t('shop_page.sold_out') : t('shop_page.stock', { n: sku.stock }) }}
            </span>
            <span v-if="sku.product?.review_stats" class="review-stats" @click.stop="openReviewDrawer(sku)">
              <el-rate :model-value="sku.product.review_stats.avg_rating || 0" disabled size="small" show-score :score-template="''" />
              <span class="review-count">({{ sku.product.review_stats.total }})</span>
            </span>
            <span v-else class="review-stats no-reviews" @click.stop="openReviewDrawer(sku)">
              <el-icon :size="12"><ChatDotRound /></el-icon>
              <span class="review-count">{{ t('shop_page.write_review') }}</span>
            </span>
          </div>

          <!-- 商品标签 -->
          <div class="sku-tags">
            <el-tag v-if="sku.product?.category?.name" size="small" type="info" effect="plain">{{ sku.product.category.name }}</el-tag>
            <el-tag v-if="sku.compare_at_price && sku.compare_at_price > sku.price" size="small" type="danger" effect="dark">
              {{ discountPercent(sku.price, sku.compare_at_price) }}% OFF
            </el-tag>
            <el-tag v-if="sku.sold_count > 50" size="small" color="#f56c6c" effect="dark" style="color:#fff">{{ t('shop_page.hot') }}</el-tag>
          </div>

          <div class="sku-actions">
            <el-button
              type="primary"
              :disabled="sku.stock === 0"
              @click="addToCart(sku.id)"
              class="btn-cart"
            >
              <el-icon><Plus /></el-icon>
              {{ sku.stock === 0 ? t('shop_page.out_of_stock') : t('shop_page.add_cart') }}
            </el-button>
            <el-button
              type="danger"
              plain
              :disabled="sku.stock === 0"
              @click="quickBuy(sku.id)"
              :loading="buyingId === sku.id"
              class="btn-buy"
            >
              {{ t('shop_page.buy_now') }}
            </el-button>
          </div>
        </el-card>
      </div>
    </div>
    <el-empty v-else-if="!loading" :description="searchQuery ? t('shop_page.empty_search') : t('shop_page.empty')" :image-size="80" />

    <el-pagination
      v-if="pagination.total > pagination.per_page"
      background
      layout="prev,pager,next,total"
      :total="pagination.total"
      :page-size="pagination.per_page"
      :current-page="pagination.current_page"
      @current-change="onPageChange"
      style="margin-top:20px;justify-content:center"
    />
  </div>

  <!-- 商品详情抽屉（评论 + 收藏） -->
  <el-drawer
    v-model="detailDrawer.visible"
    :title="detailDrawer.productName"
    :size="isMobile ? '100%' : '500px'"
    direction="rtl"
  >
    <template v-if="detailDrawer.productId">
      <ProductReviews :product-id="detailDrawer.productId" />
    </template>
  </el-drawer>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { useResponsive } from '@/composables/useResponsive';
import WishlistButton from '@/components/shop/WishlistButton.vue';
import ProductReviews from '@/components/shop/ProductReviews.vue';
import { Search, ShoppingCart, Plus, Picture, ChatDotRound } from '@element-plus/icons-vue';
import shopApi from '@/api/shop';

const { t } = useI18n();
const { isMobile } = useResponsive();

const loading = ref(false);
const products = ref([]);
const categories = ref([]);
const filterTags = ref([]);
const hotTerms = ref([]);
const searchHistory = ref([]);
const cartItemCount = ref(0);
const searchQuery = ref('');
const suggestions = ref([]);
const selectedTags = ref([]);
const isLoggedIn = ref(!!localStorage.getItem('auth_token'));
const buyingId = ref(null);

const pagination = reactive({
  current_page: 1, per_page: 20, total: 0, last_page: 1,
});

const filters = reactive({
  category_id: '',
  billing_cycle: '',
  price_min: '',
  price_max: '',
  sort: '-sold_count',
});

const detailDrawer = reactive({
  visible: false,
  productId: null,
  productName: '',
});

const billingCycleOptions = computed(() => [
  { label: t('shop_page.cycle_monthly'), value: 'monthly' },
  { label: t('shop_page.cycle_quarterly'), value: 'quarterly' },
  { label: t('shop_page.cycle_yearly'), value: 'yearly' },
  { label: t('shop_page.cycle_lifetime'), value: 'lifetime' },
  { label: t('shop_page.cycle_onetime'), value: 'one-time' },
]);

const sortOptions = computed(() => [
  { label: t('shop_page.sort_sold'), value: '-sold_count' },
  { label: t('shop_page.sort_price_asc'), value: 'price' },
  { label: t('shop_page.sort_price_desc'), value: '-price' },
  { label: t('shop_page.sort_newest'), value: '-created_at' },
  { label: t('shop_page.sort_name'), value: 'name' },
]);

function openReviewDrawer(sku) {
  detailDrawer.productId = sku.product_id;
  detailDrawer.productName = sku.product?.name || sku.name || t('shop_page.product_detail');
  detailDrawer.visible = true;
}

let suggestTimer = null;

function cycleLabel(cycle) {
  const map = {
    monthly: t('shop_page.cycle_monthly'),
    quarterly: t('shop_page.cycle_quarterly'),
    yearly: t('shop_page.cycle_yearly'),
    lifetime: t('shop_page.cycle_lifetime'),
    'one-time': t('shop_page.cycle_onetime'),
  };
  return map[cycle] || cycle || '—';
}

function discountPercent(price, compareAt) {
  if (!compareAt || compareAt <= price) return 0;
  return Math.round((1 - price / compareAt) * 100);
}

async function loadProducts(page = 1) {
  loading.value = true;
  pagination.current_page = page;
  try {
    const params = {
      search: searchQuery.value || undefined,
      page,
      per_page: pagination.per_page,
      sort: filters.sort || '-sold_count',
    };
    if (filters.category_id) params.category_id = filters.category_id;
    if (filters.billing_cycle) params.billing_cycle = filters.billing_cycle;
    if (filters.price_min) params.price_min = filters.price_min;
    if (filters.price_max) params.price_max = filters.price_max;
    if (selectedTags.value.length) params.tags = selectedTags.value.join(',');

    const res = await shopApi.getSkus(params);
    const data = res.data?.data || res.data;
    // Pagination from Laravel paginator
    products.value = data?.data || data || [];
    Object.assign(pagination, {
      current_page: data?.current_page || page,
      per_page: data?.per_page || 20,
      total: data?.total || 0,
      last_page: data?.last_page || 1,
    });
  } catch { products.value = []; }
  finally { loading.value = false; }
}

async function loadMetadata() {
  try {
    const [tagsRes, hotRes, catRes] = await Promise.allSettled([
      shopApi.getFilterTags(),
      shopApi.getHotSearchTerms(),
      shopApi.getCategories(),
    ]);
    if (tagsRes.status === 'fulfilled') {
      const r = tagsRes.value;
      filterTags.value = r.data?.data || r.data || [];
    }
    if (hotRes.status === 'fulfilled') {
      const r = hotRes.value;
      hotTerms.value = r.data?.data || r.data || [];
    }
    if (catRes.status === 'fulfilled') {
      const r = catRes.value;
      categories.value = r.data?.data || r.data || [];
    }
  } catch { /* ignore */ }
}

async function loadSearchHistory() {
  if (!isLoggedIn.value) return;
  try {
    const res = await shopApi.getSearchHistory();
    searchHistory.value = res.data?.data || res.data || [];
  } catch { /* ignore */ }
}

function onSearchInput() {
  clearTimeout(suggestTimer);
  const q = searchQuery.value?.trim();
  if (q.length < 2) { suggestions.value = []; return; }
  suggestTimer = setTimeout(async () => {
    try {
      const res = await shopApi.getSearchSuggestions(q);
      suggestions.value = res.data?.data || res.data || [];
    } catch { suggestions.value = []; }
  }, 200);
}

function onSearchClear() {
  suggestions.value = [];
  loadProducts();
}

function doSearch() {
  suggestions.value = [];
  loadProducts();
}

function clearSearch() {
  searchQuery.value = '';
  filters.category_id = '';
  filters.billing_cycle = '';
  filters.price_min = '';
  filters.price_max = '';
  selectedTags.value = [];
  loadProducts();
}

function toggleTag(tagId) {
  const idx = selectedTags.value.indexOf(tagId);
  if (idx >= 0) selectedTags.value.splice(idx, 1);
  else selectedTags.value.push(tagId);
  loadProducts();
}

async function clearAllHistory() {
  try {
    await shopApi.clearSearchHistory();
    searchHistory.value = [];
    ElMessage.success(t('shop_page.history_cleared'));
  } catch { /* ignore */ }
}

async function addToCart(skuId) {
  try {
    await shopApi.addToCart({ sku_id: skuId, quantity: 1 });
    ElMessage.success(t('shop_page.cart_ok'));
    cartItemCount.value++;
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('shop_page.cart_fail'));
  }
}

// 立即购买：一键加购→下单→跳转支付
async function quickBuy(skuId) {
  buyingId.value = skuId;
  try {
    const res = await shopApi.quickBuy({ sku_id: skuId, quantity: 1 });
    const data = res.data?.data || res.data;
    const order = data?.order || data;
    ElMessage.success(t('shop_page.order_ok'));

    // 跳转到支付选择页
    if (order?.id) {
      window.location.href = `/portal/payment-result/${order.id}`;
    } else {
      ElMessage.success(t('shop_page.order_ok'));
    }
  } catch (e) {
    ElMessage.error(e?.response?.data?.message || t('shop_page.order_fail'));
  } finally {
    buyingId.value = null;
  }
}

function onPageChange(p) { loadProducts(p); }

async function loadCartCount() {
  try {
    const res = await shopApi.getCartSummary();
    cartItemCount.value = res.data?.data?.item_count || 0;
  } catch { /* ignore */ }
}

onMounted(async () => {
  await Promise.all([
    loadProducts(),
    loadMetadata(),
    loadSearchHistory(),
    loadCartCount(),
  ]);
});
</script>

<style scoped>
.shop-page { padding: 16px; min-width: 0; overflow-x: clip; max-width: 100%; }
.shop-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; gap: 16px; flex-wrap: wrap; }
.shop-header h1 { margin: 0 0 4px; font-size: 22px; }
.shop-header .text-muted { margin: 0; color: #909399; font-size: 13px; }
.header-actions { display: flex; align-items: center; gap: 12px; }
.search-input { width: 280px; }
.cart-badge { cursor: pointer; }
.hot-search-bar { margin-bottom: 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.hot-label { font-size: 13px; color: #909399; }
.hot-tag { cursor: pointer; }
.suggestions-dropdown { background: #fff; border: 1px solid #e4e7ed; border-radius: 4px; padding: 8px 0; margin-bottom: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
.suggestion-item { padding: 8px 16px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px; }
.suggestion-item:hover { background: #f5f7fa; }
.filter-bar { margin-bottom: 16px; }
.tag-filters { margin-top: 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.tag-label { font-size: 13px; color: #909399; }
.search-status { font-size: 13px; color: #606266; margin-bottom: 16px; }
.search-history-bar { margin-bottom: 12px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.history-label { font-size: 13px; color: #909399; }
.history-tag { cursor: pointer; }
.product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 280px), 1fr)); gap: 16px; }
.product-card-inner { cursor: default; }
.product-image-wrap { position: relative; width: 100%; height: 160px; border-radius: 8px; overflow: hidden; margin-bottom: 12px; background: #f5f7fa; }
.product-image { width: 100%; height: 100%; object-fit: cover; }
.image-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #c0c4cc; background: #f5f7fa; }
.wishlist-corner { position: absolute; top: 6px; right: 6px; }
.sku-meta { font-size: 12px; color: #909399; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.review-stats { display: inline-flex; align-items: center; gap: 2px; cursor: pointer; }
.review-stats:hover { color: #0f172a; }
.review-stats.no-reviews { color: #c0c4cc; font-size: 12px; }
.review-stats.no-reviews:hover { color: #0f172a; }
.review-count { font-size: 11px; color: #c0c4cc; }
.product-name { font-size: 16px; font-weight: 600; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-desc { font-size: 13px; color: #606266; margin-bottom: 8px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 39px; }
.sku-code { font-size: 11px; color: #c0c4cc; font-family: monospace; margin-bottom: 8px; }
.sku-price-section { display: flex; align-items: baseline; gap: 8px; margin: 10px 0 4px; }
.price { font-size: 22px; font-weight: 700; color: #0f172a; }
.original-price { font-size: 14px; color: #c0c4cc; text-decoration: line-through; }
.cycle-badge { font-size: 11px; background: #f1f5f9; color: #0f172a; padding: 2px 8px; border-radius: 4px; }
.sku-meta { font-size: 12px; color: #909399; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; min-height: 22px; }
.sku-tags { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; min-height: 24px; }
.sku-actions { display: flex; gap: 10px; margin-top: 12px; }
.sku-actions .el-button { flex: 1; justify-content: center; }
.btn-cart { border-radius: 6px; }
.btn-buy { border-radius: 6px; }
:deep(.search-highlight) { background: #fff3cd; padding: 0 2px; border-radius: 2px; }

@media (max-width: 768px) {
    .shop-page { padding: 12px; }
    .shop-header {
        flex-direction: column;
        align-items: stretch;
    }
    .shop-header h1 { font-size: 20px; }
    .header-actions {
        width: 100%;
        flex-direction: column;
        align-items: stretch;
    }
    .search-input { width: 100%; }
    .product-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .sku-actions {
        flex-direction: column;
    }
    .filter-bar :deep(.el-col) {
        margin-bottom: 8px;
    }
}

@media (max-width: 480px) {
    .product-image-wrap { height: 140px; }
    .price { font-size: 20px; }
}
</style>
