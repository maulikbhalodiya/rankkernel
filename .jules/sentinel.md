# Sentinel Security Journal

## 2025-05-18 - Admin File Upload Validation on Redirect CSV Import

**Vulnerability:** The redirect CSV import handler (`RedirectsPage::handleImport()`) parsed temporary upload paths directly without validating that the file path was a legitimate HTTP upload via `is_uploaded_file()` or verifying the file extension/MIME type via `wp_check_filetype()`.
**Learning:** Relying solely on `$_FILES['rk_csv_file']['tmp_name']` and `is_readable()` left the handler open to unvalidated file processing or unexpected file types if temporary upload parameters were manipulated.
**Prevention:** Always validate uploaded temporary files using `is_uploaded_file()` (with an injectable test seam) and enforce strict file extension/MIME type validation using `wp_check_filetype()` before reading or parsing upload contents.
