
<script setup>
import {
    computed,
    reactive,
    ref,
    watch,
    onBeforeUnmount
} from 'vue';

import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import { api } from '@/services/shopApi.js';

const route = useRoute();
const router = useRouter();

// Product data
const products = ref([]);
const productForm = ref(null);

// Loading and error states
const error = ref('');
const loading = ref(false);
const saving = ref(false);
const loadFailed = ref(false);

// Image upload states
const selectedImage = ref(null);
const previewUrl = ref('');

// Product form model
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

// Existing product
const existing = computed(() =>
    products.value.find(
        p => Number(p.id) === Number(route.params.id)
    ) || null
);

// Available categories
const categories = computed(() =>
    Array.from(
        new Set(
            products.value
                .map(p => p.category)
                .filter(Boolean)
        )
    ).sort()
);

// Image preview
const imagePreview = computed(() => {
    if (previewUrl.value) {
        return previewUrl.value;
    }

    if (!model.imagePath) {
        return '';
    }

    const path = String(model.imagePath).replace(/^\/+/, '');

    return `${import.meta.env.BASE_URL}${path}`;
});

// Vuetify validation rules
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
        value !== '' &&
        value !== null &&
        value !== undefined &&
        Number.isFinite(Number(value)) &&
        Number(value) >= 0 ||
        'Enter a zero or positive number.',

    wholeNumber: value =>
        value !== '' &&
        value !== null &&
        Number.isInteger(Number(value)) &&
        Number(value) >= 0 ||
        'Stock must be a non-negative whole number.',

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

// Clear selected image and preview.
function clearImageSelection() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    previewUrl.value = '';
    selectedImage.value = null;
}

// Handle selected image.
function handleImageSelection(files) {
    const file = Array.isArray(files)
        ? files[0]
        : files;

    if (!file) {
        clearImageSelection();
        return;
    }

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!allowedTypes.includes(file.type)) {
        error.value = 'Only JPG, PNG and WEBP images are supported.';
        clearImageSelection();
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        error.value = 'Image must not exceed 5 MB.';
        clearImageSelection();
        return;
    }

    error.value = '';

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }

    selectedImage.value = file;
    previewUrl.value = URL.createObjectURL(file);
}

// Reset form for new product.
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

    clearImageSelection();
}

// Load products directly from PHP API.
async function loadEditor() {
    loading.value = true;
    loadFailed.value = false;
    error.value = '';

    clearImageSelection();

    try {
        const result = await api.get('/admin/products');

        products.value = result.products || [];

        // New product
        if (!route.params.id) {
            resetForm();
            return;
        }

        // Find existing product
        let product = products.value.find(
            p => Number(p.id) === Number(route.params.id)
        );

        if (!product) {
            throw new Error('Product not found.');
        }

        // Load restored draft version if requested.
        if (route.query.draftVersion) {
            const history = await api.get(
                `/admin/products/${route.params.id}/versions`
            );

            const draft = history.versions.find(v =>
                Number(v.version) ===
                Number(route.query.draftVersion)
            );

            if (!draft || !draft.snapshot) {
                throw new Error('Restored draft not found.');
            }

            product = {
                ...product,
                ...draft.snapshot
            };
        }

        // Populate form.
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

// Reload when product route changes.
watch(
    () => [
        route.params.id,
        route.query.draftVersion
    ],
    loadEditor,
    { immediate: true }
);

// Upload selected image to Mercury.
async function uploadImage() {
    if (!selectedImage.value) {
        return;
    }

    const formData = new FormData();

    formData.append('image', selectedImage.value);

    const result = await api.upload(
        '/admin/upload-image',
        formData
    );

    // Save returned relative path in product model.
    model.imagePath = result.imagePath;

    if (!model.imageAlt.trim()) {
        model.imageAlt = model.name.trim();
    }

    // Keep the uploaded image path, but release preview memory.
    clearImageSelection();
}

// Save product using PHP API.
async function save() {
    error.value = '';

    if (loading.value || saving.value) {
        return;
    }

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
        // Step 1: Upload image if a new file was selected.
        await uploadImage();

        // Step 2: Prepare product data.
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

            imagePath: model.imagePath,

            imageAlt: model.imageAlt.trim() ||
                model.name.trim()
        };

        // Step 3: Create or update product.
        if (route.params.id) {
            await api.put(
                '/products/' + route.params.id,
                payload
            );
        } else {
            await api.post(
                '/products',
                payload
            );
        }

        // Step 4: Return to product listing.
        router.push('/admin/products');

    } catch (e) {
        error.value = e.message;

    } finally {
        saving.value = false;
    }
}

// Clean up temporary image preview.
onBeforeUnmount(() => {
    clearImageSelection();
});
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

            <!-- Version History -->
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

        <!-- Loading -->
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

            <!-- Product Form -->
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
                            placeholder="Write a detailed description of your product..."
                            variant="outlined"
                            rows="5"
                            auto-grow
                        />

                        <!-- Image Upload -->
                        <div class="mt-4">

                            <h2>Product image</h2>

                            <v-file-input
                                v-model="selectedImage"
                                label="Choose product image"
                                accept="image/jpeg,image/png,image/webp"
                                variant="outlined"
                                density="comfortable"
                                prepend-icon="mdi-image"
                                prepend-inner-icon="mdi-upload"
                                show-size
                                clearable
                                @update:model-value="handleImageSelection"
                            />

                            <p class="small muted">
                                Supported formats: JPG, PNG and WEBP.
                                Maximum file size: 5 MB.
                                The image will be uploaded to Mercury
                                when you save the product.
                            </p>

                            <!-- Image Preview -->
                            <div
                                v-if="imagePreview"
                                class="mt-3"
                            >

                                <p class="form-label">
                                    Image preview
                                </p>

                                <img
                                    :src="imagePreview"
                                    :alt="model.imageAlt || model.name"
                                    class="product-upload-preview"
                                />

                            </div>

                            <v-alert
                                v-else
                                type="info"
                                variant="tonal"
                                density="compact"
                            >
                                No product image selected.
                            </v-alert>

                            <!-- Stored Image Path -->
                            <v-text-field
                                v-model="model.imagePath"
                                label="Saved image path"
                                variant="outlined"
                                density="comfortable"
                                readonly
                                class="mt-3"
                            />

                            <!-- Image Alt -->
                            <v-text-field
                                v-model="model.imageAlt"
                                label="Image description (alt text)"
                                variant="outlined"
                                density="comfortable"
                                maxlength="255"
                            />

                        </div>

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
                                    },
                                    {
                                        title: 'Archived',
                                        value: 'archived'
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

                            <!-- Price -->
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

                            <!-- Stock -->
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

                            <!-- Digital Product Notice -->
                            <v-alert
                                v-else
                                type="info"
                                variant="tonal"
                                density="compact"
                            >
                                Digital products use simulated
                                stock of 999.
                            </v-alert>

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

<style scoped>
.product-upload-preview {
    display: block;
    width: 100%;
    max-width: 360px;
    max-height: 260px;
    object-fit: contain;
    border: 1px solid #dce3ec;
    border-radius: 12px;
    background: #f7f9fc;
    padding: 8px;
}
</style>
