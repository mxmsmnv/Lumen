<?php namespace ProcessWire;

require_once __DIR__ . '/src/Core/LifecycleTrait.php';
require_once __DIR__ . '/src/Core/UploadTrait.php';
require_once __DIR__ . '/src/Core/DiagnosticsTrait.php';
require_once __DIR__ . '/src/Core/StreamApiTrait.php';
require_once __DIR__ . '/src/Core/ConfigUiTrait.php';

/**
 * Lumen — Cloudflare Stream video package for ProcessWire.
 *
 * @property string $cfAccountId Cloudflare Account ID
 * @property string $cfApiToken Cloudflare API Token
 * @property bool $requireSignedUrls Require signed URLs for video playback
 * @property int $maxDurationSeconds Maximum video duration in seconds
 * @property bool $localStorage Store files locally instead of Stream
 * @property bool $debugMode Enable detailed Lumen event logging
 * @property string $customerCodeOverride Manually override customer code
 *
 * @version 118
 */
class Lumen extends WireData implements Module, ConfigurableModule {

	use LumenLifecycleTrait;
	use LumenUploadTrait;
	use LumenDiagnosticsTrait;
	use LumenStreamApiTrait;
	use LumenConfigUiTrait;

	const STREAM_TOKEN_CACHE_TTL = 3000;
	const DIRECT_UPLOAD_TIMEOUT = 600;

	public static function getModuleInfo() {
		return array(
			'title' => 'Lumen',
			'version' => 118,
			'summary' => 'Cloudflare Stream video package: central configuration and API helpers',
			'href' => 'https://smnv.org',
			'author' => 'Maxim Semenov',
			'installs' => array('FieldtypeLumen', 'InputfieldLumen', 'ProcessLumen', 'TextformatterLumen'),
			'autoload' => false,
			'singular' => true
		);
	}
}
