<template>
    <div>
        <div class="mb-4">
            <h4 class="page-title">Onboarding</h4>
            <p class="page-lede">
                {{ auth.isCarrier
                    ? 'Everything a hired driver has to clear before the first dispatch.'
                    : 'What your new carrier still needs from you.' }}
            </p>
        </div>

        <AlertBox :message="error" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!onboardings.length" class="empty-state">
            {{ auth.isCarrier ? 'Nobody is onboarding right now.' : 'You are not onboarding with anyone yet.' }}
        </div>

        <div v-for="onboarding in onboardings" :key="onboarding.id" class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <div class="fw-semibold fs-5">{{ title(onboarding) }}</div>
                        <div class="label-mono">
                            {{ trackLabel(onboarding.track) }} · started {{ date(onboarding.start_date) }}
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 fw-bold">{{ onboarding.progress.percent }}%</div>
                        <div class="label-mono">
                            {{ onboarding.progress.done }} of {{ onboarding.progress.required }} required
                        </div>
                    </div>
                </div>

                <div class="progress mb-4" style="height: 5px">
                    <div class="progress-bar" :style="{ width: onboarding.progress.percent + '%' }"></div>
                </div>

                <div v-for="(steps, stage) in grouped(onboarding)" :key="stage" class="mb-3">
                    <div class="label-mono mb-2">{{ stages[stage] || stage }}</div>

                    <div v-for="step in steps" :key="step.id"
                         class="d-flex align-items-start gap-3 py-2 border-bottom">
                        <span class="badge mt-1" :class="statusVariant(step.status)" style="min-width: 78px">
                            {{ statusLabel(step.status) }}
                        </span>

                        <div class="flex-grow-1">
                            <div class="fw-semibold">
                                {{ step.label }}
                                <span v-if="!step.is_required" class="text-muted small">(optional)</span>
                            </div>
                            <div v-if="step.description" class="text-muted small">{{ step.description }}</div>
                            <div v-if="step.note" class="small text-primary">{{ step.note }}</div>
                        </div>

                        <div class="text-end" style="min-width: 150px">
                            <div class="label-mono mb-1">{{ step.owner }}</div>
                            <select v-if="canEdit(step)" :value="step.status" class="form-select form-select-sm"
                                    @change="update(onboarding, step, $event.target.value)">
                                <option v-for="option in statuses" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import api from '../api';
import AlertBox from '../components/AlertBox.vue';
import { useAuthStore } from '../stores/auth';

export default {
    name: 'OnboardingPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            onboardings: [],
            stages: {},
            statuses: [
                { value: 'pending', label: 'Not started' },
                { value: 'in_progress', label: 'In progress' },
                { value: 'done', label: 'Done' },
                { value: 'skipped', label: 'Skipped' },
                { value: 'failed', label: 'Failed' },
            ],
            loading: true,
            error: null,
        };
    },

    created() {
        this.load();
    },

    methods: {
        async load() {
            try {
                const { data } = await api.get('/onboarding');
                this.onboardings = data.onboardings;
                this.stages = data.stages;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        grouped(onboarding) {
            return onboarding.steps.reduce((groups, step) => {
                (groups[step.stage] = groups[step.stage] || []).push(step);

                return groups;
            }, {});
        },

        /** A driver moves only their own steps; the carrier owns the rest. */
        canEdit(step) {
            return this.auth.isCarrier || step.owner === 'driver';
        },

        async update(onboarding, step, status) {
            this.error = null;

            try {
                const { data } = await api.put(`/onboarding/${onboarding.id}/steps/${step.id}`, { status });
                const index = this.onboardings.findIndex((item) => item.id === onboarding.id);

                this.onboardings.splice(index, 1, {
                    ...data.onboarding,
                    driver_profile: onboarding.driver_profile,
                    carrier: onboarding.carrier,
                });
            } catch (e) {
                this.error = e.friendly;
                this.load();
            }
        },

        title(onboarding) {
            if (this.auth.isCarrier) {
                return onboarding.driver_profile
                    ? `${onboarding.driver_profile.first_name} ${onboarding.driver_profile.last_name}`.trim()
                    : 'Driver';
            }

            return onboarding.carrier ? onboarding.carrier.company_name : 'Carrier';
        },

        trackLabel(track) {
            return track === 'owner_operator' ? 'Owner operator' : 'Company driver';
        },

        statusLabel(status) {
            return {
                pending: 'Pending', in_progress: 'Working', done: 'Done',
                skipped: 'Skipped', failed: 'Failed',
            }[status] || status;
        },

        statusVariant(status) {
            return {
                pending: 'bg-secondary', in_progress: 'bg-info',
                done: 'bg-success', skipped: 'bg-dark', failed: 'bg-danger',
            }[status] || 'bg-secondary';
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('en-US') : '—';
        },
    },
};
</script>
