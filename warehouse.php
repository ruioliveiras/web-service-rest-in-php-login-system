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
	}else if ($request == 'POST'){
		
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




		private function garment(){
			//if(!$this->confirmLogin()){$this->response('',401);}			
			$entity_id = $this->_request['entity_id'];
			$id = $this->_request['id'];
						
			if(!empty($id)){
				$this->garmentID($id);
			}else{
				$request = $this->get_request_method();
				if($request == "GET"){
					$sql = mysql_query("SELECT * FROM garment WHERE entity_id = '$entity_id'", $this->db);
					if(mysql_num_rows($sql) > 0){
						$result = array();
						while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC)){
							$result[] = $rlt;
						}
						// If success everythig is good send header as "OK" and return list of users in JSON format
						$this->response($this->json($result), 200);
					}
					$this->response('',204);	// If no records "No Content" status
				}
				if($request == 'DELETE'){
					$sql = mysql_query("DELETE FROM garment WHERE entity_id = '$entity_id'", $this->db);
					$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
					$this->response($this->json($success),200);
				}
				if($request == 'POST'){
					$json = $this->_json;
					$description = $this->queryValue($json['description']); 
					$brand = $this->queryValue($json['brand_id']);
					$type = $this->queryValue($json['type_id']); $collection = $this->queryValue($json['collection_id']);
					$shop = $this->queryValue($json['shop_id']); 
					$color1 = $this->queryValue($json['color1']);$color2 = $this->queryValue($json['color2']);
					$query = "INSERT INTO garment(description,brand_id,type_id,collection_id,shop_id,color1,color2) VALUES ($description,$brand, $type, $collection,$shop, $color1,  $color2) ";			
					error_log("QUERY : $query")	;
					$sql = mysql_query($query,$this->db);
					$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);
					$row=mysql_fetch_row($sql,MYSQL_ASSOC);$lastid = $row['li'];
					$success = array('status' => "Success", "msg" => "Successfully one record edited.","lastid" => $lastid);
					$this->response($this->json($success),200);
				}
				//otherWise:
				$this->response('',406);
				
			}
		}

/*   garmentID get;
[
  {
    "id": "2",
    "entity_id": "2",
    "description": "ola",
    "brand_id": "1",
    "type_id": "1",
    "collection_id": "1",
    "shop_id": null,
    "color1": null,
    "color2": null
  }
]
*/
		private function garmentID($id){
			$request = $this->get_request_method();
			if($request == "GET"){
				$sql = mysql_query("SELECT * FROM garment WHERE id = $id", $this->db);
				f(mysql_num_rows($sql) > 0){
					$result = array();
					while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC)){
						$result[] = $rlt;
					}
					// If success everythig iis good send header as "OK" and return list of users in JSON format
					$this->response($this->json($result), 200);
				}
				$this->response('',204);	// If no records "No Content" status
			}
			if($request == "DELETE"){
				$sql = mysql_query("DELETE FROM garment WHERE id = $id", $this->db);
				$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
				$this->response($this->json($success),200);
			}
			if($request == "PUT"){
				$json = $this->_json;
				$id = $this->queryValue($json['id']); $description = $this->queryValue($json['description']); 
				$brand = $this->queryValue($json['brand_id']);
				$type = $this->queryValue($json['type_id']); $collection = $this->queryValue($json['collection_id']);
				$shop = $this->queryValue($json['shop_id']); 
				$color1 = $this->queryValue($json['color1']);$color2 = $this->queryValue($json['color2']);
				$query = "UPDATE garment SET description = $description, brand_id = $brand, type_id = $type, collection_id = $collection, shop_id = $shop, color1 = $color1, color2 = $color2 where id = $id";			
				error_log("QUERY : $query")	;
				$sql = mysql_query($query,$this->db);
				$success = array('status' => "Success", "msg" => "Successfully one record edited.");
				$this->response($this->json($success),200);
			}
			//OtherWise
			$this->response('',406);
			
		}





