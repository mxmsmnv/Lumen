<?php namespace ProcessWire;

require_once __DIR__ . '/src/Inputfield/BootstrapTrait.php';
require_once __DIR__ . '/src/Inputfield/HooksTrait.php';
require_once __DIR__ . '/src/Inputfield/UploadTrait.php';
require_once __DIR__ . '/src/Inputfield/RenderTrait.php';

/**
 * ProcessWire inputfield for Cloudflare Stream uploads.
 *
 * @property string $cfAccountId Cloudflare Account ID
 * @property string $cfApiToken Cloudflare API Token
 * @property bool $requireSignedUrls Require signed URLs
 * @property int $maxDurationSeconds Maximum video duration
 * @property bool $localStorage Store files locally instead of Stream
 *
 * @version 100
 */
class InputfieldLumen extends InputfieldFile implements Module {

	use InputfieldLumenBootstrapTrait;
	use InputfieldLumenHooksTrait;
	use InputfieldLumenUploadTrait;
	use InputfieldLumenRenderTrait;

	const UPLOAD_METHOD_DIRECT = 'direct';
	const UPLOAD_METHOD_TUS = 'tus';
	const TUS_CHUNK_SIZE = 10 * 1024 * 1024;

	public static function getModuleInfo() {
		return array(
			'title' => __('Lumen', __FILE__),
			'summary' => __('Video uploads to Cloudflare Stream with automatic transcoding', __FILE__),
			'version' => 100,
			'href' => 'https://smnv.org',
			'author' => 'Maxim Semenov',
			'requires' => 'FieldtypeLumen',
			'icon' => 'video-camera',
		);
	}
}
