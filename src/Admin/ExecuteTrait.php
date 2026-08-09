<?php namespace ProcessWire;

trait ProcessLumenExecuteTrait {

	public function ___execute() {
		if($this->input->get('video')) {
			$this->prepareWorkspacePage('videos', $this->_('Video details'));
			return $this->renderAdminShell(
				'videos',
				$this->_('Inspect playback state, delivery metadata, and publishing links for this asset.'),
				$this->handleVideoDetail()
			);
		}

		if($this->input->post('refresh')) {
			$this->validateCsrf();
			return $this->refreshStatuses();
		}

		$fields = $this->getLumenFields();
		$this->prepareWorkspacePage('overview', $this->_('Lumen overview'));
		if(!count($fields)) return $this->renderEmptyState('overview');

		$lumen = $this->wire('modules')->get('Lumen');
		$data = $this->collectWorkspaceData($fields, (bool)$lumen->localStorage, false);
		$conn = $this->getConnectionStatus();
		$body = $this->renderToolbar($data['stats']) .
			$this->renderStatsRow($data['stats']) .
			$this->renderOverviewCards($data['stats'], $fields);

		return $this->renderAdminShell(
			'overview',
			$this->_('Monitor connection health, processing state, storage, and the next work that needs attention.'),
			$body,
			$this->renderConnectionBadge($conn)
		);
	}

	public function ___executeVideos() {
		if($this->input->post('bulk_delete')) {
			$this->validateCsrf();
			return $this->handleBulkDelete();
		}

		$this->prepareWorkspacePage('videos', $this->_('Video library'));
		if($this->input->get('video')) {
			$detail = $this->handleVideoDetail();
			return $this->renderAdminShell(
				'videos',
				$this->_('Inspect playback state, delivery metadata, and publishing links for this asset.'),
				$detail
			);
		}

		$fields = $this->getLumenFields();
		if(!count($fields)) return $this->renderEmptyState('videos');
		$lumen = $this->wire('modules')->get('Lumen');
		$data = $this->collectWorkspaceData($fields, (bool)$lumen->localStorage, true);

		$body = $this->renderStatsRow($data['stats'], $data['filter_status']) .
			$this->renderFilters(
				$data['categories'],
				$data['filter_status'],
				$data['filter_category'],
				$data['filter_search'],
				$data['filter_tags'],
				$data['filtered_total']
			) .
			$this->renderSortControls($data['sort']);

		if($data['filtered_total'] > 0) {
			$body .= '<form method="post" id="LumenBulkForm">' .
				$this->csrfInput() .
				$this->renderBulkToolbar() .
				$this->renderVideoGallery($data['paged_videos']) .
			'</form>';
		} else {
			$body .= $this->renderEmptyGallery($data['has_filters']);
		}
		$body .= $this->renderPagination($data['page_num'], $data['total_pages'], $data['filtered_total']);

		return $this->renderAdminShell(
			'videos',
			sprintf($this->_('%1$d video(s) across %2$d configured Lumen field(s).'), (int)$data['stats']['total'], count($fields)),
			$body,
			'<a href="' . $this->adminSectionUrl('upload') . '" class="uk-button uk-button-primary uk-button-small">' .
				$this->wire('sanitizer')->entities($this->_('Upload video')) . '</a>'
		);
	}

	public function ___executeUpload() {
		if($this->input->post('upload_video') || $this->input->post('assign_template')) {
			$this->validateCsrf();
			if($this->input->post('upload_video')) return $this->handleUpload();
			return $this->handleAssignTemplate();
		}

		$fields = $this->getLumenFields();
		$this->prepareWorkspacePage('upload', $this->_('Upload video'));
		return $this->renderAdminShell(
			'upload',
			$this->_('Choose the destination page first, then add one source file to its configured Lumen field.'),
			$this->renderUploadAccordion($fields, false)
		);
	}

	public function ___executeSettings() {
		if($this->input->post('save_settings')) {
			$this->validateCsrf();
			return $this->handleSaveSettings();
		}

		$this->prepareWorkspacePage('settings', $this->_('Lumen settings'));
		return $this->renderAdminShell(
			'settings',
			$this->_('Manage the Stream connection, upload limits, local development mode, and diagnostic detail.'),
			$this->renderSettingsPanel(false),
			$this->renderConnectionBadge($this->getConnectionStatus())
		);
	}

	public function ___executeUsage() {
		$fields = $this->getLumenFields();
		$lumen = $this->wire('modules')->get('Lumen');
		$data = $this->collectWorkspaceData($fields, (bool)$lumen->localStorage, false);
		$this->prepareWorkspacePage('usage', $this->_('Usage and cost'));
		return $this->renderAdminShell(
			'usage',
			$this->_('Review stored and delivered minutes with a planning estimate for Cloudflare Stream.'),
			$this->renderUsagePanel($data['stats'], true)
		);
	}

