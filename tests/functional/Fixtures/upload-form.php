<?php
if(isset($_POST['submit'])) {
	echo $_FILES['file1']['name']."\n";
	exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="">
<head></head>
<body>
	<form enctype="multipart/form-data" method="post">
		<input type="file" name="file1" />
		<input type="file" name="file2" />
		<input type="submit" name="submit" value="Submit" />
	</form>
</body>
</html>
