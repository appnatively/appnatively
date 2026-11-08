<?php

namespace Crafium\AppNatively\App\Filtering\Concerns;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Models\DatabaseRow;
use Crafium\AppNatively\App\Filtering\Field;
use Crafium\AppNatively\App\Filtering\TermSource;
use Crafium\AppNatively\App\Filtering\TaxonomyTermSource;

trait DiscoversCatalogFields
{
    /**
     * Where a term facet id's terms come from, or null when it names none this catalog allows.
     */
    private function term_source( string $facet_id ): ?TermSource {
        $term_facets = $this->catalog->term_facets();
        if ( isset( $term_facets[$facet_id] ) ) {
            return $term_facets[$facet_id];
        }
        $separator = strpos( $facet_id, ':' );
        if ( $separator === false ) {
            return null;
        }
        $taxonomy = substr( $facet_id, $separator + 1 );
        $group    = $this->catalog->taxonomy_groups()[substr( $facet_id, 0, $separator )] ?? [];
        // The group names it, so its label comes from there rather than from the taxonomy.
        return isset( $group[$taxonomy] ) ? new TaxonomyTermSource( $taxonomy, $group[$taxonomy] ) : null;
    }

    /**
     * The custom field behind a `meta:<key>` or `field:<key>` facet id, or null when it isn't allowed.
     */
    private function facet_field( string $facet_id ): ?Field {
        if ( ! in_array( $facet_id, $this->allowed_fields(), true ) ) {
            return null;
        }
        if ( str_starts_with( $facet_id, 'field:' ) ) {
            return $this->catalog->fields()[substr( $facet_id, 6 )] ?? null;
        }
        return str_starts_with( $facet_id, 'meta:' ) ? $this->meta_field( substr( $facet_id, 5 ) ) : null;
    }

    // -------------------------------------------------------------------------
    // Custom fields
    // -------------------------------------------------------------------------

    /**
     * Facet ids of the custom fields a filter may narrow by. Filtering by a field reveals
     * its values (a request can probe them), so only ACF fields placed on the post types
     * and the plugin fields it marks searchable are allowed by default; a site changes
     * the list with the `craf_appna_filter_allowed_fields` filter.
     *
     * @return string[]
     */
    private function allowed_fields(): array {
        if ( $this->allowed_fields === null ) {
            $defaults = array_map( fn( $key ) => "meta:{$key}", array_keys( $this->acf_fields() ) );
            foreach ( $this->catalog->fields() as $key => $field ) {
                if ( $field->enabled ) {
                    $defaults[] = "field:{$key}";
                }
            }
            /**
             * Filters which custom fields the app may filter lists by.
             *
             * @param string[] $facet_ids  `meta:<key>` (ACF fields placed on the post types) and
             *                             `field:<key>` (the plugin's own searchable fields).
             * @param string[] $post_types The listed post types (products or directory listings).
             */
            $ids                  = apply_filters( 'craf_appna_filter_allowed_fields', $defaults, $this->post_types() );
            $this->allowed_fields = array_values( array_unique( array_filter( (array) $ids, fn( $id ) => is_string( $id ) && preg_match( '/^(meta|field):./', $id ) ) ) );
        }
        return $this->allowed_fields;
    }

    /**
     * A post-meta field: the ACF field when there is one, otherwise typed from its stored values.
     */
    private function meta_field( string $key ): Field {
        return $this->acf_fields()[$key] ?? Field::meta(
            $key, ucwords(
                str_replace(
                    [
                        '_',
                        '-'
                    ], ' ', $key
                )
            ), $this->sampled_value_type( $key )
        );
    }

    /**
     * Public (non-underscore) meta keys stored on the post types, for the builder to list,
     * without the catalog's internal keys and its own fields' keys.
     *
     * @return string[]
     */
    private function discover_meta_keys(): array {
        global $wpdb;
        $post_types = $this->post_types();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders -- builder-only listing.
        $keys       = DatabaseRow::values( DatabaseRow::from_table( "postmeta", "pm" )->join( "posts as p", "p.ID", "=", "pm.post_id" )->where_in( "p.post_type", $post_types )->where( "pm.meta_key", "not like", $wpdb->esc_like( "_" ) . "%" )->distinct()->limit( self::MAX_DISCOVERED_META_KEYS ), "pm.meta_key" );
        $field_keys = [];
        foreach ( $this->catalog->fields() as $field ) {
            if ( $field->meta_key !== null ) {
                $field_keys[] = $field->meta_key;
            }
        }
        return array_values( array_filter( (array) $keys, fn( $key ) => ! in_array( $key, $field_keys, true ) && ! $this->catalog->is_internal_meta_key( (string) $key ) ) );
    }

    /**
     * Top-level ACF fields of field groups placed on the post types, keyed by name.
     * Nested fields (repeaters, groups) store prefixed meta keys and are left out.
     *
     * @return array<string, Field>
     */
    private function acf_fields(): array {
        if ( $this->acf_fields !== null ) {
            return $this->acf_fields;
        }
        $this->acf_fields = [];
        if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
            return $this->acf_fields;
        }
        foreach ( $this->post_types() as $post_type ) {
            foreach ( acf_get_field_groups( ['post_type' => $post_type] ) as $group ) {
                foreach ( (array) acf_get_fields( $group ) as $field ) {
                    $type = $field['type'] ?? '';
                    if ( empty( $field['name'] ) || ! in_array( $type, self::ACF_FILTERABLE_TYPES, true ) ) {
                        continue;
                    }
                    $value_type                       = in_array(
                        $type, [
                            'number',
                            'range'
                        ], true
                    ) ? Field::TYPE_NUMBER : ( $type === 'true_false' ? Field::TYPE_BOOLEAN : Field::TYPE_CHOICE );
                    $this->acf_fields[$field['name']] = Field::meta( $field['name'], (string) ( $field['label'] ?? $field['name'] ), $value_type, is_array( $field['choices'] ?? null ) ? $field['choices'] : [], $type === 'checkbox' || ! empty( $field['multiple'] ), true );
                }
            }
        }
        return $this->acf_fields;
    }

    /**
     * `number` when a sample of stored values is all numeric, otherwise `choice`.
     */
    private function sampled_value_type( string $key ): string {
        global $wpdb;
        $post_types = $this->post_types();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders -- small sample; facets are cached by the caller.
        $sample = DatabaseRow::values( DatabaseRow::from_table( "postmeta", "pm" )->join( "posts as p", "p.ID", "=", "pm.post_id" )->where_in( "p.post_type", $post_types )->where( "pm.meta_key", $key )->where( "pm.meta_value", "<>", "" )->distinct()->limit( 20 ), "pm.meta_value" );
        if ( ! empty( $sample ) && count( array_filter( $sample, 'is_numeric' ) ) === count( $sample ) ) {
            return Field::TYPE_NUMBER;
        }
        return Field::TYPE_CHOICE;
    }
}
