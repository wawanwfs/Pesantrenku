import { expect } from '@playwright/test';

export function installCspGuard(test) {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      window.__cspViolations = [];
      document.addEventListener('securitypolicyviolation', (event) => {
        window.__cspViolations.push({
          directive: event.effectiveDirective,
          blockedURI: event.blockedURI,
          sourceFile: event.sourceFile,
          lineNumber: event.lineNumber,
        });
      });
    });
  });

  test.afterEach(async ({ page }) => {
    if (page.isClosed()) return;
    const violations = await page.evaluate(() => window.__cspViolations || []).catch(() => []);
    expect(violations, JSON.stringify(violations, null, 2)).toEqual([]);
  });
}
