<template>
    <div>
        <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
            <div>
                <h4 class="page-title">{{ job ? job.title : 'Matching drivers' }}</h4>
                <p class="page-lede">
                    Drivers who meet this job's requirements but have not applied yet.
                    Same criteria, same scoring — they just have not seen you.
                </p>
            </div>
            <div class="d-flex gap-2">
                <router-link :to="{ name: 'carrier.applicants', params: { id: $route.params.id } }"
                             class="btn btn-outline-secondary">Applicants</router-link>
                <router-link :to="{ name: 'carrier.jobs' }" class="btn btn-outline-secondary">Job posts</router-link>
            </div>
        </div>

        <AlertBox :message="error" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Minimum score</label>
                        <input v-model.number="filters.min_score" type="number" min="0" max="100"
                               class="form-control" @change="load">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">How many</label>
                        <select v-model.number="filters.limit" class="form-select" @change="load">
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-3">
                            <input id="same-state" v-model="filters.same_state" type="checkbox"
                                   class="form-check-input" @change="load">
                            <label class="form-check-label small" for="same-state">
                                Same state as the job
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="summary" class="row g-3 mb-3">
            <div class="col-4 col-md-3">
                <div class="card stat-card"><div class="card-body py-3">
                    <div class="stat-value">{{ summary.matched }}</div>
                    <div class="stat-label">Matched</div>
                </div></div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card stat-card"><div class="card-body py-3">
                    <div class="stat-value text-success">{{ summary.top_tier }}</div>
                    <div class="stat-label">A grade</div>
                </div></div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card stat-card"><div class="card-body py-3">
                    <div class="stat-value">{{ summary.considered }}</div>
                    <div class="stat-label">Considered</div>
                </div></div>
            </div>
        </div>

        <div v-if="summary && summary.requirements.length" class="mb-3">
            <span class="label-mono me-2">Requirements applied</span>
            <span v-for="rule in summary.requirements" :key="rule.key" class="knockout-tag">{{ rule.label }}</span>
        </div>

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!matches.length" class="empty-state">
            No unapplied drivers meet these requirements. Loosen the job's knockout rules to widen the net.
        </div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 90px">Score</th>
                            <th>Driver</th>
                            <th>Experience</th>
                            <th>Safety</th>
                            <th>Location</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in matches" :key="row.driver.id" class="driver-row" @click="selected = row">
                            <td><ScorePill :score="row.score" :tier="row.tier" /></td>
                            <td>
                                <div class="fw-semibold">{{ row.driver.full_name }}</div>
                                <div class="text-muted small">CDL {{ row.driver.cdl_class || '—' }}</div>
                            </td>
                            <td>{{ row.driver.years_experience }} yrs</td>
                            <td>
                                <span :class="row.driver.accidents_3y ? 'text-danger' : 'text-success'">
                                    {{ row.driver.accidents_3y }} accidents
                                </span>
                                <div class="text-muted small">{{ row.driver.moving_violations_3y }} violations</div>
                            </td>
                            <td class="text-muted small">
                                {{ [row.driver.city, row.driver.state].filter(Boolean).join(', ') || '—' }}
                            </td>
                            <td class="text-end" @click.stop>
                                <button v-if="row.driver.user_id" class="btn btn-sm btn-primary"
                                        :disabled="contacting === row.driver.id" @click="contact(row)">
                                    {{ contacting === row.driver.id ? '…' : 'Message' }}
                                </button>
                                <span v-else class="label-mono" :title="row.driver.phone">No account — call them</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="selected" class="modal d-block" tabindex="-1" style="background: rgba(14,20,27,.5)"
             @click.self="selected = null">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ selected.driver.full_name }}</h5>
                        <button type="button" class="btn-close" @click="selected = null"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4"><ScorePill :score="selected.score" :tier="selected.tier" /></div>
                        <DriverSummary :driver="selected.driver" class="mb-4" />
                        <h6>Score breakdown</h6>
                        <ScoreBreakdown :rows="selected.breakdown" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import DriverSummary from '../../components/DriverSummary.vue';
import ScoreBreakdown from '../../components/ScoreBreakdown.vue';
import ScorePill from '../../components/ScorePill.vue';

export default {
    name: 'CarrierMatchesPage',

    components: { AlertBox, DriverSummary, ScoreBreakdown, ScorePill },

    data() {
        return {
            job: null,
            matches: [],
            summary: null,
            selected: null,
            filters: { min_score: null, limit: 25, same_state: false },
            loading: true,
            contacting: null,
            error: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            this.loading = true;
            this.error = null;

            const params = Object.fromEntries(
                Object.entries(this.filters).filter(([, value]) => value !== null && value !== '' && value !== false)
            );

            try {
                const { data } = await api.get(`/carrier/jobs/${this.$route.params.id}/matches`, { params });
                this.matches = data.matches;
                this.summary = data.summary;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async contact(row) {
            this.contacting = row.driver.id;
            this.error = null;

            try {
                const { data } = await api.post('/carrier/conversations', {
                    driver_profile_id: row.driver.id,
                    job_post_id: Number(this.$route.params.id),
                });

                this.$router.push({ name: 'conversations', query: { id: data.conversation.id } });
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.contacting = null;
            }
        },
    },
};
</script>
