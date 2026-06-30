const fs = require('fs');
const p = 'resources/js/views/checkout/Index.vue';

// Read the template part
const template = fs.readFileSync(p, 'utf8');

// The rest of the file (script + style)
const rest = `
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import { Box, Wallet, Coin, Document } from '@element-plus/icons-vue';
import orderApi from '@/api/order';

const route = useRoute();
const router = useRouter();
const loading = ref(true);
const error = ref('');
const submitting = ref(false);
const paying = ref(false);
const product = ref(null);
const plans = ref([]);
const selectedPlanId = ref(null);
const quantity = ref(1);
const couponCode = ref('');
const discount = ref(0);
const showPayDialog = ref(false);
const paymentMethod = ref('alipay');
const createdOrder = ref(null);
const siteSettings = ref({});
const paymentMethods = ref([
  { value: 'alipay', label: '\u652f\u4ed8\u5b9d', desc: '\u652f\u4ed8\u5b9d' },
  { value: 'wxpay', label: '\u5fae\u4fe1\u652f\u4ed8', desc: '\u5fae\u4fe1\u652f\u4ed8' },
  { value: 'balance', label: '\u4f59\u989d\u652f\u4ed8', desc: '\u4f59\u989d\u652f\u4ed8' },
]);

const selectedPlan = computed(() => plans.value.find(p => p.id === selectedPlanId.value));
const subtotal = computed(() => { const p = selectedPlan.value?.price_monthly || 0; return (p * quantity.value).toFixed(2); });
const total = computed(() => (parseFloat(subtotal.value) - discount.value).toFixed(2));

onMounted(async () => {
  fetch('/api/settings/public').then(r => r.json()).then(d => { if (d.data) siteSettings.value = d.data; }).catch(() => {});
  const planId = route.query.plan_id;
  const skuId = route.query.sku_id;
  const productSlug = route.query.product;
  try {
    const prodResp = await fetch('/api/public/products?per_page=50').then(r => r.json());
    const allProducts = prodResp.data || [];
    if (skuId) {
      try {
        const token = localStorage.getItem('auth_token');
        const r = await fetch('/api/admin/product-skus/' + skuId, { headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' } });
        const d = await r.json();
        const sku = d.data || d;
        if (sku && sku.id) {
          product.value = allProducts.find(p => p.id == sku.product_id) || { name: '\u4ea7\u54c1', id: sku.product_id };
          plans.value = [{ id: sku.id, product_id: sku.product_id, name: sku.name, description: '', price_monthly: parseFloat(sku.price) || 0, sku_id: sku.id }];
          selectedPlanId.value = sku.id;
        }
      } catch(e) {}
    }
    if ((!plans.value || plans.value.length === 0) && planId) {
      try {
        const r = await fetch('/api/public/pricing-plans').then(r => r.json());
        const allPlans = r.data || [];
        const plan = allPlans.find(p => p.id == planId);
        if (plan) { selectedPlanId.value = plan.id; product.value = allProducts.find(p => p.id == plan.product_id) || { name: plan.name, id: plan.product_id }; plans.value = allPlans.filter(p => p.product_id == plan.product_id); }
      } catch(e) {}
    }
    if ((!plans.value || plans.value.length === 0) && productSlug) {
      product.value = allProducts.find(p => p.slug === productSlug);
      if (product.value) {
        try { const r = await fetch('/api/public/pricing-plans').then(r => r.json()); const allPlans = r.data || []; plans.value = allPlans.filter(p => p.product_id == product.value.id); if (plans.value.length > 0) selectedPlanId.value = plans.value[0].id; } catch(e) {}
      }
    }
    if (!product.value && allProducts.length > 0) product.value = allProducts[0];
    if ((!plans.value || plans.value.length === 0) && product.value) { plans.value = [{ id: 0, product_id: product.value.id, name: '\u6807\u51c6\u6388\u6743', description: '', price_monthly: 0 }]; selectedPlanId.value = 0; }
    if (!product.value) error.value = '\u65e0\u6cd5\u52a0\u8f7d\u4ea7\u54c1\u4fe1\u606f';
  } catch (e) { error.value = '\u65e0\u6cd5\u52a0\u8f7d\u4ea7\u54c1\u4fe1\u606f'; }
  finally { loading.value = false; }
});

function onPlanChange() { discount.value = 0; couponCode.value = ''; }
async function applyCoupon() { ElMessage.info('\u4f18\u60e0\u7801\u9a8c\u8bc1\u529f\u80fd\u5f00\u53d1\u4e2d'); }
async function submitOrder() {
  if (!selectedPlan.value) { ElMessage.warning('\u8bf7\u9009\u62e9\u5b9a\u4ef7\u65b9\u6848'); return; }
  submitting.value = true;
  try {
    const res = await orderApi.create({ items: [{ sku_id: selectedPlan.value.sku_id || selectedPlan.value.id, quantity: quantity.value, item_type: 'license', unit_price: selectedPlan.value.price_monthly }], currency: 'CNY' });
    createdOrder.value = res.data?.data || res.data;
    ElMessage.success('\u8ba2\u5355\u521b\u5efa\u6210\u529f');
    showPayDialog.value = true;
  } catch (e) { ElMessage.error(e?.response?.data?.error?.message || e?.response?.data?.message || '\u4e0b\u5355\u5931\u8d25'); }
  finally { submitting.value = false; }
}
async function handlePay() {
  if (!createdOrder.value) return;
  paying.value = true;
  try {
    await orderApi.markPaid(createdOrder.value.id, { payment_method: paymentMethod.value, transaction_id: 'TXN' + Date.now() });
    ElMessage.success('\u652f\u4ed8\u6210\u529f\uff01\u6388\u6743\u7801\u5df2\u751f\u6210');
    showPayDialog.value = false;
    setTimeout(() => router.push('/billing'), 1500);
  } catch (e) { ElMessage.error(e?.response?.data?.error?.message || '\u652f\u4ed8\u5931\u8d25'); }
  finally { paying.value = false; }
}
</script>

<style scoped>
.checkout-page { min-height: 100vh; background: #f0f2f5; }
.jd-topbar { background: #fff; border-bottom: 1px solid #e8e8e8; position: sticky; top: 0; z-index: 100; }
.jd-topbar-inner { max-width: 990px; margin: 0 auto; display: flex; align-items: center; height: 50px; padding: 0 15px; }
.jd-logo { display: flex; align-items: center; gap: 4px; text-decoration: none; }
.jd-logo-icon { width: 24px; height: 24px; background: #409eff; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; font-weight: 700; }
.jd-logo-text { font-size: 16px; font-weight: 700; color: #222; }
.jd-topbar-nav { margin-left: 24px; display: flex; align-items: center; gap: 6px; font-size: 12px; }
.jd-nav-item { color: #999; }
.jd-nav-sep { color: #ddd; }
.jd-nav-cur { color: #409eff; font-weight: 600; }
.jd-stepbar { background: #fff; border-bottom: 1px solid #e8e8e8; }
.jd-stepbar-inner { max-width: 990px; margin: 0 auto; display: flex; align-items: center; padding: 0 15px; height: 44px; }
.jd-step { display: flex; align-items: center; gap: 4px; font-size: 13px; color: #ccc; padding: 0 10px; position: relative; }
.jd-step + .jd-step::before { content: ''; position: absolute; left: -2px; top: 50%; width: 4px; height: 4px; background: #ddd; border-radius: 50%; margin-top: -2px; }
.jd-step.active { color: #409eff; font-weight: 600; }
.jd-step-num { width: 18px; height: 18px; border-radius: 50%; background: #ccc; color: #fff; font-size: 11px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
.jd-step.active .jd-step-num { background: #409eff; }
.main-wrap { max-width: 990px; margin: 0 auto; padding: 15px; }
.main-layout { display: flex; gap: 15px; align-items: flex-start; }
.main-left { flex: 1; min-width: 0; }
.main-right { width: 280px; flex-shrink: 0; }
.goods-table { background: #fff; border: 1px solid #e8e8e8; margin-bottom: 15px; }
.goods-thead { display: flex; align-items: center; height: 36px; background: #f5f5f5; border-bottom: 1px solid #e8e8e8; padding: 0 12px; font-size: 12px; color: #888; }
.goods-trow { display: flex; align-items: center; padding: 12px; font-size: 13px; color: #333; }
.goods-col-info { flex: 1; display: flex; align-items: center; gap: 10px; }
.goods-col-price { width: 100px; text-align: center; }
.goods-col-qty { width: 50px; text-align: center; }
.goods-col-total { width: 80px; text-align: right; color: #409eff; font-weight: 600; }
.goods-img { width: 48px; height: 48px; background: #f9f9f9; border: 1px solid #eee; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.goods-name { font-size: 13px; color: #333; }
.goods-name:hover { color: #409eff; }
.goods-sku { font-size: 11px; color: #999; margin-top: 1px; }
.section-h2 { font-size: 14px; font-weight: 700; color: #333; padding-left: 10px; margin-bottom: 8px; border-left: 3px solid #409eff; line-height: 1; }
.plan-tmall { background: #fff; border: 1px solid #e8e8e8; margin-bottom: 15px; padding: 4px; }
.plan-trow { display: flex; align-items: center; gap: 8px; padding: 10px 12px; cursor: pointer; border: 1px solid transparent; margin: 2px 0; }
.plan-trow:hover { background: #f5f5f5; }
.plan-trow.sel { background: #ecf5ff; border-color: #409eff; }
.plan-tradio { position: absolute; opacity: 0; width: 0; height: 0; }
.plan-tdot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #d0d0d0; display: inline-block; position: relative; flex-shrink: 0; }
.plan-tdot.on { border-color: #409eff; }
.plan-tdot.on::after { content: ''; position: absolute; inset: 2px; border-radius: 50%; background: #409eff; }
.plan-tname { font-size: 13px; font-weight: 600; color: #333; width: 70px; }
.plan-tsub { font-size: 12px; color: #999; flex: 1; }
.plan-tprice { font-size: 14px; font-weight: 700; color: #409eff; }
.form-row { display: flex; gap: 24px; background: #fff; border: 1px solid #e8e8e8; padding: 14px 16px; margin-bottom: 15px; }
.form-label { font-size: 12px; color: #888; display: block; margin-bottom: 6px; }
.qty-tmall { display: inline-flex; align-items: center; border: 1px solid #d9d9d9; }
.qty-tbtn { width: 28px; height: 28px; border: none; background: #fafafa; font-size: 14px; color: #666; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.qty-tbtn:hover:not(:disabled) { background: #eee; }
.qty-tbtn:disabled { opacity: 0.3; cursor: not-allowed; }
.qty-tnum { width: 40px; text-align: center; font-size: 13px; color: #333; border-left: 1px solid #d9d9d9; border-right: 1px solid #d9d9d9; line-height: 28px; }
.coupon-tmall { display: flex; }
.coupon-tinp { width: 160px; height: 28px; padding: 0 8px; border: 1px solid #d9d9d9; border-right: none; font-size: 12px; outline: none; }
.coupon-tinp:focus { border-color: #409eff; }
.coupon-tbtn { height: 28px; padding: 0 12px; border: 1px solid #d9d9d9; background: #fafafa; color: #666; font-size: 12px; cursor: pointer; }
.coupon-tbtn.on { background: #409eff; color: #fff; border-color: #409eff; }
.coupon-tbtn:disabled:not(.on) { opacity: 0.5; cursor: not-allowed; }
.pay-tmall { display: flex; background: #fff; border: 1px solid #e8e8e8; margin-bottom: 15px; }
.pay-topt { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 12px 8px; cursor: pointer; border-right: 1px solid #e8e8e8; font-size: 13px; }
.pay-topt:last-child { border-right: none; }
.pay-topt:hover { background: #f5f5f5; }
.pay-topt.sel { background: #ecf5ff; color: #409eff; font-weight: 600; }
.pay-tradio { position: absolute; opacity: 0; width: 0; height: 0; }
.pay-tdot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #d0d0d0; display: inline-block; position: relative; flex-shrink: 0; }
.pay-tdot.on { border-color: #409eff; }
.pay-tdot.on::after { content: ''; position: absolute; inset: 2px; border-radius: 50%; background: #409eff; }
.side-card { background: #fff; border: 1px solid #e8e8e8; position: sticky; top: 66px; }
.side-title { font-size: 14px; font-weight: 700; color: #333; padding: 14px 16px; border-bottom: 1px solid #f0f0f0; }
.side-body { padding: 12px 16px; }
.side-row { font-size: 12px; color: #666; padding: 3px 0; }
.side-green { color: #42bd56; }
.side-total { padding: 14px 16px; border-top: 1px dashed #e8e8e8; display: flex; align-items: baseline; justify-content: flex-end; gap: 4px; }
.side-total-label { font-size: 13px; color: #666; }
.side-total-price { font-size: 22px; font-weight: 700; color: #409eff; }
.side-btn-wrap { padding: 0 16px 14px; }
.order-btn { width: 100%; height: 40px; border: none; background: #409eff; color: #fff; font-size: 15px; font-weight: 700; border-radius: 2px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; }
.order-btn:hover:not(:disabled) { background: #337ecc; }
.order-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.side-agree { font-size: 11px; color: #bbb; text-align: center; padding: 0 16px 14px; }
.side-agree a { color: #1a73e8; text-decoration: none; }
.checkout-footer { border-top: 1px solid #e8e8e8; background: #fff; margin-top: 10px; }
.footer-inner { max-width: 990px; margin: 0 auto; padding: 18px 15px 22px; text-align: center; }
.footer-links a { font-size: 11px; color: #999; text-decoration: none; margin: 0 6px; }
.footer-links a:hover { color: #409eff; }
.dot { font-size: 11px; color: #ddd; }
.footer-copy { font-size: 11px; color: #bbb; margin-top: 4px; }
.footer-beian { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 6px; }
.beian-link { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: #999; text-decoration: none; }
.beian-link:hover { color: #666; }
.pay-dialog :deep(.el-dialog__header) { padding: 20px 24px 0; }
.pay-dialog :deep(.el-dialog__body) { padding: 4px 24px; }
.pay-dialog :deep(.el-dialog__footer) { padding: 0 24px 20px; }
.dlg-btn2 { flex: 1; height: 36px; font-size: 13px; cursor: pointer; border: 1px solid #d9d9d9; }
.dlg-cancel2 { background: #fff; color: #666; }
.dlg-cancel2:hover { border-color: #bbb; }
.dlg-confirm2 { background: #409eff; color: #fff; border: none; font-weight: 600; }
.dlg-confirm2:hover:not(:disabled) { background: #337ecc; }
.dlg-confirm2:disabled { opacity: 0.5; cursor: not-allowed; }
@media (max-width: 800px) {
  .main-layout { flex-direction: column; }
  .main-right { width: 100%; }
  .side-card { position: static; }
  .form-row { flex-direction: column; gap: 12px; }
  .pay-tmall { flex-direction: column; }
  .pay-topt { border-right: none; border-bottom: 1px solid #e8e8e8; }
  .jd-stepbar { display: none; }
}
</style>
`;

fs.writeFileSync(p, template + rest, 'utf8');
console.log('Done. File size:', fs.statSync(p).size);
