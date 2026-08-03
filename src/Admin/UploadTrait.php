<?php namespace ProcessWire;

trait ProcessLumenUploadTrait {


	protected function renderUploadAccordion($fields) {
	    $uploadForm = $this->renderUploadForm($fields);
	    $s = $this->wire('sanitizer');
	    $adminUrl = $this->wire('config')->urls->admin;

	    $body = $uploadForm ?: $this->renderUploadEmptyState($fields);

	    return '<div class="lumen-section-head">' .
				'<div><h2 class="uk-h3 uk-margin-remove">' .
					$s->entities($uploadForm ? $this->_('Upload Video') : $this->_('Upload Video — Setup Needed')) .
				'</h2><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">' .
					$s->entities($uploadForm
						? $this->_('Choose a target page and send a source video to the configured Lumen field.')
						: $this->_('Assign a Lumen field to a template before uploading from the dashboard.')) .
				'</p></div>' .
			'</div>' .
			'<div class="uk-card uk-card-default uk-card-small uk-card-body">' .
	                        '<p class="uk-text-small uk-text-muted uk-margin-remove-top">' .
	                            $s->entities($uploadForm
	                                ? $this->_('Choose a target page, select a video file, and Lumen will add it to the page field.')
	                                : $this->_('Assign a Lumen field to a template before uploading from the dashboard.')) .
	                        '</p>' .
	                        $body .
			'</div>';
	}


	protected function renderUploadEmptyState($fields) {
	    $s = $this->wire('sanitizer');
	    $adminUrl = $this->wire('config')->urls->admin;
	    $pages = $this->wire('pages');

	    $out = '<div>' .
	        '<p class="uk-text-muted">' .
	            sprintf($this->_('You have %d Lumen field(s), but no pages use them yet.'), count($fields)) .
	        '</p>';

	    // List fields and their templates
	    $out .= '<table class="uk-table uk-table-divider uk-table-small uk-table-justify">' .
	        '<thead><tr>' .
	            '<th>' . $s->entities($this->_('Field')) . '</th>' .
	            '<th>' . $s->entities($this->_('Templates')) . '</th>' .
	            '<th></th>' .
	        '</tr></thead><tbody>';

	    foreach($fields as $field) {
	        $templates = $field->getTemplates();
	        if(count($templates)) {
	            $names = array();
	            foreach($templates as $t) { $names[] = $t->name; }
	            $tplNames = implode(', ', $names);
	        } else {
	            $tplNames = '<span class="uk-text-muted">' . $s->entities($this->_('none assigned')) . '</span>';
	        }

	      $createTpl = count($templates) ? $templates->first() : null;

	      // For fields without templates — offer quick assign
	      $allTemplates = $this->wire('templates');
	      $unassignedTpls = array();
	      if(!count($templates)) {
	          foreach($allTemplates as $t) {
	              if($t->flags & Template::flagSystem) continue;
	              $unassignedTpls[$t->id] = $t->name;
	          }
	      }

	      $out .= '<tr>' .
	          '<td><strong>' . $s->entities($field->name) . '</strong>' .
	              ($field->label ? ' <span class="uk-text-muted">(' . $s->entities($field->label) . ')</span>' : '') .
	          '</td>' .
	          '<td>' . $tplNames . '</td>' .
	          '<td class="uk-text-right">';

	      if($createTpl) {
	          $parent = $pages->get('/');
	          $out .= '<a href="' . $adminUrl . 'page/add/?template_id=' . $createTpl->id . '&parent_id=' . $parent->id . '" ' .
	              'class="uk-button uk-button-primary uk-button-small">' .
	              $s->entities($this->_('Create Page')) .
	          '</a>';
	      } elseif($unassignedTpls) {
	          // Quick template assignment form
	          $out .= '<form method="post" class="uk-display-inline">' .
	              $this->csrfInput() .
	              '<div class="uk-grid-small" uk-grid>' .
	                  '<div>' .
	                      '<select class="uk-select uk-form-small" name="assign_template_id">' .
	                          '<option value="">' . $s->entities($this->_('choose…')) . '</option>';
	          foreach($unassignedTpls as $tplId => $tplName) {
	              $out .= '<option value="' . $tplId . '">' . $s->entities($tplName) . '</option>';
	          }
	          $out .= '</select>' .
	                  '</div>' .
	                  '<div>' .
	                      '<input type="hidden" name="assign_field_id" value="' . $field->id . '">' .
	                      '<button type="submit" name="assign_template" value="1" ' .
	                          'class="uk-button uk-button-primary uk-button-small">' .
	                          $s->entities($this->_('Assign')) .
	                      '</button>' .
	                  '</div>' .
	              '</div>' .
	          '</form>';
	      } else {
	          $out .= '<span class="uk-text-small uk-text-muted">' .
	              $s->entities($this->_('no templates available')) . '</span>';
	      }

	        $out .= '</td></tr>';
	    }

	    $out .= '</tbody></table>';

	    // Also offer to create a new field
		    $out .= '<form method="post" action="' . $adminUrl . 'setup/lumen/add-field/" class="uk-display-inline">' .
		        $this->csrfInput() .
		        '<button type="submit" name="add_field" value="1" class="uk-button uk-button-default uk-button-small">' .
		            $s->entities($this->_('Create New Field')) .
		        '</button>' .
		    '</form>';

	    $out .= '</div>';
	    return $out;
	}


