<?php
namespace FreePBX\modules\Sms;
abstract class AdaptorBase {

	public function __construct() {
		$this->db = \FreePBX::Database();
	}

	public function sendMessage($to,$from,$cnam,$message) {
		$sql = "INSERT INTO sms_messages (`from`, `to`, `cnam`, `direction`, `tx_rx_datetime`, `body`) VALUES (?, ?, ?, 'out', CURRENT_TIMESTAMP, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$sth->execute(array($from, $to, $cnam, $message));
			return $this->db->lastInsertId();
		} catch (\Exception $e) {
			throw new Exception('Unable to Insert Message into DB');
		}
	}

	public function getMessage($to,$from,$cnam,$message) {
		$sql = "INSERT INTO sms_messages (`from`, `to`, `cnam`, `direction`, `tx_rx_datetime`, `body`) VALUES (?, ?, ?, 'in', CURRENT_TIMESTAMP, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$sth->execute(array($from, $to, $cnam, $message));
		} catch (\Exception $e) {
			dbug($e->getMessage());
			throw new Exception('Unable to Insert Message into DB');
		}
	}

	public function dialPlanHooks(&$ext, $engine, $priority) {}
}
