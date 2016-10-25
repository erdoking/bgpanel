<?PHP

/**
 * @author      Par0noid Solutions <par0noid@gmx.de>
 * @package     ts3admin
 * @version     0.6.6
 * @copyright   Copyright (c) 2009-2012, Stefan Z.
 * @link        http://ts3admin.info
 * @link        http://par0noid.info
**/
class ts3admin {

	private $runtime = array('socket' => '', 'selected' => false, 'host' => '', 'queryport' => '10011', 'timeout' => 2, 'debug' => array(), 'fileSocket' => '');

/**
  * checkSelected throws out 2 errors
  *
  * <b>Output:</b>
  * <pre>
  * Array
  * {
  *  [success] => false
  *  [errors] => Array 
  *  [data] => false
  * }
  * </pre>
  *
  * @author     Stefan Zehnpfennig
  * @return     array error
  */
	private function checkSelected() {
		$backtrace = debug_backtrace();
//		$this->addDebugLog('you can\'t use this function if no server is selected', $backtrace[1]['function'], $backtrace[0]['line']);
		return $this->generateOutput(false, array('you can\'t use this function if no server is selected'), false);
	}

	function channelList($params = '') {
		if(!$this->runtime['selected']) { return $this->checkSelected(); }
		if(!empty($params)) { $params = ' '.$params; }
		
		return $this->getData('multi', 'channellist'.$params);
	}

	function clientInfo($clid) {
		if(!$this->runtime['selected']) { return $this->checkSelected(); }
		return $this->getData('array', 'clientinfo clid='.$clid);
	}


	function clientList($params = '') {
		if(!$this->runtime['selected']) { return $this->checkSelected(); }
		
		if(!empty($params)) { $params = ' '.$params; }
		
		return $this->getData('multi', 'clientlist'.$params);
	}

	function selectServer($value, $type = 'port', $virtual = false) {
		if(in_array($type, array('port', 'serverId'))) {
			if($type == 'port') {
				if($virtual) { $virtual = ' -virtual'; }else{ $virtual = ''; }
				$res = $this->getData('boolean', 'use port='.$value.$virtual);
				if($res['success']) {
					$this->runtime['selected'] = true;
				}
				return $res;
			}else{
				$res = $this->getData('boolean', 'use sid='.$value);
				if($res['success']) {
					$this->runtime['selected'] = true;
				}
				return $res;
			}
		}else{
			return $this->generateOutput(false, array('Error: wrong value type'), false);
		}
	}

	function serverInfo() {
		if(!$this->runtime['selected']) { return $this->checkSelected(); }
		return $this->getData('array', 'serverinfo');
	}

	function __construct($host, $queryport, $timeout = 2) {
		if($queryport >= 1 and $queryport <= 65536) {
			if($timeout >= 1) {
				$this->runtime['host'] = $host;
				$this->runtime['queryport'] = $queryport;
				$this->runtime['timeout'] = $timeout;
			}else{
			}
		}else{
		}
	}

	private function isConnected() {
		if(empty($this->runtime['socket'])) {
			return false;
		}else{
			return true;
		}
	}

	private function generateOutput($success, $errors, $data) {
		return array('success' => $success, 'errors' => $errors, 'data' => $data);
	}

 	private function unEscapeText($text) {
 		$escapedChars = array("\t", "\v", "\r", "\n", "\f", "\s", "\p", "\/");
 		$unEscapedChars = array('', '', '', '', '', ' ', '|', '/');
		$text = str_replace($escapedChars, $unEscapedChars, $text);
		return $text;
	}

	public function connect() {
		if($this->isConnected()) { 
			return $this->generateOutput(false, array('Error: the script is already connected!'), false);
		}
		$socket = @fsockopen($this->runtime['host'], $this->runtime['queryport'], $errnum, $errstr, $this->runtime['timeout']);

		if(!$socket) {
			return $this->generateOutput(false, array('Error: connection failed!', 'Server returns: '.$errstr), false);
		}else{
			if(strpos(fgets($socket), 'TS3') !== false) {
				$tmpVar = fgets($socket);
				$this->runtime['socket'] = $socket;
				return $this->generateOutput(true, array(), true);
			}else{
				return $this->generateOutput(false, array('Error: host isn\'t a ts3 instance!'), false);
			}
		}
	}

