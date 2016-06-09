<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
namespace UCP\Modules;
use \UCP\Modules as Modules;

class Sms extends Modules{
	protected $module = 'Sms';

	private $userID = null;
	private $limit = 25;
	private $dids = array();

	function __construct($Modules) {
		$this->Modules = $Modules;
		$this->user = $this->UCP->User->getUser();
		$this->userID = $this->user['id'];
		$this->sms = $this->UCP->FreePBX->Sms;
		$dids = $this->sms->getDIDs($this->user['id']);
		$dids = is_array($dids) ? $dids : array();
		foreach($dids as $did) {
			$adaptor = $this->sms->getAdaptor($did);
			if(is_object($adaptor) && method_exists($adaptor,"showDID") && $adaptor->showDID($this->user['id'], $did)) {
				$this->dids[] = $did;
			} elseif(is_object($adaptor) && !method_exists($adaptor,"showDID")) {
				$this->dids[] = $did;
			}
		}
	}

	function getDisplay() {
		if(empty($this->dids)) {
			return '';
		}
		$displayvars = array();
		$html = $this->load_view(__DIR__.'/views/history.php',$displayvars);
		return $html;
	}

	function poll($data,$mdata=array()) {
		if(empty($this->dids)) {
			return array('status' => false);
		}
		$messages = array();
		//see if there are any new messages since the last checked time
		$mdata['lastchecked'] = !empty($mdata['lastchecked']) ? $mdata['lastchecked'] : null;
		$newmessages = $this->sms->getMessagesSinceTime($this->userID,$mdata['lastchecked']);
		$unread = $this->sms->getUnreadCount($this->userID);
		if(!empty($newmessages)) {
			foreach($newmessages as $messageb) {
				$mid = $messageb['id'];
				if(in_array($messageb['from'],$this->dids)) {
					$messageb['did'] = $messageb['from'];
					$messageb['recp'] = $messageb['to'];
					$from = $messageb['from'];
					$to = $messageb['to'];
				} else {
					$messageb['did'] = $messageb['to'];
					$messageb['recp'] = $messageb['from'];
					$from = $messageb['to'];
					$to = $messageb['from'];
				}
				$wid = $from.$to;
				$messageb['cnam'] = !empty($messageb['cnam']) ? $messageb['cnam'] : $messageb['from'];
				$html = $this->getMessageHtmlByID($mid);
				$messageb['body'] = !empty($html) ? $html : htmlentities($messageb['body']);
				$messageb['html'] = !empty($html) ? true : false;
				$messages[$wid][$mid] = $messageb;
			}
		}

		//get all messages from open windows that weren't picked up from lastcheck
		if(!empty($mdata['messageWindows'])) {
			foreach($mdata['messageWindows'] as $window) {
				$msgs = $this->sms->getAllMessagesAfterID($this->userID,$window['from'],$window['to'],$window['last']);
				$wid = $window['windowid'];
				foreach($msgs as $messageb) {
					$mid = $messageb['id'];
					if(in_array($messageb['from'],$this->dids)) {
						$messageb['did'] = $messageb['from'];
						$messageb['recp'] = $messageb['to'];
						$from = $messageb['from'];
						$to = $messageb['to'];
					} else {
						$messageb['did'] = $messageb['to'];
						$messageb['recp'] = $messageb['from'];
						$from = $messageb['to'];
						$to = $messageb['from'];
					}
					$messageb['cnam'] = !empty($messageb['cnam']) ? $messageb['cnam'] : $messageb['from'];
					if(!isset($messages[$wid][$mid])) {
						$html = $this->getMessageHtmlByID($mid);
						$messageb['body'] = !empty($html) ? $html : htmlentities($messageb['body']);
						$messageb['html'] = !empty($html) ? true : false;
						$messages[$wid][$mid] = $messageb;
					}
				}
			}
		}

		//reset array keys so they don't get out of control
		foreach($messages as $windowid => &$m) {
			$m = array_values($m);
			$m = array_reverse($m);
		}
		$count = count($messages);
		return array('status' => true, 'messages' => $messages, 'total' => $unread);
	}

	function getChatHistory($from, $to, $newWindow) {
		$start = ($newWindow == 'true') ? 0 : 1;
		$messages = $this->sms->getAllDeliveredMessages($this->userID,$from,$to,$start,10);
		$final = array();
		if(!empty($messages)) {
			foreach($messages as $m) {
				$html = $this->getMessageHtmlByID($m['id']);
				$body = !empty($html) ? $html : $this->UCP->emoji->toImage($m['body']);
				$final['messages'][] = array(
					'id' => $m['id'],
					'from' => in_array($m['from'],$this->dids) ? _('Me') : $this->replaceDIDwithDisplay($m['from']),
					'message' => $body,
					'date' => strtotime($m['tx_rx_datetime']),
					'direction' => $m['direction']
				);
			}
			$final['lastMessage'] = $final['messages'][0];
			$final['messages'] = array_reverse($final['messages']);
		} else {
			$final = array('messages' => array(), 'lastMessage' => '');
		}
		return $final;
	}

