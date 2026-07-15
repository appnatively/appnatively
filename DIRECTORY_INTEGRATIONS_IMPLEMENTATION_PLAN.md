# Directory Integrations Implementation Plan

## 1. Objective

Integrate GeoDirectory, HivePress, Business Directory Plugin, and Classified Listing into AppNatively's existing directory REST API by following the established Directorist provider pattern. Preserve the current route names, request parameters, DTOs, pagination shape, and field-projection behavior so the mobile SDK does not need provider-specific handling.

This plan also brings Directorist under the same automated integration and REST-contract coverage as the four new providers.

## 2. Inspected Baseline

The plan is based on the following plugin versions currently present in the local WordPress checkout:

| Provider | Local version | AppNatively integration key |
| --- | ---: | --- |
| Directorist | 8.8.3 | `directorist` |
| GeoDirectory | 2.8.166 | `geodirectory` |
| HivePress | 1.7.26 | `hivepress` |
| Business Directory Plugin | 6.4.25 | `business-directory-plugin` |
| Classified Listing | 5.5.0 | `classified-listing` |

Current AppNatively state:

- `routes/rest/v1/directory.php` already exposes listing, related-listing, review, category, tag, and location routes.
- The directory controllers dispatch most requests through provider-specific WordPress filters.
- `ListingController::reviews()` is the exception: it accepts `integration` but contains Directorist-only post type, comment, and rating logic.
- `Directorist.php` implements list, detail, related, category, tag, and location filters, but has no provider-level review filter.
- The other four integration files exist but are empty.
- `config/app.php` registers only the Directorist directory provider.
- `PluginsController` reports only Directorist as an available directory integration.
- The WordPress test installer and bootstrap do not install or load any directory plugins.
- There are no directory integration tests.

## 3. Public Contract to Preserve

### Routes

Do not add provider-specific REST routes. All providers must use the existing endpoints:

| Method | Route | Provider operation |
| --- | --- | --- |
| `GET` | `/directory/listings` | `listings` |
| `GET` | `/directory/listings/{id}` | `listing` |
| `GET` | `/directory/listings/{id}/related` | `related_listings` |
| `GET` | `/directory/listings/{id}/reviews` | `reviews` |
| `GET` | `/directory/categories` | `categories` |
| `GET` | `/directory/categories/{id}` | `category` |
| `GET` | `/directory/tags` | `tags` |
| `GET` | `/directory/locations` | `locations` |
| `GET` | `/directory/locations/{id}` | `location` |

Keep the route declaration order that places `/related` and `/reviews` before `/listings/{id}`.

### Request contract

- Use the exact integration keys listed in Section 2.
- Preserve `page`, `per_page`, `search`, `sort`, `fields`, `categories`, `tags`, `locations`, and `isFeatured` as currently validated by the controllers.
- Do not add provider-specific request properties in v1.
- For GeoDirectory, use `geodir_get_default_posttype()` and fall back to `gd_place`; do not expose a new CPT selector until the shared API has an explicit multi-CPT design.
- Continue accepting only verified DTO fields. Every mapper must leave unrequested fields unset rather than loading and returning the complete provider record.

### Response contract

- Listings return `ListingPaginatorDTO` or `ListingDTO`.
- Categories return `CategoryPaginatorDTO` or `CategoryDTO`.
- Tags and locations return `TermPaginatorDTO` or `TermDTO`.
- Reviews retain the existing array shape: `current_page`, `per_page`, `total`, `last_page`, `average_rating`, `review_count`, `rating_counts`, and `items`.
- Empty collection results return a valid paginator with an empty `items` array and `last_page >= 1`.
- A missing or unpublished single resource returns `null` from the adapter so the controller produces its existing not-found response.
- Provider-specific values must be normalized to the existing DTO types; no raw provider model objects may escape into REST responses.

## 4. Capability Policy

Not every free/core directory plugin implements every Directorist concept. Register every shared filter so a recognized integration never fails merely because an optional capability is absent, but do not fabricate data.

