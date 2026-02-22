# Security Standards

## Access Control

- Every action requires:
  - Capability check (`current_user_can()`)
  - Nonce verification for forms (`wp_verify_nonce`) and URL actions

- Three custom roles with 25+ capabilities:
  - `cb_super_admin` — full system access including settings and API keys
  - `cb_admin` — member management, recruitment, matching, archivist, events, analytics
  - `cb_member` — portal access, profile edit, directory, meetings, CircleUp, events

- Portal access gated by `cbnexus_access_portal` capability
- Admin sections gated by `cbnexus_manage_members` capability
- Super admin sections gated by `cbnexus_manage_settings` capability
- Non-members redirected away from portal pages

---

## CSRF Protection

- All form submissions verified with WordPress nonces
- Portal forms use `_wpnonce` (via `wp_nonce_field`)
- Portal URL actions use `_panonce` (via `wp_nonce_url`)
- Token router POST forms include `_cbnexus_token_nonce` for defense-in-depth
- 63+ nonce checks across all admin and portal handlers

---

## Data Handling

- Validate all input
- Escape all output:
  - `esc_html()` for text content
  - `esc_attr()` for HTML attributes
  - `wp_kses_post()` for rich HTML
  - `esc_url()` for URLs

- All SQL uses `$wpdb->prepare()` with typed format arrays
- Meeting repository uses explicit format mapping (`%d`, `%f`, `%s`) for all column types

---

## Secrets

- No secrets in code or database
- Use `wp-config.php` constants:
  - `CBNEXUS_CLAUDE_API_KEY` — required for AI extraction (no database fallback)
  - `CBNEXUS_FIREFLIES_SECRET` — required for webhook validation (rejects all requests if not set)

- API key settings UI in portal only manages non-critical configuration
- Token hashing uses SHA-256; raw tokens never stored

---

## Token System

- Email action tokens (accept/decline meetings, submit notes, manage preferences)
- SHA-256 hashed before storage; raw token only in email links
- Automatic expiry (14-day default)
- Single-use tokens consumed on first use; multi-use tokens support repeated access
- CSRF nonce verification on all token form POSTs
- Automatic cleanup of expired tokens via WP-Cron

---

## Webhook Security

- Fireflies.ai webhook validates `Authorization: Bearer <secret>` header
- Falls back to `?secret=` query parameter
- **Default-deny**: rejects all requests when no secret is configured
- Duplicate detection by `fireflies_id` prevents replay

---

## Logging

- Never log:
  - Tokens or raw secrets
  - Passwords
  - Full transcripts with PII
  - API keys
- Redact emails and identifiers where possible
- Log retention: 30-day automatic purge via daily cron
- Request ID correlation for tracing

---

## Development Scripts

- Dev-only scripts (`create-members.php`, `seed-data.php`) isolated in `dev/` directory
- Not loaded by the plugin bootstrap
- Must not be deployed to production
