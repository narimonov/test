<template>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="mb-1">Kompaniyani tasdiqlash</h4>
                    <p class="text-muted small mb-4">
                        Kod FMCSA bazasida ro'yxatdan o'tgan rasmiy kontaktga yuboriladi.
                        Shuning uchun faqat kompaniya egasi akkauntni ocha oladi.
                    </p>

                    <AlertBox :message="error" :errors="errors" />
                    <AlertBox :message="notice" variant="success" />

                    <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

                    <template v-else-if="verified">
                        <div class="alert alert-success mb-3">
                            Kompaniyangiz tasdiqlandi.
                        </div>
                        <router-link :to="{ name: 'carrier.dashboard' }" class="btn btn-primary">
                            Dashboard'ga o'tish
                        </router-link>
                    </template>

                    <template v-else>
                        <div v-if="company" class="border rounded p-3 mb-4 bg-light">
                            <div class="text-muted small text-uppercase fw-bold mb-2">FMCSA ma'lumoti</div>
                            <dl class="row mb-0 small">
                                <dt class="col-5 fw-normal text-muted">Rasmiy nom</dt>
                                <dd class="col-7 fw-semibold">{{ company.legal_name || '—' }}</dd>
                                <dt class="col-5 fw-normal text-muted">USDOT</dt>
                                <dd class="col-7">{{ company.dot_number || '—' }}</dd>
                                <dt class="col-5 fw-normal text-muted">Holat</dt>
                                <dd class="col-7">
                                    <span class="badge" :class="company.allowed_to_operate ? 'bg-success' : 'bg-danger'">
                                        {{ company.allowed_to_operate ? 'Faol (allowed to operate)' : 'Faol emas' }}
                                    </span>
                                </dd>
                            </dl>
                        </div>

                        <div v-if="!channels.length" class="alert alert-warning">
                            FMCSA bazasida bu kompaniya uchun kontakt topilmadi.
                            Qo'lda tekshiruv uchun support bilan bog'laning.
                        </div>

                        <template v-else>
                            <label class="form-label">Kod qayerga yuborilsin?</label>
                            <div class="list-group mb-3">
                                <label v-for="item in channels" :key="item.channel"
                                       class="list-group-item d-flex align-items-center gap-2">
                                    <input v-model="channel" :value="item.channel" type="radio" class="form-check-input m-0">
                                    <span>
                                        {{ item.channel === 'phone' ? 'SMS' : 'Email' }}
                                        <span class="text-muted">— {{ item.masked }}</span>
                                    </span>
                                </label>
                            </div>

                            <button class="btn btn-outline-secondary w-100 mb-4" :disabled="sending || !channel"
                                    @click="sendCode">
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
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { useAuthStore } from '../../stores/auth';

export default {
    name: 'CarrierVerifyFmcsaPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            channels: [],
            company: null,
            channel: null,
            code: '',
            codeSent: false,
            verified: false,
            loading: true,
            sending: false,
            verifying: false,
            error: null,
            notice: null,
            errors: {},
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/auth/carrier/channels');
                this.channels = data.channels;
                this.company = data.company;
                this.verified = data.is_verified;
                this.channel = this.channels[0]?.channel || null;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async sendCode() {
            this.sending = true;
            this.error = null;
            this.notice = null;

            try {
                const { data } = await api.post('/auth/carrier/send-code', { channel: this.channel });

                this.codeSent = true;
                this.notice = data.verification.debug_code
                    // SMS/email gateway ulanmaguncha kod ekranda ko'rsatiladi.
                    ? `Kod ${data.verification.sent_to} manziliga yuborildi. Test rejimi — kod: ${data.verification.debug_code}`
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
                await api.post('/auth/carrier/verify-code', { code: this.code });
                await this.auth.refresh();
                this.verified = true;
                this.notice = 'Kompaniya tasdiqlandi.';
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
