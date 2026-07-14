<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class AttributeFacetDTO extends DTO {
    private string $taxonomy;

    private string $label;

    /**
     * @var AttributeFacetOptionDTO[]
     */
    private array $options;

    /**
     * Get the value of taxonomy.
     *
     * @return string
     */
    public function get_taxonomy(): string {
        return $this->taxonomy;
    }

    /**
     * Set the value of taxonomy.
     *
     * @param string $taxonomy
     *
     * @return self
     */
    public function set_taxonomy( string $taxonomy ): self {
        $this->taxonomy = $taxonomy;
        return $this;
    }

    /**
     * Get the value of label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->label;
    }

    /**
     * Set the value of label.
     *
     * @param string $label
     *
     * @return self
     */
    public function set_label( string $label ): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Get the value of options.
     *
     * @return AttributeFacetOptionDTO[]
     */
    public function get_options(): array {
        return $this->options;
    }

    /**
     * Set the value of options.
     *
     * @param AttributeFacetOptionDTO[] $options
     *
     * @return self
     */
    public function set_options( array $options ): self {
        $this->options = $options;
        return $this;
    }
}
