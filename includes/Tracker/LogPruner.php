<?php
namespace Mpc\Tracker;

/**
 * Keeps the event log from growing without limit.
 *
 * The log had no pruning at all: the only thing that ever removed rows was the
 * admin "Clear All Logs" button, which takes everything. Stores were carrying
 * hundreds of thousands of rows, almost all of them PageView.
 *
 * Purchase rows are kept far longer than the rest. They are a tiny share of the
 * table and they are the ones worth having — they are what the Purchase Send
 * Audit reads to answer "was this order sent to Meta more than once".
 *
 * @package Mpc\Tracker
 */
class LogPruner {
	/** Default retention for ordinary events, in days. */
	const DEFAULT_RETENTION_DAYS = 30;

	/** Retention for Purchase rows, in days. Diagnostics outlive the noise. */
	const PURCHASE_RETENTION_DAYS = 365;

	/** Rows deleted per statement, so a large backlog never holds a long lock. */
	const BATCH_SIZE = 2000;

	/** Batches per run, so one pass cannot monopolise the request. */
	const MAX_BATCHES = 25;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'mpc_prune_event_logs', [ $this, 'prune' ] );
	}

	/**
	 * General retention window in days. 0 disables pruning of ordinary events.
	 *
	 * @return int
	 */
	public static function retention_days() {
		$days = (int) get_option( 'mpc_log_retention_days', self::DEFAULT_RETENTION_DAYS );
		if ( $days < 0 ) {
			$days = self::DEFAULT_RETENTION_DAYS;
		}
		/**
		 * Filter the event-log retention window for non-Purchase events.
		 *
		 * @param int $days Days to keep. 0 keeps everything.
		 */
		return (int) apply_filters( 'mpc_log_retention_days', $days );
	}

	/**
	 * Delete aged rows in batches.
	 *
	 * @return int Rows removed this run.
	 */
	public function prune() {
		global $wpdb;

		$table   = $wpdb->prefix . 'mpc_event_logs';
		$deleted = 0;

		$general = self::retention_days();
		if ( $general > 0 ) {
			$deleted += $this->delete_batches(
				"DELETE FROM {$table} WHERE event_name <> 'Purchase' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY) LIMIT %d",
				$general
			);
		}

		/**
		 * Filter how long Purchase rows are kept. These back the Purchase Send
		 * Audit, so they outlive ordinary events by default.
		 *
		 * @param int $days Days to keep.
		 */
		$purchase = (int) apply_filters( 'mpc_purchase_log_retention_days', self::PURCHASE_RETENTION_DAYS );
		if ( $purchase > 0 ) {
			$deleted += $this->delete_batches(
				"DELETE FROM {$table} WHERE event_name = 'Purchase' AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY) LIMIT %d",
				$purchase
			);
		}

		return $deleted;
	}

	/**
	 * Run one DELETE repeatedly until it stops matching or the batch cap is hit.
	 *
	 * @param string $sql  Statement with %d placeholders for days and limit.
	 * @param int    $days Retention window.
	 * @return int Rows removed.
	 */
	private function delete_batches( $sql, $days ) {
		global $wpdb;

		$removed = 0;
		for ( $i = 0; $i < self::MAX_BATCHES; $i++ ) {
			$rows = $wpdb->query( $wpdb->prepare( $sql, $days, self::BATCH_SIZE ) );
			if ( ! $rows ) { // 0 rows matched, or the query failed.
				break;
			}
			$removed += (int) $rows;
			if ( (int) $rows < self::BATCH_SIZE ) {
				break;
			}
		}
		return $removed;
	}
}
