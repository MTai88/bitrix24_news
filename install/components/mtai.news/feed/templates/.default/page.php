<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/**
 * Частичный шаблон ленты: только карточки новостей. Используется и внутри
 * полного шаблона (первая страница), и в ajax-ответе /news/ajax.php
 * (includeComponentTemplate('page') при AJAX_MODE=Y).
 */
/**
 * @var array $arParams
 * @var array $arResult
 * @global CMain $APPLICATION
 * @var CBitrixComponent $component
 */

foreach ($arResult['ITEMS'] as $item) { ?>
<article class="mnt-card" data-mnt-id="<?= $item['ID'] ?>">
	<?php if (!empty($item['THUMB'])) { ?>
	<a class="mnt-card__pic" href="<?= htmlspecialcharsbx($item['DETAIL_URL']) ?>">
		<img src="<?= htmlspecialcharsbx($item['THUMB']) ?>" alt="<?= htmlspecialcharsbx($item['NAME']) ?>" loading="lazy">
	</a>
	<?php } ?>
	<div class="mnt-card__body">
		<div class="mnt-card__meta">
			<?php if ($item['DATE'] !== '') { ?><span class="mnt-card__date"><?= $item['DATE'] ?></span><?php } ?>
			<?php if ($item['SECTION_NAME'] !== '') { ?><span class="mnt-card__section"><?= htmlspecialcharsbx($item['SECTION_NAME']) ?></span><?php } ?>
		</div>
		<h2 class="mnt-card__title"><a href="<?= htmlspecialcharsbx($item['DETAIL_URL']) ?>"><?= htmlspecialcharsbx($item['NAME']) ?></a></h2>
		<?php if ($item['PREVIEW_TEXT'] !== '') { ?>
		<p class="mnt-card__text"><?= nl2br(htmlspecialcharsbx($item['PREVIEW_TEXT'])) ?></p>
		<?php } ?>
		<div class="mnt-card__stats">
			<span class="mnt-card__stat mnt-card__stat--like">
				<span id="bx-ilike-button-<?= htmlspecialcharsbx($item['VOTE_ID']) ?>"
						class="feed-inform-ilike feed-new-like">
					<span class="bx-ilike-left-wrap<?= ($item['RATING']['USER_HAS_VOTED'] ? ' bx-you-like-button' : '') ?>"><a
								href="#like" class="bx-ilike-text"><?= $item['RATING']['BUTTON_TEXT'] ?></a></span>
				</span>
				<div class="feed-post-emoji-top-panel-outer">
					<div id="feed-post-emoji-top-panel-container-<?= htmlspecialcharsbx($item['VOTE_ID']) ?>"
							class="feed-post-emoji-top-panel-box <?= ($item['RATING']['HAS_REACTIONS'] ? 'feed-post-emoji-top-panel-container-active' : '') ?>">
						<?php
						$APPLICATION->IncludeComponent(
							'bitrix:rating.vote',
							'like_react',
							$item['VOTE_PARAMS'],
							null,
							['HIDE_ICONS' => 'Y']
						);
						?>
					</div>
				</div>
			</span>
			<span class="mnt-card__stat mnt-card__stat--view"><?php
			$APPLICATION->IncludeComponent(
				'bitrix:socialnetwork.contentview.count',
				'',
				[
					'CONTENT_ID' => $item['CONTENT_ID'],
					'CONTENT_VIEW_CNT' => $item['VIEW_CNT'],
					'PATH_TO_USER_PROFILE' => '/company/personal/user/#user_id#/',
					'IS_SET' => 'N',
				],
				$component,
				['HIDE_ICONS' => 'Y']
			);
			?></span>
			<a class="mnt-card__stat mnt-card__stat--comments"
				href="<?= htmlspecialcharsbx($item['DETAIL_URL']) ?>#mnt-comments"
				title="Комментарии">
				<span class="mnt-card__stat-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>
				</span>
				<span class="mnt-card__stat-num"><?= (int)$item['COMMENTS_CNT'] ?></span>
			</a>
		</div>
	</div>
</article>
<?php }
