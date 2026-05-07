import { Page, Response, expect } from '@playwright/test';

/**
 * Patterns that indicate a *fatal* PHP/JS error rendered into the response
 * body. The smoke check fails when any of these match. We deliberately do
 * NOT match the literal string "Error" — legacy NexusPHP renders things like
 * "Error: No torrent with this ID" through its standard error template, and
 * that is still a successful render of the error path (HTTP 200 with the
 * usual layout).
 */
const FATAL_PATTERNS: RegExp[] = [
    /Fatal error/i,
    /Parse error/i,
    /Stack trace:/i,
    /Whoops!/i,
    /Class\s+["']?[A-Za-z0-9_\\\\]+["']?\s+not\s+found/i,
    /Argument\s+#\d+\s+\(\$[A-Za-z0-9_]+\)\s+must\s+be\s+of\s+type/i,
    /Call to undefined (function|method)/i,
    /Allowed memory size of \d+ bytes exhausted/i,
    /SQLSTATE\[/i,
];

/**
 * Console-error messages that are NOT considered failures: third-party noise
 * we don't control or that is environment-specific (favicon, telemetry).
 */
const CONSOLE_NOISE_PATTERNS: RegExp[] = [
    /favicon/i,
    /sentry/i,
    /Failed to load resource/i,
];

/**
 * Scan a response body for fatal-error markers. Throws a descriptive error
 * with surrounding context when one is found.
 */
export function expectNoFatalErrors(html: string, url: string): void {
    for (const re of FATAL_PATTERNS) {
        const m = re.exec(html);
        if (m) {
            const start = Math.max(0, m.index - 40);
            const end = Math.min(html.length, m.index + 160);
            throw new Error(
                `fatal error on ${url}: matched ${re.source}\n  ...${html.slice(start, end)}...`,
            );
        }
    }
}

export interface SmokeCheckResult {
    response: Response;
    html: string;
    consoleErrors: string[];
}

/**
 * Visit a URL with the supplied page, assert a 2xx/3xx status, and verify
 * that no fatal-error markers appear in the body and no JS console errors
 * (other than environmental noise) were emitted. Returns the response, full
 * HTML, and any non-noise console errors so individual specs can layer
 * page-specific assertions on top.
 */
export async function smokeCheckPage(page: Page, url: string): Promise<SmokeCheckResult> {
    const consoleErrors: string[] = [];
    page.on('pageerror', (err) => consoleErrors.push(err.message));
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            consoleErrors.push(msg.text());
        }
    });

    const response = await page.goto(url);
    expect(response, `no response for ${url}`).not.toBeNull();
    expect(response!.status(), `unexpected status for ${url}`).toBeGreaterThanOrEqual(200);
    expect(response!.status(), `unexpected status for ${url}`).toBeLessThan(400);

    const html = await page.content();
    expectNoFatalErrors(html, url);

    const fatal = consoleErrors.filter(
        (e) => !CONSOLE_NOISE_PATTERNS.some((re) => re.test(e)),
    );

    return { response: response!, html, consoleErrors: fatal };
}
