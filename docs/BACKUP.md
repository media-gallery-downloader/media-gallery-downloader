# Backup and Restore

The application includes built-in backup and restore functionality to help you safeguard your media database and migrate between installations.

## Creating Backups

Backups can be created automatically on a schedule or manually:

1. Navigate to **Settings** in the application
2. Expand the **Maintenance** section
3. Find the **Database backup** row
4. Select days and time for automatic backups, then click **Save**
5. Click **Run Now** to create an immediate backup

Backups are stored in `storage/app/data/backups/` and are automatically cleaned up based on the retention period (default: 30 days).

## Downloading Backups

To download a backup for offsite storage or migration:

1. In **Settings** → **Maintenance**, find the **Database backup** row
2. Click the **Download** button
3. Select the backup you want to download from the list

## Restoring from Backup

To restore your media database from a backup:

1. In **Settings** → **Maintenance**, find the **Database backup** row
2. Click the **Restore** button
3. Select your backup file (`.sql`, `.sqlite`, or `.db`)
4. Review the warning and click to upload

**What happens during restore:**

- **Existing records** with matching name + size are skipped (no duplicates created)
- **Records without source URLs** are skipped (they cannot be re-downloaded)
- **Records with source URLs** but missing local files are queued for automatic re-download
- **Records with existing files** have their thumbnails regenerated
- The restore process is additive - it won't delete any existing data
- **Notifications** show a summary of imported, queued, skipped duplicates, and skipped no-source records

**Supported backup formats:**

- SQLite database files (direct copies from this application)
- SQL dump files with INSERT statements

## Migration to a New Server

To migrate your media library to a new server (e.g., Synology NAS):

1. **On the old server:**
   - Create a fresh backup via Settings → Maintenance → Run Now
   - Download the backup file
   - Copy your `storage/app/data/media/` directory (the actual video files)

2. **On the new server:**
   - Set up the application following the Quick Start guide
   - Copy the media files to `./data/media/` (or `./storage/app/data/media/` for development)
   - Go to Settings → Maintenance → Restore
   - Upload your backup file

3. **After restore:**
   - Videos with local files will be immediately available
   - Videos without local files (but with source URLs) will be queued for download
   - Check the queue status in the application to monitor re-downloads

