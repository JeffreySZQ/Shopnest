<<<<<<< HEAD
<script setup>
import { ref, computed, onMounted } from 'vue';
=======
<script>
import { mapStores } from 'pinia';
>>>>>>> origin/main
import { useStore } from '@/stores/shop.js';
import { Search, ArrowRight } from 'lucide-vue-next';
import StatusBadge from '@/components/StatusBadge.vue';
import { refreshAdminData } from '@/services/shopApi.js';

<<<<<<< HEAD
const s = useStore();
const search = ref('');
const status = ref('');
const page = ref(1);
const size = 8;
const error = ref('');

onMounted(async () => {
    try {
        await refreshAdminData(s);
    } catch (e) {
        error.value = e.message;
    }
});

const filtered = computed(() =>
    s.orders.slice().reverse().filter(o =>
        (!search.value || (o.id + ' ' + o.customer + ' ' + o.email).toLowerCase().includes(search.value.toLowerCase())) &&
        (!status.value || o.fulfillmentStatus === status.value)
    )
);

const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / size)));
const shown = computed(() => filtered.value.slice((page.value - 1) * size, page.value * size));
=======
export default {
    name: 'AdminOrdersView',
    components: { Search, ArrowRight, StatusBadge },
    data() { return { search: '', status: '', page: 1, size: 8, error: '' }; },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        filtered() {
            const keyword = this.search.toLowerCase();
            return this.s.orders.slice().reverse().filter(order =>
                (!keyword || (order.id + ' ' + order.customer + ' ' + order.email).toLowerCase().includes(keyword)) &&
                (!this.status || order.fulfillmentStatus === this.status)
            );
        },
        pages() { return Math.max(1, Math.ceil(this.filtered.length / this.size)); },
        shown() { return this.filtered.slice((this.page - 1) * this.size, this.page * this.size); }
    },
    watch: { search() { this.page = 1; }, status() { this.page = 1; } },
    async mounted() {
        try { await refreshAdminData(this.s); }
        catch (error) { this.error = error.message; }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div>
        <div class="admin-page-top">
            <div>
                <p class="eyebrow">FULFILLMENT & PAYMENTS</p>

                <h1>Orders</h1>

                <p class="muted">
                    Review customer orders and update their fulfillment.
                </p>
            </div>

            <span class="demo-tag">SIMULATED ORDERS</span>
        </div>

        <div class="panel">
            <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
<<<<<<< HEAD
            <div class="toolbar">
                <label class="search-box">
                    <Search :size="18" />

                    <span class="visually-hidden">
                        Search orders
                    </span>

                    <input
                        v-model="search"
                        @input="page = 1"
                        placeholder="Search order or customer"
                    />
                </label>

                <label>
                    <span class="visually-hidden">
                        Filter fulfillment status
                    </span>

                    <select
                        v-model="status"
                        class="form-select"
                        @change="page = 1"
                    >
                        <option value="">All fulfillment statuses</option>
                        <option value="processing">Processing</option>
                        <option value="digital delivered">Digital delivered</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </label>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle">
=======
            <div class="row g-3 align-items-center mb-4">
                <div class="col-12 col-lg-6">
                    <v-text-field
                        v-model="search"
                        label="Search order or customer"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        hide-details
                    />
                </div>
                <div class="col-12 col-lg-6">
                    <v-select
                        v-model="status"
                        :items="[
                            { title: 'All fulfillment statuses', value: '' },
                            { title: 'Processing', value: 'processing' },
                            { title: 'Digital delivered', value: 'digital delivered' },
                            { title: 'Shipped', value: 'shipped' },
                            { title: 'Delivered', value: 'delivered' },
                            { title: 'Cancelled', value: 'cancelled' }
                        ]"
                        item-title="title"
                        item-value="value"
                        label="Fulfillment status"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                    />
                </div>
            </div>

            <div class="table-responsive">
                <v-table class="table admin-table align-middle admin-vuetify-table" density="comfortable">
>>>>>>> origin/main
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Created</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Fulfillment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="o in shown"
                            :key="o.id"
                        >
                            <td>
                                <strong>{{ o.id }}</strong>
                            </td>

                            <td>
                                {{ o.customer }}

                                <div class="muted small">
                                    {{ o.email }}
                                </div>
                            </td>

                            <td>
                                {{ new Date(o.createdAt).toLocaleDateString('en-MY') }}
                            </td>

                            <td>
                                RM {{ o.total.toFixed(2) }}
                            </td>

                            <td>
                                <StatusBadge :value="o.paymentStatus" />
                            </td>

                            <td>
                                <StatusBadge :value="o.fulfillmentStatus" />
                            </td>

                            <td>
                                <RouterLink
                                    class="btn btn-outline-primary btn-sm"
                                    :to="'/admin/orders/' + o.id"
                                >
                                    View
                                    <ArrowRight :size="14" />
                                </RouterLink>
                            </td>
                        </tr>
                    </tbody>
<<<<<<< HEAD
                </table>
=======
                </v-table>
>>>>>>> origin/main
            </div>

            <div
                v-if="!shown.length"
                class="empty-inline"
            >
                No orders yet. Use the customer demo account to place an order.
            </div>

<<<<<<< HEAD
            <div class="page-controls">
                <span>
                    {{ filtered.length }} orders
                </span>

                <button
                    class="btn btn-outline-primary btn-sm"
                    :disabled="page === 1"
                    @click="page--"
                >
                    Previous
                </button>

                <span>
                    {{ page }} / {{ pages }}
                </span>

                <button
                    class="btn btn-outline-primary btn-sm"
                    :disabled="page >= pages"
                    @click="page++"
                >
                    Next
                </button>
=======
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4">
                <span class="small text-medium-emphasis">{{ filtered.length }} orders</span>
                <v-pagination
                    v-model="page"
                    :length="pages"
                    :total-visible="5"
                    density="comfortable"
                    rounded="circle"
                />
                <span class="small text-medium-emphasis">Page {{ page }} of {{ pages }}</span>
>>>>>>> origin/main
            </div>
        </div>
    </div>
</template>