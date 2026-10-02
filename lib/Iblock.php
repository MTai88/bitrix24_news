<?php

namespace Mtai\News;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\SiteTable;
use Bitrix\Main\UrlRewriter;
use CIBlock;
use CIBlockType;
use RuntimeException;

/**
 * Инфоблок новостей модуля: тип news + инфоблок «Новости» (code news).
 *
 * Разделы инфоблока — категории новостей, элементы — сами новости.
 * Семантика переиспользования: если тип/инфоблок news уже есть (заведены
 * руками или другим решением) — модуль использует их, ничего не удаляя;
 * если нет — создаёт. Существующий инфоблок в простом режиме прав
 * переводится в расширенный (RIGHTS_MODE=E: G1→X, G2→R), уже настроенные
 * права при этом не трогаются.
 *
 * Что модуль создал сам, записывается в опции модуля: uninstall удаляет
 * только созданное им, чужие тип/инфоблок остаются.
 *
 * Свойства BLOG_POST_ID / BLOG_COMMENTS_CNT не создаются установщиком:
 * их заводит штатный bitrix:catalog.comments при первом открытии детальной
 * страницы (CIBlockPropertyTools::CODE_BLOG_POST / CODE_BLOG_COMMENTS_COUNT —
 * значение последней константы именно BLOG_COMMENTS_CNT).
 */
class Iblock
{
	/** Тип инфоблоков новостей. */
	public const TYPE = 'news';

	/** Символьный код инфоблока новостей. */
	public const CODE = 'news';

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

	private const OPTION_CREATED_IBLOCK = 'created_iblock';
	private const OPTION_CREATED_TYPE = 'created_type';

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
	 * Обеспечивает тип news + инфоблок news. Повторный вызов безопасен:
	 * существующий тип и инфоблок переиспользуются, настроенные вручную
	 * права не перезаписываются.
	 *
	 * @return int ID инфоблока новостей
	 * @throws RuntimeException
	 */
	public static function install(): int
	{
		Loader::includeModule('iblock');

		$iblockId = self::getIblockId();
		self::ensureType();

		if ($iblockId > 0)
		{
			self::enableExtendedRights($iblockId);
			Option::set('mtai.news', self::OPTION_CREATED_IBLOCK, 'N');
			Option::set('mtai.news', self::OPTION_CREATED_TYPE, 'N');

			return $iblockId;
		}

		$iblockId = self::createIblock();
		Option::set('mtai.news', self::OPTION_CREATED_IBLOCK, 'Y');
		// тип могли создать здесь же (при пустом портале)
		$rsType = CIBlockType::GetList([], ['=ID' => self::TYPE]);
		Option::set('mtai.news', self::OPTION_CREATED_TYPE, $rsType->Fetch() ? 'N' : 'Y');

		return $iblockId;
	}

	/**
	 * Тип news: создаётся только если его нет нигде на портале.
	 * @throws RuntimeException
	 */
	protected static function ensureType(): void
	{
		$rsType = CIBlockType::GetList([], ['=ID' => self::TYPE]);
		if ($rsType->Fetch())
		{
			return;
		}

		$obType = new CIBlockType();
		$ok = $obType->Add([
			'ID' => self::TYPE,
			'SECTIONS' => 'Y',
			'SORT' => 90,
			'LANG' => [
				'ru' => [
					'NAME' => 'Новости',
					'SECTION_NAME' => 'Раздел',
					'ELEMENT_NAME' => 'Новость',
				],
				'en' => [
					'NAME' => 'News',
					'SECTION_NAME' => 'Section',
					'ELEMENT_NAME' => 'News item',
				],
			],
		]);
		if (!$ok)
		{
			throw new RuntimeException('iblock type: ' . $obType->LAST_ERROR);
		}
	}

	/**
	 * @throws RuntimeException
	 */
	protected static function createIblock(): int
	{
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
			'NAME' => 'Новости',
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
	 * Переводит инфоблок в режим расширенных прав, если он ещё в простом.
	 * Уже настроенные права при этом не трогаются: конвертация GROUP_ID
	 * выполняется ядром только при смене режима.
	 *
	 * @param int $iblockId ID инфоблока новостей
	 */
	public static function enableExtendedRights(int $iblockId): void
	{
		$current = CIBlock::GetArrayByID($iblockId);
		if (!$current || ($current['RIGHTS_MODE'] ?? 'S') === 'E')
		{
			return;
		}

		$obIblock = new CIBlock();
		$obIblock->Update($iblockId, [
			'RIGHTS_MODE' => 'E',
			'GROUP_ID' => [1 => 'X', 2 => 'R'],
		]);

		\CIBlock::clearIblockTagCache($iblockId);
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
	 * Удаляет тип и инфоблок новостей только если они были созданы модулем.
	 * Переиспользованные (заведённые до установки) не трогаются.
	 */
	public static function uninstall(): void
	{
		Loader::includeModule('iblock');

		if (Option::get('mtai.news', self::OPTION_CREATED_IBLOCK, 'N') === 'Y')
		{
			$iblockId = self::getIblockId();
			if ($iblockId > 0)
			{
				// полный каскад: элементы, разделы, права, свойства;
				// CIBlockElement/CIBlockSection::DeleteAll на этой сборке нет
				CIBlock::Delete($iblockId);
			}
		}

		if (Option::get('mtai.news', self::OPTION_CREATED_TYPE, 'N') === 'Y')
		{
			$rsType = CIBlockType::GetList([], ['=ID' => self::TYPE]);
			if ($rsType->Fetch())
			{
				CIBlockType::Delete(self::TYPE);
			}
		}

		Option::delete('mtai.news', self::OPTION_CREATED_IBLOCK);
		Option::delete('mtai.news', self::OPTION_CREATED_TYPE);
	}
}
