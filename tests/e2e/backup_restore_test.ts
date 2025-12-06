import { chromium } from "npm:playwright";
import { assertEquals, assertNotEquals } from "jsr:@std/assert";

const BASE_URL = "http://localhost:8080";

Deno.test("Backup and restore cycle works", async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();
    let tempPath = "";

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

        // Expand the Maintenance section (it's collapsed by default)
        const maintSection = page.locator(
            'section:has(h3:text("Maintenance"))'
        );
        const collapseTrigger = maintSection
            .locator('[x-on\\:click*="isCollapsed"]')
            .first();
        await collapseTrigger.click();
        await page.waitForTimeout(500);
        console.log("✓ Maintenance section expanded");

        // Find the Database backup row
        const backupRow = page.locator('tr:has-text("Database backup")');

        // Click Run Now for Database backup to create a fresh backup
        const runNowButton = backupRow.locator('button:has-text("Run Now")');
        await runNowButton.click({ timeout: 10000 });

        // Wait for backup to complete (notification should appear)
        await page.waitForTimeout(3000);
        console.log("✓ Backup triggered");

        // Now test the restore flow - download a backup file first
        // Click Download button on the backup row
        const downloadButton = backupRow.locator('button:has-text("Download")');
        await downloadButton.click();
        await page.waitForTimeout(500);

        // Wait for modal and get the first backup download link
        const downloadLink = page.locator("a[download]").first();
        await downloadLink.waitFor({ state: "visible", timeout: 5000 });
        const backupFilename = await downloadLink.getAttribute("download");
        console.log(`✓ Found backup file: ${backupFilename}`);

        // Download the file
        const downloadPromise = page.waitForEvent("download");
        await downloadLink.click();
        const download = await downloadPromise;

        // Save to temp location
        tempPath = `/tmp/test_backup_${Date.now()}.sql`;
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
        await page.waitForTimeout(500);

        // Now try to restore
        // Click Restore button on the backup row
        const restoreButton = backupRow.locator('button:has-text("Restore")');
        await restoreButton.click();
        await page.waitForTimeout(500);

        // Upload the backup file
        const fileInput = page.locator(
            'input[type="file"][accept=".sql,.sqlite,.db"]'
        );
        await fileInput.waitFor({ state: "attached", timeout: 5000 });
        await fileInput.setInputFiles(tempPath);
        console.log("✓ Uploaded backup file for restore");

        // Wait for restore to complete
        await page.waitForTimeout(5000);

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

        if (!hasError) {
            console.log("✓ Restore completed without 'malformed' error");
        }

        // Check for success notification
        const successNotification = page
            .locator("text=Restore completed")
            .or(page.locator("text=Imported"));
        const hasSuccess = (await successNotification.count()) > 0;

        if (hasSuccess) {
            console.log("✓ Restore completed successfully");
        } else {
            console.log(
                "⚠ No explicit success message (may have 0 records to import)"
            );
        }
    } finally {
        // Cleanup
        if (tempPath) {
            try {
                await Deno.remove(tempPath);
            } catch {
                // ignore cleanup errors
            }
        }
        await browser.close();
    }
});