| Capability | Supported behavior | Unsupported/extension-only behavior |
| --- | --- | --- |
| Categories | Use the provider's primary listing category taxonomy/model. | Return an empty category paginator only if the provider genuinely has no category source. |
| Tags | Use the provider's native listing tag taxonomy. | Return an empty term paginator when the installed edition has no tag system. |
| Locations | Use a native taxonomy or stable provider location API. | Return an empty paginator; single-location lookup returns `null`. Do not generate pseudo-terms from address strings. |
| Reviews/ratings | Use the provider's native approved-review records and aggregate APIs/meta. | Return the standard empty review payload and `rating = 0.0`. |
| Favorites | Use a stable provider API when its favorites/bookmarks feature is loaded. | Return `false`; do not create AppNatively-owned favorite storage in this project. |
| Featured/new/popular | Prefer provider APIs and configured provider semantics. | Return `false` when the provider has no equivalent. Do not apply a cross-provider date/view heuristic without a contract change. |
| Pricing | Normalize a provider's native pricing values into `price`, `price_type`, and `price_range`. | Return the same keys with empty string values. |

Optional add-ons may enhance an adapter only when detected through a stable public class, function, taxonomy, or model. The base plugin path must remain fatal-error-free without those add-ons.

## 5. Provider Filter Surface

Each provider class must extend `Provider`, keep `register()` empty unless a real service binding is needed, and register these filters from `boot()` only when its upstream plugin is loaded:

```text
craf_appna_directory_{integration}_listings
craf_appna_directory_{integration}_listing
craf_appna_directory_{integration}_related_listings
craf_appna_directory_{integration}_reviews
craf_appna_directory_{integration}_categories
craf_appna_directory_{integration}_category
craf_appna_directory_{integration}_tags
craf_appna_directory_{integration}_locations
craf_appna_directory_{integration}_location
```

Use priority `10`. List/detail/related/category/tag/location filters receive the current accumulator, `Request`, and verified fields. The review filter receives the current accumulator and `Request`, plus any additional argument only if the controller and all five adapters adopt it together.

Do not introduce a new public base adapter in the first implementation. Keep each provider's query and mapping logic in its provider class, matching `Directorist.php`; extract only small private helpers when duplication exists inside that class.

## 6. Common Adapter Rules

Implement the following behavior consistently in all five providers:

1. Normalize `page` and `per_page` to the controller limits and use them in the returned paginator.
2. Sanitize `search` and translate only the supported `sort` values (`date`, `title`/`name`, and `id`) through a whitelist. Never pass an arbitrary `orderby` value to WordPress or an upstream query builder.
3. Convert filter arrays to positive integer IDs before building taxonomy/provider queries.
4. Query published listings only.
5. Verify the provider post type/model on single-listing calls.
6. Exclude the source listing from related results.
7. Build related results from shared native categories and tags, using `OR` between available taxonomies. Return an empty paginator when the source is invalid or has no related terms.
8. Map media to `{ id, src, alt, title }` where available. Always validate attachment IDs and URLs.
9. Normalize coordinates to floats only when numeric and within latitude/longitude bounds; otherwise return `null`.
10. Normalize terms to `{ id, name, slug }`; populate count/image only on DTOs that support them and only when requested.
11. Use the provider's own APIs/models for special values before reading storage directly. Storage/meta fallbacks must be documented beside the mapper because these are the most version-sensitive mappings.
12. Return stable defaults for unsupported optional fields and guard every optional upstream function/class call.

## 7. Provider Implementation Details

### 7.1 Directorist

Use the current implementation as the behavioral reference and make only the changes required for a complete shared contract:

- Register `craf_appna_directory_directorist_reviews`.
- Move the Directorist-specific review query, comment mapping, rating distribution, average, and review count out of `ListingController` into `Directorist::reviews()` without changing the response shape.
- Retain `ATBDP_POST_TYPE`, `ATBDP_CATEGORY`, `ATBDP_TAGS`, and `ATBDP_LOCATION` with their existing fallback names.
- Preserve the existing field-aware listing and term mapping, coordinate validation, image normalization, pricing, favorite, new, popular, and rating behavior.
- Audit pagination and invalid-resource paths against the common rules before using Directorist results as parity fixtures.

### 7.2 GeoDirectory

