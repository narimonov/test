<template>
    <span class="score-pill" :class="disqualified ? 'tier-out' : `tier-${tier || 'D'}`" :title="title">
        {{ disqualified ? 'RAD' : score }}
        <small v-if="!disqualified && tier" class="ms-1 fw-normal">{{ tier }}</small>
    </span>
</template>

<script>
export default {
    name: 'ScorePill',

    props: {
        score: { type: Number, default: 0 },
        tier: { type: String, default: null },
        disqualified: { type: Boolean, default: false },
    },

    computed: {
        title() {
            return this.disqualified
                ? 'Knockout kriteriyadan o\'tmadi'
                : `Ball: ${this.score} / 100 (${this.tier || '—'} daraja)`;
        },
    },
};
</script>
