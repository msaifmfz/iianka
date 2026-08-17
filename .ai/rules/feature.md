---
paths:
  - 'tests/Feature/**'
---

# Feature

## Load the page before testing an Inertia::optional partial reload
Inertia only knows its asset version after it has answered a request, so `Inertia::getVersion()` is `''` at the start of a test. Sending that as `X-Inertia-Version` on the FIRST request gets a 409 + `X-Inertia-Location` that looks like a version mismatch but really means "asked too early".

Do a normal `->get($route)` first, then the partial-reload request with the headers — which is what the browser does anyway. See `usageScheduleHeaders()` in ConstructionSiteLibraryTest and `usageSchedulePreviewHeaders()` in StockTermReportTest.