- Dependency guard: require `function_exists( 'geodir_get_post_info' )` and a registered GeoDirectory post type.
- Resolve the v1 post type through `geodir_get_default_posttype()` with `gd_place` as the fallback.
- Use the resolved post type for listing queries and validation.
- Use `${post_type}category` and `${post_type}_tags` for category/tag filters and related-listing matching.
- Hydrate a listing through `geodir_get_post_info()` so custom-table fields such as address, street, latitude, longitude, phone, email, website, featured state, overall rating, and rating count are available.
- Use GeoDirectory media helpers for the primary listing image, with the WordPress featured image as a guarded fallback.
- Use `geodir_get_post_rating()` and GeoDirectory review-count/rating-count APIs for rating data.
- Use GeoDirectory's location APIs only when location management is active. Core address/city strings must not be exposed as fake `TermDTO` resources.
- Map category images through GeoDirectory category metadata/media helpers rather than assuming Directorist's `category_img` key.
- Query approved GeoDirectory reviews using its native comment rules and rating meta; normalize the result to the shared review payload.
- Treat GeoDirectory multi-CPT aggregation as out of scope for this v1 adapter. Add tests that confirm the configured default CPT is the only one queried.

### 7.3 HivePress

- Dependency guard: require the HivePress core/model layer and the `hp_listing` post type.
- Use `HivePress\Models\Listing` where it provides stable getters; use WordPress post/taxonomy APIs for query pagination and projection when that is simpler and more stable.
- Use `hp_listing` and `hp_listing_category` as the core post type and category taxonomy.
- Use HivePress listing/category image model getters or validated WordPress attachment fallbacks.
- Map `featured` from the HivePress listing model and map configured attributes only when their canonical field names match AppNatively fields (`address`, `latitude`, `longitude`, `phone`, `email`, `website`, or price fields).
- Do not infer an email from the listing author's account when the listing itself has no public email field.
- The inspected core plugin has no native listing tag or location taxonomy. Return valid empty tag/location results unless a loaded extension registers a stable HivePress model/taxonomy for them.
- Detect HivePress reviews through the `hp_review` model/comment type when the reviews feature is loaded. Without it, return the standard empty review payload and rating defaults.
- Build related listings from `hp_listing_category`; include extension tags only when the detected extension supplies an established taxonomy.

### 7.4 Business Directory Plugin

- Dependency guard: require `WPBDP_POST_TYPE` and `wpbdp_get_listing()`.
- Use `WPBDP_POST_TYPE` (`wpbdp_listing`), `WPBDP_CATEGORY_TAX` (`wpbdp_category`), and `WPBDP_TAGS_TAX` (`wpbdp_tag`).
- Use `WPBDP_Listing` for title, images, categories, and sticky/featured state.
- Resolve contact/address data through Business Directory form-field associations and public form-field APIs instead of hard-coding database field IDs. Document the association-to-DTO mapping in the adapter.
- Use the saved thumbnail first, then the first valid Business Directory listing image.
- Map sticky listings to `featured`; leave popular/new/favorite false unless a stable provider feature supplies those values.
- Use category and tag taxonomies for list filters and related queries.
- The inspected free/core plugin has no native location taxonomy. If the Regions module exposes a registered region taxonomy through a stable public helper, adapt it; otherwise return empty location results.
- The inspected free/core plugin has no listing-review/rating contract. Return the standard empty review payload unless a supported ratings module is detected.
- Return empty pricing values unless the listing has a public/native price field association that can be identified without depending on a site-specific field ID.

### 7.5 Classified Listing

- Dependency guard: require `rtcl()`, `Rtcl\Models\Listing`, and the registered `rtcl_listing` post type.
- Use `rtcl()->post_type`, `rtcl()->category`, `rtcl()->tag`, and `rtcl()->location`, whose inspected defaults are `rtcl_listing`, `rtcl_category`, `rtcl_tag`, and `rtcl_location`.
- Hydrate single records with `Rtcl\Models\Listing` and prefer its getters for title, status, featured/new state, view count, email, categories, locations, tags, price, pricing type, images, rating counts, average rating, and review count.
- Map address, phone, website, and coordinates from the provider's public model/helper where available, then from the documented Classified Listing meta keys as guarded fallbacks.
- Normalize `price`, `get_pricing_type()`, and range values into the shared pricing object.
- Use the three native taxonomies for filters, term endpoints, and related listings.
- Use Classified Listing's category/location term image helpers or documented term metadata for media fields.
- Query approved native listing review comments, read their `rating` meta, and use the listing model's aggregate rating getters for the shared review payload.
- Use a stable Classified Listing favorites API when loaded; otherwise return `false`.

