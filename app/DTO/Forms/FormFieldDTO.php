<?php

namespace Crafium\AppNatively\App\DTO\Forms;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class FormFieldDTO extends DTO {
    private string $id;

    private string $type;

    private bool $required;

    private string $label;

    private ?string $placeholder = null;

    private string $field_name;

    private ?string $icon_name = null;

    private array $items = [];

    private ?string $range_mode = null;

    private ?float $min_value = null;

    private ?float $max_value = null;

    private ?float $range_step = null;

    private ?int $rating_max = null;

    private ?string $rating_icon_name = null;

    private ?string $picker_type = null;

    private ?string $date_format = null;

    private ?int $character_limit = null;

    private ?int $min_length = null;

    private ?int $max_length = null;

    private ?bool $show_confirmation = null;

    private ?string $confirm_label = null;

    private ?string $confirm_placeholder = null;

    private static array $universal_properties = [
        'id', 'fieldType', 'isRequired', 'fieldLabel', 'submissionKey', 'placeholderText', 'leadingIcon',
    ];

    private static array $type_property_map = [
        'text'             => ['minimumTextLength', 'maximumCharacterCount'],
        'email'            => ['minimumTextLength', 'maximumTextLength'],
        'url'              => ['minimumTextLength', 'maximumTextLength'],
        'number'           => ['minimumValue', 'maximumValue'],
        'checkbox'         => ['options'],
        'radio'            => ['options'],
        'single_select'    => ['options'],
        'multi_select'     => ['options'],
        'date_time_picker' => ['dateTimeMode', 'submittedDateTimeFormat'],
        'password'         => ['minimumTextLength', 'maximumTextLength', 'showConfirmationField', 'confirmationLabel', 'confirmationPlaceholder'],
        'range'            => ['rangeSelectionMode', 'minimumValue', 'maximumValue', 'valueStep'],
        'rating'           => ['maximumRating', 'ratingIcon'],
        'switch'           => [],
        'gdpr'             => [],
    ];

    public function __construct() {
        $this->exclude_to_array = ['universal_properties', 'type_property_map'];
    }

    public function get_id(): string {
        return $this->id;
    }

    public function set_id( string $id ): self {
        $this->id = $id;
        return $this;
    }

    public function get_type(): string {
        return $this->type;
    }

    public function set_type( string $type ): self {
        $this->type = $type;
        return $this;
    }

    public function is_required(): bool {
        return $this->required;
    }

    public function set_required( bool $required ): self {
        $this->required = $required;
        return $this;
    }

    public function get_label(): string {
        return $this->label;
    }

    public function set_label( string $label ): self {
        $this->label = $label;
        return $this;
    }

    public function get_placeholder(): ?string {
        return $this->placeholder;
    }

