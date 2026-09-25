# Decision log

Append-only. Each decision gets the next `D-NNN`. A decision that stops being true is marked
`Superseded by D-NNN` — never deleted, never rewritten.

Log a decision when it changes schema shape, tenancy or authorization approach, an external
provider, the entitlement mechanism, or deliberately deviates from the product spec. Routine
implementation choices do not need an entry.

## Template

```markdown
## D-NNN — <Decision title>
**Date:** YYYY-MM-DD · **Status:** Accepted | Superseded by D-NNN
**Context:** <the problem and the constraints>
**Decision:** <what was chosen>
**Alternatives:** <what was rejected, and why>
**Consequences:** <what this makes easy, what it makes hard>
```

---

_No decisions recorded yet. The first one due is **D-001 — multi-tenancy strategy**, which
constrains every model, query, policy and job in the project._