	public function ___executeEventLog() {
		if($this->input->post('clear_event_log')) {
			$this->validateCsrf();
			return $this->handleClearEventLog();
		}

		$this->prepareWorkspacePage('event-log', $this->_('Event log'));
		return $this->renderAdminShell(
			'event-log',
			$this->_('Review recent connection, upload, API, and processing events without exposing credentials.'),
			$this->renderEventLogPanel(false)
		);
	}

	protected function collectWorkspaceData($fields, $localStorage, $collectVideos) {
		$pages = $this->wire('pages');
		$input = $this->input;
		$filterStatus = (string)$input->get('status');
		$filterCategory = (string)$input->get('category');
		$filterSearch = (string)$input->get('search');
		$filterTags = (string)$input->get('tags');
		$searchQuery = $filterSearch !== '' ? mb_strtolower($filterSearch) : '';
		$tagsQuery = $filterTags !== '' ? mb_strtolower($filterTags) : '';
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
				foreach($this->videoFiles($page->get($field->name)) as $pf) {
					$stats['total']++;
					$actualStatus = $this->dashboardStatus($pf, $localStorage);
					$durationSeconds = max(0, (int)$pf->stream_duration);
					$views = max(0, (int)$pf->stream_views);
					if($durationSeconds > 0 && $actualStatus !== 'error') {
						$stats['duration_seconds'] += $durationSeconds;
						$stats['delivered_seconds_estimate'] += $durationSeconds * $views;
					}
					if($actualStatus === 'ready') $stats['ready']++;
					elseif($actualStatus === 'error') $stats['error']++;
					elseif($actualStatus === 'pending') $stats['pending']++;
					else $stats['processing']++;

					if(!empty($pf->stream_category)) {
						$category = trim((string)$pf->stream_category);
						$categories[$category] = ($categories[$category] ?? 0) + 1;
					}
					if(!$collectVideos) continue;
					if($filterStatus !== '' && $actualStatus !== $filterStatus) continue;
					if($filterCategory !== '' && trim((string)$pf->stream_category) !== $filterCategory) continue;
					if($searchQuery !== '') {
						$haystack = mb_strtolower($pf->basename . ' ' . ($page->title ?: '') . ' ' . ($pf->stream_category ?: '') . ' ' . ($pf->stream_tags ?: ''));
						if(strpos($haystack, $searchQuery) === false) continue;
					}
					if($tagsQuery !== '' && strpos(mb_strtolower((string)$pf->stream_tags), $tagsQuery) === false) continue;
					$filteredVideos[] = array('pf' => $pf, 'page' => $page, 'field' => $field);
				}
			}
		}

		$sort = (string)$input->get('sort');
		if(!in_array($sort, array('status', 'name', 'date', 'duration', 'views', 'category'))) $sort = 'status';
		usort($filteredVideos, function($a, $b) use ($sort, $localStorage) {
			$pfa = $a['pf'];
			$pfb = $b['pf'];
			switch($sort) {
				case 'name': return strcasecmp($pfa->basename, $pfb->basename);
				case 'date': return ($pfb->created ?? 0) - ($pfa->created ?? 0);
				case 'duration': return (int)$pfb->stream_duration - (int)$pfa->stream_duration;
				case 'views': return (int)$pfb->stream_views - (int)$pfa->stream_views;
				case 'category': return strcasecmp($pfa->stream_category ?: 'zzz', $pfb->stream_category ?: 'zzz');
				default:
					$order = array('ready' => 1, 'inprogress' => 2, 'queued' => 3, 'pending' => 4, 'error' => 5);
					return ($order[$this->dashboardStatus($pfa, $localStorage)] ?? 6) - ($order[$this->dashboardStatus($pfb, $localStorage)] ?? 6);
			}
		});

		$filteredTotal = count($filteredVideos);
		$pageNum = max(1, (int)$input->get('page'));
		$totalPages = max(1, (int)ceil($filteredTotal / self::PER_PAGE));
		$offset = ($pageNum - 1) * self::PER_PAGE;

		return array(
			'stats' => $stats,
			'categories' => $categories,
			'filter_status' => $filterStatus,
			'filter_category' => $filterCategory,
			'filter_search' => $filterSearch,
			'filter_tags' => $filterTags,
			'has_filters' => $filterStatus !== '' || $filterCategory !== '' || $filterSearch !== '' || $filterTags !== '',
			'filtered_total' => $filteredTotal,
			'sort' => $sort,
			'page_num' => $pageNum,
			'total_pages' => $totalPages,
			'paged_videos' => array_slice($filteredVideos, $offset, self::PER_PAGE),
		);
	}
}
