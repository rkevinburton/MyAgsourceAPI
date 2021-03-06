<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
 
class MY_Input extends CI_Input
{
    /**
     * json_request
     * @var stdClass object
     **/
    protected $json_request;

/*
    public function __construct(){
header("Content-type: application/json"); //being sent as json
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: -1");
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
parent::__construct();

    }
*/
    /**
     * Fetch full input array.  Data for all sources is cleaned when class is instantiated
     *
     * @access	public
     * @param	string	The index key
     * @param	bool	XSS cleaning
     * @return	string
     */
    function userInputArray() {
        if(count($_POST) > 0) {
            return $_POST;
        }
        elseif(count($this->json_request) > 0){
            return $this->json_request;
        }
        elseif(count($_GET) > 0) {
            return $_GET;
        }
    }

    // --------------------------------------------------------------------

    /**
     * Fetch an item from either the GET or POST globals, or the json_request property
     *
     * @access	public
     * @param	string	The index key
     * @param	bool	XSS cleaning
     * @return	string
     */
    function userInput($index = '', $xss_clean = FALSE) {
        if(isset($_POST[$index])) {
            return $this->post($index, $xss_clean);
        }
        elseif(isset($_GET[$index])) {
            return $this->get($index, $xss_clean);
        }
        elseif(isset($this->json_request[$index])){
            if($xss_clean){
                return $this->security->xss_clean($this->json_request[$index]);
            }
            return $this->json_request[$index];
        }
    }

    // --------------------------------------------------------------------

    /**
     * Sanitize Globals (Extends parent function to include JSON):
     *
     * This function does the following:
     *
     * Unsets $_GET data (if query strings are not enabled)
     *
     * Unsets all globals if register_globals is enabled
     *
     * Standardizes newline characters to \n
     *
     * @access	private
     * @return	void
     */
    function _sanitize_globals(){
        parent::_sanitize_globals();
        $request_headers = apache_request_headers();
        $request_headers = array_change_key_case($request_headers, CASE_LOWER);
        if(isset($request_headers['content-type']) && $request_headers['content-type'] === 'application/json'){
            $this->json_request = json_decode(file_get_contents('php://input'), true);
            // Clean $json_request Data
            $this->_sanitizeJsonData($this->json_request);
        }
    }

    /**
     * _sanitizeObjectData
     *
     *
     *
     * @param $input_obj
     */
    protected function _sanitizeJsonData(&$input_obj){
        if (is_array($input_obj)) {
            foreach ($input_obj as $key => $val) {
                if(is_array($val)){
                    $this->_sanitizeJsonData($val);
                    continue;
                }
                $input_obj[$this->_clean_input_keys($key)] = $this->_clean_input_data($val);
            }
        }
    }
}
