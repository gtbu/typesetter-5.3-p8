<?php
/**
 * Bootstrap 5 - Typesetter CMS theme (migration draft)
 * Master template
 * See settings.php for configuration options
 */

global $page;
/**
 * Load theme javascripts
 *
 */
$page->head_js[] = dirname(rawurldecode($page->theme_path)) . '/_common/script.js';
$page->head_js[] = dirname(rawurldecode($page->theme_path)) . '/_common/menu.js';


/**
 * Include template.php from the current layout subdirectory
 *
 */
include($page->theme_dir . '/' . $page->theme_color . '/template.php');
