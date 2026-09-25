<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Catalog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Filtering\Field;

/**
 * Directorist's listing data: post meta (`_price`, `_featured`, `_manual_lat` …),
 * custom fields defined per directory type in the `submission_form_fields` term
 * meta and stored as `_<field_key>`, and business hours (`_bdbh`, from its
 * Business Hours extension).
 */
class DirectoristCatalog extends ListingCatalog {
    /** The taxonomy of directory types, whose term meta holds the forms. */
    private const TYPE_TAXONOMY = 'atbdp_listing_types';

    /** Custom-field widgets that make sense as a filter, => value type. */
    private const FILTERABLE_WIDGETS = [
        'text'     => Field::TYPE_CHOICE,
        'number'   => Field::TYPE_NUMBER,
        'select'   => Field::TYPE_CHOICE,
        'radio'    => Field::TYPE_CHOICE,
        'checkbox' => Field::TYPE_CHOICE,
    ];

    /** @var array<string, Field>|null */
    private ?array $fields = null;

    public function post_types(): array {
        return [ defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir' ];
    }

    public function category_taxonomy(): string {
        return defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category';
    }

    public function tag_taxonomy(): ?string {
        return defined( 'ATBDP_TAGS' ) ? ATBDP_TAGS : 'at_biz_dir-tags';
    }

    public function location_taxonomy(): ?string {
        return defined( 'ATBDP_LOCATION' ) ? ATBDP_LOCATION : 'at_biz_dir-location';
    }

    public function featured_sql(): string {
        return $this->meta_in( '_featured', [ '1' ] );
    }

    public function price_sql(): ?array {
        $price = $this->meta_number( '_price' );
        return [ $price, $price ];
    }

    public function rating_sql(): ?string {
        return $this->meta_number( '_directorist_listing_rating' );
    }

    public function rated_sql(): ?string {
        return $this->meta_number( '_directorist_listing_review_count' ) . ' > 0';
    }

    public function views_sql(): ?string {
        return $this->meta_number( '_atbdp_post_views_count' );
    }

    public function coordinates(): ?array {
        return [ $this->meta_number( '_manual_lat' ), $this->meta_number( '_manual_lng' ) ];
    }

    public function has_business_hours(): bool {
        return true;
    }

    /**
     * `_bdbh`: weekday name => {enable, remain_close, start[], close[]}; `_enable247hour`
     * opens every day, `_disable_bz_hour_listing` hides the hours, `_timezone` is the
     * listing's own timezone.
     */
    protected function business_hours(): array {
        $hours = [];
        foreach ( $this->meta_rows( [ '_bdbh', '_enable247hour', '_disable_bz_hour_listing', '_timezone' ] ) as $post_id => $meta ) {
            if ( ! empty( $meta['_disable_bz_hour_listing'] ) ) {
                continue;
            }
            $timezone = BusinessHours::timezone( $meta['_timezone'] ?? null );
            if ( ! empty( $meta['_enable247hour'] ) ) {
                $hours[ $post_id ] = BusinessHours::always_open( $timezone );
                continue;
            }

            $schedule = new BusinessHours( $timezone );
            foreach ( is_array( $meta['_bdbh'] ?? null ) ? $meta['_bdbh'] : [] as $day => $slot ) {
                if ( ! is_array( $slot ) || empty( $slot['enable'] ) || $this->truthy( $slot['remain_close'] ?? '' ) ) {
                    continue;
                }
                $opens  = (array) ( $slot['start'] ?? [] );
                $closes = (array) ( $slot['close'] ?? [] );
                foreach ( $opens as $index => $open ) {
                    if ( is_scalar( $open ) && isset( $closes[ $index ] ) && is_scalar( $closes[ $index ] ) ) {
                        $schedule->add( (string) $day, (string) $open, (string) $closes[ $index ] );
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
     * Custom fields of every directory type's submission form; enabled when the type's
     * search form offers them too.
     */
    public function fields(): array {
        if ( $this->fields !== null ) {
            return $this->fields;
        }

        $this->fields = [];
        $types        = get_terms(
            [
                'taxonomy'   => self::TYPE_TAXONOMY,
                'hide_empty' => false,
            ]
        );
        foreach ( is_wp_error( $types ) ? [] : $types as $type ) {
            $submission = get_term_meta( $type->term_id, 'submission_form_fields', true );
            $search     = get_term_meta( $type->term_id, 'search_form_fields', true );
            $searchable = $this->search_widget_keys( is_array( $search ) ? (array) ( $search['fields'] ?? [] ) : [] );

            foreach ( is_array( $submission ) ? (array) ( $submission['fields'] ?? [] ) : [] as $widget_key => $field ) {
                $widget    = (string) ( $field['widget_name'] ?? '' );
                $field_key = (string) ( $field['field_key'] ?? '' );
                if ( ( $field['widget_group'] ?? '' ) !== 'custom' || ! isset( self::FILTERABLE_WIDGETS[ $widget ] ) || $field_key === '' || isset( $this->fields[ $field_key ] ) ) {
                    continue;
                }

                $choices = [];
                foreach ( (array) ( $field['options'] ?? [] ) as $option ) {
                    if ( is_array( $option ) && isset( $option['option_value'] ) && is_scalar( $option['option_value'] ) ) {
                        $choices[ (string) $option['option_value'] ] = (string) ( $option['option_label'] ?? $option['option_value'] );
                    }
                }

                $this->fields[ $field_key ] = Field::meta(
                    '_' . $field_key,
                    (string) ( $field['label'] ?? '' ) ?: ucwords( str_replace( [ '-', '_' ], ' ', $field_key ) ),
                    self::FILTERABLE_WIDGETS[ $widget ],
                    $choices,
                    $widget === 'checkbox',
                    in_array( (string) $widget_key, $searchable, true )
                );
            }
        }
        return $this->fields;
    }

    /**
     * The submission-form widget keys a search form offers.
     *
     * @return string[]
     */
    private function search_widget_keys( array $search_fields ): array {
        $keys = [];
        foreach ( $search_fields as $key => $field ) {
            $keys[] = (string) $key;
            if ( is_array( $field ) && ! empty( $field['original_widget_key'] ) ) {
                $keys[] = (string) $field['original_widget_key'];
            }
        }
        return $keys;
    }

    /**
     * @param mixed $value
     */
    private function truthy( $value ): bool {
        return in_array( strtolower( (string) ( is_scalar( $value ) ? $value : '' ) ), [ '1', 'on', 'yes', 'true', 'close' ], true );
    }
}
