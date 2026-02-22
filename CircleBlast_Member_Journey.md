# **Visual Flow (Linear)**

Pre-Member (Draft) → Member (Onboarding → Engagement & Growth (Draft) → Retention (Draft)) → Alumni (Draft)

---

# **System Integration**

The CircleBlast Nexus plugin tracks member status and onboarding stages:

- **Member status** (`cb_member_status`): `active` / `inactive` / `alumni` — managed by admins in the portal
- **Onboarding stage** (`cb_onboarding_stage`): `access_setup` → `walkthrough` → `ignite` → `ambassador` → `complete` — defaults to `access_setup` on member creation
- **Recruitment pipeline** (`cb_candidates` table): Tracks Pre-Member journey from `referral` → `contacted` → `invited` → `visited` → `decision` → `accepted` / `declined`
- **Referrer tracking** (`cb_referred_by`): Links to the member who referred the candidate
- **Ambassador assignment** (`cb_ambassador_id`): Links new member to their onboarding ambassador

---

# **Member Journey Overview**

### **1. Pre-Member (Draft)**

* **Awareness** (Heard about CB, attended an Event, Invited By Member)
* **Interest** (Has Visited a CircleUp, Wants to Join)
* **Evaluation** (Assessed by Group)
* **Invitation** (Invited to Join)

### **2. Member**

* **Onboarding** (Access & Setup, Walkthrough, Ignite Session, Ambassador Program — with timelines, triggers, and owners)
* **Engagement & Growth (Draft)** (sessions, networking, resources, progress tracking — placeholders for timeline, triggers, owners)
* **Retention & Value Deepening (Draft)** (celebrations, leadership, renewal — placeholders for timeline, triggers, owners)

### **3. Alumni (Draft)**

* **Transition & reflection**
* **Alumni community access**
* **Ambassadorship & referrals** — placeholders for timeline, triggers, owners

---

# **Detailed Outline**

### **1. Pre-Member (Draft)**

####

#### **A. Awareness**

* *Timeline: Ongoing / Passive*
* *Triggers: Member referral, event attendance, organic exposure*
* *Owner(s): Recruiter (Primary), Conductor (Support)*
* *System: Candidate enters pipeline as `referral` stage via portal referral form or admin intake*

* Potential member becomes aware of CircleBlast through:
  * Member referral
  * Attendance at a CircleBlast event or CircleUp
    Word-of-mouth within aligned communities

* **Notes:**
  * Conductor ensures events are well-run, welcoming, and aligned with CircleBlast culture
  * Recruiter observes early signals of values alignment and curiosity

  ---

#### **B. Interest**

* *Timeline: 1–30 days after initial exposure*
* *Triggers: Expressed curiosity, follow-up conversation, repeat event attendance*
* *Owner(s): Recruiter (Primary), Conductor (Support)*
* *System: Candidate stage updated to `contacted` — referrer receives email notification*

* Potential member demonstrates interest by:
  * Attending a CircleUp
  * Asking questions about the group
  * Expressing a desire to explore membership

* **Notes:**

  * Recruiter engages in informal conversations to understand intent and fit
  * Conductor coordinates access to appropriate events or gatherings

  ---

#### **C. Evaluation**

* *Timeline: 30–60 days (can be shorter or longer depending on fit and cadence)*
* *Triggers: Recruiter identifies strong alignment and initiates evaluation*
* *Owner(s): Recruiter (Primary), Archivist (Support), Conductor (Support)*
* *System: Candidate stage updated to `invited` → `visited` as they progress*

* Candidate is evaluated for fit based on:

  * Values alignment
    Contribution mindset
  * Relational and business fit with the group

* **Support Roles:**

  * Archivist captures feedback, observations, and key insights from member interactions

  * Conductor ensures candidate participation aligns with the group's rhythm and purpose

  ---

#### **D. Invitation**

###

* *Timeline: Within 7 days of evaluation decision*

* *Triggers: Group or leadership consensus to invite*

* *Owner(s): Recruiter (Primary), Archivist (Support)*

