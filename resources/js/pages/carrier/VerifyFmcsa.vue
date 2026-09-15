<template>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body p-4">
                    <h4 class="page-title mb-1">Verify your company</h4>
                    <p class="text-muted small mb-4">
                        The code goes to the official contact FMCSA holds for this carrier, so only
                        someone who already controls that contact can open the account.
                    </p>

                    <AlertBox :message="error" :errors="errors" />
                    <AlertBox :message="notice" variant="success" />

                    <div v-if="loading" class="empty-state">Loading…</div>

                    <template v-else-if="verified">
                        <div class="alert alert-success mb-3">
                            Your company is verified.
                        </div>
                        <router-link :to="{ name: 'carrier.dashboard' }" class="btn btn-primary">
                            Go to the dashboard
                        </router-link>
                    </template>

                    <template v-else>
                        <div v-if="company" class="border rounded p-3 mb-4 bg-light">
                            <div class="label-mono mb-2">FMCSA record</div>
                            <dl class="row mb-0 small">
                                <dt class="col-5 fw-normal text-muted">Legal name</dt>
                                <dd class="col-7 fw-semibold">{{ company.legal_name || '—' }}</dd>
                                <dt class="col-5 fw-normal text-muted">USDOT</dt>
                                <dd class="col-7">{{ company.dot_number || '—' }}</dd>
                                <dt class="col-5 fw-normal text-muted">Status</dt>
                                <dd class="col-7">
                                    <span class="badge" :class="company.allowed_to_operate ? 'bg-success' : 'bg-danger'">
                                        {{ company.allowed_to_operate ? 'Allowed to operate' : 'Not allowed to operate' }}
                                    </span>
                                </dd>
                            </dl>
                        </div>

                        <div v-if="!channels.length" class="alert alert-warning">
                            FMCSA has no contact on file for this carrier. Contact support for a
                            manual check.
                        </div>

                        <template v-else>
                            <label class="form-label">Where should the code go?</label>
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
                    // Until an SMS/email gateway is connected the code is shown here.
                    ? `Code sent to ${data.verification.sent_to}. Test mode — code: ${data.verification.debug_code}`
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
                await api.post('/auth/carrier/verify-code', { code: this.code });
                await this.auth.refresh();
                this.verified = true;
                this.notice = 'Company verified.';
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
