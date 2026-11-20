<?php
/**
 * Copyright (c) 2026 Indaka Barody
 * Multi-Journal Control Panel
 * Developed for Open Journal Systems (OJS) 3.3.x.
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$pluginDir = dirname(__DIR__);
$ojsDir = '/Users/indakabarody/Documents/OJS/Plugins/ojs-3.3.0-24';

require_once $ojsDir . '/lib/pkp/lib/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Minimal OJS 3.3 shims for isolated testing if full OJS bootstrap is not loaded
if (!function_exists('import')) {
	function import($classPath) {
		global $ojsDir, $pluginDir;
		$rel = str_replace('.', '/', $classPath) . '.inc.php';
		if (file_exists($ojsDir . '/' . $rel)) {
			require_once $ojsDir . '/' . $rel;
			return;
		}
		if (file_exists($ojsDir . '/lib/pkp/' . $rel)) {
			require_once $ojsDir . '/lib/pkp/' . $rel;
			return;
		}
		if (strpos($classPath, 'plugins.generic.multiJournalControlPanel.') === 0) {
			$sub = substr($classPath, strlen('plugins.generic.multiJournalControlPanel.'));
			$pFile = $pluginDir . '/' . str_replace('.', '/', $sub) . '.inc.php';
			if (file_exists($pFile)) {
				require_once $pFile;
				return;
			}
		}
	}
}

if (!function_exists('strtolower_codesafe')) {
	function strtolower_codesafe($str) {
		return strtolower((string) $str);
	}
}

// Load locale translations into memory for __() testing
$GLOBALS['mjcpTestTranslations'] = ['en_US' => [], 'id_ID' => []];
$GLOBALS['mjcpActiveTestLocale'] = 'en_US';

foreach (['en_US', 'id_ID'] as $loc) {
	$poContent = file_get_contents($pluginDir . '/locale/' . $loc . '/locale.po');
	preg_match_all('/^msgid "([^"]+)"\nmsgstr "((?:[^"\\\\]|\\\\.)*)"/m', $poContent, $m, PREG_SET_ORDER);
	foreach ($m as $entry) {
		$GLOBALS['mjcpTestTranslations'][$loc][$entry[1]] = str_replace(['\\n', '\\"'], ["\n", '"'], $entry[2]);
	}
}

if (!function_exists('__')) {
	function __($key, $params = [], $locale = null) {
		$loc = $locale ? $locale : $GLOBALS['mjcpActiveTestLocale'];
		$msg = isset($GLOBALS['mjcpTestTranslations'][$loc][$key])
			? $GLOBALS['mjcpTestTranslations'][$loc][$key]
			: (isset($GLOBALS['mjcpTestTranslations']['en_US'][$key]) ? $GLOBALS['mjcpTestTranslations']['en_US'][$key] : '##' . $key . '##');
		foreach ((array) $params as $k => $v) {
			$msg = str_replace('{$' . $k . '}', (string) $v, $msg);
		}
		return $msg;
	}
}

if (!class_exists('Config')) {
	class Config {
		public static $vars = [
			'general' => ['installed' => true, 'time_zone' => 'UTC'],
			'files' => ['files_dir' => ''],
		];
		public static function getVar($section, $key, $default = null) {
			return isset(self::$vars[$section][$key]) ? self::$vars[$section][$key] : $default;
		}
	}
}
Config::$vars['files']['files_dir'] = sys_get_temp_dir() . '/mjcp_test_private_files_' . getmypid();

if (!class_exists('Validation')) {
	class Validation {
		public static $isSiteAdminFlag = false;
		public static function isSiteAdmin() {
			return self::$isSiteAdminFlag;
		}
		public static function redirectLogin() {}
	}
}

if (!class_exists('HookRegistry')) {
	class HookRegistry {
		public static $hooks = [];
		public static function register($hook, $callback) {
			self::$hooks[$hook][] = $callback;
		}
		public static function call($hook, $args = []) {
			return false;
		}
	}
}

if (!class_exists('DAORegistry')) {
	class DAORegistry {
		public static $daos = [];
		public static function registerDAO($name, $dao) {
			self::$daos[$name] = $dao;
		}
		public static function getDAO($name) {
			return isset(self::$daos[$name]) ? self::$daos[$name] : null;
		}
	}
}

if (!class_exists('AppLocale')) {
	class AppLocale {
		public static function getLocale() {
			return $GLOBALS['mjcpActiveTestLocale'];
		}
		public static function registerLocaleFile($locale, $file) {
			return true;
		}
	}
}

if (!class_exists('Application')) {
	if (!defined('ROUTE_PAGE')) {
		define('ROUTE_PAGE', 'page');
	}
	if (!defined('CONTEXT_SITE')) {
		define('CONTEXT_SITE', 0);
	}
	class MockDispatcher {
		public function url($request, $route, $context, $page, $op = null, $path = null, $params = null) {
			$u = 'http://localhost/index.php/' . ($context ? $context : 'index') . '/' . $page;
			if ($op) {
				$u .= '/' . $op;
			}
			if ($path) {
				$u .= '/' . (is_array($path) ? implode('/', $path) : $path);
			}
			if (!empty($params) && is_array($params)) {
				$u .= '?' . http_build_query($params);
			}
			return $u;
		}
	}
	class MockRequest {
		public function getContext() {
			return null;
		}
		public function getBaseUrl() {
			return 'http://localhost';
		}
		public function getDispatcher() {
			return new MockDispatcher();
		}
		public function getUser() {
			return null;
		}
	}
	class Application {
		public static function get() {
			return new self();
		}
		public function getRequest() {
			return new MockRequest();
		}
	}
}

class MockUser {
	protected $_id;
	protected $_username;
	public function __construct($id, $username) {
		$this->_id = (int) $id;
		$this->_username = (string) $username;
	}
	public function getId() {
		return $this->_id;
	}
	public function getUsername() {
		return $this->_username;
	}
}

// Initialize in-memory SQLite database via OJS 3.3's Illuminate\Database\Capsule\Manager
$capsule = new Capsule();
$capsule->addConnection([
	'driver' => 'sqlite',
	'database' => ':memory:',
	'prefix' => '',
]);
$capsule->setAsGlobal();

// Create core OJS 3.3 tables required for reading editorial data
Capsule::schema()->create('plugin_settings', function (Blueprint $table) {
	$table->string('plugin_name', 80);
	$table->bigInteger('context_id')->default(0);
	$table->string('setting_name', 80);
	$table->text('setting_value')->nullable();
	$table->string('setting_type', 16)->default('string');
});

Capsule::schema()->create('journals', function (Blueprint $table) {
	$table->bigInteger('journal_id')->autoIncrement();
	$table->string('path', 32);
	$table->float('seq')->default(1);
	$table->string('primary_locale', 14)->default('en_US');
	$table->tinyInteger('enabled')->default(1);
});

Capsule::schema()->create('journal_settings', function (Blueprint $table) {
	$table->bigInteger('journal_id');
	$table->string('locale', 14)->default('');
	$table->string('setting_name', 255);
	$table->text('setting_value')->nullable();
});

Capsule::schema()->create('issues', function (Blueprint $table) {
	$table->bigInteger('issue_id')->autoIncrement();
	$table->bigInteger('journal_id');
	$table->smallInteger('volume')->nullable();
	$table->string('number', 40)->nullable();
	$table->smallInteger('year')->nullable();
	$table->tinyInteger('published')->default(0);
	$table->tinyInteger('current')->default(0);
	$table->datetime('date_published')->nullable();
});

Capsule::schema()->create('issue_settings', function (Blueprint $table) {
	$table->bigInteger('issue_id');
	$table->string('locale', 14)->default('');
	$table->string('setting_name', 255);
	$table->text('setting_value')->nullable();
});

Capsule::schema()->create('submissions', function (Blueprint $table) {
	$table->bigInteger('submission_id')->autoIncrement();
	$table->bigInteger('context_id');
	$table->bigInteger('current_publication_id')->nullable();
	$table->datetime('date_submitted')->nullable();
	$table->datetime('date_last_activity')->nullable();
	$table->datetime('last_modified')->nullable();
	$table->bigInteger('stage_id')->default(1);
	$table->tinyInteger('status')->default(1);
	$table->tinyInteger('submission_progress')->default(0);
	$table->string('locale', 14)->default('en_US');
});

Capsule::schema()->create('publications', function (Blueprint $table) {
	$table->bigInteger('publication_id')->autoIncrement();
	$table->bigInteger('submission_id');
	$table->date('date_published')->nullable();
	$table->tinyInteger('status')->default(1);
});

Capsule::schema()->create('publication_settings', function (Blueprint $table) {
	$table->bigInteger('publication_id');
	$table->string('locale', 14)->default('');
	$table->string('setting_name', 255);
	$table->text('setting_value')->nullable();
});

Capsule::schema()->create('authors', function (Blueprint $table) {
	$table->bigInteger('author_id')->autoIncrement();
	$table->bigInteger('publication_id');
	$table->string('email', 90);
	$table->float('seq')->default(1);
});

Capsule::schema()->create('author_settings', function (Blueprint $table) {
	$table->bigInteger('author_id');
	$table->string('locale', 14)->default('');
	$table->string('setting_name', 255);
	$table->text('setting_value')->nullable();
});

Capsule::schema()->create('review_rounds', function (Blueprint $table) {
	$table->bigInteger('review_round_id')->autoIncrement();
	$table->bigInteger('submission_id');
	$table->bigInteger('stage_id')->default(3);
	$table->tinyInteger('round')->default(1);
	$table->bigInteger('status')->nullable();
});

Capsule::schema()->create('review_assignments', function (Blueprint $table) {
	$table->bigInteger('review_id')->autoIncrement();
	$table->bigInteger('submission_id');
	$table->bigInteger('reviewer_id')->default(1);
	$table->bigInteger('review_round_id')->nullable();
	$table->tinyInteger('stage_id')->default(3);
	$table->tinyInteger('round')->default(1);
	$table->datetime('date_assigned')->nullable();
	$table->datetime('date_confirmed')->nullable();
	$table->datetime('date_completed')->nullable();
	$table->tinyInteger('declined')->default(0);
	$table->tinyInteger('cancelled')->default(0);
});

Capsule::schema()->create('edit_decisions', function (Blueprint $table) {
	$table->bigInteger('edit_decision_id')->autoIncrement();
	$table->bigInteger('submission_id');
	$table->bigInteger('review_round_id')->default(0);
	$table->bigInteger('stage_id')->default(3);
	$table->tinyInteger('round')->default(1);
	$table->bigInteger('editor_id')->default(1);
	$table->tinyInteger('decision');
	$table->datetime('date_decided');
});

Capsule::schema()->create('users', function (Blueprint $table) {
	$table->bigInteger('user_id')->autoIncrement();
	$table->string('username', 32);
	$table->string('email', 90);
});

Capsule::schema()->create('user_groups', function (Blueprint $table) {
	$table->bigInteger('user_group_id')->autoIncrement();
	$table->bigInteger('context_id');
	$table->bigInteger('role_id');
});

Capsule::schema()->create('user_group_settings', function (Blueprint $table) {
	$table->bigInteger('user_group_id');
	$table->string('locale', 14)->default('');
	$table->string('setting_name', 255);
	$table->text('setting_value')->nullable();
});

Capsule::schema()->create('user_user_groups', function (Blueprint $table) {
	$table->bigInteger('user_group_id');
	$table->bigInteger('user_id');
});

// Load plugin classes
require_once $pluginDir . '/MultiJournalControlPanelPlugin.inc.php';
require_once $pluginDir . '/MultiJournalControlPanelSchemaMigration.inc.php';
require_once $pluginDir . '/classes/dao/MultiJournalControlPanelDAO.inc.php';
require_once $pluginDir . '/classes/services/WorkingDayCalculator.inc.php';
require_once $pluginDir . '/classes/services/SynchronizationService.inc.php';
require_once $pluginDir . '/classes/services/DashboardService.inc.php';
require_once $pluginDir . '/classes/services/PaymentService.inc.php';
require_once $pluginDir . '/classes/services/ExportService.inc.php';

$passed = 0;
$failed = 0;

function assertTest($condition, $description) {
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "[PASS] $description\n";
	} else {
		$failed++;
		echo "[FAIL] $description\n";
	}
}

echo "=================================================================\n";
echo "MULTI-JOURNAL CONTROL PANEL - OJS 3.3.0-24 TEST SUITE\n";
echo "=================================================================\n\n";

// -------------------------------------------------------------------------
// TEST 1, 2, 3: Plugin Installation, Upgrade, and Database Migration (15 mjcp_ tables)
// -------------------------------------------------------------------------
$plugin = new MultiJournalControlPanelPlugin();
$installOk = $plugin->install();
$upgradeOk = $plugin->upgrade();

assertTest($installOk === true, "1. Plugin installation test (install() returns true)");
assertTest($upgradeOk === true, "2. Plugin upgrade test (upgrade() is idempotent and returns true)");

$expectedTables = [
	'mjcp_article_data',
	'mjcp_payments',
	'mjcp_payment_logs',
	'mjcp_author_forms',
	'mjcp_author_tokens',
	'mjcp_loa_files',
	'mjcp_issue_quotas',
	'mjcp_stage_thresholds',
	'mjcp_holidays',
	'mjcp_access_users',
	'mjcp_roles',
	'mjcp_user_roles',
	'mjcp_audit_logs',
	'mjcp_sync_logs',
	'mjcp_testimonials',
];

$allTablesExist = true;
foreach ($expectedTables as $tbl) {
	if (!Capsule::schema()->hasTable($tbl)) {
		$allTablesExist = false;
		echo "Missing table: $tbl\n";
	}
}
assertTest($allTablesExist, "3. Database migration test (all 15 mjcp_* tables created with indexes and seed roles)");

// -------------------------------------------------------------------------
// Seed OJS Journals, Issues, Users, and Submissions for Testing
// -------------------------------------------------------------------------
Capsule::table('journals')->insert([
	['journal_id' => 1, 'path' => 'commercium', 'seq' => 1, 'primary_locale' => 'en_US', 'enabled' => 1],
	['journal_id' => 2, 'path' => 'medicor', 'seq' => 2, 'primary_locale' => 'en_US', 'enabled' => 1],
	['journal_id' => 3, 'path' => 'politeia', 'seq' => 3, 'primary_locale' => 'en_US', 'enabled' => 1],
]);

Capsule::table('journal_settings')->insert([
	['journal_id' => 1, 'locale' => 'en_US', 'setting_name' => 'name', 'setting_value' => 'Commercium'],
	['journal_id' => 2, 'locale' => 'en_US', 'setting_name' => 'name', 'setting_value' => 'Medicor'],
	['journal_id' => 3, 'locale' => 'en_US', 'setting_name' => 'name', 'setting_value' => 'Politeia'],
]);

// Future unpublished issues (Vol 4 No 4)
Capsule::table('issues')->insert([
	['issue_id' => 101, 'journal_id' => 1, 'volume' => 4, 'number' => '4', 'year' => 2026, 'published' => 0, 'current' => 0],
	['issue_id' => 102, 'journal_id' => 2, 'volume' => 4, 'number' => '4', 'year' => 2026, 'published' => 0, 'current' => 0],
]);
Capsule::table('issue_settings')->insert([
	['issue_id' => 101, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'November 2026'],
	['issue_id' => 102, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'November 2026'],
]);

$dao = new MultiJournalControlPanelDAO();
$dao->saveIssueQuota(1, 0, 2, '2026-11-30', 1);
$dao->saveIssueQuota(2, 0, 5, '2026-11-30', 1);

// Seed 7 submissions covering every pipeline bucket:
$thirtyEightDaysAgo = date('Y-m-d H:i:s', strtotime('-38 days'));
$sixteenDaysAgo = date('Y-m-d H:i:s', strtotime('-16 days'));
$fiveDaysAgo = date('Y-m-d H:i:s', strtotime('-5 days'));

Capsule::table('submissions')->insert([
	['submission_id' => 1974, 'context_id' => 3, 'current_publication_id' => 1974, 'date_submitted' => $thirtyEightDaysAgo, 'stage_id' => 3, 'status' => 1, 'submission_progress' => 0],
	['submission_id' => 2011, 'context_id' => 1, 'current_publication_id' => 2011, 'date_submitted' => $thirtyEightDaysAgo, 'stage_id' => 3, 'status' => 1, 'submission_progress' => 0],
	['submission_id' => 2038, 'context_id' => 2, 'current_publication_id' => 2038, 'date_submitted' => $thirtyEightDaysAgo, 'stage_id' => 4, 'status' => 1, 'submission_progress' => 0],
	['submission_id' => 2090, 'context_id' => 1, 'current_publication_id' => 2090, 'date_submitted' => $sixteenDaysAgo, 'stage_id' => 3, 'status' => 1, 'submission_progress' => 0],
	['submission_id' => 2100, 'context_id' => 1, 'current_publication_id' => 2100, 'date_submitted' => $fiveDaysAgo, 'stage_id' => 1, 'status' => 1, 'submission_progress' => 0],
	['submission_id' => 2110, 'context_id' => 1, 'current_publication_id' => 2110, 'date_submitted' => $thirtyEightDaysAgo, 'stage_id' => 5, 'status' => 1, 'submission_progress' => 0],
	['submission_id' => 2120, 'context_id' => 2, 'current_publication_id' => 2120, 'date_submitted' => $thirtyEightDaysAgo, 'stage_id' => 5, 'status' => 3, 'submission_progress' => 0],
]);

Capsule::table('publications')->insert([
	['publication_id' => 1974, 'submission_id' => 1974, 'status' => 1, 'date_published' => null],
	['publication_id' => 2011, 'submission_id' => 2011, 'status' => 1, 'date_published' => null],
	['publication_id' => 2038, 'submission_id' => 2038, 'status' => 1, 'date_published' => null],
	['publication_id' => 2090, 'submission_id' => 2090, 'status' => 1, 'date_published' => null],
	['publication_id' => 2100, 'submission_id' => 2100, 'status' => 1, 'date_published' => null],
	['publication_id' => 2110, 'submission_id' => 2110, 'status' => 1, 'date_published' => null],
	['publication_id' => 2120, 'submission_id' => 2120, 'status' => 3, 'date_published' => substr($fiveDaysAgo, 0, 10)],
]);

Capsule::table('publication_settings')->insert([
	['publication_id' => 1974, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Digital Public Policy Analysis'],
	['publication_id' => 2011, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Small Enterprise Microeconomic Strategy'],
	['publication_id' => 2038, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Hospital Telemedicine Innovation'],
	['publication_id' => 2090, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Regional Trade Transformation'],
	['publication_id' => 2100, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Supply Chain Governance Framework'],
	['publication_id' => 2110, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Modern Financial Technology Models'],
	['publication_id' => 2120, 'locale' => 'en_US', 'setting_name' => 'title', 'setting_value' => 'Integrated Clinical Epidemiology'],
	['publication_id' => 2120, 'locale' => '', 'setting_name' => 'pub-id::doi', 'setting_value' => '10.1234/medicor.v4i4.2120'],
]);

Capsule::table('review_rounds')->insert([
	['review_round_id' => 1, 'submission_id' => 1974, 'stage_id' => 3, 'round' => 1, 'status' => 6],
	['review_round_id' => 2, 'submission_id' => 2011, 'stage_id' => 3, 'round' => 1, 'status' => 1],
	['review_round_id' => 3, 'submission_id' => 2011, 'stage_id' => 3, 'round' => 2, 'status' => 7],
	['review_round_id' => 4, 'submission_id' => 2090, 'stage_id' => 3, 'round' => 1, 'status' => 1],
]);

Capsule::table('edit_decisions')->insert([
	['submission_id' => 1974, 'review_round_id' => 1, 'stage_id' => 1, 'round' => 1, 'editor_id' => 1, 'decision' => 8, 'date_decided' => $thirtyEightDaysAgo],
	['submission_id' => 2011, 'review_round_id' => 2, 'stage_id' => 1, 'round' => 1, 'editor_id' => 1, 'decision' => 8, 'date_decided' => $thirtyEightDaysAgo],
	['submission_id' => 2011, 'review_round_id' => 3, 'stage_id' => 3, 'round' => 2, 'editor_id' => 1, 'decision' => 16, 'date_decided' => $sixteenDaysAgo],
	['submission_id' => 2038, 'review_round_id' => 0, 'stage_id' => 3, 'round' => 1, 'editor_id' => 1, 'decision' => 1, 'date_decided' => $fiveDaysAgo],
	['submission_id' => 2090, 'review_round_id' => 4, 'stage_id' => 3, 'round' => 1, 'editor_id' => 1, 'decision' => 2, 'date_decided' => $fiveDaysAgo],
	['submission_id' => 2110, 'review_round_id' => 0, 'stage_id' => 4, 'round' => 0, 'editor_id' => 1, 'decision' => 7, 'date_decided' => $fiveDaysAgo],
	['submission_id' => 2120, 'review_round_id' => 0, 'stage_id' => 3, 'round' => 1, 'editor_id' => 1, 'decision' => 1, 'date_decided' => $sixteenDaysAgo],
]);

// -------------------------------------------------------------------------
// TEST 4 & 5: Synchronization, Dashboard Summary & Editorial Pipeline Mapping
// -------------------------------------------------------------------------
$syncService = new SynchronizationService($plugin, $dao);
$syncResult = $syncService->synchronizeJournals(null, 'manual', 1, 'en_US');

$dashboardService = new DashboardService($plugin, $dao);
$summary = $dashboardService->getDashboardSummary(['contextId' => 'all', 'period' => 'all'], 'en_US');
$pipeline = $dashboardService->getSubmissionPipeline(['contextId' => 'all', 'period' => 'all'], 'en_US');

assertTest(
	$summary['totalArticles'] === 7 && $summary['activeSubmissions'] === 6 && $summary['overdueSubmissions'] >= 2,
	"4. Dashboard data accuracy test (Total=7, Active=6, Overdue=" . $summary['overdueSubmissions'] . ")"
);

$commerciumRow = null;
foreach ($pipeline as $pRow) {
	if ($pRow['journalPath'] === 'commercium') {
		$commerciumRow = $pRow;
	}
}
$sumStages = $commerciumRow
	? ($commerciumRow['submission'] + $commerciumRow['review'] + $commerciumRow['revision'] + $commerciumRow['copyediting'] + $commerciumRow['production'] + $commerciumRow['published'])
	: -1;

assertTest(
	$commerciumRow !== null && $commerciumRow['allArticle'] === 4 && $sumStages === 4 && $commerciumRow['revision'] === 1,
	"5. Editorial pipeline mapping test (Each submission counted once; Commercium All Article=4 matches sum of stages)"
);

// -------------------------------------------------------------------------
// TEST 6: Bottleneck Calculation, Custom Working Days, Holidays, & Round Reset
// -------------------------------------------------------------------------
$calc = new WorkingDayCalculator(['2026-11-11'], 'UTC');
$wDays = $calc->countWorkingDaysBetween('2026-11-09', '2026-11-16'); // 4 working days (excludes Wed holiday + Sat/Sun)

// Custom working days: Mon-Sat [1,2,3,4,5,6] -> Sat 2026-11-14 is counted -> 5 working days
$calcMonSat = new WorkingDayCalculator(['2026-11-11'], 'UTC', [1, 2, 3, 4, 5, 6]);
$wDaysMonSat = $calcMonSat->countWorkingDaysBetween('2026-11-09', '2026-11-16');

// Custom working days: Sun-Thu [7,1,2,3,4] -> Fri 13 & Sat 14 excluded, Sun 15 included -> 4 working days
$calcSunThu = new WorkingDayCalculator(['2026-11-11'], 'UTC', '7,1,2,3,4');
$wDaysSunThu = $calcSunThu->countWorkingDaysBetween('2026-11-09', '2026-11-16');

$r1Eval = $calc->evaluateReviewRound('2026-10-01', 21, 3, '2026-11-10');
$r2Eval = $calc->evaluateReviewRound('2026-11-02', 7, 2, '2026-11-13');

$bottlenecks = $dashboardService->getBottleneckSubmissions(['contextId' => 'all', 'period' => 'all'], null, 'en_US');
$found1974 = false;
$found2011Round2 = false;
foreach ($bottlenecks as $b) {
	if ($b['submissionId'] === 1974 && $b['round'] === 1 && $b['bottleneckStatus'] === 'overdue') {
		$found1974 = true;
	}
	if ($b['submissionId'] === 2011 && $b['round'] === 2 && $b['bottleneckStatus'] === 'overdue') {
		$found2011Round2 = true;
	}
}

assertTest(
	$wDays === 4 && $wDaysMonSat === 5 && $wDaysSunThu === 4 && $r1Eval['status'] === 'overdue' && $r2Eval['status'] === 'overdue' && $found1974 && $found2011Round2,
	"6. Bottleneck & custom working days calculation test (Default Mon-Fri, custom Mon-Sat & Sun-Thu, holidays, Round 1/2 limits)"
);

// -------------------------------------------------------------------------
// TEST 7: Payment Authorization (RBAC Matrix)
// -------------------------------------------------------------------------
$dao->setRoleUsernames('director', 'director_user', 1);
$dao->setRoleUsernames('journal_manager', 'jm_registered, jm_unregistered', 1);
$dao->setRoleUsernames('finance_admin', 'finance_user', 1);
$dao->setRoleUsernames('technical_admin', 'tech_admin', 1);

$dao->setPaymentAccessUsernames('jm_registered, finance_user', 1);

$permsDirector = $dashboardService->resolveUserPermissions(new MockUser(10, 'director_user'), 0);
$permsJmUnreg = $dashboardService->resolveUserPermissions(new MockUser(11, 'jm_unregistered'), 0);
$permsJmReg = $dashboardService->resolveUserPermissions(new MockUser(12, 'jm_registered'), 0);
$permsFinance = $dashboardService->resolveUserPermissions(new MockUser(13, 'finance_user'), 0);
$permsTech = $dashboardService->resolveUserPermissions(new MockUser(14, 'tech_admin'), 0);

assertTest(
	$permsDirector['canViewDashboard'] === true &&
	$permsDirector['canViewPayment'] === false &&
	$permsDirector['canEditPayment'] === false &&
	$permsJmUnreg['canViewPayment'] === false &&
	$permsJmReg['canViewPayment'] === true &&
	$permsJmReg['canEditPayment'] === false &&
	$permsFinance['canViewPayment'] === true &&
	$permsFinance['canEditPayment'] === true &&
	$permsTech['canManageSettings'] === true,
	"7. Payment authorization test (Director & unregistered JM blocked; registered JM view-only; Finance Admin view+edit)"
);

// -------------------------------------------------------------------------
// TEST 8: Payment Verification, FREE Rule, Audit Trail, & Bulk Import
// -------------------------------------------------------------------------
$paymentService = new PaymentService($plugin, $dao);
$financeUser = new MockUser(13, 'finance_user');

$verifyRes1 = $paymentService->verifyPayment(2038, 'paid', '1.500.000', 'Bank Transfer', null, $financeUser);
$verifyFree = $paymentService->verifyPayment(2090, 'paid', '0', 'free', null, $financeUser);

$bulkInvalid = $paymentService->processBulkPaymentRows([
	['2011', 'paid', '1000000', 'Settled'],
	['999999', 'paid', '500000', 'Invalid code'],
], false, $financeUser);

$detail2038 = $paymentService->getSubmissionPaymentAndLoaDetail(2038, 'en_US');
$detail2090 = $paymentService->getSubmissionPaymentAndLoaDetail(2090, 'en_US');

assertTest(
	$verifyRes1['ok'] === true &&
	$detail2038['payment']['status'] === 'paid' &&
	$detail2038['payment']['amount'] === 1500000 &&
	count($detail2038['paymentLogs']) >= 1 &&
	$detail2090['payment']['status'] === 'paid' &&
	$detail2090['payment']['amount'] === 0 &&
	$detail2090['payment']['notes'] === 'FREE' &&
	$bulkInvalid['hasErrors'] === true &&
	$bulkInvalid['previewRows'][1]['isValid'] === false,
	"8. Payment audit trail, FREE rule, & bulk import validation test"
);

// -------------------------------------------------------------------------
// TEST 9: Author Token Generation, Hashing, & Confirmation Form Validation
// -------------------------------------------------------------------------
$tokenRes = $paymentService->generateAuthorToken(2038, 30, 12);
$rawToken2038 = $tokenRes['token'];
$storedTokenRow = Capsule::table('mjcp_author_tokens')->where('submission_id', '=', 2038)->where('is_active', '=', 1)->first();

$failSubmit = $paymentService->submitAuthorConfirmationForm($rawToken2038, [
	'fullNameAuthors' => 'Dr. Alex Morgan',
	'affiliation' => 'National Research University',
	'correspondentEmail' => 'alex@example.edu',
	'whatsappNumber' => '+6281234567890',
	'testimonial' => '',
	'testimonialConsent' => 0,
], null, '127.0.0.1');

$okSubmit = $paymentService->submitAuthorConfirmationForm($rawToken2038, [
	'fullNameAuthors' => 'Dr. Alex Morgan',
	'affiliation' => 'National Research University',
	'correspondentEmail' => 'alex@example.edu',
	'whatsappNumber' => '+6281234567890',
	'testimonial' => 'The peer review and editorial communication process was thorough and timely.',
	'testimonialConsent' => 1,
], null, '127.0.0.1');

$tokenRes2011 = $paymentService->generateAuthorToken(2011, 30, 12);
$paymentService->revokeAuthorToken(2011, 12);
$resolvedRevoked = $paymentService->resolveAuthorToken($tokenRes2011['token']);

assertTest(
	$tokenRes['ok'] === true &&
	strlen($rawToken2038) === 32 &&
	$storedTokenRow->token_hash === hash('sha256', $rawToken2038) &&
	$failSubmit['ok'] === false &&
	$okSubmit['ok'] === true &&
	$resolvedRevoked['isValid'] === false &&
	$resolvedRevoked['reason'] === 'revoked',
	"9. Author token validation & confirmation form submission test (SHA-256 hash stored, mandatory testimonial/consent enforced, revoked token blocked)"
);

// -------------------------------------------------------------------------
// TEST 10: File Upload Security Test & Strict Readiness Check
// -------------------------------------------------------------------------
$tmpDir = sys_get_temp_dir();

$oversizedFile = $tmpDir . '/mjcp_oversize.jpg';
file_put_contents($oversizedFile, str_repeat('A', 1100000));
$oversizeRes = $paymentService->storePrivateProofFile(2038, 2, [
	'name' => 'oversize.jpg',
	'tmp_name' => $oversizedFile,
	'size' => filesize($oversizedFile),
	'error' => UPLOAD_ERR_OK,
]);
@unlink($oversizedFile);

$maliciousFile = $tmpDir . '/mjcp_evil.php';
file_put_contents($maliciousFile, '<?php echo "hacked"; ?>');
$maliciousRes = $paymentService->storePrivateProofFile(2038, 2, [
	'name' => 'evil.php',
	'tmp_name' => $maliciousFile,
	'size' => filesize($maliciousFile),
	'error' => UPLOAD_ERR_OK,
]);
@unlink($maliciousFile);

$validPdfFile = $tmpDir . '/mjcp_valid_loa.pdf';
file_put_contents($validPdfFile, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
$loaUploadRes = $paymentService->uploadLoa(2038, 'LOA/MED/2026/2038', [
	'name' => 'LOA-2038.pdf',
	'tmp_name' => $validPdfFile,
	'size' => filesize($validPdfFile),
	'error' => UPLOAD_ERR_OK,
	'_allowTestLocalFile' => true,
], new MockUser(12, 'jm_registered'));
@unlink($validPdfFile);

// Readiness check: #2038 is in Copyediting (stage 4, copyediting_done = 0), so even with LOA uploaded, is_ready_to_publish MUST be 0
$article2038AfterLoa = Capsule::table('mjcp_article_data')->where('submission_id', '=', 2038)->first();

assertTest(
	$oversizeRes['ok'] === false &&
	$maliciousRes['ok'] === false &&
	$loaUploadRes['ok'] === true &&
	(int) $article2038AfterLoa->is_ready_to_publish === 0,
	"10. File upload security & strict readiness test (>1MB rejected, non-JPG/PNG/PDF rejected, valid PDF stored privately, ready requires LOA AND copyediting done)"
);

// -------------------------------------------------------------------------
// TEST 11: LOA Access Control Test (Locked when unpaid, Unlocked when paid, Re-locked if reverted)
// -------------------------------------------------------------------------
$valWhenPaid = $paymentService->validateLoaByNumber('LOA/MED/2026/2038', 'en_US');

$paymentService->verifyPayment(2038, 'unpaid', '1500000', 'Verification correction', null, $financeUser);
$valWhenRevertedToUnpaid = $paymentService->validateLoaByNumber('LOA/MED/2026/2038', 'en_US');

$loaUnpaidList = $paymentService->getPaymentsDashboardData(['quickFilter' => 'loa_unpaid'], 'en_US');
$found2038InLoaUnpaid = false;
foreach ($loaUnpaidList['items'] as $pIt) {
	if ($pIt['submissionId'] === 2038) {
		$found2038InLoaUnpaid = true;
	}
}

$paymentService->verifyPayment(2038, 'paid', '1500000', 'Final verification', null, $financeUser);
$valAfterReverify = $paymentService->validateLoaByNumber('LOA/MED/2026/2038', 'en_US');

assertTest(
	$valWhenPaid !== null &&
	$valWhenPaid['isValid'] === true &&
	$valWhenRevertedToUnpaid === null &&
	$found2038InLoaUnpaid === true &&
	$valAfterReverify !== null &&
	$valAfterReverify['isValid'] === true,
	"11. LOA access control test (LOA active when paid, locked when reverted to unpaid, matched in loa_unpaid quick filter)"
);

// -------------------------------------------------------------------------
// TEST 12: Publication Link & Post-Publication Token Persistence Test
// -------------------------------------------------------------------------
$detailUnpublished = $paymentService->getSubmissionPaymentAndLoaDetail(2038, 'en_US');
$detailPublished = $paymentService->getSubmissionPaymentAndLoaDetail(2120, 'en_US');

$tokenRes2120 = $paymentService->generateAuthorToken(2120, 30, 12);
Capsule::table('mjcp_author_tokens')
	->where('submission_id', '=', 2120)
	->update(['expires_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
$resolvedPublishedToken = $paymentService->resolveAuthorToken($tokenRes2120['token']);

assertTest(
	$detailUnpublished['isPublished'] === false &&
	$detailPublished['isPublished'] === true &&
	strpos($detailPublished['publicationUrl'], '/article/view/2120') !== false &&
	$detailPublished['doi'] === '10.1234/medicor.v4i4.2120' &&
	$resolvedPublishedToken['isValid'] === true,
	"12. Publication link test (Unpublished vs Published URL + DOI; author link remains active after publication)"
);

// -------------------------------------------------------------------------
// TEST 13: Localization Completeness Test (en_US & id_ID .po and .xml parity + template key coverage)
// -------------------------------------------------------------------------
$idKeys = array_keys($GLOBALS['mjcpTestTranslations']['id_ID']);
$enKeys = array_keys($GLOBALS['mjcpTestTranslations']['en_US']);
$missingInEn = array_diff($idKeys, $enKeys);
$missingInId = array_diff($enKeys, $idKeys);

$tplFiles = [
	$pluginDir . '/templates/dashboard/index.tpl',
	$pluginDir . '/templates/payments/index.tpl',
	$pluginDir . '/templates/payments/form.tpl',
	$pluginDir . '/templates/authors/confirmation.tpl',
	$pluginDir . '/templates/loa/detail.tpl',
	$pluginDir . '/templates/settings/index.tpl',
	$pluginDir . '/templates/settings/modal.tpl',
];
$missingTemplateKeys = [];
foreach ($tplFiles as $tf) {
	$c = file_get_contents($tf);
	preg_match_all('/translate(?:\|escape)?\s+key="([^"]+)"/', $c, $tm);
	foreach ($tm[1] as $tKey) {
		if (strpos($tKey, 'mjcp.') === 0 || strpos($tKey, 'plugins.generic.multiJournalControlPanel.') === 0) {
			if (!isset($GLOBALS['mjcpTestTranslations']['id_ID'][$tKey]) || !isset($GLOBALS['mjcpTestTranslations']['en_US'][$tKey])) {
				$missingTemplateKeys[] = $tKey;
			}
		}
	}
}

$xmlIdExists = file_exists($pluginDir . '/locale/id_ID/locale.xml') && filesize($pluginDir . '/locale/id_ID/locale.xml') > 1000;
$xmlEnExists = file_exists($pluginDir . '/locale/en_US/locale.xml') && filesize($pluginDir . '/locale/en_US/locale.xml') > 1000;

assertTest(
	empty($missingInEn) && empty($missingInId) && empty($missingTemplateKeys) && $xmlIdExists && $xmlEnExists,
	"13. Localization test (" . count($enKeys) . " en_US & id_ID keys in 100% parity across locale.po and locale.xml; 0 missing template keys)"
);

// -------------------------------------------------------------------------
// TEST 14: Responsive UI, CSS Prefix (mjcp-), No Inline CSS/JS, & Copyright Headers Test
// -------------------------------------------------------------------------
$hasInlineStyleOrScriptInTemplates = false;
foreach ($tplFiles as $tf) {
	$c = file_get_contents($tf);
	if (stripos($c, 'style="') !== false || stripos($c, '<script>') !== false) {
		$hasInlineStyleOrScriptInTemplates = true;
		echo "Inline style/script found in: $tf\n";
	}
}

$cssContent = file_get_contents($pluginDir . '/styles/multiJournalControlPanel.css');
$hasMjcpClasses = (strpos($cssContent, '.mjcp-container') !== false)
	&& (strpos($cssContent, '.mjcp-dashboard') !== false)
	&& (strpos($cssContent, '.mjcp-card') !== false)
	&& (strpos($cssContent, '.mjcp-table') !== false)
	&& (strpos($cssContent, '.mjcp-badge-success') !== false)
	&& (strpos($cssContent, '@media (max-width:') !== false);

$phpFiles = [
	$pluginDir . '/index.php',
	$pluginDir . '/MultiJournalControlPanelPlugin.inc.php',
	$pluginDir . '/MultiJournalControlPanelSchemaMigration.inc.php',
	$pluginDir . '/classes/dao/MultiJournalControlPanelDAO.inc.php',
	$pluginDir . '/classes/forms/MultiJournalControlPanelSettingsForm.inc.php',
	$pluginDir . '/classes/services/WorkingDayCalculator.inc.php',
	$pluginDir . '/classes/services/SynchronizationService.inc.php',
	$pluginDir . '/classes/services/DashboardService.inc.php',
	$pluginDir . '/classes/services/PaymentService.inc.php',
	$pluginDir . '/classes/services/ExportService.inc.php',
	$pluginDir . '/classes/tasks/SynchronizationTask.inc.php',
	$pluginDir . '/pages/MultiJournalControlPanelHandler.inc.php',
];
$allHaveCopyright = true;
foreach ($phpFiles as $pf) {
	$c = file_get_contents($pf);
	if (strpos($c, 'Copyright (c) 2026 Indaka Barody') === false) {
		$allHaveCopyright = false;
		echo "Missing copyright header in: $pf\n";
	}
}

assertTest(
	!$hasInlineStyleOrScriptInTemplates && $hasMjcpClasses && $allHaveCopyright,
	"14. Responsive UI, mjcp-* CSS prefix, zero inline CSS/JS, and Copyright header verification test"
);

// -------------------------------------------------------------------------
// TEST 15: Uninstall Safety Test (Preserves OJS editorial data & plugin tables unless confirmed)
// -------------------------------------------------------------------------
$plugin->uninstall(false);
$tablesStillPresentOnSafeUninstall = Capsule::schema()->hasTable('mjcp_payments') && Capsule::schema()->hasTable('mjcp_article_data');
$ojsSubmissionsStillIntact = (int) Capsule::table('submissions')->count() === 7;

assertTest(
	$tablesStillPresentOnSafeUninstall && $ojsSubmissionsStillIntact,
	"15. Uninstall safety test (Safe uninstall preserves OJS submissions and does not drop mjcp_* data without explicit confirmation)"
);

// -------------------------------------------------------------------------
// TEST 16: Prefix Standardization Verification
// -------------------------------------------------------------------------
$legacyPrefixMatches = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDir, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($iter as $file) {
	if (!$file->isFile()) {
		continue;
	}
	$path = $file->getRealPath();
	if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
		continue;
	}
	if ($path === __FILE__) {
		continue;
	}
	$content = file_get_contents($path);
	if (stripos($content, 'mpcp') !== false) {
		$legacyPrefixMatches[] = str_replace($pluginDir . '/', '', $path);
	}
}

assertTest(
	empty($legacyPrefixMatches),
	"16. Prefix standardization test (0 occurrences of legacy prefix across all plugin files)"
);

// Clean up temporary test files directory
if (is_dir(Config::$vars['files']['files_dir'])) {
	$it = new RecursiveDirectoryIterator(Config::$vars['files']['files_dir'], RecursiveDirectoryIterator::SKIP_DOTS);
	$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) {
		if ($file->isDir()) {
			@rmdir($file->getRealPath());
		} else {
			@unlink($file->getRealPath());
		}
	}
	@rmdir(Config::$vars['files']['files_dir']);
}

echo "\n=================================================================\n";
echo "RESULT: $passed passed, $failed failed\n";
echo "=================================================================\n";

exit($failed > 0 ? 1 : 0);
