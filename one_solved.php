<?php

abstract class Model
{
    abstract public function getCreatedAt(): string;
}

class User extends Model
{
    public function __construct(
        private string $username,
        private string $email,
        private string $role,
        private ?string $id = null,
        private ?string $createdAt = null,
    ) {
        if ($this->createdAt === null) {
            $this->createdAt = time();
        }
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }
}

class Post
{
    public function __construct(
        private string $text,
        private string $type,
        private ?string $createdAt = null,
        private ?string $id = null,
    ) {
        if ($this->createdAt = null) {
            $this->createdAt = time();
        }
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public  function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    // ...
}

interface PostRepositoryInterface
{
    public function createPost(Post $post): void;
}

class PostRepository implements PostRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private LoggerInterface $logger
    ) {
    }

    public function createPost(Post $post): void
    {
        $query = "INSERT INTO posts (text, created_at) VALUES (%s, %s)";
        $result = $this->connection->query(
            $query,
            [
                $post->getText(),
                $post->getCreatedAt()
            ]
        );

        if ($result) {
            $this->logger->log("Post has been saved to the database.");
        } else {
            $this->logger->log("Failed to save post to the database.");

            throw new LogicException('Saving error');
        }
    }
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

class PostService
{
    public function __construct(
        private PostRepositoryInterface $postRepository,
    )
    {
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
                'type' => $post->type,
                'created_at' => $post->created_at
            ]));
        }
    }
}
