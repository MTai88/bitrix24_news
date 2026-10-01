<?php
/**
 * Аякс-эндпоинт ленты новостей: /news/ajax.php
 *
 * Отдаёт частичный рендер mtai.news:feed (шаблон page — только карточки)
 * в JSON {html, hasMore}:
 *   ?page=N&category=SECTION_ID — следующая страница ленты (подгрузка при скролле);
 *   ?limit=N&category=SECTION_ID — первые N карточек одним запросом
 *     (восстановление глубины ленты при возврате по кнопке «Назад»).
 *
 * Права: учитываются права инфоблока (CHECK_PERMISSIONS=Y в компоненте),
 * доступ к /news/ выдан группе 2 (все пользователи, включая гостей).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\Web\Json;

header('Content-Type: application/json; charset=' . LANG_CHARSET);

$respond = static function (array $data): void {
	echo Json::encode($data);
	die();
};

if (!Loader::includeModule('iblock')
	|| !Loader::includeModule('socialnetwork')
	|| !Loader::includeModule('mtai.news'))
{
	$respond(['html' => '', 'hasMore' => false, 'error' => 'module']);
}

$iblockId = \Mtai\News\Iblock::getIblockId();
if ($iblockId <= 0)
{
	$respond(['html' => '', 'hasMore' => false, 'error' => 'iblock']);
}

ob_start();
$GLOBALS['MNT_FEED_HAS_MORE'] = false;
$APPLICATION->IncludeComponent(
	'mtai.news:feed',
	'',
	[
		'IBLOCK_ID' => $iblockId,
		'PARTIAL' => 'Y',
		'PAGE_SIZE' => 10,
		'PAGE' => (int)($_REQUEST['page'] ?? 1),
		'LIMIT' => (int)($_REQUEST['limit'] ?? 0),
		'SECTION_ID' => (int)($_REQUEST['category'] ?? 0),
		'DETAIL_URL' => '/news/#ID#/',
	],
	false,
	['HIDE_ICONS' => 'Y']
);
$html = ob_get_clean();

$respond([
	'html' => $html,
	'hasMore' => (bool)$GLOBALS['MNT_FEED_HAS_MORE'],
]);
