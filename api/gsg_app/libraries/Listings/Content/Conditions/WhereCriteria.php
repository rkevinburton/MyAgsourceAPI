<?php
namespace myagsource\Listings\Content\Conditions;

require_once APPPATH . 'libraries/Listings/iWhereCriteria.php';

use \myagsource\Listings\iWhereCriteria;
use \myagsource\Datasource\iDataField;
//use \myagsource;

/**
 * Name:  WhereCriteria
 *
 * Author: ctranel
 *
 * Created:  2017-08-01
 *
 * Description:  WhereCriteria.
 *
 */
class WhereCriteria implements iWhereCriteria {
	/**
	 * field
	 * @var iDataField
	 **/
	protected $datafield;
	
	/**
	 * operator
	 * @var string
	 **/
	protected $operator;

    /**
     * operand
     * @var string
     **/
    protected $operand;

    /**
	 */
	/* -----------------------------------------------------------------
	*  Constructor

	*  Sets datafield and order properties

	*  @author: ctranel
	*  @date: 2017-08-01
	*  @param: iDataField sort field
	*  @param: string sort order
	*  @return datatype
	*  @throws: 
	* -----------------------------------------------------------------
	\*/
	public function __construct(\myagsource\Datasource\iDataField $datafield, $operator, $operand) {
		$this->datafield = $datafield;
		$this->operator = $operator;
        $this->operand = $operand;
	}
	
	/* -----------------------------------------------------------------
	*  fieldName

	*  Returns name of field in sort

	*  @author: ctranel
	*  @date: 2017-08-01
	*  @return string field name
	*  @throws: 
	* -----------------------------------------------------------------
	\*/
	public function fieldName(){
		return $this->datafield->dbFieldName();
	}

	/**
	 * criteria
	 *
	 * SQL conditional string.
	 * 
	 * @return 
	 * @author ctranel
	 **/
	public function criteria(){
	    switch(strtolower($this->operator)){
            case 'in':
                $condition = "IN('" . implode("','" , explode('|', $this->operand)) . "')";
                break;
            case 'between':
                $tmp = explode('|', $this->operand);
                $condition = "BETWEEN '" . $tmp[0] . "' AND '" . $tmp[1] . "'";
                break;
            default:
                if($this->operand === 'CURRDATE'){
                    $condition = $this->operator . " GETDATE()";
                    break;
                }
                if(!isset($this->operand)){
                    $condition = $this->operator . " NULL";
                    break;
                }
                $condition = $this->operator . " '" . $this->operand . "'";
        }

	    return $this->datafield->dbFieldName() . ' ' . $condition;
	}
}

?>