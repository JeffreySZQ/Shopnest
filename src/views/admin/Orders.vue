<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';
import { Search, ArrowRight } from 'lucide-vue-next';
import StatusBadge from '@/components/StatusBadge.vue';
import { refreshAdminData } from '@/services/shopApi.js';

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
                </table>
            </div>

            <div
                v-if="!shown.length"
                class="empty-inline"
            >
                No orders yet. Use the customer demo account to place an order.
            </div>

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
            </div>
        </div>
    </div>
</template>