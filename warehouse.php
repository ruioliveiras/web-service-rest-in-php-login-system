<?php  ini_set('display_errors', 1);
/*
Version: 0.7
Date: 15/01/2014
Author: Rui Oliveira
*/


/* 
	?request=login 
	?request=[/=login]&session=[mysession]&user_id=[myuserId]
	
	--register--
	 [POST]request=register  (application/json) {email,pwdsha1,name,isMale,phone,nacionality}
	--image--
	[POST]?request=image&session=[mysession]&user_id=[myuserId]&type=[avatar,outfit,garment]&id=[garment/oufit id] (image/jpeg)
	[GET] ?request=image&session=[mysession]&user_id=[myuserId]&type=[avatar,outfit,garment]&id=[garment/oufit id]&size=[512/256/128/64] 


 */

include('ImageWorker.php');
require_once("Rest.inc.php");
class API extends REST {

	public $data = "";
	
	const DB_SERVER = "localhost";
	const DB_USER = "server";
	const DB_PASSWORD = "password";
	const DB = "warehouse";
	const HOME_PATH = "/var/www";		
	
	private $db = NULL;

	private $user_id = 0;
	public $entity_id = 0;
	public $id;	

	public function __construct(){
		parent::__construct();				// Init parent contructor
		$this->dbConnect();					// Initiate Database connection
	}
	
	/*
	 *  Database connection 
	*/
	private function dbConnect(){
		$this->db = mysql_connect(self::DB_SERVER,self::DB_USER,self::DB_PASSWORD);
		if($this->db)
			mysql_select_db(self::DB,$this->db);

	}
	
	/*
	 * Public method for access api.
	 * This method dynmically call the method based on the query string
	 *
	 */
	public function processApi(){
		$serviceLow = array("login","register");
		$serviceHigh = array("friend","image","feed","notification","brand","garment","outfit");			
		
		if (!array_key_exists('request' , $_REQUEST)){
			$this->response('',404);
		}

		$requestName = strtolower(trim(str_replace("/","",$_REQUEST['request'])));

		error_log("[debug] $requestName at request: ".$this->get_request_method()."keys:".join(",",array_keys($this->_request))." vars: ".join(",",$this->_request)." input: ". file_get_contents("php://input"));
		if(in_array($requestName,$serviceLow)){
			$this->$requestName();
		}else if(in_array($requestName,$serviceHigh)){
			//if(!$this->confirmLogin()){$this->response('',401);}
			$this->$requestName();
		}else{
			$this->response('',404);// If the method not exist with in this class, response would be "Page not found".
		}
	}

	private function register(){
		if($this->get_request_method() != "POST"){
			$this->response('',406);
		}
		$email = $this->queryValue($this->_json['email']);
		$birth = $this->queryValue($this->_json['birth']);				
		$pwdsha1 = $this->queryValue($this->_json['pwdsha1']);
		$name = $this->queryValue($this->_json['name']);
		$ismale = $this->queryValue($this->_json['isMale']);
		$phone = $this->queryValue($this->_json['phone']);
		$nacionality = $this->queryValue($this->_json['nacionality']);
		

		$query = "SELECT * FROM entity where email = $email";
		$sql = mysql_query($query,$this->db);
		if (mysql_fetch_row($sql,MYSQL_ASSOC)){
			$success = array('status' => "fail", "msg" => "email already exists");
			$this->response($this->json($success),204);	
		}

		$salt = $this->randomSessionToken(16);
		$query = "INSERT INTO entity(email,name,register_date) VALUES ($email,$name,NOW())";
		error_log ("debug: $query");			
		$sql = mysql_query($query,$this->db);
		$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);
		$row=mysql_fetch_row($sql,MYSQL_ASSOC);$lastid = $row['li'];

