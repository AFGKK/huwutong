import client from './client';

export default {
    // 商品搜索 (M2-156 🛒)
    searchProducts(params = {}) {
        return client.get('/products', { params });
    },
    getSearchSuggestions(q) {
        return client.get('/products/search-suggest', { params: { q } });
    },
    getHotSearchTerms() {
        return client.get('/products/hot-search-terms');
    },
    getSearchHistory() {
        return client.get('/products/search-history');
    },
    clearSearchHistory() {
        return client.delete('/products/search-history');
    },
    getFilterTags() {
        return client.get('/products/filter-tags');
    },
    getCategories() {
        return client.get('/product-categories/public');
    },

    // 商品列表（公开）
    listProducts(params = {}) {
        return client.get('/products', { params });
    },
    getProduct(id) {
        return client.get(`/products/${id}`);
    },
    getProductSkus(productId) {
        return client.get(`/products/${productId}/skus`);
    },

    // SKU 查询（公开）
    getSkus(params = {}) {
        return client.get('/skus', { params });
    },

    // 购物车 (M2-145 🛒)
    getCart() {
        return client.get('/cart');
    },
    getCartSummary() {
        return client.get('/cart/summary');
    },
    addToCart(data) {
        return client.post('/cart/add', data);
    },
    updateCartItem(data) {
        return client.put('/cart/update', data);
    },
    removeFromCart(skuId) {
        return client.post('/cart/remove', { sku_id: skuId });
    },
    clearCart() {
        return client.post('/cart/clear');
    },
    applyCoupon(code) {
        return client.post('/cart/apply-coupon', { code });
    },
    removeCoupon() {
        return client.post('/cart/remove-coupon');
    },
    mergeCart(sessionId) {
        return client.post('/cart/merge', { session_id: sessionId });
    },
    validateCheckout() {
        return client.post('/cart/validate-checkout');
    },
    checkout(data) {
        return client.post('/cart/checkout', data);
    },

    // 快速购买（一键加购→下单→支付）
    quickBuy(data) {
        return client.post('/cart/quick-buy', data);
    },

    // 订单
    listOrders(params = {}) {
        return client.get('/orders', { params });
    },
    getOrder(id) {
        return client.get(`/orders/${id}`);
    },
    cancelOrder(id) {
        return client.post(`/orders/${id}/cancel`);
    },

    // 支付
    initiatePayment(orderId, gateway = 'alipay') {
        return client.post(`/orders/${orderId}/pay`, { gateway });
    },
    getPaymentStatus(orderId) {
        return client.get(`/orders/${orderId}/payment-status`);
    },

    // ─── 商品收藏 (Wishlist) ───
    getWishlistedIds() {
        return client.get('/wishlist/my/product-ids');
    },
    toggleWishlist(productId) {
        return client.post('/wishlist/toggle', { product_id: productId });
    },
    isWishlisted(productId) {
        return client.get(`/wishlist/check/${productId}`);
    },

    // ─── 商品评论 (Reviews) ───
    getProductReviews(productId, params = {}) {
        return client.get(`/products/${productId}/reviews`, { params });
    },
    getProductReviewStats(productId) {
        return client.get(`/products/${productId}/reviews/stats`);
    },
    submitReview(data) {
        return client.post('/product-reviews', data);
    },
};
