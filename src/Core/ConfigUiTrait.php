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
		$fs->label = __('Cloudflare Credentials');		$fs->description = __('Connect your Cloudflare Images & Stream account. Choose the free Images & Stream plan first, then create an API token for this module.');

		// Help block
		$help = $modules->get('InputfieldMarkup');
		$help->value =
			'<div class="uk-alert uk-alert-primary uk-margin-remove-bottom">'
			. '<p class="uk-text-small">'
			. '<strong>' . __('Connect checklist') . '</strong><br>'
			. __('1. In Cloudflare, open Media → Stream → Plans and activate Images & Stream.') . '<br>'
			. __('2. Copy your Account ID from the Cloudflare dashboard URL or account overview.') . '<br>'
			. __('3. Create an API token with Stream Write / Stream:Edit permission for this account.') . '<br><br>'
			. '<strong>' . __('Account ID') . '</strong> — ' . __('The dashboard URL looks like:') . '<br>'
			. '<code>dash.cloudflare.com/<mark>YOUR_ACCOUNT_ID</mark>/stream</code><br>'
			. '<strong>' . __('API Token') . '</strong> — '
			. sprintf(__('Go to %s and create a token with Stream Write / Stream:Edit permission.'),
				'<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare API Tokens →</a>') . '<br>'
			. __('After saving, visit') . ' <strong>Setup → Lumen</strong> ' . __('and click Test to verify the connection.')
			. '</p>'
			. '</div>';
		$fs->add($help);

		// Account ID
		$f = $modules->get('InputfieldPassword');
		$f->name = 'cfAccountId';
		$f->label = __('Account ID');
		$f->description = __('The 32-character ID from your Cloudflare Dashboard URL.');
		$f->required = true;
		$f->attr('placeholder', 'e.g. 1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d');
		$f->columnWidth = 50;
		if(isset($data['cfAccountId'])) $f->value = $data['cfAccountId'];
		$fs->add($f);

		// API Token
		$f = $modules->get('InputfieldText');
		$f->name = 'cfApiToken';
		$f->label = __('API Token');
		$f->description = __('Token with Stream Write / Stream:Edit permission.');
		$f->required = true;
		$f->attr('placeholder', 'Paste your token here…');
		$f->columnWidth = 50;
		if(isset($data['cfApiToken'])) $f->value = $data['cfApiToken'];
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
