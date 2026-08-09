<?php

namespace Crafium\AppNatively\App\DTO\Blog;

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

    /**
     * @var array
     */
    private array $categories = [];

    private string $date;

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

    /**
     * @return array
     */
    public function get_categories(): array {
        return $this->categories;
    }

    /**
     * @param array $categories
     */
    public function set_categories( array $categories ): self {
        $this->categories = $categories;
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
