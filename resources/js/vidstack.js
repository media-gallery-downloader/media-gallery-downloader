// Vidstack player — self-hosted web components (<media-player> + default layout).
// Replaces the native <video> in the media-preview modal. Why: Vidstack recovers
// from stalls/network errors, tears down cleanly when its `src` is cleared
// (the stalled-range-request connection leak that froze the whole app), and is
// HLS-ready for future sidecar streams. Everything is bundled locally — no CDN,
// no telemetry, nothing phones home.
import 'vidstack/player';
import 'vidstack/player/ui';
import 'vidstack/player/layouts/default';

import 'vidstack/player/styles/default/theme.css';
import 'vidstack/player/styles/default/layouts/video.css';
