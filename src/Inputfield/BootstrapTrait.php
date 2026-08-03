<?php namespace ProcessWire;

trait InputfieldLumenBootstrapTrait {
	public function __construct() {
	parent::__construct();
	// Default configuration values (overridden by Lumen module at init)
	$this->set('cfAccountId', '');
	$this->set('cfApiToken', '');
	$this->set('requireSignedUrls', false);
	$this->set('maxDurationSeconds', 3600);
	$this->set('localStorage', false);
	}
	/**
	 * Get the central Lumen module
	 *
	 * @return Lumen
	 */
	public function lumen() {
	return $this->wire('modules')->get('Lumen');
	}
	/**
	 * Get the FieldtypeLumen module
	 *
	 * @return FieldtypeLumen
	 */
	public function fieldtype() {
	    return $this->wire('modules')->get('FieldtypeLumen');
	}
	/**
	 * Fix extensions on existing Lumen fields — run once, cached daily
	 */
	protected function fixFieldExtensions() {
	    $cache = $this->wire('cache');
	    $cacheKey = 'lumen_extensions_fixed';
	    if($cache->get($cacheKey)) return;
	    $videoExt = 'mp4 mkv mov avi flv ts ps mxf lxf gxf 3gp webm mpg';
	    foreach($this->wire('fields') as $field) {
	        if(!($field->type instanceof FieldtypeLumen)) continue;
	        if($field->get('extensions') === $videoExt) continue;
	        try {
	            $field->set('extensions', $videoExt);
	            $field->save();
	            $this->lumen()->eventLog('debug', 'Fixed Lumen field extensions', array('field' => $field->name));
	        } catch(\Exception $e) {
	            // quiet
	        }
	    }
	    $cache->save($cacheKey, true, WireCache::expireDaily);
	}
	/**
	 * Make InputfieldFile assets available
	 */
	public function renderReady(?Inputfield $parent = null, $renderValueMode = false) {
	$inputfieldFile = $this->wire('modules')->get('InputfieldFile');
	if($inputfieldFile) {
	$inputfieldFile->renderReady();
	}
	return parent::renderReady($parent, $renderValueMode);
	}
	public function init() {
	    parent::init();
	    // Restrict to video files only
	    $this->extensions = 'mp4 mkv mov avi flv ts ps mxf lxf gxf 3gp webm mpg';
	    // Fix existing fields that have wrong extensions from install
	    $this->fixFieldExtensions();
	    // Set max file size to 30GB (Cloudflare Stream limit)
	    $this->maxFilesize = 30 * 1024 * 1024 * 1024; // 30GB
	// Deduplicate files before page save
	   $this->addHookBefore('Pages::saveReady', $this, 'hookPagesSaveReady');
	}
}
