<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
	'GROUPS' => [],
	'PARAMETERS' => [
		'IBLOCK_ID' => [
			'PARENT' => 'BASE',
			'NAME' => 'Инфоблок новостей',
			'TYPE' => 'STRING',
			'DEFAULT' => '',
		],
		'PAGE_SIZE' => [
			'PARENT' => 'BASE',
			'NAME' => 'Новостей за одну подгрузку',
			'TYPE' => 'STRING',
			'DEFAULT' => '10',
		],
		'DETAIL_URL' => [
			'PARENT' => 'URL_TEMPLATES_PAGE',
			'NAME' => 'Шаблон URL детальной страницы (#ID#)',
			'TYPE' => 'STRING',
			'DEFAULT' => '/news/#ID#/',
		],
		'CACHE_TIME' => [
			'DEFAULT' => 0,
		],
	],
];
