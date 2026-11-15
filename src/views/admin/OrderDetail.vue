<<<<<<< HEAD
<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
=======
<script>
import { mapStores } from 'pinia';
>>>>>>> origin/main
import { useStore } from '@/stores/shop.js';
import { ArrowLeft, Save } from 'lucide-vue-next';
import StatusBadge from '@/components/StatusBadge.vue';
import { api, refreshAdminData } from '@/services/shopApi.js';

<<<<<<< HEAD
const s = useStore();
const route = useRoute();
const o = ref(null);
const newStatus = ref('');
const notice = ref('');
const busy = ref(false);

async function loadOrder() {
    const result = await api.get('/admin/orders/' + encodeURIComponent(route.params.id));
    o.value = result.order;
    newStatus.value = o.value.fulfillmentStatus;
}

onMounted(async () => {
    try {
        await loadOrder();
    } catch (e) {
        notice.value = e.message;
    }
});

async function decide(approve) {
    if (!o.value) return;
    busy.value = true;
    notice.value = '';
    try {
        await api.post('/admin/cancellations/' + encodeURIComponent(o.value.id), { approve });
        await refreshAdminData(s);
        await loadOrder();
        notice.value = approve
            ? 'Cancellation approved. Refund status is simulated; no money was returned.'
            : 'Cancellation request declined.';
    } catch (e) {
        notice.value = e.message;
    } finally {
        busy.value = false;
    }
}

