<?php

namespace Crafium\AppNatively\App\Models;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\WpMVC\Database\Eloquent\Model;
use Crafium\AppNatively\WpMVC\Database\Query\Builder;

/** Query-only row adapter: preserve raw values without firing model lifecycle actions. */
class DatabaseRow extends Model {
    public bool $timestamps = false;

    public static function get_table_name(): string {
        return 'posts';
    }

    public static function from_table( string $table, ?string $alias = null ): Builder {
        return ( new static() )->new_query()->from( $table, $alias );
    }

    public static function rows( Builder $query ): array {
        $rows = [];
        foreach ( $query->get() as $row ) {
            $rows[] = $row->get_attributes();
        }
        return $rows;
    }

    public static function values( Builder $query, string $column ): array {
        $values = [];
        foreach ( $query->select( [ $column ] )->get() as $row ) {
            $attribute = substr( strrchr( '.' . $column, '.' ), 1 );
            $values[]  = $row->{$attribute};
        }
        return $values;
    }

    public static function value( Builder $query, string $column ) {
        $row       = $query->select( [ $column ] )->first();
        $attribute = substr( strrchr( '.' . $column, '.' ), 1 );
        return $row ? $row->{$attribute} : null;
    }

    public function new_from_builder( array $attributes = [] ) {
        $row             = new static();
        $row->attributes = $attributes;
        $row->exists     = true;
        $row->sync_original();
        return $row;
    }
}
