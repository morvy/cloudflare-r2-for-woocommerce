# Cloudflare R2 for WooCommerce

Simple integration to serve WooCommerce downloadable product files from Cloudflare R2 storage using shortcodes.

## What This Plugin Does

- Adds a simple popup to choose a file from R2 or upload a new file to R2
- Adds R2 files to WooCommerce products using shortcodes
- Generates secure pre-signed URLs with expiration
- Uses WooCommerce's native download permissions and tracking
- Caches R2 file listings for better performance

## What This Plugin Does NOT Do

- No file management (upload/delete/rename)
- No custom analytics or tracking
- No custom access control (uses WooCommerce's system)

## Requirements

- WordPress 6.4+
- WooCommerce 9.0+
- PHP 8.2+
- Cloudflare account with R2 enabled

## Installation

1. Download the latest release ZIP from [Releases](../../releases)
2. Go to **WordPress Admin** > **Plugins** > **Add New** > **Upload Plugin**
3. Upload and activate

## Configuration

### Get R2 Credentials

1. Log in to [Cloudflare dashboard](https://dash.cloudflare.com/)
2. Navigate to **R2** > **Manage R2 API Tokens**
3. Create API token and note:
   - **Endpoint**: `https://<account-id>.r2.cloudflarestorage.com`
   - **Access Key ID**
   - **Secret Access Key**
   - **Bucket Name**

### Configure Plugin

1. Go to **WooCommerce** > **Settings** > **Cloudflare R2**
2. Enter credentials
3. Test connection
4. Save

## Usage

### Adding Files to Products

1. Upload files to R2 bucket using Cloudflare dashboard or S3 tools or via popup
2. Edit WooCommerce product > Enable **Downloadable**
3. Add file using Choose/Upload buttons or directly with a shortcode:
   ```
   [cloudflare_r2 object="path/to/file.zip"]
   ```

### Shortcode Syntax

**Basic:**
```
[cloudflare_r2 object="path/to/file.zip"]
```

**Custom filename:**
```
[cloudflare_r2 object="path/to/file.zip" filename="Download.zip"]
```

**Custom expiration (seconds):**
```
[cloudflare_r2 object="path/to/file.zip" expires="7200"]
```

**Legacy syntax for various Amazon S3 plugins (backward compatibility):**
```
[amazon_s3 bucket="my-bucket" object="path/to/file.zip"]
```

### Parameters

- `object` - File path in bucket (required)
- `bucket` - Bucket name (optional, uses default)
- `filename` - Download filename (optional)
- `expires` - Link expiration in seconds (optional, default: 3600)

## How It Works

1. Customer purchases product with R2 file
2. WooCommerce verifies purchase permissions
3. Plugin generates pre-signed URL from R2
4. Customer downloads file securely
5. WooCommerce tracks download

## FAQ

**Q: How do I upload files?**
A: Use Cloudflare dashboard, AWS CLI, or any S3-compatible tool or via a popup interface in plugin.

**Q: How do I manage files?**
A: Use external tools. Plugin only uploads files and generates download links.

**Q: Is this secure?**
A: Yes. WooCommerce controls access. Only verified purchasers can download. URLs expire after set time.

**Q: Can I use with Subscriptions?**
A: Yes. WooCommerce Subscriptions access control works automatically.

**Q: Does it cache files?**
A: No. It caches file listings from R2 for performance.

## Support

- Create issue in this repository

## License

AGPL-3.0-or-later

---

For development documentation, see [CONTRIBUTING.md](CONTRIBUTING.md).
