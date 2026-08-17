---
paths:
  - 'app/Http/Presenters/SiteGuide/**'
---

# Site Guide

## Guide file usage previews are uncapped by design
GuideFileSchedulePreviews returns every schedule using a guide file — no limit, no date window. Deliberate: an admin renaming, replacing or deleting a guide file needs the full usage picture, not a window onto it (the pivot cascades, so a delete silently detaches the file everywhere).

Cost is managed instead of truncated: the index keeps it behind Inertia::optional so it is only fetched once a card is opened, the selectedGuideFiles eager load carries the same whereIn as the whereHas, and rows are streamed with lazy(). The client shows a handful per file behind a すべて表示 toggle.

Do not "fix" the missing limit() — change the product decision first.