	protected function renderUploadForm($fields) {
	    $pages = $this->wire('pages');

	    $pageOptions = array();
		foreach($fields as $field) {
		        foreach($field->getTemplates() as $tpl) {
		            $selector = array(
						"template={$tpl}",
						"include=all",
						"limit=" . self::UPLOAD_PAGE_SELECTOR_LIMIT,
					);
		            foreach($pages->find(implode(', ', $selector)) as $p) {
		                if($p->hasField($field->name)) {
		                    $pageOptions[$p->id] = $p->path . ' — ' . $field->name;
		                }
		            }
		        }
		    }

		    if(!count($pageOptions)) return '';

		    $s = $this->wire('sanitizer');
		    $out = '<form method="post" action="./" enctype="multipart/form-data" class="uk-form-stacked">' .
		        $this->csrfInput() .
		        '<div class="uk-grid-small" uk-grid>' .
		            '<div class="uk-width-1-3@m">' .
		                '<label class="uk-form-label">' . $s->entities($this->_('Add to page')) . '</label>' .
		                '<select class="uk-select uk-form-small" name="target_page_id" required>' .
		                    '<option value="">' . $s->entities($this->_('choose…')) . '</option>';
		    foreach($pageOptions as $pid => $label) {
		        $out .= '<option value="' . $pid . '">' . $s->entities($label) . '</option>';
		    }
		    $out .= '</select>' .
		            '</div>' .
		            '<div class="uk-width-1-3@m">' .
		                '<label class="uk-form-label">' . $s->entities($this->_('Choose file')) . '</label>' .
		                '<div class="uk-form-controls">' .
		                    '<div uk-form-custom="target: true">' .
		                        '<input type="file" name="video_file" accept="video/*" required>' .
		                        '<input class="uk-input uk-form-small" type="text" placeholder="' . $s->entities($this->_('Select video file')) . '" disabled>' .
		                    '</div>' .
		                '</div>' .
		                '<div class="uk-text-small uk-text-muted">mp4, mkv, mov, avi, webm — up to 30 GB</div>' .
		            '</div>' .
		            '<div class="uk-width-1-3@m">' .
		                '<label class="uk-form-label">&nbsp;</label>' .
		                '<div class="uk-form-controls">' .
		                '<button type="submit" name="upload_video" value="1" class="uk-button uk-button-primary uk-button-small uk-width-1-1">' .
		                    $s->entities($this->_('Upload and transcode')) .
		                '</button>' .
		                '</div>' .
		            '</div>' .
		        '</div>' .
		    '</form>';

		    return $out;
	}


