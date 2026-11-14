<script setup>
import { reactive, computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { ShieldCheck, ArrowLeft } from 'lucide-vue-next';
import PageHeading from '@/components/PageHeading.vue';
import { api, refreshCart, refreshOrders } from '@/services/shopApi.js';

const s = useStore();
const router = useRouter();
const error = ref('');
const submitting = ref(false);
const form = reactive({
    email: s.user ? s.user.email : '',
    address: '',
    city: '',
    postcode: ''
});

const physical = computed(() =>
    s.cartItems.some(i => i.product.type === 'physical')
);

const shipping = computed(() => physical.value ? 8 : 0);

async function finish() {
    error.value = '';
    if (!s.user) {
        error.value = 'Please sign in before checkout.';
        return;
    }
    if (!s.cartItems.length) {
        error.value = 'Your cart is empty.';
        return;
    }
    if (physical.value && (!form.address.trim() || !form.city.trim() || !form.postcode.trim())) {
        error.value = 'Please complete your delivery address.';
        return;
    }

    submitting.value = true;
    try {
        const result = await api.post('/checkout', {
            email: form.email.trim(),
            address: form.address.trim(),
            city: form.city.trim(),
            postcode: form.postcode.trim()
        });
        await refreshCart(s);
        await refreshOrders(s);
        router.push('/order-confirmed/' + encodeURIComponent(result.order.id));
    } catch (e) {
        error.value = e.message;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="container section">
        <PageHeading
            eyebrow="ALMOST THERE"
            title="Checkout"
            description="Confirm your details and place a simulated order. No payment information is collected."
        />

        <div v-if="s.cartItems.length" class="checkout-grid">
            <form
                id="checkout-form"
                @submit.prevent="finish"
            >
                <div class="form-panel">
                    <h2>1. Contact details</h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                for="contact-name"
                                class="form-label"
                            >
                                Your name
                            </label>

                            <input
                                id="contact-name"
                                class="form-control"
                                :value="s.user?.name"
                                disabled
                            />
                        </div>

                        <div class="col-md-6">
                            <label
                                for="contact-email"
                                class="form-label"
                            >
                                Email address
                            </label>

                            <input
                                id="contact-email"
                                v-model.trim="form.email"
                                type="email"
                                class="form-control"
                                autocomplete="email"
                                required
                            />
                        </div>

                    </div>
                </div>

                <div
                    v-if="physical"
                    class="form-panel mt-4"
                >
                    <h2>2. Delivery details</h2>

                    <p class="muted small">
                        Flat demo shipping: RM 8.00
                    </p>

                    <label
                        class="form-label"
                        for="delivery-address"
                    >
                        Street address
                    </label>

                    <input
                        id="delivery-address"
                        v-model="form.address"
                        class="form-control"
                        autocomplete="street-address"
                        required
                    />

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="delivery-city"
                            >
                                City
                            </label>

                            <input
                                id="delivery-city"
                                v-model="form.city"
                                class="form-control"
                                autocomplete="address-level2"
                                required
                            />
                        </div>

                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="delivery-postcode"
                            >
                                Postcode
                            </label>

                            <input
                                id="delivery-postcode"
                                v-model="form.postcode"
                                class="form-control"
                                autocomplete="postal-code"
                                required
                            />
                        </div>
                    </div>
                </div>

                <div class="form-panel mt-4">
                    <h2>{{ physical ? '3' : '2' }}. Payment</h2>

                    <div class="demo-payment">
                        <ShieldCheck :size="22" />

                        <div>
                            <strong>Demo payment simulation</strong>

                            <p>
                                No card details or actual payment will be taken.
                                Submitting will record this order as demo-paid.
                            </p>
                        </div>
                    </div>
                </div>

                <p
                    v-if="error"
                    class="field-error mt-3"
                    role="alert"
                >
                    {{ error }}
                </p>
            </form>

            <aside class="summary-card">
            
                <h2>Order review</h2>
                <p class="muted small">
                    All orders are processed and recorded in Malaysian Ringgit (MYR).
                    Your selected display currency does not change the payable amount.
                </p>
                
                <div
                    v-for="i in s.cartItems"
                    :key="i.productId"
                    class="summary-line"
                >
                    <span>
                        {{ i.product.name }} × {{ i.quantity }}
                    </span>

                    <strong>
                        RM {{ (i.product.price * i.quantity).toFixed(2) }}
                    </strong>
                </div>

                <hr />

                <div class="summary-line">
                    <span>Subtotal</span>

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

                <button
                    form="checkout-form"
                    class="btn btn-primary btn-lg w-100"
                    type="submit"
                    :disabled="submitting"
                >
                    {{ submitting ? 'Placing order...' : 'Place order' }}
                </button>

                <RouterLink
                    to="/cart"
                    class="back-link mt-3"
                >
                    <ArrowLeft :size="16" />
                    Back to cart
                </RouterLink>
            </aside>
        </div>

        <div v-else class="empty-state">
            <h2>Your cart is empty</h2>

            <RouterLink
                to="/catalog"
                class="btn btn-primary"
            >
                Shop products
            </RouterLink>
        </div>
    </div>
</template>