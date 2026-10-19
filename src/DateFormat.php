<?php

declare(strict_types=1);

namespace Gadget\Ldap;

final class DateFormat
{
    /** @var int */
    public const UTC_TIMESTAMP_START = 19700101000000;

    /** @var int */
    public const UTC_TIMESTAMP_END = 20991231235959;

    /** @var int */
    public const TIME_INTERVAL_OFFSET = 11644473600;


    /**
     * @param int $utcTimestamp
     * @param non-empty-string|null $localTimezone
     * @return \DateTimeInterface
     */
    public static function formatUTCTimestamp(
        int $utcTimestamp,
        string|null $localTimezone = null
    ): \DateTimeInterface {
        if ($utcTimestamp < self::UTC_TIMESTAMP_START || $utcTimestamp > self::UTC_TIMESTAMP_END) {
            throw new LdapException(["Invalid timestamp: %d", [$utcTimestamp]]);
        }

        $year = intval(floor($utcTimestamp / 10000000000));
        $month = intval(floor($utcTimestamp / 100000000) % 100);
        $day = intval(floor($utcTimestamp / 1000000) % 100);
        $hour = intval(floor($utcTimestamp / 10000) % 100);
        $minute = intval(floor($utcTimestamp / 100) % 100);
        $second = $utcTimestamp % 100;

        list($label, $value) = match (true) {
            $month < 1 || $month > 12 => [", month %d", $month],
            $day < 1 || $day > 31 => [", day %d", $day],
            $hour > 23 => [", hour %d", $hour],
            $minute > 59 => [", minute %d", $minute],
            $second > 59 => [", second %d", $second],
            default => [null, null]
        };
        if ($label !== null && $value !== null) {
            throw new LdapException(["Invalid timestamp: %d{$label}", [$utcTimestamp, $value]]);
        }

        return static::createLocalDateTime(
            sprintf(
                '%d-%02d-%02d %02d:%02d:%02d',
                $year,
                $month,
                $day,
                $hour,
                $minute,
                $second
            ),
            $localTimezone
        );
    }


    /**
     * @param int $timeInterval
     * @param non-empty-string|null $localTimezone
     * @return \DateTimeInterface|null
     */
    public static function formatTimeInterval(
        int $timeInterval,
        string|null $localTimezone = null
    ): \DateTimeInterface|null {
        return $timeInterval > (10000000 * self::TIME_INTERVAL_OFFSET)
            ? self::createLocalDateTime(
                date(
                    'Y-m-d H:i:s',
                    intval(floor($timeInterval / 10000000)) - self::TIME_INTERVAL_OFFSET
                ),
                $localTimezone
            )
            : null;
    }


    /**
     * @param string $utcDateTime
     * @param non-empty-string|null $timezone
     * @return \DateTimeInterface
     */
    public static function createLocalDateTime(
        string $utcDateTime,
        string|null $timezone = null
    ): \DateTimeInterface {
        $utcDateTime = (new \DateTime($utcDateTime, new \DateTimeZone('UTC')));
        return ($timezone !== null)
            ? $utcDateTime->setTimezone(new \DateTimeZone($timezone))
            : $utcDateTime;
    }
}
