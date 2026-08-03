<?php namespace ProcessWire;

trait ProcessLumenFieldsTrait {


	protected function getLumenFields() {
		$result = array();
		foreach($this->wire('fields') as $field) {
			if($field->type instanceof FieldtypeLumen) $result[] = $field;
		}
		return $result;
	}
}
