<template>
    <span class="score-pill" :class="disqualified ? 'tier-out' : `tier-${tier || 'D'}`" :title="title">
        {{ disqualified ? 'OUT' : score }}
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
                ? 'Failed a knockout rule'
                : `Score ${this.score} of 100 (grade ${this.tier || '—'})`;
        },
    },
};
</script>
