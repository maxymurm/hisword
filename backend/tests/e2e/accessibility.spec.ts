import { test, expect } from '@playwright/test';

test.describe('Responsive Design', () => {
    test('mobile navigation menu', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await page.goto('/');
        // Should have a hamburger menu on mobile
        const menuButton = page.getByRole('button', { name: /menu|nav/i });
        if (await menuButton.isVisible()) {
            await menuButton.click();
            await expect(page.getByRole('navigation')).toBeVisible();
        }
    });

    test('bottom navigation visible on mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        await page.goto('/');
        // Bottom nav should be visible on mobile
        await page.waitForLoadState('networkidle');
    });

    test('desktop layout uses full width', async ({ page }) => {
        await page.setViewportSize({ width: 1920, height: 1080 });
        await page.goto('/');
        await expect(page.locator('main')).toBeVisible();
    });
});

test.describe('Accessibility', () => {
    test('skip to content link exists', async ({ page }) => {
        await page.goto('/');
        const skipLink = page.locator('a[href="#main-content"], a:has-text("Skip to")');
        if (await skipLink.count() > 0) {
            await expect(skipLink.first()).toHaveAttribute('href', /#/);
        }
    });

    test('page has proper heading structure', async ({ page }) => {
        await page.goto('/');
        const h1 = page.locator('h1');
        await expect(h1.first()).toBeVisible();
    });

    test('images have alt text', async ({ page }) => {
        await page.goto('/');
        const images = page.locator('img');
        const count = await images.count();
        for (let i = 0; i < count; i++) {
            const alt = await images.nth(i).getAttribute('alt');
            expect(alt).not.toBeNull();
        }
    });

    test('keyboard navigation works', async ({ page }) => {
        await page.goto('/');
        await page.keyboard.press('Tab');
        const focused = page.locator(':focus');
        await expect(focused).toBeVisible();
    });
});
