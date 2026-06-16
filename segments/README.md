# Observatory Automation System - Project Segments

This folder contains blueprints for major segments of the Observatory automation system rebuild. Each segment is independently planned and can be worked on separately.

## Segments Overview

### 1. Website Segment (`./website/`)

**File:** `BLUEPRINT.md`

**Scope:** Complete rebuild of observatory.herts.ac.uk

**Timeline:** 3-4 months (Phases 1-3)

**Key Deliverables:**
- Phase 1 (6 weeks): Observation request submission + staff approval
- Phase 2 (8 weeks): 100k+ image archive with search & browsing
- Phase 3 (6 weeks): Advanced tools (stacking, photometry) + analytics

**Tech Stack:** React + FastAPI + PostgreSQL

**What's being built:**
- Public-facing portal for submitting observations
- Image search and download capability
- Staff dashboard for approvals and monitoring
- Dark mode support (critical for nighttime observatory use)
- University of Hertfordshire branding

**Integration:** Calls REST APIs on automation runners (once available in Phase 3)

---

### 2. Automation Segment (`./automation/`)

**File:** `BLUEPRINT.md`

**Scope:** Modernize the telescope automation runner stack (9 scopes)

**Timeline:** Phase 3 (6 weeks, starts after website image archive complete)

**Key Deliverables:**
- Go-based runner daemon on each scope machine (replaces bash)
- REST API for status, logs, job control
- Smart auto-recovery (detect failure type, fix intelligently)
- Structured JSON logging for debugging
- Infrastructure-as-code deployment (Ansible)

**Tech Stack:** Go + Ansible + YAML config

**What's being built:**
- REST API endpoints on each scope machine
- Watchdog service that detects crashes
- Smart recovery (KStars crash → auto-restart)
- Full audit logging of all activities
- One-command deployment for new telescopes

**Integration:** Website uses these REST APIs to display status and control telescopes

---

## Development Timeline

```
MONTHS 1-2: Website Phase 1 (Foundation)
├─ Server setup, database, authentication
├─ Observation request submission form
├─ Staff approval dashboard
└─ First integration with existing automation

MONTH 2-3: Website Phase 2 (Image Archive)
├─ Sync 100k+ FITS images
├─ Build searchable gallery
├─ Image detail pages + download
└─ Performance optimization

MONTH 3-4: Dual Track
├─ Website Phase 3 (Advanced Tools)
│  ├─ Image analysis (stacking, photometry)
│  ├─ Staff operations dashboard
│  └─ Analytics + reporting
│
└─ Automation Phase 3 (Runner Modernization)
   ├─ Go runner core implementation
   ├─ REST API + watchdog
   └─ Ansible deployment
```

## Key Decisions

### Website Segment
- **Complete rewrite** (not upgrade) with modern tech
- **Separate web server** (not on star-server)
- **Dark mode first-class feature** (not an afterthought)
- **Image analysis tools** (stacking, photometry, WCS display)

### Automation Segment
- **Go language** (better than bash for reliability)
- **REST API** (not custom protocol)
- **Smart recovery** (detect failure type, targeted fix)
- **Ansible + YAML** (infrastructure-as-code)

## Resource Requirements

### Website Segment
- **Frontend:** React, TypeScript, dark mode system
- **Backend:** FastAPI (Python), PostgreSQL
- **Effort:** ~3-4 months solo (or 1-2 with help)
- **Complexity:** Medium-High (100k images, search, branding)

### Automation Segment
- **Language:** Go 1.21+
- **Deployment:** Ansible, YAML config
- **Effort:** ~6 weeks (Phase 3, after website)
- **Complexity:** Medium (D-Bus, process management, APIs)

## Dependencies

1. **Website → Automation:** Website calls runner REST APIs (built in Phase 3)
2. **Existing System:** Both segments integrate with current bash scripts initially, then replace them

```
Timeline:
├─ Website Phase 1-2: Use bash scripts as-is
├─ Website Phase 3: Transition to Go runner APIs
└─ Automation Phase 3: Go runner available for website to use
```

## Next Steps

1. **Review both blueprints** — Do they match your vision?
2. **Clarify open questions** (listed at end of each blueprint)
3. **Choose starting point** — Website Phase 1 or Automation Phase 3 first?
4. **Secure infrastructure** — Get IT approval for separate web server
5. **Collect existing files** — PHP source, database dump, branding assets

## Related Documentation

- **Current System Guide:** `../GUIDE.md` (how the bash automation works today)
- **Memory:** `../.claude/memory/` (project context and decisions)
- **GitHub Issues:** 10 issues identified, scope/priority TBD

---

**Created:** 2026-06-16  
**Status:** Ready for implementation  
**Owner:** Denis
