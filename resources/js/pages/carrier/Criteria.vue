<template>
    <div>
        <h4 class="page-title mb-1">Scoring criteria</h4>
        <p class="text-muted small mb-4">
            Every driver is scored 0–100 against these. Change a weight and every score
            recalculates immediately.
        </p>

        <AlertBox :message="error" />
        <AlertBox :message="notice" variant="success" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else class="row g-4">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Weighted criteria</h6>

                        <div v-for="item in criteria" :key="item.key" class="mb-3 pb-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <div>
                                    <div class="fw-semibold">{{ item.label }}</div>
                                    <div class="text-muted small">{{ groupLabel(item.group) }}</div>
                                </div>
                                <div class="d-flex align-items-center gap-2" style="width: 170px">
                                    <input v-model.number="weights[item.key]" type="range" min="0" max="40"
                                           class="form-range">
                                    <span class="small fw-semibold" style="width: 2.2rem">{{ weights[item.key] }}</span>
                                </div>
                            </div>
                            <div class="criteria-bar">
                                <span :style="{ width: percentOf(item.key) + '%' }"></span>
                            </div>
                            <div class="text-muted small mt-1">
                                {{ percentOf(item.key) }}% of the total score
                            </div>
                        </div>

                        <button class="btn btn-primary" :disabled="saving" @click="save">
                            {{ saving ? 'Saving…' : 'Save weights' }}
                        </button>
                        <button class="btn btn-link" @click="resetWeights">Reset to defaults</button>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-3">Knockout rules</h6>
                        <p class="text-muted small">
                            Fail one of these and the driver is not scored at all — they show at the
                            bottom of the list marked OUT.
                        </p>
                        <ul class="list-unstyled mb-0">
                            <li v-for="(rule, index) in knockouts" :key="index" class="py-1 border-bottom">
                                <span class="knockout-tag">{{ rule.reason }}</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Grades</h6>
                        <div v-for="(threshold, tier) in tiers" :key="tier"
                             class="d-flex justify-content-between py-1 border-bottom">
                            <span class="score-pill" :class="`tier-${tier}`" style="min-width: 2.4rem; height: 1.9rem; font-size: .9rem">
                                {{ tier }}
                            </span>
                            <span class="text-muted small align-self-center">{{ threshold }} points and above</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';

const GROUP_LABELS = {
    experience: 'Experience',
    safety: 'Safety / MVR',
    stability: 'Stability',
    qualification: 'Qualifications',
    eligibility: 'Eligibility',
    other: 'Other',
};

export default {
    name: 'CarrierCriteriaPage',

    components: { AlertBox },

    data() {
        return {
            criteria: [],
            knockouts: [],
            tiers: {},
            weights: {},
            defaults: {},
            loading: true,
            saving: false,
            error: null,
            notice: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/scoring/criteria');
                this.apply(data);
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        apply(data) {
            this.criteria = data.criteria;
            this.knockouts = data.knockouts;
            this.tiers = data.tiers;
            this.weights = Object.fromEntries(data.criteria.map((item) => [item.key, item.weight]));

            if (!Object.keys(this.defaults).length) {
                this.defaults = { ...this.weights };
            }
        },

        groupLabel(group) {
            return GROUP_LABELS[group] || group;
        },

        percentOf(key) {
            const total = Object.values(this.weights).reduce((sum, value) => sum + value, 0);

            return total ? Math.round((this.weights[key] / total) * 100) : 0;
        },

        resetWeights() {
            this.weights = { ...this.defaults };
        },

        async save() {
            this.saving = true;
            this.error = null;
            this.notice = null;

            try {
                const { data } = await api.put('/carrier/scoring/overrides', {
                    criteria: Object.entries(this.weights).map(([key, weight]) => ({ key, weight })),
                });

                this.apply(data.criteria);
                this.notice = 'Saved. Scores now use the new weights.';
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.saving = false;
            }
        },
    },
};
</script>
