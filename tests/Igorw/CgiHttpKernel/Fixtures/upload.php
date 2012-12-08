<?php

echo $_FILES['kitten']['name']."\n";
echo $_FILES['kitten']['type']."\n";
echo $_FILES['kitten']['size']."\n";
echo file_exists($_FILES['kitten']['tmp_name'])."\n";
