<?php namespace ProcessWire;

trait LumenStreamApiTrait {
	/**
	 * Make an authenticated HTTP request to Cloudflare Stream API
	 *
	 * @param string $method GET, POST, PATCH, DELETE
	 * @param string $path API path (e.g. "/stream" or "/stream/{uid}")
	 * @param array|string|null $body Request body (array for JSON, string for raw)
	 * @param array $extraHeaders Additional headers
	 * @return array ['httpCode' => int, 'body' => string, 'headers' => string|false]
	 * @throws WireException on connection failure
	 */
	public function streamApiRequest($method, $path, $body = null, $extraHeaders = array()) {
		$accountId = $this->cfAccountId;
		$apiToken = $this->cfApiToken;

		if(empty($accountId) || empty($apiToken)) {
			$this->eventLog('error', 'Cloudflare API request blocked: credentials missing', array(
				'method' => $method,
				'path' => $path,
			), true);
			throw new WireException($this->_('Cloudflare Stream credentials not configured.'));
		}

		$url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}{$path}";
		$this->eventLog('debug', 'Cloudflare API request', array(
			'method' => strtoupper($method),
			'path' => $path,
		));

		$http = $this->wire('modules')->get('WireHttp');

		$headers = array(
			'Authorization' => "Bearer {$apiToken}",
		);

		// Set content type for JSON bodies
		if(is_array($body)) {
			$headers['Content-Type'] = 'application/json';
			$body = json_encode($body);
		}

		foreach($extraHeaders as $key => $value) {
			if(is_int($key) && strpos($value, ':') !== false) {
				list($headerKey, $headerValue) = explode(':', $value, 2);
				$headers[trim($headerKey)] = trim($headerValue);
			} else {
				$headers[$key] = $value;
			}
		}
		$http->setHeaders($headers);

		// Configure timeout based on method
		$http->setTimeout($method === 'PATCH' ? 300 : 30);

		switch(strtoupper($method)) {
			case 'GET':
				$response = $http->get($url);
				break;
			case 'POST':
				$response = $http->post($url, $body ?: '');
				break;
			case 'DELETE':
				$response = $http->delete($url, $body ?: '');
				break;
			case 'PATCH':
				$response = $http->patch($url, $body ?: '');
				break;
			default:
				throw new WireException("Unsupported HTTP method: {$method}");
		}

		if($response === false) {
			$error = $http->getError();
			$this->eventLog('error', 'Cloudflare API request failed', array(
				'method' => strtoupper($method),
				'path' => $path,
				'error' => $error ?: 'Unknown error',
			), true);
			throw new WireException("HTTP request failed: " . ($error ?: 'Unknown error'));
		}

		$this->eventLog('debug', 'Cloudflare API response', array(
			'method' => strtoupper($method),
			'path' => $path,
			'httpCode' => $http->getHttpCode(),
		));

