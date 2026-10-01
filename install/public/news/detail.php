<?php
/**
 * Детальная страница новости: /news/{ID}/
 * Попадаем сюда через правило urlrewrite:
 *   "#^/news/([0-9]+)/?$" → /news/detail.php?ID=$1
 *
 * Компонент mtai.news:detail — текст новости, лайки, счётчик просмотров
 * (UserContentView) и комментарии Живой ленты (bitrix:catalog.comments,
 * шаблон stream; посты блога получают право G2 через обработчик OnPostAdd).
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

if (\Bitrix\Main\Loader::includeModule('mtai.news')) {
	$APPLICATION->IncludeComponent(
		"mtai.news:detail",
		"",
		[
			"ID" => (int)($_REQUEST["ID"] ?? 0),
			"IBLOCK_ID" => \Mtai\News\Iblock::getIblockId(),
			"IBLOCK_TYPE" => \Mtai\News\Iblock::TYPE,
			"BACK_URL" => "/news/",
			"BLOG_URL" => "iblock_comments",
			"BLOG_POST_RIGHTS" => "G2",
			"CACHE_TYPE" => "N",
		],
		false
	);
} else {
	echo '<p>Модуль mtai.news не установлен. Запустите установку: '
		. '<code>php local/tools/news_module_install.php</code> в php-контейнере.</p>';
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
