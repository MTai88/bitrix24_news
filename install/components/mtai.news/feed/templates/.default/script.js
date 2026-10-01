(function () {
	'use strict';

	var root = document.querySelector('[data-mnt-feed]');
	if (!root || root.dataset.mntInited) {
		return;
	}
	root.dataset.mntInited = '1';

	var list = root.querySelector('[data-mnt-list]');
	var sentinel = root.querySelector('[data-mnt-sentinel]');
	var doneNote = root.querySelector('[data-mnt-done]');

	var config = {
		ajaxUrl: root.getAttribute('data-ajax'),
		pageSize: parseInt(root.getAttribute('data-page-size'), 10) || 10,
		section: root.getAttribute('data-section') || '0',
		hasMore: root.getAttribute('data-has-more') === 'Y'
	};

	var state = {
		count: list ? list.querySelectorAll('.mnt-card').length : 0,
		loading: false,
		done: !config.hasMore,
		restoring: false
	};

	function buildUrl(extra) {
		var url = new URL(config.ajaxUrl, window.location.origin);
		url.searchParams.set('category', config.section);
		Object.keys(extra).forEach(function (key) {
			url.searchParams.set(key, extra[key]);
		});
		return url.toString();
	}

	function isSentinelVisible() {
		if (!sentinel) {
			return false;
		}
		var rect = sentinel.getBoundingClientRect();
		return rect.top < window.innerHeight + 600 && rect.bottom > -600;
	}

	/**
	 * innerHTML не исполняет <script>: разбираем фрагмент, переносим узлы в
	 * контейнер, затем заново создаём скрипты — без этого не инициализируются
	 * RatingLike (лайки) и BX.UserContentView (просмотры) у добавленных карточек.
	 */
	function mountCards(html, mode, skip) {
		var box = document.createElement('div');
		box.innerHTML = html;
		skip = skip || 0;

		// продолжение ленты после восстановления: первая страница запроса
		// частично уже показана — отрезаем лишнее начало
		var cards = box.querySelectorAll('.mnt-card');
		for (var i = 0; i < skip && cards.length > 0; i++) {
			cards[0].parentNode.removeChild(cards[0]);
		}

		var scripts = [];
		Array.prototype.forEach.call(box.querySelectorAll('script'), function (oldScript) {
			var script = document.createElement('script');
			for (var i = 0; i < oldScript.attributes.length; i++) {
				script.setAttribute(oldScript.attributes[i].name, oldScript.attributes[i].value);
			}
			script.text = oldScript.text;
			oldScript.parentNode.removeChild(oldScript);
			scripts.push(script);
		});

		if (mode === 'replace' && list) {
			list.innerHTML = '';
		}
		while (list && box.firstChild) {
			list.appendChild(box.firstChild);
		}
		scripts.forEach(function (script) {
			document.body.appendChild(script);
		});

		state.count = list ? list.querySelectorAll('.mnt-card').length : 0;
	}

	function loadNext() {
		if (state.loading || state.done || state.restoring) {
			return;
		}
		state.loading = true;
		if (sentinel) {
			sentinel.classList.add('is-loading');
		}

		var page = Math.floor(state.count / config.pageSize) + 1;
		var skip = state.count - (page - 1) * config.pageSize;

		fetch(buildUrl({ page: page }), { credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json.html) {
					mountCards(json.html, 'append', skip);
				}
				if (!json.hasMore) {
					state.done = true;
					if (sentinel) {
						sentinel.classList.add('is-done');
					}
					if (doneNote) {
						doneNote.hidden = false;
					}
				}
			})
			.catch(function () {
				state.done = true; // сеть недоступна — не зацикливаемся
			})
			.finally(function () {
				state.loading = false;
				if (sentinel) {
					sentinel.classList.remove('is-loading');
				}
				maybeLoadMore();
			});
	}

	/**
	 * IntersectionObserver срабатывает только при изменении пересечения:
	 * сентинел, оставшийся видимым после загрузки страницы, больше не
	 * триггерит — после каждой подгрузки проверяем видимость сами.
	 */
	function maybeLoadMore() {
		if (!state.done && isSentinelVisible()) {
			loadNext();
		}
	}

	if (sentinel) {
		if ('IntersectionObserver' in window) {
			var observer = new IntersectionObserver(function (entries) {
				if (entries.some(function (entry) { return entry.isIntersecting; })) {
					loadNext();
				}
			}, { rootMargin: '600px 0px' });
			observer.observe(sentinel);
		} else {
			window.addEventListener('scroll', maybeLoadMore, { passive: true });
		}
	}

	// --- позиция ленты при возврате по кнопке «Назад» браузера ---

	function saveState(y) {
		try {
			var historyState = window.history.state || {};
			historyState.mntFeed = { y: Math.round(y), count: state.count };
			window.history.replaceState(historyState, '');
		} catch (e) { /* приватный режим и т.п. */ }
	}

	var saveTimer = null;
	window.addEventListener('scroll', function () {
		if (saveTimer) {
			return;
		}
		saveTimer = window.setTimeout(function () {
			saveTimer = null;
			if (!state.restoring) {
				saveState(window.scrollY);
			}
		}, 300);
	}, { passive: true });

	// прямо перед уходом на детальную страницу — точная позиция
	document.addEventListener('click', function () {
		if (!state.restoring) {
			saveState(window.scrollY);
		}
	}, true);

	// возврат с детальной: докатываем ленту до сохранённой глубины одним
	// запросом и возвращаем скролл (кнопка «Назад» браузера)
	var saved = (window.history.state || {}).mntFeed;
	if (saved && saved.count > state.count) {
		state.restoring = true;
		var targetY = saved.y;
		fetch(buildUrl({ limit: Math.min(saved.count, 100) }), { credentials: 'same-origin' })
			.then(function (response) { return response.json(); })
			.then(function (json) {
				if (json.html) {
					mountCards(json.html, 'replace', 0);
				}
				window.scrollTo(0, targetY);
				saveState(targetY);
			})
			.catch(function () { /* остаёмся на первой странице */ })
			.finally(function () {
				state.restoring = false;
				maybeLoadMore();
			});
	} else {
		maybeLoadMore();
	}
})();
