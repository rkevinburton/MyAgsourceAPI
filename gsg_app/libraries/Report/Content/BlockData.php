<?php

namespace myagsource\Report\Content;

require_once APPPATH . 'libraries/Report/Content/Block.php';
require_once APPPATH . 'libraries/Report/iBlockData.php';

use \myagsource\Report\Content\Block;
use \myagsource\Report\iBlockData;

/**
 * Name:  BlockData
 *
 * Author: ctranel
 *
 * Created:  02-18-2015
 *
 * Description: Data handler for report blocks
 *
 */
abstract class BlockData implements iBlockData{
	/**
	 * block
	 *
	 * report block
	 * @var Report\Content\Block
	 **/
	protected $block;
	
	/**
	 * report_datasource
	 *
	 * report datasource
	 * @var \Report_data_model 
	 **/
	protected $report_datasource;
	
	/**
	 * dataset
	 *
	 * dataset
	 * @var array
	 **/
	protected $dataset;
	
	
	/**
	 * @todo: add filter data
	 */
	function __construct(Block $block, \Report_data_model $report_datasource) {//, Benchmarks $benchmarks, DbTable $db_table
		$this->block = $block;
		$this->report_datasource = $report_datasource;
	}
	
	/** function whereCriteria
	 *
	 * translates filter criteria into sql format
	 * @param $arr_filter_criteria
	 * @return void
	 */
	
	protected function whereCriteria($arr_where_criteria){
		//incorporate built-in report filters if set
		/* NOT CURRENTLY USED
			if(is_array($this->arr_where_field) && !empty($this->arr_where_field)){
		$tmp_cnt = count($this->arr_where_field);
		for($x = 0; $x < $tmp_cnt; $x++){
		//if the field does not have a table prefix, add it
		if(strpos($this->arr_where_field[$x], '.') === FALSE){
		$this->arr_where_field[$x] =
		isset($this->arr_field_table[$this->arr_where_field[$x]]) && !empty($this->arr_field_table[$this->arr_where_field[$x]])
		? $this->arr_field_table[$this->arr_where_field[$x]] . '.' . $this->arr_where_field[$x]
		: $this->primary_table_name . '.' . $this->arr_where_field[$x];
		}
		$this->{$this->db_group_name}->where($this->arr_where_field[$x] . $this->arr_where_operator[$x] . $this->arr_where_criteria[$x]);
		}
		} */
		foreach($arr_where_criteria as $k => $v){
			//@todo: the below is only for databases as datasource
			if(strpos($k, '.') === FALSE) {
				$tbl = $this->block->getFieldTable($k); //get table for this block
				$tbl = isset($tbl) && !empty($tbl) ? $tbl : $this->block->primaryTableName();
				$db_field = $tbl . '.' . $k;

				//@todo: pull this out into function (array wrapper class (dependency)? pass function as param?)	
				$keys = array_keys($arr_where_criteria);
				$index = array_search($k, $keys, true);
				
				if ($index !== false) {
					$keys[$index] = $db_field;
					$arr_where_criteria = array_combine($keys, $arr_where_criteria);
				}
				//end function
			}
		}
		return $arr_where_criteria;
	}
	
	/*
	* @method prep_group_by()
	* @author ctranel
	protected function prep_group_by(){
		$arr_len = is_array($this->arr_group_by_field)?count($this->arr_group_by_field):0;
		for($c=0; $c<$arr_len; $c++) {
			$table = isset($this->arr_field_table[$this->arr_group_by_field[$c]]) && !empty($this->arr_field_table[$this->arr_group_by_field[$c]])?$this->arr_field_table[$this->arr_group_by_field[$c]] . '.':$this->primary_table_name . '.';
			if(!empty($this->arr_group_by_field[$c])){
				$this->{$this->db_group_name}->group_by($table . $this->arr_group_by_field[$c]);
			}
		}
	}
	*/
	
