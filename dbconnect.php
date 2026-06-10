<?php

// Database connection parameters
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "heartside_vet";   

// Create connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}