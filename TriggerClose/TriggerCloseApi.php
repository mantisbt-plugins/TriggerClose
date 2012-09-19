<?php

require_once dirname(__FILE__).'/../../core.php';

class TriggerCloseApi {

	const MIN_SECONDS = 300;
	const DISABLED = 0;

	private static $verbose;
	private static $dry_run;

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
	function available_privileges() {
		return array(
			VIEWER => 'viewer',
			REPORTER => 'reporter',
			UPDATER => 'updater',
			DEVELOPER => 'developer',
			MANAGER => 'manager',
			ADMINISTRATOR => 'administrator'
		);
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
	 * Proxy for @see close_issues_matching_criteria with criteria
	 * from saved plugin config.
	 *
	 * @return array of bug_ids that were closed
	 */
	function auto_close() {
		return $this->close_issues_matching_criteria(
			plugin_config_get('categories'),
			plugin_config_get('statuses'),
			plugin_config_get('privileges'),
			plugin_config_get('after_seconds'),
			plugin_config_get('message')
		);
	}

	/**
	 * Controller method for triggering via cli
	 */
	static function cli() {
		if(PHP_SAPI != "cli") {
			if(!headers_sent()) {
				header("HTTP/1.0 400 Bad Request");
			}
			printf("You must use %s from cli", __METHOD__);
			exit();
		}
		$argv = $GLOBALS['argv'];

		if(in_array("-h", $argv) || in_array("--help", $argv)) {
			echo self::cli_usage();
			exit(0);
		}

		// Needed since we rely on global state
		// in plugin_config_get(), amongst others
		plugin_push_current('TriggerClose');
		self::$dry_run = in_array("-n", $argv) || in_array("--dry-run", $argv);
		self::$verbose = self::$dry_run || in_array("-v", $argv);

		if(!self::cli_login()) {
			echo self::cli_usage("Could not login with given user, check TriggerClose's plugin settings in the GUI");
			exit(1);
		}

		$api = new self;
		try {
			$closed_issues = $api->auto_close();
		} catch(InvalidArgumentException $e) {
			self::cli_message($e->getMessage());
			exit(1);
		}
		self::cli_message(sprintf("Closed %d issues:", count($closed_issues)));
		foreach($closed_issues as $id => $summary) {
			self::cli_message(sprintf("%d: %s", $id, $summary));
		}
		exit(0);
	}

	/**
	 * Tries to login with the configured username
	 *
	 * @param boolean $verbosy = false
	 * @return false
	 */
	private static function cli_login($verbose = false) {
		$user_id = plugin_config_get('user');
		if(!user_exists($user_id)) {
			self::cli_message("User with saved ID was not found");
			return false;
		}
		if(!auth_attempt_script_login(user_get_name($user_id))) {
			self::cli_message("Could not login as user with saved ID");
			return false;
		}
		return true;
	}

	private static function cli_message($string) {
		if(self::$verbose) {
			echo $string."\n";
		}
	}

	/**
	 * @param string $error = null
	 * @return string
	 */
	private static function cli_usage($error = null) {
		$usage = <<<USAGE
TriggerCloseApi.php [options]

Close Mantis plugins based on inactivity.


OPTIONS

	-v		Verbose output, print closed issues
	-h | --help	This helptext
	-n | --dry-run	Only print what would happen, do not change any statuses

USAGE;
		if($error) {
			return $error."\n\n".$usage;
		}
		return $usage;
	}
	
	/**
	 * @param array $categories
	 * @param array $statuses
	 * @param int $after_seconds
	 * @param string $message
	 * @return array [int id] => string summary
	 * @throws InvalidArgumentException
	 */
	function close_issues_matching_criteria(array $categories, array $statuses, array $privileges, $after_seconds, $message) {
		if(!$categories) {
			throw new InvalidArgumentException("You must provide at least one category for me to scan for issues in");
		}
		foreach($categories as $key => $category) {
			$categories[$key] = $category = (int) $category;
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
		if(self::DISABLED == $after_seconds && PHP_SAPI != "cli") {
			// Let the cli version fall through to the next check
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
				bt.summary
			FROM
				".db_get_table('mantis_bug_table')." AS bt
			INNER JOIN
				".db_get_table('mantis_bug_history_table')." AS bht
				ON
					bht.bug_id = bt.id
				AND
					bht.date_modified < %d
			INNER JOIN
				".db_get_table('mantis_user_table')." AS ut
				ON
					bt.handler_id = ut.id
				AND
					ut.access_level IN (%s)
			WHERE
				bt.status IN (%s)
				AND
				bt.category_id IN (%s)
			GROUP BY
				bht.bug_id",
				time() - $after_seconds,
				"'".implode("', '", $privileges)."'",
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
			if(!self::$dry_run) {
				bug_close($row['bug_id'], $message);
			}
			$closed[$row['bug_id']] = $row['summary'];
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
		$privileges = $this->available_privileges();
		$admin = ADMINISTRATOR;
		unset($privileges[$admin]);
		return array(
			'maybe_close_active' => 0,
			'after_seconds' => 0,
			'message' => 'Closing automatically, stayed too long in feedback state. Feel free to re-open with additional information if you think the issue is not resolved.',
			'categories' => array(),
			'userrights' => array(),
			'statuses' => array(FEEDBACK),
			'privileges' => $privileges,
			'user' => 1
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
	function validate_privilege($privilege) {
		return in_array(
			$privilege,
			array_keys($this->available_privileges())
		);
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

if(PHP_SAPI == "cli") {
	TriggerCloseApi::cli();
}
