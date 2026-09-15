<template>
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h4 class="mb-1">Obuna</h4>
            <p class="text-muted small mb-4">
                Driver bazasi va arizachilar ro'yxati aktiv obuna bilan ochiladi.
            </p>

            <AlertBox :message="error" />
            <AlertBox :message="notice" variant="success" />

            <div v-if="!auth.isVerified" class="alert alert-warning">
                Obuna ochishdan oldin telefon yoki emailni tasdiqlang.
                <router-link :to="{ name: 'verify' }" class="alert-link">Tasdiqlash</router-link>
            </div>

            <div v-if="carrier" class="card mb-4">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="text-muted small">Joriy holat</div>
                        <div class="fw-semibold">
                            {{ planLabel(carrier.subscription_plan) }} —
                            <span :class="carrier.has_active_subscription ? 'text-success' : 'text-danger'">
                                {{ carrier.has_active_subscription ? 'aktiv' : 'aktiv emas' }}
                            </span>
                        </div>
                        <div v-if="carrier.subscription_expires_at" class="text-muted small">
                            Amal qilish muddati: {{ date(carrier.subscription_expires_at) }}
                        </div>
                    </div>
                    <button v-if="carrier.has_active_subscription" class="btn btn-outline-danger btn-sm"
                            @click="cancel">
                        Obunani bekor qilish
                    </button>
                </div>
            </div>

            <div class="row g-3">
                <div v-for="plan in plans" :key="plan.value" class="col-md-4">
                    <div class="card h-100" :class="{ 'border-primary': carrier?.subscription_plan === plan.value }">
                        <div class="card-body d-flex flex-column">
                            <h5>{{ plan.label }}</h5>
                            <div class="display-6 fw-bold mb-1">${{ plan.price }}</div>
                            <div class="text-muted small mb-3">oyiga</div>

                            <ul class="small text-muted ps-3 mb-4 flex-grow-1">
                                <li v-for="feature in plan.features" :key="feature">{{ feature }}</li>
                            </ul>

                            <button class="btn" :class="carrier?.subscription_plan === plan.value && carrier?.has_active_subscription
                                        ? 'btn-outline-secondary' : 'btn-primary'"
                                    :disabled="saving || !auth.isVerified"
                                    @click="subscribe(plan.value)">
                                <template v-if="carrier?.subscription_plan === plan.value && carrier?.has_active_subscription">
                                    Joriy tarif
                                </template>
                                <template v-else>Tanlash</template>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <p class="text-muted small mt-4 mb-0">
                To'lov tizimi hali ulanmagan — hozircha obuna shu yerdan qo'lda
                aktivlashtiriladi. Stripe ulangach shu tugma to'lov sahifasiga olib boradi.
            </p>
        </div>
    </div>
</template>

<script>
import api from '../../api';
import AlertBox from '../../components/AlertBox.vue';
import { useAuthStore } from '../../stores/auth';

const PLANS = [
    {
        value: 'starter',
        label: 'Starter',
        price: 99,
        features: ['3 tagacha ochiq vakansiya', 'Arizachilarni ball bo\'yicha ko\'rish', 'Driver bazasi qidiruvi'],
    },
    {
        value: 'pro',
        label: 'Pro',
        price: 249,
        features: ['Cheksiz vakansiya', 'Kriteriya vaznlarini sozlash', 'Qo\'lda driver kiritish', 'Butun driver bazasi'],
    },
    {
        value: 'enterprise',
        label: 'Enterprise',
        price: 599,
        features: ['Pro dagi hammasi', 'Bir nechta recruiter', 'Prioritet qo\'llab-quvvatlash'],
    },
];

export default {
    name: 'CarrierBillingPage',

    components: { AlertBox },

    setup() {
        return { auth: useAuthStore() };
    },

    data() {
        return {
            carrier: null,
            plans: PLANS,
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
                const { data } = await api.get('/carrier/profile');
                this.carrier = data.carrier;
            } catch (e) {
                this.error = e.friendly;
            }
        },

        planLabel(value) {
            return PLANS.find((plan) => plan.value === value)?.label || 'Tarif tanlanmagan';
        },

        date(value) {
            return value ? new Date(value).toLocaleDateString('uz-UZ') : '—';
        },

        async subscribe(plan) {
            this.saving = true;
            this.error = null;
            this.notice = null;

            try {
                const { data } = await api.post('/carrier/subscription', { plan });
                this.carrier = data.carrier;
                await this.auth.refresh();
                this.notice = data.message;
            } catch (e) {
                this.error = e.friendly;
            } finally {
                this.saving = false;
            }
        },

        async cancel() {
            if (!window.confirm('Obuna bekor qilinsinmi? Driver bazasi yopiladi.')) return;

            try {
                const { data } = await api.delete('/carrier/subscription');
                this.carrier = data.carrier;
                await this.auth.refresh();
                this.notice = data.message;
            } catch (e) {
                this.error = e.friendly;
            }
        },
    },
};
</script>
