# Coding Standards

## File Size Policy

- Hard limit: **750 lines per file**
- If exceeded, code must be split before merge
- Exception: `class-portal-help.php` (896 lines) — content-heavy help definitions tightly coupled to PHP i18n functions; splitting would add complexity without benefit

### Splitting Patterns

- Service → Service + Rules + Formatter
- Admin Page → Page + Table + Actions
- Webhook → Handler + Validator + Mapper
- Portal Admin → Admin shell + individual Tab classes (10 tabs in `admin-tabs/`)

---

## Modification Rules

- Do not recreate entire files/functions unless >50% change
- Use line-numbered Before/After diffs
- Never repeat unchanged code

---

## Style

- WordPress Coding Standards (PHPCS)
- Explicit types where possible (parameter types, return types)
- Early returns preferred over nested conditionals
- `CBNexus_` prefix on all classes
- `cb_` prefix on all database tables, options, cron hooks, and meta keys

---

## Architecture Patterns

- **Service/Repository separation**: Services contain business logic; Repositories encapsulate SQL
- **Autoloader**: Class-map based (`includes/class-autoloader.php`), supports runtime additions via `CBNexus_Autoloader::add()`
- **Static methods**: Used throughout for stateless service classes (no instantiation needed)
- **Format arrays**: Always provide explicit `$wpdb` format arrays for insert/update operations, especially for DECIMAL and DATETIME columns

---

## SQL Safety

- All user input via `$wpdb->prepare()` with typed placeholders (`%d`, `%s`, `%f`)
- Nullable integer columns: exclude from insert data rather than using format hacks
- Table existence checks: use `$wpdb->prepare("SHOW TABLES LIKE %s", $table)`
- Per-request caching for expensive computations (static variables)
- Batch queries where N² individual queries would otherwise occur

---

## Performance Guidelines

- Use `update_meta_cache('user', $ids)` before bulk `get_user_meta()` loops
- Cache frequently-called query results in static variables
- Batch relationship checks (e.g., active meeting pairs) into single queries with hash map lookup
- Per-request caching for computed aggregates (coverage stats, analytics)

---

## Backward Compatibility

- Do not change public method signatures
- Do not rename hooks or filters
- Database changes require migrations (append-only, never modify existing)

---

## Nonce Conventions

- Portal forms (POST): `_wpnonce` (WordPress default via `wp_nonce_field`)
- Portal URL actions (GET): `_panonce` (custom name via `wp_nonce_url`)
- Token router forms: `_cbnexus_token_nonce` (defense-in-depth alongside token auth)
- Action names follow pattern: `cbnexus_portal_{action}` or `cbnexus_{action}`
