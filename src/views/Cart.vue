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

<script>

export default {

    // CREATE THE SHOPPING CART COMPONENT
    name: 'CartView',

    components: {
        Minus,
        Plus,
        ShoppingBag,
        ArrowRight,
        ProductArt,
        PageHeading
    },


    // STORE CART NOTIFICATION AND LOADING STATUS
    data() {
        return {
            notice: '',
            busy: false
        };
    },


    // CONNECT PINIA STORES
    computed: {
        ...mapStores(useStore, useCurrencyStore),

        // Access the main ShopNest store.
        s() {
            return this.shopStore;
        },

        // Access the currency store.
        currency() {
            return this.currencyStore;
        },


        // CALCULATE SHIPPING CHARGES
        shipping() {
            return this.s.cartItems.some(
                item => item.product.type === 'physical'
            ) ? 8 : 0;
        },


        // DETERMINE THE CHECKOUT DESTINATION
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


    // LOAD ACCOUNT CART AND MERGE GUEST CART
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


    // DEFINE SHOPPING CART FUNCTIONS
    methods: {


        // RETRY GUEST CART MERGING
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


        // UPDATE OR REMOVE CART QUANTITY
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

</script>

<template>
    <div class="container section">

        <!-- Page Heading -->
        <PageHeading
            eyebrow="YOUR BAG"
            title="Shopping cart"
            description="Review your selection before checking out."
        />

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
                <RouterLink
                    class="text-cta mt-4 d-inline-flex"
                    to="/catalog"
                >
                    ← Continue shopping
                </RouterLink>

            </div>

            <!-- Order Summary -->
            <aside class="summary-card">

                <h2>Order summary</h2>

                <!-- Subtotal -->
                <div class="summary-line">

                    <span>
                        Subtotal ({{ s.cartCount }} items)
                    </span>

                    <strong>
                        {{ currency.formatPrice(s.cartSubtotal) }}
                    </strong>

                </div>

                <!-- Shipping -->
                <div class="summary-line">

                    <span>Shipping</span>

                    <strong>
                        {{ currency.formatPrice(shipping) }}
                    </strong>

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

            <RouterLink
                to="/catalog"
                class="btn btn-primary"
            >
                Explore products
            </RouterLink>

        </div>

    </div>
</template>
