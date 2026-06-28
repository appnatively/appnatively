<?php

namespace Crafium\AppNatively\App\DTO;

defined( "ABSPATH" ) || exit;

use JsonSerializable;

abstract class DTO extends \Crafium\AppNatively\WpMVC\DTO\DTO implements JsonSerializable {
    protected array $exclude_to_array = [];

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array {
        return $this->to_array();
    }
}