* *System: Candidate stage set to `decision` → `accepted`. Member account created with welcome email. Candidate pipeline record preserved for analytics.*

* Formal invitation extended to the candidate, including:

  * Expectations of membership

  * Overview of commitment and culture

  * Clear next steps if accepted

* **Support Roles:**

  * Archivist records invitation status and acceptance decision

* **Outcome:**

  * Upon acceptance, candidate transitions from **Pre-Member → Member (Onboarding begins)**

---

### **2. Member**

#### **A. Onboarding**

*System tracks onboarding stage via `cb_onboarding_stage` meta field. Default stage on creation: `access_setup`.*

**1. Access & Setup**

* *Within 3 Days of Sign-Up | Trigger: Payment confirmed, welcome email sent*
* *System stage: `access_setup`*
* **Admin Setup (Ryan)**
  * Add new contact as a Lead
  * Promote to a Member and pick a plan.
  * Billing setup - credit card first, ACH setup later.
  * CoWorks app (for reserving space)
* **Tech Setup (Ben)**
  * Club Works access (key fob / fingerprint)
  * Ctrl+All Access Panel/App setup

---

**2. Facility Walkthrough**

* *Within 7 Days of Sign-Up | Trigger: Access & Setup completed*
* *System stage: `walkthrough`*
* **Facilities Orientation (Ben or Ryan)**
  * WiFi credentials (set up on-site)
  * Coffee maker / water machines
  * Fax / printer
  * Monitors / screens
  * Golf simulator
  * Orientation of offices, meeting rooms, shared spaces
  * Locker Set Up
  * Swag Bag? (The Power of Moments)

---

**3. Ignite Session with Culture Coach**

* *Within 10 Days of Sign-Up | Trigger: Facility Walkthrough completed*
* *System stage: `ignite`*

* **Culture Coach (Lucas)**
  * Vision & Values alignment
  * Flow and structure of meetings
  * *CircleBlast app training??*
  * Defining goals and expectations
  * How to get the most out of membership
  * Quick wins (networking, needs, etc.)
  * Upcoming opportunities/events

---

**4. Ambassador Program Integration**

* *Immediately Following Ignite Session | Trigger: Completion of Ignite Session*
* *System stage: `ambassador` (ambassador assigned via `cb_ambassador_id`)*
* **Culture Coach (Lucas)**
  * Partnered with a fellow member for training on the "Perfect CB 1:1"
  * Accountability for rituals and processes
  * Ongoing support and cultural integration

*System stage moves to `complete` when ambassador program concludes.*

---

#### **B. Engagement & Growth (Draft)**

* *Timeline:*
* *Triggers:*
* *Owner(s):*
* *System: Tracked via 1:1 meeting completion, CircleUp attendance, notes submission rates, engagement score (0-100)*

* **Regular Touchpoints**
  * Sessions, events, workshops
  * Community networking opportunities

* **Support & Resources**
  * Coaching, frameworks, exclusive content

* **Progress Tracking**
  * Milestone check-ins
  * Feedback loops
  * Personal dashboard shows engagement metrics

---

#### **C. Retention & Value Deepening (Draft)**

* *Timeline:*
* *Triggers:*
* *Owner(s):*
* *System: Churn risk flagged when engagement score <40 or >45 days inactive. Admin analytics dashboard surfaces at-risk members.*

* **Recognition**
  * Celebrating member progress
  * Top connectors leaderboard on Club Stats

* **Leadership Opportunities**
  * Mentorship, volunteering, leading groups

* **Renewal Pathways**
  * Encouragement to extend/renew membership

---

### **3. Alumni (Draft)**

* *Timeline:*
* *Triggers:*
* *Owner(s):*
* *System: Member status set to `alumni`. Excluded from matching, emails, and active features. Preserved in meeting history and analytics.*

* **Transition**
  * Closing reflection / exit survey

* **Continued Connection**
  * Alumni network access
  * Updates, newsletters, events

* **Ambassadorship**
  * Referrals
  * Speaking/mentorship opportunities
