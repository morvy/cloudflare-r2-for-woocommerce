# Contributing Guide

Developer documentation for Cloudflare R2 for WooCommerce plugin.

## Development Setup

### Prerequisites

- PHP 8.2+
- Composer
- WordPress development environment
- WooCommerce plugin

### Installation

```bash
cd wp-content/plugins
git clone <repository-url> cloudflare-r2-for-woocommerce
cd cloudflare-r2-for-woocommerce
composer install
```

**Install Git hooks (recommended):**
```bash
./install-hooks.sh
```

This installs a pre-commit hook that automatically runs all quality checks before each commit.

Activate plugin in WordPress admin.

## Code Quality

### Pre-commit Hook

A Git pre-commit hook automatically runs all quality checks before each commit:

```bash
./install-hooks.sh  # Run once to install
```

The hook runs:
- PHP CodeSniffer
- PHPStan
- Rector
- PHPUnit Tests

To bypass (not recommended): `git commit --no-verify`

### PHP CodeSniffer (WordPress Coding Standards)

```bash
composer run phpcs      # Check
composer run phpcbf     # Auto-fix
```

### PHPStan (Static Analysis)

```bash
composer run phpstan
```

Configuration: `phpstan.neon`
- Level 6
- WordPress & WooCommerce stubs
- Custom bootstrap for plugin constants

### Rector (Automated Refactoring)

```bash
composer run rector       # Dry-run
composer run rector-fix   # Apply fixes
```

Configuration: `rector.php`
- PHP 8.2+ modernization
- WordPress 0.71 to 6.8 upgrade rules
- Code quality improvements

### PHPUnit Tests

```bash
composer run test                                   # Run tests
vendor/bin/phpunit --coverage-html tests/coverage  # With coverage
vendor/bin/phpunit --testdox                       # Detailed output
```

## Asset Building

Pure PHP minification - no Node.js required!

```bash
composer run build
```

Processes:
- `assets/src/js/*.js` → `assets/js/*.js` + `assets/js/*.min.js`
- `assets/src/css/*.css` → `assets/css/*.css` + `assets/css/*.min.css`

Uses `matthiasmullie/minify` package.

### Development Mode

Enable in `wp-config.php`:
```php
define('SCRIPT_DEBUG', true);
```

Loads unminified assets instead of `.min.js`/`.min.css`.

## Translation

```bash
composer run make-pot
```

Output: `languages/cfr2wc.pot`

Text domain: `cfr2wc`

## File Structure

```
cloudflare-r2-for-woocommerce/
├── includes/
│   ├── class-cloudflare-r2-woocommerce.php  # Main class
│   ├── class-database.php                   # DB schema
│   ├── class-r2-client.php                  # R2 API wrapper
│   ├── class-shortcode.php                  # Shortcode handler
│   ├── class-download-handler.php           # Download proxy
│   ├── class-file-cache.php                 # File listing cache
│   ├── class-file-cache-manager.php         # Cache manager
│   ├── class-logger.php                     # PSR-3 logging
│   ├── class-encryption.php                 # AES-256-GCM
│   └── admin/
│       ├── class-admin-settings.php         # Settings page
│       └── class-product-r2-integration.php # Product integration
├── assets/
│   ├── src/        # Source (not in ZIP)
│   ├── css/        # Built CSS (in ZIP)
│   └── js/         # Built JS (in ZIP)
├── tests/          # PHPUnit tests
├── .github/workflows/
│   ├── ci.yml      # Tests & code quality
│   └── release.yml # Automated releases
├── cloudflare-r2-for-woocommerce.php
├── composer.json
├── phpstan.neon
├── rector.php
├── phpunit.xml
└── build-assets.php
```

## Database

Single table: `{prefix}_cfr2wc_file_cache`
- Caches R2 file listings
- JSON data storage
- Expiration timestamps

## Hooks & Filters

### Filters

**R2 client configuration:**
```php
add_filter('cfr2wc_r2_client_args', function($args) {
    return $args;
});
```

**Pre-signed URL expiration:**
```php
add_filter('cfr2wc_presigned_url_expiration', function($expiration, $object_key) {
    return $expiration; // seconds
}, 10, 2);
```

