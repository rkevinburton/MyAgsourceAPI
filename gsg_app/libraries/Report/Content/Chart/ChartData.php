<?php

namespace myagsource\Report\Content\Chart;

require_once APPPATH . 'libraries/Report/Content/BlockData.php';

use \myagsource\Report\Content\BlockData;

/**
 * Name:  ChartData
 *
 * Author: ctranel
 *
 * Created:  04-29-2015
 *
 * Description: Data handler for chart blocks
 *
 */
class ChartData extends BlockData{
	/**
	FROM PARENT CLASS:
	protected $block;
	protected $report_datasource;
	protected $dataset;
	**/	
	
	/**
	 * categories
	 *
	 * @var Array
	protected $categories;
	 **/
	
	/**
	 * x_axis_field_name
	 *
	 * @var string
	 **/
	protected $x_axis_dbfield_name;
	
	/**
	 * @todo: add filter data
	 */
	function __construct(ChartBlock $block, \Report_data_model $report_datasource) {
		parent::__construct($block, $report_datasource);
		$this->setXAxesField();
	}

	/* -----------------------------------------------------------------
	*  setCategories

	*  Sets category properties for chart data object

	*  @author: ctranel
	*  @date: Apr 29, 2015
	*  @return void
	*  @throws: 
	* -----------------------------------------------------------------
	*/
	protected function setXAxesField(){
		foreach($this->block->xAxes() as $x){
			$f = $x->dbFieldName();
			if(isset($f) && !empty($f)){
				$this->x_axis_dbfield_name = $f;
			}
		}
	}
	
	/**
	 * @method getData()
	 * @param array - key-value pairs for criteria
	 * @return array of data for the chart
	 * @access public
	 *
	 *@todo: criteria param should be removed when filter data is included in object
	 **/
	public function getData($criteria_key_value){
		if(isset($this->x_axis_dbfield_name) && isset($this->x_axis_dbfield_name)){
			$criteria_key_value[$this->x_axis_dbfield_name]['dbfrom'] = $this->report_datasource->getStartDate($this->block->primaryTableName(), $this->x_axis_dbfield_name, $this->block->maxRows(), 'MM-dd-yyyy');
			$criteria_key_value[$this->x_axis_dbfield_name]['dbto'] = $this->report_datasource->getRecentDates($this->block->primaryTableName(), $this->x_axis_dbfield_name, 1, 'MM-dd-yyyy')[0];
		}
		
		$criteria_key_value = $this->whereCriteria($criteria_key_value);
		$select_fields = $this->block->getSelectFields();
		$this->dataset = $this->report_datasource->search($this->block, $select_fields, $criteria_key_value);

		$tmp_cat = $this->block->categories();
		//neither category nor timeline (e.g., pie charts)
		if(!isset($this->x_axis_dbfield_name) && (!isset($tmp_cat) || empty($tmp_cat))){//not a category or trend chart (e.g., pie chart)
			return array_values($this->dataset);
		}

		//categories
		if(isset($tmp_cat) && is_array($tmp_cat) && !empty($tmp_cat)){
			$this->splitByCategories();
			
			//field groups
			$tmp_fg = $this->block->fieldGroups();
			if(isset($tmp_fg) && is_array($tmp_fg) && !empty($tmp_fg)){
				$this->concatFieldGroups();
			}
			else{
				$this->stripFieldNames();
			}
		}
		//linear
		else{
			$this->splitLinearData($this->block->keepNulls());
			
			//field groups
			$tmp_fg = $this->block->fieldGroups();
			if(isset($tmp_fg) && is_array($tmp_fg) && !empty($tmp_fg)){
				$this->concatFieldGroups();
			}
			else{
				$this->stripFieldNames();
			}
		}

		// field names are maintained in dataset when breaking into categories because field group functionality requires them.
		// if dataset has categories but not field groups, we need to remove the field names from the dataset
//		else{
//		}
		
		return $this->dataset;
	}
	
	/**
	 * @method splitLinearData()
	 * @param string date field used on graph (test_date)
	 * @return array of data for the graph
	 * @access protected
	 *
	 **/
	protected function splitLinearData($keep_nulls = true){
		$count = count($this->dataset);
		$tmp_fg = $this->block->fieldGroups();
		$has_field_groups = (isset($tmp_fg) && !empty($tmp_fg));
		unset($tmp_fg);

		for($x = 0; $x < $count; $x++){
			$ser_key = 1;
			$arr_y_values = $this->dataset[$x];
			$arr_fields = array_keys($arr_y_values);
			$date_key = array_search($this->x_axis_dbfield_name, $arr_fields);
			unset($arr_fields[$date_key]);
			//@todo: use field meta data rather than hard-coding "age_months"?
			if($this->x_axis_dbfield_name == 'age_months'){
				$x_val = $this->dataset[$x][$this->x_axis_dbfield_name];
			}
			elseif(isset($this->dataset[$x][$this->x_axis_dbfield_name]) && !empty($this->dataset[$x][$this->x_axis_dbfield_name])){
				$arr_d = explode('-', $this->dataset[$x][$this->x_axis_dbfield_name]);
				$x_val = (mktime(0, 0, 0, $arr_d[1], $arr_d[2],$arr_d[0]) * 1000);
			}
			foreach($arr_fields as $k=>$f){
			    $tmp_data = is_numeric($this->dataset[$x][$f]) ? (float)$this->dataset[$x][$f] : $this->dataset[$x][$f];
				if($keep_nulls === true || isset($tmp_data)) {
   				    $tmp_data = is_numeric($this->dataset[$x][$f]) ? (float)$this->dataset[$x][$f] : $this->dataset[$x][$f];
   					//$arr_return[$ser_key][$x_val][$f] = $tmp_data;
   					if($has_field_groups){
   						$arr_return[$ser_key][$x_val][$f] = [
	   						$x_val,
	   						$tmp_data
	   					];
   					}
   					else{
   						$arr_return[$ser_key][$x_val] = [
	   						$x_val,
	   						$tmp_data
	   					];
   					}
   					$ser_key++;
				}
			}
		}
		if(isset($arr_return) && is_array($arr_return)){
			$this->dataset = $arr_return;
		}
		else return FALSE;
	}

		
	/**
	 * @method splitByCategories - used when data for multiple categories are returned in one row.  
	 * Breaks data down so that there is one row per category, each row having one entry for each series.
	 * 
	 * @return array of data for the graph
	 * @access protected
	 *
	 **/
	
