# CircleBlast Nexus – Architecture Overview

## High-Level Structure

This system is implemented as a modular WordPress plugin.

circleblast-nexus/

├── circleblast-nexus.php

├── includes/

│ ├── class-autoloader.php

│ ├── admin/

│ ├── logging/

│ ├── migrations/

│ ├── members/

│ ├── meetings/

│ ├── matching/

│ ├── circleup/

│ ├── emails/

│ ├── public/

├── templates/

---

## Layering Rules

UI → Controllers/Handlers → Services → Repositories → Database

- UI never queries the database directly  
- Services contain business logic  
- Repositories encapsulate all SQL  
- All classes use the CBNexus_ prefix  
- Autoloader (class-map) handles class loading  
  ---

  ## Naming & Conventions

- Class prefix: `CBNexus_`  
- File naming: `class-{name}.php` (WordPress standard)  
- Prefix everything: `cb_` (tables, options, cron, hooks)  
- No global state unless explicitly intentional  
  ---

  ## Background Processing

- All heavy work via WP-Cron or async handlers  
- Cron jobs must be idempotent  
- Each run logged with correlation ID  
