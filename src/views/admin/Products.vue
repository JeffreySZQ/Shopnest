
<<<<<<< HEAD
<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';
import { api, refreshAdminData } from '@/services/shopApi.js';

const s = useStore();

// Product data and loading states
const products = ref([]);
const loading = ref(false);
const deleting = ref(false);
const error = ref('');

// Search and filters
const search = ref('');
const type = ref('');
const status = ref('');

// Pagination
const page = ref(1);
const pageSize = ref(7);

const pageSizes = [7, 10, 15, 25];

const typeOptions = [
    { title: 'All types', value: '' },
    { title: 'Physical', value: 'physical' },
    { title: 'Digital', value: 'digital' }
];

const statusOptions = [
    { title: 'All statuses', value: '' },
    { title: 'Published', value: 'published' },
    { title: 'Draft', value: 'draft' },
    { title: 'Archived', value: 'archived' }
];

// Reset pagination when filters change.
function resetPage() {
    page.value = 1;
}

// Retrieve products from the PHP API.
// Pinia is refreshed as a frontend cache.
async function loadProducts() {
    loading.value = true;
    error.value = '';

    try {
        const results = await refreshAdminData(s);

        products.value = results[0].products.map(product => ({
            ...product,
            id: Number(product.id),
            price: Number(product.price),
            stock: Number(product.stock)
        }));

        // Keep the current page within the valid range.
        const lastPage = Math.max(
            1,
            Math.ceil(filtered.value.length / pageSize.value)
        );

        if (page.value > lastPage) {
            page.value = lastPage;
        }

    } catch (e) {
        error.value = e.message;

    } finally {
        loading.value = false;
    }
}

// Filter products.
const filtered = computed(() => {
    const keyword = String(search.value || '')
        .trim()
        .toLowerCase();

    return products.value.filter(product => {
        const matchesSearch =
            !keyword ||
            (
                product.name + ' ' + product.sku
            ).toLowerCase().includes(keyword);

        const matchesType =
            !type.value ||
            product.type === type.value;

        const matchesStatus =
            !status.value ||
            product.status === status.value;

        return (
            matchesSearch &&
            matchesType &&
            matchesStatus
        );
    });
});

// Calculate pagination.
const totalPages = computed(() =>
    Math.max(
        1,
        Math.ceil(filtered.value.length / pageSize.value)
    )
);

// Get products for the current page.
const shown = computed(() => {
    const start = (page.value - 1) * pageSize.value;

    return filtered.value.slice(
        start,
        start + pageSize.value
    );
});

// Status badge colours.
function statusColor(value) {
    if (value === 'published') return 'success';
    if (value === 'draft') return 'warning';
    if (value === 'archived') return 'error';

    return 'secondary';
}

// Archive product through the PHP API.
async function remove(product) {
    if (product.status === 'archived') return;

    const confirmed = window.confirm(
        `Archive ${product.name}? ` +
        'It will no longer appear in the public catalogue, ' +
        'and historical orders will be retained.'
    );

    if (!confirmed) return;

    error.value = '';
    deleting.value = true;

    try {
        await api.delete('/products/' + product.id);

        // Reload database records after archiving.
        await loadProducts();

        page.value = Math.min(page.value, totalPages.value);

    } catch (e) {
        error.value = e.message;

    } finally {
        deleting.value = false;
    }
}

// Load database products when the page opens.
onMounted(loadProducts);
=======
<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { api, refreshAdminData } from '@/services/shopApi.js';

