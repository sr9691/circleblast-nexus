# CircleBlast Nexus – Architecture Overview

## High-Level Structure

This system is implemented as a modular WordPress plugin.

circleblast-nexus/

├── circleblast-nexus.php

├── modules/

│ ├── Members/

│ ├── Meetings/

│ ├── Matching/

│ ├── CircleUp/

│ ├── Analytics/

├── core/

│ ├── Services/

│ ├── Repositories/

│ ├── Security/

│ ├── Logging/

├── admin/

├── public/

├── templates/

├── migrations/

---

## Layering Rules

UI → Controllers/Handlers → Services → Repositories → Database

- UI never queries the database directly  
- Services contain business logic  
- Repositories encapsulate all SQL  
- Modules register themselves via a single entry class  
  ---

  ## Naming & Conventions

- PHP namespace: `CircleBlast\Nexus`  
- Prefix everything: `cb_` (tables, options, cron, hooks)  
- No global state unless explicitly intentional  
  ---

  ## Background Processing

- All heavy work via WP-Cron or async handlers  
- Cron jobs must be idempotent  
- Each run logged with correlation ID  
  