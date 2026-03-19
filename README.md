# MarkShust_PolyshellPatch

Mitigates the PolyShell vulnerability (APSB25-94) — an unrestricted file upload in the Magento REST API that allows attackers to upload executable files via cart item custom option file uploads.

## What this module does

Two plugins enforce an image-only extension allowlist (`jpg`, `jpeg`, `gif`, `png`):

1. **ImageContentValidatorExtension** — rejects filenames with non-image extensions before the file is written to disk.
2. **ImageProcessorRestrictExtensions** — calls `setAllowedExtensions()` on the `Uploader` so the framework's own extension check blocks dangerous files as a second layer.

## Installation

```bash
bin/magento module:enable MarkShust_PolyshellPatch
bin/magento setup:upgrade
bin/magento cache:flush
```

## Web server hardening (required for production)

The module blocks uploads at the application layer, but defense-in-depth requires blocking execution/access at the web server level too. Apply the appropriate config below.

### Nginx

Add this **before** any `location ~ \.php$` block to prevent it from taking priority:

```nginx
location ^~ /media/custom_options/ {
    deny all;
    return 403;
}
```

Verify the order matters — nginx processes `^~` prefix matches before regex matches, so this ensures `.php` files in this directory are never passed to FastCGI.

Reload after applying:

```bash
nginx -t && nginx -s reload
```

### Apache

Verify that `pub/media/custom_options/.htaccess` exists and contains:

```apache
<IfVersion < 2.4>
    order deny,allow
    deny from all
</IfVersion>
<IfVersion >= 2.4>
    Require all denied
</IfVersion>
```

Also confirm that `AllowOverride All` is set for your document root so `.htaccess` files are honored.

## Scan for existing compromise

Check whether any files have already been uploaded to the custom_options directory:

```bash
find pub/media/custom_options/ -type f ! -name '.htaccess'
```

If any files are found (especially `.php`, `.phtml`, or `.phar`), investigate immediately — they may be webshells.

## References

- [Sansec: Magento PolyShell](https://sansec.io/research/magento-polyshell)
- Adobe Security Bulletin: APSB25-94
- Patched in Magento 2.4.9-alpha3+ (pre-release only, no production patch available)
