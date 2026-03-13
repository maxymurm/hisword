import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
    test('register page loads', async ({ page }) => {
        await page.goto('/register');
        await expect(page.getByRole('heading', { name: /register|sign up|create/i })).toBeVisible();
        await expect(page.locator('input[name="name"]')).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
    });

    test('login page loads', async ({ page }) => {
        await page.goto('/login');
        await expect(page.getByRole('heading', { name: /log in|sign in/i })).toBeVisible();
        await expect(page.locator('input[name="email"]')).toBeVisible();
        await expect(page.locator('input[name="password"]')).toBeVisible();
    });

    test('login with invalid credentials shows error', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'invalid@test.com');
        await page.fill('input[name="password"]', 'wrongpassword');
        await page.getByRole('button', { name: /log in|sign in/i }).click();
        await page.waitForLoadState('networkidle');
        // Should show validation error
        await expect(page.locator('.text-red-500, .text-red-600, [class*="error"]')).toBeVisible();
    });

    test('register and login flow', async ({ page }) => {
        const email = `test-${Date.now()}@example.com`;
        await page.goto('/register');
        await page.fill('input[name="name"]', 'Test User');
        await page.fill('input[name="email"]', email);
        await page.fill('input[name="password"]', 'Password123!');
        await page.fill('input[name="password_confirmation"]', 'Password123!');
        await page.getByRole('button', { name: /register|sign up|create/i }).click();
        await page.waitForLoadState('networkidle');
        // Should redirect to home or dashboard
        await expect(page.url()).not.toContain('/register');
    });
});