    public function set_placeholder( ?string $placeholder ): self {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function get_field_name(): string {
        return $this->field_name;
    }

    public function set_field_name( string $field_name ): self {
        $this->field_name = $field_name;
        return $this;
    }

    public function get_icon_name(): ?string {
        return $this->icon_name;
    }

    public function set_icon_name( ?string $icon_name ): self {
        $this->icon_name = $icon_name;
        return $this;
    }

    public function get_items(): array {
        return $this->items;
    }

    public function set_items( array $items ): self {
        $this->items = $items;
        return $this;
    }

    public function get_range_mode(): ?string {
        return $this->range_mode;
    }

    public function set_range_mode( ?string $range_mode ): self {
        $this->range_mode = $range_mode;
        return $this;
    }

    public function get_min_value(): ?float {
        return $this->min_value;
    }

    public function set_min_value( ?float $min_value ): self {
        $this->min_value = $min_value;
        return $this;
    }

    public function get_max_value(): ?float {
        return $this->max_value;
    }

    public function set_max_value( ?float $max_value ): self {
        $this->max_value = $max_value;
        return $this;
    }

    public function get_range_step(): ?float {
        return $this->range_step;
    }

    public function set_range_step( ?float $range_step ): self {
        $this->range_step = $range_step;
        return $this;
    }

    public function get_rating_max(): ?int {
        return $this->rating_max;
    }

    public function set_rating_max( ?int $rating_max ): self {
        $this->rating_max = $rating_max;
        return $this;
    }

    public function get_rating_icon_name(): ?string {
        return $this->rating_icon_name;
    }

    public function set_rating_icon_name( ?string $rating_icon_name ): self {
        $this->rating_icon_name = $rating_icon_name;
        return $this;
    }

    public function get_picker_type(): ?string {
        return $this->picker_type;
    }

    public function set_picker_type( ?string $picker_type ): self {
        $this->picker_type = $picker_type;
        return $this;
    }

    public function get_date_format(): ?string {
        return $this->date_format;
    }

    public function set_date_format( ?string $date_format ): self {
        $this->date_format = $date_format;
        return $this;
    }

    public function get_character_limit(): ?int {
        return $this->character_limit;
    }

    public function set_character_limit( ?int $character_limit ): self {
        $this->character_limit = $character_limit;
        return $this;
    }

    public function get_min_length(): ?int {
        return $this->min_length;
    }

    public function set_min_length( ?int $min_length ): self {
        $this->min_length = $min_length;
        return $this;
    }

    public function get_max_length(): ?int {
        return $this->max_length;
    }

    public function set_max_length( ?int $max_length ): self {
        $this->max_length = $max_length;
        return $this;
    }

    public function is_show_confirmation(): ?bool {
        return $this->show_confirmation;
    }

    public function set_show_confirmation( ?bool $show_confirmation ): self {
        $this->show_confirmation = $show_confirmation;
        return $this;
    }

    public function get_confirm_label(): ?string {
        return $this->confirm_label;
    }

    public function set_confirm_label( ?string $confirm_label ): self {
        $this->confirm_label = $confirm_label;
        return $this;
    }

    public function get_confirm_placeholder(): ?string {
        return $this->confirm_placeholder;
    }

    public function set_confirm_placeholder( ?string $confirm_placeholder ): self {
        $this->confirm_placeholder = $confirm_placeholder;
        return $this;
    }

    public function to_array(): array {
        $data = parent::to_array();
        $type = $data['type'] ?? '';

        $mapped = [
            'id'                      => $data['id'] ?? null,
            'fieldType'               => $type,
            'isRequired'              => $data['required'] ?? null,
            'fieldLabel'              => $data['label'] ?? null,
            'submissionKey'           => $data['field_name'] ?? null,
            'placeholderText'         => $data['placeholder'] ?? null,
            'leadingIcon'             => $data['icon_name'] ?? null,
            'options'                 => $data['items'] ?? [],
            'rangeSelectionMode'      => $data['range_mode'] ?? null,
            'minimumValue'            => $data['min_value'] ?? null,
            'maximumValue'            => $data['max_value'] ?? null,
            'valueStep'               => $data['range_step'] ?? null,
            'maximumRating'           => $data['rating_max'] ?? null,
            'ratingIcon'              => $data['rating_icon_name'] ?? null,
            'dateTimeMode'            => $data['picker_type'] ?? null,
            'submittedDateTimeFormat' => $data['date_format'] ?? null,
            'maximumCharacterCount'   => $data['character_limit'] ?? null,
            'minimumTextLength'       => $data['min_length'] ?? null,
            'maximumTextLength'       => $data['max_length'] ?? null,
            'showConfirmationField'   => $data['show_confirmation'] ?? null,
            'confirmationLabel'       => $data['confirm_label'] ?? null,
            'confirmationPlaceholder' => $data['confirm_placeholder'] ?? null,
        ];

        $allowed = self::$universal_properties;
        if ( isset( self::$type_property_map[$type] ) ) {
            $allowed = array_merge( $allowed, self::$type_property_map[$type] );
        }

        return array_filter(
            array_intersect_key( $mapped, array_flip( $allowed ) ),
            fn( $value ) => $value !== null && $value !== []
        );
    }
}
