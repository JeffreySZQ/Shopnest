<script>
import { mapStores } from 'pinia';
import { useStore } from '@/stores/shop.js';
import { UserPlus } from 'lucide-vue-next';
import {
    api,
    setStoreUser,
    mergeGuestCart,
    refreshCustomerData
} from '@/services/shopApi.js';

export default {
    name: 'SignupView',
    components: { UserPlus },

    data() {
        return {
            name: '',
            email: '',
            password: '',
            confirm: '',
            error: '',
            submitting: false,
            showPassword: false,
            showConfirm: false
        };
    },

    computed: {
        ...mapStores(useStore),

        s() {
            return this.shopStore;
        },

        // Only requirements that are not yet satisfied are displayed.
        passwordRequirementErrors() {
            const value = String(this.password || '');

            if (!value) return [];

            const errors = [];

            if (value.length < 8) {
                errors.push('Use at least 8 characters.');
            }
            if (!/[A-Z]/.test(value)) {
                errors.push('Add at least one uppercase letter (A-Z).');
            }
            if (!/[a-z]/.test(value)) {
                errors.push('Add at least one lowercase letter (a-z).');
            }
            if (!/[0-9]/.test(value)) {
                errors.push('Add at least one number (0-9).');
            }

            return errors;
        },

        passwordIsValid() {
            const value = String(this.password || '');

            return (
                value.length >= 8 &&
                /[A-Z]/.test(value) &&
                /[a-z]/.test(value) &&
                /[0-9]/.test(value)
            );
        },

        rules() {
            return {
                required: value =>
                    !!String(value || '').trim() || 'This field is required.',

                name: value =>
                    String(value || '').trim().length >= 2 ||
                    'Name must contain at least 2 characters.',

                nameLength: value =>
                    String(value || '').length <= 100 ||
                    'Name must be 100 characters or fewer.',

                email: value =>
                    /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim()) ||
                    'Enter a valid email address.',

                passwordMatch: value =>
                    value === this.password || 'Passwords do not match.'
            };
        }
    },

    // automatically triggers validation
    watch: {
        password() {
            this.$nextTick(() => {
                if (this.$refs.confirmField && this.confirm) {
                    this.$refs.confirmField.validate();
                }
            });
        }
    },

    methods: {
        async submit() {
            this.error = '';

            const validation = await this.$refs.signupForm.validate();

            if (!validation.valid || !this.passwordIsValid) {
                this.error = 'Please complete the required fields and meet all password requirements.';
                return;
            }

            this.submitting = true;

            // If valid, sends a POST request to the backend.
            try {
                const result = await api.post('/auth/register', {
                    name: this.name.trim(),
                    email: this.email.trim().toLowerCase(),
                    password: this.password
                });

                setStoreUser(this.s, result.user);

                // Preserve and merge the guest cart after registration.
                try {
                    await mergeGuestCart(this.s);
                } catch (mergeError) {
                    this.s.guestCartError = mergeError.message;
                }

                await refreshCustomerData(this.s);
                await this.$router.push('/dashboard');

            } catch (error) {
                this.error = error.message || 'Your account could not be created. Please try again.';

            } finally {
                this.submitting = false;
            }
        }
    }
};
</script>

<template>
    <div class="auth-wrap">
        <v-card class="auth-card auth-card--signup" variant="outlined" elevation="0">
            <v-card-text class="auth-card-content">
                <div class="auth-icon" aria-hidden="true">
                    <UserPlus :size="25" />
                </div>

                <p class="eyebrow">START YOUR JOURNEY</p>
                <h1>Create your account</h1>
                <p class="muted auth-intro">
                    Save favorites and keep your orders in one place.
                </p>

                <v-form
                    ref="signupForm"
                    class="auth-form"
                    validate-on="input"
                    @submit.prevent="submit"
                >
                    <div class="sn-field-group">
                        <label for="signup-name" class="sn-field-label">
                            Full name <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="signup-name"
                            v-model="name"
                            placeholder="Enter your full name"
                            autocomplete="name"
                            variant="outlined"
                            density="comfortable"
                            maxlength="100"
                            hide-details="auto"
                            :rules="[rules.required, rules.name, rules.nameLength]"
                            required
                        />
                    </div>

                    <div class="sn-field-group">
                        <label for="signup-email" class="sn-field-label">
                            Email address <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="signup-email"
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
                        <label for="signup-password" class="sn-field-label">
                            Password <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="signup-password"
                            v-model="password"
                            :type="showPassword ? 'text' : 'password'"
                            autocomplete="new-password"
                            placeholder="Create a password"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :error="password.length > 0 && passwordRequirementErrors.length > 0"
                            aria-describedby="password-requirements"
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

                        <div
                            id="password-requirements"
                            aria-live="polite"
                            aria-atomic="true"
                        >
                            <div
                                v-if="passwordRequirementErrors.length"
                                class="sn-password-errors"
                                role="status"
                            >
                                <p
                                    v-for="message in passwordRequirementErrors"
                                    :key="message"
                                >
                                    {{ message }}
                                </p>
                            </div>

                            <p
                                v-else-if="passwordIsValid"
                                class="sn-password-success"
                                role="status"
                            >
                                Password meets all requirements.
                            </p>
                        </div>
                    </div>

                    <div class="sn-field-group">
                        <label for="signup-confirm-password" class="sn-field-label">
                            Confirm password <span class="required">*</span>
                        </label>
                        <v-text-field
                            id="signup-confirm-password"
                            ref="confirmField"
                            v-model="confirm"
                            :type="showConfirm ? 'text' : 'password'"
                            autocomplete="new-password"
                            placeholder="Enter your password again"
                            variant="outlined"
                            density="comfortable"
                            hide-details="auto"
                            :rules="[rules.required, rules.passwordMatch]"
                            required
                        >
                            <template #append-inner>
                                <v-btn
                                    type="button"
                                    icon
                                    size="small"
                                    variant="text"
                                    :aria-label="showConfirm ? 'Hide confirmation password' : 'Show confirmation password'"
                                    :aria-pressed="showConfirm"
                                    @click="showConfirm = !showConfirm"
                                >
                                    <v-icon>
                                        {{ showConfirm ? 'mdi-eye-off' : 'mdi-eye' }}
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
                        Create account
                    </v-btn>
                </v-form>

                <p class="text-center auth-switch mt-4 mb-0">
                    Already have an account?
                    <RouterLink to="/login">Sign in</RouterLink>
                </p>
            </v-card-text>
        </v-card>
    </div>
</template>
