<?php
	/* File : Rest.inc.php
	 * Author : Arun Kumar Sekar
	*/

	class REST {

		public $_allow = array();
		public $_content_type = "application/json";
		public $_request = array();
		public $_json = "";		

		private $_method = "";		
		private $_code = 200;
		
		public function __construct(){
			$this->inputs();
		}
		
		public function get_referer(){
			return $_SERVER['HTTP_REFERER'];
		}
		
		public function response($data,$status){
			$this->_code = ($status)?$status:200;
			$this->set_headers();
			echo $data;
			error_log ("RESULT: |$data|");
			exit;
		}
		public function responseImage($name){
			$fp = fopen($name, 'rb');

			header("Content-Type: image/jpeg");
			header("Content-Length: " . filesize($name));

			fpassthru($fp);
			exit;
		}		

		private function get_status_message(){
			$status = array(
						100 => 'Continue',  
						101 => 'Switching Protocols',  
						200 => 'OK',
						201 => 'Created',  
						202 => 'Accepted',  
						203 => 'Non-Authoritative Information',  
						204 => 'No Content',  
						205 => 'Reset Content',  
						206 => 'Partial Content',  
						300 => 'Multiple Choices',  
						301 => 'Moved Permanently',  
						302 => 'Found',  
						303 => 'See Other',  
						304 => 'Not Modified',  
						305 => 'Use Proxy',  
						306 => '(Unused)',  
						307 => 'Temporary Redirect',  
						400 => 'Bad Request',  
						401 => 'Unauthorized',  
						402 => 'Payment Required',  
						403 => 'Forbidden',  
						404 => 'Not Found',  
						405 => 'Method Not Allowed',  
						406 => 'Not Acceptable',  
						407 => 'Proxy Authentication Required',  
						408 => 'Request Timeout',  
						409 => 'Conflict',  
						410 => 'Gone',  
						411 => 'Length Required',  
						412 => 'Precondition Failed',  
						413 => 'Request Entity Too Large',  
						414 => 'Request-URI Too Long',  
						415 => 'Unsupported Media Type',  
						416 => 'Requested Range Not Satisfiable',  
						417 => 'Expectation Failed',  
						500 => 'Internal Server Error',  
						501 => 'Not Implemented',  
						502 => 'Bad Gateway',  
						503 => 'Service Unavailable',  
						504 => 'Gateway Timeout',  
						505 => 'HTTP Version Not Supported');
			return ($status[$this->_code])?$status[$this->_code]:$status[500];
		}
		
		public function get_request_method(){
			return $_SERVER['REQUEST_METHOD'];
		}
		
		private function inputs(){
			switch($this->get_request_method()){
				case "POST":
				case "PUT":
					$json = file_get_contents("php://input");
					$this->_json = json_decode($json,TRUE);
				case "GET":
				case "DELETE":
					$this->_request = $this->cleanInputs($_GET);
					break;
				default:
					$this->response('',406);
					break;
			}
		}		
		
		private function cleanInputs($data){
			$clean_input = array();
			if(is_array($data)){
				foreach($data as $k => $v){
					$clean_input[$k] = $this->cleanInputs($v);
				}
			}else{
				if(get_magic_quotes_gpc()){
					$data = trim(stripslashes($data));
				}
				$data = strip_tags($data);
				$clean_input = trim($data);
			}
			return $clean_input;
		}		
		
		private function set_headers(){
			header("HTTP/1.1 ".$this->_code." ".$this->get_status_message());
			header("Content-Type:".$this->_content_type);
		}

		/*
		 *	Encode array into JSON
		*/
		public function json($data){
			if(is_array($data)){
				return json_encode($data);
			}
		}



		public function simpleGet($query,$name){
			$sql = mysql_query($query, $this->db);
			if(mysql_num_rows($sql) > 0){
				$result = array();
				while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC)){
					$result[] = $rlt;
				}
				$final;
				if (empty($name)){
					$final = $result[1];
				}else{
					$final = array($name => $result);
				}
				// If success everythig is good send header as "OK" and return list of users in JSON format
				$this->response($this->json($final), 200);
			}
			$this->response('',204);	// If no records "No Content" status
		}
		public function simpleDelete($query){
			$sql = mysql_query($query, $this->db);
			$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
			$this->response($this->json($success),200);
		}
		public function simplePut($query){
			$sql = mysql_query($query,$this->db);
			$success = array('status' => "Success", "msg" => "Successfully one record edited.");
			$this->response($this->json($success),200);
		}
		public function simplePost($query){
			$sql = mysql_query($query,$this->db);
			$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);$row=mysql_fetch_row($sql,MYSQL_ASSOC);
			$lastid = $row['li'];
			$success = array('status' => "Success", "msg" => "Successfully one record add.","lastid" => $lastid);
			$this->response($this->json($success),200);
		}
	}	
?>
