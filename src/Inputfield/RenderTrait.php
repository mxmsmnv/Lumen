<?php namespace ProcessWire;

trait InputfieldLumenRenderTrait {
	// ---------------------------------------------------------------------------
	// File deletion
	// ---------------------------------------------------------------------------
	/**
	 * Delete a file from Cloudflare Stream, then locally
	 */
	protected function ___processInputDeleteFile(Pagefile $pagefile) {
	// Delete from Stream first if applicable
	if(!$this->localStorage && !empty($pagefile->stream_uid)) {
	try {
	$this->lumen()->deleteStreamVideo($pagefile->stream_uid);
	} catch(\Exception $e) {
	$this->lumen()->eventLog('error', 'Failed to delete video from Stream', array(
	'uid' => $pagefile->stream_uid,
	'error' => $e->getMessage(),
	), true);
	$this->error($this->_('Cloudflare did not confirm video deletion. The local video was kept so you can try again.'));
	return false;
	}
	}
	// Then process local deletion
	return parent::___processInputDeleteFile($pagefile);
	}
	// ---------------------------------------------------------------------------
	// Admin UI rendering
	// ---------------------------------------------------------------------------
	/**
	 * Render markup for a file item in admin
	 *
	 * Uses native ProcessWire/Inputfield markup and UIkit label classes
	 * (uk-label-success, uk-label-warning, uk-label-danger).
	 */
	protected function ___renderItem($pagefile, $id, $n) {
	$displayName = $this->getDisplayBasename($pagefile);
	$sanitizer = $this->wire('sanitizer');
	// Choose URL and status badge based on storage and processing state
	if($this->localStorage) {
	$url = $pagefile->url;
	$statusBadge = '';
	} elseif(empty($pagefile->stream_uid)) {
	$url = '#';
	$statusBadge = '<span class="uk-label uk-label-warning">'
	. $sanitizer->entities($this->_('Pending Upload'))
	. '</span>';
	} elseif($pagefile->stream_ready) {
	$url = $pagefile->streamPreview();
	$statusBadge = '<span class="uk-label uk-label-success">'
	. $sanitizer->entities($this->_('Ready'))
	. '</span>';
	} elseif($pagefile->stream_status === 'error') {
	$url = '#';
	$statusBadge = '<span class="uk-label uk-label-danger">'
	. $sanitizer->entities($this->_('Error'))
	. '</span>';
	} else {
	$url = '#';
	$statusBadge = '<span class="uk-label">'
	. $sanitizer->entities($this->_('Processing…'))
	. '</span>';
	}
	$out = "<p class='InputfieldFileInfo InputfieldItemHeader ui-state-default'>" .
	"<a class='InputfieldFileName uk-link-reset' title='" . $sanitizer->entities($pagefile->basename) . "' " .
	($url !== '#' ? "target='_blank' " : "") .
	"href='" . $sanitizer->entities($url) . "'>{$displayName}</a> " .
	$statusBadge . " " .
	"<span class='InputfieldFileStats'>" . wireBytesStr($pagefile->filesize) . "</span> ";
	// Duration if available
	if(!empty($pagefile->stream_duration)) {
	$formatted = $this->fieldtype()->getStreamDurationFormatted($pagefile);
	$out .= "<span class='InputfieldFileStats'>" . $sanitizer->entities($formatted) . "</span> ";
	}
	// Resolution if available
	if(!empty($pagefile->stream_width) && !empty($pagefile->stream_height)) {
	$aspect = $this->fieldtype()->getStreamAspect($pagefile);
	$res = $pagefile->stream_width . 'x' . $pagefile->stream_height;
	if($aspect) $res .= ' (' . $aspect . ')';
	$out .= "<span class='InputfieldFileStats'>{$res}</span> ";
	}
	// View count
	$views = (int) $pagefile->stream_views;
	if($views > 0) {
	$out .= "<span class='InputfieldFileStats' title='" . $sanitizer->entities($this->_('Views')) . "'>"
	. number_format($views)
	. "</span> ";
	}
	// Subtitles indicator
	$subs = $this->fieldtype()->getSubtitlesArray($pagefile);
	if($subs) {
	$out .= "<span class='InputfieldFileStats' title='"
	. $sanitizer->entities(implode(', ', array_column($subs, 'srclang')))
	. "'>CC</span> ";
	}
	   $out .= $this->renderItemDescriptionField($pagefile, $id, $n);
	   // Category badge
	   if(!empty($pagefile->stream_category)) {
	       $out .= "<span class='uk-label'>"
	           . $sanitizer->entities($pagefile->stream_category)
	           . "</span> ";
	   }
	   // Tags
	   if(!empty($pagefile->stream_tags)) {
	       $tags = array_map('trim', explode(',', $pagefile->stream_tags));
	       foreach($tags as $tag) {
	           $out .= "<span class='InputfieldFileStats'>"
	               . $sanitizer->entities($tag)
	               . "</span> ";
	       }
	   }
	   // Linked page
	   if(!empty($pagefile->stream_page_id)) {
	       $linkedPage = $this->wire('pages')->get((int)$pagefile->stream_page_id);
	       if($linkedPage->id) {
	           $out .= "<a class='InputfieldFileStats uk-link-reset' href='{$linkedPage->url}' target='_blank' "
	               . "title='" . $sanitizer->entities($linkedPage->title) . "'"
	               . ">"
	               . $sanitizer->entities($linkedPage->title)
	               . "</a> ";
	       }
	   }
	   $out .= "</p>";
	return $out;
	}
	/**
	 * Get config for this Inputfield
	 */
	public function ___getConfigInputfields() {
	$inputfields = parent::___getConfigInputfields();
	// Remove irrelevant settings
	$inputfields->remove('extensions');
	// Add Stream-specific message
	$f = $this->wire('modules')->get('InputfieldMarkup');
	$f->label = __('Cloudflare Stream Configuration');
	$f->value = __('Video upload settings are configured in the Lumen module configuration.');
	$inputfields->prepend($f);
	return $inputfields;
	}
}
