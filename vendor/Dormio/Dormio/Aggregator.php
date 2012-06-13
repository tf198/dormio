<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Tris Forster <tris.701437@tfconsulting.com.au>
 * @license http://www.gnu.org/licenses/lgpl.txt GNU Lesser General Public License v3
 * @package Dormio
 */

/**
* Aggregator methods for querysets
* @package Dormio
* @subpackage Manager
*/
class Dormio_Aggregator {
  function __construct($manager) {
    $this->manager = $manager;
    $this->manager->query['select'] = array();
    $this->manager->reverse = array();
    $this->manager->_reset();
  }
  
  /**
  * Adds an aggregation method to the set
  *
  * @todo Need to allow running of multiple aggregate methods in a single query
  * @return int   The number result of the method
  */
  private function add($method, $extra=null, $field='pk') {
    $p = $this->manager->_resolvePath($field);
    //$this->manager->query['select'][] = "{$method}({$extra}{{$spec['db_column']}}) AS {{$field}_" . strtolower($method) . "}";
    $m = strtolower($method);
    $as = "{$p[2]}_{$p[1]}_{$m}";
    $this->manager->query['select'][] = "{$method}({$extra}{$p[2]}.{{$p[1]}}) AS {{$as}}";
    $this->manager->reverse[$as] = "{$field}.{$m}";
    return $this;
  }
  
  function run() {
  	$result = $this->manager->findArray();
	return $result[0];
  }
  
  /**
  * Runs a COUNT(<DISTINCT> $field) on the dataset
  */
  function count($field='pk', $distinct=false) {
    return $this->add("COUNT", (($distinct) ? "DISTINCT " : null), $field);
  }
  
  /**
  * Runs a MAX($field) on the dataset
  */
  function max($field='pk') {
    return $this->add("MAX", null, $field);
  }
  
  /**
  * Runs a MIN($field) on the dataset
  */
  function min($field='pk') {
    return $this->add("MIN", null, $field);
  }
  
  /**
  * Runs a AVG($field) on the dataset
  */
  function avg($field='pk') {
    return $this->add("AVG", null, $field);
  }
  
  /**
  * Runs a SUM($field) on the dataset
  */
  function sum($field='pk') {
    return $this->add("SUM", null, $field);
  }
}
