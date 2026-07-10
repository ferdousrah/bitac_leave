<?php
    class Students{

        // Connection
        private $conn;

        // Table
        private $db_table = "students";

        // Columns
        public $dataID;
        public $full_name;
        public $father_name;
        public $mother_name;
        public $dob;
        public $gender;
		public $nationality;
		public $academic_qualification;
		public $academic_institute;
		public $present_address;
		public $guardians_cell;
		public $residence_tele;
		public $students_cell;
		public $passport_no;
		public $study_abroad;
		public $email;
		public $photo;

        // Db connection
        public function __construct($db){
            $this->conn = $db;
        }

        // GET ALL
        public function getStudents(){
            $sqlQuery = "SELECT * FROM " . $this->db_table . "";
            $stmt = $this->conn->prepare($sqlQuery);
            $stmt->execute();
            return $stmt;
        }

        

        // UPDATE
        public function getSingleStudent(){
            $sqlQuery = "SELECT
                        full_name
                      FROM
                        ". $this->db_table ."
                    WHERE 
                       students_cell = ?
                    LIMIT 0,1";

            $stmt = $this->conn->prepare($sqlQuery);

            $stmt->bindParam(1, $this->students_cell);

            $stmt->execute();

            $dataRow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->full_name = $dataRow['full_name'];
            
        }        

        

    }
?>

