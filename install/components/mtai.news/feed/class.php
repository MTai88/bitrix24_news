<?php

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Mtai\News\View;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Лента новостей mtai.news.
 *
 * Карточка новости: лайки-реакции живой ленты (bitrix:rating.vote, шаблон
 * like_react), счётчик просмотров (bitrix:socialnetwork.contentview.count,
 * данные из b_sonet_user_content_view) и число комментариев (свойство
 * BLOG_COMMENTS_COUNT, его ведёт bitrix:catalog.comments на детальной).
 *
 * Подгрузка при скролле: шаблон page (только карточки) рендерится отдельно
 * через публичный скрипт /news/ajax.php (параметр AJAX_MODE=Y) и возвращается
 * в JSON {html, hasMore}. Список выбирается с CHECK_PERMISSIONS=Y — работают
 * расширенные права инфоблока.
 */
class NewsFeed extends CBitrixComponent
{
	public const DEFAULT_PAGE_SIZE = 10;

	/** Максимум карточек в одном ajax-ответе (восстановление ленты по «Назад»). */
	public const MAX_LIMIT = 100;

	protected int $total = 0;
	protected bool $hasMore = false;

	protected ?View $view = null;

	public function onPrepareComponentParams($arParams)
	{
		$arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
		$arParams['SECTION_ID'] = (int)($arParams['SECTION_ID'] ?? 0);
		if ($arParams['SECTION_ID'] <= 0 && isset($_REQUEST['category']))
		{
			$arParams['SECTION_ID'] = (int)$_REQUEST['category'];
		}

		$pageSize = (int)($arParams['PAGE_SIZE'] ?? 0);
		$arParams['PAGE_SIZE'] = $pageSize > 0 ? $pageSize : self::DEFAULT_PAGE_SIZE;

		$arParams['PAGE'] = max(1, (int)($arParams['PAGE'] ?? 1));

		$arParams['LIMIT'] = (int)($arParams['LIMIT'] ?? 0);
		if ($arParams['LIMIT'] > self::MAX_LIMIT)
		{
			$arParams['LIMIT'] = self::MAX_LIMIT;
		}

		// частичный рендер (шаблон page — только карточки) для /news/ajax.php.
		// Не называть AJAX_MODE: это штатный параметр ядра, включающий
		// компонентную ajax-обёртку (comp_*, bxajaxid) вокруг вывода
		$arParams['PARTIAL'] = ($arParams['PARTIAL'] ?? 'N') === 'Y' ? 'Y' : 'N';
		$arParams['DETAIL_URL'] = trim((string)($arParams['DETAIL_URL'] ?? '/news/#ID#/'));

		return $arParams;
	}

	public function executeComponent()
	{
		foreach (['iblock', 'socialnetwork', 'mtai.news'] as $module)
		{
			if (!Loader::includeModule($module))
			{
				ShowError('Модуль не установлен: ' . $module);
				return;
			}
		}

		if ($this->arParams['IBLOCK_ID'] <= 0)
		{
			ShowError('Не задан инфоблок новостей (параметр IBLOCK_ID).');
			return;
		}

		$this->arResult['SECTIONS'] = $this->getSections();
		$this->arResult['SECTION_ID'] = $this->arParams['SECTION_ID'];
		$this->arResult['ITEMS'] = $this->getItems();
		$this->arResult['TOTAL'] = $this->total;
		$this->arResult['HAS_MORE'] = $this->hasMore;
		$this->arResult['CURRENT_USER_ID'] = (int)CurrentUser::get()->getId();

		// флаг для /news/ajax.php: сколько карточек реально отдано
		$GLOBALS['MNT_FEED_HAS_MORE'] = $this->hasMore;

		$this->includeComponentTemplate($this->arParams['PARTIAL'] === 'Y' ? 'page' : '');
	}

