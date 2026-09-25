<?php
/**
 * Reads exposed custom field values into shapes the app can render.
 *
 * @package Crafium\AppNatively\App\Support
 */

namespace Crafium\AppNatively\App\Support;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WP_Term;

/**
 * Class CustomFields
 *
 * Values are read unformatted and normalized from the field definition, so the
 * output does not depend on each ACF field's `return_format` setting. Every
 * value comes back as `{type, value}`; empty values are left out entirely.
 */
class CustomFields {
    /**
     * Read the given fields of a post.
     *
     * @param WP_Post $post The post.
     * @param array   $defs Field definitions from ContentTypes::allowed_fields(), keyed by key.
     * @return array<string, array{type: string, value: mixed}>
     */
    public static function read( WP_Post $post, array $defs ): array {
        $values = [];

        foreach ( $defs as $key => $def ) {
            $value = 'acf' === $def['source']
                ? self::read_acf( $post, (string) $key, $def )
                : self::read_meta( $post, (string) $key, $def['type'] );

            if ( null === $value || '' === $value || [] === $value ) {
                continue;
            }

            $values[ $key ] = [
                'type'  => $def['type'],
                'value' => $value,
            ];
        }

        return $values;
    }

    /**
     * Read an ACF field, unformatted.
     *
     * @param WP_Post $post The post.
     * @param string  $key  The field name.
     * @param array   $def  The field definition, carrying its ACF `acfKey`.
     * @return mixed
     */
    private static function read_acf( WP_Post $post, string $key, array $def ) {
        $field = function_exists( 'acf_get_field' ) ? acf_get_field( (string) ( $def['acfKey'] ?? $key ) ) : false;

        if ( ! is_array( $field ) || ! function_exists( 'acf_get_value' ) ) {
            return self::read_meta( $post, $key, $def['type'] );
        }

        return self::normalize( acf_get_value( $post->ID, $field ), $def['type'], (array) ( $field['choices'] ?? [] ) );
    }

    /**
     * Read a registered meta key.
     *
     * @param WP_Post $post The post.
     * @param string  $key  The meta key.
     * @param string  $type The normalized type.
     * @return mixed
     */
    private static function read_meta( WP_Post $post, string $key, string $type ) {
        return self::normalize( get_post_meta( $post->ID, $key, true ), $type, [] );
    }

    /**
     * Normalize a raw stored value by its type.
     *
     * @param mixed  $raw     The raw value.
     * @param string $type    The normalized type.
     * @param array  $choices Choice labels for select-like fields.
     * @return mixed
     */
    private static function normalize( $raw, string $type, array $choices ) {
        if ( null === $raw || '' === $raw || false === $raw && 'boolean' !== $type ) {
            return null;
        }

        switch ( $type ) {
            case 'number':
                return is_numeric( $raw ) ? $raw + 0 : null;

            case 'boolean':
                return (bool) $raw;

            case 'wysiwyg':
                return wp_kses_post( wpautop( (string) $raw ) );

            case 'textarea':
                return sanitize_textarea_field( (string) $raw );

            case 'url':
                // ACF page_link fields store a post id.
                if ( is_numeric( $raw ) ) {
                    return 'publish' === get_post_status( (int) $raw ) ? (string) get_permalink( (int) $raw ) : null;
                }
                return is_scalar( $raw ) ? esc_url_raw( (string) $raw ) : null;

            case 'email':
                return is_scalar( $raw ) ? sanitize_email( (string) $raw ) : null;

            case 'date':
                return is_scalar( $raw ) ? self::format_date( (string) $raw, 'Y-m-d' ) : null;

            case 'datetime':
                return is_scalar( $raw ) ? self::format_date( (string) $raw, 'Y-m-d\TH:i:s' ) : null;

            case 'image':
                return self::image( $raw );

            case 'gallery':
                return array_values( array_filter( array_map( [ self::class, 'image' ], (array) $raw ) ) );

            case 'file':
                return self::file( $raw );

            case 'link':
                return self::link( $raw );

            case 'select':
                return array_values(
                    array_map(
                        function ( $value ) use ( $choices ) {
                            $value = (string) $value;
                            $label = $choices[ $value ] ?? $value;
                            return [
                                'value' => $value,
                                // ACF option groups hold an array of choices, not a label.
                                'label' => is_scalar( $label ) ? (string) $label : $value,
                            ];
                        },
                        array_filter( (array) $raw, 'is_scalar' )
                    )
                );

            case 'post_object':
                return self::posts( (array) $raw );

            case 'taxonomy':
                return self::terms( (array) $raw );

            case 'google_map':
                return self::location( $raw );

            default:
                return is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : null;
        }
    }

    /**
     * Re-format a stored date. ACF keeps dates as `Ymd` and datetimes as
     * `Y-m-d H:i:s`; anything strtotime understands is accepted.
     *
     * @param string $raw    The stored value.
     * @param string $format The output format.
     * @return string|null
     */
    private static function format_date( string $raw, string $format ): ?string {
        $date = preg_match( '/^\d{8}$/', $raw )
            ? \DateTime::createFromFormat( 'Ymd', $raw, wp_timezone() )
            : date_create( $raw, wp_timezone() );

        return $date ? $date->format( $format ) : null;
    }

