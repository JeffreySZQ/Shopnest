<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { ArrowLeft, RotateCcw, CheckCircle2, GitCompare, Image as ImageIcon } from 'lucide-vue-next';
import ProductArt from '@/components/ProductArt.vue';
import { api } from '@/services/shopApi.js';

const s = useStore();
const route = useRoute();
const router = useRouter();
const product = ref(null);
const versions = ref([]);
const olderId = ref(null);
const newerId = ref(null);
const isConfirming = ref(false);
const error = ref('');
const loading = ref(true);

async function loadVersions() {
    loading.value = true;
    error.value = '';
    try {
        const id = Number(route.params.id);
        const productResult = await api.get('/products/' + id);
        product.value = productResult.product;
        const versionResult = await api.get(`/admin/products/${id}/versions`);
        versions.value = versionResult.versions.map(v => ({
            ...v,
            version: Number(v.version),
            snapshot: v.snapshot || {}
        }));
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

onMounted(loadVersions);
watch(() => route.params.id, loadVersions);

watch(versions, list => {
    if (!list.length) return;
    if (!list.some(v => v.version === newerId.value)) newerId.value = list[0].version;
    if (!list.some(v => v.version === olderId.value) || olderId.value === newerId.value) {
        const previous = list.find(v => v.version !== newerId.value);
        olderId.value = previous ? previous.version : list[0].version;
    }
}, { immediate: true });

const oldVersion = computed(() => versions.value.find(v => v.version === olderId.value));
const newVersion = computed(() => versions.value.find(v => v.version === newerId.value));
const oldSnap = computed(() => oldVersion.value ? oldVersion.value.snapshot : {});
const newSnap = computed(() => newVersion.value ? newVersion.value.snapshot : {});

const comparable = [
    { key: 'name', label: 'Product name' },
    { key: 'sku', label: 'SKU' },
    { key: 'category', label: 'Category' },
    { key: 'type', label: 'Product type' },
    { key: 'status', label: 'Publication' },
    { key: 'short', label: 'Short description' },
    { key: 'description', label: 'Long description' },
    { key: 'price', label: 'Unit price' },
    { key: 'stock', label: 'Stock' },
    { key: 'imagePath', label: 'Image path' },
    { key: 'imageAlt', label: 'Image description' }
];

function displayValue(snapshot, key) {
    if (key === 'price') return `RM ${Number(snapshot.price || 0).toFixed(2)}`;
    if (key === 'stock') return String(Number(snapshot.stock || 0));
    return String(snapshot[key] === undefined || snapshot[key] === null || snapshot[key] === '' ? 'Not specified' : snapshot[key]);
}

function changed(key) {
    return displayValue(oldSnap.value, key) !== displayValue(newSnap.value, key);
}

const changedCount = computed(() => comparable.filter(field => changed(field.key)).length);

async function restore() {
    if (!product.value || !olderId.value) return;
    error.value = '';
    try {
        const result = await api.post(`/admin/products/${product.value.id}/restore/${olderId.value}`, {});
        isConfirming.value = false;
        router.push({
            name: 'admin-product-edit',
            params: { id: product.value.id },
            query: { draftVersion: String(result.draftVersion) }
        });
    } catch (e) {
        error.value = e.message;
    }
}

function formatted(date) {
    return new Date(date).toLocaleString('en-MY', {
        dateStyle: 'medium',
        timeStyle: 'short'
    });
}
</script>

<template>
    <div v-if="loading" class="version-page"><p>Loading version history...</p></div>
    <div v-else-if="error" class="version-page"><p class="text-danger" role="alert">{{ error }}</p></div>
    <div v-else-if="product" class="version-page">
        <RouterLink
            class="back-link"
            :to="'/admin/products/' + product.id + '/edit'"
        >
            <ArrowLeft :size="17" />
            Back to product editor
        </RouterLink>

        <div class="admin-page-top">
            <div>
                <p class="eyebrow">PRODUCT VERSION CONTROL</p>

                <h1>Compare product versions</h1>

                <p class="muted">
                    Review changes and restore an earlier version without
                    replacing the live listing.
                </p>
            </div>

            <RouterLink
                :to="'/admin/products/' + product.id + '/edit'"
                class="btn btn-outline-primary"
            >
                Return to editor
            </RouterLink>
        </div>

        <div class="panel version-product-header">
            <div class="version-product-icon">
                <ProductArt :product="product" />
            </div>

            <div>
                <strong>{{ product.name }}</strong>

                <div class="small muted">
                    {{ product.sku }} · {{ product.type }} · {{ product.category }}
                </div>
            </div>

            <span class="ms-auto fw-bold">
                RM {{ product.price.toFixed(2) }} / unit
            </span>
        </div>

        <p class="version-notice">Version history is retrieved from the ShopNest database. Restoring a version creates a draft for review; the live product is not changed until you save the draft.</p>

        <div class="version-layout">
            <aside class="panel version-sidebar">
                <h2>Version history</h2>

                <p class="small muted">
                    Choose exactly two versions to compare.
                </p>

                <button
                    v-for="v in versions"
                    :key="v.version"
                    class="version-tile"
                    variant="text"
                    block
                    :class="{ selected: v.version === olderId || v.version === newerId }"
                    @click="v.version === newerId ? olderId = v.version : newerId = v.version"
                >
                    <div class="version-tile-content">
                        <div class="version-tile-heading">
                            <strong>v{{ v.version }}</strong>
                            <span class="version-tile-status">{{ v.status }}</span>
                        </div>

                        <small>{{ formatted(v.createdAt) }}</small>
                        <small>{{ v.actor }}</small>
                        <small class="version-tile-note">{{ v.note }}</small>
                    </div>
                </button>
            </aside>

            <div class="version-main">
                <div class="panel version-selectors">
                    <div
                        class="d-flex justify-content-between align-items-center gap-2 flex-wrap"
                    >
                        <h2>Compare exactly two versions</h2>

                        <span class="version-chip">
                            {{ changedCount }} changed fields
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="oldversion"
                            >
                                Earlier / restore source
                            </label>

                            <select
                                id="oldversion"
                                class="form-select"
                                v-model.number="olderId"
                            >
                                <option
                                    v-for="v in versions"
                                    :key="v.version"
                                    :value="v.version"
                                >
                                    v{{ v.version }} ·
                                    {{ formatted(v.createdAt) }}
                                    ({{ v.status }})
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="newversion"
                            >
                                Compare with
                            </label>

                            <select
                                id="newversion"
                                class="form-select"
                                v-model.number="newerId"
                            >
                                <option
                                    v-for="v in versions"
                                    :key="v.version"
                                    :value="v.version"
                                >
                                    v{{ v.version }} ·
                                    {{ formatted(v.createdAt) }}
                                    ({{ v.status }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <p class="small muted mb-0 mt-3">
                        Highlighted values are different. Unchanged values
                        appear without highlighting.
                    </p>
                </div>

                <div
                    v-if="oldVersion && newVersion"
                    class="version-diff panel"
                >
                    <div class="version-diff-head">
                        <div>
                            <strong>
                                Earlier · v{{ oldVersion.version }}
                            </strong>

                            <small>
                                {{ oldVersion.actor }} ·
                                {{ formatted(oldVersion.createdAt) }}
                            </small>

                            <small>{{ oldVersion.status }}</small>
                        </div>

                        <div>
                            <strong>
                                Compared · v{{ newVersion.version }}
                            </strong>

                            <small>
                                {{ newVersion.actor }} ·
                                {{ formatted(newVersion.createdAt) }}
                            </small>

                            <small>{{ newVersion.status }}</small>
                        </div>
                    </div>

                    <section
                        v-for="field in comparable"
                        :key="field.key"
                        class="version-field"
                    >
                        <div class="version-field-label">
                            <strong>{{ field.label }}</strong>

                            <span
                                :class="
                                    changed(field.key)
                                        ? 'change-tag'
                                        : 'unchanged-tag'
                                "
                            >
                                {{ changed(field.key) ? 'Changed' : 'Unchanged' }}
                            </span>
                        </div>

                        <div class="version-value-grid">
                            <div
                                :class="{
                                    'version-removed': changed(field.key)
                                }"
                            >
                                <template v-if="field.key === 'imagePath'">
                                    <ProductArt
                                        :product="{ ...product, ...oldSnap }"
                                    />
                                </template>

                                <div class="version-text">
                                    {{ displayValue(oldSnap, field.key) }}
                                </div>
                            </div>

                            <div
                                :class="{
                                    'version-added': changed(field.key)
                                }"
                            >
                                <template v-if="field.key === 'imagePath'">
                                    <ProductArt
                                        :product="{ ...product, ...newSnap }"
                                    />
                                </template>

                                <div class="version-text">
                                    {{ displayValue(newSnap, field.key) }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="panel version-restore">
                    <div>
                        <h2>
                            Restore earlier version v{{ olderId }}
                        </h2>

                        <p class="muted mb-0">
                            Create a new draft based on this snapshot.
                            Current product content, stock, orders and sales
                            will stay unchanged until you explicitly save
                            the draft in the editor.
                        </p>
                    </div>

                    <button
                        class="btn btn-primary"
                        type="button"
                        :disabled="olderId === newerId"
                        @click="isConfirming = true"
                    >
                        <RotateCcw :size="16" />
                        Review restore
                    </button>
                </div>

                <div
                    v-if="isConfirming"
                    class="panel version-confirm"
                    role="region"
                    aria-label="Confirm version restore"
                >
                    <h2>
                        <CheckCircle2 :size="21" />
                        Confirm restore to a new draft?
                    </h2>

                    <p>
                        The content from
                        <strong>v{{ olderId }}</strong>
                        will be copied into a new draft version.
                        The published product will not change.
                        You will be taken to the editor to review
                        and save the draft.
                    </p>

                    <p
                        v-if="error"
                        role="alert"
                        class="text-danger"
                    >
                        {{ error }}
                    </p>

                    <div class="d-flex gap-2 flex-wrap">
                        <button
                            class="btn btn-outline-primary"
                            type="button"
                            @click="isConfirming = false"
                        >
                            Cancel
                        </button>

                        <button
                            class="btn btn-primary"
                            type="button"
                            @click="restore"
                        >
                            <RotateCcw :size="16" />
                            Confirm restore
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div v-else class="panel">
        <h1>Product not found</h1>

        <RouterLink to="/admin/products">
            Return to products
        </RouterLink>
    </div>
</template>