<?php

declare(strict_types=1);

namespace intltime;

defined('is_running') or die('Not an entry point...');

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Emulates native strftime() (deprecated in PHP 8.1+) using intl and mbstring extensions.
 *
 * DISCLAIMER & COMPATIBILITY NOTICE:
 * ---------------------------------------------------------------------------------------
 * This function is NOT a byte-for-byte replacement for libc strftime().
 * Localized composite formats (%c, %x, %X, %r) rely on ICU data provided by PHP's 'intl'
 * extension and will differ from OS-level libc outputs depending on the system locale data.
 *
 * Canonical locale identifiers should be used (e.g., 'de_DE' or 'de-DE' rather than 'de_DE.UTF-8').
 *
 * COMPATIBILITY MATRIX:
 * ---------------------------------------------------------------------------------------
 * [FULLY SUPPORTED / STANDARD]
 *   %C, %d, %D, %e, %F, %H, %I, %j, %k, %l, %m, %M, %R, %s, %S, %T, %u, %w, %y, %Y, %z, %Z, %n, %t, %%
 * 
 * [STRICT MATHEMATICAL C-LIBRARY SEMANTICS]
 *   %g, %G, %V (ISO-8601 week logic)
 *   %U (Week count starting 1st Sunday; days before are week 00)
 *   %W (Week count starting 1st Monday; days before are week 00)
 * 
 * [BEST EFFORT / ICU LOCALE DEPENDENT]
 *   %a, %A, %b, %B, %h
 *   %c (Intl LONG date + SHORT time)
 *   %x (Intl SHORT date)
 *   %X (Intl MEDIUM time)
 *   %r (Forces 12-hour format 'hh:mm:ss a' via ICU)
 *   %p, %P (Uses ICU AM/PM designator via mbstring)
 * 
 * [UNKNOWN PLACEHOLDERS]
 *   Preserved as literals (e.g. '%q' => '%q').
 *
 * @param string $format The format string.
 * @param int|string|DateTimeInterface|null $timestamp (Optional) UNIX timestamp (int), date string, DateTime object, or null for now.
 * @param string|null $locale (Optional) BCP 47 / ICU locale string (e.g., 'de_DE'). Defaults to Locale::getDefault().
 * @param string|null $timezone (Optional) Target timezone. If null & DateTimeInterface passed, keeps object's timezone.
 * @return string The formatted date/time string.
 * @throws RuntimeException If required PHP extensions (intl, mbstring) are missing or formatting fails.
 * @throws InvalidArgumentException If the timestamp or timezone is invalid.
 */
