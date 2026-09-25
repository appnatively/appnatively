<?php

namespace Crafium\AppNatively\App\Filtering;

defined( "ABSPATH" ) || exit;

/**
 * A custom field a filter can narrow by: an ACF field (`meta:<key>` facets) or
 * one the plugin defines in its own form builder (`field:<key>` facets).
 * Stored either as post meta or as a column the catalog joins in prepare().
 */
final class Field {
    public const TYPE_CHOICE  = 'choice';
    public const TYPE_NUMBER  = 'number';
    public const TYPE_BOOLEAN = 'boolean';

    /** How a checked true/false field may be stored. */
    public const TRUTHY_VALUES = [ '1', 'true', 'yes', 'on' ];

    public string $label;

    /** `choice`, `number` or `boolean`: picks the facet kind and the displays that suit it. */
    public string $value_type;

    /** @var array<string, string> Stored value => label, when the field has fixed choices. */
    public array $choices;

    /** Several choices are stored in one value (see $list_pattern). */
    public bool $multiple;

    /** Allowed by default; others are listed to the builder as not enabled. */
    public bool $enabled;

    /** The meta key holding the value, for post meta. */
    public ?string $meta_key = null;

    /** The column expression holding the value, for a joined table. */
    public ?string $column = null;

    /**
     * How one chosen value appears inside a multiple value, `{value}` standing for it:
     * `"{value}"` in a PHP-serialized array (ACF), `{value}` in a comma list.
     */
    public string $list_pattern = '"{value}"';

    /**
     * @param array<string, string> $choices
     */
    private function __construct( string $label, string $value_type, array $choices, bool $multiple, bool $enabled ) {
        $this->label      = $label;
        $this->value_type = $value_type;
        $this->choices    = $choices;
        $this->multiple   = $multiple;
        $this->enabled    = $enabled;
    }

    /**
     * @param array<string, string> $choices
     */
    public static function meta( string $meta_key, string $label, string $value_type, array $choices = [], bool $multiple = false, bool $enabled = false ): self {
        $field           = new self( $label, $value_type, $choices, $multiple, $enabled );
        $field->meta_key = $meta_key;
        return $field;
    }

    /**
     * @param array<string, string> $choices
     */
    public static function column( string $column, string $label, string $value_type, array $choices = [], bool $multiple = false, bool $enabled = false ): self {
        $field         = new self( $label, $value_type, $choices, $multiple, $enabled );
        $field->column = $column;
        return $field;
    }

    public function with_list_pattern( string $pattern ): self {
        $this->list_pattern = $pattern;
        return $this;
    }
}
