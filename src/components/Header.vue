<script>
import { mapStores } from 'pinia';
import { useRoute } from 'vue-router';
import { useCurrencyStore } from '@/stores/currency.js';
import { useStore } from '@/stores/shop.js';
import {
    ShoppingBag,
    ShoppingCart,
    UserRound,
    Menu,
    X,
    LogOut
} from 'lucide-vue-next';
import { api, clearStoreSession } from '@/services/shopApi.js';

export default {
    name: 'SiteHeader',
    components: { ShoppingBag, ShoppingCart, UserRound, Menu, X, LogOut },
    data() {
        return { open: false, logoutError: '', logoutBusy: false };
    },
    computed: {
        ...mapStores(useStore, useCurrencyStore),
        s() { return this.shopStore; },
        currency() { return this.currencyStore; }
    },
    watch: {
        '$route.fullPath'() {
            this.open = false;
        }
    },
    mounted() {
        this.currency.loadRates();
    },
    methods: {
        async logout() {
            if (this.logoutBusy) return;
            this.logoutError = '';
            this.logoutBusy = true;
            try {
                await api.post('/auth/logout', {});
                clearStoreSession(this.s);
                await this.$router.push('/');
            } catch (error) {
                this.logoutError = error.message;
            } finally {
                this.logoutBusy = false;
            }
        }
    }
};
</script>

<template>
    <header class="site-header">
        <v-alert v-if="logoutError" type="error" density="compact" variant="tonal" class="mb-0">
            {{ logoutError }}
        </v-alert>

        <div class="container header-inner">
            <RouterLink to="/" class="brand">
                <span class="brand-icon"><ShoppingBag :size="22" /></span>
                ShopNest<span class="brand-dot">.</span>
            </RouterLink>

            <nav class="desktop-links" aria-label="Primary navigation">
                <RouterLink to="/">Home</RouterLink>
                <RouterLink to="/catalog">Shop</RouterLink>
                <RouterLink v-if="s.user?.role === 'customer'" to="/dashboard">My account</RouterLink>
                <RouterLink v-if="s.user?.role === 'admin'" to="/admin">Admin dashboard</RouterLink>
            </nav>

            <div class="header-actions">
                <v-select
                    :model-value="currency.selectedCurrency"
                    :items="['MYR', 'USD', 'SGD', 'EUR']"
                    aria-label="Choose display currency"
                    density="compact"
                    variant="outlined"
                    hide-details
                    class="currency-select-vuetify"
                    :title="currency.error || (currency.rateDate ? 'Reference rates dated ' + currency.rateDate : 'Choose display currency')"
                    @update:model-value="currency.setCurrency"
                />

                <RouterLink to="/cart" class="header-icon" :aria-label="'Cart, ' + s.cartCount + ' items'">
                    <ShoppingCart :size="21" />
                    <span v-if="s.cartCount" class="cart-badge">{{ s.cartCount }}</span>
                </RouterLink>

                <RouterLink v-if="!s.user" to="/login" class="header-icon account-icon" aria-label="Sign in">
                    <UserRound :size="21" />
                </RouterLink>

                <v-btn v-else icon variant="text" class="header-icon account-icon" aria-label="Sign out" title="Sign out" :loading="logoutBusy" @click="logout">
                    <LogOut :size="21" />
                </v-btn>

                <v-btn class="header-icon mobile-toggle" icon variant="text" :aria-expanded="open" aria-controls="mobile-nav" :aria-label="open ? 'Close menu' : 'Open menu'" @click="open = !open">
                    <X v-if="open" :size="23" />
                    <Menu v-else :size="23" />
                </v-btn>
            </div>
        </div>

        <nav v-if="open" id="mobile-nav" class="mobile-links" aria-label="Mobile navigation">
            <RouterLink to="/">Home</RouterLink>
            <RouterLink to="/catalog">Shop all</RouterLink>
            <RouterLink to="/cart">Cart ({{ s.cartCount }})</RouterLink>
            <RouterLink v-if="s.user?.role === 'customer'" to="/dashboard">My account</RouterLink>
            <RouterLink v-if="s.user?.role === 'customer'" to="/orders">Purchase history</RouterLink>
            <RouterLink v-if="s.user?.role === 'admin'" to="/admin">Admin dashboard</RouterLink>
            <RouterLink v-if="!s.user" to="/login">Sign in</RouterLink>
            <RouterLink v-if="!s.user" to="/signup">Create account</RouterLink>
        </nav>
    </header>
</template>

<style scoped>
.currency-select-vuetify { width: 104px; min-width: 90px; }
@media (max-width: 450px) { .currency-select-vuetify { width: 88px; } }
</style>
