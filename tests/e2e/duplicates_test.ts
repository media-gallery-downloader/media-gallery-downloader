import { chromium, firefox } from "playwright";
import { join } from "@std/path";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";

Deno.test("Duplicate Removal Test", async (t) => {
    const browsers = [chromium, firefox];

    for (const browserType of browsers) {
        const name = browserType.name();
        await t.step(`Running in ${name}`, async (_t) => {
            const browser = await browserType.launch({
                headless: true,
                args: ["--ignore-certificate-errors"],
            });
            const context = await browser.newContext({
                ignoreHTTPSErrors: true,
            });
            const page = await context.newPage();

            // Create a dummy file for upload
            const dummyFileName = `test-duplicate-${Date.now()}.png`;
            const dummyFilePath = join(Deno.cwd(), dummyFileName);
            const minimalPng = new Uint8Array([
                137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 13, 73, 72, 68, 82, 0,
                0, 0, 1, 0, 0, 0, 1, 8, 6, 0, 0, 0, 31, 21, 196, 137, 0, 0, 0,
                10, 73, 68, 65, 84, 120, 156, 99, 0, 1, 0, 0, 5, 0, 1, 13, 10,
                45, 180, 0, 0, 0, 0, 73, 69, 78, 68, 174, 66, 96, 130,
            ]);
            await Deno.writeFile(dummyFilePath, minimalPng);

            try {
                // Go to Settings
                console.log(`[${name}] Navigating to Settings...`);
                await page.goto(`${BASE_URL}/admin/settings`);

                // Click Remove Duplicates
                console.log(`[${name}] Clicking Remove Duplicates...`);
                await page
                    .getByRole("button", { name: "Check & Remove Duplicates" })
                    .click();

                // Confirm modal
                await page
                    .getByRole("button", { name: "Yes, remove duplicates" })
                    .click();

                // Check for success message
                console.log(`[${name}] Waiting for notification...`);
                const successMsg = page.getByText(
                    /Successfully removed|No Duplicates Found/
                );
                await successMsg.waitFor();
                console.log(
                    `[${name}] Notification found:`,
                    await successMsg.innerText()
                );
            } catch (err) {
                console.error(`[${name}] Test failed:`, err);
                throw err;
            } finally {
                // Cleanup dummy file
                try {
                    await Deno.remove(dummyFilePath);
                } catch {
                    // ignore
                }
                await browser.close();
            }
        });
    }
});
