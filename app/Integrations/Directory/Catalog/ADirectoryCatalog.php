<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Catalog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Filtering\Field;

/**
 * aDirectory's listing data: one post type per directory, post meta (`_price`,
 * `_is_featured` = `yes`, `_map_lat` / `_map_lon`), ratings as
 * `adqs_review_rating` comment meta, custom fields defined per directory and
 * stored as `_<input type>_<field id>`, and business hours (`adqs_business_data`).
 */
class ADirectoryCatalog extends ListingCatalog {
    /** Custom-field input types that make sense as a filter, => value type. */
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
        return function_exists( 'adqs_get_directory_post_types' ) ? array_values( (array) adqs_get_directory_post_types() ) : [];
    }

    public function category_taxonomy(): string {
        return 'adqs_category';
    }

    public function tag_taxonomy(): ?string {
        return 'adqs_tags';
    }

    public function location_taxonomy(): ?string {
        return 'adqs_location';
    }

    /**
     * aDirectory's own bookkeeping meta is prefixed `adqs_`.
     */
    public function is_internal_meta_key( string $key ): bool {
        return str_starts_with( $key, 'adqs_' ) || parent::is_internal_meta_key( $key );
    }

    public function featured_sql(): string {
        return $this->meta_in( '_is_featured', [ 'yes' ] );
    }

    public function price_sql(): ?array {
        $price = $this->meta_number( '_price' );
        return [ $price, $price ];
    }

    /**
     * The average of approved reviews' `adqs_review_rating`.
     */
    public function rating_sql(): ?string {
        global $wpdb;
        return "(SELECT AVG(NULLIF(review_meta.meta_value, '') + 0) FROM {$wpdb->comments} review
            INNER JOIN {$wpdb->commentmeta} review_meta ON review_meta.comment_id = review.comment_ID AND review_meta.meta_key = 'adqs_review_rating'
            WHERE review.comment_post_ID = posts.ID AND review.comment_approved = '1')";
    }

    public function rated_sql(): ?string {
        return $this->rating_sql() . ' > 0';
    }

    public function coordinates(): ?array {
        return [ $this->meta_number( '_map_lat' ), $this->meta_number( '_map_lon' ) ];
    }

    public function has_business_hours(): bool {
        return true;
    }

    /**
     * `adqs_business_data`: {status: open_twenty_four | hide_b_h | open_specific,
     * <weekday>: {enable, open_24, <n>: {open, close}}}, in the site's timezone.
     */
    protected function business_hours(): array {
        $hours = [];
        foreach ( $this->meta_rows( [ 'adqs_business_data' ] ) as $post_id => $meta ) {
            $data   = is_array( $meta['adqs_business_data'] ?? null ) ? $meta['adqs_business_data'] : [];
            $status = (string) ( $data['status'] ?? '' );
            if ( $status === 'open_twenty_four' ) {
                $hours[ $post_id ] = BusinessHours::always_open();
                continue;
            }
            if ( $status === 'hide_b_h' ) {
                continue;
            }

            $schedule = new BusinessHours();
            foreach ( $data as $day => $slots ) {
                if ( ! is_array( $slots ) || empty( $slots['enable'] ) ) {
                    continue;
                }
                if ( ! empty( $slots['open_24'] ) ) {
                    $schedule->add_all_day( (string) $day );
                    continue;
                }
                foreach ( $slots as $slot ) {
                    if ( is_array( $slot ) && ! empty( $slot['open'] ) && ! empty( $slot['close'] ) ) {
                        $schedule->add( (string) $day, (string) $slot['open'], (string) $slot['close'] );
                    }
                }
            }
            if ( $schedule->has_hours() ) {
                $hours[ $post_id ] = $schedule;
            }
        }
        return $hours;
    }

    /**
     * Custom fields of every directory; enabled when shown in its search form (`in_search`).
     */
    public function fields(): array {
        if ( $this->fields !== null ) {
            return $this->fields;
        }

        $this->fields = [];
        if ( ! function_exists( 'adqs_get_directories' ) || ! function_exists( 'adqs_get_listing_fields' ) ) {
            return $this->fields;
        }

        foreach ( (array) adqs_get_directories() as $directory ) {
            $sections = adqs_get_listing_fields( is_object( $directory ) ? (int) $directory->term_id : (int) $directory );
            foreach ( is_array( $sections ) ? $sections : [] as $section ) {
                foreach ( (array) ( $section['fields'] ?? [] ) as $field ) {
                    $type     = (string) ( $field['input_type'] ?? '' );
                    $field_id = (string) ( $field['fieldid'] ?? '' );
                    $key      = "{$type}_{$field_id}";
                    if ( ! isset( self::FILTERABLE_TYPES[ $type ] ) || $field_id === '' || isset( $this->fields[ $key ] ) ) {
                        continue;
                    }

                    $choices = [];
                    foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
                        if ( is_array( $option ) && isset( $option['value'] ) && is_scalar( $option['value'] ) && $option['value'] !== '' ) {
                            $choices[ (string) $option['value'] ] = ucfirst( (string) $option['value'] );
                        }
                    }

                    $this->fields[ $key ] = Field::meta(
                        sanitize_key( "_{$key}" ),
                        (string) ( $field['label'] ?? $key ),
                        self::FILTERABLE_TYPES[ $type ],
                        $choices,
                        $type === 'checkbox',
                        ! empty( $field['in_search'] )
                    );
                }
            }
        }
        return $this->fields;
    }
}
