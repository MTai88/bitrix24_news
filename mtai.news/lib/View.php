<?php

namespace Mtai\News;

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\SystemException;
use Bitrix\Socialnetwork\Item\UserContentView;
use Bitrix\Socialnetwork\UserContentViewTable;

/**
 * Счётчик просмотров на встроенном механизме соцсети портала.
 *
 * Просмотры пишутся в b_sonet_user_content_view с уникальностью по
 * пользователю (PK: USER_ID + TYPE + ENTITY_ID): повторный просмотр того же
 * пользователя счётчик не увеличивает, своей таблицы нет. Ботов и анонимов
 * нет — считаются только авторизованные сотрудники.
 */
class View
{
	protected string $contentTypeId = 'IBLOCK_ELEMENT';
	protected int $userId;

	public function __construct()
	{
		Loader::includeModule('socialnetwork');

		$this->userId = (int)CurrentUser::get()->getId();
	}

	public function setUserId(int $userId): void
	{
		$this->userId = $userId;
	}

	public function getContentId(int $id): string
	{
		return $this->contentTypeId . '-' . $id;
	}

	/**
	 * Просмотры для пачки элементов: [CONTENT_ID => ['CNT' => int], ...].
	 * Читается из кеша Битрикса, подходит для ленты.
	 */
	public function getViewData(array $ids): array
	{
		$contentIdList = array_map(
			fn (int $id): string => $this->getContentId($id),
			$ids
		);

		if (empty($contentIdList))
		{
			return [];
		}

		$viewData = UserContentView::getViewData([
			'contentId' => $contentIdList,
		]);

		return is_array($viewData) ? $viewData : [];
	}

	/**
	 * Зафиксировать просмотр элемента текущим пользователем.
	 * Гости просмотры не увеличивают; повторы того же пользователя дедуплицируются.
	 *
	 * @throws SystemException
	 */
	public function set(int $contentEntityId): array
	{
		if ($this->userId <= 0)
		{
			return [];
		}

		return UserContentViewTable::set([
			'userId' => $this->userId,
			'typeId' => $this->contentTypeId,
			'entityId' => $contentEntityId,
			'save' => true,
		]);
	}
}
