# Configuration

Configuring a running instance: editing media, tags, authentication cookies,
yt-dlp behaviour, filenames, and playback/re-encoding. See also
[Bulk Import](BULK_IMPORT.md); for install and deployment see
[Deployment](DEPLOYMENT.md).

## Editing Media & Tags

Each gallery thumbnail has an info (ⓘ) button that opens an **edit** modal:

- **Title** — the media's display name. Editing it does **not** rename the file
  on disk or change its URL (that stays `<title>-<timestamp>.<ext>` from when it
  was downloaded); only the shown title changes.
- **Source** — where the item came from (a URL or a note).
- **Tags** — type to search existing tags or add a new one; recently-used tags
  are suggested. An item can carry any number of tags.

**Filtering by tag:** a tag bar above the gallery lists every tag in use. Click
tags to filter the grid to items that have **all** of the selected tags; click
again (or **Clear**) to remove them. The tag filter is preserved as you sort,
search, and page, and is reflected in the URL so it can be bookmarked.

## Authentication (Cookies)

Some sites require a logged-in session before they will hand over media, and the
application provides those credentials to yt-dlp via a `cookies.txt` file. The
**same cookies file is used for every site** (not just YouTube), so one file can
hold cookies for several domains. Common cases:

- **YouTube** — age-restricted videos.
- **Reddit** — Reddit now requires an account to read post metadata, so Reddit
  links (including videos hosted on redgifs and other providers embedded in a
  Reddit post) need cookies. Without them the download fails with a message like
  _"This site requires you to be logged in."_
- Any other site that gates content behind a login.

### Export Cookies from Your Browser

1. Install a browser extension like "Get cookies.txt LOCALLY" (available for Chrome and Firefox)
2. Log into the site(s) you download from (e.g. youtube.com, reddit.com)
3. Use the extension to export cookies to a `cookies.txt` file (Netscape format) — a single file can contain cookies for multiple sites

### Upload Cookies to the Application

1. Open the application and navigate to **Settings**
2. In the **Authentication (Cookies)** section, click "Upload Cookies File"
3. Select your exported `cookies.txt` file
4. Click "Upload Cookies"

Downloads from authenticated sites should now succeed.

> **⚠️ Warning**: If you continue to use the browser and account you exported the cookies from, the uploaded cookie may become invalidated quickly. For best results, use a browser and account that you don't normally use and don't use that combination again.

**Note**: Cookies expire over time. If downloads from a site start failing with an authentication / login error, export fresh cookies from your browser and upload them again.

## yt-dlp Configuration

