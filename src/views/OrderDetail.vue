<script setup>
import { computed, ref } from 'vue';
import { useStore } from '@/stores/shop.js';
import { useRoute } from 'vue-router';
import PageHeading from '@/components/PageHeading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { ArrowLeft, Download, Ban } from 'lucide-vue-next';
import { downloadReceipt } from '@/utils/receipt.js';

const store = useStore();
const route = useRoute();

const order = computed(() =>
    store.orders.find(
        o => o.id === route.params.id && o.userId === store.user?.id
    )
);

const notice = ref('');
const error = ref('');

const canCancel = computed(() =>
    order.value &&
    ['processing', 'digital delivered'].includes(order.value.fulfillmentStatus) &&
    !order.value.cancellationRequestedAt
);

function getReceipt() {
    error.value = '';

    try {
        downloadReceipt(order.value);
    } catch (e) {
        error.value = e.message;
    }
}

function cancelOrder() {
    error.value = '';

    if (
        !window.confirm(
            'Request cancellation and a simulated refund for this order? An admin must approve the request.'
        )
    ) return;

    try {
        store.requestCancellation(order.value.id);

        notice.value = 'Cancellation requested. An admin can review it. No actual refund has been issued.';
    } catch (e) {
        error.value = e.message;
    }
}

function demoDownload() {
    const blob = new Blob(
        [
            'ShopNest demo digital access notice\nThis is a sample file, not a purchased product.'
        ],
        { type: 'text/plain' }
    );

    const a = document.createElement('a');

    a.href = URL.createObjectURL(blob);
    a.download = 'shopnest-demo-access.txt';

    a.click();

    URL.revokeObjectURL(a.href);
}
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
                                :disabled="!canCancel"
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

                        <div
                            v-if="order.items.some(item => item.type === 'digital')"
                            class="demo-panel"
                        >
                            <strong>Demo digital access</strong>

                            <p class="small muted">
                                A sample access notice is available.
                                Real products require secure backend file delivery.
                            </p>

                            <button
                                class="btn btn-outline-primary btn-sm"
                                @click="demoDownload"
                            >
                                <Download :size="16" />
                                Download sample notice
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="empty-state">
            <h1>Order not found</h1>

            <RouterLink
                to="/orders"
                class="btn btn-primary"
            >
                Back to orders
            </RouterLink>
        </div>
    </div>
</template>