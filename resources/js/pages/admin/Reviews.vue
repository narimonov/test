<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Review queue</h4>
            <p class="page-lede">
                Nothing is published until the proof checks out and the other side has been contacted.
                Only published reviews count towards a blacklist.
            </p>
        </div>

        <AlertBox :message="error" />
        <AlertBox :message="notice" variant="success" />

        <div class="btn-group mb-3">
            <button v-for="option in statuses" :key="option.value" class="btn btn-sm"
                    :class="status === option.value ? 'btn-primary' : 'btn-outline-primary'"
                    @click="status = option.value; load()">
                {{ option.label }}
            </button>
        </div>

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!reviews.length" class="empty-state">Nothing in this queue.</div>

        <div v-for="review in reviews" :key="review.id" class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="fw-semibold">
                            {{ review.author ? review.author.name : 'Unknown' }}
                            <span class="text-muted fw-normal">rated</span>
                            {{ subjectName(review) }}
                        </div>
                        <div class="label-mono">
                            {{ review.subject_type }} · {{ date(review.submitted_at || review.created_at) }}
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="score-pill" :class="review.is_negative ? 'tier-out' : 'tier-A'">
                            {{ review.rating }}<small>/5</small>
                        </span>
                    </div>
                </div>

                <p v-if="review.body" class="mb-3" style="white-space: pre-line">{{ review.body }}</p>

                <div class="mb-3">
                    <div class="label-mono mb-1">Proof ({{ review.proofs.length }})</div>
                    <div v-if="!review.proofs.length" class="text-danger small">
                        No proof attached — this cannot be published.
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button v-for="proof in review.proofs" :key="proof.id"
                                class="btn btn-sm btn-outline-secondary" @click="openProof(proof)">
                            {{ kindLabel(proof.kind) }} · {{ proof.original_name }}
                        </button>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span v-if="review.counterparty_contacted_at" class="badge bg-success">
                        Other party contacted
                    </span>
                    <button v-else class="btn btn-sm btn-outline-info" @click="markContacted(review)">
                        Mark other party contacted
                    </button>

                    <template v-if="review.status === 'pending_review'">
                        <button class="btn btn-sm btn-success" @click="decide(review, 'publish')">Publish</button>
                        <button class="btn btn-sm btn-outline-danger" @click="decide(review, 'reject')">Reject</button>
                    </template>
                    <span v-else class="badge" :class="statusVariant(review.status)">{{ review.status }}</span>
                </div>

                <div v-if="review.moderation_note" class="text-muted small mt-2">
                    Note: {{ review.moderation_note }}
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';

export default {
    name: 'AdminReviewsPage',

    components: { AlertBox },

    data() {
        return {
            reviews: [],
            status: 'pending_review',
            statuses: [
                { value: 'pending_review', label: 'Awaiting check' },
                { value: 'published', label: 'Published' },
                { value: 'rejected', label: 'Rejected' },
                { value: 'all', label: 'All' },
            ],
            loading: true,
            error: null,
            notice: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await api.get('/admin/reviews', { params: { status: this.status } });
                this.reviews = data.data;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async markContacted(review) {
            try {
                const { data } = await api.post(`/admin/reviews/${review.id}/contacted`);
                this.notice = data.message;
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            }
        },

        async decide(review, decision) {
            const note = window.prompt(
                decision === 'publish' ? 'Note (optional)' : 'Why is this rejected?'
            );

            if (note === null) return;

            try {
                const { data } = await api.post(`/admin/reviews/${review.id}/decision`, {
                    decision,
                    note: note || null,
                });

                this.notice = data.blacklisted
                    ? data.message + ' The subject crossed the threshold and is now blacklisted.'
                    : data.message;

                await this.load();
            } catch (e) {
                this.error = e.friendly;
            }
        },

        async openProof(proof) {
            try {
                const response = await api.get(`/review-proofs/${proof.id}`, { responseType: 'blob' });
                const url = URL.createObjectURL(response.data);

                window.open(url, '_blank', 'noopener');
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch (e) {
                this.error = e.friendly;
            }
        },

        subjectName(review) {
            if (review.subject_type === 'driver' && review.driver_profile) {
                return `${review.driver_profile.first_name} ${review.driver_profile.last_name}`.trim();
            }

            return review.carrier ? review.carrier.company_name : '—';
        },

        kindLabel(kind) {
            return {
                rate_confirmation: 'Rate confirmation',
                employment_letter: 'Employment letter',
                settlement: 'Settlement',
                paystub: 'Pay stub',
                other: 'Document',
            }[kind] || kind;
        },

        statusVariant(status) {
            return {
                published: 'bg-success', rejected: 'bg-danger',
                pending_review: 'bg-secondary', removed: 'bg-dark',
            }[status] || 'bg-secondary';
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('en-US') : '—';
        },
    },
};
</script>
