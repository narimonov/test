<template>
    <div>
        <div class="mb-4"><h4 class="page-title">My applications</h4>
            <p class="page-lede">Where each one stands, and where you can leave a review.</p></div>

        <AlertBox :message="error" />
        <AlertBox :message="notice" variant="success" />

        <div v-if="loading" class="empty-state">Loading…</div>

        <div v-else-if="!applications.length" class="empty-state">
            You have not applied to anything yet.
            <router-link :to="{ name: 'driver.jobs' }">Browse open jobs</router-link>
        </div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>Job</th>
                            <th>Company</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="application in applications" :key="application.id">
                            <td class="fw-semibold">{{ application.job_post?.title }}</td>
                            <td class="text-muted">{{ application.job_post?.carrier?.company_name }}</td>
                            <td class="text-muted small">{{ date(application.created_at) }}</td>
                            <td><StatusBadge :status="application.status" :options="statuses" /></td>
                            <td class="text-end">
                                <button v-if="canReview(application)" class="btn btn-sm btn-outline-primary"
                                        @click="reviewing = application">
                                    Review carrier
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
            :title="`Review ${reviewing.job_post ? reviewing.job_post.carrier.company_name : 'this carrier'}`"
            @close="reviewing = null"
            @saved="onReviewed"
        />
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import ReviewModal from '../../components/ReviewModal.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import { APPLICATION_STATUSES } from '../../constants';

export default {
    name: 'DriverApplicationsPage',

    components: { AlertBox, ReviewModal, StatusBadge },

    data() {
        return {
            applications: [],
            statuses: APPLICATION_STATUSES,
            reviewing: null,
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
            try {
                const { data } = await api.get('/driver/applications');
                this.applications = data.data || [];
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        /** Reviews open once the relationship has ended. */
        canReview(application) {
            return ['hired', 'rejected'].includes(application.status);
        },

        onReviewed(data) {
            this.reviewing = null;
            this.notice = data.message;
            this.load();
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('en-US') : '—';
        },
    },
};
</script>
