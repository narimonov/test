<template>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="mb-1">Akkauntni tasdiqlash</h4>
                    <p class="text-muted small mb-4">
                        Telefon yoki email — bittasini tasdiqlasangiz yetarli.
                    </p>

                    <div v-if="auth.isVerified" class="alert alert-success">
                        Akkauntingiz tasdiqlangan.
                        <router-link :to="auth.homeRoute" class="alert-link">Davom etish</router-link>
                    </div>

                    <template v-else>
                        <AlertBox :message="error" :errors="errors" />
                        <AlertBox :message="notice" variant="success" />

                        <div class="btn-group w-100 mb-3">
                            <button type="button" class="btn" :class="channel === 'phone' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="channel = 'phone'">Telefon (SMS)</button>
                            <button type="button" class="btn" :class="channel === 'email' ? 'btn-primary' : 'btn-outline-primary'"
                                    @click="channel = 'email'">Email</button>
                        </div>

                        <div v-if="channel === 'phone'" class="mb-3">
                            <label class="form-label">Telefon raqam</label>
                            <input v-model="phone" type="tel" class="form-control" placeholder="+1 555 123 4567">
                        </div>
                        <div v-else class="mb-3">
                            <label class="form-label">Email</label>
                            <input :value="auth.user?.email" type="email" class="form-control" disabled>
                        </div>

                        <button class="btn btn-outline-secondary w-100 mb-4" :disabled="sending" @click="sendCode">
                            {{ sending ? 'Yuborilmoqda…' : 'Kod yuborish' }}
                        </button>

                        <form v-if="codeSent" @submit.prevent="verify">
                            <label class="form-label">Kelgan 6 xonali kod</label>
                            <input v-model="code" type="text" inputmode="numeric" maxlength="6"
                                   class="form-control form-control-lg text-center mb-3" placeholder="______">
                            <button class="btn btn-primary w-100" :disabled="verifying">
                                {{ verifying ? 'Tekshirilmoqda…' : 'Tasdiqlash' }}
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
                    // SMS/email gateway ulanmaguncha kod ekranda ko'rsatiladi.
                    ? `Kod yuborildi. Test rejimi — kod: ${data.verification.debug_code}`
                    : `Kod ${data.verification.sent_to} manziliga yuborildi.`;
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
                this.notice = 'Tasdiqlandi.';
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
