<?php

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Mtai\News\Iblock;
use Mtai\News\View;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/**
 * Детальная новость mtai.news.
 *
 * Фиксирует просмотр (b_sonet_user_content_view через Mtai\News\View),
 * подключает лайки (bitrix:rating.vote, шаблон like_react) и штатные
 * комментарии Живой ленты (bitrix:catalog.comments, шаблон stream:
 * упоминания, реакции, вложения, доставка через Push & Pull).
 *
 * Главная проблема, которую решает компонент: автосозданный пост блога
 * комментариев получает права Живой ленты «автор + администраторы соцсети»
 * (U{id}, SA) — обычные сотрудники не видят ни комментариев, ни формы
 * (проверка getSocNetPostPerms в socialnetwork.blog.post.comment). Обработчик
 * OnPostAdd добавляет посту коды доступа из параметра BLOG_POST_RIGHTS
 * (по умолчанию G2 — «все пользователи»). Событие срабатывает внутри
 * CBlogPost::Add до создания записи Живой ленты, права которой потом
 * пересчитываются из b_blog_socnet_rights, поэтому запись сразу получает G2.
 */
class NewsDetail extends CBitrixComponent
{
	protected const DEFAULT_BLOG_URL = 'iblock_comments';
	protected const DEFAULT_BLOG_POST_RIGHTS = 'G2';

	/** @var array Коды прав, которые получают посты блога комментариев */
	protected static array $blogPostRights = [self::DEFAULT_BLOG_POST_RIGHTS];

	/** @var string URL блога, посты которого обрабатывает обработчик прав */
	protected static string $commentsBlogUrl = self::DEFAULT_BLOG_URL;

	protected ?View $view = null;

	public function __construct($component = null)
	{
		parent::__construct($component);
		Loader::includeModule('iblock');
	}

	public function onPrepareComponentParams($arParams)
	{
		$arParams['ID'] = (int)($arParams['ID'] ?? 0);
		$arParams['IBLOCK_ID'] = (int)($arParams['IBLOCK_ID'] ?? 0);
		$arParams['IBLOCK_TYPE'] = trim((string)($arParams['IBLOCK_TYPE'] ?? ''));
		$arParams['BACK_URL'] = trim((string)($arParams['BACK_URL'] ?? '/news/'));
		$arParams['BLOG_URL'] = trim((string)($arParams['BLOG_URL'] ?? self::DEFAULT_BLOG_URL));

		$arParams['BLOG_POST_RIGHTS'] = trim((string)($arParams['BLOG_POST_RIGHTS'] ?? ''));
		if ($arParams['BLOG_POST_RIGHTS'] === '')
		{
			$arParams['BLOG_POST_RIGHTS'] = self::DEFAULT_BLOG_POST_RIGHTS;
		}

		self::$commentsBlogUrl = $arParams['BLOG_URL'];
		self::$blogPostRights = array_values(array_filter(
			array_map('trim', explode(',', $arParams['BLOG_POST_RIGHTS']))
		));

		return $arParams;
	}

