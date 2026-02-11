<?php
/*

Copyright 2011-2012 Marin Software

This file is part of XtraBackup Manager.

XtraBackup Manager is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

XtraBackup Manager is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with XtraBackup Manager.  If not, see <http://www.gnu.org/licenses/>.

*/

class slackNotifier {

	private $log;
	private $webhookUrl;
	private $enabled;
	private $channel;
	private $logLinesCount;

	public function __construct() {
		$this->log = false;
		$this->loadConfig();
	}

	public function setLogStream($log) {
		$this->log = $log;
	}

	private function loadConfig() {
		global $config;

		$this->enabled = isset($config['SLACK']['enabled']) ? $config['SLACK']['enabled'] : false;
		$this->webhookUrl = isset($config['SLACK']['webhook_url']) ? $config['SLACK']['webhook_url'] : '';
		$this->channel = isset($config['SLACK']['channel']) ? $config['SLACK']['channel'] : '';
		$this->logLinesCount = isset($config['SLACK']['log_lines_count']) ? $config['SLACK']['log_lines_count'] : 25;
	}

	public function isEnabled() {
		return $this->enabled === true && !empty($this->webhookUrl);
	}

	// Main method to send failure notifications
	public function sendBackupFailureNotification($scheduledBackup, Exception $exception, $xbmHostname) {

		if (!$this->isEnabled()) {
			return false;
		}

		try {
			// Gather backup information
			$host = $scheduledBackup->getHost();
			$hostInfo = $host->getInfo();
			$sbInfo = $scheduledBackup->getInfo();

			// Build the Slack message
			$message = $this->buildFailureMessage($hostInfo, $sbInfo, $exception, $xbmHostname);

			// Send to Slack
			return $this->sendToSlack($message);

		} catch (Exception $e) {
			// Log the error but don't throw - we don't want to fail the backup notification
			if ($this->log !== false) {
				$this->log->write(basename(__FILE__).": Error sending Slack notification: ".$e->getMessage(), XBM_LOG_ERROR);
			}
			return false;
		}
	}

	// Send a notification for aborted backups (KillException)
	public function sendBackupAbortedNotification($scheduledBackup, $xbmHostname) {

		if (!$this->isEnabled()) {
			return false;
		}

		try {
			$host = $scheduledBackup->getHost();
			$hostInfo = $host->getInfo();
			$sbInfo = $scheduledBackup->getInfo();

			$message = $this->buildAbortedMessage($hostInfo, $sbInfo, $xbmHostname);

			return $this->sendToSlack($message);

		} catch (Exception $e) {
			if ($this->log !== false) {
				$this->log->write(basename(__FILE__).": Error sending Slack notification: ".$e->getMessage(), XBM_LOG_ERROR);
			}
			return false;
		}
	}

	// Send a notification when backup info cannot be retrieved
	public function sendGenericFailureNotification(Exception $exception, $xbmHostname) {

		if (!$this->isEnabled()) {
			return false;
		}

		try {
			$message = $this->buildGenericFailureMessage($exception, $xbmHostname);
			return $this->sendToSlack($message);

		} catch (Exception $e) {
			if ($this->log !== false) {
				$this->log->write(basename(__FILE__).": Error sending Slack notification: ".$e->getMessage(), XBM_LOG_ERROR);
			}
			return false;
		}
	}

	private function buildFailureMessage($hostInfo, $sbInfo, Exception $exception, $xbmHostname) {

		$exceptionType = get_class($exception);
		$timestamp = time();

		// Determine log file path if available
		global $config;
		$logFile = isset($config['LOGS']['logdir']) ?
			$config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log' : null;

		// Get recent log lines
		$logOutput = '';
		if ($logFile !== null) {
			$logOutput = $this->getRecentLogLines($logFile, $this->logLinesCount);
		}

		$attachment = [
			'fallback' => 'XtraBackup Manager - Backup Failure for '.$hostInfo['hostname'],
			'color' => 'danger', // Red color for failures
			'title' => ':x: Backup Failure',
			'text' => 'A fatal error occurred while attempting to run a scheduled backup',
			'fields' => [
				[
					'title' => 'MySQL Backup Host',
					'value' => $hostInfo['hostname'].' - '.$hostInfo['description'],
					'short' => false
				],
				[
					'title' => 'XtraBackup Manager Host',
					'value' => $xbmHostname,
					'short' => true
				],
				[
					'title' => 'Scheduled Backup',
					'value' => $sbInfo['name'],
					'short' => true
				],
				[
					'title' => 'Exception Type',
					'value' => '`'.$exceptionType.'`',
					'short' => true
				],
				[
					'title' => 'Timestamp',
					'value' => date('Y-m-d H:i:s O', $timestamp),
					'short' => true
				],
				[
					'title' => 'Error Details',
					'value' => '```'.$exception->getMessage().'```',
					'short' => false
				]
			],
			'footer' => XBM_RELEASE_VERSION,
			'ts' => $timestamp
		];

		// Add recent log output if available
		if (!empty($logOutput)) {
			$attachment['fields'][] = [
				'title' => 'Recent Log Output',
				'value' => '```'.$logOutput.'```',
				'short' => false
			];
		}

		// Add log file field if available
		if ($logFile !== null) {
			$attachment['fields'][] = [
				'title' => 'Log File',
				'value' => '`'.$logFile.'`',
				'short' => false
			];
		}

		$message = [
			'attachments' => [$attachment]
		];

		// Add channel override if configured
		if (!empty($this->channel)) {
			$message['channel'] = $this->channel;
		}

		return $message;
	}

