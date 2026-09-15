<template>
    <div>
        <div v-if="knockouts.length" class="mb-3">
            <div class="fw-semibold text-danger mb-1">Knockout — why this is a no</div>
            <span v-for="reason in knockouts" :key="reason" class="knockout-tag">{{ reason }}</span>
        </div>

        <div v-if="!rows.length" class="text-muted small">No score was calculated.</div>

        <table v-else class="table table-sm align-middle mb-0">
            <thead>
                <tr class="text-muted small">
                    <th>Criterion</th>
                    <th style="width: 120px">Value</th>
                    <th style="width: 150px">Points</th>
                    <th style="width: 70px" class="text-end">Weight</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.key">
                    <td>{{ row.label }}</td>
                    <td class="text-muted small">{{ displayValue(row.value) }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="criteria-bar flex-grow-1">
                                <span :style="{ width: row.points + '%' }"></span>
                            </div>
                            <span class="small text-muted" style="width: 2.5rem">{{ Math.round(row.points) }}</span>
                        </div>
                    </td>
                    <td class="text-end text-muted small">{{ row.weight }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    name: 'ScoreBreakdown',

    props: {
        rows: { type: Array, default: () => [] },
        knockouts: { type: Array, default: () => [] },
    },

    methods: {
        displayValue(value) {
            if (value === null || value === undefined || value === '') return '—';
            if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
            if (typeof value === 'boolean') return value ? 'Yes' : 'No';

            return value;
        },
    },
};
</script>
