<?php namespace ProcessWire;

trait InputfieldLumenUploadTrait {
	// File lifecycle: added → upload → delete
	// ---------------------------------------------------------------------------
	/**
	 * Called after a file is successfully added
	 */
	protected function ___fileAdded(Pagefile $pagefile) {
	    if($this->noUpload) return;
	    $message = $this->_('Added video:') . " {$pagefile->basename}";
	    // Upload to Cloudflare Stream if not using local storage
	    if(!$this->localStorage) {
	        $this->lumen()->eventLog('debug', 'Starting Cloudflare Stream upload', array('file' => $pagefile->basename));
	        try {
	            $result = $this->uploadToStream($pagefile);
	            if($result && isset($result['uid'])) {
	                $pagefile->stream_uid = $result['uid'];
	                $pagefile->stream_status = 'inprogress';
	                $pagefile->stream_ready = 0;
	                $this->saveStreamMetadata($pagefile);
	                $this->lumen()->eventLog('info', 'Upload created Stream video', array(
	'file' => $pagefile->basename,
	'uid' => $result['uid'],
	'method' => $result['method'] ?? '',
	                ), true);
	                $message .= " - " . $this->_('Processing on Cloudflare Stream...');
	            } else {
	                throw new WireException("Failed to get UID from upload response");
	            }
	        } catch(\Exception $e) {
	            $errorMsg = "Cloudflare Stream Upload Error: " . $e->getMessage();
	            $this->lumen()->eventLog('error', 'Cloudflare Stream upload failed', array(
	'file' => $pagefile->basename,
	'error' => $e->getMessage(),
	            ), true);
	            $this->error($errorMsg);
	            $pagefile->stream_status = 'error';
	        }
	    } else {
	        $this->lumen()->eventLog('debug', 'Using local storage, skipping Stream upload', array('file' => $pagefile->basename));
	    }
	    // AJAX or regular response — handled here, not by parent
	    if($this->isAjax && !$this->noAjax) {
	        $pagefile->filesize = @filesize($pagefile->filename);
	        $n = count($this->value);
	        if($n) $n--;
	        $this->currentItem = $pagefile;
	        $markup = $this->fileAddedGetMarkup($pagefile, $n);
	        $this->ajaxResponse(false, $message, $pagefile->url, $pagefile->filesize, $markup);
	    } else {
	        $this->message($message);
	    }
	}
	/**
	 * Upload video to Cloudflare Stream — selects method based on file size
	 */
	protected function uploadToStream(Pagefile $pagefile) {
	$accountId = $this->cfAccountId;
	$apiToken = $this->cfApiToken;
	$this->lumen()->eventLog('debug', 'Upload configuration checked', array(
	'accountId' => $accountId ? 'set' : 'empty',
	'apiToken' => $apiToken ? 'set' : 'empty',
	));
	if(empty($accountId) || empty($apiToken)) {
	throw new WireException(
	$this->_('Cloudflare Stream credentials not configured. Please configure in Modules → Lumen.')
	);
	}
	$filePath = $pagefile->filename;
	$fileSize = filesize($filePath);
	$this->lumen()->eventLog('debug', 'Upload file inspected', array(
	'file' => $pagefile->basename,
	'sizeMb' => round($fileSize / 1024 / 1024, 2),
	));
	// Choose upload method based on file size
	if($fileSize < 200 * 1024 * 1024) { // < 200MB
	$this->lumen()->eventLog('debug', 'Using direct upload method', array('file' => $pagefile->basename));
	return $this->uploadDirect($filePath, $pagefile->basename);
	} else {
	$this->lumen()->eventLog('debug', 'Using TUS upload method', array('file' => $pagefile->basename));
	return $this->uploadTUS($filePath, $pagefile->basename);
	}
	}
	/**
	 * Upload an already saved local file to Cloudflare Stream when metadata is missing.
	 *
	 * This is used by ProcessLumen dashboard recovery: a page edit upload can finish
	 * successfully but still lose the transient stream_uid before the page save cycle
	 * writes custom Pagefile metadata. In that case the local file remains on disk and
	 * can be safely sent to Stream again from Refresh Status.
	 *
	 * @param Pagefile $pagefile
	 * @return bool True when a missing Stream UID was created and persisted
	 */
	public function recoverMissingStreamUpload(Pagefile $pagefile) {
	if($this->localStorage) return false;
	if(!empty($pagefile->stream_uid)) return false;
	if(empty($pagefile->filename) || !is_file($pagefile->filename)) return false;
	$existingUid = $this->findSavedStreamUid($pagefile);
	if($existingUid) {
	$pagefile->stream_uid = $existingUid;
	$pagefile->stream_status = $pagefile->stream_status ?: 'inprogress';
	$pagefile->stream_ready = 0;
	$this->saveStreamMetadata($pagefile);
	$this->lumen()->eventLog('info', 'Linked existing Stream upload from database', array(
	'file' => $pagefile->basename,
	'uid' => $existingUid,
	), true);
	return true;
	}
	$loggedUid = $this->getLoggedStreamUidForFile($pagefile->basename);
	if($loggedUid) {
	$pagefile->stream_uid = $loggedUid;
	$pagefile->stream_status = 'inprogress';
	$pagefile->stream_ready = 0;
	$this->saveStreamMetadata($pagefile);
	$this->lumen()->eventLog('info', 'Linked existing Stream upload from event log', array(
	'file' => $pagefile->basename,
	'uid' => $loggedUid,
	), true);
	return true;
	}
	$this->lumen()->eventLog('info', 'Recovering missing Stream upload', array(
	'file' => $pagefile->basename,
	'page' => $pagefile->page ? $pagefile->page->path : '',
	), true);
	$result = $this->uploadToStream($pagefile);
	if(!$result || empty($result['uid'])) return false;
	$pagefile->stream_uid = $result['uid'];
	$pagefile->stream_status = 'inprogress';
	$pagefile->stream_ready = 0;
	$this->saveStreamMetadata($pagefile);
	$this->lumen()->eventLog('info', 'Recovered missing Stream upload', array(
	'file' => $pagefile->basename,
	'uid' => $result['uid'],
	'method' => $result['method'] ?? '',
	), true);
	return true;
	}
	/**
	 * Find a Stream UID already saved for this file row.
	 *
	 * @param Pagefile $pagefile
	 * @return string
	 */
	protected function findSavedStreamUid(Pagefile $pagefile) {
	$field = $pagefile->field;
	$page = $pagefile->page;
	if(!$field || !$page || !$page->id) return '';
	$table = $field->getTable();
	$sql = "SELECT stream_uid FROM `{$table}` WHERE pages_id = :pages_id AND `data` = :basename AND stream_uid IS NOT NULL AND stream_uid != '' ORDER BY `sort` ASC LIMIT 1";
	$stmt = $this->wire('database')->prepare($sql);
	$stmt->execute(array(
	':pages_id' => (int) $page->id,
	':basename' => $pagefile->basename,
	));
	return (string) $stmt->fetchColumn();
	}
	/**
	 * Find the latest Stream UID already created for a filename in Lumen's event log.
	 *
	 * This prevents Refresh Status recovery from uploading the same local file again
	 * if metadata failed to persist after a previous successful direct upload.
	 *
	 * @param string $basename
	 * @return string
	 */
	protected function getLoggedStreamUidForFile($basename) {
	foreach($this->lumen()->getEventLog(200) as $line) {
	if(strpos($line, 'Stream upload') === false && strpos($line, 'Direct upload completed') === false) continue;
	$pos = strpos($line, ' | ');
	if($pos === false) continue;
	$context = json_decode(substr($line, $pos + 3), true);
	if(!is_array($context)) continue;
	if(($context['file'] ?? '') !== $basename) continue;
	$uid = (string) ($context['uid'] ?? '');
	if($uid !== '') return $uid;
	}
	return '';
	}
	/**
	 * Direct upload for files < 200MB
	 *
	 * Uses the Cloudflare Stream direct_upload endpoint.
	 * Step 1: POST /direct_upload → get {uploadURL, uid}
	 * Step 2: POST uploadURL with multipart/form-data file
	 */
	protected function uploadDirect($filePath, $filename) {
	$lumen = $this->lumen();
	$this->lumen()->eventLog('debug', 'Direct upload started', array('file' => $filename));
	// Step 1: Create direct upload URL
	$metadata = array(
	'maxDurationSeconds' => (int)$this->maxDurationSeconds,
	'meta' => array(
	'name' => $filename,
	),
	);
	if($this->requireSignedUrls) {
	$metadata['requireSignedURLs'] = true;
	}
	$this->lumen()->eventLog('debug', 'Creating direct upload URL', array(
	'file' => $filename,
	'maxDurationSeconds' => (int)$this->maxDurationSeconds,
	'requireSignedURLs' => (bool)$this->requireSignedUrls,
	));
	$result = $lumen->streamApiRequest('POST', '/stream/direct_upload', $metadata);
	$this->lumen()->eventLog('debug', 'Direct upload URL response', array(
	'file' => $filename,
	'httpCode' => $result['httpCode'],
	));
	if($result['httpCode'] !== 200) {
	throw new WireException(
	"Failed to create upload URL. HTTP {$result['httpCode']}: {$result['body']}"
	);
	}
	$data = json_decode($result['body'], true);
	if(!isset($data['result']['uploadURL'])) {
	throw new WireException("No uploadURL in response: {$result['body']}");
	}
	$uploadURL = $data['result']['uploadURL'];
	$uid = $data['result']['uid'];
	$this->lumen()->eventLog('debug', 'Direct upload URL created', array('file' => $filename, 'uid' => $uid));
	// Step 2: upload the file to the one-time URL. Remove the reserved Stream
	// item if transport fails, otherwise failed retries leave orphan videos.
	try {
		$uploadResult = $lumen->uploadMultipartFile($uploadURL, $filePath, $filename);
	} catch(\Exception $e) {
		try {
			$lumen->streamApiRequest('DELETE', '/stream/' . rawurlencode($uid));
		} catch(\Exception $cleanupError) {
			$lumen->eventLog('warning', 'Failed to clean up reserved Stream upload', array(
				'uid' => $uid,
				'error' => $cleanupError->getMessage(),
			), true);
		}
		throw $e;
	}
	$this->lumen()->eventLog('debug', 'Direct upload file response', array(
		'file' => $filename,
		'uid' => $uid,
		'httpCode' => $uploadResult['httpCode'],
	));
	$this->lumen()->eventLog('info', 'Direct upload completed', array('file' => $filename, 'uid' => $uid), true);
	return array(
	'uid' => $uid,
	'method' => self::UPLOAD_METHOD_DIRECT,
	);
	}
	/**
	 * TUS upload for files >= 200MB (resumable uploads)
	 *
	 * TUS is a lower-level protocol that WireHttp doesn't fully support,
	 * so we use PHP streams + low-level HTTP via stream_socket_client or
	 * keep cURL for this specific case.
	 *
	 * Uses cURL because TUS requires:
	 * - Custom headers (Tus-Resumable, Upload-Offset, etc.)
	 * - PATCH method
	 * - Binary chunk streaming with Content-Type: application/offset+octet-stream
	 *
	 * WireHttp does not support these TUS-specific requirements.
	 */
	protected function uploadTUS($filePath, $filename) {
	$accountId = $this->cfAccountId;
	$apiToken = $this->cfApiToken;
	$fileSize = filesize($filePath);
	// Step 1: Initiate TUS upload via Lumen's API helper
	$metadataParts = array(
	'name'               => base64_encode($filename),
	'maxDurationSeconds' => base64_encode((string)$this->maxDurationSeconds),
	);
	if($this->requireSignedUrls) {
	$metadataParts['requireSignedURLs'] = base64_encode('true');
	}
	$metadataHeaderItems = array();
	foreach($metadataParts as $key => $value) {
	$metadataHeaderItems[] = "{$key} {$value}";
	}
	$metadataString = implode(',', $metadataHeaderItems);
	// TUS initiation requires specific headers and header parsing,
	// which WireHttp does not provide. Fall back to cURL.
	$url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream";
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
	CURLOPT_POST           => true,
	CURLOPT_HTTPHEADER     => array(
	"Authorization: Bearer {$apiToken}",
	"Tus-Resumable: 1.0.0",
	"Upload-Length: {$fileSize}",
	"Upload-Metadata: {$metadataString}",
	),
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HEADER         => true,
	));
	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$curlError = curl_error($ch);
	curl_close($ch);
	if($curlError) {
	throw new WireException("TUS initiation failed: {$curlError}");
	}
	if($httpCode !== 201) {
	throw new WireException("Failed to initiate TUS upload. HTTP {$httpCode}");
	}
	// Extract Location header (upload URL)
	$headers = substr($response, 0, $headerSize);
	if(!preg_match('/Location:\s*(.+)/i', $headers, $matches)) {
	throw new WireException("No Location header in TUS response");
	}
	$uploadUrl = trim($matches[1]);
	// Prefer Cloudflare's stream-media-id response header. Fall back to URL parsing.
	$uid = null;
	if(preg_match('/stream-media-id:\s*([a-z0-9]+)/i', $headers, $mediaMatches)) {
	$uid = $mediaMatches[1];
	} elseif(preg_match('/\/stream\/([^\/\s?]+)/', $uploadUrl, $uidMatches)) {
	$uid = $uidMatches[1];
	}
	if(empty($uid)) {
	throw new WireException('Failed to parse stream UID from TUS response.');
	}
	// Step 2: Upload file in chunks using PATCH
	$offset = 0;
	$handle = fopen($filePath, 'rb');
	if(!$handle) {
	throw new WireException("Cannot open file for TUS upload: {$filePath}");
	}
	while($offset < $fileSize) {
	$chunk = fread($handle, self::TUS_CHUNK_SIZE);
	if($chunk === false || $chunk === '') {
	fclose($handle);
	throw new WireException("TUS chunk upload failed at offset {$offset}: failed to read source file.");
	}
	$chunkLength = strlen($chunk);
	$ch = curl_init($uploadUrl);
	curl_setopt_array($ch, array(
	CURLOPT_CUSTOMREQUEST  => 'PATCH',
	CURLOPT_HTTPHEADER     => array(
	"Authorization: Bearer {$apiToken}",
	"Tus-Resumable: 1.0.0",
	"Upload-Offset: {$offset}",
	"Content-Type: application/offset+octet-stream",
	"Content-Length: {$chunkLength}",
	),
	CURLOPT_POSTFIELDS     => $chunk,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_TIMEOUT        => 300,
	));
	$patchResponse = curl_exec($ch);
	$patchHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$patchError = curl_error($ch);
	curl_close($ch);
	if($patchError) {
	fclose($handle);
	throw new WireException("TUS chunk upload failed at offset {$offset}: {$patchError}");
	}
	if($patchHttpCode !== 204) {
	fclose($handle);
	throw new WireException(
	"TUS chunk upload failed at offset {$offset}. HTTP {$patchHttpCode}: {$patchResponse}"
	);
	}
	$offset += $chunkLength;
	$progress = round(($offset / $fileSize) * 100, 1);
	$this->lumen()->eventLog('debug', 'TUS upload progress', array(
	'file' => $filename,
	'uid' => $uid,
	'progress' => $progress,
	'offset' => $offset,
	'size' => $fileSize,
	));
	}
	fclose($handle);
	$this->lumen()->eventLog('info', 'TUS upload completed', array('file' => $filename, 'uid' => $uid), true);
	return array(
	'uid' => $uid,
	'method' => self::UPLOAD_METHOD_TUS,
	);
	}
	// ---------------------------------------------------------------------------
	// Status polling
	// ---------------------------------------------------------------------------
	/**
	 * Check and update video status from Cloudflare
	 *
	 * This should be called periodically (via ProcessLumen dashboard or LazyCron).
	 * Uses prepared statements exclusively for database writes.
	 *
	 * @param Pagefile $pagefile
	 * @return bool True if video is ready to stream
	 */
	public function checkStreamStatus(Pagefile $pagefile) {
	if(empty($pagefile->stream_uid)) {
	return false;
	}
	if($pagefile->stream_ready) {
	return true; // Already ready
	}
	$uid = $pagefile->stream_uid;
	try {
	$result = $this->lumen()->streamApiRequest('GET', "/stream/{$uid}");
	} catch(\Exception $e) {
	$this->lumen()->eventLog('error', 'Failed to check Stream status', array(
	'uid' => $uid,
	'error' => $e->getMessage(),
	), true);
	return false;
	}
	if($result['httpCode'] !== 200) {
	$this->lumen()->eventLog('error', 'Failed to check Stream status', array(
	'uid' => $uid,
	'httpCode' => $result['httpCode'],
	), true);
	return false;
	}
	$data = json_decode($result['body'], true);
	if(!isset($data['result'])) {
	return false;
	}
	$video = $data['result'];
	$before = array(
		(string)$pagefile->stream_status,
		(int)$pagefile->stream_ready,
		(int)$pagefile->stream_duration,
		(int)$pagefile->stream_width,
		(int)$pagefile->stream_height,
	);
	$this->lumen()->rememberCustomerCodeFromVideo($video);
	$readyToStream = !empty($video['readyToStream']);
	$cloudflareState = strtolower((string)($video['status']['state'] ?? ''));
	$hasError = $cloudflareState === 'error'
		|| !empty($video['status']['errorReasonCode'])
		|| !empty($video['status']['errorReasonText']);
	// Update object properties
	$pagefile->stream_status = $readyToStream ? 'ready' : ($hasError ? 'error' : ($cloudflareState ?: 'inprogress'));
	$pagefile->stream_ready = $readyToStream ? 1 : 0;
	if(isset($video['duration'])) {
	$pagefile->stream_duration = (int)$video['duration'];
	}
	if(isset($video['input']['width'])) {
	$pagefile->stream_width = (int)$video['input']['width'];
	}
	if(isset($video['input']['height'])) {
	$pagefile->stream_height = (int)$video['input']['height'];
	}
	// Persist to database — single unified method, prepared statement
	$this->saveStreamMetadata($pagefile);
	$after = array(
		(string)$pagefile->stream_status,
		(int)$pagefile->stream_ready,
		(int)$pagefile->stream_duration,
		(int)$pagefile->stream_width,
		(int)$pagefile->stream_height,
	);
	if($before !== $after) {
		$this->lumen()->invalidatePageCache($pagefile->page, 'stream-status');
	}
	if($readyToStream) {
	$this->lumen()->eventLog('info', 'Video ready to stream', array('uid' => $uid), true);
	} elseif($hasError) {
	$this->lumen()->eventLog('error', 'Cloudflare could not process video', array(
	'uid' => $uid,
	'code' => $video['status']['errorReasonCode'] ?? '',
	'reason' => $video['status']['errorReasonText'] ?? '',
	), true);
	}
	return $readyToStream;
	}
}
