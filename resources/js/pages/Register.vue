<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="mb-3">Ro'yxatdan o'tish</h4>

                    <div class="btn-group w-100 mb-4">
                        <button type="button" class="btn" :class="form.role === 'driver' ? 'btn-primary' : 'btn-outline-primary'"
                                @click="form.role = 'driver'">
                            Men driverman
                        </button>
                        <button type="button" class="btn" :class="form.role === 'carrier' ? 'btn-primary' : 'btn-outline-primary'"
                                @click="form.role = 'carrier'">
                            Men kompaniyaman
                        </button>
                    </div>

                    <AlertBox :message="error" :errors="errors" />

                    <form @submit.prevent="submit">
                        <div v-if="form.role === 'carrier'" class="mb-3">
                            <label class="form-label">Kompaniya nomi</label>
                            <input v-model="form.company_name" type="text" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ form.role === 'carrier' ? 'Kontakt shaxs' : 'Ism familiya' }}</label>
                            <input v-model="form.name" type="text" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Telefon <span class="text-muted small">(ixtiyoriy, SMS tasdiq uchun)</span></label>
                            <input v-model="form.phone" type="tel" class="form-control" placeholder="+1 555 123 4567">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Parol</label>
                                <input v-model="form.password" type="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Parolni takrorlang</label>
                                <input v-model="form.password_confirmation" type="password" class="form-control" required>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100" :disabled="loading">
                            {{ loading ? 'Yaratilmoqda…' : 'Ro\'yxatdan o\'tish' }}
                        </button>
                    </form>

                    <div class="text-center mt-3 small">
                        Akkauntingiz bormi? <router-link :to="{ name: 'login' }">Kirish</router-link>
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
    name: 'RegisterPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            form: {
                role: this.$route.query.role === 'carrier' ? 'carrier' : 'driver',
                name: '',
                company_name: '',
                email: '',
                phone: '',
                password: '',
                password_confirmation: '',
            },
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
                await this.auth.register(this.form);
                // Ro'yxatdan o'tgach darrov tasdiqlash sahifasiga.
                this.$router.push({ name: 'verify' });
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
