<?php
require_once(APPPATH . 'libraries/dhi/Herd.php');
require_once(APPPATH . 'libraries/dhi/HerdAccess.php');
require_once(APPPATH . 'core/MY_Api_Controller.php');
require_once(APPPATH . 'libraries/as_ion_auth.php');
require_once(APPPATH . 'libraries/Products/Products/Products.php');
require_once(APPPATH . 'libraries/Permissions/Permissions/ProgramPermissions.php');

use \myagsource\AccessLog;
use \myagsource\dhi\Herd;
use \myagsource\dhi\HerdAccess;
use \myagsource\As_ion_auth;
use \myagsource\Products\Products\Products;
use \myagsource\Permissions\Permissions\ProgramPermissions;


defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/ionauth.php';
require_once APPPATH . 'libraries/AccessLog.php';

class Auth extends MY_Api_Controller {
	function __construct()
	{
		$this->ionauth = new Ionauth();
		parent::__construct();
        //$this->load->library('as_ion_auth');
        //$this->load->library('session');
        //$this->load->helper('error');
        $this->load->library('form_validation');
        $this->load->model('dhi/herd_model');

        //instantiate in case noone is logged in
        if(!$this->session->userdata('user_id')) {
            $this->as_ion_auth = new As_ion_auth(null);
            return;
        }

        //instantiate as_ion_auth with permissions
        if($this->session->userdata('active_group_id')) {
            $herd = new Herd($this->herd_model, $this->session->userdata('herd_code'));

            $this->load->model('permissions_model');
            $this->load->model('product_model');
            $group_permissions = ProgramPermissions::getGroupPermissionsList($this->permissions_model, $this->session->userdata('active_group_id'));
            $products = new Products($this->product_model, $herd, $group_permissions);
            $this->permissions = new ProgramPermissions($this->permissions_model, $group_permissions, $products->allHerdProductCodes());
        }
        $this->as_ion_auth = new As_ion_auth($this->permissions);

		/* Load the profile.php config file if it exists
		if (ENVIRONMENT == 'development' || ENVIRONMENT == 'localhost') {
			$this->config->load('profiler', false, true);
			if ($this->config->config['enable_profiler']) {
				$this->output->enable_profiler(TRUE);
			} 
		}*/
	}

	function product_info_request(){
		$arr_inquiry = $this->input->post('products');
        $arr_user = $this->ion_auth_model->user($this->session->userdata('user_id'))->result_array()[0];

		if(isset($arr_inquiry) && is_array($arr_inquiry)){
			if($this->as_ion_auth->recordProductInquiry($arr_user['first_name'] . ' ' . $arr_user['last_name'], $arr_user['email'],$this->session->userdata('herd_code'), $arr_inquiry, $this->input->post('comments'))){
                $this->sendResponse(200, 'Thank you for your interest.  Your request for more information has been sent.');
			}
			else{
                $this->sendResponse(500, 'We encountered a problem sending your request.  Please try again or contact us at ' . $this->config->item("cust_serv_email") . ' or ' . $this->config->item("cust_serv_phone") . '.');
			}
		}
		else {
            $this->sendResponse(400, 'Please select one or more products and resubmit your request.');
		}
	}

