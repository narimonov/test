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
                        <div v-if="form.role === 'carrier'" class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold mb-1">FMCSA raqamingiz</div>
                            <p class="text-muted small mb-3">
                                Kompaniya FMCSA bazasidan tekshiriladi. Tasdiqlash kodi
                                <strong>FMCSA'da ro'yxatdan o'tgan</strong> telefon yoki emailga yuboriladi —
                                shuning uchun kompaniya nomini o'zimiz to'ldiramiz.
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">USDOT raqam</label>
                                    <input v-model="form.dot_number" type="text" class="form-control" placeholder="1234567">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">MC raqam</label>
                                    <input v-model="form.mc_number" type="text" class="form-control" placeholder="998877">
                                </div>
                            </div>
                            <div class="form-text">Ikkitasidan bittasi yetarli.</div>
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
                dot_number: '',
                mc_number: '',
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
                const data = await this.auth.register(this.form);

                // Kompaniya FMCSA kodi bilan, driver esa o'z telefoni/emaili bilan tasdiqlanadi.
                this.$router.push({
                    name: this.form.role === 'carrier' ? 'carrier.verify' : 'verify',
                    state: { fmcsa: data.fmcsa },
                });
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
