<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import {
    ArrowLeft,
    RotateCcw,
    CheckCircle2,
    GitCompare,
    Image as ImageIcon
} from 'lucide-vue-next';
import ProductArt from '@/components/ProductArt.vue';

const s = useStore();
const route = useRoute();
const router = useRouter();

const product = computed(() =>
    s.products.find(p => p.id === Number(route.params.id))
);

const versions = computed(() =>
    s.getProductVersions(Number(route.params.id))
        .slice()
        .sort((a, b) => b.version - a.version)
);

const olderId = ref(null);
const newerId = ref(null);
const isConfirming = ref(false);
const error = ref('');

watch(
    versions,
    v => {
        if (!v.length) return;

        if (!v.some(h => h.version === newerId.value)) {
            newerId.value = v[0].version;
        }

        if (
            !v.some(h => h.version === olderId.value) ||
            olderId.value === newerId.value
        ) {
            olderId.value =
                v.find(h => h.version !== newerId.value)?.version ??
                v[0].version;
        }
    },
    { immediate: true }
);

const oldVersion = computed(() =>
    versions.value.find(v => v.version === olderId.value)
);

const newVersion = computed(() =>
    versions.value.find(v => v.version === newerId.value)
);

const oldSnap = computed(() =>
    oldVersion.value?.snapshot || {}
);

const newSnap = computed(() =>
    newVersion.value?.snapshot || {}
);

const comparable = [
    {
        key: 'name',
        label: 'Product name'
    },
    {
        key: 'sku',
        label: 'SKU'
    },
    {
        key: 'category',
        label: 'Category'
    },
    {
        key: 'type',
        label: 'Product type'
    },
    {
        key: 'status',
        label: 'Publication'
    },
    {
        key: 'short',
        label: 'Short description'
    },
    {
        key: 'description',
        label: 'Long description'
    },
    {
        key: 'features',
        label: 'Specifications / Features'
    },
    {
        key: 'price',
        label: 'Unit price'
    },
    {
        key: 'image',
        label: 'Illustration'
    }
];

function displayValue(snapshot, key) {
    if (key === 'features') {
        return (snapshot.features || []).join('\n') || 'None specified';
    }

    if (key === 'price') {
        return `RM ${Number(snapshot.price || 0).toFixed(2)}`;
    }

    if (key === 'image') {
        return `${snapshot.emoji || '📦'} · ${snapshot.tone || 'blue'} illustration`;
    }

    return String(snapshot[key] ?? 'Not specified');
}

function changed(key) {
    return (
        displayValue(oldSnap.value, key) !==
        displayValue(newSnap.value, key)
    );
}

const changedCount = computed(() =>
    comparable.filter(f => changed(f.key)).length
);

function restore() {
    try {
        error.value = '';

        const draftVersion = s.restoreProductVersion(
            product.value.id,
            olderId.value
        );

        isConfirming.value = false;

        router.push({
            name: 'admin-product-edit',
            params: {
                id: product.value.id
            },
            query: {
                draftVersion: String(draftVersion)
            }
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
    <div v-if="product" class="version-page">
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

        <p class="version-notice">
            Demo note: the sample earlier history for the Executive Daily
            Planner is illustrative. Subsequent edits create real snapshots
            in this browser.
        </p>

        <div class="version-layout">
            <aside class="panel version-sidebar">
                <h2>Version history</h2>

                <p class="small muted">
                    Choose exactly two versions to compare.
                </p>

                <button
                    v-for="v in versions"
                    :key="v.version"
                    type="button"
                    class="version-tile"
                    :class="{
                        'selected':
                            v.version === olderId ||
                            v.version === newerId
                    }"
                    @click="
                        v.version === newerId
                            ? olderId = v.version
                            : newerId = v.version
                    "
                >
                    <span class="d-flex justify-content-between">
                        <strong>v{{ v.version }}</strong>
                        <span class="small">{{ v.status }}</span>
                    </span>

                    <small>{{ formatted(v.createdAt) }}</small>
                    <small>{{ v.actor }}</small>
                    <small>{{ v.note }}</small>
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
                                <template v-if="field.key === 'image'">
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
                                <template v-if="field.key === 'image'">
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