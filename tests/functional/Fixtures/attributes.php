<?php

$attributes = unserialize($_SERVER['SYMFONY_ATTRIBUTES']);
echo json_encode($attributes);
