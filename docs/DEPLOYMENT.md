# Deployment

Production deployment and environment configuration. See also
[Backup & Restore](BACKUP.md). For everyday feature configuration (cookies,
yt-dlp, tags, encoding) see [Configuration](CONFIGURATION.md) and
[Bulk Import](BULK_IMPORT.md); for contributing see [Development](DEVELOPMENT.md).

## Production Deployment

### Environment Files

The project includes several environment files for different purposes:

| File | Purpose | Committed to Git |
|------|---------|------------------|
| `.env.docker.example` | Template for Docker deployment settings (ports, paths, timezone). Copy to `.env` and customize. | ✅ Yes |
| `.env.production` | Production Laravel configuration baked into the Docker image. Contains app settings, Redis/Valkey config, etc. | ✅ Yes |
| `.env.development` | Full Laravel configuration for local development. Copy to `.env` when developing. | ✅ Yes |
| `.env` | Your local configuration (created from one of the above). | ❌ No |

**For production users:** You only need to create a `.env` file from `.env.docker.example` if you want to customize deployment settings (port, timezone, data path). The application works out of the box without any `.env` file.

**For developers:** Copy `.env.development` to `.env` before starting the development container.

### Environment Variables

These variables can be set in your `.env` file to customize the Docker deployment:

| Variable | Default | Description |
|----------|---------|-------------|
| `DATA_PATH` | `./storage` | Path to all persistent data (mounted to /app/storage) |
| `HTTP_PORT` | `8080` | HTTP port mapping |
| `UID` | `1000` | User ID for file ownership (used by fixperms) |
| `GID` | `1000` | Group ID for file ownership (used by fixperms) |
| `MEMORY_LIMIT` | `4G` | Container memory limit |
| `TZ` | `UTC` | Timezone |
| `APP_ENV` | `production` | Laravel environment |
| `APP_DEBUG` | `false` | Enable debug mode |
| `LOG_LEVEL` | `warning` | Log verbosity |

> **NAS Users (Synology, QNAP, etc.):** The container runs as UID/GID `1000:1000` by default. If you encounter permission errors, either:
>
> 1. Run `id` to find your user's UID/GID, set them in your `.env` file, then run `./mgd.sh fixperms`
> 2. Or manually fix permissions:
>
>    ```bash
>    sudo chown -R $(id -u):$(id -g) ./storage
>    sudo chmod -R 755 ./storage
>    ```

### Application Configuration

