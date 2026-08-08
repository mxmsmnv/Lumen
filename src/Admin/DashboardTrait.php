<?php namespace ProcessWire;

trait ProcessLumenDashboardTrait {

	protected function renderAdminNav() {
		$s = $this->wire('sanitizer');
		$items = array(
			'overview' => $this->_('Overview'),
			'library' => $this->_('Videos'),
			'upload' => $this->_('Upload'),
			'settings' => $this->_('Settings'),
			'usage' => $this->_('Usage'),
			'event-log' => $this->_('Event Log'),
		);
		$out = '<nav class="lumen-admin-nav uk-margin-medium-bottom" aria-label="' .
			$s->entities($this->_('Lumen sections')) . '"><ul class="uk-subnav uk-subnav-pill uk-margin-remove">';
		foreach($items as $id => $label) {
			$out .= '<li' . ($id === 'overview' ? ' class="uk-active"' : '') . '><a href="#' . $id . '">' .
				$s->entities($label) . '</a></li>';
		}
		return $out . '</ul></nav>';
	}

	protected function renderEmptyState() {
		$conn = $this->getConnectionStatus();
		$adminUrl = $this->wire('config')->urls->admin;

		$out = '<div class="pw-wrap lumen-admin">' .
			$this->renderAdminNav() .

			'<div class="lumen-empty-state uk-text-center uk-padding-large">' .

				'<h2 class="uk-margin-small-top">' .
					$this->_('Welcome to Lumen') .
				'</h2>' .

				'<p class="uk-text-muted uk-width-medium uk-margin-auto">' .
					$this->_('Cloudflare Stream video hosting for ProcessWire. Create your first video field to get started.') .
				'</p>' .

				'<div class="uk-flex uk-flex-center uk-flex-middle uk-flex-wrap uk-margin-top" uk-grid>' .
					'<a href="' . $this->wire('config')->urls->admin . 'setup/lumen/add-field/" ' .
						'class="uk-button uk-button-primary">' .
						$this->_('Create Video Field') .
					'</a>' .
					'<a href="' . $adminUrl . 'module/edit?name=Lumen" ' .
						'class="uk-button uk-button-default">' .
						$this->_('Configure Module') .
					'</a>' .
				'</div>' .

				'<div class="uk-margin-top uk-flex uk-flex-center">' .
					$this->renderConnectionBadge($conn) .
				'</div>' .

			'</div>' .

			'</div>';

		return $out;
	}


	protected function renderEmptyGallery($isFiltered = false) {
		if($isFiltered) {
			return '<div class="uk-placeholder uk-text-center">'				. '<h3 class="uk-margin-small-top">' . $this->_('No videos match your filters.') . '</h3>'
				. '<p class="uk-text-muted">' . $this->_('Try a broader search, another category, or clear all filters.') . '</p>'
				. '<a href="./" class="uk-button uk-button-default">' . $this->_('Clear Filters') . '</a>'
				. '</div>';
		}
		return '<div class="uk-placeholder uk-text-center">'			. '<h3 class="uk-margin-small-top">' . $this->_('No videos yet.') . '</h3>'
			. '<p class="uk-text-muted">' . $this->_('Upload one above, or add videos on a page.') . '</p>'
			. '</div>';
	}


	protected function renderConnectionBadge($conn) {
	    $ok = $conn['ok'];
	    $badgeClass = $ok ? 'uk-label-success' : 'uk-label-danger';
	    $label = $ok ? $this->_('Connected') : $this->_('Disconnected');
	    $sanitizer = $this->wire('sanitizer');
	    $testUrl = $this->page->url . 'test-connection/';

	    return
	        '<div class="uk-grid-small uk-flex-middle" uk-grid>' .
	            '<div class="uk-width-expand">' .
	                '<span class="uk-label ' . $badgeClass . '">' .
	                    $sanitizer->entities($label) .
	                '</span>' .
	                '<span class="uk-text-small uk-text-muted uk-margin-small-left">' .
	                    $sanitizer->entities($conn['message']) .
	                '</span>' .
	            '</div>' .
	            '<div class="uk-width-auto">' .
	                '<a href="' . $testUrl . '" class="uk-button uk-button-default uk-button-small">' .
	                    $sanitizer->entities($this->_('Test connection')) .
	                '</a>' .
	            '</div>' .
	        '</div>';
	}