	private function buildAbortedMessage($hostInfo, $sbInfo, $xbmHostname) {

		$timestamp = time();

		// Determine log file path if available
		global $config;
		$logFile = isset($config['LOGS']['logdir']) ?
			$config['LOGS']['logdir'].'/hosts/'.$hostInfo['hostname'].'.log' : null;

		// Get recent log lines
		$logOutput = '';
		if ($logFile !== null) {
			$logOutput = $this->getRecentLogLines($logFile, $this->logLinesCount);
		}

		$attachment = [
			'fallback' => 'XtraBackup Manager - Backup Aborted for '.$hostInfo['hostname'],
			'color' => 'warning', // Orange/yellow for aborts
			'title' => ':warning: Backup Aborted',
			'text' => 'The following backup was aborted by an administrator',
			'fields' => [
				[
					'title' => 'MySQL Backup Host',
					'value' => $hostInfo['hostname'].' - '.$hostInfo['description'],
					'short' => false
				],
				[
					'title' => 'XtraBackup Manager Host',
					'value' => $xbmHostname,
					'short' => true
				],
				[
					'title' => 'Scheduled Backup',
					'value' => $sbInfo['name'],
					'short' => true
				],
				[
					'title' => 'Timestamp',
					'value' => date('Y-m-d H:i:s O', $timestamp),
					'short' => true
				]
			],
			'footer' => XBM_RELEASE_VERSION,
			'ts' => $timestamp
		];

		// Add recent log output if available
		if (!empty($logOutput)) {
			$attachment['fields'][] = [
				'title' => 'Recent Log Output',
				'value' => '```'.$logOutput.'```',
				'short' => false
			];
		}

		// Add log file field if available
		if ($logFile !== null) {
			$attachment['fields'][] = [
				'title' => 'Log File',
				'value' => '`'.$logFile.'`',
				'short' => false
			];
		}

		$message = [
			'attachments' => [$attachment]
		];

		if (!empty($this->channel)) {
			$message['channel'] = $this->channel;
		}

		return $message;
	}

	private function buildGenericFailureMessage(Exception $exception, $xbmHostname) {

		$timestamp = time();

		$attachment = [
			'fallback' => 'XtraBackup Manager - Backup Failure',
			'color' => 'danger',
			'title' => ':x: Backup Failure',
			'text' => 'A fatal error occurred while attempting to run a backup',
			'fields' => [
				[
					'title' => 'XtraBackup Manager Host',
					'value' => $xbmHostname,
					'short' => true
				],
				[
					'title' => 'Timestamp',
					'value' => date('Y-m-d H:i:s O', $timestamp),
					'short' => true
				],
				[
					'title' => 'Error Details',
					'value' => '```'.$exception->getMessage().'```',
					'short' => false
				]
			],
			'footer' => XBM_RELEASE_VERSION,
			'ts' => $timestamp
		];

		$message = [
			'attachments' => [$attachment]
		];

		if (!empty($this->channel)) {
			$message['channel'] = $this->channel;
		}

		return $message;
	}

	private function getRecentLogLines($logFilePath, $lineCount) {
		// Vérifier que le fichier existe et est lisible
		if (!is_readable($logFilePath)) {
			return "[Log file not accessible: $logFilePath]";
		}

		// Lire les dernières lignes du fichier
		try {
			$lines = file($logFilePath);
			if ($lines === false || count($lines) === 0) {
				return "[Log file is empty]";
			}

			// Prendre les N dernières lignes
			$recentLines = array_slice($lines, -$lineCount);

			// Limiter la taille totale pour Slack (max 3000 caractères)
			$output = implode('', $recentLines);
			if (strlen($output) > 3000) {
				$output = '...[truncated]...' . substr($output, -3000);
			}

			return $output;

		} catch (Exception $e) {
			return "[Error reading log file: " . $e->getMessage() . "]";
		}
	}

	private function sendToSlack($message) {

		$jsonPayload = json_encode($message);

		// Use cURL to send the webhook
		$ch = curl_init($this->webhookUrl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Content-Length: ' . strlen($jsonPayload)
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 second connect timeout

		$result = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($result === false || $httpCode !== 200) {
			$errorMsg = "Slack webhook failed with HTTP code: ".$httpCode;
			if (!empty($error)) {
				$errorMsg .= " - cURL error: ".$error;
			}
			if ($this->log !== false) {
				$this->log->write(basename(__FILE__).": ".$errorMsg, XBM_LOG_ERROR);
			}
			return false;
		}

		if ($this->log !== false) {
			$this->log->write(basename(__FILE__).": Slack notification sent successfully", XBM_LOG_INFO);
		}

		return true;
	}
}

?>
