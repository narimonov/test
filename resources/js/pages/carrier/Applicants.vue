<template>
    <div>
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="mb-1">{{ job?.title || 'Arizachilar' }}</h4>
                <p class="text-muted small mb-0">
                    Arizachilar sizning kriteriyalaringiz bo'yicha ball olib, yuqoridan pastga saralangan.
                </p>
            </div>
            <router-link :to="{ name: 'carrier.jobs' }" class="btn btn-light">Vakansiyalarga</router-link>
        </div>

        <AlertBox :message="error" />

        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Saralash</label>
                        <select v-model="filters.sort" class="form-select" @change="load">
                            <option value="score">Ball bo'yicha (yuqoridan)</option>
                            <option value="date">Sana bo'yicha (yangi)</option>
                            <option value="experience">Tajriba bo'yicha</option>
                            <option value="safety">Safety bo'yicha (toza)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Daraja</label>
                        <select v-model="filters.tier" class="form-select" @change="load">
                            <option :value="null">Barchasi</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="C">C</option>
                            <option value="D">D</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Min. ball</label>
                        <input v-model.number="filters.min_score" type="number" min="0" max="100"
                               class="form-control" @change="load">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Holat</label>
                        <select v-model="filters.status" class="form-select" @change="load">
                            <option :value="null">Barchasi</option>
                            <option v-for="item in statuses" :key="item.value" :value="item.value">{{ item.label }}</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <button class="btn btn-outline-secondary btn-sm" :disabled="loading" @click="load(true)">
                            Ballarni qayta hisoblash
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="summary" class="row g-3 mb-3">
            <div class="col-4 col-md-3">
                <div class="card stat-card"><div class="card-body py-3">
                    <div class="stat-value">{{ summary.total }}</div>
                    <div class="stat-label">Jami</div>
                </div></div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card stat-card"><div class="card-body py-3">
                    <div class="stat-value text-success">{{ summary.qualified }}</div>
                    <div class="stat-label">Mos keladi</div>
                </div></div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card stat-card"><div class="card-body py-3">
                    <div class="stat-value text-danger">{{ summary.disqualified }}</div>
                    <div class="stat-label">Knockout</div>
                </div></div>
            </div>
        </div>

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

        <div v-else-if="!applicants.length" class="empty-state">
            Bu vakansiyaga hali ariza kelmagan.
        </div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th style="width: 90px">Ball</th>
                            <th>Driver</th>
                            <th>Tajriba</th>
                            <th>Safety</th>
                            <th>Joylashuv</th>
                            <th>Holat</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in applicants" :key="row.id" class="driver-row" @click="select(row)">
                            <td>
                                <ScorePill :score="row.score || 0" :tier="row.tier" :disqualified="row.disqualified" />
                            </td>
                            <td>
                                <div class="fw-semibold">{{ row.driver.full_name }}</div>
                                <div class="text-muted small">{{ row.driver.phone || row.driver.email || '—' }}</div>
                            </td>
                            <td>
                                {{ row.driver.years_experience }} yil
                                <div class="text-muted small">CDL {{ row.driver.cdl_class || '—' }}</div>
                            </td>
                            <td>
                                <span :class="row.driver.accidents_3y ? 'text-danger' : 'text-success'">
                                    {{ row.driver.accidents_3y }} avariya
                                </span>
                                <div class="text-muted small">{{ row.driver.moving_violations_3y }} violation</div>
                            </td>
                            <td class="text-muted small">
                                {{ [row.driver.city, row.driver.state].filter(Boolean).join(', ') || '—' }}
                            </td>
                            <td @click.stop>
                                <select :value="row.status" class="form-select form-select-sm"
                                        @change="updateStatus(row, $event.target.value)">
                                    <option v-for="item in statuses" :key="item.value" :value="item.value">
                                        {{ item.label }}
                                    </option>
                                </select>
                            </td>
                            <td class="text-end" @click.stop>
                                <button class="btn btn-sm btn-outline-secondary me-1" @click="select(row)">Batafsil</button>
                                <button v-if="canReview(row)" class="btn btn-sm btn-outline-primary"
                                        @click="reviewing = row">
                                    Baho
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ReviewModal
            v-if="reviewing"
            :application-id="reviewing.id"
            :title="`${reviewing.driver.full_name} haqida baho`"
            @close="reviewing = null"
            @saved="onReviewed"
        />

        <!-- Driver tafsiloti -->
        <div v-if="selected" class="modal d-block" tabindex="-1" style="background: rgba(15,23,42,.5)"
             @click.self="selected = null">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0">{{ selected.driver.full_name }}</h5>
                            <div class="text-muted small">
                                {{ selected.driver.phone }} · {{ selected.driver.email }}
                            </div>
                        </div>
                        <button type="button" class="btn-close" @click="selected = null"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <ScorePill :score="selected.score || 0" :tier="selected.tier"
                                       :disqualified="selected.disqualified" />
                            <div class="small text-muted">
                                Ariza sanasi: {{ date(selected.applied_at) }}
                            </div>
                        </div>

                        <div v-if="selected.cover_note" class="alert alert-light border">
                            {{ selected.cover_note }}
                        </div>

                        <DriverSummary :driver="selected.driver" class="mb-4" />

                        <h6>Ball taqsimoti</h6>
                        <ScoreBreakdown :rows="selected.breakdown" :knockouts="selected.knockouts" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import ScorePill from '../../components/ScorePill.vue';
import ScoreBreakdown from '../../components/ScoreBreakdown.vue';
import DriverSummary from '../../components/DriverSummary.vue';
import ReviewModal from '../../components/ReviewModal.vue';
import { APPLICATION_STATUSES } from '../../constants';

export default {
    name: 'CarrierApplicantsPage',

    components: { AlertBox, ScorePill, ScoreBreakdown, DriverSummary, ReviewModal },

    data() {
        return {
            job: null,
            applicants: [],
            summary: null,
            selected: null,
            reviewing: null,
            statuses: APPLICATION_STATUSES,
            filters: { sort: 'score', tier: null, min_score: null, status: null },
            loading: true,
            error: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load(recalculate = false) {
            this.loading = true;
            this.error = null;

            try {
                const { data } = await api.get(`/carrier/jobs/${this.$route.params.id}/applicants`, {
                    params: { ...this.filters, recalculate: recalculate ? 1 : undefined },
                });

                this.job = data.job;
                this.applicants = data.applicants;
                this.summary = data.summary;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        select(row) {
            this.selected = row;
        },

        /** Baho faqat hamkorlik yakunlangandan keyin qoldiriladi. */
        canReview(row) {
            return ['hired', 'rejected'].includes(row.status);
        },

        onReviewed(data) {
            this.reviewing = null;

            if (data.blacklisted) {
                this.error = data.message;
            }

            this.load();
        },

        async updateStatus(row, status) {
            try {
                await api.put(`/carrier/applications/${row.id}/status`, { status });
                row.status = status;
            } catch (e) {
                this.error = e.friendly;
            }
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('uz-UZ') : '—';
        },
    },
};
</script>
