<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="page-title mb-3">Create an account</h4>

                    <div class="btn-group w-100 mb-4">
                        <button type="button" class="btn" :class="form.role === 'driver' ? 'btn-primary' : 'btn-outline-primary'"
                                @click="form.role = 'driver'">
                            I'm a driver
                        </button>
                        <button type="button" class="btn" :class="form.role === 'carrier' ? 'btn-primary' : 'btn-outline-primary'"
                                @click="form.role = 'carrier'">
                            I'm a carrier
                        </button>
                    </div>

                    <AlertBox :message="error" :errors="errors" />

                    <form @submit.prevent="submit">
                        <div v-if="form.role === 'carrier'" class="border rounded p-3 mb-3 bg-light">
                            <div class="fw-semibold mb-1">Your FMCSA number</div>
                            <p class="text-muted small mb-3">
                                We check the company against the FMCSA register. The confirmation code goes to
                                the phone or email <strong>FMCSA holds for that carrier</strong>, which is why we fill
                                in the company name for you.
                            </p>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small mb-1">USDOT number</label>
                                    <input v-model="form.dot_number" type="text" class="form-control" placeholder="1234567">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-1">MC number</label>
                                    <input v-model="form.mc_number" type="text" class="form-control" placeholder="998877">
                                </div>
                            </div>
                            <div class="form-text">Either one is enough.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ form.role === 'carrier' ? 'Contact person' : 'Full name' }}</label>
                            <input v-model="form.name" type="text" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Phone <span class="text-muted small">(optional, for SMS verification)</span></label>
                            <input v-model="form.phone" type="tel" class="form-control" placeholder="+1 555 123 4567">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Password</label>
                                <input v-model="form.password" type="password" class="form-control" minlength="8" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Repeat password</label>
                                <input v-model="form.password_confirmation" type="password" class="form-control" required>
                            </div>
                        </div>

                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="form-check mb-2">
                                <input id="privacy" v-model="form.privacy_accepted" type="checkbox"
                                       class="form-check-input" required>
                                <label class="form-check-label small" for="privacy">
                                    I have read and accept the
                                    <router-link :to="{ name: 'privacy' }" target="_blank">Privacy Notice</router-link>.
                                </label>
                            </div>
                            <div class="form-check">
                                <input id="sms" v-model="form.sms_consent" type="checkbox" class="form-check-input">
                                <label class="form-check-label small" for="sms">
                                    Text me at this number, including verification codes. Message and data rates
                                    may apply; reply STOP to opt out.
                                    <span class="d-block text-muted">Required by the TCPA before we can send SMS.</span>
                                </label>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100" :disabled="loading || !form.privacy_accepted">
                            {{ loading ? 'Creating…' : 'Create account' }}
                        </button>
                    </form>

                    <div class="text-center mt-3 small">
                        Already have an account? <router-link :to="{ name: 'login' }">Sign in</router-link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../api';
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
                privacy_accepted: false,
                sms_consent: false,
                privacy_version: '',
            },
            loading: false,
            error: null,
            errors: {},
        };
    },

    created() {
        this.loadPrivacyVersion();
    },

    methods: {
        /** Consent is recorded against the exact version the user was shown. */
        async loadPrivacyVersion() {
            try {
                const { data } = await api.get('/privacy');
                this.form.privacy_version = data.version;
            } catch (e) {
                this.error = 'Could not load the privacy notice. Please reload the page.';
            }
        },

        async submit() {
            this.loading = true;
            this.error = null;
            this.errors = {};

            try {
                const data = await this.auth.register(this.form);

                // Carriers verify with the FMCSA code; drivers with their own phone or email.
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
