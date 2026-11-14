<script setup>
import { computed, ref, onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';
import { useCurrencyStore } from '@/stores/currency.js';
import { Minus, Plus, Trash2, ShoppingBag, ArrowRight } from 'lucide-vue-next';
import ProductArt from '@/components/ProductArt.vue';
import PageHeading from '@/components/PageHeading.vue';
import { api, refreshCart } from '@/services/shopApi.js';

const s = useStore();
const currency = useCurrencyStore();
const notice = ref('');
const busy = ref(false);

const shipping = computed(() =>
    s.cartItems.some(i => i.product.type === 'physical') ? 8 : 0
);

onMounted(async () => {
    if (!s.user) return;
    try {
        await refreshCart(s);
    } catch (e) {
        notice.value = e.message;
    }
});

async function qty(item, quantity) {
    notice.value = '';
    busy.value = true;
    try {
        await api.patch(`/cart/items/${item.productId}`, { quantity });
        await refreshCart(s);
    } catch (e) {
        notice.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="container section">
        <PageHeading
            eyebrow="YOUR BAG"
            title="Shopping cart"
            description="Review your selection before checking out."
        />

        <p v-if="notice && !s.cartItems.length" class="field-error" role="alert">
            {{ notice }}
        </p>

        <div v-if="s.cartItems.length" class="cart-grid">
            <div>
                <div
                    v-for="i in s.cartItems"
                    :key="i.productId"
                    class="cart-item"
                >
                    <RouterLink :to="'/products/' + i.productId">
                        <ProductArt :product="i.product" />
                    </RouterLink>

                    <div class="cart-item-info">
                        <span class="eyebrow">
                            {{ i.product.type.toUpperCase() }}
                        </span>

                        <RouterLink
                            :to="'/products/' + i.productId"
                            class="product-name"
                        >
                            {{ i.product.name }}
                        </RouterLink>

                        <p class="muted">
                            {{ currency.formatPrice(i.product.price) }} each
                        </p>

                        <div class="cart-controls">
                            <button
                                :disabled="busy || i.quantity <= 1"
                                :aria-label="'Decrease ' + i.product.name"
                                @click="qty(i, i.quantity - 1)"
                            >
                                <Minus :size="15" />
                            </button>

                            <span>{{ i.quantity }}</span>

                            <button
                                :aria-label="'Increase ' + i.product.name"
                                @click="qty(i, i.quantity + 1)" :disabled="busy"
                            >
                                <Plus :size="15" />
                            </button>

                            <button
                                class="remove-btn"
                                :aria-label="'Remove ' + i.product.name"
                                @click="qty(i, 0)" :disabled="busy"
                            >
                                <Trash2 :size="17" />
                                Remove
                            </button>
                        </div>
                    </div>

                    <strong>
                        {{ currency.formatPrice(i.product.price * i.quantity) }}
                    </strong>
                </div>

                <p
                    v-if="notice"
                    role="alert"
                    class="field-error"
                >
                    {{ notice }}
                </p>

                <RouterLink
                    class="text-cta mt-4 d-inline-flex"
                    to="/catalog"
                >
                    ← Continue shopping
                </RouterLink>
            </div>

            <aside class="summary-card">
                <h2>Order summary</h2>

                <div class="summary-line">
                    <span>Subtotal ({{ s.cartCount }} items)</span>

                    <strong>
                        {{ currency.formatPrice(s.cartSubtotal) }}
                    </strong>
                </div>

                <div class="summary-line">
                    <span>Shipping</span>

                    <strong>
                        {{ currency.formatPrice(shipping) }}
                    </strong>
                </div>

                <div class="summary-total">
                    <span>Total</span>
                    
                    <p class="muted small mt-3 mb-0">
                        Prices are displayed using the selected reference exchange rate.
                        Final checkout and order records remain in MYR.
                    </p>

                    <strong>
                        {{ currency.formatPrice(s.cartSubtotal + shipping) }}
                    </strong>
                </div>

                <RouterLink
                    to="/checkout"
                    class="btn btn-primary btn-lg w-100"
                >
                    Proceed to checkout
                    <ArrowRight :size="18" />
                </RouterLink>

                <p class="muted small mt-3 mb-0">
                    Simulated checkout. No real payments will be collected.
                </p>
            </aside>
        </div>

        <div v-else class="empty-state">
            <ShoppingBag :size="43" />

            <h2>{{ s.user ? 'Your cart is feeling a little empty.' : 'Sign in to view your saved cart.' }}</h2>

            <p v-if="!s.user">Your shopping cart is saved to your ShopNest account.</p>
            <p v-else>There are good things waiting for you in our collection.</p>

            <RouterLink v-if="!s.user" to="/login" class="btn btn-outline-primary mb-2">Sign in</RouterLink>

            <RouterLink
                to="/catalog"
                class="btn btn-primary"
            >
                Explore products
            </RouterLink>
        </div>
    </div>
</template>