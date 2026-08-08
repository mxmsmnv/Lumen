<?php namespace ProcessWire;

/**
 * TextformatterLumen — embed Lumen videos in formatted text fields
 *
 * Usage in CKEditor/TinyMCE:
 *   [[lumen:video_uid]]                  — single video by UID
 *   [[lumen:page_id.field_name]]         — first video from a page field
 *   [[lumen:page_id.field_name:thumb]]   — thumbnail instead of player
 *
 * @version 119
 */
class TextformatterLumen extends Textformatter implements Module, ConfigurableModule {

	public static function getModuleInfo() {
		return array(
			'title' => 'Lumen Embed',
			'version' => 119,
			'summary' => 'Embed Cloudflare Stream videos with [[lumen:...]] shortcodes',
			'href' => 'https://smnv.org',
				'author' => 'Maxim Semenov',
				'requires' => 'Lumen',
			'icon' => 'video-camera',
		);
	}

	public function __construct() {
		parent::__construct();
		$this->set('defaultWidth', 800);
		$this->set('defaultHeight', 450);
		$this->set('responsive', true);
	}

	/**
	 * Format the given text — replace shortcodes with video embeds
	 */
	public function format(&$str) {
		if(strpos($str, '[[lumen:') === false) return;

		$str = preg_replace_callback(
			'/\[\[lumen:([^\]]+)\]\]/',
			array($this, 'renderShortcode'),
			$str
		);
	}

	protected function renderShortcode($matches) {
		$spec = trim($matches[1]);
		$pages = $this->wire('pages');
		$lumen = $this->wire('modules')->get('Lumen');
		$fieldtype = $this->wire('modules')->get('FieldtypeLumen');

		// [[lumen:thumb:UID]]
		if(preg_match('/^thumb:(.+)/', $spec, $m)) {
			return $this->renderThumbnail($m[1], $lumen);
		}

		// [[lumen:page_id.field_name]] or [[lumen:page_id.field_name:thumb]]
		if(preg_match('/^(\d+)\.([a-z_]\w*)(:thumb)?$/', $spec, $m)) {
			$page = $pages->get((int)$m[1]);
			if(!$page->id) return '';
			$fieldName = $m[2];
			$video = $page->get($fieldName);
			$pf = $video instanceof Pagefile
				? $video
				: ($video instanceof Pagefiles ? $video->first() : null);
			if(!$pf) return '';

			if(!empty($m[3])) { // :thumb
				return $this->renderThumbnail($pf->stream_uid, $lumen);
			}
			return $this->renderEmbed($pf, $fieldtype);
		}

		// [[lumen:video_uid]]
		if(preg_match('/^[a-f0-9]{20,}$/', $spec)) {
			return $this->renderEmbedByUid($spec, $lumen);
		}

		return '';
	}

	protected function renderEmbed($pagefile, $fieldtype) {
		if(!$pagefile->streamReady()) return '';

		$w = (int)$this->defaultWidth;
		$h = (int)$this->defaultHeight;

		if($this->responsive) {
			return '<div style="margin:1em 0">'
				. $fieldtype->getStreamEmbedResponsive($pagefile, $w, $h)
				. '</div>';
		}

		return $fieldtype->getStreamEmbed($pagefile, $w, $h);
	}

	protected function renderEmbedByUid($uid, $lumen) {
		$playbackId = $lumen->getStreamPlaybackIdentifier($uid);
		if($playbackId === '') return '';

		$host = $lumen->getCustomerStreamHost();
		if($host === '') return '';
		$w = (int)$this->defaultWidth;
		$h = (int)$this->defaultHeight;
		$playbackId = $this->wire('sanitizer')->entities($playbackId);

		$iframe = '<iframe src="' . $host . '/' . $playbackId . '/iframe" title="' . $this->wire('sanitizer')->entities($this->_('Video')) . '" style="border:none" width="' . $w . '" height="' . $h . '" loading="lazy" '
			. 'allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture" '
			. 'allowfullscreen></iframe>';

		if($this->responsive) {
			$iframe = preg_replace(
				'/style="[^"]*"/',
				'style="border:none;position:absolute;inset:0;width:100%;height:100%"',
				$iframe,
				1
			);
			return '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%;margin:1em 0">'
				. $iframe
				. '</div>';
		}
		return $iframe;
	}

	protected function renderThumbnail($uid, $lumen) {
		$playbackId = $lumen->getStreamPlaybackIdentifier($uid);
		if($playbackId === '') return '';

		$host = $lumen->getCustomerStreamHost();
		if($host === '') return '';
		$playbackId = $this->wire('sanitizer')->entities($playbackId);
		return '<img src="' . $host . '/' . $playbackId . '/thumbnails/thumbnail.jpg" alt="" '
			. 'style="max-width:100%;height:auto" loading="lazy">';
	}

	public static function getModuleConfigInputfields(array $data) {
		$inputfields = new InputfieldWrapper();
		$modules = wire('modules');

		$f = $modules->get('InputfieldInteger');
		$f->name = 'defaultWidth';
		$f->label = __('Default Width');
		$f->value = isset($data['defaultWidth']) ? (int)$data['defaultWidth'] : 800;
		$f->columnWidth = 50;
		$inputfields->add($f);

		$f = $modules->get('InputfieldInteger');
		$f->name = 'defaultHeight';
		$f->label = __('Default Height');
		$f->value = isset($data['defaultHeight']) ? (int)$data['defaultHeight'] : 450;
		$f->columnWidth = 50;
		$inputfields->add($f);

		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'responsive';
		$f->label = __('Responsive Embed');
		$f->description = __('Wrap the iframe in a 16:9 responsive container.');
		$f->checked = isset($data['responsive']) ? $data['responsive'] : true;
		$inputfields->add($f);

		return $inputfields;
	}
}
