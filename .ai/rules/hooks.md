---
paths:
  - resources/js/hooks/use-audio-recorder.ts
---

# Hooks

## Reset recorder lifecycle guards in effect setup
The app enables React Strict Mode. Any unmounted guard set during effect cleanup must be reset in setup, otherwise development remounts silently discard recorded audio and leave saving stuck. Verify recorder changes with the Chromium fake-device flow in e2e/crm-map.spec.ts, including stop, repeat recording, upload, and denied microphone permission.
