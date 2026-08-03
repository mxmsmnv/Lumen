<?php namespace ProcessWire;

trait ProcessLumenActionsTrait {
	/**
	 * Create a new Lumen field and redirect to its edit screen
	 */
	public function ___executeAddField() {
		if(!$this->input->post('add_field')) {
			$this->error($this->_('Use the Create Field button from the Lumen dashboard.'));
			$this->session->redirect($this->page->parent->url . 'lumen/');
			return '';
		}
		$this->validateCsrf();

		$fields = $this->wire('fields');

		// Generate unique name
		$name = 'lumen_video';
		$n = 1;
		while($fields->get($name)) {
			$name = "lumen_video_$n";
			$n++;
		}

		$field = new Field();
		   $field->type = $this->wire('modules')->get('FieldtypeLumen');
		   $field->name = $name;
		   $field->label = 'Lumen Video';
		   $field->set('extensions', 'mp4 mkv mov avi flv ts ps mxf lxf gxf 3gp webm mpg');
		   $field->save();

		$this->message(sprintf($this->_('Lumen field created: %s'), $name));
		$this->session->redirect($this->wire('config')->urls->admin . "setup/field/edit?id={$field->id}");
	}
}
