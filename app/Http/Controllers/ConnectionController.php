<?php
/**
 * ConnectionController class
 *
 * @package Crafium\AppNatively\App\Http\Controllers
 */

namespace Crafium\AppNatively\App\Http\Controllers;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\WpMVC\Routing\Response;
use Crafium\AppNatively\WpMVC\RequestValidator\Request;

/**
 * Class ConnectionController
 *
 * Answers the "is AppNatively installed and reachable here?" handshake that
 * Studio performs before the site owner supplies a connection key.
 *
 * Deliberately says nothing about the site's configuration: no plugin list, no
 * user data, no settings, and no version. Anything that describes the site —
 * including which release of this plugin is installed, which is what someone
 * scanning for sites on a known-vulnerable version is looking for — sits behind
 * the connection key instead, on the /plugins endpoint.
 */
class ConnectionController extends Controller {
    /**
     * Report that the bridge is present.
     *
     * @param Request $request The REST request instance.
     * @return array
     */
    public function index( Request $request ): array {
        return Response::send(
            [
                "connected" => true,
                "name"      => "AppNatively",
                "version"   => craf_appna_version(),
            ]
        );
    }
}
