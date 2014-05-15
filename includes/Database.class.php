<?php

class Database {
	private $db;

	public function __construct ($db_host, $db_name, $db_user, $db_password) {
		try {
		    $this->db = new PDO('mysql:host=' . $db_host . ';dbname='.$db_name, $db_user, $db_password);
		}
		 
		catch(Exception $e) {
		    echo 'Erreur : '.$e->getMessage().'<br />';
		    echo 'Num : '.$e->getCode();
		}
	}

	public function execute ($query, $parameters = array(), $fetchOne = false) {
		$query = $this->db->prepare($query);
		$query->execute($parameters);

		if ($fetchOne) {
			$result = $query->fetch(PDO::FETCH_OBJ);
		}
		else {
			$result = $query->fetchAll(PDO::FETCH_OBJ);
		}
		
		return $result;
	}

	public function getMeta ($query, $parameters = array()) {
		$query = $this->db->prepare($query);
		$query->execute($parameters);

		$meta = array();
		foreach(range(0, $query->columnCount() - 1) as $column_index) {
		  $meta[] = $query->getColumnMeta($column_index);
		}
		
		return $meta;
	}

	public function exists ($query, $parameters = array()) {
		$query = $this->db->prepare($query);
		$query->execute($parameters);

		if ($query->fetchColumn() !== false) {
			return true;
		}

		return false;
	}

	public function getDatabase() {
		return $this->db;
	}
}