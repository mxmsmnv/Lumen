<?php namespace ProcessWire;

require_once __DIR__ . '/src/Fieldtype/HooksTrait.php';
require_once __DIR__ . '/src/Fieldtype/SchemaTrait.php';
require_once __DIR__ . '/src/Fieldtype/PlaybackTrait.php';

/**
 * ProcessWire file fieldtype for Cloudflare Stream.
 *
 * @version 122
 */
class FieldtypeLumen extends FieldtypeFile implements Module {

	use FieldtypeLumenHooksTrait;
	use FieldtypeLumenSchemaTrait;
	use FieldtypeLumenPlaybackTrait;

	public static function getModuleInfo() {
		return array(
			'title' => 'Lumen',
			'version' => 122,
			'summary' => 'Store and stream video files using Cloudflare Stream',
			'href' => 'https://smnv.org',
			'author' => 'Maxim Semenov',
			'requires' => 'Lumen',
			'installs' => 'InputfieldLumen',
			'icon' => 'video-camera'
		);
	}
}