	/*
	 * @description manage_service_grp is the page producers use to manage service group access
	 */
	function manage_service_grp(){
        if((!$this->as_ion_auth->logged_in())){
            $this->sendResponse(401);
        }
        //@todo: replace reference to group with permission-based condition
		if($this->session->userdata('active_group_id') != 2) {
            $this->sendResponse(403, 'Only producers can manage consultant access to their herd data.');
		}
		
		$this->form_validation->set_rules('modify', 'Herd Selection');

		if ($this->form_validation->run() == TRUE) {
			$action = $this->input->post('submit');
			$arr_modify_id = $this->input->post('modify');
			if(isset($arr_modify_id) && is_array($arr_modify_id)){
				//@todo: should have sep controller for consultant access, and sep path for each of these actions
                switch ($action) {
					case 'Remove Access':
						if($this->ion_auth_model->batch_herd_revoke($arr_modify_id)) {
							$this->_record_access(41);
                            $this->sendResponse(200, 'Consultant access adjusted successfully.');
						}
						else{
                            $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                        }
					break;
					case 'Grant Access':
						if($this->ion_auth_model->batch_grant_consult($arr_modify_id)) {
							$this->_record_access(34);
                            $this->sendResponse(200, 'Consultant access adjusted successfully.');
						}
						else{
                            $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                        }
					break;
					case 'Deny Access':
						if($this->ion_auth_model->batch_deny_consult($arr_modify_id)) {
							$this->_record_access(42);
                            $this->sendResponse(200, 'Consultant access adjusted successfully.');
						}
						else{
                            $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                        }
					break;
					case 'Remove Expiration Date':
						if($this->ion_auth_model->batch_remove_consult_expire($arr_modify_id)) {
							$this->_record_access(43);
                            $this->sendResponse(200, 'Consultant access adjusted successfully.');
						}
						else{
                            $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                        }
					break;
					default:
                        $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
					break;
				}
			}
		}
		
        $msg = compose_error(validation_errors(), $this->as_ion_auth->messages(), $this->as_ion_auth->errors());
        $this->sendResponse(400, $msg);
    }
	
	/*
	 * @description manage_service_grp is the page service groups use to manage herd access
	 */
	function service_grp_manage_herds(){
        if((!$this->as_ion_auth->logged_in())){
            $this->sendResponse(401);
        }
		if($this->permissions->hasPermission('View Assign w permission') !== TRUE) {
            $this->sendResponse(403, 'You do not have permission to view non-owned herds.');
		}

		$this->form_validation->set_rules('modify', 'Herd Selection');

        if($this->form_validation->run() == false){
            $this->sendResponse(400, validation_errors());
        }

        $action = $this->input->post('submit');
        $arr_modify_id = $this->input->post('modify');
        if(isset($arr_modify_id) && is_array($arr_modify_id)){
            //@todo: should have sep controller for consultant access, and sep path for each of these actions
            switch ($action) {
                case 'Remove Access':
                    if($this->ion_auth_model->batch_herd_revoke($arr_modify_id)) {
                        $this->_record_access(41);
                        $this->sendResponse(200, 'Consultant access adjusted successfully.');
                    }
                    else{
                        $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                    }
                    break;
                case 'Restore Access':
                    //if consultant had revoked access, they can restore it (call grant_access)
                    foreach($arr_modify_id as $k=>$id){
                        if($this->ion_auth_model->get_consult_status_text($id) != 'consult revoked'){
                            unset($arr_modify_id[$k]);
                        }
                    }
                    if(!empty($arr_modify_id) && $this->ion_auth_model->batch_grant_consult($arr_modify_id)) {
                        $this->_record_access(34);
                        $this->sendResponse(200, 'Consultant access adjusted successfully.');
                    }
                    else{
                        $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                    }
                break;
                case 'Resend Request Email':
                    foreach($arr_modify_id as $k=>$id){
                        $arr_relationship_data = $this->ion_auth_model->get_consult_relationship_by_id($id);
                        if ($this->as_ion_auth->send_consultant_request($arr_relationship_data, $id, $this->config->item('cust_serv_email'))) {
                            $this->_record_access(35);
                            $this->sendResponse(200, $this->as_ion_auth->messages());
                        }
                        else {
                            $this->sendResponse(400, $this->as_ion_auth->errors());
                        }
                    }
                break;
                default:
                    $this->sendResponse(500, 'Consultant access adjustment failed.  Please try again.');
                break;
            }
        }

        $this->sendResponse(400, $this->as_ion_auth->messages() + $this->as_ion_auth->errors());
    }
	
