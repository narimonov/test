<template>
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="page-title mb-3">Sign in</h4>

                    <AlertBox :message="error" :errors="errors" />

                    <form @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input v-model="form.password" type="password" class="form-control" required>
                        </div>
                        <button class="btn btn-primary w-100" :disabled="loading">
                            {{ loading ? 'Signing in…' : 'Sign in' }}
                        </button>
                    </form>

                    <div class="text-center mt-3 small">
                        No account yet?
                        <router-link :to="{ name: 'register' }">Create one</router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import AlertBox from '../components/AlertBox.vue';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'LoginPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            form: { email: '', password: '' },
            loading: false,
            error: null,
            errors: {},
        };
    },

    methods: {
        async submit() {
            this.loading = true;
            this.error = null;
            this.errors = {};

            try {
                await this.auth.login(this.form);
                this.$router.push(this.$route.query.redirect || this.auth.homeRoute);
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
