<?php
/*
* @description  Manage FTP users through a MySQL database
* @requirments 	Apache2, PHP >= 5.3, MySQL
* @author 		Raphaël Amourette
*/

############ CONFIG ################
$config_file = "_config.xml";

####################################
// Do not edit this part (unless you know what you're doing)
// You may want to change this part, according to your column(s) name
####################################
$remove_id_column = true; // remove the id column from the form

$radio_box = array(
	'status' => array(
		'0' => 'Inactif',
		'1' => 'Actif'
	)
);

// allows to check if all these field(s) are unique when adding/editing an user
$unique_fields_list = array(
	'User'
);

// all these field(s) must have a value (otherwise, an error is thrown)
$not_null_fields = array(
	'User',
	'Password',
	'Dir'
);

$special_field_treatment = array(
	'Password' => function ($value) {
		return md5($value);
	}
);

// PDO native type values (may be incomplete)
$native_number_types = array(
	'SHORT',
	'LONG'
);

####################################
// DO NOT EDIT AFTER THIS
####################################
// opening config file
if (file_exists($config_file)) {
	$xml    = simplexml_load_file($config_file);
	$json   = json_encode($xml);
	$config = json_decode($json, TRUE);
} else {
    exit('Error while opening the config file (' . $config_file .'). Maybe the name is incorrect or the file is missing ?');
}

####################################
session_start();
require_once('includes/Database.class.php');

$db = new Database($config["database"]["host"], $config["database"]["db_name"], $config["database"]["user"], $config["database"]["password"]);

####################################
function detectRadioBox($field_name) {
	global $radio_box;

	foreach ($radio_box as $id => $options) {
		if ($id == $field_name) {
			return $options;
		}
	}

	return false;
}

function redirect ($page) {
	if ($page == 'homepage') {
		$page = "?action=homepage";
	}

	header("Location: $page");
	exit;
}

// use for debug purpose
function dump($var, $exit = true) {
	echo '<pre>';
	var_dump($var);
	echo '</pre>';

	if ($exit) exit;
}

############################################
############### SCRIPT #####################
############################################

// users table and identifier
$table      = $config["database"]["table"];
$identifier = $config["database"]["identifier"];

// fields list with meta information
$fields = $db->getMeta("SELECT * FROM $table");

// removing the id column if it's defined
if ($remove_id_column) {
	for ($i = 0; $i < count($fields); $i++) {
		if ($fields[$i]['name'] == 'id') {
			unset($fields[$i]);
			break;
		}
	}
}

// used for session message
$error = '';

// define current page
$action = "homepage";

if (isset($_GET['action'])) {
	$action = $_GET['action'];

	if ($action == "add") {
		// if form has been posted
		if (isset($_POST['add-form'])) {

			$filled_fields       = array();
			$filled_fields_value = array();

			foreach ($fields as $field) {
				// we check if this field is in the not-null array
				if (in_array($field['name'], $not_null_fields)) {
					if (!isset($_POST[$field['name']]) || (isset($_POST[$field['name']]) && empty($_POST[$field['name']]))) {
						$error .= "The field <strong>" . $field['name'] . "</strong> cannot be empty<br />";
					}
				}

				// then, we check if it must be unique
				if (in_array($field['name'], $unique_fields_list)) {
					if (isset($_POST[$field['name']]) && !empty($_POST[$field['name']])) {
						$value = $_POST[$field['name']];

						$query = "SELECT " . $field['name'] . " FROM $table WHERE " . $field['name'] . "= ?";
						if ($db->exists($query, array($value))) {
							$error .= "The field <strong>" . $field['name'] . "</strong> must be unique. Record has been found in the database.<br />";
						}
					}
				}

				// finally, we handle the data
				if (empty($error) && isset($_POST[$field['name']])) {
					$value = $_POST[$field['name']];

					if (in_array($field['native_type'], $native_number_types) && !empty($value) && !is_numeric($value)) {
						$error .= "The field <strong>" . $field['name'] . "</strong> must be a numeric value.<br />";
					}
					else {
						// check for pre-treatment
						if (isset($special_field_treatment[$field['name']])) {
							$value = $special_field_treatment[$field['name']]($value);
						}

						$filled_fields[]       = $field['name'];
						$filled_fields_value[] = $value;
					}
				}
			}

			if (empty($error)) {
				// we can finally execute the query
				$query = "INSERT INTO $table (";
				$query .= implode(',', $filled_fields);
				$query .= ") VALUES (" . implode(',', array_fill(0, count($filled_fields_value), '?'));
				$query .= ")";
				
				$db->execute($query, $filled_fields_value);

				$_SESSION['message'] = array('success' => "The user has been added.");
				redirect("homepage");
			}
			else {
				$_SESSION['message'] = array('danger' => $error);
			}
		}
	}

	elseif ($action == "edit") {

	}

	elseif ($action == "remove") {
		if (isset($_GET['id'])) {
			$id = $_GET['id'];

			$query = "SELECT $identifier as identifier FROM $table WHERE $identifier = ?";

			// if the user exists, we can delete it
			if ($db->exists($query, array($id))) {
				$query = "DELETE FROM $table WHERE $identifier = ?";
				$db->execute($query, array($id));

				$_SESSION['message'] = array('success' => "The user has been deleted.");
			}
			else {
				$_SESSION['message'] = array('danger' => "This user doesn't exist in the database.");
			}
		}
		else {
			$_SESSION['message'] = array('danger' => "An error occured while deleting this user.");
		}
		
		redirect("homepage");
	}
}

