import { chromium } from 'playwright-core';
const browser = await chromium.launch({ executablePath: '/usr/bin/chromium-browser', args: ['--no-sandbox', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ ignoreHTTPSErrors: true });
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
const rows = await page.evaluate(() => {
	const root = document.querySelector('.mnt-detail__comments');
	const heading = root.querySelector('.mnt-detail__comments-title');
	const out = [{ el: 'H2 (заголовок)', x: Math.round(heading.getBoundingClientRect().x), ml: '', pl: '' }];
	// все предки add-link до root
	let el = root.querySelector('.feed-com-add-link');
	const chain = [];
	while (el && el !== root) { chain.unshift(el); el = el.parentElement; }
	for (const node of chain) {
		const cs = getComputedStyle(node);
		out.push({
			el: node.tagName + '.' + String(node.className).split(' ').slice(0, 2).join('.'),
			x: Math.round(node.getBoundingClientRect().x),
			ml: cs.marginLeft, pl: cs.paddingLeft,
		});
	}
	// плюс сам блок списка комментариев
	const list = root.querySelector('.feed-comments-block');
	if (list) {
		const cs = getComputedStyle(list);
		out.push({ el: 'LIST feed-comments-block', x: Math.round(list.getBoundingClientRect().x), ml: cs.marginLeft, pl: cs.paddingLeft });
	}
	return out;
});
console.log(JSON.stringify(rows, null, 1));
await browser.close();
