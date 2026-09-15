<template>
    <div>
        <h4 class="mb-1">Blacklist apelyatsiyalari</h4>
        <p class="text-muted small mb-4">
            Qabul qilsangiz blacklist olib tashlanadi va eski qoniqarsiz baholar
            qayta hisoblanmaydi (aks holda darrov qaytib tushardi).
        </p>

        <AlertBox :message="error" />
        <AlertBox :message="notice" variant="success" />

        <div class="btn-group mb-3">
            <button v-for="option in statuses" :key="option.value" class="btn btn-sm"
                    :class="status === option.value ? 'btn-primary' : 'btn-outline-primary'"
                    @click="status = option.value; load()">
                {{ option.label }}
            </button>
        </div>

        <div v-if="loading" class="empty-state">Yuklanmoqda…</div>

        <div v-else-if="!appeals.length" class="empty-state">Bu bo'limda apelyatsiya yo'q.</div>

        <div v-else class="row g-3">
            <div v-for="appeal in appeals" :key="appeal.id" class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-semibold">{{ subjectName(appeal) }}</div>
                                <div class="text-muted small">
                                    {{ appeal.subject_type === 'driver' ? 'Driver' : 'Kompaniya' }}
                                    · {{ appeal.submitted_by ? appeal.submitted_by.email : '—' }}
                                    · {{ date(appeal.created_at) }}
                                </div>
                            </div>
                            <span class="badge" :class="statusVariant(appeal.status)">
                                {{ statusLabel(appeal.status) }}
                            </span>
                        </div>

                        <div class="text-muted small mb-1">Blacklist sababi: {{ blacklistReason(appeal) || '—' }}</div>

                        <p class="mb-3" style="white-space: pre-line">{{ appeal.reason }}</p>

                        <div v-if="appeal.status === 'pending'" class="d-flex gap-2">
                            <button class="btn btn-sm btn-success" @click="decide(appeal, 'approved')">
                                Qabul qilish
                            </button>
                            <button class="btn btn-sm btn-outline-danger" @click="decide(appeal, 'rejected')">
                                Rad etish
                            </button>
                        </div>
                        <div v-else-if="appeal.decision_note" class="text-muted small">
                            Qaror izohi: {{ appeal.decision_note }}
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

export default {
    name: 'AdminAppealsPage',

    components: { AlertBox },

    data() {
        return {
            appeals: [],
            status: 'pending',
            statuses: [
                { value: 'pending', label: 'Kutilmoqda' },
                { value: 'approved', label: 'Qabul qilingan' },
                { value: 'rejected', label: 'Rad etilgan' },
                { value: 'all', label: 'Barchasi' },
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
                const { data } = await api.get('/admin/appeals', { params: { status: this.status } });
                this.appeals = data.data;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.loading = false;
            }
        },

        async decide(appeal, decision) {
            const note = window.prompt(
                decision === 'approved' ? 'Qabul qilish izohi (ixtiyoriy)' : 'Rad etish sababi (ixtiyoriy)'
            );

            // Prompt bekor qilinsa hech narsa qilmaymiz.
            if (note === null) return;

            try {
                const { data } = await api.post(`/admin/appeals/${appeal.id}/decision`, { decision, note: note || null });
                this.notice = data.message;
                await this.load();
            } catch (e) {
                this.error = e.friendly;
            }
        },

        subjectName(appeal) {
            if (appeal.subject_type === 'driver' && appeal.driver_profile) {
                return `${appeal.driver_profile.first_name} ${appeal.driver_profile.last_name}`.trim();
            }

            return appeal.carrier ? appeal.carrier.company_name : '—';
        },

        blacklistReason(appeal) {
            const subject = appeal.subject_type === 'driver' ? appeal.driver_profile : appeal.carrier;

            return subject ? subject.blacklist_reason : null;
        },

        statusLabel(status) {
            return { pending: 'Kutilmoqda', approved: 'Qabul qilindi', rejected: 'Rad etildi' }[status] || status;
        },

        statusVariant(status) {
            return { pending: 'bg-secondary', approved: 'bg-success', rejected: 'bg-danger' }[status] || 'bg-secondary';
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('uz-UZ') : '—';
        },
    },
};
</script>
