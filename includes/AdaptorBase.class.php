<?php
namespace FreePBX\modules\Sms;
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
abstract class AdaptorBase {
	protected $supportsMedia = false;
	public function __construct() {
		$this->db = \FreePBX::Database();
		if(!class_exists('Emojione\Emojione')) {
			include __DIR__."/Emojione.class.php";
		}
		$this->emoji = new \Emojione\Client(new \Emojione\Ruleset());
	}

	/**
	 * Insert message media into the database
	 * @param  integer $to    The DID the message was sent to
	 * @param  integer $from  The DID the message was from
	 * @param  string $cnam  The CNAME of the message
	 * @param  string $message The message body
	 * @param  array  $files Array of file names to process
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for NOW())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id if there is one
	 * @return integer        The inserted message ID
	 */
	public function sendMedia($to,$from,$cnam,$message=null,$files=array(),$time=null,$adaptor=null,$emid=null) {
		if(!$this->supportsMedia) {
			return false;
		}
		$id = self::sendMessage($to,$from,$cnam,$message,$time,$adaptor,$emid);
		foreach($files as $file){
			if(file_exists($file)) {
				$data = file_get_contents($file);
				try {
					$sql = "INSERT INTO sms_media (`mid`, `name`, `raw`) VALUES (?, ?, ?)";
					$sth = $this->db->prepare($sql);
					$sth->execute(array($id, basename($file), $data));
				} catch (\Exception $e) {
					throw new Exception('Unable to Insert Message Media into DB');
				}
				unlink($file);
			}
		}
		return $id;
	}

	/**
	 * Insert a sent message into the database
	 * @param  integer $to      The DID the message was sent to
	 * @param  integer $from    The DID the message was from
	 * @param  string $cnam    The CNAME of the message
	 * @param  string $message The message
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for NOW())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id if there is one
	 * @return integer          The ID of the row that was inserted
	 */
	public function sendMessage($to,$from,$cnam,$message,$time=null,$adaptor=null,$emid=null) {
		$sql = "INSERT INTO sms_messages (`from`, `to`, `cnam`, `direction`, `tx_rx_datetime`, `body`, `adaptor`, `emid`) VALUES (?, ?, ?, 'out', from_unixtime(?), ?, ?, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$message = $this->emoji->toShort($message);
			$time = !empty($time) ? $time : time();
			$message = !is_null($message) ? $message : "";
			$sth->execute(array($from, $to, $cnam, $time, $message, $adaptor, $emid));
			$id = $this->db->lastInsertId();
			return $id;
		} catch (\Exception $e) {
			throw new Exception('Unable to Insert Message into DB');
		}
	}

	/**
	 * Insert a received message into the database
	 * @param  integer $to      The DID the message was sent to
	 * @param  integer $from    The DID the message was from
	 * @param  string $cnam    The CNAME of the message
	 * @param  string $message The message
	 * @param  integer $time    Unix Timestamp when the message was set (Use null for NOW())
	 * @param  string $adaptor The adaptor used to send the message
	 * @param  string $emid    External message id is there is one
	 * @return integer          The ID of the row that was inserted
	 */
	public function getMessage($to,$from,$cnam,$message,$time=null,$adaptor=null,$emid=null) {
		$sql = "INSERT INTO sms_messages (`from`, `to`, `cnam`, `direction`, `tx_rx_datetime`, `body`, `adaptor`, `emid`) VALUES (?, ?, ?, 'in', from_unixtime(?), ?, ?, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$message = $this->emoji->toShort($message);
			$time = !empty($time) ? $time : time();
			$sth->execute(array($from, $to, $cnam, $time, $message, $adaptor, $emid));
			return $this->db->lastInsertId();
		} catch (\Exception $e) {
			throw new Exception('Unable to Insert Message into DB');
		}
	}

	/**
	 * Add Media into the database for sent or received messages
	 * @param integer $msgid The message ID from getMessage or sendMessage
	 * @param string $name  The filename
	 * @param string $data  The raw data from the file
	 */
	public function addMedia($msgid, $name, $data) {
		$sql = "INSERT INTO sms_media (`mid`, `name`, `raw`) VALUES (?, ?, ?)";
		try {
			$sth = $this->db->prepare($sql);
			$sth->execute(array($msgid, $name, $data));
		} catch (\Exception $e) {
			throw new Exception('Unable to Insert Media into DB');
		}
	}

	/**
	 * Hooks for adaptor classes to modify dialplan
	 */
	public function dialPlanHooks(&$ext, $engine, $priority) {}
}
