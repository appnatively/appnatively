<?php

namespace Crafium\AppNatively\App\DTO\Filter;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

/**
 * Something a merchant can add as a filter row in the app builder: a taxonomy,
 * an attribute, or a custom field (an ACF field or meta key, or one the plugin
 * defines itself).
 */
class FilterSourceDTO extends DTO {
    /** `taxonomy`, `attribute`, `meta` or `field` — the builder row's filterSource. */
    private string $source;

    /** Taxonomy name, attribute taxonomy, meta key or field key — the builder row's filterKey. */
    private string $key;

    private string $label;

    /** `choice`, `number` or `boolean`: narrows which displays suit it. */
    private string $value_type;

    /** Whether the site filters by it (custom fields must be allow-listed first). */
    private bool $enabled;

    public function get_source(): string {
        return $this->source;
    }

    public function set_source( string $source ): self {
        $this->source = $source;
        return $this;
    }

    public function get_key(): string {
        return $this->key;
    }

    public function set_key( string $key ): self {
        $this->key = $key;
        return $this;
    }

    public function get_label(): string {
        return $this->label;
    }

    public function set_label( string $label ): self {
        $this->label = $label;
        return $this;
    }

    public function get_value_type(): string {
        return $this->value_type;
    }

    public function set_value_type( string $value_type ): self {
        $this->value_type = $value_type;
        return $this;
    }

    public function is_enabled(): bool {
        return $this->enabled;
    }

    public function set_enabled( bool $enabled ): self {
        $this->enabled = $enabled;
        return $this;
    }
}
