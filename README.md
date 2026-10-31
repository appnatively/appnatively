## Installation

1. Clone Github Repository

   ```sh
   git clone https://github.com/appnatively/appnatively.git
   ```

2. Install composer dependencies
   ```sh
   composer setup
   ```

3. Install npm dependencies (Node v20.10.x)
   ```sh
   pnpm install
   ```

4. Compile Scripts
   ```sh
   pnpm start
   ```

4. Build plugin
   ```sh
   pnpm build
   ```


## Recently viewed products API

`GET /wp-json/craf_appna/v1/ecommerce/recently-viewed` resolves product IDs
saved on the device. It does not record visits or store customer history.
The API must be enabled; guest requests do not require a customer token.

Examples (send IDs in newest-first order):

```text
/wp-json/craf_appna/v1/ecommerce/recently-viewed?integration=woocommerce&ids[]=42&ids[]=18
/wp-json/craf_appna/v1/ecommerce/recently-viewed?integration=fluent-cart&ids[]=42&ids[]=18
/wp-json/craf_appna/v1/ecommerce/recently-viewed?integration=surecart&ids[]=42&ids[]=18
```

- `integration` is required and must name an available integration.
- `ids` is an optional array of at most 30 positive integers or integer strings.
  Use the `id` from AppNatively product responses. SureCart uses its mirrored
  WordPress post ID, not its remote product UUID. Missing IDs or an empty array
  return an empty list without querying products.
- Duplicate IDs are removed, retaining the first occurrence. Returned cards
  follow the supplied order. Missing, unpublished, password-protected, and
  otherwise unavailable products are omitted. Catalog-hidden and out-of-stock
  products remain eligible under the existing saved-product visibility rules.
- Responses use the standard product-list fields and `ProductPaginatorDTO`:

```json
{
  "data": {
    "current_page": 1,
    "per_page": 0,
    "total": 0,
    "last_page": 1,
    "items": []
  }
}
```

For a nonempty response, `items` contains the existing ProductDTO card objects,
including ratings where supported; `per_page` and `total` equal the number of
resolved products. All results are returned together. Sorting, pagination,
current-product exclusion, and history clearing remain in the app.

Malformed IDs, more than 30 submitted IDs (before deduplication), and invalid
integration values return the existing validation error envelope with HTTP 422.
An unavailable integration or disabled API returns HTTP 503. Lookup failures
propagate as errors, never as successful empty history. Responses use
`Cache-Control: private, no-store, max-age=0`.

Apps should skip requests for empty history and retain saved IDs after API
failures. Release this plugin support before enabling batch lookups in the app;
older plugin versions continue using individual product lookups. This plugin
change does not modify the mobile renderer, storage, or customer isolation.
