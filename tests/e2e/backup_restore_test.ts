import { chromium } from "playwright";
import { assert, assertEquals } from "@std/assert";
import { DatabaseSync } from "node:sqlite";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";

// Runs against a stack that shares its data with the dev app, so the cycle
// stays on throwaway data:
// - the backup "Run Now" creates is deleted again afterwards;
// - what gets restored is a copy of that backup with its media table emptied,
//   so the restore reads a real backup file but has nothing to import. (A
//   backup can hold media that has since been deleted; restoring it as it is
//   would bring those records back and queue their downloads.)
Deno.test("Backup and restore cycle works", async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();
    const tempDir = await Deno.makeTempDir({ prefix: "mgd-e2e-backup-" });
    let createdBackup = "";

    try {
        // Go to settings page
        await page.goto(`${BASE_URL}/admin/settings`, {
            waitUntil: "networkidle",
            timeout: 30000,
        });

        const status = page.url().includes("settings") ? 200 : 500;
        if (status !== 200) {
            throw new Error("Settings page failed to load");
        }
        console.log("✓ Settings page loaded");

        // The Maintenance section. Filament wraps the page itself in a
        // <section> too, hence the class.
        const maintSection = page.locator("section.fi-section").filter({
            has: page.getByRole("heading", { name: "Maintenance", exact: true }),
        });

        // Find the Database backup row
        const backupRow = maintSection.locator("tr", {
            hasText: "Database backup",
        });
        const runNowButton = backupRow.getByRole("button", { name: "Run Now" });

        // Expand the Maintenance section (it's collapsed by default)
        if (!(await runNowButton.isVisible())) {
            await maintSection.locator("header").first().click();
        }
        await runNowButton.waitFor({ state: "visible", timeout: 5000 });
        console.log("✓ Maintenance section expanded");

        // The backups there are before this run (the Download Backups modal
        // lists them, open or not)
        const downloadModal = page.locator(
            '[data-fi-modal-id="download-backups-modal"]'
        );
        const backupLinks = downloadModal.locator("a[download]");
        const listBackups = () =>
            backupLinks.evaluateAll((links) =>
                links.map((link) => link.getAttribute("href") ?? "")
            );
        const backupsBefore: string[] = await listBackups();

        // Click Run Now for Database backup to create a fresh backup
        await runNowButton.click({ timeout: 10000 });
        await page
            .getByText("Backup created successfully")
            .waitFor({ state: "visible", timeout: 15000 });
        console.log("✓ Backup triggered");

        // Now test the restore flow - download the new backup file first
        // Click Download button on the backup row
        const downloadButton = backupRow.getByRole("button", {
            name: "Download",
        });
        await downloadButton.click();
        await downloadModal
            .getByRole("heading", { name: "Download Backups" })
            .waitFor({ state: "visible", timeout: 5000 });

        // The backup this run created is the one that wasn't listed before
        const newBackups = (await listBackups()).filter(
            (href) => !backupsBefore.includes(href)
        );
        assertEquals(
            newBackups.length,
            1,
            "Run Now should add one backup to the Download Backups list"
        );
        const backupFilename = decodeURIComponent(
            new URL(newBackups[0], BASE_URL).pathname.split("/").pop() ?? ""
        );
        createdBackup = backupFilename;
        console.log(`✓ Found backup file: ${backupFilename}`);

        // Download the file
        const downloadLink = downloadModal.locator(
            `a[download][href="${newBackups[0]}"]`
        );
        await downloadLink.waitFor({ state: "visible", timeout: 5000 });
        const downloadPromise = page.waitForEvent("download");
        await downloadLink.click();
        const download = await downloadPromise;

        // Save to temp location
        const tempPath = `${tempDir}/${backupFilename}`;
        await download.saveAs(tempPath);
        console.log(`✓ Downloaded backup to ${tempPath}`);

        // Verify the downloaded file is a valid SQLite database
        const fileContent = await Deno.readFile(tempPath);
        const header = new TextDecoder().decode(fileContent.slice(0, 16));
        assertEquals(
            header.startsWith("SQLite format 3"),
            true,
            "Downloaded file should be a valid SQLite database"
        );
        console.log("✓ Verified backup is valid SQLite database");

        // Close the download modal
        await page.keyboard.press("Escape");
        await downloadModal
            .getByRole("heading", { name: "Download Backups" })
            .waitFor({ state: "hidden", timeout: 5000 });

        // Empty the copy's media table, so restoring it imports nothing.
        // Leaving WAL mode keeps the copy a single self-contained file.
        const copy = new DatabaseSync(tempPath, {
            enableForeignKeyConstraints: false,
        });
        try {
            copy.exec("PRAGMA journal_mode = DELETE");
            copy.exec("DELETE FROM media");
        } finally {
            copy.close();
        }

        // Check the file as it will be uploaded, not the connection that
        // changed it
        const emptied = new DatabaseSync(tempPath, { readOnly: true });
        try {
            const left = emptied
                .prepare("SELECT COUNT(*) AS n FROM media")
                .get() as { n: number };
            assertEquals(
                left.n,
                0,
                "The copy to restore should hold no media records"
            );
        } finally {
            emptied.close();
        }
        console.log("✓ Emptied the media table in the downloaded copy");

        // Now try to restore
        // Click Restore button on the backup row
        const restoreButton = backupRow.getByRole("button", {
            name: "Restore",
        });
        await restoreButton.click();
        const restoreModal = page.locator(
            '[data-fi-modal-id="restore-backup-modal"]'
        );
        await restoreModal
            .getByRole("heading", { name: "Restore from Backup" })
            .waitFor({ state: "visible", timeout: 5000 });

        // Upload the backup file
        const fileInput = restoreModal.locator('input[type="file"]');
        await fileInput.waitFor({ state: "attached", timeout: 5000 });
        await fileInput.setInputFiles(tempPath);
        console.log("✓ Uploaded backup file for restore");

        // Wait for restore to complete
        const restoreResult = page
            .locator(".fi-no-notification")
            .filter({ hasText: /Restore (completed|failed)|Invalid file/ });
        await restoreResult
            .first()
            .waitFor({ state: "visible", timeout: 15000 });
        const resultText = (await restoreResult.first().innerText())
            .replace(/\s+/g, " ")
            .trim();

        // Take screenshot for debugging
        await page.screenshot({ path: "/tmp/restore_result.png" });

        // Check for error notification
        const errorNotification = page.locator("text=malformed");
        const hasError = (await errorNotification.count()) > 0;

        assertEquals(
            hasError,
            false,
            "Should not have 'malformed' error after restore"
        );
        console.log("✓ Restore completed without 'malformed' error");

        // Check for success notification
        assert(
            resultText.includes("Restore completed"),
            `Restore should complete, got: ${resultText}`
        );
        assert(
            resultText.includes("Imported 0 records"),
            `The emptied copy should import nothing, got: ${resultText}`
        );
        console.log("✓ Restore completed successfully");
    } finally {
        // Cleanup
        try {
            await Deno.remove(tempDir, { recursive: true });
        } catch {
            // ignore cleanup errors
        }
        if (createdBackup) {
            await removeBackup(createdBackup);
        }
        await browser.close();
    }
});

/** Delete the backup this run created from the app's backups directory. */
async function removeBackup(filename: string): Promise<void> {
    if (!/^backup_[\d_-]+\.(sqlite|sql)$/.test(filename)) {
        console.error(`⚠ Not removing unexpected backup name: ${filename}`);
        return;
    }

    try {
        const { code, stderr } = await new Deno.Command("docker", {
            args: [
                "compose",
                "exec",
                "-T",
                "app",
                "rm",
                "-f",
                `storage/app/data/backups/${filename}`,
            ],
        }).output();
        if (code !== 0) {
            throw new Error(new TextDecoder().decode(stderr));
        }
        console.log(`✓ Removed test backup ${filename}`);
    } catch (err) {
        console.error(`⚠ Could not remove test backup ${filename}:`, err);
    }
}
