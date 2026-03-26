<?php

namespace AppNatively\App\Http\Controllers;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Routing\Response;

class UserController extends Controller
{
    public function index() {
        return Response::send( ['message' => 'Hello WpMVC'] );
    }
}