	//Producers only, give consultant permission to view herd
	function service_grp_access($cuid = NULL) {
        if((!$this->as_ion_auth->logged_in())){
            $this->sendResponse(401);
        }
        //@todo: replace reference to group with permission-based condition
		if($this->session->userdata('active_group_id') != 2) {
            $this->sendResponse(403, 'Only producers can manage consultant access to their herd data.');
		}
		
		//validate form input
		$this->form_validation->set_rules('section_id', 'Sections', '');
		$this->form_validation->set_rules('exp_date', 'Expiration Date', 'trim');
		$this->form_validation->set_rules('request_status_id', 'Request Status', '');
		$this->form_validation->set_rules('write_data', 'Enter Event Data', '');
		//$this->form_validation->set_rules('request_status_id', '', '');
		$this->form_validation->set_rules('disclaimer', 'Confirmation of Understanding', 'required');

        if ($this->form_validation->run() === false) {
            $this->sendResponse(400, validation_errors());
        }

        $arr_relationship_data = array(
            'sg_user_id' => (int)$this->input->post('sg_user_id'),
            'herd_code' => $this->session->userdata('herd_code'),
            'write_data' => (int)$this->input->post('write_data'),
            'active_date' => date('Y-m-d'),
            'active_user_id' => $this->session->userdata('user_id'),
        );
        $post_request_status_id = $this->input->post('request_status_id');
        if(isset($post_request_status_id) && !empty($post_request_status_id)){
            $arr_relationship_data['request_status_id'] = (int)$post_request_status_id;
        }
        $tmp = human_to_mysql($this->input->post('exp_date'));
        if(isset($tmp) && !empty($tmp)) {
            $arr_relationship_data['exp_date'] = $tmp;
        }
        elseif(isset($tmp) && empty($tmp)) {
            $arr_relationship_data['exp_date'] = null;
        }

        //convert submitted section id values to int
        $arr_post_section_id = $this->input->post('section_id');
        if(isset($arr_post_section_id) && is_array($arr_post_section_id)){
            array_walk($arr_post_section_id, function (&$value) { $value = (int)$value; });
        }

        if ($this->as_ion_auth->allow_service_grp($arr_relationship_data, $arr_post_section_id)) { //if permission is granted successfully
            $this->_record_access(34);
            $this->sendResponse(200, 'Permission is granted successfully');
        }

        $this->sendResponse(400, $this->as_ion_auth->messages() + $this->as_ion_auth->errors());
	}

	//Consultants only, request permission to view herd
	function service_grp_request() {
        if((!$this->as_ion_auth->logged_in())){
            $this->sendResponse(401);
        }
		if(!$this->permissions->hasPermission('View Assign w permission')) {
            $this->sendResponse(403, 'You do not have permission to request the data of a herd you do not own.');
		}

		//validate form input
		$this->form_validation->set_rules('herd_code', 'Herd Code', 'trim|required|exact_length[8]');
		$this->form_validation->set_rules('herd_release_code', 'Release Code', 'trim|required|exact_length[10]');
		$this->form_validation->set_rules('section_id', 'Sections', '');
		$this->form_validation->set_rules('exp_date', 'Expiration Date', 'trim');
		$this->form_validation->set_rules('write_data', 'Enter Event Data', '');
//		$this->form_validation->set_rules('request_status_id', '', '');
		$this->form_validation->set_rules('disclaimer', 'Confirmation of Understanding', 'required');

		$is_validated = $this->form_validation->run();
		if ($is_validated === TRUE) {
			$herd_code = $this->input->post('herd_code');
			$herd_release_code = $this->input->post('herd_release_code');
			$error = $this->herd_model->herd_authorization_error($herd_code, $herd_release_code);
			
			if($this->ion_auth_model->get_consult_relationship_id($this->session->userdata('user_id'), $herd_code) !== FALSE){
				$error = 'relationship_exists';
			}
			if($error){
				$this->as_ion_auth->set_error($error);
				$is_validated = false;
			}
			$arr_relationship_data = array(
				'herd_code' => $herd_code,
				'sg_user_id' => $this->session->userdata('user_id'),
				'service_grp_request' => 1, //bit - did a service group request
				'write_data' => (int)$this->input->post('write_data'),
				'request_status_id' => 7, //7 is the id for open request
				'active_date' => date('Y-m-d'),
				'active_user_id' => $this->session->userdata('user_id'),
			);
			$tmp = human_to_mysql($this->input->post('exp_date'));
			if(isset($tmp) && !empty($tmp)) $arr_relationship_data['exp_date'] = $tmp;

			//convert submitted section id values to int
/*			$arr_post_section_id = $this->input->post('section_id');
			array_walk($arr_post_section_id, function (&$value) { $value = (int)$value; });
*/			$arr_post_section_id = array();
			
			if ($is_validated === TRUE && $this->as_ion_auth->service_grp_request($arr_relationship_data, $arr_post_section_id, $this->config->item('cust_serv_email'))) {
				$this->_record_access(35);
				$msg = compose_error(validation_errors(), $this->as_ion_auth->messages(), $this->as_ion_auth->errors());
                $this->sendResponse(200, $msg); //  to manage access page
			}
			else { //if the request was un-successful
				$msg = compose_error(validation_errors(), $this->as_ion_auth->messages(), $this->as_ion_auth->errors());
                $this->sendResponse(500, $msg);
			}
		}
		else {
            //set the flash data error message if there is one
            $msg = compose_error(validation_errors(), $this->as_ion_auth->messages(), $this->as_ion_auth->errors());
            $this->sendResponse(400, $msg);
        }
	}

