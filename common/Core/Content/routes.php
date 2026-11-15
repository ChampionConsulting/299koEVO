<?php

defined('ROOT') or exit('Access denied!');

$router = \Core\Router\Router::getInstance();

$router->map('GET', '/admin/content[/?]', 'Core\Content\Controllers\ContentAdminController#index', 'content-admin-index');
$router->map('GET', '/admin/content/type/new', 'Core\Content\Controllers\ContentAdminController#editType', 'content-admin-type-new');
$router->map('GET', '/admin/content/type/edit/[:type]', 'Core\Content\Controllers\ContentAdminController#editType', 'content-admin-type-edit');
$router->map('POST', '/admin/content/type/save', 'Core\Content\Controllers\ContentAdminController#saveType', 'content-admin-type-save');
$router->map('POST', '/admin/content/type/delete/[:type]', 'Core\Content\Controllers\ContentAdminController#deleteType', 'content-admin-type-delete');

$router->map('GET', '/admin/content/entries/[:type]', 'Core\Content\Controllers\ContentAdminController#listEntries', 'content-admin-entries');
$router->map('GET', '/admin/content/entries/[:type]/new', 'Core\Content\Controllers\ContentAdminController#editEntry', 'content-admin-entry-new');
$router->map('GET', '/admin/content/entries/[:type]/edit/[:id]', 'Core\Content\Controllers\ContentAdminController#editEntry', 'content-admin-entry-edit');
$router->map('POST', '/admin/content/entries/[:type]/save', 'Core\Content\Controllers\ContentAdminController#saveEntry', 'content-admin-entry-save');
$router->map('POST', '/admin/content/entries/[:type]/delete/[:id]', 'Core\Content\Controllers\ContentAdminController#deleteEntry', 'content-admin-entry-delete');
$router->map('POST', '/admin/content/entries/[:type]/restore-revision', 'Core\Content\Controllers\ContentAdminController#restoreRevision', 'content-admin-entry-restore-revision');
