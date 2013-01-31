<?php

class Scaffold {
	public $table = array();

	function Scaffold($project, $table_name, $table_info) {
		$columns = array();
		foreach($table_info['columns'] as $key => $value)
			if (is_array($value))
				$columns[] = array('tipo' => $value, 'nombre' => $key);
		$this->project = $project;
		$this->table   = $table_name;
		$this->id_key  = $table_info['id_key'];
		$this->columns = $columns;
		$this->resultsPerPage = $project['results_per_page'];
		$this->relInputsAndQueries = $this->_relationship_inputs_and_queries();
	}

	function list_page() {
		$return_string = "<?php
require_once('../inc.functions.php');\n";

		$columns = array();                    
		$relationshipsUsed = array();
		foreach ($this->columns as $column) {
			if ($column['tipo']['fk']) {
				$originalRelationshipData = $this->oneToManyOriginalDataForColumn($column['nombre']);
				$columns[] =
					"`".$originalRelationshipData['A']['table'].
					"`.`".$originalRelationshipData['A']['nameField'].
					"` AS `".$column['nombre']."`";
				$relationshipsUsed[] = $originalRelationshipData;
			} else {
				$columns[] = "T.`{$column['nombre']}`";
			}
		}
		$columnsForSelect = implode(', ', $columns);            
		              
		$selectJoin = '';
		foreach ($relationshipsUsed as $relationshipUsed) {
			$tableName = $relationshipUsed['A']['table'];
			$fk = $relationshipUsed['B']['FK'];         
			$foreignPk = $relationshipUsed['B']['PK'];
			$selectJoin .= " LEFT JOIN `$tableName` ON T.`$fk` = `$tableName`.`$foreignPk`";
		}

		$return_string .= "\nprint_header('{$this->project['project_name']} » " . $this->_titleize($this->table) . "');

if (isset(\$_GET['msg'])) echo '<p id=\"msg\">'.\$_GET['msg'].'</p>';

/* Default search criteria (may be overriden by search form) */
\$conds = 'TRUE';
require_once('{$this->project['search_page']}');

/* Default paging criteria (may be overriden by paging functions) */
\$start     = 0;
\$per_page  = {$this->resultsPerPage};
\$count_sql = 'SELECT COUNT({$this->id_key}) AS tot FROM `{$this->table}` T WHERE ' . \$conds;
include('../inc.paging.php');

/* Get selected entries! */
\$sql = \"SELECT $columnsForSelect FROM `{$this->table}` T $selectJoin WHERE \$conds \" . get_order('{$this->table}') . \" LIMIT \$start,\$per_page\";

echo '<table>\n";
		$return_string .= "  <tr>\n";
		foreach($this->columns as $v) {
			$return_string .= '    <th>'. $this->_titleize($v['nombre']) . ' \' . put_order(\''.$v['nombre']."') . '</th>\n";
		}
		$return_string .= '    <th colspan="2" style="text-align:center">Actions</th>';
		$return_string .= "\n  </tr>\n';

\$r = mysql_query(\$sql) or trigger_error(mysql_error());
while(\$row = mysql_fetch_array(\$r)) {\n";
		$return_string .= "	echo '  <tr>\n";

		foreach($this->columns as $v) {
			if($v['tipo']['blob'])
				$val = "limit_chars(\$row['".$v['nombre']."'])";
			elseif($v['tipo']['date'] or $v['tipo']['datetime'])
				$val = "humanize(\$row['".$v['nombre']."'])";
			elseif($v['tipo']['bool'])
				$val = "(\$row['".$v['nombre']."'] ? 'Yes' : 'No')";
			else
				$val = "\$row['".$v['nombre']."']";
				
			$val = "htmlentities($val, ENT_COMPAT | ENT_HTML401, 'UTF-8')";
			
			if ($v['tipo']['blob'])
				$val = "nl2br($val)";

			$return_string .= "    <td>' . " . $val . " . '</td>\n";
		}
		$return_string .= "    <td><a href=\"{$this->project['crud_page']}?{$this->id_key}=' . \$row['{$this->id_key}'] . '\">Edit</a></td>
    <td><a href=\"{$this->project['crud_page']}?delete=1&amp;{$this->id_key}=' . \$row['{$this->id_key}'] . '\" onclick=\"return confirm(\'Are you sure?\')\">Delete</a></td>
  </tr>' . \"\n\";\n";
		$return_string .= "}\n\n";
		$return_string .= 'echo "</table>\n\n";

include(\'../inc.paging.php\');

echo \'<p><a href="' . $this->project['crud_page'] . '">New entry</a></p>\';

print_footer();
?>';

		return $return_string;
	}

	function crud_page() {
		
		$return_string = "<?php
require_once('../inc.functions.php');\n\n";

		$return_string .= "if (isset(\$_GET['delete'])) {
	mysql_query(\"DELETE FROM `{$this->table}` WHERE `{$this->id_key}` = '\$_GET[{$this->id_key}]}'\");
	\$msg = (mysql_affected_rows() ? 'Row deleted.' : 'Nothing deleted.');
	header('Location: {$this->project['list_page']}?msg='.\$msg);
}

\${$this->id_key} = (isset(\$_GET['{$this->id_key}']) ? \$_GET['{$this->id_key}'] : 0);
\$action = (\${$this->id_key} ? 'Editing' : 'Add new') . ' entry';\n\n";

		$column_array = array();

		$return_string .= "if (isset(\$_POST['submitted'])) {
			\$values = array();
			foreach(\$_POST AS \$key => \$value) { if (is_string(\$value)) \$values[\$key] = mysql_real_escape_string(\$value); }\n";
		$insert = "INSERT INTO `{$this->table}` (";
		$counter = 0;
		foreach($this->columns as $v) {
			$insert .= "`$v[nombre]`" ;
			if ($counter < count($this->columns) - 1)
				$insert .= ", ";
			$counter++;
		}
		$insert .= ") VALUES ('\${$this->id_key}', ";

		$counter = 0;
		foreach ($this->columns as $v) {
			if ($v['nombre'] != $this->id_key) {
				$field = $v['nombre'];
				$val = $this->_parse($field, $v['tipo']);
				
				if ($v['tipo']['date'] || $v['tipo']['datetime'])
					$insert .= '\'".'.$val.'."\'';
				else
					$insert .= '".'."(strlen($val) > 0 ? \"'\".$val.\"'\" : \"DEFAULT(`$field`)\")".'."';
					
				if ($counter < count($this->columns) - 2)
					$insert .= ", ";
				$counter++;
			}
		}
		$insert .= ') ON DUPLICATE KEY UPDATE ';
		
		$counter = 0;
		foreach($this->columns as $v) {
			$insert .= "`$v[nombre]` = VALUES(`$v[nombre]`)" ;
			if ($counter < count($this->columns) - 1)
				$insert .= ", ";
			$counter++;
		}
		
		$insert .= ';';

		$return_string .= "	\$sql = \"$insert\";
	mysql_query(\$sql) or die(mysql_error());
	\$msg = (mysql_affected_rows()) ? 'Edited row.' : 'Nothing changed.';
	";
	
	// update relationships
	$return_string .= "\$actualPk = \$id;\n";
	foreach ($this->relInputsAndQueries as $inputAndQueries) {
		if ($inputAndQueries['updateQueryFirst']) {
			$updateQueryFirst = $inputAndQueries['updateQueryFirst'];
			$updateQuerySecond = $inputAndQueries['updateQuerySecond'];
			
			$type = $inputAndQueries['originalData']['type'];
			$relationshipName = $inputAndQueries['relationshipName'];
			
			$return_string .= "
			\$postedRelationships = (isset(\$_POST['$type']['$relationshipName'])) ? \$_POST['$type']['$relationshipName'] : array();
			
			
			\$postedRelationshipsSafe = array();
			foreach (\$postedRelationships as \$postedRelationship)
				\$postedRelationshipsSafe[] = intval(\$postedRelationship);
			";
			
			if ($type == 'oneToMany') {
				$return_string .= "\$postedRelationshipsForQuery = \"('\".implode(\"','\", \$postedRelationshipsSafe).\"')\";\n";
			} elseif ($type == 'manyToMany') {
				$return_string .= "\$postedRelationshipsForQuery = \"(\$actualPk, '\".implode(\"'), (\$actualPk, '\", \$postedRelationshipsSafe).\"')\";\n";
			}
			
			$return_string .= "\$updateQueryFirst = \"$updateQueryFirst\";\n";
			$return_string .= "\$updateQuerySecond = \"$updateQuerySecond\";\n";
			$return_string .= "mysql_query(\$updateQueryFirst);\n";
			$return_string .= "if (count(\$postedRelationships) > 0) mysql_query(\$updateQuerySecond);\n";     
		}
	}
	
	
	$return_string .= "
	header('Location: {$this->project['list_page']}?msg='.\$msg);
}

";
		
		// query relationship tables to fill selects
		
		$return_string .= "\$actualPk = \$id;\n";
		$return_string .= "\$relationships = array();\n";
		
		foreach ($this->relInputsAndQueries as $inputAndQueries) {
			$relationshipName = $inputAndQueries['relationshipName'];
			$selectQuery = $inputAndQueries['selectQuery'];
			$tableName = 
			$return_string .= "
				\$sql = \"$selectQuery\";
				\$res = mysql_query(\$sql);
				\$relationships['$relationshipName'] = array();
				while (\$relRow = mysql_fetch_assoc(\$res)) {
					\$relationships['$relationshipName'][] = \$relRow;
				}
			";
		}
				
		$return_string .= "
print_header(\"{$this->project['project_name']} » " . $this->_titleize($this->table) . " » \$action\");

\$row = mysql_fetch_array ( mysql_query(\"SELECT * FROM `{$this->table}` WHERE `{$this->id_key}` = '\${$this->id_key}' \"));
?>\n";

$return_string .= $this->_build_form($this->columns, 'Add / Edit') . '
<?
print_footer();
?>';

		return $return_string;
	}

	function search_page() {
		$return_string = $this->_build_form($this->columns, 'Search', 'get', '_GET');
		$return_string .= "\n\n<?php\n";

		$return_string .= '$opts = array(';
		$cols = '';
		foreach($this->columns as $col) {
			$cols .= "'{$col['nombre']}_opts', ";
		}
		$return_string .= substr($cols, 0, -2) . ");\n"
. '/* Sorround "contains" search term between %% */
foreach ($opts as $o) {
	if (isset($_GET[$o]) && $_GET[$o] == \'like\') {
		$v = substr($o, 0, -5);
		$_GET[$v] = \'%\' . $_GET[$v] . \'%\';
	}
}'."\n\n";
		foreach($this->columns as $col) {
			$return_string .= "if (search_by('{$col['nombre']}'))
	\$conds .= \" AND {$col['nombre']} {\$_GET['{$col['nombre']}_opts']} '{\$_GET['{$col['nombre']}']}'\";\n";
		}

		return $return_string . "?>";
	}

	function _build_form($cols, $submit, $method = 'post', $value = 'row') {
		$is_search = ($submit == 'Search');

		$legend = $submit;
		if ($is_search)
			$legend = "<a href=\"#\" onclick=\"$('#search-form').slideToggle()\">$legend</a>";

		$res = '<form action="<?= $_SERVER[\'REQUEST_URI\'] ?>" method="'.$method.'">
<fieldset>
<legend>' . $legend . '</legend>
<div' . ($is_search ? ' id="search-form" style="display:none"' : '') . '>
<ul>
';
		foreach ($cols as $col)
			$res .= $this->_form_input($col, $value, $is_search);
		
		// print remaining inputs
		if (!$is_search)
			foreach ($this->relInputsAndQueries as $inputAndQueries)
				if (empty($inputAndQueries['done'])) {
					$res .= '  <li><label><span>' . $this->_titleize($inputAndQueries['relationshipName']) . ":</span>\n";
					$res .= $inputAndQueries['input'];
					$res .= '</label></li>';
				}

		$res .= '</ul>
<p><input type="hidden" value="1" name="submitted" />
  <input type="submit" value="'.$submit.'" /></p>
</div>
</fieldset>
</form>';
		return $res;
	}

	function _form_input($col, $value, $is_search = false) {
		if ($col['nombre'] != $this->id_key) {

		$text = '  <li><label><span>' . $this->_titleize($col['nombre']) . ":</span>\n";
		if ($is_search)
			$text .= "    <?= search_options('".$col['nombre']."', (isset(\$_GET['".$col['nombre']."_opts']) ? stripslashes(\$_GET['".$col['nombre']."_opts']) : '')) ?></label>\n";
		$text .= '    ';

		/* Takes value either from $_GET['id'] or from $row['id'] */
		$val = '$'.$value.'[\''.$col['nombre'].'\']';
		$isset_val = '(isset('.$val.') ? stripslashes('.$val.') : \'\')';
		$htmlentities_val = "htmlentities($isset_val, ENT_COMPAT | ENT_HTML401, 'UTF-8')";

		if ($col['tipo']['fk'])
			$text .= $this->oneToManyInputForColumn($col['nombre']);
		elseif ($col['tipo']['bool'])
			$text .= '<input type="hidden" name="'.$col['nombre'].'" value="0"/><input type="checkbox" name="'.$col['nombre'].'" value="1" <?= (isset('.$val.') && '.$val.' ? \'checked="checked"\' : \'\') ?> />';
		elseif ($col['tipo']['date'])
			$text .= '<?= input_date(\''.$col['nombre'].'\', ' . $isset_val . ') ?>';
		elseif ($col['tipo']['datetime'])
			$text .= '<?= input_datetime(\''.$col['nombre'].'\', ' . $isset_val . ') ?>';
		elseif ($col['tipo']['blob'])
			$text .= '<textarea name="'.$col['nombre'].'" cols="40" rows="10"><?= '.$htmlentities_val.' ?></textarea>';
		else
			$text .= '<input type="text" name="'.$col['nombre'].'" value="<?= '.$htmlentities_val.' ?>" />';

		if (!$is_search) $text .= '</label>'; /* Could be closed after search_options */
		return $text . "</li>\n";
		} /* If not id column */
	}

	/* Merge split form data into single (SQL) data */
	function _parse($field, $type) {
		if ($type['date']) {
			$day  = $field . '_day';
			$mth  = $field . '_mth';
			$year = $field . '_year';
			$val = "\$values['$year']-\$values['$mth']-\$values['$day']";
		} elseif ($type['datetime']) {
			$seg  = $field . '_seg';
			$min  = $field . '_min';
			$hour = $field . '_hour';
			$day  = $field . '_day';
			$mth  = $field . '_mth';
			$year = $field . '_year';
			$val = "\$values['$year'].'-'.\$values['$mth'].'-'.\$values['$day'].' '.\$values['$hour'].':'.\$values['$min'].':'.\$values['$seg']";
		} else {
			$val = "\$values['$field']";
		}
		return $val;
	}

	function _titleize($name) {
		return ucwords(str_replace('_', ' ', trim($name)));
	}
	
	function _relationship_inputs_and_queries () {
		
		$results = array();
		
		foreach ($this->project['relationships'] as $type => $relationships) {
			
			foreach ($relationships as $relationship) {
			
				$input = false;
				$selectQuery = false;
				$updateQueryFirst = false;
				$updateQuerySecond = false;
				$relationshipName = false;
			
				// vars common to all relationships
				$tableNameA = $relationship['A']['table'];
				$nameFieldA = $relationship['A']['nameField'];
				$pkA		= $relationship['A']['PK'];
				$tableNameB = $relationship['B']['table'];
				$nameFieldB = $relationship['B']['nameField'];
				$pkB		= $relationship['B']['PK'];
			
				$relationship['type'] = $type;
			
				if ($type == 'oneToMany') {
				
					// vars common to both sides of o2m relationships
					$fkB = $relationship['B']['FK'];
			
					// if the FK is from outside to us - we are A
					if ($relationship['A']['table'] == $this->table) {
				
						$relationshipName = $tableNameB;
				
						// render HTML input
						$input .= '<select multiple="multiple" name="oneToMany['.$tableNameB.'][]">'."\n";
						$input .= "<?php
							foreach (\$relationships['".$tableNameB."'] as \$relationshipRow) {
								\$stringSelected = \$relationshipRow['selected'] ? ' selected=\"selected\"' : '';
								echo '<option value=\"'.\$relationshipRow['key'].'\"'.\$stringSelected.'>';
								echo \$relationshipRow['name'];
								echo '</option>'.\"\\n\";
							}
						?>\n";
						$input .= '</select>'."\n";
					
						// build queries
					
						$selectQuery =
							"SELECT
								B.`$pkB` AS `key`,
								B.`$nameFieldB` AS `name`,
								A.`$pkA` AS `selected`
							FROM
								`$tableNameB` B
								LEFT JOIN `$tableNameA` A
									ON A.`$pkA` = B.`$fkB`
									AND A.`$pkA` = '\$actualPk'";
						
						$updateQueryFirst =
							"UPDATE `$tableNameB`
							SET `$fkB` = DEFAULT(`$fkB`)
							WHERE `$fkB` = '\$actualPk'";

						$updateQuerySecond =
							"UPDATE `$tableNameB`
							SET `$fkB` = '\$actualPk'
							WHERE `$pkB` IN \$postedRelationshipsForQuery";
				
					// if we have the FK to outside - we are B
					} elseif ($relationship['B']['table'] == $this->table) {
				
						$relationshipName = $tableNameA;
				
						// render HTML input
				
						$input .= '<select name="'.$fkB.'">'."\n";
						$input .= "<?php
							foreach (\$relationships['".$tableNameA."'] as \$relationshipRow) {
								\$stringSelected = \$relationshipRow['selected'] ? ' selected=\"selected\"' : '';
								echo '<option value=\"'.\$relationshipRow['key'].'\"'.\$stringSelected.'>';
								echo \$relationshipRow['name'];
								echo '</option>'.\"\\n\";
							}
						?>\n";
						$input .= '</select>'."\n";
					
						// build queries
					
						$selectQuery =
							"SELECT
								A.`$pkA` AS `key`,
								A.`$nameFieldA` AS `name`,
								B.`$pkB` AS `selected`
							FROM
								`$tableNameA` A
								LEFT JOIN `$tableNameB` B
									ON A.`$pkA` = B.`$fkB`
									AND B.`$pkB` = '\$actualPk'";
						
							$updateQueryFirst = false;
								// "UPDATE `$tableNameB`
								// SET `$fkB` = {\$postedRelationships['$tableNameA']['forQuery']}
								// WHERE `$pkB` = '\$actualPk'";

							$updateQuerySecond = false;
					}
				
				} elseif ($type == 'manyToMany') {
			
					// swap tables so we are table A
					if ($relationship['B']['table'] == $this->table) {
						$aux = $relationship['A'];
						$relationship['A'] = $relationship['B'];
						$relationship['B'] = $aux;
			
						$aux = $relationship['intermediate']['FKA'];
						$relationship['intermediate']['FKA'] = $relationship['intermediate']['FKB'];
						$relationship['intermediate']['FKB'] = $aux;
					}
		
					if ($relationship['A']['table'] == $this->table) {
						
						$relationshipName = $tableNameB;
						
						$intermediateTableName = $relationship['intermediate']['table'];
						$fkA = $relationship['intermediate']['FKA'];
						$fkB = $relationship['intermediate']['FKB'];
				
						// render HTML input
				
						$input .= '<select multiple="multiple" name="manyToMany['.$tableNameB.'][]">'."\n";
						$input .= "<?php
							foreach (\$relationships['".$tableNameB."'] as \$relationshipRow) {
								\$stringSelected = \$relationshipRow['selected'] ? ' selected=\"selected\"' : '';
								echo '<option value=\"'.\$relationshipRow['key'].'\"'.\$stringSelected.'>';
								echo \$relationshipRow['name'];
								echo '</option>'.\"\\n\";
							}
						?>\n";
						$input .= '</select>'."\n";
				
						// create queries - we are always table A
				
						$selectQuery =
							"SELECT
								B.`$pkB` AS `key`,
								B.`$nameFieldB` AS `name`,
								I.`$fkA` AS `selected`
							FROM
								`$tableNameB` B
								LEFT JOIN `$intermediateTableName` I
									ON I.`$fkB` = B.`$pkB`
									AND I.`$fkA` = '\$actualPk'";
				
						$updateQueryFirst =
							"DELETE FROM `$intermediateTableName`
							WHERE `$fkA` = '\$actualPk'";

						$updateQuerySecond =
							"INSERT INTO `$intermediateTableName` (`$fkA`, `$fkB`)
							VALUES \$postedRelationshipsForQuery";
					}
				}
		
				if (!empty($input)) {
			
					$results[] = array(
						'input' => $input,
						'selectQuery' => $selectQuery,
						'updateQueryFirst' => $updateQueryFirst,
						'updateQuerySecond' => $updateQuerySecond,
						'relationshipName' => $relationshipName,
						'originalData' => $relationship
					);
				}
			}
		}
		
		return $results;
	}
	                        
	function oneToManyOriginalDataForColumn ($column) {
		foreach ($this->relInputsAndQueries as $inputAndQueries) {
			
			if (!empty($inputAndQueries['originalData']['B']['FK']) &&
				$inputAndQueries['originalData']['B']['FK'] == $column) {
					
				return $inputAndQueries['originalData'];
			}
		}
	}
	
	function oneToManyInputForColumn ($column) {
		foreach ($this->relInputsAndQueries as &$inputAndQueries) {
			
			if (!empty($inputAndQueries['originalData']['B']['FK']) &&
				$inputAndQueries['originalData']['B']['FK'] == $column) {
					
				$inputAndQueries['done'] = true;
				return $inputAndQueries['input'];
			}
		}
	}
}
?>
