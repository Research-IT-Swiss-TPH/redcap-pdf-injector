#!/usr/bin/php -q
<?php
$tests = shell_exec('.\vendor\bin\phpunit tests');
echo $tests
?>
