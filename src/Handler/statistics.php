<?php
class statistics extends Handler
{
	function __construct ( $type )
	{
		if ( ! module_hook($type,'statistics') ) {
			error_exit('Operation not found');
		}
		$this->type = $type;
	}

	function has_permission()
	{
		global $lr_session;
		return $lr_session->is_admin();
	}

	function process ()
	{
		$this->title = ucfirst($this->type) . ' Statistics';
		$this->setLocation(array($this->title => 0));
		return module_invoke($this->type, 'statistics');
	}
}

function person_statistics ( )
{
	global $dbh;
	$rows = array();

	$sth = $dbh->prepare('SELECT status, COUNT(*) FROM person GROUP BY status');
	$sth->execute();

	$sub_table = array();
	$sum = 0;
	while($row = $sth->fetch(PDO::FETCH_NUM)) {
		$sub_table[] = $row;
		$sum += $row[1];
	}
	$sub_table[] = array('Total', $sum);
	$rows[] = array('Players by account status:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT class, COUNT(*) FROM person GROUP BY class');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by account class:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT gender, COUNT(*) FROM person GROUP BY gender');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by gender:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT FLOOR((YEAR(NOW()) - YEAR(birthdate)) / 5) * 5 as age_bucket, COUNT(*) AS count FROM person GROUP BY age_bucket');
	$sth->execute();
	$sub_table = array();
	while($row = $sth->fetch(PDO::FETCH_ASSOC)) {
		$sub_table[] = array($row['age_bucket'] . ' to ' . ($row['age_bucket'] + 4), $row['count']);
	}
	$rows[] = array('Players by age:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT addr_city, COUNT(*) AS num FROM person GROUP BY addr_city HAVING num > 2 ORDER BY num DESC');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by city:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT skill_level, COUNT(*) FROM person GROUP BY skill_level');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by skill level:', table(null, $sub_table));

	$sth = $dbh->prepare('SELECT year_started, COUNT(*) FROM person GROUP BY year_started');
	$sth->execute();
	$sub_table = $sth->fetchAll(PDO::FETCH_NUM);
	$rows[] = array('Players by starting year:', table(null, $sub_table));

	if (variable_get('dog_questions', 1)) {
		$sth = $dbh->prepare("SELECT COUNT(*) FROM person where has_dog = 'Y'");
		$sth->execute();
		$rows[] = array('Players with dogs :', $sth->fetchColumn());
	}

	$output = "<div class='pairtable'>" . table(null, $rows) . "</div>";
	return form_group('Player Statistics', $output);
}


?>
