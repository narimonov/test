<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Personal recruiting</h4>
            <p class="page-lede">
                Tell us what you need and a recruiter works it for you — sourcing, screening and
                walking each driver through onboarding.
            </p>
        </div>

        <AlertBox :message="error" :errors="errors" />
        <AlertBox :message="notice" variant="success" />

        <div v-if="!allowed" class="card mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-semibold">This is part of the Pro plan</div>
                    <div class="text-muted small">
                        Pro is $500/month. It is people's time, not software: a recruiter works your
                        requisition, sends matched drivers and helps them proceed.
                    </div>
                </div>
                <router-link :to="{ name: 'carrier.billing' }" class="btn btn-primary">See plans</router-link>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body p-4">
                        <h6 class="mb-3">New request</h6>
                        <form @submit.prevent="submit">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input v-model="form.title" type="text" class="form-control"
                                       placeholder="5 OTR reefer drivers, Midwest" :disabled="!allowed" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Drivers needed</label>
                                <input v-model.number="form.drivers_needed" type="number" min="1"
                                       class="form-control" :disabled="!allowed">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Brief</label>
                                <textarea v-model="form.brief" rows="6" class="form-control" :disabled="!allowed"
                                          placeholder="Lanes, pay, equipment, home time, what disqualifies someone"
                                          required></textarea>
                            </div>
                            <button class="btn btn-primary" :disabled="!allowed || saving">
                                {{ saving ? 'Sending…' : 'Send to a recruiter' }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div v-if="loading" class="empty-state">Loading…</div>

                <div v-else-if="!requests.length" class="empty-state">No requests yet.</div>

                <div v-for="request in requests" :key="request.id" class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">{{ request.title }}</div>
                                <div class="label-mono">{{ request.drivers_needed }} needed</div>
                            </div>
                            <span class="badge" :class="statusVariant(request.status)">{{ request.status }}</span>
                        </div>

                        <p class="small text-muted mb-3" style="white-space: pre-line">{{ request.brief }}</p>

                        <div v-if="request.referrals && request.referrals.length">
                            <div class="label-mono mb-1">Drivers sent to you</div>
                            <div v-for="referral in request.referrals" :key="referral.id"
                                 class="d-flex justify-content-between border-bottom py-1 small">
                                <span>
                                    {{ referral.driver_profile.first_name }} {{ referral.driver_profile.last_name }}
                                    <span class="text-muted">
                                        · {{ referral.driver_profile.years_experience }}y
                                        · {{ referral.driver_profile.city }}, {{ referral.driver_profile.state }}
                                    </span>
                                </span>
                                <span class="badge bg-light text-dark">{{ referral.status }}</span>
                            </div>
                        </div>

                        <router-link v-if="request.conversation_id"
                                     :to="{ name: 'conversations', query: { id: request.conversation_id } }"
                                     class="btn btn-sm btn-outline-secondary mt-3">
                            Open thread
                        </router-link>
                    </div>
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
    name: 'CarrierRecruitingPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            requests: [],
            form: { title: '', brief: '', drivers_needed: 1 },
            loading: true,
            saving: false,
            error: null,
            notice: null,
            errors: {},
        };
    },

    computed: {
        allowed() {
            return this.auth.user?.carrier?.subscription_plan === 'pro'
                && this.auth.hasSubscription;
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/carrier/recruiting-requests');
                this.requests = data.requests;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async submit() {
            this.saving = true;
            this.error = null;
            this.notice = null;
            this.errors = {};

            try {
                const { data } = await api.post('/carrier/recruiting-requests', this.form);
                this.notice = data.message;
                this.form = { title: '', brief: '', drivers_needed: 1 };
                await this.load();
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },

        statusVariant(status) {
            return {
                open: 'bg-secondary', in_progress: 'bg-info',
                fulfilled: 'bg-success', closed: 'bg-dark',
            }[status] || 'bg-secondary';
        },
    },
};
</script>
