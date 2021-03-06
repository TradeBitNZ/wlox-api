<?php
class Requests{
	function get($count=false,$page=false,$per_page=false,$withdrawals=false) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$page = ($page > 0) ? $page - 1 : 0;
		$r1 = $page * $per_page;
		$type = ($withdrawals) ? $CFG->request_withdrawal_id : $CFG->request_deposit_id;
		
		if (!$count)
			$sql = "SELECT requests.*, request_descriptions.name_{$CFG->language} AS description, request_status.name_{$CFG->language} AS status, currencies.fa_symbol AS fa_symbol FROM requests LEFT JOIN request_descriptions ON (request_descriptions.id = requests.description) LEFT JOIN request_status ON (request_status.id = requests.request_status) LEFT JOIN currencies ON (requests.currency = currencies.id) WHERE 1 ";
		else
			$sql = "SELECT COUNT(requests.id) AS total FROM requests WHERE 1 ";
		
		$sql .= " AND requests.site_user = ".User::$info['id'];
		
		if ($type > 0)
			$sql .= " AND requests.request_type = $type ";
		
		if ($per_page > 0 && !$count)
			$sql .= " ORDER BY requests.date DESC LIMIT $r1,$per_page ";
		
		$result = db_query_array($sql);
		if (!$count)
			return $result;
		else
			return $result[0]['total'];
	}
	
	function insert($is_btc=false,$bank_account_currency=false,$amount=false,$btc_address=false,$account_number=false) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;

		if ($is_btc) {
			if (User::$info['confirm_withdrawal_2fa_btc'] == 'Y' && !$CFG->token_verified)
				return false;
			
			$status = (User::$info['confirm_withdrawal_email_btc'] == 'Y' && !$CFG->token_verified) ? $CFG->request_awaiting_id : $CFG->request_pending_id;
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$CFG->btc_currency_id,'amount'=>$amount,'description'=>$CFG->withdraw_btc_desc,'request_status'=>$status,'request_type'=>$CFG->request_withdrawal_id,'send_address'=>$btc_address));
			
			if (User::$info['confirm_withdrawal_email_btc'] == 'Y' && !$CFG->token_verified  && $request_id > 0) {
				$status = DB::getRecord('status',1,0,1);
				$pending_withdrawals = $status['pending_withdrawals'];
				db_update('status',1,array('pending_withdrawals'=>($status['pending_withdrawals'] + $amount)));
				
				$vars = User::$info;
				$vars['authcode'] = urlencode(Encryption::encrypt($request_id));
					
				$email = SiteEmail::getRecord('request-auth');
				Email::send($CFG->form_email,User::$info['email'],$email['title'],$CFG->form_email_from,false,$email['content'],$vars);
			}
			
			return $request_id;
		}
		else {
			if (User::$info['confirm_withdrawal_2fa_bank'] == 'Y' && !$CFG->token_verified)
				return false;
				
			$status = (User::$info['confirm_withdrawal_email_bank'] == 'Y' && !$CFG->token_verified) ? $CFG->request_awaiting_id : $CFG->request_pending_id;
			$request_id = db_insert('requests',array('date'=>date('Y-m-d H:i:s'),'site_user'=>User::$info['id'],'currency'=>$bank_account_currency,'amount'=>$amount,'description'=>$CFG->withdraw_fiat_desc,'request_status'=>$status,'request_type'=>$CFG->request_withdrawal_id,'account'=>$account_number));
		
			if (User::$info['confirm_withdrawal_email_bank'] == 'Y' && !$CFG->token_verified && $request_id > 0) {
				$vars = User::$info;
				$vars['authcode'] = urlencode(Encryption::encrypt($request_id));
			
				$email = SiteEmail::getRecord('request-auth');
				Email::send($CFG->form_email,User::$info['email'],$email['title'],$CFG->form_email_from,false,$email['content'],$vars);
			}
			return $request_id;
		}
	}
	
	function emailValidate($authcode) {
		global $CFG;
		
		if (!$CFG->session_active)
			return false;
		
		$request_id = Encryption::decrypt(urldecode($authcode));
		if ($request_id > 0) {
			return db_update('requests',$request_id,array('request_status'=>$CFG->request_pending_id));
		}
	}
}