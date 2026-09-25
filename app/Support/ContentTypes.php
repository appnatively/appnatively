<?php
/**
 * Which post types, taxonomies and custom fields the app may read.
 *
 * @package Crafium\AppNatively\App\Support
 */

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

use WP_Post_Type;

/**
 * Class ContentTypes
 *
 * The blog endpoints serve any post type through one code path, so this is the
 * gate that decides what they may serve. Studio discovers what the site has,
 * the owner picks from it, and Studio pushes that choice here per app. Reads
 * are public, so the stored choice is never trusted on its own: every entry is
 * re-checked against what the site actually offers each time it is used.
 *
 * `post` with its `category` taxonomy is always served, configured or not, so
 * apps built before custom post types existed keep working unchanged.
 */
class ContentTypes {
    const OPTION = 'craf_appna_content_types';

    /** Most post types one app may expose. */
    const MAX_SELECTIONS = 50;

    const DEFAULT_POST_TYPE = 'post';

    /**
     * ACF field types mapped to the normalized types the app renders. Anything
     * absent — repeaters, groups, clones, passwords, users, layout-only fields —
     * is never discovered and so can never be exposed.
     */
    const ACF_TYPES = [
        'text'             => 'text',
        'textarea'         => 'textarea',
        'number'           => 'number',
        'range'            => 'number',
        'email'            => 'email',
        'url'              => 'url',
        'oembed'           => 'url',
        'page_link'        => 'url',
        'image'            => 'image',
        'gallery'          => 'gallery',
        'file'             => 'file',
        'wysiwyg'          => 'wysiwyg',
        'select'           => 'select',
        'checkbox'         => 'select',
        'radio'            => 'select',
        'button_group'     => 'select',
        'true_false'       => 'boolean',
        'link'             => 'link',
        'post_object'      => 'post_object',
        'relationship'     => 'post_object',
        'taxonomy'         => 'taxonomy',
        'google_map'       => 'google_map',
        'date_picker'      => 'date',
        'date_time_picker' => 'datetime',
        'time_picker'      => 'time',
        'color_picker'     => 'color',
    ];

    /**
     * Registered meta types mapped to normalized field types.
     */
    const META_TYPES = [
        'string'  => 'text',
        'integer' => 'number',
        'number'  => 'number',
        'boolean' => 'boolean',
    ];

    /**
     * Per-request memo of discovered post types, keyed by post type name.
     *
     * @var array<string, array|null>
     */
    private static array $discovered = [];

    /**
     * Per-request memo of the effective allow-list.
     *
     * @var array<string, array{taxonomies: string[], fields: string[]}>|null
     */
    private static ?array $allowed = null;

    /**
     * Forget the per-request memo. Used after a save and by tests.
     *
     * @return void
     */
    public static function flush(): void {
        self::$discovered = [];
        self::$allowed    = null;
    }

    /**
     * Post types owned by an integration, which have their own endpoints and
     * visibility rules (catalog visibility, private listings, …) that a generic
     * read would bypass.
     *
     * @return string[]
     */
    public static function excluded_post_types(): array {
        $excluded = [
            'attachment',
            'product',
            'product_variation',
            'sc_product',
            'sc_collection',
            'sc_upsell',
            'fluent-products',
            'formgent_form',
            'at_biz_dir',
            'hp_listing',
            'hp_vendor',
            'hp_request',
            'wpbdp_listing',
            'rtcl_listing',
            'wp-rest-api-log',
        ];

        if ( function_exists( 'geodir_get_posttypes' ) ) {
            $excluded = array_merge( $excluded, (array) geodir_get_posttypes() );
        }

        if ( function_exists( 'adqs_get_directory_post_types' ) ) {
            $excluded = array_merge( $excluded, array_keys( (array) adqs_get_directory_post_types() ) );
        }

        /**
         * Filters the post types the generic content endpoints never serve.
         *
         * @param string[] $excluded Post type names.
         */
        return array_values( array_unique( (array) apply_filters( 'craf_appna_excluded_post_types', $excluded ) ) );
    }

