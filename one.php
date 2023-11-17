<?php

class HttpException extends \Exception {}

abstract class Model
{
    abstract public function getCreatedAt(): string;
}

class User extends Model {
    private $db;

    public $id;
    public $username;
    public $email;
    public $role;

    public function __construct($db) {
        $this->db = $db;
    }

    public function saveToDatabase() {
        $query = "INSERT INTO users (id, username, email, role) VALUES (" . $this->id . ", " . $this->username . ", " . $this->email . ", " . $this->role . ")";
        $result = $this->db->query($query);

        if ($result) {
            $this->log("User with ID {$this->id} has been saved to the database.");
            return true;
        } else {
            $this->log("Failed to save user with ID {$this->id} to the database.");
            return false;
        }
    }

    public function getCreatedAt(): string
    {
        throw new \Exception('Not supported');
    }

    private function log($message) {
        file_put_contents('log.txt', $message . PHP_EOL, FILE_APPEND);
    }
}

class Post
{
    private $db;

    public $id;
    public $text;
    public $created_at;

    public function __construct($db) {
        $this->db = $db;
    }

    public function searchForPostsInDbByLimitAndOffset($limit, $offset): ?array
    {
        $query = "SELECT * FROM posts LIMIT $limit OFFSET $offset";
        $result = $this->db->query($query);

        if (!$result->getResult()) {
            return null;
        }

        return $result->getResult();
    }

    public  function getCreatedAt(): string
    {
        return $this->created_at;
    }
}

class GetPostsController
{
    public function __invoke()
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->role !== 10) {
            echo json_encode(['message' => 'no access']);
            exit;
        }

        $limit = $_GET['limit'];
        $offset = $_GET['offset'];
        if ($offset < 0) {
            throw new \Exception();
        }

        $db = new mysqli('localhost', 'username', 'password', 'database');
        $post = new Post($db);

        return json_encode($post->searchForPostsInDbByLimitAndOffset($limit, $offset));
    }
}
