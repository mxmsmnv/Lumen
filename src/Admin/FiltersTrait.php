<?php namespace ProcessWire;

trait ProcessLumenFiltersTrait {


	protected function renderFilters($categories, $filterStatus, $filterCategory, $filterSearch, $filterTags, $totalFiltered) {
		$s = $this->wire('sanitizer');
		$baseUrl = $this->page->url;

		$hasFilters = $filterStatus || $filterCategory || $filterSearch || $filterTags;
		$out = '<div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-bottom">' .
			'<div class="uk-grid-small uk-flex-middle uk-margin-small-bottom" uk-grid>' .
				'<div class="uk-width-expand">' .
					'<h3 class="uk-card-title uk-margin-remove">' . $s->entities($this->_('Find Videos')) . '</h3>' .
				'</div>' .
				'<div class="uk-width-auto">' .
					'<span class="uk-text-small uk-text-muted">' .
						sprintf($this->_('%d shown'), $totalFiltered) .
					'</span>' .
					($hasFilters
						? ' <a href="' . $baseUrl . '" class="uk-button uk-button-default uk-button-small">' . $s->entities($this->_('Clear all')) . '</a>'
						: '') .
				'</div>' .
			'</div>';

		// Row: search + status
		$out .= '<div class="uk-grid-small uk-flex-middle" uk-grid>' .

			// Search input
			'<div class="uk-width-expand@m uk-width-1-1@s">' .
				'<form method="get" action="' . $baseUrl . '" class="uk-search uk-search-default uk-width-1-1">' .
					'' .
					'<input class="uk-search-input" type="search" name="search" ' .
						'value="' . $s->entities($filterSearch) . '" ' .
						'placeholder="' . $s->entities($this->_('Search videos…')) . '">' .
					// Preserve other filters
					($filterStatus ? '<input type="hidden" name="status" value="' . $s->entities($filterStatus) . '">' : '') .
					($filterCategory ? '<input type="hidden" name="category" value="' . $s->entities($filterCategory) . '">' : '') .
				'</form>' .
			'</div>' .

			// Tags filter
			'<div class="uk-width-1-4@m uk-width-1-2@s">' .
				'<form method="get" action="' . $baseUrl . '" class="uk-search uk-search-default uk-width-1-1">' .
					'' .
					'<input class="uk-search-input" type="search" name="tags" ' .
						'value="' . $s->entities($filterTags) . '" ' .
						'placeholder="' . $s->entities($this->_('Filter by tag…')) . '">' .
					($filterStatus ? '<input type="hidden" name="status" value="' . $s->entities($filterStatus) . '">' : '') .
					($filterCategory ? '<input type="hidden" name="category" value="' . $s->entities($filterCategory) . '">' : '') .
					($filterSearch ? '<input type="hidden" name="search" value="' . $s->entities($filterSearch) . '">' : '') .
				'</form>' .
			'</div>';

		$out .= '</div>';

		if($hasFilters) {
			$activeFilters = array();
			if($filterStatus) $activeFilters[] = '<span class="uk-label uk-label-default uk-margin-small-right">' . $s->entities($this->_('Status')) . ': ' . $s->entities($filterStatus) . '</span>';
			if($filterCategory) $activeFilters[] = '<span class="uk-label uk-label-default uk-margin-small-right">' . $s->entities($this->_('Category')) . ': ' . $s->entities($filterCategory) . '</span>';
			if($filterSearch) $activeFilters[] = '<span class="uk-label uk-label-default uk-margin-small-right">' . $s->entities($this->_('Search')) . ': ' . $s->entities($filterSearch) . '</span>';
			if($filterTags) $activeFilters[] = '<span class="uk-label uk-label-default uk-margin-small-right">' . $s->entities($this->_('Tag')) . ': ' . $s->entities($filterTags) . '</span>';
			$out .= '<div class="uk-alert-primary uk-margin-small-top uk-margin-remove-bottom" uk-alert>' .
				'<p class="uk-margin-remove uk-text-small">' .
					$s->entities($this->_('Filtered view')) . ': ' .
					sprintf($this->_('%d video(s) match the current filters.'), $totalFiltered) .
				'</p>' .
				'<p class="uk-margin-small-top uk-margin-remove-bottom uk-text-small">' .
					implode('', $activeFilters) .
				'</p>' .
			'</div>';
		}

		// Category chips
		if(count($categories)) {
			arsort($categories);
			$out .= '<div class="uk-margin-small-top">' .
				'<span class="uk-text-small uk-text-muted uk-margin-small-right">' . $s->entities($this->_('Categories')) . '</span>';

			// "All" chip
			$activeClass = !$filterCategory ? 'uk-label uk-link-reset' : 'uk-label uk-label-default uk-link-reset';
			$out .= '<a href="' . $baseUrl . ($filterStatus ? '?status=' . $s->entities($filterStatus) : '') . '" ' .
				'class="' . $activeClass . ' uk-margin-small-right uk-margin-small-bottom">' .
				$this->_('All') . '</a>';

			foreach($categories as $cat => $count) {
				$isActive = ($filterCategory === $cat);
				$cls = $isActive ? 'uk-label uk-link-reset' : 'uk-label uk-label-default uk-link-reset';
				$url = $baseUrl . '?category=' . urlencode($cat);
				if($filterStatus) $url .= '&status=' . urlencode($filterStatus);
				if($filterSearch) $url .= '&search=' . urlencode($filterSearch);
				$out .= '<a href="' . $url . '" class="' . $cls . ' uk-margin-small-right uk-margin-small-bottom uk-link-reset">' .
					$s->entities($cat) . ' <span class="uk-text-muted">' . $count . '</span>' .
				'</a>';
			}

			$out .= '</div>';
		}

		$out .= '</div>';
		return $out;
	}


