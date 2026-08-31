<?php

namespace Crafium\AppNatively\App\DTO\Ecommerce;

defined( 'ABSPATH' ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class CouponDTO extends DTO {
    private string $code = '';

    private string $amount = '0';

    private string $description = '';

    public function get_code(): string {
        return $this->code;
    }

    public function set_code( string $code ): self {
        $this->code = $code;
        return $this;
    }

    public function get_amount(): string {
        return $this->amount;
    }

    public function set_amount( string $amount ): self {
        $this->amount = $amount; return $this;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function set_description( string $description ): self {
        $this->description = $description;
        return $this;
    }
}