		$query = "INSERT INTO users(password,salt,entity_id,isMale,phone,nacionality) VALUES (SHA1(CONCAT($pwdsha1,'$salt')),'$salt','$lastid',$ismale,$phone,$nacionality)";
		error_log ("debug: $query");
		$sql = mysql_query($query,$this->db);
		$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);
		$row=mysql_fetch_row($sql,MYSQL_ASSOC);$lastid = $row['li'];
		


		mkdir(self::HOME_PATH."/pic/user$lastid/garment",0755,TRUE);
		mkdir(self::HOME_PATH."/pic/user$lastid/outfit",0755,TRUE);

		$success = array('status' => "Success", "msg" => "Successfully one record edited.","lastid" => $lastid);
		$this->response($this->json($success),200);	
	}


	

	/* 
	 *	Simple login API
	 *  Login must be POST method
	 *  email : <USER EMAIL>
	 *  pwdsha1 : <USER PASSWORD>
	 */
	private function login(){		
		// Cross validation if the request method is POST else it will return "Not Acceptable" status
		if($this->get_request_method() != "POST"){
			$this->response('',406);
		}
		
		$email = $this->_json['email'];		
		$password = $this->_json['pwdsha1'];
		//error_log ("DEBUG pass $password");
		// Input validations
		if(!empty($email) and !empty($password)){
			error_log ("[DEBUG] $email , ".filter_var($email, FILTER_VALIDATE_EMAIL));				
			if(filter_var($email, FILTER_VALIDATE_EMAIL)){
				error_log ("[DEBUG] 2");		
				$query = "SELECT entity.id from entity inner join users on entity_id = entity.id  where (SHA1(CONCAT('$password',salt)) = password) and email = '$email' LIMIT 1";
				$sql = mysql_query($query, $this->db);
				error_log ("[debug] $sql| $query | pass; ");
				if(mysql_num_rows($sql) > 0){
					$rand = $this->randomSessionToken(32);
					$row=mysql_fetch_row($sql,MYSQL_ASSOC);$id = $row['id'];
					$sql =mysql_query("INSERT INTO session_on(session_token,logout_time,entity_id) VALUES ('$rand',NOW() + INTERVAL 30 MINUTE, $id) on duplicate KEY UPDATE session_token = '$rand', logout_time = NOW() + INTERVAL 30 MINUTE",$this->db);
					
					$result = array('entity_id' => $id,"session_token" => $rand );
					// If success everythig is good send header as "OK" and user details
					$this->response($this->json($result), 200);
				}
				$this->response('', 204);	// If no records "No Content" status
			}
		}
		
		// If invalid inputs "Bad Request" status message and reason
		$error = array('status' => "Failed", "msg" => "Invalid Email address or Password");
		$this->response($this->json($error), 400);
	}

	private function confirmLogin(){
			$session = $this->_request['session_token'];
			$user_id = $this->_request['entity_id'];
			$query = "SELECT entity_id FROM session_on WHERE entity_id = '$user_id' and session_token = '$session' and logout_time > NOW()";
			error_log ("[DEBUG] COMFIRM LOGIN query: $query");
			$sql = mysql_query($query, $this->db);
			if(mysql_num_rows($sql) == 1){
				return TRUE;
			}
			error_log("DEGUG: CONFIRMA LOGIN FAILS");
			return FALSE;
	}





	private function image(){
		if($this->get_request_method() == "POST"){
			if(!$this->confirmLogin()){$this->response('',401);}
			$entity_id = $this->_request['entity_id'];
			$type = $this->_request['type'];

			if ($type="avatar"){
				$image = new ImageWorker($_FILES['userfile']['tmp_name'], self::HOME_PATH."/pic/user$entity_id/avatar");
				$success = array('status' => "Success", "msg" => "Successfully image add");
				$this->response($this->json($success),200);			
			}
			if ($type="outfit"){
				$id = $this->request['id'];
				$image = new ImageWorker($_FILES['userfile']['tmp_name'], self::HOME_PATH."/pic/user$entity_id/outfit/$id.jpg");
				$success = array('status' => "Success", "msg" => "Successfully image add.");
				$this->response($this->json($success),200);			
			}
			if ($type="garment"){
				$id = $this->request['id'];
				$image = new ImageWorker($_FILES['userfile']['tmp_name'], self::HOME_PATH."/pic/user$entity_id/garment/$id.jpg");
				$success = array('status' => "Success", "msg" => "Successfully image add.");
				$this->response($this->json($success),200);
			}
			$this->response('',406);
		}
		if ($this->get_request_method()== "GET"){
			$entity_id = $this->_request['entity_id'];
			$type = $this->_request['type'];
			$size = $this->_request['size'];
			if (empty($size)) {$size= 512;}
			if ($type="avatar"){
				$this->responseImage(self::HOME_PATH."/pic/user$entity_id/avatar$size.jpeg");
			}
			if ($type="outfit"){
				$id = $this->request['id'];
				$this->responseImage(self::HOME_PATH."/pic/user$entity_id/outfit/$id$size.jpeg");
			}
			if ($type="garment"){
				$id = $this->request['id'];
				$this->responseImage(self::HOME_PATH."/pic/user$entity_id/garment/$id$size.jpeg");
			}
			if ($type="feed"){
				$id = $this->queryValue($this->request['id']);
				$query="SELECT url from feed_photos where feed_id = $id";
				$sql =mysql_query($query,$this->db);
				$row=mysql_fetch_row($sql,MYSQL_ASSOC);
				if(!$row) $this->response('',204);
				$this->responseImage($row['url']."&size=$size");
			}
			$this->response('',406);
		}
		
		
	}
	
