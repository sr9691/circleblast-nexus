# CircleBlast Nexus – Progress Tracker

## Completed Iteration: ITER-0007 (Member Directory)

### Goals

- Searchable, filterable member directory with grid/list views
- Individual member profile pages with contact and networking info
- AJAX-powered filtering for responsive experience

### Deliverables

- [x] includes/public/class-directory.php — Directory rendering, AJAX filter handler, member cards, individual profile pages
- [x] assets/js/directory.js — AJAX search (350ms debounce), industry/status filters, grid/list view toggle
- [x] assets/css/portal.css — Directory grid/list layouts, member cards, avatars, tags, profile page, contact list, responsive
- [x] Portal router wired: directory section now renders CBNexus_Directory::render
- [x] "Request 1:1" button on profiles (disabled placeholder, wired in ITER-0009)
- [x] Updated autoloader, main plugin bootstrap
- [x] Updated PROGRESS.md

### Risks / Notes

- Industry filter is client-side post-fetch; scales fine for groups under ~200 members
- "Request 1:1" button is disabled with tooltip — will be enabled in ITER-0009
- Profile photos use URL-based approach (no file upload yet)

---

## Completed Iteration: ITER-0006 (Portal Shell & Profile Edit)

### Goals

- Authenticated member-facing portal with profile self-management
- Shortcode-based page routing with access control
- Responsive CSS for mobile-friendly portal

### Deliverables

- [x] includes/public/class-portal-router.php — Shortcode [cbnexus_portal], section routing, access control, nav, dashboard placeholder
- [x] includes/public/class-portal-profile.php — Member self-service profile edit with nonce verification
- [x] assets/css/portal.css — Responsive portal layout, cards, forms, nav, buttons, mobile breakpoints
- [x] Non-members redirected away; non-logged-in users sent to login
- [x] Updated autoloader and main plugin bootstrap
- [x] Updated PROGRESS.md

### Risks / Notes

- Portal requires a WordPress page containing [cbnexus_portal] shortcode
- Dashboard, Directory, Meetings sections are placeholders until their iterations
- Email field is disabled on profile form (admin-only change)

---

## Completed Iteration: ITER-0005 (Admin Member Management UI)

### Goals

- Admin interface for creating and managing members
- Centralized email service with template support
- Welcome email on member creation

### Deliverables

- [x] Migration 004: Create cb_email_log table
- [x] includes/admin/class-admin-members.php — Member list with status filters, search, bulk actions
- [x] includes/admin/class-admin-member-form.php — Add/edit form with all 17 fields in grouped sections
- [x] includes/emails/class-email-service.php — Centralized sender with template support, HTML layout, db logging
- [x] templates/emails/welcome_member.php — Branded welcome email with password reset link
- [x] Nonce verification + capability checks on all admin actions

---

## Completed Iteration: ITER-0004 (Member Data Model & Roles)

### Goals

- Define the member data foundation that all features depend on
- Create custom roles with capability matrix
- Build service/repository layer for member CRUD operations

### Deliverables

- [x] Migration 002: Custom WordPress roles (cb_super_admin, cb_admin, cb_member) with 25+ capabilities
- [x] Migration 003: Member meta schema (17 keys) + industry taxonomy stored in wp_options
- [x] includes/members/class-member-repository.php — CRUD, search, status, count operations via wp_usermeta
- [x] includes/members/class-member-service.php — Validation, creation, update, status transitions (active/inactive/alumni)
- [x] Updated migration runner with 002 + 003 entries
- [x] Updated autoloader with Member_Repository + Member_Service
- [x] Updated PROGRESS.md

### Risks / Notes

- Roles are removed and re-added on activation to allow capability updates
- Industry taxonomy stored in wp_options; editable via future admin settings
- Tags fields (expertise, looking_for, can_help_with) stored as JSON in usermeta

---

## Completed Iteration: ITER-0003 (Architecture Consolidation)

### Goals

- Resolve dual-architecture issue (core/ vs includes/)
- Establish clean foundation for all feature development

### Deliverables

- [x] Removed orphaned core/ directory (Plugin.php, Logger.php, Migrator.php)
- [x] Removed orphaned modules/Modules.php and migrations/0001_init.php
- [x] Created includes/class-autoloader.php (class-map autoloader, replaces manual requires)
- [x] Created includes/admin/class-admin-logs.php (WP admin page: log viewer with level/date filtering)
- [x] Added PHP 7.4+ / WP 5.9+ compatibility checks with admin notice + activation guard
- [x] Fixed CBNexus_Log_Retention::warning() call signature (3 args → 2)
- [x] Fixed CBNexus_Logger user_id null → 0 cast (conditional format array)
- [x] Updated circleblast-nexus.php to use autoloader, bumped version to 0.2.0
- [x] Updated PROGRESS.md

### Risks / Notes

- No external references to core/ were found; safe to remove
- Autoloader supports runtime class-map additions via CBNexus_Autoloader::add()

---

## Completed Iteration: ITER-0002 (Persistence Foundations)

### Goals

- Introduce first real migration with no behavioral breakage  
- Enable durable internal logging for diagnostics and support  

### Deliverables

- [x] Migration `001_create_log_table` (creates `{$wpdb->prefix}cbnexus_log`)  
- [x] Activation-only migration execution  
- [x] DB-backed logging with guaranteed fallback to baseline behavior  

### Risks / Notes

- No runtime or upgrade-triggered migrations  
- No admin UI or capability changes  

---

## Completed Iteration: ITER-0001 (Plugin Skeleton)

Delivered:
- Plugin bootstrap  
- Activation/deactivation hooks  
- Migration runner (activation-only)  
- Base logging service (stub)  

---

## Completed Iteration: ITER-0000 (Foundation)

Delivered:
- CONTRIBUTING.md  
- ARCHITECTURE.md  
- CODING_STANDARDS.md  
- SECURITY.md  
- MIGRATIONS.md  
- PROGRESS.md  

---

## Backlog (High Level)

- **ITER-0005:** Admin Member Management UI
- **ITER-0006:** Portal Shell & Profile Edit
- **ITER-0007:** Member Directory
- **ITER-0008:** Meetings Data Model & Core Workflow
- **ITER-0009:** Manual 1:1 Requests & Meeting UI
- **ITER-0010:** Matching Rules Engine
- **ITER-0011:** Automated Suggestion Generation
- **ITER-0012:** CircleUp + Fireflies Integration
- **ITER-0013:** AI Extraction + Archivist Workflow
- **ITER-0014:** CircleUp Archive & Member Submissions
- **ITER-0015:** Personal Member Dashboard
- **ITER-0016:** Club Dashboard & Presentation Mode
- **ITER-0017:** Admin Analytics, Reports & Recruitment Pipeline

---

## Rules

- Every feature or fix **must update this file**  
- Updates should be 1–3 lines per change  
