<?php

namespace Crafium\AppNatively\App\DTO\Directory;

defined( "ABSPATH" ) || exit;

use Crafium\AppNatively\App\DTO\DTO;

class ListingDTO extends DTO {
    private int $id;

    private string $title;

    private string $url;

    private string $slug;

    private string $description;

    private string $excerpt;

    private string $status;

    private array $image = [];

    private int $views_count = 0;

    private string $address;

    private ?float $latitude = null;

    private ?float $longitude = null;

    private string $phone;

    private string $email;

    private string $website;

    private bool $favorite = false;

    private bool $featured = false;

    private bool $new = false;

    private bool $popular = false;

    private array $pricing = [];

    private array $categories = [];

    private array $locations = [];

    private array $tags = [];

    private float $rating;

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

    public function get_description(): string {
        return $this->description;
    }

    public function set_description( string $description ): self {
        $this->description = $description;
        return $this;
    }

    public function get_excerpt(): string {
        return $this->excerpt;
    }

    public function set_excerpt( string $excerpt ): self {
        $this->excerpt = $excerpt;
        return $this;
    }

    public function get_status(): string {
        return $this->status;
    }

    public function set_status( string $status ): self {
        $this->status = $status;
        return $this;
    }

    public function get_image(): array {
        return $this->image;
    }

    public function set_image( array $image ): self {
        $this->image = $image;
        return $this;
    }

    public function get_views_count(): int {
        return $this->views_count;
    }

    public function set_views_count( int $views_count ): self {
        $this->views_count = $views_count;
        return $this;
    }

    public function get_address(): string {
        return $this->address;
    }

    public function set_address( string $address ): self {
        $this->address = $address;
        return $this;
    }

    public function get_latitude(): ?float {
        return $this->latitude;
    }

    public function set_latitude( ?float $latitude ): self {
        $this->latitude = $latitude;
        return $this;
    }

    public function get_longitude(): ?float {
        return $this->longitude;
    }

    public function set_longitude( ?float $longitude ): self {
        $this->longitude = $longitude;
        return $this;
    }

    public function get_phone(): string {
        return $this->phone;
    }

    public function set_phone( string $phone ): self {
        $this->phone = $phone;
        return $this;
    }

    public function get_email(): string {
        return $this->email;
    }

    public function set_email( string $email ): self {
        $this->email = $email;
        return $this;
    }

    public function get_website(): string {
        return $this->website;
    }

    public function set_website( string $website ): self {
        $this->website = $website;
        return $this;
    }

    public function is_favorite(): bool {
        return $this->favorite;
    }

    public function set_favorite( bool $favorite ): self {
        $this->favorite = $favorite;
        return $this;
    }

    public function get_featured(): bool {
        return $this->featured;
    }

    public function is_featured(): bool {
        return $this->featured;
    }

    public function set_featured( bool $featured ): self {
        $this->featured = $featured;
        return $this;
    }

    public function is_new(): bool {
        return $this->new;
    }

    public function set_new( bool $new ): self {
        $this->new = $new;
        return $this;
    }

    public function is_popular(): bool {
        return $this->popular;
    }

    public function set_popular( bool $popular ): self {
        $this->popular = $popular;
        return $this;
    }

    public function get_pricing(): array {
        return $this->pricing;
    }

    public function set_pricing( array $pricing ): self {
        $this->pricing = $pricing;
        return $this;
    }

    public function get_categories(): array {
        return $this->categories;
    }

    public function set_categories( array $categories ): self {
        $this->categories = $categories;
        return $this;
    }

    public function get_locations(): array {
        return $this->locations;
    }

    public function set_locations( array $locations ): self {
        $this->locations = $locations;
        return $this;
    }

    public function get_tags(): array {
        return $this->tags;
    }

    public function set_tags( array $tags ): self {
        $this->tags = $tags;
        return $this;
    }

    public function get_rating(): float {
        return $this->rating;
    }

    public function set_rating( float $rating ): self {
        $this->rating = $rating;
        return $this;
    }

    public function get_url(): string {
        return $this->url;
    }

    public function set_url( string $url ): self {
        $this->url = $url;
        return $this;
    }
}
