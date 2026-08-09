<?php namespace ProcessWire;

trait ProcessLumenSettingsTrait {


		protected function renderSettingsPanel($showHeading = true) {
		    /** @var Lumen $lumen */
		    $lumen = $this->wire('modules')->get('Lumen');
		    $s = $this->wire('sanitizer');

		    $accountId = $s->entities($lumen->cfAccountId);
		    $hasApiToken = trim((string)$lumen->cfApiToken) !== '';
		    $localStorage = (bool) $lumen->localStorage;
		    $debugMode = (bool) $lumen->debugMode;
		    $maxDuration = (int) $lumen->maxDurationSeconds;

		    $heading = $showHeading ? '<div class="lumen-section-head">' .
					'<div><h2 class="uk-h3 uk-margin-remove">' . $s->entities($this->_('Settings')) . '</h2>' .
					'<p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">' .
						$s->entities($this->_('Connection, upload limits, local development, and diagnostics.')) .
					'</p></div>' .
				'</div>' : '';
		    $settings = $heading . '<div class="uk-card uk-card-default uk-card-small uk-card-body">' .
		                    '<div class="uk-alert-primary uk-margin-remove-top" uk-alert>' .
		                        '<p class="uk-margin-remove uk-text-small">' .
		                            '<strong>' . $s->entities($this->_('Cloudflare Images and Stream setup')) . '</strong><br>' .
		                            $s->entities($this->_('Activate the Images and Stream plan in Cloudflare, paste your Account ID and a token with Stream Write / Stream:Edit permission, save, then click Test.')) .
		                        '</p>' .
		                    '</div>' .
		                    '<form method="post" class="uk-form-stacked">' .
		                        $this->csrfInput() .
		                        '<div class="uk-grid-small" uk-grid>' .
		                            '<div class="uk-width-1-2@m">' .
		                                '<label class="uk-form-label">' . $s->entities($this->_('Cloudflare Account ID')) . '</label>' .
		                                '<div class="uk-form-controls">' .
		                                    '<input class="uk-input" type="text" name="cf_account_id" ' .
		                                        'value="' . $accountId . '" ' .
		                                        'placeholder="' . $s->entities($this->_('32-character ID from Dashboard URL')) . '">' .
		                                '</div>' .
		                            '</div>' .
		                            '<div class="uk-width-1-2@m">' .
		                                '<label class="uk-form-label">' . $s->entities($this->_('Cloudflare API Token')) . '</label>' .
		                                '<div class="uk-form-controls">' .
		                                    '<input class="uk-input" type="password" name="cf_api_token" ' .
		                                        'value="" autocomplete="new-password" ' .
		                                        'placeholder="' . $s->entities($hasApiToken
													? $this->_('Configured — enter a new token to replace it')
													: $this->_('Token with Stream Write / Stream:Edit permission')) . '">' .
		                                '</div>' .
		                            '</div>' .
		                        '</div>' .
		                        '<div class="uk-grid-small uk-margin-small-top" uk-grid>' .
		                            '<div class="uk-width-1-2@m">' .
		                                '<label class="uk-form-label">' . $s->entities($this->_('Max Duration (seconds)')) . '</label>' .
		                                '<p class="uk-text-small uk-text-muted uk-margin-remove-top">' .
		                                    $s->entities($this->_('Cloudflare reserves this duration for upload URLs. Keep it close to your real maximum. API max: 36000 seconds.')) .
		                                '</p>' .
		                                '<div class="uk-form-controls">' .
		                                    '<input class="uk-input" type="number" name="max_duration" ' .
		                                        'value="' . $maxDuration . '" min="1" max="36000">' .
		                                '</div>' .
		                            '</div>' .
		                            '<div class="uk-width-1-2@m">' .
		                                '<label class="uk-form-label">&nbsp;</label>' .
		                                '<div class="uk-form-controls">' .
		                                    '<label class="uk-display-block uk-margin-small-top">' .
		                                        '<input class="uk-checkbox" type="checkbox" name="local_storage" value="1"' .
		                                            ($localStorage ? ' checked' : '') . '> ' .
		                                        '<span class="uk-text-bold">' . $s->entities($this->_('Local Storage Mode')) . '</span>' .
		                                        '<br><span class="uk-text-small uk-text-muted">' .
		                                            $s->entities($this->_('Keep videos on server. No Cloudflare account needed.')) .
		                                        '</span>' .
		                                    '</label>' .
		                                    '<label class="uk-display-block uk-margin-small-top">' .
		                                        '<input class="uk-checkbox" type="checkbox" name="debug_mode" value="1"' .
		                                            ($debugMode ? ' checked' : '') . '> ' .
		                                        '<span class="uk-text-bold">' . $s->entities($this->_('Debug Mode')) . '</span>' .
		                                        '<br><span class="uk-text-small uk-text-muted">' .
		                                            $s->entities($this->_('Show detailed Lumen events here and write them to the ProcessWire lumen-events log.')) .
		                                        '</span>' .
		                                    '</label>' .
		                                '</div>' .
		                            '</div>' .
		                        '</div>' .
		                        '<div class="uk-margin-small-top">' .
			                            '<button type="submit" name="save_settings" value="1" class="uk-button uk-button-primary">' .
			                                $s->entities($this->_('Save Settings')) .
		                            '</button>' .
		                        '</div>' .
		                    '</form>' .
				'</div>';

		    return $settings;
		}