	protected function handleUpload() {
		$pages = $this->wire('pages');
		$sanitizer = $this->wire('sanitizer');

		$pageId = (int) $this->input->post('target_page_id');
		$page = $pageId ? $pages->get($pageId) : null;

		if(!$page || !$page->id) {
			$this->error($this->_('Please select a target page.'));
			$this->session->redirect('./');
			return '';
		}

		$lumenField = null;
		foreach($page->fields as $f) {
			if($f->type instanceof FieldtypeLumen) { $lumenField = $f; break; }
		}

		if(!$lumenField) {
			$this->error($this->_('Selected page has no Lumen video field.'));
			$this->session->redirect('./');
			return '';
		}

		$fieldName = 'video_file';
		   if(empty($_FILES[$fieldName]['tmp_name']) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
		        $this->error($this->_('No file uploaded or upload failed.'));
		        $this->session->redirect('./');
		        return '';
		    }

		$tmpPath = $_FILES[$fieldName]['tmp_name'];
		$rawName = basename($_FILES[$fieldName]['name']);
		$baseName = (string) pathinfo($rawName, PATHINFO_FILENAME);
		$extension = strtolower((string) pathinfo($rawName, PATHINFO_EXTENSION));

		$baseName = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName);
		$baseName = trim($baseName, '._-');
		if(!$baseName) $baseName = 'video';
		if(strlen($baseName) > 120) $baseName = substr($baseName, 0, 120);
		if($extension) {
			$extension = preg_replace('/[^A-Za-z0-9]/', '', $extension);
			$baseName .= '.' . $extension;
		}

		$originalName = time() . '_' . $baseName;

		$uploadDir = $this->wire('config')->paths->assets . 'lumen-uploads/';
		if(!is_dir($uploadDir)) wireMkdir($uploadDir);

		$destPath = $uploadDir . $originalName;
		   if(!move_uploaded_file($tmpPath, $destPath)) {
		        $this->error($this->_('Failed to save uploaded file.'));
		        $this->session->redirect('./');
		        return '';
		    }

		    $page->of(false);
		    $page->{$lumenField->name}->add($destPath);
		    $page->save();

		    @unlink($destPath);

		    $lumen = $this->wire('modules')->get('Lumen');
		    $msg = $lumen->localStorage
		        ? $this->_('Video uploaded to %s.')
		        : $this->_('Video uploaded to %s. Cloudflare Stream is transcoding…');
		    $this->message(sprintf($msg, $page->path));

		$this->session->redirect($page->editUrl());
		return '';
	}


	protected function refreshStatuses() {
		$pages = $this->wire('pages');

		/** @var InputfieldLumen $inputfield */
		$inputfield = $this->wire('modules')->get('InputfieldLumen');

		$lumen = $this->wire('modules')->get('Lumen');
		$inputfield->cfAccountId = $lumen->cfAccountId;
		$inputfield->cfApiToken = $lumen->cfApiToken;
		$inputfield->localStorage = $lumen->localStorage;
		if($lumen->localStorage) {
			$this->message($this->_('Local storage mode is enabled; there is nothing to upload to Stream.'));
			$this->session->redirect('./');
			return;
		}

		$checked = 0;
		$updated = 0;
		$errors = 0;
		$recovered = 0;
		$remaining = false;

		foreach($this->getLumenFields() as $field) {
			foreach($pages->find("{$field->name}.count>0, include=all") as $page) {
				$value = $page->get($field->name);
				foreach($this->videoFiles($value) as $pagefile) {
					if($pagefile->stream_uid && $pagefile->stream_ready) {
						continue;
					}

					if($checked < self::REFRESH_BATCH_SIZE) {
						try {
							if(!$pagefile->stream_uid) {
								if($inputfield->recoverMissingStreamUpload($pagefile)) $recovered++;
							} elseif($inputfield->checkStreamStatus($pagefile)) {
								$updated++;
							}
						} catch(\Exception $e) {
							$errors++;
							$checked++;
							if(self::API_RATE_LIMIT_US > 0) usleep(self::API_RATE_LIMIT_US);
							continue;
						}
						$checked++;
						if(self::API_RATE_LIMIT_US > 0) usleep(self::API_RATE_LIMIT_US);
					} else {
						$remaining = true;
						break 3;
					}
				}
			}
		}
		$messages = array();
		if($recovered > 0) $messages[] = sprintf($this->_('%d local video(s) uploaded to Stream.'), $recovered);
		if($updated > 0) $messages[] = sprintf($this->_('%d video(s) became ready.'), $updated);
		if($checked > 0 && $updated === 0 && $recovered === 0) $messages[] = sprintf($this->_('%d checked — none ready yet.'), $checked);
		if($errors > 0) $messages[] = sprintf($this->_('%d error(s).'), $errors);
		if($remaining) $messages[] = $this->_('Some videos remain. Refresh again.');

		if($messages) $this->message(implode(' ', $messages));
		else $this->message($this->_('Nothing to check.'));

		$this->session->redirect('./');
	}
}
