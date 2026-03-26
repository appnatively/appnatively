<?php

namespace AppNatively\App\Models;

defined( 'ABSPATH' ) || exit;

use AppNatively\WpMVC\Database\Eloquent\Model;
use AppNatively\WpMVC\Database\Resolver;

/**
 * Class UserMeta
 *
 * Represents the WordPress usermeta table.
 *
 * @package AppNatively\App\Models
 */
class UserMeta extends Model {
    /**
     * Indicates if the model should handle timestamps.
     *
     * @var bool
     */
    public bool $timestamps = false;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected string $primary_key = 'umeta_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected array $fillable = [
        'user_id',
        'meta_key',
        'meta_value',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected array $casts = [
        'umeta_id' => 'int',
        'user_id'  => 'int',
    ];

    /**
     * Get the table name associated with the model.
     *
     * @return string
     */
    public static function get_table_name(): string {
        return 'usermeta';
    }

    /**
     * Get the resolver instance.
     *
     * @return Resolver
     */
    public function resolver(): Resolver {
        return new Resolver();
    }

    /**
     * Get the user that owns the meta.
     */
    public function user() {
        return $this->belongs_to( User::class, 'user_id', 'ID' );
    }
}
