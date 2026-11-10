<?php
declare(strict_types=1);

/*
 * Visitor currency (display only).
 *
 * Prices are stored in INR or USD. Every price shown on the public site is wrapped by fx()
 * in a <span data-fx="…" data-fx-from="INR|USD">; assets/js/app.js converts it to the
 * visitor's local currency (country from Cloudflare's CF-IPCountry header, else browser
 * timezone / language) using the rates published in <meta name="fx">.
 * Payments are still charged in INR or USD by Razorpay — conversions are approximate.
 */

const FX_SOURCE_URL = 'https://open.er-api.com/v6/latest/USD';
const FX_MAX_AGE = 43200; // refresh rates every 12 hours

/** Units of each currency per 1 USD (fallback when the rates API is unreachable; Nov 2026). */
const FX_FALLBACK = [
    'USD' => 1, 'INR' => 93.93, 'EUR' => 0.8924, 'GBP' => 0.7447, 'AED' => 3.6725, 'SAR' => 3.75, 'QAR' => 3.64, 'KWD' => 0.3106,
    'BHD' => 0.376, 'OMR' => 0.3845, 'CAD' => 1.4113, 'AUD' => 1.448, 'NZD' => 1.7775, 'SGD' => 1.2795, 'MYR' => 4.0594,
    'JPY' => 158.02, 'CNY' => 6.6866, 'HKD' => 7.8423, 'KRW' => 1344.79, 'TWD' => 32.78, 'THB' => 33.48, 'IDR' => 18083.72,
    'PHP' => 63.16, 'VND' => 25527.69, 'NPR' => 150.28, 'BDT' => 124.26, 'LKR' => 332.53, 'PKR' => 276.54, 'CHF' => 0.8197,
    'SEK' => 9.8115, 'NOK' => 9.4056, 'DKK' => 6.6762, 'PLN' => 3.9138, 'CZK' => 21.72, 'HUF' => 323.41, 'RON' => 4.7689,
    'TRY' => 50.20, 'ILS' => 3.1623, 'ZAR' => 16.41, 'NGN' => 1349.07, 'KES' => 129.93, 'EGP' => 54.07, 'BRL' => 5.5122,
    'MXN' => 18.57, 'ARS' => 1545.40, 'CLP' => 976.34, 'COP' => 3242.13, 'PEN' => 3.4215, 'RUB' => 82.24, 'UAH' => 45.44,
];

/** ISO country → currency for every currency above (others fall back to USD). */
const FX_COUNTRY_CURRENCY = [
    'IN' => 'INR', 'US' => 'USD', 'GB' => 'GBP', 'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR',
    'CA' => 'CAD', 'AU' => 'AUD', 'NZ' => 'NZD', 'SG' => 'SGD', 'MY' => 'MYR', 'JP' => 'JPY', 'CN' => 'CNY', 'HK' => 'HKD', 'KR' => 'KRW',
    'TW' => 'TWD', 'TH' => 'THB', 'ID' => 'IDR', 'PH' => 'PHP', 'VN' => 'VND', 'NP' => 'NPR', 'BD' => 'BDT', 'LK' => 'LKR', 'PK' => 'PKR',
    'CH' => 'CHF', 'LI' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN', 'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON',
    'TR' => 'TRY', 'IL' => 'ILS', 'ZA' => 'ZAR', 'NG' => 'NGN', 'KE' => 'KES', 'EG' => 'EGP', 'BR' => 'BRL', 'MX' => 'MXN', 'AR' => 'ARS',
    'CL' => 'CLP', 'CO' => 'COP', 'PE' => 'PEN', 'RU' => 'RUB', 'UA' => 'UAH',
    // Euro area
    'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'IE' => 'EUR', 'PT' => 'EUR',
    'FI' => 'EUR', 'GR' => 'EUR', 'LU' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'EE' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'CY' => 'EUR',
    'MT' => 'EUR', 'HR' => 'EUR', 'MC' => 'EUR', 'SM' => 'EUR', 'VA' => 'EUR', 'AD' => 'EUR', 'ME' => 'EUR', 'XK' => 'EUR',
];

/** Rates per 1 USD, refreshed from FX_SOURCE_URL at most every 12 h and cached in settings. */
function fx_rates(): array
{
    static $rates = null;
    if ($rates !== null) {
        return $rates;
    }
    $rates = FX_FALLBACK;
    $cached = json_decode(setting('fx_rates'), true);
    if (is_array($cached)) {
        foreach ($cached as $c => $r) {
            if (isset(FX_FALLBACK[$c]) && is_numeric($r) && $r > 0) {
                $rates[$c] = (float)$r;
            }
        }
    }
    $age = time() - (int)setting('fx_rates_at', '0');
    if ($age > FX_MAX_AGE && function_exists('curl_init')) {
        try {
            setting_set('fx_rates_at', (string)time()); // claim the refresh first so concurrent requests don't all fetch
            $fresh = fx_fetch_rates();
            if ($fresh) {
                $rates = $fresh + $rates;
                setting_set('fx_rates', json_encode($fresh));
            }
        } catch (Throwable $e) {
            error_log('fx refresh failed: ' . $e->getMessage());
        }
    }
    return $rates;
}

