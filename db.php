<?php
// db.php - Simple Database Connection

$host = 'localhost:3307';
$user = 'root';
$pass = '';
$db   = 'ite1_portal';

// Establish connection using PDO
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);