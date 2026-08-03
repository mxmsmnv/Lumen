<?php namespace ProcessWire;

trait FieldtypeLumenSchemaTrait {
	/**
	 * Scan all Lumen fields and add any missing database columns
	 *
	 * Runs on module init, cached daily.
	 */
	protected function migrateExistingFieldSchemas() {
	    $cache = $this->wire('cache');
	    $cacheKey = 'lumen_schema_migrated_v' . self::getModuleInfo()['version'];
	    if($cache->get($cacheKey)) return;

	    $database = $this->wire('database');
	    $fields = $this->wire('fields');
	    if(!$fields) return;

	    $allOk = true;

	    foreach($fields as $field) {
	        if(!($field->type instanceof FieldtypeLumen)) continue;

	        $table = $field->getTable();
	        if(!$table) continue;

	        $expected = $this->getDatabaseSchema($field);
	        unset($expected['keys']);

	        $existingCols = array();
	        try {
	            $result = $database->query("SHOW COLUMNS FROM `{$table}`");
	            if($result) {
	                while($row = $result->fetch(\PDO::FETCH_ASSOC)) {
	                    $existingCols[$row['Field']] = true;
	                }
	            }
	        } catch(\Exception $e) {
	            $allOk = false;
	            continue;
	        }

	        foreach($expected as $colName => $colDef) {
	            if(isset($existingCols[$colName])) continue;
	            if(is_array($colDef)) continue;
	            try {
	                $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$colName}` {$colDef}";
	                $database->exec($sql);
	                $this->log->save('lumen-info', "Schema migration: added {$colName} to {$table}");
	            } catch(\Exception $e) {
	                $allOk = false;
	                $this->log->save('lumen-error', "Schema migration failed for {$colName} on {$table}: {$e->getMessage()}");
	            }
	        }
	    }

	    // Only cache when all tables are fully migrated
	    if($allOk) {
	        $cache->save($cacheKey, true, WireCache::expireDaily);
	    }
	}

	/**
	 * Get inputfield for this fieldtype
	 */
	public function getInputfield(Page $page, Field $field) {
		$inputfield = $this->modules->get('InputfieldLumen');
		$inputfield->class = $this->className();

		// Get configuration from the central Lumen module
		$moduleConfig = $this->modules->getConfig('Lumen');

		// Transfer configuration from module to inputfield
		if(!empty($moduleConfig['cfAccountId'])) {
			$inputfield->cfAccountId = $moduleConfig['cfAccountId'];
		}
		if(!empty($moduleConfig['cfApiToken'])) {
			$inputfield->cfApiToken = $moduleConfig['cfApiToken'];
		}
		if(isset($moduleConfig['requireSignedUrls'])) {
			$inputfield->requireSignedUrls = $moduleConfig['requireSignedUrls'];
		}
		if(isset($moduleConfig['maxDurationSeconds'])) {
			$inputfield->maxDurationSeconds = $moduleConfig['maxDurationSeconds'];
		}
		if(isset($moduleConfig['localStorage'])) {
			$inputfield->localStorage = $moduleConfig['localStorage'];
		}

		return $inputfield;
	}

	/**
	 * Convert database rows into Pagefile objects and hydrate Stream metadata.
	 *
	 * FieldtypeFile only knows about the native file columns. Lumen stores extra
	 * Stream columns in the same field table, so they must be copied onto each
	 * Pagefile here or every request will see stream_uid as empty again.
	 */
	public function ___wakeupValue(Page $page, Field $field, $value) {
		if($value instanceof Pagefiles) return $value;

		$pagefiles = $this->getBlankValue($page, $field);
		if(empty($value)) return $pagefiles;

		if(!is_array($value) || array_key_exists('data', $value)) $value = array($value);

		foreach($value as $v) {
			if(empty($v['data'])) continue;
			$pagefile = $this->getBlankPagefile($pagefiles, $v['data']);
			$pagefile->description(true, $v['description'] ?? '');
			if(isset($v['modified'])) $pagefile->modified = $v['modified'];
			if(isset($v['created'])) $pagefile->created = $v['created'];
			if(isset($v['tags'])) $pagefile->tags = $v['tags'];
			if(isset($v['filesize'])) $pagefile->fSize = $v['filesize'];

			foreach($this->getStreamMetadataColumns() as $column => $default) {
				$pagefile->$column = array_key_exists($column, $v) ? $v[$column] : $default;
			}
			$pagefile->stream_ready = (int) $pagefile->stream_ready;
			$pagefile->stream_page_id = (int) $pagefile->stream_page_id;
			$pagefile->stream_views = (int) $pagefile->stream_views;

			$pagefile->setTrackChanges(true);
			$pagefiles->add($pagefile);
		}

		$pagefiles->resetTrackChanges(true);
		return $pagefiles;
	}

	/**
	 * Convert Pagefile objects back into database rows with Stream metadata.
	 */
	public function ___sleepValue(Page $page, Field $field, $value) {
		$sleepValue = array();
		if(!$value instanceof Pagefiles) return $sleepValue;

		foreach($value as $pagefile) {
			$item = array(
				'data' => $pagefile->basename,
				'description' => $pagefile->description(true),
				'filesize' => $pagefile->fSize,
			);

			if($field->fileSchema & self::fileSchemaDate) {
				$item['modified'] = date('Y-m-d H:i:s', $pagefile->modified);
				$item['created'] = date('Y-m-d H:i:s', $pagefile->created);
			}

			if($field->fileSchema & self::fileSchemaTags) {
				$item['tags'] = $pagefile->tags;
			}

			foreach($this->getStreamMetadataColumns() as $column => $default) {
				$item[$column] = isset($pagefile->$column) ? $pagefile->$column : $default;
			}

			$sleepValue[] = $item;
		}

		return $sleepValue;
	}

	protected function getStreamMetadataColumns() {
		return array(
			'stream_uid' => null,
			'stream_status' => 'queued',
			'stream_ready' => 0,
			'stream_duration' => null,
			'stream_width' => null,
			'stream_height' => null,
			'stream_category' => null,
			'stream_tags' => null,
			'stream_page_id' => 0,
			'stream_poster' => null,
			'stream_subtitles' => null,
			'stream_trim_start' => null,
			'stream_trim_end' => null,
			'stream_views' => 0,
		);
	}

	/**
	 * Get database schema
	 */
	public function getDatabaseSchema(Field $field) {
		$schema = parent::getDatabaseSchema($field);

		// Ensure PRIMARY KEY is set correctly
		$schema['keys']['primary'] = 'PRIMARY KEY (`pages_id`, `sort`)';

		    // Add Cloudflare Stream specific fields
		    $schema['stream_uid'] = 'varchar(100) DEFAULT NULL';
		    $schema['stream_status'] = "varchar(20) NOT NULL DEFAULT 'queued'";
		    $schema['stream_ready'] = 'tinyint(1) NOT NULL DEFAULT 0';
		    $schema['stream_duration'] = 'int DEFAULT NULL';
		    $schema['stream_width'] = 'int DEFAULT NULL';
		    $schema['stream_height'] = 'int DEFAULT NULL';
		    $schema['stream_category'] = 'varchar(255) DEFAULT NULL';
		    $schema['stream_tags'] = 'varchar(500) DEFAULT NULL';
		    $schema['stream_page_id'] = 'int UNSIGNED NOT NULL DEFAULT 0';
		    $schema['stream_poster'] = 'varchar(500) DEFAULT NULL';
		    $schema['stream_subtitles'] = 'text DEFAULT NULL';
		    $schema['stream_trim_start'] = 'decimal(10,3) DEFAULT NULL';
		    $schema['stream_trim_end'] = 'decimal(10,3) DEFAULT NULL';
		    $schema['stream_views'] = 'int UNSIGNED NOT NULL DEFAULT 0';

		   // Indexes for common queries
		   $schema['keys']['stream_uid'] = 'KEY stream_uid (stream_uid)';
		   $schema['keys']['stream_ready'] = 'KEY stream_ready (stream_ready)'; // For LazyCron queries
		   $schema['keys']['stream_page_id'] = 'KEY stream_page_id (stream_page_id)';
		   $schema['keys']['stream_category'] = 'KEY stream_category (stream_category)';

		   return $schema;
	}


	public function hookPagesSaved(HookEvent $event) {
		$page = $event->arguments(0);
		foreach($page->fields as $field) {
			if(!($field->type instanceof self)) continue;
			$value = $page->get($field->name);
			$files = $value instanceof Pagefile
				? array($value)
				: ($value instanceof Pagefiles ? iterator_to_array($value) : array());
			foreach($files as $pagefile) {
				$this->saveStreamMetadata($pagefile);
			}
		}
	}

	public function saveStreamMetadata(Pagefile $pagefile) {
		$field = $pagefile->field;
		$page = $pagefile->page;
		$table = $field->getTable();
		$sql = "UPDATE `{$table}` SET
			stream_uid = :stream_uid, stream_status = :stream_status,
			stream_ready = :stream_ready, stream_duration = :stream_duration,
			stream_width = :stream_width, stream_height = :stream_height,
			stream_category = :stream_category, stream_tags = :stream_tags,
			stream_page_id = :stream_page_id, stream_poster = :stream_poster,
			stream_subtitles = :stream_subtitles, stream_trim_start = :stream_trim_start,
			stream_trim_end = :stream_trim_end, stream_views = :stream_views
			WHERE pages_id = :pages_id AND `sort` = :sort";
		$stmt = $this->wire('database')->prepare($sql);
		$stmt->execute(array(
			':stream_uid' => $pagefile->stream_uid ?: null,
			':stream_status' => $pagefile->stream_status ?: 'queued',
			':stream_ready' => $pagefile->stream_ready ? 1 : 0,
			':stream_duration' => $pagefile->stream_duration ? (int)$pagefile->stream_duration : null,
			':stream_width' => $pagefile->stream_width ? (int)$pagefile->stream_width : null,
			':stream_height' => $pagefile->stream_height ? (int)$pagefile->stream_height : null,
			':stream_category' => $pagefile->stream_category ?: null,
			':stream_tags' => $pagefile->stream_tags ?: null,
			':stream_page_id' => (int)$pagefile->stream_page_id,
			':stream_poster' => $pagefile->stream_poster ?: null,
			':stream_subtitles' => $pagefile->stream_subtitles ?: null,
			':stream_trim_start' => $pagefile->stream_trim_start !== null && $pagefile->stream_trim_start !== '' ? (float)$pagefile->stream_trim_start : null,
			':stream_trim_end' => $pagefile->stream_trim_end !== null && $pagefile->stream_trim_end !== '' ? (float)$pagefile->stream_trim_end : null,
			':stream_views' => (int)$pagefile->stream_views,
			':pages_id' => (int)$page->id,
			':sort' => (int)$pagefile->sort,
		));
	}

	/**
	 * Install
	 */
	public function ___install() {
		// Installation handled by schema
	}

	/**
	 * Uninstall
	 */
	public function ___uninstall() {
		// Cleanup handled by ProcessWire
	}
}
