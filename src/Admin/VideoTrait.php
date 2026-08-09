<?php namespace ProcessWire;

trait ProcessLumenVideoTrait {


	protected function renderVideoGallery($videos) {
		$copyScript = $this->renderCopyScript();
		$cards = '';
		foreach($videos as $idx => $v) {
			$cards .= $this->renderVideoCard($v['pf'], $v['page'], $v['field']);
		}

		if(!$cards) {
			return $copyScript . '<div class="uk-placeholder uk-text-center">'				. '<p class="uk-text-muted uk-margin-small-top">' . $this->_('No videos on this page.') . '</p>'
				. '</div>';
		}

		return $copyScript
			. '<div class="uk-child-width-1-2@s uk-child-width-1-3@m uk-child-width-1-4@l uk-grid-small uk-grid-match" uk-grid>' . $cards . '</div>';
	}


	protected function renderVideoPosterFallback($label = '') {
		$config = $this->wire('config');
		$moduleUrl = method_exists($config, 'urls') ? $config->urls($this) : '';
		if(!$moduleUrl) $moduleUrl = $config->urls->siteModules . 'Lumen/';

		$assetPath = dirname(__DIR__, 2) . '/assets/images/lumen-placeholder.png';
		$version = is_file($assetPath) ? filemtime($assetPath) : time();

		return $moduleUrl . 'assets/images/lumen-placeholder.png?v=' . $version;
	}


