<?php

require_once "Abstraction/ClassNode.php";
require_once "Abstraction/RDFSClass.php";
require_once "Abstraction/Link.php";
require_once "Abstraction/Utils.php";

//under construction
/**
 * Erfurt_Sparql Query - Abstraction.
 * 
 * an Abstraction for Sparql-Queries
 * 
 * @see			{@link http://code.google.com/p/Erfurt_Sparql/wiki/QueryObject Idea}
 * @package    query
 * @subpackage abstraction
 * @author     Jonas Brekle <jonas.brekle@gmail.com>
 * @copyright  Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id$
 */
class Erfurt_Sparql_Query2_Abstraction
{
	protected $query;
	protected $startNode;
	
	protected $allowedCalls = array("addFrom", "getFrom", "setFrom", "getFroms", "setFroms", "addProjectionVar", "getOrder", "setLimit", "setOffset", "getLimit", "getOffset", "setDistinct", "setReduced", "getDistinct", "getReduced");
	
	public function __construct(){
		$this->query = new Erfurt_Sparql_Query2();
	}
	
	
	public function __clone() {
	    foreach($this as $key => $val) {
	        if(is_object($val)||(is_array($val))){
	            $this->{$key} = unserialize(serialize($val));
	            //$this->$key= clone($this->$key); 
	        }
	    }
	} 
	
	
	/**
	 * redirect method calls
	 */
	public function __call($name, $params){
		if(in_array($name, $this->allowedCalls)){
			return call_user_func_array(array($this->query, $name), $params); 
		} else throw new RuntimeException("Query2_Abstraction: method $name not found");
	}
	
	public function addNode(Erfurt_Sparql_Query2_Abstraction_ClassNode $sourceNode = null,  $LinkPredicate = null, $targetClass = null, $withChilds = true, $varName = null, $member_predicate = EF_RDF_TYPE){
		// hack for overloaded functioncalls
		if($LinkPredicate != null && !($LinkPredicate instanceof Erfurt_Sparql_Query2_IriRef)){
			if(is_string($LinkPredicate)){
				$LinkPredicate = new Erfurt_Sparql_Query2_IriRef($LinkPredicate);
			} else throw new RuntimeException("Argument 2 passed to Erfurt_Sparql_Query2_Abstraction::addNode must be an instance of Erfurt_Sparql_Query2_IriRef or string, instance of ".typeHelper($LinkPredicate)." given");
		}
		if($targetClass != null && !($targetClass instanceof Erfurt_Sparql_Query2_IriRef)){
			if(is_string($targetClass)){
				$targetClass = new Erfurt_Sparql_Query2_IriRef($targetClass);
			} 
			if(!($targetClass instanceof Erfurt_Sparql_Query2_IriRef))
				 throw new RuntimeException("Argument 3 passed to Erfurt_Sparql_Query2_Abstraction::addNode must be an instance of Erfurt_Sparql_Query2_IriRef or string, instance of ".typeHelper($targetClass)." given");
		}
		
		if($sourceNode == null && $LinkPredicate == null){
			//add startnode
			$this->startNode = new Erfurt_Sparql_Query2_Abstraction_ClassNode($targetClass, $member_predicate, $this->query, $varName, $withChilds);
			return $this->startNode;
		}
		
		if($sourceNode != null && $LinkPredicate != null){
			if($targetClass == null){
				//TODO: find type of referenced objects
			}
			//add link from source node to new node
			$newnode = new Erfurt_Sparql_Query2_Abstraction_ClassNode($targetClass, $member_predicate, $this->query, $varName, $withChilds);
			$sourceNode->addLink($LinkPredicate, $newnode);
			return $newnode; //for chaining
		} else {
			throw new RuntimeException("Erfurt_Sparql_Query2_Abstraction::addNode : argument 1 and 2 must either both be null or both not null");
		}
	}
	
	public function getSparql(){
		return $this->query->getSparql();
	}
	
	public function __toString(){
		return $this->getSparql();
	}
	
	public function getStartNode(){
		return $this->startNode;
	}
	
	public function getQueryClone(){
		return clone $this->query;
	}
}
?>
