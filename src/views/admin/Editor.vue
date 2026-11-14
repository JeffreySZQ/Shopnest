
<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Save } from 'lucide-vue-next';
import { api } from '@/services/shopApi.js';

const route = useRoute();
const router = useRouter();

const products = ref([]);
const productForm = ref(null);

const error = ref('');
const loading = ref(false);
const saving = ref(false);
const loadFailed = ref(false);

// Find the existing product from API-loaded data.
const existing = computed(() =>
    products.value.find(
        p => Number(p.id) === Number(route.params.id)
    ) || null
);

// Get available categories from the database products.
const categories = computed(() =>
    Array.from(
        new Set(
            products.value
                .map(p => p.category)
                .filter(Boolean)
        )
    ).sort()
);

// Product form data.
const model = reactive({
    name: '',
    sku: '',
    category: '',
    type: 'physical',
    price: 0,
    stock: 0,
    status: 'draft',
    short: '',
    description: '',
    imagePath: '',
    imageAlt: ''
});

// Vuetify validation rules.
const rules = {
    required: value =>
        !!String(value ?? '').trim() ||
        'This field is required.',

    uniqueSku: value => {
        const sku = String(value ?? '')
            .trim()
            .toLowerCase();

        if (!sku) return true;

        const duplicate = products.value.some(p =>
            String(p.sku).toLowerCase() === sku &&
            Number(p.id) !== Number(route.params.id || 0)
        );

        return !duplicate || 'SKU must be unique.';
    },

    nonNegative: value =>
        (
            value !== '' &&
            value !== null &&
            value !== undefined &&
            Number.isFinite(Number(value)) &&
            Number(value) >= 0
        ) || 'Enter a zero or positive number.',

    wholeNumber: value =>
        (
            value !== '' &&
            value !== null &&
            Number.isInteger(Number(value)) &&
            Number(value) >= 0
        ) || 'Stock must be a whole number.',

    maxName: value =>
        String(value ?? '').length <= 150 ||
        'Maximum 150 characters allowed.',

    maxSku: value =>
        String(value ?? '').length <= 40 ||
        'Maximum 40 characters allowed.',

    maxCategory: value =>
        String(value ?? '').length <= 80 ||
        'Maximum 80 characters allowed.',

    maxShort: value =>
        String(value ?? '').length <= 255 ||
        'Maximum 255 characters allowed.',

    maxImage: value =>
        String(value ?? '').length <= 255 ||
        'Maximum 255 characters allowed.'
};

// Reset form values.
function resetForm() {
    Object.assign(model, {
        name: '',
        sku: '',
        category: categories.value[0] || '',
        type: 'physical',
        price: 0,
        stock: 0,
        status: 'draft',
        short: '',
        description: '',
        imagePath: '',
        imageAlt: ''
    });
}

// Load product information directly from PHP API.
async function loadEditor() {
    loading.value = true;
    loadFailed.value = false;
    error.value = '';

    try {
        // Retrieve products from MySQL through the admin API.
        const result = await api.get('/admin/products');

        products.value = result.products || [];

        // New product.
        if (!route.params.id) {
            resetForm();
            return;
        }

        // Find the selected product.
        let product = products.value.find(
            p => Number(p.id) === Number(route.params.id)
        );

        if (!product) {
            throw new Error('Product not found.');
        }

        // Load a restored draft when requested.
        if (route.query.draftVersion) {
            const history = await api.get(
                `/admin/products/${route.params.id}/versions`
            );

            const draft = history.versions.find(v =>
                Number(v.version) ===
                Number(route.query.draftVersion)
            );

            if (!draft || !draft.snapshot) {
                throw new Error('Restored product draft not found.');
            }

            product = {
                ...product,
                ...draft.snapshot
            };
        }

        // Populate the form.
        Object.assign(model, {
            name: product.name || '',
            sku: product.sku || '',
            category: product.category || '',
            type: product.type || 'physical',
            price: Number(product.price || 0),
            stock: Number(product.stock || 0),
            status: product.status || 'draft',
            short: product.short || '',
            description: product.description || '',
            imagePath: product.imagePath || '',
            imageAlt: product.imageAlt || ''
        });

    } catch (e) {
        error.value = e.message;
        loadFailed.value = true;

    } finally {
        loading.value = false;
    }
}