	protected function renderVideoCard($pf, $page, $field = null) {
		$s = $this->wire('sanitizer');
		$ft = $this->wire('modules')->get('FieldtypeLumen');
		$uid = $pf->stream_uid;
		$name = $s->entities($pf->basename);
		$pagePath = $s->entities($page->title ?: $page->path);

		// Unique ID for bulk selection: pageId_fieldName_sortIdx
		$sortIdx = $pf->sort;
		$fieldName = $field ? $field->name : ($pf->field ? $pf->field->name : '');
		$bulkId = $page->id . ':' . $fieldName . ':' . urlencode($pf->basename);

		    // Detail view URL
		$detailUrl = $this->adminSectionUrl('videos') . '?video=1&page_id=' . $page->id . '&field=' . urlencode($fieldName) . '&file=' . urlencode($pf->basename);
		$detailTitle = $s->entities(sprintf($this->_('View details: %s'), $pf->basename));

		// Status badge
		if($pf->stream_ready) {
			$badge = '<span class="uk-label uk-label-success">' . $s->entities($this->_('Ready')) . '</span>';
		} elseif($pf->stream_status === 'error') {
			$badge = '<span class="uk-label uk-label-danger">' . $s->entities($this->_('Error')) . '</span>';
		} elseif($uid) {
			$badge = '<span class="uk-label uk-label-warning">' . $s->entities($this->_('Processing')) . '</span>';
		} else {
			$badge = '<span class="uk-label">' . $s->entities($this->_('Pending')) . '</span>';
		}

		// Thumbnail / placeholder
		$fallbackThumb = $this->renderVideoPosterFallback($this->_('No preview'));
		if($uid) {
			$thumbnailUrl = $ft ? $ft->getStreamThumbnail($pf) : '';
			if(!$thumbnailUrl) $thumbnailUrl = $fallbackThumb;
			$thumb = '<div class="uk-cover-container" style="aspect-ratio: 16 / 9;">'
				. '<canvas width="16" height="9"></canvas>'
				. '<img src="' . $s->entities($thumbnailUrl) . '" alt="' . $name . '" loading="lazy" uk-cover '
				. 'onerror="this.onerror=null;this.src=\'' . $fallbackThumb . '\';">'
				. '</div>';
		} else {
			$thumb = '<div class="uk-cover-container" style="aspect-ratio: 16 / 9;">'
				. '<canvas width="16" height="9"></canvas>'
				. '<img src="' . $fallbackThumb . '" alt="' . $s->entities($this->_('No preview')) . '" loading="lazy" uk-cover>'
				. '</div>';
		}

		// Checkbox overlay
		$selectLabel = $s->entities(sprintf($this->_('Select %s'), $pf->basename));
		$checkbox = '<label class="uk-position-top-left uk-margin-small-left uk-margin-small-top">'
			. '<input type="checkbox" class="uk-checkbox LumenCheckbox" name="video_ids[]" value="' . $s->entities($bulkId) . '" '
			. 'title="' . $selectLabel . '" aria-label="' . $selectLabel . '" '
			. 'onchange="window.lumenUpdateBulkActions && window.lumenUpdateBulkActions()">'
			. '</label>';

		// Tech info
		$info = array();
		if($pf->stream_duration) {
			$info[] = $ft ? $ft->getStreamDurationFormatted($pf) : gmdate('i:s', $pf->stream_duration);
		}
		if($pf->stream_width && $pf->stream_height) {
			$info[] = $pf->stream_width . '×' . $pf->stream_height;
		}
		$info[] = wireBytesStr($pf->filesize);
		$techInfo = implode(' · ', $info);

		// Copy URL buttons (for ready videos)
		$copyButtons = '';
		if($uid && $pf->stream_ready) {
			$embedCode = '[[lumen:' . $uid . ']]';
			$streamUrl = $ft ? $ft->getStreamUrl($pf) : '';
			$copyEmbedLabel = $s->entities($this->_('Embed'));
			$copyCopiedLabel = $s->entities($this->_('Copied!'));
			$copyUrlLabel = $s->entities($this->_('URL'));
			$copyUrlTitle = $s->entities($this->_('Copy stream URL'));

			$copyButtons =
					'<div class="uk-margin-small-top uk-padding-small uk-background-muted">' .
						'<div class="uk-grid-small" uk-grid>' .
							// Embed shortcode
							'<div class="uk-width-expand">' .
								'<a href="#" class="uk-button uk-button-default uk-button-small uk-width-1-1 LumenCopyBtn" ' .
									'data-code="' . $s->entities($embedCode) . '" ' .
									'data-copy-label="' . $copyEmbedLabel . '" ' .
									'data-copy-copied="' . $copyCopiedLabel . '" ' .
									'aria-live="polite" onclick="return window.lumenCopyText(this)" title="' . $s->entities($this->_('Copy embed code')) . '">' .
									$copyEmbedLabel .
								'</a>' .
							'</div>' .
							// Stream URL
							'<div class="uk-width-expand">' .
								'<a href="#" class="uk-button uk-button-default uk-button-small uk-width-1-1 LumenCopyBtn" ' .
									'data-code="' . $s->entities($streamUrl) . '" ' .
									'data-copy-label="' . $copyUrlLabel . '" ' .
									'data-copy-copied="' . $copyCopiedLabel . '" ' .
									'aria-live="polite" onclick="return window.lumenCopyText(this)" title="' . $copyUrlTitle . '">' .
									$copyUrlLabel .
								'</a>' .
							'</div>' .
						'</div>' .
					'</div>';
		} elseif($uid) {
			$copyButtons = '<div class="uk-margin-small-top uk-padding-small uk-background-muted">' .
				'<p class="uk-text-small uk-text-muted uk-margin-remove">' .
				$s->entities($this->_('Copy links will appear when processing is complete.')) .
				'</p>' .
			'</div>';
		} else {
			$copyButtons = '<div class="lumen-card-note uk-text-small uk-text-muted">' .
				$s->entities($this->_('Local file · Stream links unavailable')) .
			'</div>';
		}

		// View count
		$viewInfo = '';
		$views = (int) $pf->stream_views;
			if($views > 0) {
				$viewInfo = '<span class="uk-text-small uk-text-muted">' .
					number_format($views) . ' ' . $s->entities($this->_('views')) .
					'</span> ';
			}

		$out =
			'<div>' .
			'<div class="uk-card uk-card-default uk-height-1-1">' .
			'<div class="uk-position-relative">' .
				$checkbox .
				'<a class="uk-link-reset" href="' . $detailUrl . '" title="' . $detailTitle . '">' . $thumb . '</a>' .
			'</div>' .
			'<div class="uk-card-body uk-padding-small">' .
			'<div class="uk-grid-small uk-flex-middle uk-margin-small-bottom" uk-grid>' .
			'<div class="uk-width-expand uk-text-truncate">' .
			'<a href="' . $detailUrl . '" class="uk-text-bold uk-text-small uk-link-reset" title="' . $detailTitle . '">' .
			$name . '</a>' .
			'</div>' .
			'<div class="uk-width-auto">' . $badge . '</div>' .
			'</div>' .
			'<div class="uk-text-muted uk-text-small uk-text-truncate uk-margin-small-bottom">' . $pagePath . '</div>' .
			'<div class="uk-text-muted uk-text-small">' . $viewInfo . $s->entities($techInfo) . '</div>';

		// Category badge
		if(!empty($pf->stream_category)) {
			$out .= '<div class="uk-margin-small-top">'
				. '<span class="uk-label uk-text-truncate">'
				. $s->entities($pf->stream_category)
				. '</span>'
				. '</div>';
		}

		// Tags
		if(!empty($pf->stream_tags)) {
			$out .= '<div class="uk-margin-small-top">';
			$tags = array_map('trim', explode(',', $pf->stream_tags));
			foreach($tags as $tag) {
				$tagUrl = $this->adminSectionUrl('videos') . '?tags=' . urlencode($tag);
				$out .= '<a href="' . $tagUrl . '" class="uk-label uk-label-default uk-link-reset uk-margin-small-right uk-margin-small-bottom">'
					. $s->entities($tag)
					. '</a>';
			}
			$out .= '</div>';
		}

		// Linked page
		if(!empty($pf->stream_page_id)) {
			$linkedPage = $this->wire('pages')->get((int)$pf->stream_page_id);
			if($linkedPage->id) {
					$out .= '<div class="uk-margin-small-top">'
						. '<a class="uk-link-reset" href="' . $linkedPage->url . '" target="_blank" '
						. 'class="uk-text-small uk-link-reset">'
						. $s->entities($linkedPage->title)
					. '</a>'
					. '</div>';
			}
		}

		$out .= $copyButtons
			. '</div>'
			. '</div>'
			. '</div>';

		return $out;
	}

