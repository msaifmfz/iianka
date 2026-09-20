---
paths:
  - 'resources/js/components/crm-map/**'
---

# Crm Map

## Avoid Leaflet zoom-transition callbacks after navigation
Keep zoomAnimation disabled on CRM MapContainer instances while using Leaflet 1.9.4. Its uncancelled zoom-transition fallback timer can run after Inertia unmounts the map and access a removed pane (_leaflet_pos error). Verify zoom, map/list return, and location-picker navigation before changing this workaround.

## Keep place ownership in the client-wide timeline
The selected-place panel shows all histories of the same client, including archived places, newest first. Dim and label other-place entries, but never reassign them to the selected place during edits. New records belong to the selected place; edits preserve the original place and author. Staff authors are 担当者(社), client contacts are 担当者(客). Help icons may sit visually inside actions but must remain separate interactive siblings, never nested buttons.

## Explicit location snapshots and client navigation
Current location is an explicit one-shot permission request, never automatic tracking or application-server storage. Show acquisition time and accuracy; nearby candidates are up to three active places within 5 km by straight-line distance (not travel time). Draw the non-interactive blue point above client markers. Keep client-specific map links separate from map-return links: the former frame the chosen client including archived places; the latter restore the saved user/tab view. Other-place history navigation must enable archived visibility when its target is archived.