	protected function renderSortControls($currentSort) {
		$s = $this->wire('sanitizer');
		$baseUrl = $this->page->url;

		$options = array(
			'status'   => $this->_('Status'),
			'name'     => $this->_('Name'),
			'date'     => $this->_('Date'),
			'duration' => $this->_('Duration'),
			'views'    => $this->_('Views'),
			'category' => $this->_('Category'),
		);

		// Build query without sort
		$queryParts = array();
		foreach(array('status', 'category', 'search', 'tags') as $k) {
			    $v = $this->input->get($k);
			    if(strlen((string)$v)) $queryParts[] = $k . '=' . urlencode($v);
			   }
			   $q = $queryParts ? '?' . implode('&', $queryParts) . '&' : '?';

			   $out = '<div class="uk-grid-small uk-flex-middle uk-margin-small-bottom" uk-grid>' .
			    '<div class="uk-width-expand">' .
			        '<h3 class="uk-heading-line uk-text-small uk-margin-remove"><span>' .
			            $s->entities($this->_('Library')) .
			        '</span></h3>' .
			    '</div>' .
			    '<div class="uk-width-auto">' .
			        '<span class="uk-text-small uk-text-muted uk-margin-small-right">' . $s->entities($this->_('Sort')) . '</span>' .
			        '<div>';

		foreach($options as $key => $label) {
			$isActive = ($currentSort === $key);
			$cls = $isActive ? 'uk-button uk-button-primary uk-button-small uk-margin-small-right uk-margin-small-bottom' : 'uk-button uk-button-default uk-button-small uk-margin-small-right uk-margin-small-bottom';
			$out .= '<button type="button" onclick="window.location.href=\'' . $baseUrl . $q . 'sort=' . $key . '\'" class="' . $cls . '">' .
				$s->entities($label) . '</button>';
		}

		$out .= '</div></div></div>';
		return $out;
	}


	protected function renderBulkToolbar() {
		$s = $this->wire('sanitizer');
		$confirmText = $s->entities($this->_('Delete selected videos? This cannot be undone.'));
		return '<div class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-small-bottom">' .
		'<div class="uk-grid-small uk-flex-middle" uk-grid>' .
			'<div class="uk-width-expand">' .
				'<label class="uk-text-small">' .
					'<input type="checkbox" class="uk-checkbox" id="LumenSelectAll" ' .
						'onchange="document.querySelectorAll(\'.LumenCheckbox\').forEach(c=>c.checked=this.checked); window.lumenUpdateBulkActions && window.lumenUpdateBulkActions()"> ' .
					$s->entities($this->_('Select all')) .
				'</label>' .
				'<span id="LumenBulkCount" class="uk-text-small uk-text-muted uk-margin-small-left" aria-live="polite" ' .
					'style="display:inline-block; min-width: 8.5rem;" ' .
					'data-empty="' . $s->entities($this->_('No videos selected')) . '" ' .
					'data-selected="' . $s->entities($this->_('selected')) . '">' .
					$s->entities($this->_('No videos selected')) .
				'</span>' .
			'</div>' .
			'<div class="uk-width-auto">' .
			'<button type="submit" name="bulk_delete" value="1" id="LumenBulkDelete" class="uk-button uk-button-danger uk-button-small" disabled ' .
					'style="min-width: 151px; min-height: 30px;" ' .
					'onclick="return window.lumenConfirm(this)" ' .
					'data-confirm="' . $confirmText . '">' .
					$s->entities($this->_('Delete Selected')) .
				'</button>' .
			'</div>' .
		'</div>' .
		'</div>';
	}


