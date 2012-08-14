<?php

class TriggerCloseApi {

	const MIN_SECONDS = 300;
	const DISABLED = 0;

	/**
	 * @return array [int id] => string label
	 */
	function available_categories() {
		static $categories;
		if(!$categories) {
			foreach(project_get_all_rows() as $project) {
				foreach(category_get_all_rows($project['id']) as $category) {
					$categories[$category['id']] = $project['name'].' &gt; '.$category['name'];
				}
			}
		}
		return $categories;
	}

	/**
	 * @return array [int id] => string label
	 */
	function available_statuses() {
		return array(
			FEEDBACK => 'feedback',
			ACKNOWLEDGED => 'acknowledged',
			CONFIRMED => 'confirmed',
			ASSIGNED => 'assigned',
			RESOLVED => 'resolved'
		);
	}
	
	/**
	 * @param array $categories
	 * @param array $statuses
	 * @param int $after_seconds
	 * @param string $message
 	 * @return array of bug_ids that were closed
	 * @throws InvalidArgumentException
	 */
	function close_issues_matching_criteria(array $categories, array $statuses, $after_seconds, $message) {
		foreach($categories as $category) {
			if(!$this->validate_category($category)) {
				throw new InvalidArgumentException("$category is an invalid category");
			}
		}

		foreach($statuses as $status) {
			if(!$this->validate_status($status)) {
				throw new InvalidArgumentException("$status is an invalid status");
			}
		}

		$after_seconds = (int) $after_seconds;
		if(self::DISABLED == $after_seconds) {
			return array();
		}
		if($after_seconds < self::MIN_SECONDS) {
			throw new InvalidArgumentException("Must provide at least ".self::MIN_SECONDS." as seconds lapsed before issue should be closed ($after_seconds given)");
		}

		if(!$message) {
			throw new InvalidArgumentException("Must provide message");
		}

		$sql = sprintf("
			SELECT
				bht.bug_id,
				bht.date_modified
			FROM
				".db_get_table('mantis_bug_table')." AS bt
			INNER JOIN
				".db_get_table('mantis_bug_history_table')." AS bht
				ON
					bht.bug_id = bt.id
				AND
					bht.date_modified < %d
			WHERE
				bt.status IN (%s)
				AND
				bt.category_id IN (%s)
			GROUP BY
				bht.bug_id",
				time() - $after_seconds,
				"'".implode("', '", $statuses)."'",
				"'".implode("', '", $categories)."'"

		);
		$query = db_query($sql);
		$count = db_num_rows($query);
		if(!$count) {
			return array();
		}
		$closed = array();
		while($count--) {
			$row = db_fetch_array($query);
			bug_close($row['bug_id'], $message);
			$closed[] = $row['bug_id'];
		}
		return $closed;
	}

	/**
	 * Proxy to TriggerClosePlugin::config() which is not conveniantely
	 * reachable.
	 *
	 * @return array
	 */
	function config() {
		return array(
			'maybe_close_active' => 0,
			'after_seconds' => 0,
			'message' => 'Closing automatically, stayed too long in feedback state.',
			'categories' => array(),
			'statuses' => array(FEEDBACK)
		);
	}

	/**
 	 * @param int $category_id
	 * @return boolean
	 */
	function validate_category($category_id) {
		return category_exists($category_id);
	}

	/**
	 * @param int $status
	 * @retun boolean
	 */
	function validate_status($status) {
		return in_array(
			$status,
			array_keys($this->available_statuses())
		);
	}
}
