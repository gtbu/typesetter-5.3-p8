<?php

declare(strict_types=1);

namespace intltime;

defined('is_running') or die('Not an entry point...');

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use Locale;

/**
 * Drop-in replacement for strftime(), which is deprecated since PHP 8.1,
 * implemented on top of the intl extension.
 *
 * Compatibility with native strftime():
 *  - Same call signatures: strftime($format) / strftime($format, $timestamp).
 *    DateTimeInterface timestamps are additionally accepted (superset).
 *  - Returns string|false and never throws – failures return false like the original.
 *  - Honors LC_TIME (setlocale()) for the default locale, then falls back to
 *    Locale::getDefault(), then 'en' (the C-locale equivalent) – matching native output.
 *  - %c / %x / %X use fixed layouts that exactly match the C/en_US output of
 *    native strftime(); with other LC_TIME locales they are approximations.
 *  - Leap seconds (%S = 60) cannot be represented by DateTime and are not supported.
 *
 * @param string $format The format string (see https://www.php.net/manual/en/function.strftime.php).
 * @param int|string|DateTimeInterface|null $timestamp Optional timestamp; null = now.
 *        Numeric values/strings are treated as UNIX timestamps, other strings as date-time strings.
 * @param string|null $locale Optional ICU locale; default derived from LC_TIME / Locale::getDefault().
 * @param string|null $timezone Optional timezone identifier; default is the PHP default timezone.
 * @return string|false Formatted string, or false on failure (invalid format/timestamp/locale).
 */
