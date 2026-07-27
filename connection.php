<?php
class Database {
    private $sname = "localhost";
    private $unmae = "root";
    private $password = "";
    private $db_name = "cafe_db2";

    public $conn;

    public function __construct() {
        $this->conn = mysqli_connect($this->sname, $this->unmae, $this->password, $this->db_name, 3307);

        if (!$this->conn) {
            die("Connection failed: " . mysqli_connect_error());
        }
    }
}