**Shortcode output:**
```php
add_filter('cfr2wc_shortcode_output', function($output, $atts, $url) {
    return $output;
}, 10, 3);
```

## Continuous Integration

### CI Workflow (`.github/workflows/ci.yml`)

Triggers: Push to main/develop, Pull Requests

**Jobs:**
- **Test**: PHPUnit on PHP 8.2, 8.3, 8.4
- **Code Quality**: PHPCS, PHPStan, Rector

### Release Workflow (`.github/workflows/release.yml`)

Trigger: Version tags (v*)

**Automated steps:**
1. Update version in plugin file and composer.json
2. Build minified assets
3. Install production dependencies
4. Generate POT file
5. Generate changelog
6. Create ZIP
7. Create GitHub release

## Building for Production

### Automated Release (Recommended)

```bash
# Run checks
composer run test
composer run phpstan
composer run rector

# Commit changes
git add .
git commit -m "Release preparation"

# Create and push tag
git tag -a v1.0.1 -m "Release 1.0.1"
git push origin v1.0.1

# GitHub Actions builds and releases automatically
```

### Manual Build

1. **Update versions:**
   - `cloudflare-r2-for-woocommerce.php`: Version header + `CFR2WC_VERSION`
   - `composer.json`: `"version"` field

2. **Run checks:**
   ```bash
   composer run test
   composer run phpstan
   composer run rector
   ```

3. **Build assets:**
   ```bash
   composer run build
   ```

4. **Install production dependencies:**
   ```bash
   composer install --no-dev --optimize-autoloader --ignore-platform-req=ext-curl
   ```

5. **Generate POT:**
   ```bash
   composer run make-pot
   ```

6. **Create package:**
   ```bash
   mkdir -p build
   rsync -av --progress . build/cloudflare-r2-for-woocommerce \
     --exclude .git \
     --exclude .github \
     --exclude .claude \
     --exclude tests \
     --exclude .gitignore \
     --exclude .phpunit.cache \
     --exclude phpunit.xml \
     --exclude phpstan.neon \
     --exclude phpstan-bootstrap.php \
     --exclude rector.php \
     --exclude composer.json \
     --exclude composer.lock \
     --exclude 'assets/src' \
     --exclude build-assets.php \
     --exclude BUILD.md \
     --exclude CONTRIBUTING.md \
     --exclude build

   cd build
   zip -r cloudflare-r2-for-woocommerce-1.0.1.zip cloudflare-r2-for-woocommerce/
   ```

## Pull Request Guidelines

1. Create feature branch from `develop`
2. Write tests for new features
3. Run all checks before submitting
4. Update documentation
5. Clear commit messages
6. One feature per PR

## Coding Standards

- **WordPress Coding Standards** (enforced by PHPCS)
- **Type declarations** (PHP 8.2+)
- **PHPDoc comments** for classes/methods
- **Class naming**: `CFR2WC_` prefix (e.g., `CFR2WC_Logger`)
- **Function prefix**: `cfr2wc_` for functions
- **Constant prefix**: `CFR2WC_` for constants
- **Text domain**: `cfr2wc`

## Security

- Never commit credentials
- Use nonces for forms
- Sanitize input, escape output
- Prepared statements for queries
- Follow WordPress security best practices

Report security issues privately to maintainers.

## Release Checklist

### Automated Release
- [ ] Run `composer run test`
- [ ] Run `composer run phpstan`
- [ ] Run `composer run rector`
- [ ] Commit all changes
- [ ] Create tag: `git tag -a v1.0.1 -m "Release 1.0.1"`
- [ ] Push tag: `git push origin v1.0.1`
- [ ] Wait for GitHub Actions
- [ ] Verify release on GitHub
- [ ] Test installation from ZIP

### Manual Release
- [ ] Update version in `cloudflare-r2-for-woocommerce.php`
- [ ] Update version in `composer.json`
- [ ] Run all quality checks
- [ ] Build assets
- [ ] Generate POT file
- [ ] Create distribution package
- [ ] Test installation

## Support

- [GitHub Issues](../../issues)
- [Pull Requests](../../pulls)

## License

AGPL-3.0-or-later
