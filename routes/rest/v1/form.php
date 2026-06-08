
<?php

defined( 'ABSPATH' ) || exit;

use AppNatively\App\Http\Controllers\FormController;
use AppNatively\WpMVC\Routing\Route;

Route::post( 'form', [FormController::class, 'store'] );