//select id,name from (select id2 from friend inner join (select * from users where id = 1) as t1 on t1.id = id1) as t2  inner join entity on t2.id2 = entity.id ; //os amigos de rui oliveira

	private function item(){
	$request = $this->get_request_method();
	if ($request == "GET"){
		if (!empty($id)){
			item_getID();
		}else{
			item_getList();
		}
	}else if ($request == 'POST' or $request == 'PUT'){
		if (!empty($id)){
			item_editId();
		}else{
			item_new();
		}
	}

	}

	private function item_getList(){
	$query = "SELECT * FROM item";
	$this->simpleGet($query,"items");
	}
	private function item_getID(){
	$query = "Select * from item where id = $id";
	$this->simpleGet($query,"");
	}
	private function item_editId(){
	//TODO;
	}
	private function item_new(){
	$array = array('description' => (1,1),'rating' => (1,1),'brand_id' => (1,1), 'type_id' => (1,1) , 'price' => (1,1)); // number 1 is to later assinale types to cast
	$query = queryFromArray("item",$array);
	error_log("QUERY : $query");
	simplePost($query);
	}
	
	

	private function randomSessionToken($len){
		 $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
		 $string = '';
		 for ($i = 0; $i < $len; $i++) {
		      $string .= $characters[rand(0, strlen($characters) - 1)];
		 }
		return $string;
	}
	private function queryValue($value){
	if ($value == "") return "null";
		return "'$value'";
	}
	/**
	 * Array form: foreach($array as ($CanBeNull,$castType)) 
	 */
	private function queryFromArray($table,$array){
		$mixed = array();
		foreach($array as $k=>($canBeNull,$Cast)){
			$mixed[$k]= ($this->queryValue($this->_json[$key]));
			if ($canBeNull == 0 and $mixed[$k] == "null" ){
				return "error";
			}			
		}
		$valuesNames=implode(",",array_keys($mixed));
		$values = =implode(",",array_values($mixed));
		$query = "INSERT INTO $table($valuesNames) VALUES ($values)";
	}

	private function checkExist($query){
		$sql = mysql_query($query,$this->db);
		return (mysql_fetch_row($sql,MYSQL_ASSOC));			
	}
	private function simpleValue($query){
		$sql = mysql_query($query,$this->db);$row=mysql_fetch_row($sql,MYSQL_ASSOC);
		return $row[1];
	}
}


	
// Initiiate Library

$api = new API;
$api->processApi();
?>