	function list_accounts(){
		if(!$this->permissions->hasPermission("Edit All Users") && !$this->permissions->hasPermission("Edit Users In Region")){
            $this->sendResponse(403, 'You do not have permission to edit user accounts.');
		}
		//list the users
		$this->data['users'] = $this->as_ion_auth->get_editable_users();
		$this->data['arr_group_lookup'] = $this->ion_auth_model->get_group_lookup();
	}

	function login() {
		$this->data['trial_days'] = $this->config->item('trial_period');

		//validate form input
		$this->form_validation->set_rules('identity', 'Email Address', 'trim|required|valid_email');
		$this->form_validation->set_rules('password', 'Password', 'trim|required');

		if ($this->form_validation->run() == TRUE){ //check to see if the user is logging in
			//check for "remember me"
			$remember = (bool) $this->input->post('remember');
			//Clear out herd code in case user was browsing demo herd before logging in.
			$this->session->unset_userdata('herd_code');
			$this->session->unset_userdata('arr_pstring');
			$this->session->unset_userdata('pstring');
			$this->session->unset_userdata('arr_tstring');
			$this->session->unset_userdata('tstring');
			//$this->session->sess_destroy();
			//$this->session->sess_create();
		
			if ($this->as_ion_auth->login($this->input->post('identity'), $this->input->post('password'), $remember)){ //if the login is successful
				$this->_record_access(1); //1 is the page code for login for the user management section
                //get permissions (also in constuctor, put in function/class somewhere)
                $this->load->model('permissions_model');
                $this->load->model('product_model');
                $herd = new Herd($this->herd_model, $this->session->userdata('herd_code'));
                $group_permissions = ProgramPermissions::getGroupPermissionsList($this->permissions_model, $this->session->userdata('active_group_id'));
                $products = new Products($this->product_model, $herd, $group_permissions);
                $this->permissions = new ProgramPermissions($this->permissions_model, $group_permissions, $products->allHerdProductCodes());

                //get herd list
                $tmp_arr = $this->herd_access->getAccessibleHerdOptions($this->session->userdata('user_id'), $this->permissions->permissionsList(), $this->session->userdata('arr_regions'));
                //@todo: handle if there is only 1 herd (or 0)
				if(count($tmp_arr) === 0){
                    $this->sendResponse(404);
                }

                //send response
                $this->sendResponse(200, 'Login Successful', ['herd_codes' => $tmp_arr]);
			}
			else{ //if the login was un-successful
                $this->sendResponse(401, $this->as_ion_auth->errors());
			}
		}
		else{
            $msg = compose_error(validation_errors(), $this->as_ion_auth->messages(), $this->as_ion_auth->errors());
            $this->sendResponse(400, $msg);
		}
	}

	//log the user out
	function logout(){
		//log the user out
		$this->as_ion_auth->logout();

        $this->sendResponse(200);
	}

