<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';
import { Search, Plus, Trash2, Pencil, History } from 'lucide-vue-next';
import StatusBadge from '@/components/StatusBadge.vue';
import { api, refreshAdminData } from '@/services/shopApi.js';

const s = useStore();
const search = ref('');
const type = ref('');
const status = ref('');
const page = ref(1);
const pageSize = 7;
const error = ref('');

onMounted(async () => {
    try {
        await refreshAdminData(s);
    } catch (e) {
        error.value = e.message;
    }
});

const filtered = computed(() =>
    s.products.filter(p =>
        (!search.value || (p.name + ' ' + p.sku).toLowerCase().includes(search.value.toLowerCase())) &&
        (!type.value || p.type === type.value) &&
        (!status.value || p.status === status.value)
    )
);

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)));
const shown = computed(() => filtered.value.slice((page.value - 1) * pageSize, page.value * pageSize));

async function remove(product) {
    if (!confirm(`Archive ${product.name}? It will no longer appear in the public catalogue, and historical orders will be retained.`)) return;
    error.value = '';
    try {
        await api.delete('/products/' + product.id);
        await refreshAdminData(s);
    } catch (e) {
        error.value = e.message;
    }
}
</script>

<template>
    <div>
        <div class="admin-page-top">
            <div>
                <p class="eyebrow">CATALOG MANAGEMENT</p>

                <h1>Products</h1>

                <p class="muted">
                    Manage listings, prices, visibility and stock.
                </p>
            </div>

            <RouterLink
                to="/admin/products/new"
                class="btn btn-primary"
            >
                <Plus :size="17" />
                Add product
            </RouterLink>
        </div>

        <div class="panel">
            <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
            <div class="toolbar">
                <label class="search-box">
                    <Search :size="18" />

                    <span class="visually-hidden">
                        Search product name or SKU
                    </span>

                    <input
                        v-model="search"
                        @input="page = 1"
                        placeholder="Search name or SKU"
                    />
                </label>

                <label>
                    <span class="visually-hidden">
                        Filter type
                    </span>

                    <select
                        v-model="type"
                        class="form-select"
                        @change="page = 1"
                    >
                        <option value="">All types</option>
                        <option value="physical">Physical</option>
                        <option value="digital">Digital</option>
                    </select>
                </label>

                <label>
                    <span class="visually-hidden">
                        Filter status
                    </span>

                    <select
                        v-model="status"
                        class="form-select"
                        @change="page = 1"
                    >
                        <option value="">All statuses</option>
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </select>
                </label>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col">Type</th>
                            <th scope="col">Price</th>
                            <th scope="col">Stock</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="p in shown"
                            :key="p.id"
                        >
                            <td>
                                <strong>{{ p.name }}</strong>

                                <div class="muted small">
                                    {{ p.sku }}
                                </div>
                            </td>

                            <td>
                                {{ p.type }}
                            </td>

                            <td>
                                RM {{ p.price.toFixed(2) }}
                            </td>

                            <td>
                                {{ p.type === 'digital' ? 'Digital' : p.stock }}
                            </td>

                            <td>
                                <StatusBadge :value="p.status" />
                            </td>

                            <td>
                                <div class="table-actions">
                                    <RouterLink
                                        class="btn btn-outline-primary btn-sm"
                                        :to="'/admin/products/' + p.id + '/edit'"
                                        :aria-label="'Edit ' + p.name"
                                    >
                                        <Pencil :size="15" />
                                        Edit
                                    </RouterLink>

                                    <RouterLink
                                        class="btn btn-outline-primary btn-sm"
                                        :to="'/admin/products/' + p.id + '/versions'"
                                        :aria-label="'Compare versions of ' + p.name"
                                    >
                                        <History :size="15" />
                                        Versions
                                    </RouterLink>

                                    <button
                                        class="btn btn-outline-danger btn-sm"
                                        :aria-label="'Archive ' + p.name"
                                        @click="remove(p)"
                                    >
                                        <Trash2 :size="15" />
                                        Archive
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="!shown.length"
                class="empty-inline"
            >
                No products match your filters.
            </div>

            <div class="page-controls">
                <span>
                    {{ filtered.length }} products
                </span>

                <button
                    class="btn btn-outline-primary btn-sm"
                    :disabled="page === 1"
                    @click="page--"
                >
                    Previous
                </button>

                <span>
                    {{ page }} / {{ totalPages }}
                </span>

                <button
                    class="btn btn-outline-primary btn-sm"
                    :disabled="page >= totalPages"
                    @click="page++"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>