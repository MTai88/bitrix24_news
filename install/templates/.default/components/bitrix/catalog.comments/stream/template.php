<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
/**
 * Шаблон "stream" штатного bitrix:catalog.comments (из репозитория
 * bitrix24_comments): подключает socialnetwork.blog.post.comment —
 * лента комментариев Живой ленты для элемента инфоблока.
 * Устанавливается модулем mtai.news в local/templates/.default/.
 */
/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @var CBitrixComponentTemplate $this */
$this->setFrameMode(true);
?>
<?php $APPLICATION->IncludeComponent("bitrix:socialnetwork.blog.post.comment","",Array(
      'bPublicPage' => false,
      'SEF' => 'Y',
      'BLOG_VAR' => 'blog',
      'POST_VAR' => 'id',
      'USER_VAR' => 'id',
      'PAGE_VAR' => 'page',
      'PATH_TO_BLOG' => $APPLICATION->GetCurDir() . '?page=blog&amp;amp;blog=#blog#',
      'PATH_TO_POST' => '/company/personal/user/#user_id#/blog/#post_id#/',
      'PATH_TO_USER' => '/company/personal/user/#user_id#/',
      'PATH_TO_SMILE' => false,
      'PATH_TO_MESSAGES_CHAT' => '/company/personal/messages/chat/#user_id#/',
      'ID' => $arResult["BLOG_DATA"]["BLOG_POST_ID"],
      'CACHE_TYPE' => 'A',
      'CACHE_TIME' => 31536000,
      'COMMENTS_COUNT' => 25,
      'DATE_TIME_FORMAT' => 'd.m.Y H:i',
      'DATE_TIME_FORMAT_WITHOUT_YEAR' => 'd.m H:i',
      'TIME_FORMAT' => 'H:i',
      'USE_ASC_PAGING' => '',
      'SONET_GROUP_ID' => 0,
      'NOT_USE_COMMENT_TITLE' => 'Y',
      'USE_SOCNET' => 'Y',
      'NAME_TEMPLATE' => '#NAME# #LAST_NAME#',
      'SHOW_LOGIN' => 'Y',
      'SHOW_YEAR' => 'M',
      'PATH_TO_CONPANY_DEPARTMENT' => '/company/structure.php?set_filter_structure=Y&structure_UF_DEPARTMENT=#ID#',
      'PATH_TO_VIDEO_CALL' => '/company/personal/video/#user_id#/',
      'SHOW_RATING' => 'Y',
      'RATING_TYPE' => 'like_react',
      'IMAGE_MAX_WIDTH' => 600,
      'IMAGE_MAX_HEIGHT' => 600,
      'ALLOW_VIDEO' => 'Y',
      'ALLOW_IMAGE_UPLOAD' => '',
      'SHOW_SPAM' => NULL,
      'NO_URL_IN_COMMENTS' => '',
      'NO_URL_IN_COMMENTS_AUTHORITY' => '',
      'ALLOW_POST_CODE' => true,
      'AJAX_POST' => 'Y',
      'POST_DATA' => [],
      'BLOG_DATA' => [],
      'FROM_LOG' => 'Y',
      'bFromList' => true,
      'LAST_LOG_TS' => null,
      'MARK_NEW_COMMENTS' => 'Y',
      'AVATAR_SIZE' => 100,
      'AVATAR_SIZE_COMMON' => 100,
      'AVATAR_SIZE_COMMENT' => 100,
      'FOLLOW' => 'Y',
      'LOG_ID' => null,
      'LOG_CONTENT_ITEM_TYPE' => '',
      'LOG_CONTENT_ITEM_ID' => 0,
      'CREATED_BY_ID' => false,
      'MOBILE' => null,
      'LAZYLOAD' => 'Y',
      'CAN_USER_COMMENT' => 'Y',
      'NAV_TYPE_NEW' => 'Y',
      'SELECTOR_VERSION' => 2,
      'FORM_ID' => null,
      'SOCNET_GROUP_ID' => 0,
      'BLOG_URL' => '',
      'NAV_PAGE_VAR' => 'pagen',
      'COMMENT_ID_VAR' => 'commentId',
      'USE_DESC_PAGING' => 'Y',
      'PATH_TO_POST_CURRENT' => '/company/personal/user/#user_id#/blog/#post_id#/',
      'ATTACHED_IMAGE_MAX_WIDTH_SMALL' => 70,
      'ATTACHED_IMAGE_MAX_HEIGHT_SMALL' => 70,
      'ATTACHED_IMAGE_MAX_WIDTH_FULL' => 1000,
      'ATTACHED_IMAGE_MAX_HEIGHT_FULL' => 1000,
      'DATE_TIME_FORMAT_S' => 'd.m.Y H:i',
      'DATE_FORMAT' => 'd.m.Y',
      'PAGE_SIZE' => 20,
      'PAGE_SIZE_MIN' => 3,
      'COMMENT_PROPERTY' =>
      array (
        0 => 'UF_BLOG_COMMENT_DOC',
        1 => 'UF_BLOG_COMMENT_FILE',
        2 => 'UF_BLOG_COMMENT_FH',
        3 => 'UF_BLOG_COMM_URL_PRV',
      ),
      "COMPONENT_AJAX" => "Y",
    )
);?>
