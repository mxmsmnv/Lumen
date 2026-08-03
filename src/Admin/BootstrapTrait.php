<?php namespace ProcessWire;

trait ProcessLumenBootstrapTrait {
	/** Render the ProcessWire CSRF token for an administrative POST form. */
	protected function csrfInput() {
		return $this->wire('session')->CSRF->renderInput();
	}

	/** Reject a forged administrative mutation before dispatching it. */
	protected function validateCsrf() {
		$this->wire('session')->CSRF->validate();
	}

	public function init() {
		parent::init();
		$config = $this->wire('config');
		$moduleUrl = method_exists($config, 'urls') ? $config->urls($this) : '';
		if(!$moduleUrl) $moduleUrl = $config->urls->siteModules . 'Lumen/';
		$assetPath = __DIR__ . '/assets/css/lumen-admin.css';
		$version = is_file($assetPath) ? filemtime($assetPath) : time();
		$config->styles->add($moduleUrl . 'assets/css/lumen-admin.css?v=' . $version);
		$scriptPath = __DIR__ . '/assets/js/lumen-admin.js';
		$scriptVersion = is_file($scriptPath) ? filemtime($scriptPath) : time();
		$config->scripts->add($moduleUrl . 'assets/js/lumen-admin.js?v=' . $scriptVersion);
	}

	/**
	 * Normalize both single-file and multi-file Lumen field values.
	 */
	protected function videoFiles($value) {
		if($value instanceof Pagefile) return array($value);
		if($value instanceof Pagefiles) return iterator_to_array($value);
		return array();
	}

	protected function dashboardStatus(Pagefile $pagefile, $localStorage) {
		if(!$localStorage && empty($pagefile->stream_uid)) return 'pending';
		if($pagefile->stream_ready) return 'ready';
		if($pagefile->stream_status === 'error') return 'error';
		return $pagefile->stream_uid ? ($pagefile->stream_status ?: 'inprogress') : 'pending';
	}

	// ---------------------------------------------------------------------------
	// Connection status
	// ---------------------------------------------------------------------------

	protected function getConnectionStatus() {
	    $cache = $this->wire('cache');
	    $cached = $cache->get('lumen_connection_status');
	    if($cached !== null) return $cached;

	    /** @var Lumen $lumen */
	    $lumen = $this->wire('modules')->get('Lumen');

	    if(empty($lumen->cfAccountId) || empty($lumen->cfApiToken)) {
	        $result = array('ok' => false, 'status' => 'not_configured', 'message' => $this->_('Not configured'));
	    } elseif($lumen->localStorage) {
	        $result = array('ok' => true, 'status' => 'local', 'message' => $this->_('Local mode'));
	    } else {
	        $validation = $lumen->validateCredentials($lumen->cfAccountId, $lumen->cfApiToken);
	        $result = array(
	            'ok' => $validation['valid'],
	            'status' => $validation['valid'] ? 'connected' : 'invalid',
	            'message' => $validation['message']
	        );
	    }

	    $cache->save('lumen_connection_status', $result, WireCache::expireDaily);
	    return $result;
	}

	/**
	 * AJAX-friendly manual connection check
	 */
	public function ___executeTestConnection() {
	    $this->wire('cache')->delete('lumen_connection_status');
	    $status = $this->getConnectionStatus();
	    if($status['ok']) {
	        $this->message($status['message']);
	    } else {
	        $this->error($status['message']);
	    }
	    $this->session->redirect($this->page->url);
	}
}
