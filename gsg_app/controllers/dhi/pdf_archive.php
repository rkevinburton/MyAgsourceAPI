<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use \myagsource\AccessLog;

class Pdf_archive extends MY_Controller {

    function __construct(){
        parent::__construct();
        $this->session->keep_all_flashdata();

        $herd_code = $this->session->userdata('herd_code');
        if(((!isset($this->as_ion_auth) || !$this->as_ion_auth->logged_in()) && $herd_code != $this->config->item('default_herd')) || empty($herd_code)){
            $this->load->view('session_expired', array('url'=>$this->session->flashdata('redirect_url')));
            exit;
        }

        /* Load the profile.php config file if it exists
        if (ENVIRONMENT == 'development' || ENVIRONMENT == 'localhost') {
            $this->config->load('profiler', false, true);
            if ($this->config->config['enable_profiler']) {
                $this->output->enable_profiler(TRUE);
            }
        } */
    }
    function index($pdf_id){
        $this->loadPdf($pdf_id);
    }

    function show($pdf_id){
        if(!$this->permissions->hasPermission('View All Content') && !$this->permissions->hasPermission('View Archived Reports')){
            $this->session->set_flashdata('message',  $this->session->flashdata('message') + ['You do not have permission to view archived reports.']);
            redirect('/');
        }

        $this->load->model('dhi/pdf_archive_model');
        try{
            $pdf_data = $this->pdf_archive_model->getPdfData($pdf_id, $this->session->userdata('herd_code')); //herd_code, test_date, report_code, report_name, file_path
        }
        catch(\Exception $e){
            $this->session->set_flashdata('message',  $this->session->flashdata('message') + [$e->getMessage()]);
            redirect('/');
        }
        $file = $this->config->item('pdf_path') . $pdf_data['herd_code'] . '/' . str_replace('-', '', $pdf_data['test_date']) . '/' . $pdf_data['filename'];
var_dump(get_current_user(), file_exists('/opt/data/agsource/all_pdf_reports'),file_exists($file), $file); die;

        if(!file_exists($file)){
            $this->session->set_flashdata('message',  $this->session->flashdata('message') + ['Could not find PDF file.']);
            redirect('/');
        }

        $filename = $pdf_data['herd_code'] . '_' . str_replace('-', '', $pdf_data['test_date']) . '_' . str_replace(' ', '-', $pdf_data['report_name']) . '.pdf';

        $this->_record_access(96);

        header('Content-type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');
        @readfile($file);
    }

    protected function _record_access($event_id){
        if($this->session->userdata('user_id') === FALSE){
            return FALSE;
        }
        $herd_code = $this->session->userdata('herd_code');
        $herd_enroll_status_id = empty($herd_code) ? NULL : $this->session->userdata('herd_enroll_status_id');
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
            $this->session->userdata('active_group_id'),
            null //no report code for cow lookup
        );
    }
}