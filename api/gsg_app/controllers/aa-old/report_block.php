<?php
//namespace myagsource;
require_once(APPPATH . 'libraries/Filters/ReportFilters.php');
require_once(APPPATH . 'libraries/Benchmarks/Benchmarks.php');
require_once(APPPATH . 'libraries/Supplemental/Content/SupplementalFactory.php');
require_once(APPPATH . 'libraries/dhi/HerdAccess.php');
require_once(APPPATH . 'libraries/dhi/Herd.php');
require_once(APPPATH . 'libraries/Page/Content/Blocks.php');
require_once(APPPATH . 'libraries/Site/WebContent/WebBlockFactory.php');
require_once(APPPATH . 'libraries/Site/WebContent/PageFactory.php');
require_once(APPPATH . 'libraries/Site/WebContent/PageAccess.php');
require_once(APPPATH . 'libraries/dhi/HerdPageAccess.php');
require_once(APPPATH . 'libraries/Site/WebContent/SectionFactory.php');
require_once(APPPATH . 'libraries/Datasource/DbObjects/DbTable.php');
//require_once(APPPATH . 'libraries/Page/Content/Chart/ChartData.php');
require_once(APPPATH . 'libraries/Page/Content/SortBuilder.php');
require_once(APPPATH . 'libraries/DataHandler.php');
require_once(APPPATH . 'libraries/Page/Content/Table/TableData.php');
require_once(APPPATH . 'libraries/Page/Content/Table/Header/TableHeader.php');

use \myagsource\Benchmarks\Benchmarks;
use \myagsource\Filters\ReportFilters;
use \myagsource\Supplemental\Content\SupplementalFactory;
use \myagsource\dhi\HerdAccess;
use \myagsource\dhi\HerdPageAccess;
use \myagsource\Site\WebContent\PageAccess;
use \myagsource\dhi\Herd;
use \myagsource\Site\WebContent\SectionFactory;
use \myagsource\Site\WebContent\PageFactory;
use \myagsource\Site\WebContent\WebBlockFactory;
use \myagsource\Report\Content\Table\Header\TableHeader;
use \myagsource\Datasource\DbObjects\DbTable;
use \myagsource\Report\Content\SortBuilder;
use \myagsource\DataHandler;
use \myagsource\Report\Content\Table\TableData;

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/* -----------------------------------------------------------------
 *	CLASS comments
 *  @file: report_parent.php
 *  @author: ctranel
 *
 *  @description: Parent abstract class that drives report page generation.  All database driven report pages 
 *  	extend this class.
 *
 * -----------------------------------------------------------------
 */

class report_block extends MY_Controller {
	/**
	 * herd_access
	 * @var HerdAccess
	 **/
	protected $herd_access;
	
	/**
	 * herd_page_access
	 * @var HerdPageAccess
	 **/
	protected $herd_page_access;
	
	/**
	 * section_factory
	 * @var SectionFactory
	 **/
	protected $section_factory;
	
	/**
	 * section
	 * @var Section
	 **/
	protected $section;
	
	/**
	 * page_factory
	 * 
	 * page repository
	 * @var PageFactory
	 **/
	protected $page_factory;
	
	/**
	 * page
	 * @var Page
	 **/
	protected $page;
	
	/**
	 * blocks
	 * 
	 * Block repository
	 * @var blocks
	 **/
	protected $blocks;

    /**
     * web_block_factory
     *
     * Block factory
     * @var WebBlockFactory
     **/
    protected $web_block_factory;

    /**
	 * supp_factory
	 * 
	 * Supplemental factory
	 * @var \myagsource\Supplemental\Content\SupplementalFactory
	 **/
	protected $supp_factory;
	
	/**
	 * herd
	 * 
	 * Herd object
	 * @var Herd
	 **/
	protected $herd;
	
	/**
	 * product_name
	 * 
	 * @var String
	 **/
	protected $product_name;

	/**
	 * section_path
	 * 
	 * The path to the site section; set in constructor to point to the controller name
	 * 
	 * @var String
	 **/
	protected $section_path;

	/**
	 * report_data
	 * 
	 * @var Array
	 **/
	protected $report_data;

	/**
	 * filters
	 * 
	 * Filters object
	 * @var Filters
	 **/
	protected $filters;