**Core settings** — set these for any production deployment:

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_KEY` | _(none)_ | **Required.** A stable base64 encryption key (generate with `php artisan key:generate --show`). It signs/encrypts sessions and cookies, so it must stay constant — if it changes, everyone is logged out and uploads fail with a `419 CSRF token mismatch`. Supply it via the environment; the image does **not** generate one at build time |
| `APP_URL` | `http://localhost:8080` | Public URL the app is served at, used for absolute/signed-URL generation. Set it to your real address (e.g. `https://media.example.com`) |
| `TRUSTED_PROXIES` | `*` | Reverse proxies to trust for `X-Forwarded-*` headers. `*` trusts any proxy (fine when the container is only reachable through your proxy); a comma-separated list of IPs/CIDRs restricts it; an empty value disables proxy trust — see [Running without a reverse proxy](#running-without-a-reverse-proxy) |

The remaining variables are optional — they tune download behaviour, safety
limits, and the database. Only set them to change the defaults.

| Variable | Default | Description |
|----------|---------|-------------|
| `YTDLP_COOKIES_FILE` | `storage/app/cookies.txt` | Path to the `cookies.txt` sent to yt-dlp for **all** sites (see [Authentication](CONFIGURATION.md#authentication-cookies)) |
| `YTDLP_COOKIES_FROM_BROWSER` | _(unset)_ | Extract cookies from a local browser instead of a file (`chrome`, `firefox`, `edge`, …) |
| `MGD_BLOCK_PRIVATE_HOSTS` | `true` | Block direct (non-yt-dlp) downloads whose host resolves to a private/loopback/link-local/reserved IP (SSRF protection). Disable only if you intentionally download from your LAN |
| `MGD_MAX_DOWNLOAD_BYTES` | `0` | Maximum size of a single direct download, in bytes (`0` = unlimited) |
| `MGD_MAX_ARCHIVE_BYTES` | `0` | Maximum total **uncompressed** size of an uploaded archive, in bytes (`0` = unlimited; guards against zip bombs) |
| `DB_JOURNAL_MODE` | `WAL` | SQLite journal mode. WAL allows concurrent reads during writes |
| `DB_BUSY_TIMEOUT` | `5000` | SQLite busy timeout in ms; waits for a lock instead of failing with "database is locked" |
| `DB_SYNCHRONOUS` | `NORMAL` | SQLite synchronous setting |

### Changing the Default Port

By default, the application listens on port 8080 (HTTP). To use a different port:

- Set the `HTTP_PORT` environment variable in `.env`:

    ```bash
    HTTP_PORT=3000
    ```

- Or edit `docker-compose.yml` and change the port mapping:

    ```yaml
    ports:
        - "3000:8080"
    ```

- Update your `.env` file with the new port:

    ```bash
    APP_URL=http://192.168.1.100:3000
    ```

- Restart the application and access via `http://192.168.1.100:3000`

### Updating

To update to the latest version:

```bash
./mgd.sh update
```

Or manually:

```bash
docker compose pull
docker compose up -d
```

The application will automatically run any necessary database migrations on startup.

> **Tip:** Check the [releases page](https://github.com/media-gallery-downloader/media-gallery-downloader/releases) for changelogs before updating.
>
> **Upgrading an older library?** New media is now stored with readable
> `<title>-<timestamp>.<ext>` filenames. To convert previously downloaded files
> (named with a UUID), run the one-off [`media:rename-files`](CONFIGURATION.md#migrating-existing-files)
> command after updating.

### Using a Reverse Proxy (Recommended for Production)

For HTTPS support, place the application behind a reverse proxy like Caddy or Traefik.

The app trusts reverse-proxy `X-Forwarded-*` headers by default (`TRUSTED_PROXIES=*`)
so it sees the real `https` scheme and host (needed for correct links, signed
upload URLs, and session cookies). This is safe when the container is only
reachable through your proxy — e.g. its port is bound to `127.0.0.1` (as in the
provided compose) or only the proxy shares its network. To restrict it, set
`TRUSTED_PROXIES` to a comma-separated list of your proxy's IPs/CIDRs.

Also set a real `APP_URL` (e.g. `https://media.example.com`) and a stable
`APP_KEY` — see [Application Configuration](#application-configuration).

#### Caddy Example

Add to your Caddyfile:

```caddyfile
mgd.example.com {
    reverse_proxy app:8080
}
```

Caddy automatically obtains and renews SSL certificates from Let's Encrypt.

#### Traefik Example

Add these labels to your docker-compose.yml:

```yaml
labels:
    - "traefik.enable=true"
    - "traefik.http.routers.mgd.rule=Host(`mgd.example.com`)"
    - "traefik.http.routers.mgd.entrypoints=websecure"
    - "traefik.http.routers.mgd.tls.certresolver=letsencrypt"
    - "traefik.http.services.mgd.loadbalancer.server.port=8080"
```

This approach lets you:

- Use your existing SSL certificate infrastructure
- Avoid port conflicts with other services
- Integrate with your existing reverse proxy setup

#### Running Without a Reverse Proxy

The app can run standalone (exposed directly on its port) without a proxy. In that
case there is no trusted proxy in front of it, so disable proxy-header trust so
clients can't spoof their scheme/IP, and point `APP_URL` at the address you serve:

```env
TRUSTED_PROXIES=
APP_URL=http://your-host:8080
```

You'll be serving plain HTTP unless you terminate TLS some other way. The
[Security](../README.md#security) note still applies — the app has no built-in authentication,
so don't put it on an untrusted network without protecting it.