	function getOldMessages($id,$from,$to) {
		$messages = $this->sms->getMessagesOlderThanID($this->userID,$id,$from,$to,10);
		$final = array();
		if(!empty($messages)) {
			foreach($messages as $m) {
				$html = $this->getMessageHtmlByID($m['id']);
				$body = !empty($html) ? $html : $this->UCP->emoji->toImage($m['body']);
				$final[] = array(
					'id' => $m['id'],
					'from' => in_array($m['from'],$this->dids) ? _('Me') : $this->replaceDIDwithDisplay($m['from']),
					'message' => $body,
					'date' => strtotime($m['tx_rx_datetime']),
					'direction' => $m['direction']
				);
			}
			$final = array_reverse($final);
		} else {
			$final = array();
		}
		return $final;
	}

	/**
	 * Determine what commands are allowed
	 *
	 * Used by Ajax Class to determine what commands are allowed by this class
	 *
	 * @param string $command The command something is trying to perform
	 * @param string $settings The Settings being passed through $_POST or $_PUT
	 * @return bool True if pass
	 */
	function ajaxRequest($command, $settings) {
		switch($command) {
			case 'upload':
			case 'messages':
			case 'history':
			case 'delivered':
			case 'read':
			case 'send':
			case 'dids':
			case 'delete':
			case 'contacts':
			case 'grid':
			case 'media':
				return true;
			break;
			default:
				return false;
			break;
		}
	}

	public function getNavItems() {
		if(empty($this->dids)) {
			return false;
		}
		$dlist = "";
		$count = 1;
		foreach($this->sms->getAllMessagesHistory($this->userID) as $did) {
			if($count > 5) {
				break;
			}
			$dlist .= "<li><a class='did' data-did='" . $did['to'] . "'>" . $did['prettyto'] . "</a></li>";
			$count++;
		}
		$out = array();
		$out[] = array(
			"rawname" => "sms",
			"badge" => false,
			"icon" => "fa-comments-o",
			"menu" => array(
				"html" => '<li><a class="new">'._("New SMS").'</a></li><li><hr></li>' . $dlist
			)
		);
		return $out;
	}

	public function getMessageHtmlByID($id) {
		$medias = $this->sms->getMediaByID($id);
		if(empty($medias)) {
			return '';
		}
		$html = '';
		foreach($medias as $data) {
			switch($data['type']) {
				case "img":
					$link = 'index.php?quietmode=1&module=sms&command=media&name='.$data['link'];
					$html .= '<a href="'.$link.'" target="_blank"><img src="'.$link.'" style="width: 100%;"></a>';
				break;
				case "text":
					$data['data'] = htmlentities($data['data']);
					$html .= $this->UCP->emoji->toImage($data['data']);
				break;
			}
			$html .= "</br>";
		}
		return $html;
	}

