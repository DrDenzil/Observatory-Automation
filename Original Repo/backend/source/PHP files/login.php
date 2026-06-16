<?php
require("../constants.php");

echo "Redirecting to University of Hertfordshire login...";//<br><br>After login will redirect to $target";

$auth->requireAuth(array(
	'ReturnTo' => "https://observatory.herts.ac.uk/telescopes/",
));



?>
