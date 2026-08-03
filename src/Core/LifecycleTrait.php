<?php namespace ProcessWire;

trait LumenLifecycleTrait {
	/**
	 * Construct — default configuration
	 */
	public function __construct() {
	    parent::__construct();

	    $this->set('cfAccountId', '');
	    $this->set('cfApiToken', '');
	    $this->set('requireSignedUrls', false);
	    $this->set('maxDurationSeconds', 3600); // 1 hour default
	    $this->set('localStorage', false);
	    $this->set('debugMode', false);
	    $this->set('customerCodeOverride', '');
	}

	public function init() {
	    // Invalidate stale connection-status cache when credentials are configured
	    if(!empty($this->cfAccountId) && !empty($this->cfApiToken)) {
	        $cached = $this->wire('cache')->get('lumen_connection_status');
	        if($cached && isset($cached['status']) && $cached['status'] === 'not_configured') {
	            $this->wire('cache')->delete('lumen_connection_status');
	        }
	    }
	}

	/**
	 * Get the shared Stream configuration as an array
	 */
	public function streamConfig() {
		return array(
			'cfAccountId' => $this->cfAccountId,
			'cfApiToken' => $this->cfApiToken,
			'requireSignedUrls' => (bool) $this->requireSignedUrls,
			'maxDurationSeconds' => (int) $this->maxDurationSeconds,
			'localStorage' => (bool) $this->localStorage,
			'debugMode' => (bool) $this->debugMode,
			'customerCodeOverride' => $this->customerCodeOverride,
		);
	}

	/**
	 * Whether rendered playback URLs are stable enough for a shared HTML cache.
	 *
	 * Remote private Stream playback uses expiring tokens. A cached document
	 * could otherwise outlive its token and expose a broken player. Local files
	 * and public Stream UIDs are stable.
	 */
	public function isSharedPageCacheSafe() {
		return (bool) $this->localStorage || !(bool) $this->requireSignedUrls;
	}

	/**
	 * Invalidate public page caches after a metadata-only Stream mutation.
	 */
	public function invalidatePageCache(Page $page, $trigger = 'metadata') {
		if(!$page->id || !$this->wire('modules')->isInstalled('CloudCache')) return false;
		$cloudCache = $this->wire('modules')->get('CloudCache');
		if(!$cloudCache || !method_exists($cloudCache, 'invalidatePage')) return false;
		$cloudCache->invalidatePage($page, 'lumen-' . (string)$trigger);
		return true;
	}

	/**
	 * Return the decoded source orientation reported by Cloudflare Stream.
	 *
	 * @return string portrait, landscape, square, or unknown
	 */
	public function streamOrientation(Pagefile $pagefile) {
		$width = (int) $pagefile->stream_width;
		$height = (int) $pagefile->stream_height;
		if($width <= 0 || $height <= 0) return 'unknown';
		if($height > $width) return 'portrait';
		if($width > $height) return 'landscape';
		return 'square';
	}

	/**
	 * Whether a Stream file is eligible for a vertical short-form feed.
	 *
	 * Unknown, still-processing, landscape, and square files fail closed.
	 */
	public function isShortFormVideo(Pagefile $pagefile, $maxDurationSeconds = 120, $requireReady = true) {
		$duration = (int) $pagefile->stream_duration;
		$maximum = max(1, (int) $maxDurationSeconds);
		if($requireReady && !(bool) $pagefile->stream_ready) return false;
		return $duration > 0
			&& $duration <= $maximum
			&& $this->streamOrientation($pagefile) === 'portrait';
	}
}