	/**
	 * The Handler for all ajax events releated to this class
	 *
	 * Used by Ajax Class to process commands
	 *
	 * @return mixed Output if success, otherwise false will generate a 500 error serverside
	 */
	function ajaxHandler() {
		$return = array("status" => false, "message" => "");
		switch($_REQUEST['command']) {
			case 'upload':
				foreach ($_FILES["files"]["error"] as $key => $error) {
					if ($error == UPLOAD_ERR_OK) {
						$tmp_path = \FreePBX::Config()->get("ASTSPOOLDIR") . "/tmp";
						if(!file_exists($tmp_path)) {
							mkdir($tmp_path,0777,true);
						}

						$extension = pathinfo($_FILES["files"]["name"][$key], PATHINFO_EXTENSION);
						$supported = $this->UCP->FreePBX->Media->getSupportedFormats();
						if(true) {
							$tmp_name = $_FILES["files"]["tmp_name"][$key];
							$name = \Media\Media::cleanFileName($_FILES["files"]["name"][$key]);
							$fid = uniqid('sms');
							move_uploaded_file($tmp_name, $tmp_path."/".$fid."-".$name.".".$extension );
							$size = filesize($tmp_path."/".$fid."-".$name.".".$extension);
							if($size > 1500000) {
								$return['message'] = "<span class='text-danger'>"._('File Size is too large. Max: 1.5mb')."</span>";
								break;
							}
							$files = array($tmp_path."/".$fid."-".$name.".".$extension);
							$did = $_REQUEST['from'];
							$adaptor = $this->sms->getAdaptor($did);
							if(is_object($adaptor)) {
								$name = !empty($this->user['fname']) ? $this->user['fname'] : $this->user['username'];
								$o = $adaptor->sendMedia($_REQUEST['to'],$this->formatNumber($did),$name,"",$files);
								if($o['status']) {
									$return['status'] = true;
									$return['id'] = $o['id'];
									$return['html'] = $this->getMessageHtmlByID($o['id']);
								} else {
									$return['message'] = "<span class='text-danger'>".$o['message']."</span>";
									break;
								}
							} else {
								$return['message'] = "<span class='text-danger'>"._("Adaptor not loaded")."</span>";
								break;
							}
						} else {
							$return = array("status" => false, "message" => "<span class='text-danger'>"._("Unsupported file format")."</span>");
							break;
						}
					}
				}
			break;
			case 'messages':
				$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : '';
				$this->sms->markAllMessagesReadByDIDs($_REQUEST['from'],$_REQUEST['to']);
				$t = $this->sms->getAllMessages($this->userID,$_REQUEST['from'],$_REQUEST['to'],$search);
				$final = array();
				foreach($t as $m) {
					$html = $this->getMessageHtmlByID($m['id']);
					if(!empty($html)) {
						$m['body'] = $html;
					}
					$final[] = $m;
				}
				return $final;
			break;
			case 'grid':
				$sort = $_REQUEST['sort'];
				$order = $_REQUEST['order'];
				$limit = $_REQUEST['limit'];
				$offset = $_REQUEST['offset'];
				$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : '';
				$messages = $this->sms->getAllMessagesHistory($this->userID,$search,$order,$sort,$offset,$limit);
				$m = $this->sms->getAllMessagesHistory($this->userID,$search);
				$total = !empty($m) ? count($m) : 0;
				$final = array();
				$rows = array();
				foreach($messages as $conversation) {
					$msgs = array();
					foreach($conversation['messages'] as $message) {
						$message['body'] = $this->UCP->emoji->toImage($message['body']);
						$msgs[] = $message['body'];
					}
					reset($conversation['messages']);
					$rows[] = current($conversation['messages']);
					$conversation['messages'] = $msgs;
					$final[] = $conversation;
				}
				return array(
					"total" => $total,
					"rows" => $rows
				);
			break;
			case 'contacts':
				$return = array();
				if($this->Modules->moduleHasMethod('Contactmanager','lookupMultiple')) {
					$search = !empty($_REQUEST['search']) ? $_REQUEST['search'] : "";
					$results = $this->Modules->Contactmanager->lookupMultiple($search);
					if(!empty($results)) {
						foreach($results as $res) {
							foreach($res['numbers'] as $type => $num) {
								if(!empty($num)) {
									$return[] = array(
										"value" => $num,
										"text" => $res['displayname'] . " (".$type.")"
									);
								}
							}
						}
					}
				}
				break;
			case 'delete':
				$this->sms->deleteConversations($this->userID, $_REQUEST['from'], $_REQUEST['to']);
				$return['status'] = true;
			break;
			case 'history':
				$messages = $this->getOldMessages($_POST['id'],$_POST['from'],$_POST['to']);
				$return['status'] = true;
				$final = array();
				foreach($messages as $m) {
					$html = $this->getMessageHtmlByID($m['id']);
					if(!empty($html)) {
						$m['body'] = $html;
					}
					$final[] = $m;
				}
				$return['messages'] = $messages;
			break;
			case 'dids':
				$return['status'] = true;
				$return['dids'] = $this->dids;
			break;
			case 'read':
				$this->sms->markMessageRead($_POST['id']);
			break;
			case 'delivered':
				foreach($_POST['ids'] as $id) {
					$this->sms->markMessageDelivered($id);
				}
			break;
			case 'send':
				$did = $_POST['from'];
				$adaptor = $this->sms->getAdaptor($did);
				if(is_object($adaptor)) {
					$name = !empty($this->user['fname']) ? $this->user['fname'] : $this->user['username'];
					$o = $adaptor->sendMessage($_POST['to'],$this->formatNumber($did),$name,$_POST['message']);
					if($o['status']) {
						$return['status'] = true;
						$return['id'] = $o['id'];
					} else {
						$return['message'] = "<span class='text-danger'>".$o['message']."</span>";
					}
				} else {
					$return['message'] = "<span class='text-danger'>"._("Adaptor not loaded")."</span>";
				}
			break;
			default:
				return false;
			break;
		}
		return $return;
	}

	function ajaxCustomHandler() {
		switch($_REQUEST['command']) {
			case "media":
				$data = $this->sms->getMediaByName($_REQUEST['name']);
				if(!empty($data)) {
					$finfo = new \finfo(FILEINFO_MIME);
					header('Content-Type: '.$finfo->buffer($data));
					header("Content-Length: " .strlen($data) );
					echo $data;
				}
				return true;
			break;
			default:
				return false;
			break;
		}
		return false;
	}

	private function formatNumber($number) {
		if(strlen($number) == 10) {
			$number = '1'.$number;
		}
		return $number;
	}

	public function getBadge() {
		return false;
	}

	public function getMenuItems() {
		if(empty($this->dids)) {
			return array();
		}
		$unread = $this->sms->getUnreadCount($this->userID);
		$menu = array(
			"rawname" => "sms",
			"name" => "Sms",
			"badge" => $unread
		);
		return $menu;
	}

	/**
	* Send settings to UCP upon initalization
	*/
	public function getStaticSettings() {
		if(!empty($this->dids)) {
			return array(
				'enabled' => true,
				'dids' => $this->dids
			);
		} else {
			return array('enabled' => false);
		}
	}

	public function replaceDIDwithDisplay($did) {
		return $this->UCP->FreePBX->Sms->replaceDIDwithDisplay($this->userID,$did);
	}
}