	public function executeComponent()
	{
		global $APPLICATION;

		foreach (['socialnetwork', 'mtai.news'] as $module)
		{
			if (!Loader::includeModule($module))
			{
				ShowError('Модуль не установлен: ' . $module);
				return;
			}
		}

		if ($this->arParams['ID'] <= 0 || $this->arParams['IBLOCK_ID'] <= 0)
		{
			ShowError('Не заданы параметры ID и IBLOCK_ID.');
			return;
		}

		$this->registerBlogPostRightsHandler();

		$rs = CIBlockElement::GetList(
			['ID' => 'DESC'],
			[
				'IBLOCK_ID' => $this->arParams['IBLOCK_ID'],
				'ID' => $this->arParams['ID'],
				'ACTIVE' => 'Y',
				'CHECK_PERMISSIONS' => 'Y',
				'MIN_PERMISSION' => 'R',
			],
			false,
			['nTopCount' => 1]
		);

		if (!$ob = $rs->GetNextElement())
		{
			\Bitrix\Iblock\Component\Tools::process404('Новость не найдена', true, 'Y', 'Y');
			return;
		}

		$fields = $ob->GetFields();
		$properties = $ob->GetProperties();

		$id = (int)$fields['ID'];

		$this->arResult = [
			'ID' => $id,
			'NAME' => (string)$fields['NAME'],
			'PREVIEW_TEXT' => (string)$fields['~PREVIEW_TEXT'],
			'DETAIL_TEXT' => (string)$fields['~DETAIL_TEXT'],
			'DETAIL_TEXT_TYPE' => (string)$fields['DETAIL_TEXT_TYPE'],
			'DATE' => $this->formatDate((string)$fields['ACTIVE_FROM']),
			'SECTION_NAME' => $this->getSectionName((int)$fields['IBLOCK_SECTION_ID']),
			'PICTURE' => $this->getImageSrc((int)$fields['DETAIL_PICTURE']),
			'COMMENTS_CNT' => (int)($properties[Iblock::PROP_BLOG_COMMENTS_COUNT]['VALUE'] ?? 0),
			'BACK_URL' => $this->arParams['BACK_URL'],
		];

		// просмотры: фиксируем до вывода счётчика, повторы не растят его
		$this->view = new View();
		$this->view->set($id);
		$viewData = $this->view->getViewData([$id]);
		$this->arResult['CONTENT_ID'] = $this->view->getContentId($id);
		$this->arResult['VIEW_CNT'] = (int)($viewData[$this->arResult['CONTENT_ID']]['CNT'] ?? 0);

		// лайки: состояние кнопки и параметры bitrix:rating.vote
		$ratingResults = CRatings::GetRatingVoteResult('IBLOCK_ELEMENT', [$id]);
		$rating = $ratingResults[$id] ?? [];

		$reaction = mb_strtoupper((string)($rating['USER_REACTION'] ?? ''));
		if ($reaction === '')
		{
			$reaction = 'LIKE';
		}

		$this->arResult['RATING'] = [
			'USER_HAS_VOTED' => ($rating['USER_HAS_VOTED'] ?? 'N') === 'Y',
			'BUTTON_TEXT' => CRatingsComponentsMain::getRatingLikeMessage($reaction),
			'HAS_REACTIONS' => (int)($rating['TOTAL_POSITIVE_VOTES'] ?? 0) > 0,
		];

		$this->arResult['VOTE_PARAMS'] = [
			'ENTITY_TYPE_ID' => 'IBLOCK_ELEMENT',
			'ENTITY_ID' => $id,
			'OWNER_ID' => (int)$fields['CREATED_BY'],
			'PATH_TO_USER_PROFILE' => '/company/personal/user/#USER_ID#/',
			'CURRENT_USER_ID' => (int)CurrentUser::get()->getId(),
			'VOTE_ID' => 'IBLOCK_ELEMENT_' . $id,
			'USER_VOTE' => (int)($rating['USER_VOTE'] ?? 0),
			'USER_HAS_VOTED' => $rating['USER_HAS_VOTED'] ?? 'N',
			'TOTAL_VOTES' => (int)($rating['TOTAL_VOTES'] ?? 0),
			'TOTAL_POSITIVE_VOTES' => (int)($rating['TOTAL_POSITIVE_VOTES'] ?? 0),
			'TOTAL_NEGATIVE_VOTES' => (int)($rating['TOTAL_NEGATIVE_VOTES'] ?? 0),
			'TOTAL_VALUE' => (int)($rating['TOTAL_VALUE'] ?? 0),
			'USER_REACTION' => $rating['USER_REACTION'] ?? '',
			'REACTIONS_LIST' => $rating['REACTIONS_LIST'] ?? [],
			'TOP_DATA' => false,
		];

		$APPLICATION->SetTitle($this->arResult['NAME']);

		$this->includeComponentTemplate();
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

	protected function getSectionName(int $sectionId): string
	{
		if ($sectionId <= 0)
		{
			return '';
		}

		$row = CIBlockSection::GetByID($sectionId)->Fetch();

		return $row ? (string)$row['NAME'] : '';
	}

	protected function getImageSrc(int $pictureId)
	{
		if ($pictureId <= 0)
		{
			return null;
		}

		$file = CFile::GetFileArray($pictureId);

		return $file ? CFile::GetFileSRC($file) : null;
	}

	/**
	 * Обработчик вешается только на посты блога комментариев инфоблоков
	 * (URL блога из параметра BLOG_URL) и выдаёт им коды доступа
	 * BLOG_POST_RIGHTS.
	 */
	protected function registerBlogPostRightsHandler(): void
	{
		static $registered = false;
		if ($registered)
		{
			return;
		}
		$registered = true;

		AddEventHandler('blog', 'OnPostAdd', [self::class, 'onBlogPostAdd']);
	}

	public static function onBlogPostAdd($postId, &$arFields): void
	{
		$postId = (int)$postId;
		$blogId = (int)($arFields['BLOG_ID'] ?? 0);

		if ($postId <= 0 || $blogId <= 0 || empty(self::$blogPostRights))
		{
			return;
		}

		if (!Loader::includeModule('blog'))
		{
			return;
		}

		static $commentsBlogIds = null;
		if ($commentsBlogIds === null)
		{
			$commentsBlogIds = [];
			$rsBlog = CBlog::GetList([], ['URL' => self::$commentsBlogUrl]);
			while ($arBlog = $rsBlog->Fetch())
			{
				$commentsBlogIds[] = (int)$arBlog['ID'];
			}
		}

		if (!in_array($blogId, $commentsBlogIds, true))
		{
			return;
		}

		CBlogPost::AddSocNetPerms($postId, self::$blogPostRights, [
			'AUTHOR_ID' => (int)($arFields['AUTHOR_ID'] ?? 0),
		]);
	}
}
