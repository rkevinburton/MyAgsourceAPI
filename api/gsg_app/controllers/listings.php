<?php
//namespace myagsource;
require_once(APPPATH . 'controllers/dpage.php');
require_once APPPATH . 'libraries/Site/WebContent/WebBlockFactory.php';
require_once APPPATH . 'libraries/Listings/Content/ListingFactory.php';
require_once(APPPATH . 'libraries/Supplemental/Content/SupplementalFactory.php');
require_once(APPPATH . 'libraries/Site/WebContent/Page.php');
require_once(APPPATH . 'libraries/dhi/HerdPageAccess.php');
require_once(APPPATH . 'libraries/Site/WebContent/PageAccess.php');

use \myagsource\Site\WebContent\WebBlockFactory;
use \myagsource\Listings\Content\ListingFactory;
use \myagsource\Supplemental\Content\SupplementalFactory;
use \myagsource\Site\WebContent\Page;
use \myagsource\dhi\HerdPageAccess;
use \myagsource\Site\WebContent\PageAccess;

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

class listings extends dpage {
	function __construct(){
		parent::__construct();

		/* Load the profile.php config file if it exists*/
		if (ENVIRONMENT == 'development' || ENVIRONMENT == 'localhost') {
			$this->config->load('profiler', false, true);
			if ($this->config->config['enable_profiler']) {
				$this->output->enable_profiler(TRUE);
			}
		}
	}
	
	function index($page_id, $json_filter_data = null){
        //filters
        $params = [];
        if(isset($json_filter_data)) {
            $params = (array)json_decode(urldecode($json_filter_data));
        }

        $supplemental_factory = $this->_supplementalFactory();

        //Set up site content objects
        $this->load->model('web_content/page_model', null, false, $this->session->userdata('user_id'));
        $this->load->model('web_content/block_model');
        $web_block_factory = new WebBlockFactory($this->block_model, $supplemental_factory);

        $serial_num = isset($params['serial_num']) ? $params['serial_num'] : null;

        //page content
        $this->load->model('ReportContent/report_block_model');

        $this->load->model('Listings/herd_options_model');
		$option_listing_factory = new ListingFactory($this->herd_options_model);

        //create block content
        $listings = $option_listing_factory->getByPage($page_id, ['herd_code'=>$this->herd->herdCode(), 'serial_num'=>$serial_num]);


        //create blocks for content
        $blocks = $web_block_factory->getBlocksFromContent($page_id, $listings);
        $this->load->model('web_content/page_model');
        $page_data = $this->page_model->getPage($page_id);
        $this->page = new Page($page_data, $blocks, $supplemental_factory, $this->filters, null);

        //does user have access to current page for selected herd?
        $this->herd_page_access = new HerdPageAccess($this->page_model, $this->herd, $this->page);
        $this->page_access = new PageAccess($this->page, ($this->permissions->hasPermission("View All Content") || $this->permissions->hasPermission("View All Content-Billed")));
        if(!$this->page_access->hasAccess($this->herd_page_access->hasAccess())) {
            $this->sendResponse(403, new ResponseMessage('You do not have permission to view the requested report for herd ' . $this->herd->herdCode() . '.  Please select a report from the navigation', 'error'));
        }
        //the user can access this page for this herd, but do they have to pay?
        if($this->permissions->hasPermission("View All Content-Billed")){
            $this->message[] = new ResponseMessage('Herd ' . $this->herd->herdCode() . ' is not paying for this product.  You will be billed a monthly fee for any month in which you view content for which the herd is not paying.', 'message');
        }

        $this->sendResponse(200, $this->message, $this->page->toArray());
	}

    function ev_seq($page_id, $protocol_id){
        //filters
        $params = [];
        if(isset($json_filter_data)) {
            $params = (array)json_decode(urldecode($json_filter_data));
        }

        $this->load->model('Listings/herd_options_model');
        $option_listing_factory = new ListingFactory($this->herd_options_model);

        //create block content
        $listings = $option_listing_factory->getByPage($page_id, ['herd_code'=>$this->herd->herdCode(), 'protocol_id' => $protocol_id]);

        //supplemental factory
        $this->load->model('supplemental_model');
        $supplemental_factory = new SupplementalFactory($this->supplemental_model, site_url());

        //Set up site content objects
        $this->load->model('web_content/page_model', null, false, $this->session->userdata('user_id'));
        $this->load->model('web_content/block_model');
        $web_block_factory = new WebBlockFactory($this->block_model, $supplemental_factory);

        //page content
        $this->load->model('ReportContent/report_block_model');

        //create blocks for content
        $blocks = $web_block_factory->getBlocksFromContent($page_id, $listings);

        $this->load->model('web_content/page_model');
        $page_data = $this->page_model->getPage($page_id);
        $this->page = new Page($page_data, $blocks, $supplemental_factory, $this->filters, null);

        //does user have access to current page for selected herd?
        $this->herd_page_access = new HerdPageAccess($this->page_model, $this->herd, $this->page);
        $this->page_access = new PageAccess($this->page, ($this->permissions->hasPermission("View All Content") || $this->permissions->hasPermission("View All Content-Billed")));
        if(!$this->page_access->hasAccess($this->herd_page_access->hasAccess())) {
            $this->sendResponse(403, new ResponseMessage('You do not have permission to view the requested report for herd ' . $this->herd->herdCode() . '.  Please select a report from the navigation', 'error'));
        }
        //the user can access this page for this herd, but do they have to pay?
        if($this->permissions->hasPermission("View All Content-Billed")){
            $this->message[] = new ResponseMessage('Herd ' . $this->herd->herdCode() . ' is not paying for this product.  You will be billed a monthly fee for any month in which you view content for which the herd is not paying.', 'message');
        }

        $this->sendResponse(200, $this->message, $this->page->toArray());

    }
}