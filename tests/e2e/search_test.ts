import { chromium, firefox } from "playwright";
import { assert } from "@std/assert";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";

const BROWSERS = [
    { name: "chromium", launcher: chromium },
    { name: "firefox", launcher: firefox },
];

for (const { name, launcher } of BROWSERS) {
    Deno.test({
        name: `Search Functionality Tests (${name})`,
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
                console.log(`[${name}] BROWSER LOG: ${msg.text()}`),
            );
            page.on("pageerror", (err: any) =>
                console.log(`[${name}] BROWSER ERROR: ${err.message}`),
            );

            // Track HTTP errors
            const httpErrors: { url: string; status: number }[] = [];
            page.on("response", (response: any) => {
                if (response.status() >= 400) {
                    httpErrors.push({
                        url: response.url(),
                        status: response.status(),
                    });
                    console.log(
                        `[${name}] HTTP ${response.status()}: ${response.url()}`,
                    );
                }
            });

            await t.step("First search works correctly", async () => {
                // Navigate to home page
                await page.goto(`${BASE_URL}/admin/home`);

                // Wait for the page to load
                const heading = page.locator("h1");
                await heading.waitFor({ state: "visible", timeout: 10000 });
                console.log(`[${name}] Home page loaded`);

                // Find the search input
                const searchInput = page.locator('input[type="search"]');
                await searchInput.waitFor({ state: "visible", timeout: 5000 });
                console.log(`[${name}] Search input found`);

                // Perform first search
                await searchInput.fill("test");
                await searchInput.press("Enter");

                // Wait for navigation/reload
                await page.waitForLoadState("networkidle");
                console.log(`[${name}] First search completed`);

                // Verify URL contains search parameter
                const url1 = page.url();
                console.log(`[${name}] URL after first search: ${url1}`);
                assert(
                    url1.includes("search=test"),
                    "URL should contain search parameter after first search",
                );
            });

            await t.step("Second search works correctly", async () => {
                // Clear any previous errors for this step
                const errorCountBefore = httpErrors.length;

                // Find the search input again
                const searchInput = page.locator('input[type="search"]');
                await searchInput.waitFor({ state: "visible", timeout: 5000 });

                // Perform second search
                await searchInput.fill("video");
                await searchInput.press("Enter");

                // Wait for navigation/reload
                await page.waitForLoadState("networkidle");
                console.log(`[${name}] Second search completed`);

                // Check for 500 errors
                const newErrors = httpErrors.slice(errorCountBefore);
                const has500 = newErrors.some((e) => e.status === 500);
                if (has500) {
                    console.log(
                        `[${name}] 500 ERROR DETECTED on second search!`,
                    );
                    console.log(`[${name}] Errors:`, newErrors);
                }

                // Verify URL contains new search parameter
                const url2 = page.url();
                console.log(`[${name}] URL after second search: ${url2}`);
                assert(
                    url2.includes("search=video"),
                    "URL should contain updated search parameter after second search",
                );

                // Assert no 500 errors
                assert(
                    !has500,
                    `Second search should not produce 500 error. Errors: ${JSON.stringify(newErrors)}`,
                );
            });

            await t.step("Third search works correctly", async () => {
                // Clear any previous errors for this step
                const errorCountBefore = httpErrors.length;

                // Find the search input again
                const searchInput = page.locator('input[type="search"]');
                await searchInput.waitFor({ state: "visible", timeout: 5000 });

                // Perform third search
                await searchInput.fill("media");
                await searchInput.press("Enter");

                // Wait for navigation/reload
                await page.waitForLoadState("networkidle");
                console.log(`[${name}] Third search completed`);

                // Check for 500 errors
                const newErrors = httpErrors.slice(errorCountBefore);
                const has500 = newErrors.some((e) => e.status === 500);
                if (has500) {
                    console.log(
                        `[${name}] 500 ERROR DETECTED on third search!`,
                    );
                    console.log(`[${name}] Errors:`, newErrors);
                }

                // Verify URL contains new search parameter
                const url3 = page.url();
                console.log(`[${name}] URL after third search: ${url3}`);
                assert(
                    url3.includes("search=media"),
                    "URL should contain updated search parameter after third search",
                );

                // Assert no 500 errors
                assert(
                    !has500,
                    `Third search should not produce 500 error. Errors: ${JSON.stringify(newErrors)}`,
                );
            });

            await t.step("Fourth search with empty query works", async () => {
                // Clear any previous errors for this step
                const errorCountBefore = httpErrors.length;

                // Find the search input again
                const searchInput = page.locator('input[type="search"]');
                await searchInput.waitFor({ state: "visible", timeout: 5000 });

                // Perform search with empty string (clear search)
                await searchInput.fill("");
                await searchInput.press("Enter");

                // Wait for navigation/reload
                await page.waitForLoadState("networkidle");
                console.log(`[${name}] Fourth search (empty) completed`);

                // Check for 500 errors
                const newErrors = httpErrors.slice(errorCountBefore);
                const has500 = newErrors.some((e) => e.status === 500);
                if (has500) {
                    console.log(
                        `[${name}] 500 ERROR DETECTED on fourth search!`,
                    );
                    console.log(`[${name}] Errors:`, newErrors);
                }

                // Assert no 500 errors
                assert(
                    !has500,
                    `Fourth search should not produce 500 error. Errors: ${JSON.stringify(newErrors)}`,
                );
            });

            // Cleanup
            await context.close();
            await browser.close();

            // Final summary
            console.log(
                `[${name}] Total HTTP errors encountered: ${httpErrors.length}`,
            );
            if (httpErrors.length > 0) {
                console.log(`[${name}] All errors:`, httpErrors);
            }
        },
    });
}