	protected function renderStatsRow($stats, $activeStatus = '') {
		$items = array(
			'total' => array('label' => $this->_('Total')),
			'ready' => array('label' => $this->_('Ready')),
			'processing' => array('label' => $this->_('Processing')),
			'error' => array('label' => $this->_('Error')),
			'pending' => array('label' => $this->_('Pending')),
		);

		$baseUrl = $this->page->url;

		$cards = '';
		foreach($items as $key => $item) {
			$value = $stats[$key] ?? 0;
			$isActive = $key === 'total' ? $activeStatus === '' : $activeStatus === $key;
			$url = $key === 'total' ? $baseUrl . '#library' : $baseUrl . '?status=' . $key . '#library';
			$cards .=
				'<li>' .
					'<a href="' . $url . '" ' .
						'class="lumen-stat uk-link-reset' . ($isActive ? ' lumen-stat--active' : '') . '"' .
						($isActive ? ' aria-current="page"' : '') . '>' .
						'<span class="lumen-stat-value">' . (int)$value . '</span>' .
						'<span class="lumen-stat-label">' . $this->wire('sanitizer')->entities($item['label']) . '</span>' .
					'</a>' .
				'</li>';
		}

		return '<ul class="lumen-stat-grid uk-margin-bottom" aria-label="' .
			$this->wire('sanitizer')->entities($this->_('Video status filters')) . '">' . $cards . '</ul>';
	}


	protected function renderUsagePanel($stats) {
		$s = $this->wire('sanitizer');
		$storedSeconds = max(0, (int) ($stats['duration_seconds'] ?? 0));
		$deliveredSeconds = max(0, (int) ($stats['delivered_seconds_estimate'] ?? 0));
		$storedMinutes = (int) ceil($storedSeconds / 60);
		$deliveredMinutes = (int) ceil($deliveredSeconds / 60);
		$storageCost = ($storedMinutes / 1000) * self::STREAM_STORAGE_USD_PER_1000_MINUTES;
		$deliveryCost = ($deliveredMinutes / 1000) * self::STREAM_DELIVERY_USD_PER_1000_MINUTES;
		$totalCost = $storageCost + $deliveryCost;
		$starterStoragePct = min(100, round(($storedMinutes / self::STREAM_STARTER_STORAGE_MINUTES) * 100));
		$starterDeliveryPct = min(100, round(($deliveredMinutes / self::STREAM_STARTER_DELIVERY_MINUTES) * 100));
		$creatorStoragePct = min(100, round(($storedMinutes / self::STREAM_CREATOR_STORAGE_MINUTES) * 100));
		$creatorDeliveryPct = min(100, round(($deliveredMinutes / self::STREAM_CREATOR_DELIVERY_MINUTES) * 100));

		return '<details class="lumen-usage-panel uk-card uk-card-default">' .
			'<summary class="lumen-disclosure-summary">' .
				'<span><strong>' . $s->entities($this->_('Stream usage and cost estimate')) . '</strong>' .
				'<small>' . sprintf(
					$s->entities($this->_('%1$s stored · %2$s delivered · about $%3$s/mo')),
					$s->entities($this->formatUsageMinutes($storedMinutes)),
					$s->entities($this->formatUsageMinutes($deliveredMinutes)),
					number_format($totalCost, 2)
				) . '</small></span>' .
				'<span class="uk-label">' . $s->entities($this->_('Estimate')) . '</span>' .
			'</summary>' .
			'<div class="lumen-usage-body">' .
			'<div class="lumen-usage-metrics">' .
				'<div><span>' . $s->entities($this->_('Stored')) . '</span><strong>' . $s->entities($this->formatUsageMinutes($storedMinutes)) . '</strong><small>$' . number_format($storageCost, 2) . '/mo</small></div>' .
				'<div><span>' . $s->entities($this->_('Delivered')) . '</span><strong>' . $s->entities($this->formatUsageMinutes($deliveredMinutes)) . '</strong><small>$' . number_format($deliveryCost, 2) . '/mo</small></div>' .
				'<div><span>' . $s->entities($this->_('Estimated total')) . '</span><strong>$' . number_format($totalCost, 2) . '</strong><small>' . $s->entities($this->_('per month')) . '</small></div>' .
			'</div>' .
			'<div class="uk-alert-primary uk-margin-small-top uk-margin-remove-bottom" uk-alert>' .
				'<p class="uk-margin-remove uk-text-small">' .
					sprintf(
						$s->entities($this->_('Pay-as-you-go estimate: $%1$s/mo total. Starter includes %2$d stored / %3$d delivered minutes. Creator includes %4$d stored / %5$d delivered minutes.')),
						number_format($totalCost, 2),
						self::STREAM_STARTER_STORAGE_MINUTES,
						self::STREAM_STARTER_DELIVERY_MINUTES,
						self::STREAM_CREATOR_STORAGE_MINUTES,
						self::STREAM_CREATOR_DELIVERY_MINUTES
					) .
					' ' .
					$s->entities($this->_('Use Cloudflare billing as the source of truth after you activate a plan.')) .
				'</p>' .
			'</div>' .
			'<div class="uk-child-width-1-2@m uk-grid-small uk-grid-match uk-margin-small-top" uk-grid>' .
				'<div>' .
					'<div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">' .
						'<div class="uk-text-bold uk-margin-small-bottom">' . $s->entities($this->_('Starter Bundle')) . '</div>' .
						'<div class="uk-text-small uk-text-muted">' .
							sprintf(
								$s->entities($this->_('Stored: %1$d / %2$d min')),
								$storedMinutes,
								self::STREAM_STARTER_STORAGE_MINUTES
							) .
						'</div>' .
						'<progress class="uk-progress uk-margin-small" value="' . (int) $starterStoragePct . '" max="100"></progress>' .
						'<div class="uk-text-small uk-text-muted">' .
							sprintf(
								$s->entities($this->_('Delivered: %1$d / %2$d min')),
								$deliveredMinutes,
								self::STREAM_STARTER_DELIVERY_MINUTES
							) .
						'</div>' .
						'<progress class="uk-progress uk-margin-small" value="' . (int) $starterDeliveryPct . '" max="100"></progress>' .
					'</div>' .
				'</div>' .
				'<div>' .
					'<div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">' .
						'<div class="uk-text-bold uk-margin-small-bottom">' . $s->entities($this->_('Creator Bundle')) . '</div>' .
						'<div class="uk-text-small uk-text-muted">' .
							sprintf(
								$s->entities($this->_('Stored: %1$d / %2$d min')),
								$storedMinutes,
								self::STREAM_CREATOR_STORAGE_MINUTES
							) .
						'</div>' .
						'<progress class="uk-progress uk-margin-small" value="' . (int) $creatorStoragePct . '" max="100"></progress>' .
						'<div class="uk-text-small uk-text-muted">' .
							sprintf(
								$s->entities($this->_('Delivered: %1$d / %2$d min')),
								$deliveredMinutes,
								self::STREAM_CREATOR_DELIVERY_MINUTES
							) .
						'</div>' .
						'<progress class="uk-progress uk-margin-small" value="' . (int) $creatorDeliveryPct . '" max="100"></progress>' .
					'</div>' .
				'</div>' .
			'</div>' .
		'</div></details>';
	}