private function friend(){
			$entity_id = $this->_request['entity_id'];			
			$request = $this->get_request_method();
			$email = (isset($this->_request['email'])) ? $this->queryValue($this->_request['email']): "";
			$id = (isset($this->_request['id'])) ? $this->queryValue($this->_request['id']): "";
			if (!empty($email) and empty($id)){
				$query = "Select users.id from entity inner join users on entity_id = entity.id where email = $email";
				$id = $this->simpleValue($query);
				if (empty($id)) $this->response("",204);
			}
		
			if(!empty($id)){
				//if are friends return their information
				$query = "select * from friend where id1 = '$entity_id' and id2 = $id";
				if ($this->checkExist($query)){
					if ($request == "GET"){
						$query = "Select id,name from entity where id = $id";
						$this->simpleGet($query,"");}
					if ($request == "DELETE"){
						$query = "delete from friend where (id1 = '$entity_id' or id2 = '$entity_id') and (id1 = $id or id2 = $id)";
						$this->simpleDelete($query);}
				}else{
					//if are inviteds return the acept;
					$query = "select * from invite where receiver = '$entity_id' and sender = $id";
					if ($this->checkExist($query)){
						if ($request == "POST"){
							$query = "INSERT INTO friend (id1,id2) VALUES ('$entity_id',$id)";
							mysql_query($query,$this->db);
							$query = "INSERT INTO friend (id2,id1) VALUES ('$entity_id',$id)";
							$this->simplePost($query);
						}
						if ($request == "DELETE"){
							$query = "delete from invite where reciver = '$entity_id' and sender = $id";
							$this->simpleDelete($query);}
						}else{
						//SENT INVITE
							if ($request == "POST"){
								$query = "INSERT INTO invite(sender,receiver) VALUES ('$entity_id',$id)";
								$this->simplePost($query);}		
						}
				}
				
				$this->response('',400);
			}
			if ($request == "GET"){
				$query = "select id,name from (select id2 from friend where id1 = '$entity_id') as t1 inner join entity on id2 = id";
//				$query = "select id,name from (select id2 from friend inner join (select * from users where id = '$entity_id') as t1 on t1.id = id1) as t2  inner join entity on t2.id2 = entity.id";
				$this->simpleGet($query,"friends");}

		}
		


		private function notification(){ //improve this;
			$entity_id = $this->_request['entity_id'];
			$request = $this->get_request_method();
			if ($request=="GET"){
				$query = "SELECT sender from invite where receiver == '$entity_id'";
				$this->simpleGet($query,"notifications");
			}
			$this->response('',400);
		}

		private function feed(){
			
		//if(!$this->confirmLogin()){$this->response('',401);}			
			$entity_id = $this->_request['entity_id'];
			$friend = (isset( $this->_request['friend'])) ? $this->_request['friend'] : ""; //id like entity friend id;
			$id = (isset($this->_request['id'])) ? $this->_request['id']: "";			
			$request = $this->get_request_method();
			if (!empty($friend)){
				if($request == "GET"){//TODO temos direitos para fazer isto? fazer query a confirmar
					$query = "Select id,entity_id,description,feed_date from feed where entity_id = $friend order by feed_date";
					$this->simpleGet($query,"feeds");}
			}
			if(!empty($id)){
				if ($request == "DELETE"){
					$query = "DELETE from feed where id = '$id' and entity_id = '$entity_id'";
					$this->simpleDelete($query);}
				if ($request == "GET"){
					$query = "Select id,entity_id,description,feed_date from feed where id = '$id' and entity_id = '$entity_id' order by feed_date" ;
					$this->simpleGet($query,"");}
				if ($request == "PUT"){
					$json = $this->_json;	$descr = $this->queryValue($json["description"]);
					$query = "UPDATE feed SET description = $descr  where id = '$id' and entity_id = '$entity_id'";
					$this->simplePut($query);
				}
			}
			//other wise get geral feed;
			if ($request == "GET"){
				$query = "select id,entity_id,description,feed_date from feed inner join friend on id2 = id where id1 = $entity_id order by feed_date";
				//feed photos getet from image service;
				$this->simpleGet($query,"feeds");}
			if ($request == "POST"){
				$json = $this->_json;	$descr = $this->queryValue($json["description"]);
				$photos = $json['photos'];
				
				$query = "INSERT INTO feed(entity_id,description,feed_date) VALUES ($entity_id,$descr,NOW())";						
				$sql = mysql_query($query,$this->db);
				$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);$row=mysql_fetch_row($sql,MYSQL_ASSOC);
				$lastid = $row['li'];

				foreach ($photos  as $value){
					$value = $this->queryValue($value);
					$query = "INSERT INTO feed_photos(feed_id,url) VALUES ('$lastid',$value)";
					$sql = mysql_query($query,$this->db);
				}
				
				$success = array('status' => "Success", "msg" => "Successfully one record edited.","lastid" => $lastid);
				$this->response($this->json($success),200);
			}
		
		}


		private function brand(){
			if(!$this->confirmLogin()){$this->response('',401);}			
			$entity_id = $this->_request['entity_id'];
			$id = $this->_request['id'];
			$request = $this->get_request_method();			
			if(empty($id)){
				if($request == "GET"){
//select brand.id as id,name,entity_id from brand inner join garment on brand_id = brand.id where entity_id = 2 group by id ;
					$query = "select brand.id as id,name,entity_id from brand inner join garment on brand_id = brand.id where entity_id = '$entity_id' group by id";
					$this->simpleGet($query,"brands");}
				if($request == 'POST'){
					$json = $this->_json;$name = $this->queryValue($json['name']);
					$query = "INSERT INTO brand(name) VALUES($name)";
					$this->simpePost($query);}
				//otherWise:
				$this->response('',406);
			}
			if($request == "GET"){
					$query = "select * from brand where id = $id";
					$this->simpleGet($query,"");}
			//select brand.id as id,name,entity_id from brand inner join garment on brand_id = brand.id where entity_id = 2 group by id ;
			

		}


		private function garment(){
			//if(!$this->confirmLogin()){$this->response('',401);}			
			$entity_id = $this->_request['entity_id'];
			$id = $this->_request['id'];
						
			if(!empty($id)){
				$this->garmentID($id);
			}else{
				$request = $this->get_request_method();
				if($request == "GET"){
					$sql = mysql_query("SELECT * FROM garment WHERE entity_id = '$entity_id'", $this->db);
					if(mysql_num_rows($sql) > 0){
						$result = array();
						while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC)){
							$result[] = $rlt;
						}
						// If success everythig is good send header as "OK" and return list of users in JSON format
						$this->response($this->json($result), 200);
					}
					$this->response('',204);	// If no records "No Content" status
				}
				if($request == 'DELETE'){
					$sql = mysql_query("DELETE FROM garment WHERE entity_id = '$entity_id'", $this->db);
					$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
					$this->response($this->json($success),200);
				}
				if($request == 'POST'){
					$json = $this->_json;
					$description = $this->queryValue($json['description']); 
					$brand = $this->queryValue($json['brand_id']);
					$type = $this->queryValue($json['type_id']); $collection = $this->queryValue($json['collection_id']);
					$shop = $this->queryValue($json['shop_id']); 
					$color1 = $this->queryValue($json['color1']);$color2 = $this->queryValue($json['color2']);
					$query = "INSERT INTO garment(description,brand_id,type_id,collection_id,shop_id,color1,color2) VALUES ($description,$brand, $type, $collection,$shop, $color1,  $color2) ";			
					error_log("QUERY : $query")	;
					$sql = mysql_query($query,$this->db);
					$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);
					$row=mysql_fetch_row($sql,MYSQL_ASSOC);$lastid = $row['li'];
					$success = array('status' => "Success", "msg" => "Successfully one record edited.","lastid" => $lastid);
					$this->response($this->json($success),200);
				}
				//otherWise:
				$this->response('',406);
				
			}
		}

