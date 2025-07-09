<?php

echo "Current user: " . posix_getpwuid(posix_geteuid())['name'];
echo "Current group: " . posix_getgrgid(posix_getegid())['name'];

phpinfo();

?>