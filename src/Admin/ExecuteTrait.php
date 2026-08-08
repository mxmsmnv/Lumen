<?php namespace ProcessWire;

trait ProcessLumenExecuteTrait {
	// ---------------------------------------------------------------------------
	// Default screen
	// ---------------------------------------------------------------------------

	public function ___execute() {
		$modules = $this->wire('modules');
		$lumen = $modules->get('Lumen');
		$localStorage = (bool) $lumen->localStorage;
		$pages = $this->wire('pages');
		$sanitizer = $this->wire('sanitizer');
		$input = $this->input;

		// Validate every state-changing dashboard action before dispatch.
		$mutation = $input->post('refresh')
			|| $input->post('upload_video')
			|| $input->post('bulk_delete')
			|| $input->post('save_settings')
			|| $input->post('clear_event_log')
			|| $input->post('assign_template');
		if($mutation) $this->validateCsrf();

		// Handle actions
		   if($input->post('refresh')) return $this->refreshStatuses();
		   if($input->post('upload_video')) return $this->handleUpload();
		   if($input->post('bulk_delete')) return $this->handleBulkDelete();
		   if($input->post('save_settings')) return $this->handleSaveSettings();
		   if($input->post('clear_event_log')) return $this->handleClearEventLog();
		   if($input->post('assign_template')) return $this->handleAssignTemplate();
		   if($input->get('video')) return $this->handleVideoDetail();

		$fields = $this->getLumenFields();

		// --- No fields yet — empty state ---
		if(!count($fields)) {
			return $this->renderEmptyState();
		}

		// --- Filtering ---
		$filterStatus = $input->get('status');
		$filterCategory = $input->get('category');
		$filterSearch = $input->get('search');
		$filterTags = $input->get('tags');
		$searchQuery = strlen((string)$filterSearch) > 0 ? mb_strtolower($filterSearch) : '';
		$tagsQuery = strlen((string)$filterTags) > 0 ? mb_strtolower($filterTags) : '';

		// --- Collect stats and matching videos ---
		$filteredVideos = array();
		$stats = array(
			'total' => 0,
			'ready' => 0,
			'processing' => 0,
			'error' => 0,
			'pending' => 0,
			'duration_seconds' => 0,
			'delivered_seconds_estimate' => 0,
		);
		$categories = array();

		foreach($fields as $field) {
			foreach($pages->find("{$field->name}.count>0, include=all") as $page) {
				$value = $page->get($field->name);
				foreach($this->videoFiles($value) as $pf) {
					$stats['total']++;
					$actualStatus = $this->dashboardStatus($pf, $localStorage);
					$durationSeconds = max(0, (int) $pf->stream_duration);
					$views = max(0, (int) $pf->stream_views);
					if($durationSeconds > 0 && $actualStatus !== 'error') {
						$stats['duration_seconds'] += $durationSeconds;
						$stats['delivered_seconds_estimate'] += $durationSeconds * $views;
					}

					if($actualStatus === 'ready') {
						$stats['ready']++;
					} elseif($actualStatus === 'error') {
						$stats['error']++;
					} elseif($actualStatus !== 'pending') {
						$stats['processing']++;
					} else {
						$stats['pending']++;
					}

					if(!empty($pf->stream_category)) {
						$cat = trim($pf->stream_category);
						$categories[$cat] = ($categories[$cat] ?? 0) + 1;
					}

					if($filterStatus && $actualStatus !== $filterStatus) continue;
					if($filterCategory && trim($pf->stream_category) !== $filterCategory) continue;

					if($searchQuery !== '') {
						$haystack = mb_strtolower($pf->basename . ' ' . ($page->title ?: '') . ' ' . ($pf->stream_category ?: '') . ' ' . ($pf->stream_tags ?: ''));
						if(strpos($haystack, $searchQuery) === false) continue;
					}

					if($tagsQuery !== '') {
						$haystack = mb_strtolower($pf->stream_tags ?: '');
						if(strpos($haystack, $tagsQuery) === false) continue;
					}

					$filteredVideos[] = array('pf' => $pf, 'page' => $page, 'field' => $field);
				}
			}
		}

		$filteredTotal = count($filteredVideos);

		// --- Sorting ---
		$sort = $input->get('sort');
		$allowedSorts = array('status', 'name', 'date', 'duration', 'views', 'category');
		if(!in_array($sort, $allowedSorts)) $sort = 'status';

		usort($filteredVideos, function($a, $b) use ($sort, $localStorage) {
			$pfa = $a['pf'];
			$pfb = $b['pf'];
			switch($sort) {
				case 'name':
					return strcasecmp($pfa->basename, $pfb->basename);
				case 'date':
					return ($pfb->created ?? 0) - ($pfa->created ?? 0);
				case 'duration':
					return (int)$pfb->stream_duration - (int)$pfa->stream_duration;
				case 'views':
					return (int)$pfb->stream_views - (int)$pfa->stream_views;
				case 'category':
					return strcasecmp($pfa->stream_category ?: 'zzz', $pfb->stream_category ?: 'zzz');
				case 'status':
				default:
					$o = array('ready' => 1, 'inprogress' => 2, 'queued' => 3, 'error' => 4, '' => 5);
					$aStatus = $this->dashboardStatus($pfa, $localStorage);
					$bStatus = $this->dashboardStatus($pfb, $localStorage);
					return ($o[$aStatus] ?? 5) - ($o[$bStatus] ?? 5);
			}
		});

		// --- Pagination ---
		$pageNum = max(1, (int) $input->get('page'));
		$totalPages = max(1, ceil($filteredTotal / self::PER_PAGE));
		$offset = ($pageNum - 1) * self::PER_PAGE;
		$pagedVideos = array_slice($filteredVideos, $offset, self::PER_PAGE);

		$conn = $this->getConnectionStatus();

		$out = '<div class="pw-wrap lumen-admin">' .
			$this->renderAdminNav() .

			// --- Header ---
			'<section id="overview" class="lumen-admin-section">' .
			'<div class="lumen-module-head uk-grid-small uk-flex-middle uk-margin-bottom" uk-grid>' .
				'<div class="uk-width-expand">' .
					'<h2 class="uk-margin-remove">' .
						$sanitizer->entities($this->_('Cloudflare Stream')) .
					'</h2>' .
					'<p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">' .
						$sanitizer->entities($this->_('Upload, transcode, organize, and deliver video from one ProcessWire workspace.')) .
					'</p>' .
				'</div>' .
				'<div class="uk-width-auto@m uk-width-1-1">' .
					$this->renderConnectionBadge($conn) .
				'</div>' .
			'</div>' .

			// --- Primary actions ---
			$this->renderToolbar($stats) .

			// --- Status filters ---
			$this->renderStatsRow($stats, $filterStatus) .
			'</section>' .
			'<section id="library" class="lumen-admin-section">' .
				'<div class="lumen-section-head">' .
					'<div><h2 class="uk-h3 uk-margin-remove">' . $sanitizer->entities($this->_('Video Library')) . '</h2>' .
					'<p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">' .
						sprintf($this->_('%1$d video(s) in %2$d Lumen field(s).'), (int)$stats['total'], count($fields)) .
					'</p></div>' .
				'</div>';

		// --- Filters ---
		$out .= $this->renderFilters($categories, $filterStatus, $filterCategory, $filterSearch, $filterTags, $filteredTotal);

		// --- Sort controls ---
		$out .= $this->renderSortControls($sort);

		// --- Bulk form wrapper ---
		if($filteredTotal > 0) {
			$out .= '<form method="post" id="LumenBulkForm">' .
				$this->csrfInput() .
				$this->renderBulkToolbar() .
				$this->renderVideoGallery($pagedVideos) .
				'</form>';
		} else {
			$out .= $this->renderEmptyGallery($filterSearch || $filterStatus || $filterCategory || $filterTags);
		}

		// --- Pagination ---
		$out .= $this->renderPagination($pageNum, $totalPages, $filteredTotal);

		$out .= '</section>' .
			'<section id="upload" class="lumen-admin-section">' .
				$this->renderUploadAccordion($fields) .
			'</section>' .
			'<section id="settings" class="lumen-admin-section">' .
				$this->renderSettingsPanel() .
			'</section>' .
			'<section id="usage" class="lumen-admin-section">' .
				'<div class="lumen-section-head"><div><h2 class="uk-h3 uk-margin-remove">' . $sanitizer->entities($this->_('Usage')) . '</h2>' .
				'<p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">' .
					$sanitizer->entities($this->_('Storage, delivery, and estimated Cloudflare Stream cost.')) .
				'</p></div></div>' .
				$this->renderUsagePanel($stats) .
			'</section>' .
			'<section id="event-log" class="lumen-admin-section">' .
				$this->renderEventLogPanel() .
			'</section>' .
		'</div>';
		return $out;
	}
}
