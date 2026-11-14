<script setup>
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useStore } from '@/stores/shop.js';
import { LogIn } from 'lucide-vue-next';
import { api, setStoreUser, refreshCustomerData, refreshAdminData } from '@/services/shopApi.js';

const s = useStore();
const router = useRouter();
const route = useRoute();
const email = ref('');
const password = ref('');
const error = ref('');
const show = ref(false);
const submitting = ref(false);

async function login() {
    error.value = '';
    submitting.value = true;
    try {
        const result = await api.post('/auth/login', {
            email: email.value.trim(),
            password: password.value
        });
        setStoreUser(s, result.user);
        if (result.user.role === 'admin') {
            await refreshAdminData(s);
        } else {
            await refreshCustomerData(s);
        }
        const dest = String(route.query.redirect || '');
        router.push(
            dest && dest.startsWith('/') && !dest.startsWith('//')
                ? dest
                : (result.user.role === 'admin' ? '/admin' : '/dashboard')
        );
    } catch (e) {
        error.value = e.message;
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-icon">
                <LogIn :size="25" />
            </div>

            <p class="eyebrow">WELCOME BACK</p>

            <h1>Sign in to ShopNest</h1>

            <p class="muted">
                Your thoughtful shopping experience continues here.
            </p>

            <form @submit.prevent="login" class="mt-4">
                <label class="form-label" for="login-email">
                    Email address
                </label>

                <input
                    id="login-email"
                    v-model.trim="email"
                    class="form-control"
                    type="email"
                    autocomplete="email"
                    required
                    placeholder="you@example.com"
                />

                <label class="form-label mt-3" for="login-password">
                    Password
                </label>

                <div class="password-row">
                    <input
                        id="login-password"
                        v-model="password"
                        class="form-control"
                        :type="show ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        minlength="8"
                    />

                    <button
                        type="button"
                        class="btn btn-link"
                        @click="show = !show"
                    >
                        {{ show ? 'Hide' : 'Show' }}
                    </button>
                </div>

                <p
                    v-if="error"
                    class="field-error mt-2"
                    role="alert"
                >
                    {{ error }}
                </p>

                <button
                    class="btn btn-primary btn-lg w-100 mt-4"
                    type="submit"
                    :disabled="submitting"
                >
                    {{ submitting ? 'Signing in...' : 'Sign in' }}
                </button>
            </form>

            <p class="text-center mt-3">
                New here?
                <RouterLink to="/signup">
                    Create an account
                </RouterLink>
            </p>

        </div>
    </div>
</template>