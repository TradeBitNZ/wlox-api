<?php
class BitcoinAddresses{
	function get($count=false,$page=false,$per_page=false,$user=false,$unassigned=false,$system=false) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$page = mysql_real_escape_string($page);
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;
		$user = User::$info['id'];
		
		if (!$count)
			$sql = "SELECT * FROM bitcoin_addresses WHERE 1 ";
		else
			$sql = "SELECT COUNT(id) AS total FROM bitcoin_addresses WHERE 1  ";
		
		if ($user > 0)
			$sql .= " AND site_user = $user ";
		
		if ($unassigned)
			$sql .= " AND site_user = 0 ";
		
		if ($system)
			$sql .= " AND system_address = 'Y' ";
		else
			$sql .= " AND system_address != 'Y' ";
		
		if ($per_page > 0 && !$count)
			$sql .= " ORDER BY bitcoin_addresses.date DESC LIMIT $r1,$per_page ";
		
		$result = db_query_array($sql);
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	function getNew() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		require_once('../lib/easybitcoin.php');
		$bitcoin = new Bitcoin($CFG->bitcoin_username,$CFG->bitcoin_passphrase,$CFG->bitcoin_host,$CFG->bitcoin_port,$CFG->bitcoin_protocol);
		$new_address = $bitcoin->getnewaddress($CFG->bitcoin_accountname);
		$new_id = db_insert('bitcoin_addresses',array('address'=>$new_address,'site_user'=>User::$info['id'],'date'=>date('Y-m-d H:i:s')));
		
		return $new_id;
	}
	
	function getAddress($address) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = "SELECT id, site_user,`date` FROM bitcoin_addresses WHERE address = '$address' ";
		$result = db_query_array($sql);
		return $result[0];
	}
	
	function getBalance() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = "SELECT SUM(balance) AS balance FROM bitcoin_addresses WHERE confirmed = 'Y' ";
		$result = db_query_array($sql);
		return $result[0]['balance'];
	}
	
	function getBitcoindBalance($bitcoin) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$accounts = $bitcoin->listaccounts(3);
		$total = 0;
		if (is_array($accounts)) {
			foreach ($accounts as $account) {
				$total += $account;
			}
		}
		return $total;
	}
	
	function cheapsweep($bitcoin,$destination) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		if (!$destination || !$bitcoin)
			return false;
		
		$addresses1 = $bitcoin->listaddressgroupings();
		if ($addresses1) {
			foreach ($addresses1 as $address1) {
				if (is_array($address1)) {
					foreach ($address1 as $address2) {
						if (!($address2[1] > 0) || $address2[0] == $destination)
							continue;
							
						$addresses[] = $address2[0];
					}
				}
			}
		}
		
		if ($addresses) {
			$address_str = implode(' ', $addresses);
			$response = shell_exec('cd '.$CFG->bitcoin_directory.' && ./cheapsweap -d '.$destination.' '.$address_str);
			return $response;
		}
	}
	
	function getHotWallet() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = "SELECT * FROM bitcoin_addresses WHERE system_address = 'Y' AND hot_wallet = 'Y' ORDER BY `date` ASC LIMIT 0,1";
		$result = db_query_array($sql);
		return $result[0];
	}
	
	function getWarmWallet() {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$sql = "SELECT * FROM bitcoin_addresses WHERE system_address = 'Y' AND warm_wallet = 'Y' LIMIT 0,1";
		$result = db_query_array($sql);
		return $result[0];
	}
	
	function validateAddress($btc_address) {
		global $CFG;
	
		require_once('../lib/easybitcoin.php');
		$bitcoin = new Bitcoin($CFG->bitcoin_username,$CFG->bitcoin_passphrase,$CFG->bitcoin_host,$CFG->bitcoin_port,$CFG->bitcoin_protocol);
		$response = $bitcoin->validateaddress($btc_address);
	
		if (!$response['isvalid'] || !is_array($response))
			return false;
		else
			return true;
	}
}