	/**
	 * supplemental
	 * 
	 * Supplemental
	 * @var Supplemental
	 **/
	protected $supplemental;

	function __construct(){
		parent::__construct();
		//set up herd
		$this->load->model('herd_model');
		$this->herd_access = new HerdAccess($this->herd_model);
		$this->herd = new Herd($this->herd_model, $this->session->userdata('herd_code'));

		//is someone logged in?
		if(!$this->as_ion_auth->logged_in() && $this->herd->herdCode() != $this->config->item('default_herd')) {
			$this->post_message("Please log in.  ");
		}
		
		//is a herd selected?
		if(!$this->herd->herdCode() || $this->herd->herdCode() == ''){
			$this->post_message("Please select a herd and try again.  ");
		}
		
		//does logged in user have access to selected herd?
		$has_herd_access = $this->herd_access->hasAccess($this->session->userdata('user_id'), $this->herd->herdCode(), $this->session->userdata('arr_regions'), $this->permissions->permissionsList());
		if(!$has_herd_access){
			$this->post_message("You do not have permission to access this herd.  Please select another herd and try again.  ");
		}


		//set up web content objects
		$this->load->model('web_content/section_model');
		$this->load->model('web_content/page_model', null, false, $this->session->userdata('user_id'));
		$this->load->model('web_content/block_model', 'WebBlockModel');
		$this->web_block_factory = new WebBlockFactory($this->WebBlockModel);
		$this->page_factory = new PageFactory($this->page_model, $this->web_block_factory);
		$this->section_factory = new SectionFactory($this->section_model, $this->page_factory);

		// report content
		$this->load->model('supplemental_model');
		$this->load->model('ReportContent/report_block_model');
		$this->load->model('Datasource/db_field_model');
		$this->supp_factory = new SupplementalFactory($this->supplemental_model, site_url());

		/* Load the profile.php config file if it exists
		if (ENVIRONMENT == 'development' || ENVIRONMENT == 'localhost') {
			$this->config->load('profiler', false, true);
			if ($this->config->config['enable_profiler']) {
				$this->output->enable_profiler(TRUE);
			} 
		}*/
	}

	//@todo: needs to be a part of some kind of authorization class
	protected function post_message($message = ''){
		$this->session->keep_all_flashdata();
		$this->load->view('echo.php', ['text' => $message]);
//		exit;
	}