		return array(
			'httpCode' => $http->getHttpCode(),
			'body' => $response,
			'headers' => null, // WireHttp doesn't expose response headers directly
		);
	}

	/**
	 * Upload one file to a Cloudflare one-time direct upload URL.
	 *
	 * This deliberately lives in the central transport module. WireHttp has no
	 * stable multipart-file API across the supported ProcessWire releases.
	 */
	public function uploadMultipartFile($uploadUrl, $filePath, $filename = '') {
		$uploadUrl = trim((string) $uploadUrl);
		$filePath = (string) $filePath;
		$filename = trim((string) $filename) ?: basename($filePath);

		if(!filter_var($uploadUrl, FILTER_VALIDATE_URL) || stripos($uploadUrl, 'https://') !== 0) {
			throw new WireException($this->_('Cloudflare returned an invalid upload URL.'));
		}
		if(!is_file($filePath) || !is_readable($filePath)) {
			throw new WireException($this->_('The source video is not readable.'));
		}
		if(!function_exists('curl_init') || !function_exists('curl_file_create')) {
			throw new WireException($this->_('The PHP cURL extension is required for direct uploads.'));
		}

		$mimeType = function_exists('mime_content_type') ? @mime_content_type($filePath) : '';
		if(!$mimeType) $mimeType = 'application/octet-stream';

		$ch = curl_init($uploadUrl);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => array(
				'file' => curl_file_create($filePath, $mimeType, $filename),
			),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 30,
			CURLOPT_TIMEOUT => self::DIRECT_UPLOAD_TIMEOUT,
			CURLOPT_FOLLOWLOCATION => false,
		));

		$response = curl_exec($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		if(PHP_VERSION_ID < 80000) curl_close($ch);

		if($response === false) {
			throw new WireException(sprintf(
				$this->_('Cloudflare upload failed: %s'),
				$error ?: $this->_('unknown transport error')
			));
		}
		if($httpCode < 200 || $httpCode >= 300) {
			throw new WireException(sprintf(
				$this->_('Cloudflare upload returned HTTP %1$d: %2$s'),
				$httpCode,
				$this->cloudflareErrorMessage($response)
			));
		}

		return array('httpCode' => $httpCode, 'body' => (string) $response);
	}

	/**
	 * Delete one video from Cloudflare Stream.
	 *
	 * Local metadata should only be removed after this method succeeds so a
	 * failed API request remains safely retryable.
	 */
	public function deleteStreamVideo($uid) {
		$uid = trim((string) $uid);
		if($uid === '') {
			throw new WireException($this->_('A Stream video UID is required.'));
		}

		$result = $this->streamApiRequest('DELETE', '/stream/' . rawurlencode($uid));
		if($result['httpCode'] !== 200 && $result['httpCode'] !== 204) {
			throw new WireException(sprintf(
				$this->_('Failed to delete video from Stream. HTTP %1$d: %2$s'),
				$result['httpCode'],
				$this->cloudflareErrorMessage($result['body'])
			));
		}

		$this->eventLog('info', 'Deleted video from Stream', array('uid' => $uid), true);
		return true;
	}

	/**
	 * Get the Cloudflare customer code (subdomain) for building playback URLs
	 *
	 * Strategy (in order):
	 * 1. Manual override from module config
	 * 2. Cached value from ProcessWire cache (monthly)
	 * 3. Fetch from Stream API using the first available video
	 * Playback is unavailable until a valid customer code is discovered or
	 * configured. The Cloudflare Account ID is not a playback customer code.
	 */
	protected function normalizeCustomerCode($code) {
		$code = trim((string) $code);
		if($code === '') return '';

		if(preg_match('/(?:https?:\/\/)?(?:www\.)?([^\.\/\s]+)\.cloudflarestream\.com/', $code, $m)) {
			$code = $m[1];
		}

		$prefix = 'customer-';
		if(stripos($code, $prefix) === 0) {
			$code = substr($code, strlen($prefix));
		}

		if(preg_match('/customer-([a-z0-9_-]+)/i', $code, $m)) {
			$code = $m[1];
		}

		$code = strtolower($code);
		if(preg_match('/^[a-z0-9_-]+$/', $code)) return $code;

		return '';
	}

	public function getCustomerStreamHost() {
		$customerCode = $this->getCustomerCode();
		return $customerCode ? 'https://customer-' . $customerCode . '.cloudflarestream.com' : '';
	}

	/**
	 * Remember the playback customer code exposed by a Stream video response.
	 */
	public function rememberCustomerCodeFromVideo(array $video) {
		$candidates = array(
			$video['preview'] ?? '',
			$video['playback']['hls'] ?? '',
			$video['playback']['dash'] ?? '',
		);
		foreach($candidates as $candidate) {
			$code = $this->normalizeCustomerCode($candidate);
			if(!$code) continue;
			$this->wire('cache')->save('lumen_customer_code', $code, WireCache::expireMonthly);
			return $code;
		}
		return '';
	}

	public function getCustomerCode() {
		// 0. Manual override
		if(!empty($this->customerCodeOverride)) {
			$normalized = $this->normalizeCustomerCode($this->customerCodeOverride);
			if($normalized !== '') {
				$customerCode = $normalized;
				return $customerCode;
			}
		}

		static $customerCode = null;

		if($customerCode !== null) {
			return $customerCode;
		}

		// 1. ProcessWire cache
		$cache = $this->wire('cache');
		$customerCode = $cache->get('lumen_customer_code');
		if($customerCode) {
			$customerCode = $this->normalizeCustomerCode($customerCode);
			if($customerCode) {
				return $customerCode;
			}

			$customerCode = null;
		}

		$accountId = $this->cfAccountId;
		$apiToken = $this->cfApiToken;

		if(empty($accountId) || empty($apiToken)) {
			return '';
		}

		// 2. Try fetching from API
		try {
			$result = $this->streamApiRequest('GET', '/stream');

			if($result['httpCode'] === 200) {
				$data = json_decode($result['body'], true);

				if(!empty($data['result'][0]) && is_array($data['result'][0])) {
					$customerCode = $this->rememberCustomerCodeFromVideo($data['result'][0]);
					if($customerCode) return $customerCode;
				}
			}
		} catch(\Exception $e) {
			$this->eventLog('error', 'Failed to get customer code', array('error' => $e->getMessage()), true);
		}

		// Account ID and Stream customer code are different identifiers.
		return '';
	}

	/**
	 * Get the identifier to use in Stream playback URLs.
	 *
	 * Public videos use their UID directly. Private videos that require signed
	 * URLs use Cloudflare's /token endpoint; the returned token replaces the UID
	 * in player, HLS, thumbnail, and watch URLs.
	 */
	public function getStreamPlaybackIdentifier($uid) {
		$uid = trim((string) $uid);
		if($uid === '') return '';

		if(!$this->requireSignedUrls) {
			return $uid;
		}

		$cache = $this->wire('cache');
		$cacheKey = 'lumen_stream_token_' . md5($uid);
		$token = $cache->get($cacheKey);
		if($token) return $token;

		try {
			$result = $this->streamApiRequest('POST', '/stream/' . rawurlencode($uid) . '/token');
			if($result['httpCode'] === 200) {
				$data = json_decode($result['body'], true);
				if(!empty($data['result']['token'])) {
					$token = $data['result']['token'];
					$cache->save($cacheKey, $token, self::STREAM_TOKEN_CACHE_TTL);
					return $token;
				}
			}

			$this->eventLog('error', 'Failed to create signed Stream token', array(
				'uid' => $uid,
				'httpCode' => $result['httpCode'],
			), true);
		} catch(\Exception $e) {
			$this->eventLog('error', 'Failed to create signed Stream token', array(
				'uid' => $uid,
				'error' => $e->getMessage(),
			), true);
		}

		return '';
	}
}
