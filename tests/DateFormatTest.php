<?php

declare(strict_types=1);

namespace Gadget\Ldap\Tests;

use Gadget\Ldap\DateFormat;
use Gadget\Ldap\LdapException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs with a non-UTC default timezone so any accidental use of local time shows up.
 */
final class DateFormatTest extends TestCase
{
    private string $defaultTimezone;


    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
    }


    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }


    public function testFormatUtcTimestampReadsTheDigitsAsAUtcDateTime(): void
    {
        $date = DateFormat::formatUTCTimestamp(20240315123045);

        self::assertSame('2024-03-15 12:30:45 UTC', $date->format('Y-m-d H:i:s T'));
    }


    public function testFormatUtcTimestampConvertsToTheRequestedTimezone(): void
    {
        $date = DateFormat::formatUTCTimestamp(20240315123045, 'America/New_York');

        self::assertSame('2024-03-15T08:30:45-04:00', $date->format(\DateTimeInterface::ATOM));
    }


    public function testFormatUtcTimestampAcceptsTheRangeBoundaries(): void
    {
        self::assertSame(
            '1970-01-01 00:00:00',
            DateFormat::formatUTCTimestamp(DateFormat::UTC_TIMESTAMP_START)->format('Y-m-d H:i:s')
        );
        self::assertSame(
            '2099-12-31 23:59:59',
            DateFormat::formatUTCTimestamp(DateFormat::UTC_TIMESTAMP_END)->format('Y-m-d H:i:s')
        );
    }


    /**
     * @return array<string,array{int,string}>
     */
    public static function invalidTimestamps(): array
    {
        return [
            'before 1970' => [DateFormat::UTC_TIMESTAMP_START - 1, 'Invalid timestamp: 19700100999999'],
            'after 2099' => [DateFormat::UTC_TIMESTAMP_END + 1, 'Invalid timestamp: 20991231235960'],
            'month 0' => [20240001000000, 'Invalid timestamp: 20240001000000, month 0'],
            'month 13' => [20241301000000, 'Invalid timestamp: 20241301000000, month 13'],
            'day 0' => [20240100000000, 'Invalid timestamp: 20240100000000, day 0'],
            'day 32' => [20240132000000, 'Invalid timestamp: 20240132000000, day 32'],
            'hour 24' => [20240101240000, 'Invalid timestamp: 20240101240000, hour 24'],
            'minute 60' => [20240101006000, 'Invalid timestamp: 20240101006000, minute 60'],
            'second 60' => [20240101000060, 'Invalid timestamp: 20240101000060, second 60'],
        ];
    }


    #[DataProvider('invalidTimestamps')]
    public function testFormatUtcTimestampRejectsInvalidValues(int $timestamp, string $message): void
    {
        $this->expectException(LdapException::class);
        $this->expectExceptionMessage($message);

        DateFormat::formatUTCTimestamp($timestamp);
    }


    public function testFormatTimeIntervalConvertsAWindowsFileTimeToUtc(): void
    {
        $date = DateFormat::formatTimeInterval(133500000000000000);

        self::assertInstanceOf(\DateTimeInterface::class, $date);
        self::assertSame('2024-01-17 21:20:00 UTC', $date->format('Y-m-d H:i:s T'));
    }


    public function testFormatTimeIntervalConvertsToTheRequestedTimezone(): void
    {
        $date = DateFormat::formatTimeInterval(133500000000000000, 'America/New_York');

        self::assertInstanceOf(\DateTimeInterface::class, $date);
        self::assertSame('2024-01-17T16:20:00-05:00', $date->format(\DateTimeInterface::ATOM));
    }


    public function testFormatTimeIntervalIsNullAtOrBeforeTheUnixEpoch(): void
    {
        self::assertNull(DateFormat::formatTimeInterval(0));
        self::assertNull(DateFormat::formatTimeInterval(10000000 * DateFormat::TIME_INTERVAL_OFFSET));
    }


    public function testFormatTimeIntervalResolvesOneSecondAfterTheUnixEpoch(): void
    {
        $date = DateFormat::formatTimeInterval(10000000 * DateFormat::TIME_INTERVAL_OFFSET + 10000000);

        self::assertInstanceOf(\DateTimeInterface::class, $date);
        self::assertSame('1970-01-01 00:00:01 UTC', $date->format('Y-m-d H:i:s T'));
    }


    public function testCreateLocalDateTimeReadsTheStringAsUtc(): void
    {
        $date = DateFormat::createLocalDateTime('2024-06-01 10:00:00');

        self::assertSame('2024-06-01T10:00:00+00:00', $date->format(\DateTimeInterface::ATOM));
        self::assertSame('UTC', $date->getTimezone()->getName());
    }


    public function testCreateLocalDateTimeConvertsToTheRequestedTimezone(): void
    {
        $date = DateFormat::createLocalDateTime('2024-06-01 10:00:00', 'Asia/Tokyo');

        self::assertSame('2024-06-01T19:00:00+09:00', $date->format(\DateTimeInterface::ATOM));
    }
}