    /**
     * Whether a post type may be offered at all, before any owner choice.
     *
     * @param string $post_type The post type name.
     * @return bool
     */
    public static function is_eligible_post_type( string $post_type ): bool {
        if ( self::DEFAULT_POST_TYPE === $post_type ) {
            return true;
        }

        $object = get_post_type_object( $post_type );

        // `public` matters, not just viewable: a type can be publicly queryable
        // while deliberately kept off the site (logs, internal records).
        return $object instanceof WP_Post_Type
            && ! empty( $object->public )
            && is_post_type_viewable( $post_type )
            && ! in_array( $post_type, self::excluded_post_types(), true );
    }

    /**
     * Everything the site could expose, for Studio to choose from.
     *
     * @return array[]
     */
    public static function discover(): array {
        $types = [];

        foreach ( get_post_types( [], 'names' ) as $post_type ) {
            $described = self::describe( (string) $post_type );

            if ( null !== $described ) {
                // The count is only for the picker; the public reads that describe every allowed
                // type on each request do not pay for it.
                $counts  = wp_count_posts( (string) $post_type );
                $types[] = $described + [ 'count' => isset( $counts->publish ) ? (int) $counts->publish : 0 ];
            }
        }

        return $types;
    }

    /**
     * Describe one eligible post type: its labels, viewable taxonomies and the
     * custom fields that could be exposed. Null when it is not eligible.
     *
     * @param string $post_type The post type name.
     * @return array|null
     */
    public static function describe( string $post_type ): ?array {
        if ( array_key_exists( $post_type, self::$discovered ) ) {
            return self::$discovered[ $post_type ];
        }

        $object = get_post_type_object( $post_type );

        if ( ! $object instanceof WP_Post_Type || ! self::is_eligible_post_type( $post_type ) ) {
            self::$discovered[ $post_type ] = null;
            return null;
        }

        self::$discovered[ $post_type ] = [
            'postType'      => $post_type,
            'label'         => (string) $object->labels->name,
            'singularLabel' => (string) $object->labels->singular_name,
            'supports'      => [
                'thumbnail' => post_type_supports( $post_type, 'thumbnail' ),
                'excerpt'   => post_type_supports( $post_type, 'excerpt' ),
                'editor'    => post_type_supports( $post_type, 'editor' ),
            ],
            'taxonomies'    => self::discover_taxonomies( $post_type ),
            'fields'        => self::discover_fields( $post_type ),
        ];

        return self::$discovered[ $post_type ];
    }

    /**
     * Viewable taxonomies attached to a post type.
     *
     * @param string $post_type The post type name.
     * @return array[]
     */
    private static function discover_taxonomies( string $post_type ): array {
        $taxonomies = [];

        foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
            if ( 'post_format' === $taxonomy->name || ! is_taxonomy_viewable( $taxonomy ) ) {
                continue;
            }

            $taxonomies[] = [
                'taxonomy'     => (string) $taxonomy->name,
                'label'        => (string) $taxonomy->labels->name,
                'hierarchical' => (bool) $taxonomy->hierarchical,
            ];
        }