	/*
	 * ajax_report: Called via AJAX to populate graphs
	 * @param string page path
	 * @param string block name
	 * @param string sort by
	 * @param string sort order
	 * @param int report count
	 * @param string serialized filter data
	 * @param string cache_buster: text to make page appear as a different page so that new data is retrieved
	 * @todo: can I delete the last param?
	 */
	public function ajax_report($page_path, $block_name, $sort_by = 'null', $sort_order = 'null', $report_count=0, $json_filter_data = NULL, $cache_buster = NULL) {//, $herd_size_code = FALSE, $all_breeds_code = FALSE
		$this->session->keep_all_flashdata();
		
		$page_path = str_replace('|', '/', trim(urldecode($page_path), '|'));
		$path_parts = explode('/', $page_path);
		$num_parts = count($path_parts);
		$path_page_segment = $path_parts[$num_parts - 1];
		
		//load section
		$this->section_path = isset($path_parts[$num_parts - 2]) ? $path_parts[$num_parts - 2] . '/' : '/';
		$this->section = $this->section_factory->getByPath($this->section_path);
		
		//is container page viewable to this user?
		//does user have access to current page for selected herd?
		$this->page = $this->page_factory->getByPath($path_page_segment, $this->section->id());
		$this->herd_page_access = new HerdPageAccess($this->page_model, $this->herd, $this->page);
		$this->page_access = new PageAccess($this->page, $this->permissions->hasPermission("View All Content"));
		if(!$this->page_access->hasAccess($this->herd_page_access->hasAccess())) {
			$this->post_message('You do not have permission to view the requested report for herd ' . $this->herd->herdCode() . '.  Please select a report from the navigation or contact ' . $this->config->item('cust_serv_company') . ' at ' . $this->config->item('cust_serv_email') . ' or ' . $this->config->item('cust_serv_phone') . ' if you have questions or concerns.');
			return;
		}

        //FILTERS
        if(isset($json_filter_data)){
            $section = $this->section;
            $arr_params = (array)json_decode(urldecode($json_filter_data));
            /* @todo: backend csrf was blocking CORS, so we need to turn it off for development
            if(isset($arr_params['csrf_test_name']) && $arr_params['csrf_test_name'] != $this->security->get_csrf_hash()){
            die("I don't recognize your browser session, your session may have expired, or you may have cookies turned off.");
            } */
            unset($arr_params['csrf_test_name']);

            //prep data for filter library
            $this->load->model('filter_model');
            $filters = new ReportFilters($this->filter_model, $this->page->id(), ['herd_code' => $this->session->userdata('herd_code')] + $arr_params);
        }
        //END FILTERS

        $this->blocks = new Blocks($this->report_block_model, $this->db_field_model, $filters, $this->supp_factory, $this->web_block_factory);

        $block = $this->blocks->getByPath(urldecode($block_name));
		$output = $block->displayType();
		
		//SORT
		$sort_builder = new SortBuilder($this->report_block_model);
		$sort_builder->build($block, $sort_by, $sort_order);
		//END SORT

		// block-level supplemental data  NOW DONE IN BLOCK OBJECT
		//$block_supp = $this->supp_factory->getBlockSupplemental($block->id());
		//$this->supplemental = $block_supp->getContent();
		//end supplemental

		// benchmarks
		$this->load->model('Forms/setting_form_model', null, false, ['user_id'=>$this->session->userdata('user_id'), 'herd_code'=>$this->session->userdata('herd_code')]);
		$herd_info = $this->herd_model->header_info($this->herd->herdCode());
		$this->load->model('Settings/benchmark_model');
		$this->benchmarks = new Benchmarks($this->session->userdata('user_id'), $this->input->post('herd_code'), $herd_info, $this->setting_form_model, $this->benchmark_model, $this->session->userdata('benchmarks'));
		// end benchmarks
			
		// report data
		$this->load->model('ReportContent/report_data_model');
		$this->load->model('Datasource/db_table_model');
		$db_table = new DbTable($block->primaryTableName (), $this->db_table_model);

		// Load the most specific data-handling library that exists
		$tmp_path = 'libraries/' . $page_path . '/' . $block_name;
		$data_handler = new DataHandler($this->report_data_model, $this->benchmarks);
		$block_data_handler = $data_handler->load($block, $tmp_path);
		//End load data-handling library

		$results = $block_data_handler->getData();//$report_count, 
		// end report data
		
		//Handle table headers for table blocks
		if($block->displayType() == 'table'){
			//table header
			$header_groups = $this->report_block_model->getHeaderGroups($block->id());
			
			//@todo: pull this only when needed? move adjustHeaderGroups to TableBlock or TableHeader class
			$arr_dates = $this->herd_model->get_test_dates_7_short($this->session->userdata('herd_code'));
			$header_groups = TableHeader::mergeDateIntoHeader($header_groups, $arr_dates);
			
			$block->setTableHeader($results, $this->supp_factory, $header_groups);
			unset($supp_factory);
		}
		$this->report_data = $block->getOutputData();
		$this->report_data['herd_code'] = $this->session->userdata('herd_code');
		if($block->hasBenchmark()){
			$this->report_data['benchmark_text'] = $this->benchmarks->get_bench_text();
		}

		$this->report_data['data'] = $results;
		
		if(isset($this->supplemental) && !empty($this->supplemental)){
			$this->report_data['supplemental'] = $this->supplemental;
		}

		if($block->displayType() == 'table'){
			$this->report_data['table_header'] = $this->load->view('table_header', $block->getTableHeaderData($report_count), TRUE);
			//finish table
			/*
			 * @todo: when we have a javascript framework in place, we will send table data via json too.
			 * for now, we need to send the html for the table instead of the data
			 */
				$this->report_data['html'] = $this->load->view('report_table.php', $this->report_data, TRUE);
				unset($this->report_data['data'],$this->report_data['table_header']);
		}

		//@todo: base header on accept property of request header 
		$return_val = json_encode($this->report_data);//, JSON_HEX_QUOT | JSON_HEX_TAG); //json_encode_jsfunc
		header("Content-type: application/json"); //being sent as json
		header("Cache-Control: no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header("Expires: -1");
		$this->load->view('echo.php', ['text' => $return_val]);
	}
}
