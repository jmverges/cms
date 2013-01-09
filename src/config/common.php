<?php

Yii::setPathOfAlias('app', BLOCKS_APP_PATH);
Yii::setPathOfAlias('plugins', BLOCKS_PLUGINS_PATH);

// Load the configs
$blocksConfig = require_once(BLOCKS_APP_PATH.'config/defaults/blocks.php');
$dbConfig = require_once(BLOCKS_APP_PATH.'config/defaults/db.php');

if (is_array($_blocksConfig = require_once(BLOCKS_CONFIG_PATH.'blocks.php')))
{
	$blocksConfig = array_merge($blocksConfig, $_blocksConfig);
}

if (is_array($_dbConfig = require_once(BLOCKS_CONFIG_PATH.'db.php')))
{
	$dbConfig = array_merge($dbConfig, $_dbConfig);
}

if ($blocksConfig['devMode'] == true)
{
	defined('YII_DEBUG') || define('YII_DEBUG', true);
	error_reporting(E_ALL & ~E_STRICT);
	ini_set('display_errors', 1);
	ini_set('log_errors', 1);
	ini_set('error_log', BLOCKS_STORAGE_PATH.'runtime/phperrors.log');
}
else
{
	error_reporting(0);
	ini_set('display_errors', 0);
}

// Table prefixes cannot be longer than 5 characters
$tablePrefix = rtrim($dbConfig['tablePrefix'], '_');
if ($tablePrefix)
{
	if (strlen($tablePrefix) > 5)
	{
		$tablePrefix = substr($tablePrefix, 0, 5);
	}

	$tablePrefix .= '_';
}

$packages = explode(',', BLOCKS_PACKAGES);

$configArray = array(

	// autoloading model and component classes
	'import' => array(
		'application.framework.cli.commands.*',
		'application.framework.console.*',
		'application.framework.logging.CLogger',
	),

	'componentAliases' => array(
/* COMPONENT ALIASES */
		),

	'components' => array(

		'db' => array(
			'connectionString'  => strtolower('mysql:host='.$dbConfig['server'].';dbname='.$dbConfig['database'].';port='.$dbConfig['port'].';'),
			'emulatePrepare'    => true,
			'username'          => $dbConfig['user'],
			'password'          => $dbConfig['password'],
			'charset'           => $dbConfig['charset'],
			'tablePrefix'       => $tablePrefix,
			'driverMap'         => array('mysql' => 'Blocks\MysqlSchema'),
			'class'             => 'Blocks\DbConnection',
			'pdoClass'          => 'Blocks\PDO',
		),

		'config' => array(
			'class' => 'Blocks\ConfigService',
		),

		'i18n' => array(
			'class' => 'Blocks\LocalizationService',
		),

		'formatter' => array(
			'class' => '\CFormatter'
		),
	),

	'params' => array(
		'adminEmail'            => 'brad@pixelandtonic.com',
		'dbConfig'              => $dbConfig,
		'blocksConfig'          => $blocksConfig,
	)
);

if (in_array('Rebrand', $packages))
{
	$configArray['componentAliases'] = array_merge($configArray['componentAliases'], array(
/* REBRAND COMPONENT ALIASES */
	));
}

if (in_array('PublishPro', $packages))
{
	$configArray['componentAliases'] = array_merge($configArray['componentAliases'], array(
/* PUBLISHPRO COMPONENT ALIASES */
	));
}

if (in_array('Cloud', $packages))
{
	$configArray['componentAliases'] = array_merge($configArray['componentAliases'], array(
/* CLOUD COMPONENT ALIASES */
	));
}

if (in_array('Language', $packages))
{
	$configArray['componentAliases'] = array_merge($configArray['componentAliases'], array(
/* LANGUAGE COMPONENT ALIASES */
	));
}

if (in_array('Users', $packages))
{
	$configArray['componentAliases'] = array_merge($configArray['componentAliases'], array(
/* USERS COMPONENT ALIASES */
	));
}

// -------------------------------------------
//  CP routes
// -------------------------------------------

$cpRoutes['content']                                                          = 'content/entries/index';

$cpRoutes['content\/pages']                                                   = 'content/pages';
$cpRoutes['content\/pages\/new']                                              = 'content/pages/_edit/settings';
$cpRoutes['content\/pages\/(?P<pageId>\d+)']                                  = 'content/pages/_edit';
$cpRoutes['content\/pages\/(?P<pageId>\d+)\/settings']                        = 'content/pages/_edit/settings';
$cpRoutes['content\/pages\/(?P<pageId>\d+)\/blocks']                          = 'content/pages/_edit/blocks/index';
$cpRoutes['content\/pages\/(?P<pageId>\d+)\/blocks\/new']                     = 'content/pages/_edit/blocks/settings';
$cpRoutes['content\/pages\/(?P<pageId>\d+)\/blocks\/(?P<blockId>\d+)']        = 'content/pages/_edit/blocks/settings';

