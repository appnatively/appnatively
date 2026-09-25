<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Catalog;

defined( "ABSPATH" ) || exit;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * A listing's weekly opening hours, normalized from each directory plugin's own
 * format, and whether it is open at a given moment.
 *
 * Days are 0 (Sunday) to 6 (Saturday); each holds [open, close] minute ranges
 * from midnight. A range whose close is not after its open runs past midnight.
 */
final class BusinessHours {
    private const DAY_MINUTES = 1440;

    private const DAY_NAMES = [
        'sunday'    => 0, 'sun' => 0, 'su' => 0,
        'monday'    => 1, 'mon' => 1, 'mo' => 1,
        'tuesday'   => 2, 'tue' => 2, 'tu' => 2,
        'wednesday' => 3, 'wed' => 3, 'we' => 3,
        'thursday'  => 4, 'thu' => 4, 'th' => 4,
        'friday'    => 5, 'fri' => 5, 'fr' => 5,
        'saturday'  => 6, 'sat' => 6, 'sa' => 6,
    ];

    private bool $always = false;

    /** @var array<int, array<int, array{0: int, 1: int}>> */
    private array $days = [];

    private ?DateTimeZone $timezone;

    /** @var array<string, bool|array<int, array{0: int, 1: int}>> Date (Y-m-d) => closed (false) or ranges, overriding the weekday. */
    private array $dates = [];

    public function __construct( ?DateTimeZone $timezone = null ) {
        $this->timezone = $timezone;
    }

    public static function always_open( ?DateTimeZone $timezone = null ): self {
        $hours         = new self( $timezone );
        $hours->always = true;
        return $hours;
    }

    /**
     * Open on `$day` (a name like `monday` / `Mo`, or 0-6) from `$open` to `$close` (times like `09:00`, `9:00 am`).
     */
    public function add( $day, string $open, string $close ): self {
        $index = self::day_index( $day );
        $from  = self::minutes( $open );
        $to    = self::minutes( $close );
        if ( $index !== null && $from !== null && $to !== null ) {
            $this->days[ $index ][] = [ $from, $to === 0 && $from > 0 ? self::DAY_MINUTES : $to ];
        }
        return $this;
    }

    /**
     * Open all day on `$day`.
     */
    public function add_all_day( $day ): self {
        $index = self::day_index( $day );
        if ( $index !== null ) {
            $this->days[ $index ][] = [ 0, self::DAY_MINUTES ];
        }
        return $this;
    }

    /**
     * Special hours for one date (Y-m-d): closed, or open all day / at the given ranges.
     *
     * @param array<int, array{0: string, 1: string}> $ranges Empty with `$open` = open all day.
     */
    public function add_date( string $date, bool $open, array $ranges = [] ): self {
        if ( ! $open ) {
            $this->dates[ $date ] = false;
            return $this;
        }
        $minutes = [];
        foreach ( $ranges as [ $from, $to ] ) {
            $from = self::minutes( $from );
            $to   = self::minutes( $to );
            if ( $from !== null && $to !== null ) {
                $minutes[] = [ $from, $to ];
            }
        }
        $this->dates[ $date ] = $minutes ?: [ [ 0, self::DAY_MINUTES ] ];
        return $this;
    }

    public function has_hours(): bool {
        return $this->always || ! empty( $this->days ) || ! empty( $this->dates );
    }

    /**
     * Whether the listing is open at `$timestamp`, in its own timezone (the site's when it has none).
     */
    public function is_open_at( int $timestamp ): bool {
        if ( $this->always ) {
            return true;
        }

        $now     = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $this->timezone ?? wp_timezone() );
        $minute  = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );
        $today   = $this->ranges_on( $now );
        $earlier = $this->ranges_on( $now->modify( '-1 day' ) );

        foreach ( $today as [ $open, $close ] ) {
            if ( $close > $open ? ( $minute >= $open && $minute < $close ) : $minute >= $open ) {
                return true;
            }
        }
        // Yesterday's overnight ranges reach into today.
        foreach ( $earlier as [ $open, $close ] ) {
            if ( $close <= $open && $minute < $close ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int, array{0: int, 1: int}>
     */
    private function ranges_on( DateTimeImmutable $day ): array {
        $date = $day->format( 'Y-m-d' );
        if ( array_key_exists( $date, $this->dates ) ) {
            return $this->dates[ $date ] ?: [];
        }
        return $this->days[ (int) $day->format( 'w' ) ] ?? [];
    }

    /**
     * A timezone from an identifier (`Europe/Paris`) or a UTC offset (`+5:30`, `+5.5`, `-4`), or null.
     */
    public static function timezone( $value ): ?DateTimeZone {
        if ( is_int( $value ) || is_float( $value ) ) {
            $value = sprintf( '%+g', $value );
        }
        if ( ! is_string( $value ) || trim( $value ) === '' ) {
            return null;
        }
        $value = trim( $value );
        if ( preg_match( '/^(?:UTC|GMT)?\s*([+-])(\d{1,2})(?:(:|\.)(\d{1,2}))?$/i', $value, $offset ) ) {
            $minutes = ( $offset[3] ?? '' ) === '.' ? (int) round( (float) ( '0.' . $offset[4] ) * 60 ) : (int) ( $offset[4] ?? 0 );
            $value   = sprintf( '%s%02d:%02d', $offset[1], (int) $offset[2], $minutes );
        }
        try {
            return new DateTimeZone( $value );
        } catch ( Exception $e ) {
            return null;
        }
    }

    /**
     * @param string|int $day
     */
    private static function day_index( $day ): ?int {
        if ( is_int( $day ) || ( is_string( $day ) && ctype_digit( $day ) ) ) {
            $day = (int) $day;
            return $day >= 0 && $day <= 6 ? $day : null;
        }
        return self::DAY_NAMES[ strtolower( trim( (string) $day ) ) ] ?? null;
    }

    /**
     * Minutes from midnight for a time like `09:00`, `9:00:00`, `9 am` or `9:30 PM`.
     */
    private static function minutes( string $time ): ?int {
        $time = strtolower( trim( $time ) );
        if ( ! preg_match( '/^(\d{1,2})(?::(\d{2}))?(?::\d{2})?\s*(am|pm)?$/', $time, $parts ) ) {
            return null;
        }
        $hour   = (int) $parts[1];
        $minute = (int) ( $parts[2] ?? 0 );
        $suffix = $parts[3] ?? '';
        if ( $suffix === 'pm' && $hour < 12 ) {
            $hour += 12;
        } elseif ( $suffix === 'am' && $hour === 12 ) {
            $hour = 0;
        }
        if ( $hour > 24 || $minute > 59 ) {
            return null;
        }
        return min( self::DAY_MINUTES, $hour * 60 + $minute );
    }
}
