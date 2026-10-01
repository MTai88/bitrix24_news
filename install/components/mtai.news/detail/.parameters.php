<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
	'GROUPS' => [],
	'PARAMETERS' => [
		'ID' => [
			'PARENT' => 'BASE',
			'NAME' => 'ID новости (элемента инфоблока)',
			'TYPE' => 'STRING',
			'DEFAULT' => '',
		],
		'IBLOCK_ID' => [
			'PARENT' => 'BASE',
			'NAME' => 'Инфоблок новостей',
			'TYPE' => 'STRING',
			'DEFAULT' => '',
		],
		'IBLOCK_TYPE' => [
			'PARENT' => 'BASE',
			'NAME' => 'Тип инфоблока',
			'TYPE' => 'STRING',
			'DEFAULT' => 'mtai_news',
		],
		'BACK_URL' => [
			'PARENT' => 'URL_TEMPLATES_PAGE',
			'NAME' => 'Ссылка «Назад к новостям»',
			'TYPE' => 'STRING',
			'DEFAULT' => '/news/',
		],
		'BLOG_URL' => [
			'PARENT' => 'BASE',
			'NAME' => 'URL блога комментариев',
			'TYPE' => 'STRING',
			'DEFAULT' => 'iblock_comments',
		],
		'BLOG_POST_RIGHTS' => [
			'PARENT' => 'BASE',
			'NAME' => 'Права Живой ленты для автосозданных постов (коды через запятую)',
			'TYPE' => 'STRING',
			'DEFAULT' => 'G2',
		],
		'CACHE_TIME' => [
			'DEFAULT' => 0,
		],
	],
];
