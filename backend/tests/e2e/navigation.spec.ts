import { test, expect } from '@playwright/test';

test.describe('Reader Page', () => {
    test('reader page loads', async ({ page }) => {
        await page.goto('/reader');
        await expect(page.locator('main')).toBeVisible();
    });

    test('module selector is present', async ({ page }) => {
        await page.goto('/reader');
        // Reader should have a module/book selector
        await expect(page.locator('select, [role="listbox"], [role="combobox"]').first()).toBeVisible();
    });
});

test.describe('Search Page', () => {
    test('search page loads', async ({ page }) => {
        await page.goto('/search');
        await expect(page.locator('input[type="text"], input[type="search"]').first()).toBeVisible();
    });

    test('search form accepts input', async ({ page }) => {
        await page.goto('/search');
        const searchInput = page.locator('input[type="text"], input[type="search"]').first();
        await searchInput.fill('love');
        await expect(searchInput).toHaveValue('love');
    });
});

test.describe('Modules Page', () => {
    test('modules page loads', async ({ page }) => {
        await page.goto('/modules');
        await expect(page.locator('main')).toBeVisible();
    });
});

test.describe('Cross References Page', () => {
    test('cross references page loads', async ({ page }) => {
        await page.goto('/cross-references');
        await expect(page.locator('main')).toBeVisible();
    });
});

test.describe('History Page (requires auth)', () => {
    test('redirects to login when not authenticated', async ({ page }) => {
        await page.goto('/history');
        await page.waitForLoadState('networkidle');
        // Should redirect to login
        await expect(page.url()).toMatch(/login/);
    });
});
