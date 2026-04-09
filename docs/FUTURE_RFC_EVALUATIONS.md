# Future RFC Evaluations

This document records the current decision state for deferred technical evaluations so they stop living only as roadmap bullets.

## JSON:API Resources

### Question

Should the public API migrate toward JSON:API-compliant resources and envelopes?

### Assessment

- Current API responses are consistent enough for the existing surface area.
- A full JSON:API migration would introduce contract churn across every existing consumer.
- The API is still relatively small, so strict JSON:API compliance is not yet buying enough to offset migration cost.

### Decision

- Do not migrate the existing `/api/v1` contract to JSON:API.
- Revisit only when a larger public integration ecosystem or external partner demand exists.

## PHP Attributes For Middleware

### Question

Should controller and route middleware move to PHP attributes?

### Assessment

- Current route and bootstrap registration is explicit and already consistent.
- Mixing route-file middleware declarations with controller attributes would increase style fragmentation.
- The main benefit is syntactic compactness, not a clear architectural gain.

### Decision

- Keep middleware registration in route files and bootstrap configuration.
- Revisit only for newly isolated modules where attribute-based routing is adopted consistently from day one.

## pgvector Semantic Search

### Question

Should the product add PostgreSQL `pgvector` support for semantic search?

### Assessment

- Current search requirements are met by Scout and Meilisearch.
- Semantic search would add infrastructure and product complexity without a defined user-facing feature yet.
- Operating `pgvector` also raises deployment and backup considerations.

### Decision

- Do not introduce `pgvector` in the current release cycle.
- Revisit when a concrete search experience requires semantic ranking or AI-assisted retrieval.

## Laravel AI SDK

### Question

Should the platform adopt the Laravel AI SDK now?

### Assessment

- No committed product feature currently depends on LLM orchestration.
- Adding the SDK now would increase dependency surface without a clear operational owner.
- AI-related features should be driven by product requirements, data handling policy, and cost controls, not framework novelty.

### Decision

- Defer Laravel AI SDK adoption.
- Revisit only when a concrete product feature, provider strategy, and data-governance policy are defined.

## Review Trigger

Re-open any of these RFCs only when one of the following changes:

- a new external integration requirement appears,
- performance goals cannot be met with the current stack,
- or a product feature requires capabilities the current architecture does not provide.