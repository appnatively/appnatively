<?php

namespace Crafium\AppNatively\App\Http\Controllers\Concerns;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Filter\FiltersDTO;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

/**
 * The list, filters and filter-sources actions shared by filterable resources
 * (products, directory listings). Integrations answer them through
 * Filtering\CatalogQuery.
 */
trait ServesFilters {
    /**
     * Validation rules for the params that narrow a filterable list, shared by the list
     * and its filters action. Nested values are read as scalars and allow-listed by
     * the integration's catalog.
     *
     * - categories / tags / locations: term ids from the page or list (hierarchical ones include children)
     * - $context_flags:                a list's own settings (`in_stock`, `featured`)
     * - values:                        the filter selection, [facet id => value[]]
     * - ranges:                        the filter selection, [facet id => {min, max}]
     * - near:                          the shopper's location, {lat, lng}, for distance
     *
     * @param string[] $integrations  Valid integration slugs.
     * @param string[] $context_flags Boolean list settings the catalog understands.
     * @return array
     */
    protected function filter_context_rules( array $integrations, array $context_flags ): array {
        $rules = [
            "search"      => "nullable|string",
            "categories"  => "nullable|array|max:100",
            "tags"        => "nullable|array|max:100",
            "locations"   => "nullable|array|max:100",
            "values"      => "nullable|array",
            "ranges"      => "nullable|array",
            "near"        => "nullable|array",
            "integration" => "required|string|" . craf_appna_in_rule( $integrations ),
        ];
        foreach ( $context_flags as $flag ) {
            $rules[ $flag ] = "nullable|boolean";
        }
        return $rules;
    }

    /**
     * Rules for one page of a filterable list.
     *
     * @return array
     */
    protected function filter_list_rules(): array {
        return [
            "page"     => "nullable|integer|min:1",
            "per_page" => "nullable|integer|min:1|max:100",
            "sort"     => "nullable|string|in:" . implode( ',', FiltersDTO::SORT_TOKENS ),
        ];
    }

    /**
     * Which filters are available for the request's context, with per-option counts.
     *
     * @param Request $request
     * @param array $context_rules See filter_context_rules().
     * @param string $hook The integration's filter hook, `%s` standing for its slug.
     * @return array
     * @throws Exception
     */
    protected function send_filters( Request $request, array $context_rules, string $hook ): array {
        $request->validate( array_merge( $context_rules, [ "facets" => "nullable|array" ] ) );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $filters     = apply_filters( sprintf( $hook, $integration ), null, $request );

        if ( ! $filters instanceof FiltersDTO ) {
            throw new Exception( esc_html__( "Filters integration not found", 'appnatively' ) );
        }

        return Response::send( [ "data" => $filters ] );
    }

    /**
     * What the app builder can offer as filter rows (taxonomies, attributes, custom fields).
     *
     * @param Request $request
     * @param string[] $integrations Valid integration slugs.
     * @param string $hook The integration's filter hook, `%s` standing for its slug.
     * @return array
     */
    protected function send_filter_sources( Request $request, array $integrations, string $hook ): array {
        $request->validate( [ "integration" => "required|string|" . craf_appna_in_rule( $integrations ) ] );

        $integration = sanitize_text_field( $request->get_param( "integration" ) );
        $sources     = apply_filters( sprintf( $hook, $integration ), [], $request );

        return Response::send( [ "data" => [ "sources" => is_array( $sources ) ? array_values( $sources ) : [] ] ] );
    }
}
