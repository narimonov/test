<template>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <h4 class="page-title mb-1">Standing &amp; appeals</h4>
            <p class="text-muted small mb-4">
                Too many unsatisfactory reviews and an account is blacklisted. If you think that
                is unfair, appeal it — an admin reviews every appeal.
            </p>

            <AlertBox :message="error" :errors="errors" />
            <AlertBox :message="notice" variant="success" />

            <div v-if="loading" class="empty-state">Loading…</div>

            <template v-else>
                <div class="card mb-4">
                    <div class="card-body">
                        <div v-if="isBlacklisted" class="d-flex align-items-start gap-3">
                            <span class="badge bg-danger">Blacklist</span>
                            <div>
                                <div class="fw-semibold">This account is restricted</div>
                                <div class="text-muted small">Reason: {{ blacklistReason || '—' }}</div>
                            </div>
                        </div>
                        <div v-else class="d-flex align-items-center gap-3">
                            <span class="badge bg-success">Clear</span>
                            <div class="text-muted small">No restrictions on this account.</div>
                        </div>
                    </div>
                </div>

                <div v-if="isBlacklisted && !hasPending" class="card mb-4">
                    <div class="card-body">
                        <h6 class="mb-3">Submit an appeal</h6>
                        <form @submit.prevent="submit">
                            <textarea v-model="reason" rows="5" class="form-control mb-3" minlength="20"
                                      placeholder="Explain what happened and why you believe the reviews are unfair"></textarea>
                            <button class="btn btn-primary" :disabled="saving">
                                {{ saving ? 'Sending…' : 'Submit' }}
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Appeal history</h6>

                        <div v-if="!appeals.length" class="text-muted small">No appeals yet.</div>

                        <div v-for="appeal in appeals" :key="appeal.id" class="py-2 border-bottom">
                            <div class="d-flex justify-content-between">
                                <span class="badge" :class="statusVariant(appeal.status)">{{ statusLabel(appeal.status) }}</span>
                                <span class="text-muted small">{{ date(appeal.created_at) }}</span>
                            </div>
                            <p class="small mt-2 mb-1">{{ appeal.reason }}</p>
                            <p v-if="appeal.decision_note" class="small text-muted mb-0">
                                Admin note: {{ appeal.decision_note }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>

<script>
import api from '../api';
import AlertBox from '../components/AlertBox.vue';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'AppealPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            appeals: [],
            isBlacklisted: false,
            blacklistReason: null,
            reason: '',
            loading: true,
            saving: false,
            error: null,
            notice: null,
            errors: {},
        };
    },

    computed: {
        hasPending() {
            return this.appeals.some((appeal) => appeal.status === 'pending');
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/appeals');
                this.appeals = data.appeals;
                this.isBlacklisted = data.is_blacklisted;
                this.blacklistReason = data.blacklist_reason;
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
                const { data } = await api.post('/appeals', { reason: this.reason });
                this.notice = data.message;
                this.reason = '';
                await this.load();
            } catch (e) {
                this.error = e.friendly;
                this.errors = e.errors || {};
            } finally {
                this.saving = false;
            }
        },

        statusLabel(status) {
            return { pending: 'Under review', approved: 'Approved', rejected: 'Rejected' }[status] || status;
        },

        statusVariant(status) {
            return { pending: 'bg-secondary', approved: 'bg-success', rejected: 'bg-danger' }[status] || 'bg-secondary';
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('en-US') : '—';
        },
    },
};
</script>
