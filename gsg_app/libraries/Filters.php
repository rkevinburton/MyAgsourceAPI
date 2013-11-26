<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
* Name:  Filters Library File
*
* Author: Chris Tranel
*		  Compiled and Expanded by Kevin Marshall
*

*
* Created:  20131118
*
* Description:  Library for filter handling
*
* Requirements: PHP5 or above
*
*/

class Filters{

	private $ci;
	private $sect_id;
	private $page;
	private $arr_params;
	private $criteria;
	private $primary_model;
	private $log_filter_text;
	private $report_path;
	
	public function __construct($lib_data){
		$this->ci =& get_instance();
		$this->ci->load->model('filter_model');
		$this->ci->load->library('session');
		$this->ci->load->library('form_validation');
		$this->ci->load->library('reports');
		$this->sect_id = $lib_data['section'];
		$this->page = $lib_data['page'];
		$this->arr_params = $lib_data['params'];		
		$this->criteria = $lib_data['criteria'];
		$this->primary_model = $lib_data['primary_model'];
		$this->log_filter_text = $lib_data['log_filter_text'];
		$this->report_path = $lib_data['report_path'];
		
	}

	public static function get_filter_array($json_array){
		//always have filters for pstring (and page?)
		$ci =& get_instance();
		$arr_params = (array)json_decode(urldecode($json_array));
		if(isset($arr_params['csrf_test_name']) && $arr_params['csrf_test_name'] != $ci->security->get_csrf_hash()) die("I don't recognize your browser session, your session may have expired, or you may have cookies turned off.");
		unset($arr_params['csrf_test_name']);
		return $arr_params;
	}

