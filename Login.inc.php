<?php  ini_set('display_errors', 1);

class SimpleLogin{
	private $rest;

	public function __construct($_rest){
		$rest = $_rest;
	}

	private function register($args){
		if($rest->get_request_method() != "POST"){
			$rest->response('',406);
		}


		$email   = $args[0];
		$birth   = $args[1];
		$pwdsha1 = $args[2];
		$name    = $args[3];
		$ismale  = $args[4];
		$phone   = $args[5];
		$nacionality =  $args[6];
		

		$query = "SELECT * FROM entity where email = $email";
		$sql = mysql_query($query,$rest->db);
		if (mysql_fetch_row($sql,MYSQL_ASSOC)){
			$success = array('status' => "fail", "msg" => "email already exists");
			$rest->response($rest->json($success),204);	
		}

		$salt = $rest->randomSessionToken(16);
		$query = "INSERT INTO entity(email,name,register_date) VALUES ($email,$name,NOW())";
		error_log ("debug: $query");			
		$sql = mysql_query($query,$rest->db);
		$sql = mysql_query("select LAST_INSERT_ID() as li",$rest->db);
		$row=mysql_fetch_row($sql,MYSQL_ASSOC);$lastid = $row['li'];

		$query = "INSERT INTO users(password,salt,entity_id,isMale,phone,nacionality) VALUES (SHA1(CONCAT($pwdsha1,'$salt')),'$salt','$lastid',$ismale,$phone,$nacionality)";
		error_log ("debug: $query");
		$sql = mysql_query($query,$rest->db);
		$sql = mysql_query("select LAST_INSERT_ID() as li",$rest->db);
		$row=mysql_fetch_row($sql,MYSQL_ASSOC);$lastid = $row['li'];
		


		mkdir(self::HOME_PATH."/pic/user$lastid/garment",0755,TRUE);
		mkdir(self::HOME_PATH."/pic/user$lastid/outfit",0755,TRUE);

		$success = array('status' => "Success", "msg" => "Successfully one record edited.","lastid" => $lastid);
		$rest->response($rest->json($success),200);	
	}


	

	/* 
	 *	Simple login API
	 *  Login must be POST method
	 *  email : <USER EMAIL>
	 *  pwdsha1 : <USER PASSWORD>
	 */
	private function login(){		
		// Cross validation if the request method is POST else it will return "Not Acceptable" status
		if($rest->get_request_method() != "POST"){
			$rest->response('',406);
		}
		
		$email = $rest->_json['email'];		
		$password = $rest->_json['pwdsha1'];
		//error_log ("DEBUG pass $password");
		// Input validations
		if(!empty($email) and !empty($password)){
			error_log ("[DEBUG] $email , ".filter_var($email, FILTER_VALIDATE_EMAIL));				
			if(filter_var($email, FILTER_VALIDATE_EMAIL)){
				error_log ("[DEBUG] 2");		
				$query = "SELECT entity.id from entity inner join users on entity_id = entity.id  where (SHA1(CONCAT('$password',salt)) = password) and email = '$email' LIMIT 1";
				$sql = mysql_query($query, $rest->db);
				error_log ("[debug] $sql| $query | pass; ");
				if(mysql_num_rows($sql) > 0){
					$rand = $rest->randomSessionToken(32);
					$row=mysql_fetch_row($sql,MYSQL_ASSOC);$id = $row['id'];
					$sql =mysql_query("INSERT INTO session_on(session_token,logout_time,entity_id) VALUES ('$rand',NOW() + INTERVAL 30 MINUTE, $id) on duplicate KEY UPDATE session_token = '$rand', logout_time = NOW() + INTERVAL 30 MINUTE",$rest->db);
					
					$result = array('entity_id' => $id,"session_token" => $rand );
					// If success everythig is good send header as "OK" and user details
					$rest->response($rest->json($result), 200);
				}
				$rest->response('', 204);	// If no records "No Content" status
			}
		}
		
		// If invalid inputs "Bad Request" status message and reason
		$error = array('status' => "Failed", "msg" => "Invalid Email address or Password");
		$rest->response($rest->json($error), 400);
	}

	private function confirmLogin(){
			$session = $rest->_request['session_token'];
			$user_id = $rest->_request['entity_id'];
			$query = "SELECT entity_id FROM session_on WHERE entity_id = '$user_id' and session_token = '$session' and logout_time > NOW()";
			error_log ("[DEBUG] COMFIRM LOGIN query: $query");
			$sql = mysql_query($query, $rest->db);
			if(mysql_num_rows($sql) == 1){
				return TRUE;
			}
			error_log("DEGUG: CONFIRMA LOGIN FAILS");
			return FALSE;
	}

}
?>
