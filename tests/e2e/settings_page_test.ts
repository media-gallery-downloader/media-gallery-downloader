import { chromium } from "playwright";

const BASE_URL = Deno.env.get("APP_URL") || "https://mgd.localhost";

Deno.test("Settings page loads without errors", async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ ignoreHTTPSErrors: true });
    const page = await context.newPage();

    try {
        const response = await page.goto(`${BASE_URL}/admin/settings`, {
            waitUntil: "networkidle",
            timeout: 30000,
        });

        const status = response?.status() ?? 0;
        if (status >= 400) {
            throw new Error(`Page returned error status ${status}`);
        }
        
        console.log("Settings page loaded successfully");
    } finally {
        await browser.close();
    }
});