	protected function handleVideoDetail() {
	    $pageId = (int) $this->input->get('page_id');
	    $fieldName = $this->input->get('field');
	    $fileName = $this->input->get('file');

	    if(!$pageId || !$fieldName || !$fileName) {
	        $this->error($this->_('Invalid video reference.'));
	        $this->session->redirect('./');
	        return '';
	    }

	    $page = $this->wire('pages')->get($pageId);
	    if(!$page->id) {
	        $this->error($this->_('Page not found.'));
	        $this->session->redirect('./');
	        return '';
	    }

	    $files = $this->videoFiles($page->get($fieldName));
	    if(!$files) {
	        $this->error($this->_('Video not found.'));
	        $this->session->redirect('./');
	        return '';
	    }

	    // Find by basename
	    $pf = null;
	    foreach($files as $f) {
	        if($f->basename === $fileName) { $pf = $f; break; }
	    }
	    if(!$pf) {
	        $this->error($this->_('Video not found.'));
	        $this->session->redirect('./');
	        return '';
	    }

	    return $this->renderVideoDetail($pf, $page);
	}

	protected function renderVideoDetail($pf, $page) {
	    $s = $this->wire('sanitizer');
	    $copyScript = $this->renderCopyScript();
	    $ft = $this->wire('modules')->get('FieldtypeLumen');
	    $uid = $pf->stream_uid;
	    $dashboardUrl = $this->adminSectionUrl('videos');
	    $dash = '<span class="uk-text-muted">—</span>';

	    // Status
		$statusLabel = $pf->stream_ready ? 'Ready' : ($pf->stream_status === 'error' ? 'Error' : ($uid ? 'Processing' : 'Pending'));
	    $statusClass = $pf->stream_ready ? 'uk-label-success' : ($pf->stream_status === 'error' ? 'uk-label-danger' : ($uid ? 'uk-label-warning' : ''));
	    $streamUrl = ($uid && $ft) ? $ft->getStreamUrl($pf) : '';
	    $previewUrl = ($uid && $ft) ? $ft->getStreamPreview($pf) : '';
	    $durationText = $pf->stream_duration ? $ft->getStreamDurationFormatted($pf) : '';
	    $resolutionText = '';
	    if($pf->stream_width && $pf->stream_height) {
	        $resolutionText = $pf->stream_width . '×' . $pf->stream_height;
	        $aspect = $ft->getStreamAspect($pf);
	        if($aspect) $resolutionText .= ' ' . $aspect;
	    }
	    $copyCopiedLabel = $s->entities($this->_('Copied!'));
	    $copyButton = function($label, $value) use ($s, $copyCopiedLabel) {
	        if(!$value) return '';
	        $safeLabel = $s->entities($label);
	        return '<a href="#" class="uk-button uk-button-default uk-button-small uk-width-1-1 LumenCopyBtn" '
	            . 'data-code="' . $s->entities($value) . '" '
	            . 'data-copy-label="' . $safeLabel . '" '
	            . 'data-copy-copied="' . $copyCopiedLabel . '" '
	            . 'aria-live="polite" onclick="return window.lumenCopyText(this)">' .
	            $safeLabel .
	        '</a>';
	    };
	    $detailRow = function($label, $value) use ($s, $dash) {
	        return '<dt class="uk-text-muted">' . $s->entities($label) . '</dt>' .
	            '<dd class="uk-text-break">' . ($value !== '' && $value !== null ? $value : $dash) . '</dd>';
	    };

	    $out = '<div class="lumen-video-detail">' .

	        // --- Top bar ---
	        '<div class="uk-grid-small uk-flex-middle uk-margin-bottom" uk-grid>' .
	            '<div class="uk-width-expand"><a href="' . $dashboardUrl . '" class="uk-button uk-button-default uk-button-small">' .
	                $s->entities($this->_('Video library')) .
	            '</a></div>' .
	            '<div class="uk-width-auto">' .
	                '<a href="' . $page->editUrl() . '" class="uk-button uk-button-primary uk-button-small">' .
	                    $s->entities($this->_('Edit Page')) .
	                '</a>' .
	            '</div>' .
	        '</div>' .

	        // --- Hero: compact preview + primary video data ---
	        '<div class="uk-card uk-card-default uk-card-body uk-margin-bottom">' .
	            '<div class="uk-grid-medium" uk-grid>' .
	                '<div class="uk-width-2-3@m">';

	    if($uid && $pf->stream_ready) {
	        $out .= '<div class="uk-cover-container" style="aspect-ratio: 16 / 9;">'
	            . $ft->getStreamEmbed($pf, 960, 540)
	            . '</div>';
	    } elseif($uid) {
	        $fallbackThumb = $this->renderVideoPosterFallback($this->_('No preview'));
	        $thumbnailUrl = $ft ? $ft->getStreamThumbnail($pf) : '';
	        if(!$thumbnailUrl) $thumbnailUrl = $fallbackThumb;
	        $out .= '<div class="uk-cover-container" style="aspect-ratio: 16 / 9;">'
	            . '<canvas width="16" height="9"></canvas>'
	            . '<img src="' . $s->entities($thumbnailUrl) . '" alt="' . $s->entities($pf->basename) . '" loading="lazy" uk-cover '
	            . 'onerror="this.onerror=null;this.src=\'' . $fallbackThumb . '\';">'
	            . '</div>';
	    } else {
	        $out .= '<div class="uk-cover-container" style="aspect-ratio: 16 / 9;">'
	            . '<canvas width="16" height="9"></canvas>'
	            . '<img src="' . $this->renderVideoPosterFallback($this->_('Local file — no preview')) . '" alt="' . $s->entities($this->_('Local file — no preview')) . '" loading="lazy" uk-cover>'
	            . '</div>';
	    }

	    $out .= '</div>' .
	                '<div class="uk-width-1-3@m">' .
	                    '<div class="uk-grid-small uk-flex-middle uk-margin-small-bottom" uk-grid>' .
	                        '<div class="uk-width-expand">' .
	                            '<h2 class="uk-margin-remove uk-text-large uk-text-break">' .
	                                $s->entities($pf->basename) .
	                            '</h2>' .
	                        '</div>' .
	                        '<div class="uk-width-auto"><span class="uk-label ' . $statusClass . '">' .
	                            $s->entities($this->_($statusLabel)) .
	                        '</span></div>' .
	                    '</div>' .
	                    '<p class="uk-text-muted uk-text-small uk-margin-small-top">' .
	                        '<a href="' . $page->editUrl() . '" class="uk-text-muted uk-link-reset">' .
	                            $s->entities($page->path) .
	                        '</a>' .
	                        ' · ' . wireBytesStr($pf->filesize) .
	                    '</p>' .
	                    '<dl class="uk-description-list uk-description-list-divider uk-margin-small">' .
	                        $detailRow($this->_('Stream UID'), $uid ? '<code>' . $s->entities($uid) . '</code>' : '') .
	                        $detailRow($this->_('Status'), $s->entities($this->_($statusLabel))) .
	                        $detailRow($this->_('HLS URL'), $streamUrl ? '<code class="uk-text-small lumen-hls-url">' . $s->entities($streamUrl) . '</code>' : '') .
	                        $detailRow($this->_('Duration'), $s->entities($durationText)) .
	                        $detailRow($this->_('Resolution'), $s->entities($resolutionText)) .
	                    '</dl>' .
	                    '<div class="uk-grid-small uk-child-width-1-1" uk-grid>' .
	                        ($uid ? '<div>' . $copyButton($this->_('Copy Stream UID'), $uid) . '</div>' : '') .
	                        ($streamUrl ? '<div>' . $copyButton($this->_('Copy HLS URL'), $streamUrl) . '</div>' : '') .
	                        ($previewUrl ? '<div><a href="' . $s->entities($previewUrl) . '" target="_blank" class="uk-button uk-button-default uk-button-small uk-width-1-1">' . $s->entities($this->_('Open Stream Preview')) . '</a></div>' : '') .
	                    '</div>' .
	                '</div>' .
	            '</div>' .
	        '</div>';

	    // --- Copy buttons (if ready) ---
	    if($uid && $pf->stream_ready) {
	        $embedCode = '[[lumen:' . $uid . ']]';
	        $copyEmbedLabel = $s->entities($this->_('Copy Embed'));
	        $copyUrlLabel = $s->entities($this->_('Copy HLS URL'));
	        $out .= '<div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-bottom">' .
	        '<div class="uk-grid-small" uk-grid>' .
	            '<div class="uk-width-expand">' .
	                '<a href="#" class="uk-button uk-button-default uk-button-small uk-width-1-1 LumenCopyBtn" ' .
	                    'data-code="' . $s->entities($embedCode) . '" ' .
	                    'data-copy-label="' . $copyEmbedLabel . '" ' .
		                    'data-copy-copied="' . $copyCopiedLabel . '" ' .
		                    'aria-live="polite" onclick="return window.lumenCopyText(this)" title="' . $s->entities($this->_('Copy embed code')) . '">' .
		                    $copyEmbedLabel .
	                '</a>' .
	            '</div>' .
	            '<div class="uk-width-expand">' .
	                '<a href="#" class="uk-button uk-button-default uk-button-small uk-width-1-1 LumenCopyBtn" ' .
	                    'data-code="' . $s->entities($streamUrl) . '" ' .
	                    'data-copy-label="' . $copyUrlLabel . '" ' .
		                    'data-copy-copied="' . $copyCopiedLabel . '" ' .
		                    'aria-live="polite" onclick="return window.lumenCopyText(this)" title="' . $s->entities($this->_('Copy HLS URL')) . '">' .
		                    $copyUrlLabel .
	                '</a>' .
	            '</div>' .
	        '</div>' .
	        '</div>';
	    } elseif($uid) {
	        $out .= '<div class="uk-alert-primary uk-margin-bottom" uk-alert>' .
	            '<p class="uk-margin-remove">' .
	                $s->entities($this->_('This video is still processing. Embed and HLS copy actions will appear when it is ready.')) .
	            '</p>' .
	        '</div>';
	    } else {
	        $out .= '<div class="uk-alert-primary uk-margin-bottom" uk-alert>' .
	            '<p class="uk-margin-remove">' .
	                $s->entities($this->_('This is a local file. Upload it to Stream to enable embed and HLS copy actions.')) .
	            '</p>' .
	        '</div>';
	    }

	    // --- Metadata cards ---
	    $out .= '<div class="uk-child-width-1-2@m uk-child-width-1-3@l uk-grid-small uk-grid-match" uk-grid>';

	    // Card helper
	    $card = function($title, $value) use ($s, $dash) {
	        $hasValue = strlen((string)$value) > 0;
	        $val = $hasValue ? $value : $dash;
	        $valueClass = $hasValue ? 'uk-text-bold' : '';
	        return '<div>' .
	            '<div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">' .
	                '<div class="uk-text-small uk-text-muted uk-margin-small-bottom">' .
	                    $s->entities($title) .
	                '</div>' .
	                '<div class="' . $valueClass . '">' . $val . '</div>' .
	            '</div>' .
	        '</div>';
	    };
	    $codeValue = function($value) use ($s) {
	        return '<code class="uk-text-small uk-text-break">' . $s->entities($value) . '</code>';
	    };

	    // Duration
	    $dur = $pf->stream_duration ? $ft->getStreamDurationFormatted($pf) : '';
	    $out .= $card($this->_('Duration'), $dur);

	    // Resolution
	    $res = '';
	    if($pf->stream_width && $pf->stream_height) {
	        $aspect = $ft->getStreamAspect($pf);
	        $res = $pf->stream_width . '×' . $pf->stream_height;
	        if($aspect) $res .= ' <span class="uk-text-muted">' . $s->entities($aspect) . '</span>';
	    }
	    $out .= $card($this->_('Resolution'), $res);

	    // Views
	    $viewsHtml = '';
	    if((int)$pf->stream_views > 0) {
	        $viewsHtml = number_format((int)$pf->stream_views) . ' ' . $s->entities($this->_('views'));
	    }
	    $out .= $card($this->_('Views'), $viewsHtml);

	    // Category
	    $catHtml = '';
	    if(!empty($pf->stream_category)) {
	        $catHtml = '<span class="uk-label uk-text-truncate">' . $s->entities($pf->stream_category) . '</span>';
	    }
	    $out .= $card($this->_('Category'), $catHtml);

	    // Tags
	    $tagsHtml = '';
	    if(!empty($pf->stream_tags)) {
	        foreach(array_map('trim', explode(',', $pf->stream_tags)) as $tag) {
	            $tagsHtml .= '<span class="uk-label uk-label-default uk-margin-small-right uk-margin-small-bottom">' . $s->entities($tag) . '</span>';
	        }
	    }
	    $out .= $card($this->_('Tags'), $tagsHtml);

	    // Linked page
	    $linkedHtml = '';
	    if(!empty($pf->stream_page_id)) {
	        $linked = $this->wire('pages')->get((int)$pf->stream_page_id);
		        if($linked->id) {
		            $linkedHtml = '<a href="' . $linked->url . '" target="_blank" class="uk-link-reset uk-text-truncate">' .
		                $s->entities($linked->title) . '</a>';
	        }
	    }
	    $out .= $card($this->_('Linked Page'), $linkedHtml);

	    // Poster
	    $posterHtml = '';
	    if(!empty($pf->stream_poster)) {
	        $posterHtml = $codeValue($pf->stream_poster);
	    }
	    $out .= $card($this->_('Custom Poster'), $posterHtml);

	    // Trim
	    $trimHtml = '';
	    if(($pf->stream_trim_start !== null && $pf->stream_trim_start !== '') || ($pf->stream_trim_end !== null && $pf->stream_trim_end !== '')) {
	        $t1 = ($pf->stream_trim_start !== null && $pf->stream_trim_start !== '') ? $pf->stream_trim_start . 's' : '0s';
	        $t2 = ($pf->stream_trim_end !== null && $pf->stream_trim_end !== '') ? $pf->stream_trim_end . 's' : 'end';
	        $trimHtml = $t1 . ' – ' . $t2;
	    }
	    $out .= $card($this->_('Trim'), $trimHtml);

	    // Subtitles
	    $subsHtml = '';
	    $subs = $ft->getSubtitlesArray($pf);
	    if($subs) {
	        foreach($subs as $sub) {
	            $subsHtml .= '<span class="uk-label uk-label-default uk-margin-small-right uk-margin-small-bottom">' .
	                $s->entities(($sub['label'] ?? $sub['srclang'] ?? '?')) . '</span>';
	        }
	    }
	    $out .= $card($this->_('Subtitles'), $subsHtml);

	    // Stream info (technical)
	    $out .= $card($this->_('Stream UID'), $uid ? $codeValue($uid) : '');
	    $out .= $card($this->_('Field'), $codeValue($pf->field ? $pf->field->name : ''));

	    // File URL (local)
	    if(!$uid) {
	        $out .= $card($this->_('File URL'), $codeValue($pf->url));
	    }

	    $out .= '</div>' .
	    '</div>';

	    return $copyScript . $out;
	}
}