	protected function handleBulkDelete() {
		$pages = $this->wire('pages');
		$lumen = $this->wire('modules')->get('Lumen');
		$ids = $this->input->post('video_ids');
		if(!is_array($ids) || !count($ids)) {
			$this->warning($this->_('No videos selected.'));
			$this->session->redirect('./');
			return '';
		}

		$deleted = 0;
		$failed = 0;
		   foreach($ids as $rawId) {
		        // Format: pageId:fieldName:basename
		        $parts = explode(':', $rawId, 3);
		        if(count($parts) < 3) continue;

		        $pageId = (int) $parts[0];
		        $fieldName = $parts[1];
		        $fileName = urldecode($parts[2]);

		        $page = $pages->get($pageId);
		        if(!$page->id) continue;

		        $files = $this->videoFiles($page->get($fieldName));
		        if(!$files) continue;

		        // Find by basename and delete
		        foreach($files as $idx => $f) {
		            if($f->basename === $fileName) {
						if(!$lumen->localStorage && !empty($f->stream_uid)) {
							try {
								$lumen->deleteStreamVideo($f->stream_uid);
							} catch(\Exception $e) {
								$failed++;
								$lumen->eventLog('error', 'Bulk deletion kept local video after Stream failure', array(
									'uid' => $f->stream_uid,
									'file' => $f->basename,
									'error' => $e->getMessage(),
								), true);
								break;
							}
						}

		                $page->of(false);
		                if($page->{$fieldName} instanceof Pagefile) {
							$page->set($fieldName, null);
		                } else {
							$page->{$fieldName}->delete($idx);
		                }
		                $page->save();
		                $deleted++;
		                break;
		            }
		        }
		    }

		$this->message(sprintf($this->_('%d video(s) deleted.'), $deleted));
		if($failed) {
			$this->error(sprintf(
				$this->_('%d video(s) were kept because Cloudflare did not confirm deletion. Try again.'),
				$failed
			));
		}
		$this->session->redirect('./');
		return '';
	}


	protected function renderPagination($currentPage, $totalPages, $totalItems) {
		if($totalPages <= 1) return '';

		$s = $this->wire('sanitizer');
		$baseUrl = $this->page->url;

		// Rebuild query
		$queryParts = array();
		foreach(array('status', 'category', 'search', 'tags', 'sort') as $k) {
			    $v = $this->input->get($k);
			    if(strlen((string)$v)) $queryParts[] = $k . '=' . urlencode($v);
			   }

			   $out = '<div class="uk-margin-top">' .
			    '<ul class="uk-pagination uk-flex-center" uk-margin>';

		// Prev
		if($currentPage > 1) {
			$q = $queryParts;
			if($currentPage - 1 > 1) $q[] = 'page=' . ($currentPage - 1);
			$url = $baseUrl . ($q ? '?' . implode('&', $q) : '');
			$out .= '<li><a class="uk-link-reset" href="' . $url . '">Previous</a></li>';
		} else {
			$out .= '<li class="uk-disabled"><span>Previous</span></li>';
		}

		// Pages
		$start = max(1, $currentPage - 2);
		$end = min($totalPages, $currentPage + 2);

		if($start > 1) {
			$out .= '<li><a class="uk-link-reset" href="' . $baseUrl . ($queryParts ? '?' . implode('&', $queryParts) : '') . '">1</a></li>';
			if($start > 2) $out .= '<li class="uk-disabled"><span>…</span></li>';
		}

		for($p = $start; $p <= $end; $p++) {
			if($p == $currentPage) {
				$out .= '<li class="uk-active"><span>' . $p . '</span></li>';
			} else {
				$q = $queryParts;
				if($p > 1) $q[] = 'page=' . $p;
				$url = $baseUrl . ($q ? '?' . implode('&', $q) : '');
				$out .= '<li><a class="uk-link-reset" href="' . $url . '">' . $p . '</a></li>';
			}
		}

		if($end < $totalPages) {
			if($end < $totalPages - 1) $out .= '<li class="uk-disabled"><span>…</span></li>';
			$q = $queryParts;
			$q[] = 'page=' . $totalPages;
			$out .= '<li><a class="uk-link-reset" href="' . $baseUrl . '?' . implode('&', $q) . '">' . $totalPages . '</a></li>';
		}

		// Next
		if($currentPage < $totalPages) {
			$q = $queryParts;
			$q[] = 'page=' . ($currentPage + 1);
			$url = $baseUrl . '?' . implode('&', $q);
			$out .= '<li><a class="uk-link-reset" href="' . $url . '">Next</a></li>';
		} else {
			$out .= '<li class="uk-disabled"><span>Next</span></li>';
		}

		$out .= '</ul>' .
			'<div class="uk-text-center uk-text-small uk-text-muted">' .
				sprintf($this->_('Page %1$d of %2$d · %3$d video(s) total'), $currentPage, $totalPages, $totalItems) .
			'</div>' .
			'</div>';

		return $out;
	}
}
