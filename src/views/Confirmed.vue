<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { downloadReceipt } from '@/utils/receipt.js';
import { CheckCircle2, PackageCheck, ArrowRight, Download } from 'lucide-vue-next';
import { api } from '@/services/shopApi.js';

const s = useStore();
const route = useRoute();
const order = ref(null);
const error = ref('');

onMounted(async () => {
    try {
        const result = await api.get('/orders/' + encodeURIComponent(route.params.id));
        order.value = result.order;
    } catch (e) {
        error.value = e.message;
    }
});

function getReceipt() {
    try {
        error.value = '';
        downloadReceipt(order.value);
    } catch (e) {
        error.value = e.message;
    }
}
</script>

<template>
    <div class="container section">
        <div v-if="order" class="confirmation">
            <div class="confirm-icon">
                <CheckCircle2 :size="43" />
            </div>

            <p class="eyebrow">YOUR ORDER IS IN</p>

            <h1>Order confirmed!</h1>

            <p class="muted">
                Thank you for shopping with us. Your demonstration order has been recorded.
            </p>

            <div class="confirm-grid">
                <div>
                    <span>Order number</span>
                    <strong>{{ order.id }}</strong>
                </div>

                <div>
                    <span>Total</span>
                    <strong>RM {{ order.total.toFixed(2) }}</strong>
                </div>

                <div>
                    <span>Items</span>
                    <strong>
                        {{ order.items.reduce((n, i) => n + i.quantity, 0) }}
                    </strong>
                </div>
            </div>

            <div class="confirm-message">
                <PackageCheck :size="23" />

                <div>
                    <strong>What happens next?</strong>

                    <p>
                        For this student demonstration, payment, shipping and digital delivery are simulated. View your order to see the purchased items.
                    </p>
                </div>
            </div>

            <div class="d-flex justify-content-center flex-wrap gap-3">
                <RouterLink
                    :to="'/orders/' + order.id"
                    class="btn btn-primary btn-lg"
                >
                    View order details
                    <ArrowRight :size="17" />
                </RouterLink>

                <button
                    v-if="order.paymentStatus === 'paid'"
                    class="btn btn-outline-primary btn-lg"
                    @click="getReceipt"
                >
                    <Download :size="17" />
                    Download PDF Receipt
                </button>

                <RouterLink
                    to="/catalog"
                    class="btn btn-outline-primary btn-lg"
                >
                    Continue shopping
                </RouterLink>
            </div>

            <p
                v-if="error"
                class="text-danger mt-3"
                role="alert"
            >
                {{ error }}
            </p>
        </div>

        <div v-else-if="error" class="empty-state">
            <h1>Unable to retrieve order</h1>
            <p role="alert">{{ error }}</p>

            <p>We couldn't find that order in your account.</p>

            <RouterLink to="/orders" class="btn btn-primary">
                My orders
            </RouterLink>
        </div>
        <div v-else class="empty-state"><p>Loading order details...</p></div>
    </div>
</template>