<?php namespace ProcessWire;

trait LumenDiagnosticsTrait {
	public function isDebugMode() {
		return (bool) $this->debugMode;
	}

	public function eventLog($level, $event, array $context = array(), $force = false) {
		$level = strtolower((string) $level);
		if(!$force && !$this->isDebugMode() && !in_array($level, array('error', 'warning'), true)) {
			return;
		}

		unset($context['cfApiToken'], $context['apiToken'], $context['token'], $context['Authorization']);

		$message = '[' . strtoupper($level) . '] ' . (string) $event;
		if($context) {
			$message .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
		}

		$this->wire('log')->save('lumen-events', $message);
	}

	public function getEventLog($limit = 50) {
		$limit = max(1, min(200, (int) $limit));
		$file = $this->wire('config')->paths->logs . 'lumen-events.txt';
		if(!is_file($file)) return array();

		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if(!$lines) return array();
		return array_slice(array_reverse($lines), 0, $limit);
	}

	public function clearEventLog() {
		$file = $this->wire('config')->paths->logs . 'lumen-events.txt';
		if(is_file($file)) @unlink($file);
		$this->eventLog('info', 'Event log cleared', array(), true);
	}

	protected function cloudflareErrorMessage($response) {
		$data = json_decode((string) $response, true);
		if(!is_array($data)) return trim((string) $response);

		$messages = array();
		if(!empty($data['errors']) && is_array($data['errors'])) {
			foreach($data['errors'] as $error) {
				if(!empty($error['message'])) $messages[] = $error['message'];
				elseif(is_string($error)) $messages[] = $error;
			}
		}
		if(!empty($data['messages']) && is_array($data['messages'])) {
			foreach($data['messages'] as $message) {
				if(!empty($message['message'])) $messages[] = $message['message'];
				elseif(is_string($message)) $messages[] = $message;
			}
		}

		return $messages ? implode(' ', $messages) : trim((string) $response);
	}

	protected function verifyCloudflareToken($apiToken) {
		$http = $this->wire('modules')->get('WireHttp');
		$url = 'https://api.cloudflare.com/client/v4/user/tokens/verify';
		$http->setHeader('Authorization', "Bearer {$apiToken}");
		$response = $http->get($url);
		$httpCode = $http->getHttpCode();
		$data = json_decode((string) $response, true);
		$active = $httpCode === 200 && !empty($data['success']) && (($data['result']['status'] ?? '') === 'active');

		return array(
			'ok' => $active,
			'httpCode' => $httpCode,
			'message' => $this->cloudflareErrorMessage($response),
		);
	}

	/**
	 * Validate Cloudflare credentials by making a test API call
	 *
	 * Public method for programmatic use. Also called from ProcessLumen dashboard.
	 *
	 * @param string $accountId
	 * @param string $apiToken
	 * @return array ['valid' => bool, 'message' => string]
	 */
	public function validateCredentials($accountId, $apiToken) {
		if(empty($accountId) || empty($apiToken)) {
			return array('valid' => false, 'message' => $this->_('Account ID and API Token are required.'));
		}

		try {
			$http = $this->wire('modules')->get('WireHttp');
			if(!$http) {
				return array('valid' => false, 'message' => $this->_('HTTP client not available.'));
			}

			$tokenCheck = $this->verifyCloudflareToken($apiToken);
			if(!$tokenCheck['ok']) {
				$this->eventLog('error', 'Cloudflare token verification failed', array(
					'httpCode' => $tokenCheck['httpCode'],
					'message' => $tokenCheck['message'],
				), true);
				return array('valid' => false, 'message' => sprintf(
					$this->_('API token verification failed. HTTP %1$d. %2$s'),
					$tokenCheck['httpCode'],
					$tokenCheck['message']
				));
			}

			$this->eventLog('info', 'Cloudflare token verified', array('httpCode' => $tokenCheck['httpCode']), true);

			$url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/stream?limit=1";
			$http->setHeader('Authorization', "Bearer {$apiToken}");

			$response = $http->get($url);
			$httpCode = $http->getHttpCode();
			$errorMessage = $this->cloudflareErrorMessage($response);

			if($httpCode === 200) {
				$this->eventLog('info', 'Credentials verified successfully', array('httpCode' => $httpCode), true);
				return array('valid' => true, 'message' => $this->_('Credentials verified successfully.'));
			} elseif($httpCode === 401 || $httpCode === 403) {
				$this->eventLog('error', 'Credential validation failed: authentication or permission error', array(
					'httpCode' => $httpCode,
					'message' => $errorMessage,
				), true);
				return array('valid' => false, 'message' => sprintf(
					$this->_('Token is active, but Stream access failed. Check Stream Write / Stream:Edit permission. Cloudflare: %s'),
					$errorMessage
				));
			} elseif($httpCode === 404) {
				$this->eventLog('error', 'Credential validation failed: account not found', array(
					'httpCode' => $httpCode,
					'message' => $errorMessage,
				), true);
				return array('valid' => false, 'message' => $this->_('Account not found. Verify your Account ID.'));
			} elseif($httpCode === 400) {
				$this->eventLog('error', 'Credential validation failed: Stream endpoint returned HTTP 400', array(
					'httpCode' => $httpCode,
					'message' => $errorMessage,
				), true);
				return array('valid' => false, 'message' => sprintf(
					$this->_('Token is active, but Cloudflare Stream returned HTTP 400. Activate/finish the Images & Stream plan for this account, then try Test again. Cloudflare: %s'),
					$errorMessage
				));
			} else {
				$this->eventLog('error', 'Credential validation returned unexpected HTTP code', array(
					'httpCode' => $httpCode,
					'message' => $errorMessage,
				), true);
				return array('valid' => false, 'message' => sprintf(
					$this->_('Token is active, but Stream API returned HTTP %1$d. Cloudflare: %2$s'),
					$httpCode,
					$errorMessage
				));
			}
		} catch(\Exception $e) {
			$this->eventLog('error', 'Credential validation failed with exception', array('error' => $e->getMessage()), true);
			return array('valid' => false, 'message' => sprintf($this->_('Connection failed: %s'), $e->getMessage()));
		}
	}
}