	/**
	 * Разделы инфоблока — категории новостей: для табов фильтра и подписей
	 * на карточках.
	 */
	protected function getSections(): array
	{
		$sections = [];
		$rs = CIBlockSection::GetList(
			['SORT' => 'ASC', 'NAME' => 'ASC'],
			[
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
				'ACTIVE' => 'Y',
				'CHECK_PERMISSIONS' => 'Y',
				'MIN_PERMISSION' => 'R',
			],
			false,
			['ID', 'NAME', 'CODE']
		);
		while ($row = $rs->Fetch())
		{
			$row['ID'] = (int)$row['ID'];
			$row['URL'] = '?category=' . $row['ID'];
			$sections[$row['ID']] = $row;
		}

		return $sections;
	}

	protected function getItems(): array
	{
		$filter = [
			'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
			'ACTIVE' => 'Y',
			'CHECK_PERMISSIONS' => 'Y',
			'MIN_PERMISSION' => 'R',
		];
		if ($this->arParams['SECTION_ID'] > 0)
		{
			$filter['SECTION_ID'] = $this->arParams['SECTION_ID'];
		}

		// LIMIT > 0 — восстановление ленты: первые N карточек одним запросом.
		$limit = $this->arParams['LIMIT'] > 0 ? $this->arParams['LIMIT'] : $this->arParams['PAGE_SIZE'];
		$page = $this->arParams['LIMIT'] > 0 ? 1 : $this->arParams['PAGE'];

		$rs = CIBlockElement::GetList(
			['ACTIVE_FROM' => 'DESC', 'ID' => 'DESC'],
			$filter,
			false,
			['nPageSize' => $limit, 'iNumPage' => $page],
			[
				'ID', 'NAME', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DETAIL_PICTURE',
				'ACTIVE_FROM', 'CREATED_BY', 'IBLOCK_SECTION_ID',
				'PROPERTY_BLOG_COMMENTS_CNT',
			]
		);

		$this->total = (int)$rs->NavRecordCount;
		$offset = ($page - 1) * $limit;

		$items = [];
		while ($row = $rs->GetNextElement())
		{
			$fields = $row->GetFields();
			$id = (int)$fields['ID'];

			$items[$id] = [
				'ID' => $id,
				'NAME' => (string)$fields['NAME'],
				'PREVIEW_TEXT' => (string)$fields['~PREVIEW_TEXT'],
				'PREVIEW_PICTURE' => (int)$fields['PREVIEW_PICTURE'],
				'DETAIL_URL' => str_replace('#ID#', (string)$id, $this->arParams['DETAIL_URL']),
				'DATE' => $this->formatDate((string)$fields['ACTIVE_FROM']),
				'CREATED_BY' => (int)$fields['CREATED_BY'],
				'SECTION_ID' => (int)$fields['IBLOCK_SECTION_ID'],
				'SECTION_NAME' => '',
				'COMMENTS_CNT' => (int)($fields['PROPERTY_BLOG_COMMENTS_CNT_VALUE'] ?? 0),
				'THUMB' => null,
				'CONTENT_ID' => '',
				'VIEW_CNT' => 0,
				'VOTE_ID' => 'IBLOCK_ELEMENT_' . $id,
				'RATING' => [
					'USER_HAS_VOTED' => false,
					'BUTTON_TEXT' => 'Нравится',
					'HAS_REACTIONS' => false,
				],
				'VOTE_PARAMS' => [],
			];
		}

		$this->hasMore = count($items) > 0 && $offset + count($items) < $this->total;

		$this->prepareSections($items);
		$this->preparePictures($items);
		$this->prepareRating($items);
		$this->prepareViews($items);

		return array_values($items);
	}

	protected function formatDate(string $formattedDate): string
	{
		$timestamp = strtotime($formattedDate);
		if ($timestamp <= 0)
		{
			return '';
		}

		return FormatDate('j F Y', $timestamp);
	}

