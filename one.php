<?php

abstract class Model
{
    abstract public function getCreatedAt(): string;
}

class User extends Model
{
    private $db;

    public $id;
    public $username;
    public $email;
    public $role;

    public function __construct() {
        $this->db = new mysqli('localhost', 'username', 'password', 'database');
    }

    public function getCreatedAt(): string
    {
        throw new \Exception('Not supported');
    }

    public function findById($id): User {
        $query = "SELECT * FROM users where id = " . $id;
        $result = $this->db->query($query)->fetch_row();
        $user = new User();
        $user->role = $result['role'];
        $user->id = $result['id'];
        $user->username = $result['username'];
        $user->email = $result['email'];

        return $user;
    }

    // ...
}

class Post extends Model
{
    private $db;

    public $id;
    public $text;
    public $type;
    public $created_at;

    public function __construct()
    {
        $this->db = new mysqli('localhost', 'username', 'password', 'database');
    }

    public function savePost()
    {
        $query = "INSERT INTO posts (text, created_at) VALUES (" . $this->text . ", " . $this->created_at . ")";
        $result = $this->db->query($query);

        if ($result) {
            $this->log("Post has been saved to the database.");
            return true;
        } else {
            $this->log("Failed to save post to the database.");
            http_response_code(500);
            exit;
        }
    }

    private function log($message) {
        file_put_contents('log.txt', $message . PHP_EOL, FILE_APPEND);
    }

    public  function getCreatedAt(): string
    {
        return $this->created_at;
    }

    // ...
}

/**
 * @method User getUser
 */
class CreatePostController
{
    public function __invoke()
    {
        $user = $this->getUser();
        if ($user->role !== 10) {
            echo json_encode(['message' => 'no access']);
            exit;
        }

        $post = new Post();
        $post->created_at = time();
        $post->text = $_POST['text'];
        $post->type = $_POST['type'];

        if ($post->savePost()) {
            $redisService = new RedisService();
            $redisService->addToEmailQueue($post);

            http_response_code(200);
            exit;
        }
    }
}

class RedisService
{
    public $redisQueue;

    public function __construct()
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1');

        $this->redisQueue = new RSMQClient($redis);
    }

    public function addToEmailQueue(Post $post)
    {
        if ($post->type === 'blog_post') {
            $this->redisQueue->sendMessage('blog_email_queue', json_encode([
                'text' => $post->text,
                'type' => $post->type,
                'created_at' => $post->created_at
            ]));
        } elseif ($post->type === 'personal_post') {
            $this->redisQueue->sendMessage('personal_email_queue', json_encode([
                'text' => $post->text,
                'created_at' => $post->created_at
            ]));
        }
    }
}
