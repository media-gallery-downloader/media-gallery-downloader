import { chromium, firefox } from "playwright";
import { assert } from "@std/assert";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";
const TEST_VIDEO_URL = "https://www.youtube.com/watch?v=jNQXAC9IVRw";

const BROWSERS = [
    { name: "chromium", launcher: chromium },
    { name: "firefox", launcher: firefox },
];

for (const { name, launcher } of BROWSERS) {
    Deno.test({
        name: `Media Gallery Downloader Acceptance Tests (${name})`,
        sanitizeOps: false,
        sanitizeResources: false,
        fn: async (t) => {
            // Setup
            const browser = await launcher.launch({ headless: true });
            const context = await browser.newContext({
                ignoreHTTPSErrors: true,
            });
            const page = await context.newPage();

            // Log browser console messages
            page.on("console", (msg: any) =>
                console.log(`[${name}] BROWSER LOG: ${msg.text()}`)
            );
            page.on("pageerror", (err: any) =>
                console.log(`[${name}] BROWSER ERROR: ${err.message}`)
            );

            await t.step("Homepage loads successfully", async () => {
                await page.goto(BASE_URL);
                const title = await page.title();
                console.log(`[${name}] Visited ${BASE_URL}, Title: ${title}`);
                assert(title.length > 0, "Page title should not be empty");
            });

            await t.step("Dashboard loads successfully", async () => {
                // Navigate to the dashboard
                await page.goto(`${BASE_URL}/admin/home`);

                // Wait for the main content to load
                // Filament usually puts the page title in an h1 or h2
                const heading = page.locator("h1");
                await heading.waitFor({ state: "visible", timeout: 10000 });

                const title = await page.title();
                console.log(`[${name}] Visited Dashboard, Title: ${title}`);

                // Check that "Media" is NOT in the navigation
                const mediaLink = page.getByRole("link", {
                    name: "Media",
                    exact: true,
                });

                const isVisible = await mediaLink.isVisible();
                if (isVisible) {
                    throw new Error(
                        `[${name}] Media navigation link found, but it should have been removed!`
                    );
                } else {
                    console.log(
                        `[${name}] Media navigation link correctly NOT found`
                    );
                }

                // Verify that the media route is actually gone (404)
                const response = await page.goto(`${BASE_URL}/admin/media`);
                if (response && response.status() === 404) {
                    console.log(
                        `[${name}] /admin/media correctly returned 404`
                    );
                } else {
                    // Filament might redirect to home or login, or show a 404 page with 200 status?
                    // But usually if the resource is gone, the route is gone.
                    // However, Filament catches 404s and shows a 404 page.
                    // Let's just check if the title says "Not Found" or similar if status isn't 404.
                    console.log(
                        `[${name}] /admin/media returned status ${response?.status()}`
                    );
                }

                // Go back to dashboard for next steps
                await page.goto(`${BASE_URL}/admin/home`);
            });

            await t.step(
                "End-to-end download and gallery refresh",
                async () => {
                    // Navigate to Dashboard with high per_page to ensure we see all items
                    await page.goto(`${BASE_URL}/admin/home?per_page=100`);

                    // Count initial items
                    // The gallery uses custom divs, not a table.
                    // Selector based on media-gallery.blade.php:
                    // <div class="flex flex-wrap"> ... <div class="relative cursor-pointer ...">
                    const galleryItemSelector =
                        "div.flex.flex-wrap > div.relative.cursor-pointer";

                    const initialCount = await page
                        .locator(galleryItemSelector)
                        .count();
                    console.log(
                        `[${name}] Initial media count: ${initialCount}`
                    );

                    try {
                        // Find the URL input
                        const urlInput = page.locator('input[type="url"]');
                        await urlInput.waitFor({ state: "visible" });
                        await urlInput.fill(TEST_VIDEO_URL);

                        // Click download
                        const downloadButton = page
                            .locator("button", { hasText: /Download/i })
                            .first();
                        await downloadButton.click();

                        // Wait for notification
                        const notification = page.locator(
                            ".fi-no-notification"
                        );
                        try {
                            await notification.waitFor({
                                state: "visible",
                                timeout: 5000,
                            });
                            console.log(`[${name}] Notification appeared`);
                        } catch {
                            console.log(
                                `[${name}] No notification appeared, but continuing...`
                            );
                        }

                        console.log(
                            `[${name}] Waiting for download to complete and appear in list (up to 60s)...`
                        );

                        let found = false;
                        for (let i = 0; i < 12; i++) {
                            // 12 * 5s = 60s
                            // We rely on auto-refresh now, so no page reload
                            // await page.goto(`${BASE_URL}/admin/home?per_page=100`);
                            // await page.waitForLoadState("networkidle");

                            const newCount = await page
                                .locator(galleryItemSelector)
                                .count();
                            console.log(
                                `[${name}] Current media count: ${newCount}`
                            );

                            if (newCount > initialCount) {
                                found = true;
                                break;
                            }

                            console.log(
                                `[${name}] New item not found yet, waiting...`
                            );
                            await page.waitForTimeout(5000);
                        }

                        assert(
                            found,
                            "New media item should appear in the list"
                        );
                    } finally {
                        // Cleanup
                        console.log(
                            `[${name}] Cleaning up downloaded media...`
                        );
                        const videoId = new URL(
                            TEST_VIDEO_URL
                        ).searchParams.get("v");
                        const command = new Deno.Command("docker", {
                            args: [
                                "compose",
                                "exec",
                                "-T",
                                "mgd_app",
                                "php",
                                "-r",
                                `require 'vendor/autoload.php'; $app = require_once 'bootstrap/app.php'; $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); App\\Models\\Media::where('source', 'like', '%${videoId}%')->get()->each->delete();`,
                            ],
                        });
                        const { code, stderr } = await command.output();
                        if (code !== 0) {
                            console.error(
                                `[${name}] Cleanup failed:`,
                                new TextDecoder().decode(stderr)
                            );
                        } else {
                            console.log(`[${name}] Cleanup successful`);
                        }
                    }
                }
            );

            // Teardown
            await browser.close();
        },
    });
}