// Reload when the selected product or restored draft changes.
watch(
    () => [
        route.params.id,
        route.query.draftVersion
    ],
    loadEditor,
    { immediate: true }
);

// Save product through the PHP REST API.
async function save() {
    error.value = '';

    if (loading.value || saving.value) return;

    if (route.params.id && !existing.value) {
        error.value = 'Product could not be loaded. Please try again.';
        return;
    }

    // Validate Vuetify form.
    const validation = await productForm.value.validate();

    if (!validation.valid) {
        error.value = 'Please correct the highlighted fields.';
        return;
    }

    // Extra numeric validation.
    if (
        !Number.isFinite(Number(model.price)) ||
        Number(model.price) < 0
    ) {
        error.value = 'Price cannot be negative.';
        return;
    }

    if (
        model.type === 'physical' &&
        (
            !Number.isInteger(Number(model.stock)) ||
            Number(model.stock) < 0
        )
    ) {
        error.value = 'Stock must be a non-negative whole number.';
        return;
    }

    saving.value = true;

    try {
        // Data structure must match the PHP API.
        const payload = {
            name: model.name.trim(),
            sku: model.sku.trim(),
            category: model.category.trim(),
            type: model.type,

            price: Number(model.price),

            stock: model.type === 'digital'
                ? 999
                : Number(model.stock),

            status: model.status,

            short: model.short.trim(),

            description: model.description.trim(),

            imagePath: model.imagePath.trim(),

            imageAlt: model.imageAlt.trim() ||
                model.name.trim()
        };

        // UPDATE existing product.
        if (route.params.id) {
            await api.put(
                '/products/' + route.params.id,
                payload
            );

        } else {
            // CREATE new product.
            await api.post(
                '/products',
                payload
            );
        }

        // Return to the admin product listing.
        // Products.vue will reload the database records.
        router.push('/admin/products');

    } catch (e) {
        error.value = e.message;

    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div>

        <!-- Back Button -->
        <RouterLink
            class="back-link"
            to="/admin/products"
        >
            <ArrowLeft :size="17" />
            Products
        </RouterLink>

        <!-- Page Heading -->
        <div class="admin-page-top">

            <div>
                <p class="eyebrow">
                    PRODUCT EDITOR
                </p>

                <h1>
                    {{ route.params.id
                        ? 'Edit product'
                        : 'Add product'
                    }}
                </h1>

                <p class="muted">
                    Keep your catalog accurate, helpful
                    and easy to explore.
                </p>
            </div>

            <!-- Version History Button -->
            <v-btn
                v-if="route.params.id"
                variant="outlined"
                color="primary"
                prepend-icon="mdi-history"
                :to="'/admin/products/' + route.params.id + '/versions'"
            >
                Version history & compare
            </v-btn>

        </div>

        <!-- Loading Indicator -->
        <v-progress-linear
            v-if="loading"
            indeterminate
            color="primary"
            class="mb-4"
        />

        <!-- Loading Error -->
        <v-alert
            v-if="loadFailed"
            type="error"
            variant="tonal"
            class="mb-4"
        >
            {{ error }}

            <template #append>
                <v-btn
                    variant="text"
                    @click="loadEditor"
                >
                    Retry
                </v-btn>
            </template>
        </v-alert>

        <template v-else-if="!loading">

            <!-- Restored Draft Notice -->
            <v-alert
                v-if="route.query.draftVersion"
                type="info"
                variant="tonal"
                class="mb-4"
            >
                You are editing restored draft
                v{{ route.query.draftVersion }}.

                Saving will create a new product version.
                Review before saving.
            </v-alert>

            <!-- Vuetify Form -->
            <v-form
                v-if="!loadFailed"
                ref="productForm"
                class="panel editor-form admin-vuetify-form"
                @submit.prevent="save"
            >

                <div class="row g-4">

                    <!-- Product Details -->
                    <div class="col-lg-8">

                        <h2>Product details</h2>

                        <!-- Product Name -->
                        <v-text-field
                            v-model="model.name"
                            label="Product name"
                            variant="outlined"
                            density="comfortable"
                            maxlength="150"
                            :rules="[
                                rules.required,
                                rules.maxName
                            ]"
                            required
                        />

                        <div class="row g-3">

                            <!-- SKU -->
                            <div class="col-md-6">

                                <v-text-field
                                    v-model="model.sku"
                                    label="SKU"
                                    variant="outlined"
                                    density="comfortable"
                                    maxlength="40"
                                    :rules="[
                                        rules.required,
                                        rules.maxSku,
                                        rules.uniqueSku
                                    ]"
                                    required
                                />

                            </div>

                            <!-- Category -->
                            <div class="col-md-6">

                                <v-combobox
                                    v-model="model.category"
                                    :items="categories"
                                    label="Category"
                                    variant="outlined"
                                    density="comfortable"
                                    :rules="[
                                        rules.required,
                                        rules.maxCategory
                                    ]"
                                    required
                                />

                            </div>

                        </div>

                        <!-- Short Description -->
                        <v-text-field
                            v-model="model.short"
                            label="Short description"
                            variant="outlined"
                            density="comfortable"
                            counter="255"
                            :rules="[
                                rules.required,
                                rules.maxShort
                            ]"
                            required
                        />

                        <!-- Full Description -->
                        <v-textarea
                            v-model="model.description"
                            label="Product description"
                            variant="outlined"
                            rows="5"
                            auto-grow
                            hint="Enter the full product description."
                            persistent-hint
                        />

                        <!-- Image Path -->
                        <v-text-field
                            v-model="model.imagePath"
                            label="Product image path"
                            variant="outlined"
                            density="comfortable"
                            maxlength="255"
                            placeholder="images/products/product-name.jpg"
                            :rules="[rules.maxImage]"
                        />

                        <!-- Image Description -->
                        <v-text-field
                            v-model="model.imageAlt"
                            label="Product image description"
                            variant="outlined"
                            density="comfortable"
                            maxlength="255"
                            :rules="[rules.maxImage]"
                        />

                    </div>

                    <!-- Store Settings -->
                    <div class="col-lg-4">

                        <div class="editor-side">

                            <h2>Store settings</h2>

                            <!-- Publication Status -->
                            <v-select
                                v-model="model.status"
                                :items="[
                                    {
                                        title: 'Draft',
                                        value: 'draft'
                                    },
                                    {
                                        title: 'Published',
                                        value: 'published'
                                    }
                                ]"
                                item-title="title"
                                item-value="value"
                                label="Publication status"
                                variant="outlined"
                                density="comfortable"
                            />

                            <!-- Product Type -->
                            <v-select
                                v-model="model.type"
                                :items="[
                                    {
                                        title: 'Physical',
                                        value: 'physical'
                                    },
                                    {
                                        title: 'Digital',
                                        value: 'digital'
                                    }
                                ]"
                                item-title="title"
                                item-value="value"
                                label="Product type"
                                variant="outlined"
                                density="comfortable"
                            />

                            <!-- Product Price -->
                            <v-text-field
                                v-model.number="model.price"
                                label="Price (MYR)"
                                type="number"
                                min="0"
                                step="0.01"
                                variant="outlined"
                                density="comfortable"
                                :rules="[rules.nonNegative]"
                                required
                            />

                            <!-- Physical Product Stock -->
                            <v-text-field
                                v-if="model.type === 'physical'"
                                v-model.number="model.stock"
                                label="Available stock"
                                type="number"
                                min="0"
                                step="1"
                                variant="outlined"
                                density="comfortable"
                                :rules="[
                                    rules.nonNegative,
                                    rules.wholeNumber
                                ]"
                                required
                            />

                            <!-- Digital Product Information -->
                            <v-alert
                                v-else
                                type="info"
                                variant="tonal"
                                density="compact"
                            >
                                Digital products use simulated
                                stock of 999.
                            </v-alert>

                            <p class="small muted mt-3">
                                Image paths are saved in the
                                existing image_path and image_alt
                                database columns.

                                Upload image files separately
                                to the project images folder.
                            </p>

                        </div>

                    </div>

                </div>

                <!-- Error Message -->
                <v-alert
                    v-if="error"
                    type="error"
                    variant="tonal"
                    class="mt-3"
                >
                    {{ error }}
                </v-alert>

                <!-- Form Actions -->
                <div class="editor-actions">

                    <v-btn
                        to="/admin/products"
                        variant="outlined"
                        color="primary"
                        :disabled="saving"
                    >
                        Cancel
                    </v-btn>

                    <v-btn
                        type="submit"
                        color="primary"
                        prepend-icon="mdi-content-save"
                        :loading="saving"
                        :disabled="loading"
                    >
                        Save product
                    </v-btn>

                </div>

            </v-form>

        </template>

    </div>
</template>