	//change password
	function change_password(){
        $this->form_validation->set_rules('old', 'Old password', 'required');
        $this->form_validation->set_rules('new', 'New Password', 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[new_confirm]');
        $this->form_validation->set_rules('new_confirm', 'Confirm New Password', 'required');

        if (!$this->as_ion_auth->logged_in()){
            $this->sendResponse(401);
        }

        $user = $this->as_ion_auth->user()->row();

        if ($this->form_validation->run()) {
            $identity = $this->session->userdata($this->config->item('identity', 'ion_auth'));

            $change = $this->as_ion_auth->change_password($identity, $this->input->post('old'), $this->input->post('new'));

            if ($change) { //if the password was successfully changed
                $this->as_ion_auth->logout();
                $this->sendResponse(200, $this->as_ion_auth->messages());
            }
            else {
                $this->sendResponse(500, $this->as_ion_auth->errors());
            }
        }
        $this->sendResponse(400, validation_errors());
	}

	//forgot password
	function forgot_password(){
        $this->form_validation->set_rules('email', 'Email Address', 'required');
        if ($this->form_validation->run()){
            //run the forgotten password method to email an activation code to the user
            $forgotten = $this->as_ion_auth->forgotten_password($this->input->post('email'));

            if ($forgotten) { //if there were no errors
                $this->sendResponse(200, $this->as_ion_auth->messages());
            }
            else {
                $this->sendResponse(500, $this->as_ion_auth->errors());
            }
        }
        $this->sendResponse(400, validation_errors());
	}

	//reset password - final step for forgotten password
	public function reset_password($code = NULL)
    {
        if (!$code) {
            $this->sendResponse(400, 'Invalid or expired reset code.  Please restart process.');
        }

        $user = $this->as_ion_auth->forgotten_password_check($code);

        if ($user) {  //if the code is valid then display the password reset form

            $this->form_validation->set_rules('new', 'New Password',
                'required|min_length[' . $this->config->item('min_password_length',
                    'ion_auth') . ']|max_length[' . $this->config->item('max_password_length',
                    'ion_auth') . ']|matches[new_confirm]');
            $this->form_validation->set_rules('new_confirm', 'Confirm New Password', 'required');

            if ($this->form_validation->run()){
                // do we have a valid request?
                if ($this->_valid_csrf_nonce() === FALSE || $user->id != $this->input->post('user_id')) {
                    //something fishy might be up
                    $this->as_ion_auth->clear_forgotten_password_code($code);
                    $this->sendResponse(400);
                }
                else {
                    // finally change the password
                    $identity = $user->{$this->config->item('identity', 'ion_auth')};

                    $change = $this->as_ion_auth->reset_password($identity, $this->input->post('new'));

                    if ($change) { //if the password was successfully changed
                        $this->as_ion_auth->logout();
                        $this->sendResponse(200, $this->as_ion_auth->messages());
                    }
                    else {
                        $this->sendResponse(500, $this->ion_auth->errors(), ['reset_code' => $code]);
                    }
                }
            }
            $this->sendResponse(400, validation_errors());
        }
    }

    //activate the user
    function activate($id, $code=false)
    {
        if($code === false && !$this->ion_auth->is_admin()){
            $this->sendResponse(400, 'Invalid or expired reset code.  Please restart process.');
        }
        if ($this->ion_auth->is_admin()){
            $activation = $this->as_ion_auth->activate($id);
        }
        else {
            $activation = $this->as_ion_auth->activate($id, $code);
        }

        if ($activation) {
            $this->sendResponse(200, $this->ion_auth->messages());
        }
        else {
            $this->sendResponse(400, $this->ion_auth->errors());
        }
    }

    //deactivate the user
    function deactivate($id = NULL)
    {
        $id = $this->config->item('use_mongodb', 'ion_auth') ? (string) $id : (int) $id;

        $this->load->library('form_validation');
        $this->form_validation->set_rules('confirm', 'confirmation', 'required');
        $this->form_validation->set_rules('id', 'user ID', 'required|alpha_numeric');

        if ($this->form_validation->run()) {
            // do we really want to deactivate?
            if ($this->input->post('confirm') == 'yes') {
                // do we have a valid request?
                if ($this->_valid_csrf_nonce() === FALSE || $id != $this->input->post('id')) {
                    $this->sendResponse(400, 'User not specified.');
                }

                // do we have the right userlevel?
                if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                    if($this->ion_auth->deactivate($id)) {
                        $this->sendResponse(200);
                    }
                }
                $this->sendResponse(500);
            }
            $this->sendResponse(400, 'Invalid submission.');
        }
        $this->sendResponse(400, validation_errors());
    }

