import { chromium, firefox } from "playwright";
import { assert } from "@std/assert";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";

// Duplicate removal is a row of the Maintenance table on the Settings page.
// Its "Run Now" has no confirmation step and deletes duplicates from the
// whole library at once, and the stack under test shares its library with
// the dev app. So this only checks the controls are reachable; it never
// clicks them. The removal itself is covered by Pest (MaintenanceServiceTest,
// SettingsPageTest).
Deno.test("Duplicate removal controls", async (t) => {
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

            try {
                // Go to Settings
                console.log(`[${name}] Navigating to Settings...`);
                await page.goto(`${BASE_URL}/admin/settings`);

                // The Maintenance section. Filament wraps the page itself in
                // a <section> too, hence the class.
                const maintenance = page.locator("section.fi-section").filter({
                    has: page.getByRole("heading", {
                        name: "Maintenance",
                        exact: true,
                    }),
                });
                const row = maintenance.locator("tr", {
                    hasText: "Duplicate removal",
                });
                const runNow = row.getByRole("button", { name: "Run Now" });
                const save = row.getByRole("button", { name: "Save" });

                // The section starts collapsed, which hides its table
                if (!(await runNow.isVisible())) {
                    console.log(`[${name}] Expanding Maintenance section...`);
                    await maintenance.locator("header").first().click();
                }

                await row.getByText("Remove duplicate media files.").waitFor();
                await runNow.waitFor();
                await save.waitFor();
                assert(await runNow.isEnabled(), "Run Now is enabled");
                assert(await save.isEnabled(), "Save is enabled");
                console.log(`[${name}] Duplicate removal controls found.`);
            } catch (err) {
                console.error(`[${name}] Test failed:`, err);
                throw err;
            } finally {
                await browser.close();
            }
        });
    }
});
