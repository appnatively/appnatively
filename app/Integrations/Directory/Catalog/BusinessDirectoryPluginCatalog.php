<?php

namespace Crafium\AppNatively\App\Integrations\Directory\Catalog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Filtering\Field;

/**
 * Business Directory Plugin's data: form fields in the `wpbdp_form_fields` table,
 * stored as `_wpbdp[fields][<id>]` meta, and sticky (featured) listings in the
 * `wpbdp_listings` table. It has no prices, ratings, coordinates or opening hours.
 */
class BusinessDirectoryPluginCatalog extends ListingCatalog {
    /** Field types that make sense as a filter, => value type. */
    private const FILTERABLE_TYPES = [
        'textfield'   => Field::TYPE_CHOICE,
        'select'      => Field::TYPE_CHOICE,
        'radio'       => Field::TYPE_CHOICE,
        'checkbox'    => Field::TYPE_CHOICE,
        'multiselect' => Field::TYPE_CHOICE,
    ];

    /** Field tags read elsewhere (contact details, location), never offered as filters. */
    private const CONTACT_TAGS = [ 'address', 'address2', 'city', 'state', 'country', 'zip', 'phone', 'fax', 'email', 'website', 'social' ];

    /** @var array<string, Field>|null */
    private ?array $fields = null;

    public function post_types(): array {
        return [ defined( 'WPBDP_POST_TYPE' ) ? WPBDP_POST_TYPE : 'wpbdp_listing' ];
    }

    public function category_taxonomy(): string {
        return defined( 'WPBDP_CATEGORY_TAX' ) ? WPBDP_CATEGORY_TAX : 'wpbdp_category';
    }

    public function tag_taxonomy(): ?string {
        return defined( 'WPBDP_TAGS_TAX' ) ? WPBDP_TAGS_TAX : 'wpbdp_tag';
    }

    public function featured_sql(): string {
        global $wpdb;
        return "EXISTS (SELECT 1 FROM {$wpdb->prefix}wpbdp_listings sticky WHERE sticky.listing_id = posts.ID AND sticky.is_sticky = 1)";
    }

    /**
     * Meta-associated form fields; enabled when shown in the search form.
     */
    public function fields(): array {
        if ( $this->fields !== null ) {
            return $this->fields;
        }

        global $wpdb;
        $this->fields = [];
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small table, read per request.
        $rows = $wpdb->get_results( "SELECT id, label, field_type, display_flags, field_data, tag FROM {$wpdb->prefix}wpbdp_form_fields WHERE association = 'meta'" );
        foreach ( (array) $rows as $row ) {
            $type = (string) $row->field_type;
            if ( ! isset( self::FILTERABLE_TYPES[ $type ] ) || in_array( (string) $row->tag, self::CONTACT_TAGS, true ) ) {
                continue;
            }

            $data    = maybe_unserialize( $row->field_data );
            $choices = [];
            foreach ( is_array( $data ) && is_array( $data['options'] ?? null ) ? $data['options'] : [] as $value => $label ) {
                if ( is_scalar( $label ) ) {
                    $choices[ is_int( $value ) ? (string) $label : (string) $value ] = (string) $label;
                }
            }
            $multiple = in_array( $type, [ 'checkbox', 'multiselect' ], true );
            $field    = Field::meta(
                '_wpbdp[fields][' . (int) $row->id . ']',
                (string) $row->label,
                self::FILTERABLE_TYPES[ $type ],
                $choices,
                $multiple,
                in_array( 'search', array_map( 'trim', explode( ',', (string) $row->display_flags ) ), true )
            );
            // Several choices are stored tab-separated.
            $this->fields[ (string) $row->id ] = $multiple ? $field->with_list_pattern( '{value}' ) : $field;
        }
        return $this->fields;
    }
}
