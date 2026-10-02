import { chromium } from 'playwright-core';
const browser = await chromium.launch({ executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1400, height: 1000 } });
const login = await fetch('https://bitrix.local/?login=yes', {
	method: 'POST', redirect: 'manual', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
	body: new URLSearchParams({ AUTH_FORM: 'Y', TYPE: 'AUTH', backurl: '/news/', USER_LOGIN: 'admin', USER_PASSWORD: 'Admin_Bx24t3st_2026', Login: 'Войти' }),
});
const cookies = (login.headers.getSetCookie?.() ?? []).map(l => l.split(';')[0]).map(p => { const i = p.indexOf('='); return { name: p.slice(0, i), value: p.slice(i + 1), domain: 'bitrix.local', path: '/' }; });
await context.addCookies(cookies);
const page = await context.newPage();
await page.goto('https://bitrix.local/news/1448/', { waitUntil: 'domcontentloaded' });
await page.waitForSelector('.feed-com-add-link', { timeout: 30000 });
await page.waitForTimeout(2000);
const xs = await page.evaluate(() => ({
	heading: Math.round(document.querySelector('.mnt-detail__comments-title').getBoundingClientRect().x),
	commentBlock: Math.round(document.querySelector('.feed-comments-block').getBoundingClientRect().x),
	addBox: Math.round(document.querySelector('.feed-com-add-box').getBoundingClientRect().x),
}));
console.log('x-координаты:', JSON.stringify(xs));
await page.screenshot({ path: 'e2e/artifacts/06-detail-comments-aligned.png', fullPage: true });
await browser.close();
