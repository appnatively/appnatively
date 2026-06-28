
<?php

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\Http\Controllers\FormController;
use Crafium\AppNatively\WpMVC\Routing\Route;

Route::post( 'form', [FormController::class, 'store'] );