		protected function handleSaveSettings() {
		    $lumen = $this->wire('modules')->get('Lumen');
		    $data = array(
		        'cfAccountId' => $this->input->post('cf_account_id'),
		        'cfApiToken' => trim((string)$this->input->post('cf_api_token')),
		        'localStorage' => (bool) $this->input->post('local_storage'),
		        'debugMode' => (bool) $this->input->post('debug_mode'),
		        'maxDurationSeconds' => max(1, min(36000, (int) $this->input->post('max_duration'))),
		    );

		    // Merge with existing config to preserve other settings
		    $config = $this->wire('modules')->getConfig('Lumen');
		    $config['cfAccountId'] = $data['cfAccountId'];
		    if($data['cfApiToken'] !== '') $config['cfApiToken'] = $data['cfApiToken'];
		    $config['localStorage'] = $data['localStorage'];
		    $config['debugMode'] = $data['debugMode'];
		    $config['maxDurationSeconds'] = $data['maxDurationSeconds'];
		    $this->wire('modules')->saveConfig('Lumen', $config);

		    // Clear caches
		    $this->wire('cache')->delete('lumen_connection_status');

		    $this->message($this->_('Settings saved.'));
		    $this->session->redirect($this->adminSectionUrl('settings'));
		    return '';
		}


		protected function handleClearEventLog() {
		    $lumen = $this->wire('modules')->get('Lumen');
		    $lumen->clearEventLog();
		    $this->message($this->_('Lumen event log cleared.'));
		    $this->session->redirect($this->adminSectionUrl('event-log'));
		    return '';
		}


		protected function renderEventLogPanel($showHeading = true) {
		    /** @var Lumen $lumen */
		    $lumen = $this->wire('modules')->get('Lumen');
		    $s = $this->wire('sanitizer');
		    $events = $lumen->getEventLog(30);

		    $rows = '';
		    foreach($events as $event) {
		        $levelClass = 'uk-label';
		        if(strpos($event, '[ERROR]') !== false) $levelClass .= ' uk-label-danger';
		        elseif(strpos($event, '[WARNING]') !== false) $levelClass .= ' uk-label-warning';
		        elseif(strpos($event, '[INFO]') !== false) $levelClass .= ' uk-label-success';

		        $level = 'DEBUG';
		        if(preg_match('/\\[(ERROR|WARNING|INFO|DEBUG)\\]/', $event, $m)) $level = $m[1];
		        $message = preg_replace('/^.*?\\[(ERROR|WARNING|INFO|DEBUG)\\]\\s*/', '', $event);

		        $rows .= '<tr>' .
		            '<td class="uk-width-small"><span class="' . $levelClass . '">' . $s->entities($level) . '</span></td>' .
		            '<td><code class="uk-text-small uk-text-break">' . $s->entities($message) . '</code></td>' .
		        '</tr>';
		    }

		    if($rows === '') {
		        $rows = '<tr><td colspan="2" class="uk-text-muted">' .
		            $s->entities($this->_('No Lumen events logged yet. Run Test, upload a video, refresh statuses, or enable Debug Mode.')) .
		        '</td></tr>';
		    }

		    $heading = $showHeading ? '<div class="lumen-section-head">' .
					'<div><h2 class="uk-h3 uk-margin-remove">' . $s->entities($this->_('Event Log')) . '</h2>' .
					'<p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">' .
						$s->entities($this->_('Recent connection, upload, API, and processing events.')) .
					'</p></div>' .
				'</div>' : '';

		    return $heading . '<div class="uk-card uk-card-default uk-card-small uk-card-body">' .
		        '<div class="uk-grid-small uk-flex-middle uk-margin-small-bottom" uk-grid>' .
		            '<div class="uk-width-expand">' .
		                '<p class="uk-text-small uk-text-muted uk-margin-remove">' .
		                    $s->entities($lumen->debugMode
		                        ? $this->_('Debug Mode is on. Detailed Lumen events are being recorded.')
		                        : $this->_('Errors are always recorded. Turn on Debug Mode to record detailed upload/API/status events.')) .
		                '</p>' .
		            '</div>' .
		            '<div class="uk-width-auto">' .
		                '<form method="post" class="uk-margin-remove">' .
		                    $this->csrfInput() .
		                    '<button type="submit" name="clear_event_log" value="1" class="uk-button uk-button-default uk-button-small">' .
		                        $s->entities($this->_('Clear Log')) .
		                    '</button>' .
		                '</form>' .
		            '</div>' .
		        '</div>' .
		        '<div class="uk-overflow-auto"><table class="uk-table uk-table-divider uk-table-small uk-table-middle">' .
		            '<tbody>' . $rows . '</tbody>' .
		        '</table></div>' .
		    '</div>';
		}


		protected function handleAssignTemplate() {
		    $fieldId = (int) $this->input->post('assign_field_id');
		    $templateId = (int) $this->input->post('assign_template_id');

		    $field = $this->wire('fields')->get($fieldId);
		    $template = $this->wire('templates')->get($templateId);

		    if(!$field || !$field->id || !$template || !$template->id) {
		        $this->error($this->_('Invalid field or template.'));
		        $this->session->redirect($this->adminSectionUrl('upload'));
		        return '';
		    }

		    // Assign field to template
		    $fieldTemplates = $field->getTemplates();
		    $fieldTemplates->add($template);
		    $field->save();

		    $this->message(sprintf(
		        $this->_('Field %s assigned to template %s.'),
		        $field->name, $template->name
		    ));
		    $this->session->redirect($this->adminSectionUrl('upload'));
		    return '';
		}
}
