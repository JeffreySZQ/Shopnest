<<<<<<< HEAD
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
=======
<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { LogIn } from 'lucide-vue-next';
import {
    api,
    setStoreUser,
    mergeGuestCart,
    refreshCustomerData,
    refreshAdminData
} from '@/services/shopApi.js';

export default {
    name: 'LoginView',
    components: { LogIn },

    data() {
        return {
            email: '',
            password: '',
            error: '',
            showPassword: false,
            submitting: false
        };
    },

    computed: {
        ...mapStores(useStore),

        s() {
            return this.shopStore;
        },

        rules() {
            return {
                required: value =>
                    !!String(value || '').trim() || 'This field is required.',

                email: value =>
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim()) ||
                    'Enter a valid email address.'
            };
        }
    },

    methods: {
        async login() {
            this.error = '';

            const validation = await this.$refs.loginForm.validate();

            if (!validation.valid) {
                this.error = 'Please correct the highlighted fields.';
                return;
            }

            this.submitting = true;

            try {
                const result = await api.post('/auth/login', {
                    email: this.email.trim(),
                    password: this.password
                });

                setStoreUser(this.s, result.user);

                if (result.user.role === 'customer') {
                    try {
                        await mergeGuestCart(this.s);
                    } catch (mergeError) {
                        this.s.guestCartError = mergeError.message;
                    }

                    await refreshCustomerData(this.s);

                } else {
                    await refreshAdminData(this.s);
                }

                const destination = String(this.$route.query.redirect || '');
                const safeDestination =
                    destination.startsWith('/') && !destination.startsWith('//');

                await this.$router.push(
                    safeDestination
                        ? destination
                        : (result.user.role === 'admin' ? '/admin' : '/dashboard')
                );

            } catch (error) {
                this.error = error.message;

            } finally {
                this.submitting = false;
            }
        }
    }
};
>>>>>>> origin/main
</script>

<template>
    <div class="auth-wrap">
<<<<<<< HEAD
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
=======
        <v-card class="auth-card auth-card--login" variant="outlined" elevation="0">
            <v-card-text class="auth-card-content">
                <div class="auth-icon" aria-hidden="true">
                    <LogIn :size="25" />
                </div>

                <p class="eyebrow">WELCOME BACK</p>
                <h1>Sign in to ShopNest</h1>
                <p class="muted auth-intro">
                    Your thoughtful shopping experience continues here.
                </p>

                <v-form
                    ref="loginForm"
                    class="auth-form"
                    validate-on="input"
                    @submit.prevent="login"
                >
                    <div class="sn-field-group">
                        <label for="login-email" class="sn-field-label">
                            Email address <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="login-email"
                            v-model.trim="email"
                            type="email"
                            autocomplete="email"
                            inputmode="email"
                            placeholder="you@example.com"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :rules="[rules.required, rules.email]"
                            required
                        />
                    </div>

                    <div class="sn-field-group">
                        <label for="login-password" class="sn-field-label">
                            Password <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="login-password"
                            v-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :rules="[rules.required]"
                            required
                        >
                        <template #append-inner>
                            <v-btn
                                type="button"
                                icon
                                size="small"
                                variant="text"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                :aria-pressed="showPassword"
                                @click="showPassword = !showPassword"
                            >
                                <v-icon>
                                    {{ showPassword ? 'mdi-eye-off' : 'mdi-eye' }}
                                </v-icon>
                            </v-btn>
                        </template>
                        </v-text-field>
                    </div>

                    <v-alert
                        v-if="error"
                        type="error"
                        variant="tonal"
                        class="mt-2"
                        role="alert"
                        aria-live="assertive"
                    >
                        {{ error }}
                    </v-alert>

                    <v-btn
                        type="submit"
                        color="primary"
                        size="large"
                        block
                        class="auth-submit mt-3"
                        :loading="submitting"
                        :disabled="submitting"
                    >
                        Sign in
                    </v-btn>
                </v-form>

                <p class="text-center auth-switch mt-4 mb-0">
                    New here?
                    <RouterLink to="/signup">Create an account</RouterLink>
                </p>
            </v-card-text>
        </v-card>
    </div>
</template>
>>>>>>> origin/main
