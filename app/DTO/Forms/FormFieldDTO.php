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

    private string $fieldName;

    private ?string $iconName = null;

    private array $items = [];

    private ?string $rangeMode = null;

    private ?float $minValue = null;

    private ?float $maxValue = null;

    private ?float $rangeStep = null;

    private ?int $ratingMax = null;

    private ?string $ratingIconName = null;

    private ?string $pickerType = null;

    private ?string $dateFormat = null;

    private ?int $characterLimit = null;

    private ?int $minLength = null;

    private ?int $maxLength = null;

    private ?bool $showConfirmation = null;

    private ?string $confirmLabel = null;

    private ?string $confirmPlaceholder = null;

    private static array $universalProperties = [
        'id', 'type', 'required', 'label', 'fieldName', 'placeholder',
    ];

    private static array $typePropertyMap = [
        'text'             => ['characterLimit'],
        'email'            => [],
        'url'              => [],
        'number'           => ['minLength', 'maxLength'],
        'checkbox'         => ['items'],
        'radio'            => ['items'],
        'single_select'    => ['items'],
        'date_time_picker' => ['pickerType', 'dateFormat'],
        'password'         => ['minLength', 'showConfirmation', 'confirmLabel', 'confirmPlaceholder'],
        'range'            => ['rangeMode', 'minValue', 'maxValue', 'rangeStep'],
        'rating'           => ['ratingMax', 'ratingIconName'],
        'gdpr'             => [],
    ];

    public function __construct() {
        $this->exclude_to_array = ['universalProperties', 'typePropertyMap'];
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

    public function get_fieldName(): string {
        return $this->fieldName;
    }

    public function set_fieldName( string $fieldName ): self {
        $this->fieldName = $fieldName;
        return $this;
    }

    public function get_iconName(): ?string {
        return $this->iconName;
    }

    public function set_iconName( ?string $iconName ): self {
        $this->iconName = $iconName;
        return $this;
    }

    public function get_items(): array {
        return $this->items;
    }

    public function set_items( array $items ): self {
        $this->items = array_map( function ( $item ) {
            if ( empty( $item['value'] ) && ! empty( $item['label'] ) ) {
                $item['value'] = $item['label'];
            }
            return $item;
        }, $items );
        return $this;
    }

    public function get_rangeMode(): ?string {
        return $this->rangeMode;
    }

    public function set_rangeMode( ?string $rangeMode ): self {
        $this->rangeMode = $rangeMode;
        return $this;
    }

    public function get_minValue(): ?float {
        return $this->minValue;
    }

    public function set_minValue( ?float $minValue ): self {
        $this->minValue = $minValue;
        return $this;
    }

    public function get_maxValue(): ?float {
        return $this->maxValue;
    }

    public function set_maxValue( ?float $maxValue ): self {
        $this->maxValue = $maxValue;
        return $this;
    }

    public function get_rangeStep(): ?float {
        return $this->rangeStep;
    }

    public function set_rangeStep( ?float $rangeStep ): self {
        $this->rangeStep = $rangeStep;
        return $this;
    }

    public function get_ratingMax(): ?int {
        return $this->ratingMax;
    }

    public function set_ratingMax( ?int $ratingMax ): self {
        $this->ratingMax = $ratingMax;
        return $this;
    }

    public function get_ratingIconName(): ?string {
        return $this->ratingIconName;
    }

    public function set_ratingIconName( ?string $ratingIconName ): self {
        $this->ratingIconName = $ratingIconName;
        return $this;
    }

    public function get_pickerType(): ?string {
        return $this->pickerType;
    }

    public function set_pickerType( ?string $pickerType ): self {
        $this->pickerType = $pickerType;
        return $this;
    }

    public function get_dateFormat(): ?string {
        return $this->dateFormat;
    }

    public function set_dateFormat( ?string $dateFormat ): self {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    public function get_characterLimit(): ?int {
        return $this->characterLimit;
    }

    public function set_characterLimit( ?int $characterLimit ): self {
        $this->characterLimit = $characterLimit;
        return $this;
    }

    public function get_minLength(): ?int {
        return $this->minLength;
    }

    public function set_minLength( ?int $minLength ): self {
        $this->minLength = $minLength;
        return $this;
    }

    public function get_maxLength(): ?int {
        return $this->maxLength;
    }

    public function set_maxLength( ?int $maxLength ): self {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function is_showConfirmation(): ?bool {
        return $this->showConfirmation;
    }

    public function set_showConfirmation( ?bool $showConfirmation ): self {
        $this->showConfirmation = $showConfirmation;
        return $this;
    }

    public function get_confirmLabel(): ?string {
        return $this->confirmLabel;
    }

    public function set_confirmLabel( ?string $confirmLabel ): self {
        $this->confirmLabel = $confirmLabel;
        return $this;
    }

    public function get_confirmPlaceholder(): ?string {
        return $this->confirmPlaceholder;
    }

    public function set_confirmPlaceholder( ?string $confirmPlaceholder ): self {
        $this->confirmPlaceholder = $confirmPlaceholder;
        return $this;
    }

    public function to_array(): array {
        $data = parent::to_array();
        $type = $data['type'] ?? '';

        $allowed = self::$universalProperties;
        if ( isset( self::$typePropertyMap[$type] ) ) {
            $allowed = array_merge( $allowed, self::$typePropertyMap[$type] );
        }

		return array_filter(
			array_intersect_key( $data, array_flip( $allowed ) ),
			fn( $value ) => $value !== null
		);
    }
}
