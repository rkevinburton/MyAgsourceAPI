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
     * name
     * @var string
     **/
    protected $name;

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
     * form initial_defaults_url
     * @var string
     **/
    protected $initial_defaults_url;

    /**
     * form validators
     * @var array of FormValidator objects
     **/
    protected $form_validators;

    /**
     * array of control groups
     * @var FormControlGroup[]
     **/
    protected $control_groups;

    public function __construct($id, $datasource, $control_groups, $name, $dom_id, $action, $initial_defaults_url, $form_validators){
        $this->id = $id;
        $this->datasource = $datasource;
        $this->control_groups = $control_groups;
        $this->name = $name;
        $this->dom_id = $dom_id;
        $this->action = $action;
        $this->initial_defaults_url = $initial_defaults_url;
        $this->form_validators = $form_validators;
    }

    public function action(){
        return $this->action;
    }

    protected function controls(){
        if(!isset($this->control_groups) || !is_array($this->control_groups)){
            return [];
        }

        $ret_controls = [];
        foreach($this->control_groups as $cg){
            $ret_controls = array_merge($ret_controls, $cg->controls());
        }
        return $ret_controls;
    }

    public function toArray(){
        $ret['dom_id'] = $this->dom_id;
        $ret['action'] = $this->action;
        $ret['initial_defaults_url'] = $this->initial_defaults_url;
        $ret['name'] = $this->name;
        $ret['form_id'] = $this->id;

        if(isset($this->control_groups) && is_array($this->control_groups)){
            $ret_cg = [];
            foreach($this->control_groups as $cg){
                $ret_cg[] = $cg->toArray();
            }
            $ret['control_groups'] = $ret_cg;
            unset($ret_cg);
        }

        if(isset($this->form_validators) && is_array($this->form_validators)){
            $ret_v = [];
            foreach($this->form_validators as $v){
                $ret_v[] = $v->toArray();
            }
            $ret['form_validators'] = $ret_v;
            unset($ret_v);
        }

        return $ret;
    }



    /* -----------------------------------------------------------------
*  controlsMetaArray

*  returns field-name-keyed array with meta data for controls

*  @author: ctranel
*  @date: Jul 1, 2014
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return key->value array of control meta data
*  @throws: * -----------------------------------------------------------------
*/
    public function controlsMetaArray(){
        $ret_controls = [];

        $controls = $this->controls();

        if(isset($controls) && is_array($controls) && !empty($controls)){
            foreach($controls as $c){
                $ret_controls[$c->name()] = $c->toArray();
                $subform_controls = $c->subformControlsMetaArray();
                if(isset($subform_controls)){
                    $ret_controls = $ret_controls + $subform_controls;
                }
            }
        }
        return $ret_controls;
    }

    /* -----------------------------------------------------------------
*  keyMetaArray

*  returns field-name-keyed array with meta data for keys

*  @author: ctranel
*  @date: Jul 1, 2014
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return key->value array of keys meta data
*  @throws: * -----------------------------------------------------------------
*/
    public function keyMetaArray(){
        $keys = [];

        $controls = $this->controls();

        if(isset($controls) && is_array($controls) && !empty($controls)){
            foreach($controls as $c){
                if($c->isKey()){
                    $keys[$c->name()] = $c->toArray();
                }
                $subform_keys = $c->subformKeyMetaArray();
                if(isset($subform_keys)){
                    $keys = $keys + $subform_keys;
                }
            }
        }
        return $keys;
    }

    /* -----------------------------------------------------------------
*  write

*  write form to datasource

*  @author: ctranel
*  @date: Jul 1, 2014
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return key->value array of keys for the record
*  @throws: * -----------------------------------------------------------------
*/
    public function write($form_data){
        if(!isset($form_data) || !is_array($form_data)){
            throw new \UnexpectedValueException('No form data received');
        }

        $is_update = (bool)isset($form_data['is_edited']) ? $form_data['is_edited'] : false;
        $form_data = $this->parseFormData($form_data);

        if($is_update){
            $key_vals = $this->datasource->update($this->id, $form_data, $this->controlsMetaArray(), $this->keyMetaArray());
        }
        else {
            $key_vals = $this->datasource->insert($this->id, $form_data, $this->controlsMetaArray());
            $this->insertDefaultListingRecords($key_vals, $form_data);
        }
        return $key_vals;
    }

    /* -----------------------------------------------------------------
*  writeBatch

*  write batch form to datasource

*  @author: ctranel
*  @date: 2016-12-22
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return void
*  @throws: UnexpectedValueException
     * * -----------------------------------------------------------------
*/
    public function writeBatch($form_data){
        if(!isset($form_data) || !is_array($form_data)){
            throw new \UnexpectedValueException('No form data received');
        }

        $variable_field = $this->batchVariableControl();
        if(!isset($variable_field)){
            throw new \UnexpectedValueException('Variable field not set on batch form submission.');
        }

        $is_update = (bool)isset($form_data['is_edited']) ? $form_data['is_edited'] : false;
        $form_data = $this->parseFormData($form_data);

        if($is_update){
            $key_vals = $this->datasource->batchUpdate($this->id, $variable_field->name(), $form_data, $this->controlsMetaArray());
        }
        else {
            $key_vals = $this->datasource->batchInsert($this->id, $variable_field->name(), $form_data, $this->controlsMetaArray());
            $this->insertDefaultListingRecords($key_vals, $form_data);
        }
        return $key_vals;
    }

    /* -----------------------------------------------------------------
*  insertDefaultListingRecords

*  returns the field

*  @author: ctranel
*  @date: 2017-02-23
*  @param: array of key=>value pairs (values can be array of values) of identity columns from inserted
*  @return: void
*  @throws: * -----------------------------------------------------------------
*/
    protected function insertDefaultListingRecords($parent_key_vals, $form_data){
        $controls = $this->controls();

        foreach($controls as $c){
            $c->insertDefaultListingRecords($parent_key_vals, $form_data, $this->batchVariableControl());
        }
    }

    /* -----------------------------------------------------------------
*  batchVariableControl

*  returns the field

*  @author: ctranel
*  @date: 2016-12-22
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return FormControl object
*  @throws: * -----------------------------------------------------------------
*/
    public function batchVariableControl() {
        $controls = $this->controls();

        foreach($controls as $c){
            if($c->batchVariableType() !== null){
                //each form can only have one
                return $c;
            }

            $sbvc = $c->subformBatchVariableControl();
            if(isset($sbvc)){
                return $sbvc;
            }
        }
        return null;
    }

    /* -----------------------------------------------------------------
*  delete

*  delete record from database

*  @author: ctranel
*  @date: 2016-11-30
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return void
*  @throws:
* -----------------------------------------------------------------
*/
    public function delete($key_data){
        if(!isset($key_data) || !is_array($key_data)){
            throw new \UnexpectedValueException('No form data received');
        }

        $key_data = $this->parseFormData($key_data);
        //SEND KEY FIELDS AND VALUES
        $this->datasource->delete($this->id, $key_data, $this->keyMetaArray());
    }

    /* -----------------------------------------------------------------
*  deleteBatch

*  deleteBatch form from datasource

*  @author: ctranel
*  @date: 2017-02-16
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return void
*  @throws: UnexpectedValueException
     * * -----------------------------------------------------------------
*/
    public function deleteBatch($key_data){
        if(!isset($key_data) || !is_array($key_data)){
            throw new \UnexpectedValueException('No form data received');
        }

        $variable_field = $this->batchVariableControl();
        if(!isset($variable_field)){
            throw new \UnexpectedValueException('Variable field not set on batch form submission.');
        }

        $key_data = $this->parseFormData($key_data);

        $key_vals = $this->datasource->batchDelete($this->id, $variable_field->name(), $key_data);
        return $key_vals;
    }

    /* -----------------------------------------------------------------
*  deactivate

*  deactivate record from database

*  @author: ctranel
*  @date: 2016-11-30
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return key->value array of keys for the record
*  @throws:
* -----------------------------------------------------------------
*/
    public function deactivate($entity_data){
        if(!isset($entity_data) || !is_array($entity_data)){
            throw new \UnexpectedValueException('No form data received');
        }

        $entity_data['isactive'] = 0;

        $key_vals = $this->datasource->update($this->id, $entity_data, $this->controlsMetaArray(), $this->keyMetaArray());
        return $key_vals;
    }

    /* -----------------------------------------------------------------
*  activate

*  activate record from database

*  @author: ctranel
*  @date: 2017-03-31
*  @param: array of key=>value pairs that have been processed by the parseFormData static function
*  @return key->value array of keys for the record
*  @throws:
* -----------------------------------------------------------------
*/
    public function activate($entity_data){
        if(!isset($entity_data) || !is_array($entity_data)){
            throw new \UnexpectedValueException('No form data received');
        }

        $entity_data['isactive'] = 1;

        $key_vals = $this->datasource->update($this->id, $entity_data, $this->controlsMetaArray(), $this->keyMetaArray());
        return $key_vals;
    }

    /* -----------------------------------------------------------------
     *  parses form data according to data type conventions.

    *  Parses form data according to data type conventions.

    *  @since: version 1
    *  @author: ctranel
    *  @date: July 1, 2014
    *  @param array of key-value pairs from form submission
    *  @return array of formatted key-value pairs from form submission
    *  @throws:
    * -----------------------------------------------------------------
    */
    public function parseFormData($form_data){
        $ret_val = [];
        if(!isset($form_data) || !is_array($form_data)){
            throw new \Exception('No form data found');
        }

        $controls = $this->controls();

        foreach($controls as $c){
            if(isset($form_data[$c->name()])){//!$c->isGenerated() &&
                $ret_val[$c->name()] = $c->parseFormData($form_data[$c->name()]);
                $parsed_subforms = $c->parseSubformData($form_data);
                if(isset($parsed_subforms)){
                    $ret_val = $ret_val + $parsed_subforms;
                }
            }
        }
        return $ret_val;
    }

    /* -----------------------------------------------------------------
    *  extracts uneditable column data

    *  extracts uneditable column data from submitted form data, as it is not included in the form data sent to datasource,
    * but is needed for

    *  @author: ctranel
    *  @date: 2016-10-13
    *  @param array of key-value pairs from form submission
    *  @return key-value pairs
    *  @throws:
    * -----------------------------------------------------------------
    protected function getUneditableCols($form_data){
        $ret_val = [];
        if(!isset($form_data) || !is_array($form_data)){
            throw new \Exception('No form data found');
        }
        foreach($this->controls as $c){
            if(!$c->isEditable()){
                $ret_val[] = $c->name();
            }
        }
        return $ret_val;
    }
*/

}