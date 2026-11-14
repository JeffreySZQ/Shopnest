<script setup>
import { ref } from 'vue';
import { useStore } from '@/stores/shop.js';
import { useRouter } from 'vue-router';
import {
    LayoutDashboard,
    Package,
    ClipboardList,
    History,
    ArrowLeft,
    Menu,
    X,
    LogOut
} from 'lucide-vue-next';
import { api, clearStoreSession } from '@/services/shopApi.js';

const open = ref(false);
const s = useStore();
const router = useRouter();

async function logout() {
    try {
        await api.post('/auth/logout', {});
        clearStoreSession(s);
        await router.push('/login');
    } catch (e) {
        alert(e.message);
    }
}
</script>

<template>
    <div class="admin-shell">
        <a
            class="skip-link"
            href="#admin-main"
        >
            Skip to main content
        </a>

        <aside
            class="admin-sidebar"
            :class="{ expanded: open }"
        >
            <div class="admin-brand">
                <RouterLink
                    to="/admin"
                    @click="open = false"
                >
                    ◈ ShopNest
                    <small>ADMIN</small>
                </RouterLink>
            </div>

            <nav
                aria-label="Admin navigation"
                class="admin-links"
            >
                <RouterLink
                    to="/admin"
                    exact-active-class="active"
                    @click="open = false"
                >
                    <LayoutDashboard :size="19" />
                    Overview
                </RouterLink>

                <RouterLink
                    to="/admin/products"
                    @click="open = false"
                >
                    <Package :size="19" />
                    Products
                </RouterLink>

                <RouterLink
                    to="/admin/orders"
                    @click="open = false"
                >
                    <ClipboardList :size="19" />
                    Orders
                </RouterLink>

                <RouterLink
                    to="/admin/activity"
                    @click="open = false"
                >
                    <History :size="19" />
                    Activity Log
                </RouterLink>
            </nav>

            <div class="admin-side-bottom">
                <RouterLink
                    to="/"
                    @click="open = false"
                >
                    <ArrowLeft :size="17" />
                    View store
                </RouterLink>

                <button @click="logout">
                    <LogOut :size="17" />
                    Sign out
                </button>
            </div>
        </aside>

        <div
            v-if="open"
            class="drawer-backdrop"
            @click="open = false"
        ></div>

        <div class="admin-workspace">
            <header class="admin-topbar">
                <button
                    class="icon-btn admin-mobile"
                    @click="open = !open"
                    :aria-label="open ? 'Close menu' : 'Open menu'"
                    :aria-expanded="open"
                >
                    <X v-if="open" />
                    <Menu v-else />
                </button>

                <span class="admin-top-title">
                    Store management
                </span>

                <div class="small muted">
                    {{ s.user?.name }}

                    <span class="avatar">
                        {{ s.user?.name?.charAt(0) }}
                    </span>
                </div>
            </header>

            <main
                id="admin-main"
                class="admin-content"
            >
                <RouterView />
            </main>
        </div>
    </div>
</template>