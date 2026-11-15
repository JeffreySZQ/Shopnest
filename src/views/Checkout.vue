<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { ShieldCheck, ArrowLeft } from 'lucide-vue-next';
import PageHeading from '@/components/PageHeading.vue';
import { api, refreshCart, refreshOrders } from '@/services/shopApi.js';

export default {
    name: 'CheckoutView',
    components: { ShieldCheck, ArrowLeft, PageHeading },

    data() {
        return {
            form: {
                email: '',
                phone: '',
                address: '',
                city: '',
                postcode: ''
            },
            error: '',
            submitting: false
        };
    },

    computed: {
        ...mapStores(useStore),
        s() {
            return this.shopStore;
        },
        physical() {
            return this.s.cartItems.some(item => item.product.type === 'physical');
        },
        shipping() {
            return this.physical ? 8 : 0;
        },
        rules() {
            return {
                required: value => !!String(value || '').trim() || 'This field is required.',
                email: value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim()) || 'Enter a valid email address.',
                phone: value => /^[0-9+()\-\s]{7,20}$/.test(String(value || '').trim()) || 'Enter a valid phone number.',
                address: value => String(value || '').trim().length >= 5 || 'Enter a complete street address.',
                city: value => String(value || '').trim().length >= 2 || 'Enter a valid city.',
                postcode: value => /^\d{5,10}$/.test(String(value || '').trim()) || 'Enter a valid postcode (5–10 digits).'
            };
        }
    },

    created() {
        this.form.email = this.s.user ? this.s.user.email : '';
    },

    methods: {
        async finish() {
            this.error = '';

            if (!this.s.user) {
                this.error = 'Please sign in before checkout.';
                return;
            }

            if (!this.s.cartItems.length) {
                this.error = 'Your cart is empty.';
                return;
            }

            const validation = await this.$refs.checkoutForm.validate();
            if (!validation.valid) {
                this.error = 'Please correct the highlighted checkout fields.';
                return;
            }

            this.submitting = true;

            try {
                const result = await api.post('/checkout', {
                    email: this.form.email.trim(),
                    phone: this.form.phone.trim(),
                    address: this.physical ? this.form.address.trim() : '',
                    city: this.physical ? this.form.city.trim() : '',
                    postcode: this.physical ? this.form.postcode.trim() : ''
                });

                await refreshCart(this.s);
                await refreshOrders(this.s);

                await this.$router.push(
                    '/order-confirmed/' + encodeURIComponent(result.order.id)
                );

            } catch (error) {
                this.error = error.message;

            } finally {
                this.submitting = false;
            }
        }
    }
};
</script>

<template>
    <div class="container section">
        <PageHeading
            eyebrow="ALMOST THERE"
            title="Checkout"
            description="Confirm your details and place a simulated order. No payment information is collected."
        />

        <div v-if="s.cartItems.length" class="checkout-grid">
            <v-form
                id="checkout-form"
                ref="checkoutForm"
                validate-on="input"
                @submit.prevent="finish"
            >
                <div class="form-panel">
                    <h2>1. Contact details</h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="sn-field-group">
                                <label for="checkout-name" class="sn-field-label">Your name</label>
                                <v-text-field
                                    id="checkout-name"
                                    :model-value="s.user ? s.user.name : ''"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    readonly
                                />
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sn-field-group">
                                <label for="checkout-email" class="sn-field-label">
                                    Email address <span class="required">*</span>
                                </label>
                                <v-text-field
                                    id="checkout-email"
                                    v-model.trim="form.email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    :rules="[rules.required, rules.email]"
                                    required
                                />
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="sn-field-group">
                                <label for="checkout-phone" class="sn-field-label">
                                    Contact phone number <span class="required">*</span>
                                </label>
                                <v-text-field
                                    id="checkout-phone"
                                    v-model.trim="form.phone"
                                    type="tel"
                                    autocomplete="tel"
                                    placeholder="e.g. +60123456789"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    :rules="[rules.required, rules.phone]"
                                    required
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="physical" class="form-panel mt-4">
                    <h2>2. Delivery details</h2>
                    <p class="muted small">Flat demo shipping: RM 8.00</p>

                    <div class="sn-field-group">
                        <label for="checkout-address" class="sn-field-label">
                            Street address <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="checkout-address"
                            v-model.trim="form.address"
                            autocomplete="street-address"
                            placeholder="House number and street name"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :rules="[rules.required, rules.address]"
                            required
                        />
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="sn-field-group">
                                <label for="checkout-city" class="sn-field-label">
                                    City <span class="required">*</span>
                                </label>
                                <v-text-field
                                    id="checkout-city"
                                    v-model.trim="form.city"
                                    autocomplete="address-level2"
                                    placeholder="Enter your city"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    :rules="[rules.required, rules.city]"
                                    required
                                />
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="sn-field-group">
                                <label for="checkout-postcode" class="sn-field-label">
                                    Postcode <span class="required">*</span>
                                </label>
                                <v-text-field
                                    id="checkout-postcode"
                                    v-model.trim="form.postcode"
                                    autocomplete="postal-code"
                                    placeholder="e.g. 50000"
                                    variant="outlined"
                                    density="comfortable"
                                    maxlength="10"
                                    hide-details="auto"
                                    :rules="[rules.required, rules.postcode]"
                                    required
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-panel mt-4">
                    <h2>{{ physical ? '3' : '2' }}. Payment</h2>
                    <div class="demo-payment">
                        <ShieldCheck :size="22" />
                        <div>
                            <strong>Demo payment simulation</strong>
                            <p>No card details or actual payment will be taken. Submitting will record this order as demo-paid.</p>
                        </div>
                    </div>
                </div>

                <v-alert
                    v-if="error"
                    type="error"
                    variant="tonal"
                    class="mt-3"
                    role="alert"
                    aria-live="assertive"
                >
                    {{ error }}
                </v-alert>
            </v-form>

            <aside class="summary-card">
                <h2>Order review</h2>
                <p class="muted small">
                    All orders are processed and recorded in Malaysian Ringgit (MYR).
                    Your selected display currency does not change the payable amount.
                </p>

                <div
                    v-for="item in s.cartItems"
                    :key="item.productId"
                    class="summary-line"
                >
                    <span>{{ item.product.name }} × {{ item.quantity }}</span>
                    <strong>RM {{ (item.product.price * item.quantity).toFixed(2) }}</strong>
                </div>

                <hr />

                <div class="summary-line">
                    <span>Subtotal</span>
                    <strong>RM {{ s.cartSubtotal.toFixed(2) }}</strong>
                </div>

                <div class="summary-line">
                    <span>Shipping</span>
                    <strong>RM {{ shipping.toFixed(2) }}</strong>
                </div>

                <div class="summary-total">
                    <span>Total</span>
                    <strong>RM {{ (s.cartSubtotal + shipping).toFixed(2) }}</strong>
                </div>

                <v-btn
                    form="checkout-form"
                    type="submit"
                    color="primary"
                    size="large"
                    block
                    :loading="submitting"
                >
                    Place order
                </v-btn>

                <RouterLink to="/cart" class="back-link mt-3">
                    <ArrowLeft :size="16" /> Back to cart
                </RouterLink>
            </aside>
        </div>

        <div v-else class="empty-state">
            <h2>Your cart is empty</h2>
            <RouterLink to="/catalog" class="btn btn-primary">Shop products</RouterLink>
        </div>
    </div>
</template>
