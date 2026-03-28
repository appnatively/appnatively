<?php

namespace AppNatively\App\Models;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Database\Eloquent\Model;

/**
 * Class Connection
 *
 * Represents an AppNatively app-site connection.
 *
 * @package AppNatively\App\Models
 */
class Connection extends Model {
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [
        'app_id',
        'app_name',
        'status',
        'token',
        'site_url',
    ];

    /**
     * Get the table name associated with the model.
     *
     * @return string
     */
    public static function get_table_name(): string {
        return 'appnatively_connections';
    }
}
