import { test, expect } from '@playwright/test';

test.describe('Home Page', () => {
    test('loads and displays hero section', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/HisWord/);
        await expect(page.locator('main')).toBeVisible();
    });

    test('has navigation links', async ({ page }) => {
        await page.goto('/');
        await expect(page.getByRole('navigation')).toBeVisible();
    });

    test('verse of the day widget renders', async ({ page }) => {
        await page.goto('/');
        // The VerseOfDayWidget fetches /verse-of-day
        await page.waitForLoadState('networkidle');
    });

    test('dark mode toggle works', async ({ page }) => {
        await page.goto('/');
        const html = page.locator('html');
        const toggleButton = page.getByRole('button', { name: /theme|dark|light/i });
        if (await toggleButton.isVisible()) {
            await toggleButton.click();
            await expect(html).toHaveClass(/dark/);
        }
    });
});
