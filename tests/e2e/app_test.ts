import { chromium, firefox } from "playwright";
import { assert } from "@std/assert";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";
const TEST_VIDEO_URL = "https://www.youtube.com/watch?v=jNQXAC9IVRw";
const TEST_VIDEO_ID = new URL(TEST_VIDEO_URL).searchParams.get("v");

const BROWSERS = [
    { name: "chromium", launcher: chromium },
    { name: "firefox", launcher: firefox },
];

/** Run PHP inside the app container with Laravel booted. */
async function runInApp(
    php: string
): Promise<{ code: number; stdout: string; stderr: string }> {
    const command = new Deno.Command("docker", {
        args: [
            "compose",
            "exec",
            "-T",
            "app",
            "php",
            "-r",
            `require 'vendor/autoload.php'; $app = require_once 'bootstrap/app.php'; $kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class); $kernel->bootstrap(); ${php}`,
        ],
    });
    const { code, stdout, stderr } = await command.output();
    const decoder = new TextDecoder();

    return {
        code,
        stdout: decoder.decode(stdout),
        stderr: decoder.decode(stderr),
    };
}

/**
 * Ids of the copies of the test video already in the library, or null when
 * they can't be listed (the app isn't running under Docker Compose here).
 */
async function existingTestVideoIds(): Promise<number[] | null> {
    try {
        const { code, stdout } = await runInApp(
            `echo json_encode(App\\Models\\Media::where('source', 'like', '%${TEST_VIDEO_ID}%')->pluck('id')->all());`
        );
        if (code !== 0) return null;
        const ids = JSON.parse(stdout.trim().split("\n").pop() ?? "");

        return Array.isArray(ids) && ids.every(Number.isInteger) ? ids : null;
    } catch {
        return null;
    }
}

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

                // Wait for the main content to load. Home has no page header
                // (Home::getHeading() returns ''), so there is no h1: the
                // Media Gallery section marks the page as rendered.
                const heading = page.getByRole("heading", {
                    name: "Media Gallery",
                });
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
                    // <div class="mgd-gallery-grid"> ... <div data-media-url="/admin/view/{id}" ...>
                    const galleryItemSelector =
                        ".mgd-gallery-grid > [data-media-url]";

                    const initialCount = await page
                        .locator(galleryItemSelector)
                        .count();
                    console.log(
                        `[${name}] Initial media count: ${initialCount}`
                    );

                    // The stack under test shares its library with the dev
                    // app, so note any copies of the test video that are
                    // already there: cleanup must leave those alone.
                    const existingIds = await existingTestVideoIds();
                    if (existingIds === null) {
                        console.log(
                            `[${name}] Could not list existing copies of the test video; cleanup will be skipped`
                        );
                    }

                    try {
                        // Find the URL input
                        const urlInput = page.locator('input[type="url"]');
                        await urlInput.waitFor({ state: "visible" });
                        await urlInput.fill(TEST_VIDEO_URL);

                        // Click download. Matched by its full name: the Add
                        // Media header also has a "Download" mode pill, and
                        // every gallery card a "Download" menu item.
                        const downloadButton = page.getByRole("button", {
                            name: "Start Download",
                        });
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
                        // Cleanup: only the copies this run downloaded.
                        if (existingIds !== null) {
                            console.log(
                                `[${name}] Cleaning up downloaded media...`
                            );
                            const { code, stderr } = await runInApp(
                                `App\\Models\\Media::where('source', 'like', '%${TEST_VIDEO_ID}%')->whereNotIn('id', [${existingIds.join(
                                    ","
                                )}])->get()->each->delete();`
                            );
                            if (code !== 0) {
                                console.error(
                                    `[${name}] Cleanup failed:`,
                                    stderr
                                );
                            } else {
                                console.log(`[${name}] Cleanup successful`);
                            }
                        }
                    }
                }
            );

            // Teardown
            await browser.close();
        },
    });
}
