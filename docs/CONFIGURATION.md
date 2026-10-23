# Configuration

Configuring a running instance: editing media, tags, sites (cookies and
per-site arguments), yt-dlp behaviour, filenames, and playback/re-encoding. See also
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

## Sites (Cookies and Per-Site Arguments)

Some sites only hand over media to a logged-in session, and some need special
yt-dlp arguments. **Settings → Sites** keeps both per site. Each site has:

- **Domains**: a download uses the site whose domain matches its URL.
  Subdomains are included (`reddit.com` also covers `old.reddit.com`), so a
  leading `www.` is dropped. Add a site's short-link domains too, such as
  `youtu.be` for YouTube or `redd.it` for Reddit. The most specific domain wins
  when two sites match, and a domain can belong to one site only.
- **Cookies**: sent to yt-dlp only for that site's downloads. There is no
  shared cookies file, so a URL that no site covers gets no cookies.
- **Extra yt-dlp arguments**: added to the global arguments or, with **Combine
  with the global arguments** unchecked, used instead of them (see
  [Order of arguments](#order-of-arguments)).

Common reasons to add cookies:

- **YouTube**: age-restricted videos.
- **Reddit**: Reddit links (including videos hosted on redgifs and other
  providers embedded in a Reddit post) need two things in the Reddit site:
  cookies from a logged-in account, and `--impersonate firefox` in its
  arguments. Reddit refuses yt-dlp's own requests even with valid cookies, and
  refuses everyone who isn't logged in.
- Any other site that gates content behind a login.

### Adding Cookies to a Site

1. Use Firefox (its cookies stay valid longer than Chrome's) and log into the site.
2. Export the site's cookies in Netscape `cookies.txt` format, e.g. with the
   "Get cookies.txt LOCALLY" extension.
3. In **Settings → Sites**, add the site (or edit it) and paste the export into
   **Paste cookies**, or upload the file.

The cookies must belong to one of the site's domains (cookies for a parent
domain, such as `google.com` for `accounts.google.com`, count). Stored cookies
are never shown again: the list only says how many there are and when they were
added. Editing a site without pasting new cookies keeps the stored ones, and
**Clear cookies** removes them. To delete several sites at once, tick them (the
box in the header ticks them all) and choose **Delete selected**; their cookies
are deleted too.

> **⚠️ Warning**: If you keep using the browser and account you exported the cookies from, the site may invalidate them quickly. For best results, export from a browser and account you don't otherwise use.

**Note**: Cookies expire over time. If downloads from a site start failing with
an authentication / login error, export fresh cookies and paste them into the
site again.

**Upgrading from a single cookies file**: earlier versions sent one
`cookies.txt` to every site (`YTDLP_COOKIES_FILE`, default
`storage/app/cookies.txt`). On the first start after upgrading, that file is
split into one site for each domain something was downloaded from: a source URL
in the library, or a failed or queued download. Subdomains and short links
count, so a `youtu.be` link counts for YouTube. Subdomains are folded into
their parent domain, and `youtu.be`, `youtube-nocookie.com` and `redd.it` are
added to the YouTube and Reddit sites. Cookies for every other domain are
dropped (a full browser export also holds banks, shops and ad networks), the
log lists those domains, and the file is removed. Rename the generated sites as
you like; to combine two, paste both exports into one site and delete the
other. If the file can't be imported, it is left in place and the reason is
logged. Version 0.7.0 imported every domain in the file; if that left sites you
don't need, delete them together as described above.
`YTDLP_COOKIES_FROM_BROWSER` is no longer supported: it only worked with a
browser on the same machine as yt-dlp.

## yt-dlp Configuration

The application uses [yt-dlp](https://github.com/yt-dlp/yt-dlp) to download media from various online platforms. You can customize the download behavior by passing additional command line arguments.

### Configuring Extra Arguments

1. Navigate to **Settings** in the application
2. Expand the **Configuration** section
3. In the **yt-dlp extra arguments** field, enter any additional arguments you want to pass to yt-dlp
4. Click **Save**

The arguments apply to every yt-dlp step of a download: the metadata lookup and
the download itself. Quote values that contain spaces, as you would in a shell
(`--add-headers "Referer: https://example.com"`). Lines starting with `#` are
comments.

### Order of Arguments

A download's yt-dlp arguments come from up to three places, applied in this
order (when two set the same option, the later one wins):

1. **Global**: Settings → Configuration → yt-dlp Extra Arguments.
2. **Site**: the matching site's arguments (Settings → Sites), added to the
   global ones or replacing them.
3. **This download**: the one-off arguments from the Home page (below), added
   to everything from Settings or replacing it.

### One-off Arguments for a Download

On the Home page, paste a URL and press Enter (or click **Start Download**).
The gear button between the URL field and **Start Download** opens a field for
arguments that apply only to downloads started there. With
**Combine with Settings arguments** checked (the default) they are added to the
global and site arguments; unchecked, they replace them, and leaving the field
empty then runs the download without any extra arguments. The gear stays
highlighted while one-off settings are active, and they stay filled in for the
next URL. They are saved with each download, so its retries (automatic or from
the Logs page) use them again, while global and site arguments are read fresh
at retry time.

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

**Look like a regular browser** (some sites, Reddit among them, refuse yt-dlp's
own requests; the image includes
[curl_cffi](https://github.com/lexiforest/curl_cffi) for this, and it suits a
site's own arguments in Settings → Sites):

```text
--impersonate firefox
```

**Multiple arguments:**

```text
--limit-rate 5M --sleep-interval 5 --max-sleep-interval 30
```

> **Restricted arguments**: Arguments are checked with yt-dlp's own option
> parser when you save them and again before every download, so every spelling
> or abbreviation of an option counts. Options that run programs, load code or
> config files, update yt-dlp, read or write local files, change where files are
> saved, rewrite metadata fields, or dump request traffic are rejected, as are
> `--alias` and anything that isn't an option (such as an extra URL). Options
> that a newer yt-dlp adds are refused until they have been reviewed. If the
> arguments can't be checked, the download fails with the reason. For
> authentication, add the site's cookies in Settings → Sites (see
> [Sites](#sites-cookies-and-per-site-arguments)) rather than passing
> `--cookies` here.

For a complete list of available options, see the [yt-dlp documentation](https://github.com/yt-dlp/yt-dlp#usage-and-options).

## Failed Downloads & Retries

Downloads that fail are recorded on the **Logs** page, and a red badge on its
nav item shows how many failed downloads/uploads are outstanding (it disappears
at zero). Failures retry automatically with exponential backoff — 10, 20, 40,
then 80 minutes (an hourly task re-queues anything whose window has passed) —
up to **5 attempts** in total, after which the item is marked permanently
failed and left for manual retry from the Logs page. A later successful
download of the same URL resolves its failure record, and each Logs section has
a **Clear all** action. A failed download started with one-off yt-dlp
arguments shows them, since its retries use them again.

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
errors, and tears its connection down cleanly when the modal closes. Its
controls sit in a black band below the video instead of overlaying it, so
burned-in subtitles are never covered. Media is
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
