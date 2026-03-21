<?php
foreach (['1001','1002','1003','1004','2001','2002'] as $password) {
    echo $password, ' => ', password_hash($password, PASSWORD_DEFAULT), PHP_EOL;
}
