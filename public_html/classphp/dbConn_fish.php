<?php

if (!class_exists('dbconnClassFish')) {

	class dbconnClassFish
	{

		private $host = 'localhost';
		private $dbname = 'joeu_fish';
		private $username = 'joeu_fish';
		private $password = 'w3m69p21!@';
		
		public function pdo_conn()
		{
			try {
				$pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
				$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				return $pdo;
			} catch (PDOException $e) {
				die("DB Connection failed: " . $e->getMessage());
			}
		}

		function conn()
		{
			// ini_set('default_socket_timeout', 10);
			$conn = new mysqli('localhost', $this->username, $this->password, $this->dbname);
			$conn->set_charset("utf8mb4");
			if ($conn->connect_error) {
				die("DB Connection failed: " . $conn->connect_error);
			}
			mysqli_set_charset($conn, 'utf8');
			return $conn;
		}

		function countLog($conn, $ip)
		{
			$today = date('Y-m-d');
			$sql = "select count(*) cnt from logdb where ip='" . $ip . "' and substr(dt,1,10) = '" . $today . "' ";
			$result = $conn->query($sql);
			$cnt = 0;
			if ($row = $result->fetch_assoc()) $cnt = $row['cnt'];
			return $cnt;
		}

		// IP로 그룹핑 -by jsj 20210324
		function countLogIpGrp($conn, $searchDate)
		{
			$today = date('Y-m-d');
			$sql =  " SELECT COUNT(c.ip) as cnt FROM ";
			$sql .= " (SELECT ip, count(ip) FROM logdb WHERE SUBSTR(dt,1,10) = '" . $searchDate . "' GROUP BY ip ) AS c";
			$result = $conn->query($sql);
			if ($row = $result->fetch_assoc()) {
				$cnt = $row['cnt'];
			} else {
				return ("ln50::SQL Err=" . $sql);
			}
			return $cnt;
		}

		function countLogId($conn, $id)
		{
			$today = date('Y-m-d');
			$sql = "select count(*) cnt from logdb where id like '" . $id . "' and substr(dt,1,10) = '" . $today . "' ";
			$result = $conn->query($sql);
			//echo $sql;
			$cnt = 0;
			if ($row = $result->fetch_assoc()) $cnt = $row['cnt'];
			//echo ' cnt='.$cnt;
			return $cnt;
		}

		function countLogdt($conn, $id, $dt1, $dt2)
		{ //from-to
			//$today = date('Y-m-d');
			$sql = "select count(*) cnt from logdb where id like '" . $id . "' and substr(dt,1,10) >= '" . $dt1 . "' and substr(dt,1,10) <= '" . $dt2 . "' ";
			$result = $conn->query($sql);
			//echo $sql;
			$cnt = 0;
			if ($row = $result->fetch_assoc()) $cnt = $row['cnt'];
			//echo ' cnt='.$cnt;
			return $cnt;
		}

		// JSon make
		function rs2Json1($result, $msgCode, $jsonFileName = '')
		{
			// $json_string = '{"response": { "header": { "resultCode": "' . $msgCode . '", "resultMsg":"정상"}, "body": { "items": [';
			$json_string = '{"response": { "header": { "resultCode": "' . $msgCode . '", "resultMsg":"정상", "jsonFileName": "' . $jsonFileName . '"}, "body": { "items": [';
			$total_rows = mysqli_num_rows($result);
			$results = $result->fetch_all(MYSQLI_ASSOC); // fetch_all 함수를 사용하여 모든 결과를 연관 배열로 가져옴

			// 결과가 없는 경우 처리
			if (!$results || $total_rows == 0) {
				$json_string .= '], "numOfRows": 0, "pageNo": 1, "totalCount": 0}} }';
				return $json_string;
			}

			// 결과가 있을 경우 처리
			$rowCount = count($results); // 결과 행의 수를 세기 위해 배열의 길이를 사용
			$i = 1;
			foreach ($results as $row) {
				$json_string .= '{ ';
				foreach ($row as $key => $value) {
					$value = str_replace("\"", "'", $value);
					$json_string .= '"' . $key . '": "' . $value . '", ';
				}
				$json_string = rtrim($json_string, ', '); // 마지막 쉼표 제거
				if ($i >= $rowCount) {
					$json_string .= '}';
				} else {
					$json_string .= '},';
				}
				$i++;
			}
			$json_string .= '], "numOfRows": ' . $rowCount . ', "pageNo": 1, "totalCount": ' . $rowCount . '}} }';
			echo $json_string;
			return;
		}

		// -------------------------------------
		// log
		// -------------------------------------
		function logWrite($id, $pg, $rmrk)
		{
			$conn = $this->conn();
			$pgd = urldecode($pg); //decodeURIComponent($pg);
			$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
			$sql  = "INSERT INTO logdb (pg,id,ip,rmrk) VALUES ('" . $pgd . "','" . $id . "','" . $ip . "','" . $rmrk . "')";
			if (!$conn->query($sql)) {
				echo "ln545::sql error=" . $sql;
			}
			$this->addlog($conn, $id);
		}

		function addlog($conn, $id)
		{
			if (trim($id) == '') return;
			$sql = "select * from PayUser02H where PayUser01M_user_login='" . $id . "'";
			//echo 'sql='.$sql;
			$result3 = $conn->query($sql);
			if ($row = $result3->fetch_assoc()) {
			} else {
				$sql = 'insert into PayUser02H (PayUser01M_user_login,payDate,payTypeCD,svrstrDT,svrendDT,svrserCnt,modifyDT) ';
				$sql .= " VALUES('" . $id . "',now(),'00',now(),now(),0,now() )";
				//echo 'sql='.$sql;
				$conn->query($sql);
			}
			$today = date('Y-m-d');
			$sql  = "update PayUser02H set svrserCnt = svrserCnt + 1 where PayUser01M_user_login = '" . $id . "' and substr(svrstrDT,1,10) <='" . $today . "' and substr(svrendDT,1,10)>='" . $today . "'";
			//echo $sql;
			$conn->query($sql);
		}

		// -------------------------------------
		// DB Bind
		// -------------------------------------
		function bindAll2($stmt)
		{
			$meta = $stmt->result_metadata();
			$fields = array();
			$fieldRefs = array();
			while ($field = $meta->fetch_field()) {
				$fields[$field->name] = "";
				$fieldRefs[] = &$fields[$field->name];
			}
			call_user_func_array(array($stmt, 'bind_result'), $fieldRefs);
			$stmt->store_result();
			//var_dump($fields);
			return $fields;
		}

		// --------------------
		// AI가 만든 소스
		// --------------------
		function fetchResults($stmt)
		{
			$result = array();
			// 확인할 유효한 mysqli_stmt 객체인지 확인합니다.
			if ($stmt instanceof mysqli_stmt) {
				// 결과 메타데이터를 가져옵니다.
				$meta = $stmt->result_metadata();
				// 메타데이터가 유효한지 확인합니다.
				if ($meta) {
					// 결과를 바인딩할 변수 배열을 초기화합니다.
					$fields 	= array();
					$fieldRefs 	= array();
					// 필드의 메타데이터를 반복하여 필드명을 추출합니다.
					while ($field = $meta->fetch_field()) {
						// 필드명을 배열에 추가합니다.
						$fields[$field->name] = "";
						$fieldRefs[] = &$fields[$field->name];
					}
					// 결과를 바인딩합니다.
					call_user_func_array(array($stmt, 'bind_result'), $fieldRefs);
					while ($stmt->fetch()) {
						// 현재 결과를 복사하여 결과 배열에 추가합니다.
						$result[] = $fields;
					}
					$meta->free_result();
				}
			}
			return $result;
		}

		function fetchRowAssoc($stmt, &$fields)
		{
			if ($stmt->fetch()) {
				return $fields;
			}
			return false;
		}

		function MobileCheck()
		{
			// CLI 환경에서는 웹 브라우저가 아니므로 기본값 반환
			if (php_sapi_name() === 'cli') {
				return 'computer';
			}
			
			$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
			$isMobile = (bool) preg_match('/iPhone|iPod|Android/i', $userAgent);
			return ($isMobile) ? "mobile" : "computer";
		}

		// -----------------------------------------------
		// 아파트|매매::검색 -by jsj 20220101
		// -----------------------------------------------
		function getSvrData_Fish($kwd, $curStart, $cntOnce, $searchType = 1)
		{
			$conn = $this->conn();

			$sql = " SELECT *
				   FROM tbarcodein 
				  WHERE 1";
			$sql .= " ORDER BY create_dt DESC LIMIT " . $curStart . "," . $cntOnce;

			$result = $conn->query($sql);
			$json_string = $this->rs2Json1($result, '00'); // 추세분석 JSON 파일명 전달
			echo $json_string;

			return;
		}

		function checkUserIsAdmin()
		{
			// 현재 사용자가 로그인되어 있는지 확인
			if (!is_user_logged_in()) {
				return false;
			}

			// 현재 사용자 객체 가져오기
			$current_user = wp_get_current_user();

			// 사용자가 관리자 역할을 가지고 있는지 확인
			if (in_array('administrator', $current_user->roles)) {
				return true;
			}

			// 또는 특정 기능에 대한 권한을 확인할 수도 있습니다
			// 예: 'manage_options' 권한은 일반적으로 관리자에게만 주어집니다
			if (current_user_can('manage_options')) {
				return true;
			}

			// 위의 조건을 모두 만족하지 않으면 관리자가 아님
			return false;
		}

		function is_user_editor()
		{
			$user = wp_get_current_user();
			if (in_array('editor', (array) $user->roles)) {
				return true;
			}
			return false;
		}

		function is_user_editor_or_above()
		{
			return current_user_can('edit_others_posts') || current_user_can('manage_options');
		}
		
	} //class dbConn
}
