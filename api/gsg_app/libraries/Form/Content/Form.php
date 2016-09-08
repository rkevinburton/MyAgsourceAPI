<?php
namespace myagsource\Form\Content;

/**
 * Form
 * 
 * Object representing individual form
 * 
 * Created by PhpStorm.
 * User: ctranel
 * Date: 6/20/2016
 * Time: 11:23 AM
 */

require_once APPPATH . 'libraries/Form/iForm.php';

use \myagsource\Form\iForm;

class Form implements iForm
{
    /**
     * id
     * @var int
     **/
    protected $id;

    /**
     * datasource
     * @var object
     **/
    protected $datasource;

    /**
     * form dom_id
     * @var string
     **/
    protected $dom_id;

    /**
     * form action
     * @var string
     **/
    protected $action;

    /**
     * array of control objects
     * @var FormControl[]
     **/
    protected $controls;

    public function __construct($id, $datasource, $controls, $dom_id, $action){
        $this->id = $id;
        $this->datasource = $datasource;
        $this->controls = $controls;
        $this->dom_id = $dom_id;
        $this->action = $action;
    }

    public function toArray(){
       $ret['dom_id'] = $this->dom_id;
        $ret['action'] = $this->action;

        if(isset($this->controls) && is_array($this->controls) && !empty($this->controls)){
            $controls = [];
            foreach($this->controls as $c){
                $controls[] = $c->toArray();
            }
            $ret['controls'] = $controls;
            unset($controls);
        }
        return $ret;
    }

/* -----------------------------------------------------------------
 *  parses form data according to data type conventions.

*  Parses form data according to data type conventions.

*  @since: version 1
*  @author: ctranel
*  @date: July 1, 2014
*  @param array of key-value pairs from form submission
*  @return void
*  @throws:
* -----------------------------------------------------------------
*/
    protected function parseFormData($form_data){
        $ret_val = [];
        if(!isset($form_data) || !is_array($form_data)){
            throw new \Exception('No form data found');
        }
        foreach($this->controls as $c){
            foreach($form_data as $k=>$v){
                if($c->name() === $k){
                    $ret_val[$k] = $c->parseFormData($v);
                }
            }
        }
        return $ret_val;
    }

    /* -----------------------------------------------------------------
*  write

*  write form to datasource

*  @author: ctranel
*  @date: Jul 1, 2014
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return void
*  @throws: * -----------------------------------------------------------------
*/
    public function write($form_data){
        if(!isset($form_data) || !is_array($form_data)){
            throw new \UnexpectedValueException('No form data received');
        }
        $form_data = $this->parseFormData($form_data);
        $this->datasource->upsert($this->id, $form_data);
    }


}