	protected function prepareSections(array &$items): void
	{
		foreach ($items as &$item)
		{
			$section = $this->arResult['SECTIONS'][$item['SECTION_ID']] ?? null;
			$item['SECTION_NAME'] = $section['NAME'] ?? '';
		}
		unset($item);
	}

	protected function preparePictures(array &$items): void
	{
		foreach ($items as &$item)
		{
			if ($item['PREVIEW_PICTURE'] <= 0)
			{
				continue;
			}
			$thumb = CFile::ResizeImageGet(
				$item['PREVIEW_PICTURE'],
				['width' => 640, 'height' => 360],
				BX_RESIZE_IMAGE_EXACT,
				true
			);
			$item['THUMB'] = $thumb['src'] ?? null;
		}
		unset($item);
	}

	/**
	 * Реакции пачкой из стандартных таблиц рейтингов: состояние кнопки
	 * и параметры подключения bitrix:rating.vote для каждой карточки.
	 */
	protected function prepareRating(array &$items): void
	{
		if (empty($items))
		{
			return;
		}

		$ids = array_keys($items);

		$ratingResults = CRatings::GetRatingVoteResult('IBLOCK_ELEMENT', $ids);
		$topRatingData = CRatings::getEntityRatingData([
			'entityTypeId' => 'IBLOCK_ELEMENT',
			'entityId' => $ids,
		]);

		$currentUserId = (int)CurrentUser::get()->getId();

		foreach ($items as &$item)
		{
			$rating = $ratingResults[$item['ID']] ?? [];

			$reaction = mb_strtoupper((string)($rating['USER_REACTION'] ?? ''));
			if ($reaction === '')
			{
				$reaction = 'LIKE';
			}

			$item['RATING'] = [
				'USER_HAS_VOTED' => ($rating['USER_HAS_VOTED'] ?? 'N') === 'Y',
				'USER_REACTION' => $reaction,
				'BUTTON_TEXT' => CRatingsComponentsMain::getRatingLikeMessage($reaction),
				'HAS_REACTIONS' => (int)($rating['TOTAL_POSITIVE_VOTES'] ?? 0) > 0,
			];

			$item['VOTE_PARAMS'] = [
				'ENTITY_TYPE_ID' => 'IBLOCK_ELEMENT',
				'ENTITY_ID' => $item['ID'],
				'OWNER_ID' => $item['CREATED_BY'],
				'PATH_TO_USER_PROFILE' => '/company/personal/user/#USER_ID#/',
				'CURRENT_USER_ID' => $currentUserId,
				'VOTE_ID' => $item['VOTE_ID'],
				'USER_VOTE' => (int)($rating['USER_VOTE'] ?? 0),
				'USER_HAS_VOTED' => $rating['USER_HAS_VOTED'] ?? 'N',
				'TOTAL_VOTES' => (int)($rating['TOTAL_VOTES'] ?? 0),
				'TOTAL_POSITIVE_VOTES' => (int)($rating['TOTAL_POSITIVE_VOTES'] ?? 0),
				'TOTAL_NEGATIVE_VOTES' => (int)($rating['TOTAL_NEGATIVE_VOTES'] ?? 0),
				'TOTAL_VALUE' => (int)($rating['TOTAL_VALUE'] ?? 0),
				'USER_REACTION' => $rating['USER_REACTION'] ?? '',
				'REACTIONS_LIST' => $rating['REACTIONS_LIST'] ?? [],
				'TOP_DATA' => $topRatingData[$item['ID']] ?? false,
			];
		}
		unset($item);
	}

	protected function prepareViews(array &$items): void
	{
		if (empty($items))
		{
			return;
		}

		$this->view = new View();
		$viewData = $this->view->getViewData(array_keys($items));

		foreach ($items as &$item)
		{
			$item['CONTENT_ID'] = $this->view->getContentId($item['ID']);
			$item['VIEW_CNT'] = (int)($viewData[$item['CONTENT_ID']]['CNT'] ?? 0);
		}
		unset($item);
	}
}