## 8. Controller and Route Work

### `routes/rest/v1/directory.php`

- Keep the existing route set and ordering.
- Add no provider names to paths.
- Verify through route tests that `/related` and `/reviews` still resolve to their dedicated controller methods rather than `{id}`.

### `ListingController.php`

- Replace the Directorist-only body of `reviews()` with `apply_filters( "craf_appna_directory_{$integration}_reviews", null, $request )`.
- Validate that the adapter returns the agreed review array before sending it; use the existing integration-not-found exception pattern for an unregistered provider.
- Remove Directorist-specific private review helpers from the controller after they move to `Directorist.php`.
- Leave listing DTO allowed fields and the other public methods unchanged unless a failing contract test demonstrates a shared bug.

### Other directory controllers

- Keep the existing dynamic provider filter dispatch.
- Add tests for their default-field and missing-integration behavior rather than duplicating provider logic in controllers.

## 9. Provider Registration and Discovery

### `config/app.php`

- Import `GeoDirectory`, `HivePress`, `BusinessDirectoryPlugin`, and `ClassifiedListing`.
- Register all four after `Directorist` in the Directory Integrations block.
- Each provider must safely no-op when its upstream plugin is inactive, because AppNatively service providers are loaded independently of WordPress plugin activation state.

### `PluginsController.php`

Add the four providers to `craf_appna_integrated_plugins` with exact plugin slugs and main files:

| Key | Label | Main plugin file |
| --- | --- | --- |
| `geodirectory` | GeoDirectory | `geodirectory/geodirectory.php` |
| `hivepress` | HivePress | `hivepress/hivepress.php` |
| `business-directory-plugin` | Business Directory Plugin | `business-directory-plugin/business-directory-plugin.php` |
| `classified-listing` | Classified Listing | `classified-listing/classified-listing.php` |

Keep these discovery keys identical to the `integration` query values and filter-name segments. Do not create underscore aliases.

## 10. File Change Map

| File | Planned change |
| --- | --- |
| `app/Integrations/Directorist.php` | Add provider-level reviews and parity fixes found by tests. |
| `app/Integrations/GeoDirectory.php` | Implement all shared filters and GeoDirectory mapping. |
| `app/Integrations/HivePress.php` | Implement all shared filters and HivePress capability fallbacks. |
| `app/Integrations/BusinessDirectoryPlugin.php` | Implement all shared filters and form-field-aware mapping. |
| `app/Integrations/ClassifiedListing.php` | Implement all shared filters using the Classified Listing model/API. |
| `app/Http/Controllers/Directory/ListingController.php` | Make reviews provider-dispatched. |
| `app/Http/Controllers/PluginsController.php` | Advertise active directory integrations. |
| `config/app.php` | Register the four new providers. |
| `routes/rest/v1/directory.php` | No functional expansion; verify route order/coverage and change only if route tests expose ambiguity. |
| `bin/install-wp-tests.sh` | Install the five directory plugins for the WordPress test environment. |
| `tests/bootstrap.php` | Load and initialize the five directory plugins before AppNatively. |
| `tests/Integrations/DirectoristTest.php` | Add Directorist adapter coverage. |
| `tests/Integrations/GeoDirectoryTest.php` | Add GeoDirectory adapter coverage. |
| `tests/Integrations/HivePressTest.php` | Add HivePress adapter coverage. |
| `tests/Integrations/BusinessDirectoryPluginTest.php` | Add Business Directory adapter coverage. |
| `tests/Integrations/ClassifiedListingTest.php` | Add Classified Listing adapter coverage. |
| `tests/Integrations/DirectoryRestApiTest.php` | Add shared route/controller response-contract coverage. |

## 11. Implementation Sequence

1. Freeze the shared field, request, paginator, review, and error contracts in tests before changing adapters.
2. Move Directorist review logic behind a Directorist filter and prove that existing Directorist REST output is unchanged.
3. Add provider discovery and safe provider registration.
4. Implement GeoDirectory and Classified Listing first because their core plugins expose the broadest native listing/location/rating APIs.
5. Implement HivePress and Business Directory Plugin with explicit capability fallbacks for extension-only features.
6. Run every provider test independently to prevent one plugin's globals, hooks, or custom tables from masking another provider failure.
7. Run the combined suite with all five plugins loaded to detect hook collisions and cross-provider post leakage.
8. Run REST contract tests for every integration key and route.
9. Run PHP syntax, coding-standard, and full integration checks.
10. Perform a manual smoke pass against seeded listings in the local site before release.