    function create_user(){
		//validate form input
		$this->form_validation->set_rules('first_name', 'First Name', 'trim|required');
		$this->form_validation->set_rules('last_name', 'Last Name', 'trim|required');
		$this->form_validation->set_rules('email', 'Email Address', 'trim|required|valid_email');
		$this->form_validation->set_rules('supervisor_acct_num', 'Field Technician Account Number', 'max_length[8]');
		$this->form_validation->set_rules('sg_acct_num', 'Service Group Account Number', 'max_length[8]');
		$this->form_validation->set_rules('assoc_acct_num[]', 'Association Account Number', 'max_length[8]');
		$this->form_validation->set_rules('phone1', 'First Part of Phone', 'exact_length[3]|required');
		$this->form_validation->set_rules('phone2', 'Second Part of Phone', 'exact_length[3]|required');
		$this->form_validation->set_rules('phone3', 'Third Part of Phone', 'exact_length[4]|required');
		$this->form_validation->set_rules('best_time', 'Best Time to Call', 'max_length[10]|required');
		$this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
		$this->form_validation->set_rules('password_confirm', 'Password Confirmation', 'trim|required');
		$this->form_validation->set_rules('group_id[]', 'Name of User Group');
		$this->form_validation->set_rules('terms', 'Terms of Use Acknowledgement', 'required|exact_length[1]');
		$this->form_validation->set_rules('herd_code', 'Herd Code', 'exact_length[8]');
		$this->form_validation->set_rules('herd_release_code', 'Release Code', 'trim|exact_length[10]');
		$this->form_validation->set_rules('section_id[]', 'Section');

       if($this->form_validation->run() === false){
            $this->sendResponse(400, validation_errors());
        }

        $arr_posted_group_id = $this->form_validation->set_value('group_id[]');
        if(!$this->as_ion_auth->group_assignable($arr_posted_group_id)){
            $this->sendResponse(403, 'You do not have permissions to create a user with the user group you selected.  Please try again, or contact ' . $this->config->item('cust_serv_company') . ' at ' . $this->config->item('cust_serv_email') . ' or ' . $this->config->item('cust_serv_phone') . '.');
        }

        //start with nothing
        $assoc_acct_num = NULL;
        $supervisor_acct_num = NULL;
        $sg_acct_num = NULL;
        $herd_code = NULL;
        $herd_release_code = NULL;

        //Set variables that depend on group(s) selected
        if(isset($this->permissions)){
            if($this->permissions->hasPermission("Add All Users") || $this->permissions->hasPermission("Add Users In Region")){
                $arr_posted_group_id = $this->input->post('group_id');
                if(!$this->as_ion_auth->group_assignable($arr_posted_group_id)){
                    $this->sendResponse(403, 'You do not have permissions to add a user with the user group you selected.  Please try again, or contact ' . $this->config->item('cust_serv_company') . ' at ' . $this->config->item('cust_serv_email') . ' or ' . $this->config->item('cust_serv_phone') . '.');
                }
                $assoc_acct_num = $this->input->post('assoc_acct_num');
                $supervisor_acct_num = $this->input->post('supervisor_acct_num');
                if(empty($assoc_acct_num)){
                    $assoc_acct_num = NULL;
                }
                if(empty($supervisor_acct_num)){
                    $supervisor_acct_num = NULL;
                }
            }
        }
        if(in_array(2, $arr_posted_group_id) || in_array(13, $arr_posted_group_id)){ //producers
            $herd_code = $this->input->post('herd_code') ? $this->input->post('herd_code') : NULL;
            $herd_release_code = $this->input->post('herd_release_code');
            $error = $this->herd_model->herd_authorization_error($herd_code, $herd_release_code);
            if($error){
                $this->sendResponse(403, $error);
            }
        }
        if(in_array(9, $arr_posted_group_id)){ //service groups
            $sg_acct_num = $this->input->post('sg_acct_num');
            if(!$this->as_ion_auth->service_grp_exists($sg_acct_num)){
                $this->sendResponse(400, 'The service group entered does not exist. Please try again, or contact ' . $this->config->item('cust_serv_company') . ' at ' . $this->config->item('cust_serv_email') . ' or ' . $this->config->item('cust_serv_phone') . '.');
            }
        }

        $username = substr(strtolower($this->input->post('first_name')) . ' ' . strtolower($this->input->post('last_name')),0,15);
        $email = $this->input->post('email');
        $password = $this->input->post('password');
        $additional_data = array('first_name' => $this->input->post('first_name'),
            'herd_code' => $herd_code,
            'last_name' => $this->input->post('last_name'),
            'supervisor_acct_num' => $supervisor_acct_num,
            'sg_acct_num' => $sg_acct_num,
            'assoc_acct_num' => $assoc_acct_num,
            'phone' => $this->input->post('phone1') . '-' . $this->input->post('phone2') . '-' . $this->input->post('phone3'),
            'best_time' => $this->input->post('best_time'),
            'group_id' => $arr_posted_group_id,
            'section_id' => $this->input->post('section_id')
        );
        if($additional_data['phone'] == '--') $additional_data['phone'] = '';

        try{
            $is_registered = $this->as_ion_auth->register($username, $password, $email, $additional_data, $arr_posted_group_id, 'AMYA-500');
            if ($is_registered === true) { //check to see if we are creating the user
                //$this->as_ion_auth->activate();
                $this->sendResponse(200, 'Your account has been created.  You will be receiving an email shortly that will confirm your registration and allow you to activate your account.');
            }
        }
        catch(Exception $e){
            //will eventually catch registration errors here, but for now they are written to as_ion_auth errors()
        }

        $this->sendResponse(500, 'Registration was not successful.');
	}

