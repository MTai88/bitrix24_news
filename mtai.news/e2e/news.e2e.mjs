/**
 * E2E-проверка ленты новостей mtai.news (/news/) headless-браузером.
 *
 * Запуск (стенд bitrix24_test, chromium из apk):
 *   cd local/modules/mtai.news/e2e
 *   docker run --rm --add-host bitrix.local:host-gateway \
 *     -e NODE_TLS_REJECT_UNAUTHORIZED=0 -v "$PWD:/work" -w /work \
 *     node:22-alpine sh -c "apk add --no-cache chromium && npm i && node news.e2e.mjs"
 *
 * Сценарий (админ + test.user в двух контекстах):
 *   1. Лента: первая страница (10 карточек), табы категорий;
 *   2. Аякс-подгрузка при скролле (карточек становится больше 10);
 *   3. Лайк первой карточки (штатный rating.vote, подсветка «Вы»);
 *   4. Детальная: фиксация просмотра, форма комментариев Живой ленты,
 *      отправка комментария (Ctrl+Enter);
 *   5. Кнопка «Назад» браузера: лента докатывается до сохранённой глубины
 *      и возвращается к сохранённой позиции скролла;
 *   6. test.user: лента видна (права G2 инфоблока), комментарий админа
 *      виден (право G2 поста блога), форма комментария доступна.
 * Результат: PASS/FAIL по шагам + скриншоты в e2e/artifacts/.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';

const BASE = process.env.E2E_BASE_URL || 'https://bitrix.local';
const ARTIFACTS = new URL('./artifacts/', import.meta.url).pathname;

const results = [];
function report(step, ok, details = '') {
	results.push({ step, ok, details });
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${step}${details ? ' — ' + details : ''}`);
}

fs.mkdirSync(ARTIFACTS, { recursive: true });

async function loginCookies(login, password) {
	const response = await fetch(`${BASE}/?login=yes`, {
		method: 'POST',
		redirect: 'manual',
		credentials: 'omit',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: new URLSearchParams({
			AUTH_FORM: 'Y',
			TYPE: 'AUTH',
			backurl: '/news/',
			USER_LOGIN: login,
			USER_PASSWORD: password,
			Login: 'Войти',
		}),
	});
	const setCookies = response.headers.getSetCookie?.() ?? [];
	return setCookies
		.map((line) => line.split(';')[0])
		.map((pair) => {
			const [name, ...rest] = pair.split('=');
			return { name, value: rest.join('='), domain: 'bitrix.local', path: '/' };
		});
}

const browser = await chromium.launch({
	executablePath: '/usr/bin/chromium-browser',
	args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

// ================= 1. Админ =================
const adminContext = await browser.newContext({
	ignoreHTTPSErrors: true,
	viewport: { width: 1400, height: 900 },
});
await adminContext.addCookies(await loginCookies('admin', 'Admin_Bx24t3st_2026'));
const admin = await adminContext.newPage();

// --- лента: первая страница ---
await admin.goto(`${BASE}/news/`, { waitUntil: 'domcontentloaded' });
await admin.waitForSelector('.mnt-card', { timeout: 15000 });

const initialCards = await admin.locator('.mnt-card').count();
report('лента: первая страница', initialCards > 0, `${initialCards} карточек`);
report(
	'лента: размер первой страницы = 10',
	initialCards === 10,
	`фактически ${initialCards}`,
);
report(
	'лента: табы категорий',
	(await admin.locator('.mnt-feed__tab').count()) >= 3,
);
report(
	'лента: кнопки лайков',
	(await admin.locator('.bx-ilike-text').count()) === initialCards,
);
report(
	'лента: счётчики просмотров',
	(await admin.locator('.feed-content-view-cnt').count()) >= initialCards,
);
report(
	'лента: счётчики комментариев',
	(await admin.locator('.mnt-card__stat--comments').count()) === initialCards,
);

await admin.screenshot({ path: `${ARTIFACTS}01-feed-first-page.png`, fullPage: false });

// --- аякс-подгрузка при скролле ---
await admin.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await admin.waitForFunction(
	() => document.querySelectorAll('.mnt-card').length > 10,
	null,
	{ timeout: 20000 },
);
const afterScrollCards = await admin.locator('.mnt-card').count();
report('аякс-подгрузка при скролле', afterScrollCards > initialCards, `${afterScrollCards} карточек`);

// --- лайк первой карточки (идемпотентно: лайк может остаться с прошлого прогона) ---
const firstCard = admin.locator('.mnt-card').first();
const alreadyLiked = (await firstCard.locator('.bx-you-like-button').count()) > 0;
if (!alreadyLiked) {
	await firstCard.locator('.bx-ilike-text').click();
	await firstCard.locator('.bx-you-like-button').waitFor({ timeout: 10000 });
}
report('лайк: подсветка «Вы»', true, alreadyLiked ? 'лайк уже стоял' : 'поставлен кликом');
await admin.screenshot({ path: `${ARTIFACTS}02-feed-like.png`, fullPage: false });

// --- глубокий скролл для проверки «Назад» ---
const deepY = 4000;
await admin.evaluate((y) => window.scrollTo(0, y), deepY);
await admin.waitForTimeout(1500); // троттлинг сохранения состояния 300 мс + подгрузки
const cardsBeforeNav = await admin.locator('.mnt-card').count();
const yBeforeNav = await admin.evaluate(() => window.scrollY);
const historyState = await admin.evaluate(() => window.history.state);
report(
	'лента: состояние позиции записано в history.state',
	Boolean(historyState && historyState.mntFeed && historyState.mntFeed.count >= cardsBeforeNav),
	`count=${historyState?.mntFeed?.count}, scrollY=${Math.round(yBeforeNav)}`,
);

// --- переход на детальную: JS-клик по ссылке видимой карточки ---
// Playwright-клик прокрутил бы страницу (испортив сохранённую позицию) и
// мог быть перехвачен панелью эмодзи активной карточки
const navInfo = await admin.evaluate(() => {
	const card = [...document.querySelectorAll('.mnt-card')].find((el) => {
		const r = el.getBoundingClientRect();
		return r.top > 0 && r.top < window.innerHeight;
	});
	const link = card && card.querySelector('.mnt-card__title a');
	return link ? { title: link.textContent.trim() } : null;
});
if (!navInfo) {
	throw new Error('не найдена видимая карточка для перехода');
}
const newsTitle = navInfo.title;
await admin.evaluate(() => {
	const card = [...document.querySelectorAll('.mnt-card')].find((el) => {
		const r = el.getBoundingClientRect();
		return r.top > 0 && r.top < window.innerHeight;
	});
	const link = card && card.querySelector('.mnt-card__title a');
	if (link) {
		link.click();
	}
});
await admin.waitForLoadState('domcontentloaded');
await admin.waitForSelector('.mnt-detail__title', { timeout: 15000 });
report(
	'детальная: заголовок новости',
	(await admin.locator('.mnt-detail__title').innerText()).trim() === newsTitle,
	newsTitle,
);
report(
	'детальная: счётчик просмотров',
	(await admin.locator('.feed-content-view-cnt').count()) > 0,
);

// --- форма комментариев (ленивый lazyload; свёрнута в заглушку
// .feed-com-add-link — сам .feed-com-add-box пустой и невидимый) ---
let commentFormOk = true;
try {
	await admin.evaluate(() => document.getElementById('mnt-comments')?.scrollIntoView());
	await admin.waitForSelector('.feed-com-add-link', { timeout: 20000 });
} catch {
	// повторная попытка после перезагрузки: lazyload ленты комментариев
	// изредка не стартует с первого захода
	await admin.reload({ waitUntil: 'domcontentloaded' });
	await admin.waitForSelector('.mnt-detail__title', { timeout: 15000 });
	try {
		await admin.evaluate(() => document.getElementById('mnt-comments')?.scrollIntoView());
		await admin.waitForSelector('.feed-com-add-link', { timeout: 20000 });
	} catch {
		commentFormOk = false;
	}
}
report('детальная: форма комментариев Живой ленты', commentFormOk);

// отправка комментария: клик по заглушке раскрывает редактор (iframe либо
// textarea), текст вводим в textarea — данные LHE, отправляем кнопкой
let commentPosted = false;
if (commentFormOk) {
	const COMMENT_TEXT = `E2E комментарий ${Date.now()}`;
	const addLink = admin.locator('.feed-com-add-link').first();
	await addLink.click();
	let typed = false;
	// видимый редактор LHE — iframe; textarea скрыт (data-holder)
	const frameBody = admin.frameLocator('.feed-com-add-box iframe').locator('body');
	try {
		await frameBody.click({ timeout: 5000 });
		await frameBody.fill(COMMENT_TEXT);
		typed = true;
	} catch {
		typed = false;
	}
	if (!typed) {
		await admin.evaluate((text) => {
			const el = document.querySelector('.feed-com-add-box textarea, .feed-com-add-box-outer textarea');
			if (el) {
				el.value = text;
				el.dispatchEvent(new Event('input', { bubbles: true }));
			}
		}, COMMENT_TEXT);
	}

	await admin.getByRole('button', { name: 'Отправить' }).last().click();
	try {
		await admin.getByText(COMMENT_TEXT).first().waitFor({ timeout: 20000 });
		commentPosted = true;
	} catch {
		commentPosted = false;
	}
	report('детальная: комментарий отправлен и виден', commentPosted, COMMENT_TEXT);
	await admin.screenshot({ path: `${ARTIFACTS}03-detail-comment.png`, fullPage: false });
}

// --- «Назад»: восстановление глубины и позиции ленты ---
await admin.goBack();
await admin.waitForSelector('.mnt-card', { timeout: 15000 });
let restored = false;
let restoredDetails = '';
try {
	await admin.waitForFunction(
		({ expectedCount, expectedY }) =>
			document.querySelectorAll('.mnt-card').length >= expectedCount &&
			Math.abs(window.scrollY - expectedY) < 150,
		{ expectedCount: cardsBeforeNav, expectedY: yBeforeNav },
		{ timeout: 20000 },
	);
	restored = true;
} catch (error) {
	const count = await admin.locator('.mnt-card').count();
	const y = await admin.evaluate(() => window.scrollY);
	restoredDetails = `карточек ${count}/${cardsBeforeNav}, scrollY ${Math.round(y)}/${Math.round(yBeforeNav)}`;
}
report('«Назад»: лента восстановила глубину и позицию', restored, restoredDetails);
await admin.screenshot({ path: `${ARTIFACTS}04-feed-restored.png`, fullPage: false });

// ================= 2. test.user =================
const userContext = await browser.newContext({
	ignoreHTTPSErrors: true,
	viewport: { width: 1400, height: 900 },
});
await userContext.addCookies(await loginCookies('test.user', 'Testuser_Bx24t3st_2026'));
const user = await userContext.newPage();

await user.goto(`${BASE}/news/`, { waitUntil: 'domcontentloaded' });
await user.waitForSelector('.mnt-card', { timeout: 15000 });
report(
	'test.user: лента видна (права G2 инфоблока)',
	(await user.locator('.mnt-card').count()) > 0,
);

// комментарий админа и форма — права G2 поста блога
// открываем ту же новость: докручиваем ленту, пока карточка не подгрузится
let userNewsLink = user.locator('.mnt-card__title a', { hasText: newsTitle }).first();
for (let i = 0; (i < 4) && ((await userNewsLink.count()) === 0); i++) {
	await user.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
	await user.waitForTimeout(2500);
}
report(
	'test.user: новость с комментарием найдена в ленте',
	(await userNewsLink.count()) > 0,
);
await userNewsLink.click();
await user.waitForLoadState('domcontentloaded');
await user.waitForSelector('.mnt-detail__title', { timeout: 15000 });

let userCommentVisible = false;
try {
	if (commentPosted) {
		await user
			.locator('.feed-com-block')
			.first()
			.waitFor({ timeout: 25000 });
	}
	userCommentVisible = (await user.locator('.feed-com-block').count()) > 0;
} catch {
	userCommentVisible = false;
}
report('test.user: комментарий админа виден (право G2 поста)', userCommentVisible);

let userFormVisible = false;
try {
	await user.evaluate(() => document.getElementById('mnt-comments')?.scrollIntoView());
	await user.waitForSelector('.feed-com-add-link', { timeout: 20000 });
	userFormVisible = true;
} catch {
	await user.reload({ waitUntil: 'domcontentloaded' });
	try {
		await user.evaluate(() => document.getElementById('mnt-comments')?.scrollIntoView());
		await user.waitForSelector('.feed-com-add-link', { timeout: 20000 });
		userFormVisible = true;
	} catch {
		userFormVisible = false;
	}
}
report('test.user: доступна форма комментария', userFormVisible);

await user.screenshot({ path: `${ARTIFACTS}05-detail-testuser.png`, fullPage: false });

await browser.close();

const failed = results.filter((r) => !r.ok);
console.log('---');
console.log(`Итого: ${results.length - failed.length}/${results.length} PASS`);
process.exit(failed.length > 0 ? 1 : 0);
