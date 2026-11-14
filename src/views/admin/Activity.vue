<script setup>
import { computed, ref, onMounted } from 'vue';
import { useStore } from '@/stores/shop.js';
import { History, Search } from 'lucide-vue-next';
import { api } from '@/services/shopApi.js';

const s = useStore();
const q = ref('');
const resource = ref('');
const page = ref(1);
const size = 10;
const error = ref('');

onMounted(async () => {
    try {
        const result = await api.get('/admin/activity');
        s.logs = result.logs;
    } catch (e) {
        error.value = e.message;
    }
});

const filtered = computed(() =>
    s.logs.filter(log =>
        (!resource.value || log.resource === resource.value) &&
        (!q.value || (log.actor + ' ' + log.action + ' ' + log.resource + ' ' + log.detail).toLowerCase().includes(q.value.toLowerCase()))
    )
);

const pages = computed(() => Math.max(1, Math.ceil(filtered.value.length / size)));
const shown = computed(() => filtered.value.slice((page.value - 1) * size, page.value * size));
const resources = ['Products', 'Orders', 'Account', 'System'];
</script>

<template>
    <div>
        <div class="admin-page-top">
            <div>
                <p class="eyebrow">AUDIT TRAIL</p>

                <h1>Activity log</h1>

                <p class="muted">
                    A read-only view of actions recorded in this browser demo.
                </p>
            </div>

            <History :size="28" />
        </div>

        <div class="panel">
            <p v-if="error" class="text-danger" role="alert">{{ error }}</p>
            <div class="demo-panel mb-4">
                These records are retrieved from the ShopNest database and show actions recorded by the PHP backend.
                Real auditing requires append-only server records and role-based permissions.
            </div>

            <div class="toolbar">
                <label class="search-box">
                    <Search :size="18" />

                    <span class="visually-hidden">
                        Search activity
                    </span>

                    <input
                        v-model="q"
                        @input="page = 1"
                        placeholder="Search actor, action, record"
                    />
                </label>

                <label>
                    <span class="visually-hidden">
                        Filter resource
                    </span>

                    <select
                        v-model="resource"
                        class="form-select"
                        @change="page = 1"
                    >
                        <option value="">All resources</option>

                        <option
                            v-for="r in resources"
                            :key="r"
                        >
                            {{ r }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Resource</th>
                            <th>Details</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="log in shown"
                            :key="log.id"
                        >
                            <td class="small">
                                {{ new Date(log.time).toLocaleString('en-MY') }}
                            </td>

                            <td>
                                {{ log.actor }}
                            </td>

                            <td>
                                <strong>{{ log.action }}</strong>
                            </td>

                            <td>
                                {{ log.resource }}
                            </td>

                            <td>
                                {{ log.detail }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="!shown.length"
                class="empty-inline"
            >
                No activity matches your filters.
            </div>

            <div class="page-controls">
                <span>
                    {{ filtered.length }} events
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