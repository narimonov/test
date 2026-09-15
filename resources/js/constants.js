/** Butun ilova bo'ylab ishlatiladigan ro'yxatlar — bitta joyda. */

export const US_STATES = [
    'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL',
    'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT',
    'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI',
    'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
];

export const ENDORSEMENTS = [
    { value: 'hazmat', label: 'Hazmat' },
    { value: 'tanker', label: 'Tanker' },
    { value: 'doubles', label: 'Doubles/Triples' },
    { value: 'passenger', label: 'Passenger' },
    { value: 'twic', label: 'TWIC' },
];

export const EQUIPMENT = [
    { value: 'dry_van', label: 'Dry Van' },
    { value: 'reefer', label: 'Reefer' },
    { value: 'flatbed', label: 'Flatbed' },
    { value: 'tanker', label: 'Tanker' },
    { value: 'stepdeck', label: 'Step Deck' },
    { value: 'car_hauler', label: 'Car Hauler' },
    { value: 'box_truck', label: 'Box Truck' },
];

export const ROUTE_TYPES = [
    { value: 'otr', label: 'OTR' },
    { value: 'regional', label: 'Regional' },
    { value: 'local', label: 'Local' },
    { value: 'dedicated', label: 'Dedicated' },
];

export const DRIVER_TYPES = [
    { value: 'company_driver', label: 'Company Driver' },
    { value: 'owner_operator', label: 'Owner Operator' },
    { value: 'lease_purchase', label: 'Lease Purchase' },
];

export const WORK_AUTH = [
    { value: 'us_citizen', label: 'US Citizen' },
    { value: 'green_card', label: 'Green Card' },
    { value: 'ead', label: 'EAD / Work Permit' },
    { value: 'other', label: 'Boshqa' },
];

export const SAP_STATUSES = [
    { value: 'none', label: 'Yo\'q' },
    { value: 'in_program', label: 'Dasturda' },
    { value: 'completed', label: 'Tugatgan' },
];

export const APPLICATION_STATUSES = [
    { value: 'applied', label: 'Yangi ariza', variant: 'secondary' },
    { value: 'screening', label: 'Tekshiruvda', variant: 'info' },
    { value: 'interview', label: 'Suhbat', variant: 'primary' },
    { value: 'hired', label: 'Ishga olindi', variant: 'success' },
    { value: 'rejected', label: 'Rad etildi', variant: 'danger' },
];

export const DRIVER_STATUSES = [
    { value: 'new', label: 'Yangi', variant: 'secondary' },
    { value: 'contacted', label: 'Bog\'lanildi', variant: 'info' },
    { value: 'screening', label: 'Tekshiruvda', variant: 'primary' },
    { value: 'hired', label: 'Ishga olindi', variant: 'success' },
    { value: 'rejected', label: 'Rad etildi', variant: 'danger' },
];

export function labelFor(list, value) {
    return list.find((item) => item.value === value)?.label || value || '—';
}

export function formatPay(cents, unit) {
    if (!cents) return null;

    if (unit === 'per_mile') return `$${(cents / 100).toFixed(2)}/mile`;
    if (unit === 'percentage') return `${cents / 100}%`;

    return `$${Math.round(cents / 100).toLocaleString()}/hafta`;
}
