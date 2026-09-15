<template>
    <div>
        <h4 class="mb-3">Arizalarim</h4>

        <AlertBox :message="error" />

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

        <div v-else-if="!applications.length" class="empty-state">
            Hali ariza bermagansiz.
            <router-link :to="{ name: 'driver.jobs' }">Vakansiyalarni ko'rish</router-link>
        </div>

        <div v-else class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr class="text-muted small">
                            <th>Vakansiya</th>
                            <th>Kompaniya</th>
                            <th>Sana</th>
                            <th>Holat</th>
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
                                    Kompaniyaga baho
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
            :title="`${reviewing.job_post ? reviewing.job_post.carrier.company_name : 'Kompaniya'} haqida baho`"
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

        /** Baho faqat hamkorlik yakunlangandan keyin qoldiriladi. */
        canReview(application) {
            return ['hired', 'rejected'].includes(application.status);
        },

        onReviewed() {
            this.reviewing = null;
            this.load();
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('uz-UZ') : '—';
        },
    },
};
</script>
