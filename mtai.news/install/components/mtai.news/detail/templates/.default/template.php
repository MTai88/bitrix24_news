<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/**
 * Детальная новость: текст, лайки (rating.vote like_react), просмотры
 * (socialnetwork.contentview.count), комментарии Живой ленты
 * (bitrix:catalog.comments, шаблон stream — подключается установщиком модуля
 * в local/templates/.default/components/bitrix/catalog.comments/stream/).
 */
/**
 * @var array $arParams
 * @var array $arResult
 * @global CMain $APPLICATION
 */
?>
<article class="mnt-detail">
	<a class="mnt-detail__back" href="<?= htmlspecialcharsbx($arResult['BACK_URL']) ?>" data-mnt-back>&larr; Назад к новостям</a>

	<?php if (!empty($arResult['PICTURE'])) { ?>
		<img class="mnt-detail__pic" src="<?= htmlspecialcharsbx($arResult['PICTURE']) ?>" alt="<?= htmlspecialcharsbx($arResult['NAME']) ?>">
	<?php } ?>

	<div class="mnt-detail__meta">
		<?php if ($arResult['DATE'] !== '') { ?><span class="mnt-detail__date"><?= $arResult['DATE'] ?></span><?php } ?>
		<?php if ($arResult['SECTION_NAME'] !== '') { ?><span class="mnt-detail__section"><?= htmlspecialcharsbx($arResult['SECTION_NAME']) ?></span><?php } ?>
		<span class="feed-inform-item feed-post-time-wrap feed-inform-contentview"><?php
		$APPLICATION->IncludeComponent(
			'bitrix:socialnetwork.contentview.count',
			'',
			[
				'CONTENT_ID' => $arResult['CONTENT_ID'],
				'CONTENT_VIEW_CNT' => $arResult['VIEW_CNT'],
				'PATH_TO_USER_PROFILE' => '/company/personal/user/#user_id#/',
				'IS_SET' => 'N',
			],
			$component,
			['HIDE_ICONS' => 'Y']
		);
		?></span>
	</div>

	<h1 class="mnt-detail__title"><?= htmlspecialcharsbx($arResult['NAME']) ?></h1>

	<?php if ($arResult['PREVIEW_TEXT'] !== '') { ?>
		<p class="mnt-detail__lead"><?= nl2br(htmlspecialcharsbx($arResult['PREVIEW_TEXT'])) ?></p>
	<?php } ?>

	<div class="mnt-detail__text"><?=
		$arResult['DETAIL_TEXT_TYPE'] === 'html'
			? $arResult['DETAIL_TEXT']
			: nl2br(htmlspecialcharsbx($arResult['DETAIL_TEXT']))
	?></div>

	<div class="mnt-detail__actions">
		<span class="mnt-detail__like">
			<span id="bx-ilike-button-<?= htmlspecialcharsbx($arResult['VOTE_PARAMS']['VOTE_ID']) ?>"
					class="feed-inform-ilike feed-new-like">
				<span class="bx-ilike-left-wrap<?= ($arResult['RATING']['USER_HAS_VOTED'] ? ' bx-you-like-button' : '') ?>"><a
							href="#like" class="bx-ilike-text"><?= $arResult['RATING']['BUTTON_TEXT'] ?></a></span>
			</span>
			<div class="feed-post-emoji-top-panel-outer">
				<div id="feed-post-emoji-top-panel-container-<?= htmlspecialcharsbx($arResult['VOTE_PARAMS']['VOTE_ID']) ?>"
						class="feed-post-emoji-top-panel-box <?= ($arResult['RATING']['HAS_REACTIONS'] ? 'feed-post-emoji-top-panel-container-active' : '') ?>">
					<?php
					$APPLICATION->IncludeComponent(
						'bitrix:rating.vote',
						'like_react',
						$arResult['VOTE_PARAMS'],
						null,
						['HIDE_ICONS' => 'Y']
					);
					?>
				</div>
			</div>
		</span>
	</div>
</article>

<section class="mnt-detail__comments" id="mnt-comments">
	<h2 class="mnt-detail__comments-title">Комментарии</h2>

	<?php if (!empty($arResult['ID'])): ?>
		<?php $APPLICATION->IncludeComponent(
			'bitrix:catalog.comments',
			'stream',
			[
				'BLOG_TITLE' => 'Комментарии',
				'BLOG_URL' => $arParams['BLOG_URL'],
				'BLOG_USE' => 'Y',
				'CACHE_TIME' => '0',
				'CACHE_TYPE' => 'A',
				'CHECK_DATES' => 'Y',
				'COMMENTS_COUNT' => '10',
				'ELEMENT_CODE' => '',
				'ELEMENT_ID' => $arResult['ID'],
				'EMAIL_NOTIFY' => 'N',
				'FB_USE' => 'N',
				'IBLOCK_ID' => $arParams['IBLOCK_ID'],
				'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
				'PATH_TO_SMILE' => '/bitrix/images/blog/smile/',
				'RATING_TYPE' => '',
				'SHOW_DEACTIVATED' => 'N',
				'SHOW_RATING' => 'Y',
				'SHOW_SPAM' => 'Y',
				'TEMPLATE_THEME' => 'blue',
				'URL_TO_COMMENT' => '',
				'VK_USE' => 'N',
				'WIDTH' => '',
			],
			false,
			['HIDE_ICONS' => 'Y']
		); ?>
	<?php endif; ?>
</section>

<script>
	// «Назад» — на предыдущую запись истории: лента при этом сама
	// восстанавливает глубину и позицию скролла (см. script.js ленты).
	// Если детальная открыта напрямую (нет referrer'а с этого портала) —
	// работаем как обычная ссылка на BACK_URL.
	(function () {
		var link = document.querySelector('[data-mnt-back]');
		if (!link) {
			return;
		}
		link.addEventListener('click', function (event) {
			if (window.history.length > 1
				&& document.referrer
				&& document.referrer.indexOf(window.location.origin) === 0
			) {
				event.preventDefault();
				window.history.back();
			}
		});
	})();
</script>
