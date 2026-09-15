<?php

namespace App\Services\Fmcsa;

/**
 * Lokal ishlab chiqish va testlar uchun. FMCSA_DRIVER=fake bo'lganda ishlaydi,
 * hech qanday tashqi so'rov yubormaydi.
 *
 * Qoida: DOT raqami 9 bilan boshlansa — nofaol kompaniya, 0 bilan boshlansa —
 * topilmadi. Qolganlari faol.
 */
class FakeFmcsaClient implements FmcsaClient
{
    /** @var array<string, CarrierRecord> */
    protected $overrides = [];

    public function stub(string $number, ?CarrierRecord $record): void
    {
        $this->overrides[$number] = $record;
    }

    public function findByDotNumber(string $dotNumber): ?CarrierRecord
    {
        return $this->resolve($dotNumber, 'dot');
    }

    public function findByDocketNumber(string $docketNumber): ?CarrierRecord
    {
        return $this->resolve($docketNumber, 'mc');
    }

    protected function resolve(string $number, string $kind): ?CarrierRecord
    {
        $number = preg_replace('/\D+/', '', $number);

        if (array_key_exists($number, $this->overrides)) {
            return $this->overrides[$number];
        }

        if ($number === '' || str_starts_with($number, '0')) {
            return null;
        }

        $inactive = str_starts_with($number, '9');

        return CarrierRecord::fromArray([
            'dotNumber'        => $kind === 'dot' ? $number : '1' . $number,
            'docketNumber'     => $kind === 'mc' ? $number : null,
            'legalName'        => 'TEST CARRIER ' . $number . ' LLC',
            'dbaName'          => null,
            'statusCode'       => $inactive ? 'I' : 'A',
            'allowedToOperate' => ! $inactive,
            'phone'            => '+1555' . str_pad(substr($number, -7), 7, '0', STR_PAD_LEFT),
            'email'            => 'dispatch+' . $number . '@fmcsa-test.example',
            'city'             => 'Chicago',
            'state'            => 'IL',
        ]);
    }
}
