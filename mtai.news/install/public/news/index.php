<?php
/**
 * Публичная страница ленты новостей: /news/
 * Компонент mtai.news:feed — карточки с лайками, просмотрами и числом
 * комментариев, аякс-подгрузка при скролле (/news/ajax.php), фильтр по
 * категориям (разделы инфоблока, ?category=SECTION_ID).
 *
 * Детальные страницы: /news/{ID}/ (правило urlrewrite → detail.php?ID=$1).
 */

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/header.php");

$APPLICATION->SetTitle("Новости");

if (\Bitrix\Main\Loader::includeModule('mtai.news')) {
	$APPLICATION->IncludeComponent(
		"mtai.news:feed",
		"",
		[
			"IBLOCK_ID" => \Mtai\News\Iblock::getIblockId(),
			"PAGE_SIZE" => "10",
			"DETAIL_URL" => "/news/#ID#/",
			"CACHE_TYPE" => "N",
		],
		false
	);
} else {
	echo '<p>Модуль mtai.news не установлен. Запустите установку: '
		. '<code>php local/tools/news_module_install.php</code> в php-контейнере.</p>';
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/footer.php");
