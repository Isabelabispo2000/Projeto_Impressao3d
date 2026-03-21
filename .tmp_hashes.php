<?php
foreach (['apolinario1001','gustavo1002','stefani1003','santos1004','oliveira2001','silva2002'] as $password) {
    echo $password, ' => ', password_hash($password, PASSWORD_DEFAULT), PHP_EOL;
}