## 12. Completion Criteria

- All five integration keys are returned by `/plugins` only when their real upstream plugins are active.
- All five providers register the complete shared filter surface without fatal errors.
- Every existing directory route accepts each recognized integration key and returns the existing DTO/review shape.
- Search, sort, pagination, field selection, category/tag/location filters, and featured filtering behave consistently where the provider supports them.
- Unsupported optional capabilities return documented empty/default values, not integration-not-found errors or fabricated data.
- Single and related endpoints never return records from another provider's post type.
- Review routing contains no Directorist-specific code in `ListingController`.
- The test environment can install, load, initialize, seed, and clean up all five upstream plugins reproducibly.
- Targeted and combined directory test suites pass on the supported PHP/WordPress matrix.

## 13. Test Plan

### 13.1 Test infrastructure

1. Extend `bin/install-wp-tests.sh` with the WordPress.org packages `directorist`, `geodirectory`, `hivepress`, `business-directory-plugin`, and `classified-listing`.
2. Introduce a small installer helper in the script so download, destination, and main-file checks are consistent, while preserving the current local cache behavior.
3. Support pinned plugin versions for the primary CI job through environment variables or a version manifest. Use the versions in Section 2 as the initial verified pins; run a separate allowed-to-fail/latest job to discover upstream breakage.
4. Update `tests/bootstrap.php` to locate each plugin in the temporary WordPress installation first and the checkout sibling directory second, matching the existing test bootstrap convention.
5. Load upstream plugins before AppNatively and call their supported installation/setup routines when they require custom tables or default taxonomies. Do not reproduce upstream table schemas in AppNatively tests.
6. Trigger the normal WordPress lifecycle needed to register post types and taxonomies before fixtures are created.
7. Add a `directory` PHPUnit group. A dedicated directory CI job must fail if any required upstream plugin is missing; ordinary local runs may skip a provider test with an explicit reason.
8. Reset current user, comments, attachments, terms, provider custom rows, caches, and provider options in `tearDown()` so test order cannot affect results.

### 13.2 Shared fixture contract

Create equivalent fixtures for each provider:

- Three published listings and one draft/unpublished listing.
- Distinct titles and dates for search/sort assertions.
- Two categories, two tags where supported, and two locations where supported.
- One featured and one non-featured listing.
- One listing with complete projected fields: content, excerpt, image, views, address, valid coordinates, phone, email, website, pricing, terms, and rating where supported.
- One listing with invalid/missing optional data to verify stable defaults and coordinate validation.
- Related fixtures sharing a category, a tag, both, or neither; the source listing must never appear in results.
- At least three approved reviews with different ratings, plus one pending/spam record that must be excluded, where reviews are supported.

Create fixtures through each provider's public model/factory APIs whenever practical. Use direct WordPress post/term creation only for data that the provider stores natively as ordinary WordPress objects, and use documented provider storage helpers for custom tables/meta.

### 13.3 Provider boot tests

Run these assertions for Directorist, GeoDirectory, HivePress, Business Directory Plugin, and Classified Listing:

- `boot()` registers all nine expected provider filters at priority `10` when the upstream plugin is loaded.
- `boot()` does not emit warnings or register unusable callbacks when the upstream plugin is absent.
- Applying each registered filter returns the expected DTO/payload type.
- Activating all five providers together does not cause one provider's callback to run for another integration key.

### 13.4 Listing collection tests

For each provider, test:

- Default page/per-page behavior and paginator metadata.
- Explicit pagination on page 1 and page 2.
- Published-only isolation.
- Search by title.
- Ascending and descending supported sorts.
- Rejection/fallback of an unsupported sort key.
- Category filtering.
- Tag filtering, or a valid empty result for unsupported tags.
- Location filtering, or a valid empty result for unsupported locations.
- Featured filtering, including the provider's native featured representation.
- Combined filters.
- Field projection: requested fields are populated and expensive unrequested fields are not mapped.
- No records from another directory plugin's post type appear when all providers are active.

### 13.5 Single-listing tests

For each provider, test:

