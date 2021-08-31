<?php

$output = shell_exec('.\..\vendor\bin\phpunit --testdox .');
echo $output;
