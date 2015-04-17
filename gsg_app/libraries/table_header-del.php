<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
* Name:  Table Header Library
*
* Author: ctranel
*
* Created:  04.07.2014
*
* Description:  Logic and service functions to support the custom report model.
*
* Requirements: PHP5 or above
*
*/

class Table_header
{
	protected $arr_header_structure;
	protected $arr_header_data;
	//protected $depth;
	//protected $rowspan;
	protected $tot_levels;
	//protected $arr_pdf_widths;
	protected $columns;
	
	public function __construct()
	{
	}
	/**
	 *  @method: get_column_count() returns count of columns
	 *  @access public
	 *  @return int
	 **/
	public function get_column_count(){
		return $this->columns;
	}
	
	/**
	 *  @method: get_table_header_array() takes array of data structure and returns and array of menu data including text,
	 * 		colspan, rowspan and level (to be used to create class names in view) *
	 *  @access public
	 *  @param array $arr_header_data
	 *  @param array pdf widths
	 *  @param int total levels in header array hierarchy
	 **/
	public function get_table_header_array($arr_header_data, $arr_pdf_widths = array()){
		$depth = 0;
		$rowspan = 1;
		$tot_levels = array_depth($arr_header_data);
		$this->arr_header_structure = array(); //return value
		$this->getHeaderLayer($arr_header_data, $depth, $rowspan, $tot_levels, $arr_pdf_widths);
		ksort($this->arr_header_structure);
		return $this->arr_header_structure;
	} //end function table_header_cell
	
	/** 
	 * @method getHeaderLayer() Takes array of header structure by reference from the parent function, as well as array of data structure, depth level and total number of tiers in the header structure.
	 * Returns and array of menu data including text, colspan, rowspan, level (to be used to create class names in view)
	 * and database field name.
	 *  @access protected
	 *  @param array $arr_header_ section_data
	 *  @param int current depth within hierarchy
	 *  @param int total levels in header array hierarchy
	 *  @param array pdf column widths
	 **/
	protected function getHeaderLayer($arr_data_in, $curr_depth, $rowspan, $tot_levels, $arr_pdf_widths, $parent_was_empty = FALSE){
		foreach($arr_data_in as $k => $v){
			$trim_k = trim($k);
			if(empty($trim_k)){ //if the header has no text, keep the current depth, but add one to the rowspan
				$rowspan++;
			}
			else{ //if there is text in the header, increment the depth (not the rowspan), and create an entry in the header structure array
				$curr_depth++;
			}
			if(is_array($v)){
				//get number of leaves and PDF width for this array
				$num_leaves = 0;
				$pdf_width = 0;
				array_walk_recursive( 
					$v,
					create_function(
						'$val, $key, $obj',
						'$obj["num_leaves_in"] = $obj["num_leaves_in"] + 1; if(!empty($obj["arr_pdf_widths"])) $obj["pdf_width"] += $obj["arr_pdf_widths"][$val];'
					),
					array('num_leaves_in' => &$num_leaves, 'pdf_width' => &$pdf_width, 'arr_pdf_widths' => $arr_pdf_widths)
				);
				//add data to object array ($this->arr_header_structure)
				if(!empty($trim_k)){ //if the header has no text, keep the current depth, but add one to the rowspan
					$this->arr_header_structure[($curr_depth - 1)][] = Array('text' => $k, 'colspan' => $num_leaves, 'rowspan' => $rowspan, 'pdf_width' => $pdf_width);
				}
				//recursively retrieve header info for this sub-array
				$pass_rowspan = $parent_was_empty && !empty($trim_k) ? 1 : $rowspan;
				$pass_depth = $parent_was_empty && !empty($trim_k) ? $curr_depth + 1 : $curr_depth;
				$this->getHeaderLayer($v, $pass_depth, $pass_rowspan, $tot_levels, $arr_pdf_widths, empty($trim_k));
			}
			else { //add leaf node
				$this->arr_header_structure[($curr_depth - 1)][] = Array('text' => $k, 'colspan' => '1', 'rowspan' => $rowspan, 'field_name' => $v);
				$this->columns++;
			}
			if(empty($trim_k)) $rowspan--;
			else $curr_depth--;
		}
	}
	
	/** *takes array of data structure and returns and array of menu data including text,
	 * colspan, rowspan and level (to be used to create class names in view) *
	 *  @access public
	 *  @param array $arr_header_data
	function get_csv_header_array($arr_header_data){
		$tot_levels = 3; //need to make this dynamic -- snippets at bottom of page for seed code
		$depth = 0;
		$arr_header_structure = Array(); //return value
		get_csv_header($arr_header_structure, $arr_header_data, $depth, $tot_levels);
		return $arr_header_structure;
	} //end function table_header_cell
	 */
	
	/** 
	 * Takes array of header structure by reference from the parent function, as well as array of data structure, depth level and total number of tiers in the header structure.
	 * Returns and array of menu data including text, colspan, rowspan, level (to be used to create class names in view)
	 * and database field name.
	 *  @access public
	 *  @param array $arr_header_structure
	 *  @param array data in
	 *  @param int current depth within hierarchy
	 *  @param int total levels in header array hierarchy
	function get_csv_header(&$arr_header_structure, $arr_data_in, &$depth, $tot_levels){
		foreach($arr_data_in as $k => $v){
			if(is_array($v)){
				//get number of leaves for this array
				$num_leaves = 0;
				array_walk_recursive($v, create_function('$val, $key, $obj', '$obj["num_leaves_in"] = $obj["num_leaves_in"] + 1;'), array('num_leaves_in' => &$num_leaves));
				//add data to return array
				$arr_header_structure[] = Array('text' => $k, 'colspan' => $num_leaves, 'rowspan' => '1');
				$this->getHeaderLayer($arr_header_structure, $v, ++$depth, $tot_levels);
			}
			else {
				$rowspan = $tot_levels - $depth;
				$arr_header_structure[$v] = Array('text' => $k, 'colspan' => '1', 'rowspan' => $rowspan, 'field_name' => $v);
			}
		}
		$depth--; //revert to the depth from before the array was processed.
	}
 **/
}