$cpRoutes['content\/globals']                                                 = 'content/globals/index';
$cpRoutes['content\/(?P<sectionHandle>{handle})\/new']                        = 'content/entries/_edit';
$cpRoutes['content\/(?P<sectionHandle>{handle})\/(?P<entryId>\d+)']           = 'content/entries/_edit';
$cpRoutes['content\/(?P<filter>{handle})']                                    = 'content/entries/index';

$cpRoutes['dashboard\/settings\/new']                                         = 'dashboard/settings/_widgetsettings';
$cpRoutes['dashboard\/settings\/(?P<widgetId>\d+)']                           = 'dashboard/settings/_widgetsettings';

$cpRoutes['updates\/go\/(?P<handle>[^\/]*)']                                  = 'updates/_go';

$cpRoutes['settings\/assets']                                                 = 'settings/assets/sources';
$cpRoutes['settings\/assets\/sources\/new']                                   = 'settings/assets/sources/_settings';
$cpRoutes['settings\/assets\/sources\/(?P<sourceId>\d+)']                     = 'settings/assets/sources/_settings';
$cpRoutes['settings\/assets\/sizes\/new']                                     = 'settings/assets/sizes/_settings';
$cpRoutes['settings\/assets\/sizes\/(?P<sizeHandle>{handle})']                = 'settings/assets/sizes/_settings';
$cpRoutes['settings\/assets\/blocks\/new']                                    = 'settings/assets/blocks/_settings';
$cpRoutes['settings\/assets\/blocks\/(?P<blockId>\d+)']                       = 'settings/assets/blocks/_settings';
$cpRoutes['settings\/globals\/new']                                           = 'settings/globals/_settings';
$cpRoutes['settings\/globals\/(?P<blockId>\d+)']                              = 'settings/globals/_settings';
$cpRoutes['settings\/pages\/new']                                             = 'settings/pages/_edit/settings';
$cpRoutes['settings\/pages\/(?P<pageId>\d+)']                                 = 'settings/pages/_edit/settings';
$cpRoutes['settings\/pages\/(?P<pageId>\d+)\/blocks']                         = 'settings/pages/_edit/blocks/index';
$cpRoutes['settings\/pages\/(?P<pageId>\d+)\/blocks\/new']                    = 'settings/pages/_edit/blocks/settings';
$cpRoutes['settings\/pages\/(?P<pageId>\d+)\/blocks\/(?P<blockId>\d+)']       = 'settings/pages/_edit/blocks/settings';
$cpRoutes['settings\/plugins\/(?P<pluginClass>{handle})']                     = 'settings/plugins/_settings';

$cpRoutes['myaccount']                                                        = 'users/_edit/account';

if (in_array('PublishPro', $packages))
{
	$cpRoutes['content\/(?P<sectionHandle>{handle})\/(?P<entryId>\d+)\/drafts\/(?P<draftId>\d+)']        = 'content/entries/_edit';
	$cpRoutes['content\/(?P<sectionHandle>{handle})\/(?P<entryId>\d+)\/versions\/(?P<versionId>\d+)']    = 'content/entries/_edit';

	$cpRoutes['settings\/sections\/new']                                          = 'settings/sections/_edit/settings';
	$cpRoutes['settings\/sections\/(?P<sectionId>\d+)']                           = 'settings/sections/_edit/settings';

	$cpRoutes['settings\/sections\/(?P<sectionId>\d+)\/blocks']                   = 'settings/sections/_edit/blocks';
	$cpRoutes['settings\/sections\/(?P<sectionId>\d+)\/blocks\/new']              = 'settings/sections/_edit/blocks/settings';
	$cpRoutes['settings\/sections\/(?P<sectionId>\d+)\/blocks\/(?P<blockId>\d+)'] = 'settings/sections/_edit/blocks/settings';
}
else
{
	$cpRoutes['settings\/blog\/blocks\/new']                                      = 'settings/sections/_edit/blocks/settings';
	$cpRoutes['settings\/blog\/blocks\/(?P<blockId>\d+)']                         = 'settings/sections/_edit/blocks/settings';
}

if (in_array('Users', $packages))
{
	$cpRoutes['myaccount\/profile']                                               = 'users/_edit/profile';
	$cpRoutes['myaccount\/info']                                                  = 'users/_edit/info';
	$cpRoutes['myaccount\/admin']                                                 = 'users/_edit/admin';

	$cpRoutes['users\/new']                                                       = 'users/_edit/account';
	$cpRoutes['users\/(?P<filter>{handle})']                                      = 'users';
	$cpRoutes['users\/(?P<userId>\d+)']                                           = 'users/_edit/account';
	$cpRoutes['users\/(?P<userId>\d+)\/profile']                                  = 'users/_edit/profile';
	$cpRoutes['users\/(?P<userId>\d+)\/admin']                                    = 'users/_edit/admin';
	$cpRoutes['users\/(?P<userId>\d+)\/info']                                     = 'users/_edit/info';

	$cpRoutes['settings\/users']                                                  = 'settings/users/groups';
	$cpRoutes['settings\/users\/groups\/new']                                     = 'settings/users/groups/_settings';
	$cpRoutes['settings\/users\/groups\/(?P<groupId>\d+)']                        = 'settings/users/groups/_settings';
	$cpRoutes['settings\/users\/blocks\/new']                                     = 'settings/users/blocks/_settings';
	$cpRoutes['settings\/users\/blocks\/(?P<blockId>\d+)']                        = 'settings/users/blocks/_settings';
}