	protected function splitByCategories(){
		$mod_base = (int)($this->block->numFields() /count($this->block->categories()));//
		if(is_array($this->dataset) && !empty($this->dataset)){
			$ser_key = 0;
			$prev_cat = '';
			foreach($this->dataset as $k=>$row){
				//must account for multiple series being returned in a single row
				$categories = [];
				$cat_idx = 0;
				foreach($this->block->reportFields() as $kk => $f){
					//get the position of the category, 0 indexed
					$cat = $f->categoryId();
					if(!array_key_exists($cat, $categories)){
						$categories[$cat] = $cat_idx;
						$cat_idx++;
					}
					$x_val = $categories[$cat];
					
					//When changing to new category, reset ser_key counter
					if($cat !== $prev_cat){
						$ser_key = 1;
					}
					else{
						$ser_key++;
					}
					$prev_cat = $cat;
					$arr_return[$ser_key][$x_val][$f->dbFieldName()] = $row[$f->dbFieldName()];
   					//$arr_return[$ser_key][$x_val][$f->dbFieldName()] = [
   					//	$x_val,
   					//	$row[$f->dbFieldName()]
   					//];
				}
			}
			$this->dataset = $arr_return;
		}
		else return FALSE;
	}

	/**
	 * @method concatFieldGroups - used when data for multiple categories are returned in one row.  
	 * Breaks data down so that there is one row per category, each row having one entry for each series.
	 * 
	 * @return array of data for the graph
	 * @access protected
	 *
	 * @todo: needs to handle time 
	 **/
	
	protected function concatFieldGroups(){
		if(!is_array($this->dataset) || empty($this->dataset)){
			return false;
		}
		$arr_return = [];
		foreach($this->dataset as $series=>$rows){
			foreach($rows as $x_val=>$v){
				$field_definitions = $this->block->reportFields();
				foreach($field_definitions as $f){
					if(!isset($v[$f->dbFieldName()])){
						continue;
					}
					$ser_idx = $f->fieldGroup();
					$ref_key = $f->fieldGroupRefKey();
//var_dump($ref_key);
					if(isset($ref_key)){
						//if the value is an array, we assume [xValue, yValue].  We want the yValue
						$tmp_val = is_array($v[$f->dbFieldName()]) ? $v[$f->dbFieldName()][1] : $v[$f->dbFieldName()];
						$arr_return[$ser_idx][$x_val][$ref_key] = $tmp_val;
					}
					else{
						$arr_return[$ser_idx][$x_val][] = $v[$f->dbFieldName()][1];
						//@todo: this assumes that chart is a boxplot (duplicates top and bottom values for a total of 5.  Should actually for and create a PR for highcharts)
						//if(count($arr_return[$ser_idx][$x_val]) === 1 || count($arr_return[$ser_idx][$x_val]) === 5){
							//$arr_return[$ser_idx][$x_val][] = $v[$f->dbFieldName()][1];
						//}
					}
				}
				if(array_search($x_val, $arr_return[$ser_idx][$x_val]) === false){
					//if(isset($ref_key)){
						$arr_return[$ser_idx][$x_val]['x'] = $x_val;
					//}
					//else{
					//	array_unshift($arr_return[$ser_idx][$x_val], $x_val);
					//}
				}
			}
		}
		$this->dataset = $arr_return;
		$this->stripFieldNames();
	}
	
	/**
	 * stripFieldNames
	 * 
	 * Removes text keys from dataset recursively (keys become numeric).
	 * 
	 * @return array of data for the graph
	 * @access protected
	 *
	 **/
	protected function stripFieldNames(){
		if(!isset($this->dataset) || !is_array($this->dataset)){
			return false;
		}
		$this->dataset = array_values_recursive($this->dataset);
	}
}

//utility function outside of class.  See todo below
/**
	 * Get all values from a multidimensional array
	 *
	 * @param $arr array
	 * @return null|string|array
	 * @todo -- build into class for dataset object?
	 */
	function array_values_recursive(array $arr){
//$k = key($arr);
		$arr = array_values($arr);
		foreach($arr as $key => $val){
			if(is_array($val)){//array_values($val) === $val){
//if(isset($val['x'])) echo "$k: " . $val['x'] . "<br>\n";
//else echo "$k: x not set<br>\n";
				if(!isset($val['x'])){
					$arr[$key] = array_values_recursive($val);
				}
				else{
					$arr[$key] = $val;
				}
			}
		}
		return $arr;
	}
?>