	private function executeCommand($command, $tracert) {
		if(!$this->isConnected()) {
			return $this->generateOutput(false, array('Error: script isn\'t connected to server'), false);
		}
		
		$data = '';

		
		$splittedCommand = str_split($command, 1024);
		
		$splittedCommand[(count($splittedCommand) - 1)] .= "\n";
		
		foreach($splittedCommand as $commandPart) {
			fputs($this->runtime['socket'], $commandPart);
		}

		do {
			$data .= fgets($this->runtime['socket'], 4096);
			
			if(strpos($data, 'error id=3329 msg=connection') !== false) {
				$this->runtime['socket'] = '';
				return $this->generateOutput(false, array('You got banned from server. Connection closed.'), false);
			}
			
		} while(strpos($data, 'msg=') === false or strpos($data, 'error id=') === false);

		if(strpos($data, 'error id=0 msg=ok') === false) {
			$splittedResponse = explode('error id=', $data);
			$chooseEnd = count($splittedResponse) - 1;
			
			$cutIdAndMsg = explode(' msg=', $splittedResponse[$chooseEnd]);
			
			
			return $this->generateOutput(false, array('ErrorID: '.$cutIdAndMsg[0].' | Message: '.$this->unEscapeText($cutIdAndMsg[1])), false);
		}else{
			return $this->generateOutput(true, array(), $data);
		}
	}

	private function getData($mode, $command) {
	
		$validModes = array('boolean', 'array', 'multi', 'plain');
	
		if(!in_array($mode, $validModes)) {
			return $this->generateOutput(false, array('Error: '.$mode.' is an invalid mode'), false);
		}
		
		if(empty($command)) {
			return $this->generateOutput(false, array('Error: you have to enter a command'), false);
		}
		
		$fetchData = $this->executeCommand($command, debug_backtrace());
		
		
		$fetchData['data'] = str_replace(array('error id=0 msg=ok', chr('01')), '', $fetchData['data']);
		
		
		if($fetchData['success']) {
			if($mode == 'boolean') {
				return $this->generateOutput(true, array(), true);
			}
			
			if($mode == 'array') {
				if(empty($fetchData['data'])) { return $this->generateOutput(true, array(), array()); }
				$datasets = explode(' ', $fetchData['data']);
				
				$output = array();
				
				foreach($datasets as $dataset) {
					$dataset = explode('=', $dataset);
					
					if(count($dataset) > 2) {
						for($i = 2; $i < count($dataset); $i++) {
							$dataset[1] .= '='.$dataset[$i];
						}
						$output[$this->unEscapeText($dataset[0])] = $this->unEscapeText($dataset[1]);
					}else{
						if(count($dataset) == 1) {
							$output[$this->unEscapeText($dataset[0])] = '';
						}else{
							$output[$this->unEscapeText($dataset[0])] = $this->unEscapeText($dataset[1]);
						}
						
					}
				}
				return $this->generateOutput(true, array(), $output);
			}
			if($mode == 'multi') {
				if(empty($fetchData['data'])) { return $this->generateOutput(true, array(), array()); }
				$datasets = explode('|', $fetchData['data']);
				
				$output = array();
				
				foreach($datasets as $datablock) {
					$datablock = explode(' ', $datablock);
					
					$tmpArray = array();
					
					foreach($datablock as $dataset) {
						$dataset = explode('=', $dataset);
						if(count($dataset) > 2) {
							for($i = 2; $i < count($dataset); $i++) {
								$dataset[1] .= '='.$dataset[$i];
							}
							$tmpArray[$this->unEscapeText($dataset[0])] = $this->unEscapeText($dataset[1]);
						}else{
							if(count($dataset) == 1) {
								$tmpArray[$this->unEscapeText($dataset[0])] = '';
							}else{
								$tmpArray[$this->unEscapeText($dataset[0])] = $this->unEscapeText($dataset[1]);
							}
						}					
					}
					$output[] = $tmpArray;
				}
				return $this->generateOutput(true, array(), $output);
			}
			if($mode == 'plain') {
				return $fetchData;
			}
		}else{
			return $this->generateOutput(false, $fetchData['errors'], false);
		}
	}
}

?>