	public function set_filters(){
		$arr_page_filters = $this->ci->filter_model->get_page_filters($this->sect_id, $this->page);
		//always have filters for herd & pstring (and page?)
		if(array_key_exists('pstring', $arr_page_filters) === FALSE){ //all queries need to specify pstring
			$arr_page_filters['pstring'] = array('db_field_name' => 'pstring', 'name' => 'PString', 'type' => 'select multiple', 'default_value' => array(0));
			if(isset($this->arr_params['pstring']) === FALSE) $this->arr_params['pstring'] = array(0);
		}

		$this->criteria['herd_code'] = $this->ci->session->userdata('herd_code');
	
		//iterate through page filter options
		foreach($arr_page_filters as $k=>$f){ //key is the db field name
			//if range, create 2 fields, to and from.  Default value stored in DB as pipe-delimited
			if($f['type'] == 'range' || $f['type'] == 'date range'){
				if(!isset($f['default_value'])) $f['default_value'] = '|';
				list($this->criteria[$k . '_dbfrom'], $this->criteria[$k . '_dbto']) = explode('|', $f['default_value']);
			}
			elseif(!isset($this->criteria[$k])) $this->criteria[$k] = $f['default_value'];
			$arr_filters_list[] = $f['db_field_name'];
		}
		$this->arr_params = array_filter($this->arr_params, function($val){
			return ($val !== FALSE && $val !== NULL && $val !== '');
		});
		if (is_array($this->arr_params) && !empty($this->arr_params)) {
			foreach($arr_page_filters as $k=>$f){ //key is the db field name
	
				if($k == 'page') $this->criteria['page'] = $this->arr_pages[$this->$arr_params['page']]['name'];
				elseif($f['type'] == 'range' || $f['type'] == 'date range'){
					if(!isset($this->arr_params[$k . '_dbfrom']) || !isset($this->arr_params[$k . '_dbto'])) continue;
					$this->criteria[$k . '_dbfrom'] = $this->arr_params[$k . '_dbfrom'];
					$this->criteria[$k . '_dbto'] = $this->arr_params[$k . '_dbto'];
				}
				elseif($f['type'] == 'select multiple'){
					if(isset($this->arr_params[$k]) && is_array($this->arr_params[$k])){
						foreach($this->arr_params[$k] as $k1=>$v1){
							$this->arr_params[$k][$k1] = explode('|', $v1);
						}
						$this->arr_params[$k] = array_flatten($this->arr_params[$k]);
						$this->criteria[$k] = $this->arr_params[$k];
					}
					if(!$this->criteria[$k] && $k != 'pstring') {
						$this->criteria[$k] = array();
					}
					elseif(isset($this->arr_params[$k])) $this->criteria[$k] = $this->arr_params[$k];
				}
				else {
					if(!isset($this->arr_params[$k])) continue;
					$this->criteria[$k] = $this->arr_params[$k];
				}
			}
		}
		else { //if no form has been successfully submitted, set to defaults
			foreach($arr_page_filters as $f){
				if($f['db_field_name'] == 'pstring' && (!isset($f['default_value']) || empty($f['default_value']))){
					$this->criteria['pstring'] = $this->ci->pstring;
				}
				elseif($f['db_field_name'] == 'test_date' && (!isset($f['default_value']) || empty($f['default_value']))){
					$this->criteria['test_date'] = $this->ci->{$this->ci->primary_model}->get_recent_dates();
				}
				else $this->criteria[$f['db_field_name']] = $f['default_value'];
			}
		}
		if(validation_errors()) $this->primary_model->arr_messages[] = validation_errors();
		$arr_filter_text = $this->ci->reports->filters_to_text($this->criteria, $this->primary_model->arr_pstring);
		$this->log_filter_text = is_array($arr_filter_text) && !empty($arr_filter_text)?implode('; ', $arr_filter_text):'';
		
		//if params were passed to the function
		if(isset($arr_params) && is_array($arr_params)){
			foreach($arr_params as $k => $v){
				$this->criteria[$k] = $v;
			}
			foreach($arr_page_filters as $k=>$f){ //key is the db field name
				//if range, create 2 fields, to and from.  Default value stored in DB as pipe-delimited
				if($f['type'] == 'range' || $f['type'] == 'date range'){
					if(!isset($f['default_value'])) $f['default_value'] = '|';
				}
				$arr_filters_list[] = $f['db_field_name'];
				$this->ci->form_validation->set_rules($f['db_field_name'], $f['name']);
			}
		}
		else{
			//validate form input for filters
			foreach($arr_page_filters as $k=>$f){ //key is the db field name
				//if range, create 2 fields, to and from.  Default value stored in DB as pipe-delimited
				if($f['type'] == 'range' || $f['type'] == 'date range'){
					if(!isset($f['default_value'])) $f['default_value'] = '|';
					list($this->criteria[$k . '_dbfrom'], $this->criteria[$k . '_dbto']) = explode('|', $f['default_value']);
				}
				elseif(!isset($this->criteria[$k])) $this->criteria[$k] = $f['default_value'];
				$arr_filters_list[] = $f['db_field_name'];
				$this->ci->form_validation->set_rules($f['db_field_name'], $f['name']);
			}
			if ($this->ci->form_validation->run() == TRUE) { //successful submission
				foreach($arr_page_filters as $k=>$f){ //key is the db field name
					if($k == 'page') $this->criteria['page'] = $this->arr_pages[$this->input->post('page', TRUE)]['name'];
					elseif($f['type'] == 'range' || $f['type'] == 'date range'){
						$this->criteria[$k . '_dbfrom'] = $this->input->post($k . '_dbfrom', TRUE);
						$this->criteria[$k . '_dbto'] = $this->input->post($k . '_dbto', TRUE);
					}
					else $this->criteria[$k] = $this->input->post($k, TRUE);
					if($f['type'] == 'select multiple' && !$this->criteria[$k] && $k != 'pstring') {
						$this->criteria[$k] = array();
					}
				}
			}
			else { //if no form has been successfully submitted, set to defaults
				foreach($arr_page_filters as $f){
					if($f['db_field_name'] == 'pstring' && (!isset($f['default_value']) || empty($f['default_value']))){
						$this->criteria['pstring'] = $this->pstring;
					}
					elseif($f['db_field_name'] == 'test_date' && (!isset($f['default_value']) || empty($f['default_value']))){
						$this->criteria['test_date'] = $this->primary_model->get_recent_dates();
					}
					else $this->criteria[$f['db_field_name']] = $f['default_value'];
				}
			}
		}
		if(validation_errors()) $this->primary_model->arr_messages[] = validation_errors();
		$arr_filter_text = $this->ci->reports->filters_to_text($this->criteria, $this->primary_model->arr_pstring);
		$log_filter_text = is_array($arr_filter_text) && !empty($arr_filter_text)?implode('; ', $arr_filter_text):'';
		$filter_data = array(
				'arr_filters'=>isset($arr_filters_list) && is_array($arr_filters_list)?$arr_filters_list:array(),
				'filter_selected'=>$this->criteria,
				'report_path'=>$this->report_path,
				'arr_pstring'=>$this->primary_model->arr_pstring);

		return $filter_data;
	}
	
}