	function edit_user($user_id = FALSE) {
		if($user_id === FALSE){
			$user_id = $this->session->userdata('user_id');
		}
		//does the logged in user have permission to edit this user?
		if (!$this->as_ion_auth->logged_in()) {
            $this->sendResponse(401, 'Please log in and try again.');
        }
        if(!$this->as_ion_auth->is_editable_user($user_id, $this->session->userdata('user_id'))){
            $this->sendResponse(403, 'You do not have permission to edit the requested account.');
        }

        $this->data['title'] = "Edit Account";
		//validate form input
		$this->form_validation->set_rules('first_name', 'First Name', 'trim|required');
		$this->form_validation->set_rules('last_name', 'Last Name', 'trim|required');
		$this->form_validation->set_rules('email', 'Email Address', 'trim|required|valid_email');
		$this->form_validation->set_rules('supervisor_acct_num', 'Field Technician Number', 'exact_length[8]');
		$this->form_validation->set_rules('assoc_acct_num[]', 'Association/Region Account Number', 'exact_length[8]');
		$this->form_validation->set_rules('phone1', 'First Part of Phone', 'exact_length[3]|required');
		$this->form_validation->set_rules('phone2', 'Second Part of Phone', 'exact_length[3]|required');
		$this->form_validation->set_rules('phone3', 'Third Part of Phone', 'exact_length[4]|required');
		$this->form_validation->set_rules('best_time', 'Best Time to Call', 'max_length[10]|required');
		$this->form_validation->set_rules('password', 'Password', 'trim|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
		$this->form_validation->set_rules('password_confirm', 'Password Confirmation', 'trim');
		$this->form_validation->set_rules('group_id[]', 'Name of Account Group');
		//$this->form_validation->set_rules('herd_code', 'Herd Code', 'exact_length[8]');
		$this->form_validation->set_rules('section_id[]', 'Section');
		
		$email_in = $this->input->post('email');
		if(empty($email_in)){
            $this->sendResponse(400, 'Form data not found.');
        }

        if($this->form_validation->run() === false){
            $this->sendResponse(400, validation_errors());
        }

        //populate data fields for specific group choices
        //start with the minimum
        $user_id = $this->input->post('user_id');
        $arr_posted_group_id = FALSE;
        $assoc_acct_num = NULL;
        $supervisor_acct_num = NULL;

        //Set variables that depend on group(s) selected
        if($this->permissions->hasPermission("Edit All Users") || $this->permissions->hasPermission("Edit Users In Region")){
            $arr_posted_group_id = $this->input->post('group_id');
            if(!$this->as_ion_auth->group_assignable($arr_posted_group_id)){
                $this->sendResponse(403, 'You do not have permissions to edit a user with the user group you selected.');
            }
            $assoc_acct_num = $this->input->post('assoc_acct_num');
            $supervisor_acct_num = $this->input->post('supervisor_acct_num');
        }

        $obj_user = $this->ion_auth_model->user($user_id)->row();
        /*if($this->input->post('herd_code') && $this->input->post('herd_code') != $obj_user->herd_code){
            $herd_code = $this->input->post('herd_code') ? $this->input->post('herd_code') : NULL;
            $herd_release_code = $this->input->post('herd_release_code');
            $error = $this->herd_model->herd_authorization_error($herd_code, $herd_release_code);
            if($error){
                $this->as_ion_auth->set_error($error);
                $is_validated = false;
            }
        }*/

        //populate
        $username = substr(strtolower($this->input->post('first_name')) . ' ' . strtolower($this->input->post('last_name')),0,15);
        $email = $this->input->post('email');
        $data = array('username' => $username,
            'email' => $email,
            'first_name' => $this->input->post('first_name'),
            'last_name' => $this->input->post('last_name'),
            'phone' => $this->input->post('phone1') . '-' . $this->input->post('phone2') . '-' . $this->input->post('phone3'),
            'best_time' => $this->input->post('best_time'),
            'group_id' => $arr_posted_group_id,
            'supervisor_acct_num' => $supervisor_acct_num,
            'assoc_acct_num' => $assoc_acct_num,
            'herd_code' => $this->input->post('herd_code') ? $this->input->post('herd_code') : NULL
        );
        if($data['phone'] == '--') $data['phone'] = '';
        if(isset($_POST['section_id'])) $data['section_id'] = $this->input->post('section_id');
        $password = $this->input->post('password');
        if(!empty($password)) $data['password'] = $password;

		$arr_curr_group_ids = array_keys($this->session->userdata('arr_groups'));
		if ($this->ion_auth_model->update($user_id, $data, $this->session->userdata('active_group_id'), $arr_curr_group_ids)) { //check to see if we are creating the user
            $this->sendResponse(200, "Account Edited");
		}

        $this->sendResponse(400, $this->as_ion_auth->messages() + $this->as_ion_auth->errors());
	}
		
	function ajax_techs($assoc_acct_num){
		$arr_tech_obj = $this->ion_auth_model->get_dhi_supervisor_acct_nums_by_association($assoc_acct_num);
		$supervisor_acct_num_options = $this->as_ion_auth->get_dhi_supervisor_dropdown_data($arr_tech_obj);
        $this->sendResponse(200, null, $supervisor_acct_num_options);
	}
	
	function ajax_terms(){
		$text = $this->load->view('auth/terms', array(), true);
        $this->sendResponse(200, null, $text);
	}
	
	function set_role($group_id){
		if(array_key_exists($group_id, $this->session->userdata('arr_groups'))){
			$this->session->set_userdata('active_group_id', (int)$group_id);
            $this->sendResponse(200, 'Active group has been set');
		}
		else {
            $this->sendResponse(403, 'You do not have rights to the requested group.');
		}
        $this->sendResponse(500, 'Request was unsuccessful.');
	}
	
	protected function _record_access($event_id){
		if($this->session->userdata('user_id') === FALSE){
			return FALSE;
		}
		$herd_code = $this->session->userdata('herd_code');
		$herd_enroll_status_id = $this->session->userdata('herd_enroll_status_id');
        if(!$herd_enroll_status_id){
            $herd_enroll_status_id = null;
        }
		$recent_test = $this->session->userdata('recent_test_date');
		$recent_test = empty($recent_test) ? NULL : $recent_test;

		$this->load->model('access_log_model');
		$access_log = new AccessLog($this->access_log_model);
				
		$access_log->write_entry(
			$this->as_ion_auth->is_admin(),
			$event_id,
			$herd_code,
			$recent_test,
			$herd_enroll_status_id,
			$this->session->userdata('user_id'),
			$this->session->userdata('active_group_id')
		);
	}
}
