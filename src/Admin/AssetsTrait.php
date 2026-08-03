<?php namespace ProcessWire;

trait ProcessLumenAssetsTrait {

	protected function renderCopyScript() {
		$this->copyScriptRendered = true;
		return '';
	}
}
