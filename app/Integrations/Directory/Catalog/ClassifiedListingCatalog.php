<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Catalog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Filtering\Field;

/**
 * Classified Listing's data: post meta (`price` / `_rtcl_max_price`, `featured`,
 * `_rtcl_average_rating`, `latitude` / `longitude`), custom fields from its Form
 * Builder (or legacy `rtcl_cf` posts), and business hours (`_rtcl_bhs`, with
 * `_rtcl_special_bhs` for single dates).
 */
class ClassifiedListingCatalog extends ListingCatalog {
    /** Custom-field types that make sense as a filter, => value type. */
    private const FILTERABLE_TYPES = [
        'text'     => Field::TYPE_CHOICE,
        'number'   => Field::TYPE_NUMBER,
        'select'   => Field::TYPE_CHOICE,
        'radio'    => Field::TYPE_CHOICE,
        'checkbox' => Field::TYPE_CHOICE,
    ];

    /** @var array<string, Field>|null */
    private ?array $fields = null;

    public function post_types(): array {
        return [ function_exists( 'rtcl' ) ? (string) rtcl()->post_type : 'rtcl_listing' ];
    }

    public function category_taxonomy(): string {
        return function_exists( 'rtcl' ) ? (string) rtcl()->category : 'rtcl_category';
    }

    public function tag_taxonomy(): ?string {
        return function_exists( 'rtcl' ) && ! empty( rtcl()->tag ) ? (string) rtcl()->tag : 'rtcl_tag';
    }

    public function location_taxonomy(): ?string {
        return function_exists( 'rtcl' ) ? (string) rtcl()->location : 'rtcl_location';
    }

    public function featured_sql(): string {
        return $this->meta_in( 'featured', [ '1' ] );
    }

    /**
     * `price`, up to `_rtcl_max_price` for a price range.
     */
    public function price_sql(): ?array {
        $price = $this->meta_number( 'price' );
        return [ $price, 'COALESCE(' . $this->meta_number( '_rtcl_max_price' ) . ", {$price})" ];
    }

    public function rating_sql(): ?string {
        return $this->meta_number( '_rtcl_average_rating' );
    }

    public function rated_sql(): ?string {
        return $this->meta_number( '_rtcl_review_count' ) . ' > 0';
    }

    public function views_sql(): ?string {
        return $this->meta_number( '_views' );
    }

    public function coordinates(): ?array {
        return [ $this->meta_number( 'latitude' ), $this->meta_number( 'longitude' ) ];
    }

    public function internal_meta_keys(): array {
        return [
            'price', 'featured', 'latitude', 'longitude', 'price_type', 'ad_type', 'zipcode', 'address', 'phone', 'email', 'website',
            'whatsapp_number', 'never_expires', 'expiry_date', 'featured_expiry_date', 'images', 'rtcl_agree', 'renewal_reminder_sent',
        ];
    }

    public function has_business_hours(): bool {
        return true;
    }

    /**
     * `_rtcl_bhs`: weekday (0 = Sunday) => {open, times: [{start, end}]}, open all day
     * without times; `_rtcl_special_bhs`: [{date, open, times}] for single dates. In the
     * site's timezone.
     */
    protected function business_hours(): array {
        $hours = [];
        foreach ( $this->meta_rows( [ '_rtcl_bhs', '_rtcl_special_bhs' ] ) as $post_id => $meta ) {
            $schedule = new BusinessHours();
            foreach ( is_array( $meta['_rtcl_bhs'] ?? null ) ? $meta['_rtcl_bhs'] : [] as $day => $slot ) {
                if ( ! is_array( $slot ) || empty( $slot['open'] ) ) {
                    continue;
                }
                $times = $this->times( $slot['times'] ?? [] );
                if ( empty( $times ) ) {
                    $schedule->add_all_day( (int) $day );
                }
                foreach ( $times as [ $start, $end ] ) {
                    $schedule->add( (int) $day, $start, $end );
                }
            }
            foreach ( is_array( $meta['_rtcl_special_bhs'] ?? null ) ? $meta['_rtcl_special_bhs'] : [] as $special ) {
                if ( is_array( $special ) && ! empty( $special['date'] ) ) {
                    $schedule->add_date( (string) $special['date'], ! empty( $special['open'] ), $this->times( $special['times'] ?? [] ) );
                }
            }
            if ( $schedule->has_hours() ) {
                $hours[ $post_id ] = $schedule;
            }
        }
        return $hours;
    }