	/*
	 * @method prep_sort()
	* @param array fields to sort by
	* @param array sort order--corresponds to first parameter
	* @author ctranel
	*/
	protected function prep_sort($arr_sort_by, $arr_sort_order){
		$arr_len = is_array($arr_sort_by)?count($arr_sort_by):0;
		for($c=0; $c<$arr_len; $c++) {
			$sort_order = (strtoupper($arr_sort_order[$c]) == 'DESC') ? 'DESC' : 'ASC';
			$table = isset($this->arr_field_table[$arr_sort_by[$c]]) && !empty($this->arr_field_table[$arr_sort_by[$c]])?$this->arr_field_table[$arr_sort_by[$c]] . '.':$this->primary_table_name . '.';
			if((!is_array($this->arr_unsortable_columns) || in_array($arr_sort_by[$c], $this->arr_unsortable_columns) === FALSE) && !empty($arr_sort_by[$c])){
				//put the select in an array in case the field includes a function with commas between parameters
				if(is_array($this->arr_natural_sort_fields) && in_array($arr_sort_by[$c], $this->arr_natural_sort_fields) !== FALSE){
					$this->{$this->db_group_name}->order_by('users.dbo.naturalize(' . $table . $arr_sort_by[$c] . ')', $sort_order);
				}
				else {
					$this->{$this->db_group_name}->order_by($table . $arr_sort_by[$c], $sort_order);
				}
			}
		}
	}
	
	/*  
	 * @method pivot()
	 * @param array dataset
	 * @return array pivoted resultset
	 * @author ctranel
	 */
	public function pivot($arr_dataset){
		$header_text = ' ';
		$header_field = $this->block->pivotFieldName();
		$header_field_width = 10;
		$label_column_width = 10;
		
		$new_dataset = [];

		if(!isset($arr_dataset) || empty($arr_dataset)){
			return false;
		}
		foreach($arr_dataset as $k => $row){
			foreach($row as $name => $val){
				if(strpos($name, 'isnull') === FALSE && isset($row[$header_field]) && !empty($row[$header_field])) { //2nd part eliminates rows where fresh date is null (FCS)
					$new_dataset[$name][$k] = $val;

					if(isset($new_dataset[$name]['total']) === FALSE && $val !== NULL){
						$new_dataset[$name]['total'] = 0;
						$new_dataset[$name]['count'] = 0;
					} 
					
					if($val !== NULL){
						$new_dataset[$name]['total'] += $val;
						$new_dataset[$name]['count'] ++;
					} 
				}				
			}
		}
/*
		if($this->block->hasAvgRow()){
			$this->arr_fields['Average'] = 'average';
			$this->arr_pdf_widths['average'] = $header_field_width;
			$this->arr_field_sort['average'] = 'ASC';
			$this->arr_unsortable_columns[] = 'average';
		}
		if($this->block->hasSumRow()){
			$this->arr_fields['Total'] = 'total';
			$this->arr_pdf_widths['total'] = $header_field_width;
			$this->arr_field_sort['total'] = 'ASC';
			$this->arr_unsortable_columns[] = 'total';
		}
*/
		foreach($new_dataset as $k=>$a){
			if(!empty($k)){
//				if($bool_bench_column){
//					if($arr_benchmarks[$k] !== NULL) $sum_data['benchmark'] = round($arr_benchmarks[$k], $this->arr_decimal_points[$k]);//strpos($arr_benchmarks[$k], '.') !== FALSE ? trim(trim($arr_benchmarks[$k],'0'), '.') : $arr_benchmarks[$k];
//					else $sum_data['benchmark'] = NULL;
//				}
				if($this->block->hasAvgRow()){
					$new_dataset[$k]['average'] = $new_dataset[$k]['total'] / $new_dataset[$name]['count'];
					if(isset($this->arr_decimal_points[$k])) $new_dataset[$k]['average'] = round($new_dataset[$k]['average'], $this->arr_decimal_points[$k]);
				}
				if(($this->block->hasAvgRow() && !$this->block->hasSumRow()) || (!$this->block->hasAvgRow() && !$this->block->hasSumRow())){ //total column should not be displayed on PDF if it is only used to calculate avg 
					unset($new_dataset[$k]['total']);
				}
				unset($new_dataset[$k]['count']);
			}
		}
		//the following line is needed--didn't finish researching, but fresh cow summary tables break when it is removed
		//$this->arr_db_field_list = $this->arr_fields;
		return $new_dataset;
	}
}
?>