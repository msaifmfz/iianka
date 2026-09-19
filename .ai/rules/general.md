---
paths:
  - vite.config.ts
---

# General

## Prebundle Leaflet and its cluster adapters together
Keep leaflet, react-leaflet, and react-leaflet-cluster in optimizeDeps.include. The map is lazy-loaded; discovering these dependencies after startup can rebundle Leaflet and leave markercluster registered on a different global L instance. Verify dependency changes with the Chromium CRM flow from a cold Vite cache.
