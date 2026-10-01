<?php

namespace Mtai\News;

use Bitrix\Main\Loader;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UrlRewriter;
use CIBlock;
use CIBlockType;
use RuntimeException;

/**
 * Инфоблок новостей модуля: тип mtai_news + инфоблок «Новости» (code mtai_news).
 *
 * Разделы инфоблока — категории новостей, элементы — сами новости.
 * Инфоблок создаётся сразу с расширенным управлением прав (RIGHTS_MODE=E):
 * администраторы портала (группа 1) — полный доступ (X), все пользователи
 * (группа 2) — чтение (R). Дальше права настраиваются в админке, в том числе
 * по разделам и элементам.
 *
 * Свойства BLOG_POST_ID / BLOG_COMMENTS_COUNT не создаются установщиком:
 * их заводит штатный bitrix:catalog.comments при первом открытии детальной
 * страницы (CIBlockPropertyTools::CODE_BLOG_POST / CODE_BLOG_COMMENTS_COUNT).
 */
class Iblock
{
	/** Собственный тип инфоблоков модуля. */
	public const TYPE = 'mtai_news';

	/** Символьный код инфоблока новостей. */
	public const CODE = 'mtai_news';

	/** Публичный раздел новостей. */
	public const PUBLIC_PATH = '/news/';

	/** Свойство элемента: ID поста блога комментариев (заводит catalog.comments). */
	public const PROP_BLOG_POST_ID = 'BLOG_POST_ID';

	/**
	 * Свойство элемента: счётчик комментариев (заводит catalog.comments).
	 * Внимание: у CIBlockPropertyTools константа называется
	 * CODE_BLOG_COMMENTS_COUNT, но её значение — BLOG_COMMENTS_CNT.
	 */
	public const PROP_BLOG_COMMENTS_COUNT = 'BLOG_COMMENTS_CNT';

	/** @return int ID инфоблока новостей, 0 если ещё не создан */
	public static function getIblockId(): int
	{
		// CIBlock не автозагружается: без includeModule — «Class CIBlock not found»
		Loader::includeModule('iblock');

		$row = CIBlock::GetList(
			[],
			[
				'TYPE' => self::TYPE,
				'CODE' => self::CODE,
				'CHECK_PERMISSIONS' => 'N',
			]
		)->Fetch();

		return $row ? (int)$row['ID'] : 0;
	}

	/**
	 * Создаёт тип инфоблоков и инфоблок новостей. Повторный вызов безопасен:
	 * существующий инфоблок переиспользуется, настроенные вручную права
	 * не перезаписываются.
	 *
	 * @return int ID инфоблока новостей
	 * @throws RuntimeException
	 */
	public static function install(): int
	{
		$iblockId = self::getIblockId();
		if ($iblockId > 0)
		{
			return $iblockId;
		}

		$rsType = CIBlockType::GetList([], ['=ID' => self::TYPE]);
		if (!$rsType->Fetch())
		{
			$obType = new CIBlockType();
			$ok = $obType->Add([
				'ID' => self::TYPE,
				'SECTIONS' => 'Y',
				'SORT' => 95,
				'LANG' => [
					'ru' => [
						'NAME' => 'Новости (mtai.news)',
						'SECTION_NAME' => 'Категория новостей',
						'ELEMENT_NAME' => 'Новость',
					],
					'en' => [
						'NAME' => 'News (mtai.news)',
						'SECTION_NAME' => 'News category',
						'ELEMENT_NAME' => 'News item',
					],
				],
			]);
			if (!$ok)
			{
				throw new RuntimeException('iblock type: ' . $obType->LAST_ERROR);
			}
		}

		$siteIds = [];
		$rsSites = SiteTable::getList(['select' => ['LID'], 'filter' => ['=ACTIVE' => 'Y']]);
		while ($site = $rsSites->fetch())
		{
			$siteIds[] = $site['LID'];
		}
		if (!$siteIds)
		{
			$siteIds = ['s1'];
		}

		$obIblock = new CIBlock();
		$iblockId = (int)$obIblock->Add([
			'ACTIVE' => 'Y',
			'NAME' => 'Новости (mtai.news)',
			'CODE' => self::CODE,
			'IBLOCK_TYPE_ID' => self::TYPE,
			'SITE_ID' => $siteIds,
			'SORT' => 100,
			// 1 = администраторы портала, 2 = все пользователи (читают новости;
			// редактирование выдаётся в админке). RIGHTS_MODE=E: расширенное
			// управление правами, ядро само конвертирует GROUP_ID в права при
			// создании (ConvertGroups + SetRights).
			'RIGHTS_MODE' => 'E',
			'GROUP_ID' => [1 => 'X', 2 => 'R'],
			'WORKFLOW' => 'N',
			'LIST_PAGE_URL' => '#SITE_DIR#news/',
			'SECTION_PAGE_URL' => '#SITE_DIR#news/?category=#SECTION_ID#',
			'DETAIL_PAGE_URL' => '#SITE_DIR#news/#ID#/',
			'INDEX_ELEMENT' => 'N',
			'INDEX_SECTION' => 'N',
			'FIELDS' => [
				'PREVIEW_TEXT' => ['IS_REQUIRED' => 'N', 'USE_EDITOR' => 'N', 'DEFAULT_VALUE' => ''],
				'PREVIEW_TEXT_TYPE' => ['DEFAULT_VALUE' => 'text'],
				'DETAIL_TEXT' => ['IS_REQUIRED' => 'N', 'USE_EDITOR' => 'Y', 'DEFAULT_VALUE' => ''],
				'DETAIL_TEXT_TYPE' => ['DEFAULT_VALUE' => 'text'],
				// у новости без даты активности она ставится автоматически
				'ACTIVE_FROM' => ['DEFAULT_VALUE' => '=now'],
			],
		]);
		if ($iblockId <= 0)
		{
			throw new RuntimeException('iblock: ' . $obIblock->LAST_ERROR);
		}

		return $iblockId;
	}

	/**
	 * Правило urlrewrite для детальных страниц: /news/{id}/ → /news/detail.php?ID={id}.
	 * Добавляется только если его ещё нет.
	 */
	public static function ensureDetailUrlRewrite(): void
	{
		$condition = '#^/news/([0-9]+)/?$#';
		$path = self::PUBLIC_PATH . 'detail.php';

		foreach (UrlRewriter::getList(SITE_ID) as $rule)
		{
			if (($rule['CONDITION'] ?? '') === $condition && ($rule['PATH'] ?? '') === $path)
			{
				return;
			}
		}

		UrlRewriter::add(SITE_ID, [
			'CONDITION' => $condition,
			'RULE' => 'ID=$1',
			'ID' => null,
			'PATH' => $path,
			'SORT' => 90,
		]);
	}

	/**
	 * Удаляет инфоблок новостей вместе с содержимым и тип инфоблоков модуля.
	 */
	public static function uninstall(): void
	{
		$iblockId = self::getIblockId();
		if ($iblockId > 0)
		{
			\CIBlockSection::DeleteAll($iblockId);
			\CIBlockElement::DeleteAll($iblockId);
			CIBlock::Delete($iblockId);
		}

		$rsType = CIBlockType::GetList([], ['=ID' => self::TYPE]);
		if ($rsType->Fetch())
		{
			CIBlockType::Delete(self::TYPE);
		}
	}
}