	protected function formatUsageMinutes($minutes) {
		$minutes = max(0, (int) $minutes);
		if($minutes < 60) return sprintf($this->_('%d min'), $minutes);
		$hours = floor($minutes / 60);
		$remaining = $minutes % 60;
		if($remaining === 0) return sprintf($this->_('%d h'), $hours);
		return sprintf($this->_('%d h %d min'), $hours, $remaining);
	}


	protected function renderToolbar($stats) {
		$adminUrl = $this->wire('config')->urls->admin;
		$modules = $this->wire('modules');
		$s = $this->wire('sanitizer');
		$needsRefresh = $stats['processing'] > 0 || $stats['pending'] > 0;
		$waiting = (int) $stats['processing'] + (int) $stats['pending'];

		$out = '<div class="lumen-toolbar uk-margin-bottom">' .
			'<p class="uk-text-small uk-text-muted uk-margin-remove">' .
						($needsRefresh
							? $s->entities(sprintf($this->_('%d video(s) are waiting for status updates.'), $waiting))
							: $s->entities($this->_('Upload, organize, and publish Stream videos from one place.'))) .
			'</p><div class="lumen-toolbar-actions">';

		// Refresh button
		if($needsRefresh) {
			$refresh = $modules->get('InputfieldSubmit');
			$refresh->name = 'refresh';
			$refresh->value = sprintf($this->_('Refresh Status (%d)'), $waiting);
			$refresh->addClass('uk-button-small');
			$out .= '<div>' .
				'<form method="post" action="./" class="uk-margin-remove">' .
					$this->csrfInput() .
					$refresh->render() .
				'</form>' .
			'</div>';
		}

		$out .=
			'<div>' .
				'<form method="post" action="' . $adminUrl . 'setup/lumen/add-field/" class="uk-margin-remove">' .
					$this->csrfInput() .
					'<button type="submit" name="add_field" value="1" class="uk-button uk-button-primary uk-button-small">' .
						$s->entities($this->_('Create Field')) .
					'</button>' .
				'</form>' .
			'</div>';

		$out .=
			'<div>' .
				'<a href="#settings" class="uk-button uk-button-default uk-button-small">' .
					$s->entities($this->_('Settings')) .
				'</a>' .
			'</div>';

		$out .= '</div></div>';
		    return $out;
		}
}