- A published provider listing maps to `ListingDTO`.
- Every supported allowed field has the correct normalized PHP type and value.
- Image output contains a usable `src` and normalized attachment metadata.
- Invalid, missing, draft, and wrong-provider IDs return `null` from the adapter.
- Invalid/out-of-range coordinates become `null`.
- Missing optional fields use stable defaults.
- Requested-field projection works independently from the collection mapper.

### 13.6 Related-listing tests

For each provider, test:

- Category overlap returns the expected listing.
- Tag overlap returns the expected listing where tags exist.
- Category-or-tag semantics work when both are available.
- The source listing is excluded.
- Unrelated and unpublished listings are excluded.
- Pagination metadata is correct.
- Invalid source IDs and sources with no usable terms return an empty paginator.

### 13.7 Category, tag, and location tests

For each supported taxonomy/model, test:

- Pagination, search, and allowed sorting.
- Count, parent, description, and image projection where the DTO supports them.
- Single category/location lookup.
- Invalid IDs return `null` from the adapter.
- Taxonomy isolation prevents another provider's terms from appearing.
- Provider-specific term media is normalized to `{ id, src }`.

For unsupported capabilities, assert a valid empty paginator and a `null` single resource rather than an integration-not-found failure.

### 13.8 Review tests

For each provider, test:

- Only reviews attached to a valid published provider listing are returned.
- Only approved reviews are included.
- Pagination and newest-first ordering.
- Review item mapping: `id`, `reviewer`, `review`, `rating`, `date_created`, and `avatar_url`.
- Rating values are clamped/normalized to the shared 1-5 distribution keys.
- `average_rating`, `review_count`, `rating_counts`, and `total` agree with the provider's native aggregates.
- Invalid or wrong-provider listing IDs produce the existing not-found behavior.
- Providers without a loaded reviews capability return the standard empty payload.
- The Directorist response before and after controller extraction is identical for the same fixtures.

### 13.9 REST controller and route tests

Issue `WP_REST_Request` calls through the registered AppNatively routes for every integration key and assert:

- Each route resolves to the intended controller method.
- Required `integration` and `id` validation remains active.
- Invalid pagination, field, and filter values are rejected by existing validation.
- Unknown/inactive integration keys return the existing integration-not-found error.
- List endpoints wrap paginator data under `data` exactly once.
- Single endpoints wrap one DTO under `data` exactly once.
- Review payloads retain their exact existing key names and nesting.
- Field queries cannot expose unapproved fields.
- Hyphenated keys `business-directory-plugin` and `classified-listing` dispatch to their exact filters.
- `/listings/{id}/related` and `/listings/{id}/reviews` are not swallowed by `/listings/{id}`.

### 13.10 Plugin discovery tests

- With each plugin active, `/plugins` returns its exact integration key and label under `directory`.
- With each plugin inactive, it is omitted.
- Custom main-file paths are resolved correctly.
- No underscore or alternate aliases are exposed.

### 13.11 Compatibility and quality gates

Run the primary matrix against the pinned provider versions and:

- The plugin's minimum supported PHP/WordPress combination that is also accepted by the five upstream plugin versions.
- The project-preferred PHP version with the latest supported WordPress release.
- A current high PHP version as a compatibility job.

Run a scheduled latest-upstream matrix separately so upstream changes are visible without making routine builds nondeterministic.

Required verification commands from the AppNatively plugin directory:

```bash
composer test:setup
vendor/vendor-src/bin/phpunit --group directory
vendor/vendor-src/bin/phpunit --testsuite Integrations
composer phpcs
php -l app/Integrations/Directorist.php
php -l app/Integrations/GeoDirectory.php
php -l app/Integrations/HivePress.php
php -l app/Integrations/BusinessDirectoryPlugin.php
php -l app/Integrations/ClassifiedListing.php
php -l app/Http/Controllers/Directory/ListingController.php
php -l app/Http/Controllers/PluginsController.php
php -l config/app.php
php -l routes/rest/v1/directory.php
```

Final manual smoke checks:

1. Activate one directory plugin at a time and confirm plugin discovery plus every supported directory screen/API flow.
2. Activate all five together and confirm integration selection isolates data correctly.
3. Verify listing cards, listing details, maps, category/location pages, related listings, and reviews against seeded data.
4. Deactivate optional provider add-ons and confirm extension-only fields degrade to the documented empty/default behavior without warnings or fatal errors.