if ($action == "homepage") {
	// current data
	$displayed_fields    = $config["displayed_fields"]["field"];
	$db_displayed_fields = implode(',', $displayed_fields);
	$data                = $db->execute("SELECT $identifier as identifier, $db_displayed_fields FROM $table");
}

?>

<html>
<head>
	<meta charset="utf-8" />
	<link rel="stylesheet" type="text/css" href="assets/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>

<body style="width: 90%; margin: auto;">
<div class="row">
<div class="col-md-12">
	
	<div class="page-header" style="margin-bottom: 30px;">
		<h3 style="text-align: center;">FTP &ndash; <small>Manage users account</small></h3>
	</div>

	<?php
	if (isset($_SESSION["message"])) {
		foreach ($_SESSION["message"] as $key => $message) {
			echo '<div class="alert alert-' . $key .'">' . $message . '</div>';
		}

		unset($_SESSION["message"]);
	}
	?>

	<?php 
		if ($action == "homepage") {
	?>
		<a href="?action=add" title="Add an user" class="btn btn-primary">Add an user account</a>

		<table class="table table-striped table-bordered table-hover table-condensed" style="margin-top: 25px; font-size: 1.1em;">
			<thead>
			<tr>
				<?php
					foreach ($displayed_fields as $displayed_field) {
						echo '<th>' . $displayed_field . '</th>';
					}
				?>
				<th style="width: 80px;">Edit</th>
				<th style="width: 80px;">Remove</th>
			</tr>
			</thead>

			<tbody>
				<?php
					foreach ($data as $entry) {
						echo '<tr>';

						foreach ($displayed_fields as $displayed_field) {
							if (($options = detectRadioBox($displayed_field)) !== false) {
								echo '<td>' . $options[$entry->$displayed_field] . '</td>';
							}
							else {
								echo '<td>' . $entry->$displayed_field . '</td>';
							}
						}

						echo '<td><a href="?action=edit&id=' . $entry->identifier . '" title="Edit user"><span class="glyphicon glyphicon-edit"></span></a></td>';
						echo '<td><a class="remove" href="?action=remove&id=' . $entry->identifier . '" title="Remove user" onclick="return(confirm(\'Please confirm that you want to delete this user.\'));"><span class="glyphicon glyphicon-remove"></span></a></td>';

						echo '</tr>';
					}
				?>
			</tbody>
		</table>

	<?php
		}
		elseif ($action == "add") {
	?>
		<a href="?action=homepage" title="Back home" class="btn btn-default">Home</a>

		<form action="?action=add" method="post" class="form-horizontal well" role="form" style="margin-top: 25px;">

			<?php
				foreach ($fields as $field) {
					echo '<div class="form-group">';

					if (($options = detectRadioBox($field['name'])) !== false) {
						echo '<label class="col-sm-2 control-label">' . ucfirst($field['name']) . '</label>';

						foreach ($options as $key => $value) {
							echo '<div class="radio col-sm-offset-2">';
							echo '<label>';
								echo '<input type="radio" name="' . $field['name'] .'" id="' . $field['name'] . '" value="' . $key . '"';
								if (isset($_POST[$field['name']]) && $_POST[$field['name']] == $key) {
									echo ' checked="checked"';
								}

								echo '>' . $value;
							echo '</label>';
							echo '</div>';
						}
					}
					else {
						echo '<label for="' . $field['name'] . '" class="col-sm-2 control-label">' . ucfirst($field['name']) . '</label>';

						echo '<div class="col-sm-6">';
						echo '<input type="text" class="form-control" name="' . $field['name'] . '" id="' . $field['name'] . '"';

						if (isset($field['len']) && !empty($field['len'])) {
							echo 'maxlength="' . $field['len'] . '"';
						}

						if (isset($_POST[$field['name']]) && !empty($_POST[$field['name']])) {
							echo ' value="' . $_POST[$field['name']] . '"';
						}

						echo ' />';
						echo '</div>';
					}

					echo '</div>';
				}

				if (isset($config["options"]['add_email_field']) && $config["options"]['add_email_field']) {
					echo '<div class="form-group">';
					echo '<label for="receiver_email" class="col-sm-2 control-label">Email (will receive all the information above)</label>';

					echo '<div class="col-sm-6">';
					echo '<input type="text" class="form-control" name="receiver_email" id="receiver_email" />';
					echo '</div>';

					echo '</div>';
				}
			?>

			<button type="submit" name="add-form" class="btn btn-primary">Submit</button>
		</form>

	<?php
		}
		elseif ($action == "edit") {
	?>


	<?php
		}
	?>
</div>
</div>
</body>
</html>