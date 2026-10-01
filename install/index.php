<?php
/**
 * Installer of the module mtai.news.
 *
 * On install:
 *   1. infoblock type mtai_news + infoblock «Новости» with extended rights
 *      (RIGHTS_MODE=E): sections are news categories, elements are news items;
 *   2. public pages /news/ (feed, detail, ajax partial endpoint);
 *   3. components mtai.news:feed, mtai.news:detail → local/components/mtai.news/;
 *   4. template "stream" for bitrix:catalog.comments → local/templates/.default/;
 *   5. urlrewrite rule /news/{id}/ → /news/detail.php?ID={id};
 *   6. file access to /news/ for group 2 (all users, incl. guests).
 *
 * On uninstall all of the above are removed, including news items and categories.
 */

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Mtai\News\Iblock;

// модуль ещё не зарегистрирован — автозагрузка lib/ недоступна
require_once __DIR__ . '/../lib/Iblock.php';

Loc::loadMessages(__FILE__);

class mtai_news extends CModule
{
	public $MODULE_ID = 'mtai.news';
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_GROUP_RIGHTS = 'N';

	public function __construct()
	{
		$arModuleVersion = [];
		include __DIR__ . '/version.php';

		$this->MODULE_VERSION = (string)($arModuleVersion['VERSION'] ?? '');
		$this->MODULE_VERSION_DATE = (string)($arModuleVersion['VERSION_DATE'] ?? '');

		$this->MODULE_NAME = Loc::getMessage('MTAI_NEWS_MODULE_NAME') ?: 'Новости (mtai.news)';
		$this->MODULE_DESCRIPTION = Loc::getMessage('MTAI_NEWS_MODULE_DESCRIPTION')
			?: 'Лента новостей на инфоблоке с расширенными правами: категории — разделы ИБ, лайки, счётчик просмотров, комментарии Живой ленты, аякс-подгрузка ленты.';
		$this->PARTNER_NAME = 'mtai';
		$this->PARTNER_URI = '';
	}

	public function DoInstall(): bool
	{
		$this->InstallFiles();
		$this->InstallDB();

		return true;
	}

	public function DoUninstall(): bool
	{
		$this->UnInstallFiles();
		$this->UnInstallDB();

		return true;
	}

	public function InstallDB(): bool
	{
		ModuleManager::registerModule($this->MODULE_ID);

		// CIBlock/CIBlockType не автозагружаются — без includeModule будет
		// «Class CIBlock not found»
		Loader::includeModule('iblock');

		Iblock::install();

		return true;
	}

	public function UnInstallDB(): bool
	{
		Loader::includeModule('iblock');

		Iblock::uninstall();

		ModuleManager::unRegisterModule($this->MODULE_ID);

		return true;
	}

	public function InstallEvents(): bool
	{
		return true;
	}

	public function UnInstallEvents(): bool
	{
		return true;
	}

	public function InstallFiles(): bool
	{
		global $APPLICATION;

		$documentRoot = Application::getDocumentRoot();

		// public pages /news/
		CopyDirFiles(__DIR__ . '/public', $documentRoot, true, true);

		// components mtai.news:feed, mtai.news:detail → local/components/mtai.news/
		CopyDirFiles(__DIR__ . '/components', $documentRoot . '/local/components', true, true);

		// template "stream" for bitrix:catalog.comments → local/templates/.default/
		CopyDirFiles(__DIR__ . '/templates', $documentRoot . '/local/templates', true, true);

		Iblock::ensureDetailUrlRewrite();

		// group 2 = all users incl. guests: без этого ядро показывает гостям
		// форму авторизации вместо страниц раздела
		$APPLICATION->SetFileAccessPermission(Iblock::PUBLIC_PATH, [2 => 'R']);

		return true;
	}

	public function UnInstallFiles(): bool
	{
		global $APPLICATION;

		DeleteDirFilesEx(Iblock::PUBLIC_PATH);
		DeleteDirFilesEx('/local/components/mtai.news/');
		DeleteDirFilesEx('/local/templates/.default/components/bitrix/catalog.comments/stream/');

		$APPLICATION->SetFileAccessPermission(Iblock::PUBLIC_PATH, []);

		return true;
	}
}
