<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;

/** HTML-escape any value for safe output (XSS protection). */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

/** Absolute URL for an application path. */
function url(string $path = '/', array $query = []): string
{
    $base = (string) Config::get('app.url');
    $u = $base . '/' . ltrim($path, '/');
    return $query ? $u . (str_contains($u, '?') ? '&' : '?') . http_build_query($query) : $u;
}

/** Root-relative path (respects sub-directory installs), used for links and assets. */
function path(string $path = '/', array $query = []): string
{
    $basePath = rtrim((string) (parse_url((string) Config::get('app.url'), PHP_URL_PATH) ?? ''), '/');
    $p = $basePath . '/' . ltrim($path, '/');
    return $query ? $p . '?' . http_build_query($query) : $p;
}

/** Versioned asset URL for cache busting. */
function asset(string $file): string
{
    $full = CEX_ROOT . '/assets/' . ltrim($file, '/');
    $v = is_file($full) ? substr((string) filemtime($full), -8) : '1';
    return path('assets/' . ltrim($file, '/')) . '?v=' . $v;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Money formatting from minor units: 1000 EUR → "€10". */
function money(int $cents, string $currency = 'EUR'): string
{
    $symbols = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'XAF' => 'FCFA ', 'XOF' => 'FCFA '];
    $sym = $symbols[strtoupper($currency)] ?? strtoupper($currency) . ' ';
    $amount = $cents % 100 === 0 ? number_format($cents / 100, 0, '.', ',') : number_format($cents / 100, 2, '.', ',');
    return $sym . $amount;
}

/** Formats a UTC DB datetime for display. */
function fmt_date(?string $datetime, string $format = 'j F Y'): string
{
    if (!$datetime) {
        return '—';
    }
    $dt = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone((string) Config::get('app.timezone', 'UTC')))->format($format);
}

/** 4472 → "01:14:32" */
function fmt_duration(?int $seconds): string
{
    if ($seconds === null) {
        return '—';
    }
    $seconds = max(0, $seconds);
    return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

function fmt_score(mixed $score): string
{
    $s = (float) $score;
    return (floor($s) == $s ? number_format($s, 0) : number_format($s, 2)) . '%';
}

/** Inline SVG icon from the sprite. */
function icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . e($class) . '" aria-hidden="true" focusable="false"><use href="' . e(path('assets/images/icons.svg')) . '#' . e($name) . '"></use></svg>';
}

function difficulty_label(string $d): string
{
    return ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'][$d] ?? ucfirst($d);
}

function country_name(string $code): string
{
    return countries()[$code] ?? $code;
}

/** ISO 3166-1 alpha-2 country list. */
function countries(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra', 'AO' => 'Angola',
        'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AU' => 'Australia', 'AT' => 'Austria',
        'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
        'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BT' => 'Bhutan', 'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
        'CA' => 'Canada', 'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China',
        'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)', 'CR' => 'Costa Rica',
        'CI' => "Côte d'Ivoire", 'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark',
        'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt',
        'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini',
        'ET' => 'Ethiopia', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GA' => 'Gabon', 'GM' => 'Gambia',
        'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GD' => 'Grenada', 'GT' => 'Guatemala',
        'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HN' => 'Honduras', 'HK' => 'Hong Kong',
        'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq',
        'IE' => 'Ireland', 'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan',
        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos',
        'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein',
        'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MO' => 'Macao', 'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia',
        'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania',
        'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia',
        'ME' => 'Montenegro', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru',
        'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria',
        'KP' => 'North Korea', 'MK' => 'North Macedonia', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau',
        'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru',
        'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RO' => 'Romania',
        'RU' => 'Russia', 'RW' => 'Rwanda', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent and the Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
        'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia',
        'ZA' => 'South Africa', 'KR' => 'South Korea', 'SS' => 'South Sudan', 'ES' => 'Spain', 'LK' => 'Sri Lanka',
        'SD' => 'Sudan', 'SR' => 'Suriname', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Türkiye', 'TM' => 'Turkmenistan', 'TV' => 'Tuvalu',
        'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
        'US' => 'United States', 'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City',
        'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    ];
    return $c;
}

/** Icon used for a certification card (falls back to a generic award icon). */
function cert_icon(string $code): string
{
    return [
        'SOC' => 'radar', 'NETSEC' => 'network', 'ETH' => 'crosshair', 'CLOUD' => 'cloud',
        'DFIR' => 'fingerprint', 'GRC' => 'scale', 'ARCH' => 'layers',
    ][strtoupper($code)] ?? 'award';
}

/** Pagination links preserving the current query string. */
function paginate_links(int $page, int $pages): string
{
    if ($pages <= 1) {
        return '';
    }
    $query = $_GET;
    $html = '<nav class="pagination" aria-label="Pagination">';
    $window = array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], fn ($p) => $p >= 1 && $p <= $pages));
    sort($window);
    $prev = 0;
    foreach ($window as $p) {
        if ($prev && $p - $prev > 1) {
            $html .= '<span>…</span>';
        }
        $query['page'] = $p;
        $html .= $p === $page
            ? '<span class="is-current" aria-current="page">' . $p . '</span>'
            : '<a href="?' . e(http_build_query($query)) . '">' . $p . '</a>';
        $prev = $p;
    }
    return $html . '</nav>';
}
