<<<<<<< HEAD

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
=======
<script>
import { api } from '@/services/shopApi.js';
import { ArrowLeft } from 'lucide-vue-next';

export default {
    name: 'AdminProductEditorView',
    components: { ArrowLeft },

    data() {
        return {
            products: [],
            model: {
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
            },
            selectedImage: null,
            previewUrl: '',
            error: '',
            loading: false,
            saving: false,
            loadFailed: false
        };
    },

    computed: {
        existing() {
            return this.products.find(product =>
                Number(product.id) === Number(this.$route.params.id)
            ) || null;
        },
        categories() {
            return Array.from(new Set(
                this.products.map(product => product.category).filter(Boolean)
            )).sort();
        },
        imagePreview() {
            if (this.previewUrl) return this.previewUrl;
            if (!this.model.imagePath) return '';
            const path = String(this.model.imagePath).replace(/^\/+/, '');
            return `${import.meta.env.BASE_URL}${path}`;
        },
        rules() {
            return {
                required: value => !!String(value === undefined || value === null ? '' : value).trim() || 'This field is required.',
                maxName: value => String(value || '').length <= 150 || 'Maximum 150 characters allowed.',
                maxSku: value => String(value || '').length <= 40 || 'Maximum 40 characters allowed.',
                maxCategory: value => String(value || '').length <= 80 || 'Maximum 80 characters allowed.',
                maxShort: value => String(value || '').length <= 255 || 'Maximum 255 characters allowed.',
                maxImage: value => String(value || '').length <= 255 || 'Maximum 255 characters allowed.',
                uniqueSku: value => {
                    const sku = String(value || '').trim().toLowerCase();
                    if (!sku) return true;
                    const duplicate = this.products.some(product =>
                        String(product.sku || '').toLowerCase() === sku &&
                        Number(product.id) !== Number(this.$route.params.id || 0)
                    );
                    return !duplicate || 'SKU must be unique.';
                },
                nonNegative: value => (
                    value !== '' && value !== null && value !== undefined &&
                    Number.isFinite(Number(value)) && Number(value) >= 0
                ) || 'Enter a zero or positive number.',
                wholeNumber: value => (
                    value !== '' && value !== null &&
                    Number.isInteger(Number(value)) && Number(value) >= 0
                ) || 'Stock must be a non-negative whole number.'
            };
        }
    },

    watch: {
        '$route.params.id'() { this.loadEditor(); },
        '$route.query.draftVersion'() { this.loadEditor(); }
    },

    mounted() {
        this.loadEditor();
    },

    beforeUnmount() {
        this.clearImageSelection();
    },

    methods: {
        clearImageSelection() {
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
            }
            this.previewUrl = '';
            this.selectedImage = null;
        },

        handleImageSelection(value) {
            const file = Array.isArray(value) ? value[0] : value;
            if (!file) {
                this.clearImageSelection();
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                this.error = 'Only JPG, PNG and WEBP images are supported.';
                this.clearImageSelection();
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                this.error = 'Image must not exceed 5 MB.';
                this.clearImageSelection();
                return;
            }

            this.error = '';
            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
            this.selectedImage = file;
            this.previewUrl = URL.createObjectURL(file);
        },

        resetForm() {
            this.model = {
                name: '',
                sku: '',
                category: this.categories[0] || '',
                type: 'physical',
                price: 0,
                stock: 0,
                status: 'draft',
                short: '',
                description: '',
                imagePath: '',
                imageAlt: ''
            };
            this.clearImageSelection();
        },

        async loadEditor() {
            this.loading = true;
            this.loadFailed = false;
            this.error = '';
            this.clearImageSelection();

            try {
                const result = await api.get('/admin/products');
                this.products = result.products || [];

                if (!this.$route.params.id) {
                    this.resetForm();
                    return;
                }

                let product = this.products.find(item =>
                    Number(item.id) === Number(this.$route.params.id)
                );

                if (!product) throw new Error('Product not found.');

                if (this.$route.query.draftVersion) {
                    const history = await api.get(
                        `/admin/products/${this.$route.params.id}/versions`
                    );
                    const draft = (history.versions || []).find(version =>
                        Number(version.version) === Number(this.$route.query.draftVersion)
                    );
                    if (!draft || !draft.snapshot) {
                        throw new Error('Restored product draft not found.');
                    }
                    product = { ...product, ...draft.snapshot };
                }

                this.model = {
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
                };

            } catch (error) {
                this.error = error.message;
                this.loadFailed = true;
            } finally {
                this.loading = false;
            }
        },

        async uploadImage() {
            if (!this.selectedImage) return;

            const formData = new FormData();
            formData.append('image', this.selectedImage);

            const result = await api.upload('/admin/upload-image', formData);
            this.model.imagePath = result.imagePath;
            if (!this.model.imageAlt.trim()) {
                this.model.imageAlt = this.model.name.trim();
            }
            this.clearImageSelection();
        },

        async save() {
            this.error = '';
            if (this.loading || this.saving) return;

            if (this.$route.params.id && !this.existing) {
                this.error = 'Product could not be loaded. Please try again.';
                return;
            }

            const validation = await this.$refs.productForm.validate();
            if (!validation.valid) {
                this.error = 'Please correct the highlighted fields.';
                return;
            }

            if (this.model.type === 'physical' && !Number.isInteger(Number(this.model.stock))) {
                this.error = 'Stock must be a whole number.';
                return;
            }

            this.saving = true;
            try {
                // Upload first; the API returns a relative path for MySQL.
                await this.uploadImage();

                const payload = {
                    name: this.model.name.trim(),
                    sku: this.model.sku.trim(),
                    category: this.model.category.trim(),
                    type: this.model.type,
                    price: Number(this.model.price),
                    stock: this.model.type === 'digital' ? 999 : Number(this.model.stock),
                    status: this.model.status,
                    short: this.model.short.trim(),
                    description: this.model.description.trim(),
                    imagePath: this.model.imagePath,
                    imageAlt: this.model.imageAlt.trim() || this.model.name.trim()
                };

                if (this.$route.params.id) {
                    await api.put('/products/' + this.$route.params.id, payload);
                } else {
                    await api.post('/products', payload);
                }

                await this.$router.push('/admin/products');

            } catch (error) {
                this.error = error.message;
            } finally {
                this.saving = false;
            }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div>
<<<<<<< HEAD

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
=======
        <RouterLink class="back-link" to="/admin/products">
            <ArrowLeft :size="17" /> Products
        </RouterLink>

        <div class="admin-page-top">
            <div>
                <p class="eyebrow">PRODUCT EDITOR</p>
                <h1>{{ $route.params.id ? 'Edit product' : 'Add product' }}</h1>
                <p class="muted">Keep your catalog accurate, helpful and easy to explore.</p>
            </div>

            <v-btn
                v-if="existing"
                variant="outlined"
                color="primary"
                prepend-icon="mdi-history"
                :to="'/admin/products/' + existing.id + '/versions'"
            >
                Version history & compare
            </v-btn>
        </div>

        <v-progress-linear v-if="loading" indeterminate color="primary" class="mb-4" />

        <v-alert v-if="loadFailed" type="error" variant="tonal" class="mb-4">
            {{ error }}
            <template #append>
                <v-btn variant="text" @click="loadEditor">Retry</v-btn>
>>>>>>> origin/main
            </template>
        </v-alert>

        <template v-else-if="!loading">
<<<<<<< HEAD

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
=======
            <v-alert v-if="$route.query.draftVersion" type="info" variant="tonal" class="mb-4">
                You are editing restored draft v{{ $route.query.draftVersion }}. Saving will create a new product version. Review before saving.
            </v-alert>

>>>>>>> origin/main
            <v-form
                v-if="!loadFailed"
                ref="productForm"
                class="panel editor-form admin-vuetify-form"
<<<<<<< HEAD
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

=======
                validate-on="input"
                @submit.prevent="save"
            >
                <div class="row g-4">
                    <div class="col-lg-8">
                        <h2>Product details</h2>

                        <div class="sn-field-group">
                            <label for="editor-product-name" class="sn-field-label">Product name <span class="required">*</span></label>
                            <v-text-field
                                id="editor-product-name"
                                v-model="model.name"
                                placeholder="Enter product name"
                                variant="outlined"
                                density="comfortable"
                                maxlength="150"
                                hide-details="auto"
                                :rules="[rules.required, rules.maxName]"
                                required
                            />
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="sn-field-group">
                                    <label for="editor-sku" class="sn-field-label">SKU <span class="required">*</span></label>
                                    <v-text-field
                                        id="editor-sku"
                                        v-model="model.sku"
                                        placeholder="Enter unique SKU"
                                        variant="outlined"
                                        density="comfortable"
                                        maxlength="40"
                                        hide-details="auto"
                                        :rules="[rules.required, rules.maxSku, rules.uniqueSku]"
                                        required
                                    />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="sn-field-group">
                                    <label for="editor-category" class="sn-field-label">Category <span class="required">*</span></label>
                                    <v-combobox
                                        id="editor-category"
                                        v-model="model.category"
                                        :items="categories"
                                        placeholder="Choose or enter a category"
                                        variant="outlined"
                                        density="comfortable"
                                        hide-details="auto"
                                        :rules="[rules.required, rules.maxCategory]"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="sn-field-group">
                            <label for="editor-short" class="sn-field-label">Short description <span class="required">*</span></label>
                            <v-text-field
                                id="editor-short"
                                v-model="model.short"
                                placeholder="Write a short product summary"
                                variant="outlined"
                                density="comfortable"
                                counter="255"
                                hide-details="auto"
                                :rules="[rules.required, rules.maxShort]"
                                required
                            />
                        </div>

                        <div class="sn-field-group">
                            <label for="editor-description" class="sn-field-label">Product description</label>
                            <v-textarea
                                id="editor-description"
                                v-model="model.description"
                                placeholder="Write a detailed description of your product..."
                                variant="outlined"
                                rows="5"
                                auto-grow
                                hide-details="auto"
                            />
                        </div>

                        <div class="mt-4">
                            <h2>Product image</h2>
                            <div class="sn-field-group">
                                <label for="editor-image" class="sn-field-label">Choose product image</label>
                                <v-file-input
                                    id="editor-image"
                                    v-model="selectedImage"
                                    accept="image/jpeg,image/png,image/webp"
                                    placeholder="Select JPG, PNG or WEBP image"
                                    variant="outlined"
                                    density="comfortable"
                                    prepend-icon="mdi-image"
                                    show-size
                                    clearable
                                    hide-details="auto"
                                    @update:model-value="handleImageSelection"
                                />
                            </div>
                            <p class="small muted">
                                Supported formats: JPG, PNG and WEBP. Maximum file size: 5 MB.
                                The image will be uploaded to Mercury when you save the product.
                            </p>

                            <div v-if="imagePreview" class="mt-3">
                                <p class="form-label">Image preview</p>
>>>>>>> origin/main
                                <img
                                    :src="imagePreview"
                                    :alt="model.imageAlt || model.name"
                                    class="product-upload-preview"
                                />
<<<<<<< HEAD

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

=======
                            </div>
                            <v-alert v-else type="info" variant="tonal" density="compact">
                                No product image selected. Existing images remain unchanged unless you select a replacement.
                            </v-alert>

                            <div class="sn-field-group mt-3">
                                <label for="editor-image-path" class="sn-field-label">Saved image path</label>
                                <v-text-field
                                    id="editor-image-path"
                                    v-model="model.imagePath"
                                    placeholder="Image path appears after upload"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    readonly
                                />
                            </div>
                            <div class="sn-field-group">
                                <label for="editor-image-alt" class="sn-field-label">Image description (alt text)</label>
                                <v-text-field
                                    id="editor-image-alt"
                                    v-model="model.imageAlt"
                                    placeholder="Describe the product image"
                                    variant="outlined"
                                    density="comfortable"
                                    maxlength="255"
                                    hide-details="auto"
                                    :rules="[rules.maxImage]"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="editor-side">
                            <h2>Store settings</h2>

                            <div class="sn-field-group">
                                <label for="editor-status" class="sn-field-label">Publication status</label>
                                <v-select
                                    id="editor-status"
                                    v-model="model.status"
                                    :items="[
                                        { title: 'Draft', value: 'draft' },
                                        { title: 'Published', value: 'published' },
                                        { title: 'Archived', value: 'archived' }
                                    ]"
                                    item-title="title"
                                    item-value="value"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                />
                            </div>

                            <div class="sn-field-group">
                                <label for="editor-type" class="sn-field-label">Product type</label>
                                <v-select
                                    id="editor-type"
                                    v-model="model.type"
                                    :items="[
                                        { title: 'Physical', value: 'physical' },
                                        { title: 'Digital', value: 'digital' }
                                    ]"
                                    item-title="title"
                                    item-value="value"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                />
                            </div>

                            <div class="sn-field-group">
                                <label for="editor-price" class="sn-field-label">Price (MYR) <span class="required">*</span></label>
                                <v-text-field
                                    id="editor-price"
                                    v-model.number="model.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    :rules="[rules.nonNegative]"
                                    required
                                />
                            </div>

                            <div v-if="model.type === 'physical'" class="sn-field-group">
                                <label for="editor-stock" class="sn-field-label">Available stock <span class="required">*</span></label>
                                <v-text-field
                                    id="editor-stock"
                                    v-model.number="model.stock"
                                    type="number"
                                    min="0"
                                    step="1"
                                    placeholder="0"
                                    variant="outlined"
                                    density="comfortable"
                                    hide-details="auto"
                                    :rules="[rules.nonNegative, rules.wholeNumber]"
                                    required
                                />
                            </div>

                            <v-alert v-else type="info" variant="tonal" density="compact">
                                Digital products use simulated stock of 999.
                            </v-alert>
                        </div>
                    </div>
                </div>

                <v-alert v-if="error" type="error" variant="tonal" class="mt-3">
                    {{ error }}
                </v-alert>

                <div class="editor-actions">
                    <v-btn to="/admin/products" variant="outlined" color="primary" :disabled="saving">
                        Cancel
                    </v-btn>
                    <v-btn type="submit" color="primary" prepend-icon="mdi-content-save" :loading="saving" :disabled="loading">
                        Save product
                    </v-btn>
                </div>
            </v-form>
        </template>
>>>>>>> origin/main
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
