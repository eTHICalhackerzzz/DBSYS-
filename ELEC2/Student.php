<?php

    class Person {
        public $username;
        public $firstname;
        public $lastname;
        public $dob;

        public function __construct($username, $firstname, $lastname, $dob) {
            $this->username = $username;
            $this->firstname = $firstname;
            $this->lastname = $lastname;
            $this->dob = $dob;
        }
    }

    class Student extends Person {
        public $id;
        public $address;

        public function __construct($id, $username, $firstname, $lastname, $dob, $address) {
            parent::__construct($username, $firstname, $lastname, $dob);
            $this->id = $id;
            $this->address = $address;
        }
    }
?>