        return $taxonomies;
    }

    /**
     * Custom fields that could be exposed for a post type: top-level ACF fields
     * in groups located on it, then meta registered for it with show_in_rest.
     * Protected (`_`-prefixed) meta is only ever reachable as an ACF field.
     *
     * @param string $post_type The post type name.
     * @return array[]
     */
    private static function discover_fields( string $post_type ): array {
        $fields = [];

        if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
            foreach ( acf_get_field_groups( [ 'post_type' => $post_type ] ) as $group ) {
                foreach ( (array) acf_get_fields( $group ) as $field ) {
                    $type = self::ACF_TYPES[ $field['type'] ?? '' ] ?? null;
                    $name = (string) ( $field['name'] ?? '' );

                    if ( null === $type || '' === $name || isset( $fields[ $name ] ) ) {
                        continue;
                    }

                    $fields[ $name ] = [
                        'key'    => $name,
                        'label'  => (string) ( $field['label'] ?? $name ),
                        'type'   => $type,
                        'source' => 'acf',
                        'group'  => (string) ( $group['title'] ?? '' ),
                        // Resolves the definition without relying on the
                        // per-post reference meta, which posts saved outside
                        // the ACF UI (imports, REST, WP-CLI) do not carry.
                        'acfKey' => (string) ( $field['key'] ?? '' ),
                    ];
                }
            }
        }

        $registered = array_merge(
            get_registered_meta_keys( 'post', '' ),
            get_registered_meta_keys( 'post', $post_type )
        );

        foreach ( $registered as $key => $args ) {
            $key  = (string) $key;
            $type = self::META_TYPES[ $args['type'] ?? '' ] ?? null;

            if ( null === $type || empty( $args['show_in_rest'] ) || isset( $fields[ $key ] ) || is_protected_meta( $key, 'post' ) ) {
                continue;
            }

            $fields[ $key ] = [
                'key'    => $key,
                'label'  => ucwords( str_replace( [ '_', '-' ], ' ', $key ) ),
                'type'   => $type,
                'source' => 'meta',
                'group'  => '',
            ];
        }

        return array_values( $fields );
    }

    /**
     * The stored per-app selections, as saved.
     *
     * @return array<string, array>
     */
    private static function stored(): array {
        $stored = get_option( self::OPTION, [] );

        return is_array( $stored ) ? $stored : [];
    }

    /**
     * The union of every app's selection, re-validated against the site now.
     * Several apps can share one site key, so a read serves what any of them
     * exposed.
     *
     * @return array<string, array{taxonomies: string[], fields: string[]}>
     */
    private static function allowed(): array {
        if ( null !== self::$allowed ) {
            return self::$allowed;
        }

        $allowed = [
            self::DEFAULT_POST_TYPE => [
                'taxonomies' => [ 'category' ],
                'fields'     => [],
            ],
        ];

        foreach ( self::stored() as $selections ) {
            foreach ( (array) $selections as $selection ) {
                $effective = self::validate_selection( (array) $selection );

                if ( null === $effective ) {
                    continue;
                }

                $post_type = $effective['postType'];
                $current   = $allowed[ $post_type ] ?? [
                    'taxonomies' => [],
                    'fields'     => [],
                ];

                $allowed[ $post_type ] = [
                    'taxonomies' => array_values( array_unique( array_merge( $current['taxonomies'], array_column( $effective['taxonomies'], 'taxonomy' ) ) ) ),
                    'fields'     => array_values( array_unique( array_merge( $current['fields'], array_column( $effective['fields'], 'key' ) ) ) ),
                ];
            }
        }

        self::$allowed = $allowed;

        return $allowed;
    }

    /**
     * What public reads serve right now: blog posts plus every post type any
     * app exposed, each with only its allowed taxonomies and fields. This is
     * what the app and the builder's post type pickers list.
     *
     * @return array[]
     */
    public static function served(): array {
        $served = [];

        foreach ( self::allowed() as $post_type => $allowed ) {
            $described = self::describe( (string) $post_type );

            if ( null === $described ) {
                continue;
            }

            $taxonomies = [];
            foreach ( $allowed['taxonomies'] as $name ) {
                foreach ( $described['taxonomies'] as $taxonomy ) {
                    if ( $taxonomy['taxonomy'] === $name ) {
                        $taxonomies[] = $taxonomy;
                    }
                }
            }

            $served[] = [
                'postType'      => $described['postType'],
                'label'         => $described['label'],
                'singularLabel' => $described['singularLabel'],
                'taxonomies'    => $taxonomies,
                'fields'        => array_values(
                    array_filter(
                        $described['fields'],
                        function ( array $field ) use ( $allowed ) {
                            return in_array( $field['key'], $allowed['fields'], true );
                        }
                    )
                ),
            ];
        }

        return $served;
    }

    /**
     * Whether the app may read a post type.
     *
     * @param string $post_type The post type name.
     * @return bool
     */
    public static function is_allowed_post_type( string $post_type ): bool {
        return isset( self::allowed()[ $post_type ] );
    }

    /**
     * Taxonomies the app may read for a post type, primary first.
     *
     * @param string $post_type The post type name.
     * @return string[]
     */
    public static function allowed_taxonomies( string $post_type ): array {
        return self::allowed()[ $post_type ]['taxonomies'] ?? [];
    }

    /**
     * The taxonomy used when a request names none: `category` for posts,
     * otherwise the first one selected for the type.
     *
     * @param string $post_type The post type name.
     * @return string|null
     */
    public static function primary_taxonomy( string $post_type ): ?string {
        return self::allowed_taxonomies( $post_type )[0] ?? null;
    }

    /**
     * Field definitions the app may read for a post type, keyed by field key.
     *
     * @param string $post_type The post type name.
     * @return array<string, array>
     */
    public static function allowed_fields( string $post_type ): array {
        $keys = self::allowed()[ $post_type ]['fields'] ?? [];

        if ( empty( $keys ) ) {
            return [];
        }

        $described = self::describe( $post_type );
        $fields    = [];

        foreach ( $described['fields'] ?? [] as $field ) {
            if ( in_array( $field['key'], $keys, true ) ) {
                $fields[ $field['key'] ] = $field;
            }
        }

        return $fields;
    }

    /**
     * Reduce one selection to what the site actually offers, filling in labels
     * and types from discovery. Null when the post type itself is not eligible.
     *
     * @param array $selection `{postType, taxonomies: string[], fields: string[]}`.
     * @return array|null
     */
    private static function validate_selection( array $selection ): ?array {
        $post_type = sanitize_key( (string) ( $selection['postType'] ?? '' ) );
        $described = '' === $post_type ? null : self::describe( $post_type );

        if ( null === $described ) {
            return null;
        }

        $taxonomies = array_map( 'strval', (array) ( $selection['taxonomies'] ?? [] ) );
        $fields     = array_map( 'strval', (array) ( $selection['fields'] ?? [] ) );

        return [
            'postType'      => $post_type,
            'label'         => $described['label'],
            'singularLabel' => $described['singularLabel'],
            // Keep the owner's order: the first taxonomy is the primary one.
            'taxonomies'    => array_values(
                array_filter(
                    array_map(
                        function ( string $name ) use ( $described ) {
                            foreach ( $described['taxonomies'] as $taxonomy ) {
                                if ( $taxonomy['taxonomy'] === $name ) {
                                    return $taxonomy;
                                }
                            }
                            return null;
                        },
                        $taxonomies
                    )
                )
            ),
            'fields'        => array_values(
                array_filter(
                    $described['fields'],
                    function ( array $field ) use ( $fields ) {
                        return in_array( $field['key'], $fields, true );
                    }
                )
            ),
        ];
    }

    /**
     * The effective selection stored for one app.
     *
     * @param string $app_id The Studio app id.
     * @return array[]
     */
    public static function get_for_app( string $app_id ): array {
        $effective = [];

        foreach ( (array) ( self::stored()[ $app_id ] ?? [] ) as $selection ) {
            $validated = self::validate_selection( (array) $selection );

            if ( null !== $validated ) {
                $effective[] = $validated;
            }
        }

        return $effective;
    }

    /**
     * Store one app's selection, dropping anything the site does not offer,
     * and return what was kept so Studio never advertises a rejected field.
     *
     * @param string $app_id     The Studio app id.
     * @param array  $selections List of `{postType, taxonomies, fields}`.
     * @return array[]
     */
    public static function save_for_app( string $app_id, array $selections ): array {
        $effective = [];
        $seen      = [];

        // No site has more than a handful of post types to expose; a longer list is noise.
        foreach ( array_slice( $selections, 0, self::MAX_SELECTIONS ) as $selection ) {
            $validated = is_array( $selection ) ? self::validate_selection( $selection ) : null;

            if ( null === $validated || isset( $seen[ $validated['postType'] ] ) ) {
                continue;
            }

            $seen[ $validated['postType'] ] = true;
            $effective[]                    = $validated;
        }

        $stored = self::stored();

        if ( empty( $effective ) ) {
            unset( $stored[ $app_id ] );
        } else {
            $stored[ $app_id ] = array_map(
                function ( array $selection ): array {
                    return [
                        'postType'   => $selection['postType'],
                        'taxonomies' => array_column( $selection['taxonomies'], 'taxonomy' ),
                        'fields'     => array_column( $selection['fields'], 'key' ),
                    ];
                },
                $effective
            );
        }

        update_option( self::OPTION, $stored, false );
        self::flush();

        return $effective;
    }

    /**
     * Every app's effective selection, for the settings screen.
     *
     * @return array<string, array[]>
     */
    public static function all(): array {
        $all = [];

        foreach ( array_keys( self::stored() ) as $app_id ) {
            $all[ (string) $app_id ] = self::get_for_app( (string) $app_id );
        }

        return $all;
    }
}
