<?php

namespace Crafium\AppNatively\App\Http\Controllers\PostType;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\Http\Controllers\Controller;
use Crafium\AppNatively\App\Support\ContentTypes;
use Crafium\AppNatively\WpMVC\Exceptions\Exception;
use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

class PostTypeController extends Controller {
    /**
     * The post types the app can show, with their taxonomies and custom fields.
     * Only what the owner exposed in Studio, so it is as public as the posts.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        $items = ContentTypes::served();

        return Response::send(
            [
                "data" => [
                    "current_page" => 1,
                    "last_page"    => 1,
                    "per_page"     => count( $items ),
                    "total"        => count( $items ),
                    "items"        => $items,
                ],
            ]
        );
    }

    /**
     * What the site could expose, plus what this app currently exposes.
     * Key-protected: it describes the site's content model.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function available( Request $request ): array {
        $request->validate(
            [
                "app_id" => "nullable|string|max:64",
            ]
        );

        $app_id = $this->app_id( $request );

        return Response::send(
            [
                "data" => [
                    "available" => ContentTypes::discover(),
                    "selected"  => "" === $app_id ? [] : ContentTypes::get_for_app( $app_id ),
                    "acf"       => function_exists( "acf_get_field_groups" ),
                ],
            ]
        );
    }

    /**
     * Store an app's selection and return the part of it the site accepted.
     * Key-protected: writing it widens what the public reads serve.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function store( Request $request ): array {
        $request->validate(
            [
                "app_id"       => "required|string|max:64",
                "contentTypes" => "nullable|array",
            ]
        );

        // `app_id()` strips characters, so an id of only symbols would otherwise be stored under "".
        $app_id = $this->app_id( $request );

        if ( "" === $app_id ) {
            throw new Exception( esc_html__( "Invalid app id", "appnatively" ), 422 );
        }

        $selections = $request->get_param( "contentTypes" );

        return Response::send(
            [
                "data" => [
                    "selected" => ContentTypes::save_for_app( $app_id, is_array( $selections ) ? $selections : [] ),
                ],
            ]
        );
    }

    /**
     * The Studio app id, reduced to the characters an id can contain.
     *
     * @param Request $request The REST request instance.
     * @return string
     */
    private function app_id( Request $request ): string {
        return substr( (string) preg_replace( "/[^A-Za-z0-9_-]/", "", (string) $request->get_param( "app_id" ) ), 0, 64 );
    }
}
