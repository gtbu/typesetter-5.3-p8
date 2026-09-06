<?php
/**
 * Bootstrap 4 - Typesetter CMS theme
 * settings for all layouts
 */

defined('is_running') or die('Not an entry point...');

// Text Area for navbar-brand
gpOutput::Area('Site-Name', '<span class="brand-name">%s</span>');

// Search Gadget in a Text Area
ob_start();
gpOutput::GetGadget('Search');
$area_content = ob_get_clean();
gpOutput::Area('Search-Gadget', $area_content);

// Admin Link in a Text Area
ob_start();
gpOutput::GetAdminLink(false); // false = do not attach messages here
$area_content = ob_get_clean();
gpOutput::Area('Admin-Link-Area', '%s' . $area_content);




/**
 * Include current layout settings
 *
 */
include($page->theme_dir . '/' . $page->theme_color . '/settings.php');
