# Bulk Import

The bulk import feature allows you to import large numbers of video files directly from a directory, bypassing the web upload interface. This is ideal for migrating existing video collections or importing files that are too large to upload through a browser.

## How It Works

1. **Place video files** in the `incoming` directory
2. **Schedule or manually trigger** the import scan from Settings → Maintenance
3. **Files are queued** for processing in batches to avoid system overload
4. **Each file is imported** with the filename (without extension) as the display name
5. **File dates are preserved** - the file's last modified date becomes the media record's created/updated date
6. **Successfully imported files** are moved to the media storage and renamed to their database ID
7. **Failed imports** are moved to the `failed` directory with an accompanying `.log` file containing error details

## Directory Structure

```text
storage/                    # DATA_PATH mount point
├── app/
│   ├── db/                 # SQLite database
│   │   └── database.sqlite
│   └── data/
│       ├── media/          # Stored video files
│       ├── thumbnails/     # Generated thumbnails
│       ├── backups/        # Database backups
│       └── import/
│           ├── incoming/   # Place video files here for import
│           └── failed/     # Failed imports with error logs
├── logs/                   # Application logs
└── framework/              # Laravel cache, sessions, views
```

## Accessing the Import Directory

The `storage/` directory is bind-mounted, so files persist even after `docker compose down -v`. You can place files directly in:

```text
./storage/app/data/import/incoming/
```

Or copy files into the container:

```bash
docker compose cp /path/to/videos/. app:/app/storage/app/data/import/incoming/
```

## Supported Video Formats

The bulk import supports the same formats as the web upload:

- MP4, MKV, AVI, MOV, WMV, FLV, WebM, M4V, MPG, MPEG, 3GP, OGV

## Scheduling Import Scans

1. Navigate to **Settings** in the application
2. Expand the **Maintenance** section
3. Find **Bulk import scan** row
4. Select the days and time for automatic scans
5. Click **Save**

You can also click **Run Now** to immediately scan for and queue files.

## Performance Considerations

- **Large files**: The import job has a 2-hour timeout to accommodate very large files (100s of GB)
- **Batch processing**: Files are queued in batches (default: 10) with delays between batches to prevent system overload
- **Queue**: Import jobs run on the `imports` queue, separate from regular uploads and downloads
- **Single attempt**: Each file gets one import attempt; failures are logged and the file is preserved in the `failed` directory

## Handling Failed Imports

If an import fails, the file is moved to `storage/app/import/failed/` along with a `.log` file containing:

- Original filename
- Timestamp of the failure
- Error message explaining why it failed

To retry a failed import:

1. Check the `.log` file to understand the failure
2. Fix any issues (e.g., corrupted file, unsupported format)
3. Move the file back to the `incoming` directory
4. Run another import scan

## Import Environment Variables

You can customize the import paths and batch size via environment variables in `.env`:

```bash
# Custom import paths (optional)
MGD_IMPORT_INCOMING_PATH=/custom/path/to/incoming
MGD_IMPORT_FAILED_PATH=/custom/path/to/failed

# Number of files to queue at once (default: 10)
MGD_IMPORT_BATCH_SIZE=10
```

