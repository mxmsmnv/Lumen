<?php namespace ProcessWire;

require_once __DIR__ . '/src/Admin/DashboardTrait.php';
require_once __DIR__ . '/src/Admin/SettingsTrait.php';
require_once __DIR__ . '/src/Admin/FiltersTrait.php';
require_once __DIR__ . '/src/Admin/UploadTrait.php';
require_once __DIR__ . '/src/Admin/VideoTrait.php';
require_once __DIR__ . '/src/Admin/AssetsTrait.php';
require_once __DIR__ . '/src/Admin/BootstrapTrait.php';
require_once __DIR__ . '/src/Admin/ActionsTrait.php';
require_once __DIR__ . '/src/Admin/ExecuteTrait.php';
require_once __DIR__ . '/src/Support/FieldsTrait.php';

/**
 * Lumen administrative process.
 *
 * @version 120
 */
class ProcessLumen extends Process implements Module {

	use ProcessLumenDashboardTrait;
	use ProcessLumenSettingsTrait;
	use ProcessLumenFiltersTrait;
	use ProcessLumenUploadTrait;
	use ProcessLumenVideoTrait;
	use ProcessLumenAssetsTrait;
	use ProcessLumenBootstrapTrait;
	use ProcessLumenActionsTrait;
	use ProcessLumenExecuteTrait;
	use ProcessLumenFieldsTrait;

	const REFRESH_BATCH_SIZE = 25;
	const PER_PAGE = 24;
	const UPLOAD_PAGE_SELECTOR_LIMIT = 200;
	const API_RATE_LIMIT_US = 100000;
	const STREAM_STORAGE_USD_PER_1000_MINUTES = 5.0;
	const STREAM_DELIVERY_USD_PER_1000_MINUTES = 1.0;
	const STREAM_STARTER_STORAGE_MINUTES = 1000;
	const STREAM_STARTER_DELIVERY_MINUTES = 5000;
	const STREAM_CREATOR_STORAGE_MINUTES = 10000;
	const STREAM_CREATOR_DELIVERY_MINUTES = 50000;

	protected static $scriptsRendered = false;

	public static function getModuleInfo() {
		return array(
			'title' => 'Lumen',
			'version' => 120,
			'summary' => 'Dashboard for Lumen / Cloudflare Stream video fields',
			'href' => 'https://smnv.org',
			'author' => 'Maxim Semenov',
			'requires' => array('FieldtypeLumen', 'Lumen'),
			'icon' => 'video-camera',
			'permission' => 'lumen-admin',
			'permissions' => array(
				'lumen-admin' => 'Manage Lumen / Cloudflare Stream videos'
			),
			'page' => array(
				'name' => 'lumen',
				'parent' => 'setup',
				'title' => 'Lumen'
			)
		);
	}
}