function fx_fetch_rates(): array
{
    $ch = curl_init(FX_SOURCE_URL);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => false]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $d = is_string($raw) && $code === 200 ? json_decode($raw, true) : null;
    if (!is_array($d) || ($d['result'] ?? '') !== 'success' || !is_array($d['rates'] ?? null) || empty($d['rates']['INR'])) {
        return [];
    }
    $out = [];
    foreach (FX_FALLBACK as $c => $_) {
        $r = $d['rates'][$c] ?? null;
        if (is_numeric($r) && $r > 0) {
            $out[$c] = (float)$r;
        }
    }
    return $out;
}

/** Visitor's ISO country from Cloudflare (empty when not behind Cloudflare). */
function fx_country(): string
{
    $c = strtoupper((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    return preg_match('/^[A-Z]{2}$/', $c) && !in_array($c, ['XX', 'T1'], true) ? $c : '';
}

/** Display currency chosen for this visitor on the server ('' = let the browser decide). */
function fx_visitor_currency(): string
{
    $ck = strtoupper((string)($_COOKIE['da_fx'] ?? ''));
    if (isset(FX_FALLBACK[$ck])) {
        return $ck;
    }
    $country = fx_country();
    return $country === '' ? '' : (FX_COUNTRY_CURRENCY[$country] ?? 'USD');
}

function fx_convert(float $amount, string $from, string $to): float
{
    $r = fx_rates();
    if (empty($r[$from]) || empty($r[$to])) {
        return $amount;
    }
    return $amount / $r[$from] * $r[$to];
}

/** JSON for <meta name="fx">: rates + what the server knows about the visitor. */
function fx_meta_json(): string
{
    $rates = array_map(static fn($v) => round((float)$v, 6), fx_rates());
    return (string)json_encode(['rates' => $rates, 'cur' => fx_visitor_currency(), 'country' => fx_country(), 'map' => FX_COUNTRY_CURRENCY], JSON_UNESCAPED_SLASHES);
}

/**
 * Escape a price text and wrap every ₹ / $ amount in it so the browser can show it in the
 * visitor's currency: "₹3,500 – ₹9,999+" → two convertible spans. Returns safe HTML.
 * $attrs is added to every span (e.g. an international override, see service_price_html()).
 */
function fx(?string $text, string $attrs = ''): string
{
    $html = e((string)$text);
    return preg_replace_callback('/(₹|US\$|\$)\s?(\d+(?:,\d+)*(?:\.\d{1,2})?)/u', static function ($m) use ($attrs) {
        $amount = (float)str_replace(',', '', $m[2]);
        if ($amount <= 0) {
            return $m[0];
        }
        $from = $m[1] === '₹' ? 'INR' : 'USD';
        return '<span data-fx="' . $amount . '" data-fx-from="' . $from . '"' . ($attrs !== '' ? ' ' . $attrs : '') . '>' . $m[0] . '</span>';
    }, $html) ?? $html;
}

/* ---------------- Service prices ---------------- */

function service_currency(array $s): string
{
    return strtoupper((string)($s['price_currency'] ?? 'INR')) === 'USD' ? 'USD' : 'INR';
}

/** Whole-unit amount in the service's own currency ("₹3,500" / "$11"). */
function service_money(int $n, string $currency): string
{
    return $currency === 'USD' ? '$' . number_format($n) : inr($n);
}

/**
 * Main service price as convertible HTML. When an INR service also has a USD label
 * (e.g. "$199 one-time"), international visitors see that USD price converted instead
 * of a conversion of the INR amount.
 */
function service_price_html(array $s): string
{
    $attrs = '';
    if (service_currency($s) === 'INR' && empty($s['price_to']) && preg_match('/\$\s?(\d+(?:,\d+)*(?:\.\d{1,2})?)/', (string)($s['price_usd'] ?? ''), $m)) {
        $attrs = 'data-fx-intl="' . (float)str_replace(',', '', $m[1]) . '"';
    }
    return fx(price_text($s), $attrs);
}

/**
 * Online booking amounts in minor units per currency.
 * INR: the ₹ booking price, else the USD price converted at today's rate (whole rupees).
 * USD: only when a $ booking price is set (international cards via Razorpay).
 */
function service_buy_amounts(array $s): array
{
    if (!(int)($s['is_buyable'] ?? 0)) {
        return [];
    }
    $out = [];
    $usd = (int)($s['buy_usd_cents'] ?? 0);
    $inr = (int)($s['buy_price'] ?? 0);
    if ($inr > 0) {
        $out['INR'] = $inr * 100;
    } elseif ($usd > 0) {
        $out['INR'] = (int)ceil(fx_convert($usd / 100, 'USD', 'INR')) * 100;
    }
    if ($usd > 0) {
        $out['USD'] = $usd;
    }
    return $out;
}

function service_buyable(array $s): bool
{
    return service_buy_amounts($s) !== [];
}

/** "₹3,500" or "$11 / ₹1,034" — for admin tables and the AI assistant. */
function service_buy_text(array $s): string
{
    $a = service_buy_amounts($s);
    $parts = [];
    foreach (['USD', 'INR'] as $c) {
        if (isset($a[$c])) {
            $parts[] = money($a[$c], $c);
        }
    }
    return implode(' / ', $parts);
}
