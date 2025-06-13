<?php
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "Current directory: " . __DIR__;
?>