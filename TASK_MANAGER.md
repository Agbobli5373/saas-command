# SaaS Starter Task Manager

This file is the source of truth for project execution order and status.

## Status Legend

- `[x]` Done
- `[-]` In Progress
- `[ ]` Planned

## Current Focus

- `[ ] 2.3` Team invite and acceptance flow

## Milestones

### V1 Stripe Billing Foundation

- `[x] 1.1` Stripe billing scaffold (checkout, portal, cancel, resume, protected workspace)
- `[x] 1.2` Idempotent Stripe webhook ingestion and async processing
- `[x] 1.3` Billing UX polish (plan metadata, invoice history, unresolved payment warning, notifications)

### V2 Multi-tenant SaaS Core

- `[x] 2.1` Workspaces + membership model (owner/admin/member)
- `[x] 2.2` Move subscription billable from user to workspace
- `[ ] 2.3` Team invite and acceptance flow
- `[ ] 2.4` Role-based access guards and policy checks
- `[ ] 2.5` Seat-aware billing hooks (prepare for per-seat pricing)

### V3 Product-Ready SaaS Starter

- `[ ] 3.1` Onboarding flow (create workspace + choose plan)
- `[ ] 3.2` Usage limits and feature flags by plan
- `[ ] 3.3` Account notifications center (billing + product events)
- `[ ] 3.4` Admin/owner billing audit timeline
- `[ ] 3.5` Production readiness (error monitoring, queue health, backup checks)

## Rules We Follow

- Work from top to bottom.
- Do not start a new item until the current one is complete and tested.
- Each completed item must have:
  - passing targeted tests
  - clean commit(s) with scoped messages
  - status changed in this file
