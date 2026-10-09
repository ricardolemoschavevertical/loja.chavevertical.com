# CV R2 Media Bridge (experimental)

A small, optional WordPress plugin for the CV Product Transfer Windows client. It registers an existing **publicly accessible R2 image** as a WordPress attachment without copying its bytes. It does **not** create product categories, change product slugs, or import products. Product imports remain with the WooCommerce REST API.

## Configuration

1. Deploy and activate this plugin on a **staging** WordPress installation.
2. Add the administrator-controlled public media origin in `wp-config.php`:

```php
define( 'CV_TRANSFER_R2_PUBLIC_BASE', 'https://your-public-r2-image-domain.example' );
```

3. Use an HTTPS WordPress **Application Password** for a user with `upload_files` and `edit_products` permissions (not a server password).
4. Call `POST /wp-json/cv-transfer/v1/media/resolve` with JSON `{"key":"path/to/image.webp"}`. Returns `id`, `created`, and `url` after an HTTPS HEAD check. Use returned `id` in WooCommerce `images` REST payloads.

## Important limitations / precautions

- **Do not activate in production without checking compatibility** with the existing `cv-r2-media-linker`, its R2 URL rewriting, and media offload implementation.
- This initial bridge does not discover or deduplicate attachments created by other plugins using different meta fields. Existing media must be reconciled before bulk operation.
- The public R2 URL must respond to HEAD with HTTP 200. Private bucket URLs, redirects and expiring signed URLs are not supported.
- No image bytes are transferred, and no image dimensions, thumbnails or `_wp_attachment_metadata` are generated. Some themes, image editors, offload plugins or srcset handling may require those fields.
- For safety the endpoint does not accept arbitrary URLs, only object keys under a fixed, administrator-controlled HTTPS origin. The user must not configure the origin to an internal service.
- There is no dry-run, queue, inventory reconciliation or concurrency lock; run a few controlled tests first.
- Repeated concurrent requests could create duplicates without an external lock. Never run this endpoint concurrently for the same key in this version.
- If the image is not public, the key is ambiguous, or validation fails, correct the issue rather than creating a blind attachment.