/*   garmentID get;
[
  {
    "id": "2",
    "entity_id": "2",
    "description": "ola",
    "brand_id": "1",
    "type_id": "1",
    "collection_id": "1",
    "shop_id": null,
    "color1": null,
    "color2": null
  }
]
*/
		private function garmentID($id){
			$request = $this->get_request_method();
			if($request == "GET"){
				$sql = mysql_query("SELECT * FROM garment WHERE id = $id", $this->db);
				if(mysql_num_rows($sql) > 0){
					$result = array();
					while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC)){
						$result[] = $rlt;
					}
					// If success everythig is good send header as "OK" and return list of users in JSON format
					$this->response($this->json($result), 200);
				}
				$this->response('',204);	// If no records "No Content" status
			}
			if($request == "DELETE"){
				$sql = mysql_query("DELETE FROM garment WHERE id = $id", $this->db);
				$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
				$this->response($this->json($success),200);
			}
			if($request == "PUT"){
				$json = $this->_json;
				$id = $this->queryValue($json['id']); $description = $this->queryValue($json['description']); 
				$brand = $this->queryValue($json['brand_id']);
				$type = $this->queryValue($json['type_id']); $collection = $this->queryValue($json['collection_id']);
				$shop = $this->queryValue($json['shop_id']); 
				$color1 = $this->queryValue($json['color1']);$color2 = $this->queryValue($json['color2']);
				$query = "UPDATE garment SET description = $description, brand_id = $brand, type_id = $type, collection_id = $collection, shop_id = $shop, color1 = $color1, color2 = $color2 where id = $id";			
				error_log("QUERY : $query")	;
				$sql = mysql_query($query,$this->db);
				$success = array('status' => "Success", "msg" => "Successfully one record edited.");
				$this->response($this->json($success),200);
			}
			//OtherWise
			$this->response('',406);
			
		}


		private function outfit(){
			if(!$this->confirmLogin()){$this->response('',401);}			
			$entity_id = $this->_request['entity_id'];
			$id = $this->_request['id'];
			if(!empty($id)){
				$this->outfitID($id);
			}else{
				$request = $this->get_request_method();	
				if($request=='GET'){
					$query1 = "select id,description,category_id  from outfit where entity_id = $entity_id order by id";
					$query2 = "select outfit_id,garment_id from outfit_garment inner join outfit on id = outfit_id where entity_id = $entity_id order by outfit_id";
					$sql1 = mysql_query($query1, $this->db);
					
					$sql2 = mysql_query($query2, $this->db);
					
					if(mysql_num_rows($sql1) > 0){
					    $result = array();
						$aux = array();
						$row2 = mysql_fetch_array($sql2,MYSQL_ASSOC);
						while($row1 = mysql_fetch_array($sql1,MYSQL_ASSOC)){
							//if ($row2) {$aux = array();} else {$aux = array($row2);}					
							//error_log ("debug2 ".var_export($row2,true));
							if (($row2 != false)&&($row2['outfit_id'] != $row1['id'])){$row1['garments'] = array();}
							else{
								$aux = array();

								while($row2['outfit_id'] == $row1['id']){								
									$aux[] = $row2['garment_id'];
									$row2 = mysql_fetch_array($sql2,MYSQL_ASSOC);
								}
								$row1['garments'] = $aux;								
							}
							$result[] = $row1;
						}
						// If success everythig is good send header as "OK" and return list of users in JSON format
						$this->response($this->json($result), 200);
					}
					$this->response('',204);	// If no records "No Content" status
//select outfit_id,garment_id from outfit_garment inner join outfit on id = outfit_id where entity_id = $entity_id order by outfit_id;

				}
				if ($request = "DELETE"){
					$query = "DELETE FROM outfit where entity_id = $entity_id";
					mysql_query($query,$this->db);
					$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
					$this->response($this->json($success),200);
				}
				if ($request = "POST"){ //TODO: testar(no android)
					$json = $this->_json;
					$garments = $json['garments'];
					$description = $this->queryValue($json['description']); 
					$category_id = $this->queryValue($json['category_id']);
					$query = "INSERT INTO outfit(description,category_id) VALUES ($description,$category_id)";
	  				mysql_query($query,$this->db);				
					$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);$row=mysql_fetch_row($sql,MYSQL_ASSOC);
					$id = $row['li'];
					foreach ($garments as $value) {
						$query = "INSERT INTO outfit_garment(oufit_id,garment_id) VALUES ($id,$value)";			
		 				mysql_query($query,$this->db);						   			
					}
					$success = array('status' => "Success", "msg" => "Successfully one record add.", "lastid" => $id);
					$this->response($this->json($success),200);
					}
				//OtherWise
				$this->response('',406);
			}
			
		}

		private function outfitID($id){
			$request = $this->get_request_method();
			$entity_id = $this->_request['entity_id'];
			if($request == "GET"){
				$sql = mysql_query("SELECT garment_id FROM outfit_garment WHERE outfit_id = $id", $this->db);
				if(mysql_num_rows($sql) > 0){
					$garments = array();
					while($rlt = mysql_fetch_array($sql,MYSQL_ASSOC)){
						$garments[] = $rlt['garment_id'];
					}
					$sql = mysql_query("SELECT * FROM outfit WHERE id = $id and entity_id = $entity_id", $this->db);
					
					$row=mysql_fetch_row($sql,MYSQL_ASSOC);
//					error_log("debug: ".var_export($garments[0],true));
					$row['garments'] =array_values($garments);
					// If success everythig is good send header as "OK" and return list of users in JSON format
					$this->response($this->json($row), 200);
				}
				$this->response('',204);	// If no records "No Content" status
			}
			if($request == "DELETE"){
				$sql = mysql_query("DELETE FROM outfit WHERE id = $id and entity_id = $entity_id", $this->db);
				$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
				$this->response($this->json($success),200);
			}
			if($request == "PUT"){ //TODO: testar(no android)
				$json = $this->_json;
				$garments = $json['garments'];
				$id = $this->queryValue($json['id']); $description = $this->queryValue($json['description']); 
				$category_id = $this->queryValue($json['category_id']);
				$query = "UPDATE outfit SET description = $description,category_id = $category_id where id = $id";			
  				mysql_query($query,$this->db);				
				
				error_log("debug: ".var_export($json,true));
				$query = "DELETE from outfit_garment where outfit_id = $id";			
  				mysql_query($query,$this->db);				
				foreach ($garments as $value) {
					$query = "INSERT INTO outfit_garment(oufit_id,garment_id) VALUES ($id,$value)";			
	 				mysql_query($query,$this->db);						   			
				}
				$success = array('status' => "Success", "msg" => "Successfully one record edited.");
				$this->response($this->json($success),200);
			}
			//OtherWise
			$this->response('',406);
			
		}
		
		


		/*
		 *	Encode array into JSON
		*/
		private function json($data){
			if(is_array($data)){
				return json_encode($data);
			}
		}
		private function simpleGet($query,$name){
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
		private function simpleDelete($query){
			$sql = mysql_query($query, $this->db);
			$success = array('status' => "Success", "msg" => "Successfully one record deleted.");
			$this->response($this->json($success),200);
		}
		private function simplePut($query){
		//	error_log("QUERY : $query")	;
			$sql = mysql_query($query,$this->db);
			$success = array('status' => "Success", "msg" => "Successfully one record edited.");
			$this->response($this->json($success),200);
		}
		private function simplePost($query){
			$sql = mysql_query($query,$this->db);
			$sql = mysql_query("select LAST_INSERT_ID() as li",$this->db);$row=mysql_fetch_row($sql,MYSQL_ASSOC);
			$lastid = $row['li'];
			$success = array('status' => "Success", "msg" => "Successfully one record edited.","lastid" => $lastid);
			$this->response($this->json($success),200);
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
//		error_log ("$value is null? (".strtolower($value)." == null ?".(strtolower($value) == "null")."#");
		if ($value == "") return "null";
			return "'$value'";
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