// -------------------------------------------
//  Component config
// -------------------------------------------

$components['users']['class']             = 'Blocks\UsersService';
$components['assets']['class']            = 'Blocks\AssetsService';
$components['assetSizes']['class']        = 'Blocks\AssetSizesService';
$components['assetIndexing']['class']     = 'Blocks\AssetIndexingService';
$components['assetSources']['class']      = 'Blocks\AssetSourcesService';
$components['blockTypes']['class']        = 'Blocks\BlockTypesService';
$components['components']['class']        = 'Blocks\ComponentsService';
$components['dashboard']['class']         = 'Blocks\DashboardService';
$components['email']['class']             = 'Blocks\EmailService';
$components['entries']['class']           = 'Blocks\EntriesService';
$components['et']['class']                = 'Blocks\EtService';
$components['globals']['class']           = 'Blocks\GlobalsService';
$components['install']['class']           = 'Blocks\InstallService';
$components['images']['class']            = 'Blocks\ImagesService';
$components['links']['class']             = 'Blocks\LinksService';
$components['migrations']['class']        = 'Blocks\MigrationsService';
$components['pages']['class']             = 'Blocks\PagesService';
$components['path']['class']              = 'Blocks\PathService';
$components['plugins']['class']           = 'Blocks\PluginsService';

$components['resources']['class']         = 'Blocks\ResourcesService';
$components['resources']['dateParam']     = 'd';

$components['routes']['class']            = 'Blocks\RoutesService';
$components['security']['class']          = 'Blocks\SecurityService';
$components['systemSettings']['class']    = 'Blocks\SystemSettingsService';
$components['templates']['class']         = 'Blocks\TemplatesService';
$components['updates']['class']           = 'Blocks\UpdatesService';

if (in_array('PublishPro', $packages))
{
	$components['entryRevisions']['class']    = 'Blocks\EntryRevisionsService';
	$components['sections']['class']          = 'Blocks\SectionsService';
}

if (in_array('Users', $packages))
{
	$components['userProfiles']['class']      = 'Blocks\UserProfilesService';
	$components['userGroups']['class']        = 'Blocks\UserGroupsService';
	$components['userPermissions']['class']   = 'Blocks\UserPermissionsService';
}

if (in_array('Rebrand', $packages))
{
	$components['emailMessages']['class']     = 'Blocks\EmailMessagesService';
}

$components['file']['class'] = 'Blocks\File';
$components['messages']['class'] = 'Blocks\PhpMessageSource';
$components['request']['class'] = 'Blocks\HttpRequestService';
$components['request']['enableCookieValidation'] = true;
$components['viewRenderer']['class'] = 'Blocks\TemplateProcessor';
$components['statePersister']['class'] = 'Blocks\StatePersister';

$components['urlManager']['class'] = 'Blocks\UrlManager';
$components['urlManager']['cpRoutes'] = $cpRoutes;
$components['urlManager']['pathParam'] = 'p';

$components['assetManager']['basePath'] = dirname(__FILE__).'/../assets';
$components['assetManager']['baseUrl'] = '../../blocks/app/assets';

$components['errorHandler']['class'] = 'Blocks\ErrorHandler';

$components['fileCache']['class'] = 'CFileCache';

$components['log']['class'] = 'Blocks\LogRouter';
$components['log']['routes'] = array(
	array(
		'class'  => 'Blocks\FileLogRoute',
	),
	array(
		'class'         => 'Blocks\WebLogRoute',
		'filter'        => 'CLogFilter',
		'showInFireBug' => true,
	),
	array(
		'class'         => 'Blocks\ProfileLogRoute',
		'showInFireBug' => true,
	),
);

$components['httpSession']['autoStart']   = true;
$components['httpSession']['cookieMode']  = 'only';
$components['httpSession']['class']       = 'Blocks\HttpSessionService';
$components['httpSession']['sessionName'] = 'BlocksSessionId';

$components['userSession']['class'] = 'Blocks\UserSessionService';
$components['userSession']['allowAutoLogin']  = true;
$components['userSession']['loginUrl']        = $blocksConfig['loginPath'];
$components['userSession']['autoRenewCookie'] = true;

$configArray['components'] = array_merge($configArray['components'], $components);

return $configArray;
