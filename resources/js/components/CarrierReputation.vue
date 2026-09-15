<template>
    <div>
        <div v-if="loading" class="text-muted small">Loading…</div>

        <template v-else>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="score-pill" :class="overallTier">
                    {{ overall === null ? '—' : overall }}<small>/5</small>
                </div>
                <div class="small text-muted">
                    Combined from public sources and reviews left here.
                </div>
                <button v-if="canRefresh" class="btn btn-sm btn-link ms-auto" :disabled="refreshing"
                        @click="refresh">
                    {{ refreshing ? 'Refreshing…' : 'Refresh' }}
                </button>
            </div>

            <div v-for="source in allSources" :key="source.source"
                 class="d-flex justify-content-between align-items-start py-2 border-bottom">
                <div>
                    <div class="fw-semibold">
                        <a v-if="source.url" :href="source.url" target="_blank" rel="noopener">{{ source.source_label }}</a>
                        <template v-else>{{ source.source_label }}</template>
                    </div>
                    <div v-if="source.details" class="text-muted small">
                        <span v-for="(value, key) in displayDetails(source.details)" :key="key" class="me-3">
                            {{ label(key) }}: {{ value }}
                        </span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">{{ source.rating === null ? '—' : source.rating }}</div>
                    <div class="label-mono">
                        {{ source.review_count ? source.review_count + ' reviews' : 'no reviews' }}
                    </div>
                </div>
            </div>

            <p class="text-muted small mt-3 mb-0">
                Indeed and Glassdoor are not included — they have no public API and their terms
                do not allow collecting their content.
            </p>
        </template>
    </div>
</template>

<script>
import api from '../api';

const DETAIL_LABELS = {
    allowed_to_operate: 'Allowed to operate',
    safety_rating: 'Safety rating',
    driver_oos_rate: 'Driver out-of-service',
    vehicle_oos_rate: 'Vehicle out-of-service',
    total_drivers: 'Drivers',
    total_power_units: 'Power units',
    matched_name: 'Matched',
};

export default {
    name: 'CarrierReputation',

    props: {
        carrierId: { type: Number, required: true },
        canRefresh: { type: Boolean, default: false },
    },

    data() {
        return {
            sources: [],
            platform: null,
            overall: null,
            loading: true,
            refreshing: false,
        };
    },

    computed: {
        allSources() {
            return this.platform ? [...this.sources, this.platform] : this.sources;
        },

        overallTier() {
            if (this.overall === null) return 'tier-D';
            if (this.overall >= 4.2) return 'tier-A';
            if (this.overall >= 3.5) return 'tier-B';
            if (this.overall >= 2.5) return 'tier-C';

            return 'tier-out';
        },
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get(`/carriers/${this.carrierId}/reputation`);
                this.apply(data);
            } catch (e) {
                // A missing reputation panel should not break the page it sits on.
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.sources = data.sources;
            this.platform = data.platform;
            this.overall = data.overall;
        },

        async refresh() {
            this.refreshing = true;

            try {
                const { data } = await api.post(`/carriers/${this.carrierId}/reputation/refresh`);
                this.apply(data);
            } catch (e) {
                // ignore
            } finally {
                this.refreshing = false;
            }
        },

        displayDetails(details) {
            const out = {};

            Object.entries(details || {}).forEach(([key, value]) => {
                if (key === 'note' || value === null) return;

                out[key] = typeof value === 'boolean' ? (value ? 'Yes' : 'No')
                    : (key.endsWith('_rate') ? `${value}%` : value);
            });

            return out;
        },

        label(key) {
            return DETAIL_LABELS[key] || key;
        },
    },
};
</script>
