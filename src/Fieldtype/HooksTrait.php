<?php namespace ProcessWire;

trait FieldtypeLumenHooksTrait {
	/**
	 * Get the central Lumen module (configuration + API helpers)
	 *
	 * @return Lumen
	 */
	public function lumen() {
		return $this->wire('modules')->get('Lumen');
	}

	/**
	 * Initialize
	 */
	public function init() {
	    parent::init();

	    // Auto-migrate schema for existing fields (adds new columns to existing tables)
	    $this->migrateExistingFieldSchemas();

	    // Public Pagefile API must be available on frontend requests, where the
	    // administrative InputfieldLumen module is not necessarily instantiated.
	    $this->addHook('Pagefile::streamUrl', $this, 'hookStreamUrl');
	    $this->addHook('Pagefile::streamEmbed', $this, 'hookStreamEmbed');
	    $this->addHook('Pagefile::streamThumbnail', $this, 'hookStreamThumbnail');
	    $this->addHook('Pagefile::streamPreview', $this, 'hookStreamPreview');
	    $this->addHook('Pagefile::streamReady', $this, 'hookStreamReady');
	    $this->addHook('Pagefile::linkedPage', $this, 'hookLinkedPage');
	    $this->addHook('Pagefile::tags', $this, 'hookTags');
	    $this->addHook('Pagefile::streamPoster', $this, 'hookStreamPoster');
	    $this->addHook('Pagefile::subtitles', $this, 'hookSubtitles');
	    $this->addHook('Pagefile::streamDurationFormatted', $this, 'hookStreamDurationFormatted');
	    $this->addHook('Pagefile::streamAspect', $this, 'hookStreamAspect');
	    $this->addHook('Pagefile::streamEmbedResponsive', $this, 'hookStreamEmbedResponsive');
	    $this->addHookAfter('Pages::saved', $this, 'hookPagesSaved');
	    $this->addHook('LazyCron::everyMinute', $this, 'hookRefreshPendingStreams');
	    if($this->wire('modules')->isInstalled('CloudCache')) {
	        $this->addHookAfter('CloudCache::isCacheable', $this, 'hookCloudCacheIsCacheable');
	    }
	}

	/**
	 * Refresh a bounded batch of pending Cloudflare videos.
	 *
	 * Cloudflare supplies duration and input dimensions only after processing.
	 * Keeping this server-side avoids browser polling and lets optional
	 * consumers classify portrait videos as short-form content automatically.
	 */
	public function hookRefreshPendingStreams(HookEvent $event) {
		if($this->lumen()->localStorage) return;
		$remaining = 10;
		foreach($this->wire('fields') as $field) {
			if($remaining < 1) break;
			if(!($field->type instanceof self)) continue;
			$table = $field->getTable();
			$sql = "SELECT pages_id, `sort`, stream_uid FROM `{$table}` "
				. "WHERE stream_uid IS NOT NULL AND stream_uid != '' "
				. "AND stream_ready = 0 AND stream_status != 'error' "
				. "ORDER BY pages_id ASC LIMIT " . (int)$remaining;
			try {
				$rows = $this->wire('database')->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
			} catch(\Throwable $e) {
				$this->lumen()->eventLog('error', 'Pending Stream refresh query failed', array(
					'field' => $field->name,
					'error' => $e->getMessage(),
				), true);
				continue;
			}
			if(!$rows) continue;
			foreach($rows as $row) {
				if($remaining-- < 1) break 2;
				$page = $this->wire('pages')->get('id=' . (int)$row['pages_id'] . ', include=all');
				if(!$page->id || !$page->hasField($field->name)) continue;
				$inputfield = $this->getInputfield($page, $field);
				if(!$inputfield || !method_exists($inputfield, 'checkStreamStatus')) continue;
				$value = $page->get($field->name);
				$files = $value instanceof Pagefile
					? array($value)
					: ($value instanceof Pagefiles ? iterator_to_array($value) : array());
				foreach($files as $pagefile) {
					if((string)$pagefile->stream_uid !== (string)$row['stream_uid']) continue;
					$inputfield->checkStreamStatus($pagefile);
					break;
				}
			}
		}
	}

	/**
	 * Fail closed when private Stream playback would put expiring tokens in a
	 * shared L1/L2/edge document cache.
	 */
	public function hookCloudCacheIsCacheable(HookEvent $event) {
		if(!$event->return) return;
		if(!$this->lumen()->isSharedPageCacheSafe()) {
			$event->return = false;
		}
	}

	public function hookStreamUrl(HookEvent $event) {
		$event->return = $this->getStreamUrl($event->object);
	}

	public function hookStreamEmbed(HookEvent $event) {
		$event->return = $this->getStreamEmbed($event->object, $event->arguments(0) ?? 640, $event->arguments(1) ?? 360);
	}

	public function hookStreamThumbnail(HookEvent $event) {
		$event->return = $this->getStreamThumbnail($event->object, $event->arguments(0) ?? null);
	}

	public function hookStreamPreview(HookEvent $event) {
		$event->return = $this->getStreamPreview($event->object);
	}

	public function hookStreamReady(HookEvent $event) {
		$event->return = $this->isStreamReady($event->object);
	}

	public function hookLinkedPage(HookEvent $event) {
		$event->return = $this->getLinkedPage($event->object);
	}

	public function hookTags(HookEvent $event) {
		$event->return = $this->getTags($event->object);
	}

	public function hookStreamPoster(HookEvent $event) {
		$event->return = $this->getStreamPoster($event->object, $event->arguments(0) ?? null);
	}

	public function hookSubtitles(HookEvent $event) {
		$event->return = $this->getSubtitlesArray($event->object);
	}

	public function hookStreamDurationFormatted(HookEvent $event) {
		$event->return = $this->getStreamDurationFormatted($event->object);
	}

	public function hookStreamAspect(HookEvent $event) {
		$event->return = $this->getStreamAspect($event->object);
	}

	public function hookStreamEmbedResponsive(HookEvent $event) {
		$event->return = $this->getStreamEmbedResponsive($event->object, $event->arguments(0) ?? 640, $event->arguments(1) ?? 360);
	}
}
