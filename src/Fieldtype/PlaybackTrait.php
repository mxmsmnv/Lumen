<?php namespace ProcessWire;

trait FieldtypeLumenPlaybackTrait {
	/**
	 * Get HLS stream URL for a Pagefile
	 *
	 * Public API that InputfieldLumen's hooks delegate to.
	 */
	public function getStreamUrl(Pagefile $pagefile) {
		if($this->lumen()->localStorage) {
			return $pagefile->url;
		}

		$uid = $pagefile->stream_uid;
		if(empty($uid)) {
			return '';
		}

		$lumen = $this->lumen();
		$playbackId = $lumen->getStreamPlaybackIdentifier($uid);
		if($playbackId === '') return '';
		$host = $lumen->getCustomerStreamHost();
		if($host === '') return '';

		return "{$host}/{$playbackId}/manifest/video.m3u8";
	}

	/**
	 * Get embed iframe HTML for a Pagefile
	 */
	public function getStreamEmbed(Pagefile $pagefile, $width = 640, $height = 360) {
		$uid = $pagefile->stream_uid;

		if(empty($uid)) {
			return '';
		}

		$lumen = $this->lumen();
		$playbackId = $lumen->getStreamPlaybackIdentifier($uid);
		if($playbackId === '') return '';
		$host = $lumen->getCustomerStreamHost();
		if($host === '') return '';

		$width = (int)$width;
		$height = (int)$height;
		$title = trim((string) ($pagefile->description ?: $pagefile->basename));
		if($title === '') $title = $this->_('Video');

		// Build URL with optional trim parameters
		$src = $host . '/'
			. $this->wire('sanitizer')->entities($playbackId) . '/iframe';

		$params = array();
		$trimStart = $pagefile->stream_trim_start;
		$trimEnd = $pagefile->stream_trim_end;
		if($trimStart !== null && $trimStart >= 0) $params[] = 'start=' . (float)$trimStart . 's';
		if($trimEnd !== null && $trimEnd > 0) $params[] = 'end=' . (float)$trimEnd . 's';
		if($params) $src .= '?' . implode('&', $params);

		$iframe = '<iframe src="' . $src . '" '
		          . 'title="' . $this->wire('sanitizer')->entities($title) . '" '
		          . 'style="border: none;" '
		          . 'height="' . $height . '" '
				  . 'width="' . $width . '" '
				  . 'loading="lazy" '
				  . 'allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" '
				  . 'allowfullscreen="true"'
				  . '></iframe>';

		return $iframe;
	}

	/**
	 * Get thumbnail URL for a Pagefile
	 */
	public function getStreamThumbnail(Pagefile $pagefile, $timestamp = null) {
		$uid = $pagefile->stream_uid;

		if(empty($uid)) {
			return '';
		}

		$lumen = $this->lumen();
		$playbackId = $lumen->getStreamPlaybackIdentifier($uid);
		if($playbackId === '') return '';
		$host = $lumen->getCustomerStreamHost();
		if($host === '') return '';

		$url = "{$host}/{$playbackId}/thumbnails/thumbnail.jpg";
		if($timestamp !== null && $timestamp >= 0) {
			$url .= "?time=" . (float)$timestamp . "s";
		}

		return $url;
	}

	/**
	 * Get preview/watch URL for a Pagefile
	 */
	public function getStreamPreview(Pagefile $pagefile) {
		$uid = $pagefile->stream_uid;

		if(empty($uid)) {
			return '';
		}

		$lumen = $this->lumen();
		$playbackId = $lumen->getStreamPlaybackIdentifier($uid);
		if($playbackId === '') return '';
		$host = $lumen->getCustomerStreamHost();
		if($host === '') return '';

		return "{$host}/{$playbackId}/watch";
	}

  /**
   * Get linked ProcessWire page for a video
   *
   * @param Pagefile $pagefile
   * @return Page|null Returns Page object or null if not linked
   */
  public function getLinkedPage(Pagefile $pagefile) {
      $pageId = (int) $pagefile->stream_page_id;
      if($pageId < 1) return null;
      $p = $this->wire('pages')->get($pageId);
      return $p->id ? $p : null;
  }

  /**
   * Get tags as an array
   *
   * @param Pagefile $pagefile
   * @return array
   */
	public function getTags(Pagefile $pagefile) {
		$tags = $pagefile->stream_tags;
		if(empty($tags)) return array();
		return array_map('trim', explode(',', $tags));
	}

	/**
	 * Get custom poster URL (or auto-generated thumbnail)
	 */
	public function getStreamPoster(Pagefile $pagefile, $timestamp = null) {
		if(!empty($pagefile->stream_poster)) {
			return $pagefile->stream_poster;
		}
		return $this->getStreamThumbnail($pagefile, $timestamp);
	}

	/**
	 * Get subtitles as array of [src, srclang, label]
	 */
	public function getSubtitlesArray(Pagefile $pagefile) {
		$raw = $pagefile->stream_subtitles;
		if(empty($raw)) return array();
		$data = json_decode($raw, true);
		return is_array($data) ? $data : array();
	}

	/**
	 * Get formatted duration string (e.g. "1:23" or "1:02:45")
	 */
	public function getStreamDurationFormatted(Pagefile $pagefile) {
		$sec = (int) $pagefile->stream_duration;
		if($sec <= 0) return '0:00';
		$h = floor($sec / 3600);
		$m = floor(($sec % 3600) / 60);
		$s = $sec % 60;
		if($h > 0) {
			return $h . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
		}
		return $m . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Get aspect ratio string (e.g. "16:9", "4:3", "21:9")
	 */
	public function getStreamAspect(Pagefile $pagefile) {
		$w = (int) $pagefile->stream_width;
		$h = (int) $pagefile->stream_height;
		if($w <= 0 || $h <= 0) return '';

		// Common aspect ratios
		$ratio = $w / $h;
		$ratios = array(
			'21:9' => 21/9,
			'16:9' => 16/9,
			'4:3'  => 4/3,
			'1:1'  => 1,
			'9:16' => 9/16,
		);
		foreach($ratios as $label => $val) {
			if(abs($ratio - $val) < 0.02) return $label;
		}
		return round($ratio, 2) . ':1';
	}

	/**
	 * Get responsive embed HTML (16:9 wrapper)
	 */
	public function getStreamEmbedResponsive(Pagefile $pagefile, $width = 640, $height = 360) {
		$iframe = $this->getStreamEmbed($pagefile, $width, $height);
		if(empty($iframe)) return '';
		$iframe = preg_replace(
			'/style="[^"]*"/',
			'style="border:none;position:absolute;inset:0;width:100%;height:100%"',
			$iframe,
			1
		);
		return '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;max-width:100%">'
			. $iframe
			. '</div>';
	}

  /**
   * Check if video is ready to stream
   */
	public function isStreamReady(Pagefile $pagefile) {
		return (bool) $pagefile->stream_ready;
	}

}
