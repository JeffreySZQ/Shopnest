<<<<<<< HEAD
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
=======
<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { useCurrencyStore } from '@/stores/currency.js';

import {
    Minus,
    Plus,
    ShoppingBag,
    ArrowRight
} from 'lucide-vue-next';

import ProductArt from '@/components/ProductArt.vue';
import PageHeading from '@/components/PageHeading.vue';

import {
    api,
    refreshCart,
    mergeGuestCart
} from '@/services/shopApi.js';

export default {
    name: 'CartView',

    components: {
        Minus,
        Plus,
        ShoppingBag,
        ArrowRight,
        ProductArt,
        PageHeading
    },

    data() {
        return {
            notice: '',
            busy: false
        };
    },

    computed: {
        ...mapStores(useStore, useCurrencyStore),

        // Pinia shop store
        s() {
            return this.shopStore;
        },

        // Currency store
        currency() {
            return this.currencyStore;
        },

        // Calculate shipping charges.
        shipping() {
            return this.s.cartItems.some(
                item => item.product.type === 'physical'
            ) ? 8 : 0;
        },

        // Checkout destination.
        checkoutLink() {
            return this.s.user
                ? '/checkout'
                : {
                    name: 'login',
                    query: {
                        redirect: '/checkout'
                    }
                };
        }
    },

    // Load the account cart and merge guest cart.
    async mounted() {
        if (this.s.user) {
            try {
                if (this.s.guestCart.length) {
                    await mergeGuestCart(this.s);
                }

                await refreshCart(this.s);

            } catch (error) {
                this.s.guestCartError = error.message;
                this.notice = error.message;
            }
        }
    },

    methods: {

        // Retry guest cart merging.
        async retryGuestMerge() {
            this.notice = '';
            this.busy = true;

            try {
                await mergeGuestCart(this.s);
                await refreshCart(this.s);

            } catch (error) {
                this.notice = error.message;
                this.s.guestCartError = error.message;

            } finally {
                this.busy = false;
            }
        },

        // Update or remove cart quantity.
        async qty(item, quantity) {
            this.notice = '';
            this.busy = true;

            try {
                // Guest cart uses Pinia.
                if (!this.s.user) {
                    this.s.setGuestCartQty(
                        item.productId,
                        quantity
                    );

                } else {
                    // Logged-in cart uses PHP API.
                    await api.patch(
                        `/cart/items/${item.productId}`,
                        {
                            quantity: quantity
                        }
                    );

                    await refreshCart(this.s);
                }

            } catch (error) {
                this.notice = error.message;

            } finally {
                this.busy = false;
            }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div class="container section">
<<<<<<< HEAD
=======

        <!-- Page Heading -->
>>>>>>> origin/main
        <PageHeading
            eyebrow="YOUR BAG"
            title="Shopping cart"
            description="Review your selection before checking out."
        />

<<<<<<< HEAD
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

=======
        <!-- Guest Cart Merge Warning -->
        <v-alert
            v-if="s.guestCartError"
            type="warning"
            variant="tonal"
            class="mb-3"
        >
            Your guest cart is still saved on this device,
            but it could not be merged yet:
            {{ s.guestCartError }}

            <template #append>

                <v-btn
                    v-if="s.user"
                    variant="text"
                    :loading="busy"
                    @click="retryGuestMerge"
                >
                    Retry merge
                </v-btn>

                <RouterLink
                    v-else
                    to="/login"
                >
                    Sign in to retry
                </RouterLink>

            </template>
        </v-alert>

        <!-- Error Message -->
        <v-alert
            v-if="notice"
            type="error"
            variant="tonal"
            class="mb-3"
            role="alert"
        >
            {{ notice }}
        </v-alert>

        <!-- Cart with Products -->
        <div
            v-if="s.cartItems.length"
            class="cart-grid"
        >

            <!-- Cart Items -->
            <div>

                <div
                    v-for="item in s.cartItems"
                    :key="item.productId"
                    class="cart-item"
                >

                    <!-- Product Image -->
                    <RouterLink
                        :to="'/products/' + item.productId"
                        :aria-label="'View ' + item.product.name"
                    >
                        <ProductArt
                            :product="item.product"
                        />
                    </RouterLink>

                    <!-- Product Information -->
                    <div class="cart-item-info">

                        <span class="eyebrow">
                            {{ item.product.type.toUpperCase() }}
                        </span>

                        <RouterLink
                            :to="'/products/' + item.productId"
                            class="product-name"
                        >
                            {{ item.product.name }}
                        </RouterLink>

                        <p class="muted">
                            {{ currency.formatPrice(item.product.price) }}
                            each
                        </p>

                        <!-- Quantity Controls -->
                        <div class="cart-controls">

                            <!-- Decrease Quantity -->
                            <v-btn
                                icon
                                size="small"
                                variant="outlined"
                                :disabled="
                                    busy ||
                                    item.quantity <= 1
                                "
                                :aria-label="
                                    'Decrease ' + item.product.name
                                "
                                @click="
                                    qty(item, item.quantity - 1)
                                "
                            >
                                <Minus :size="15" />
                            </v-btn>

                            <!-- Current Quantity -->
                            <span
                                aria-live="polite"
                                aria-atomic="true"
                            >
                                {{ item.quantity }}
                            </span>

                            <!-- Increase Quantity -->
                            <v-btn
                                icon
                                size="small"
                                variant="outlined"
                                :disabled="busy"
                                :aria-label="
                                    'Increase ' + item.product.name
                                "
                                @click="
                                    qty(item, item.quantity + 1)
                                "
                            >
                                <Plus :size="15" />
                            </v-btn>

                            <!-- Remove Product -->
                            <v-btn
                                type="button"
                                class="cart-remove-btn"
                                variant="outlined"
                                color="error"
                                size="small"
                                prepend-icon="mdi-trash-can-outline"
                                :aria-label="
                                    'Remove ' +
                                    item.product.name +
                                    ' from cart'
                                "
                                :disabled="busy"
                                @click="qty(item, 0)"
                            >
                                Remove
                            </v-btn>

                        </div>

                    </div>

                    <!-- Product Subtotal -->
                    <strong>
                        {{
                            currency.formatPrice(
                                item.product.price * item.quantity
                            )
                        }}
                    </strong>

                </div>

                <!-- Continue Shopping -->
>>>>>>> origin/main
                <RouterLink
                    class="text-cta mt-4 d-inline-flex"
                    to="/catalog"
                >
                    ← Continue shopping
                </RouterLink>
<<<<<<< HEAD
            </div>

            <aside class="summary-card">
                <h2>Order summary</h2>

                <div class="summary-line">
                    <span>Subtotal ({{ s.cartCount }} items)</span>
=======

            </div>

            <!-- Order Summary -->
            <aside class="summary-card">

                <h2>Order summary</h2>

                <!-- Subtotal -->
                <div class="summary-line">

                    <span>
                        Subtotal ({{ s.cartCount }} items)
                    </span>
>>>>>>> origin/main

                    <strong>
                        {{ currency.formatPrice(s.cartSubtotal) }}
                    </strong>
<<<<<<< HEAD
                </div>

                <div class="summary-line">
=======

                </div>

                <!-- Shipping -->
                <div class="summary-line">

>>>>>>> origin/main
                    <span>Shipping</span>

                    <strong>
                        {{ currency.formatPrice(shipping) }}
                    </strong>
<<<<<<< HEAD
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
=======

                </div>

                <!-- Total -->
                <div class="summary-total">

                    <span>Total</span>

                    <strong>
                        {{
                            currency.formatPrice(
                                s.cartSubtotal + shipping
                            )
                        }}
                    </strong>

                </div>

                <!-- Checkout Button -->
                <v-btn
                    :to="checkoutLink"
                    color="primary"
                    size="large"
                    block
                >
                    {{
                        s.user
                            ? 'Proceed to checkout'
                            : 'Sign in to checkout'
                    }}

                    <ArrowRight :size="18" />

                </v-btn>

                <!-- Guest Cart Information -->
                <p
                    v-if="!s.user"
                    class="muted small mt-3"
                >
                    Your guest cart is saved on this device.
                    Sign in or create an account to merge it
                    into your account cart.
                </p>

                <!-- Payment Notice -->
                <p class="muted small mt-3 mb-0">
                    Simulated checkout.
                    No real payments will be collected.
                </p>

            </aside>

        </div>

        <!-- Empty Cart -->
        <div
            v-else
            class="empty-state"
        >

            <ShoppingBag :size="43" />

            <h2>
                Your cart is feeling a little empty.
            </h2>

            <p>
                There are good things waiting for you
                in our collection.
            </p>
>>>>>>> origin/main

            <RouterLink
                to="/catalog"
                class="btn btn-primary"
            >
                Explore products
            </RouterLink>
<<<<<<< HEAD
        </div>
    </div>
</template>
=======

        </div>

    </div>
</template>
>>>>>>> origin/main