async function update() {
    if (!newStatus.value || !o.value) return;
    busy.value = true;
    notice.value = '';
    try {
        await api.patch('/admin/orders/' + encodeURIComponent(o.value.id), {
            fulfillmentStatus: newStatus.value
        });
        await refreshAdminData(s);
        await loadOrder();
        notice.value = 'Order fulfillment status updated.';
    } catch (e) {
        notice.value = e.message;
    } finally {
        busy.value = false;
    }
}
=======
export default {
    name: 'AdminOrderDetailView',
    components: { ArrowLeft, Save, StatusBadge },
    data() { return { o: null, newStatus: '', notice: '', busy: false, loading: true }; },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; }
    },
    async mounted() {
        try { await this.loadOrder(); }
        catch (error) { this.notice = error.message; }
    },
    watch: { '$route.params.id'() { this.loadOrder(); } },
    methods: {
        async loadOrder() {
            this.loading = true;
            const result = await api.get('/admin/orders/' + encodeURIComponent(this.$route.params.id));
            this.o = result.order;
            this.newStatus = this.o.fulfillmentStatus;
            this.loading = false;
        },
        async decide(approve) {
            if (!this.o) return;
            this.busy = true;
            this.notice = '';
            try {
                await api.post('/admin/cancellations/' + encodeURIComponent(this.o.id), { approve });
                await refreshAdminData(this.s);
                await this.loadOrder();
                this.notice = approve
                    ? 'Cancellation approved. Refund status is simulated; no money was returned.'
                    : 'Cancellation request declined.';
            } catch (error) { this.notice = error.message; }
            finally { this.busy = false; }
        },
        async update() {
            if (!this.newStatus || !this.o) return;
            this.busy = true;
            this.notice = '';
            try {
                await api.patch('/admin/orders/' + encodeURIComponent(this.o.id), { fulfillmentStatus: this.newStatus });
                await refreshAdminData(this.s);
                await this.loadOrder();
                this.notice = 'Order fulfillment status updated.';
            } catch (error) { this.notice = error.message; }
            finally { this.busy = false; }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div>
        <RouterLink
            class="back-link"
            to="/admin/orders"
        >
            <ArrowLeft :size="17" />
            All orders
        </RouterLink>

        <template v-if="o">
            <div class="admin-page-top">
                <div>
                    <p class="eyebrow">ORDER DETAIL</p>

                    <h1>{{ o.id }}</h1>

                    <p class="muted">
                        Placed {{ new Date(o.createdAt).toLocaleString('en-MY') }}
                    </p>
                </div>

                <div>
                    <StatusBadge :value="o.paymentStatus" />
                    <StatusBadge :value="o.fulfillmentStatus" />
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="panel">
                        <h2>Purchased items</h2>

                        <div
                            v-for="i in o.items"
                            :key="i.productId"
                            class="order-row"
                        >
                            <div>
                                <strong>{{ i.name }}</strong>

                                <div class="muted small">
                                    {{ i.type }} × {{ i.quantity }}
                                </div>
                            </div>

                            <strong>
                                RM {{ (i.price * i.quantity).toFixed(2) }}
                            </strong>
                        </div>

                        <div class="summary-line mt-3">
                            <span>Subtotal</span>

                            <strong>
                                RM {{ o.subtotal.toFixed(2) }}
                            </strong>
                        </div>

                        <div class="summary-line">
                            <span>Shipping</span>

                            <strong>
                                RM {{ o.shipping.toFixed(2) }}
                            </strong>
                        </div>

                        <div class="summary-total">
                            <span>Total (demo-paid)</span>

                            <strong>
                                RM {{ o.total.toFixed(2) }}
                            </strong>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="panel">
                        <h2>Customer</h2>

                        <p>
                            <strong>{{ o.customer }}</strong>
                            <br />
                            {{ o.email }}
<<<<<<< HEAD
=======
                            <br />
                            <span v-if="o.phone">{{ o.phone }}</span>
>>>>>>> origin/main
                        </p>

                        <p v-if="o.address">
                            {{ o.address }}
                            <br />
                            {{ o.postcode }} {{ o.city }}
                        </p>

                        <div
                            v-if="o.cancellationStatus === 'pending'"
                            class="demo-panel"
                        >
                            <h2>Cancellation requested</h2>

                            <p class="small muted">
                                Customer requests cancellation and a demo refund.
                                No real payment or refund is processed.
                            </p>

                            <div class="d-flex flex-wrap gap-2">
<<<<<<< HEAD
                                <button
                                    class="btn btn-outline-danger btn-sm"
                                    @click="decide(true)" :disabled="busy"
                                >
                                    Approve cancellation (demo)
                                </button>

                                <button
                                    class="btn btn-outline-primary btn-sm"
                                    @click="decide(false)" :disabled="busy"
                                >
                                    Decline
                                </button>
=======
                                <v-btn color="error" variant="outlined" size="small" :disabled="busy" @click="decide(true)">
                                    Approve cancellation (demo)
                                </v-btn>

                                <v-btn color="primary" variant="outlined" size="small" :disabled="busy" @click="decide(false)">
                                    Decline
                                </v-btn>
>>>>>>> origin/main
                            </div>
                        </div>

                        <hr />

                        <h2>Fulfillment</h2>

                        <p class="small muted">
                            Digital items have simulated delivery.
                            Do not use these controls for real-world fulfillment.
                        </p>

<<<<<<< HEAD
                        <label
                            for="fulfillment-state"
                            class="form-label"
                        >
                            Update status
                        </label>

                        <select
                            id="fulfillment-state"
                            v-model="newStatus"
                            class="form-select"
                        >
                            <option value="">Choose status</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        <button
                            class="btn btn-primary w-100 mt-3"
                            :disabled="!newStatus || busy"
                            @click="update"
                        >
                            <Save :size="17" />
                            Save status
                        </button>

                        <p
                            v-if="notice"
                            class="form-notice mt-2"
                            role="status"
                        >
                            {{ notice }}
                        </p>
=======
                        <v-select
                            id="fulfillment-state"
                            v-model="newStatus"
                            :items="[
                                { title: 'Processing', value: 'processing' },
                                { title: 'Shipped', value: 'shipped' },
                                { title: 'Delivered', value: 'delivered' },
                                { title: 'Cancelled', value: 'cancelled' }
                            ]"
                            item-title="title"
                            item-value="value"
                            label="Update status"
                            variant="outlined"
                            density="comfortable"
                        />

                        <v-btn color="primary" block class="mt-3" :disabled="!newStatus || busy" :loading="busy" @click="update">
                            <Save :size="17" /> Save status
                        </v-btn>

                        <v-alert v-if="notice" type="info" variant="tonal" density="compact" class="mt-2" role="status">
                            {{ notice }}
                        </v-alert>
>>>>>>> origin/main
                    </div>
                </div>
            </div>
        </template>

        <div v-else class="empty-state">
            <h1>Order unavailable</h1>

            <RouterLink
                to="/admin/orders"
                class="btn btn-primary"
            >
                Back to orders
            </RouterLink>
        </div>
    </div>
</template>