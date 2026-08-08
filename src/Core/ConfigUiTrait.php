<?php namespace ProcessWire;

trait LumenConfigUiTrait {
	/**
	 * Module configuration — single source of truth for Cloudflare credentials
	 */
	public static function getModuleConfigInputfields(array $data) {
		$inputfields = new InputfieldWrapper();
		$modules = wire('modules');

		// ── Credentials ──────────────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = __('Cloudflare Credentials');
		$fs->description = __('Connect the Cloudflare account used by Images and Stream.');
		$fs->icon = 'cloud';

		// Help block
		$help = $modules->get('InputfieldMarkup');
		$help->label = __('Where to find these values');
		$help->icon = 'question-circle';
		$help->collapsed = Inputfield::collapsedYes;
		$help->value =
			'<ol class="uk-list uk-list-decimal uk-margin-remove-top">'
			. '<li>' . __('In Cloudflare, open Media → Stream → Plans and activate Images & Stream.') . '</li>'
			. '<li>' . __('Copy your Account ID from the Cloudflare dashboard URL or account overview.') . '</li>'
			. '<li>' . __('Create an API token with Stream Write / Stream:Edit permission for this account.') . '</li>'
			. '</ol>'
			. '<p class="uk-text-small"><strong>' . __('Account ID') . '</strong> — '
			. '<code>dash.cloudflare.com/<mark>YOUR_ACCOUNT_ID</mark>/stream</code><br>'
			. '<strong>' . __('API Token') . '</strong> — '
			. sprintf(__('Go to %s and create a token with Stream Write / Stream:Edit permission.'),
				'<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare API Tokens →</a>') . '<br>'
			. __('After saving, visit') . ' <strong>Setup → Lumen</strong> ' . __('and click Test to verify the connection.')
			. '</p>'
			;
		$fs->add($help);

		// Account ID
		$f = $modules->get('InputfieldText');
		$f->name = 'cfAccountId';
		$f->label = __('Account ID');
		$f->description = __('Public 32-character account identifier. This is not a password or API secret.');
		$f->required = true;
		$f->attr('placeholder', 'e.g. 1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d');
		$f->attr('autocomplete', 'off');
		$f->attr('spellcheck', 'false');
		$f->attr('maxlength', 32);
		$f->attr('pattern', '[A-Fa-f0-9]{32}');
		$f->icon = 'id-card-o';
		$f->columnWidth = 50;
		if(isset($data['cfAccountId'])) $f->value = $data['cfAccountId'];
		$fs->add($f);

		// API Token
		$storedToken = isset($data['cfApiToken']) ? (string)$data['cfApiToken'] : '';
		$f = $modules->get('InputfieldText');
		$f->name = 'cfApiToken';
		$f->label = __('API Token');
		$f->description = $storedToken !== ''
			? __('A token is configured. Enter a new token to replace it, or leave this field blank to keep the current token.')
			: __('Token with Stream Write / Stream:Edit permission. It is never displayed after saving.');
		$f->required = $storedToken === '';
		$f->attr('type', 'password');
		$f->attr('value', '');
		$f->attr('autocomplete', 'new-password');
		$f->attr('spellcheck', 'false');
		$f->attr('placeholder', $storedToken !== '' ? __('Enter a new token to replace the stored token') : __('Paste your token here…'));
		$f->icon = 'key';
		$f->columnWidth = 50;
		$f->addHookAfter('processInput', function(HookEvent $event) use ($storedToken) {
			$field = $event->object;
			if(trim((string)$field->attr('value')) === '' && $storedToken !== '') {
				$field->attr('value', $storedToken);
			}
		});
		$fs->add($f);

		$inputfields->add($fs);

		// ── Video Settings ──────────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = __('Video Settings');
		$f = $modules->get('InputfieldInteger');
		$f->name = 'maxDurationSeconds';
		$f->label = __('Max Duration');
		$f->description = __('Maximum allowed video length in seconds. Cloudflare reserves this duration when creating upload URLs, so keep it close to your real maximum. Default 3600 = 1 hour. API limit is 10 hours (36000).');
		$f->value = isset($data['maxDurationSeconds']) ? (int)$data['maxDurationSeconds'] : 3600;
		$f->min = 1;
		$f->max = 36000;
		$f->columnWidth = 50;
		$fs->add($f);

		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'requireSignedUrls';
		$f->label = __('Require Signed URLs');
		$f->description = __('Make Stream videos private. Leave off for normal public embeds; turn on only when playback must require a generated signed token.');
		$f->columnWidth = 50;
		if(isset($data['requireSignedUrls'])) $f->checked = $data['requireSignedUrls'];
		$fs->add($f);

		$inputfields->add($fs);

		// ── Advanced ────────────────────────────────────────────
		$fs = $modules->get('InputfieldFieldset');
		$fs->label = __('Advanced');		$fs->collapsed = InputfieldFieldset::collapsedYes;

		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'localStorage';
		$f->label = __('Local Storage Mode');
		$f->description = __('Keep videos on your server instead of uploading to Cloudflare. For development and testing only.');
		$f->columnWidth = 50;
		if(isset($data['localStorage'])) $f->checked = $data['localStorage'];
		$fs->add($f);

		$f = $modules->get('InputfieldCheckbox');
		$f->name = 'debugMode';
		$f->label = __('Debug Mode');
		$f->description = __('Write detailed Lumen events to Setup → Lumen and the ProcessWire log file "lumen-events". Errors are logged even when debug mode is off.');
		$f->columnWidth = 50;
		if(isset($data['debugMode'])) $f->checked = $data['debugMode'];
		$fs->add($f);

		$f = $modules->get('InputfieldText');
		$f->name = 'customerCodeOverride';
		$f->label = __('Customer Code Override');
		$f->description = __('Normally auto-detected from Cloudflare. Set manually only if playback, thumbnail, or HLS URLs use the wrong customer subdomain.');
		$f->columnWidth = 50;
		if(isset($data['customerCodeOverride'])) $f->value = $data['customerCodeOverride'];
		$fs->add($f);

		// Verify note
		$note = $modules->get('InputfieldMarkup');
		$note->value =
			'<p class="uk-text-small uk-text-muted">'			. __('After saving, open') . ' <a href="./../lumen/">Setup → Lumen</a> ' . __('to check the connection status and start uploading.')
			. '</p>';
		$fs->add($note);

		$inputfields->add($fs);

		return $inputfields;
	}
}
