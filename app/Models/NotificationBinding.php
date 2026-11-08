<?php

namespace Crafium\AppNatively\App\Models;

defined( 'ABSPATH' ) || exit;

/** Internal encrypted binding rows; never serialized as API resources. */
class NotificationBinding extends DatabaseRow {
    public bool $timestamps = false;

    public bool $incrementing = false;

    protected string $primary_key = 'source_id';

    public static function get_table_name(): string {
        return 'craf_appna_event_bindings';
    }

    public static function stored_rows(): array {
        $rows = [];
        foreach ( self::query()->select(
            [
                'source_id',
                'app_id',
                'payload',
                'storage_version'
            ]
        )->get() as $row ) {
            $rows[] = $row->get_attributes();
        }
        return $rows;
    }
}
