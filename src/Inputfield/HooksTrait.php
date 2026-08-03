<?php namespace ProcessWire;

trait InputfieldLumenHooksTrait {
	// ---------------------------------------------------------------------------
	// Pagefile hooks — delegate to FieldtypeLumen's public methods
	// ---------------------------------------------------------------------------
	public function hookStreamUrl($event) {
	$event->return = $this->fieldtype()->getStreamUrl($event->object);
	}
	public function hookStreamEmbed($event) {
	$width = $event->arguments(0) ?? 640;
	$height = $event->arguments(1) ?? 360;
	$event->return = $this->fieldtype()->getStreamEmbed($event->object, $width, $height);
	}
	public function hookStreamThumbnail($event) {
	$timestamp = $event->arguments(0) ?? null;
	$event->return = $this->fieldtype()->getStreamThumbnail($event->object, $timestamp);
	}
	public function hookStreamPreview($event) {
	$event->return = $this->fieldtype()->getStreamPreview($event->object);
	}
	 public function hookStreamReady($event) {
	  $event->return = $this->fieldtype()->isStreamReady($event->object);
	 }
	 public function hookLinkedPage($event) {
	  $event->return = $this->fieldtype()->getLinkedPage($event->object);
	 }
	 public function hookTags($event) {
	  $event->return = $this->fieldtype()->getTags($event->object);
	 }
	 public function hookStreamPoster($event) {
	  $timestamp = $event->arguments(0) ?? null;
	  $event->return = $this->fieldtype()->getStreamPoster($event->object, $timestamp);
	 }
	 public function hookSubtitles($event) {
	  $event->return = $this->fieldtype()->getSubtitlesArray($event->object);
	 }
	 public function hookStreamDurationFormatted($event) {
	  $event->return = $this->fieldtype()->getStreamDurationFormatted($event->object);
	 }
	 public function hookStreamAspect($event) {
	  $event->return = $this->fieldtype()->getStreamAspect($event->object);
	 }
	 public function hookStreamEmbedResponsive($event) {
	  $width = $event->arguments(0) ?? 640;
	  $height = $event->arguments(1) ?? 360;
	  $event->return = $this->fieldtype()->getStreamEmbedResponsive($event->object, $width, $height);
	 }
	// ---------------------------------------------------------------------------
	// Database persistence — unified single entry point for all writes
	// ---------------------------------------------------------------------------
	/**
	 * Remove duplicate files (same basename) before page save
	 */
	public function hookPagesSaveReady($event) {
	    $page = $event->arguments(0);
	    foreach($page->fields as $field) {
	        if(!($field->type instanceof FieldtypeLumen)) continue;
	        $value = $page->get($field->name);
	        if(!($value instanceof Pagefiles)) continue;
	        $seen = array();
	        $toDelete = array();
	        foreach($value as $idx => $pf) {
	            $key = $pf->basename;
	            if(isset($seen[$key])) {
	                $toDelete[] = $idx;
	            } else {
	                $seen[$key] = true;
	            }
	        }
	        foreach(array_reverse($toDelete) as $idx) {
	            $page->{$field->name}->delete($idx);
	        }
	    }
	}
	/**
	 * Hook after page save to persist stream metadata to database
	 *
	 * ProcessWire saves file records during page save. This hook runs AFTER
	 * the save, so we can UPDATE the record with stream-specific fields.
	 */
	public function hookPagesSaved($event) {
	$page = $event->arguments(0);
	foreach($page->fields as $field) {
	if(!($field->type instanceof FieldtypeLumen)) continue;
	$value = $page->get($field->name);
	if(!($value instanceof Pagefiles)) continue;
	foreach($value as $pagefile) {
	if(!empty($pagefile->stream_uid)) {
	$this->saveStreamMetadata($pagefile);
	}
	}
	}
	}
	/**
	 * Single unified method for persisting all stream metadata to database
	 *
	 * Uses prepared statements exclusively. Reads current values from the
	 * Pagefile object and writes them to the field's database table.
	 *
	 * @param Pagefile $pagefile
	 */
	public function saveStreamMetadata(Pagefile $pagefile) {
	$database = $this->wire('database');
	$field = $pagefile->field;
	$page = $pagefile->page;
	$table = $field->getTable();
	   $sql = "UPDATE `{$table}` SET
	        stream_uid        = :stream_uid,
	        stream_status     = :stream_status,
	        stream_ready      = :stream_ready,
	        stream_duration   = :stream_duration,
	        stream_width      = :stream_width,
	        stream_height     = :stream_height,
	        stream_category   = :stream_category,
	        stream_tags       = :stream_tags,
	        stream_page_id    = :stream_page_id,
	        stream_poster     = :stream_poster,
	        stream_subtitles  = :stream_subtitles,
	        stream_trim_start = :stream_trim_start,
	        stream_trim_end   = :stream_trim_end,
	        stream_views      = :stream_views
	        WHERE pages_id = :pages_id
	        AND `data` = :basename";
	$stmt = $database->prepare($sql);
	   $values = array(
	        ':stream_uid'        => $pagefile->stream_uid ?: null,
	        ':stream_status'     => $pagefile->stream_status ?: 'queued',
	        ':stream_ready'      => $pagefile->stream_ready ? 1 : 0,
	        ':stream_duration'   => $pagefile->stream_duration ? (int)$pagefile->stream_duration : null,
	        ':stream_width'      => $pagefile->stream_width ? (int)$pagefile->stream_width : null,
	        ':stream_height'     => $pagefile->stream_height ? (int)$pagefile->stream_height : null,
	        ':stream_category'   => $pagefile->stream_category ?: null,
	        ':stream_tags'       => $pagefile->stream_tags ?: null,
	        ':stream_page_id'    => (int) $pagefile->stream_page_id,
	        ':stream_poster'     => $pagefile->stream_poster ?: null,
	        ':stream_subtitles'  => $pagefile->stream_subtitles ?: null,
	        ':stream_trim_start' => $pagefile->stream_trim_start !== null && $pagefile->stream_trim_start !== '' ? (float) $pagefile->stream_trim_start : null,
	        ':stream_trim_end'   => $pagefile->stream_trim_end !== null && $pagefile->stream_trim_end !== '' ? (float) $pagefile->stream_trim_end : null,
	        ':stream_views'      => (int) $pagefile->stream_views,
	        ':pages_id'          => (int)$page->id,
	        ':basename'          => $pagefile->basename,
	    );
	$exists = $database->prepare("SELECT COUNT(*) FROM `{$table}` WHERE pages_id = :pages_id AND `data` = :basename");
	$exists->execute(array(
	':pages_id' => (int)$page->id,
	':basename' => $pagefile->basename,
	));
	$saveTarget = $exists->fetchColumn() ? 'filename' : 'sort';
	if($saveTarget === 'sort') {
	$sql = "UPDATE `{$table}` SET
	        stream_uid        = :stream_uid,
	        stream_status     = :stream_status,
	        stream_ready      = :stream_ready,
	        stream_duration   = :stream_duration,
	        stream_width      = :stream_width,
	        stream_height     = :stream_height,
	        stream_category   = :stream_category,
	        stream_tags       = :stream_tags,
	        stream_page_id    = :stream_page_id,
	        stream_poster     = :stream_poster,
	        stream_subtitles  = :stream_subtitles,
	        stream_trim_start = :stream_trim_start,
	        stream_trim_end   = :stream_trim_end,
	        stream_views      = :stream_views
	        WHERE pages_id = :pages_id
	        AND `sort` = :sort";
	$stmt = $database->prepare($sql);
	$values[':sort'] = (int) $pagefile->sort;
	unset($values[':basename']);
	}
	$stmt->execute($values);
	$this->lumen()->eventLog('debug', 'Stream metadata saved', array(
	'file' => $pagefile->basename,
	'uid' => $pagefile->stream_uid,
	'status' => $pagefile->stream_status,
	'target' => $saveTarget,
	'rows' => $stmt->rowCount(),
	));
	}
	// ---------------------------------------------------------------------------
}
