#!/bin/sh
php ./mpaseco.php SM </dev/null >mpaseco.log 2>&1 &
echo $!
