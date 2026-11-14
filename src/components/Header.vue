<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCurrencyStore } from '@/stores/currency.js';
import {
    ShoppingBag,
    ShoppingCart,
    UserRound,
    Menu,
    X,
    LogOut
} from 'lucide-vue-next';
import { useStore } from '@/stores/shop.js';

const s = useStore(),
    currency = useCurrencyStore(),
    route = useRoute(),
    router = useRouter(),
    open = ref(false);

watch(
    () => route.fullPath,
    () => open.value = false
);

onMounted(() => {
    currency.loadRates();
});

function logout() {
    s.logout();
    router.push('/');
}
</script>

<template>
    <header class="site-header">
        <div class="container header-inner">
            <RouterLink
                to="/"
                class="brand"
            >
                <span class="brand-icon">
                    <ShoppingBag :size="22" />
                </span>

                ShopNest<span class="brand-dot">.</span>
            </RouterLink>

            <nav
                class="desktop-links"
                aria-label="Primary navigation"
            >
                <RouterLink to="/">
                    Home
                </RouterLink>

                <RouterLink to="/catalog">
                    Shop
                </RouterLink>

                <RouterLink
                    v-if="s.user?.role === 'customer'"
                    to="/dashboard"
                >
                    My account
                </RouterLink>

                <RouterLink
                    v-if="s.user?.role === 'admin'"
                    to="/admin"
                >
                    Admin dashboard
                </RouterLink>
            </nav>

            <div class="header-actions">
                <label class="currency-control">
                    <span class="visually-hidden">
                        Display currency
                    </span>

                    <select
                        class="currency-select"
                        :value="currency.selectedCurrency"
                        :title="
                            currency.error ||
                            (
                                currency.rateDate
                                    ? 'Reference rates dated ' + currency.rateDate
                                    : 'Choose display currency'
                            )
                        "
                        aria-label="Choose display currency"
                        @change="currency.setCurrency($event.target.value)"
                    >
                        <option value="MYR">MYR</option>
                        <option value="USD">USD</option>
                        <option value="SGD">SGD</option>
                        <option value="EUR">EUR</option>
                    </select>

                    <span
                        class="visually-hidden"
                        role="status"
                        aria-live="polite"
                    >
                        {{ currency.error }}
                    </span>
                </label>

                <RouterLink
                    to="/cart"
                    class="header-icon"
                    :aria-label="'Cart, ' + s.cartCount + ' items'"
                >
                    <ShoppingCart :size="21" />

                    <span
                        v-if="s.cartCount"
                        class="cart-badge"
                    >
                        {{ s.cartCount }}
                    </span>
                </RouterLink>

                <RouterLink
                    v-if="!s.user"
                    to="/login"
                    class="header-icon account-icon"
                    aria-label="Sign in"
                >
                    <UserRound :size="21" />
                </RouterLink>

                <button
                    v-else
                    class="header-icon account-icon"
                    aria-label="Sign out"
                    title="Sign out"
                    @click="logout"
                >
                    <LogOut :size="21" />
                </button>

                <button
                    class="header-icon mobile-toggle"
                    :aria-expanded="open"
                    aria-controls="mobile-nav"
                    :aria-label="open ? 'Close menu' : 'Open menu'"
                    @click="open = !open"
                >
                    <X v-if="open" :size="23" />
                    <Menu v-else :size="23" />
                </button>
            </div>
        </div>

        <nav
            v-if="open"
            id="mobile-nav"
            class="mobile-links"
            aria-label="Mobile navigation"
        >
            <RouterLink to="/">
                Home
            </RouterLink>

            <RouterLink to="/catalog">
                Shop all
            </RouterLink>

            <RouterLink to="/cart">
                Cart ({{ s.cartCount }})
            </RouterLink>

            <RouterLink
                v-if="s.user?.role === 'customer'"
                to="/dashboard"
            >
                My account
            </RouterLink>

            <RouterLink
                v-if="s.user?.role === 'customer'"
                to="/orders"
            >
                Purchase history
            </RouterLink>

            <RouterLink
                v-if="s.user?.role === 'admin'"
                to="/admin"
            >
                Admin dashboard
            </RouterLink>

            <RouterLink
                v-if="!s.user"
                to="/login"
            >
                Sign in
            </RouterLink>

            <RouterLink
                v-if="!s.user"
                to="/signup"
            >
                Create account
            </RouterLink>
        </nav>
    </header>
</template>

<style scoped>
.currency-control {
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}

.currency-select {
    width: 82px;
    min-height: 40px;
    padding: 5px 7px;
    border: 1px solid var(--line);
    border-radius: 9px;
    background: white;
    color: var(--ink);
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
}

.currency-select:focus-visible {
    outline: 3px solid var(--primary);
    outline-offset: 2px;
}

@media (max-width: 450px) {
    .currency-select {
        width: 70px;
        min-height: 38px;
        padding: 4px;
        font-size: 0.76rem;
    }
}
</style>