function strftime(
    string $format,
    int|string|DateTimeInterface|null $timestamp = null,
    ?string $locale = null,
    ?string $timezone = null
): string|false {

    if (!extension_loaded('intl')) {
        trigger_error('The intl extension is not loaded.', E_USER_WARNING);
        return false;
    }

    /* ---------------------------------------------------------------------
     * 1. Normalize the timestamp to a MUTABLE DateTime.
     *    - Never mutate the caller's object (clone).
     *    - Convert DateTimeImmutable, because setTimezone() on an immutable
     *      returns a new instance instead of modifying in place.
     * ------------------------------------------------------------------- */
    try {
        if ($timestamp === null) {
            $dateTime = new DateTime();
        } elseif ($timestamp instanceof DateTimeInterface) {
            if ($timestamp instanceof DateTimeImmutable) {
                // Preserve the exact instant (microseconds are irrelevant to strftime).
                $dateTime = DateTime::createFromInterface($timestamp);
            } else {
                $dateTime = clone $timestamp;
            }
        } elseif (is_numeric($timestamp)) {
            $dateTime = new DateTime('@' . (int) $timestamp);
        } else {
            $dateTime = new DateTime((string) $timestamp);
        }
    } catch (\Throwable $e) {
        // Native strftime() returns false on failure instead of throwing.
        error_log('intltime\strftime(): invalid timestamp: ' . $e->getMessage());
        return false;
    }

    /* ---------------------------------------------------------------------
     * 2. Resolve locale and timezone.
     *    Native strftime() is driven by LC_TIME (setlocale); mirror that first.
     * ------------------------------------------------------------------- */
    if ($locale === null || $locale === '') {
        $lcTime = setlocale(\LC_TIME, 0); // query only – does not change the locale
        $lcToken = is_string($lcTime) ? strtok($lcTime, ':@.') : ''; // "de_DE:de_DE@euro" -> "de_DE"
        if ($lcToken !== '' && strtoupper($lcToken) === 'C' || strtoupper($lcToken) === 'POSIX') {
            $locale = 'en'; // C/POSIX locale uses English names – closest ICU equivalent
        } elseif ($lcToken !== '') {
            $locale = $lcToken;
        } else {
            $locale = Locale::getDefault();
        }
        if ($locale === '') {
            $locale = 'en'; // matches native strftime() default output
        }
    }

    try {
        $timeZoneObject = new DateTimeZone(
            ($timezone !== null && $timezone !== '') ? $timezone : date_default_timezone_get()
        );
    } catch (\Throwable $e) {
        error_log('intltime\strftime(): invalid timezone: ' . $e->getMessage());
        return false;
    }

    // Safe now that $dateTime is always a mutable DateTime.
    $dateTime = $dateTime->setTimezone($timeZoneObject);

    /* ---------------------------------------------------------------------
     * 3. Translate the strftime format into an ICU pattern.
     *
     *    Literal text (including computed values) MUST be single-quoted in the
     *    ICU pattern, otherwise A-Z/a-z characters would be parsed as pattern
     *    letters (e.g. "PDT" from %Z or "pm" from %P).
     * ------------------------------------------------------------------- */
    $quote = static function (string $text): string {
        return $text === '' ? '' : "'" . str_replace("'", "''", $text) . "'";
    };

    // Specifiers with a direct, locale-independent ICU pattern.
    $patternMap = [
        '%a' => 'EEE',   // Abbreviated weekday name
        '%A' => 'EEEE',  // Full weekday name
        '%b' => 'MMM',   // Abbreviated month name
        '%B' => 'MMMM',  // Full month name
        '%c' => 'EEE MMM dd HH:mm:ss yyyy', // matches native C/en_US layout (locale-dependent in native)
        '%D' => 'MM/dd/yy',     // MM/DD/YY
        '%F' => 'yyyy-MM-dd',   // ISO 8601 date
        '%h' => 'MMM',          // Same as %b
        '%H' => 'HH',           // Hour 00-23
        '%I' => 'hh',           // Hour 01-12
        '%m' => 'MM',           // Month 01-12
        '%M' => 'mm',           // Minute 00-59
        '%p' => 'a',            // AM/PM
        '%r' => 'hh:mm:ss a',   // 12-hour time with AM/PM
        '%R' => 'HH:mm',        // 24-hour HH:MM
        '%S' => 'ss',           // Second 00-59 (leap second 60 not representable)
        '%T' => 'HH:mm:ss',     // 24-hour time
        '%x' => 'MM/dd/yy',     // matches native C/en_US layout (locale-dependent in native)
        '%X' => 'HH:mm:ss',     // matches native C/en_US layout (locale-dependent in native)
        '%y' => 'yy',           // Year without century
        '%Y' => 'yyyy',         // Year with century
    ];

    $icuFormat = '';
    $literal   = '';
    $len       = strlen($format);

    for ($i = 0; $i < $len; $i++) {
        if ($format[$i] !== '%') {
            $literal .= $format[$i];
            continue;
        }

        // Flush pending literal text (quoted) before appending a pattern.
        $icuFormat .= $quote($literal);
        $literal    = '';

        $i++;
        if (!isset($format[$i])) {
            // Trailing '%': glibc/PHP leave it as a literal percent sign.
            $icuFormat .= "'%'";
            break;
        }

        $key = '%' . $format[$i];

        if (isset($patternMap[$key])) {
            $icuFormat .= $patternMap[$key]; // pure pattern – must stay unquoted
            continue;
        }

        switch ($key) {
            case '%%': // Literal percent sign
                $icuFormat .= "'%'";
                break;

            case '%C': // Century (year / 100, truncated)
                $icuFormat .= $quote((string) intdiv((int) $dateTime->format('Y'), 100));
                break;

            case '%e': // Day of month, space-padded (" 1".."31")
                $icuFormat .= $quote(sprintf('%2d', (int) $dateTime->format('j')));
                break;

            case '%g': // ISO week-based year without century
            case '%G': { // ISO week-based year with century
                // The ISO week-year is the calendar year containing the Thursday of that week.
                $thursday = new DateTime($dateTime->format('Y-m-d'));
                $offset   = 4 - (int) $dateTime->format('N'); // days until Thursday (-3..+3)
                if ($offset !== 0) {
                    $thursday->modify($offset . ' days');
                }
                $isoYear = (int) $thursday->format('Y');
                $icuFormat .= $quote(
                    $key === '%G' ? (string) $isoYear : sprintf('%02d', $isoYear % 100)
                );
                break;
            }

            case '%j': // Day of year, zero-padded (001-366)
                $icuFormat .= $quote(sprintf('%03d', (int) $dateTime->format('z') + 1));
                break;

            case '%k': // Hour 24h, space-padded (" 0".."23")
                $icuFormat .= $quote(sprintf('%2d', (int) $dateTime->format('G')));
                break;

            case '%l': // Hour 12h, space-padded (" 1".."12")
                $icuFormat .= $quote(sprintf('%2d', (int) $dateTime->format('g')));
                break;

            case '%n': // Newline
                $icuFormat .= $quote("\n");
                break;

            case '%P': // Lowercase am/pm
                $icuFormat .= $quote(strtolower($dateTime->format('a')));
                break;

            case '%s': // Seconds since the Unix epoch
                $icuFormat .= $quote((string) $dateTime->getTimestamp());
                break;

            case '%t': // Tab
                $icuFormat .= $quote("\t");
                break;

            case '%u': // ISO weekday number, Monday = 1 .. Sunday = 7
                $icuFormat .= $quote($dateTime->format('N'));
                break;

            case '%U': { // Week number, Sunday-first; days before the first Sunday are week 0
                $z    = (int) $dateTime->format('z');   // 0-based day of year
                $dowS = (int) $dateTime->format('w');  // 0 = Sunday .. 6 = Saturday
                $icuFormat .= $quote(sprintf('%02d', intdiv($z - $dowS + 7, 7)));
                break;
            }

            case '%V': // ISO-8601 week number (PHP's 'W' is exactly that definition)
                $icuFormat .= $quote(sprintf('%02d', (int) $dateTime->format('W')));
                break;

            case '%w': // Weekday number, Sunday = 0 .. Saturday = 6
                $icuFormat .= $quote($dateTime->format('w'));
                break;

            case '%W': { // Week number, Monday-first; days before the first Monday are week 0
                $z    = (int) $dateTime->format('z');     // 0-based day of year
                $dowM = (int) $dateTime->format('N') - 1; // Mon = 0 .. Sun = 6
                $icuFormat .= $quote((string) intdiv($z - $dowM + 7, 7));
                break;
            }

            case '%z': // Numeric timezone offset without colon (e.g. "-0800")
                $icuFormat .= $quote(str_replace(':', '', $dateTime->format('P')));
                break;

            case '%Z': // Timezone abbreviation (e.g. "UTC", "PDT")
                $icuFormat .= $quote($dateTime->format('T'));
                break;

            default:
                // Unknown conversion specifier – native strftime() returns false here too.
                return false;
        }
    }

    $icuFormat .= $quote($literal);

    if ($icuFormat === '') {
        return '';
    }

    /* ---------------------------------------------------------------------
     * 4. Format with IntlDateFormatter.
     * ------------------------------------------------------------------- */
    try {
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::FULL,   // ignored while a pattern is supplied – kept for clarity
            IntlDateFormatter::FULL,
            $timeZoneObject,
            IntlDateFormatter::GREGORIAN,
            $icuFormat
        );
    } catch (\Throwable $e) {
        error_log('intltime\strftime(): invalid locale or ICU pattern: ' . $e->getMessage());
        return false;
    }

    if ($formatter === null || $formatter === false) { // failure mode on older PHP versions
        return false;
    }

    try {
        $result = $formatter->format($dateTime);
    } catch (\Throwable $e) {
        error_log('intltime\strftime(): formatting failed: ' . $e->getMessage());
        return false;
    }

    if ($result === false) {
        error_log('intltime\strftime(): IntlDateFormatter error: ' . $formatter->getErrorName());
        return false;
    }

    return $result;
}
