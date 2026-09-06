<?php
/**
 * Bootstrap 4 - Typesetter CMS theme
 * 'sidebars' layout settings
 */

defined('is_running') or die('Not an entry point...');

// add additional custom settings or areas if required

// Simple Blog Gadgets in Text Areas
ob_start();
gpOutput::GetGadget('Simple_Blog');
$area_content = ob_get_clean();
gpOutput::Area('Simple-Blog-Gadget', $area_content);

ob_start();
gpOutput::GetGadget('Simple_Blog_Categories');
$area_content = ob_get_clean();
gpOutput::Area('Simple-Blog-Categories-Gadget', $area_content);

ob_start();
gpOutput::GetGadget('Simple_Blog_Archives');
$area_content = ob_get_clean();
gpOutput::Area('Simple-Blog-Archives-Gadget', $area_content);