function strftime(string $format, int|string|DateTimeInterface|null $timestamp = null, ?string $locale = null, ?string $timezone = null): string
{
    if (!extension_loaded('intl') || !extension_loaded('mbstring')) {
        throw new RuntimeException('Both "intl" and "mbstring" PHP extensions are required for this strftime polyfill.');
    }

    $timezoneWasProvided = ($timezone !== null);
    $targetTimezoneName = $timezone ?? date_default_timezone_get();

    try {
        $targetTimezone = new DateTimeZone($targetTimezoneName);
    } catch (\Exception $e) {
        throw new InvalidArgumentException(sprintf('Invalid timezone: "%s"', $targetTimezoneName), 0, $e);
    }

    // 1. Resolve timestamp strictly with DateTimeImmutable
    try {
        if ($timestamp === null) {
            $dateTime = new DateTimeImmutable('now', $targetTimezone);
        } elseif (is_int($timestamp)) {
            $dateTime = (new DateTimeImmutable('@' . $timestamp))->setTimezone($targetTimezone);
        } elseif (is_string($timestamp)) {
            $dateTime = new DateTimeImmutable($timestamp, $targetTimezone);
            if ($timezoneWasProvided) {
                $dateTime = $dateTime->setTimezone($targetTimezone);
            }
        } elseif ($timestamp instanceof DateTimeInterface) {
            $dateTime = DateTimeImmutable::createFromInterface($timestamp);
            if ($timezoneWasProvided) {
                $dateTime = $dateTime->setTimezone($targetTimezone);
            }
        } else {
            throw new InvalidArgumentException('$timestamp must be a valid date string, integer timestamp, DateTimeInterface, or null.');
        }
    } catch (\Exception $e) {
        throw new InvalidArgumentException('Invalid timestamp provided.', 0, $e);
    }

    $effectiveLocale = $locale ?? \Locale::getDefault();
    $effectiveTimezone = $dateTime->getTimezone()->getName();

    // 2. Persistent Static Formatter Cache
    /** @var array<string, IntlDateFormatter> $formatters */
    static $formatters = [];

    $getIntl = function (string $pattern = '', int $dateType = IntlDateFormatter::NONE, int $timeType = IntlDateFormatter::NONE) use (&$formatters, $effectiveLocale, $effectiveTimezone, $dateTime): IntlDateFormatter {
        $cacheKey = implode('|', [$effectiveLocale, $effectiveTimezone, $pattern, $dateType, $timeType]);
        if (!isset($formatters[$cacheKey])) {
            $formatters[$cacheKey] = new IntlDateFormatter(
                $effectiveLocale,
                $dateType,
                $timeType,
                $dateTime->getTimezone(),
                IntlDateFormatter::GREGORIAN,
                $pattern
            );
        }
        return $formatters[$cacheKey];
    };

    // Helper wrapper to handle formatting failures explicitly
    $formatIntl = static function (IntlDateFormatter $formatter, DateTimeInterface $date): string {
        $value = $formatter->format($date);
        if ($value === false) {
            throw new RuntimeException(sprintf('IntlDateFormatter failed to format date: %s', $formatter->getErrorMessage()));
        }
        return $value;
    };

    $result = '';
    $length = strlen($format);

    // 3. Main Format Processing Loop
    for ($i = 0; $i < $length; $i++) {
        if ($format[$i] === '%') {
            $i++;
            if ($i >= $length) {
                $result .= '%'; // Preserve trailing %
                break;
            }

            $specifier = $format[$i];

            switch ($specifier) {
                // Best-Effort Localized Formats
                case 'a': $result .= $formatIntl($getIntl('EEE'), $dateTime); break;
                case 'A': $result .= $formatIntl($getIntl('EEEE'), $dateTime); break;
                case 'b':
                case 'h': $result .= $formatIntl($getIntl('MMM'), $dateTime); break;
                case 'B': $result .= $formatIntl($getIntl('MMMM'), $dateTime); break;
                case 'c': $result .= $formatIntl($getIntl('', IntlDateFormatter::LONG, IntlDateFormatter::SHORT), $dateTime); break;
                case 'x': $result .= $formatIntl($getIntl('', IntlDateFormatter::SHORT, IntlDateFormatter::NONE), $dateTime); break;
                case 'X': $result .= $formatIntl($getIntl('', IntlDateFormatter::NONE, IntlDateFormatter::MEDIUM), $dateTime); break;
                case 'r': $result .= $formatIntl($getIntl('hh:mm:ss a'), $dateTime); break;

                // Standard Numeric Date & Time Formats
                case 'C': $result .= sprintf('%02d', (int)floor((int)$dateTime->format('Y') / 100)); break;
                case 'd': $result .= $dateTime->format('d'); break;
                case 'D': $result .= $dateTime->format('m/d/y'); break;
                case 'e': $result .= sprintf('% 2d', (int)$dateTime->format('j')); break;
                case 'F': $result .= $dateTime->format('Y-m-d'); break;
                case 'H': $result .= $dateTime->format('H'); break;
                case 'I': $result .= $dateTime->format('h'); break;
                case 'j': $result .= sprintf('%03d', (int)$dateTime->format('z') + 1); break;
                case 'k': $result .= sprintf('% 2d', (int)$dateTime->format('G')); break;
                case 'l': $result .= sprintf('% 2d', (int)$dateTime->format('g')); break;
                case 'm': $result .= $dateTime->format('m'); break;
                case 'M': $result .= $dateTime->format('i'); break;
                case 'p': $result .= mb_strtoupper($formatIntl($getIntl('a'), $dateTime), 'UTF-8'); break;
                case 'P': $result .= mb_strtolower($formatIntl($getIntl('a'), $dateTime), 'UTF-8'); break;
                case 'R': $result .= $dateTime->format('H:i'); break;
                case 's': $result .= $dateTime->getTimestamp(); break;
                case 'S': $result .= $dateTime->format('s'); break;
                case 'T': $result .= $dateTime->format('H:i:s'); break;
                case 'u': $result .= $dateTime->format('N'); break;
                case 'w': $result .= $dateTime->format('w'); break;
                case 'y': $result .= $dateTime->format('y'); break;
                case 'Y': $result .= $dateTime->format('Y'); break;
                case 'z': $result .= $dateTime->format('O'); break;
                case 'Z': $result .= $dateTime->format('T'); break;

                // ISO-8601 Weeks
                case 'g': $result .= substr($dateTime->format('o'), -2); break;
                case 'G': $result .= $dateTime->format('o'); break;
                case 'V': $result .= $dateTime->format('W'); break;

                // C-Library Semantics for Legacy Weeks (%U and %W)
                case 'U':
                case 'W':
                    $year = (int)$dateTime->format('Y');
                    $dayOfYear = (int)$dateTime->format('z'); // 0-indexed (0..365)
                    $jan1 = $dateTime->setDate($year, 1, 1);
                    $jan1DayOfWeek = (int)$jan1->format('w'); // 0 (Sun) to 6 (Sat)

                    if ($specifier === 'U') {
                        // First Sunday is week 01. Days prior are week 00.
                        $daysToFirstWeekStart = (7 - $jan1DayOfWeek) % 7;
                    } else { // 'W'
                        // First Monday is week 01. Days prior are week 00.
                        $jan1IsoDay = $jan1DayOfWeek === 0 ? 7 : $jan1DayOfWeek;
                        $daysToFirstWeekStart = (8 - $jan1IsoDay) % 7;
                    }

                    $week = $dayOfYear < $daysToFirstWeekStart
                        ? 0
                        : intdiv($dayOfYear - $daysToFirstWeekStart, 7) + 1;

                    $result .= sprintf('%02d', $week);
                    break;

                // Escape Sequences & Unknown Formats
                case 'n': $result .= "\n"; break;
                case 't': $result .= "\t"; break;
                case '%': $result .= '%'; break;

                default:
                    // Unknown specifiers are preserved as literal strings (e.g., %q -> %q)
                    $result .= '%' . $specifier;
                    break;
            }
        } else {
            $result .= $format[$i];
        }
    }

    return $result;
}