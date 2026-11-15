<<<<<<< HEAD
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';
import { useRoute } from 'vue-router';
=======
<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
>>>>>>> origin/main
import PageHeading from '@/components/PageHeading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { ArrowLeft, Download, Ban } from 'lucide-vue-next';
import { downloadReceipt } from '@/utils/receipt.js';
import { api, refreshOrders } from '@/services/shopApi.js';

<<<<<<< HEAD
const store = useStore();
const route = useRoute();
const order = ref(null);
const notice = ref('');
const error = ref('');
const busy = ref(false);

const canCancel = computed(() =>
    order.value &&
    ['processing', 'digital delivered'].includes(order.value.fulfillmentStatus) &&
    order.value.cancellationStatus !== 'pending' &&
    order.value.cancellationStatus !== 'approved'
);

onMounted(async () => {
    try {
        const result = await api.get('/orders/' + encodeURIComponent(route.params.id));
        order.value = result.order;
    } catch (e) {
        error.value = e.message;
    }
});

function getReceipt() {
    error.value = '';
    try {
        downloadReceipt(order.value);
    } catch (e) {
        error.value = e.message;
    }
}

async function cancelOrder() {
    error.value = '';
    if (!order.value) return;
    if (!window.confirm('Request cancellation? An administrator must review it. No real refund will be issued.')) return;

    busy.value = true;
    try {
        await api.post('/orders/' + encodeURIComponent(order.value.id) + '/cancellation', {});
        await refreshOrders(store);
        const result = await api.get('/orders/' + encodeURIComponent(order.value.id));
        order.value = result.order;
        notice.value = 'Cancellation requested. An administrator can review it. No actual refund has been issued.';
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}

function demoDownload() {
    const blob = new Blob(
        ['ShopNest demo digital access notice\nThis is a sample file, not a purchased product.'],
        { type: 'text/plain' }
    );
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'shopnest-demo-access.txt';
    a.click();
    URL.revokeObjectURL(a.href);
}
=======
export default {
    name: 'OrderDetailView',
    components: { PageHeading, StatusBadge, ArrowLeft, Download, Ban },
    data() { return { order: null, notice: '', error: '', busy: false, loading: true }; },
    computed: {
        ...mapStores(useStore),
        store() { return this.shopStore; },
        canCancel() {
            return this.order &&
                ['processing', 'digital delivered'].includes(this.order.fulfillmentStatus) &&
                !['pending', 'approved'].includes(this.order.cancellationStatus);
        }
    },
    async mounted() { await this.loadOrder(); },
    watch: {
        '$route.params.id'() { this.loadOrder(); }
    },
    methods: {
        async loadOrder() {
            this.loading = true;
            this.error = '';
            try {
                const result = await api.get('/orders/' + encodeURIComponent(this.$route.params.id));
                this.order = result.order;
            } catch (error) {
                this.error = error.message;
            } finally { this.loading = false; }
        },
        getReceipt() {
            this.error = '';
            try { downloadReceipt(this.order); }
            catch (error) { this.error = error.message; }
        },
        async cancelOrder() {
            if (!this.order) return;
            if (!window.confirm('Request cancellation? An administrator must review it. No real refund will be issued.')) return;
            this.busy = true;
            this.error = '';
            try {
                await api.post('/orders/' + encodeURIComponent(this.order.id) + '/cancellation', {});
                await refreshOrders(this.store);
                await this.loadOrder();
                this.notice = 'Cancellation requested. An administrator can review it. No actual refund has been issued.';
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        demoDownload() {
            const blob = new Blob([`ShopNest demo digital access notice\nThis is a sample file, not a purchased product.`], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.download = 'shopnest-demo-access.txt';
            anchor.click();
            URL.revokeObjectURL(url);
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div class="container section">
        <RouterLink
            to="/orders"
            class="back-link"
        >
            <ArrowLeft :size="17" />
            Purchase history
        </RouterLink>

        <template v-if="order">
            <PageHeading
                eyebrow="ORDER DETAILS"
                :title="order.id"
                :description="'Placed ' + new Date(order.createdAt).toLocaleString('en-MY')"
            />

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="panel">
                        <div class="panel-header">
                            <h2>Items ordered</h2>

                            <StatusBadge :value="order.fulfillmentStatus" />
                        </div>

                        <div
                            v-for="item in order.items"
                            :key="item.productId"
                            class="summary-line order-detail-line"
                        >
                            <div>
                                <strong>{{ item.name }}</strong>

                                <div class="muted small">
                                    {{ item.type }} · Quantity {{ item.quantity }}
                                </div>
                            </div>

                            <strong>
                                RM {{ (item.price * item.quantity).toFixed(2) }}
                            </strong>
                        </div>

                        <div class="summary-line">
                            <span>Subtotal</span>

                            <strong>
                                RM {{ order.subtotal.toFixed(2) }}
                            </strong>
                        </div>

                        <div class="summary-line">
                            <span>Shipping</span>

                            <strong>
                                RM {{ order.shipping.toFixed(2) }}
                            </strong>
                        </div>

                        <div class="summary-total">
                            <span>Total</span>

                            <strong>
                                RM {{ order.total.toFixed(2) }}
                            </strong>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel">
                        <h2>Status &amp; delivery</h2>

                        <p>
                            <StatusBadge :value="order.paymentStatus" />
                            <StatusBadge :value="order.fulfillmentStatus" />
                        </p>

                        <p class="small muted">
                            {{ order.email }}
<<<<<<< HEAD
=======
                            <span v-if="order.phone"> · {{ order.phone }}</span>
>>>>>>> origin/main
                        </p>

                        <p v-if="order.address">
                            {{ order.address }}
                            <br />
                            {{ order.postcode }} {{ order.city }}
                        </p>

                        <div
                            class="order-actions"
                            aria-label="Order actions"
                        >
<<<<<<< HEAD
                            <button
                                type="button"
                                class="btn btn-primary w-100"
                                :disabled="order.paymentStatus !== 'paid'"
                                @click="getReceipt"
                            >
                                <Download
                                    :size="18"
                                    aria-hidden="true"
                                />
                                Download PDF Receipt
                            </button>

                            <button
                                type="button"
                                class="btn btn-outline-danger w-100"
                                :disabled="!canCancel || busy"
                                @click="cancelOrder"
                            >
                                <Ban
                                    :size="18"
                                    aria-hidden="true"
                                />

                                {{
                                    order.cancellationStatus === 'pending'
                                        ? 'Cancellation requested'
                                        : 'Cancel / Request Refund'
                                }}
                            </button>
=======
                            <v-btn color="primary" block :disabled="order.paymentStatus !== 'paid'" @click="getReceipt">
                                <Download :size="18" aria-hidden="true" /> Download PDF Receipt
                            </v-btn>

                            <v-btn color="error" variant="outlined" block :disabled="!canCancel || busy" :loading="busy" @click="cancelOrder">
                                <Ban :size="18" aria-hidden="true" />
                                {{ order.cancellationStatus === 'pending' ? 'Cancellation requested' : 'Cancel / Request Refund' }}
                            </v-btn>
>>>>>>> origin/main
                        </div>

                        <p class="small muted mt-3">
                            Demo only: cancellation is reviewed by an admin.
                            Payments and refunds are not real.
                        </p>

                        <p
                            v-if="order.cancellationStatus === 'rejected'"
                            class="small"
                            role="status"
                        >
                            Previous cancellation request was declined.
                        </p>

<<<<<<< HEAD
                        <p
                            v-if="notice"
                            class="form-notice mt-2"
                            role="status"
                        >
                            {{ notice }}
                        </p>

                        <p
                            v-if="error"
                            class="text-danger mt-2"
                            role="alert"
                        >
                            {{ error }}
                        </p>
=======
                        <v-alert v-if="notice" type="info" variant="tonal" class="mt-2" role="status">
                            {{ notice }}
                        </v-alert>
                        <v-alert v-if="error" type="error" variant="tonal" class="mt-2" role="alert">
                            {{ error }}
                        </v-alert>
>>>>>>> origin/main

                        <div
                            v-if="order.items.some(item => item.type === 'digital')"
                            class="demo-panel"
                        >
                            <strong>Demo digital access</strong>

                            <p class="small muted">
                                A sample access notice is available.
                                Real products require secure backend file delivery.
                            </p>

<<<<<<< HEAD
                            <button
                                class="btn btn-outline-primary btn-sm"
                                @click="demoDownload"
                            >
                                <Download :size="16" />
                                Download sample notice
                            </button>
=======
                            <v-btn variant="outlined" color="primary" size="small" @click="demoDownload">
                                <Download :size="16" /> Download sample notice
                            </v-btn>
>>>>>>> origin/main
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="empty-state">
            <h1>{{ error ? 'Unable to load order' : 'Loading order details...' }}</h1>
            <p v-if="error" role="alert">{{ error }}</p>

            <RouterLink
                to="/orders"
                class="btn btn-primary"
            >
                Back to orders
            </RouterLink>
        </div>
    </div>
</template>