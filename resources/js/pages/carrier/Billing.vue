<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Billing</h4>
            <p class="page-lede">Your plan decides how much of the driver market you can reach.</p>
        </div>

        <AlertBox :message="error" />
        <AlertBox :message="notice" variant="success" />

        <div v-if="!auth.isVerified" class="alert alert-warning">
            Verify your account before subscribing.
            <router-link :to="{ name: 'carrier.verify' }" class="alert-link">Verify now</router-link>
        </div>

        <div v-if="loading" class="empty-state">Loading…</div>

        <template v-else>
            <div v-if="current" class="card mb-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="label-mono">Current plan</div>
                        <div class="fs-5 fw-bold">
                            {{ current.name }}
                            <span class="badge ms-1" :class="current.active ? 'bg-success' : 'bg-secondary'">
                                {{ current.active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="text-muted small">
                            {{ current.active_jobs }} active job posts
                            <template v-if="current.max_active_jobs"> of {{ current.max_active_jobs }}</template>
                            <template v-else> (no limit)</template>
                        </div>
                    </div>
                    <button v-if="current.active" class="btn btn-sm btn-outline-danger" @click="cancel">
                        Cancel subscription
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div v-for="tier in tiers" :key="tier.key" class="col-lg-4">
                    <div class="card h-100" :class="{ 'border-primary': isCurrent(tier) }">
                        <div class="card-body d-flex flex-column">
                            <div class="label-mono">{{ tier.name }}</div>
                            <div class="d-flex align-items-baseline gap-1 mb-1">
                                <span class="display-6 fw-bold">${{ tier.price_cents / 100 }}</span>
                                <span class="text-muted small">/month</span>
                            </div>
                            <p class="text-muted small mb-3">{{ tier.tagline }}</p>

                            <ul class="small ps-3 mb-4 flex-grow-1">
                                <li v-for="line in tier.highlights" :key="line" class="mb-1">{{ line }}</li>
                            </ul>

                            <button class="btn w-100"
                                    :class="isCurrent(tier) ? 'btn-outline-secondary' : 'btn-primary'"
                                    :disabled="working || !auth.isVerified"
                                    @click="choose(tier)">
                                <template v-if="isCurrent(tier)">Current plan</template>
                                <template v-else>Choose {{ tier.name }}</template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="mb-3">Payment method</h6>
                    <div class="d-flex flex-wrap gap-3">
                        <label v-for="(label, key) in providers" :key="key" class="form-check">
                            <input v-model="provider" :value="key" type="radio" class="form-check-input">
                            <span class="form-check-label">{{ label }}</span>
                        </label>
                    </div>
                    <div class="form-text mt-2">
                        Cards are billed in USD. Payme and Click settle in UZS at the current rate.
                    </div>
                </div>
            </div>

            <div v-if="payments.length" class="card">
                <div class="card-header py-2">Recent payments</div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Plan</th>
                                <th>Provider</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in payments" :key="payment.id">
                                <td class="text-muted small">{{ date(payment.created_at) }}</td>
                                <td>{{ payment.plan }}</td>
                                <td>{{ payment.provider }}</td>
                                <td>${{ payment.amount_cents / 100 }}</td>
                                <td>
                                    <span class="badge" :class="statusVariant(payment.status)">
                                        {{ payment.status }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { useAuthStore } from '../../stores/auth';

export default {
    name: 'CarrierBillingPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            tiers: [],
            providers: {},
            current: null,
            payments: [],
            provider: 'stripe',
            loading: true,
            working: false,
            error: null,
            notice: null,
        };
    },

    created() {
        this.load();
        this.settleReturn();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/carrier/plans');
                this.tiers = data.tiers;
                this.providers = data.providers;
                this.current = data.current;
                this.payments = data.payments;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        /**
         * The development gateway sends the browser straight back here with a
         * reference; confirm it so the whole flow can be tested without a
         * real provider.
         */
        async settleReturn() {
            const { status, reference } = this.$route.query;

            if (status !== 'success' || !reference) return;

            try {
                const { data } = await api.post('/carrier/checkout/confirm', { reference });
                this.notice = data.message;
                await this.auth.refresh();
                await this.load();
            } catch (e) {
                // A real gateway confirms through its webhook instead.
            } finally {
                this.$router.replace({ query: {} });
            }
        },

        isCurrent(tier) {
            return this.current && this.current.active && this.current.plan === tier.key;
        },

        async choose(tier) {
            this.working = true;
            this.error = null;

            try {
                const { data } = await api.post('/carrier/checkout', {
                    plan: tier.key,
                    provider: this.provider,
                });

                window.location.href = data.redirect_url;
            } catch (e) {
                this.error = e.friendly;
                this.working = false;
            }
        },

        async cancel() {
            if (!window.confirm('Cancel the subscription? The driver pool closes immediately.')) return;

            try {
                const { data } = await api.delete('/carrier/subscription');
                this.notice = data.message;
                await this.auth.refresh();
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            }
        },

        statusVariant(status) {
            return {
                paid: 'bg-success', pending: 'bg-secondary',
                failed: 'bg-danger', cancelled: 'bg-warning text-dark',
            }[status] || 'bg-secondary';
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('en-US') : '—';
        },
    },
};
</script>
