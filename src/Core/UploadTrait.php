<?php namespace ProcessWire;

trait LumenUploadTrait {
	/**
	 * Attach a trusted local video file to a Lumen field and start Stream upload.
	 *
	 * This is the shared integration API for modules that create video Pages
	 * outside ProcessWire's administrative file input.
	 */
	public function attachLocalVideo(Page $page, $fieldName, $sourcePath) {
		$fieldName = $this->wire('sanitizer')->fieldName((string) $fieldName);
		$sourcePath = (string) $sourcePath;
		if(!$page->id || !$fieldName || !$page->hasField($fieldName)) {
			throw new WireException($this->_('A saved Page with a Lumen field is required.'));
		}
		$field = $this->wire('fields')->get($fieldName);
		if(!$field || !($field->type instanceof FieldtypeLumen)) {
			throw new WireException($this->_('The selected field is not a Lumen video field.'));
		}
		if(!is_file($sourcePath) || !is_readable($sourcePath)) {
			throw new WireException($this->_('The source video is not readable.'));
		}

		$wasFormatted = $page->of();
		$page->of(false);
		try {
			$files = $page->get($fieldName);
			if(!$files instanceof Pagefiles) {
				throw new WireException($this->_('The Lumen field is unavailable on this Page.'));
			}
			if((int) $field->maxFiles === 1 && $files->count()) {
				throw new WireException($this->_('This Page already has a video.'));
			}
			$files->add($sourcePath);
			$page->save($fieldName);
			$pagefile = $files->last();
			if(!$pagefile instanceof Pagefile) {
				throw new WireException($this->_('The video could not be attached to the Page.'));
			}

			if(!$this->localStorage) {
				$inputfield = $field->type->getInputfield($page, $field);
				if(!$inputfield || !method_exists($inputfield, 'recoverMissingStreamUpload')) {
					throw new WireException($this->_('The Lumen upload service is unavailable.'));
				}
				if(!$inputfield->recoverMissingStreamUpload($pagefile)) {
					throw new WireException($this->_('Cloudflare Stream did not accept the video.'));
				}
			}
			$this->invalidatePageCache($page, 'video-attached');
			return $pagefile;
		} finally {
			$page->of($wasFormatted);
		}
	}

	/**
	 * Validate a browser upload, store it safely, and pass it to Lumen.
	 */
	public function attachUploadedVideo(Page $page, $fieldName, array $upload, $maxBytes = 1073741824) {
		$error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
		$tmp = (string) ($upload['tmp_name'] ?? '');
		$name = (string) ($upload['name'] ?? '');
		$size = (int) ($upload['size'] ?? 0);
		if($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
			throw new WireException($this->_('The video could not be uploaded.'));
		}
		if($size < 1 || $size > max(1, (int) $maxBytes)) {
			throw new WireException($this->_('The uploaded video exceeds the allowed size.'));
		}

		$mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
		$extensions = array(
			'video/mp4' => 'mp4',
			'video/webm' => 'webm',
			'video/quicktime' => 'mov',
			'video/x-m4v' => 'm4v',
		);
		if(!isset($extensions[$mime])) {
			throw new WireException($this->_('Use an MP4, WebM, MOV, or M4V video.'));
		}

		$directory = $this->wire('config')->paths->cache . 'Lumen/uploads/';
		if(!is_dir($directory) && !wireMkdir($directory, true)) {
			throw new WireException($this->_('The video upload cache is unavailable.'));
		}
		$base = $this->wire('sanitizer')->filename(pathinfo($name, PATHINFO_FILENAME)) ?: 'video';
		$copy = $directory . uniqid($base . '-', true) . '.' . $extensions[$mime];
		if(!move_uploaded_file($tmp, $copy)) {
			throw new WireException($this->_('The uploaded video could not be stored.'));
		}
		try {
			return $this->attachLocalVideo($page, $fieldName, $copy);
		} finally {
			if(is_file($copy)) @unlink($copy);
		}
	}
}
