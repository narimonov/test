<?php

namespace App\Services\Fmcsa;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FMCSA QCMobile API (https://mobile.fmcsa.dot.gov/QCDevsite/).
 *
 * QCMobile safety/status ma'lumotini beradi, lekin telefon/email qaytarmaydi.
 * Shuning uchun kontakt ma'lumoti Company Census dataset'idan (DOT Open Data,
 * Socrata) qo'shimcha so'rov bilan olinadi — tasdiqlash kodi o'sha kontaktga
 * yuboriladi.
 *
 * Kerakli sozlamalar (.env):
 *   FMCSA_WEB_KEY=...            QCDevsite'da ro'yxatdan o'tib olinadi (bepul)
 *   FMCSA_CENSUS_DATASET=...     data.transportation.gov dagi dataset id
 *   FMCSA_CENSUS_APP_TOKEN=...   ixtiyoriy, rate limit uchun
 */
class QcMobileFmcsaClient implements FmcsaClient
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function findByDotNumber(string $dotNumber): ?CarrierRecord
    {
        $dotNumber = preg_replace('/\D+/', '', $dotNumber);

        if ($dotNumber === '') {
            return null;
        }

        $payload = $this->get("carriers/{$dotNumber}");

        return $this->toRecord($this->firstCarrier($payload));
    }

    public function findByDocketNumber(string $docketNumber): ?CarrierRecord
    {
        $docketNumber = preg_replace('/\D+/', '', $docketNumber);

        if ($docketNumber === '') {
            return null;
        }

        $payload = $this->get("carriers/docket-number/{$docketNumber}");

        return $this->toRecord($this->firstCarrier($payload));
    }

    // ------------------------------------------------------------------

    protected function get(string $path): ?array
    {
        $response = Http::timeout($this->config['timeout'] ?? 15)
            ->retry(2, 500)
            ->get(rtrim($this->config['base_url'], '/') . '/' . $path, [
                'webKey' => $this->config['web_key'],
            ]);

        if (! $response->successful()) {
            Log::warning('FMCSA lookup failed', ['path' => $path, 'status' => $response->status()]);

            return null;
        }

        return $response->json();
    }

    /**
     * QCMobile "content" ni ba'zan obyekt, ba'zan massiv qilib qaytaradi.
     */
    protected function firstCarrier(?array $payload): ?array
    {
        $content = $payload['content'] ?? null;

        if (! $content) {
            return null;
        }

        if (isset($content['carrier'])) {
            return $content['carrier'];
        }

        $first = is_array($content) ? reset($content) : null;

        return is_array($first) ? ($first['carrier'] ?? $first) : null;
    }

    protected function toRecord(?array $carrier): ?CarrierRecord
    {
        if (! $carrier) {
            return null;
        }

        $dotNumber = (string) ($carrier['dotNumber'] ?? '');
        $contact = $dotNumber ? $this->lookupContact($dotNumber) : [];

        return CarrierRecord::fromArray([
            'dotNumber'        => $dotNumber ?: null,
            'docketNumber'     => $carrier['docketNumber'] ?? null,
            'legalName'        => $carrier['legalName'] ?? null,
            'dbaName'          => $carrier['dbaName'] ?? null,
            'statusCode'       => $carrier['statusCode'] ?? null,
            'allowedToOperate' => ($carrier['allowedToOperate'] ?? 'N') === 'Y',
            'phone'            => $contact['phone'] ?? null,
            'email'            => $contact['email'] ?? null,
            'city'             => $carrier['phyCity'] ?? null,
            'state'            => $carrier['phyState'] ?? null,
            'raw'              => $carrier,
        ]);
    }

    /**
     * Company Census dataset'idan telefon/email. Topilmasa bo'sh massiv —
     * bu holda kompaniya qo'lda tekshiruvga tushadi.
     */
    protected function lookupContact(string $dotNumber): array
    {
        if (empty($this->config['census_dataset'])) {
            return [];
        }

        $request = Http::timeout($this->config['timeout'] ?? 15);

        if (! empty($this->config['census_app_token'])) {
            $request = $request->withHeaders(['X-App-Token' => $this->config['census_app_token']]);
        }

        $response = $request->get(
            rtrim($this->config['census_base_url'], '/') . '/resource/' . $this->config['census_dataset'] . '.json',
            ['dot_number' => $dotNumber, '$limit' => 1]
        );

        if (! $response->successful()) {
            Log::warning('FMCSA census lookup failed', ['dot' => $dotNumber, 'status' => $response->status()]);

            return [];
        }

        $row = $response->json()[0] ?? null;

        if (! $row) {
            return [];
        }

        return [
            'phone' => $row['telephone'] ?? $row['phone'] ?? null,
            'email' => $row['email_address'] ?? $row['email'] ?? null,
        ];
    }
}