The application uses [yt-dlp](https://github.com/yt-dlp/yt-dlp) to download media from various online platforms. You can customize the download behavior by passing additional command line arguments.

### Configuring Extra Arguments

1. Navigate to **Settings** in the application
2. Expand the **Configuration** section
3. In the **yt-dlp extra arguments** field, enter any additional arguments you want to pass to yt-dlp
4. Click **Save**

### Example Configurations

**Limit download speed:**

```text
--limit-rate 5M
```

**Download specific format:**

```text
-f "bestvideo[height<=1080]+bestaudio/best[height<=1080]"
```

**Use a proxy:**

```text
--proxy socks5://127.0.0.1:1080
```

**Multiple arguments:**

```text
--limit-rate 5M --sleep-interval 5 --max-sleep-interval 30
```

> **Restricted arguments**: For safety, a few flags are ignored if you add them
> here, because they allow arbitrary command/file access or change where files
> are written: `--exec`, `--exec-before-download`, `--config-location`,
> `--load-info-json`, `--batch-file`/`-a`, `--output`/`-o`, `--paths`/`-P`,
> `--downloader`/`--external-downloader`, `--use-postprocessor`,
> `--print-to-file`, `--cookies`, `--cookies-from-browser`. For authentication,
> upload a cookies file via Settings (see [Authentication](#authentication-cookies))
> rather than passing `--cookies` here.

For a complete list of available options, see the [yt-dlp documentation](https://github.com/yt-dlp/yt-dlp#usage-and-options).

## Media Filenames

Media is stored on disk using the item's title plus the unix timestamp (seconds)
of when it was added, e.g. a video titled "My Day at the Zoo" becomes:

```text
My Day at the Zoo-1749134400.mkv
```

This applies to every way media enters the app — downloads, uploads, bulk
imports, and backup restores. The display name shown in the gallery is unchanged.

Titles are made safe for the filesystem and for serving over HTTP:

- Path separators (`/`, `\`), Windows-reserved characters (`: * ? " < > |`), and
  control characters are removed; runs of whitespace are collapsed. Unicode
  letters and emoji are kept.
- The filename is capped at 255 bytes (Linux `NAME_MAX`), without splitting a
  multi-byte character.
- If two items would produce the same name, a `-2`, `-3`, … suffix is added.
- The on-disk name keeps spaces/readable characters; the URL used to serve the
  file is URL-encoded, so spaces and special characters work in the browser.

### Migrating existing files

If you have a library that predates this scheme (files named with a UUID), a
one-off command renames the existing files (and their thumbnails) and updates
the database records. It is safe to re-run (already-renamed files are skipped).

```bash
# Preview every planned rename without changing anything
docker compose exec app php artisan media:rename-files --dry-run

# Apply the renames
docker compose exec app php artisan media:rename-files
```

The command moves each file, then updates its record (rolling the file move back
if the database update fails), and reports how many were renamed, skipped,
missing, or had collisions resolved. Files whose source is missing on disk are
left untouched.

## Video Playback & Media Maintenance

The in-app preview uses a self-hosted [Vidstack](https://vidstack.io) player
(bundled via Vite — no CDN, no telemetry). It recovers from network stalls and
errors, and tears its connection down cleanly when the modal closes. Media is
still stored and served as a **single, plain file** (e.g. `mp4`), so it remains
directly downloadable and playable from an SMB/NFS share — nothing is repackaged
into segmented streams.

For smooth seeking over HTTP, downloaded/imported videos are **faststarted** (the
`moov` atom is moved to the front of the file) automatically on ingestion. Three
maintenance commands cover existing libraries:

```bash
# 1. Faststart any pre-existing files (moov atom -> front). Safe, lossless,
#    in-place; already-optimised files are skipped.
docker compose exec app php artisan media:remux-faststart --dry-run
docker compose exec app php artisan media:remux-faststart

# 2. Report files whose video/audio codec is OUTSIDE the browser baseline
#    (config: config/mgd.php -> codecs, env MGD_BASELINE_VIDEO/AUDIO).
docker compose exec app php artisan media:probe-codecs
docker compose exec app php artisan media:probe-codecs --json

# 3. Re-encode. Two modes (always --dry-run first):
#    a) compatibility (default): non-baseline codec -> H.264/AAC so it plays
#       everywhere.
docker compose exec app php artisan media:reencode --dry-run
docker compose exec app php artisan media:reencode

#    b) --shrink: re-encode large, already-compatible files to a smaller codec
#       (HEVC by default). LOSSY + CPU-heavy, so it is opt-in and gated by
#       --min-size. HEVC stays in the baseline because modern clients (incl.
#       Firefox on Windows) play it.
docker compose exec app php artisan media:reencode --shrink --min-size=500M --dry-run
```

`media:reencode` flags: `--id=` (limit to specific media IDs), `--to=h264|hevc`,
`--min-size=` (e.g. `500M`, `2G`), `--crf=` (quality; lower = better/larger),
`--accel=none|vaapi|nvenc`. The re-encoded file replaces the original in place
(its size — and, if the container changed, its name/URL — are updated).

### Hardware-accelerated re-encoding (optional)

Re-encoding runs in **software by default** (`--accel=none`) and works on any
host. The published `latest` image is GPU-neutral — hardware encode is opt-in, so
it never assumes a particular vendor. First see what's actually available inside
the container:

```bash
docker compose exec app php artisan media:encoders
```

Then enable your GPU:

- **Intel / AMD (VAAPI):** use the `latest-vaapi` image tag (it bundles VAAPI
  drivers for both Intel and AMD) and pass `/dev/dri` into the container via your
  compose (the homelab stacks ship a `docker-compose.override.yml.example`). Then
  run with `--accel=vaapi`. The render node (default `/dev/dri/renderD128`) is set
  by `MGD_VAAPI_DEVICE`.
- **NVIDIA (NVENC):** works on the plain image — `ffmpeg`'s `*_nvenc` encoders
  load the host driver at runtime via the [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/latest/install-guide.html).
  Add the toolkit on the host + a GPU reservation in compose, then `--accel=nvenc`.

```bash
docker compose exec app php artisan media:reencode --shrink --accel=vaapi --min-size=1G
```