export default {
    name: 'AdminProductsView',
    data() {
        return {
            products: [], loading: false, deleting: false, error: '',
            search: '', type: '', status: '', page: 1, pageSize: 7,
            pageSizes: [7, 10, 15, 25],
            typeOptions: [
                { title: 'All types', value: '' },
                { title: 'Physical', value: 'physical' },
                { title: 'Digital', value: 'digital' }
            ],
            statusOptions: [
                { title: 'All statuses', value: '' },
                { title: 'Published', value: 'published' },
                { title: 'Draft', value: 'draft' },
                { title: 'Archived', value: 'archived' }
            ]
        };
    },
    computed: {
        ...mapStores(useStore),
        s() { return this.shopStore; },
        filtered() {
            const keyword = String(this.search || '').trim().toLowerCase();
            return this.products.filter(product =>
                (!keyword || (product.name + ' ' + product.sku).toLowerCase().includes(keyword)) &&
                (!this.type || product.type === this.type) &&
                (!this.status || product.status === this.status)
            );
        },
        totalPages() { return Math.max(1, Math.ceil(this.filtered.length / this.pageSize)); },
        shown() {
            const start = (this.page - 1) * this.pageSize;
            return this.filtered.slice(start, start + this.pageSize);
        }
    },
    watch: {
        search() { this.resetPage(); },
        type() { this.resetPage(); },
        status() { this.resetPage(); },
        pageSize() { this.resetPage(); }
    },
    mounted() { this.loadProducts(); },
    methods: {
        resetPage() { this.page = 1; },
        async loadProducts() {
            this.loading = true;
            this.error = '';
            try {
                const results = await refreshAdminData(this.s);
                this.products = (results[0].products || []).map(product => ({
                    ...product,
                    id: Number(product.id),
                    price: Number(product.price),
                    stock: Number(product.stock)
                }));
                this.page = Math.min(this.page, this.totalPages);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.loading = false;
            }
        },
        statusColor(value) {
            if (value === 'published') return 'success';
            if (value === 'draft') return 'warning';
            if (value === 'archived') return 'error';
            return 'secondary';
        },
        async remove(product) {
            if (product.status === 'archived') return;
            const confirmed = window.confirm(
                `Archive ${product.name}? It will no longer appear in the public catalogue, and historical orders will be retained.`
            );
            if (!confirmed) return;
            this.error = '';
            this.deleting = true;
            try {
                await api.delete('/products/' + product.id);
                await this.loadProducts();
                this.page = Math.min(this.page, this.totalPages);
            } catch (error) {
                this.error = error.message;
            } finally {
                this.deleting = false;
            }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div>

        <!-- Page Heading -->
        <div class="admin-page-top">

            <div>
                <p class="eyebrow">
                    CATALOG MANAGEMENT
                </p>

                <h1>Products</h1>

                <p class="muted">
                    Manage listings, prices, visibility and stock.
                </p>
            </div>

            <v-btn
                to="/admin/products/new"
                color="primary"
                prepend-icon="mdi-plus"
            >
                Add product
            </v-btn>

        </div>

        <!-- Product Management Panel -->
        <div class="panel">

            <!-- Loading Indicator -->
            <v-progress-linear
                v-if="loading"
                indeterminate
                color="primary"
                class="mb-4"
            />

            <!-- Error Message -->
            <v-alert
                v-if="error"
                type="error"
                variant="tonal"
                closable
                class="mb-4"
                @click:close="error = ''"
            >
                {{ error }}
            </v-alert>

            <!-- Search and Filters -->
            <div class="row g-3 align-items-center mb-4">

                <!-- Search -->
                <div class="col-12 col-lg-5">
<<<<<<< HEAD

                    <v-text-field
                        v-model="search"
                        label="Search product name or SKU"
                        prepend-inner-icon="mdi-magnify"
                        variant="outlined"
                        density="comfortable"
                        clearable
                        hide-details
                        @update:model-value="resetPage"
                    />

=======
                    <div class="sn-field-group">
                        <label for="admin-product-search" class="sn-field-label">Search products</label>
                        <v-text-field
                            id="admin-product-search"
                            v-model="search"
                            placeholder="Search product name or SKU"
                            prepend-inner-icon="mdi-magnify"
                            variant="outlined"
                            density="comfortable"
                            clearable
                            hide-details
                            @update:model-value="resetPage"
                        />
                    </div>
>>>>>>> origin/main
                </div>

                <!-- Product Type -->
                <div class="col-6 col-lg-3">
<<<<<<< HEAD

                    <v-select
                        v-model="type"
                        :items="typeOptions"
                        item-title="title"
                        item-value="value"
                        label="Product type"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        @update:model-value="resetPage"
                    />

=======
                    <div class="sn-field-group">
                        <label for="admin-product-type" class="sn-field-label">Product type</label>
                        <v-select
                            id="admin-product-type"
                            v-model="type"
                            :items="typeOptions"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="comfortable"
                            hide-details
                            @update:model-value="resetPage"
                        />
                    </div>
>>>>>>> origin/main
                </div>

                <!-- Status -->
                <div class="col-6 col-lg-2">
<<<<<<< HEAD

                    <v-select
                        v-model="status"
                        :items="statusOptions"
                        item-title="title"
                        item-value="value"
                        label="Status"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        @update:model-value="resetPage"
                    />

=======
                    <div class="sn-field-group">
                        <label for="admin-product-status" class="sn-field-label">Status</label>
                        <v-select
                            id="admin-product-status"
                            v-model="status"
                            :items="statusOptions"
                            item-title="title"
                            item-value="value"
                            variant="outlined"
                            density="comfortable"
                            hide-details
                            @update:model-value="resetPage"
                        />
                    </div>
>>>>>>> origin/main
                </div>

                <!-- Rows Per Page -->
                <div class="col-6 col-lg-2">
<<<<<<< HEAD

                    <v-select
                        v-model="pageSize"
                        :items="pageSizes"
                        label="Rows per page"
                        variant="outlined"
                        density="comfortable"
                        hide-details
                        @update:model-value="resetPage"
                    />

=======
                    <div class="sn-field-group">
                        <label for="admin-product-page-size" class="sn-field-label">Rows per page</label>
                        <v-select
                            id="admin-product-page-size"
                            v-model="pageSize"
                            :items="pageSizes"
                            variant="outlined"
                            density="comfortable"
                            hide-details
                            @update:model-value="resetPage"
                        />
                    </div>
>>>>>>> origin/main
                </div>

            </div>

            <!-- Product Table -->
            <div class="table-responsive">

                <v-table
                    class="admin-table admin-vuetify-table"
                    density="comfortable"
                >

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
                            v-for="product in shown"
                            :key="product.id"
                        >

                            <!-- Product Information -->
                            <td>
                                <strong>
                                    {{ product.name }}
                                </strong>

                                <div class="small text-medium-emphasis">
                                    {{ product.sku }}
                                </div>
                            </td>

                            <!-- Product Type -->
                            <td>
                                {{ product.type }}
                            </td>

                            <!-- Price -->
                            <td>
                                RM {{ product.price.toFixed(2) }}
                            </td>

                            <!-- Stock -->
                            <td>
                                {{
                                    product.type === 'digital'
                                        ? 'Digital'
                                        : product.stock
                                }}
                            </td>

                            <!-- Status -->
                            <td>
                                <v-chip
                                    size="small"
                                    variant="tonal"
                                    :color="statusColor(product.status)"
                                >
                                    {{ product.status }}
                                </v-chip>
                            </td>

                            <!-- Actions -->
                            <td>

                                <div class="table-actions flex-wrap">

                                    <!-- Edit -->
                                    <v-btn
                                        size="small"
                                        variant="outlined"
                                        color="primary"
                                        prepend-icon="mdi-pencil"
                                        :to="'/admin/products/' + product.id + '/edit'"
                                    >
                                        Edit
                                    </v-btn>

                                    <!-- Version History -->
                                    <v-btn
                                        size="small"
                                        variant="outlined"
                                        color="primary"
                                        prepend-icon="mdi-history"
                                        :to="'/admin/products/' + product.id + '/versions'"
                                    >
                                        Versions
                                    </v-btn>

                                    <!-- Archive -->
                                    <v-btn
                                        size="small"
                                        variant="outlined"
                                        color="error"
                                        prepend-icon="mdi-archive"
                                        :disabled="
                                            product.status === 'archived' ||
                                            deleting
                                        "
                                        @click="remove(product)"
                                    >
                                        Archive
                                    </v-btn>

                                </div>

                            </td>

                        </tr>

                    </tbody>

                </v-table>

            </div>

            <!-- Empty State -->
            <v-alert
                v-if="!loading && !shown.length"
                type="info"
                variant="tonal"
                class="mt-4"
            >
                No products match your filters.
            </v-alert>

            <!-- Pagination -->
            <div
                class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-4"
            >

                <span class="small text-medium-emphasis">
                    {{ filtered.length }} products found
                </span>

                <v-pagination
                    v-if="totalPages > 1"
                    v-model="page"
                    :length="totalPages"
                    :total-visible="5"
                    rounded="circle"
                    density="comfortable"
                />

                <span class="small text-medium-emphasis">
                    Page {{ page }} of {{ totalPages }}
                </span>

            </div>

        </div>

    </div>
</template>
