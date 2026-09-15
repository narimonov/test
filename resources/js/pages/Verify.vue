<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="page-title mb-1">Verify your account</h4>
                    <p class="text-muted small mb-4">
                        Confirm either your phone or your email — one is enough.
                    </p>

                    <div v-if="auth.isVerified" class="alert alert-success">
                        Your account is verified.
                        <router-link :to="auth.homeRoute" class="alert-link">Continue</router-link>
                    </div>

                    <template v-else>
                        <AlertBox :message="error" :errors="errors" />
                        <AlertBox :message="notice" variant="success" />

                        <div class="btn-group w-100 mb-3">
                            <button type="button" class="btn" :class="channel === 'phone' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="channel = 'phone'">Phone (SMS)</button>
                            <button type="button" class="btn" :class="channel === 'email' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="channel = 'email'">Email</button>
                        </div>

                        <div v-if="channel === 'phone'" class="mb-3">
                            <label class="form-label">Phone number</label>
                            <input v-model="phone" type="tel" class="form-control" placeholder="+1 555 123 4567">
                        </div>
                        <div v-else class="mb-3">
                            <label class="form-label">Email</label>
                            <input :value="auth.user?.email" type="email" class="form-control" disabled>
                        </div>

                        <button class="btn btn-outline-secondary w-100 mb-4" :disabled="sending" @click="sendCode">
                            {{ sending ? 'Sending…' : 'Send code' }}
                        </button>

                        <form v-if="codeSent" @submit.prevent="verify">
                            <label class="form-label">6-digit code</label>
                            <input v-model="code" type="text" inputmode="numeric" maxlength="6"
                                   class="form-control form-control-lg text-center mb-3" placeholder="______">
                            <button class="btn btn-primary w-100" :disabled="verifying">
                                {{ verifying ? 'Checking…' : 'Verify' }}
                            </button>
                        </form>
                    </template>
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
    name: 'VerifyPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            channel: 'email',
            phone: '',
            code: '',
            codeSent: false,
            sending: false,
            verifying: false,
            error: null,
            notice: null,
            errors: {},
        };
    },

    created() {
        this.phone = this.auth.user?.phone || '';
        this.channel = this.phone ? 'phone' : 'email';
    },

    methods: {
        async sendCode() {
            this.sending = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            try {
                const { data } = await api.post('/auth/send-code', {
                    channel: this.channel,
                    phone: this.channel === 'phone' ? this.phone : undefined,
                });

                this.codeSent = true;
                this.notice = data.verification.debug_code
                    // Until an SMS/email gateway is connected the code is shown here.
                    ? `Code sent. Test mode — code: ${data.verification.debug_code}`
                    : `Code sent to ${data.verification.sent_to}.`;
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.sending = false;
            }
        },

        async verify() {
            this.verifying = true;
            this.error = null;
            this.errors = {};

            try {
                await api.post('/auth/verify-code', { code: this.code });
                await this.auth.refresh();
                this.notice = 'Verified.';
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.verifying = false;
            }
        },
    },
};
</script>
