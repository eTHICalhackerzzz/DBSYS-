<?php
require_once "Model.php";

class Students extends Model {
    private $table = "students";

    public function getAllStudents() {
        return $this->read($this->table);
    }

    public function addStudent($data) {
        return $this->create($this->table, $data);
    }

    public function updateStudent($id, $data) {
        return $this->update($this->table, $data, $id);
    }
    
    public function deleteStudent($id) {
        return $this->delete($this->table, $id);
    }

    public function getAll() {
        return $this->readAll($this->table);
    }

    public function authenticate($username, $password) {
        $results = $this->read($this->table, ['username' => $username]);
        if ($results && password_verify($password, $results[0]['password'])) {
            return $results[0]; // Logged in
        }
        return false;
    }
}
?>
