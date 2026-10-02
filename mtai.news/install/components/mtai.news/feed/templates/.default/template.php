<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/**
 * Полный шаблон ленты: заголовок, табы категорий, первая страница карточек
 * (page.php), сентинел для подгрузки при скролле, восстановление позиции
 * при возврате по «Назад» (script.js, history.state).
 */
/**
 * @var array $arParams
 * @var array $arResult
 * @global CMain $APPLICATION
 */
?>
<div class="mnt-feed"
	data-mnt-feed
	data-ajax="/news/ajax.php"
	data-page-size="<?= (int)$arParams['PAGE_SIZE'] ?>"
	data-section="<?= (int)$arResult['SECTION_ID'] ?>"
	data-has-more="<?= $arResult['HAS_MORE'] ? 'Y' : 'N' ?>">

	<div class="mnt-feed__head">
		<h1 class="mnt-feed__title">Новости</h1>
		<div class="mnt-feed__tabs">
			<a class="mnt-feed__tab<?= $arResult['SECTION_ID'] === 0 ? ' is-active' : '' ?>"
				href="<?= htmlspecialcharsbx('/news/') ?>">Все</a>
			<?php foreach ($arResult['SECTIONS'] as $section) { ?>
				<a class="mnt-feed__tab<?= $arResult['SECTION_ID'] === $section['ID'] ? ' is-active' : '' ?>"
					href="<?= htmlspecialcharsbx($section['URL']) ?>"><?= htmlspecialcharsbx($section['NAME']) ?></a>
			<?php } ?>
		</div>
	</div>

	<div class="mnt-feed__list" data-mnt-list><?php include __DIR__ . '/page.php'; ?></div>

	<?php if (empty($arResult['ITEMS'])) { ?>
		<p class="mnt-feed__empty">Новостей пока нет.</p>
	<?php } ?>

	<?php if ($arResult['HAS_MORE']) { ?>
		<div class="mnt-feed__sentinel" data-mnt-sentinel><span class="mnt-feed__spinner"></span></div>
	<?php } ?>
	<p class="mnt-feed__done" data-mnt-done<?= $arResult['HAS_MORE'] ? ' hidden' : '' ?>>Вы посмотрели все новости</p>
</div>
<?php
// style.css из каталога шаблона подключается автоматически, а script.js —
// нет (BX.setJSList на лёгком шаблоне не гарантирует загрузку): явный тег
?><script src="<?= $templateFolder ?>/script.js"></script>
