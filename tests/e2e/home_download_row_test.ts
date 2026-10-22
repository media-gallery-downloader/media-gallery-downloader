import { chromium, firefox } from 'playwright';
import { assert, assertEquals } from '@std/assert';

const BASE_URL = Deno.env.get('APP_URL') || 'https://mgd.localhost';

// The Home page's download row: URL field, gear (one-off yt-dlp arguments)
// and Start Download.
// On phones the button gets its own row below the URL field and gear.
const VIEWPORTS = [
    { name: 'desktop', width: 1280, height: 800, buttonOnSameRow: true },
    { name: 'phone', width: 390, height: 844, buttonOnSameRow: false },
];

Deno.test('Home download row', async (t) => {
    for (const browserType of [chromium, firefox]) {
        const name = browserType.name();

        await t.step(`Running in ${name}`, async (t) => {
            const browser = await browserType.launch({ headless: true });

            try {
                for (const viewport of VIEWPORTS) {
                    await t.step(
                        `the row is vertically centred on the URL field (${viewport.name})`,
                        async () => {
                            const page = await browser.newPage({
                                ignoreHTTPSErrors: true,
                                viewport: {
                                    width: viewport.width,
                                    height: viewport.height,
                                },
                            });
                            await page.goto(`${BASE_URL}/admin/home`);

                            const url = page.getByPlaceholder(
                                'Enter video URL',
                            );
                            const gear = page.getByRole('button', {
                                name:
                                    'Extra yt-dlp arguments for this download',
                            });
                            const start = page.getByRole('button', {
                                name: 'Start Download',
                            });
                            const field = await url.boundingBox();
                            const middle = field && field.y + field.height / 2;
                            assert(middle !== null, 'the URL field is visible');

                            const checks = viewport.buttonOnSameRow
                                ? [['gear', gear], [
                                    'Start Download button',
                                    start,
                                ]] as const
                                : [['gear', gear]] as const;
                            for (const [label, locator] of checks) {
                                const box = await locator.boundingBox();
                                assert(box, `the ${label} is visible`);
                                const offset = Math.abs(
                                    middle - (box.y + box.height / 2),
                                );
                                assert(
                                    offset <= 1.5,
                                    `the ${label} is ${
                                        offset.toFixed(1)
                                    }px off the URL field's centre`,
                                );
                            }
                            await page.close();
                        },
                    );
                }

                await t.step(
                    'Enter in the URL field starts the download',
                    async () => {
                        const page = await browser.newPage({
                            ignoreHTTPSErrors: true,
                        });
                        await page.goto(`${BASE_URL}/admin/home`);
                        const url = page.getByPlaceholder('Enter video URL');

                        await url.fill('not a url');
                        await url.press('Enter');
                        await page.getByText('Please enter a valid URL')
                            .waitFor({ timeout: 5000 });

                        // A real URL gets queued; example.com has no media, so the
                        // download fails harmlessly (it shows up on the Logs page).
                        await url.fill('https://example.com/mgd-e2e-enter-key');
                        await url.press('Enter');
                        await page.getByText('Added to download queue').waitFor(
                            { timeout: 5000 },
                        );
                        assertEquals(await url.inputValue(), '');
                        await page.close();
                    },
                );
            } finally {
                await browser.close();
            }
        });
    }
});
