<?php

namespace Crafium\AppNatively\App\Http\Controllers\Blog;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\Blog\PostCategoryDTO;
use Crafium\AppNatively\App\DTO\Blog\PostCategoryPaginatorDTO;
use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;
use WP_Term;

class CategoryController extends Controller {
    /**
     * The allowed fields for the resource.
     *
     * @var array
     */
    protected array $allowed_fields = [
        "id",
        "name",
        "slug",
        "description",
        "parent",
        "count",
    ];

    /**
     * Display a listing of the resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $request->validate(
            [
                "page"     => "nullable|integer|min:1",
                "per_page" => "nullable|integer|min:1|max:100",
                "search"   => "nullable|string",
                "sort"     => "nullable|string",
            ]
        );

        $page     = (int) $request->get_param( "page" ) ?: 1;
        $per_page = (int) $request->get_param( "per_page" ) ?: 10;
        $search   = sanitize_text_field( (string) $request->get_param( "search" ) );

        $sort     = sanitize_text_field( (string) $request->get_param( "sort" ) );
        $order_by = "name";
        $order    = "ASC";

        if ( ! empty( $sort ) ) {
            if ( 0 === strpos( $sort, "-" ) ) {
                $order_by = ltrim( $sort, "-" );
                $order    = "DESC";
            } else {
                $order_by = $sort;
                $order    = "ASC";
            }
        }

        $sort_map = [
            "name"  => "name",
            "id"    => "id",
            "slug"  => "slug",
            "count" => "count",
        ];

        $term_args = [
            "taxonomy"   => "category",
            "hide_empty" => false,
            "number"     => $per_page,
            "offset"     => ( $page - 1 ) * $per_page,
            "orderby"    => $sort_map[$order_by] ?? "name",
            "order"      => $order,
        ];

        if ( ! empty( $search ) ) {
            $term_args["search"] = $search;
        }

        $terms = get_terms( $term_args );
        $total = (int) wp_count_terms(
            array_merge(
                $term_args,
                [
                    "number" => 0,
                    "offset" => 0,
                ]
            )
        );

        $items = [];
        foreach ( $terms as $term ) {
            if ( $term instanceof WP_Term ) {
                $items[] = $this->map_term_to_dto( $term );
            }
        }

        $category_paginator = new PostCategoryPaginatorDTO(
            $page,
            $per_page,
            $total,
            (int) max( 1, ceil( $total / $per_page ) ),
            $items
        );

        return Response::send( ["data" => $category_paginator] );
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request The REST request instance.
     * @return array
     * @throws Exception
     */
    public function show( Request $request ): array {
        $request->validate(
            [
                "id" => "required|numeric",
            ]
        );

        $id   = (int) craf_appna_route_param( $request, "id" );
        $term = get_term( $id, "category" );

        if ( ! $term instanceof WP_Term ) {
            throw new Exception( esc_html__( "Category not found", "appnatively" ) );
        }

        return Response::send(
            [
                "data" => $this->map_term_to_dto( $term ),
            ]
        );
    }

    /**
     * Map a WP_Term to a PostCategoryDTO.
     *
     * @param WP_Term $term The term.
     * @return PostCategoryDTO
     */
    private function map_term_to_dto( WP_Term $term ): PostCategoryDTO {
        return ( new PostCategoryDTO() )
            ->set_id( (int) $term->term_id )
            ->set_name( (string) $term->name )
            ->set_slug( (string) $term->slug )
            ->set_description( (string) $term->description )
            ->set_parent( (int) $term->parent )
            ->set_count( (int) $term->count );
    }
}