    /**
     * Whether an attachment may be served: it is not trashed, and its parent post, when it has
     * one, is itself published and not password-protected. An id stored in a field is otherwise
     * a way to reach a file attached to a draft or private post.
     *
     * @param int $id The attachment id.
     * @return bool
     */
    public static function is_public_attachment( int $id ): bool {
        $attachment = get_post( $id );

        if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || 'trash' === $attachment->post_status ) {
            return false;
        }

        if ( ! $attachment->post_parent ) {
            return true;
        }

        $parent = get_post( (int) $attachment->post_parent );

        return $parent instanceof WP_Post
            && 'publish' === $parent->post_status
            && ! craf_appna_is_post_password_protected( $parent );
    }

    /**
     * An attachment as `{id, src, alt}`, the same shape as a post thumbnail.
     *
     * @param mixed $raw Attachment id, or an ACF image array.
     * @return array|null
     */
    public static function image( $raw ): ?array {
        $id = is_array( $raw ) ? (int) ( $raw['ID'] ?? $raw['id'] ?? 0 ) : (int) $raw;

        if ( ! $id || ! wp_attachment_is_image( $id ) || ! self::is_public_attachment( $id ) ) {
            return null;
        }

        $src = wp_get_attachment_url( $id );

        if ( ! $src ) {
            return null;
        }

        return [
            'id'  => $id,
            'src' => (string) $src,
            'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
        ];
    }

    /**
     * A file attachment as `{id, url, title}`.
     *
     * @param mixed $raw Attachment id.
     * @return array|null
     */
    private static function file( $raw ): ?array {
        $id  = is_array( $raw ) ? (int) ( $raw['ID'] ?? $raw['id'] ?? 0 ) : (int) $raw;
        $url = $id && self::is_public_attachment( $id ) ? wp_get_attachment_url( $id ) : false;

        if ( ! $url ) {
            return null;
        }

        return [
            'id'    => $id,
            'url'   => (string) $url,
            'title' => get_the_title( $id ),
        ];
    }

    /**
     * An ACF link as `{url, title, target}`.
     *
     * @param mixed $raw The stored link array, or a bare URL.
     * @return array|null
     */
    private static function link( $raw ): ?array {
        $link = is_array( $raw ) ? $raw : [ 'url' => (string) $raw ];
        $url  = esc_url_raw( (string) ( $link['url'] ?? '' ) );

        if ( '' === $url ) {
            return null;
        }

        return [
            'url'    => $url,
            'title'  => sanitize_text_field( (string) ( $link['title'] ?? '' ) ),
            'target' => '_blank' === ( $link['target'] ?? '' ) ? '_blank' : '',
        ];
    }

    /**
     * Linked posts as `{id, title, postType}`, keeping only ones the app may
     * read on its own: published, not password protected, of an allowed type.
     *
     * @param array $ids Post ids (or posts).
     * @return array[]
     */
    private static function posts( array $ids ): array {
        $posts = [];

        foreach ( $ids as $id ) {
            $post = get_post( $id instanceof WP_Post ? $id->ID : (int) $id );

            if ( ! $post instanceof WP_Post
                || 'publish' !== $post->post_status
                || craf_appna_is_post_password_protected( $post )
                || ! ContentTypes::is_allowed_post_type( $post->post_type )
            ) {
                continue;
            }

            $posts[] = [
                'id'       => (int) $post->ID,
                'title'    => get_the_title( $post ),
                'postType' => (string) $post->post_type,
            ];
        }

        return $posts;
    }

    /**
     * Linked terms as `{id, name, slug, taxonomy}`, viewable taxonomies only.
     *
     * @param array $ids Term ids.
     * @return array[]
     */
    private static function terms( array $ids ): array {
        $terms = [];

        foreach ( $ids as $id ) {
            $term = get_term( $id instanceof WP_Term ? $id->term_id : (int) $id );

            if ( ! $term instanceof WP_Term || ! is_taxonomy_viewable( $term->taxonomy ) ) {
                continue;
            }

            $terms[] = [
                'id'       => (int) $term->term_id,
                'name'     => (string) $term->name,
                'slug'     => (string) $term->slug,
                'taxonomy' => (string) $term->taxonomy,
            ];
        }

        return $terms;
    }

    /**
     * An ACF Google Map value as `{address, lat, lng}`.
     *
     * @param mixed $raw The stored map array.
     * @return array|null
     */
    private static function location( $raw ): ?array {
        if ( ! is_array( $raw ) || ( empty( $raw['address'] ) && ! isset( $raw['lat'], $raw['lng'] ) ) ) {
            return null;
        }

        return [
            'address' => sanitize_text_field( (string) ( $raw['address'] ?? '' ) ),
            'lat'     => isset( $raw['lat'] ) && is_numeric( $raw['lat'] ) ? (float) $raw['lat'] : null,
            'lng'     => isset( $raw['lng'] ) && is_numeric( $raw['lng'] ) ? (float) $raw['lng'] : null,
        ];
    }
}
