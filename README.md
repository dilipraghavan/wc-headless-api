# WooCommerce Headless REST API

A WordPress plugin to extend WooCommerce with a headless-ready REST API featuring JWT authentication. This project serves as a portfolio piece to demonstrate proficiency in WordPress plugin development, REST API design, JWT authentication, and headless e-commerce architecture.

## Features

- **JWT Authentication:** Secure token-based authentication with access and refresh tokens using the Firebase PHP-JWT library. Access tokens expire in 1 hour, refresh tokens in 7 days.

- **Product Endpoints:** Full product listing with pagination, filtering by category, price range, sale status, and featured status. Single product endpoint returns detailed data including variations for variable products.

- **Search Functionality:** Dedicated search endpoint for product discovery with pagination support.

- **Wishlist System:** User-specific wishlist stored in WordPress user meta. Supports add, remove, clear, and check operations with real-time updates.

- **CORS Handling:** Configured for cross-origin requests from frontend applications. Handles preflight OPTIONS requests properly for browser compatibility.

- **Extensible Architecture:** Clean class structure with hooks and filters for third-party customization. Follows WordPress coding standards and PSR-4 autoloading.

---

## Installation

There are two methods for installation depending on whether you are an end-user or a developer.

### For End-Users (Packaged Plugin)

To install a ready-to-use version of the plugin, download the latest release from the [Releases page](https://github.com/dilipraghavan/wc-headless-api/releases). This version is pre-packaged with all dependencies included.

1. Download the `.zip` file from the latest release.
2. In the WordPress dashboard, go to **Plugins > Add New**.
3. Click **Upload Plugin**, select the downloaded `.zip` file, and click **Install Now**.
4. After installation, click **Activate Plugin**.

### For Developers (with Composer)

This is the recommended method for developers who want to work with the source code or contribute to the plugin.

1. **Clone the Repository:** Clone the plugin from GitHub to your local machine using Git.

   ```bash
   git clone https://github.com/dilipraghavan/wc-headless-api.git
   ```

2. **Install Dependencies:** Navigate into the cloned folder from your command line and run Composer to install the required libraries.

   ```bash
   cd wc-headless-api
   composer install
   ```

3. **Upload to WordPress:** Copy the entire `wc-headless-api` folder to your WordPress installation's `wp-content/plugins/` directory.

4. **Activate Plugin:** In the WordPress dashboard, go to **Plugins**, find "WooCommerce Headless REST API", and click **Activate**.

---

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/wp-json/wc-headless/v1/auth/login` | Login with username/password, returns JWT tokens |
| POST | `/wp-json/wc-headless/v1/auth/refresh` | Refresh access token using refresh token |
| POST | `/wp-json/wc-headless/v1/auth/logout` | Invalidate current tokens |
| GET | `/wp-json/wc-headless/v1/auth/me` | Get current user info (requires auth) |

### Products

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/wc-headless/v1/products` | List products with filters and pagination |
| GET | `/wp-json/wc-headless/v1/products/{id}` | Get single product with full details |
| GET | `/wp-json/wc-headless/v1/products/slug/{slug}` | Get product by URL slug |
| GET | `/wp-json/wc-headless/v1/products/search?q={query}` | Search products |
| GET | `/wp-json/wc-headless/v1/products/{id}/related` | Get related products |

### Wishlist (requires authentication)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/wp-json/wc-headless/v1/wishlist` | Get user's wishlist with product details |
| GET | `/wp-json/wc-headless/v1/wishlist/ids` | Get wishlist product IDs only |
| POST | `/wp-json/wc-headless/v1/wishlist` | Add product to wishlist |
| DELETE | `/wp-json/wc-headless/v1/wishlist/{product_id}` | Remove product from wishlist |
| DELETE | `/wp-json/wc-headless/v1/wishlist/clear` | Clear entire wishlist |
| GET | `/wp-json/wc-headless/v1/wishlist/check/{product_id}` | Check if product is in wishlist |

---

## Usage

### Authentication Flow

1. **Login:** Send POST request to `/auth/login` with `username` and `password` in JSON body.
2. **Store Tokens:** Save the returned `access_token` and `refresh_token` on the client.
3. **Authenticated Requests:** Include the access token in the Authorization header: `Authorization: Bearer {access_token}`
4. **Token Refresh:** When access token expires, use `/auth/refresh` with the refresh token to get new tokens.

### Product Filters

The products endpoint supports these query parameters:

- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 12)
- `category` - Filter by category slug
- `orderby` - Sort by: date, price, popularity, rating, title
- `order` - Sort order: ASC or DESC
- `min_price` - Minimum price filter
- `max_price` - Maximum price filter
- `featured` - Filter featured products (true/false)
- `on_sale` - Filter sale products (true/false)

Example: `/wp-json/wc-headless/v1/products?category=hoodies&on_sale=true&orderby=price&order=ASC`

---

## Live Demo

- **Backend API:** [techvault.wpshiftstudio.com](https://techvault.wpshiftstudio.com)
- **Frontend App:** [wc-product-browser.vercel.app](https://wc-product-browser.vercel.app)

---

## Contributing

We welcome contributions! If you have a bug fix or a new feature, please follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or bug fix.
3. Commit your changes following a clear and concise commit message format.
4. Push your branch to your forked repository.
5. Submit a pull request.

---

## License

This project is licensed under the MIT License.