    /**
     * @param mixed $times
     * @return array<int, array{0: string, 1: string}>
     */
    private function times( $times ): array {
        $ranges = [];
        foreach ( is_array( $times ) ? $times : [] as $time ) {
            if ( is_array( $time ) && ! empty( $time['start'] ) && ! empty( $time['end'] ) ) {
                $ranges[] = [ (string) $time['start'], (string) $time['end'] ];
            }
        }
        return $ranges;
    }

    /**
     * Custom fields of the Form Builder's forms (stored as meta named after the field) and
     * legacy `rtcl_cf` posts (stored as `_field_<id>`); enabled when marked filterable /
     * searchable.
     */
    public function fields(): array {
        if ( $this->fields === null ) {
            $this->fields = $this->form_builder_fields() + $this->legacy_fields();
        }
        return $this->fields;
    }

    /**
     * @return array<string, Field>
     */
    private function form_builder_fields(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'rtcl_forms';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small lookup.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return [];
        }

        $fields = [];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a few rows of a trusted table name.
        foreach ( (array) $wpdb->get_col( "SELECT fields FROM {$table}" ) as $form_fields ) {
            foreach ( (array) json_decode( (string) $form_fields, true ) as $field ) {
                $type = (string) ( $field['element'] ?? '' );
                $name = (string) ( $field['name'] ?? '' );
                if ( ! empty( $field['preset'] ) || ! isset( self::FILTERABLE_TYPES[ $type ] ) || $name === '' || isset( $fields[ $name ] ) ) {
                    continue;
                }
                $choices = [];
                foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
                    if ( is_array( $option ) && isset( $option['value'] ) && is_scalar( $option['value'] ) ) {
                        $choices[ (string) $option['value'] ] = (string) ( $option['label'] ?? $option['value'] );
                    }
                }
                $fields[ $name ] = Field::meta( $name, (string) ( $field['label'] ?? $name ), self::FILTERABLE_TYPES[ $type ], $choices, $type === 'checkbox', ! empty( $field['filterable'] ) );
            }
        }
        return $fields;
    }

    /**
     * @return array<string, Field>
     */
    private function legacy_fields(): array {
        $fields      = [];
        $post_type   = function_exists( 'rtcl' ) && ! empty( rtcl()->post_type_cf ) ? (string) rtcl()->post_type_cf : 'rtcl_cf';
        $field_posts = get_posts(
            [
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'no_found_rows'  => true,
            ]
        );
        foreach ( $field_posts as $field_post ) {
            $type = (string) get_post_meta( $field_post->ID, '_type', true );
            if ( ! isset( self::FILTERABLE_TYPES[ $type ] ) ) {
                continue;
            }
            $options = get_post_meta( $field_post->ID, '_options', true );
            $choices = is_array( $options ) ? ( is_array( $options['choices'] ?? null ) ? $options['choices'] : $options ) : [];

            $fields[ '_field_' . $field_post->ID ] = Field::meta(
                '_field_' . $field_post->ID,
                (string) ( get_post_meta( $field_post->ID, '_label', true ) ?: $field_post->post_title ),
                self::FILTERABLE_TYPES[ $type ],
                array_filter( array_map( fn( $label ) => is_scalar( $label ) ? (string) $label : '', $choices ) ),
                $type === 'checkbox',
                (bool) get_post_meta( $field_post->ID, '_searchable', true )
            );
        }
        return $fields;
    }
}
