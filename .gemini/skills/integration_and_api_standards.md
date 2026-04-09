# Skill: AppNatively Integration & API Development

This skill provides instructions for developing and extending the AppNatively plugin. When performing tasks related to this plugin, adhere to the following architectural standards and implementation patterns.

## 1. Inheritance Standards

Every component MUST extend the appropriate base class to ensure compatibility with the WpMVC framework and the AppNatively core:

*   **Integrations (Services)**: `AppNatively\WpMVC\Contracts\Provider`
*   **Controllers**: `AppNatively\App\Http\Controllers\Controller`
*   **Data Transfer Objects (DTOs)**: `AppNatively\App\DTO\DTO`
*   **Database Models**: `AppNatively\WpMVC\Database\Eloquent\Model`

## 2. Integration Pattern (Provider/Filter)

Integrations must be decoupled from the API routes using WordPress filters.

1.  **Register the Provider**: In `config/app.php`, add your class to the `providers` array.
2.  **Hook Implementation**: Use the `boot()` method of the Provider to attach to application-specific filters.
    *   *Naming Pattern*: `appnatively_{module}_{integration}_{resource}` (e.g., `appnatively_ecommerce_woocommerce_products`). The integration value will be dynamically derived (e.g., `woocommerce`, `edd`, `fluentcart`).
3.  **Data Flow**: Controllers trigger these filters using `apply_filters()`. Providers return fully populated DTOs.

## 3. Communication Standards

*   **Strict Typing**: Always use parameter type hints and return types for all methods.
*   **Data Objects**: Never return raw arrays from integrations. Always map data to the relevant DTO.
*   **Consistent Responses**: Use `AppNatively\WpMVC\Routing\Response::send(["data" => $data])` to return JSON responses.

## 4. REST API Registration

Routes are defined in `routes/rest/v1/`.
*   Use the fluent `Route` utility class.
*   Group related routes with `Route::group()`.
*   Define parameters using curly braces (e.g., `/{id}`).

## 5. Security & Performance

*   **Field Selection**: Always support a `fields` parameter. Verify it using `appnatively_get_verified_fields( $request->get_param( "fields" ), $this->allowed_fields )`.
*   **Database Efficiency**: Use `select()` in the Query Builder based on requested fields.
*   **Security**: Always escape LIKE search terms with `$wpdb->esc_like()` and verify `post_status` visibility for sensitive entities.

## 6. Naming Conventions

*   **Classes/Files**: `PascalCase`.
*   **JSON Keys/Parameters/Filters**: `snake_case`.
*   **Unified API Sorting Standard**:
    *   Parameter Name: `sort`.
    *   Ascending: `sort=field_name` (e.g., `sort=price`).
    *   Descending: `sort=-field_name` (e.g., `sort=-price`).
    *   Multiple Fields: Comma-separated (e.g., `sort=-date,name`).
*   **Unified Field Naming Map**:
    All ecommerce adapters MUST map platform-specific fields to the following Universal Keys. Avoid using platform-specific terminology (e.g., do not use "regular_price" or "stock_status").

| Universal Key | WooCommerce | Shopify | FluentCart | Wix | Description |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `compare_at_price` | `_regular_price` | `compare_at_price` | `compare_price` | `priceData.listPrice` | The original, non-discounted price. |
| `price` | `_price` | `price` | `item_price` | `priceData.price` | The actual price the customer pays. |
| `inventory_status` | `_stock_status` | `inventory_policy` | `stock_status` | `stock.status` | One of: `in_stock`, `out_of_stock`, `on_backorder`. |
| `variants` | `variations` | `variants` | `variations` | `variants` | Array of child SKU objects. |
| `options` | `attributes` | `options` | `attributes` | `productOptions` | Customer-selectable configurations (Color, Size). |
| `permalink` | `get_permalink()` | `handle/url` | `view_url` | `productPageUrl` | The canonical public web URL. |
| `images` | `get_gallery_ids()`| `images` | `images()` | `media.items` | Array of ProductImageDTOs. |
| `brand` | `_brand` | `vendor` | `other_info.brand`| `brand` | The manufacturer or label name. |

---

### 7. Implementation Checklist for New Platforms
When adding a new integration:
- [ ] Implement `Provider` contract.
- [ ] Create an Adapter that maps the **Unified Field Map** above.
- [ ] Throw `AppNatively\WpMVC\Exceptions\Exception` (404) for missing/invalid items.
- [ ] Ensure all timestamps are **ISO-8601** (`format('c')`).
- [ ] Selective Initialization: Avoid setting default values in DTO property declarations. Allow properties to remain `uninitialized` so the core DTO logic can skip them in the JSON response if they weren't explicitly requested.
