<script setup>
import { computed, ref } from 'vue';
import { useStore } from '@/stores/shop.js';
import {
    Minus,
    Plus,
    Trash2,
    ShoppingBag,
    ArrowRight
} from 'lucide-vue-next';
import ProductArt from '@/components/ProductArt.vue';
import PageHeading from '@/components/PageHeading.vue';

const s = useStore(),
    notice = ref('');

const shipping = computed(() =>
    s.cartItems.some(i => i.product.type === 'physical') ? 8 : 0
);

function qty(i, n) {
    try {
        s.setCartQty(i.productId, n);
        notice.value = '';
    } catch (e) {
        notice.value = e.message;
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
                            RM {{ i.product.price.toFixed(2) }} each
                        </p>

                        <div class="cart-controls">
                            <button
                                :disabled="i.quantity <= 1"
                                :aria-label="'Decrease ' + i.product.name"
                                @click="qty(i, i.quantity - 1)"
                            >
                                <Minus :size="15" />
                            </button>

                            <span>{{ i.quantity }}</span>

                            <button
                                :aria-label="'Increase ' + i.product.name"
                                @click="qty(i, i.quantity + 1)"
                            >
                                <Plus :size="15" />
                            </button>

                            <button
                                class="remove-btn"
                                :aria-label="'Remove ' + i.product.name"
                                @click="qty(i, 0)"
                            >
                                <Trash2 :size="17" />
                                Remove
                            </button>
                        </div>
                    </div>

                    <strong>
                        RM {{ (i.product.price * i.quantity).toFixed(2) }}
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
                        RM {{ s.cartSubtotal.toFixed(2) }}
                    </strong>
                </div>

                <div class="summary-line">
                    <span>Shipping</span>

                    <strong>
                        RM {{ shipping.toFixed(2) }}
                    </strong>
                </div>

                <div class="summary-total">
                    <span>Total</span>

                    <strong>
                        RM {{ (s.cartSubtotal + shipping).toFixed(2) }}
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

            <h2>Your cart is feeling a little empty.</h2>

            <p>
                There are good things waiting for you in our collection.
            </p>

            <RouterLink
                to="/catalog"
                class="btn btn-primary"
            >
                Explore products
            </RouterLink>
        </div>
    </div>
</template>