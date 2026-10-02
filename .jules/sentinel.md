# Sentinel's Security Journal

## 2026-10-02 - Path Traversal Prevention in HtaccessFile

**Vulnerability:** Unsanitized filtered file path in `HtaccessFile::path()` allowed potential arbitrary file read/write or path traversal via the `rankkernel/htaccess/path` filter hook.
**Learning:** Public filter hooks returning file system paths must validate that resolved paths stay confined within `ABSPATH` (site root) before being used for file read or write operations.
**Prevention:** Enforce directory containment verification using `realpath()` and `wp_normalize_path()` on all filterable file path methods.
