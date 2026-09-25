<?php

namespace Crafium\AppNatively\App\DTO\PostType;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class PostDTO extends DTO {
    private int $id;

    private string $title;

    private string $slug;

    private string $excerpt;

    private string $content;

    private string $status;

    private string $url;

    private array $thumbnail = [];

    private string $date;

    private string $post_type;

    /**
     * Terms of every exposed taxonomy, keyed by taxonomy name.
     *
     * @var array<string, array[]>
     */
    private array $terms;

    /**
     * Exposed custom field values as `{type, value}`, keyed by field key.
     *
     * @var array<string, array>
     */
    private array $custom_fields;

    public function get_post_type(): string {
        return $this->post_type;
    }

    public function set_post_type( string $post_type ): self {
        $this->post_type = $post_type;
        return $this;
    }

    public function get_terms(): array {
        return $this->terms;
    }

    public function set_terms( array $terms ): self {
        $this->terms = $terms;
        return $this;
    }

    /**
     * @return array|object An empty list serializes as `{}` so clients always see an object.
     */
    public function get_custom_fields() {
        return empty( $this->custom_fields ) ? (object) [] : $this->custom_fields;
    }

    public function set_custom_fields( array $custom_fields ): self {
        $this->custom_fields = $custom_fields;
        return $this;
    }

    public function get_id(): int {
        return $this->id;
    }

    public function set_id( int $id ): self {
        $this->id = $id;
        return $this;
    }

    public function get_title(): string {
        return $this->title;
    }

    public function set_title( string $title ): self {
        $this->title = $title;
        return $this;
    }

    public function get_slug(): string {
        return $this->slug;
    }

    public function set_slug( string $slug ): self {
        $this->slug = $slug;
        return $this;
    }

    public function get_excerpt(): string {
        return $this->excerpt;
    }

    public function set_excerpt( string $excerpt ): self {
        $this->excerpt = $excerpt;
        return $this;
    }

    public function get_content(): string {
        return $this->content;
    }

    public function set_content( string $content ): self {
        $this->content = $content;
        return $this;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function set_status( string $status ): self {
        $this->status = $status;
        return $this;
    }

    public function get_url(): string {
        return $this->url;
    }

    public function set_url( string $url ): self {
        $this->url = $url;
        return $this;
    }

    public function get_thumbnail(): array {
        return $this->thumbnail;
    }

    public function set_thumbnail( array $thumbnail ): self {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    public function get_date(): string {
        return $this->date;
    }

    public function set_date( string $date ): self {
        $this->date = $date;
        return $this;
    }
}
